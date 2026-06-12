<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('partner_histories')) {
            return;
        }

        Schema::create('partner_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('partner_application_id')->constrained('partner_applications')->onDelete('cascade');
            $table->string('action');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_histories');
    }
};
