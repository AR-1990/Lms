<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Services\AttachmentService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private AttachmentService $attachmentService) {}

    /**
     * Files of one record: ?owner=users&owner_id=5
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner' => ['required', 'string', Rule::in($this->attachmentService->ownerKeys())],
            'owner_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->authorizeOwner($request, $validated['owner']);
        $owner = $this->attachmentService->resolveOwner($validated['owner'], (int) $validated['owner_id']);

        return $this->successResponse(
            AttachmentResource::collection($this->attachmentService->forOwner($owner))->resolve(),
            'Attachments retrieved.',
        );
    }

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $owner = $this->attachmentService->resolveOwner($request->validated('owner'), (int) $request->validated('owner_id'));

        $attachment = $this->attachmentService->store(
            $owner,
            $request->validated('owner'),
            $request->file('file'),
            $request->safe()->except(['owner', 'owner_id', 'file']),
        );

        return $this->successResponse(new AttachmentResource($attachment), 'File uploaded.', 201);
    }

    public function show(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorizeAttachment($request, $attachment);

        return $this->successResponse(new AttachmentResource($attachment->load('documentType:id,name')), 'Attachment retrieved.');
    }

    public function verify(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorizeAttachment($request, $attachment);
        $validated = $request->validate(['is_verified' => ['required', 'boolean']]);

        $attachment = $this->attachmentService->setVerified($attachment, (bool) $validated['is_verified']);

        return $this->successResponse(new AttachmentResource($attachment), $attachment->is_verified ? 'Marked as verified.' : 'Verification removed.');
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorizeAttachment($request, $attachment);

        return $this->deleteWithApproval($attachment, 'File deleted.');
    }

    /**
     * Signed-URL download (no token needed; the signature expires after a few minutes).
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        return $this->attachmentService->response($attachment);
    }

    private function authorizeOwner(Request $request, string $owner): void
    {
        abort_unless($this->attachmentService->canManage($request->user(), $owner), 403, 'You do not have permission to manage these files.');
    }

    private function authorizeAttachment(Request $request, Attachment $attachment): void
    {
        $owner = $this->attachmentService->ownerKeyOf($attachment);

        abort_if($owner === null, 404);
        $this->authorizeOwner($request, $owner);
    }
}
