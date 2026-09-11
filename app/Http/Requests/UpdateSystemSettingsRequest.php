<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Models\Region;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSystemSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-settings');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:255'],
            'school_address' => ['required', 'string', 'max:1000'],
            'school_phone' => ['required', 'string', 'max:30'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $countryId = (int) $this->input('country_id');
            $regionId = (int) $this->input('region_id');
            $currencyId = (int) $this->input('currency_id');

            $regionMatchesCountry = Region::query()
                ->whereKey($regionId)
                ->where('country_id', $countryId)
                ->exists();

            if (! $regionMatchesCountry) {
                $validator->errors()->add('region_id', 'The selected region does not belong to the selected country.');
            }

            $currencyMatchesCountry = Currency::query()
                ->whereKey($currencyId)
                ->where(function ($query) use ($countryId) {
                    $query->where('country_id', $countryId)
                        ->orWhereNull('country_id');
                })
                ->exists();

            if (! $currencyMatchesCountry) {
                $validator->errors()->add('currency_id', 'The selected currency does not belong to the selected country.');
            }
        });
    }
}
