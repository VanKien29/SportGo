<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminRoutePermissionResolver
{
    public function resolve(Request $request): array
    {
        $relative = Str::after($request->path(), 'api/admin/');
        $method = strtoupper($request->method());

        if ($relative === 'dashboard' || Str::startsWith($relative, ['pending-counts', 'work-center'])) {
            return $this->all('dashboard.view');
        }

        if (Str::startsWith($relative, 'system-profile')) {
            return $this->all($method === 'GET' ? 'system_profile.view' : 'system_profile.update');
        }

        if (Str::startsWith($relative, 'ui-settings')) {
            return $this->all($method === 'GET' ? 'ui_settings.view' : 'ui_settings.update');
        }

        if ($relative === 'user-lock-policy') {
            return $this->all('user.lock');
        }

        if (Str::startsWith($relative, 'users')) {
            return $this->userPermission($request, $relative, $method);
        }

        if (Str::startsWith($relative, 'vouchers')) {
            if ($method === 'GET') {
                return $this->all('voucher.view');
            }

            if ($method === 'POST' && $relative === 'vouchers') {
                return $this->all('voucher.create');
            }

            if ($method === 'PUT') {
                return $this->all('voucher.update');
            }

            return $this->all('voucher.delete');
        }

        if (Str::startsWith($relative, 'membership-packages')) {
            return $this->all($method === 'GET' ? 'membership.view' : 'membership.update');
        }

        if (Str::startsWith($relative, 'payments')) {
            return $this->all($method === 'GET' ? 'payment.view' : 'payment.manage');
        }

        if (Str::startsWith($relative, 'finance/refunds')) {
            return $this->all($method === 'GET' ? 'refund.view' : 'refund.approve');
        }

        if (Str::startsWith($relative, ['finance/withdrawals', 'finance/user-withdrawals'])) {
            return $this->all($method === 'GET' ? 'withdrawal.view' : 'withdrawal.manage');
        }

        if (Str::startsWith($relative, 'finance/system-wallet')) {
            return $this->all($method === 'GET' ? 'wallet.view' : 'reconciliation.manage');
        }

        if (Str::startsWith($relative, 'platform-fee-plans')) {
            if ($method === 'GET') {
                return $this->all('platform_fee.view');
            }

            return $this->all($method === 'POST' && $relative === 'platform-fee-plans'
                ? 'platform_fee.create'
                : 'platform_fee.update');
        }

        if (Str::startsWith($relative, 'platform-fee-arrangements')) {
            if ($method === 'GET') {
                return $this->all('platform_fee.view');
            }

            return $this->all($method === 'POST' && $relative === 'platform-fee-arrangements'
                ? 'platform_fee.create'
                : 'platform_fee.process');
        }

        if (Str::startsWith($relative, ['platform-fee-ledgers', 'platform-fee-tiers', 'platform-fee-settings'])) {
            if ($method === 'GET') {
                return $this->all('platform_fee.view');
            }

            if ($method === 'DELETE') {
                return $this->all('platform_fee.delete');
            }

            if ($method === 'POST' && in_array($relative, ['platform-fee-ledgers', 'platform-fee-tiers'], true)) {
                return $this->all('platform_fee.create');
            }

            if (Str::contains($relative, ['pay', 'overdue', 'cancel', 'lock-venue', 'unlock-venue', 'reminders', 'preview'])) {
                return $this->all('platform_fee.process');
            }

            return $this->all('platform_fee.update');
        }

        if (Str::startsWith($relative, ['partner-applications', 'partner-profiles', 'partner-termination-requests', 'contracts', 'termination-settings'])) {
            return $this->all($method === 'GET' ? 'partner.view' : 'partner.review');
        }

        if (Str::startsWith($relative, 'banners')) {
            if ($method === 'GET') {
                return $this->all('banner.view');
            }

            if ($method === 'DELETE') {
                return $this->all('banner.delete');
            }

            return $this->all($method === 'POST' && $relative === 'banners' ? 'banner.create' : 'banner.update');
        }

        if (Str::startsWith($relative, ['reports', 'violation-records'])) {
            return $this->all($method === 'GET' ? 'report.view' : 'report.resolve');
        }

        if ($relative === 'report-resolve-policy') {
            return $this->all('policy.rule.manage');
        }

        if (Str::startsWith($relative, 'complaints')) {
            return $this->all($method === 'GET' ? 'complaint.view' : 'complaint.handle');
        }

        if ($relative === 'complaint-resolve-policy') {
            return $this->all('policy.rule.manage');
        }

        if (Str::startsWith($relative, 'violation-types')) {
            return $this->all($method === 'GET' ? 'policy.view' : 'policy.rule.manage');
        }

        if (Str::startsWith($relative, 'court-types')) {
            return $this->resourcePermission($method, 'court_type');
        }

        if (Str::startsWith($relative, 'amenities')) {
            if (Str::endsWith($relative, '/review')) {
                return $this->all('amenity.review');
            }

            return $this->resourcePermission($method, 'amenity');
        }

        if (Str::startsWith($relative, 'service-categories')) {
            if (Str::endsWith($relative, '/toggle-status')) {
                return $this->all('service_category.delete');
            }

            return $this->resourcePermission($method, 'service_category');
        }

        if ($relative === 'permissions' || Str::startsWith($relative, 'roles')) {
            if ($method === 'GET') {
                return $this->all('role.view');
            }

            if ($method === 'DELETE') {
                return $this->all('role.delete');
            }

            if (Str::contains($relative, '/permissions')) {
                return $this->all('role.permission.manage');
            }

            return $this->all($method === 'POST' ? 'role.create' : 'role.update');
        }

        if (Str::startsWith($relative, 'policies')) {
            if ($method === 'GET') {
                return $this->all('policy.view');
            }

            if ($method === 'DELETE') {
                return $this->all('policy.delete');
            }

            if (Str::contains($relative, ['publish', 'status'])) {
                return $this->all('policy.publish');
            }

            if (Str::contains($relative, ['rules', 'bindings', 'configuration', 'thresholds', 'cancel-refund-tiers'])) {
                return $this->all('policy.rule.manage');
            }

            return $this->all($method === 'POST' ? 'policy.create' : 'policy.update');
        }

        if (Str::startsWith($relative, 'venue-clusters')) {
            if ($method === 'GET') {
                return $this->all('venue.view');
            }

            if (Str::contains($relative, ['/lock', '/unlock'])) {
                return $this->all('venue.lock');
            }

            return $this->all('venue.manage');
        }

        if (Str::startsWith($relative, 'moderation')) {
            if ($method === 'GET') {
                return $this->all('moderation.view');
            }

            if ($method === 'DELETE') {
                return $this->all('moderation.delete');
            }

            if (Str::endsWith($relative, '/approve')) {
                return $this->all('moderation.approve');
            }

            if (Str::endsWith($relative, '/reject')) {
                return $this->all('moderation.reject');
            }

            return $this->all('moderation.manage');
        }

        if (Str::startsWith($relative, 'venue-posts')) {
            if ($method === 'GET') {
                return $this->all('moderation.view');
            }

            if ($method === 'DELETE') {
                return $this->all('moderation.delete');
            }

            return $this->all('moderation.manage');
        }

        if (Str::startsWith($relative, 'system-posts')) {
            return $this->resourcePermission($method, 'system_post');
        }

        if (Str::startsWith($relative, ['posts', 'comments'])) {
            return $method === 'GET'
                ? $this->any(['user.view', 'moderation.view', 'content.view'])
                : $this->any(['moderation.manage', 'content.manage']);
        }

        return ['permissions' => [], 'mode' => 'all'];
    }

    private function userPermission(Request $request, string $relative, string $method): array
    {
        $isStaffRequest = $request->query('role_group') === 'staff';
        $targetUserId = preg_match('/^users\/([^\/]+)/', $relative, $matches) ? $matches[1] : null;

        if ($targetUserId && ctype_digit((string) $targetUserId)) {
            $adminRoleNames = [
                'super_admin', 'admin', 'system_staff', 'content_moderator', 'complaint_handler',
                'venue_manager', 'partner_manager', 'booking_support', 'finance_operator',
                'policy_manager', 'staff_manager',
            ];
            $isStaffRequest = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $targetUserId)
                ->whereIn('roles.name', $adminRoleNames)
                ->exists();
        }

        if ($method === 'GET') {
            return $this->all($isStaffRequest ? 'staff.view' : 'user.view');
        }

        if ($method === 'POST' && $relative === 'users') {
            return $this->all('staff.create');
        }

        if ($method === 'PUT') {
            return $this->all('staff.assign_role');
        }

        if (Str::endsWith($relative, '/unlock')) {
            return $this->all($isStaffRequest ? 'staff.lock' : 'user.unlock');
        }

        return $this->all($isStaffRequest ? 'staff.lock' : 'user.lock');
    }

    private function resourcePermission(string $method, string $prefix): array
    {
        return match ($method) {
            'GET' => $this->all($prefix.'.view'),
            'POST' => $this->all($prefix.'.create'),
            'DELETE' => $this->all($prefix.'.delete'),
            default => $this->all($prefix.'.update'),
        };
    }

    private function all(string|array $permissions): array
    {
        return ['permissions' => (array) $permissions, 'mode' => 'all'];
    }

    private function any(array $permissions): array
    {
        return ['permissions' => $permissions, 'mode' => 'any'];
    }
}
