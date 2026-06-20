<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CommunityPost;
use App\Models\PolicyRule;
use App\Models\Report;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Services\Policies\ModerationReportPolicyService;
use App\Services\Policies\RefundCancellationPolicyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PolicyWorkflowEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_cancellation_evaluator_uses_separate_cancel_and_refund_policies(): void
    {
        $service = app(RefundCancellationPolicyService::class);
        $this->seedCancellationAndRefundPolicies($service);

        $booking = Booking::query()->create([
            'booking_code' => 'BKPOL001',
            'venue_cluster_id' => (string) Str::uuid(),
            'booking_date' => '2026-01-10',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $cases = [
            '2026-01-09 11:00:00' => 100,
            '2026-01-10 02:00:00' => 80,
            '2026-01-10 09:00:00' => 50,
            '2026-01-10 11:30:00' => 0,
        ];

        foreach ($cases as $cancelAt => $expectedRefundPercent) {
            $result = $service->evaluateBookingCancellation($booking, null, Carbon::parse($cancelAt));

            $this->assertTrue($result['allow_cancel']);
            $this->assertSame((float) $expectedRefundPercent, $result['refund_percent']);
        }
    }

    public function test_moderation_report_policy_moves_content_to_review_when_threshold_is_reached(): void
    {
        $admin = User::query()->create([
            'username' => 'policy_admin',
            'full_name' => 'Policy Admin',
            'email' => 'policy-admin@sportgo.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        $author = User::query()->create([
            'username' => 'post_author',
            'full_name' => 'Post Author',
            'email' => 'post-author@sportgo.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        $post = CommunityPost::query()->create([
            'author_id' => $author->id,
            'content' => 'Nội dung cần kiểm duyệt',
            'status' => 'published',
        ]);

        $policy = SystemPolicy::query()->create([
            'key' => 'moderation',
            'version' => 1,
            'title' => 'Kiểm duyệt',
            'content' => 'Chính sách kiểm duyệt',
            'type' => 'moderation',
            'policy_type' => 'moderation',
            'status' => 'active',
            'is_active' => true,
            'is_overridable' => false,
            'priority' => 1,
        ]);
        PolicyRule::query()->create([
            'system_policy_id' => $policy->id,
            'action_code' => 'post.report',
            'rule_code' => 'report_threshold_requires_review',
            'rule_name' => 'Ngưỡng báo cáo',
            'rule_type' => ModerationReportPolicyService::RULE_TYPE,
            'decision_key' => 'report_review_required',
            'conflict_group' => 'moderation_report_threshold',
            'condition_json' => [
                'target_type' => 'content',
                'report_count' => ['gte' => 5],
                'unique_reporters' => ['gte' => 2],
                'window_days' => 14,
            ],
            'result_json' => [
                'actions' => ['pending_review', 'notify_admin'],
                'action' => 'pending_review',
            ],
            'priority' => 90,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $reporter = User::query()->create([
                'username' => 'reporter_'.$i,
                'full_name' => 'Reporter '.$i,
                'email' => "reporter{$i}@sportgo.test",
                'password' => bcrypt('secret'),
                'status' => 'active',
            ]);
            Report::query()->create([
                'reporter_id' => $reporter->id,
                'reportable_type' => CommunityPost::class,
                'reportable_id' => $post->id,
                'reason' => 'spam',
                'description' => 'Báo cáo hợp lệ',
                'status' => 'resolved',
                'created_at' => now()->subDays(2),
            ]);
        }

        $result = app(ModerationReportPolicyService::class)->evaluate($post, null, $admin);

        $this->assertTrue($result['matched']);
        $this->assertSame('pending_review', $post->fresh()->status);
        $this->assertDatabaseHas('policy_evaluation_logs', [
            'system_policy_id' => $policy->id,
            'action_code' => 'post.report',
            'entity_id' => $post->id,
        ]);
    }

    private function seedCancellationAndRefundPolicies(RefundCancellationPolicyService $service): void
    {
        $cancellationPolicy = SystemPolicy::query()->create([
            'key' => 'booking_cancellation',
            'version' => 1,
            'title' => 'Hủy booking',
            'content' => 'Chính sách hủy booking',
            'type' => 'booking',
            'policy_type' => 'booking_cancellation',
            'status' => 'active',
            'is_active' => true,
            'is_overridable' => true,
            'priority' => 1,
        ]);
        PolicyRule::query()->create([
            'system_policy_id' => $cancellationPolicy->id,
            'action_code' => 'booking.cancel_by_customer',
            'rule_code' => 'cancel_before_hours',
            'rule_name' => 'Bảng mốc hủy booking',
            'rule_type' => RefundCancellationPolicyService::CANCELLATION_RULE_TYPE,
            'decision_key' => 'cancel_allowed',
            'conflict_group' => 'booking_cancel_window',
            'condition_json' => ['uses_tier_table' => true],
            'result_json' => $service->cancellationResultJson($service->defaultCancellationTiers()),
            'priority' => 100,
            'is_active' => true,
        ]);

        $refundPolicy = SystemPolicy::query()->create([
            'key' => 'refund',
            'version' => 1,
            'title' => 'Hoàn tiền',
            'content' => 'Chính sách hoàn tiền',
            'type' => 'refund',
            'policy_type' => 'refund',
            'status' => 'active',
            'is_active' => true,
            'is_overridable' => true,
            'priority' => 1,
        ]);
        PolicyRule::query()->create([
            'system_policy_id' => $refundPolicy->id,
            'action_code' => 'refund.request',
            'rule_code' => 'refund_percent_by_cancel_time',
            'rule_name' => 'Bảng mốc hoàn tiền',
            'rule_type' => RefundCancellationPolicyService::REFUND_RULE_TYPE,
            'decision_key' => 'refund_percent',
            'conflict_group' => 'refund_percent_minimum',
            'condition_json' => ['uses_tier_table' => true],
            'result_json' => $service->resultJson($service->defaultTiers()),
            'priority' => 100,
            'is_active' => true,
        ]);
    }
}
