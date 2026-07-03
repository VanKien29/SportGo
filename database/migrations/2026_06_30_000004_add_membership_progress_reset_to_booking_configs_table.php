<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_configs', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_configs', 'reset_membership_progress_on_upgrade')) {
                $table->boolean('reset_membership_progress_on_upgrade')
                    ->default(false)
                    ->after('deposit_percent')
                    ->comment('Reset booking va chi tieu tich luy sau khi khach len hang.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_configs', function (Blueprint $table): void {
            if (Schema::hasColumn('booking_configs', 'reset_membership_progress_on_upgrade')) {
                $table->dropColumn('reset_membership_progress_on_upgrade');
            }
        });
    }
};
