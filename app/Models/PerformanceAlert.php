<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workflow: open → acknowledged → resolved.
 */
#[Fillable(['enrollment_id', 'student_id', 'performance_alert_rule_id', 'exam_term_id', 'metric_value', 'subject_id', 'status', 'action_taken', 'resolved_by', 'resolved_at'])]
class PerformanceAlert extends Model
{
    use LogsActivity;

    public const STATUSES = ['open', 'acknowledged', 'resolved'];

    protected string $activityModule = 'performance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['metric_value' => 'float', 'resolved_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return 'alert #'.$this->id;
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PerformanceAlertRule::class, 'performance_alert_rule_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
