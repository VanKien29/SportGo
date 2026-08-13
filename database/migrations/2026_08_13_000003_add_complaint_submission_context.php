<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('complaints')) {
            return;
        }

        Schema::table('complaints', function (Blueprint $table): void {
            if (! Schema::hasColumn('complaints', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique('complaints_idempotency_key_unique');
            }
            if (! Schema::hasColumn('complaints', 'request_fingerprint')) {
                $table->string('request_fingerprint', 64)->nullable();
            }
            if (! Schema::hasColumn('complaints', 'booking_snapshot')) {
                $table->json('booking_snapshot')->nullable();
            }
            if (! Schema::hasColumn('complaints', 'submitted_ip')) {
                $table->string('submitted_ip', 45)->nullable();
            }
            if (! Schema::hasColumn('complaints', 'submitted_user_agent')) {
                $table->string('submitted_user_agent', 500)->nullable();
            }
            if (! Schema::hasColumn('complaints', 'policy_version')) {
                $table->string('policy_version', 50)->nullable();
            }
            if (! Schema::hasColumn('complaints', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable();
            }
            if (! Schema::hasColumn('complaints', 'response_due_at')) {
                $table->timestamp('response_due_at')->nullable();
            }
            if (! Schema::hasColumn('complaints', 'resolution_due_at')) {
                $table->timestamp('resolution_due_at')->nullable();
            }
        });

        if (! Schema::hasIndex('complaints', 'complaints_customer_booking_created_index')) {
            Schema::table('complaints', function (Blueprint $table): void {
                $table->index(['customer_id', 'booking_id', 'created_at'], 'complaints_customer_booking_created_index');
            });
        }

        if (! Schema::hasIndex('complaints', 'complaints_status_due_index')) {
            Schema::table('complaints', function (Blueprint $table): void {
                $table->index(['status', 'response_due_at', 'resolution_due_at'], 'complaints_status_due_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('complaints')) {
            return;
        }

        foreach ([
            'complaints_customer_booking_created_index',
            'complaints_status_due_index',
            'complaints_idempotency_key_unique',
        ] as $index) {
            if (Schema::hasIndex('complaints', $index)) {
                Schema::table('complaints', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }

        $columns = collect([
            'idempotency_key',
            'request_fingerprint',
            'booking_snapshot',
            'submitted_ip',
            'submitted_user_agent',
            'policy_version',
            'first_response_at',
            'response_due_at',
            'resolution_due_at',
        ])->filter(fn (string $column): bool => Schema::hasColumn('complaints', $column))->values()->all();

        if ($columns !== []) {
            Schema::table('complaints', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
