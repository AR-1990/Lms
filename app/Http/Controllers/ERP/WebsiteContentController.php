<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\SaveWebsiteContentRequest;
use App\Http\Resources\WebsiteContentResource;
use App\Services\WebsiteContentService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public website content, one controller for every type in the URL: stats, testimonials, posts, faqs.
 */
class WebsiteContentController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private WebsiteContentService $websiteService) {}

    public function datatable(string $type): JsonResponse
    {
        $this->websiteService->modelFor($type);

        return $this->successResponse($this->websiteService->datatable($type), 'Content retrieved.');
    }

    public function show(string $type, int $id): JsonResponse
    {
        return $this->successResponse(new WebsiteContentResource($this->websiteService->find($type, $id)), 'Content retrieved.');
    }

    public function store(SaveWebsiteContentRequest $request, string $type): JsonResponse
    {
        return $this->successResponse(new WebsiteContentResource($this->websiteService->save($type, null, $request->validated())), 'Saved to the website.', 201);
    }

    public function update(SaveWebsiteContentRequest $request, string $type, int $id): JsonResponse
    {
        return $this->successResponse(new WebsiteContentResource($this->websiteService->save($type, $this->websiteService->find($type, $id), $request->validated())), 'Website updated.');
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        return $this->deleteWithApproval($this->websiteService->find($type, $id), 'Removed from the website.');
    }

    public function image(Request $request, int $id): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:4096']]);
        $post = $this->websiteService->uploadPostImage($this->websiteService->find('posts', $id), $request->file('image'));

        return $this->successResponse(new WebsiteContentResource($post), 'Picture uploaded.');
    }
}
