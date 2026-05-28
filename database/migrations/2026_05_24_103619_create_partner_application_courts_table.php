<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_application_courts', function (Blueprint $table) {
            $table->id();
            $table->char('partner_application_id', 36);
            $table->unsignedBigInteger('court_type_id');
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('partner_application_id')->references('id')->on('partner_applications')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_application_courts');
    }
};
