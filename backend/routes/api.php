<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\PromptEnvironmentController;
use App\Http\Controllers\PromptVersionController;
use App\Http\Controllers\TestCaseController;
use App\Http\Controllers\TestRunController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/auth/login', [AuthController::class, 'login']);

// Public resolve endpoint
Route::get('/prompts/{slug}/resolve', [PromptEnvironmentController::class, 'resolve']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Prompts
    Route::get('/prompts', [PromptController::class, 'index']);
    Route::post('/prompts', [PromptController::class, 'store']);
    Route::get('/prompts/{slug}', [PromptController::class, 'show']);
    Route::delete('/prompts/{slug}', [PromptController::class, 'destroy']);

    // Versions
    Route::get('/prompts/{slug}/versions', [PromptVersionController::class, 'index']);
    Route::post('/prompts/{slug}/versions', [PromptVersionController::class, 'store']);
    Route::patch('/prompts/{slug}/versions/{id}', [PromptVersionController::class, 'update']);

    // Environments
    Route::get('/prompts/{slug}/environments', [PromptEnvironmentController::class, 'index']);
    Route::post('/prompts/{slug}/promote', [PromptEnvironmentController::class, 'promote']);

    // Test cases
    Route::get('/prompts/{slug}/test-cases', [TestCaseController::class, 'index']);
    Route::post('/prompts/{slug}/test-cases', [TestCaseController::class, 'store']);
    Route::delete('/test-cases/{id}', [TestCaseController::class, 'destroy']);

    // Test runs
    Route::post('/test-cases/{id}/run', [TestRunController::class, 'run']);
    Route::get('/test-runs/{id}', [TestRunController::class, 'show']);
    Route::get('/prompts/{slug}/test-runs', [TestRunController::class, 'history']);
});
