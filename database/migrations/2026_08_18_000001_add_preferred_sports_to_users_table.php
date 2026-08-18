<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'preferred_sports')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('preferred_sports')->nullable()->after('bio');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'preferred_sports')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('preferred_sports');
            });
        }
    }
};
