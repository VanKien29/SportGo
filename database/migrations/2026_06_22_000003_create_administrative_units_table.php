<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_units', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('code', 20)->unique()->comment('Ma don vi hanh chinh.');
            $table->string('name', 255)->comment('Ten don vi hanh chinh.');
            $table->string('name_en', 255)->nullable()->comment('Ten tieng Anh.');
            $table->string('full_name', 500)->nullable()->comment('Ten day du kem loai don vi.');
            $table->enum('type', ['province', 'district', 'ward'])->comment('Loai don vi hanh chinh.');
            $table->string('parent_code', 20)->nullable()->comment('Ma don vi cha.');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'parent_code'], 'administrative_units_type_parent_index');
            $table->index(['type', 'is_active'], 'administrative_units_type_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_units');
    }
};
