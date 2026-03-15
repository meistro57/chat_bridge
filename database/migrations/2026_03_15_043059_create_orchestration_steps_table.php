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
        Schema::create('orchestration_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orchestration_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('label')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('conversation_templates')->nullOnDelete();

            // Persona / provider overrides
            $table->foreignUuid('persona_a_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignUuid('persona_b_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->string('provider_a')->nullable();
            $table->string('model_a')->nullable();
            $table->string('provider_b')->nullable();
            $table->string('model_b')->nullable();

            // Input wiring
            $table->string('input_source')->default('static');
            $table->text('input_value')->nullable();
            $table->string('input_variable_name')->nullable();

            // Output wiring
            $table->string('output_action')->default('log');
            $table->string('output_variable_name')->nullable();
            $table->string('output_webhook_url')->nullable();

            // Control flow
            $table->json('condition')->nullable();
            $table->boolean('pause_before_run')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orchestration_steps');
    }
};
