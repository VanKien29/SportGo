<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        Schema::table('reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('reports', 'violation_type_id')) {
                $table->unsignedBigInteger('violation_type_id')->nullable()->after('reportable_id');
            }
            if (! Schema::hasColumn('reports', 'severity_level')) {
                $table->string('severity_level', 20)->default('mild')->after('violation_type_id');
            }
            if (! Schema::hasColumn('reports', 'score_contribution')) {
                $table->unsignedSmallInteger('score_contribution')->default(0)->after('severity_level');
            }
            if (! Schema::hasColumn('reports', 'auto_action_taken')) {
                $table->string('auto_action_taken', 50)->nullable()->after('score_contribution');
            }
            if (! Schema::hasColumn('reports', 'auto_actioned_at')) {
                $table->timestamp('auto_actioned_at')->nullable()->after('auto_action_taken');
            }
        });

        Schema::table('reports', function (Blueprint $table): void {
            if (Schema::hasColumn('reports', 'violation_type_id')) {
                $table->foreign('violation_type_id', 'reports_violation_type_foreign')
                    ->references('id')->on('violation_types')->nullOnDelete();
            }
            $table->index(['reportable_type', 'reportable_id', 'created_at'], 'reports_target_created_index');
            $table->index('severity_level', 'reports_severity_level_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        Schema::table('reports', function (Blueprint $table): void {
            if (Schema::hasColumn('reports', 'violation_type_id')) {
                $table->dropForeign('reports_violation_type_foreign');
            }
            $table->dropIndex('reports_target_created_index');
            $table->dropIndex('reports_severity_level_index');
        });

        Schema::table('reports', function (Blueprint $table): void {
            foreach (['violation_type_id', 'severity_level', 'score_contribution', 'auto_action_taken', 'auto_actioned_at'] as $column) {
                if (Schema::hasColumn('reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
