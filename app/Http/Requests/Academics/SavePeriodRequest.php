<?php

namespace App\Http\Requests\Academics;

use App\Models\Period;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SavePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-academics');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'is_break' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'],
        ];
    }

    /**
     * Periods of one bell schedule may not overlap.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $overlapping = Period::query()
                ->where('starts_at', '<', $this->input('ends_at'))
                ->where('ends_at', '>', $this->input('starts_at'))
                ->when($this->route('period'), fn ($query, Period $period) => $query->whereKeyNot($period->id))
                ->value('name');

            if ($overlapping !== null) {
                $validator->errors()->add('starts_at', "This time overlaps {$overlapping}.");
            }
        });
    }
}
