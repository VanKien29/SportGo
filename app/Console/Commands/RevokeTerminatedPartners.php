<?php

namespace App\Console\Commands;

use App\Models\PartnerApplication;
use App\Models\PartnerTerminationRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokeTerminatedPartners extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'partners:revoke-terminated';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke VENUE_OWNER role and lock account for terminated partner applications older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $requests = PartnerTerminationRequest::where('status', 'approved')
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', now()->subDays(30))
            ->with('application.user')
            ->get();

        $roleId = DB::table('roles')->where('name', 'venue_owner')->value('id');

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
                    ->where('status', 'approved')
                    ->exists();

                if (!$hasOtherActiveClusters) {
                    // Lock account
                    $user->update([
                        'status' => 'locked',
                        'status_reason' => 'Contract terminated over 30 days ago',
                        'locked_at' => now(),
                        'lock_type' => 'permanent',
                    ]);

                    // Revoke role
                    if ($roleId) {
                        DB::table('user_roles')
                            ->where('user_id', $user->id)
                            ->where('role_id', $roleId)
                            ->delete();
                    }

                    $this->info("Revoked access and locked user {$user->id} (Application {$application->id})");
                }
            }
        }
    }
}
