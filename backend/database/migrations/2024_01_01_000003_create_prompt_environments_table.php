<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_environments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('prompt_id')->constrained('prompts')->cascadeOnDelete();
            $table->foreignUlid('prompt_version_id')->constrained('prompt_versions')->cascadeOnDelete();
            $table->enum('environment', ['development', 'staging', 'production']);
            $table->timestamp('promoted_at');
            $table->string('promoted_by')->nullable();

            $table->unique(['prompt_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_environments');
    }
};
