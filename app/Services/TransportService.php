<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\FeeHead;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\SystemSetting;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\User;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * School transport (PLAN.md §18): vehicles with paper expiry alerts, routes with stops, and which student
 * rides from which stop. Active riders get the stop (or route) fee on their monthly challan.
 */
class TransportService
{
    public const FEE_HEAD_CODE = 'TRN';

    public function __construct(private NotificationService $notificationService, private ActivityLogger $activityLogger) {}

    /* ---------- Vehicles ---------- */

    /**
     * @return array<string, mixed>
     */
    public function vehiclesDatatable(): array
    {
        $query = Vehicle::query()
            ->select(['id', 'registration_no', 'type_id', 'make_model', 'capacity', 'asset_id', 'insurance_expiry', 'fitness_expiry', 'route_permit_expiry', 'is_active', 'created_at'])
            ->with(['type:id,name', 'asset:id,asset_tag'])
            ->withCount(['routes' => fn (Builder $query) => $query->where('is_active', true)]);

        return DataTables::eloquent($query)
            ->addColumn('type_name', fn (Vehicle $vehicle): ?string => $vehicle->type?->name)
            ->addColumn('asset_tag', fn (Vehicle $vehicle): ?string => $vehicle->asset?->asset_tag)
            ->addColumn('expiring', fn (Vehicle $vehicle): array => $this->expiringPapers($vehicle, 30))
            ->editColumn('insurance_expiry', fn (Vehicle $vehicle): ?string => $vehicle->insurance_expiry?->toDateString())
            ->editColumn('fitness_expiry', fn (Vehicle $vehicle): ?string => $vehicle->fitness_expiry?->toDateString())
            ->editColumn('route_permit_expiry', fn (Vehicle $vehicle): ?string => $vehicle->route_permit_expiry?->toDateString())
            ->removeColumn('type', 'asset')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveVehicle(?Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle ??= new Vehicle(['is_active' => true]);
        $vehicle->fill([...$data, 'capacity' => (int) ($data['capacity'] ?? 0)])->save();

        return $vehicle;
    }

    public function deleteVehicle(Vehicle $vehicle): void
    {
        if ($vehicle->routes()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['vehicle' => ['This vehicle runs a route. Change the route first.']]);
        }

        $vehicle->delete();
    }

    /**
     * Papers expiring within the given days (or already expired).
     *
     * @return array<int, string>
     */
    public function expiringPapers(Vehicle $vehicle, int $days): array
    {
        $limit = today()->addDays($days);

        return collect(['insurance_expiry' => 'Insurance', 'fitness_expiry' => 'Fitness', 'route_permit_expiry' => 'Route permit'])
            ->filter(fn (string $label, string $field): bool => $vehicle->{$field} !== null && $vehicle->{$field}->lessThanOrEqualTo($limit))
            ->map(fn (string $label, string $field): string => $label.($vehicle->{$field}->isPast() ? ' expired ' : ' expires ').$vehicle->{$field}->format('d M Y'))
            ->values()->all();
    }

    /**
     * Tell transport managers about papers expiring within 30 days (run daily).
     */
    public function sendExpiryAlerts(): int
    {
        $managers = User::where('is_active', true)->whereHas('roles.permissions', fn (Builder $query) => $query->where('slug', 'manage-transport'))->pluck('id')->all();
        $sent = 0;

        foreach (Vehicle::where('is_active', true)->get() as $vehicle) {
            $papers = $this->expiringPapers($vehicle, 30);
            $dueToday = collect(['insurance_expiry', 'fitness_expiry', 'route_permit_expiry'])->contains(fn (string $field): bool => $vehicle->{$field} !== null
                && (in_array((int) today()->diffInDays($vehicle->{$field}, false), [30, 7, 0], true) || ($vehicle->{$field}->isPast() && today()->day === 1)));

            if ($papers !== [] && $dueToday) {
                $this->notificationService->notify($managers, 'vehicle_papers', "{$vehicle->registration_no}: papers need renewal", implode(' · ', $papers), route('admin.transport.vehicles'), $vehicle);
                $sent++;
            }
        }

        return $sent;
    }

    /* ---------- Routes and stops ---------- */

    /**
     * @return array<string, mixed>
     */
    public function routesDatatable(): array
    {
        $query = TransportRoute::query()
            ->select(['id', 'name', 'code', 'driver_employee_id', 'attendant_employee_id', 'vehicle_id', 'monthly_fee', 'is_active', 'created_at'])
            ->with(['driver:id,first_name,middle_name,last_name,phone', 'vehicle:id,registration_no,capacity'])
            ->withCount(['stops', 'riders' => fn (Builder $query) => $query->where('status', 'active')]);

        return DataTables::eloquent($query)
            ->addColumn('driver_name', fn (TransportRoute $route): ?string => $route->driver?->fullName())
            ->addColumn('driver_phone', fn (TransportRoute $route): ?string => $route->driver?->phone)
            ->addColumn('vehicle_label', fn (TransportRoute $route): ?string => $route->vehicle?->registration_no)
            ->addColumn('capacity', fn (TransportRoute $route): ?int => $route->vehicle?->capacity)
            ->removeColumn('driver', 'vehicle')
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function route(TransportRoute $route): array
    {
        $route->load(['driver:id,first_name,middle_name,last_name,phone', 'attendant:id,first_name,middle_name,last_name,phone', 'vehicle:id,registration_no,capacity', 'stops' => fn ($query) => $query->orderBy('sort_order')->with('area:id,name')]);

        return [
            ...$route->attributesToArray(),
            'driver_name' => $route->driver?->fullName(),
            'attendant_name' => $route->attendant?->fullName(),
            'vehicle_label' => $route->vehicle?->registration_no,
            'capacity' => $route->vehicle?->capacity,
            'riders' => $route->riders()->where('status', 'active')->count(),
            'stops' => $route->stops->map(fn (TransportStop $stop): array => [
                'id' => $stop->id, 'name' => $stop->name, 'area_id' => $stop->area_id, 'area_name' => $stop->area?->name,
                'pickup_time' => $stop->pickup_time ? substr((string) $stop->pickup_time, 0, 5) : null, 'drop_time' => $stop->drop_time ? substr((string) $stop->drop_time, 0, 5) : null,
                'fee_override' => $stop->fee_override, 'sort_order' => $stop->sort_order,
                'riders' => StudentTransport::where('transport_stop_id', $stop->id)->where('status', 'active')->count(),
            ])->all(),
        ];
    }

    /**
     * Save a route with its stops in order: existing stops are updated, new ones added, missing ones removed
     * (a stop with riders cannot be removed).
     *
     * @param  array<string, mixed>  $data  route fields + stops[{id?, name, area_id?, pickup_time?, drop_time?, fee_override?}]
     */
    public function saveRoute(?TransportRoute $route, array $data): TransportRoute
    {
        return DB::transaction(function () use ($route, $data): TransportRoute {
            $route ??= new TransportRoute(['is_active' => true]);
            $route->fill([...collect($data)->except('stops')->all(), 'monthly_fee' => (int) ($data['monthly_fee'] ?? 0), 'fee_head_id' => $data['fee_head_id'] ?? $route->fee_head_id ?? FeeHead::where('code', self::FEE_HEAD_CODE)->value('id')])->save();

            if ($route->vehicle_id !== null) {
                $this->ensureSeats($route, 0);
            }

            $kept = [];

            foreach (array_values($data['stops'] ?? []) as $position => $row) {
                $stop = isset($row['id']) ? TransportStop::where('transport_route_id', $route->id)->findOrFail($row['id']) : new TransportStop(['transport_route_id' => $route->id]);
                $stop->fill([...collect($row)->except('id')->all(), 'sort_order' => $position])->save();
                $kept[] = $stop->id;
            }

            foreach (TransportStop::where('transport_route_id', $route->id)->whereNotIn('id', $kept)->get() as $removed) {
                if (StudentTransport::where('transport_stop_id', $removed->id)->where('status', 'active')->exists()) {
                    throw ValidationException::withMessages(['stops' => ["Students still ride from {$removed->name}. Move them first."]]);
                }

                $removed->delete();
            }

            return $route;
        });
    }

    public function deleteRoute(TransportRoute $route): void
    {
        if ($route->riders()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['route' => ['Students still ride this route. Move them first.']]);
        }

        DB::transaction(function () use ($route): void {
            $route->stops()->get()->each->delete();
            $route->delete();
        });
    }

    /* ---------- Riders ---------- */

    /**
     * @param  array{transport_route_id?: int|null, status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function ridersDatatable(array $filters): array
    {
        $query = StudentTransport::query()
            ->select(['id', 'student_id', 'transport_route_id', 'transport_stop_id', 'from_date', 'to_date', 'status', 'remarks', 'created_at'])
            ->with(['student:id,first_name,middle_name,last_name,admission_no', 'student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name', 'route:id,name,monthly_fee', 'stop:id,name,pickup_time,drop_time,fee_override'])
            ->when($filters['transport_route_id'] ?? null, fn (Builder $query, int $id) => $query->where('transport_route_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        return DataTables::eloquent($query)
            ->addColumn('student_name', fn (StudentTransport $row): ?string => $row->student?->fullName())
            ->addColumn('admission_no', fn (StudentTransport $row): ?string => $row->student?->admission_no)
            ->addColumn('class_label', fn (StudentTransport $row): string => trim(($row->student?->currentEnrollment?->schoolClass?->name ?? '').' '.($row->student?->currentEnrollment?->section?->name ?? '')))
            ->addColumn('route_name', fn (StudentTransport $row): ?string => $row->route?->name)
            ->addColumn('stop_name', fn (StudentTransport $row): ?string => $row->stop?->name)
            ->addColumn('fee', fn (StudentTransport $row): int => $this->feeFor($row))
            ->filterColumn('student_name', fn (Builder $query, string $keyword) => $query->whereHas('student', fn (Builder $inner) => $inner->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%")->orWhere('admission_no', 'like', "%{$keyword}%")))
            ->editColumn('from_date', fn (StudentTransport $row): ?string => $row->from_date?->toDateString())
            ->editColumn('to_date', fn (StudentTransport $row): ?string => $row->to_date?->toDateString())
            ->removeColumn('student', 'route', 'stop')
            ->toArray();
    }

    /**
     * Put a student on a stop. A student already riding is moved (the old ride ends the day before).
     *
     * @param  array{student_id: int, transport_stop_id: int, from_date: string, remarks?: string|null}  $data
     */
    public function assign(array $data, User $user): StudentTransport
    {
        $student = Student::findOrFail($data['student_id']);
        $enrollment = Enrollment::where('student_id', $student->id)->whereHas('academicSession', fn (Builder $query) => $query->where('is_current', true))->first();

        if ($enrollment === null) {
            throw ValidationException::withMessages(['student_id' => ['This student is not enrolled in the current session.']]);
        }

        $stop = TransportStop::findOrFail($data['transport_stop_id']);
        $route = TransportRoute::findOrFail($stop->transport_route_id);
        $current = StudentTransport::where('student_id', $student->id)->where('status', 'active')->first();

        if ($current?->transport_route_id !== $route->id) {
            $this->ensureSeats($route, 1);
        }

        return DB::transaction(function () use ($data, $student, $stop, $route, $enrollment, $current, $user): StudentTransport {
            if ($current !== null) {
                $end = Carbon::parse($data['from_date'])->subDay();
                $current->update(['status' => 'ended', 'to_date' => $end->lt($current->from_date) ? $current->from_date : $end]);
            }

            $ride = StudentTransport::create([
                'student_id' => $student->id, 'transport_route_id' => $route->id, 'transport_stop_id' => $stop->id, 'academic_session_id' => $enrollment->academic_session_id,
                'from_date' => $data['from_date'], 'status' => 'active', 'remarks' => $data['remarks'] ?? null,
            ]);
            $this->activityLogger->record('transport', 'assigned', "{$user->name} put {$student->fullName()} on {$route->name} ({$stop->name})", $ride);

            return $ride;
        });
    }

    public function end(StudentTransport $ride, string $toDate, User $user): StudentTransport
    {
        if ($ride->status !== 'active') {
            throw ValidationException::withMessages(['ride' => ['This student no longer rides.']]);
        }

        $ride->update(['status' => 'ended', 'to_date' => $toDate]);
        $this->activityLogger->record('transport', 'ended', "{$user->name} took ".Student::whereKey($ride->student_id)->first()?->fullName().' off transport', $ride);

        return $ride;
    }

    /**
     * End every active ride of a student who leaves the school.
     */
    public function endForStudent(Student $student, string $toDate): void
    {
        StudentTransport::where('student_id', $student->id)->where('status', 'active')->get()
            ->each(fn (StudentTransport $ride) => $ride->update(['status' => 'ended', 'to_date' => $toDate]));
    }

    public function feeFor(StudentTransport $ride): int
    {
        $stop = $ride->relationLoaded('stop') ? $ride->stop : TransportStop::find($ride->transport_stop_id);
        $route = $ride->relationLoaded('route') ? $ride->route : TransportRoute::find($ride->transport_route_id);

        return (int) ($stop?->fee_override ?? $route?->monthly_fee ?? 0);
    }

    /**
     * Transport lines for a student's monthly challan: every ride active at some point in the month.
     *
     * @return Collection<int, array{head: FeeHead, amount: int, description: string}>
     */
    public function monthlyLines(Student $student, Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth();

        return StudentTransport::where('student_id', $student->id)
            ->whereIn('status', ['active', 'ended'])
            ->whereDate('from_date', '<=', $start->copy()->endOfMonth())
            ->where(fn (Builder $query) => $query->whereNull('to_date')->orWhereDate('to_date', '>=', $start))
            ->with(['route:id,name,monthly_fee,fee_head_id', 'stop:id,name,fee_override'])
            ->get()
            ->unique('transport_route_id')
            ->map(function (StudentTransport $ride) use ($start): ?array {
                $head = FeeHead::find($ride->route?->fee_head_id) ?? FeeHead::where('code', self::FEE_HEAD_CODE)->first();
                $amount = $this->feeFor($ride);

                return $head === null || $amount <= 0 ? null : ['head' => $head, 'amount' => $amount, 'description' => "Transport — {$ride->route?->name} ({$ride->stop?->name}) — ".$start->format('M Y')];
            })
            ->filter()
            ->values();
    }

    /**
     * The bus a student rides now (portals and the mobile app).
     *
     * @return array<string, mixed>|null
     */
    public function busFor(Student $student): ?array
    {
        $ride = StudentTransport::where('student_id', $student->id)->where('status', 'active')
            ->with(['route.driver:id,first_name,middle_name,last_name,phone', 'route.attendant:id,first_name,middle_name,last_name,phone', 'route.vehicle:id,registration_no,make_model', 'stop'])
            ->first();

        return $ride === null ? null : [
            'route' => $ride->route?->name,
            'stop' => $ride->stop?->name,
            'pickup_time' => $ride->stop?->pickup_time ? substr((string) $ride->stop->pickup_time, 0, 5) : null,
            'drop_time' => $ride->stop?->drop_time ? substr((string) $ride->stop->drop_time, 0, 5) : null,
            'vehicle' => trim(($ride->route?->vehicle?->registration_no ?? '').' '.($ride->route?->vehicle?->make_model ?? '')) ?: null,
            'driver' => $ride->route?->driver?->fullName(),
            'driver_phone' => $ride->route?->driver?->phone,
            'attendant' => $ride->route?->attendant?->fullName(),
            'attendant_phone' => $ride->route?->attendant?->phone,
            'monthly_fee' => $this->feeFor($ride),
        ];
    }

    /**
     * Riders by stop for a route sheet (drivers and attendants).
     *
     * @return array<string, mixed>
     */
    public function routeSheet(TransportRoute $route): array
    {
        $details = $this->route($route);
        $riders = StudentTransport::where('transport_route_id', $route->id)->where('status', 'active')
            ->with(['student:id,first_name,middle_name,last_name,admission_no,phone', 'student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name', 'student.guardians:id,name,phone'])
            ->get()->groupBy('transport_stop_id');

        $details['stops'] = array_map(fn (array $stop): array => [...$stop, 'students' => ($riders[$stop['id']] ?? collect())->map(fn (StudentTransport $ride): array => [
            'name' => $ride->student?->fullName(),
            'admission_no' => $ride->student?->admission_no,
            'class' => trim(($ride->student?->currentEnrollment?->schoolClass?->name ?? '').' '.($ride->student?->currentEnrollment?->section?->name ?? '')),
            'contact' => $ride->student?->guardians->first()?->phone ?? $ride->student?->phone,
        ])->sortBy('name')->values()->all()], $details['stops']);

        return $details;
    }

    public function routeSheetPdf(TransportRoute $route): Response
    {
        return Pdf::loadView('pdf.route-sheet', ['route' => $this->routeSheet($route), 'schoolName' => SystemSetting::query()->value('school_name')])->setPaper('a4')
            ->stream('route-'.str($route->name)->slug().'.pdf');
    }

    /**
     * Refuse when the route's vehicle would have more riders than seats.
     */
    private function ensureSeats(TransportRoute $route, int $adding): void
    {
        $capacity = (int) Vehicle::whereKey($route->vehicle_id)->value('capacity');
        $riders = StudentTransport::where('transport_route_id', $route->id)->where('status', 'active')->count();

        if ($capacity > 0 && $riders + $adding > $capacity) {
            throw ValidationException::withMessages(['transport_stop_id' => ["{$route->name} is full ({$riders} of {$capacity} seats)."]]);
        }
    }
}
