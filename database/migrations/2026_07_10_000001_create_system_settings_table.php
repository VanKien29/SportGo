<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->string('type', 30)->default('string');
                $table->string('value_type', 30)->default('string');
                $table->string('group', 60)->default('general');
                $table->string('label')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'type')) {
                $table->string('type', 30)->default('string')->after('value');
            }

            if (! Schema::hasColumn('system_settings', 'value_type')) {
                $table->string('value_type', 30)->default('string')->after('type');
            }

            if (! Schema::hasColumn('system_settings', 'group')) {
                $table->string('group', 60)->default('general')->after('value_type');
            }

            if (! Schema::hasColumn('system_settings', 'label')) {
                $table->string('label')->nullable()->after('group');
            }

            if (! Schema::hasColumn('system_settings', 'description')) {
                $table->text('description')->nullable()->after('label');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            foreach (['label', 'group', 'type'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
