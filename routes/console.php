<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
Schedule::command('app:release-expired-slot-locks')->everyMinute();
Schedule::command('app:apply-policy-access-restrictions')->everyMinute();
Schedule::command('app:revoke-expired-owner-roles')->daily();
Schedule::command('app:evaluate-court-membership-maintenance')->daily();
Schedule::command('app:expire-vip-subscriptions')->daily();
Schedule::command('app:issue-monthly-vip-vouchers')->monthlyOn(1, '00:10');

Artisan::command('user:reset-admin', function () {
    $this->info('Đang đồng bộ danh sách Roles...');
    $this->call(\Database\Seeders\RolesTableSeeder::class);

    $this->info('Đang tạo/cập nhật thông tin các Users hệ thống...');
    $this->call(\Database\Seeders\UsersTableSeeder::class);

    $this->info('Đang gán quyền cho tài khoản Admin...');
    $this->call(\Database\Seeders\UserRolesTableSeeder::class);

    $this->info('Khôi phục tài khoản Super Admin thành công!');
})->purpose('Reset and re-assign roles for superadmin');
