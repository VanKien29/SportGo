<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\Policies\RefundCancellationPolicyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function __construct(private readonly RefundCancellationPolicyService $refundPolicies) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'refund_destination' => ['required', Rule::in(['user_wallet'])],
        ]);

        $result = $this->refundPolicies->requestCustomerRefund(
            Booking::query()->findOrFail($data['booking_id']),
            $request->user(),
            isset($data['amount']) ? (float) $data['amount'] : null,
            $data['refund_destination'],
            trim($data['reason']),
        );

        return response()->json([
            'message' => 'Đã gửi yêu cầu hoàn tiền. Yêu cầu sẽ được chuyển cho chủ sân xác nhận theo chính sách.',
            ...$result,
        ], 201);
    }

    public function index(Request $request)
    {
        $refunds = Refund::query()
            ->with(['booking.venueCluster', 'booking.venueCourt', 'payment', 'statusHistories'])
            ->where(function ($query) use ($request): void {
                $query->where('customer_id', $request->user()->id)
                    ->orWhereHas('booking', fn ($bookingQuery) => $bookingQuery->where('customer_id', $request->user()->id));
            })
            ->latest('created_at')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json($refunds);
    }

    public function show(Request $request, string $id)
    {
        $refund = Refund::query()
            ->with([
                'booking.venueCluster',
                'booking.venueCourt.courtType',
                'payment.logs',
                'statusHistories.changedBy',
                'payoutAccount',
            ])
            ->where(function ($query) use ($request): void {
                $query->where('customer_id', $request->user()->id)
                    ->orWhereHas('booking', fn ($bookingQuery) => $bookingQuery->where('customer_id', $request->user()->id));
            })
            ->findOrFail($id);

        return response()->json($refund);
    }
}
