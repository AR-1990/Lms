<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\CampusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'address', 'city', 'phone', 'email', 'principal_employee_id', 'logo_path', 'is_active', 'created_by', 'updated_by'])]
class Campus extends Model
{
    /** @use HasFactory<CampusFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected string $activityModule = 'campuses';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
