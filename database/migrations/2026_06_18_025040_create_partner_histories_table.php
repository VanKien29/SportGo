<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_histories')) {
            return;
        }

        Schema::create('partner_histories', function (Blueprint $table) {
            $table->id();
            $table->char('partner_application_id', 36);
            $table->string('action');
            $table->char('actor_id', 36)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('partner_application_id')->references('id')->on('partner_applications')->onDelete('cascade');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_histories');
    }
};
