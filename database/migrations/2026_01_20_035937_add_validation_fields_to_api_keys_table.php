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
        Schema::table('api_keys', function (Blueprint $table) {
            $table->boolean('is_validated')->default(false)->after('is_active');
            $table->timestamp('last_validated_at')->nullable()->after('is_validated');
            $table->text('validation_error')->nullable()->after('last_validated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['is_validated', 'last_validated_at', 'validation_error']);
        });
    }
};
