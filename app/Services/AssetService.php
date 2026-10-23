<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetAudit;
use App\Models\AssetAuditItem;
use App\Models\AssetCategory;
use App\Models\AssetMaintenance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Fixed assets (PLAN.md §15): categories, locations, the register, who holds what, repairs and physical
 * verification. Ledger postings (purchase, repair cost, depreciation, disposal) are in AssetLedgerService.
 */
class AssetService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private AssetLedgerService $ledgerService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Categories ---------- */

    /**
     * @return array<string, mixed>
     */
    public function categoriesDatatable(): array
    {
        $query = AssetCategory::query()
            ->select(['id', 'name', 'code', 'parent_id', 'asset_account_id', 'depreciation_account_id', 'accumulated_depreciation_account_id', 'depreciation_method', 'useful_life_months', 'salvage_percent', 'is_active', 'created_at'])
            ->with(['parent:id,name', 'assetAccount:id,code,name'])
            ->withCount('assets');

        return DataTables::eloquent($query)
            ->addColumn('parent_name', fn (AssetCategory $category): ?string => $category->parent?->name)
            ->addColumn('asset_account_name', fn (AssetCategory $category): ?string => $category->assetAccount ? $category->assetAccount->code.' '.$category->assetAccount->name : null)
            ->removeColumn('parent', 'asset_account')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCategory(?AssetCategory $category, array $data): AssetCategory
    {
        return DB::transaction(function () use ($category, $data): AssetCategory {
            $category ??= new AssetCategory(['is_active' => true]);
            $category->fill([...$data, 'salvage_percent' => $data['salvage_percent'] ?? 0])->save();

            return $category;
        });
    }

    public function deleteCategory(AssetCategory $category): void
    {
        if ($category->assets()->exists() || AssetCategory::where('parent_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => ['This category still has assets or sub-categories. Move them first.']]);
        }

        $category->delete();
    }

    /* ---------- Locations ---------- */

    /**
     * @return array<string, mixed>
     */
    public function locationsDatatable(): array
    {
        $query = Location::query()
            ->select(['id', 'name', 'code', 'building', 'floor', 'parent_id', 'in_charge_employee_id', 'is_store', 'is_active', 'created_at'])
            ->with(['parent:id,name', 'inCharge:id,first_name,middle_name,last_name'])
            ->withCount(['assets' => fn (Builder $query) => $query->where('status', '!=', 'disposed')]);

        return DataTables::eloquent($query)
            ->addColumn('parent_name', fn (Location $location): ?string => $location->parent?->name)
            ->addColumn('in_charge_name', fn (Location $location): ?string => $location->inCharge?->fullName())
            ->filterColumn('in_charge_name', fn (Builder $query, string $keyword) => $query->whereHas('inCharge', fn (Builder $inner) => $inner->where(fn (Builder $name) => $name->where('first_name', 'like', '%'.$keyword.'%')->orWhere('last_name', 'like', '%'.$keyword.'%'))))
            ->removeColumn('parent', 'in_charge')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveLocation(?Location $location, array $data): Location
    {
        return DB::transaction(function () use ($location, $data): Location {
            $location ??= new Location(['is_active' => true]);
            $location->fill($data)->save();

            return $location;
        });
    }

    public function deleteLocation(Location $location): void
    {
        if ($location->assets()->where('status', '!=', 'disposed')->exists() || Location::where('parent_id', $location->id)->exists()) {
            throw ValidationException::withMessages(['location' => ['Assets or sub-locations are still in this location. Move them first.']]);
        }

        $location->delete();
    }

    /* ---------- Register ---------- */

    /**
     * @param  array{status?: string|null, asset_category_id?: int|null, location_id?: int|null, holder_type?: string|null, holder_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        $query = Asset::query()
            ->select(['id', 'asset_tag', 'asset_category_id', 'name', 'brand', 'model', 'serial_no', 'purchase_date', 'purchase_cost', 'current_book_value', 'location_id', 'condition_id', 'status', 'current_holder_type', 'current_holder_id', 'warranty_expires_on', 'created_at'])
            ->with(['category:id,name', 'location:id,name', 'condition:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['asset_category_id'] ?? null, fn (Builder $query, int $id) => $query->where('asset_category_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->when(($filters['holder_type'] ?? null) && ($filters['holder_id'] ?? null), fn (Builder $query) => $query->where('current_holder_type', $filters['holder_type'])->where('current_holder_id', $filters['holder_id']));

        return DataTables::eloquent($query)
            ->addColumn('category_name', fn (Asset $asset): ?string => $asset->category?->name)
            ->addColumn('location_name', fn (Asset $asset): ?string => $asset->location?->name)
            ->addColumn('condition_name', fn (Asset $asset): ?string => $asset->condition?->name)
            ->addColumn('holder_name', fn (Asset $asset): ?string => Asset::holderName($asset->current_holder_type, $asset->current_holder_id))
            ->editColumn('purchase_date', fn (Asset $asset): ?string => $asset->purchase_date?->toDateString())
            ->editColumn('warranty_expires_on', fn (Asset $asset): ?string => $asset->warranty_expires_on?->toDateString())
            ->removeColumn('category', 'location', 'condition')
            ->toArray();
    }

    /**
     * One asset with its history: hand-overs, repairs and depreciation.
     *
     * @return array<string, mixed>
     */
    public function details(Asset $asset): array
    {
        $asset->load(['category:id,name,depreciation_method,useful_life_months', 'vendor:id,name', 'location:id,name', 'condition:id,name']);

        return [
            ...$asset->attributesToArray(),
            'purchase_date' => $asset->purchase_date?->toDateString(),
            'warranty_expires_on' => $asset->warranty_expires_on?->toDateString(),
            'depreciation_start_date' => $asset->depreciation_start_date?->toDateString(),
            'category_name' => $asset->category?->name,
            'vendor_name' => $asset->vendor?->name,
            'location_name' => $asset->location?->name,
            'condition_name' => $asset->condition?->name,
            'holder_name' => Asset::holderName($asset->current_holder_type, $asset->current_holder_id),
            'assignments' => $asset->assignments()->with(['conditionOut:id,name', 'conditionIn:id,name', 'issuer:id,name'])->latest('assigned_on')->latest('id')->limit(50)->get()
                ->map(fn (AssetAssignment $row): array => [
                    'id' => $row->id,
                    'holder' => Asset::holderName($row->assignee_type, $row->assignee_id),
                    'assignee_type' => $row->assignee_type,
                    'assigned_on' => $row->assigned_on?->toDateString(),
                    'expected_return_on' => $row->expected_return_on?->toDateString(),
                    'returned_on' => $row->returned_on?->toDateString(),
                    'condition_out' => $row->conditionOut?->name,
                    'condition_in' => $row->conditionIn?->name,
                    'issued_by' => $row->issuer?->name,
                    'remarks' => $row->remarks,
                ])->all(),
            'maintenances' => $asset->maintenances()->with(['type:id,name', 'vendor:id,name'])->latest('reported_on')->limit(50)->get()
                ->map(fn (AssetMaintenance $row): array => [
                    'id' => $row->id, 'type' => $row->type?->name, 'vendor' => $row->vendor?->name, 'status' => $row->status,
                    'reported_on' => $row->reported_on?->toDateString(), 'completed_on' => $row->completed_on?->toDateString(), 'cost' => $row->cost, 'description' => $row->description,
                ])->all(),
            'depreciations' => $asset->depreciations()->latest('period_month')->limit(60)->get(['period_month', 'amount', 'book_value_after'])
                ->map(fn ($row): array => ['month' => $row->period_month?->format('M Y'), 'amount' => $row->amount, 'book_value_after' => $row->book_value_after])->all(),
        ];
    }

    /**
     * Register an asset. The tag comes from the numbering setup when left blank; book value starts at cost and
     * salvage follows the category. With post_purchase the cost is posted (asset account ← vendor payable / payment method).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Asset
    {
        return DB::transaction(function () use ($data): Asset {
            $category = AssetCategory::findOrFail($data['asset_category_id']);
            $cost = (int) ($data['purchase_cost'] ?? 0);
            $asset = Asset::create([
                ...collect($data)->except(['post_purchase', 'payment_method_id'])->all(),
                'purchase_cost' => $cost,
                'asset_tag' => filled($data['asset_tag'] ?? null) ? $data['asset_tag'] : $this->numberSequenceService->next('asset_tag'),
                'salvage_value' => (int) round($cost * (float) $category->salvage_percent / 100),
                'current_book_value' => $cost,
                'depreciation_start_date' => $data['depreciation_start_date'] ?? (filled($data['purchase_date'] ?? null) ? substr((string) $data['purchase_date'], 0, 8).'01' : now()->startOfMonth()->toDateString()),
                'status' => 'in_store',
            ]);

            if (! empty($data['post_purchase']) && $cost > 0) {
                $this->ledgerService->postPurchase($asset, isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null);
            }

            return $asset;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Asset $asset, array $data): Asset
    {
        return DB::transaction(function () use ($asset, $data): Asset {
            $data = [...$data, ...(array_key_exists('purchase_cost', $data) ? ['purchase_cost' => (int) $data['purchase_cost']] : [])];
            $costChanged = array_key_exists('purchase_cost', $data) && $data['purchase_cost'] !== $asset->purchase_cost;

            if ($costChanged && ($asset->journal_entry_id !== null || $asset->depreciations()->exists())) {
                throw ValidationException::withMessages(['purchase_cost' => ['The cost is already in the ledger or depreciated. Correct it with a journal voucher instead.']]);
            }

            $asset->fill(collect($data)->except(['post_purchase', 'payment_method_id', 'status', 'asset_tag'])->all());

            if ($costChanged) {
                $category = AssetCategory::findOrFail($asset->asset_category_id);
                $asset->current_book_value = $asset->purchase_cost;
                $asset->salvage_value = (int) round($asset->purchase_cost * (float) $category->salvage_percent / 100);
            }

            $asset->save();

            return $asset;
        });
    }

    /**
     * Only a mistaken entry can be deleted: once handed out, depreciated or posted, an asset is disposed instead.
     */
    public function delete(Asset $asset): void
    {
        if ($asset->journal_entry_id !== null || $asset->assignments()->exists() || $asset->depreciations()->exists()) {
            throw ValidationException::withMessages(['asset' => ['This asset has history (hand-overs, ledger or depreciation). Dispose of it instead of deleting.']]);
        }

        $asset->delete();
    }

    /* ---------- Who holds what ---------- */

    /**
     * People, departments and rooms an asset can be handed to.
     *
     * @return array<int, array{id: int, name: string, detail: string|null}>
     */
    public function holders(string $type, string $term): array
    {
        $like = '%'.$term.'%';

        return match ($type) {
            'employee' => Employee::whereIn('status', Employee::CURRENT_STATUSES)->where(fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->orWhere('employee_code', 'like', $like))
                ->limit(20)->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_code'])->map(fn (Employee $row): array => ['id' => $row->id, 'name' => $row->fullName(), 'detail' => $row->employee_code])->all(),
            'student' => Student::whereNotIn('current_status', Student::LEAVING_STATUSES)->where(fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->orWhere('admission_no', 'like', $like))
                ->limit(20)->get(['id', 'first_name', 'middle_name', 'last_name', 'admission_no'])->map(fn (Student $row): array => ['id' => $row->id, 'name' => $row->fullName(), 'detail' => $row->admission_no])->all(),
            'department' => Department::where('name', 'like', $like)->limit(20)->get(['id', 'name'])->map(fn (Department $row): array => ['id' => $row->id, 'name' => $row->name, 'detail' => null])->all(),
            'location' => Location::where('is_active', true)->where('name', 'like', $like)->limit(20)->get(['id', 'name', 'building'])->map(fn (Location $row): array => ['id' => $row->id, 'name' => $row->name, 'detail' => $row->building])->all(),
            default => [],
        };
    }

    /**
     * Hand an asset in store to an employee, student, department or room.
     *
     * @param  array{assignee_type: string, assignee_id: int, assigned_on: string, expected_return_on?: string|null, condition_out_id?: int|null, acknowledgement_signed?: bool, remarks?: string|null}  $data
     */
    public function assign(Asset $asset, array $data, User $user): Asset
    {
        if ($asset->status !== 'in_store') {
            throw ValidationException::withMessages(['asset' => ['Only an asset in store can be handed out. Take it back first.']]);
        }

        $class = Asset::HOLDERS[$data['assignee_type']];
        $holder = $class::findOrFail($data['assignee_id']);

        DB::transaction(function () use ($asset, $data, $user): void {
            $asset->assignments()->create([...$data, 'issued_by' => $user->id]);
            $asset->update([
                'status' => 'assigned',
                'current_holder_type' => $data['assignee_type'],
                'current_holder_id' => $data['assignee_id'],
                'condition_id' => $data['condition_out_id'] ?? $asset->condition_id,
                'location_id' => $data['assignee_type'] === 'location' ? $data['assignee_id'] : $asset->location_id,
            ]);
            $this->activityLogger->record('assets', 'assigned', "{$user->name} handed {$asset->asset_tag} {$asset->name} to ".Asset::holderName($data['assignee_type'], $data['assignee_id']), $asset);
        });

        if ($holder instanceof Employee && $holder->user_id !== null) {
            $this->notificationService->notify([$holder->user_id], 'asset_assigned', "{$asset->name} ({$asset->asset_tag}) was handed to you", 'You can see the assets you hold in My HR.', route('my-hr'), $asset);
        }

        return $asset->refresh();
    }

    /**
     * Take an asset back into a store or room.
     *
     * @param  array{returned_on: string, condition_in_id?: int|null, location_id?: int|null, remarks?: string|null}  $data
     */
    public function takeBack(Asset $asset, array $data, User $user): Asset
    {
        $open = $asset->assignments()->whereNull('returned_on')->latest('id')->first();

        if ($asset->status !== 'assigned' || $open === null) {
            throw ValidationException::withMessages(['asset' => ['This asset is not handed out.']]);
        }

        return DB::transaction(function () use ($asset, $open, $data, $user): Asset {
            $open->update([
                'returned_on' => $data['returned_on'],
                'condition_in_id' => $data['condition_in_id'] ?? null,
                'received_by' => $user->id,
                'remarks' => trim(($open->remarks ? $open->remarks."\n" : '').($data['remarks'] ?? '')) ?: null,
            ]);
            $holder = Asset::holderName($open->assignee_type, $open->assignee_id);
            $asset->update([
                'status' => 'in_store',
                'current_holder_type' => null,
                'current_holder_id' => null,
                'condition_id' => $data['condition_in_id'] ?? $asset->condition_id,
                'location_id' => $data['location_id'] ?? $asset->location_id,
            ]);
            $this->activityLogger->record('assets', 'returned', "{$user->name} took {$asset->asset_tag} {$asset->name} back from {$holder}", $asset);

            return $asset->refresh();
        });
    }

    /**
     * @param  array{assignee_type?: string|null, open?: bool|null}  $filters
     * @return array<string, mixed>
     */
    public function assignmentsDatatable(array $filters): array
    {
        $query = AssetAssignment::query()
            ->select(['id', 'asset_id', 'assignee_type', 'assignee_id', 'assigned_on', 'expected_return_on', 'returned_on', 'remarks', 'created_at'])
            ->with('asset:id,asset_tag,name')
            ->when($filters['assignee_type'] ?? null, fn (Builder $query, string $type) => $query->where('assignee_type', $type))
            ->when(($filters['open'] ?? null) === true, fn (Builder $query) => $query->whereNull('returned_on'))
            ->when(($filters['open'] ?? null) === false, fn (Builder $query) => $query->whereNotNull('returned_on'));

        return DataTables::eloquent($query)
            ->addColumn('asset_label', fn (AssetAssignment $row): string => $row->asset ? $row->asset->asset_tag.' '.$row->asset->name : '—')
            ->addColumn('holder_name', fn (AssetAssignment $row): ?string => Asset::holderName($row->assignee_type, $row->assignee_id))
            ->addColumn('is_overdue', fn (AssetAssignment $row): bool => $row->returned_on === null && $row->expected_return_on !== null && $row->expected_return_on->isPast())
            ->filterColumn('asset_label', fn (Builder $query, string $keyword) => $query->whereHas('asset', fn (Builder $inner) => $inner->where('asset_tag', 'like', "%{$keyword}%")->orWhere('name', 'like', "%{$keyword}%")))
            ->editColumn('assigned_on', fn (AssetAssignment $row): ?string => $row->assigned_on?->toDateString())
            ->editColumn('expected_return_on', fn (AssetAssignment $row): ?string => $row->expected_return_on?->toDateString())
            ->editColumn('returned_on', fn (AssetAssignment $row): ?string => $row->returned_on?->toDateString())
            ->removeColumn('asset')
            ->toArray();
    }

    /**
     * Assets an employee holds now (My HR and exit clearance).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function heldBy(Employee $employee): Collection
    {
        return Asset::where('current_holder_type', 'employee')->where('current_holder_id', $employee->id)->where('status', 'assigned')
            ->with('category:id,name')->orderBy('asset_tag')->get(['id', 'asset_tag', 'name', 'serial_no', 'asset_category_id'])
            ->map(function (Asset $asset): array {
                $open = $asset->assignments()->whereNull('returned_on')->latest('id')->first(['assigned_on', 'expected_return_on']);

                return [
                    'asset_tag' => $asset->asset_tag, 'name' => $asset->name, 'serial_no' => $asset->serial_no, 'category' => $asset->category?->name,
                    'since' => $open?->assigned_on?->toDateString(), 'expected_return_on' => $open?->expected_return_on?->toDateString(),
                ];
            });
    }

    /* ---------- Repairs ---------- */

    /**
     * @return array<string, mixed>
     */
    public function maintenancesDatatable(?string $status): array
    {
        $query = AssetMaintenance::query()
            ->select(['id', 'asset_id', 'maintenance_type_id', 'vendor_id', 'reported_on', 'sent_on', 'completed_on', 'cost', 'description', 'status', 'journal_entry_id', 'created_at'])
            ->with(['asset:id,asset_tag,name', 'type:id,name', 'vendor:id,name', 'journalEntry:id,voucher_no'])
            ->when($status, fn (Builder $query, string $value) => $query->where('status', $value));

        return DataTables::eloquent($query)
            ->addColumn('asset_label', fn (AssetMaintenance $row): string => $row->asset ? $row->asset->asset_tag.' '.$row->asset->name : '—')
            ->addColumn('type_name', fn (AssetMaintenance $row): ?string => $row->type?->name)
            ->addColumn('vendor_name', fn (AssetMaintenance $row): ?string => $row->vendor?->name)
            ->addColumn('voucher_no', fn (AssetMaintenance $row): ?string => $row->journalEntry?->voucher_no)
            ->filterColumn('asset_label', fn (Builder $query, string $keyword) => $query->whereHas('asset', fn (Builder $inner) => $inner->where('asset_tag', 'like', "%{$keyword}%")->orWhere('name', 'like', "%{$keyword}%")))
            ->editColumn('reported_on', fn (AssetMaintenance $row): ?string => $row->reported_on?->toDateString())
            ->editColumn('completed_on', fn (AssetMaintenance $row): ?string => $row->completed_on?->toDateString())
            ->removeColumn('asset', 'type', 'vendor', 'journal_entry')
            ->toArray();
    }

    /**
     * Report a repair: the asset is in repair until the job is completed.
     *
     * @param  array<string, mixed>  $data
     */
    public function reportRepair(Asset $asset, array $data): AssetMaintenance
    {
        if (in_array($asset->status, ['disposed', 'lost', 'in_repair'], true)) {
            throw ValidationException::withMessages(['asset' => ['This asset is already in repair, lost or disposed.']]);
        }

        return DB::transaction(function () use ($asset, $data): AssetMaintenance {
            $job = $asset->maintenances()->create([...$data, 'cost' => (int) ($data['cost'] ?? 0), 'status' => filled($data['sent_on'] ?? null) ? 'in_progress' : 'reported', 'previous_status' => $asset->status]);
            $asset->update(['status' => 'in_repair']);

            return $job;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRepair(AssetMaintenance $job, array $data): AssetMaintenance
    {
        if ($job->status === 'completed') {
            throw ValidationException::withMessages(['maintenance' => ['A completed job cannot be changed.']]);
        }

        $job->update([...$data, 'cost' => (int) ($data['cost'] ?? $job->cost), 'status' => filled($data['sent_on'] ?? $job->sent_on) ? 'in_progress' : 'reported']);

        return $job;
    }

    /**
     * Close a repair: the asset goes back to its earlier status and the cost (if any) is posted as a repair expense.
     *
     * @param  array{completed_on: string, cost?: int|null, vendor_id?: int|null, payment_method_id?: int|null, condition_id?: int|null, description?: string|null}  $data
     */
    public function completeRepair(AssetMaintenance $job, array $data, User $user): AssetMaintenance
    {
        if ($job->status === 'completed') {
            throw ValidationException::withMessages(['maintenance' => ['This job is already completed.']]);
        }

        return DB::transaction(function () use ($job, $data, $user): AssetMaintenance {
            $job->update([
                'completed_on' => $data['completed_on'],
                'cost' => $data['cost'] ?? $job->cost,
                'vendor_id' => $data['vendor_id'] ?? $job->vendor_id,
                'description' => $data['description'] ?? $job->description,
                'status' => 'completed',
            ]);
            $asset = Asset::findOrFail($job->asset_id);
            $asset->update(['status' => $job->previous_status ?: 'in_store', 'condition_id' => $data['condition_id'] ?? $asset->condition_id]);

            if ($job->cost > 0) {
                $this->ledgerService->postRepair($job, isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null);
            }

            $this->activityLogger->record('assets', 'repaired', "{$user->name} closed the repair of {$asset->asset_tag} {$asset->name}", $job);

            return $job->refresh();
        });
    }

    /* ---------- Physical verification ---------- */

    /**
     * @return array<string, mixed>
     */
    public function auditsDatatable(): array
    {
        $query = AssetAudit::query()
            ->select(['id', 'audit_date', 'location_id', 'conducted_by', 'status', 'remarks', 'completed_at', 'created_at'])
            ->with(['location:id,name', 'conductor:id,name'])
            ->withCount(['items', 'items as found_count' => fn (Builder $query) => $query->where('found', true), 'items as missing_count' => fn (Builder $query) => $query->where('found', false)]);

        return DataTables::eloquent($query)
            ->addColumn('location_name', fn (AssetAudit $audit): string => $audit->location?->name ?? 'All locations')
            ->addColumn('conductor_name', fn (AssetAudit $audit): ?string => $audit->conductor?->name)
            ->editColumn('audit_date', fn (AssetAudit $audit): ?string => $audit->audit_date?->toDateString())
            ->removeColumn('location', 'conductor')
            ->toArray();
    }

    /**
     * Start a verification with one line per asset expected in the location (or everywhere).
     *
     * @param  array{audit_date: string, location_id?: int|null, remarks?: string|null}  $data
     */
    public function startAudit(array $data, User $user): AssetAudit
    {
        return DB::transaction(function () use ($data, $user): AssetAudit {
            $audit = AssetAudit::create([...$data, 'conducted_by' => $user->id, 'status' => 'draft']);
            $assets = Asset::whereNotIn('status', ['disposed', 'lost'])
                ->when($data['location_id'] ?? null, fn (Builder $query, int $id) => $query->where('location_id', $id))
                ->pluck('id');

            foreach ($assets->chunk(500) as $chunk) {
                AssetAuditItem::insert($chunk->map(fn (int $id): array => ['asset_audit_id' => $audit->id, 'asset_id' => $id, 'created_at' => now(), 'updated_at' => now()])->all());
            }

            return $audit;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function audit(AssetAudit $audit): array
    {
        $audit->load(['location:id,name', 'conductor:id,name']);

        return [
            ...$audit->attributesToArray(),
            'audit_date' => $audit->audit_date?->toDateString(),
            'location_name' => $audit->location?->name ?? 'All locations',
            'conductor_name' => $audit->conductor?->name,
            'items' => $audit->items()->with(['asset:id,asset_tag,name,location_id,current_holder_type,current_holder_id', 'asset.location:id,name'])->get()
                ->map(fn (AssetAuditItem $item): array => [
                    'id' => $item->id, 'found' => $item->found, 'condition_id' => $item->condition_id, 'remarks' => $item->remarks,
                    'asset_tag' => $item->asset?->asset_tag, 'asset_name' => $item->asset?->name, 'location' => $item->asset?->location?->name,
                    'holder' => Asset::holderName($item->asset?->current_holder_type, $item->asset?->current_holder_id),
                ])->all(),
        ];
    }

    /**
     * Save ticks; with complete=true the verification is closed and, if asked, missing assets are marked lost.
     *
     * @param  array<int, array{id: int, found?: bool|null, condition_id?: int|null, remarks?: string|null}>  $items
     */
    public function saveAudit(AssetAudit $audit, array $items, bool $complete, bool $markMissingLost, User $user): AssetAudit
    {
        if ($audit->status === 'completed') {
            throw ValidationException::withMessages(['audit' => ['This verification is already completed.']]);
        }

        return DB::transaction(function () use ($audit, $items, $complete, $markMissingLost, $user): AssetAudit {
            foreach ($items as $row) {
                AssetAuditItem::where('asset_audit_id', $audit->id)->whereKey($row['id'])
                    ->update(collect($row)->only(['found', 'condition_id', 'remarks'])->all());
            }

            if ($complete) {
                $audit->update(['status' => 'completed', 'completed_at' => now()]);

                foreach (AssetAuditItem::where('asset_audit_id', $audit->id)->whereNotNull('condition_id')->get(['asset_id', 'condition_id']) as $item) {
                    Asset::whereKey($item->asset_id)->update(['condition_id' => $item->condition_id]);
                }

                if ($markMissingLost) {
                    Asset::whereIn('id', AssetAuditItem::where('asset_audit_id', $audit->id)->where('found', false)->select('asset_id'))->get()
                        ->each(fn (Asset $asset) => $asset->update(['status' => 'lost']));
                }

                $this->activityLogger->record('assets', 'verified', "{$user->name} completed the asset verification of {$audit->audit_date->toDateString()}", $audit);
            }

            return $audit->refresh();
        });
    }
}
