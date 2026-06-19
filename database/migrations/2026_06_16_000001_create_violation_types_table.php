<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('violation_types')) {
            return;
        }

        Schema::create('violation_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedTinyInteger('base_score')->default(1);
            $table->boolean('is_immediate')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'violation_types_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_types');
    }
};
