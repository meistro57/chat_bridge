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
        Schema::create('model_prices', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model');
            $table->decimal('prompt_per_million', 12, 6)->nullable();
            $table->decimal('completion_per_million', 12, 6)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_prices');
    }
};
