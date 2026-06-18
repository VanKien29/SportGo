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
}
