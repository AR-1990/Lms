<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\TransportRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bus route: driver, attendant, vehicle and the monthly fee (a stop may charge its own fee).
 */
#[Fillable(['name', 'code', 'driver_employee_id', 'attendant_employee_id', 'vehicle_id', 'monthly_fee', 'fee_head_id', 'is_active'])]
class TransportRoute extends Model
{
    /** @use HasFactory<TransportRouteFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'transport';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['monthly_fee' => 'integer', 'is_active' => 'boolean'];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_employee_id');
    }

    public function attendant(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'attendant_employee_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TransportStop::class);
    }

    public function riders(): HasMany
    {
        return $this->hasMany(StudentTransport::class);
    }
}
