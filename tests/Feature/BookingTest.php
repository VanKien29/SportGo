<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\CourtType;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $player;
    private VenueCourt $court;
    private VenueCluster $cluster;
    private CourtType $courtType;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo vai trò người chơi
        $role = Role::create([
            'name' => 'user',
            'display_name' => 'User',
            'is_system' => true,
        ]);

        // 2. Tạo người chơi (Player)
        $this->player = User::create([
            'username' => 'player_test',
            'full_name' => 'Player Test',
            'email' => 'player@sportgo.vn',
            'phone' => '0999999999',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        UserRole::create([
            'user_id' => $this->player->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        // 3. Tạo loại sân
        $this->courtType = CourtType::create([
            'name' => 'Badminton',
            'description' => 'Sân cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        // 4. Tạo cụm sân và cấu hình
        $owner = User::create([
            'username' => 'owner_test',
            'full_name' => 'Owner Test',
            'email' => 'owner@sportgo.vn',
            'phone' => '0888888888',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->cluster = VenueCluster::create([
            'owner_id' => $owner->id,
            'name' => 'SportGo Test Cluster',
            'slug' => 'sportgo-test-cluster',
            'address' => 'Ha Noi',
            'latitude' => 21.0278000,
            'longitude' => 105.8342000,
            'status' => 'active',
            'rating_avg' => 0,
            'rating_count' => 0,
        ]);

        BookingConfig::create([
            'venue_cluster_id' => $this->cluster->id,
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 180,
            'slot_hold_minutes' => 20,
            'allow_full_payment' => true,
            'allow_deposit' => true,
            'allow_no_prepay' => true,
            'deposit_percent' => 30.00,
        ]);

        // 5. Tạo sân con
        $this->court = VenueCourt::create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Sân Số 1',
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    /**
     * Test API kiểm tra trống sân khi không có bất kỳ đơn nào.
     */
    public function test_check_availability_when_no_bookings(): void
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson("/api/bookings/check-availability?" . http_build_query([
                'venue_court_id' => $this->court->id,
                'booking_date' => '2026-06-01',
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
            ]));

        $response->assertStatus(200)
            ->assertJson([
                'available' => true,
            ]);
    }

    /**
     * Test API kiểm tra trống sân khi bị trùng với đơn đặt hiện hữu.
     */
    public function test_check_availability_when_overlapping_booking_exists(): void
    {
        // Tạo booking trùng giờ
        Booking::create([
            'booking_code' => 'BK123456',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-01',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'total_price' => 150000.00,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0.00,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // Kiểm tra khung giờ overlap hoàn toàn
        $response1 = $this->actingAs($this->player, 'sanctum')
            ->getJson("/api/bookings/check-availability?" . http_build_query([
                'venue_court_id' => $this->court->id,
                'booking_date' => '2026-06-01',
                'start_time' => '08:30:00',
                'end_time' => '09:00:00',
            ]));

        $response1->assertStatus(200)->assertJson(['available' => false]);
    }

    /**
     * Test API kiểm tra trống sân khi bị trùng với Slot Lock tạm thời.
     */
    public function test_check_availability_when_slot_lock_exists(): void
    {
        // Tạo Slot Lock
        SlotLock::create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => '2026-06-01',
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'locked_by' => $this->player->id,
            'lock_type' => 'auto',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson("/api/bookings/check-availability?" . http_build_query([
                'venue_court_id' => $this->court->id,
                'booking_date' => '2026-06-01',
                'start_time' => '14:30:00',
                'end_time' => '15:30:00',
            ]));

        $response->assertStatus(200)->assertJson(['available' => false]);
    }

    /**
     * Test tạo đơn không trả trước (no_prepay) thành công.
     */
    public function test_create_booking_no_prepay_success(): void
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => '2026-06-01',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'payment_option' => 'no_prepay',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'payment_option' => 'no_prepay',
                'required_payment_amount' => 0.00,
                'status' => 'pending_approval',
            ]);

        $this->assertDatabaseHas('bookings', [
            'venue_court_id' => $this->court->id,
            'payment_option' => 'no_prepay',
            'status' => 'pending_approval',
        ]);

        // Đơn no_prepay không được tạo slot_locks giữ chỗ
        $this->assertDatabaseMissing('slot_locks', [
            'venue_court_id' => $this->court->id,
        ]);
    }

    /**
     * Test đặt sân yêu cầu đặt cọc (deposit) thành công.
     */
    public function test_create_booking_deposit_success(): void
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => '2026-06-01',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'payment_option' => 'deposit',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'payment_option' => 'deposit',
                'status' => 'pending_payment',
            ]);

        // Cần đảm bảo Required Payment Amount = 30% của tổng tiền
        $booking = Booking::first();
        $expectedAmount = $booking->total_price * 0.30;
        $this->assertEquals($expectedAmount, $booking->required_payment_amount);

        // Đơn trả trước/cọc phải tự động sinh slot lock giữ sân 20 phút
        $this->assertDatabaseHas('slot_locks', [
            'booking_id' => $booking->id,
            'lock_scope' => 'court',
        ]);
    }

    /**
     * Test đặt sân bị lỗi do trùng lịch.
     */
    public function test_create_booking_overlap_fails(): void
    {
        // Đặt trước 1 đơn
        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => '2026-06-01',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'payment_option' => 'no_prepay',
            ]);

        // Đặt đơn thứ 2 trùng khung giờ
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => '2026-06-01',
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'payment_option' => 'no_prepay',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Sân đã bị đặt hoặc đang được giữ chỗ trong khung giờ này.',
            ]);
    }

    /**
     * Test command giải phóng slot lock quá hạn 20 phút.
     */
    public function test_release_expired_slot_locks_command(): void
    {
        // 1. Tạo đơn chờ thanh toán và khoá slot hết hạn
        $booking = Booking::create([
            'booking_code' => 'BKEXPIRED',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000.00,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000.00,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_payment',
        ]);

        SlotLock::create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'locked_by' => $this->player->id,
            'booking_id' => $booking->id,
            'lock_type' => 'auto',
            'expires_at' => Carbon::now()->subMinutes(1), // Đã hết hạn cách đây 1 phút
        ]);

        // 2. Chạy lệnh giải phóng slot
        $exitCode = Artisan::call('app:release-expired-slot-locks');
        $this->assertEquals(0, $exitCode);

        // 3. Kiểm tra DB: Slot lock bị xoá, booking chuyển sang expired
        $this->assertDatabaseMissing('slot_locks', [
            'booking_id' => $booking->id,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'expired',
            'status_reason' => 'Thanh toán quá hạn 20 phút.',
        ]);
    }

    /**
     * Test API thanh toán VNPAY thành công.
     */
    public function test_vnpay_payment_success(): void
    {
        $this->useTestVnpayConfig();

        // 1. Tạo đơn chờ thanh toán và khoá slot hợp lệ
        $booking = Booking::create([
            'booking_code' => 'BKVNPAY',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000.00,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000.00,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_payment',
        ]);

        SlotLock::create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'locked_by' => $this->player->id,
            'booking_id' => $booking->id,
            'lock_type' => 'auto',
            'expires_at' => Carbon::now()->addMinutes(20),
        ]);

        // 2. Gọi API tạo URL thanh toán VNPAY
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/payments/vnpay");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'payment_url',
                'payment' => ['id', 'payment_code', 'booking_id', 'amount', 'method', 'status'],
                'booking' => ['id', 'booking_code', 'status'],
            ])
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.method', 'vnpay');

        $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();

        // 3. Giả lập VNPAY redirect về return URL với chữ ký hợp lệ
        $returnPayload = [
            'vnp_Amount' => 10000000,
            'vnp_BankCode' => 'NCB',
            'vnp_BankTranNo' => 'VNPTEST123',
            'vnp_CardType' => 'ATM',
            'vnp_OrderInfo' => 'Thanh toan don dat san '.$booking->booking_code,
            'vnp_PayDate' => now()->format('YmdHis'),
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'TESTCODE',
            'vnp_TransactionNo' => '14123456',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $payment->payment_code,
        ];

        ksort($returnPayload);
        $returnPayload['vnp_SecureHash'] = hash_hmac(
            'sha512',
            collect($returnPayload)
                ->map(fn ($value, $key) => urlencode((string) $key) . '=' . urlencode((string) $value))
                ->implode('&'),
            'TESTSECRET'
        );

        $response = $this->getJson('/api/payments/vnpay/return?'.http_build_query($returnPayload));

        $response->assertStatus(200)
            ->assertJsonPath('payment_status', 'success')
            ->assertJsonPath('booking_id', $booking->id);

        // 4. Kiểm tra DB: Slot lock bị xoá, đơn thành confirmed, Payment paid
        $this->assertDatabaseMissing('slot_locks', [
            'booking_id' => $booking->id,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'status' => 'paid',
            'method' => 'vnpay',
        ]);

    }

    /**
     * Test VNPAY trả amount sai thì không được xác nhận booking.
     */
    public function test_vnpay_return_fails_when_amount_does_not_match(): void
    {
        $this->useTestVnpayConfig();

        $booking = $this->createPendingPaymentBooking('BKAMOUNT');
        $payment = $this->createPendingVnpayPayment($booking, 'PMAMOUNT');
        $payload = $this->signedVnpayPayload($payment, $booking, 9000000, [
            'vnp_TransactionNo' => 'TXNAMOUNT',
        ]);

        $response = $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload));

        $response->assertStatus(200)
            ->assertJsonPath('payment_status', 'failed');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $payment->id,
            'error_code' => 'invalid_amount',
            'error_message' => 'Số tiền VNPAY trả về không khớp với số tiền giao dịch.',
        ]);
    }

    /**
     * Test người dùng hủy giao dịch bên VNPAY.
     */
    public function test_vnpay_return_marks_payment_failed_when_user_cancels(): void
    {
        $this->useTestVnpayConfig();

        $booking = $this->createPendingPaymentBooking('BKCANCEL');
        $payment = $this->createPendingVnpayPayment($booking, 'PMCANCEL');
        $payload = $this->signedVnpayPayload($payment, $booking, 10000000, [
            'vnp_ResponseCode' => '24',
            'vnp_TransactionNo' => 'TXNCANCEL',
            'vnp_TransactionStatus' => '02',
        ]);

        $response = $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload));

        $response->assertStatus(200)
            ->assertJsonPath('payment_status', 'failed');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $payment->id,
            'error_code' => '24',
            'error_message' => 'Người dùng đã hủy giao dịch VNPAY.',
        ]);
    }

    /**
     * Test giao dịch VNPAY timeout thì payment fail, booking không confirm.
     */
    public function test_vnpay_return_marks_payment_failed_when_gateway_times_out(): void
    {
        $this->useTestVnpayConfig();

        $booking = $this->createPendingPaymentBooking('BKTIMEOUT');
        $payment = $this->createPendingVnpayPayment($booking, 'PMTIMEOUT');
        $payload = $this->signedVnpayPayload($payment, $booking, 10000000, [
            'vnp_ResponseCode' => '11',
            'vnp_TransactionNo' => 'TXNTIMEOUT',
            'vnp_TransactionStatus' => '02',
        ]);

        $response = $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload));

        $response->assertStatus(200)
            ->assertJsonPath('payment_status', 'failed');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $payment->id,
            'error_code' => '11',
            'error_message' => 'Giao dịch VNPAY đã hết hạn thanh toán.',
        ]);
    }

    /**
     * Test retry payment: pending cũ quá hạn sẽ bị fail và tạo payment mới.
     */
    public function test_vnpay_retry_marks_stale_pending_payment_failed_and_creates_new_payment(): void
    {
        $this->useTestVnpayConfig();
        config()->set('services.vnpay.pending_ttl_minutes', 15);

        $booking = $this->createPendingPaymentBooking('BKSTALE');
        $stalePayment = $this->createPendingVnpayPayment($booking, 'PMSTALE');
        $stalePayment->forceFill([
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ])->save();

        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/payments/vnpay");

        $response->assertStatus(200)
            ->assertJsonPath('payment.status', 'pending');

        $newPaymentId = $response->json('payment.id');
        $this->assertNotSame($stalePayment->id, $newPaymentId);

        $this->assertDatabaseHas('payments', [
            'id' => $stalePayment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $newPaymentId,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $stalePayment->id,
            'event_type' => 'vnpay_pending_stale',
            'error_code' => 'stale_pending_payment',
        ]);
    }

    /**
     * Test callback lặp lại sau khi payment đã paid thì không downgrade trạng thái.
     */
    public function test_vnpay_duplicate_return_after_paid_is_idempotent(): void
    {
        $this->useTestVnpayConfig();

        $booking = $this->createPendingPaymentBooking('BKDUPCALL');
        $payment = $this->createPendingVnpayPayment($booking, 'PMDUPCALL');
        $payload = $this->signedVnpayPayload($payment, $booking, 10000000, [
            'vnp_TransactionNo' => 'TXNDUPCALL',
        ]);

        $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload))
            ->assertStatus(200)
            ->assertJsonPath('payment_status', 'success');

        $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload))
            ->assertStatus(200)
            ->assertJsonPath('payment_status', 'success');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'gateway_txn_id' => 'TXNDUPCALL',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $payment->id,
            'event_type' => 'vnpay_return_duplicate',
            'error_code' => 'duplicate_callback',
        ]);
    }

    /**
     * Test gateway trả transaction id đã tồn tại ở payment khác.
     */
    public function test_vnpay_return_fails_when_gateway_transaction_id_is_duplicate(): void
    {
        $this->useTestVnpayConfig();

        $paidBooking = $this->createPendingPaymentBooking('BKPAIDTXN');
        $this->createPendingVnpayPayment($paidBooking, 'PMPAIDTXN')->forceFill([
            'gateway_txn_id' => 'TXNEXISTS',
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $booking = $this->createPendingPaymentBooking('BKDUPTXN');
        $payment = $this->createPendingVnpayPayment($booking, 'PMDUPTXN');
        $payload = $this->signedVnpayPayload($payment, $booking, 10000000, [
            'vnp_TransactionNo' => 'TXNEXISTS',
        ]);

        $response = $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload));

        $response->assertStatus(200)
            ->assertJsonPath('payment_status', 'failed');

        $payment->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertNull($payment->gateway_txn_id);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $payment->id,
            'error_code' => 'duplicate_gateway_txn_id',
            'error_message' => 'Mã giao dịch VNPAY đã tồn tại ở giao dịch khác.',
        ]);
    }

    /**
     * Test API lấy dữ liệu khởi tạo cụm sân.
     */
    public function test_booking_init_data(): void
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/bookings/init');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'clusters' => [
                    '*' => [
                        'id',
                        'name',
                        'venue_courts',
                    ]
                ]
            ]);
    }

    private function useTestVnpayConfig(): void
    {
        config()->set('services.vnpay.tmn_code', 'TESTCODE');
        config()->set('services.vnpay.hash_secret', 'TESTSECRET');
        config()->set('services.vnpay.payment_url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        config()->set('services.vnpay.return_url', 'http://localhost/api/payments/vnpay/return');
    }

    private function createPendingPaymentBooking(string $bookingCode): Booking
    {
        $booking = Booking::create([
            'booking_code' => $bookingCode,
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000.00,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000.00,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_payment',
        ]);

        SlotLock::create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'locked_by' => $this->player->id,
            'booking_id' => $booking->id,
            'lock_type' => 'auto',
            'expires_at' => Carbon::now()->addMinutes(20),
        ]);

        return $booking;
    }

    private function createPendingVnpayPayment(Booking $booking, string $paymentCode): Payment
    {
        return Payment::query()->create([
            'payment_code' => $paymentCode,
            'booking_id' => $booking->id,
            'amount' => $booking->required_payment_amount,
            'payment_kind' => 'full',
            'method' => 'vnpay',
            'status' => 'pending',
        ]);
    }

    private function signedVnpayPayload(Payment $payment, Booking $booking, int $amount, array $overrides = []): array
    {
        $payload = array_merge([
            'vnp_Amount' => $amount,
            'vnp_BankCode' => 'NCB',
            'vnp_BankTranNo' => 'VNPTEST123',
            'vnp_CardType' => 'ATM',
            'vnp_OrderInfo' => 'Thanh toan don dat san '.$booking->booking_code,
            'vnp_PayDate' => now()->format('YmdHis'),
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'TESTCODE',
            'vnp_TransactionNo' => '14123456',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $payment->payment_code,
        ], $overrides);

        ksort($payload);
        $payload['vnp_SecureHash'] = hash_hmac(
            'sha512',
            collect($payload)
                ->map(fn ($value, $key) => urlencode((string) $key) . '=' . urlencode((string) $value))
                ->implode('&'),
            'TESTSECRET'
        );

        return $payload;
    }
}
