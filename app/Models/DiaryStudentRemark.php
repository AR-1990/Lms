<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A note for one student only; visible to that student's parents.
 */
#[Fillable(['diary_id', 'student_id', 'teacher_id', 'remark_type_id', 'remark'])]
class DiaryStudentRemark extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'diary';

    public function activityLabel(): string
    {
        return 'remark for student #'.$this->student_id;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class);
    }

    public function remarkType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'remark_type_id');
    }
}
