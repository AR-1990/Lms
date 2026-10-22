<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'name', 'cnic', 'phone', 'alt_phone', 'whatsapp', 'email', 'occupation_id', 'employer', 'designation', 'monthly_income',
    'education_id', 'address_line', 'city', 'photo_path',
])]
class Guardian extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'guardians';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['monthly_income' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot(['relation_id', 'is_primary_contact', 'is_fee_payer', 'can_pick_up', 'lives_with_student'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }
}
