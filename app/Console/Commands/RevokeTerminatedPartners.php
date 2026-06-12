<?php

namespace App\Console\Commands;

use App\Models\PartnerProfile;
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
    protected $description = 'Revoke FIELD_OWNER role for terminated partner profiles older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $profiles = PartnerProfile::where('status', 'terminated')
            ->where('terminated_at', '<=', now()->subDays(30))
            ->get();

        foreach ($profiles as $profile) {
            $user = $profile->user;
            if ($user) {
                // Lock account
                $user->update([
                    'status' => 'locked',
                    'status_reason' => 'Contract terminated over 30 days ago',
                    'locked_at' => now(),
                    'lock_type' => 'permanent',
                ]);

                // Revoke role
                $roleId = DB::table('roles')->where('name', 'field_owner')->value('id');
                if ($roleId) {
                    DB::table('user_roles')
                        ->where('user_id', $user->id)
                        ->where('role_id', $roleId)
                        ->delete();
                }

                $this->info("Revoked access for user {$user->id} (Profile {$profile->id})");
            }
        }
    }
}
