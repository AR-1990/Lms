<?php

namespace App\Http\Requests\Transport;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A school vehicle and the expiry dates of its papers.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration_no' => ['required', 'string', 'max:30', Rule::unique('vehicles', 'registration_no')->ignore($this->route('vehicle')?->id)->whereNull('deleted_at')],
            'type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'vehicle_type')->whereNull('deleted_at')],
            'make_model' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:200'],
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->whereNull('deleted_at')],
            'insurance_expiry' => ['nullable', 'date'],
            'fitness_expiry' => ['nullable', 'date'],
            'route_permit_expiry' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
