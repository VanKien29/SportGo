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
        Schema::create('partner_application_courts', function (Blueprint $table) {
            $table->id();
            $table->char('partner_application_id', 36)->comment('Hồ sơ chủ sân chứa sân con này.; VD: 10000000-0000-0000-0000-000000000001');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân của sân con ban đầu.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('name', 100)->comment('Tên sân con trong hồ sơ, ví dụ Sân A1.; VD: Sân Cầu Lông A1');
            $table->integer('sort_order')->comment('Thứ tự hiển thị sân con trong hồ sơ.; VD: 1');
            $table->timestamps();
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('partner_application_id')->references('id')->on('partner_applications')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_application_courts');
    }
};
