<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'effective_payment_option')) {
                $table->string('effective_payment_option', 30)->nullable()->after('payment_option');
            }
            if (! Schema::hasColumn('bookings', 'approval_deadline_at')) {
                $table->timestamp('approval_deadline_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('bookings', 'owner_approved_at')) {
                $table->timestamp('owner_approved_at')->nullable()->after('approval_deadline_at');
            }
            if (! Schema::hasColumn('bookings', 'owner_approved_by')) {
                $table->unsignedBigInteger('owner_approved_by')->nullable()->after('owner_approved_at');
            }
            if (! Schema::hasColumn('bookings', 'payment_deadline_at')) {
                $table->timestamp('payment_deadline_at')->nullable()->after('owner_approved_by');
            }
            if (! Schema::hasColumn('bookings', 'payment_fallback_at')) {
                $table->timestamp('payment_fallback_at')->nullable()->after('payment_deadline_at');
            }
            if (! Schema::hasColumn('bookings', 'payment_fallback_reason')) {
                $table->text('payment_fallback_reason')->nullable()->after('payment_fallback_at');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'owner_approved_by')) {
                $table->index('owner_approved_by', 'bookings_owner_approved_by_index');
                $table->foreign('owner_approved_by', 'bookings_owner_approved_by_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (Schema::hasColumn('bookings', 'approval_deadline_at')) {
                $table->index(['status', 'approval_deadline_at'], 'bookings_status_approval_deadline_index');
            }
        });

        // Preserve the originally selected payment option while exposing the
        // effective collection mode for existing records.
        if (Schema::hasColumn('bookings', 'effective_payment_option')) {
            \Illuminate\Support\Facades\DB::table('bookings')
                ->whereNull('effective_payment_option')
                ->update(['effective_payment_option' => \Illuminate\Support\Facades\DB::raw('payment_option')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'owner_approved_by')) {
                $table->dropForeign('bookings_owner_approved_by_foreign');
                $table->dropIndex('bookings_owner_approved_by_index');
            }
            if (Schema::hasColumn('bookings', 'approval_deadline_at')) {
                $table->dropIndex('bookings_status_approval_deadline_index');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            foreach ([
                'payment_fallback_reason',
                'payment_fallback_at',
                'payment_deadline_at',
                'owner_approved_by',
                'owner_approved_at',
                'approval_deadline_at',
                'effective_payment_option',
            ] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
