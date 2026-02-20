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
        Schema::table('conversation_templates', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->text('starter_message');
            $table->integer('max_rounds')->nullable();
            $table->foreignUuid('persona_a_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignUuid('persona_b_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->boolean('is_public')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('persona_a_id');
            $table->dropConstrainedForeignId('persona_b_id');
            $table->dropColumn([
                'name',
                'category',
                'description',
                'starter_message',
                'max_rounds',
                'is_public',
            ]);
        });
    }
};
