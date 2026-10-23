<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\SaveStickyNoteRequest;
use App\Http\Resources\StickyNoteResource;
use App\Models\Lookup;
use App\Models\StickyNoteItem;
use App\Services\StickyNoteService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The signed-in user's own sticky notes (web dashboard and mobile app). Anyone else's note is a 404.
 */
class StickyNoteController extends Controller
{
    use ApiResponse;

    public function __construct(private StickyNoteService $stickyNoteService) {}

    public function index(Request $request): JsonResponse
    {
        $view = $request->validate(['view' => ['nullable', Rule::in(['active', 'done', 'archived'])]])['view'] ?? 'active';
        $notes = $this->stickyNoteService->forUser($request->user(), $view)->paginate(min(max($request->integer('per_page', 30), 1), 100));

        return $this->paginatedResponse($notes, StickyNoteResource::class, 'Sticky notes retrieved.');
    }

    public function colors(): JsonResponse
    {
        return $this->successResponse(Lookup::query()->ofType('sticky_note_color')->active()->get(['id', 'name', 'code']), 'Note colours retrieved.');
    }

    public function store(SaveStickyNoteRequest $request): JsonResponse
    {
        return $this->successResponse(new StickyNoteResource($this->stickyNoteService->save($request->user(), null, $request->validated())), 'Note added.', 201);
    }

    public function update(SaveStickyNoteRequest $request, int $note): JsonResponse
    {
        $record = $this->stickyNoteService->find($request->user(), $note);

        return $this->successResponse(new StickyNoteResource($this->stickyNoteService->save($request->user(), $record, $request->validated())), 'Saved.');
    }

    public function done(Request $request, int $note): JsonResponse
    {
        $done = $request->validate(['is_done' => ['required', 'boolean']])['is_done'];

        return $this->successResponse(new StickyNoteResource($this->stickyNoteService->setDone($this->stickyNoteService->find($request->user(), $note), $done)), $done ? 'Marked done.' : 'Back on the board.');
    }

    public function pin(Request $request, int $note): JsonResponse
    {
        $pinned = $request->validate(['is_pinned' => ['required', 'boolean']])['is_pinned'];

        return $this->successResponse(new StickyNoteResource($this->stickyNoteService->setPinned($this->stickyNoteService->find($request->user(), $note), $pinned)), $pinned ? 'Pinned.' : 'Unpinned.');
    }

    public function toggleItem(Request $request, int $note, int $item): JsonResponse
    {
        $record = $this->stickyNoteService->find($request->user(), $note);
        $toggled = $this->stickyNoteService->toggleItem(StickyNoteItem::where('sticky_note_id', $record->id)->findOrFail($item));

        return $this->successResponse($toggled->only(['id', 'text', 'is_done']), 'Updated.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $this->stickyNoteService->reorder($request->user(), $ids);

        return $this->successResponse(null, 'Order saved.');
    }

    public function destroy(Request $request, int $note): JsonResponse
    {
        $this->stickyNoteService->delete($this->stickyNoteService->find($request->user(), $note));

        return $this->successResponse(null, 'Note removed.');
    }
}
