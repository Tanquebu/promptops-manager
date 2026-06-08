<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromptVersionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Prompt $prompt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => Hash::make('secret'),
        ]);

        $this->prompt = Prompt::create([
            'slug'      => 'version-test-prompt',
            'name'      => 'Version Test Prompt',
            'variables' => [],
        ]);
    }

    public function test_index_returns_versions_for_prompt(): void
    {
        PromptVersion::create(['prompt_id' => $this->prompt->id, 'version_number' => 1, 'content' => 'v1', 'status' => 'archived']);
        PromptVersion::create(['prompt_id' => $this->prompt->id, 'version_number' => 2, 'content' => 'v2', 'status' => 'draft']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/prompts/{$this->prompt->slug}/versions");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_store_creates_draft_version(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/prompts/{$this->prompt->slug}/versions", [
                'content' => 'Summarize {{text}} in {{words}} words.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version_number', 1);
    }

    public function test_store_auto_increments_version_number(): void
    {
        PromptVersion::create(['prompt_id' => $this->prompt->id, 'version_number' => 1, 'content' => 'v1', 'status' => 'draft']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/prompts/{$this->prompt->slug}/versions", [
                'content' => 'Second version content.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.version_number', 2);
    }

    public function test_update_changes_version_status(): void
    {
        $version = PromptVersion::create([
            'prompt_id'      => $this->prompt->id,
            'version_number' => 1,
            'content'        => 'Draft content.',
            'status'         => 'draft',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/prompts/{$this->prompt->slug}/versions/{$version->id}", [
                'status' => 'published',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_update_rejects_invalid_status(): void
    {
        $version = PromptVersion::create([
            'prompt_id'      => $this->prompt->id,
            'version_number' => 1,
            'content'        => 'Draft content.',
            'status'         => 'draft',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/prompts/{$this->prompt->slug}/versions/{$version->id}", [
                'status' => 'invalid-status',
            ])
            ->assertStatus(422);
    }
}
