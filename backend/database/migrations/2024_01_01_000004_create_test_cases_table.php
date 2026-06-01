<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('prompt_id')->constrained('prompts')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('input_variables')->default('{}');
            $table->text('expected_output');
            $table->enum('assertion_type', ['contains', 'not_contains', 'regex', 'llm_judge']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
