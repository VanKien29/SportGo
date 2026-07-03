<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_membership_tiers', function (Blueprint $table): void {
            if (! Schema::hasColumn('court_membership_tiers', 'tier_label')) {
                $table->string('tier_label', 80)->nullable()->after('tier')
                    ->comment('Ten hien thi tuy chinh cua hang thanh vien.');
            }

            if (! Schema::hasColumn('court_membership_tiers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('tier_label')
                    ->comment('Hang co duoc tinh len hang va hien thi hay khong.');
            }

            if (! Schema::hasColumn('court_membership_tiers', 'voucher_id')) {
                $table->char('voucher_id', 36)->nullable()->after('is_active')
                    ->comment('Voucher di kem hang thanh vien neu co.');
            }
        });

        Schema::table('court_membership_tiers', function (Blueprint $table): void {
            if (Schema::hasColumn('court_membership_tiers', 'voucher_id')) {
                $table->index('voucher_id', 'court_membership_tiers_voucher_id_index');
                $table->foreign('voucher_id', 'court_membership_tiers_voucher_id_foreign')
                    ->references('id')->on('vouchers')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('court_membership_tiers', function (Blueprint $table): void {
            if (Schema::hasColumn('court_membership_tiers', 'voucher_id')) {
                $table->dropForeign('court_membership_tiers_voucher_id_foreign');
                $table->dropIndex('court_membership_tiers_voucher_id_index');
                $table->dropColumn('voucher_id');
            }

            if (Schema::hasColumn('court_membership_tiers', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('court_membership_tiers', 'tier_label')) {
                $table->dropColumn('tier_label');
            }
        });
    }
};
