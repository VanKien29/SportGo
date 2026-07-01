<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\CourtMembershipTier;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserCourtMembership;
use App\Models\VenueCluster;
use App\Services\Memberships\VenueMembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_booking_sync_upgrades_member_when_both_thresholds_are_met(): void
    {
        [$user, $cluster] = $this->createUserAndCluster();
        $service = app(VenueMembershipService::class);

        CourtMembershipTier::query()->create([
            'venue_cluster_id' => $cluster->id,
            'tier' => 'silver',
            'discount_percent' => 4,
            'min_bookings' => 2,
            'min_spent_amount' => 200000,
        ]);

        $this->createCompletedBooking($user, $cluster, 150000, 'BK-MEMBER-1');
        $membership = $service->syncUserVenue($user->id, $cluster->id, 'booking_completed');

        $this->assertSame('standard', $membership->tier);

        $this->createCompletedBooking($user, $cluster, 80000, 'BK-MEMBER-2');
        $membership = $service->syncUserVenue($user->id, $cluster->id, 'booking_completed')->fresh();

        $this->assertSame('silver', $membership->tier);
        $this->assertSame(2, $membership->total_bookings);
        $this->assertEquals(230000.0, (float) $membership->total_spent);

        $discount = $service->discountForBooking($user->id, $cluster->id, 100000);
        $this->assertSame('silver', $discount['tier']);
        $this->assertEquals(4000.0, $discount['discount_amount']);

        $payload = $service->membershipsForUser($user);
        $this->assertCount(4, $payload[0]['tiers']);
        $this->assertSame(['standard', 'silver', 'gold', 'diamond'], array_column($payload[0]['tiers'], 'tier'));
    }

    public function test_profile_membership_counts_confirmed_successful_booking(): void
    {
        [$user, $cluster] = $this->createUserAndCluster();
        $service = app(VenueMembershipService::class);

        $this->createBooking($user, $cluster, 120000, 'BK-MEMBER-CONFIRMED', 'confirmed');

        $payload = $service->membershipsForUser($user);

        $this->assertCount(1, $payload);
        $this->assertSame('standard', $payload[0]['tier']['tier']);
        $this->assertSame(1, $payload[0]['completed_bookings']);
        $this->assertEquals(120000.0, $payload[0]['total_spend_amount']);
        $this->assertSame('silver', $payload[0]['next_tier']['tier']);
        $this->assertSame(4, $payload[0]['remaining_bookings']);
        $this->assertEquals(380000.0, $payload[0]['remaining_spend_amount']);
    }

    public function test_profile_recalculation_does_not_restore_a_maintenance_downgrade(): void
    {
        [$user, $cluster] = $this->createUserAndCluster();
        $service = app(VenueMembershipService::class);

        $this->createCompletedBooking($user, $cluster, 3000000, 'BK-MEMBER-3');
        $membership = UserCourtMembership::query()->create([
            'user_id' => $user->id,
            'venue_cluster_id' => $cluster->id,
            'tier' => 'silver',
            'total_bookings' => 20,
            'total_spent' => 3000000,
            'period_bookings' => 0,
            'period_spent' => 0,
            'period_start' => now()->toDateString(),
            'last_downgraded_at' => now(),
        ]);

        $recalculated = $service->syncUserVenue($user->id, $cluster->id)->fresh();

        $this->assertSame($membership->id, $recalculated->id);
        $this->assertSame('silver', $recalculated->tier);
    }

    public function test_profile_recalculation_upgrades_when_member_meets_next_tier_without_maintenance_downgrade(): void
    {
        [$user, $cluster] = $this->createUserAndCluster();
        $service = app(VenueMembershipService::class);

        CourtMembershipTier::query()->create([
            'venue_cluster_id' => $cluster->id,
            'tier' => 'gold',
            'discount_percent' => 5,
            'min_bookings' => 1,
            'min_spent_amount' => 1000000,
        ]);

        $this->createCompletedBooking($user, $cluster, 3000000, 'BK-MEMBER-UPGRADE-1');
        UserCourtMembership::query()->create([
            'user_id' => $user->id,
            'venue_cluster_id' => $cluster->id,
            'tier' => 'silver',
            'total_bookings' => 20,
            'total_spent' => 3000000,
            'period_bookings' => 20,
            'period_spent' => 3000000,
            'period_start' => now()->toDateString(),
        ]);

        $recalculated = $service->syncUserVenue($user->id, $cluster->id)->fresh();

        $this->assertSame('gold', $recalculated->tier);
    }

    public function test_reset_progress_setting_starts_booking_and_spend_from_zero_after_upgrade(): void
    {
        [$user, $cluster] = $this->createUserAndCluster();
        $service = app(VenueMembershipService::class);

        BookingConfig::query()->create([
            'venue_cluster_id' => $cluster->id,
            'reset_membership_progress_on_upgrade' => true,
        ]);

        CourtMembershipTier::query()->create([
            'venue_cluster_id' => $cluster->id,
            'tier' => 'silver',
            'discount_percent' => 3,
            'min_bookings' => 1,
            'min_spent_amount' => 100000,
        ]);

        CourtMembershipTier::query()->create([
            'venue_cluster_id' => $cluster->id,
            'tier' => 'gold',
            'discount_percent' => 5,
            'min_bookings' => 2,
            'min_spent_amount' => 200000,
        ]);

        Carbon::setTestNow('2026-06-01 10:00:00');
        $this->createCompletedBooking($user, $cluster, 100000, 'BK-RESET-1');
        $membership = $service->syncUserVenue($user->id, $cluster->id, 'booking_completed')->fresh();
        $this->assertSame('silver', $membership->tier);
        $this->assertSame(0, $membership->total_bookings);
        $this->assertEquals(0.0, (float) $membership->total_spent);

        Carbon::setTestNow('2026-06-01 11:00:00');
        $this->createCompletedBooking($user, $cluster, 100000, 'BK-RESET-2');
        $membership = $service->syncUserVenue($user->id, $cluster->id, 'booking_completed')->fresh();
        $this->assertSame('silver', $membership->tier);
        $this->assertSame(1, $membership->total_bookings);
        $this->assertEquals(100000.0, (float) $membership->total_spent);

        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->createCompletedBooking($user, $cluster, 100000, 'BK-RESET-3');
        $membership = $service->syncUserVenue($user->id, $cluster->id, 'booking_completed')->fresh();
        $this->assertSame('gold', $membership->tier);
        $this->assertSame(0, $membership->total_bookings);
        $this->assertEquals(0.0, (float) $membership->total_spent);

        Carbon::setTestNow();
    }

    public function test_maintenance_evaluation_downgrades_one_tier_and_notifies_user(): void
    {
        [$user, $cluster] = $this->createUserAndCluster();
        $service = app(VenueMembershipService::class);

        CourtMembershipTier::query()->create([
            'venue_cluster_id' => $cluster->id,
            'tier' => 'gold',
            'discount_percent' => 6,
            'min_bookings' => 10,
            'min_spent_amount' => 1000000,
            'maintain_period_months' => 1,
            'maintain_min_bookings' => 2,
            'maintain_min_spent' => 300000,
        ]);

        $membership = UserCourtMembership::query()->create([
            'user_id' => $user->id,
            'venue_cluster_id' => $cluster->id,
            'tier' => 'gold',
            'total_bookings' => 10,
            'total_spent' => 1000000,
            'period_bookings' => 0,
            'period_spent' => 0,
            'period_start' => now()->subMonths(2)->toDateString(),
        ]);

        $this->assertSame(1, $service->evaluateMaintenance());

        $membership->refresh();
        $this->assertSame('silver', $membership->tier);
        $this->assertNotNull($membership->last_downgraded_at);

        $this->assertDatabaseHas('user_court_membership_histories', [
            'membership_id' => $membership->id,
            'from_tier' => 'gold',
            'to_tier' => 'silver',
            'change_type' => 'downgraded',
        ]);

        $this->assertSame(1, Notification::query()
            ->where('user_id', $user->id)
            ->where('type', 'membership_downgrade')
            ->count());
    }

    private function createUserAndCluster(): array
    {
        $owner = User::query()->create([
            'username' => 'owner_'.uniqid(),
            'full_name' => 'Owner',
            'email' => uniqid('owner').'@sportgo.test',
            'phone' => '08'.random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'username' => 'user_'.uniqid(),
            'full_name' => 'User',
            'email' => uniqid('user').'@sportgo.test',
            'phone' => '09'.random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $cluster = VenueCluster::query()->create([
            'owner_id' => $owner->id,
            'name' => 'SportGo Membership Test',
            'slug' => 'sportgo-membership-test-'.uniqid(),
            'address' => 'Ha Noi',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        return [$user, $cluster];
    }

    private function createCompletedBooking(User $user, VenueCluster $cluster, float $amount, string $code): Booking
    {
        return $this->createBooking($user, $cluster, $amount, $code, 'completed');
    }

    private function createBooking(User $user, VenueCluster $cluster, float $amount, string $code, string $status): Booking
    {
        return Booking::query()->create([
            'booking_code' => $code,
            'customer_id' => $user->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => now()->toDateString(),
            'total_price' => $amount,
            'original_amount' => $amount,
            'final_amount' => $amount,
            'payment_option' => 'full_payment',
            'required_payment_amount' => $amount,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => $status,
            'created_by' => $user->id,
        ]);
    }
}
