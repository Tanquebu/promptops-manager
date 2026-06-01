<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptVersionRequest;
use App\Http\Requests\UpdatePromptVersionRequest;
use App\Http\Resources\PromptVersionResource;
use App\Models\PromptVersion;
use App\Services\PromptService;
use Illuminate\Http\JsonResponse;

class PromptVersionController extends Controller
{
    public function __construct(private PromptService $service) {}

    /** List all versions for a prompt, ordered descending. */
    public function index(string $slug): JsonResponse
    {
        $prompt   = $this->service->findBySlug($slug);
        $versions = $prompt->versions()->get();
        return response()->json(['data' => PromptVersionResource::collection($versions)]);
    }

    /** Create a new draft version. */
    public function store(StorePromptVersionRequest $request, string $slug): JsonResponse
    {
        $prompt  = $this->service->findBySlug($slug);
        $version = $this->service->createVersion($prompt, $request->validated('content'));
        return response()->json(['data' => new PromptVersionResource($version)], 201);
    }

    /** Update status of a version. */
    public function update(UpdatePromptVersionRequest $request, string $slug, string $id): JsonResponse
    {
        $this->service->findBySlug($slug);
        $version = PromptVersion::findOrFail($id);
        $version = $this->service->updateVersionStatus($version, $request->validated('status'));
        return response()->json(['data' => new PromptVersionResource($version)]);
    }
}
