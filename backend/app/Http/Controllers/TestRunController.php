<?php

namespace App\Http\Controllers;

use App\Http\Resources\TestRunResource;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Services\PromptService;
use App\Services\TestRunService;
use Illuminate\Http\JsonResponse;

class TestRunController extends Controller
{
    public function __construct(
        private TestRunService $runService,
        private PromptService  $promptService,
    ) {}

    /** Dispatch a test run for the given test case, returns 202. */
    public function run(string $testCaseId): JsonResponse
    {
        $testCase = TestCase::findOrFail($testCaseId);
        $run      = $this->runService->dispatch($testCase);

        return response()->json([
            'data' => ['id' => $run->id, 'status' => $run->status],
        ], 202);
    }

    /** Return full test run data for polling. */
    public function show(string $id): JsonResponse
    {
        $run = TestRun::findOrFail($id);
        return response()->json(['data' => new TestRunResource($run)]);
    }

    /** Return test run history for a prompt, ordered descending. */
    public function history(string $slug): JsonResponse
    {
        $prompt = $this->promptService->findBySlug($slug);

        $runs = TestRun::whereHas('testCase', fn ($q) => $q->where('prompt_id', $prompt->id))
            ->with('testCase')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => TestRunResource::collection($runs)]);
    }
}
