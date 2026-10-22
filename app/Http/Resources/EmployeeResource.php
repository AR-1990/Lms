<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'full_name' => $this->fullName(),
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'father_or_spouse_name' => $this->father_or_spouse_name,
            'gender_id' => $this->gender_id,
            'gender' => $this->whenLoaded('gender', fn (): ?string => $this->gender?->name),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'cnic' => $this->cnic,
            'cnic_expiry' => $this->cnic_expiry?->toDateString(),
            'marital_status_id' => $this->marital_status_id,
            'religion_id' => $this->religion_id,
            'blood_group_id' => $this->blood_group_id,
            'nationality_id' => $this->nationality_id,
            'phone' => $this->phone,
            'alt_phone' => $this->alt_phone,
            'email' => $this->email,
            'personal_email' => $this->personal_email,
            'current_address' => $this->current_address,
            'permanent_address' => $this->permanent_address,
            'city' => $this->city,
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn (): ?string => $this->department?->name),
            'designation_id' => $this->designation_id,
            'designation_name' => $this->whenLoaded('designation', fn (): ?string => $this->designation?->name),
            'employment_type_id' => $this->employment_type_id,
            'employment_type_name' => $this->whenLoaded('employmentType', fn (): ?string => $this->employmentType?->name),
            'reports_to_id' => $this->reports_to_id,
            'reports_to_name' => $this->whenLoaded('reportsTo', fn (): ?string => $this->reportsTo?->fullName()),
            'joining_date' => $this->joining_date?->toDateString(),
            'probation_end_date' => $this->probation_end_date?->toDateString(),
            'confirmation_date' => $this->confirmation_date?->toDateString(),
            'contract_end_date' => $this->contract_end_date?->toDateString(),
            'is_teaching_staff' => $this->is_teaching_staff,
            'ntn' => $this->ntn,
            'eobi_no' => $this->eobi_no,
            'bank_id' => $this->bank_id,
            'bank_account_title' => $this->bank_account_title,
            'bank_account_no' => $this->bank_account_no,
            'iban' => $this->iban,
            'status' => $this->status,
            'exit_date' => $this->exit_date?->toDateString(),
            'portal_login' => $this->whenLoaded('user', fn (): ?array => $this->user ? ['username' => $this->user->username, 'email' => $this->user->email, 'is_active' => $this->user->is_active] : null),
            'service_record' => $this->whenLoaded('statusHistories', fn (): array => $this->statusHistories->map(fn ($history): array => [
                'field' => $history->field,
                'old_value' => $history->old_value,
                'new_value' => $history->new_value,
                'effective_on' => $history->effective_on?->toDateString(),
                'remarks' => $history->remarks,
                'changed_by' => $history->changedBy?->name,
            ])->all()),
        ];
    }
}
