<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\SaveAnnouncementCategoryRequest;
use App\Http\Requests\Communication\SaveAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Services\AnnouncementService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Announcements: admin writing / publishing (manage-announcements) and every user's own feed.
 */
class AnnouncementController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private AnnouncementService $announcementService) {}

    /* ---------- Everyone: own feed ---------- */

    public function feed(Request $request): JsonResponse
    {
        $announcements = $this->announcementService->feedFor($request->user())->paginate(min(max($request->integer('per_page', 15), 1), 50));

        return $this->paginatedResponse($announcements, AnnouncementResource::class, 'Announcements retrieved.');
    }

    public function read(Request $request, int $announcement): JsonResponse
    {
        $validated = $request->validate(['acknowledge' => ['sometimes', 'boolean'], 'device' => ['nullable', Rule::in(['web', 'android', 'ios'])]]);
        $read = $this->announcementService->markRead($announcement, $request->user(), $validated['device'] ?? 'web', (bool) ($validated['acknowledge'] ?? false));

        return $this->successResponse(['read_at' => $read->read_at->toIso8601String(), 'acknowledged_at' => $read->acknowledged_at?->toIso8601String()], $read->acknowledged_at ? 'Thank you for confirming.' : 'Marked as read.');
    }

    /* ---------- Admin ---------- */

    public function datatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(Announcement::STATUSES)], 'category_id' => ['nullable', 'integer']]);

        return $this->successResponse($this->announcementService->datatable($filters), 'Announcements retrieved.');
    }

    public function show(int $announcement): JsonResponse
    {
        $record = $this->announcementService->find($announcement);

        return $this->successResponse([...(new AnnouncementResource($record))->resolve(), 'stats' => $this->announcementService->stats($record)], 'Announcement retrieved.');
    }

    public function store(SaveAnnouncementRequest $request): JsonResponse
    {
        return $this->successResponse(new AnnouncementResource($this->announcementService->save(null, $request->validated())), 'Draft saved. Publish it when ready.', 201);
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        return $this->successResponse(new AnnouncementResource($this->announcementService->save($announcement, $request->validated())), 'Announcement updated.');
    }

    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement = $this->announcementService->publish($announcement, $request->user());

        return $this->successResponse(new AnnouncementResource($announcement), $announcement->status === 'scheduled' ? 'Scheduled for '.$announcement->publish_at->format('d M Y h:i A').'.' : 'Published.');
    }

    public function archive(Announcement $announcement): JsonResponse
    {
        return $this->successResponse(new AnnouncementResource($this->announcementService->archive($announcement)), 'Archived; it no longer shows to anyone.');
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        return $this->deleteWithApproval($announcement, 'Announcement deleted.');
    }

    /* ---------- Categories ---------- */

    public function categories(): JsonResponse
    {
        return $this->successResponse(AnnouncementCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color', 'icon']), 'Categories retrieved.');
    }

    public function categoriesDatatable(): JsonResponse
    {
        return $this->successResponse($this->announcementService->categoriesDatatable(), 'Categories retrieved.');
    }

    public function showCategory(AnnouncementCategory $category): JsonResponse
    {
        return $this->successResponse($category, 'Category retrieved.');
    }

    public function storeCategory(SaveAnnouncementCategoryRequest $request): JsonResponse
    {
        return $this->successResponse($this->announcementService->saveCategory(null, $request->validated()), 'Category added.', 201);
    }

    public function updateCategory(SaveAnnouncementCategoryRequest $request, AnnouncementCategory $category): JsonResponse
    {
        return $this->successResponse($this->announcementService->saveCategory($category, $request->validated()), 'Category updated.');
    }

    public function destroyCategory(AnnouncementCategory $category): JsonResponse
    {
        return $this->deleteWithApproval($category, 'Category deleted.');
    }
}
