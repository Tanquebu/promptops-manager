<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => Hash::make('secret'),
        ]);
    }

    private function createPrompt(string $slug = 'test-prompt'): Prompt
    {
        return Prompt::create([
            'slug'        => $slug,
            'name'        => 'Test Prompt',
            'description' => 'A test prompt',
            'variables'   => ['var1'],
        ]);
    }

    public function test_index_returns_paginated_prompts(): void
    {
        foreach (['prompt-one', 'prompt-two', 'prompt-three'] as $slug) {
            $this->createPrompt($slug);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/prompts');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page', 'last_page', 'per_page']])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/prompts')->assertStatus(401);
    }

    public function test_store_creates_prompt(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/prompts', [
                'slug'      => 'my-new-prompt',
                'name'      => 'My New Prompt',
                'variables' => ['topic', 'length'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'my-new-prompt');

        $this->assertDatabaseHas('prompts', ['slug' => 'my-new-prompt']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        $this->createPrompt('existing-slug');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/prompts', [
                'slug' => 'existing-slug',
                'name' => 'Another Prompt',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertNotEmpty($response->json('error.details.slug'));
    }

    public function test_store_rejects_invalid_slug_format(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/prompts', [
                'slug' => 'Invalid Slug With Spaces',
                'name' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_show_returns_prompt_by_slug(): void
    {
        $this->createPrompt('find-me');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/prompts/find-me');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'find-me');
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/prompts/does-not-exist')
            ->assertStatus(404);
    }

    public function test_destroy_deletes_prompt(): void
    {
        $this->createPrompt('to-delete');

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/prompts/to-delete')
            ->assertStatus(204);

        $this->assertDatabaseMissing('prompts', ['slug' => 'to-delete']);
    }
}
