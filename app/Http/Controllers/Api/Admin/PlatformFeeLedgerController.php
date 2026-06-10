<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VenuePlatformFeeLedger;
use App\Models\VenueCluster;
use App\Models\PlatformFeeTier;
use App\Models\InternalReceipt;
use App\Services\Finance\FinanceReceiptService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PlatformFeeLedgerController extends Controller
{
    public function __construct(private readonly FinanceReceiptService $receiptService)
    {
    }

    /**
     * Danh sách kỳ phí duy trì
     */
    public function index(Request $request): JsonResponse
    {
        $query = VenuePlatformFeeLedger::query()
            ->with(['venueCluster.owner', 'tier', 'paymentProofMedia', 'internalReceipt']);

        if ($request->filled('venue_cluster_id')) {
            $query->where('venue_cluster_id', $request->input('venue_cluster_id'));
        }

        if ($request->filled('owner_id')) {
            $ownerId = $request->input('owner_id');
            $query->whereHas('venueCluster', function ($q) use ($ownerId) {
                $q->where('owner_id', $ownerId);
            });
        }

        if ($request->filled('status')) {
            $statusInput = $request->input('status');
            if ($statusInput === 'overdue') {
                $query->where(function ($q) {
                    $q->where('status', 'overdue')
                      ->orWhere(function ($sub) {
                          $sub->where('status', 'pending')
                              ->whereDate('due_date', '<', Carbon::today());
                      });
                });
            } elseif ($statusInput === 'pending') {
                $query->where('status', 'pending')
                      ->where(function ($sub) {
                          $sub->whereNull('due_date')
                              ->orWhereDate('due_date', '>=', Carbon::today());
                      });
            } else {
                $query->where('status', $statusInput);
            }
        }

        if ($request->filled('period_months')) {
            $query->where('period_months', $request->integer('period_months'));
        }

        if ($request->filled('period_start')) {
            $query->whereDate('period_start', '>=', $request->input('period_start'));
        }

        if ($request->filled('period_end')) {
            $query->whereDate('period_end', '<=', $request->input('period_end'));
        }

        if ($request->filled('due_date')) {
            $query->whereDate('due_date', $request->input('due_date'));
        }

        if ($request->boolean('overdue_only')) {
            $query->where(function ($q) {
                $q->where('status', 'overdue')
                  ->orWhere(function ($sub) {
                      $sub->where('status', 'pending')
                          ->whereDate('due_date', '<', Carbon::today());
                  });
            });
        }

        if ($request->filled('keyword')) {
            $q = '%' . $request->input('keyword') . '%';
            $query->where(function ($builder) use ($q) {
                $builder->where('id', 'like', $q)
                  ->orWhereHas('venueCluster', function ($v) use ($q) {
                      $v->where('name', 'like', $q)
                        ->orWhereHas('owner', function ($o) use ($q) {
                            $o->where('full_name', 'like', $q)
                              ->orWhere('email', 'like', $q);
                        });
                  });
            });
        }

        $ledgers = $query->latest('period_start')->get()->map(fn ($f) => $this->payload($f));

        return response()->json([
            'status' => 'success',
            'data' => $ledgers,
        ]);
    }

    /**
     * Thống kê KPI
     */
    public function metrics(): JsonResponse
    {
        $ledgers = VenuePlatformFeeLedger::all();
        $today = Carbon::today();

        $pending = $ledgers->filter(fn ($l) => $l->status === 'pending' && (!$l->due_date || $l->due_date->gte($today)))->count();
        $overdue = $ledgers->filter(fn ($l) => $l->status === 'overdue' || ($l->status === 'pending' && $l->due_date && $l->due_date->lt($today)))->count();

        $pendingAmount = $ledgers->filter(fn ($l) => $l->status === 'pending' && (!$l->due_date || $l->due_date->gte($today)))->sum(fn ($l) => max(0, $l->amount_due - $l->amount_paid));
        $overdueAmount = $ledgers->filter(fn ($l) => $l->status === 'overdue' || ($l->status === 'pending' && $l->due_date && $l->due_date->lt($today)))->sum(fn ($l) => max(0, $l->amount_due - $l->amount_paid));

        return response()->json([
            'status' => 'success',
            'data' => [
                'pending' => $pending,
                'overdue' => $overdue,
                'pending_amount' => $pendingAmount,
                'overdue_amount' => $overdueAmount,
            ],
        ]);
    }

    /**
     * Chi tiết kỳ phí
     */
    public function show(string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::with(['venueCluster.owner', 'tier', 'paymentProofMedia', 'internalReceipt'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->payload($ledger, true),
        ]);
    }

    /**
     * Xem trước tính toán kỳ phí
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'period_months' => ['required', 'integer', 'in:1,3,6,9,12'],
            'period_start' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        $start = Carbon::parse($data['period_start']);
        $end = $start->copy()->addMonths($data['period_months'])->subDay();
        $dueDate = ($data['due_date'] ?? null) ? Carbon::parse($data['due_date']) : $end->copy();

        $cluster = VenueCluster::withCount(['venueCourts' => function ($q) {
            $q->where('status', '!=', 'deleted');
        }])->findOrFail($data['venue_cluster_id']);

        $courtCount = max(1, $cluster->venue_courts_count);

        $tier = PlatformFeeTier::query()
            ->where('is_active', true)
            ->where('min_courts', '<=', $courtCount)
            ->where(function ($q) use ($courtCount) {
                $q->whereNull('max_courts')
                  ->orWhere('max_courts', '>=', $courtCount);
            })
            ->orderByDesc('min_courts')
            ->first();

        if (! $tier) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy cấu hình bậc phí phù hợp.',
            ], 422);
        }

        // Kiểm tra trùng thời gian (overlap)
        $overlap = VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $data['venue_cluster_id'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($sub) use ($start, $end) {
                    $sub->where('period_start', '<=', $end)
                        ->where('period_end', '>=', $start);
                });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đã có kỳ phí trùng thời gian cho cụm sân này.',
            ], 422);
        }

        $pricePerCourtMonth = (float) $tier->price_per_court_month;
        $baseAmount = $courtCount * $pricePerCourtMonth * $data['period_months'];
        $discountPercent = $data['period_months'] === 12 ? (float) $tier->annual_discount_percent : 0.0;
        $discountAmount = ($baseAmount * $discountPercent) / 100;
        $amountDue = $baseAmount - $discountAmount;

        return response()->json([
            'status' => 'success',
            'data' => [
                'isValid' => true,
                'court_count' => $courtCount,
                'tier' => [
                    'id' => $tier->id,
                    'name' => $tier->name,
                ],
                'fee' => [
                    'base_amount' => $baseAmount,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'amount_due' => $amountDue,
                ],
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'warnings' => [],
            ],
        ]);
    }

    /**
     * Tạo mới kỳ phí
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'period_months' => ['required', 'integer', 'in:1,3,6,9,12'],
            'period_start' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        $start = Carbon::parse($data['period_start']);
        $end = $start->copy()->addMonths($data['period_months'])->subDay();
        $dueDate = ($data['due_date'] ?? null) ? Carbon::parse($data['due_date']) : $end->copy();

        $cluster = VenueCluster::withCount(['venueCourts' => function ($q) {
            $q->where('status', '!=', 'deleted');
        }])->findOrFail($data['venue_cluster_id']);

        $courtCount = max(1, $cluster->venue_courts_count);

        $tier = PlatformFeeTier::query()
            ->where('is_active', true)
            ->where('min_courts', '<=', $courtCount)
            ->where(function ($q) use ($courtCount) {
                $q->whereNull('max_courts')
                  ->orWhere('max_courts', '>=', $courtCount);
            })
            ->orderByDesc('min_courts')
            ->first();

        if (! $tier) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy cấu hình bậc phí phù hợp.',
            ], 422);
        }

        // Kiểm tra trùng thời gian (overlap)
        $overlap = VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $data['venue_cluster_id'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($sub) use ($start, $end) {
                    $sub->where('period_start', '<=', $end)
                        ->where('period_end', '>=', $start);
                });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đã có kỳ phí trùng thời gian cho cụm sân này.',
            ], 422);
        }

        $pricePerCourtMonth = (float) $tier->price_per_court_month;
        $baseAmount = $courtCount * $pricePerCourtMonth * $data['period_months'];
        $discountPercent = $data['period_months'] === 12 ? (float) $tier->annual_discount_percent : 0.0;
        $discountAmount = ($baseAmount * $discountPercent) / 100;
        $amountDue = $baseAmount - $discountAmount;

        $year = now()->year;
        $latest = VenuePlatformFeeLedger::query()->count();
        $code = 'PF-' . $year . '-' . str_pad($latest + 1, 4, '0', STR_PAD_LEFT);

        $ledger = VenuePlatformFeeLedger::create([
            'venue_cluster_id' => $data['venue_cluster_id'],
            'tier_id' => $tier->id,
            'court_count' => $courtCount,
            'billing_cycle' => $data['period_months'] === 12 ? 'yearly' : 'monthly',
            'period_months' => $data['period_months'],
            'period_start' => $start,
            'period_end' => $end,
            'due_date' => $dueDate,
            'price_per_court_month' => $pricePerCourtMonth,
            'discount_percent' => $discountPercent,
            'amount_due' => $amountDue,
            'amount_paid' => 0.00,
            'status' => 'pending',
        ]);

        // Gán mã code ảo nếu cần thiết (vì cấu trúc DB ban đầu dùng UUID cho ID chính nhưng seeder gán code tay)
        // Lưu trữ code vào metadata của model hoặc gán vào chính ID (chúng ta sẽ dùng ID tự sinh của Laravel là UUID)

        return response()->json([
            'status' => 'success',
            'message' => 'Đã tạo kỳ phí thành công.',
            'data' => $this->payload($ledger),
        ]);
    }

    /**
     * Xác nhận thanh toán đủ
     */
    public function confirmPayment(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::findOrFail($id);

        if ($ledger->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Kỳ phí đã hủy không thể thanh toán.',
            ], 422);
        }

        $actor = $request->user();

        DB::transaction(function () use ($ledger, $actor) {
            $ledger->status = 'paid';
            $ledger->amount_paid = $ledger->amount_due;
            $ledger->paid_at = now();
            $ledger->payment_proof_status = 'approved';
            $ledger->payment_confirmed_by = $actor?->id;
            $ledger->payment_confirmed_at = now();
            $ledger->save();

            // Phát hành phiếu thu nội bộ
            $receipt = $this->receiptService->createPlatformFeeReceipt($ledger, $actor?->id);
            $ledger->internal_receipt_id = $receipt->id;
            $ledger->save();

            // Mở khóa cụm sân nếu đang bị khóa vì nợ phí
            $cluster = $ledger->venueCluster;
            if ($cluster && $cluster->status === 'locked' && str_contains(strtolower($cluster->status_reason), 'phí')) {
                $cluster->status = 'active';
                $cluster->status_reason = null;
                $cluster->locked_at = null;
                $cluster->locked_by = null;
                $cluster->locked_until = null;
                $cluster->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xác nhận thanh toán kỳ phí thành công.',
            'data' => $this->payload($ledger->fresh(['venueCluster', 'tier', 'paymentProofMedia', 'internalReceipt'])),
        ]);
    }

    /**
     * Từ chối bằng chứng thanh toán
     */
    public function rejectPayment(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::findOrFail($id);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do từ chối bằng chứng.',
        ]);

        $actor = $request->user();

        $ledger->payment_proof_status = 'rejected';
        $ledger->payment_reject_reason = $data['reason'];
        $ledger->payment_rejected_by = $actor?->id;
        $ledger->payment_rejected_at = now();
        $ledger->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã từ chối bằng chứng thanh toán.',
            'data' => $this->payload($ledger->fresh(['venueCluster', 'tier', 'paymentProofMedia', 'internalReceipt'])),
        ]);
    }

    /**
     * Đánh dấu quá hạn và tự động khóa cụm sân
     */
    public function markOverdue(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::findOrFail($id);

        if (in_array($ledger->status, ['paid', 'cancelled'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kỳ phí đã hoàn tất hoặc đã hủy không thể đánh dấu quá hạn.',
            ], 422);
        }

        $actor = $request->user();
        $reason = $request->input('reason', 'Quá hạn phí duy trì hệ thống');

        DB::transaction(function () use ($ledger, $actor, $reason) {
            $ledger->status = 'overdue';
            $ledger->locked_venue_at = now();
            $ledger->save();

            // Khóa cụm sân nợ phí
            $cluster = $ledger->venueCluster;
            if ($cluster && $cluster->status !== 'locked') {
                $cluster->status = 'locked';
                $cluster->status_reason = $reason;
                $cluster->locked_at = now();
                $cluster->locked_by = $actor?->id;
                $cluster->save();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đánh dấu quá hạn và khóa cụm sân thành công.',
            'data' => $this->payload($ledger->fresh(['venueCluster', 'tier', 'paymentProofMedia', 'internalReceipt'])),
        ]);
    }

    /**
     * Hủy kỳ phí
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::findOrFail($id);

        if ($ledger->status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Kỳ phí đã thanh toán không thể hủy bỏ.',
            ], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do hủy kỳ phí.',
        ]);

        $ledger->status = 'cancelled';
        $ledger->payment_proof_note = 'Hủy kỳ phí: ' . $data['reason'];
        $ledger->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã hủy kỳ phí thành công.',
            'data' => $this->payload($ledger),
        ]);
    }

    /**
     * Khóa cụm sân nợ phí
     */
    public function lockVenue(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::findOrFail($id);
        $reason = $request->input('reason', 'Quá hạn phí duy trì hệ thống');
        $actor = $request->user();

        $cluster = $ledger->venueCluster;
        if ($cluster && $cluster->status !== 'locked') {
            $cluster->status = 'locked';
            $cluster->status_reason = $reason;
            $cluster->locked_at = now();
            $cluster->locked_by = $actor?->id;
            $cluster->save();

            $ledger->locked_venue_at = now();
            $ledger->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã khóa cụm sân thành công.',
            'data' => $this->payload($ledger->fresh(['venueCluster'])),
        ]);
    }

    /**
     * Mở khóa cụm sân nợ phí
     */
    public function unlockVenue(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::findOrFail($id);

        $cluster = $ledger->venueCluster;
        if ($cluster && $cluster->status === 'locked') {
            $cluster->status = 'active';
            $cluster->status_reason = null;
            $cluster->locked_at = null;
            $cluster->locked_by = null;
            $cluster->locked_until = null;
            $cluster->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã mở khóa cụm sân thành công.',
            'data' => $this->payload($ledger->fresh(['venueCluster'])),
        ]);
    }

    /**
     * Phân tải định dạng trả về (payload) khớp với frontend
     */
    private function payload(VenuePlatformFeeLedger $ledger, bool $includeDetails = false): array
    {
        $remaining = max(0, $ledger->amount_due - $ledger->amount_paid);

        $data = [
            'id' => $ledger->id,
            'code' => 'PF-' . $ledger->created_at->format('Y') . '-' . str_pad(hexdec(substr(hash('md5', $ledger->id), 0, 4)) % 10000, 4, '0', STR_PAD_LEFT),
            'venue_cluster_id' => $ledger->venue_cluster_id,
            'tier_id' => $ledger->tier_id,
            'tier_name' => $ledger->tier?->name ?: 'Bậc phí tự động',
            'court_count' => $ledger->court_count,
            'period_months' => $ledger->period_months,
            'billing_cycle' => $ledger->billing_cycle,
            'period_start' => $ledger->period_start->toDateString(),
            'period_end' => $ledger->period_end->toDateString(),
            'due_date' => $ledger->due_date->toDateString(),
            'price_per_court_month' => $ledger->price_per_court_month,
            'discount_percent' => $ledger->discount_percent,
            'base_amount' => $ledger->court_count * $ledger->price_per_court_month * $ledger->period_months,
            'discount_amount' => ($ledger->court_count * $ledger->price_per_court_month * $ledger->period_months * $ledger->discount_percent) / 100,
            'amount_due' => $ledger->amount_due,
            'amount_paid' => $ledger->amount_paid,
            'remaining_amount' => $remaining,
            'status' => ($ledger->status === 'pending' && $ledger->due_date && $ledger->due_date->lt(Carbon::today())) ? 'overdue' : $ledger->status,
            'paid_at' => $ledger->paid_at ? $ledger->paid_at->toIso8601String() : null,
            'payment_proof_status' => $ledger->payment_proof_status ?: 'none',
            'payment_proof_note' => $ledger->payment_proof_note,
            'payment_reject_reason' => $ledger->payment_reject_reason,
            'payment_proof_media_url' => $ledger->paymentProofMedia ? asset($ledger->paymentProofMedia->file_path) : null,
            'venue' => $ledger->venueCluster ? [
                'id' => $ledger->venueCluster->id,
                'name' => $ledger->venueCluster->name,
                'status' => $ledger->venueCluster->status,
            ] : null,
            'owner' => $ledger->venueCluster && $ledger->venueCluster->owner ? [
                'id' => $ledger->venueCluster->owner->id,
                'full_name' => $ledger->venueCluster->owner->full_name,
                'email' => $ledger->venueCluster->owner->email,
            ] : null,
        ];

        // Lấy thông tin hóa đơn nội bộ
        if ($ledger->internalReceipt) {
            $data['receipt'] = [
                'id' => $ledger->internalReceipt->id,
                'code' => $ledger->internalReceipt->receipt_code,
                'amount' => $ledger->internalReceipt->amount,
                'issued_at' => $ledger->internalReceipt->issued_at->toDateString(),
                'content' => $ledger->internalReceipt->title,
            ];
        } else {
            $data['receipt'] = null;
        }

        return $data;
    }
}
