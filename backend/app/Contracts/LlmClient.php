<?php

namespace App\Contracts;

interface LlmClient
{
    public function complete(string $prompt): string;
}
