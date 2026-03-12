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
        Schema::table('model_prices', function (Blueprint $table) {
            $table->decimal('prompt_per_million', 18, 6)->nullable()->change();
            $table->decimal('completion_per_million', 18, 6)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_prices', function (Blueprint $table) {
            $table->decimal('prompt_per_million', 12, 6)->nullable()->change();
            $table->decimal('completion_per_million', 12, 6)->nullable()->change();
        });
    }
};
