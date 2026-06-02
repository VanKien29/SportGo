<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemPolicy;
use App\Models\UserPolicyAcceptance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PolicyAcceptanceController extends Controller
{
    public function required(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập để kiểm tra chính sách.'], 401);
        }

        $policies = SystemPolicy::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->where('require_reaccept', true)
            ->where(function ($query): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', now());
            })
            ->orderByDesc('priority')
            ->orderBy('policy_type')
            ->orderBy('key')
            ->get()
            ->filter(fn (SystemPolicy $policy): bool => ! $this->hasAccepted($user->id, $policy))
            ->map(fn (SystemPolicy $policy): array => $this->policyPayload($policy))
            ->values();

        return response()->json([
            'message' => $policies->isNotEmpty()
                ? 'Người dùng cần xác nhận chính sách trước khi tiếp tục.'
                : 'Không có chính sách mới cần xác nhận.',
            'required' => $policies->isNotEmpty(),
            'count' => $policies->count(),
            'data' => $policies,
            'policies' => $policies,
        ]);
    }

    public function accept(Request $request, SystemPolicy $policy): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập để xác nhận chính sách.'], 401);
        }

        if (! $this->canBeAccepted($policy)) {
            return response()->json([
                'message' => 'Chính sách này hiện không cần hoặc không thể xác nhận.',
            ], 422);
        }

        $values = [
            'accepted_at' => now(),
        ];

        if (Schema::hasColumn('user_policy_acceptances', 'ip_address')) {
            $values['ip_address'] = $request->ip();
        }

        if (Schema::hasColumn('user_policy_acceptances', 'user_agent')) {
            $values['user_agent'] = substr((string) $request->userAgent(), 0, 500);
        }

        $acceptance = UserPolicyAcceptance::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'system_policy_id' => $policy->id,
                'policy_version' => (string) $policy->version,
            ],
            $values,
        );

        if ($acceptance->wasRecentlyCreated) {
            $this->auditAcceptance($request, $policy, $acceptance);
        }

        return response()->json([
            'message' => 'Đã ghi nhận xác nhận chính sách.',
            'accepted' => true,
            'already_accepted' => ! $acceptance->wasRecentlyCreated,
            'data' => [
                'policy_id' => $policy->id,
                'policy_version' => (string) $policy->version,
                'accepted_at' => $acceptance->accepted_at,
            ],
        ]);
    }

    private function hasAccepted(string $userId, SystemPolicy $policy): bool
    {
        return UserPolicyAcceptance::query()
            ->where('user_id', $userId)
            ->where('system_policy_id', $policy->id)
            ->where('policy_version', (string) $policy->version)
            ->exists();
    }

    private function canBeAccepted(SystemPolicy $policy): bool
    {
        if (! $policy->is_active || $policy->status !== 'active' || ! $policy->require_reaccept) {
            return false;
        }

        if ($policy->effective_from && $policy->effective_from->isAfter(now())) {
            return false;
        }

        if ($policy->effective_to && $policy->effective_to->isPast()) {
            return false;
        }

        return true;
    }

    private function policyPayload(SystemPolicy $policy): array
    {
        $type = $policy->policy_type ?: $policy->type;

        return [
            'id' => $policy->id,
            'key' => $policy->key,
            'title' => $policy->title,
            'content' => $policy->content,
            'type' => $type,
            'policy_type' => $type,
            'policy_type_label' => $this->policyTypeLabel($type),
            'version' => (int) $policy->version,
            'effective_from' => $policy->effective_from,
            'change_summary' => $policy->change_summary,
            'require_reaccept' => (bool) $policy->require_reaccept,
        ];
    }

    private function policyTypeLabel(?string $type): string
    {
        return [
            'general' => 'Chung',
            'refund' => 'Hủy lịch và hoàn tiền',
            'booking' => 'Đặt sân',
            'moderation' => 'Kiểm duyệt và báo cáo',
            'account' => 'Tài khoản',
            'platform_fee' => 'Phí duy trì cụm sân',
            'terms' => 'Điều khoản sử dụng',
        ][$type] ?? ($type ?: 'Không xác định');
    }

    private function auditAcceptance(Request $request, SystemPolicy $policy, UserPolicyAcceptance $acceptance): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $request->user()?->id,
            'actor_type' => 'user',
            'module' => 'policy',
            'action' => 'policy.accepted',
            'entity_type' => 'user_policy_acceptances',
            'entity_id' => (string) $acceptance->id,
            'old_values' => null,
            'new_values' => [
                'system_policy_id' => $policy->id,
                'policy_key' => $policy->key,
                'policy_version' => (string) $policy->version,
                'accepted_at' => $acceptance->accepted_at,
            ],
            'context' => 'policy_acceptance',
            'metadata' => [
                'policy_title' => $policy->title,
                'policy_type' => $policy->policy_type ?: $policy->type,
            ],
            'policy_id' => $policy->id,
            'severity' => 'info',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
