<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasTable('partner_termination_requests')) {
                DB::statement("ALTER TABLE partner_termination_requests MODIFY status VARCHAR(80) NOT NULL DEFAULT 'draft'");
            }

            if (Schema::hasTable('venue_clusters')) {
                DB::statement("ALTER TABLE venue_clusters MODIFY status ENUM('pending','active','locked','termination_locked','termination_processing','partner_terminated') NOT NULL DEFAULT 'pending'");
            }

            if (Schema::hasTable('partner_contracts')) {
                DB::statement("ALTER TABLE partner_contracts MODIFY status ENUM('draft','generated','pending_owner_signature','pending_sportgo_signature','signed_active','termination_requested','terminating','cancelled','terminated') NOT NULL DEFAULT 'draft'");
            }
        }

        if (Schema::hasTable('partner_termination_requests')) {
            Schema::table('partner_termination_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('partner_termination_requests', 'detail_reason')) {
                    $table->text('detail_reason')->nullable()->after('reason');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'future_booking_policy')) {
                    $table->string('future_booking_policy', 80)->nullable()->after('requested_effective_date');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'future_booking_policy_confirmed_at')) {
                    $table->timestamp('future_booking_policy_confirmed_at')->nullable()->after('future_booking_policy');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'owner_warning_accepted_at')) {
                    $table->timestamp('owner_warning_accepted_at')->nullable()->after('future_booking_policy_confirmed_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'future_booking_count')) {
                    $table->unsignedInteger('future_booking_count')->default(0)->after('owner_warning_accepted_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'owner_balance_total')) {
                    $table->decimal('owner_balance_total', 14, 2)->default(0)->after('future_booking_count');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'future_online_booking_liability')) {
                    $table->decimal('future_online_booking_liability', 14, 2)->default(0)->after('owner_balance_total');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'pending_refund_liability')) {
                    $table->decimal('pending_refund_liability', 14, 2)->default(0)->after('future_online_booking_liability');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'pending_withdrawal_amount')) {
                    $table->decimal('pending_withdrawal_amount', 14, 2)->default(0)->after('pending_refund_liability');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'withdrawable_amount')) {
                    $table->decimal('withdrawable_amount', 14, 2)->default(0)->after('pending_withdrawal_amount');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'future_booking_summary')) {
                    $table->json('future_booking_summary')->nullable()->after('withdrawable_amount');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'owner_attachments')) {
                    $table->json('owner_attachments')->nullable()->after('future_booking_summary');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'admin_locked_owner_cancel')) {
                    $table->boolean('admin_locked_owner_cancel')->default(false)->after('owner_attachments');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'owner_cancel_reason')) {
                    $table->text('owner_cancel_reason')->nullable()->after('admin_locked_owner_cancel');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'owner_cancelled_at')) {
                    $table->timestamp('owner_cancelled_at')->nullable()->after('owner_cancel_reason');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'owner_cancelled_by')) {
                    $table->unsignedBigInteger('owner_cancelled_by')->nullable()->after('owner_cancelled_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'admin_rejected_by')) {
                    $table->unsignedBigInteger('admin_rejected_by')->nullable()->after('owner_cancelled_by');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'admin_rejected_at')) {
                    $table->timestamp('admin_rejected_at')->nullable()->after('admin_rejected_by');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'manual_debt_resolved_at')) {
                    $table->timestamp('manual_debt_resolved_at')->nullable()->after('admin_rejected_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'manual_debt_resolved_by')) {
                    $table->unsignedBigInteger('manual_debt_resolved_by')->nullable()->after('manual_debt_resolved_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'final_document_generated_at')) {
                    $table->timestamp('final_document_generated_at')->nullable()->after('manual_debt_resolved_by');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'final_document_ready_at')) {
                    $table->timestamp('final_document_ready_at')->nullable()->after('final_document_generated_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'final_document_admin_signed_at')) {
                    $table->timestamp('final_document_admin_signed_at')->nullable()->after('final_document_ready_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'final_document_owner_signed_at')) {
                    $table->timestamp('final_document_owner_signed_at')->nullable()->after('final_document_admin_signed_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'final_document_completed_at')) {
                    $table->timestamp('final_document_completed_at')->nullable()->after('final_document_owner_signed_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'grace_period_days')) {
                    $table->unsignedInteger('grace_period_days')->default(14)->after('final_document_completed_at');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'owner_access_view_until')) {
                    $table->timestamp('owner_access_view_until')->nullable()->after('grace_period_days');
                }
                if (! Schema::hasColumn('partner_termination_requests', 'metadata')) {
                    $table->json('metadata')->nullable()->after('owner_access_view_until');
                }
            });

            Schema::table('partner_termination_requests', function (Blueprint $table): void {
                foreach ([
                    ['owner_cancelled_by', 'partner_term_requests_owner_cancelled_by_foreign'],
                    ['admin_rejected_by', 'partner_term_requests_admin_rejected_by_foreign'],
                    ['manual_debt_resolved_by', 'partner_term_requests_debt_resolved_by_foreign'],
                ] as [$column, $foreign]) {
                    if (
                        Schema::hasColumn('partner_termination_requests', $column)
                        && ! $this->foreignKeyExists('partner_termination_requests', $foreign)
                    ) {
                        $table->foreign($column, $foreign)->references('id')->on('users')->onDelete('set null');
                    }
                }
            });
        }

        if (! Schema::hasTable('partner_termination_booking_actions')) {
            Schema::create('partner_termination_booking_actions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('partner_termination_request_id');
                $table->unsignedBigInteger('booking_id');
                $table->string('action', 80);
                $table->string('status', 50)->default('pending');
                $table->decimal('paid_online_amount', 14, 2)->default(0);
                $table->unsignedBigInteger('refund_id')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['partner_termination_request_id', 'booking_id'], 'partner_term_booking_unique');
                $table->index(['partner_termination_request_id', 'status'], 'partner_term_booking_request_status_index');
                $table->index(['booking_id', 'action'], 'partner_term_booking_booking_action_index');
                $table->foreign('partner_termination_request_id', 'partner_term_booking_request_foreign')
                    ->references('id')->on('partner_termination_requests')->onDelete('restrict');
                $table->foreign('booking_id', 'partner_term_booking_booking_foreign')
                    ->references('id')->on('bookings')->onDelete('restrict');
                $table->foreign('refund_id', 'partner_term_booking_refund_foreign')
                    ->references('id')->on('refunds')->onDelete('set null');
                $table->foreign('processed_by', 'partner_term_booking_processed_by_foreign')
                    ->references('id')->on('users')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 120)->unique();
                $table->text('value')->nullable();
                $table->string('value_type', 30)->default('string');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('system_settings') && ! Schema::hasColumn('system_settings', 'value_type')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->string('value_type', 30)->default('string')->after('value');
            });
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'partner_termination_view_grace_days'],
            [
                'value' => '14',
                'value_type' => 'integer',
                'description' => 'So ngay chu san con duoc xem ho so sau khi bien ban cham dut cuoi da ky.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_termination_booking_actions');

        if (Schema::hasTable('partner_termination_requests')) {
            Schema::table('partner_termination_requests', function (Blueprint $table): void {
                foreach ([
                    'partner_term_requests_owner_cancelled_by_foreign',
                    'partner_term_requests_admin_rejected_by_foreign',
                    'partner_term_requests_debt_resolved_by_foreign',
                ] as $foreign) {
                    if ($this->foreignKeyExists('partner_termination_requests', $foreign)) {
                        $table->dropForeign($foreign);
                    }
                }
            });

            Schema::table('partner_termination_requests', function (Blueprint $table): void {
                foreach ([
                    'metadata',
                    'owner_access_view_until',
                    'grace_period_days',
                    'final_document_completed_at',
                    'final_document_owner_signed_at',
                    'final_document_admin_signed_at',
                    'final_document_ready_at',
                    'final_document_generated_at',
                    'manual_debt_resolved_by',
                    'manual_debt_resolved_at',
                    'admin_rejected_at',
                    'admin_rejected_by',
                    'owner_cancelled_by',
                    'owner_cancelled_at',
                    'owner_cancel_reason',
                    'admin_locked_owner_cancel',
                    'owner_attachments',
                    'future_booking_summary',
                    'withdrawable_amount',
                    'pending_withdrawal_amount',
                    'pending_refund_liability',
                    'future_online_booking_liability',
                    'owner_balance_total',
                    'future_booking_count',
                    'owner_warning_accepted_at',
                    'future_booking_policy_confirmed_at',
                    'future_booking_policy',
                    'detail_reason',
                ] as $column) {
                    if (Schema::hasColumn('partner_termination_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
