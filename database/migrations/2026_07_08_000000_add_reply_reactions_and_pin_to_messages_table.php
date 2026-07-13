<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->char('reply_to_id', 36)->nullable()->after('conversation_id');
            $table->json('reactions')->nullable()->after('reference_id');
            $table->boolean('is_pinned')->default(false)->after('reactions');
            
            $table->foreign('reply_to_id')->references('id')->on('messages')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropColumn(['reply_to_id', 'reactions', 'is_pinned']);
        });
    }
};
