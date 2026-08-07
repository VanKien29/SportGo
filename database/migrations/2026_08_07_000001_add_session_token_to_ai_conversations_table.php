<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_conversations')) {
            Schema::table('ai_conversations', function (Blueprint $table) {
                if (! Schema::hasColumn('ai_conversations', 'session_token')) {
                    $table->string('session_token', 64)->nullable()->after('user_id')->index();
                }
            });

            // Make user_id nullable for guest conversations
            try {
                Schema::table('ai_conversations', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Ignore if DB driver doesn't allow direct change
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_conversations')) {
            Schema::table('ai_conversations', function (Blueprint $table) {
                if (Schema::hasColumn('ai_conversations', 'session_token')) {
                    $table->dropColumn('session_token');
                }
            });
        }
    }
};
