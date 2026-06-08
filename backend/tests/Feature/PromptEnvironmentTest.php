<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptEnvironment;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromptEnvironmentTest extends TestCase
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
            'slug'      => 'env-test-prompt',
            'name'      => 'Env Test Prompt',
            'variables' => ['topic'],
        ]);

        $this->version = PromptVersion::create([
            'prompt_id'      => $this->prompt->id,
            'version_number' => 1,
            'content'        => 'Tell me about {{topic}}.',
            'status'         => 'published',
        ]);
    }

    public function test_index_returns_environment_assignments(): void
    {
        PromptEnvironment::create([
            'prompt_id'         => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'environment'       => 'staging',
            'promoted_at'       => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/prompts/{$this->prompt->slug}/environments");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('staging', $response->json('data.0.environment'));
    }

    public function test_promote_assigns_version_to_environment(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/prompts/{$this->prompt->slug}/promote", [
                'version_id'  => $this->version->id,
                'environment' => 'production',
                'promoted_by' => 'ci-pipeline',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.environment', 'production');

        $this->assertDatabaseHas('prompt_environments', [
            'prompt_id'         => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'environment'       => 'production',
        ]);
    }

    public function test_promote_upserts_existing_environment(): void
    {
        $v2 = PromptVersion::create([
            'prompt_id'      => $this->prompt->id,
            'version_number' => 2,
            'content'        => 'Updated content about {{topic}}.',
            'status'         => 'published',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/prompts/{$this->prompt->slug}/promote", [
                'version_id'  => $this->version->id,
                'environment' => 'staging',
            ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/prompts/{$this->prompt->slug}/promote", [
                'version_id'  => $v2->id,
                'environment' => 'staging',
            ]);

        $this->assertDatabaseCount('prompt_environments', 1);
        $this->assertDatabaseHas('prompt_environments', [
            'environment'       => 'staging',
            'prompt_version_id' => $v2->id,
        ]);
    }

    public function test_promote_rejects_invalid_environment(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/prompts/{$this->prompt->slug}/promote", [
                'version_id'  => $this->version->id,
                'environment' => 'qa',
            ])
            ->assertStatus(422);
    }

    public function test_resolve_returns_compiled_content_without_auth(): void
    {
        PromptEnvironment::create([
            'prompt_id'         => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'environment'       => 'production',
            'promoted_at'       => now(),
        ]);

        $response = $this->getJson("/api/prompts/{$this->prompt->slug}/resolve?env=production");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['slug', 'environment', 'version_id', 'content', 'variables']]);
    }

    public function test_resolve_returns_404_when_no_version_promoted(): void
    {
        $response = $this->getJson("/api/prompts/{$this->prompt->slug}/resolve?env=production");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_VERSION_FOR_ENVIRONMENT');
    }
}
