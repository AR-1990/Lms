<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A stop on a route with pickup and drop times; fee_override (paisa) replaces the route fee for riders from here.
 */
#[Fillable(['transport_route_id', 'name', 'area_id', 'pickup_time', 'drop_time', 'sort_order', 'fee_override'])]
class TransportStop extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'transport';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'fee_override' => 'integer'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'area_id');
    }
}
