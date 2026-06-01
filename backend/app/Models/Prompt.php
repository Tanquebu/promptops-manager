<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prompt extends Model
{
    use HasUlids;

    protected $fillable = ['slug', 'name', 'description', 'variables'];

    protected $casts = [
        'variables' => 'array',
    ];

    /** All versions for this prompt. */
    public function versions(): HasMany
    {
        return $this->hasMany(PromptVersion::class)->orderByDesc('version_number');
    }

    /** Current environment assignments. */
    public function environments(): HasMany
    {
        return $this->hasMany(PromptEnvironment::class);
    }

    /** All test cases for this prompt. */
    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }

    /** Next version number (auto-increment per prompt). */
    public function nextVersionNumber(): int
    {
        return ($this->versions()->max('version_number') ?? 0) + 1;
    }
}
