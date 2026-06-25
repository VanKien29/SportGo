<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_membership_tier_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('venue_cluster_id', 36);
            $table->string('tier_key', 30);
            $table->string('tier_label', 60);
            $table->unsignedTinyInteger('tier_order');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('min_completed_bookings')->default(0);
            $table->decimal('min_spend_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('maintain_period_months')->nullable();
            $table->unsignedInteger('maintain_min_bookings')->nullable();
            $table->decimal('maintain_min_spend_amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['venue_cluster_id', 'tier_key'], 'venue_membership_settings_cluster_tier_unique');
            $table->index(['venue_cluster_id', 'tier_order'], 'venue_membership_settings_cluster_order_index');
            $table->foreign('venue_cluster_id', 'venue_membership_settings_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('cascade');
        });

        Schema::create('user_venue_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('user_id', 36);
            $table->char('venue_cluster_id', 36);
            $table->string('tier_key', 30)->default('regular');
            $table->unsignedInteger('completed_bookings')->default(0);
            $table->decimal('total_spend_amount', 12, 2)->default(0);
            $table->timestamp('last_booking_completed_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'venue_cluster_id'], 'user_venue_memberships_user_cluster_unique');
            $table->index(['venue_cluster_id', 'tier_key'], 'user_venue_memberships_cluster_tier_index');
            $table->foreign('user_id', 'user_venue_memberships_user_foreign')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('venue_cluster_id', 'user_venue_memberships_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('cascade');
        });

        Schema::create('user_venue_membership_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('membership_id', 36)->nullable();
            $table->char('user_id', 36);
            $table->char('venue_cluster_id', 36);
            $table->string('from_tier_key', 30)->nullable();
            $table->string('to_tier_key', 30);
            $table->string('change_type', 30);
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('completed_bookings')->default(0);
            $table->decimal('total_spend_amount', 12, 2)->default(0);
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['user_id', 'venue_cluster_id', 'changed_at'], 'membership_histories_user_cluster_changed_index');
            $table->foreign('membership_id', 'membership_histories_membership_foreign')
                ->references('id')->on('user_venue_memberships')->onDelete('set null');
            $table->foreign('user_id', 'membership_histories_user_foreign')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('venue_cluster_id', 'membership_histories_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('cascade');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'membership_discount_amount')) {
                $table->decimal('membership_discount_amount', 12, 2)->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('bookings', 'membership_tier_snapshot')) {
                $table->json('membership_tier_snapshot')->nullable()->after('membership_discount_amount');
            }
        });

        DB::statement("ALTER TABLE voucher_scopes MODIFY scope_type ENUM('all','venue_cluster','court_type','booking_type','membership_tier') NOT NULL");
    }

    public function down(): void
    {
        DB::table('voucher_scopes')->where('scope_type', 'membership_tier')->delete();
        DB::statement("ALTER TABLE voucher_scopes MODIFY scope_type ENUM('all','venue_cluster','court_type','booking_type') NOT NULL");

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'membership_tier_snapshot')) {
                $table->dropColumn('membership_tier_snapshot');
            }
            if (Schema::hasColumn('bookings', 'membership_discount_amount')) {
                $table->dropColumn('membership_discount_amount');
            }
        });

        Schema::dropIfExists('user_venue_membership_histories');
        Schema::dropIfExists('user_venue_memberships');
        Schema::dropIfExists('venue_membership_tier_settings');
    }
};
