<?php

namespace Tests\Unit;

use App\Services\Policies\RefundCancellationPolicyService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundCancellationPolicyServiceTest extends TestCase
{
    public function test_it_matches_all_required_refund_time_tiers(): void
    {
        $service = new RefundCancellationPolicyService();
        $tiers = $service->defaultTiers();

        $this->assertSame('from_24', $service->matchTier($tiers, 24)['key']);
        $this->assertSame('from_24', $service->matchTier($tiers, 48)['key']);
        $this->assertSame('from_6_to_24', $service->matchTier($tiers, 6)['key']);
        $this->assertSame('from_6_to_24', $service->matchTier($tiers, 23.99)['key']);
        $this->assertSame('from_1_to_6', $service->matchTier($tiers, 1)['key']);
        $this->assertSame('from_1_to_6', $service->matchTier($tiers, 5.99)['key']);
        $this->assertSame('under_1', $service->matchTier($tiers, 0.5)['key']);
        $this->assertSame('under_1', $service->matchTier($tiers, -0.5)['key']);
    }

    public function test_it_matches_all_required_cancellation_time_tiers(): void
    {
        $service = new RefundCancellationPolicyService();
        $tiers = $service->defaultCancellationTiers();

        $this->assertSame('from_24', $service->matchTier($tiers, 24)['key']);
        $this->assertSame('from_6_to_24', $service->matchTier($tiers, 6)['key']);
        $this->assertSame('from_1_to_6', $service->matchTier($tiers, 1)['key']);
        $this->assertSame('under_1', $service->matchTier($tiers, 0.5)['key']);
        $this->assertSame('under_1', $service->matchTier($tiers, -0.5)['key']);
    }

    public function test_venue_refund_tiers_cannot_be_less_favorable_than_system_tiers(): void
    {
        $service = new RefundCancellationPolicyService();
        $systemTiers = $service->defaultTiers();
        $venueTiers = $systemTiers;
        $venueTiers[1]['refund_percent'] = 50;

        $this->expectException(ValidationException::class);
        $service->validateVenueTiers($venueTiers, $systemTiers);
    }

    public function test_venue_cancellation_tiers_cannot_block_system_allowed_cancel(): void
    {
        $service = new RefundCancellationPolicyService();
        $systemTiers = $service->defaultCancellationTiers();
        $venueTiers = $systemTiers;
        $venueTiers[1]['allow_cancel'] = false;

        $this->expectException(ValidationException::class);
        $service->validateVenueCancellationTiers($venueTiers, $systemTiers);
    }

    public function test_venue_can_customize_cancel_refund_time_ranges_when_every_interval_is_not_worse_than_system(): void
    {
        $service = new RefundCancellationPolicyService();
        $systemTiers = $service->defaultCancelRefundTiers();
        $venueTiers = [
            $this->cancelRefundTier('venue_from_24', 24, null, 100),
            $this->cancelRefundTier('venue_from_6_to_24', 6, 24, 90),
            $this->cancelRefundTier('venue_under_6', 0, 6, 60),
        ];

        $validated = $service->validateVenueCancelRefundTiers($venueTiers, $systemTiers);

        $this->assertCount(3, $validated);
        $this->assertSame('venue_under_6', $service->matchTier($validated, 0.5)['key']);
        $this->assertSame('venue_under_6', $service->matchTier($validated, 5.5)['key']);
        $this->assertSame('venue_from_6_to_24', $service->matchTier($validated, 8)['key']);
    }

    public function test_venue_custom_time_range_uses_highest_system_refund_floor_it_overlaps(): void
    {
        $service = new RefundCancellationPolicyService();
        $systemTiers = $service->defaultCancelRefundTiers();
        $venueTiers = [
            $this->cancelRefundTier('venue_from_24', 24, null, 100),
            $this->cancelRefundTier('venue_from_8_to_24', 8, 24, 80),
            $this->cancelRefundTier('venue_under_8', 0, 8, 50),
        ];

        $this->expectException(ValidationException::class);
        $service->validateVenueCancelRefundTiers($venueTiers, $systemTiers);
    }

    public function test_venue_must_split_custom_range_when_system_cancel_permission_changes_inside_it(): void
    {
        $service = new RefundCancellationPolicyService();
        $systemTiers = $service->defaultCancelRefundTiers();
        $systemTiers[3]['allow_cancel'] = false;
        $systemTiers[3]['refund_percent'] = 0;
        $venueTiers = [
            $this->cancelRefundTier('venue_from_24', 24, null, 100),
            $this->cancelRefundTier('venue_from_6_to_24', 6, 24, 80),
            $this->cancelRefundTier('venue_under_6', 0, 6, 50),
        ];

        $this->expectException(ValidationException::class);
        $service->validateVenueCancelRefundTiers($venueTiers, $systemTiers);
    }

    private function cancelRefundTier(string $key, float $fromHours, ?float $toHours, float $refundPercent): array
    {
        return [
            'key' => $key,
            'label' => $key,
            'from_hours' => $fromHours,
            'to_hours' => $toHours,
            'allow_cancel' => true,
            'refund_percent' => $refundPercent,
            'require_owner_confirm' => true,
            'require_admin_confirm' => true,
            'customer_message' => 'Áp dụng theo chính sách sân.',
        ];
    }
}
