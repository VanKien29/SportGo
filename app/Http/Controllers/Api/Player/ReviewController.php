<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\VenueCluster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function eligible(Request $request)
    {
        $validated = $request->validate([
            'venue_cluster_id' => ['nullable', 'integer', 'exists:venue_clusters,id'],
        ]);

        $bookings = Booking::query()
            ->with('venueCluster:id,name')
            ->where('customer_id', $request->user()->id)
            ->where('status', 'completed')
            ->when(
                $validated['venue_cluster_id'] ?? null,
                fn ($query, int $clusterId) => $query->where('venue_cluster_id', $clusterId),
            )
            ->whereDoesntHave('review')
            ->orderByDesc('booking_date')
            ->limit(20)
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'booking_date' => $booking->booking_date?->toDateString(),
                'venue_cluster_id' => $booking->venue_cluster_id,
                'venue_cluster' => $booking->venueCluster?->only(['id', 'name']),
            ])
            ->values();

        return response()->json(['data' => $bookings]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking = Booking::query()->with('venueCluster')->findOrFail($validated['booking_id']);
        $this->assertEligibleBooking($booking, $request);

        if (Review::query()->where('booking_id', $booking->id)->exists()) {
            throw ValidationException::withMessages([
                'booking_id' => 'Booking này đã được đánh giá.',
            ]);
        }

        $review = DB::transaction(function () use ($validated, $booking, $request): Review {
            $review = Review::query()->create([
                'booking_id' => $booking->id,
                'customer_id' => $request->user()->id,
                'venue_cluster_id' => $booking->venue_cluster_id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'is_visible' => true,
            ]);

            $this->refreshVenueRating($booking->venue_cluster_id);

            return $review;
        });

        return response()->json([
            'message' => 'Cảm ơn bạn đã đánh giá sân.',
            'data' => $review->fresh(),
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        $review = Review::query()->findOrFail($id);

        if ((string) $review->customer_id !== (string) $request->user()->id) {
            abort(403, 'Bạn không có quyền sửa đánh giá này.');
        }

        $review->update($validated);
        $this->refreshVenueRating($review->venue_cluster_id);

        return response()->json(['message' => 'Đã cập nhật đánh giá.', 'data' => $review->fresh()]);
    }

    public function destroy(Request $request, string $id)
    {
        $review = Review::query()->findOrFail($id);
        if ((string) $review->customer_id !== (string) $request->user()->id) {
            abort(403, 'Bạn không có quyền xóa đánh giá này.');
        }

        $clusterId = $review->venue_cluster_id;
        $review->delete();
        $this->refreshVenueRating($clusterId);

        return response()->json(['message' => 'Đã xóa đánh giá.']);
    }

    private function assertEligibleBooking(Booking $booking, Request $request): void
    {
        if ((string) $booking->customer_id !== (string) $request->user()->id) {
            abort(403, 'Bạn không có quyền đánh giá booking này.');
        }

        if ($booking->status !== 'completed') {
            throw ValidationException::withMessages([
                'booking_id' => 'Chỉ booking đã hoàn tất mới có thể đánh giá.',
            ]);
        }
    }

    private function refreshVenueRating(int|string $clusterId): void
    {
        $stats = Review::query()
            ->where('venue_cluster_id', $clusterId)
            ->where('is_visible', true)
            ->selectRaw('AVG(rating) as rating_avg, COUNT(*) as rating_count')
            ->first();

        VenueCluster::query()->whereKey($clusterId)->update([
            'rating_avg' => round((float) ($stats?->rating_avg ?? 0), 2),
            'rating_count' => (int) ($stats?->rating_count ?? 0),
        ]);
    }
}
