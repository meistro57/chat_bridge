<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('discourse_streaming_enabled')->default(false)->after('discord_streaming_enabled');
            $table->unsignedBigInteger('discourse_topic_id')->nullable()->after('discourse_streaming_enabled');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('discourse_streaming_default')->default(false)->after('discord_streaming_default');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['discourse_streaming_enabled', 'discourse_topic_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['discourse_streaming_default']);
        });
    }
};
