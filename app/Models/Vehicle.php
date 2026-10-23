<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A school bus or van; papers (registration, insurance, fitness) are attachments and their expiry dates raise alerts.
 */
#[Fillable(['registration_no', 'type_id', 'make_model', 'capacity', 'asset_id', 'insurance_expiry', 'fitness_expiry', 'route_permit_expiry', 'is_active'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'transport';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['capacity' => 'integer', 'insurance_expiry' => 'date', 'fitness_expiry' => 'date', 'route_permit_expiry' => 'date', 'is_active' => 'boolean'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'type_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TransportRoute::class);
    }
}
