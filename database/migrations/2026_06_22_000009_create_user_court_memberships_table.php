<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_court_memberships', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->comment('User tich luy hang tai cum san.');
            $table->char('venue_cluster_id', 36)->comment('Cum san ap dung hang thanh vien.');
            $table->enum('tier', ['standard', 'silver', 'gold', 'diamond'])->default('standard')
                ->comment('Hang hien tai.');
            $table->unsignedInteger('total_bookings')->default(0)->comment('Tong booking da hoan thanh.');
            $table->decimal('total_spent', 14, 2)->default(0)->comment('Tong tien da chi.');
            $table->unsignedInteger('period_bookings')->default(0)->comment('Booking trong ky duy tri hien tai.');
            $table->decimal('period_spent', 14, 2)->default(0)->comment('Tien chi trong ky duy tri hien tai.');
            $table->date('period_start')->nullable()->comment('Ngay bat dau ky duy tri hien tai.');
            $table->timestamp('last_upgraded_at')->nullable()->comment('Thoi diem len hang gan nhat.');
            $table->timestamp('last_downgraded_at')->nullable()->comment('Thoi diem ha hang gan nhat.');
            $table->timestamp('downgrade_notified_at')->nullable()->comment('Thoi diem da thong bao ha hang.');
            $table->timestamps();

            $table->unique(['user_id', 'venue_cluster_id'], 'user_court_memberships_user_cluster_unique');
            $table->index(['venue_cluster_id', 'tier'], 'user_court_memberships_cluster_tier_index');
            $table->index(['user_id', 'tier'], 'user_court_memberships_user_tier_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_court_memberships');
    }
};
