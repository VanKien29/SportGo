<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table) {
            $table->string('evidence_image', 500)->nullable()->after('status_reason')
                  ->comment('Đường dẫn ảnh minh chứng do chủ sân gửi kèm');
        });
    }

    public function down(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table) {
            $table->dropColumn('evidence_image');
        });
    }
};
