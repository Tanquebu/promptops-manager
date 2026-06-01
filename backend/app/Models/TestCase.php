<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestCase extends Model
{
    use HasUlids;

    protected $fillable = [
        'prompt_id', 'name', 'description',
        'input_variables', 'expected_output', 'assertion_type',
    ];

    protected $casts = [
        'input_variables' => 'array',
    ];

    /** Parent prompt. */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /** All test runs for this test case. */
    public function testRuns(): HasMany
    {
        return $this->hasMany(TestRun::class)->orderByDesc('created_at');
    }
}
