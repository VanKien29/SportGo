<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type', 255)->comment('Model sở hữu token, thường là User; polymorphic của Sanctum.; VD: booking_reminder');
            $table->char('tokenable_id', 36)->comment('ID model sở hữu token.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('name', 255)->comment('Tên token như mobile-app hoặc web-client.; VD: Sân Cầu Lông A1');
            $table->string('token', 64)->unique()->comment('Hash token dùng để xác thực API.; VD: giá trị mẫu');
            $table->text('abilities')->nullable()->comment('Danh sách quyền token được phép sử dụng.; VD: giá trị mẫu');
            $table->timestamp('last_used_at')->nullable()->comment('Thời điểm token được dùng gần nhất.; VD: 18:00:00');
            $table->timestamp('expires_at')->nullable()->comment('Thời điểm token hết hạn nếu có.; VD: 2026-06-15 18:00:00');
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
