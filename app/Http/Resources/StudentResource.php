<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * List rows carry the current enrolment as joined columns; the profile loads full relations.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_no' => $this->admission_no,
            'admission_date' => $this->admission_date?->toDateString(),
            'full_name' => $this->fullName(),
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'gender_id' => $this->gender_id,
            'gender' => $this->whenLoaded('gender', fn (): ?string => $this->gender?->name),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'place_of_birth' => $this->place_of_birth,
            'b_form_no' => $this->b_form_no,
            'nationality_id' => $this->nationality_id,
            'religion_id' => $this->religion_id,
            'blood_group_id' => $this->blood_group_id,
            'mother_tongue' => $this->mother_tongue,
            'phone' => $this->phone,
            'email' => $this->email,
            'address_line' => $this->address_line,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'permanent_address' => $this->permanent_address,
            'house_id' => $this->house_id,
            'previous_school' => $this->previous_school,
            'previous_class' => $this->previous_class,
            'current_status' => $this->current_status,
            'left_on' => $this->left_on?->toDateString(),
            'is_staff_child' => $this->is_staff_child,
            'special_needs' => $this->special_needs,
            'remarks' => $this->remarks,
            'enrollment_id' => $this->enrollment_id,
            'class_id' => $this->class_id,
            'class_name' => $this->class_name,
            'section_id' => $this->section_id,
            'section_name' => $this->section_name,
            'roll_no' => $this->roll_no,
            'portal_login' => $this->whenLoaded('user', fn (): ?array => $this->user ? ['username' => $this->user->username, 'is_active' => $this->user->is_active] : null),
            'guardians' => $this->whenLoaded('guardians', fn (): array => $this->guardians->map(fn ($guardian): array => [
                ...$guardian->only(['id', 'name', 'cnic', 'phone', 'email', 'occupation_id', 'employer']),
                'relation_id' => $guardian->pivot->relation_id,
                'is_primary_contact' => (bool) $guardian->pivot->is_primary_contact,
                'is_fee_payer' => (bool) $guardian->pivot->is_fee_payer,
                'has_portal_login' => $guardian->user_id !== null,
            ])->all()),
            'enrollments' => $this->whenLoaded('enrollments', fn (): array => $this->enrollments->sortByDesc('academic_session_id')->values()->map(fn ($enrollment): array => [
                'id' => $enrollment->id,
                'academic_session_id' => $enrollment->academic_session_id,
                'session' => $enrollment->academicSession?->name,
                'class_id' => $enrollment->class_id,
                'class' => $enrollment->schoolClass?->name,
                'section_id' => $enrollment->section_id,
                'section' => $enrollment->section?->name,
                'roll_no' => $enrollment->roll_no,
                'status' => $enrollment->status,
            ])->all()),
            'history' => $this->whenLoaded('statusHistories', fn (): array => $this->statusHistories->map(fn ($history): array => [
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'reason' => $history->reason?->name,
                'effective_on' => $history->effective_on?->toDateString(),
                'remarks' => $history->remarks,
                'changed_by' => $history->changedBy?->name,
            ])->all()),
        ];
    }
}
