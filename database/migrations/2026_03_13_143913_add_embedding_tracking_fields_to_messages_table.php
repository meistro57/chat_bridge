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
            $table->string('embedding_status')->nullable()->after('embedding');
            $table->unsignedInteger('embedding_attempts')->default(0)->after('embedding_status');
            $table->text('embedding_last_error')->nullable()->after('embedding_attempts');
            $table->string('embedding_skip_reason')->nullable()->after('embedding_last_error');
            $table->timestamp('embedding_last_attempt_at')->nullable()->after('embedding_skip_reason');
            $table->timestamp('embedding_next_retry_at')->nullable()->after('embedding_last_attempt_at');

            $table->index('embedding_status');
            $table->index('embedding_next_retry_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['embedding_status']);
            $table->dropIndex(['embedding_next_retry_at']);
            $table->dropColumn([
                'embedding_status',
                'embedding_attempts',
                'embedding_last_error',
                'embedding_skip_reason',
                'embedding_last_attempt_at',
                'embedding_next_retry_at',
            ]);
        });
    }
};
