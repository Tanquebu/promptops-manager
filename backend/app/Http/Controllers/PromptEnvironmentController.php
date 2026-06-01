<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotePromptRequest;
use App\Http\Resources\PromptEnvironmentResource;
use App\Services\PromptService;
use Illuminate\Http\JsonResponse;

class PromptEnvironmentController extends Controller
{
    public function __construct(private PromptService $service) {}

    /** Return current environment assignments for a prompt. */
    public function index(string $slug): JsonResponse
    {
        $prompt = $this->service->findBySlug($slug);
        $envs   = $prompt->environments()->with('version')->get();
        return response()->json(['data' => PromptEnvironmentResource::collection($envs)]);
    }

    /** Promote a version to an environment. */
    public function promote(PromotePromptRequest $request, string $slug): JsonResponse
    {
        $prompt = $this->service->findBySlug($slug);
        $env    = $this->service->promote(
            $prompt,
            $request->validated('version_id'),
            $request->validated('environment'),
            $request->validated('promoted_by'),
        );
        $env->load('version');
        return response()->json(['data' => new PromptEnvironmentResource($env)]);
    }

    /** Resolve the compiled prompt for an environment (public endpoint). */
    public function resolve(string $slug): JsonResponse
    {
        $environment = request()->query('env', 'production');
        $prompt      = $this->service->findBySlug($slug);
        $version     = $this->service->resolveVersion($prompt, $environment);

        if (!$version) {
            return response()->json([
                'error' => [
                    'message' => "No version promoted to {$environment}",
                    'code'    => 'NO_VERSION_FOR_ENVIRONMENT',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'slug'        => $prompt->slug,
                'environment' => $environment,
                'version_id'  => $version->id,
                'content'     => $version->content,
                'variables'   => $prompt->variables,
            ],
        ]);
    }
}
