<?php

namespace App\Services;

use App\Jobs\RunTestJob;
use App\Models\Prompt;
use App\Models\TestCase;
use App\Models\TestRun;

class TestRunService
{
    /** Create a pending TestRun and dispatch the job, returning the run. */
    public function dispatch(TestCase $testCase): TestRun
    {
        $prompt = $testCase->prompt;

        $version = $this->resolveVersion($prompt);

        $run = TestRun::create([
            'test_case_id'      => $testCase->id,
            'prompt_version_id' => $version->id,
            'status'            => 'pending',
        ]);

        RunTestJob::dispatch($run->id);

        return $run;
    }

    /** Resolve the best version to use: dev env → latest published → latest any. */
    private function resolveVersion(Prompt $prompt): \App\Models\PromptVersion
    {
        $devEnv = $prompt->environments()->where('environment', 'development')->first();
        if ($devEnv) {
            return $devEnv->version;
        }

        $published = $prompt->versions()
            ->where('status', 'published')
            ->orderByDesc('version_number')
            ->first();
        if ($published) {
            return $published;
        }

        return $prompt->versions()->orderByDesc('version_number')->firstOrFail();
    }
}
