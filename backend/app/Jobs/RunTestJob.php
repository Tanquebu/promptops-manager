<?php

namespace App\Jobs;

use App\Contracts\LlmClient;
use App\Models\TestRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    private LlmClient $llm;

    public function __construct(public readonly string $testRunId) {}

    /** Execute the test run job. */
    public function handle(LlmClient $llm): void
    {
        $this->llm = $llm;

        $run = TestRun::findOrFail($this->testRunId);

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $testCase = $run->testCase;
            $version  = $run->promptVersion;

            $compiled = $version->compile($testCase->input_variables ?? []);

            $response = $this->llm->complete($compiled);

            $evaluation = $this->evaluate($response, $testCase->expected_output, $testCase->assertion_type);

            $run->update([
                'status'            => $evaluation['passed'] ? 'passed' : 'failed',
                'llm_response'      => $response,
                'evaluation_result' => $evaluation,
                'completed_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('RunTestJob failed', ['run_id' => $this->testRunId, 'error' => $e->getMessage()]);
            $run->update([
                'status'        => 'error',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }
    }

    /** Evaluate an LLM response against expected output using the given assertion type. */
    private function evaluate(string $response, string $expected, string $assertionType): array
    {
        return match ($assertionType) {
            'contains'     => $this->evaluateContains($response, $expected),
            'not_contains' => $this->evaluateNotContains($response, $expected),
            'regex'        => $this->evaluateRegex($response, $expected),
            'llm_judge'    => $this->evaluateLlmJudge($response, $expected),
            default        => ['passed' => false, 'reason' => "Unknown assertion type: {$assertionType}"],
        };
    }

    /** Check that response contains expected string (case-insensitive). */
    private function evaluateContains(string $response, string $expected): array
    {
        $passed = str_contains(strtolower($response), strtolower($expected));
        return ['passed' => $passed, 'reason' => $passed ? 'Response contains expected string' : 'Response does not contain expected string'];
    }

    /** Check that response does not contain expected string (case-insensitive). */
    private function evaluateNotContains(string $response, string $expected): array
    {
        $passed = !str_contains(strtolower($response), strtolower($expected));
        return ['passed' => $passed, 'reason' => $passed ? 'Response does not contain the forbidden string' : 'Response contains the forbidden string'];
    }

    /** Check that response matches the expected regex pattern. */
    private function evaluateRegex(string $response, string $expected): array
    {
        $passed = (bool) preg_match($expected, $response);
        return ['passed' => $passed, 'reason' => $passed ? 'Response matches pattern' : 'Response does not match pattern'];
    }

    /** Use a second LLM call to judge whether the response satisfies the expected output. */
    private function evaluateLlmJudge(string $response, string $expected): array
    {
        $metaPrompt = <<<PROMPT
You are an evaluator. Given this LLM response: "{$response}"
Does it satisfy this requirement: "{$expected}"?
Answer ONLY with valid JSON: {"passed": true/false, "reason": "brief explanation"}
PROMPT;

        $judgeResponse = $this->llm->complete($metaPrompt);

        $decoded = json_decode(trim($judgeResponse), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['passed' => false, 'reason' => 'LLM judge returned invalid JSON: ' . $judgeResponse];
        }

        return [
            'passed' => (bool) ($decoded['passed'] ?? false),
            'reason' => $decoded['reason'] ?? '',
        ];
    }
}
