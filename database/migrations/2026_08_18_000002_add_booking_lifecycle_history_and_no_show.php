<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_status_histories')) {
            Schema::create('booking_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40);
                $table->string('reason_code', 80)->nullable();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['booking_id', 'created_at']);
                $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
                $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('bookings')) {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending_approval','pending_payment','confirmed','checked_in','completed','no_show','cancelled','expired','rejected') NOT NULL DEFAULT 'pending_approval'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('bookings')) {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending_approval','pending_payment','confirmed','checked_in','completed','cancelled','expired','rejected') NOT NULL DEFAULT 'pending_approval'");
        }

        Schema::dropIfExists('booking_status_histories');
    }
};
