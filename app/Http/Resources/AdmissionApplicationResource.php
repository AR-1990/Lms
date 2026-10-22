<?php

namespace App\Http\Resources;

use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdmissionApplication
 */
class AdmissionApplicationResource extends JsonResource
{
    /**
     * Money is in paisa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_no' => $this->application_no,
            'academic_session_id' => $this->academic_session_id,
            'session_name' => $this->whenLoaded('academicSession', fn (): ?string => $this->academicSession?->name),
            'class_applied_id' => $this->class_applied_id,
            'class_name' => $this->whenLoaded('classApplied', fn (): ?string => $this->classApplied?->name),
            'applied_on' => $this->applied_on?->toDateString(),
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
            'address_line' => $this->address_line,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'previous_school' => $this->previous_school,
            'previous_class' => $this->previous_class,
            'previous_result' => $this->previous_result,
            'reason_for_leaving' => $this->reason_for_leaving,
            'has_sibling_in_school' => $this->has_sibling_in_school,
            'sibling_student_id' => $this->sibling_student_id,
            'special_needs' => $this->special_needs,
            'medical_notes' => $this->medical_notes,
            'transport_required' => $this->transport_required,
            'inquiry_id' => $this->inquiry_id,
            'status' => $this->status,
            'next_statuses' => AdmissionApplication::TRANSITIONS[$this->status] ?? [],
            'decision_on' => $this->decision_on?->toDateString(),
            'rejection_reason_id' => $this->rejection_reason_id,
            'offer_expires_on' => $this->offer_expires_on?->toDateString(),
            'student_id' => $this->student_id,
            'admission_no' => $this->whenLoaded('student', fn (): ?string => $this->student?->admission_no),
            'remarks' => $this->remarks,
            'guardians' => $this->whenLoaded('guardians', fn (): array => $this->guardians->map(fn ($guardian): array => [
                ...$guardian->only(['id', 'relation_id', 'name', 'cnic', 'phone', 'email', 'occupation_id', 'employer', 'monthly_income', 'is_primary_contact', 'existing_guardian_id']),
                'relation' => $guardian->relation?->name,
            ])->all()),
            'tests' => $this->whenLoaded('tests', fn (): array => $this->tests->map(fn ($test): array => [
                ...$test->only(['id', 'venue', 'subject_id', 'max_marks', 'obtained_marks', 'result', 'examiner_id', 'remarks']),
                'scheduled_at' => $test->scheduled_at?->toIso8601String(),
                'subject' => $test->subject?->name,
            ])->all()),
            'interviews' => $this->whenLoaded('interviews', fn (): array => $this->interviews->map(fn ($interview): array => [
                ...$interview->only(['id', 'mode', 'venue_or_link', 'overall_rating', 'recommendation', 'feedback', 'attended']),
                'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
            ])->all()),
            'invoices' => $this->whenLoaded('invoices', fn (): array => $this->invoices->map(fn ($invoice): array => [
                ...$invoice->only(['id', 'invoice_no', 'invoice_type', 'total', 'paid_total', 'waived_total', 'balance', 'status']),
                'due_date' => $invoice->due_date?->toDateString(),
            ])->all()),
            'fees_due' => (int) ($this->fees_due ?? 0),
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
