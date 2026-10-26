<?php

namespace App\Http\Controllers\Portal;

use App\Models\AnnouncementCategory;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AcademicSessionService;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Announcement screens. Admin pages load data through /api/erp/announcements, /api/erp/message-templates
 * and /api/erp/notification-logs; the notices page shows the signed-in user's own feed.
 */
class CommunicationPortalController extends PortalController
{
    public function announcements(AcademicSessionService $sessionService): View
    {
        return $this->page('dashboards.communication.announcements', 'admin', 'announcements', [
            'categories' => AnnouncementCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'classes' => SchoolClass::orderBy('level_order')->get(['id', 'name']),
            'sections' => Section::query()
                ->join('classes', 'classes.id', '=', 'sections.class_id')
                ->where('sections.academic_session_id', $sessionService->current()?->id)
                ->orderBy('classes.level_order')
                ->orderBy('sections.name')
                ->get(['sections.id', 'sections.name', 'classes.name as class_name']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function settings(string $type): View
    {
        return $this->page('dashboards.communication.settings', 'admin', 'announcements', [
            'type' => $type,
            'channels' => MessageTemplate::CHANNELS,
            'events' => MessageTemplate::EVENTS,
        ]);
    }

    public function deliveryLog(): View
    {
        return $this->page('dashboards.communication.delivery-log', 'admin', 'announcements', ['channels' => MessageTemplate::CHANNELS]);
    }

    /**
     * Everyone's notices (same data as GET /api/erp/my-announcements).
     */
    public function notices(Request $request, AnnouncementService $announcementService): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.communication.notices', $user->roles->first()->slug ?? 'user', 'notices', [
            'announcements' => $announcementService->feedFor($user)->paginate(10)->withQueryString(),
            'open_id' => $request->integer('announcement') ?: null,
        ]);
    }

    /**
     * User guide: how to use each screen, showing only the topics this user's permissions open.
     */
    public function guide(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.guide', $user->roles->first()->slug ?? 'user', 'guide', [
            'is_staff' => Employee::where('user_id', $user->id)->exists(),
        ]);
    }

    /**
     * The user's own sticky notes (data: /api/erp/sticky-notes).
     */
    public function notes(string $view): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.my-notes', $user->roles->first()->slug ?? 'user', 'dashboard', ['view' => $view]);
    }
}
