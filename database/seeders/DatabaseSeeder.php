<?php
 
 namespace Database\Seeders;
 
 use App\Models\CourtType;
 use App\Models\User;
 use App\Models\Role;
 use App\Models\SystemPolicy;
 use Illuminate\Database\Seeder;
 use Illuminate\Support\Facades\Hash;
 
 class DatabaseSeeder extends Seeder
 {
     /**
      * Seed the application's database.
      */
     public function run(): void
     {
         $this->call([
             RolesTableSeeder::class,
             PermissionsTableSeeder::class,
             RolePermissionsTableSeeder::class,
         ]);

         // Tạo tài khoản Admin mẫu
         $admin = User::create([
             'username' => 'admin',
             'full_name' => 'Hệ thống Admin',
             'email' => 'admin@sportgo.com',
             'phone' => '0987654321',
             'password' => Hash::make('123456'),
             'status' => 'active',
         ]);

         $adminRole = Role::where('name', 'super_admin')->first();
         if ($adminRole) {
             $admin->roles()->attach($adminRole->id, [
                 'scope_type' => 'system',
                 'scope_id' => '00000000-0000-0000-0000-000000000000',
             ]);
         }
 
         // Tạo tài khoản người dùng mẫu
         User::create([
             'username' => 'user',
             'full_name' => 'Người dùng mẫu',
             'email' => 'user@sportgo.com',
             'phone' => '0123456789',
             'password' => Hash::make('123456'),
             'status' => 'active',
         ]);
 
         foreach ([
             ['name' => 'Sân bóng đá', 'player_count' => 14],
             ['name' => 'Sân bóng rổ', 'player_count' => 10],
             ['name' => 'Sân cầu lông', 'player_count' => 4],
             ['name' => 'Sân tennis', 'player_count' => 4],
             ['name' => 'Sân bóng bàn', 'player_count' => 4],
         ] as $courtType) {
             CourtType::firstOrCreate(
                 ['name' => $courtType['name']],
                 ['player_count' => $courtType['player_count'], 'is_active' => true]
             );
         }
 
         // Tạo chính sách mẫu loại general để popup hiển thị ngay
         SystemPolicy::create([
             'key' => 'general',
             'version' => 1,
             'title' => 'Điều khoản sử dụng SportGo',
             'content' => '<h3>Chào mừng bạn đến với SportGo!</h3><p>Đây là nội dung chính sách hệ thống phiên bản v1. Bạn cần chấp thuận để tiếp tục sử dụng dịch vụ.</p><ul><li>Bảo mật thông tin cá nhân.</li><li>Quy tắc đặt sân và hoàn tiền.</li><li>Hành vi ứng xử trong cộng đồng.</li></ul>',
             'type' => 'general',
             'is_active' => true,
             'effective_from' => now(),
             'created_by' => $admin->id,
         ]);
     }
 }

