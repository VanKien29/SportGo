<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', [
                'pending_review',
                'active',
                'rejected',
                'inactive',
                'cancelled',
            ]);
            $table->char('created_by', 36)->nullable();
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            // Virtual column to enforce unique active names
            $table->string('active_name')
                ->virtualAs("IF(status = 'active' AND deleted_at IS NULL, name, NULL)")
                ->nullable();
            $table->unique('active_name', 'amenities_active_name_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE amenities ADD CONSTRAINT amenities_status_reason_check CHECK (status NOT IN ('rejected', 'inactive', 'cancelled') OR status_reason IS NOT NULL)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
