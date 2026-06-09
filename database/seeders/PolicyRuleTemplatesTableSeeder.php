<?php

namespace Database\Seeders;

use App\Models\PolicyRuleTemplate;
use App\Support\PolicyUiText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PolicyRuleTemplatesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('policy_rule_templates')) {
            return;
        }

        foreach (PolicyUiText::ruleTemplateOptions() as $template) {
            PolicyRuleTemplate::query()->updateOrCreate(
                ['policy_type' => $template['policy_type'], 'rule_code' => $template['rule_code']],
                [
                    'rule_name' => $template['label'],
                    'description' => $template['description'],
                    'action_code' => $template['action_code'],
                    'condition_schema' => [
                        'type' => 'object',
                        'summary_vi' => $template['condition_summary_vi'],
                        'default' => $template['condition_json'],
                    ],
                    'result_schema' => [
                        'type' => 'object',
                        'summary_vi' => $template['result_summary_vi'],
                        'default' => $template['result_json'],
                    ],
                    'is_venue_overridable' => $template['is_venue_overridable'],
                    'risk_level' => $template['risk_level'],
                    'is_active' => true,
                ],
            );
        }
    }
}
