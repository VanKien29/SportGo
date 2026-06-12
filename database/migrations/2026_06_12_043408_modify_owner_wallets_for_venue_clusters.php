<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_wallets', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropUnique(['owner_id']);
            
            if (!Schema::hasColumn('owner_wallets', 'venue_cluster_id')) {
                $table->char('venue_cluster_id', 36)->nullable()->after('owner_id')->comment('Cụm sân sở hữu ví này.');
            }
            
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
            $table->unique(['owner_id', 'venue_cluster_id'], 'owner_wallets_owner_venue_cluster_unique');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('owner_wallets', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['venue_cluster_id']);
            $table->dropUnique('owner_wallets_owner_venue_cluster_unique');
            $table->dropColumn('venue_cluster_id');
            $table->unique('owner_id');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
        });
    }
};
