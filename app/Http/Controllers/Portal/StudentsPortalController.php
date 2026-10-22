<?php

namespace App\Http\Controllers\Portal;

use App\Models\Section;
use App\Models\Student;
use App\Services\AcademicSessionService;
use App\Services\LookupService;
use App\Services\StudentRecordService;
use Illuminate\View\View;

/**
 * Students and admissions screens. Grids, popups and actions call the /api/erp endpoints;
 * these actions only render the page shell with the dropdown options it needs.
 */
class StudentsPortalController extends PortalController
{
    public function __construct(
        private AcademicSessionService $sessionService,
        private LookupService $lookupService,
    ) {}

    public function students(): View
    {
        return $this->screen('students', 'students');
    }

    public function show(Student $student, StudentRecordService $studentService): View
    {
        return $this->screen('profile', 'students', [
            'student' => $studentService->find($student),
            'document_types' => $this->lookupService->options('document_type'),
        ]);
    }

    public function applications(): View
    {
        return $this->screen('applications', 'admissions');
    }

    public function inquiries(): View
    {
        return $this->screen('inquiries', 'admissions');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, string $active, array $data = []): View
    {
        $lookups = collect(['gender', 'guardian_relation', 'occupation', 'religion', 'nationality', 'blood_group', 'withdrawal_reason', 'inquiry_source', 'admission_rejection_reason', 'bank'])
            ->mapWithKeys(fn (string $type): array => [$type => $this->lookupService->options($type)]);

        return $this->page("dashboards.students.{$screen}", 'admin', $active, [
            ...$this->sessionService->options(),
            'sections' => Section::query()
                ->join('classes', 'classes.id', '=', 'sections.class_id')
                ->where('sections.academic_session_id', $this->sessionService->current()?->id)
                ->orderBy('classes.level_order')
                ->orderBy('sections.name')
                ->get(['sections.id', 'sections.class_id', 'sections.name', 'classes.name as class_name']),
            'lookups' => $lookups,
            ...$data,
        ]);
    }
}
