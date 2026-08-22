<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_types', function (Blueprint $table): void {
            $table->string('icon_key', 40)->default('activity')->after('description');
        });

        DB::table('court_types')
            ->where(function ($query): void {
                $query->where('name', 'like', '%cầu lông%')
                    ->orWhere('name', 'like', '%badminton%');
            })
            ->update(['icon_key' => 'badminton']);

        DB::table('court_types')
            ->where(function ($query): void {
                $query->where('name', 'like', '%pickleball%');
            })
            ->update(['icon_key' => 'pickleball']);

        DB::table('court_types')
            ->where(function ($query): void {
                $query->where('name', 'like', '%bóng đá%')
                    ->orWhere('name', 'like', '%football%');
            })
            ->update(['icon_key' => 'football']);

        DB::table('court_types')
            ->where(function ($query): void {
                $query->where('name', 'like', '%bóng rổ%')
                    ->orWhere('name', 'like', '%basketball%');
            })
            ->update(['icon_key' => 'basketball']);

        DB::table('court_types')
            ->where(function ($query): void {
                $query->where('name', 'like', '%tennis%')
                    ->orWhere('name', 'like', '%quần vợt%');
            })
            ->update(['icon_key' => 'tennis']);

        DB::table('court_types')
            ->where(function ($query): void {
                $query->where('name', 'like', '%bóng chuyền%')
                    ->orWhere('name', 'like', '%volleyball%');
            })
            ->update(['icon_key' => 'volleyball']);
    }

    public function down(): void
    {
        Schema::table('court_types', function (Blueprint $table): void {
            $table->dropColumn('icon_key');
        });
    }
};
