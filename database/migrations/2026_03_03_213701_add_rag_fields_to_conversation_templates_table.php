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
            $table->boolean('rag_enabled')->default(false)->after('is_public');
            $table->unsignedTinyInteger('rag_source_limit')->default(6)->after('rag_enabled');
            $table->decimal('rag_score_threshold', 3, 2)->default(0.30)->after('rag_source_limit');
            $table->text('rag_system_prompt')->nullable()->after('rag_score_threshold');
            $table->json('rag_files')->nullable()->after('rag_system_prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_templates', function (Blueprint $table) {
            $table->dropColumn([
                'rag_enabled',
                'rag_source_limit',
                'rag_score_threshold',
                'rag_system_prompt',
                'rag_files',
            ]);
        });
    }
};
