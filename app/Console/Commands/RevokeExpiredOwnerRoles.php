<?php

namespace App\Console\Commands;

use App\Models\PartnerApplication;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RevokeExpiredOwnerRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:revoke-expired-owner-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke venue_owner role from users whose contracts have been terminated for more than 1 month';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $oneMonthAgo = Carbon::now()->subMonth();

        $expiredApplications = PartnerApplication::whereNotNull('terminated_at')
            ->where('terminated_at', '<=', $oneMonthAgo)
            // Ideally, we would add a flag to avoid processing the same record multiple times, e.g., 'role_revoked_at'
            // For now, we will just attempt to remove the role.
            ->get();

        $count = 0;
        $roleId = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'venue_owner')->value('id');
        
        if (!$roleId) {
            $this->error('Role venue_owner not found.');
            return;
        }

        foreach ($expiredApplications as $application) {
            $user = $application->user;
            if ($user) {
                // Determine if the user has any other active clusters
                $hasOtherActiveClusters = PartnerApplication::where('user_id', $user->id)
                    ->where('id', '!=', $application->id)
                    ->whereNull('terminated_at')
                    ->where('status', 'approved') // assuming approved means active contract
                    ->exists();

                if (!$hasOtherActiveClusters) {
                    \Illuminate\Support\Facades\DB::table('user_roles')
                        ->where('user_id', $user->id)
                        ->where('role_id', $roleId)
                        ->delete();
                    $this->info("Revoked venue_owner role from User ID: {$user->id}");
                    $count++;
                }
            }
        }

        $this->info("Successfully revoked roles for {$count} users.");
    }
}
