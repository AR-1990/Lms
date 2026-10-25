<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A discipline incident: what happened, the action taken and whether the parents were told.
 */
#[Fillable(['student_id', 'incident_date', 'category_id', 'description', 'action_taken', 'reported_by', 'parent_informed', 'parent_informed_on'])]
class DisciplineRecord extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'students';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['incident_date' => 'date', 'parent_informed' => 'boolean', 'parent_informed_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by');
    }
}
