<?php

namespace App\Services\Auth;

use Illuminate\Support\Collection;

class SystemPermissionCatalog
{
    public const ACTION_LABELS = [
        'access' => 'Truy cập',
        'create' => 'Thêm',
        'update' => 'Sửa',
        'delete' => 'Xóa/Ngưng',
        'process' => 'Duyệt/Xử lý',
    ];

    public static function sections(): array
    {
        return [
            self::section('overview', 'Tổng quan', [
                self::row('dashboard', 'Bảng điều hành', 'Xem số liệu và tình hình vận hành toàn hệ thống.', [
                    'access' => self::permissions(['dashboard.view' => 'Truy cập bảng điều hành']),
                ]),
                self::row('profile', 'Hồ sơ cá nhân', 'Xem và cập nhật hồ sơ của chính nhân sự đang đăng nhập.', [
                    'access' => self::permissions(['profile.view' => 'Truy cập hồ sơ cá nhân']),
                    'update' => self::permissions(['profile.update' => 'Cập nhật hồ sơ cá nhân']),
                ]),
            ]),
            self::section('venue_operations', 'Vận hành sân', [
                self::row('venue', 'Cụm sân', 'Xem, cập nhật, khóa hoặc xử lý yêu cầu liên quan đến cụm sân.', [
                    'access' => self::permissions(['venue.view' => 'Xem cụm sân']),
                    'update' => self::permissions(['venue.manage' => 'Cập nhật cụm sân'], 'sensitive'),
                    'process' => self::permissions(['venue.lock' => 'Khóa hoặc mở khóa cụm sân'], 'sensitive'),
                ]),
                self::row('court', 'Sân con', 'Xem và cập nhật thông tin sân con trong các cụm sân.', [
                    'access' => self::permissions(['court.view' => 'Xem sân con']),
                    'update' => self::permissions(['court.manage' => 'Cập nhật sân con'], 'sensitive'),
                ]),
                self::row('platform_fee', 'Phí nền tảng', 'Quản lý bậc phí, kỳ phí, nhắc phí và xử lý thanh toán phí nền tảng.', [
                    'access' => self::permissions(['platform_fee.view' => 'Truy cập quản lý phí nền tảng']),
                    'create' => self::permissions(['platform_fee.create' => 'Tạo bậc phí hoặc kỳ phí'], 'finance'),
                    'update' => self::permissions(['platform_fee.update' => 'Cập nhật cấu hình phí nền tảng'], 'finance'),
                    'delete' => self::permissions(['platform_fee.delete' => 'Ngưng hoặc xóa cấu hình phí nền tảng'], 'finance'),
                    'process' => self::permissions(['platform_fee.process' => 'Xử lý kỳ phí và thanh toán phí'], 'finance'),
                ]),
                self::row('partner', 'Hồ sơ đối tác và hợp đồng', 'Xem hồ sơ, tài liệu, hợp đồng và xử lý quy trình đối tác.', [
                    'access' => self::permissions(['partner.view' => 'Truy cập hồ sơ đối tác']),
                    'process' => self::permissions(['partner.review' => 'Duyệt và xử lý hồ sơ, hợp đồng đối tác'], 'sensitive'),
                ]),
                self::row('booking', 'Booking hệ thống', 'Xem và hỗ trợ xử lý booking trong phạm vi nghiệp vụ được giao.', [
                    'access' => self::permissions(['booking.view' => 'Truy cập booking hệ thống']),
                    'update' => self::permissions(['booking.manage' => 'Cập nhật booking'], 'sensitive'),
                    'process' => self::permissions(['booking.support' => 'Hỗ trợ xử lý booking'], 'sensitive'),
                ]),
                self::row('pricing', 'Bảng giá', 'Xem và quản lý bảng giá sân trong hệ thống.', [
                    'access' => self::permissions(['price.view' => 'Truy cập bảng giá']),
                    'update' => self::permissions(['price.manage' => 'Cập nhật bảng giá'], 'sensitive'),
                ]),
            ]),
            self::section('people_permissions', 'Người dùng và phân quyền', [
                self::row('staff', 'Nhân sự hệ thống', 'Tạo tài khoản, gán nhóm quyền và khóa nhân sự nội bộ.', [
                    'access' => self::permissions(['staff.view' => 'Truy cập quản lý nhân sự']),
                    'create' => self::permissions(['staff.create' => 'Tạo nhân sự hệ thống'], 'permission'),
                    'update' => self::permissions(['staff.assign_role' => 'Gán nhóm quyền cho nhân sự'], 'permission'),
                    'process' => self::permissions(['staff.lock' => 'Khóa hoặc mở khóa nhân sự'], 'account_lock'),
                ]),
                self::row('user', 'Tài khoản người dùng', 'Xem và kiểm soát trạng thái tài khoản người dùng.', [
                    'access' => self::permissions(['user.view' => 'Truy cập quản lý tài khoản']),
                    'process' => self::permissions([
                        'user.lock' => 'Khóa tài khoản người dùng',
                        'user.unlock' => 'Mở khóa tài khoản người dùng',
                    ], 'account_lock'),
                ]),
                self::row('role', 'Nhóm quyền', 'Tạo, sửa, xóa và cấu hình quyền cho các nhóm nhân sự hệ thống.', [
                    'access' => self::permissions(['role.view' => 'Truy cập quản lý nhóm quyền']),
                    'create' => self::permissions(['role.create' => 'Tạo nhóm quyền'], 'permission'),
                    'update' => self::permissions(['role.update' => 'Cập nhật nhóm quyền'], 'permission'),
                    'delete' => self::permissions(['role.delete' => 'Xóa nhóm quyền'], 'permission'),
                    'process' => self::permissions([
                        'role.permission.manage' => 'Phân quyền cho nhóm',
                        'role.manage' => 'Quản lý vai trò hệ thống',
                    ], 'permission'),
                ]),
            ]),
            self::section('finance', 'Tài chính', [
                self::row('payment', 'Thanh toán booking', 'Theo dõi và xử lý giao dịch thanh toán booking.', [
                    'access' => self::permissions(['payment.view' => 'Truy cập thanh toán booking'], 'finance'),
                    'process' => self::permissions(['payment.manage' => 'Xử lý thanh toán booking'], 'finance'),
                ]),
                self::row('refund', 'Hoàn tiền', 'Xem và xử lý yêu cầu hoàn tiền của khách hàng.', [
                    'access' => self::permissions(['refund.view' => 'Xem yêu cầu hoàn tiền'], 'finance'),
                    'process' => self::permissions(['refund.approve' => 'Duyệt hoàn tiền'], 'finance'),
                ]),
                self::row('withdrawal', 'Rút tiền', 'Xem và xử lý yêu cầu rút tiền của chủ sân hoặc người dùng.', [
                    'access' => self::permissions(['withdrawal.view' => 'Xem yêu cầu rút tiền'], 'finance'),
                    'process' => self::permissions(['withdrawal.manage' => 'Xử lý yêu cầu rút tiền'], 'finance'),
                ]),
                self::row('wallet', 'Số dư và đối soát', 'Xem số dư và thực hiện nghiệp vụ đối soát tài chính.', [
                    'access' => self::permissions(['wallet.view' => 'Xem số dư người dùng và chủ sân'], 'finance'),
                    'process' => self::permissions(['reconciliation.manage' => 'Xử lý đối soát'], 'finance'),
                ]),
                self::row('voucher', 'Voucher hệ thống', 'Tạo và quản lý voucher do hệ thống tài trợ.', [
                    'access' => self::permissions(['voucher.view' => 'Truy cập voucher hệ thống']),
                    'create' => self::permissions(['voucher.create' => 'Tạo voucher hệ thống'], 'sensitive'),
                    'update' => self::permissions(['voucher.update' => 'Cập nhật voucher hệ thống'], 'sensitive'),
                    'delete' => self::permissions(['voucher.delete' => 'Ngưng hoặc kích hoạt voucher hệ thống'], 'sensitive'),
                ]),
                self::row('membership', 'Gói VIP hệ thống', 'Xem và cập nhật cấu hình các gói VIP hệ thống.', [
                    'access' => self::permissions(['membership.view' => 'Truy cập gói VIP hệ thống']),
                    'update' => self::permissions(['membership.update' => 'Cập nhật gói VIP hệ thống'], 'sensitive'),
                ]),
            ]),
            self::section('content_configuration', 'Nội dung và cấu hình', [
                self::row('system_post', 'Tin tức hệ thống', 'Tạo, chỉnh sửa và quản lý tin tức do hệ thống phát hành.', [
                    'access' => self::permissions(['system_post.view' => 'Truy cập tin tức hệ thống']),
                    'create' => self::permissions(['system_post.create' => 'Tạo tin tức hệ thống']),
                    'update' => self::permissions(['system_post.update' => 'Cập nhật tin tức hệ thống']),
                    'delete' => self::permissions(['system_post.delete' => 'Xóa tin tức hệ thống'], 'sensitive'),
                ]),
                self::row('policy', 'Chính sách', 'Quản lý phiên bản, quy tắc và trạng thái áp dụng của chính sách.', [
                    'access' => self::permissions(['policy.view' => 'Truy cập quản lý chính sách']),
                    'create' => self::permissions(['policy.create' => 'Tạo chính sách'], 'system'),
                    'update' => self::permissions(['policy.update' => 'Cập nhật chính sách'], 'system'),
                    'delete' => self::permissions(['policy.delete' => 'Xóa chính sách'], 'system'),
                    'process' => self::permissions([
                        'policy.publish' => 'Công bố chính sách',
                        'policy.rule.manage' => 'Quản lý quy tắc chính sách',
                    ], 'system'),
                ]),
                self::row('system_profile', 'Thông tin hệ thống', 'Quản lý thông tin pháp lý, liên hệ và nhận diện dùng trên giấy tờ.', [
                    'access' => self::permissions(['system_profile.view' => 'Truy cập thông tin hệ thống']),
                    'update' => self::permissions(['system_profile.update' => 'Cập nhật thông tin hệ thống'], 'system'),
                ]),
                self::row('court_type', 'Loại sân', 'Quản lý danh mục loại sân dùng toàn hệ thống.', [
                    'access' => self::permissions(['court_type.view' => 'Truy cập loại sân']),
                    'create' => self::permissions(['court_type.create' => 'Tạo loại sân']),
                    'update' => self::permissions(['court_type.update' => 'Cập nhật loại sân']),
                    'delete' => self::permissions(['court_type.delete' => 'Xóa loại sân'], 'sensitive'),
                ]),
                self::row('amenity', 'Tiện ích', 'Quản lý và duyệt danh mục tiện ích sân.', [
                    'access' => self::permissions(['amenity.view' => 'Truy cập tiện ích']),
                    'create' => self::permissions(['amenity.create' => 'Tạo tiện ích']),
                    'update' => self::permissions(['amenity.update' => 'Cập nhật tiện ích']),
                    'delete' => self::permissions(['amenity.delete' => 'Xóa tiện ích'], 'sensitive'),
                    'process' => self::permissions(['amenity.review' => 'Duyệt đề xuất tiện ích'], 'sensitive'),
                ]),
                self::row('service_category', 'Danh mục dịch vụ', 'Quản lý danh mục dịch vụ và sản phẩm tại sân.', [
                    'access' => self::permissions(['service_category.view' => 'Truy cập danh mục dịch vụ']),
                    'create' => self::permissions(['service_category.create' => 'Tạo danh mục dịch vụ']),
                    'update' => self::permissions(['service_category.update' => 'Cập nhật danh mục dịch vụ']),
                    'delete' => self::permissions(['service_category.delete' => 'Xóa hoặc ngưng danh mục dịch vụ'], 'sensitive'),
                ]),
                self::row('banner', 'Banner', 'Quản lý banner hiển thị trên ứng dụng SportGo.', [
                    'access' => self::permissions(['banner.view' => 'Truy cập quản lý banner']),
                    'create' => self::permissions(['banner.create' => 'Tạo banner']),
                    'update' => self::permissions(['banner.update' => 'Cập nhật và sắp xếp banner']),
                    'delete' => self::permissions(['banner.delete' => 'Xóa banner'], 'sensitive'),
                ]),
                self::row('ui_settings', 'Cài đặt giao diện', 'Xem và cập nhật giao diện dùng chung của khu vực quản trị.', [
                    'access' => self::permissions(['ui_settings.view' => 'Truy cập cài đặt giao diện']),
                    'update' => self::permissions(['ui_settings.update' => 'Cập nhật cài đặt giao diện'], 'system'),
                ]),
            ]),
            self::section('moderation_support', 'Kiểm duyệt và hỗ trợ', [
                self::row('moderation', 'Kiểm duyệt nội dung', 'Xem, duyệt, từ chối, ẩn hoặc xóa nội dung vi phạm.', [
                    'access' => self::permissions(['moderation.view' => 'Truy cập hàng đợi kiểm duyệt']),
                    'update' => self::permissions(['moderation.manage' => 'Quản lý nội dung kiểm duyệt'], 'sensitive'),
                    'delete' => self::permissions(['moderation.delete' => 'Xóa nội dung vi phạm'], 'sensitive'),
                    'process' => self::permissions([
                        'moderation.approve' => 'Duyệt nội dung',
                        'moderation.reject' => 'Từ chối nội dung',
                    ], 'sensitive'),
                ]),
                self::row('content', 'Nội dung hệ thống', 'Xem và cập nhật trạng thái nội dung trong hệ thống.', [
                    'access' => self::permissions(['content.view' => 'Xem nội dung hệ thống']),
                    'update' => self::permissions(['content.manage' => 'Cập nhật trạng thái nội dung'], 'sensitive'),
                ]),
                self::row('report', 'Báo cáo vi phạm', 'Xem, tiếp nhận và xử lý báo cáo vi phạm.', [
                    'access' => self::permissions(['report.view' => 'Xem báo cáo vi phạm']),
                    'process' => self::permissions(['report.resolve' => 'Xử lý báo cáo vi phạm'], 'sensitive'),
                ]),
                self::row('complaint', 'Khiếu nại', 'Xem, tiếp nhận và xử lý khiếu nại.', [
                    'access' => self::permissions(['complaint.view' => 'Xem khiếu nại']),
                    'process' => self::permissions(['complaint.handle' => 'Xử lý khiếu nại'], 'sensitive'),
                ]),
                self::row('audit', 'Nhật ký hệ thống', 'Xem lịch sử các thao tác nhạy cảm trong hệ thống.', [
                    'access' => self::permissions(['audit.view' => 'Truy cập nhật ký hệ thống'], 'system'),
                ]),
            ]),
        ];
    }

    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::sections() as $section) {
            foreach ($section['rows'] as $row) {
                foreach ($row['actions'] as $permissions) {
                    foreach ($permissions as $permission) {
                        $definitions[$permission['code']] = [
                            'name' => $permission['name'],
                            'group_name' => $row['label'],
                            'description' => $row['description'],
                            'risk_level' => $permission['risk_level'],
                            'module_key' => $row['key'],
                        ];
                    }
                }
            }
        }

        return $definitions;
    }

    public static function permissionMeta(string $code): ?array
    {
        $definition = self::definitions()[$code] ?? null;

        if (! $definition) {
            return null;
        }

        return [
            'label' => $definition['name'],
            'description' => $definition['description'],
            'risk_level' => $definition['risk_level'],
            'module_key' => $definition['module_key'],
        ];
    }

    public static function matrixGroups(Collection $permissions, callable $payload): array
    {
        $byCode = $permissions->keyBy('code');

        return collect(self::sections())
            ->map(function (array $section) use ($byCode, $payload): array {
                $rows = collect($section['rows'])
                    ->map(function (array $row) use ($byCode, $payload): array {
                        $actions = [];
                        $rowPermissions = collect();

                        foreach (self::ACTION_LABELS as $actionKey => $actionLabel) {
                            $actionPermissions = collect($row['actions'][$actionKey] ?? [])
                                ->map(fn (array $definition) => $byCode->get($definition['code']))
                                ->filter()
                                ->values();

                            if ($actionPermissions->isEmpty()) {
                                continue;
                            }

                            $rowPermissions = $rowPermissions->merge($actionPermissions);
                            $actions[$actionKey] = [
                                'key' => $actionKey,
                                'label' => $actionLabel,
                                'permission_ids' => $actionPermissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
                                'permission_codes' => $actionPermissions->pluck('code')->all(),
                            ];
                        }

                        $rowPermissions = $rowPermissions->unique('id')->values();

                        return [
                            'key' => $row['key'],
                            'label' => $row['label'],
                            'description' => $row['description'],
                            'actions' => $actions,
                            'all_permission_ids' => $rowPermissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
                            'permissions' => $rowPermissions->map($payload)->values()->all(),
                        ];
                    })
                    ->filter(fn (array $row): bool => $row['all_permission_ids'] !== [])
                    ->values();

                return [
                    'group_name' => $section['key'],
                    'module_label' => $section['label'],
                    'module_description' => $section['description'],
                    'rows' => $rows->all(),
                    'permissions' => $rows->flatMap(fn (array $row) => $row['permissions'])->unique('id')->values()->all(),
                ];
            })
            ->filter(fn (array $section): bool => $section['rows'] !== [])
            ->values()
            ->all();
    }

    public static function validateSelection(array $selectedCodes): array
    {
        $selected = collect($selectedCodes)->unique();
        $errors = [];

        foreach (self::sections() as $section) {
            foreach ($section['rows'] as $row) {
                $accessCodes = collect($row['actions']['access'] ?? [])->pluck('code');
                $dependentCodes = collect($row['actions'])
                    ->except('access')
                    ->flatten(1)
                    ->pluck('code');

                if ($dependentCodes->intersect($selected)->isNotEmpty() && $accessCodes->diff($selected)->isNotEmpty()) {
                    $errors[] = 'Chức năng "'.$row['label'].'" phải bật Truy cập trước khi cấp quyền thao tác.';
                }
            }
        }

        return $errors;
    }

    public static function cascadeRevokedAccess(array $selectedCodes, array $revokedCodes): array
    {
        $selected = collect($selectedCodes)->unique();
        $revoked = collect($revokedCodes);

        foreach (self::sections() as $section) {
            foreach ($section['rows'] as $row) {
                $accessCodes = collect($row['actions']['access'] ?? [])->pluck('code');

                if ($accessCodes->intersect($revoked)->isEmpty()) {
                    continue;
                }

                $dependentCodes = collect($row['actions'])
                    ->except('access')
                    ->flatten(1)
                    ->pluck('code');
                $selected = $selected->diff($dependentCodes);
            }
        }

        return $selected->values()->all();
    }

    public static function effectiveCodes(array $selectedCodes): array
    {
        $selected = collect($selectedCodes)->unique();

        foreach (self::sections() as $section) {
            foreach ($section['rows'] as $row) {
                $accessCodes = collect($row['actions']['access'] ?? [])->pluck('code');

                if ($accessCodes->isEmpty() || $accessCodes->diff($selected)->isEmpty()) {
                    continue;
                }

                $dependentCodes = collect($row['actions'])
                    ->except('access')
                    ->flatten(1)
                    ->pluck('code');
                $selected = $selected->diff($dependentCodes);
            }
        }

        return $selected->values()->all();
    }

    public static function defaultRolePermissions(): array
    {
        $all = array_keys(self::definitions());
        $common = ['dashboard.view', 'profile.view', 'profile.update'];

        return [
            'super_admin' => $all,
            'admin' => $all,
            'system_staff' => array_merge($common, [
                'user.view', 'content.view', 'moderation.view', 'report.view', 'complaint.view',
                'booking.view', 'venue.view', 'court.view', 'policy.view',
            ]),
            'content_moderator' => array_merge($common, [
                'system_post.view', 'system_post.create', 'system_post.update', 'system_post.delete',
                'content.view', 'content.manage', 'moderation.view', 'moderation.manage',
                'moderation.delete', 'moderation.approve', 'moderation.reject', 'report.view', 'report.resolve',
            ]),
            'complaint_handler' => array_merge($common, [
                'complaint.view', 'complaint.handle', 'booking.view', 'venue.view', 'court.view', 'report.view',
            ]),
            'venue_manager' => array_merge($common, [
                'venue.view', 'venue.manage', 'venue.lock', 'court.view', 'court.manage',
                'partner.view', 'booking.view', 'platform_fee.view',
            ]),
            'partner_manager' => array_merge($common, ['partner.view', 'partner.review', 'venue.view', 'court.view']),
            'booking_support' => array_merge($common, [
                'booking.view', 'booking.manage', 'booking.support', 'payment.view', 'venue.view', 'court.view',
            ]),
            'finance_operator' => array_merge($common, [
                'payment.view', 'payment.manage', 'refund.view', 'refund.approve', 'withdrawal.view',
                'wallet.view', 'withdrawal.manage', 'reconciliation.manage', 'booking.view', 'audit.view',
                'platform_fee.view', 'platform_fee.process',
            ]),
            'policy_manager' => array_merge($common, [
                'policy.view', 'policy.create', 'policy.update', 'policy.delete', 'policy.publish',
                'policy.rule.manage', 'audit.view',
            ]),
            'staff_manager' => array_merge($common, [
                'staff.view', 'staff.create', 'staff.assign_role', 'staff.lock',
                'user.view', 'user.lock', 'user.unlock', 'role.view',
            ]),
            'venue_owner' => array_merge($common, [
                'venue.view', 'venue.manage', 'court.view', 'court.manage',
                'booking.view', 'booking.manage', 'price.view', 'price.manage',
            ]),
            'venue_staff' => array_merge($common, ['court.view', 'booking.view', 'booking.manage']),
            'user' => ['profile.view', 'profile.update', 'booking.view'],
        ];
    }

    private static function section(string $key, string $label, array $rows): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => 'Phân quyền theo từng chức năng trong khu vực '.$label.'.',
            'rows' => $rows,
        ];
    }

    private static function row(string $key, string $label, string $description, array $actions): array
    {
        return compact('key', 'label', 'description', 'actions');
    }

    private static function permissions(array $permissions, string $riskLevel = 'normal'): array
    {
        return collect($permissions)
            ->map(fn (string $name, string $code): array => [
                'code' => $code,
                'name' => $name,
                'risk_level' => $riskLevel,
            ])
            ->values()
            ->all();
    }
}
