<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\SaveMessageTemplateRequest;
use App\Http\Resources\UserNotificationResource;
use App\Models\MessageTemplate;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The signed-in user's notification bell, plus message templates and the delivery log (manage-announcements).
 */
class NotificationController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->forUser($request->user(), $request->boolean('unread'))->paginate(min(max($request->integer('per_page', 20), 1), 50));

        return $this->paginatedResponse($notifications, UserNotificationResource::class, 'Notifications retrieved.');
    }

    /**
     * Bell badge and the latest few, in one call.
     */
    public function summary(Request $request): JsonResponse
    {
        return $this->successResponse([
            'unread' => $this->notificationService->unreadCount($request->user()),
            'latest' => UserNotificationResource::collection($this->notificationService->forUser($request->user())->limit(8)->get())->resolve(),
        ], 'Notifications retrieved.');
    }

    public function read(Request $request, int $notification): JsonResponse
    {
        $this->notificationService->markRead($request->user(), $notification);

        return $this->successResponse(['unread' => $this->notificationService->unreadCount($request->user())], 'Marked as read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->notificationService->markRead($request->user());

        return $this->successResponse(['unread' => 0], 'All caught up.');
    }

    /* ---------- Message templates ---------- */

    public function templatesDatatable(): JsonResponse
    {
        return $this->successResponse($this->notificationService->templatesDatatable(), 'Message templates retrieved.');
    }

    public function showTemplate(MessageTemplate $template): JsonResponse
    {
        return $this->successResponse($template, 'Message template retrieved.');
    }

    public function storeTemplate(SaveMessageTemplateRequest $request): JsonResponse
    {
        return $this->successResponse($this->notificationService->saveTemplate(null, $request->validated()), 'Message template added.', 201);
    }

    public function updateTemplate(SaveMessageTemplateRequest $request, MessageTemplate $template): JsonResponse
    {
        return $this->successResponse($this->notificationService->saveTemplate($template, $request->validated()), 'Message template updated.');
    }

    public function destroyTemplate(MessageTemplate $template): JsonResponse
    {
        return $this->deleteWithApproval($template, 'Message template deleted.');
    }

    /* ---------- Delivery log ---------- */

    public function logsDatatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['channel' => ['nullable', Rule::in(MessageTemplate::CHANNELS)], 'status' => ['nullable', Rule::in(['queued', 'sent', 'delivered', 'failed'])]]);

        return $this->successResponse($this->notificationService->logsDatatable($filters), 'Delivery log retrieved.');
    }
}
