<?php

use App\Services\Auth\VenueStaffMenuCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_staff_menu_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('venue_cluster_id');
            $table->string('menu_key', 50);
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'venue_cluster_id', 'menu_key'], 'venue_staff_menu_permissions_unique');
            $table->index(['venue_cluster_id', 'menu_key'], 'venue_staff_menu_permissions_cluster_menu_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
        });

        $now = now();
        $assignments = DB::table('venue_staff_assignments')
            ->where('status', 'active')
            ->select('user_id', 'venue_cluster_id', 'assigned_by')
            ->distinct()
            ->get();

        foreach ($assignments as $assignment) {
            foreach (VenueStaffMenuCatalog::legacyKeys() as $menuKey) {
                DB::table('venue_staff_menu_permissions')->insertOrIgnore([
                    'user_id' => $assignment->user_id,
                    'venue_cluster_id' => $assignment->venue_cluster_id,
                    'menu_key' => $menuKey,
                    'granted_by' => $assignment->assigned_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_staff_menu_permissions');
    }
};
