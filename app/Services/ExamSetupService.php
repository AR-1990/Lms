<?php

namespace App\Services;

use App\Models\AssessmentSkill;
use App\Models\Exam;
use App\Models\ExamHall;
use App\Models\ExamSeating;
use App\Models\ExamTerm;
use App\Models\GradingScale;
use App\Models\PerformanceAlertRule;
use App\Models\SkillAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Academic setup lists, one service for every kind in the URL: exam terms, grading scales (with
 * bands), exam halls, and the performance tracker's skills and alert rules.
 */
class ExamSetupService
{
    /**
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'terms' => ExamTerm::class,
        'scales' => GradingScale::class,
        'halls' => ExamHall::class,
        'skills' => AssessmentSkill::class,
        'rules' => PerformanceAlertRule::class,
    ];

    /**
     * @return class-string<Model>
     */
    public function modelFor(string $type): string
    {
        return self::TYPES[$type] ?? abort(404);
    }

    public function find(string $type, int $id): Model
    {
        return $this->query($type)->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(string $type, ?int $sessionId): array
    {
        return DataTables::eloquent($this->query($type)->when($type === 'terms' && $sessionId, fn (Builder $query) => $query->where('academic_session_id', $sessionId)))
            ->escapeColumns([])
            ->addColumn('bands', fn (Model $record): ?string => $record instanceof GradingScale ? $record->items->map(fn ($item): string => "{$item->grade} ≥{$item->min_percent}%")->implode(', ') : null)
            ->editColumn('starts_on', fn (Model $record): ?string => $record instanceof ExamTerm ? $record->starts_on?->toDateString() : null)
            ->editColumn('ends_on', fn (Model $record): ?string => $record instanceof ExamTerm ? $record->ends_on?->toDateString() : null)
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data  scales also take items[]
     */
    public function save(string $type, ?Model $record, array $data): Model
    {
        return DB::transaction(function () use ($type, $record, $data): Model {
            $attributes = collect($data)->except('items')->all();

            if ($record === null) {
                $record = $this->modelFor($type)::create($attributes);
            } else {
                $record->update($attributes);
            }

            if ($record instanceof GradingScale) {
                if (array_key_exists('items', $data)) {
                    $record->items()->delete();
                    $record->items()->createMany($data['items']);
                }

                if ($record->is_default) {
                    GradingScale::whereKeyNot($record->id)->update(['is_default' => false]);
                }
            }

            return $this->find($type, $record->id);
        });
    }

    /**
     * @throws ValidationException when exams or seating still use the record
     */
    public function delete(Model $record): void
    {
        $inUse = match (true) {
            $record instanceof ExamTerm => Exam::where('exam_term_id', $record->id)->exists(),
            $record instanceof GradingScale => Exam::where('grading_scale_id', $record->id)->exists(),
            $record instanceof ExamHall => ExamSeating::where('exam_hall_id', $record->id)->exists(),
            $record instanceof AssessmentSkill => SkillAssessment::where('assessment_skill_id', $record->id)->exists(),
            default => false,
        };

        if ($inUse) {
            throw ValidationException::withMessages(['record' => ['This is still in use. Mark it inactive instead.']]);
        }

        DB::transaction(function () use ($record): void {
            if ($record instanceof GradingScale) {
                $record->items()->delete();
            }

            $record->delete();
        });
    }

    private function query(string $type): Builder
    {
        $query = $this->modelFor($type)::query();

        return $type === 'scales' ? $query->with('items') : $query;
    }
}
