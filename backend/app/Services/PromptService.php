<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\PromptEnvironment;
use App\Models\PromptVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PromptService
{
    /** Return paginated list of prompts. */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Prompt::orderByDesc('created_at')->paginate($perPage);
    }

    /** Create a new prompt. */
    public function create(array $data): Prompt
    {
        return Prompt::create([
            'slug'        => $data['slug'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'variables'   => $data['variables'] ?? [],
        ]);
    }

    /** Find a prompt by slug or fail with 404. */
    public function findBySlug(string $slug): Prompt
    {
        return Prompt::where('slug', $slug)->firstOrFail();
    }

    /** Create a new draft version for the given prompt. */
    public function createVersion(Prompt $prompt, string $content): PromptVersion
    {
        return $prompt->versions()->create([
            'version_number' => $prompt->nextVersionNumber(),
            'content'        => $content,
            'status'         => 'draft',
        ]);
    }

    /** Update the status of a prompt version. */
    public function updateVersionStatus(PromptVersion $version, string $status): PromptVersion
    {
        $version->update(['status' => $status]);
        return $version;
    }

    /** Promote a version to an environment (upsert by prompt+environment). */
    public function promote(Prompt $prompt, string $versionId, string $environment, ?string $promotedBy): PromptEnvironment
    {
        return PromptEnvironment::updateOrCreate(
            ['prompt_id' => $prompt->id, 'environment' => $environment],
            [
                'prompt_version_id' => $versionId,
                'promoted_at'       => now(),
                'promoted_by'       => $promotedBy,
            ]
        );
    }

    /** Resolve the active version for a prompt in a given environment. */
    public function resolveVersion(Prompt $prompt, string $environment): ?PromptVersion
    {
        $env = PromptEnvironment::where('prompt_id', $prompt->id)
            ->where('environment', $environment)
            ->first();

        return $env?->version;
    }
}
