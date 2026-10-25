<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\IssueCardsRequest;
use App\Http\Requests\Cards\SaveCardTemplateRequest;
use App\Http\Resources\CardTemplateResource;
use App\Http\Resources\IdCardResource;
use App\Models\CardTemplate;
use App\Models\IdCard;
use App\Services\CardService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Card templates and issued ID cards. `print` streams a PDF (the one non-JSON endpoint here).
 */
class IdCardController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private CardService $cardService) {}

    /* ---------- Templates ---------- */

    public function templates(Request $request): JsonResponse
    {
        $templates = $this->cardService->templatesQuery(['card_type' => $request->string('card_type')->value() ?: null])->with('fields')->orderBy('name')->get();

        return $this->successResponse(CardTemplateResource::collection($templates)->resolve(), 'Card templates retrieved.');
    }

    public function templatesDatatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->cardService->templatesDatatable(['card_type' => $request->string('card_type')->value() ?: null]), 'Card templates retrieved.');
    }

    public function showTemplate(CardTemplate $cardTemplate): JsonResponse
    {
        return $this->successResponse(new CardTemplateResource($this->cardService->findTemplate($cardTemplate)), 'Card template retrieved.');
    }

    public function storeTemplate(SaveCardTemplateRequest $request): JsonResponse
    {
        return $this->successResponse(new CardTemplateResource($this->cardService->saveTemplate(null, $request->validated())), 'Card template created.', 201);
    }

    public function updateTemplate(SaveCardTemplateRequest $request, CardTemplate $cardTemplate): JsonResponse
    {
        return $this->successResponse(new CardTemplateResource($this->cardService->saveTemplate($cardTemplate, $request->validated())), 'Card template updated.');
    }

    /**
     * PDF of the template filled with sample data (designer preview).
     */
    public function previewTemplate(CardTemplate $cardTemplate): Response
    {
        return $this->cardService->preview($cardTemplate);
    }

    /**
     * Copies of an invitation / event pass / visitor card.
     */
    public function printTemplate(Request $request, CardTemplate $cardTemplate): Response
    {
        abort_unless($cardTemplate->isCustom(), 422, 'ID and admit cards are printed from issued cards.');
        $copies = $request->validate(['copies' => ['required', 'integer', 'min:1', 'max:300']])['copies'];

        return $this->cardService->printCustom($cardTemplate, (int) $copies);
    }

    public function destroyTemplate(CardTemplate $cardTemplate): JsonResponse
    {
        return $this->deleteWithApproval($cardTemplate, 'Card template deleted.');
    }

    /* ---------- Issued cards ---------- */

    public function index(Request $request): JsonResponse
    {
        $cards = $this->cardService->cardsQuery($this->filters($request))->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($cards, IdCardResource::class, 'ID cards retrieved.');
    }

    public function holders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'holder_type' => ['required', Rule::in(['student', 'employee'])],
            'q' => ['required', 'string', 'min:2', 'max:60'],
            'section_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);
        $groupId = $validated['holder_type'] === 'student' ? ($validated['section_id'] ?? null) : ($validated['department_id'] ?? null);

        return $this->successResponse($this->cardService->searchHolders($validated['holder_type'], $validated['q'], $groupId), 'Matching people.');
    }

    /**
     * The signed-in student / parent / staff member's own active cards (web portal and mobile app).
     */
    public function mine(Request $request): JsonResponse
    {
        return $this->successResponse(IdCardResource::collection($this->cardService->ownCards($request->user())->latest('id')->get())->resolve(), 'Your ID cards.');
    }

    /**
     * My attendance QR (dashboard and mobile app): the code on my printed ID card.
     */
    public function myQr(Request $request): JsonResponse
    {
        $qr = $this->cardService->attendanceQr($request->user());

        return $this->successResponse($qr, $qr === null ? 'No ID card has been issued to you yet.' : 'Your attendance QR.');
    }

    public function downloadMine(Request $request, int $idCard): Response
    {
        $card = $this->cardService->ownCards($request->user())->findOrFail($idCard);

        return $this->cardService->pdf(new Collection([$card]), true);
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->cardService->cardsDatatable($this->filters($request)), 'ID cards retrieved.');
    }

    public function issue(IssueCardsRequest $request): JsonResponse
    {
        $result = $this->cardService->issue($request->validated());

        $message = $result['issued'] === 0 && $result['skipped'] > 0
            ? "No new cards: all {$result['skipped']} already have an active card. Print them from the list, or mark a card lost to reissue it."
            : "{$result['issued']} card(s) issued".($result['skipped'] ? "; {$result['skipped']} already had one." : '.');

        return $this->successResponse($result, $message, 201);
    }

    public function changeStatus(Request $request, IdCard $idCard): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['lost', 'revoked', 'expired'])],
            'reason' => ['nullable', 'string', 'max:255'],
            'reissue' => ['sometimes', 'boolean'],
        ]);

        $card = $this->cardService->changeStatus($idCard, $validated['status'], $validated['reason'] ?? null, (bool) ($validated['reissue'] ?? false));

        return $this->successResponse(new IdCardResource($card->load(['template:id,name', 'student', 'employee'])), $card->id === $idCard->id ? 'Card updated.' : "Replacement card {$card->card_no} issued.");
    }

    /**
     * PDF of the chosen cards (all must use the same card size).
     */
    public function print(Request $request): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:300'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $cards = IdCard::whereIn('id', $ids)->where('status', 'active')->orderBy('id')->get();

        abort_if($cards->isEmpty(), 404, 'No active cards to print.');

        return $this->cardService->pdf($cards);
    }

    /**
     * @return array{holder_type: string|null, status: string|null, card_template_id: int|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'holder_type' => ['nullable', Rule::in(['student', 'employee'])],
            'status' => ['nullable', Rule::in(IdCard::STATUSES)],
            'card_template_id' => ['nullable', 'integer'],
        ]);

        return [
            'holder_type' => $validated['holder_type'] ?? null,
            'status' => $validated['status'] ?? null,
            'card_template_id' => isset($validated['card_template_id']) ? (int) $validated['card_template_id'] : null,
        ];
    }
}
