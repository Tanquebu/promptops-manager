<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestRun extends Model
{
    use HasUlids;

    protected $fillable = [
        'test_case_id', 'prompt_version_id', 'status',
        'llm_response', 'evaluation_result', 'error_message',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'evaluation_result' => 'array',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
    ];

    /** Parent test case. */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    /** Prompt version used in this run. */
    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class);
    }
}
