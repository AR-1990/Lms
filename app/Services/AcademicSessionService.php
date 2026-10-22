<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class AcademicSessionService
{
    public function query(): Builder
    {
        return AcademicSession::query()
            ->select(['id', 'name', 'starts_on', 'ends_on', 'is_current', 'admissions_open', 'created_at', 'updated_at'])
            ->withCount(['sections', 'curriculum']);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->orderByDesc('starts_on')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        return DataTables::eloquent($this->query())
            ->escapeColumns([])
            ->editColumn('starts_on', fn (AcademicSession $session): string => $session->starts_on->toDateString())
            ->editColumn('ends_on', fn (AcademicSession $session): string => $session->ends_on->toDateString())
            ->toArray();
    }

    public function current(): ?AcademicSession
    {
        return AcademicSession::where('is_current', true)->first();
    }

    /**
     * Dropdown data every academic form and the mobile app needs.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'current_session_id' => $this->current()?->id,
            'sessions' => AcademicSession::orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
            'classes' => SchoolClass::where('is_active', true)->orderBy('level_order')->get(['id', 'name', 'code']),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'has_practical']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AcademicSession
    {
        return DB::transaction(function () use ($data): AcademicSession {
            $session = AcademicSession::create([
                ...$data,
                'is_current' => $data['is_current'] ?? false,
                'admissions_open' => $data['admissions_open'] ?? false,
            ]);

            $this->keepSingleCurrent($session);

            return $session->loadCount(['sections', 'curriculum']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the only current session would stop being current
     */
    public function update(AcademicSession $session, array $data): AcademicSession
    {
        if ($session->is_current && array_key_exists('is_current', $data) && ! $data['is_current']) {
            throw ValidationException::withMessages([
                'is_current' => ['Mark another session as current instead; one session must always be current.'],
            ]);
        }

        return DB::transaction(function () use ($session, $data): AcademicSession {
            $session->update($data);
            $this->keepSingleCurrent($session);

            return $session->loadCount(['sections', 'curriculum']);
        });
    }

    /**
     * @throws ValidationException for the current session or one that already has sections / curriculum
     */
    public function delete(AcademicSession $session): void
    {
        if ($session->is_current) {
            throw ValidationException::withMessages(['session' => ['The current session cannot be deleted.']]);
        }

        if ($session->sections()->exists() || $session->curriculum()->exists()) {
            throw ValidationException::withMessages(['session' => ['This session already has sections or curriculum. Remove them first.']]);
        }

        DB::transaction(fn () => $session->delete());
    }

    private function keepSingleCurrent(AcademicSession $session): void
    {
        if (! $session->is_current) {
            return;
        }

        AcademicSession::where('is_current', true)->whereKeyNot($session->id)->get()
            ->each(fn (AcademicSession $other) => $other->update(['is_current' => false]));
    }
}
