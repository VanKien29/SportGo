<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Chèn dữ liệu tiện ích mặc định
        DB::table('amenities')->insert([
            ['name' => 'Wifi', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gửi xe', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Căng tin', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tắm nóng lạnh', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cho thuê vợt', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Nước uống free', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
