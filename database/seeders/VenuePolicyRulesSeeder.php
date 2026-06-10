<?php

namespace Database\Seeders;

use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePolicyRule;
use App\Services\Policies\RefundCancellationPolicyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenuePolicyRulesSeeder extends Seeder
{
    public function __construct(private readonly RefundCancellationPolicyService $refundPolicies)
    {
    }

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

        $validVenueTiers = $this->refundPolicies->defaultTiers();
        foreach ($validVenueTiers as &$tier) {
            if ($tier['key'] === 'from_6_to_24') {
                $tier['refund_percent'] = 90;
            }
            if ($tier['key'] === 'from_1_to_6') {
                $tier['refund_percent'] = 60;
            }
        }

        VenuePolicyRule::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'rule_code' => 'venue_refund_tiers_customer_friendly',
            ],
            [
                'base_policy_rule_id' => $baseRule->id,
                'action_code' => 'refund.request',
                'rule_name' => 'Bảng mốc hoàn tiền riêng có lợi hơn cho khách',
                'rule_type' => 'refund_percent_by_cancel_time',
                'condition_json' => ['uses_tier_table' => true],
                'result_json' => [
                    'tiers' => $validVenueTiers,
                    'refund_percent' => 100,
                    'requires_owner_confirm' => true,
                    'requires_admin_confirm' => true,
                    'summary_vi' => $this->refundPolicies->summary($validVenueTiers),
                ],
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

        $invalidVenueTiers = $this->refundPolicies->defaultTiers();
        foreach ($invalidVenueTiers as &$tier) {
            if ($tier['key'] === 'from_6_to_24') {
                $tier['refund_percent'] = 50;
            }
        }

        VenuePolicyRule::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'rule_code' => 'venue_refund_tiers_below_system_minimum',
            ],
            [
                'base_policy_rule_id' => $baseRule->id,
                'action_code' => 'refund.request',
                'rule_name' => 'Bảng mốc hoàn tiền thấp hơn khung hệ thống',
                'rule_type' => 'refund_percent_by_cancel_time',
                'condition_json' => ['uses_tier_table' => true],
                'result_json' => [
                    'tiers' => $invalidVenueTiers,
                    'refund_percent' => 100,
                    'requires_owner_confirm' => true,
                    'requires_admin_confirm' => true,
                    'summary_vi' => $this->refundPolicies->summary($invalidVenueTiers),
                ],
                'status' => 'rejected',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_reason' => 'Mốc từ 6 đến dưới 24 giờ thấp hơn mức tối thiểu 80% của chính sách hệ thống.',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
                'submitted_by' => $owner->id,
                'submitted_at' => now()->subDays(2),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDay(),
                'reject_reason' => 'Mốc từ 6 đến dưới 24 giờ thấp hơn mức tối thiểu 80% của chính sách hệ thống.',
                'effective_from' => null,
                'effective_to' => null,
                'constraint_check_result' => [
                    'passed' => false,
                    'message_vi' => 'Mức hoàn ở mốc từ 6 đến dưới 24 giờ không được thấp hơn 80% theo chính sách hệ thống.',
                    'failed_constraints' => ['refund_percent_minimum'],
                    'minimum_refund_percent' => 80,
                    'submitted_refund_percent' => 50,
                ],
            ],
        );
    }
}
