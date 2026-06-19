<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AssignDefaultUserRoleCommand extends Command
{
    protected $signature = 'app:assign-default-roles';
    protected $description = 'Assign default "user" role to any user that does not have any roles';

    public function handle(): int
    {
        $this->info('Starting to assign default "user" role...');

        $role = Role::query()->where('name', 'user')->first();
        if (! $role) {
            $this->error('Role "user" not found in the database. Please seed roles first.');
            return self::FAILURE;
        }

        $usersWithoutRoles = User::query()
            ->whereDoesntHave('roles')
            ->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->info('All users already have at least one role. No action needed.');
            return self::SUCCESS;
        }

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($usersWithoutRoles as $user) {
                UserRole::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'scope_type' => 'system',
                    'scope_id' => '00000000-0000-0000-0000-000000000000',
                ]);
                $count++;
            }
            DB::commit();
            $this->info("Successfully assigned 'user' role to {$count} users.");
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error("Failed to assign roles: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
