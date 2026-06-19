<?php

namespace App\Console\Commands;

use App\Models\PartnerApplication;
use App\Models\PartnerTerminationRequest;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        $requests = PartnerTerminationRequest::where('status', 'approved')
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $oneMonthAgo)
            ->with('application.user')
            ->get();

        $count = 0;
        $roleId = DB::table('roles')->where('name', 'venue_owner')->value('id');
        
        if (!$roleId) {
            $this->error('Role venue_owner not found.');
            return;
        }

        foreach ($requests as $request) {
            $application = $request->application;
            if (!$application) {
                continue;
            }

            $user = $application->user;
            if ($user) {
                // Determine if the user has any other active clusters
                $hasOtherActiveClusters = PartnerApplication::where('user_id', $user->id)
                    ->where('id', '!=', $application->id)
                    ->whereNull('terminated_at')
                    ->where('status', 'approved') // assuming approved means active contract
                    ->exists();

                if (!$hasOtherActiveClusters) {
                    DB::table('user_roles')
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
