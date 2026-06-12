<?php

namespace Database\Seeders;

use App\Models\PolicyStatusHistory;
use App\Models\SystemPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PolicyStatusHistoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('policy_status_histories') || ! Schema::hasTable('system_policies')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        SystemPolicy::query()
            ->whereIn('key', ['terms', 'booking_cancellation', 'refund', 'platform_fee', 'venue_policy', 'moderation', 'partner_contract'])
            ->get()
            ->each(function (SystemPolicy $policy) use ($admin): void {
                PolicyStatusHistory::query()->firstOrCreate(
                    [
                        'system_policy_id' => $policy->id,
                        'old_status' => null,
                        'new_status' => 'active',
                        'reason' => 'Seed chính sách active v1 đúng luồng.',
                    ],
                    [
                        'changed_by' => $admin?->id,
                        'actor_type' => 'seeder',
                        'created_at' => now()->subDays(7),
                    ],
                );
            });
    }
}
