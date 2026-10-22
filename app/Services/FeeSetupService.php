<?php

namespace App\Services;

use App\Models\ConcessionType;
use App\Models\FeeHead;
use App\Models\FeeInvoiceItem;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\StudentConcession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Fee heads, concession types, class fee structures and student concessions.
 */
class FeeSetupService
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /* ---------- Fee heads ---------- */

    public function headsQuery(): Builder
    {
        return FeeHead::query()
            ->select(['id', 'name', 'code', 'income_account_id', 'frequency_id', 'is_refundable', 'is_concession_allowed', 'sort_order', 'is_active', 'created_at'])
            ->with(['incomeAccount:id,code,name', 'frequency:id,name']);
    }

    /**
     * @return array<string, mixed>
     */
    public function headsDatatable(): array
    {
        return DataTables::eloquent($this->headsQuery())
            ->escapeColumns([])
            ->addColumn('income_account_label', fn (FeeHead $head): ?string => $head->incomeAccount ? $head->incomeAccount->code.' '.$head->incomeAccount->name : null)
            ->addColumn('frequency_name', fn (FeeHead $head): ?string => $head->frequency?->name)
            ->removeColumn('income_account', 'frequency')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveHead(?FeeHead $head, array $data): FeeHead
    {
        return DB::transaction(function () use ($head, $data): FeeHead {
            if ($head === null) {
                $head = FeeHead::create([
                    ...$data,
                    'is_refundable' => $data['is_refundable'] ?? false,
                    'is_concession_allowed' => $data['is_concession_allowed'] ?? true,
                    'is_active' => $data['is_active'] ?? true,
                    'sort_order' => $data['sort_order'] ?? ((int) FeeHead::max('sort_order') + 10),
                ]);
            } else {
                $head->update($data);
            }

            return $head->load(['incomeAccount:id,code,name', 'frequency:id,name']);
        });
    }

    /**
     * @throws ValidationException when structures or invoices use the head
     */
    public function deleteHead(FeeHead $head): void
    {
        if (FeeStructureItem::where('fee_head_id', $head->id)->exists() || FeeInvoiceItem::where('fee_head_id', $head->id)->exists()) {
            throw ValidationException::withMessages(['fee_head' => ['Fee structures or challans use this head. Mark it inactive instead.']]);
        }

        DB::transaction(fn () => $head->delete());
    }

    /* ---------- Concession types ---------- */

    public function concessionTypesQuery(): Builder
    {
        return ConcessionType::query()
            ->select(['id', 'name', 'calculation', 'default_value', 'expense_account_id', 'requires_approval', 'is_active', 'created_at'])
            ->with('expenseAccount:id,code,name');
    }

    /**
     * @return array<string, mixed>
     */
    public function concessionTypesDatatable(): array
    {
        return DataTables::eloquent($this->concessionTypesQuery())
            ->escapeColumns([])
            ->addColumn('expense_account_label', fn (ConcessionType $type): ?string => $type->expenseAccount ? $type->expenseAccount->code.' '.$type->expenseAccount->name : null)
            ->removeColumn('expense_account')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveConcessionType(?ConcessionType $type, array $data): ConcessionType
    {
        return DB::transaction(function () use ($type, $data): ConcessionType {
            if ($type === null) {
                $type = ConcessionType::create([
                    ...$data,
                    'requires_approval' => $data['requires_approval'] ?? true,
                    'is_active' => $data['is_active'] ?? true,
                ]);
            } else {
                $type->update($data);
            }

            return $type->load('expenseAccount:id,code,name');
        });
    }

    /**
     * @throws ValidationException when students hold this concession
     */
    public function deleteConcessionType(ConcessionType $type): void
    {
        if (StudentConcession::where('concession_type_id', $type->id)->exists()) {
            throw ValidationException::withMessages(['concession_type' => ['Students hold this concession. Mark it inactive instead.']]);
        }

        DB::transaction(fn () => $type->delete());
    }

    /* ---------- Fee structures ---------- */

    /**
     * @param  array{academic_session_id?: int|null}  $filters
     */
    public function structuresQuery(array $filters = []): Builder
    {
        return FeeStructure::query()
            ->join('classes', 'classes.id', '=', 'fee_structures.class_id')
            ->select(['fee_structures.*', 'classes.name as class_name', 'classes.level_order'])
            ->withCount('items')
            ->withSum(['items as monthly_total' => fn (Builder $query) => $query->where('charge_on', 'recurring')->where('is_mandatory', true)], 'amount')
            ->withSum(['items as admission_total' => fn (Builder $query) => $query->where('charge_on', 'on_admission')->where('is_mandatory', true)], 'amount')
            ->when($filters['academic_session_id'] ?? null, fn (Builder $query, int $id) => $query->where('fee_structures.academic_session_id', $id));
    }

    /**
     * @param  array{academic_session_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function structuresDatatable(array $filters): array
    {
        return DataTables::eloquent($this->structuresQuery($filters))
            ->escapeColumns([])
            ->filterColumn('class_name', fn (Builder $query, string $keyword) => $query->where('classes.name', 'like', "%{$keyword}%"))
            ->orderColumn('class_name', 'classes.level_order $1')
            ->toArray();
    }

    public function findStructure(FeeStructure $structure): FeeStructure
    {
        return $structure->load(['items.feeHead:id,name,code', 'items.months', 'academicSession:id,name', 'schoolClass:id,name']);
    }

    /**
     * Create or replace a class fee structure with all its items (and recurring months).
     *
     * @param  array{academic_session_id: int, class_id: int, name?: string|null, is_active?: bool, items: array<int, array<string, mixed>>}  $data
     */
    public function saveStructure(?FeeStructure $structure, array $data): FeeStructure
    {
        return DB::transaction(function () use ($structure, $data): FeeStructure {
            $attributes = collect($data)->only(['academic_session_id', 'class_id', 'name', 'is_active'])->all();

            if ($structure === null) {
                $structure = FeeStructure::create([...$attributes, 'is_active' => $data['is_active'] ?? true]);
            } else {
                $old = $structure->items()->with('feeHead:id,name')->get()->map(fn (FeeStructureItem $item): string => $item->feeHead?->name.' '.$item->amount)->implode(', ');
                $structure->update($attributes);
                FeeStructureItem::where('fee_structure_id', $structure->id)->get()->each(function (FeeStructureItem $item): void {
                    $item->months()->delete();
                    $item->delete();
                });
            }

            foreach (array_values($data['items']) as $position => $itemData) {
                $item = $structure->items()->create([
                    ...collect($itemData)->except('months')->all(),
                    'is_mandatory' => $itemData['is_mandatory'] ?? true,
                    'applies_to_new_students_only' => $itemData['applies_to_new_students_only'] ?? false,
                    'sort_order' => $itemData['sort_order'] ?? ($position + 1) * 10,
                ]);

                foreach (array_unique($itemData['months'] ?? []) as $month) {
                    $item->months()->create(['month' => $month]);
                }
            }

            if (isset($old)) {
                $new = $structure->items()->with('feeHead:id,name')->get()->map(fn (FeeStructureItem $item): string => $item->feeHead?->name.' '.$item->amount)->implode(', ');

                if ($old !== $new) {
                    $this->activityLogger->record('fee_structures', 'items_changed', (auth()->user()?->name ?? 'System')." changed the charges of {$structure->name}", $structure, ['items' => ['old' => $old, 'new' => $new]]);
                }
            }

            return $this->findStructure($structure);
        });
    }

    public function deleteStructure(FeeStructure $structure): void
    {
        DB::transaction(function () use ($structure): void {
            FeeStructureItem::where('fee_structure_id', $structure->id)->get()->each(function (FeeStructureItem $item): void {
                $item->months()->delete();
                $item->delete();
            });
            $structure->delete();
        });
    }

    /**
     * Copy every class's fee structure into a new session; classes that already have one are skipped.
     *
     * @throws ValidationException
     */
    public function copyStructures(int $fromSessionId, int $toSessionId): int
    {
        if ($fromSessionId === $toSessionId) {
            throw ValidationException::withMessages(['to_session_id' => ['Choose a different session to copy into.']]);
        }

        return DB::transaction(function () use ($fromSessionId, $toSessionId): int {
            $existing = FeeStructure::where('academic_session_id', $toSessionId)->pluck('class_id')->all();
            $copied = 0;

            FeeStructure::with('items.months')->where('academic_session_id', $fromSessionId)->whereNotIn('class_id', $existing)->get()
                ->each(function (FeeStructure $source) use ($toSessionId, &$copied): void {
                    $this->saveStructure(null, [
                        'academic_session_id' => $toSessionId,
                        'class_id' => $source->class_id,
                        'name' => $source->name,
                        'is_active' => $source->is_active,
                        'items' => $source->items->map(fn (FeeStructureItem $item): array => [
                            ...$item->only(['fee_head_id', 'charge_on', 'amount', 'due_day', 'due_in_days', 'is_mandatory', 'applies_to_new_students_only', 'sort_order']),
                            'months' => $item->months->pluck('month')->all(),
                        ])->all(),
                    ]);
                    $copied++;
                });

            return $copied;
        });
    }

    /* ---------- Student concessions ---------- */

    /**
     * @param  array{student_id?: int|null, status?: string|null}  $filters
     */
    public function concessionsQuery(array $filters = []): Builder
    {
        return StudentConcession::query()
            ->with(['student:id,admission_no,first_name,last_name', 'concessionType:id,name', 'feeHead:id,name'])
            ->when($filters['student_id'] ?? null, fn (Builder $query, int $id) => $query->where('student_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * @param  array{student_id?: int|null, status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function concessionsDatatable(array $filters): array
    {
        return DataTables::eloquent($this->concessionsQuery($filters))
            ->escapeColumns([])
            ->addColumn('student_name', fn (StudentConcession $concession): ?string => $concession->student?->fullName())
            ->addColumn('admission_no', fn (StudentConcession $concession): ?string => $concession->student?->admission_no)
            ->addColumn('type_name', fn (StudentConcession $concession): ?string => $concession->concessionType?->name)
            ->addColumn('fee_head_name', fn (StudentConcession $concession): string => $concession->feeHead?->name ?? 'All heads')
            ->filterColumn('student_name', fn (Builder $query, string $keyword) => $query->whereHas('student', fn (Builder $student) => $student->whereAny(['first_name', 'last_name', 'admission_no'], 'like', "%{$keyword}%")))
            ->editColumn('valid_from', fn (StudentConcession $concession): ?string => $concession->valid_from?->toDateString())
            ->editColumn('valid_to', fn (StudentConcession $concession): ?string => $concession->valid_to?->toDateString())
            ->removeColumn('student', 'concession_type', 'fee_head')
            ->toArray();
    }

    /**
     * A concession that does not need approval is approved straight away.
     *
     * @param  array<string, mixed>  $data
     */
    public function grantConcession(array $data): StudentConcession
    {
        return DB::transaction(function () use ($data): StudentConcession {
            $type = ConcessionType::findOrFail($data['concession_type_id']);
            $autoApproved = ! $type->requires_approval;

            $concession = StudentConcession::create([
                ...$data,
                'calculation' => $data['calculation'] ?? $type->calculation,
                'value' => $data['value'] ?? $type->default_value,
                'status' => $autoApproved ? 'approved' : 'pending',
                'approved_by' => $autoApproved ? auth()->id() : null,
                'approved_at' => $autoApproved ? now() : null,
            ]);

            return $concession->load(['student:id,admission_no,first_name,last_name', 'concessionType:id,name', 'feeHead:id,name']);
        });
    }

    /**
     * Approve or reject a pending concession. Approved concessions apply to challans issued from then on.
     *
     * @throws ValidationException when the concession is no longer pending
     */
    public function decideConcession(StudentConcession $concession, string $decision): StudentConcession
    {
        if ($concession->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['This concession has already been '.$concession->status.'.']]);
        }

        return DB::transaction(function () use ($concession, $decision): StudentConcession {
            $concession->update(['status' => $decision, 'approved_by' => auth()->id(), 'approved_at' => now()]);

            return $concession->load(['student:id,admission_no,first_name,last_name', 'concessionType:id,name', 'feeHead:id,name']);
        });
    }

    public function deleteConcession(StudentConcession $concession): void
    {
        if ($concession->status === 'approved' && FeeInvoiceItem::where('student_concession_id', $concession->id)->exists()) {
            throw ValidationException::withMessages(['concession' => ['This concession is already on challans. Set an end date instead.']]);
        }

        DB::transaction(fn () => $concession->delete());
    }
}
