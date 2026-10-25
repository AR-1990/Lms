<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Region;
use App\Models\SystemSetting;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SystemSettingsService
{
    /**
     * Cached timezone and school name; cleared when system_settings or regions change (see the models).
     */
    public const RUNTIME_CACHE_KEY = 'settings:runtime';

    public function getSettingsPageData(): array
    {
        $this->ensureReferenceData();
        $settings = $this->getSettingsRecord();

        return [
            'settings' => $this->formatSettings($settings),
            'countries' => $this->getCountries(),
            'regions' => $this->getRegions(),
            'currencies' => $this->getCurrencies(),
        ];
    }

    public function getSettingsPayload(): array
    {
        $this->ensureReferenceData();

        return $this->formatSettings($this->getSettingsRecord());
    }

    /**
     * The school logo for pages, PDFs and the mobile app; the browser keeps it for a week (the URL changes with each upload).
     */
    public function logoResponse(): Response
    {
        $path = SystemSetting::query()->value('logo_path');
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=604800']);
    }

    /**
     * Full path of the logo file for PDFs, or null when none is uploaded.
     */
    public function logoFile(): ?string
    {
        $path = SystemSetting::query()->value('logo_path');

        return $path !== null && Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }

    public function updateSettings(array $validatedData, ?UploadedFile $logo = null): array
    {
        $this->ensureReferenceData();
        $settings = $this->getSettingsRecord();

        $payload = [
            'school_name' => $validatedData['school_name'],
            'school_address' => $validatedData['school_address'],
            'school_phone' => $validatedData['school_phone'],
            'school_tagline' => $validatedData['school_tagline'] ?? null,
            'admissions_phone' => $validatedData['admissions_phone'] ?? null,
            'whatsapp_number' => $validatedData['whatsapp_number'] ?? null,
            'contact_email' => $validatedData['contact_email'] ?? null,
            'admissions_email' => $validatedData['admissions_email'] ?? null,
            'office_hours' => $validatedData['office_hours'] ?? null,
            'visiting_hours' => $validatedData['visiting_hours'] ?? null,
            'footer_text' => $validatedData['footer_text'] ?? null,
            'country_id' => $validatedData['country_id'],
            'region_id' => $validatedData['region_id'],
            'currency_id' => $validatedData['currency_id'],
        ];

        if ($logo instanceof UploadedFile) {
            $old = $settings->logo_path;
            $payload['logo_path'] = $logo->storeAs('branding', 'logo-'.now()->format('YmdHis').'.'.($logo->extension() ?: 'png'), 'local');

            if ($old !== null && $old !== $payload['logo_path']) {
                Storage::disk('local')->delete($old);
            }
        }

        $settings->fill($payload);
        $settings->save();
        $settings->refresh()->load(['country', 'region', 'currency']);

        return $this->formatSettings($settings);
    }

    public function getCountries(?string $search = null): Collection
    {
        return Country::query()
            ->where('is_active', true)
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('iso2', 'like', "%{$search}%")
                        ->orWhere('iso3', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'iso2', 'iso3', 'phone_code']);
    }

    public function getRegions(?string $search = null, ?int $countryId = null): Collection
    {
        return Region::query()
            ->with('country:id,name,iso2')
            ->where('is_active', true)
            ->when($countryId !== null, fn ($query) => $query->where('country_id', $countryId))
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('timezone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get(['id', 'country_id', 'name', 'slug', 'timezone']);
    }

    public function getCurrencies(?string $search = null, ?int $countryId = null): Collection
    {
        return Currency::query()
            ->with('country:id,name,iso2')
            ->where('is_active', true)
            ->when($countryId !== null, fn ($query) => $query->where('country_id', $countryId))
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get(['id', 'country_id', 'name', 'code', 'symbol', 'precision']);
    }

    public function applyRuntimeConfiguration(): void
    {
        try {
            // Runs on every request, so the school's timezone and name are cached until the settings are saved.
            $runtime = Cache::rememberForever(self::RUNTIME_CACHE_KEY, function (): ?array {
                if (! Schema::hasTable('system_settings') || ! Schema::hasTable('regions')) {
                    return null;
                }

                $settings = SystemSetting::query()->with('region:id,timezone')->first();

                return $settings === null ? null : ['timezone' => $settings->region?->timezone, 'name' => $settings->school_name];
            });

            if (filled($runtime['timezone'] ?? null)) {
                config(['app.timezone' => $runtime['timezone']]);
                date_default_timezone_set($runtime['timezone']);
            }

            if (filled($runtime['name'] ?? null)) {
                config(['app.name' => $runtime['name']]);
            }
        } catch (Throwable) {
        }
    }

    private function ensureReferenceData(): void
    {
        if (! Schema::hasTable('countries') || ! Schema::hasTable('regions') || ! Schema::hasTable('currencies')) {
            return;
        }

        if (Country::query()->exists() && Region::query()->exists() && Currency::query()->exists()) {
            return;
        }

        (new CountrySeeder)->run();
        (new RegionSeeder)->run();
        (new CurrencySeeder)->run();
        (new SystemSettingSeeder)->run();
    }

    private function getSettingsRecord(): SystemSetting
    {
        $settings = SystemSetting::query()
            ->with(['country', 'region', 'currency'])
            ->first();

        if ($settings instanceof SystemSetting) {
            return $settings;
        }

        return SystemSetting::query()->create();
    }

    private function formatSettings(SystemSetting $settings): array
    {
        return [
            'id' => $settings->id,
            'school_name' => $settings->school_name,
            'school_address' => $settings->school_address,
            'school_phone' => $settings->school_phone,
            'school_tagline' => $settings->school_tagline,
            'admissions_phone' => $settings->admissions_phone,
            'whatsapp_number' => $settings->whatsapp_number,
            'contact_email' => $settings->contact_email,
            'admissions_email' => $settings->admissions_email,
            'office_hours' => $settings->office_hours,
            'visiting_hours' => $settings->visiting_hours,
            'footer_text' => $settings->footer_text,
            'timezone' => $settings->region?->timezone ?? config('app.timezone'),
            'logo' => [
                'url' => $settings->logo_path ? route('school-logo', ['v' => basename($settings->logo_path)]) : null,
            ],
            'country' => $settings->country === null ? null : [
                'id' => $settings->country->id,
                'name' => $settings->country->name,
                'iso2' => $settings->country->iso2,
                'phone_code' => $settings->country->phone_code,
            ],
            'region' => $settings->region === null ? null : [
                'id' => $settings->region->id,
                'country_id' => $settings->region->country_id,
                'name' => $settings->region->name,
                'slug' => $settings->region->slug,
                'timezone' => $settings->region->timezone,
            ],
            'currency' => $settings->currency === null ? null : [
                'id' => $settings->currency->id,
                'country_id' => $settings->currency->country_id,
                'name' => $settings->currency->name,
                'code' => $settings->currency->code,
                'symbol' => $settings->currency->symbol,
                'precision' => $settings->currency->precision,
            ],
        ];
    }
}
