<?php

namespace Database\Seeders;

use App\Models\Prompt;
use App\Models\PromptEnvironment;
use App\Models\PromptVersion;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** Seed the application's database. */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@promptops.test'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );

        $this->seedSummarizeText();
        $this->seedExtractEntities();
    }

    private function seedSummarizeText(): void
    {
        $prompt = Prompt::firstOrCreate(
            ['slug' => 'summarize-text'],
            [
                'name'        => 'Summarize Text',
                'description' => 'Summarizes a block of text to a given word count in the specified language.',
                'variables'   => ['text', 'max_words', 'language'],
            ]
        );

        $v1 = PromptVersion::firstOrCreate(
            ['prompt_id' => $prompt->id, 'version_number' => 1],
            ['content' => 'Summarize the following text:\n\n{{text}}', 'status' => 'archived']
        );

        $v2 = PromptVersion::firstOrCreate(
            ['prompt_id' => $prompt->id, 'version_number' => 2],
            ['content' => 'Summarize the following text in {{max_words}} words or fewer:\n\n{{text}}', 'status' => 'published']
        );

        $v3 = PromptVersion::firstOrCreate(
            ['prompt_id' => $prompt->id, 'version_number' => 3],
            ['content' => 'Please summarize the following text in {{max_words}} words or fewer. Respond in {{language}}.\n\nText:\n{{text}}', 'status' => 'published']
        );

        PromptEnvironment::updateOrCreate(
            ['prompt_id' => $prompt->id, 'environment' => 'staging'],
            ['prompt_version_id' => $v2->id, 'promoted_at' => now()->subDays(5), 'promoted_by' => 'admin@promptops.test']
        );

        PromptEnvironment::updateOrCreate(
            ['prompt_id' => $prompt->id, 'environment' => 'production'],
            ['prompt_version_id' => $v3->id, 'promoted_at' => now()->subDays(2), 'promoted_by' => 'admin@promptops.test']
        );

        TestCase::firstOrCreate(
            ['prompt_id' => $prompt->id, 'name' => 'Checks summary contains key word'],
            [
                'description'     => 'Summary of a short paragraph should mention "climate".',
                'input_variables' => [
                    'text'      => 'Climate change is one of the most pressing issues of our time, affecting ecosystems worldwide.',
                    'max_words' => '20',
                    'language'  => 'English',
                ],
                'expected_output' => 'climate',
                'assertion_type'  => 'contains',
            ]
        );

        TestCase::firstOrCreate(
            ['prompt_id' => $prompt->id, 'name' => 'LLM judge: coherent summary'],
            [
                'description'     => 'Uses LLM to verify the summary is coherent and relevant.',
                'input_variables' => [
                    'text'      => 'The quick brown fox jumps over the lazy dog. This sentence is often used to test typefaces.',
                    'max_words' => '15',
                    'language'  => 'English',
                ],
                'expected_output' => 'The summary should be coherent, relevant to the input, and under 15 words.',
                'assertion_type'  => 'llm_judge',
            ]
        );
    }

    private function seedExtractEntities(): void
    {
        $prompt = Prompt::firstOrCreate(
            ['slug' => 'extract-entities'],
            [
                'name'        => 'Extract Entities',
                'description' => 'Extracts named entities of specified types from a text.',
                'variables'   => ['text', 'entity_types'],
            ]
        );

        PromptVersion::firstOrCreate(
            ['prompt_id' => $prompt->id, 'version_number' => 1],
            ['content' => 'Extract all {{entity_types}} from the following text. Return a JSON array.\n\nText:\n{{text}}', 'status' => 'archived']
        );

        PromptVersion::firstOrCreate(
            ['prompt_id' => $prompt->id, 'version_number' => 2],
            ['content' => 'From the text below, extract all named entities of the following types: {{entity_types}}.\nReturn ONLY a valid JSON array of strings, e.g. ["Entity1", "Entity2"].\n\nText:\n{{text}}', 'status' => 'published']
        );

        TestCase::firstOrCreate(
            ['prompt_id' => $prompt->id, 'name' => 'Output is valid JSON array'],
            [
                'description'     => 'Checks the output matches a JSON array pattern.',
                'input_variables' => [
                    'text'         => 'Apple Inc. was founded by Steve Jobs in Cupertino.',
                    'entity_types' => 'persons, organizations, locations',
                ],
                'expected_output' => '/^\s*\[.*\]\s*$/s',
                'assertion_type'  => 'regex',
            ]
        );

        TestCase::firstOrCreate(
            ['prompt_id' => $prompt->id, 'name' => 'Does not include raw prose'],
            [
                'description'     => 'Verifies the model does not include explanatory prose.',
                'input_variables' => [
                    'text'         => 'Barack Obama was born in Hawaii.',
                    'entity_types' => 'persons',
                ],
                'expected_output' => 'Here are the entities',
                'assertion_type'  => 'not_contains',
            ]
        );
    }
}
