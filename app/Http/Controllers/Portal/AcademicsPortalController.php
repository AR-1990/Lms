<?php

namespace App\Http\Controllers\Portal;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Services\AcademicSessionService;
use App\Services\LookupService;
use Illuminate\View\View;

/**
 * Academic setup screens. Each grid and popup talks to the /api/erp academic endpoints;
 * these actions only render the page shell with the dropdown options it needs.
 */
class AcademicsPortalController extends PortalController
{
    public function __construct(private AcademicSessionService $sessionService) {}

    public function sessions(): View
    {
        return $this->screen('sessions');
    }

    public function classes(LookupService $lookupService): View
    {
        return $this->screen('classes', ['wings' => $lookupService->options('wing')]);
    }

    public function sections(): View
    {
        return $this->screen('sections');
    }

    public function subjects(LookupService $lookupService): View
    {
        return $this->screen('subjects', ['subject_types' => $lookupService->options('subject_type')]);
    }

    public function curriculum(): View
    {
        return $this->screen('curriculum');
    }

    public function periods(): View
    {
        return $this->screen('periods');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        return $this->page("dashboards.admin.academics.{$screen}", 'admin', 'academics', [
            ...$this->sessionService->options(),
            'all_classes' => SchoolClass::orderBy('level_order')->get(['id', 'name']),
            'session_names' => AcademicSession::pluck('name', 'id'),
            ...$data,
        ]);
    }
}
