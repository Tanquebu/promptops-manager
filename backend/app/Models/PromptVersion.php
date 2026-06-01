<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptVersion extends Model
{
    use HasUlids;

    protected $fillable = ['prompt_id', 'version_number', 'content', 'status'];

    /** Parent prompt. */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /** Environment assignments using this version. */
    public function environments(): HasMany
    {
        return $this->hasMany(PromptEnvironment::class);
    }

    /** Test runs against this version. */
    public function testRuns(): HasMany
    {
        return $this->hasMany(TestRun::class);
    }

    /** Compile prompt by substituting {{var}} placeholders with provided values. */
    public function compile(array $variables): string
    {
        $content = $this->content;
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }
}
