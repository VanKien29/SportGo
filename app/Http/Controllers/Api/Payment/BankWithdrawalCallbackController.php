<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\OwnerWithdrawalRequest;
use App\Services\Finance\AdminWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BankWithdrawalCallbackController extends Controller
{
    public function __construct(private readonly AdminWithdrawalService $withdrawals) {}

    public function __invoke(Request $request): JsonResponse
    {
        $configuredKey = (string) config('services.mbbank.withdraw_callback_api_key');
        $providedKey = preg_replace('/^(Apikey|Bearer)\s+/i', '', (string) $request->header('Authorization'));

        if ($configuredKey === '' || ! hash_equals($configuredKey, (string) $providedKey)) {
            return response()->json(['message' => 'API key callback không hợp lệ.'], 401);
        }

        $data = $request->validate([
            'withdraw_code' => ['required', 'string', 'max:30'],
            'status' => ['required', 'boolean'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'message' => ['nullable', 'string', 'max:2000'],
            'transfer_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $withdrawal = OwnerWithdrawalRequest::query()
            ->where('request_code', $data['withdraw_code'])
            ->firstOrFail();

        if ($data['status'] && (float) ($data['amount'] ?? 0) !== (float) $withdrawal->amount) {
            return response()->json(['message' => 'Số tiền callback không khớp yêu cầu rút.'], 422);
        }

        try {
            $updated = $this->withdrawals->updateStatus($withdrawal, $data['status'] ? 'completed' : 'rejected', [
                'actor_id' => null,
                'reason' => $data['message'] ?? ($data['status'] ? 'MB Bank xác nhận chuyển tiền thành công.' : 'MB Bank báo chuyển tiền thất bại.'),
                'source' => 'mbbank_callback',
                'transfer_reference' => $data['transfer_reference'] ?? $data['withdraw_code'],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Đã xử lý callback rút tiền.',
            'data' => [
                'request_code' => $updated->request_code,
                'status' => $updated->status,
                'transfer_reference' => $updated->transfer_reference,
            ],
        ]);
    }
}
