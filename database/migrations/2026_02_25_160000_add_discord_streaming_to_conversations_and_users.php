<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('discord_webhook_url')->nullable()->after('stop_word_threshold');
            $table->string('discord_thread_id')->nullable()->after('discord_webhook_url');
            $table->boolean('discord_streaming_enabled')->default(false)->after('discord_thread_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_webhook_url')->nullable()->after('notification_preferences');
            $table->boolean('discord_streaming_default')->default(false)->after('discord_webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['discord_webhook_url', 'discord_thread_id', 'discord_streaming_enabled']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['discord_webhook_url', 'discord_streaming_default']);
        });
    }
};
