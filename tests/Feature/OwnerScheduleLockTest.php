<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\BookingItem;
use App\Models\CourtType;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OwnerScheduleLockTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherOwner;

    private VenueCluster $cluster;

    private VenueCourt $court;

    private VenueCourt $secondCourt;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true,
        ]);

        $this->owner = $this->createUser('schedule_owner');
        $this->otherOwner = $this->createUser('schedule_other_owner');
        $this->assignRole($this->owner, $role);
        $this->assignRole($this->otherOwner, $role);
        $this->date = today()->addWeek()->toDateString();

        $type = CourtType::query()->create([
            'name' => 'Cầu lông',
            'description' => 'Sân cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Cụm sân khóa lịch',
            'slug' => 'cum-san-khoa-lich',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        $this->court = VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $type->id,
            'name' => 'Sân A1',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->secondCourt = VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $type->id,
            'name' => 'Sân A2',
            'status' => 'active',
            'sort_order' => 2,
        ]);
    }

    public function test_owner_can_create_manual_lock_and_schedule_marks_slots_as_locked(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '08:00:00',
                'end_time' => '09:30:00',
                'reason' => 'Bảo trì mặt sân.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.lock_type', 'manual')
            ->assertJsonPath('data.reason', 'Bảo trì mặt sân.');

        $lockId = $response->json('data.id');

        $this->assertDatabaseHas('slot_locks', [
            'id' => $lockId,
            'venue_court_id' => $this->court->id,
            'lock_type' => 'manual',
            'reason' => 'Bảo trì mặt sân.',
            'booking_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'schedule_lock.created',
            'entity_id' => $lockId,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/bookings/schedule?'.http_build_query([
                'venue_cluster_id' => $this->cluster->id,
                'booking_date' => $this->date,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'venue_court_id' => $this->court->id,
                'start_time' => '08:30:00',
                'end_time' => '09:00:00',
                'is_available' => false,
                'busy_source' => 'slot_lock',
                'busy_status' => 'manual',
                'schedule_lock_id' => $lockId,
                'lock_reason' => 'Bảo trì mặt sân.',
            ]);
    }

    public function test_owner_can_lock_full_operating_day_for_each_date_in_a_range(): void
    {
        $secondDate = Carbon::parse($this->date)->addDay()->toDateString();

        BookingConfig::query()->updateOrCreate(
            ['venue_cluster_id' => $this->cluster->id],
            [
                'fixed_open_time' => '07:00:00',
                'fixed_close_time' => '21:30:00',
                'special_operating_hours' => [[
                    'start_date' => $secondDate,
                    'end_date' => $secondDate,
                    'open_time' => '09:00',
                    'close_time' => '18:00',
                ]],
            ],
        );

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'full_day' => true,
                'venue_court_ids' => [$this->court->id, $this->secondCourt->id],
                'start_date' => $this->date,
                'end_date' => $secondDate,
                'reason' => 'Bảo trì toàn bộ sân.',
            ])
            ->assertCreated()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('message', 'Đã khóa toàn bộ giờ hoạt động của các sân đã chọn.');

        foreach ([$this->court->id, $this->secondCourt->id] as $courtId) {
            $this->assertDatabaseHas('slot_locks', [
                'venue_court_id' => $courtId,
                'booking_date' => $this->date,
                'start_time' => '07:00:00',
                'end_time' => '21:30:00',
                'reason' => 'Bảo trì toàn bộ sân.',
            ]);
            $this->assertDatabaseHas('slot_locks', [
                'venue_court_id' => $courtId,
                'booking_date' => $secondDate,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'reason' => 'Bảo trì toàn bộ sân.',
            ]);
        }

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/schedule-locks?'.http_build_query([
                'venue_cluster_id' => $this->cluster->id,
                'start_date' => $this->date,
                'end_date' => $secondDate,
            ]))
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->court->id, $this->secondCourt->id],
            $response->json('meta.full_day_locked_court_ids'),
        );
    }

    public function test_owner_cannot_create_lock_overlapping_booking_or_existing_lock(): void
    {
        Booking::query()->create([
            'booking_code' => 'BKLOCKOVERLAP',
            'customer_id' => $this->owner->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->date,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'reason' => 'Bảo trì.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => $this->date,
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'locked_by' => $this->owner->id,
            'lock_type' => 'manual',
            'reason' => 'Nghỉ trưa.',
            'expires_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '12:30:00',
                'end_time' => '13:30:00',
                'reason' => 'Sự kiện riêng.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_manual_lock_times_must_follow_thirty_minute_steps(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '08:15:00',
                'end_time' => '09:00:00',
                'reason' => 'Bảo trì mặt sân.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '08:00:00',
                'end_time' => '09:15:00',
                'reason' => 'Bảo trì mặt sân.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');
    }

    public function test_owner_can_lock_multiple_ranges_across_multiple_courts(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'booking_date' => $this->date,
                'reason' => 'Bảo trì đồng loạt.',
                'slots' => [
                    [
                        'venue_court_id' => $this->court->id,
                        'start_time' => '08:00:00',
                        'end_time' => '10:00:00',
                    ],
                    [
                        'venue_court_id' => $this->secondCourt->id,
                        'start_time' => '09:00:00',
                        'end_time' => '11:30:00',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Đã tạo 2 khoảng khóa lịch.')
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('slot_locks', [
            'venue_court_id' => $this->court->id,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'reason' => 'Bảo trì đồng loạt.',
        ]);
        $this->assertDatabaseHas('slot_locks', [
            'venue_court_id' => $this->secondCourt->id,
            'start_time' => '09:00:00',
            'end_time' => '11:30:00',
            'reason' => 'Bảo trì đồng loạt.',
        ]);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_emergency_lock_interrupts_active_booking_and_refunds_remaining_rounded_time(): void
    {
        Carbon::setTestNow(Carbon::parse(today()->toDateString().' 10:40:00'));
        $date = today()->toDateString();

        $booking = Booking::query()->create([
            'booking_code' => 'BKEMERGENCY01',
            'customer_id' => $this->otherOwner->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $date,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'total_price' => 120000,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 120000,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'checked_in',
        ]);
        $item = BookingItem::query()->create([
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 120,
            'unit_price' => 60000,
            'subtotal' => 120000,
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $payment = Payment::query()->create([
            'payment_code' => 'PMEMERGENCY01',
            'booking_id' => $booking->id,
            'amount' => 120000,
            'wallet_amount' => 0,
            'gateway_amount' => 120000,
            'payment_kind' => 'full',
            'method' => 'sepay',
            'status' => 'paid',
            'paid_at' => now()->subHour(),
        ]);

        $preview = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks/preview', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $date,
                'start_time' => '10:30:00',
                'end_time' => '12:00:00',
                'lock_type' => 'emergency',
                'reason' => 'Mặt sân hỏng đột xuất.',
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.is_playing', true)
            ->assertJsonPath('data.items.0.incident.played_minutes', 30)
            ->assertJsonPath('data.items.0.incident.remaining_minutes', 90)
            ->assertJsonPath('data.items.0.incident.estimated_refund_amount', 90000);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $date,
                'start_time' => '10:30:00',
                'end_time' => '12:00:00',
                'lock_type' => 'emergency',
                'reason' => 'Mặt sân hỏng đột xuất.',
                'resolutions' => [[
                    'booking_item_id' => $item->id,
                    'action' => 'cancel',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.lock_type', 'emergency');

        $this->assertDatabaseHas('booking_items', [
            'id' => $item->id,
            'status' => 'interrupted_by_emergency',
            'start_time' => '10:30:00',
            'end_time' => '12:00:00',
            'played_minutes' => 0,
            'remaining_minutes' => 90,
            'incident_resolution' => 'wallet_refund',
        ]);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 30,
            'subtotal' => 30000,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
            'amount' => 90000,
            'refund_destination' => 'user_wallet',
            'status' => 'completed',
        ]);

        Carbon::setTestNow();
    }

    public function test_affected_booking_requires_emergency_lock_and_an_explicit_resolution(): void
    {
        $booking = Booking::query()->create([
            'booking_code' => 'BKEMERGENCY02',
            'customer_id' => $this->otherOwner->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->date,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);
        BookingItem::query()->create([
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'duration_minutes' => 60,
            'unit_price' => 100000,
            'subtotal' => 100000,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $payload = [
            'venue_court_id' => $this->court->id,
            'booking_date' => $this->date,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'reason' => 'Mặt sân hỏng đột xuất.',
        ];

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', $payload + ['lock_type' => 'manual'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lock_type');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', $payload + ['lock_type' => 'emergency'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolutions');

        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('slot_locks', [
            'venue_court_id' => $this->court->id,
            'booking_date' => $this->date,
            'lock_type' => 'emergency',
        ]);
    }

    public function test_emergency_lock_can_move_booking_to_an_available_court_of_the_same_type(): void
    {
        $booking = Booking::query()->create([
            'booking_code' => 'BKEMERGENCY03',
            'customer_id' => $this->otherOwner->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->date,
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);
        $item = BookingItem::query()->create([
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'duration_minutes' => 60,
            'unit_price' => 100000,
            'subtotal' => 100000,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $payload = [
            'venue_court_id' => $this->court->id,
            'booking_date' => $this->date,
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'lock_type' => 'emergency',
            'reason' => 'Mặt sân hỏng đột xuất.',
        ];

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.affected_count', 1)
            ->assertJsonPath('data.items.0.alternatives.0.id', $this->secondCourt->id);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', $payload + [
                'resolutions' => [[
                    'booking_item_id' => $item->id,
                    'action' => 'switch',
                    'venue_court_id' => $this->secondCourt->id,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.lock_type', 'emergency');

        $this->assertDatabaseHas('booking_items', [
            'id' => $item->id,
            'venue_court_id' => $this->secondCourt->id,
            'status' => 'moved',
            'incident_resolution' => null,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'venue_court_id' => $this->secondCourt->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseMissing('refunds', ['booking_id' => $booking->id]);
    }

    public function test_batch_lock_does_not_offer_a_court_that_is_also_being_locked(): void
    {
        $booking = Booking::query()->create([
            'booking_code' => 'BKBATCHNOALT',
            'customer_id' => $this->otherOwner->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->date,
            'start_time' => '18:00:00',
            'end_time' => '18:30:00',
            'duration_minutes' => 30,
            'total_price' => 50000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);
        $item = BookingItem::query()->create([
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'start_time' => '18:00:00',
            'end_time' => '18:30:00',
            'duration_minutes' => 30,
            'unit_price' => 100000,
            'subtotal' => 50000,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $payload = [
            'booking_date' => $this->date,
            'lock_type' => 'emergency',
            'reason' => 'Khóa đồng thời toàn bộ sân cùng loại.',
            'slots' => [
                [
                    'venue_court_id' => $this->court->id,
                    'start_time' => '18:00:00',
                    'end_time' => '18:30:00',
                ],
                [
                    'venue_court_id' => $this->secondCourt->id,
                    'start_time' => '18:00:00',
                    'end_time' => '18:30:00',
                ],
            ],
        ];

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.affected_count', 1)
            ->assertJsonCount(0, 'data.items.0.alternatives')
            ->assertJsonCount(0, 'data.items.0.full_item_alternatives');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', $payload + [
                'resolutions' => [[
                    'booking_item_id' => $item->id,
                    'action' => 'switch',
                    'venue_court_id' => $this->secondCourt->id,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolutions');

        $this->assertDatabaseMissing('slot_locks', [
            'booking_date' => $this->date,
            'lock_type' => 'emergency',
        ]);
        $this->assertDatabaseHas('booking_items', [
            'id' => $item->id,
            'venue_court_id' => $this->court->id,
            'status' => 'active',
        ]);
    }

    public function test_emergency_lock_cancels_only_the_overlapping_part_by_default(): void
    {
        $booking = Booking::query()->create([
            'booking_code' => 'BKPARTIAL01',
            'customer_id' => $this->otherOwner->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->date,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'duration_minutes' => 120,
            'total_price' => 140000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);
        $item = BookingItem::query()->create([
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'duration_minutes' => 120,
            'unit_price' => 70000,
            'subtotal' => 140000,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '14:00:00',
                'end_time' => '15:00:00',
                'lock_type' => 'emergency',
                'reason' => 'Mặt sân hỏng đột xuất.',
                'resolutions' => [[
                    'booking_item_id' => $item->id,
                    'action' => 'cancel',
                ]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('booking_items', [
            'id' => $item->id,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'duration_minutes' => 60,
            'subtotal' => 70000,
            'status' => 'cancelled_by_maintenance',
        ]);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'duration_minutes' => 60,
            'subtotal' => 70000,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_emergency_lock_can_move_the_whole_booking_item_when_requested(): void
    {
        $booking = Booking::query()->create([
            'booking_code' => 'BKFULLSWITCH01',
            'customer_id' => $this->otherOwner->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->date,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'duration_minutes' => 120,
            'total_price' => 140000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);
        $item = BookingItem::query()->create([
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'duration_minutes' => 120,
            'unit_price' => 70000,
            'subtotal' => 140000,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '14:00:00',
                'end_time' => '15:00:00',
                'lock_type' => 'emergency',
                'reason' => 'Mặt sân hỏng đột xuất.',
                'resolutions' => [[
                    'booking_item_id' => $item->id,
                    'scope' => 'booking_item',
                    'action' => 'switch',
                    'venue_court_id' => $this->secondCourt->id,
                ]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('booking_items', [
            'id' => $item->id,
            'venue_court_id' => $this->secondCourt->id,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'duration_minutes' => 120,
            'status' => 'moved',
        ]);
        $this->assertDatabaseMissing('booking_items', [
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);
    }

    public function test_batch_lock_is_rolled_back_when_one_range_is_unavailable(): void
    {
        Booking::query()->create([
            'booking_code' => 'BKBATCHLOCK',
            'customer_id' => $this->owner->id,
            'venue_court_id' => $this->secondCourt->id,
            'requested_venue_court_id' => $this->secondCourt->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->date,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'booking_date' => $this->date,
                'reason' => 'Khóa nhiều sân.',
                'slots' => [
                    [
                        'venue_court_id' => $this->court->id,
                        'start_time' => '08:00:00',
                        'end_time' => '09:00:00',
                    ],
                    [
                        'venue_court_id' => $this->secondCourt->id,
                        'start_time' => '09:00:00',
                        'end_time' => '10:00:00',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slots.1.start_time');

        $this->assertDatabaseMissing('slot_locks', [
            'venue_court_id' => $this->court->id,
            'booking_date' => $this->date,
        ]);
    }

    public function test_owner_can_only_delete_manual_lock_in_visible_cluster(): void
    {
        $manual = $this->createLock('manual', null, 'Bảo trì.');
        $auto = $this->createLock('auto', null, null);

        $this->actingAs($this->otherOwner, 'sanctum')
            ->deleteJson('/api/owner/schedule-locks/'.$manual->id)
            ->assertForbidden();

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/owner/schedule-locks/'.$auto->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_lock');

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/owner/schedule-locks/'.$manual->id)
            ->assertOk()
            ->assertJsonPath('message', 'Đã mở lại khung giờ.');

        $this->assertDatabaseMissing('slot_locks', ['id' => $manual->id]);
        $this->assertDatabaseHas('slot_locks', ['id' => $auto->id]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'schedule_lock.deleted',
            'entity_id' => $manual->id,
        ]);
    }

    public function test_owner_can_unlock_selected_cells_without_removing_the_rest_of_the_lock(): void
    {
        $lock = SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => $this->date,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'locked_by' => $this->owner->id,
            'lock_type' => 'manual',
            'reason' => 'Bảo trì theo ca.',
            'expires_at' => Carbon::parse($this->date)->endOfDay(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks/unlock', [
                'ranges' => [[
                    'schedule_lock_id' => $lock->id,
                    'start_time' => '14:30:00',
                    'end_time' => '15:30:00',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Đã mở các khung giờ được chọn.')
            ->assertJsonCount(2, 'data.remaining_locks');

        $this->assertDatabaseMissing('slot_locks', ['id' => $lock->id]);
        $this->assertDatabaseHas('slot_locks', [
            'venue_court_id' => $this->court->id,
            'booking_date' => $this->date,
            'start_time' => '14:00:00',
            'end_time' => '14:30:00',
            'reason' => 'Bảo trì theo ca.',
        ]);
        $this->assertDatabaseHas('slot_locks', [
            'venue_court_id' => $this->court->id,
            'booking_date' => $this->date,
            'start_time' => '15:30:00',
            'end_time' => '16:00:00',
            'reason' => 'Bảo trì theo ca.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'schedule_lock.partially_unlocked',
            'entity_id' => $lock->id,
        ]);
    }

    public function test_owner_cannot_unlock_time_outside_the_selected_lock(): void
    {
        $lock = $this->createLock('manual', null, 'Bảo trì.');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks/unlock', [
                'ranges' => [[
                    'schedule_lock_id' => $lock->id,
                    'start_time' => '14:30:00',
                    'end_time' => '15:30:00',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ranges');

        $this->assertDatabaseHas('slot_locks', [
            'id' => $lock->id,
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
        ]);
    }

    public function test_unlock_resolves_the_cluster_from_the_schedule_lock_when_numeric_ids_overlap(): void
    {
        $this->createLock('manual', null, 'Khóa đệm.');
        $manual = $this->createLock('manual', null, 'Bảo trì.');

        $pendingCluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Cụm sân chờ ký hợp đồng',
            'slug' => 'cum-san-cho-ky-hop-dong',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'pending',
        ]);

        $this->assertSame($pendingCluster->id, $manual->id);

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/owner/schedule-locks/'.$manual->id.'?venue_cluster_id='.$pendingCluster->id)
            ->assertOk()
            ->assertJsonPath('message', 'Đã mở lại khung giờ.');

        $this->assertDatabaseMissing('slot_locks', ['id' => $manual->id]);
    }

    public function test_expired_lock_cleanup_does_not_delete_manual_locks(): void
    {
        $manual = SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => today()->subDay(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'locked_by' => $this->owner->id,
            'lock_type' => 'manual',
            'reason' => 'Bảo trì.',
            'expires_at' => now()->subHour(),
        ]);

        $auto = SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => today(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'locked_by' => $this->owner->id,
            'lock_type' => 'auto',
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('app:release-expired-slot-locks');

        $this->assertDatabaseHas('slot_locks', ['id' => $manual->id]);
        $this->assertDatabaseMissing('slot_locks', ['id' => $auto->id]);
    }

    public function test_owner_can_list_manual_locks_for_a_date_range_with_status_information(): void
    {
        foreach ([1, 2, 4] as $offset) {
            SlotLock::query()->create([
                'venue_cluster_id' => $this->cluster->id,
                'venue_court_id' => $this->court->id,
                'lock_scope' => 'court',
                'booking_date' => today()->addDays($offset)->toDateString(),
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
                'locked_by' => $this->owner->id,
                'lock_type' => 'manual',
                'reason' => 'Bảo trì theo kế hoạch.',
                'expires_at' => today()->addDays($offset)->endOfDay(),
            ]);
        }

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/schedule-locks?'.http_build_query([
                'venue_cluster_id' => $this->cluster->id,
                'start_date' => today()->addDay()->toDateString(),
                'end_date' => today()->addDays(2)->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame(
            ['Đang áp dụng'],
            collect($response->json('data'))->pluck('status_label')->unique()->values()->all(),
        );
    }

    private function createLock(string $type, ?string $bookingId, ?string $reason): SlotLock
    {
        return SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => $this->date,
            'start_time' => $type === 'manual' ? '15:00:00' : '16:00:00',
            'end_time' => $type === 'manual' ? '16:00:00' : '17:00:00',
            'locked_by' => $this->owner->id,
            'booking_id' => $bookingId,
            'lock_type' => $type,
            'reason' => $reason,
            'expires_at' => now()->addWeek(),
        ]);
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'username' => $username,
            'full_name' => $username,
            'email' => $username.'@sportgo.test',
            'phone' => '09'.random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function assignRole(User $user, Role $role): void
    {
        UserRole::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => 0,
        ]);
    }
}
