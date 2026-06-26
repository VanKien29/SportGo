<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_court_membership_histories')) {
            Schema::create('user_court_membership_histories', function (Blueprint $table): void {
                $table->char('id', 36)->primary();
                $table->char('membership_id', 36)->nullable();
                $table->char('user_id', 36);
                $table->char('venue_cluster_id', 36);
                $table->enum('from_tier', ['standard', 'silver', 'gold', 'diamond'])->nullable();
                $table->enum('to_tier', ['standard', 'silver', 'gold', 'diamond']);
                $table->string('change_type', 30);
                $table->string('reason', 255)->nullable();
                $table->unsignedInteger('total_bookings')->default(0);
                $table->decimal('total_spent', 14, 2)->default(0);
                $table->unsignedInteger('period_bookings')->default(0);
                $table->decimal('period_spent', 14, 2)->default(0);
                $table->timestamp('changed_at');
                $table->timestamps();

                $table->index(['user_id', 'venue_cluster_id', 'changed_at'], 'user_court_membership_histories_user_cluster_changed_index');
                $table->foreign('membership_id', 'user_court_membership_histories_membership_foreign')
                    ->references('id')->on('user_court_memberships')->onDelete('set null');
                $table->foreign('user_id', 'user_court_membership_histories_user_foreign')
                    ->references('id')->on('users')->onDelete('cascade');
                $table->foreign('venue_cluster_id', 'user_court_membership_histories_cluster_foreign')
                    ->references('id')->on('venue_clusters')->onDelete('cascade');
            });
        }

        $this->copyLegacyMembershipSettings();
        $this->copyLegacyUserMemberships();
        $this->copyLegacyMembershipHistories();

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (Schema::hasColumn('bookings', 'membership_tier_snapshot')) {
                    $table->dropColumn('membership_tier_snapshot');
                }
                if (Schema::hasColumn('bookings', 'membership_discount_amount')) {
                    $table->dropColumn('membership_discount_amount');
                }
            });
        }

        Schema::dropIfExists('user_venue_membership_histories');
        Schema::dropIfExists('user_venue_memberships');
        Schema::dropIfExists('venue_membership_tier_settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_court_membership_histories');

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (! Schema::hasColumn('bookings', 'membership_discount_amount')) {
                    $table->decimal('membership_discount_amount', 12, 2)->default(0)->after('discount_amount');
                }
                if (! Schema::hasColumn('bookings', 'membership_tier_snapshot')) {
                    $table->json('membership_tier_snapshot')->nullable()->after('membership_discount_amount');
                }
            });
        }
    }

    private function copyLegacyMembershipSettings(): void
    {
        if (! Schema::hasTable('venue_membership_tier_settings') || ! Schema::hasTable('court_membership_tiers')) {
            return;
        }

        DB::table('venue_membership_tier_settings')
            ->orderBy('id')
            ->get()
            ->each(function (object $tier): void {
                DB::table('court_membership_tiers')->updateOrInsert(
                    [
                        'venue_cluster_id' => $tier->venue_cluster_id,
                        'tier' => $this->normalizeTier($tier->tier_key),
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'discount_percent' => $tier->discount_percent,
                        'min_bookings' => $tier->min_completed_bookings,
                        'min_spent_amount' => $tier->min_spend_amount,
                        'maintain_min_bookings' => $tier->maintain_min_bookings,
                        'maintain_min_spent' => $tier->maintain_min_spend_amount,
                        'maintain_period_months' => $tier->maintain_period_months,
                        'created_at' => $tier->created_at,
                        'updated_at' => $tier->updated_at,
                    ],
                );
            });
    }

    private function copyLegacyUserMemberships(): void
    {
        if (! Schema::hasTable('user_venue_memberships') || ! Schema::hasTable('user_court_memberships')) {
            return;
        }

        DB::table('user_venue_memberships')
            ->orderBy('id')
            ->get()
            ->each(function (object $membership): void {
                DB::table('user_court_memberships')->updateOrInsert(
                    [
                        'user_id' => $membership->user_id,
                        'venue_cluster_id' => $membership->venue_cluster_id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'tier' => $this->normalizeTier($membership->tier_key),
                        'total_bookings' => $membership->completed_bookings,
                        'total_spent' => $membership->total_spend_amount,
                        'period_bookings' => 0,
                        'period_spent' => 0,
                        'period_start' => $membership->evaluated_at ? Carbon::parse($membership->evaluated_at)->toDateString() : null,
                        'created_at' => $membership->created_at,
                        'updated_at' => $membership->updated_at,
                    ],
                );
            });
    }

    private function copyLegacyMembershipHistories(): void
    {
        if (! Schema::hasTable('user_venue_membership_histories') || ! Schema::hasTable('user_court_membership_histories')) {
            return;
        }

        DB::table('user_venue_membership_histories')
            ->orderBy('id')
            ->get()
            ->each(function (object $history): void {
                DB::table('user_court_membership_histories')->insert([
                    'id' => (string) Str::uuid(),
                    'membership_id' => null,
                    'user_id' => $history->user_id,
                    'venue_cluster_id' => $history->venue_cluster_id,
                    'from_tier' => $history->from_tier_key ? $this->normalizeTier($history->from_tier_key) : null,
                    'to_tier' => $this->normalizeTier($history->to_tier_key),
                    'change_type' => $history->change_type,
                    'reason' => $history->reason,
                    'total_bookings' => $history->completed_bookings,
                    'total_spent' => $history->total_spend_amount,
                    'period_bookings' => 0,
                    'period_spent' => 0,
                    'changed_at' => $history->changed_at,
                    'created_at' => $history->created_at,
                    'updated_at' => $history->updated_at,
                ]);
            });
    }

    private function normalizeTier(?string $tier): string
    {
        return $tier === 'regular' ? 'standard' : ($tier ?: 'standard');
    }
};
