<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;

class FakeLlmClient implements LlmClient
{
    private array $responses;
    private int $callCount = 0;

    public function __construct(string|array $responses = 'fake llm response')
    {
        $this->responses = is_array($responses) ? $responses : [$responses];
    }

    public function complete(string $prompt): string
    {
        $index = min($this->callCount, count($this->responses) - 1);
        $this->callCount++;

        return $this->responses[$index];
    }

    public function callCount(): int
    {
        return $this->callCount;
    }
}
