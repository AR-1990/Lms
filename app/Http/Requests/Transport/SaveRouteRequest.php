<?php

namespace App\Http\Requests\Transport;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A route with its stops in pickup order. Fees in paisa.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'driver_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'attendant_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'monthly_fee' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'fee_head_id' => ['nullable', 'integer', Rule::exists('fee_heads', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
            'stops' => ['array', 'max:60'],
            'stops.*.id' => ['nullable', 'integer'],
            'stops.*.name' => ['required', 'string', 'max:100'],
            'stops.*.area_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'transport_stop_area')->whereNull('deleted_at')],
            'stops.*.pickup_time' => ['nullable', 'date_format:H:i'],
            'stops.*.drop_time' => ['nullable', 'date_format:H:i'],
            'stops.*.fee_override' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
