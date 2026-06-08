<?php

namespace App\Providers;

use App\Contracts\LlmClient;
use App\Services\Llm\AnthropicClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\OpenAiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmClient::class, function () {
            return match (config('services.llm.provider', 'openai')) {
                'anthropic' => new AnthropicClient(
                    config('services.anthropic.key'),
                    config('services.anthropic.model', 'claude-haiku-3-5-20251001'),
                ),
                'fake' => new FakeLlmClient(),
                default => new OpenAiClient(
                    config('services.openai.key'),
                    config('services.openai.model', 'gpt-4o-mini'),
                ),
            };
        });
    }
}
