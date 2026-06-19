<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_base_prices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36);
            $table->unsignedBigInteger('court_type_id');
            $table->decimal('price', 12, 2)->default(10000);
            $table->timestamps();

            $table->unique(
                ['venue_cluster_id', 'court_type_id'],
                'venue_base_prices_cluster_court_unique'
            );
            $table->foreign('venue_cluster_id')
                ->references('id')
                ->on('venue_clusters')
                ->cascadeOnDelete();
            $table->foreign('court_type_id')
                ->references('id')
                ->on('court_types')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_base_prices');
    }
};
