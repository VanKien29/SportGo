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
        $cancellationPolicy = SystemPolicy::query()->where('key', 'booking_cancellation')->where('version', 1)->first();
        $baseRule = $cancellationPolicy
            ? PolicyRule::query()->where('system_policy_id', $cancellationPolicy->id)->where('rule_code', 'cancel_before_hours')->first()
            : null;

        if (! $owner || ! $admin || ! $cluster || ! $baseRule) {
            return;
        }

        $validVenueTiers = $this->refundPolicies->defaultCancelRefundTiers();
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
                'rule_code' => 'cancel_before_hours',
            ],
            [
                'base_policy_rule_id' => $baseRule->id,
                'action_code' => 'booking.cancel_by_customer',
                'rule_name' => 'Bảng mốc hủy & hoàn booking riêng có lợi hơn cho khách',
                'rule_type' => 'cancel_before_hours',
                'condition_json' => ['uses_cancel_refund_tier_table' => true],
                'result_json' => [
                    'tiers' => $validVenueTiers,
                    'cancel_refund_tiers' => $validVenueTiers,
                    'refund_basis' => 'paid_amount',
                    'summary_vi' => $this->refundPolicies->cancelRefundSummary($validVenueTiers),
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

        $invalidVenueTiers = $this->refundPolicies->defaultCancelRefundTiers();
        foreach ($invalidVenueTiers as &$tier) {
            if ($tier['key'] === 'from_6_to_24') {
                $tier['refund_percent'] = 50;
            }
        }

        VenuePolicyRule::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'rule_code' => 'cancel_before_hours_invalid',
            ],
            [
                'base_policy_rule_id' => $baseRule->id,
                'action_code' => 'booking.cancel_by_customer',
                'rule_name' => 'Bảng mốc hủy & hoàn booking thấp hơn khung hệ thống',
                'rule_type' => 'cancel_before_hours',
                'condition_json' => ['uses_cancel_refund_tier_table' => true],
                'result_json' => [
                    'tiers' => $invalidVenueTiers,
                    'cancel_refund_tiers' => $invalidVenueTiers,
                    'refund_basis' => 'paid_amount',
                    'summary_vi' => $this->refundPolicies->cancelRefundSummary($invalidVenueTiers),
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
