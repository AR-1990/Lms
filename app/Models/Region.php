<?php

namespace App\Models;

use App\Services\SystemSettingsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'slug',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * The school's timezone comes from its region and is cached (SystemSettingsService).
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(SystemSettingsService::RUNTIME_CACHE_KEY));
    }
}
