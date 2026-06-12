<?php

namespace App\Console\Commands;

use App\Models\VenueCluster;
use App\Models\VenueAccessRestriction;
use App\Models\VenuePlatformFeeLedger;
use App\Models\PartnerTerminationRequest;
use App\Models\PolicyRule;
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
    public function handle()
    {
        $this->info('Starting scanning of venue access policies...');

        // 1. Process platform fee overdue locks
        $this->processPlatformFeeOverdueLocks();

        // 2. Process partner contract termination transition and locks
        $this->processContractTerminations();

        // 3. Synchronize status of all clusters based on access restrictions
        $this->syncClustersStatus();

        $this->info('Finished scanning of venue access policies.');

        return self::SUCCESS;
    }

    private function processPlatformFeeOverdueLocks()
    {
        $this->info('Processing platform fee overdue locks...');

        // Find the active rule for platform fee overdue lock
        $rule = PolicyRule::where('rule_code', 'platform_fee_overdue_lock')
            ->where('is_active', true)
            ->first();

        // Default to 7 days if rule doesn't exist
        $lockAfterDays = 7;
        if ($rule) {
            $lockAfterDays = $rule->result_json['lock_after_days'] ?? 7;
        }

        // Select all clusters
        $clusters = VenueCluster::all();

        foreach ($clusters as $cluster) {
            // Find if there is any ledger of this cluster that is overdue by >= $lockAfterDays days
            $hasOverdueLedger = VenuePlatformFeeLedger::where('venue_cluster_id', $cluster->id)
                ->where('status', '!=', 'paid')
                ->where('due_date', '<=', Carbon::now()->subDays($lockAfterDays)->toDateString())
                ->exists();

            if ($hasOverdueLedger) {
                // Find or create an active restriction
                VenueAccessRestriction::updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'restriction_type' => 'platform_fee_overdue',
                        'status' => 'active',
                    ],
                    [
                        'access_mode' => 'limited',
                        'reason' => 'Cụm sân quá hạn phí duy trì hệ thống.',
                        'starts_at' => Carbon::now(),
                        'ends_at' => null,
                    ]
                );

                // Update ledger lock timestamp
                VenuePlatformFeeLedger::where('venue_cluster_id', $cluster->id)
                    ->where('status', '!=', 'paid')
                    ->where('due_date', '<=', Carbon::now()->subDays($lockAfterDays)->toDateString())
                    ->whereNull('locked_venue_at')
                    ->update(['locked_venue_at' => Carbon::now()]);

                $this->info("Cluster {$cluster->name} ({$cluster->id}) is locked due to overdue platform fee.");
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
