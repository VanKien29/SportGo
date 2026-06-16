<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_policies') || Schema::hasColumn('system_policies', 'policy_category')) {
            return;
        }

        Schema::table('system_policies', function (Blueprint $table): void {
            $table->enum('policy_category', [
                'document',
                'numeric_threshold',
                'penalty_matrix',
                'percentage_table',
            ])->default('document')->after('key');

            $table->index('policy_category', 'system_policies_policy_category_index');
        });

        DB::table('system_policies')->orderBy('id')->chunkById(100, function ($policies): void {
            foreach ($policies as $policy) {
                $key = $policy->key ?: ($policy->policy_type ?? $policy->type ?? '');
                $policyType = $policy->policy_type ?? $policy->type ?? $key;
                $category = match (true) {
                    in_array($key, ['booking_cancellation', 'refund'], true),
                    in_array($policyType, ['booking_cancellation', 'refund'], true) => 'percentage_table',
                    in_array($key, ['platform_fee'], true),
                    in_array($policyType, ['platform_fee'], true) => 'numeric_threshold',
                    in_array($key, ['content_moderation', 'moderation'], true),
                    in_array($policyType, ['content_moderation', 'moderation'], true) => 'penalty_matrix',
                    default => 'document',
                };

                DB::table('system_policies')->where('id', $policy->id)->update([
                    'policy_category' => $category,
                ]);
            }
        }, 'id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_policies') || ! Schema::hasColumn('system_policies', 'policy_category')) {
            return;
        }

        Schema::table('system_policies', function (Blueprint $table): void {
            $table->dropIndex('system_policies_policy_category_index');
            $table->dropColumn('policy_category');
        });
    }
};
