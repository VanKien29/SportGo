<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('severity_levels')) {
            return;
        }

        Schema::create('severity_levels', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id', true);
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->decimal('multiplier', 3, 1)->default(1.0);
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->index('sort_order', 'severity_levels_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('severity_levels');
    }
};
