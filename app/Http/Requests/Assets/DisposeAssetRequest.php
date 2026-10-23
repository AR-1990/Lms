<?php

namespace App\Http\Requests\Assets;

use App\Models\AssetDisposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisposeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('approve-requests');
    }

    /**
     * Dispose of an asset (sold, scrapped, donated or lost). Only approvers dispose; the reason is the voucher narration.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'disposed_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(AssetDisposal::METHODS)],
            'sale_amount' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
