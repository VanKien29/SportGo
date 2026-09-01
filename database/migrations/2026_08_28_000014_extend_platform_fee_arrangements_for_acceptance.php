<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_payment_arrangements', function (Blueprint $table): void {
            $table->unsignedInteger('terms_revision')->default(1)->after('arrangement_type');
            $table->timestamp('expires_at')->nullable()->after('payment_due_date');
            $table->json('accepted_terms_snapshot')->nullable()->after('owner_accepted_by');
            $table->string('owner_accepted_ip', 45)->nullable()->after('accepted_terms_snapshot');
            $table->text('owner_accepted_user_agent')->nullable()->after('owner_accepted_ip');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('owner_accepted_by');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->timestamp('rejected_at')->nullable()->after('cancelled_at');

            $table->index(['status', 'expires_at'], 'pf_arrangements_status_expiry_index');
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_payment_arrangements', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by']);
            $table->dropIndex('pf_arrangements_status_expiry_index');
            $table->dropColumn([
                'terms_revision',
                'expires_at',
                'accepted_terms_snapshot',
                'owner_accepted_ip',
                'owner_accepted_user_agent',
                'cancelled_by',
                'cancellation_reason',
                'rejected_at',
            ]);
        });
    }
};
