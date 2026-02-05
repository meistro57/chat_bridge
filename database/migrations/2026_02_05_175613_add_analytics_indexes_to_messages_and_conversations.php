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
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_at_index');
            $table->index(['persona_id', 'created_at'], 'messages_persona_created_at_index');
            $table->index('created_at', 'messages_created_at_index');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'conversations_user_created_at_index');
            $table->index(['user_id', 'status'], 'conversations_user_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_created_at_index');
            $table->dropIndex('messages_persona_created_at_index');
            $table->dropIndex('messages_created_at_index');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_user_created_at_index');
            $table->dropIndex('conversations_user_status_index');
        });
    }
};
