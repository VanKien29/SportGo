<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('membership_packages')) {
            return;
        }

        DB::table('membership_packages')->get()->each(function (object $package): void {
            if ($package->type === 'free' || $package->monthly_price === null) {
                DB::table('membership_packages')->where('id', $package->id)->update([
                    'quarterly_price' => null,
                    'yearly_price' => null,
                    'updated_at' => now(),
                ]);

                return;
            }

            $monthlyPrice = (int) round((float) $package->monthly_price);
            $quarterlyDiscount = in_array($package->type, ['saving', 'pro'], true) ? 15 : 0;
            $yearlyDiscount = in_array($package->type, ['saving', 'pro'], true) ? 25 : 0;

            DB::table('membership_packages')->where('id', $package->id)->update([
                'monthly_price' => $monthlyPrice,
                'quarterly_price' => $this->periodPrice($monthlyPrice, 3, $quarterlyDiscount),
                'yearly_price' => $this->periodPrice($monthlyPrice, 12, $yearlyDiscount),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // The previous values were inconsistent with the active pricing rules.
        // Keeping the normalized values avoids restoring stale customer-facing prices.
    }

    private function periodPrice(int $monthlyPrice, int $months, int $discountPercent): int
    {
        return (int) round($monthlyPrice * $months * (100 - $discountPercent) / 100, -3);
    }
};
