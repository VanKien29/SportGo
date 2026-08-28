<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_code', 80)->unique();
            $table->string('job_type', 50);
            $table->date('as_of_date');
            $table->boolean('dry_run')->default(false);
            $table->string('status', 30)->default('running');
            $table->unsignedInteger('scanned_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['job_type', 'as_of_date'], 'pf_automation_runs_type_date_index');
        });

        Schema::create('platform_fee_automation_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('automation_run_id');
            $table->unsignedBigInteger('venue_cluster_id')->nullable();
            $table->unsignedBigInteger('ledger_id')->nullable();
            $table->string('result', 30);
            $table->string('reason', 255)->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['automation_run_id', 'result'], 'pf_automation_results_run_result_index');
            $table->foreign('automation_run_id')->references('id')->on('platform_fee_automation_runs')->cascadeOnDelete();
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->nullOnDelete();
            $table->foreign('ledger_id')->references('id')->on('venue_platform_fee_ledgers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_automation_results');
        Schema::dropIfExists('platform_fee_automation_runs');
    }
};
