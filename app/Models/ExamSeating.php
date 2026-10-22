<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['exam_subject_id', 'exam_hall_id', 'enrollment_id', 'seat_no', 'row_no', 'column_no'])]
class ExamSeating extends Model
{
    use SoftDeletes;

    public function hall(): BelongsTo
    {
        return $this->belongsTo(ExamHall::class, 'exam_hall_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
