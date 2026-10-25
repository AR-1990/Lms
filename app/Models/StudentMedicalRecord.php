<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student's health details: condition, allergies, medicines, doctor, height / weight checks.
 */
#[Fillable(['student_id', 'condition_id', 'allergies', 'medications', 'doctor_name', 'doctor_phone', 'height_cm', 'weight_kg', 'vision', 'checked_on', 'notes'])]
class StudentMedicalRecord extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'students';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['checked_on' => 'date', 'height_cm' => 'float', 'weight_kg' => 'float'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'condition_id');
    }
}
