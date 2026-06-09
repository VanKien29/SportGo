<?php

namespace Database\Seeders;

use App\Models\PolicyActionBinding;
use App\Models\SystemPolicy;
use App\Support\PolicyUiText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PolicyActionBindingsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('policy_action_bindings') || ! Schema::hasTable('system_policies')) {
            return;
        }

        $policies = SystemPolicy::query()
            ->where('version', 1)
            ->get()
            ->keyBy('policy_type');

        foreach (PolicyUiText::actionOptions() as $action) {
            $policy = $policies->get($action['policy_types'][0] ?? null);

            if (! $policy) {
                continue;
            }

            PolicyActionBinding::query()->updateOrCreate(
                ['system_policy_id' => $policy->id, 'action_code' => $action['action_code']],
                [
                    'module' => $action['module'],
                    'description' => $action['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
