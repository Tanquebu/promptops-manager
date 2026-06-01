<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptRequest;
use App\Http\Resources\PromptResource;
use App\Services\PromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptController extends Controller
{
    public function __construct(private PromptService $service) {}

    /** Return paginated list of prompts. */
    public function index(): JsonResponse
    {
        $paginator = $this->service->paginate();

        return response()->json([
            'data' => PromptResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** Create a new prompt. */
    public function store(StorePromptRequest $request): JsonResponse
    {
        $prompt = $this->service->create($request->validated());
        return response()->json(['data' => new PromptResource($prompt)], 201);
    }

    /** Return prompt detail by slug. */
    public function show(string $slug): JsonResponse
    {
        $prompt = $this->service->findBySlug($slug);
        return response()->json(['data' => new PromptResource($prompt)]);
    }

    /** Delete a prompt and cascade. */
    public function destroy(string $slug): JsonResponse
    {
        $prompt = $this->service->findBySlug($slug);
        $prompt->delete();
        return response()->json(null, 204);
    }
}
