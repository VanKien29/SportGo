<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table) {
                if (Schema::hasColumn('complaints', 'resolution_note') && ! Schema::hasColumn('complaints', 'resolve_note')) {
                    $table->renameColumn('resolution_note', 'resolve_note');
                }

                if (! Schema::hasColumn('complaints', 'status_reason')) {
                    $table->text('status_reason')->nullable()->after('resolve_note');
                }
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $this->alignMysqlColumns();
        }

        $this->ensureIndex('bookings', 'bookings_venue_cluster_id_index', ['venue_cluster_id']);
        $this->ensureIndex('slot_locks', 'slot_locks_booking_id_foreign', ['booking_id']);
        $this->ensureIndex('user_roles', 'user_roles_scope_type_index', ['scope_type']);
        $this->ensureIndex('user_roles', 'user_roles_scope_id_index', ['scope_id']);
        $this->ensureIndex('user_permission_revokes', 'user_permission_revokes_scope_type_index', ['scope_type']);
        $this->ensureIndex('user_permission_revokes', 'user_permission_revokes_scope_id_index', ['scope_id']);
        $this->ensureIndex('verification_codes', 'verification_codes_identifier_index', ['identifier']);
        $this->ensureIndex('verification_codes', 'verification_codes_type_index', ['type']);
        $this->ensureIndex('verification_codes', 'verification_codes_is_used_index', ['is_used']);
        $this->ensureIndex('verification_codes', 'verification_codes_expires_at_index', ['expires_at']);
    }

    public function down(): void
    {
        // Intentionally not reverting to the pre-design schema.
    }

    private function alignMysqlColumns(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->decimal('total_price', 12, 2)->default(0.00)->change();
                $table->enum('payment_option', ['full_payment', 'deposit', 'no_prepay'])->default('no_prepay')->change();
                $table->decimal('required_payment_amount', 12, 2)->default(0.00)->change();
                $table->enum('source', ['online', 'counter'])->default('online')->change();
                $table->enum('booking_type', ['single', 'recurring'])->default('single')->change();
                $table->unsignedInteger('recurrence_interval')->nullable()->change();
                $table->enum('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired', 'rejected'])->default('pending_approval')->change();
            });
        }

        if (Schema::hasTable('user_roles')) {
            if (Schema::hasIndex('user_roles', 'user_roles_scope_id_unique')) {
                Schema::table('user_roles', function (Blueprint $table) {
                    $table->dropUnique('user_roles_scope_id_unique');
                });
            }

            DB::table('user_roles')
                ->whereNull('scope_id')
                ->update(['scope_id' => '00000000-0000-0000-0000-000000000000']);

            Schema::table('user_roles', function (Blueprint $table) {
                $table->enum('scope_type', ['system', 'venue'])->default('system')->change();
                $table->char('scope_id', 36)->default('00000000-0000-0000-0000-000000000000')->change();
            });
        }

        if (Schema::hasTable('user_permission_revokes')) {
            DB::table('user_permission_revokes')
                ->whereNull('scope_id')
                ->update(['scope_id' => '00000000-0000-0000-0000-000000000000']);

            Schema::table('user_permission_revokes', function (Blueprint $table) {
                $table->enum('scope_type', ['system', 'venue'])->default('system')->change();
                $table->char('scope_id', 36)->default('00000000-0000-0000-0000-000000000000')->change();
            });
        }

        if (Schema::hasTable('verification_codes')) {
            Schema::table('verification_codes', function (Blueprint $table) {
                $table->enum('type', ['register', 'reset_password', 'phone_verify', 'email_verify'])->change();
                $table->enum('channel', ['email', 'sms'])->default('email')->change();
                $table->unsignedSmallInteger('attempt_count')->default(0)->change();
                $table->unsignedSmallInteger('max_attempts')->default(5)->change();
                $table->boolean('is_used')->default(false)->change();
            });
        }
    }

    private function ensureIndex(string $tableName, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }
};
