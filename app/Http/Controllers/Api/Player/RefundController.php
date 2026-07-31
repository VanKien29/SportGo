<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\Request;

class RefundController extends Controller
{
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
