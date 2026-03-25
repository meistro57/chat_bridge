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
            $table->boolean('is_favorite')->default(false)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_templates', function (Blueprint $table) {
            $table->dropColumn('is_favorite');
        });
    }
};
