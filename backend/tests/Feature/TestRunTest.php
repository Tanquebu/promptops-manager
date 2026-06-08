<?php

namespace Tests\Feature;

use App\Contracts\LlmClient;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\TestCase as TestCaseModel;
use App\Models\User;
use App\Services\Llm\FakeLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TestRunTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Prompt $prompt;
    private PromptVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => Hash::make('secret'),
        ]);

        $this->prompt = Prompt::create([
            'slug'      => 'run-test-prompt',
            'name'      => 'Run Test Prompt',
            'variables' => ['color'],
        ]);

        $this->version = PromptVersion::create([
            'prompt_id'      => $this->prompt->id,
            'version_number' => 1,
            'content'        => 'Describe the color {{color}}.',
            'status'         => 'published',
        ]);
    }

    private function createTestCase(string $assertionType = 'contains', string $expected = 'blue'): TestCaseModel
    {
        return $this->prompt->testCases()->create([
            'name'            => 'Sample test case',
            'input_variables' => ['color' => 'blue'],
            'expected_output' => $expected,
            'assertion_type'  => $assertionType,
        ]);
    }

    public function test_store_test_case_requires_auth(): void
    {
        $this->postJson("/api/prompts/{$this->prompt->slug}/test-cases", [
            'name'            => 'Test',
            'expected_output' => 'something',
            'assertion_type'  => 'contains',
        ])->assertStatus(401);
    }

    public function test_store_test_case_creates_record(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/prompts/{$this->prompt->slug}/test-cases", [
                'name'            => 'Color check',
                'input_variables' => ['color' => 'blue'],
                'expected_output' => 'blue',
                'assertion_type'  => 'contains',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('test_cases', [
            'prompt_id'      => $this->prompt->id,
            'assertion_type' => 'contains',
        ]);
    }

    public function test_store_test_case_validates_assertion_type(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/prompts/{$this->prompt->slug}/test-cases", [
                'name'            => 'Invalid',
                'expected_output' => 'something',
                'assertion_type'  => 'invalid_type',
            ])
            ->assertStatus(422);
    }

    public function test_run_dispatches_and_returns_pending(): void
    {
        Queue::fake();
        $testCase = $this->createTestCase();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_run_with_contains_assertion_passes(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient('The sky is bright blue today.'));
        $testCase = $this->createTestCase('contains', 'blue');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run")
            ->assertStatus(202);

        $run = $testCase->testRuns()->latest()->first();
        $this->assertEquals('passed', $run->status);
        $this->assertTrue($run->evaluation_result['passed']);
    }

    public function test_run_with_contains_assertion_fails(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient('The sky is green and cloudy.'));
        $testCase = $this->createTestCase('contains', 'blue');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $run = $testCase->testRuns()->latest()->first();
        $this->assertEquals('failed', $run->status);
        $this->assertFalse($run->evaluation_result['passed']);
    }

    public function test_run_with_not_contains_assertion_passes(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient('The sky is azure and clear.'));
        $testCase = $this->createTestCase('not_contains', 'error');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $run = $testCase->testRuns()->latest()->first();
        $this->assertEquals('passed', $run->status);
    }

    public function test_run_with_regex_assertion_passes(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient('42'));
        $testCase = $this->createTestCase('regex', '/^\d+$/');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $run = $testCase->testRuns()->latest()->first();
        $this->assertEquals('passed', $run->status);
    }

    public function test_run_with_regex_assertion_fails(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient('not a number'));
        $testCase = $this->createTestCase('regex', '/^\d+$/');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $run = $testCase->testRuns()->latest()->first();
        $this->assertEquals('failed', $run->status);
    }

    public function test_run_with_llm_judge_passes(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient([
            'Blue is a calm and cool color.',
            '{"passed": true, "reason": "Response correctly describes blue."}',
        ]));
        $testCase = $this->createTestCase('llm_judge', 'The response should describe a color correctly.');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $run = $testCase->testRuns()->latest()->first();
        $this->assertEquals('passed', $run->status);
        $this->assertEquals('Response correctly describes blue.', $run->evaluation_result['reason']);
    }

    public function test_run_with_llm_judge_fails(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient([
            'I have no idea.',
            '{"passed": false, "reason": "Response is not relevant."}',
        ]));
        $testCase = $this->createTestCase('llm_judge', 'The response should describe a color correctly.');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $run = $testCase->testRuns()->latest()->first();
        $this->assertEquals('failed', $run->status);
        $this->assertFalse($run->evaluation_result['passed']);
    }

    public function test_run_marks_error_on_llm_exception(): void
    {
        $failingClient = new class implements LlmClient {
            public function complete(string $prompt): string
            {
                throw new \RuntimeException('LLM API timeout');
            }
        };
        $this->instance(LlmClient::class, $failingClient);

        $testCase = $this->createTestCase();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $run = $testCase->testRuns()->latest()->first();
        $this->assertEquals('error', $run->status);
        $this->assertNotNull($run->error_message);
    }

    public function test_show_returns_test_run_data(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient('Blue is a primary color.'));
        $testCase = $this->createTestCase();

        $runResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/test-cases/{$testCase->id}/run");

        $runId = $runResponse->json('data.id');

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/test-runs/{$runId}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'status', 'llm_response', 'evaluation_result']]);
    }

    public function test_history_returns_runs_for_prompt(): void
    {
        $this->instance(LlmClient::class, new FakeLlmClient('Blue is nice.'));
        $testCase = $this->createTestCase();

        $this->actingAs($this->user, 'sanctum')->postJson("/api/test-cases/{$testCase->id}/run");
        $this->actingAs($this->user, 'sanctum')->postJson("/api/test-cases/{$testCase->id}/run");

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/prompts/{$this->prompt->slug}/test-runs");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}
