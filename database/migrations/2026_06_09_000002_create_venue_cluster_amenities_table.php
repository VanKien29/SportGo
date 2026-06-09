<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_cluster_amenities', function (Blueprint $table) {
            $table->id();
            $table->char('venue_cluster_id', 36);
            $table->unsignedBigInteger('amenity_id');
            $table->text('description')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['venue_cluster_id', 'amenity_id'], 'venue_cluster_amenities_cluster_amenity_unique');
            $table->index('venue_cluster_id');
            $table->index('amenity_id');

            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->cascadeOnDelete();
            $table->foreign('amenity_id')->references('id')->on('amenities')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("
                CREATE TRIGGER check_venue_cluster_amenity_active_before_insert
                BEFORE INSERT ON venue_cluster_amenities FOR EACH ROW
                BEGIN
                    DECLARE v_status VARCHAR(50);
                    SELECT status INTO v_status FROM amenities WHERE id = NEW.amenity_id;
                    IF v_status IS NULL OR v_status != 'active' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Only active amenities can be assigned to venue clusters';
                    END IF;
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER check_venue_cluster_amenity_active_before_update
                BEFORE UPDATE ON venue_cluster_amenities FOR EACH ROW
                BEGIN
                    DECLARE v_status VARCHAR(50);
                    SELECT status INTO v_status FROM amenities WHERE id = NEW.amenity_id;
                    IF v_status IS NULL OR v_status != 'active' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Only active amenities can be assigned to venue clusters';
                    END IF;
                END;
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("DROP TRIGGER IF EXISTS check_venue_cluster_amenity_active_before_insert");
            DB::unprepared("DROP TRIGGER IF EXISTS check_venue_cluster_amenity_active_before_update");
        }

        Schema::dropIfExists('venue_cluster_amenities');
    }
};
