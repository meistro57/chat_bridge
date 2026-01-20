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
        Schema::table('conversations', function (Blueprint $table) {
            $table->integer('max_rounds')->default(10)->after('status');
            $table->boolean('stop_word_detection')->default(false)->after('max_rounds');
            $table->json('stop_words')->nullable()->after('stop_word_detection');
            $table->float('stop_word_threshold')->default(0.8)->after('stop_words');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['max_rounds', 'stop_word_detection', 'stop_words', 'stop_word_threshold']);
        });
    }
};
