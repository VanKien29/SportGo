<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot_locks', function (Blueprint $table) {
            $table->string('reason', 500)->nullable()->after('lock_type')
                ->comment('Lý do khóa lịch thủ công như bảo trì, nghỉ hoặc sự kiện riêng.');
        });
    }

    public function down(): void
    {
        Schema::table('slot_locks', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
