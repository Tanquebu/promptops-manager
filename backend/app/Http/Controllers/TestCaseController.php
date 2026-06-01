<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTestCaseRequest;
use App\Http\Resources\TestCaseResource;
use App\Models\TestCase;
use App\Services\PromptService;
use Illuminate\Http\JsonResponse;

class TestCaseController extends Controller
{
    public function __construct(private PromptService $service) {}

    /** List test cases for a prompt. */
    public function index(string $slug): JsonResponse
    {
        $prompt    = $this->service->findBySlug($slug);
        $testCases = $prompt->testCases()->get();
        return response()->json(['data' => TestCaseResource::collection($testCases)]);
    }

    /** Create a new test case for a prompt. */
    public function store(StoreTestCaseRequest $request, string $slug): JsonResponse
    {
        $prompt   = $this->service->findBySlug($slug);
        $testCase = $prompt->testCases()->create($request->validated());
        return response()->json(['data' => new TestCaseResource($testCase)], 201);
    }

    /** Delete a test case by id. */
    public function destroy(string $id): JsonResponse
    {
        $testCase = TestCase::findOrFail($id);
        $testCase->delete();
        return response()->json(null, 204);
    }
}
