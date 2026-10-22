<?php

namespace App\Http\Resources;

use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobApplication
 */
class JobApplicationResource extends JsonResource
{
    /**
     * Salaries are in paisa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_no' => $this->application_no,
            'job_opening_id' => $this->job_opening_id,
            'opening_title' => $this->whenLoaded('jobOpening', fn (): ?string => $this->jobOpening?->title),
            'full_name' => $this->fullName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'cnic' => $this->cnic,
            'gender_id' => $this->gender_id,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'highest_qualification_id' => $this->highest_qualification_id,
            'qualification_details' => $this->qualification_details,
            'experience_years' => $this->experience_years,
            'current_employer' => $this->current_employer,
            'current_salary' => $this->current_salary,
            'expected_salary' => $this->expected_salary,
            'notice_period_days' => $this->notice_period_days,
            'source_id' => $this->source_id,
            'status' => $this->status,
            'next_statuses' => JobApplication::TRANSITIONS[$this->status] ?? [],
            'rating' => $this->rating,
            'rejection_reason_id' => $this->rejection_reason_id,
            'employee_id' => $this->employee_id,
            'employee_code' => $this->whenLoaded('employee', fn (): ?string => $this->employee?->employee_code),
            'remarks' => $this->remarks,
            'interviews' => $this->whenLoaded('interviews', fn (): array => JobInterviewResource::collection($this->interviews)->resolve()),
            'history' => $this->whenLoaded('statusHistories', fn (): array => $this->statusHistories->map(fn ($history): array => [
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'remarks' => $history->remarks,
                'changed_by' => $history->changedBy?->name,
                'at' => $history->created_at?->toIso8601String(),
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
