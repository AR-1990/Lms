<?php

namespace App\Http\Requests\Communication;

use App\Models\Announcement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAnnouncementRequest extends FormRequest
{
    /**
     * Table checked for the audience_id of each targeted audience type.
     */
    private const AUDIENCE_TABLES = ['role' => 'roles', 'class' => 'classes', 'section' => 'sections', 'department' => 'departments', 'user' => 'users'];

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-announcements');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('announcement_categories', 'id')->whereNull('deleted_at')],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'is_public' => ['sometimes', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_urgent' => ['sometimes', 'boolean'],
            'send_push' => ['sometimes', 'boolean'],
            'send_sms' => ['sometimes', 'boolean'],
            'send_email' => ['sometimes', 'boolean'],
            'requires_acknowledgement' => ['sometimes', 'boolean'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:now', Rule::when($this->filled('publish_at'), 'after:publish_at')],
            'audiences' => ['sometimes', 'array', 'max:200'],
            'audiences.*.audience_type' => ['required', Rule::in(Announcement::AUDIENCE_TYPES)],
            'audiences.*.audience_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Every role / class / section / department / user target must name an existing record.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('audiences', []) as $index => $audience) {
                $table = self::AUDIENCE_TABLES[$audience['audience_type'] ?? ''] ?? null;

                if ($table !== null && (empty($audience['audience_id']) || DB::table($table)->where('id', $audience['audience_id'])->whereNull('deleted_at')->doesntExist())) {
                    $validator->errors()->add("audiences.{$index}.audience_id", 'Choose a valid '.$audience['audience_type'].' for this audience.');
                }
            }
        }];
    }
}
