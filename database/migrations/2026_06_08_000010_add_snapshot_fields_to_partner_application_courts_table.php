<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_application_courts')) {
            return;
        }

        Schema::table('partner_application_courts', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_application_courts', 'court_type_name_snapshot')) {
                $table->string('court_type_name_snapshot', 255)->nullable()->after('court_type_id');
            }
            if (! Schema::hasColumn('partner_application_courts', 'expected_court_count')) {
                $table->unsignedInteger('expected_court_count')->default(1)->after('court_type_name_snapshot');
            }
            if (! Schema::hasColumn('partner_application_courts', 'note')) {
                $table->text('note')->nullable()->after('expected_court_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_application_courts')) {
            return;
        }

        Schema::table('partner_application_courts', function (Blueprint $table): void {
            foreach (['note', 'expected_court_count', 'court_type_name_snapshot'] as $column) {
                if (Schema::hasColumn('partner_application_courts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
