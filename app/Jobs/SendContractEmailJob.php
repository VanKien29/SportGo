<?php

namespace App\Jobs;

use App\Models\PartnerContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendContractEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $contract;

    /**
     * Create a new job instance.
     */
    public function __construct(PartnerContract $contract)
    {
        $this->contract = $contract;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mock email sending
        \Log::info("Sending contract {$this->contract->contract_number} to " . $this->contract->application?->applicant_email);
    }
}
