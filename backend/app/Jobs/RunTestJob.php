<?php

namespace App\Jobs;

use App\Models\TestRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly string $testRunId) {}

    /** Execute the test run job. */
    public function handle(): void
    {
        $run = TestRun::findOrFail($this->testRunId);

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $testCase = $run->testCase;
            $version  = $run->promptVersion;

            $compiled = $version->compile($testCase->input_variables ?? []);

            $response = $this->callLlm($compiled);

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

    /** Call the configured LLM provider and return the text response. */
    private function callLlm(string $prompt): string
    {
        $provider = config('services.llm.provider', 'openai');

        return match ($provider) {
            'anthropic' => $this->callAnthropic($prompt),
            default     => $this->callOpenAi($prompt),
        };
    }

    /** Call OpenAI chat completions API. */
    private function callOpenAi(string $prompt): string
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        $response->throw();

        return $response->json('choices.0.message.content');
    }

    /** Call Anthropic messages API. */
    private function callAnthropic(string $prompt): string
    {
        $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(90)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('services.anthropic.model', 'claude-haiku-3-5-20251001'),
                'max_tokens' => 1024,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        $response->throw();

        return $response->json('content.0.text');
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

        $judgeResponse = $this->callLlm($metaPrompt);

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
