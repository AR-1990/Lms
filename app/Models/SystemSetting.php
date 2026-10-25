<?php

namespace App\Models;

use App\Services\SystemSettingsService;
use App\Services\WebsitePageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_name',
        'school_tagline',
        'school_address',
        'school_phone',
        'admissions_phone',
        'whatsapp_number',
        'contact_email',
        'admissions_email',
        'office_hours',
        'visiting_hours',
        'footer_text',
        'country_id',
        'region_id',
        'currency_id',
        'logo_binary',
        'logo_mime_type',
        'logo_file_name',
        'late_grace_count',
        'lates_per_deduction_day',
        'fee_auto_generate_day',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The timezone / school name and the public pages are cached; a saved setting reaches them at once.
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget(SystemSettingsService::RUNTIME_CACHE_KEY);
            WebsitePageService::flush();
        });
    }
}
