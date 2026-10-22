<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A school-defined "at risk" rule, e.g. attendance_percent < 75.
 */
#[Fillable(['name', 'metric', 'operator', 'threshold', 'notify_class_teacher', 'notify_parents', 'is_active'])]
class PerformanceAlertRule extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Snapshot figures a rule can watch (the code compares these columns).
     */
    public const METRICS = ['attendance_percent', 'average_percent', 'homework_completion_percent', 'discipline_count', 'late_count'];

    public const OPERATORS = ['<', '<=', '>', '>='];

    protected string $activityModule = 'performance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['threshold' => 'float', 'notify_class_teacher' => 'boolean', 'notify_parents' => 'boolean', 'is_active' => 'boolean'];
    }

    public function matches(?float $value): bool
    {
        return $value !== null && match ($this->operator) {
            '<' => $value < $this->threshold,
            '<=' => $value <= $this->threshold,
            '>' => $value > $this->threshold,
            default => $value >= $this->threshold,
        };
    }
}
