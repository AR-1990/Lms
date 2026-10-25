<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rooms, labs, offices and stores. Assets sit in a location; stores also hold inventory stock.
 */
#[Fillable(['campus_id', 'name', 'code', 'building', 'floor', 'parent_id', 'in_charge_employee_id', 'is_store', 'is_active'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToCampus, HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'assets';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_store' => 'boolean', 'is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function inCharge(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'in_charge_employee_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
