<?php

namespace App\Services;

use App\Models\AttendanceKiosk;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

/**
 * Attendance kiosks: public scanning links for a tablet or phone at the gate. The card itself says whether it
 * is a student (marked present) or staff (checked in / out by shift), so one kiosk serves everyone.
 */
class AttendanceKioskService
{
    public function __construct(private AttendanceService $attendanceService) {}

    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        return DataTables::eloquent(AttendanceKiosk::query()->with('campus:id,name'))
            ->addColumn('campus_name', fn (AttendanceKiosk $kiosk): ?string => $kiosk->campus?->name)
            ->removeColumn('campus')
            ->toArray();
    }

    /**
     * A new kiosk and its link (the link is shown only now; only its hash is kept).
     *
     * @param  array{name: string, campus_id?: int|null}  $data
     * @return array{kiosk: AttendanceKiosk, url: string}
     */
    public function create(array $data): array
    {
        $token = Str::random(40);
        $kiosk = DB::transaction(fn (): AttendanceKiosk => AttendanceKiosk::create([...$data, 'token_hash' => hash('sha256', $token), 'is_active' => true]));

        return ['kiosk' => $kiosk, 'url' => route('kiosk.show', $token)];
    }

    /**
     * @param  array{name?: string, campus_id?: int|null, is_active?: bool}  $data
     */
    public function update(AttendanceKiosk $kiosk, array $data): AttendanceKiosk
    {
        DB::transaction(fn () => $kiosk->update($data));

        return $kiosk;
    }

    /**
     * A fresh link for a kiosk (the old link stops working), e.g. when a tablet is lost.
     *
     * @return array{kiosk: AttendanceKiosk, url: string}
     */
    public function renew(AttendanceKiosk $kiosk): array
    {
        $token = Str::random(40);
        DB::transaction(fn () => $kiosk->update(['token_hash' => hash('sha256', $token)]));

        return ['kiosk' => $kiosk, 'url' => route('kiosk.show', $token)];
    }

    /**
     * @return array{name: string, school_name: string|null}
     */
    public function page(AttendanceKiosk $kiosk): array
    {
        return ['name' => $kiosk->name, 'school_name' => SystemSetting::query()->value('school_name')];
    }

    /**
     * @return array{name: string|null, holder_type: string, status: string, time: string, action: string}
     */
    public function scan(AttendanceKiosk $kiosk, string $code): array
    {
        $result = $this->attendanceService->scan($code, null, $kiosk);
        AttendanceKiosk::whereKey($kiosk->id)->update(['last_used_at' => now(), 'scan_count' => $kiosk->scan_count + 1]);

        return $result;
    }
}
