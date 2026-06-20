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
        Schema::create('vn_provinces', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('codename')->nullable();
            $table->string('division_type')->nullable();
            $table->integer('phone_code')->nullable();
            $table->timestamps();
        });

        Schema::create('vn_wards', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('codename')->nullable();
            $table->string('division_type')->nullable();
            $table->string('province_code');
            $table->foreign('province_code')->references('code')->on('vn_provinces')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vn_wards');
        Schema::dropIfExists('vn_provinces');
    }
};
