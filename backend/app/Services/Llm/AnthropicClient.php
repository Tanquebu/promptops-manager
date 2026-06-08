<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;

class AnthropicClient implements LlmClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $prompt): string
    {
        $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(90)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        $response->throw();

        return $response->json('content.0.text');
    }
}
