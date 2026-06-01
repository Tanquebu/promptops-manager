<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptEnvironment extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['prompt_id', 'prompt_version_id', 'environment', 'promoted_at', 'promoted_by'];

    protected $casts = [
        'promoted_at' => 'datetime',
    ];

    /** Parent prompt. */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /** Version assigned to this environment. */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'prompt_version_id');
    }
}
