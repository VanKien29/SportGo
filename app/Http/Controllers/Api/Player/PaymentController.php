<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\InternalReceipt;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function receipt(Request $request, string $id)
    {
        $payment = Payment::query()->with('booking.venueCluster')->findOrFail($id);

        if ((string) $payment->booking?->customer_id !== (string) $request->user()->id) {
            abort(403, 'Bạn không có quyền xem giao dịch này.');
        }

        if ($payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment' => 'Chỉ giao dịch đã thanh toán thành công mới có biên lai.',
            ]);
        }

        if (! Schema::hasTable('internal_receipts')) {
            return response()->json([
                'receipt' => [
                    'code' => $payment->payment_code,
                    'title' => 'Biên nhận thanh toán booking '.$payment->booking?->booking_code,
                    'amount' => (float) $payment->amount,
                    'issued_at' => $payment->paid_at,
                    'view_url' => null,
                ],
            ]);
        }

        $receipt = InternalReceipt::query()->firstOrCreate(
            [
                'receiptable_type' => Payment::class,
                'receiptable_id' => (string) $payment->id,
            ],
            [
                'receipt_code' => $this->receiptCode(),
                'receipt_type' => 'payment',
                'issued_to_user_id' => $request->user()->id,
                'title' => 'Biên lai thanh toán booking '.$payment->booking?->booking_code,
                'amount' => $payment->amount,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $payment->paid_at ?: now(),
                'metadata' => [
                    'booking_code' => $payment->booking?->booking_code,
                    'payment_code' => $payment->payment_code,
                    'payment_method' => $payment->method,
                    'venue_name' => $payment->booking?->venueCluster?->name,
                ],
            ],
        );

        return response()->json([
            'receipt' => [
                'id' => $receipt->id,
                'code' => $receipt->receipt_code,
                'title' => $receipt->title,
                'amount' => (float) $receipt->amount,
                'issued_at' => $receipt->issued_at,
                'view_url' => url('/receipts/'.$receipt->id),
            ],
        ]);
    }

    private function receiptCode(): string
    {
        do {
            $code = 'RC'.Str::upper(Str::random(10));
        } while (InternalReceipt::query()->where('receipt_code', $code)->exists());

        return $code;
    }
}
