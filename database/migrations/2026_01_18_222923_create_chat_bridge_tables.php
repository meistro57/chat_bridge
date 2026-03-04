<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_bridge_threads', function (Blueprint $table) {
            $table->id();
            $table->string('bridge_thread_id')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_bridge_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_bridge_threads')->cascadeOnDelete();
            $table->string('role'); // user, assistant, system
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_bridge_messages');
        Schema::dropIfExists('chat_bridge_threads');
    }
};
