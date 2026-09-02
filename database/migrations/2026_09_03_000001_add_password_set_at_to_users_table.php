<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'password_set_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('password_set_at')
                    ->nullable()
                    ->after('password')
                    ->comment('Thời điểm người dùng thiết lập mật khẩu thật; null với tài khoản Google chưa thiết lập.');
            });
        }

        // Tài khoản thường cũ đã có mật khẩu từ lúc tạo. Giữ null cho tài khoản
        // Google để buộc đi qua luồng thiết lập mật khẩu lần đầu.
        if (Schema::hasColumn('users', 'google_id')) {
            DB::table('users')
                ->whereNull('google_id')
                ->whereNull('password_set_at')
                ->update(['password_set_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'password_set_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('password_set_at');
            });
        }
    }
};
