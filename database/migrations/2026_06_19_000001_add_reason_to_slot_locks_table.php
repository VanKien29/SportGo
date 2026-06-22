<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('slot_locks', 'reason')) {
            Schema::table('slot_locks', function (Blueprint $table): void {
                $table->text('reason')->nullable()->after('lock_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('slot_locks', 'reason')) {
            Schema::table('slot_locks', function (Blueprint $table): void {
                $table->dropColumn('reason');
            });
        }
    }
};
