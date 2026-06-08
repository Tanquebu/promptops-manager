<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;

class OpenAiClient implements LlmClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $prompt): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        $response->throw();

        return $response->json('choices.0.message.content');
    }
}
