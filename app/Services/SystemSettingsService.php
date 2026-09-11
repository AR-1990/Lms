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
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettingsService
{
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

    public function updateSettings(array $validatedData, ?UploadedFile $logo = null): array
    {
        $this->ensureReferenceData();
        $settings = $this->getSettingsRecord();

        $payload = [
            'school_name' => $validatedData['school_name'],
            'school_address' => $validatedData['school_address'],
            'school_phone' => $validatedData['school_phone'],
            'country_id' => $validatedData['country_id'],
            'region_id' => $validatedData['region_id'],
            'currency_id' => $validatedData['currency_id'],
        ];

        if ($logo instanceof UploadedFile) {
            $payload['logo_binary'] = file_get_contents($logo->getRealPath()) ?: null;
            $payload['logo_mime_type'] = $logo->getMimeType();
            $payload['logo_file_name'] = $logo->getClientOriginalName();
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
            if (! Schema::hasTable('system_settings') || ! Schema::hasTable('regions')) {
                return;
            }

            $settings = SystemSetting::query()->with('region:id,timezone')->first();

            if ($settings === null) {
                return;
            }

            if (filled($settings->region?->timezone)) {
                config(['app.timezone' => $settings->region->timezone]);
                date_default_timezone_set($settings->region->timezone);
            }

            if (filled($settings->school_name)) {
                config(['app.name' => $settings->school_name]);
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
            'timezone' => $settings->region?->timezone ?? config('app.timezone'),
            'logo' => [
                'file_name' => $settings->logo_file_name,
                'mime_type' => $settings->logo_mime_type,
                'data_uri' => filled($settings->logo_binary) && filled($settings->logo_mime_type)
                    ? 'data:'.$settings->logo_mime_type.';base64,'.base64_encode($settings->logo_binary)
                    : null,
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
