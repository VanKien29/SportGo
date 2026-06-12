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
            $this->stringColumn($table, 'applicant_full_name', 255, 'user_id');
            $this->stringColumn($table, 'applicant_phone', 30, 'applicant_full_name');
            $this->stringColumn($table, 'applicant_email', 255, 'applicant_phone');
            $this->textColumn($table, 'applicant_address', 'applicant_email');
            $this->stringColumn($table, 'applicant_type', 50, 'applicant_address');
            $this->stringColumn($table, 'representative_name', 255, 'applicant_type');
            $this->stringColumn($table, 'representative_identity_type', 50, 'representative_name');
            $this->stringColumn($table, 'representative_identity_number', 50, 'representative_identity_type');
            $this->dateColumn($table, 'representative_identity_issued_date', 'representative_identity_number');
            $this->stringColumn($table, 'representative_identity_issued_place', 255, 'representative_identity_issued_date');
            $this->stringColumn($table, 'representative_position', 150, 'representative_identity_issued_place');
            $this->stringColumn($table, 'business_code', 100, 'tax_code');
            $this->stringColumn($table, 'business_license_number', 100, 'business_code');
            $this->textColumn($table, 'business_address', 'business_license_number');
            $this->stringColumn($table, 'business_representative_name', 255, 'business_address');
            $this->stringColumn($table, 'business_representative_position', 150, 'business_representative_name');
            $this->stringColumn($table, 'venue_province', 100, 'venue_address');
            $this->stringColumn($table, 'venue_district', 100, 'venue_province');
            $this->stringColumn($table, 'venue_ward', 100, 'venue_district');
            $this->stringColumn($table, 'venue_phone', 30, 'venue_longitude');
            $this->stringColumn($table, 'venue_email', 255, 'venue_phone');
            $this->textColumn($table, 'venue_description', 'venue_email');
            $this->stringColumn($table, 'expected_opening_hours', 255, 'venue_description');
            $this->textColumn($table, 'parking_info', 'expected_opening_hours');
            $this->jsonColumn($table, 'amenities', 'parking_info');
            $this->integerColumn($table, 'court_count_total', 'amenities');
            $this->stringColumn($table, 'bank_name', 150, 'court_count_total');
            $this->stringColumn($table, 'bank_code', 50, 'bank_name');
            $this->stringColumn($table, 'account_number', 50, 'bank_code');
            $this->stringColumn($table, 'account_holder_name', 255, 'account_number');
            $this->stringColumn($table, 'bank_branch', 255, 'account_holder_name');
            if (! Schema::hasColumn('partner_applications', 'bank_verification_status')) {
                $table->enum('bank_verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('bank_branch');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            foreach ([
                'court_count_total',
                'bank_verification_status',
                'bank_branch',
                'account_holder_name',
                'account_number',
                'bank_code',
                'bank_name',
                'amenities',
                'parking_info',
                'expected_opening_hours',
                'venue_description',
                'venue_email',
                'venue_phone',
                'venue_ward',
                'venue_district',
                'venue_province',
                'business_representative_position',
                'business_representative_name',
                'business_address',
                'business_license_number',
                'business_code',
                'representative_position',
                'representative_identity_issued_place',
                'representative_identity_issued_date',
                'representative_identity_number',
                'representative_identity_type',
                'representative_name',
                'applicant_type',
                'applicant_address',
                'applicant_email',
                'applicant_phone',
                'applicant_full_name',
            ] as $column) {
                if (Schema::hasColumn('partner_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function stringColumn(Blueprint $table, string $name, int $length, string $after): void
    {
        if (! Schema::hasColumn('partner_applications', $name)) {
            $table->string($name, $length)->nullable()->after($after);
        }
    }

    private function textColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::hasColumn('partner_applications', $name)) {
            $table->text($name)->nullable()->after($after);
        }
    }

    private function dateColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::hasColumn('partner_applications', $name)) {
            $table->date($name)->nullable()->after($after);
        }
    }

    private function jsonColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::hasColumn('partner_applications', $name)) {
            $table->json($name)->nullable()->after($after);
        }
    }

    private function integerColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::hasColumn('partner_applications', $name)) {
            $table->unsignedInteger($name)->default(0)->after($after);
        }
    }
};
