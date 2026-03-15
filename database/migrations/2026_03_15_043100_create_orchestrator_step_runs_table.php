<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orchestrator_step_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('orchestrator_runs')->cascadeOnDelete();
            $table->foreignUuid('step_id')->constrained('orchestration_steps')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('output_summary')->nullable();
            $table->boolean('condition_passed')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orchestrator_step_runs');
    }
};
