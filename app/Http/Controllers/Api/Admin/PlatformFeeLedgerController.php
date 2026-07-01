<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternalReceipt;
use App\Models\PlatformFeeEmailLog;
use App\Models\PlatformFeeTier;
use App\Models\VenueAccessRestriction;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use App\Services\Payments\PlatformFeePaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class PlatformFeeLedgerController extends Controller
{
    public function __construct(private readonly PlatformFeePaymentService $platformFeePayments) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'period_months' => ['nullable', 'integer'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'overdue_only' => ['nullable'],
            'range' => ['nullable', 'string'],
            'email_status' => ['nullable', 'string'],
            'keyword' => ['nullable', 'string', 'max:120'],
        ]);

        $query = VenuePlatformFeeLedger::query()
            ->with(['venueCluster.owner', 'tier', 'internalReceipt', 'emailLogs'])
            ->when($data['venue_cluster_id'] ?? null, fn ($query, string $id) => $query->where('venue_cluster_id', $id))
            ->when($data['owner_id'] ?? null, fn ($query, string $id) => $query->whereHas('venueCluster', fn ($venueQuery) => $venueQuery->where('owner_id', $id)))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['period_months'] ?? null, fn ($query, int $months) => $query->where('period_months', $months))
            ->when($data['period_start'] ?? null, fn ($query, string $date) => $query->whereDate('period_start', '>=', $date))
            ->when($data['period_end'] ?? null, fn ($query, string $date) => $query->whereDate('period_end', '<=', $date))
            ->when($data['due_date'] ?? null, fn ($query, string $date) => $query->whereDate('due_date', $date))
            ->when($this->truthy($data['overdue_only'] ?? false), fn ($query) => $query
                ->where(function ($innerQuery): void {
                    $innerQuery
                        ->where('status', 'overdue')
                        ->orWhere(function ($pendingQuery): void {
                            $pendingQuery
                                ->where('status', 'pending')
                                ->whereDate('due_date', '<', now()->toDateString());
                        });
                }))
            ->when(($data['range'] ?? '') === 'this_month', fn ($query) => $query
                ->whereNotNull('paid_at')
                ->whereYear('paid_at', now()->year)
                ->whereMonth('paid_at', now()->month))
            ->when($data['email_status'] ?? null, function ($query, string $emailStatus): void {
                if ($emailStatus === 'not_sent') {
                    $query->whereDoesntHave('emailLogs');
                    return;
                }

                if ($emailStatus === 'failed') {
                    $query->whereHas('emailLogs', fn ($emailQuery) => $emailQuery->where('status', 'failed'));
                    return;
                }

                $query->whereHas('emailLogs', fn ($emailQuery) => $emailQuery
                    ->where('type', $emailStatus)
                    ->where('status', 'sent'));
            })
            ->when($data['keyword'] ?? null, function ($query, string $keyword): void {
                $keyword = trim($keyword);
                $query->where(function ($searchQuery) use ($keyword): void {
                    $searchQuery
                        ->where('payment_code', 'like', "%{$keyword}%")
                        ->orWhere('id', 'like', "%{$keyword}%")
                        ->orWhereHas('venueCluster', fn ($venueQuery) => $venueQuery->where('name', 'like', "%{$keyword}%"))
                        ->orWhereHas('venueCluster.owner', fn ($ownerQuery) => $ownerQuery
                            ->where('full_name', 'like', "%{$keyword}%")
                            ->orWhere('username', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%"));
                });
            });

        $ledgers = $query
            ->orderByDesc('paid_at')
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (VenuePlatformFeeLedger $ledger): array => $this->ledgerPayload($ledger));

        return response()->json($ledgers);
    }

    public function show(string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::query()
            ->with(['venueCluster.owner', 'tier', 'internalReceipt', 'emailLogs'])
            ->findOrFail($id);

        return response()->json($this->ledgerPayload($ledger));
    }



    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate($this->createRules());

        return response()->json($this->previewPayload($data));
    }

    public function emailLogs(string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::query()->findOrFail($id);

        return response()->json(
            $ledger->emailLogs()
                ->with('triggeredBy:id,username,full_name,email')
                ->get()
                ->map(fn (PlatformFeeEmailLog $log): array => $this->emailLogPayload($log))
        );
    }

    public function sendReminder(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:due_soon_7_days,due_today,overdue_3_days,manual'],
            'force' => ['nullable', 'boolean'],
        ]);

        $ledger = VenuePlatformFeeLedger::query()
            ->with(['venueCluster.owner', 'tier', 'internalReceipt', 'emailLogs'])
            ->findOrFail($id);

        if (in_array($ledger->status, ['paid', 'cancelled'], true)) {
            return response()->json(['message' => 'Kỳ phí đã kết thúc, không cần gửi nhắc phí.'], 422);
        }

        $type = $data['type'];
        $existing = $ledger->emailLogs()
            ->where('type', $type)
            ->whereIn('status', ['queued', 'sent'])
            ->first();

        if ($existing && ! ($data['force'] ?? false)) {
            return response()->json([
                'message' => 'Email nhắc phí này đã được ghi nhận trước đó.',
                'data' => $this->emailLogPayload($existing),
            ]);
        }

        $owner = $ledger->venueCluster?->owner;
        $subject = $this->reminderSubject($type);
        $content = $this->reminderContent($ledger, $type);

        $log = PlatformFeeEmailLog::query()->create([
            'ledger_id' => $ledger->id,
            'venue_cluster_id' => $ledger->venue_cluster_id,
            'type' => $type,
            'email' => $owner?->email,
            'subject' => $subject,
            'content' => $content,
            'status' => 'queued',
            'queued_at' => now(),
            'triggered_by' => $request->user()?->id,
            'metadata' => [
                'ledger_status' => $ledger->status,
                'amount_due' => (float) $ledger->amount_due,
                'amount_paid' => (float) $ledger->amount_paid,
                'remaining_amount' => max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0),
            ],
        ]);

        if (! $owner?->email) {
            $log->forceFill([
                'status' => 'failed',
                'sent_at' => now(),
                'error_reason' => 'Chủ sân chưa có email.',
            ])->save();

            return response()->json([
                'message' => 'Không gửi được email vì chủ sân chưa có email.',
                'data' => $this->emailLogPayload($log),
            ], 422);
        }

        try {
            Mail::raw($content, function ($message) use ($owner, $subject): void {
                $message->to($owner->email)->subject($subject);
            });

            $log->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'error_reason' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $log->forceFill([
                'status' => 'failed',
                'sent_at' => now(),
                'error_reason' => $exception->getMessage(),
            ])->save();

            return response()->json([
                'message' => 'Gửi email nhắc phí thất bại.',
                'data' => $this->emailLogPayload($log),
            ], 422);
        }

        return response()->json([
            'message' => 'Đã gửi email nhắc phí.',
            'data' => $this->emailLogPayload($log),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->createRules());
        $preview = $this->previewPayload($data);

        if (! $preview['isValid']) {
            return response()->json([
                'message' => $preview['error'],
                'preview' => $preview,
            ], 422);
        }

        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $preview['venue']['id'],
            'creation_source' => 'admin',
            'tier_id' => $preview['tier']['id'],
            'tier_name_snapshot' => $preview['tier']['name'],
            'tier_min_courts_snapshot' => $preview['tier']['min_courts'],
            'tier_max_courts_snapshot' => $preview['tier']['max_courts'],
            'court_count' => $preview['court_count'],
            'billing_cycle' => (int) $preview['period_months'] === 12 ? 'yearly' : 'monthly',
            'period_months' => $preview['period_months'],
            'period_start' => $preview['period_start'],
            'period_end' => $preview['period_end'],
            'due_date' => $preview['due_date'],
            'price_per_court_month' => $preview['tier']['price_per_court_month'],
            'discount_percent' => $preview['fee']['discount_percent'],
            'pricing_snapshotted_at' => now(),
            'amount_due' => $preview['fee']['amount_due'],
            'amount_paid' => 0,
            'payment_proof_status' => 'none',
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Đã tạo kỳ phí chờ thanh toán.',
            'data' => $this->ledgerPayload($ledger->fresh(['venueCluster.owner', 'tier', 'internalReceipt'])),
        ], 201);
    }

    public function pay(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $ledger = DB::transaction(function () use ($request, $id, $data): VenuePlatformFeeLedger {
            $ledger = VenuePlatformFeeLedger::query()
                ->with(['venueCluster.owner', 'tier', 'internalReceipt'])
                ->lockForUpdate()
                ->findOrFail($id);

            if ($ledger->status === 'cancelled') {
                abort(422, 'Kỳ phí đã hủy không thể thanh toán.');
            }

            $amount = min((float) $data['amount'], max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0));

            if ($amount <= 0) {
                abort(422, 'Kỳ phí đã thanh toán đủ.');
            }

            $newPaid = round((float) $ledger->amount_paid + $amount, 2);
            $isPaid = $newPaid >= (float) $ledger->amount_due;

            $ledger->forceFill([
                'amount_paid' => $newPaid,
                'status' => $isPaid ? 'paid' : $ledger->status,
                'paid_at' => $isPaid ? now() : $ledger->paid_at,
                'payment_proof_status' => $isPaid ? 'approved' : $ledger->payment_proof_status,
                'payment_confirmed_by' => $isPaid ? $request->user()?->id : $ledger->payment_confirmed_by,
                'payment_confirmed_at' => $isPaid ? now() : $ledger->payment_confirmed_at,
            ])->save();

            if ($isPaid) {
                $receipt = $this->issueReceipt($ledger->fresh(['venueCluster.owner']), $request->user()?->id);
                $ledger->forceFill(['internal_receipt_id' => $receipt->id])->save();
                $this->clearPlatformFeeRestriction($ledger, $request->user()?->id);
            }

            return $ledger->fresh(['venueCluster.owner', 'tier', 'internalReceipt']);
        });

        return response()->json([
            'message' => 'Đã xác nhận thanh toán kỳ phí.',
            'data' => $this->ledgerPayload($ledger),
        ]);
    }

    public function overdue(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $ledger = VenuePlatformFeeLedger::query()->findOrFail($id);

        if (in_array($ledger->status, ['paid', 'cancelled'], true)) {
            return response()->json(['message' => 'Kỳ phí đã kết thúc không thể đánh dấu quá hạn.'], 422);
        }

        $ledger->forceFill([
            'status' => 'overdue',
            'payment_proof_note' => $data['reason'] ?? 'Quá hạn thanh toán',
        ])->save();

        return response()->json([
            'message' => 'Đã đánh dấu quá hạn.',
            'data' => $this->ledgerPayload($ledger->fresh(['venueCluster.owner', 'tier', 'internalReceipt'])),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $ledger = VenuePlatformFeeLedger::query()->findOrFail($id);

        try {
            $ledger = $this->platformFeePayments->cancelPendingLedger(
                $ledger,
                $request->user()?->id,
                'admin',
                $data['reason'],
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Đã hủy kỳ phí.',
            'data' => $this->ledgerPayload($ledger->fresh(['venueCluster.owner', 'tier', 'internalReceipt'])),
        ]);
    }

    public function lockVenue(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $ledger = DB::transaction(function () use ($request, $id, $data): VenuePlatformFeeLedger {
            $ledger = VenuePlatformFeeLedger::query()
                ->with(['venueCluster'])
                ->lockForUpdate()
                ->findOrFail($id);

            if ($ledger->status !== 'overdue') {
                abort(422, 'Chỉ khóa cụm khi kỳ phí đã quá hạn.');
            }

            $ledger->venueCluster?->forceFill([
                'status' => 'locked',
                'status_reason' => $data['reason'],
                'locked_at' => now(),
                'locked_by' => $request->user()?->id,
            ])->save();

            VenueAccessRestriction::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $ledger->venue_cluster_id,
                    'restriction_type' => 'platform_fee_overdue',
                    'status' => 'active',
                ],
                [
                    'access_mode' => 'limited',
                    'reason' => $data['reason'],
                    'starts_at' => now(),
                    'ends_at' => null,
                    'created_by' => $request->user()?->id,
                ],
            );

            $ledger->forceFill(['locked_venue_at' => now()])->save();

            return $ledger->fresh(['venueCluster.owner', 'tier', 'internalReceipt']);
        });

        return response()->json([
            'message' => 'Đã khóa cụm sân vì quá hạn phí.',
            'data' => $this->ledgerPayload($ledger),
        ]);
    }

    public function unlockVenue(Request $request, string $id): JsonResponse
    {
        $ledger = DB::transaction(function () use ($request, $id): VenuePlatformFeeLedger {
            $ledger = VenuePlatformFeeLedger::query()
                ->with(['venueCluster'])
                ->lockForUpdate()
                ->findOrFail($id);

            if ($ledger->status !== 'paid') {
                abort(422, 'Chỉ mở khóa sau khi kỳ phí đã thanh toán đủ.');
            }

            $this->clearPlatformFeeRestriction($ledger, $request->user()?->id);

            return $ledger->fresh(['venueCluster.owner', 'tier', 'internalReceipt']);
        });

        return response()->json([
            'message' => 'Đã mở khóa cụm sân.',
            'data' => $this->ledgerPayload($ledger),
        ]);
    }

    private function createRules(): array
    {
        return [
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'period_months' => ['required', 'integer', 'in:1,3,6,9,12'],
            'period_start' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    private function previewPayload(array $data): array
    {
        $cluster = VenueCluster::query()
            ->withCount('venueCourts')
            ->findOrFail($data['venue_cluster_id']);
        $courtCount = max(1, (int) $cluster->venue_courts_count);
        $tier = $this->tierForCourtCount($courtCount);

        if (! $tier) {
            return [
                'isValid' => false,
                'error' => 'Chưa có bậc phí phù hợp với số sân của cụm này.',
                'warnings' => [],
            ];
        }

        $periodMonths = (int) $data['period_months'];
        $periodStart = CarbonImmutable::parse($data['period_start'])->startOfDay();
        $periodEnd = $periodStart->addMonthsNoOverflow($periodMonths)->subDay();
        $dueDate = $data['due_date'] ?? $periodEnd->toDateString();
        $baseAmount = round($courtCount * (float) $tier->price_per_court_month * $periodMonths, 2);
        $discountPercent = $periodMonths === 12 ? (float) $tier->annual_discount_percent : 0.0;
        $discountAmount = round($baseAmount * $discountPercent / 100, 2);
        $amountDue = round($baseAmount - $discountAmount, 2);
        $overlap = VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', '!=', 'cancelled')
            ->whereDate('period_start', '<=', $periodEnd->toDateString())
            ->whereDate('period_end', '>=', $periodStart->toDateString())
            ->exists();

        if ($overlap) {
            return [
                'isValid' => false,
                'error' => 'Đã có kỳ phí trùng thời gian cho cụm sân này.',
                'venue' => $this->venuePayload($cluster),
                'tier' => $this->tierPayload($tier),
                'fee' => compact('baseAmount', 'discountPercent', 'discountAmount', 'amountDue'),
                'warnings' => [],
            ];
        }

        return [
            'isValid' => true,
            'venue' => $this->venuePayload($cluster),
            'tier' => $this->tierPayload($tier),
            'court_count' => $courtCount,
            'period_months' => $periodMonths,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'due_date' => $dueDate,
            'fee' => [
                'base_amount' => $baseAmount,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'amount_due' => $amountDue,
                'warnings' => [],
            ],
            'warnings' => [],
        ];
    }

    private function tierForCourtCount(int $courtCount): ?PlatformFeeTier
    {
        return PlatformFeeTier::query()
            ->where('is_active', true)
            ->where('min_courts', '<=', $courtCount)
            ->where(function ($query) use ($courtCount): void {
                $query->whereNull('max_courts')
                    ->orWhere('max_courts', '>=', $courtCount);
            })
            ->orderByDesc('min_courts')
            ->first();
    }

    private function issueReceipt(VenuePlatformFeeLedger $ledger, ?string $adminId): InternalReceipt
    {
        return InternalReceipt::query()->updateOrCreate(
            [
                'receipt_code' => 'RCPT-FEE-'.strtoupper(substr(hash('sha256', $ledger->id), 0, 18)),
            ],
            [
                'receipt_type' => 'platform_fee',
                'receiptable_type' => VenuePlatformFeeLedger::class,
                'receiptable_id' => $ledger->id,
                'issued_to_user_id' => $ledger->venueCluster?->owner_id,
                'issued_by' => $adminId,
                'title' => 'Phiếu thu phí nền tảng',
                'amount' => $ledger->amount_paid,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => now(),
                'metadata' => [
                    'venue_cluster_id' => $ledger->venue_cluster_id,
                    'period_start' => $ledger->period_start?->toDateString(),
                    'period_end' => $ledger->period_end?->toDateString(),
                    'period_months' => $ledger->period_months,
                    'court_count' => $ledger->court_count,
                ],
            ],
        );
    }

    private function clearPlatformFeeRestriction(VenuePlatformFeeLedger $ledger, ?string $adminId): void
    {
        VenueAccessRestriction::query()
            ->where('venue_cluster_id', $ledger->venue_cluster_id)
            ->where('restriction_type', 'platform_fee_overdue')
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
                'ends_at' => now(),
            ]);

        if ($ledger->venueCluster?->status === 'locked') {
            $ledger->venueCluster->forceFill([
                'status' => 'active',
                'status_reason' => null,
                'locked_at' => null,
                'locked_until' => null,
                'locked_by' => null,
            ])->save();
        }
    }

    private function ledgerPayload(VenuePlatformFeeLedger $ledger): array
    {
        $baseAmount = round((float) $ledger->price_per_court_month * (int) $ledger->court_count * (int) $ledger->period_months, 2);
        $amountDue = (float) $ledger->amount_due;
        $amountPaid = (float) $ledger->amount_paid;
        $remainingAmount = round(max($amountDue - $amountPaid, 0), 2);
        $code = $ledger->payment_code ?: 'PF-'.strtoupper(substr(str_replace('-', '', $ledger->id), 0, 10));
        $venue = $ledger->venueCluster;
        $owner = $venue?->owner;
        $periodState = $this->periodState($ledger);
        $periodDaysRemaining = $ledger->period_end ? (int) today()->diffInDays($ledger->period_end, false) : null;
        $tierName = $ledger->tier_name_snapshot
            ?: $ledger->tier?->name
            ?: ($ledger->tier_id ? 'Bậc phí #'.$ledger->tier_id : 'Theo cấu hình');

        return [
            'id' => $ledger->id,
            'code' => $code,
            'venue_cluster_id' => $ledger->venue_cluster_id,
            'creation_source' => $ledger->creation_source,
            'can_cancel' => ! in_array($ledger->status, ['paid', 'cancelled'], true)
                && $amountPaid <= 0,
            'tier_id' => $ledger->tier_id,
            'tier_name' => $tierName,
            'tier_min_courts_snapshot' => $ledger->tier_min_courts_snapshot,
            'tier_max_courts_snapshot' => $ledger->tier_max_courts_snapshot,
            'pricing_snapshotted_at' => $ledger->pricing_snapshotted_at?->toISOString(),
            'court_count' => (int) $ledger->court_count,
            'period_months' => (int) $ledger->period_months,
            'billing_cycle' => $ledger->billing_cycle,
            'period_start' => $ledger->period_start?->toDateString(),
            'period_end' => $ledger->period_end?->toDateString(),
            'due_date' => $ledger->due_date?->toDateString(),
            'is_current_period' => $periodState === 'active',
            'period_state' => $periodState,
            'period_days_remaining' => $periodDaysRemaining,
            'period_warning_level' => match (true) {
                $periodState === 'expired' && ! in_array($ledger->status, ['paid', 'cancelled'], true) => 'overdue',
                $periodState === 'active' && $periodDaysRemaining !== null && $periodDaysRemaining <= 7 => 'expiring_soon',
                default => null,
            },
            'period_label' => $this->periodLabel($ledger),
            'snapshot_note' => 'Số tiền và điều kiện của kỳ này được giữ nguyên theo snapshot khi tạo kỳ phí.',
            'price_per_court_month' => (float) $ledger->price_per_court_month,
            'discount_percent' => (float) $ledger->discount_percent,
            'base_amount' => $baseAmount,
            'discount_amount' => round(max($baseAmount - $amountDue, 0), 2),
            'amount_due' => $amountDue,
            'amount_paid' => $amountPaid,
            'remaining_amount' => $remainingAmount,
            'status' => $ledger->status,
            'paid_at' => $ledger->paid_at?->toISOString(),
            'cancelled_reason' => $ledger->payment_reject_reason,
                'email_logs' => $ledger->emailLogs
                    ? $ledger->emailLogs->map(fn (PlatformFeeEmailLog $log): array => $this->emailLogPayload($log))->values()
                    : [],
            'receipt' => $ledger->internalReceipt ? [
                'id' => $ledger->internalReceipt->id,
                'code' => $ledger->internalReceipt->receipt_code ?? $ledger->internalReceipt->code ?? $ledger->internalReceipt->id,
                'amount' => (float) ($ledger->internalReceipt->amount ?? $amountPaid),
                'issued_at' => $ledger->internalReceipt->issued_at?->toISOString(),
                'content' => $ledger->internalReceipt->title ?? 'Phiếu thu phí nền tảng.',
            ] : null,
            'venue' => $venue ? [
                'id' => $venue->id,
                'name' => $venue->name,
                'status' => $venue->status,
                'owner_id' => $venue->owner_id,
            ] : null,
            'owner' => $owner ? [
                'id' => $owner->id,
                'full_name' => $owner->full_name ?: $owner->username,
                'email' => $owner->email,
                'phone' => $owner->phone,
            ] : null,
        ];
    }

    private function venuePayload(VenueCluster $venue): array
    {
        return [
            'id' => $venue->id,
            'name' => $venue->name,
            'status' => $venue->status,
            'owner_id' => $venue->owner_id,
        ];
    }

    private function tierPayload(PlatformFeeTier $tier): array
    {
        return [
            'id' => $tier->id,
            'name' => $tier->name,
            'min_courts' => (int) $tier->min_courts,
            'max_courts' => $tier->max_courts !== null ? (int) $tier->max_courts : null,
            'price_per_court_month' => (float) $tier->price_per_court_month,
            'annual_discount_percent' => (float) $tier->annual_discount_percent,
        ];
    }

    private function periodState(VenuePlatformFeeLedger $ledger): string
    {
        if (! $ledger->period_start || ! $ledger->period_end) {
            return 'unknown';
        }

        if (today()->lt($ledger->period_start)) {
            return 'upcoming';
        }

        if (today()->gt($ledger->period_end)) {
            return 'expired';
        }

        return 'active';
    }

    private function periodLabel(VenuePlatformFeeLedger $ledger): string
    {
        $months = (int) ($ledger->period_months ?: 1);

        return "Kỳ {$months} tháng";
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    private function emailLogPayload(PlatformFeeEmailLog $log): array
    {
        return [
            'id' => $log->id,
            'ledger_id' => $log->ledger_id,
            'venue_cluster_id' => $log->venue_cluster_id,
            'type' => $log->type,
            'email' => $log->email,
            'subject' => $log->subject,
            'content' => $log->content,
            'status' => $log->status,
            'queued_at' => $log->queued_at?->toISOString(),
            'sent_at' => $log->sent_at?->toISOString(),
            'error_reason' => $log->error_reason,
            'triggered_by' => $log->triggeredBy ? [
                'id' => $log->triggeredBy->id,
                'name' => $log->triggeredBy->full_name ?: $log->triggeredBy->username,
                'email' => $log->triggeredBy->email,
            ] : null,
            'created_at' => $log->created_at?->toISOString(),
        ];
    }

    private function reminderSubject(string $type): string
    {
        return [
            'due_soon_7_days' => 'Phí duy trì sắp đến hạn',
            'due_today' => 'Hôm nay là hạn đóng phí duy trì',
            'overdue_3_days' => 'Phí duy trì đã quá hạn 3 ngày',
            'manual' => 'Nhắc thanh toán phí duy trì',
        ][$type] ?? 'Nhắc thanh toán phí duy trì';
    }

    private function reminderContent(VenuePlatformFeeLedger $ledger, string $type): string
    {
        $venueName = $ledger->venueCluster?->name ?: 'cụm sân';
        $remaining = number_format(max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0), 0, ',', '.');
        $dueDate = $ledger->due_date?->format('d/m/Y') ?: '-';
        $line = [
            'due_soon_7_days' => 'sẽ đến hạn sau 7 ngày',
            'due_today' => 'đến hạn trong hôm nay',
            'overdue_3_days' => 'đã quá hạn 3 ngày và có thể bị hạn chế vận hành',
            'manual' => 'cần được thanh toán',
        ][$type] ?? 'cần được thanh toán';

        return "Kỳ phí duy trì của {$venueName} {$line}.\nHạn thanh toán: {$dueDate}.\nSố tiền còn lại: {$remaining} VND.\nVui lòng đăng nhập SportGo Owner để xử lý.";
    }
}
