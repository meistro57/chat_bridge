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
            $table->foreignId('persona_a_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('persona_b_id')->nullable()->constrained('personas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('persona_a_id');
            $table->dropConstrainedForeignId('persona_b_id');
        });
    }
};
