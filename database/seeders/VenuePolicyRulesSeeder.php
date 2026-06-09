<?php

namespace Database\Seeders;

use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePolicyRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenuePolicyRulesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('venue_policy_rules') || ! Schema::hasTable('policy_rules')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $admin = User::query()->where('username', 'admin')->first();
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();
        $refundPolicy = SystemPolicy::query()->where('key', 'refund')->where('version', 1)->first();
        $baseRule = $refundPolicy
            ? PolicyRule::query()->where('system_policy_id', $refundPolicy->id)->where('rule_code', 'refund_percent_by_cancel_time')->first()
            : null;

        if (! $owner || ! $admin || ! $cluster || ! $baseRule) {
            return;
        }

        VenuePolicyRule::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'rule_code' => 'venue_refund_percent_90_before_24h',
            ],
            [
                'base_policy_rule_id' => $baseRule->id,
                'action_code' => 'refund.request',
                'rule_name' => 'Hoàn 90% khi khách hủy trước 24 giờ',
                'rule_type' => 'refund_percent_by_cancel_time',
                'condition_json' => ['hours_before_start' => ['gte' => 24]],
                'result_json' => ['refund_percent' => 90, 'requires_owner_confirm' => true],
                'status' => 'active',
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(2),
                'rejected_reason' => null,
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
                'submitted_by' => $owner->id,
                'submitted_at' => now()->subDays(3),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(2),
                'reject_reason' => null,
                'effective_from' => now()->subDays(2),
                'effective_to' => null,
                'constraint_check_result' => [
                    'passed' => true,
                    'message_vi' => 'Chính sách sân có lợi hơn cho khách và vẫn yêu cầu chủ sân xác nhận.',
                    'checked_constraints' => ['refund_percent_minimum', 'owner_confirm_required'],
                ],
            ],
        );

        VenuePolicyRule::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'rule_code' => 'venue_refund_percent_50_before_24h',
            ],
            [
                'base_policy_rule_id' => $baseRule->id,
                'action_code' => 'refund.request',
                'rule_name' => 'Hoàn 50% khi khách hủy trước 24 giờ',
                'rule_type' => 'refund_percent_by_cancel_time',
                'condition_json' => ['hours_before_start' => ['gte' => 24]],
                'result_json' => ['refund_percent' => 50, 'requires_owner_confirm' => true],
                'status' => 'rejected',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_reason' => 'Tỷ lệ hoàn tiền thấp hơn mức tối thiểu 80% của chính sách hệ thống.',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
                'submitted_by' => $owner->id,
                'submitted_at' => now()->subDays(2),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDay(),
                'reject_reason' => 'Tỷ lệ hoàn tiền thấp hơn mức tối thiểu 80% của chính sách hệ thống.',
                'effective_from' => null,
                'effective_to' => null,
                'constraint_check_result' => [
                    'passed' => false,
                    'message_vi' => 'Chính sách sân không được hoàn thấp hơn mức tối thiểu của hệ thống.',
                    'failed_constraints' => ['refund_percent_minimum'],
                    'minimum_refund_percent' => 80,
                    'submitted_refund_percent' => 50,
                ],
            ],
        );
    }
}
