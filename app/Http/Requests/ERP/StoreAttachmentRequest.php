<?php

namespace App\Http\Requests\ERP;

use App\Services\AttachmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * Only users who may manage the owning record may add files to it.
     */
    public function authorize(): bool
    {
        return app(AttachmentService::class)->canManage($this->user(), (string) $this->input('owner'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner' => ['required', 'string', Rule::in(app(AttachmentService::class)->ownerKeys())],
            'owner_id' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
            'document_type_id' => [
                'nullable', 'integer',
                Rule::exists('lookups', 'id')->where('type', 'document_type')->where('is_active', 1)->whereNull('deleted_at'),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'Files can be up to 10 MB.',
            'file.mimes' => 'Upload an image (JPG, PNG, WEBP), PDF, Word, or Excel file.',
            'document_type_id.exists' => 'Choose a document type from the list.',
        ];
    }
}
