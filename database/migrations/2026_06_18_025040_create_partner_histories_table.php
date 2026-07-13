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
            $table->unsignedBigInteger('partner_application_id');
            $table->string('action');
            $table->unsignedBigInteger('actor_id')->nullable();
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
