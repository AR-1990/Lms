<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Guardian details captured on the application, copied into `guardians` on enrolment.
 */
#[Fillable(['application_id', 'relation_id', 'name', 'cnic', 'phone', 'email', 'occupation_id', 'employer', 'monthly_income', 'is_primary_contact', 'existing_guardian_id'])]
class AdmissionApplicationGuardian extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary_contact' => 'boolean', 'monthly_income' => 'integer'];
    }

    public function relation(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'relation_id');
    }
}
