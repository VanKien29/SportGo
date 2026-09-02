<?php

namespace Tests\Unit\Services\Bookings;

use App\Services\Bookings\BookingApprovalService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingApprovalServiceTest extends TestCase
{
    private const BUSINESS_TIMEZONE = 'Asia/Ho_Chi_Minh';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.business_timezone', self::BUSINESS_TIMEZONE);
        config()->set('app.timezone', 'UTC');
    }

    public function test_approval_deadline_is_thirty_minutes_after_creation_when_session_is_far_away(): void
    {
        $deadline = $this->deadline('2026-09-02', '21:30:00', '2026-09-02 18:07:00');

        $this->assertSame('2026-09-02 18:37:00', $deadline->setTimezone(self::BUSINESS_TIMEZONE)->format('Y-m-d H:i:s'));
    }

    public function test_next_day_booking_still_uses_thirty_minutes_after_creation(): void
    {
        $deadline = $this->deadline('2026-09-03', '10:00:00', '2026-09-02 18:07:00');

        $this->assertSame('2026-09-02 18:37:00', $deadline->setTimezone(self::BUSINESS_TIMEZONE)->format('Y-m-d H:i:s'));
    }

    public function test_booking_starting_in_fifteen_to_forty_five_minutes_has_a_fifteen_minute_lead_deadline(): void
    {
        $deadline = $this->deadline('2026-09-02', '21:30:00', '2026-09-02 21:00:00');

        $this->assertSame('2026-09-02 21:15:00', $deadline->setTimezone(self::BUSINESS_TIMEZONE)->format('Y-m-d H:i:s'));
    }

    public function test_booking_starting_in_less_than_fifteen_minutes_is_rejected(): void
    {
        try {
            app(BookingApprovalService::class)->assertApprovalWindowAvailable(
                '2026-09-02',
                '21:30:00',
                Carbon::parse('2026-09-02 21:20:00', self::BUSINESS_TIMEZONE),
            );
            $this->fail('Expected a validation exception for a booking that starts too soon.');
        } catch (ValidationException $exception) {
            $message = implode(' ', $exception->errors()['start_time'] ?? []);
            $this->assertStringContainsString('dưới 15 phút', $message);
            $this->assertStringContainsString('thanh toán đủ', $message);
        }
    }

    private function deadline(string $bookingDate, string $startTime, string $createdAt): Carbon
    {
        return app(BookingApprovalService::class)->approvalDeadlineForValues(
            $bookingDate,
            $startTime,
            Carbon::parse($createdAt, self::BUSINESS_TIMEZONE),
        );
    }
}
