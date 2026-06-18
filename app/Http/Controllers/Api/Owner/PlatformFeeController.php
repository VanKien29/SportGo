<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\SystemBankAccount;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlatformFeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'uuid'],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $ledgers = VenuePlatformFeeLedger::query()
            ->with(['tier', 'paymentProofMedia'])
            ->where('venue_cluster_id', $cluster->id)
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (VenuePlatformFeeLedger $ledger): array => $this->ledgerPayload($ledger));

        $outstanding = $ledgers
            ->whereIn('effective_status', ['pending', 'overdue'])
            ->sum('amount_remaining');

        return response()->json([
            'data' => $ledgers->values(),
            'summary' => [
                'total' => $ledgers->count(),
                'pending' => $ledgers->where('effective_status', 'pending')->count(),
                'overdue' => $ledgers->where('effective_status', 'overdue')->count(),
                'outstanding_amount' => round($outstanding, 2),
            ],
            'venue_cluster' => [
                'id' => $cluster->id,
                'name' => $cluster->name,
            ],
            'payment_account' => $this->paymentAccountPayload(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::query()
            ->with(['tier', 'paymentProofMedia'])
            ->findOrFail($id);

        $this->ownedCluster($request, $ledger->venue_cluster_id);

        return response()->json([
            'data' => $this->ledgerPayload($ledger),
            'payment_account' => $this->paymentAccountPayload(),
        ]);
    }

    public function submitProof(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::query()->findOrFail($id);
        $cluster = $this->ownedCluster($request, $ledger->venue_cluster_id);

        if (in_array($ledger->status, ['paid', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Kỳ phí này đã hoàn tất hoặc đã hủy, không thể gửi thêm minh chứng.',
            ], 422);
        }

        if ($ledger->payment_proof_status === 'submitted') {
            return response()->json([
                'message' => 'Minh chứng đang chờ quản trị viên kiểm tra.',
            ], 422);
        }
        if ((float) $ledger->amount_due <= (float) $ledger->amount_paid) {
            return response()->json([
                'message' => 'Kỳ phí này không còn số tiền cần thanh toán.',
            ], 422);
        }

        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $request->file('proof');
        $path = $file->store('platform-fee-proofs/'.now()->format('Y/m'), 'public');
        $oldValues = [
            'payment_proof_media_id' => $ledger->payment_proof_media_id,
            'payment_proof_status' => $ledger->payment_proof_status,
            'payment_proof_note' => $ledger->payment_proof_note,
        ];

        try {
            DB::transaction(function () use ($request, $ledger, $cluster, $file, $path, $data, $oldValues): void {
                $media = Media::query()->create([
                    'mediable_type' => VenuePlatformFeeLedger::class,
                    'mediable_id' => $ledger->id,
                    'collection' => 'payment_proof',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                $ledger->update([
                    'payment_proof_media_id' => $media->id,
                    'payment_proof_status' => 'submitted',
                    'payment_proof_note' => $data['note'] ?? null,
                    'payment_rejected_by' => null,
                    'payment_rejected_at' => null,
                    'payment_reject_reason' => null,
                ]);

                AuditLog::query()->create([
                    'actor_id' => $request->user()->id,
                    'actor_type' => 'owner',
                    'module' => 'platform_fee',
                    'action' => 'platform_fee.proof_submitted',
                    'entity_type' => VenuePlatformFeeLedger::class,
                    'entity_id' => $ledger->id,
                    'old_values' => $oldValues,
                    'new_values' => [
                        'payment_proof_media_id' => $media->id,
                        'payment_proof_status' => 'submitted',
                        'payment_proof_note' => $data['note'] ?? null,
                    ],
                    'context' => 'owner',
                    'metadata' => [
                        'venue_cluster_id' => $cluster->id,
                        'file_name' => $media->file_name,
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        $ledger->load(['tier', 'paymentProofMedia']);

        return response()->json([
            'message' => 'Đã gửi minh chứng thanh toán. Quản trị viên sẽ kiểm tra và xác nhận.',
            'data' => $this->ledgerPayload($ledger),
        ]);
    }

    private function ownedCluster(Request $request, string $clusterId): VenueCluster
    {
        $cluster = VenueCluster::query()->findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            abort(403, 'Bạn không có quyền xem phí của cụm sân này.');
        }

        return $cluster;
    }

    private function ledgerPayload(VenuePlatformFeeLedger $ledger): array
    {
        $effectiveStatus = $this->effectiveStatus($ledger);
        $dueDate = $ledger->due_date ?? $ledger->period_end;
        $daysUntilDue = $dueDate ? today()->diffInDays($dueDate, false) : null;
        $amountRemaining = max(0, (float) $ledger->amount_due - (float) $ledger->amount_paid);

        return [
            'id' => $ledger->id,
            'court_count' => $ledger->court_count,
            'billing_cycle' => $ledger->billing_cycle,
            'period_months' => $ledger->period_months,
            'period_start' => $ledger->period_start?->toDateString(),
            'period_end' => $ledger->period_end?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'price_per_court_month' => (float) $ledger->price_per_court_month,
            'discount_percent' => (float) $ledger->discount_percent,
            'amount_due' => (float) $ledger->amount_due,
            'amount_paid' => (float) $ledger->amount_paid,
            'amount_remaining' => round($amountRemaining, 2),
            'payment_reference' => 'SPORTGO-PF-'.strtoupper(substr(str_replace('-', '', $ledger->id), 0, 12)),
            'status' => $ledger->status,
            'effective_status' => $effectiveStatus,
            'paid_at' => $ledger->paid_at?->toISOString(),
            'days_until_due' => $daysUntilDue,
            'warning_level' => match (true) {
                $effectiveStatus === 'overdue' => 'overdue',
                $effectiveStatus === 'pending' && $daysUntilDue !== null && $daysUntilDue <= 7 => 'due_soon',
                default => null,
            },
            'tier' => $ledger->tier ? [
                'id' => $ledger->tier->id,
                'name' => $ledger->tier->name,
            ] : null,
            'payment_proof' => [
                'status' => $ledger->payment_proof_status,
                'note' => $ledger->payment_proof_note,
                'reject_reason' => $ledger->payment_reject_reason,
                'submitted_at' => $ledger->paymentProofMedia?->created_at?->toISOString(),
                'file_name' => $ledger->paymentProofMedia?->file_name,
                'file_url' => $ledger->paymentProofMedia
                    ? Storage::disk('public')->url($ledger->paymentProofMedia->file_path)
                    : null,
            ],
        ];
    }

    private function effectiveStatus(VenuePlatformFeeLedger $ledger): string
    {
        if (in_array($ledger->status, ['paid', 'cancelled'], true)) {
            return $ledger->status;
        }

        $dueDate = $ledger->due_date ?? $ledger->period_end;

        return $dueDate && Carbon::parse($dueDate)->isBefore(today()) ? 'overdue' : 'pending';
    }

    private function paymentAccountPayload(): ?array
    {
        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->first();

        if (! $account) {
            return null;
        }

        return [
            'bank_name' => $account->bank_name,
            'bank_code' => $account->bank_code,
            'account_number' => $account->account_number,
            'account_holder_name' => $account->account_holder_name,
        ];
    }
}
