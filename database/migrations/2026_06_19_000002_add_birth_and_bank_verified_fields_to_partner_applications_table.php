<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_applications', 'applicant_birth_date')) {
                $table->date('applicant_birth_date')
                    ->nullable()
                    ->after('applicant_email')
                    ->comment('Ngay sinh nguoi dang ky, dung de kiem tra du 18 tuoi.');
            }

            if (! Schema::hasColumn('partner_applications', 'bank_verified_at')) {
                $table->timestamp('bank_verified_at')
                    ->nullable()
                    ->after('bank_verification_status')
                    ->comment('Thoi diem tai khoan ngan hang duoc xac minh tu dong.');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            foreach (['bank_verified_at', 'applicant_birth_date'] as $column) {
                if (Schema::hasColumn('partner_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
