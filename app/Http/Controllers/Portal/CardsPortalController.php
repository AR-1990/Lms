<?php

namespace App\Http\Controllers\Portal;

use App\Models\CardTemplate;
use App\Models\Department;
use App\Models\Section;
use App\Services\AcademicSessionService;
use App\Services\CardService;
use Illuminate\View\View;

/**
 * ID card screens; data and actions go through /api/erp/id-cards and /api/erp/card-templates.
 */
class CardsPortalController extends PortalController
{
    public function cards(AcademicSessionService $sessionService): View
    {
        return $this->page('dashboards.cards.cards', 'admin', 'id_cards', [
            'templates' => CardTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'card_type']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'sections' => Section::query()
                ->join('classes', 'classes.id', '=', 'sections.class_id')
                ->where('sections.academic_session_id', $sessionService->current()?->id)
                ->orderBy('classes.level_order')
                ->orderBy('sections.name')
                ->get(['sections.id', 'sections.name', 'classes.name as class_name']),
        ]);
    }

    public function templates(): View
    {
        return $this->page('dashboards.cards.templates', 'admin', 'id_cards', [
            'types' => CardTemplate::TYPES,
            'sizes' => CardTemplate::SIZES,
            'themes' => CardTemplate::THEMES,
            'sizes_for_type' => CardTemplate::SIZES_FOR_TYPE,
        ]);
    }

    /**
     * Drag-and-drop designer for one template.
     */
    public function design(CardTemplate $cardTemplate, CardService $cardService): View
    {
        return $this->page('dashboards.cards.designer', 'admin', 'id_cards', [
            'template' => $cardTemplate,
            'keys' => CardTemplate::KEYS_FOR_TYPE[$cardTemplate->card_type] ?? [],
            'samples' => collect($cardService->sampleValues($cardTemplate))->except(['qr_code', 'date_sheet', 'photo'])->all(),
        ]);
    }

    /**
     * A student, parent or staff member's own cards (same data as GET /api/erp/my-id-cards).
     */
    public function mine(CardService $cardService): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.cards.mine', $user->roles->first()->slug ?? 'user', 'my_id_card', [
            'cards' => $cardService->ownCards($user)->latest('id')->get(),
        ]);
    }
}
