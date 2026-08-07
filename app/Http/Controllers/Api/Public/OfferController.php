<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('vouchers')) {
            return response()->json(['data' => []]);
        }

        $limit = min(max((int) $request->query('limit', 24), 1), 50);
        $now = now();

        $offers = DB::table('vouchers')
            ->where('status', 'active')
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $now);
            })
            ->where(function ($query): void {
                $query->whereNull('total_quantity')->orWhereColumn('used_quantity', '<', 'total_quantity');
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($offer): array => [
                'id' => (int) $offer->id,
                'code' => $offer->code,
                'name' => $offer->name,
                'description' => $offer->description,
                'owner_type' => $offer->owner_type,
                'discount_type' => $offer->discount_type,
                'discount_value' => (float) $offer->discount_value,
                'max_discount_amount' => $offer->max_discount_amount === null ? null : (float) $offer->max_discount_amount,
                'min_order_amount' => (float) ($offer->min_order_amount ?? 0),
                'valid_to' => $offer->valid_to,
            ])
            ->values();

        return response()->json(['data' => $offers]);
    }
}
