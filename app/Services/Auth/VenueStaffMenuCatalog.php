<?php

namespace App\Services\Auth;

class VenueStaffMenuCatalog
{
    public static function items(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Tổng quan ca trực',
                'description' => 'Xem số liệu ca trực, công việc và trạng thái vận hành được giao.',
                'default' => true,
                'requires_all_cluster' => false,
            ],
            [
                'key' => 'schedules',
                'label' => 'Lịch trực của tôi',
                'description' => 'Xem lịch trực, chấm công vào ca và kết thúc ca.',
                'default' => true,
                'requires_all_cluster' => false,
            ],
            [
                'key' => 'bookings',
                'label' => 'Quản lý booking',
                'description' => 'Xem và xử lý booking trong phạm vi sân được phân công.',
                'default' => false,
                'requires_all_cluster' => false,
            ],
            [
                'key' => 'counter_booking',
                'label' => 'Đặt sân tại quầy',
                'description' => 'Tạo booking lẻ, lịch cố định và thu tiền tại quầy.',
                'default' => false,
                'requires_all_cluster' => false,
            ],
            [
                'key' => 'vouchers',
                'label' => 'Quản lý voucher sân',
                'description' => 'Xem, tạo, sửa và ngưng voucher của cụm sân.',
                'default' => false,
                'requires_all_cluster' => true,
            ],
            [
                'key' => 'chat',
                'label' => 'Trò chuyện',
                'description' => 'Trao đổi với khách hàng và các bên liên quan.',
                'default' => false,
                'requires_all_cluster' => false,
            ],
            [
                'key' => 'settings',
                'label' => 'Cài đặt giao diện',
                'description' => 'Điều chỉnh giao diện làm việc của khu vực nhân viên sân.',
                'default' => false,
                'requires_all_cluster' => false,
            ],
        ];
    }

    public static function keys(): array
    {
        return array_column(self::items(), 'key');
    }

    public static function defaultKeys(): array
    {
        return collect(self::items())
            ->where('default', true)
            ->pluck('key')
            ->values()
            ->all();
    }

    public static function legacyKeys(): array
    {
        return ['dashboard', 'schedules', 'bookings', 'counter_booking', 'chat', 'settings'];
    }

    public static function requiresAllCluster(string $key): bool
    {
        $item = collect(self::items())->firstWhere('key', $key);

        return (bool) ($item['requires_all_cluster'] ?? false);
    }
}
