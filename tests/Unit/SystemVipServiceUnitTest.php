<?php

namespace Tests\Unit;

use App\Models\MembershipPackage;
use App\Services\Memberships\SystemVipService;
use Tests\TestCase;

class SystemVipServiceUnitTest extends TestCase
{
    public function test_free_package_always_has_zero_monthly_price_and_no_period_prices(): void
    {
        $package = new MembershipPackage(['type' => 'free']);

        $this->assertSame([
            'monthly_price' => 0,
            'quarterly_price' => null,
            'yearly_price' => null,
        ], app(SystemVipService::class)->pricesFromMonthly($package, 0));
    }
}
