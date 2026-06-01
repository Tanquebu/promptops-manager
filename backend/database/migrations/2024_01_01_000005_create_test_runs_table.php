<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('test_case_id')->constrained('test_cases')->cascadeOnDelete();
            $table->foreignUlid('prompt_version_id')->constrained('prompt_versions')->cascadeOnDelete();
            $table->enum('status', ['pending', 'running', 'passed', 'failed', 'error'])->default('pending');
            $table->text('llm_response')->nullable();
            $table->jsonb('evaluation_result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_runs');
    }
};
