<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A concession granted to one student; percent values are × 100, fixed values are paisa per invoice line.
 */
#[Fillable(['student_id', 'concession_type_id', 'fee_head_id', 'calculation', 'value', 'valid_from', 'valid_to', 'reason', 'status', 'approved_by', 'approved_at'])]
class StudentConcession extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'student_concessions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'integer', 'valid_from' => 'date', 'valid_to' => 'date', 'approved_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function concessionType(): BelongsTo
    {
        return $this->belongsTo(ConcessionType::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    /**
     * Discount on one invoice line, never more than the line itself.
     */
    public function discountOn(int $amount): int
    {
        $discount = $this->calculation === 'percent' ? intdiv($amount * $this->value, 10000) : $this->value;

        return min(max($discount, 0), $amount);
    }

    public function activityLabel(): string
    {
        return ($this->concessionType?->name ?? 'Concession').' for student #'.$this->student_id;
    }
}
