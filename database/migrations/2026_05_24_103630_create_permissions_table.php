<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique()->comment('Mã quyền duy nhất.');
            $table->string('name', 255)->comment('Tên quyền dễ đọc.');
            $table->string('group_name', 50)->comment('Nhóm quyền để FE gom theo module.');
            $table->timestamp('created_at')->nullable();
            $table->index('group_name', 'permissions_group_name_index');
        });
    }
    public function down(): void { Schema::dropIfExists('permissions'); }
};
