<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Policies\Evaluators\PlatformFeePolicyEvaluator;

use App\Services\Policies\Evaluators\PermissionRevokePolicyEvaluator;
use App\Services\Policies\Evaluators\PartnerContractPolicyEvaluator;

class RunPolicyEvaluations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sportgo:run-policy-evaluations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chạy các trình đánh giá chính sách định kỳ (ví dụ: phí nền tảng, thu hồi quyền, hợp đồng đối tác)';

    /**
     * Execute the console command.
     */
    public function handle(
        PlatformFeePolicyEvaluator $platformFeeEvaluator, 
        PermissionRevokePolicyEvaluator $permissionRevokeEvaluator,
        PartnerContractPolicyEvaluator $partnerContractEvaluator
    ) {
        $this->info('Starting policy evaluations...');

        // 1. Run Platform Fee Evaluator
        $this->info('Evaluating Platform Fee Policy...');
        $platformFeeEvaluator->evaluate();

        // 2. Run Permission Revoke Evaluator
        $this->info('Evaluating Permission Revoke Policy...');
        $permissionRevokeEvaluator->evaluate();

        // 3. Run Partner Contract Evaluator
        $this->info('Evaluating Partner Contract Policy...');
        $partnerContractEvaluator->evaluate();

        $this->info('Policy evaluations completed successfully.');
    }
}
