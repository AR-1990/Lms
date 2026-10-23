<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
