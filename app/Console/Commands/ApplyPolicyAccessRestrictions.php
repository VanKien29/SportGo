<?php

namespace App\Console\Commands;

use App\Models\VenueCluster;
use App\Models\VenueAccessRestriction;
use App\Models\VenuePlatformFeeLedger;
use App\Models\PartnerTerminationRequest;
use App\Models\PlatformFeePaymentArrangement;
use App\Models\SystemPolicy;
use App\Services\Policies\PolicyConfigurationService;
use App\Services\Payments\PlatformFeeWalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApplyPolicyAccessRestrictions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apply-policy-access-restrictions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically scans and applies policy-based access restrictions to venue clusters.';

    /**
     * Execute the console command.
     */
    public function handle(PolicyConfigurationService $policyConfigurations)
    {
        $this->info('Starting scanning of venue access policies...');

        // 1. Process platform fee overdue locks
        $this->processPlatformFeeOverdueLocks($this->platformFeeConfiguration($policyConfigurations));

        // 2. Process partner contract termination transition and locks
        $this->processContractTerminations();

        // 3. Synchronize status of all clusters based on access restrictions
        $this->syncClustersStatus();

        $this->info('Finished scanning of venue access policies.');

        return self::SUCCESS;
    }

    private function processPlatformFeeOverdueLocks(array $configuration)
    {
        $this->info('Processing platform fee overdue locks...');

        $restrictAfterDays = (int) ($configuration['restrict_overdue_days'] ?? 7);
        $lockAfterDays = (int) ($configuration['lock_overdue_days'] ?? 14);

        // Select all clusters
        $clusters = VenueCluster::all();

        foreach ($clusters as $cluster) {
            $overdueLedgers = VenuePlatformFeeLedger::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->whereRaw('amount_paid < amount_due')
                ->whereDate('due_date', '<', Carbon::today()->toDateString())
                ->get();
            foreach ($overdueLedgers as $overdueLedger) {
                if ($overdueLedger->status === 'pending') {
                    $overdueLedger->forceFill(['status' => 'overdue'])->save();
                }
                try {
                    app(PlatformFeeWalletService::class)->ensureLedgerHold(
                        $overdueLedger,
                        'Tạm giữ số dư cho kỳ phí nền tảng đã quá hạn.',
                    );
                } catch (\RuntimeException) {
                    // Cụm sân chưa phát sinh số dư vẫn bị ghi nhận nợ và xử lý theo chính sách.
                }
            }
            $overdueArrangementIds = $overdueLedgers->pluck('payment_arrangement_id')->filter()->unique();
            if ($overdueArrangementIds->isNotEmpty()) {
                PlatformFeePaymentArrangement::query()
                    ->whereIn('id', $overdueArrangementIds)
                    ->where('status', 'active')
                    ->update(['status' => 'overdue']);
            }

            $oldestDueDate = $overdueLedgers->min('due_date');
            $overdueDays = $oldestDueDate
                ? Carbon::parse($oldestDueDate)->startOfDay()->diffInDays(Carbon::today())
                : 0;

            if ($oldestDueDate && $overdueDays >= $restrictAfterDays) {
                $accessMode = $overdueDays >= $lockAfterDays ? 'blocked' : 'limited';
                $reason = $accessMode === 'blocked'
                    ? "Cụm sân quá hạn phí nền tảng {$overdueDays} ngày và đã đến mốc khóa theo chính sách."
                    : "Cụm sân quá hạn phí nền tảng {$overdueDays} ngày và đang bị hạn chế quyền theo chính sách.";

                VenueAccessRestriction::updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'restriction_type' => 'platform_fee_overdue',
                        'status' => 'active',
                    ],
                    [
                        'access_mode' => $accessMode,
                        'reason' => $reason,
                        'starts_at' => Carbon::now(),
                        'ends_at' => null,
                    ]
                );

                if ($accessMode === 'blocked') {
                    VenuePlatformFeeLedger::whereIn('id', $overdueLedgers->pluck('id'))
                        ->whereNull('locked_venue_at')
                        ->update(['locked_venue_at' => Carbon::now()]);
                }

                $this->info("Cluster {$cluster->name} ({$cluster->id}) applied {$accessMode} platform fee restriction.");
            } else {
                // If there is any active platform_fee_overdue restriction, expire it
                $activeOverdueRestriction = VenueAccessRestriction::where('venue_cluster_id', $cluster->id)
                    ->where('restriction_type', 'platform_fee_overdue')
                    ->where('status', 'active')
                    ->first();

                if ($activeOverdueRestriction) {
                    $activeOverdueRestriction->update([
                        'status' => 'expired',
                        'ends_at' => Carbon::now(),
                    ]);

                    $this->info("Platform fee overdue restriction expired for cluster {$cluster->name}.");
                }
            }
        }
    }

    private function platformFeeConfiguration(PolicyConfigurationService $configurations): array
    {
        $policy = SystemPolicy::query()
            ->with('rules')
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('policy_type', 'platform_fee')
                    ->orWhere('type', 'platform_fee');
            })
            ->where(function ($query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        return $policy
            ? $configurations->extractConfigurationData($policy)
            : ['restrict_overdue_days' => 7, 'lock_overdue_days' => 14];
    }

    private function processContractTerminations()
    {
        $this->info('Processing contract terminations...');

        // Fetch termination requests that are approved/transitioning/completed (to check for transitions/locks)
        $requests = PartnerTerminationRequest::whereNotNull('approved_at')
            ->whereIn('status', ['approved', 'transition_period', 'settlement_processing', 'completed'])
            ->get();

        foreach ($requests as $request) {
            $approvedAt = $request->approved_at;
            $transitionEndAt = $request->transition_end_at;

            if (! $transitionEndAt) {
                // Default to 30 days if not set
                $transitionEndAt = $approvedAt->copy()->addDays(30);
            }

            if (Carbon::now()->lt($transitionEndAt)) {
                // 1. Transition Period (access_mode = transition, cluster remains active)
                VenueAccessRestriction::updateOrCreate(
                    [
                        'venue_cluster_id' => $request->venue_cluster_id,
                        'restriction_type' => 'contract_termination',
                        'access_mode' => 'transition',
                        'status' => 'active',
                    ],
                    [
                        'reason' => 'Cụm sân đang trong thời gian chuyển tiếp sau khi chấm dứt hợp đồng.',
                        'starts_at' => $approvedAt,
                        'ends_at' => $transitionEndAt,
                    ]
                );

                // Expire any blocked restriction for this contract termination if it exists
                VenueAccessRestriction::where('venue_cluster_id', $request->venue_cluster_id)
                    ->where('restriction_type', 'contract_termination')
                    ->where('access_mode', 'blocked')
                    ->where('status', 'active')
                    ->update([
                        'status' => 'expired',
                        'ends_at' => Carbon::now(),
                    ]);
            } else {
                // 2. Blocked Period (access_mode = blocked, cluster is locked)
                VenueAccessRestriction::updateOrCreate(
                    [
                        'venue_cluster_id' => $request->venue_cluster_id,
                        'restriction_type' => 'contract_termination',
                        'access_mode' => 'blocked',
                        'status' => 'active',
                    ],
                    [
                        'reason' => 'Đã hết thời gian chuyển tiếp, owner bị chặn quyền quản lý cụm sân.',
                        'starts_at' => $transitionEndAt,
                        'ends_at' => null,
                    ]
                );

                // Expire any transition restriction for this contract termination
                VenueAccessRestriction::where('venue_cluster_id', $request->venue_cluster_id)
                    ->where('restriction_type', 'contract_termination')
                    ->where('access_mode', 'transition')
                    ->where('status', 'active')
                    ->update([
                        'status' => 'expired',
                        'ends_at' => Carbon::now(),
                    ]);

                // Update owner_access_revoked_at if not set
                if (! $request->owner_access_revoked_at) {
                    $request->update(['owner_access_revoked_at' => Carbon::now()]);
                }
            }
        }

        // Cleanup contract termination restrictions for clusters without active termination requests
        $activeTerminationClusterIds = PartnerTerminationRequest::whereNotNull('approved_at')
            ->whereIn('status', ['approved', 'transition_period', 'settlement_processing', 'completed'])
            ->pluck('venue_cluster_id')
            ->all();

        VenueAccessRestriction::where('restriction_type', 'contract_termination')
            ->where('status', 'active')
            ->whereNotIn('venue_cluster_id', $activeTerminationClusterIds)
            ->update([
                'status' => 'expired',
                'ends_at' => Carbon::now(),
            ]);
    }

    private function syncClustersStatus()
    {
        $this->info('Synchronizing status of all venue clusters...');

        $clusters = VenueCluster::all();

        foreach ($clusters as $cluster) {
            // Get all active access restrictions for this cluster
            $activeRestrictions = VenueAccessRestriction::where('venue_cluster_id', $cluster->id)
                ->where('status', 'active')
                ->where('starts_at', '<=', Carbon::now())
                ->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', Carbon::now());
                })
                ->get();

            // Check if there is a blocked or limited restriction
            $blockedRestriction = $activeRestrictions->firstWhere('access_mode', 'blocked');
            $limitedRestriction = $activeRestrictions->firstWhere('access_mode', 'limited');

            if ($blockedRestriction) {
                if ($cluster->status !== 'locked' || $cluster->status_reason !== $blockedRestriction->reason) {
                    $cluster->forceFill([
                        'status' => 'locked',
                        'status_reason' => $blockedRestriction->reason,
                        'locked_at' => $blockedRestriction->starts_at ?? Carbon::now(),
                    ])->save();
                    $this->info("Updated status of cluster {$cluster->name} to locked (blocked restriction).");
                }
            } elseif ($limitedRestriction) {
                if ($cluster->status !== 'locked' || $cluster->status_reason !== $limitedRestriction->reason) {
                    $cluster->forceFill([
                        'status' => 'locked',
                        'status_reason' => $limitedRestriction->reason,
                        'locked_at' => $limitedRestriction->starts_at ?? Carbon::now(),
                    ])->save();
                    $this->info("Updated status of cluster {$cluster->name} to locked (limited restriction).");
                }
            } else {
                // If the cluster is locked but the lock reason is from our policy locks,
                // and there are no active blocked/limited restrictions, we unlock the cluster.
                $policyLockReasons = [
                    'Quá hạn phí duy trì hệ thống.',
                    'Đã hết thời gian chuyển tiếp, owner bị chặn quyền quản lý cụm sân.',
                    'Cụm sân quá hạn phí duy trì hệ thống.'
                ];

                $isPolicyLocked = in_array($cluster->status_reason, $policyLockReasons, true)
                    || Str::contains((string)$cluster->status_reason, 'Quá hạn phí')
                    || Str::contains((string)$cluster->status_reason, 'chuyển tiếp');

                if ($cluster->status === 'locked' && $isPolicyLocked) {
                    $cluster->forceFill([
                        'status' => 'active',
                        'status_reason' => null,
                        'locked_at' => null,
                        'locked_until' => null,
                        'locked_by' => null,
                    ])->save();
                    $this->info("Automatically unlocked cluster {$cluster->name} as restrictions were cleared.");
                }
            }
        }
    }
}
