<?php

namespace App\Support;

use App\Models\PolicyRule;
use App\Models\SystemPolicy;

class PolicyUiText
{
    public static function policyTypeLabels(): array
    {
        return [
            'terms' => 'Điều khoản sử dụng',
            'booking_cancellation' => 'Hoàn hủy',
            'refund' => 'Hoàn tiền',
            'platform_fee' => 'Phí nền tảng',
            'venue_policy' => 'Chính sách sân',
            'moderation' => 'Kiểm duyệt & báo cáo',
            'partner_contract' => 'Đối tác & hợp đồng',
            'general' => 'Chung',
            'booking' => 'Đặt sân',
            'account' => 'Tài khoản',
            'text_only' => 'Văn bản',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'draft' => 'Bản nháp',
            'active' => 'Đang áp dụng',
            'inactive' => 'Ngưng áp dụng',
            'archived' => 'Lưu trữ',
            'pending_review' => 'Chờ duyệt',
            'rejected' => 'Bị từ chối',
            'pending_owner_confirmation' => 'Chờ chủ sân xác nhận',
            'owner_confirmed' => 'Chủ sân đã xác nhận',
            'admin_processing' => 'Admin đang xử lý',
            'completed' => 'Hoàn tất',
            'owner_rejected' => 'Chủ sân từ chối',
            'pending_owner_signature' => 'Chờ chủ sân ký',
            'pending_sportgo_signature' => 'Chờ SportGo ký',
            'signed_active' => 'Đã ký và có hiệu lực',
            'terminated' => 'Đã chấm dứt',
        ];
    }

    public static function actionOptions(): array
    {
        return [
            self::action('auth', 'Xác nhận chính sách', 'first_login.accept_policy', 'Người dùng/chủ sân chấp nhận chính sách', 'Áp dụng khi tài khoản cần đọc và xác nhận điều khoản hoặc chính sách mới.', ['terms']),
            self::action('booking', 'Hủy booking', 'booking.cancel_by_customer', 'Khách hủy booking', 'Áp dụng khi khách yêu cầu hủy booking.', ['booking_cancellation']),
            self::action('booking', 'Hủy booking', 'booking.cancel_by_owner', 'Chủ sân hủy booking', 'Áp dụng khi chủ sân hoặc nhân viên sân hủy booking.', ['booking_cancellation']),
            self::action('booking', 'Hủy booking', 'booking.expire_unpaid', 'Hệ thống hủy booking do quá hạn thanh toán', 'Áp dụng khi booking hết thời gian giữ chỗ nhưng chưa thanh toán.', ['booking_cancellation']),
            self::action('refund', 'Hoàn tiền', 'refund.owner_fault_100', 'Hoàn 100% do lỗi phía sân', 'Áp dụng khi chủ sân hủy, khóa sân hoặc bảo trì làm ảnh hưởng booking đã thanh toán.', ['booking_cancellation', 'refund']),
            self::action('refund', 'Hoàn tiền', 'refund.request', 'Khách gửi yêu cầu hoàn tiền', 'Áp dụng khi khách gửi yêu cầu hoàn tiền.', ['booking_cancellation', 'refund']),
            self::action('refund', 'Hoàn tiền', 'refund.owner_confirm', 'Chủ sân xác nhận yêu cầu hoàn tiền', 'Áp dụng khi chủ sân đồng ý hoặc từ chối yêu cầu hoàn.', ['booking_cancellation', 'refund']),
            self::action('refund', 'Hoàn tiền', 'refund.admin_complete', 'Admin xác nhận hoàn tất hoàn tiền', 'Áp dụng khi admin xác nhận giao dịch hoàn tiền đã hoàn tất.', ['booking_cancellation', 'refund']),
            self::action('venue', 'Phí nền tảng', 'venue.platform_fee_due', 'Sắp đến hạn hoặc quá hạn phí nền tảng', 'Áp dụng khi hệ thống kiểm tra kỳ phí nền tảng của cụm sân.', ['platform_fee']),
            self::action('venue', 'Phí nền tảng', 'venue.lock_due_fee', 'Khóa/giới hạn cụm sân do quá hạn phí nền tảng', 'Áp dụng khi cụm sân quá hạn phí duy trì.', ['platform_fee']),
            self::action('owner', 'Phí nền tảng', 'owner.access_limited_due_fee', 'Giới hạn quyền chủ sân do quá hạn phí', 'Áp dụng khi owner chỉ được thao tác trong phạm vi cho phép.', ['platform_fee']),
            self::action('venue_policy', 'Chính sách sân', 'venue_policy.submit', 'Chủ sân gửi chính sách riêng để duyệt', 'Áp dụng khi owner gửi chính sách sân riêng.', ['venue_policy']),
            self::action('venue_policy', 'Chính sách sân', 'venue_policy.activate', 'Kích hoạt chính sách sân hợp lệ', 'Áp dụng khi chính sách sân vượt kiểm tra ràng buộc.', ['venue_policy']),
            self::action('moderation', 'Kiểm duyệt', 'post.report', 'Người dùng báo cáo nội dung', 'Áp dụng khi người dùng báo cáo bài viết hoặc bình luận.', ['moderation']),
            self::action('moderation', 'Kiểm duyệt', 'post.hide', 'Ẩn nội dung vi phạm', 'Áp dụng khi hệ thống hoặc admin ẩn nội dung vi phạm.', ['moderation']),
            self::action('moderation', 'Kiểm duyệt', 'report.score_evaluated', 'Đánh giá điểm vi phạm nội dung/người dùng', 'Áp dụng khi hệ thống tính điểm vi phạm dựa trên báo cáo.', ['moderation']),
            self::action('moderation', 'Kiểm duyệt', 'report.resolve', 'Xử lý vi phạm theo mức leo thang', 'Áp dụng khi hệ thống xử lý vi phạm theo quy tắc leo thang.', ['moderation']),
            self::action('partner', 'Đối tác & hợp đồng', 'partner_application.approve', 'Admin duyệt hồ sơ đối tác', 'Áp dụng khi admin duyệt hồ sơ đăng ký làm chủ sân.', ['partner_contract']),
            self::action('partner', 'Đối tác & hợp đồng', 'partner_contract.generate', 'Hệ thống sinh hợp đồng đối tác', 'Áp dụng khi hệ thống tạo hợp đồng từ template.', ['partner_contract']),
            self::action('partner', 'Đối tác & hợp đồng', 'partner_contract.sign', 'Chủ sân và SportGo ký hợp đồng', 'Áp dụng khi hai bên ký xác nhận hợp đồng.', ['partner_contract']),
            self::action('partner', 'Đối tác & hợp đồng', 'partner_termination.approve', 'Admin duyệt yêu cầu chấm dứt hợp tác', 'Áp dụng khi admin duyệt chấm dứt hợp tác và bắt đầu thời gian chuyển tiếp.', ['partner_contract']),
        ];
    }

    public static function ruleTemplateOptions(): array
    {
        return [
            'terms_acceptance_required' => self::template(
                'terms',
                'terms_acceptance_required',
                'Bắt buộc chấp nhận điều khoản trước khi sử dụng',
                'Yêu cầu người dùng/chủ sân xác nhận chính sách trước khi tiếp tục.',
                'first_login.accept_policy',
                ['active_policy_version_not_accepted' => true],
                ['must_accept' => true],
                'must_accept_terms',
                'terms_acceptance',
                false,
                'high'
            ),
            'cancel_before_hours' => self::template(
                'booking_cancellation',
                'cancel_before_hours',
                'Bảng mốc thời gian được hủy booking',
                'Kiểm tra khách có được hủy booking theo từng mốc thời gian trước giờ chơi hay không.',
                'booking.cancel_by_customer',
                ['uses_tier_table' => true],
                ['tiers' => self::defaultCancellationTiers(), 'may_create_refund_request' => true],
                'cancel_allowed',
                'booking_cancel_window',
                true,
                'medium',
                ['booking.cancel_by_customer', 'booking.cancel_by_owner', 'booking.expire_unpaid']
            ),
            'refund_percent_by_cancel_time' => self::template(
                'refund',
                'refund_percent_by_cancel_time',
                'Tính phần trăm hoàn tiền theo thời gian hủy',
                'Tính mức hoàn tiền theo số giờ khách hủy trước giờ chơi.',
                'refund.request',
                ['uses_tier_table' => true],
                ['tiers' => self::defaultRefundTiers(), 'refund_percent' => 100, 'requires_owner_confirm' => true, 'requires_admin_confirm' => true],
                'refund_percent',
                'refund_percent_minimum',
                true,
                'high'
            ),
            'owner_fault_full_refund' => self::template(
                'booking_cancellation',
                'owner_fault_full_refund',
                'Hoàn 100% khi lỗi phát sinh từ phía sân',
                'Khi chủ sân hủy, khóa sân hoặc bảo trì làm ảnh hưởng booking đã thanh toán, hệ thống hoàn 100% vào ví SportGo của khách.',
                'refund.owner_fault_100',
                ['owner_fault_refund' => true],
                ['refund_percent' => 100, 'refund_basis' => 'paid_amount', 'refund_destination' => 'user_wallet', 'requires_owner_confirm' => false, 'requires_admin_confirm' => true],
                'refund_percent',
                'owner_fault_refund',
                false,
                'critical',
                ['refund.owner_fault_100', 'refund.admin_complete']
            ),
            'owner_confirm_required_before_admin_transfer' => self::template(
                'refund',
                'owner_confirm_required_before_admin_transfer',
                'Bắt buộc chủ sân xác nhận trước khi admin hoàn tiền',
                'Admin không được hoàn tất yêu cầu hoàn tiền nếu chủ sân chưa xác nhận.',
                'refund.owner_confirm',
                ['refund_requested' => true],
                ['owner_confirm_required' => true, 'admin_can_complete_without_owner' => false],
                'owner_confirm_required',
                'refund_owner_confirm',
                false,
                'critical',
                ['refund.owner_confirm', 'refund.admin_complete']
            ),
            'platform_fee_overdue_warning' => self::template(
                'platform_fee',
                'platform_fee_overdue_warning',
                'Nhắc chủ sân khi sắp/quá hạn phí nền tảng',
                'Gửi thông báo nhắc chủ sân khi kỳ phí sắp đến hạn hoặc đã quá hạn.',
                'venue.platform_fee_due',
                ['days_before_due' => ['lte' => 3]],
                ['action' => 'notify_owner', 'message_type' => 'platform_fee_reminder'],
                'platform_fee_warning',
                'platform_fee_reminder',
                false,
                'medium'
            ),
            'platform_fee_overdue_lock' => self::template(
                'platform_fee',
                'platform_fee_overdue_lock',
                'Giới hạn hoặc khóa cụm sân khi quá hạn phí nền tảng',
                'Giới hạn quyền owner khi cụm sân quá hạn phí duy trì.',
                'venue.lock_due_fee',
                ['overdue_days' => ['gte' => 7], 'amount_due_remaining' => ['gt' => 0]],
                ['action' => 'limit_owner_access', 'access_mode' => 'limited', 'lock_after_days' => 7],
                'venue_fee_action',
                'platform_fee_overdue',
                false,
                'critical',
                ['venue.lock_due_fee', 'owner.access_limited_due_fee']
            ),
            'venue_policy_override_limit' => self::template(
                'venue_policy',
                'venue_policy_override_limit',
                'Giới hạn chính sách riêng của sân theo khung hệ thống',
                'Chính sách sân chỉ được áp dụng khi không thấp hơn hoặc trái chính sách hệ thống.',
                'venue_policy.submit',
                ['is_overridable' => true, 'constraint_check_passed' => true],
                ['allow_activate' => true, 'reject_if_failed' => true],
                'venue_policy_constraint_passed',
                'venue_policy_override',
                false,
                'critical',
                ['venue_policy.submit', 'venue_policy.activate']
            ),
            'report_threshold_requires_review' => self::template(
                'moderation',
                'report_threshold_requires_review',
                'Đưa nội dung vào chờ kiểm duyệt khi có nhiều báo cáo',
                'Đưa nội dung vào chờ kiểm duyệt khi có đủ báo cáo hợp lệ từ nhiều người.',
                'post.report',
                ['target_type' => 'content', 'report_count' => ['gte' => 5], 'unique_reporters' => ['gte' => 2], 'window_days' => 14],
                ['actions' => ['pending_review', 'notify_admin'], 'action' => 'pending_review'],
                'report_review_required',
                'moderation_report_threshold',
                false,
                'medium',
                ['post.report', 'post.hide']
            ),
            'moderation_score_threshold' => self::template(
                'moderation',
                'moderation_score_threshold',
                'Ngưỡng điểm vi phạm theo đối tượng kiểm duyệt',
                'Xác định ngưỡng điểm cảnh báo và xử lý cho từng loại đối tượng (bài viết, bình luận, người dùng, sân).',
                'report.score_evaluated',
                ['target_type' => 'content', 'timeframe_days' => 30],
                ['action_threshold' => 10, 'warning_threshold' => 5, 'unique_reporters_threshold' => 3],
                'moderation_score',
                'moderation_score_threshold',
                false,
                'high',
                ['report.score_evaluated']
            ),
            'penalty_escalation' => self::template(
                'moderation',
                'penalty_escalation',
                'Leo thang xử lý vi phạm theo số lần vi phạm',
                'Tự động áp dụng mức xử lý tăng dần khi đối tượng tái phạm nhiều lần.',
                'report.resolve',
                ['target_type' => 'user', 'violation_count' => 1],
                ['action' => 'warning', 'duration_days' => null],
                'penalty_action',
                'penalty_escalation',
                false,
                'critical',
                ['report.resolve']
            ),
            'contract_signing_required' => self::template(
                'partner_contract',
                'contract_signing_required',
                'Hợp đồng phải có đủ chữ ký mới có hiệu lực',
                'Hợp đồng đối tác chỉ active khi chủ sân và SportGo đã ký.',
                'partner_contract.sign',
                ['owner_signed' => true, 'sportgo_signed' => true],
                ['contract_status' => 'signed_active', 'complete_partner_application' => true],
                'contract_can_activate',
                'partner_contract_signature',
                false,
                'critical'
            ),
            'partner_termination_transition_30_days' => self::template(
                'partner_contract',
                'partner_termination_transition_30_days',
                'Thu quyền chủ sân sau thời gian chuyển tiếp khi chấm dứt hợp đồng',
                'Sau 30 ngày chuyển tiếp kể từ khi duyệt chấm dứt, hệ thống chặn quyền owner.',
                'partner_termination.approve',
                ['approved_termination' => true],
                ['transition_days' => 30, 'access_mode_after_transition' => 'blocked'],
                'owner_access_revocation_date',
                'partner_termination_transition',
                false,
                'high'
            ),
            'partner_application_approve_requires_contract' => self::template(
                'partner_contract',
                'partner_application_approve_requires_contract',
                'Duyệt hồ sơ đối tác xong phải sinh hợp đồng',
                'Sau khi admin duyệt hồ sơ đối tác, hệ thống phải sinh hợp đồng để hai bên ký.',
                'partner_application.approve',
                ['application_status' => 'approved_pending_contract'],
                ['next_step' => 'generate_partner_contract', 'contract_required' => true],
                'contract_required_after_application_approved',
                'partner_application_contract_flow',
                false,
                'high',
                ['partner_application.approve', 'partner_contract.generate']
            ),
        ];
    }

    public static function policyTypeLabel(?string $type): string
    {
        return self::policyTypeLabels()[$type] ?? ($type ?: 'Không xác định');
    }

    public static function statusLabel(?string $status): string
    {
        if (! $status) {
            return 'Không xác định';
        }

        $normalized = strtolower($status);
        return self::statusLabels()[$normalized] ?? self::statusLabels()[$status] ?? $status;
    }

    public static function actionLabel(?string $code): string
    {
        if (! $code) {
            return 'Không xác định';
        }

        foreach (self::actionOptions() as $option) {
            if ($option['action_code'] === $code) {
                return $option['action_label'];
            }
        }

        return $code;
    }

    public static function ruleTypeLabel(?string $ruleType): string
    {
        if (! $ruleType) {
            return 'Không xác định';
        }

        return self::ruleTemplateOptions()[$ruleType]['label'] ?? $ruleType;
    }

    public static function moduleLabel(?string $module): string
    {
        return [
            'auth' => 'Xác nhận chính sách',
            'booking' => 'Đặt sân',
            'refund' => 'Hoàn tiền',
            'venue' => 'Cụm sân',
            'owner' => 'Quyền chủ sân',
            'venue_policy' => 'Chính sách sân',
            'moderation' => 'Kiểm duyệt',
            'partner' => 'Đối tác & hợp đồng',
        ][$module] ?? ($module ?: 'Khác');
    }

    public static function policyBusinessSummary(SystemPolicy $policy): string
    {
        $type = $policy->policy_type ?: $policy->type;

        $summary = [
            'terms' => 'Quy định người dùng và chủ sân cần đọc, hiểu và xác nhận trước khi sử dụng SportGo.',
            'booking_cancellation' => 'Quy định điều kiện khách được hủy booking và mốc tiền hoàn tự động tương ứng theo bảng cấu hình.',
            'platform_fee' => 'Quy định nhắc phí, giới hạn quyền và khóa cụm sân khi chủ sân quá hạn phí nền tảng.',
            'venue_policy' => 'Quy định phạm vi chủ sân được cấu hình chính sách riêng và các giới hạn không được vượt khung hệ thống.',
            'moderation' => 'Quy định cách xử lý nội dung bị báo cáo, nội dung vi phạm và các bước kiểm duyệt.',
            'partner_contract' => 'Quy định duyệt hồ sơ đối tác, sinh hợp đồng, ký hợp đồng và chấm dứt hợp tác.',
        ][$type] ?? 'Chính sách vận hành của SportGo.';

        if ($policy->require_reaccept) {
            $summary .= ' Khi có phiên bản mới, hệ thống có thể yêu cầu người dùng xác nhận lại.';
        }

        if ($policy->is_overridable) {
            $summary .= ' Một số sân có thể cấu hình riêng nhưng không được thấp hơn khung hệ thống.';
        }

        return $summary;
    }

    public static function ruleBusinessSummary(PolicyRule $rule): string
    {
        return self::ruleSummary($rule->rule_type ?: $rule->rule_code, $rule->condition_json ?: [], $rule->result_json ?: []);
    }

    public static function ruleSummary(string $ruleType, array $condition, array $result): string
    {
        return match ($ruleType) {
            'terms_acceptance_required' => 'Nếu người dùng/chủ sân chưa chấp nhận phiên bản chính sách đang áp dụng, hệ thống yêu cầu xác nhận trước khi tiếp tục sử dụng.',
            'cancel_before_hours' => sprintf(
                '%s',
                isset($result['tiers']) && is_array($result['tiers'])
                    ? self::cancellationTierSummary($result['tiers'])
                    : sprintf(
                        'Khách chỉ được hủy booking trước giờ chơi tối thiểu %s giờ và booking phải ở trạng thái hợp lệ.',
                        self::conditionValue($condition, 'hours_before_start')
                    )
            ),
            'refund_percent_by_cancel_time' => sprintf(
                '%s',
                isset($result['tiers']) && is_array($result['tiers'])
                    ? self::refundTierSummary($result['tiers'])
                    : sprintf(
                        'Nếu khách hủy booking trước giờ chơi ít nhất %s giờ, hệ thống đề xuất hoàn tối thiểu %s%% số tiền đã thanh toán.',
                        self::conditionValue($condition, 'hours_before_start'),
                        self::scalar($result['refund_percent'] ?? '?')
                    )
            ),
            'owner_fault_full_refund' => 'Nếu booking bị ảnh hưởng do chủ sân hủy, khóa sân hoặc bảo trì, hệ thống hoàn 100% phần đã thanh toán vào ví SportGo của khách.',
            'owner_confirm_required_before_admin_transfer' => 'Nếu yêu cầu hoàn tiền chưa được chủ sân xác nhận, admin không được chuyển tiền và không được chuyển yêu cầu sang hoàn tất.',
            'platform_fee_overdue_warning' => 'Khi phí nền tảng sắp đến hạn hoặc đã quá hạn, hệ thống gửi nhắc nhở cho chủ sân.',
            'platform_fee_overdue_lock' => sprintf(
                'Nếu cụm sân quá hạn phí nền tảng %s ngày, hệ thống chuyển cụm sân sang trạng thái bị giới hạn quyền.',
                self::conditionValue($condition, 'overdue_days')
            ),
            'venue_policy_override_limit' => 'Nếu chính sách sân thấp hơn hoặc trái khung hệ thống, hệ thống không cho active và lưu lý do từ chối.',
            'report_threshold_requires_review' => sprintf(
                'Nếu một nội dung nhận từ %s báo cáo hợp lệ bởi ít nhất %s người khác nhau trong %s ngày, hệ thống đưa nội dung vào chờ kiểm duyệt.',
                self::conditionValue($condition, 'report_count'),
                self::conditionValue($condition, 'unique_reporters'),
                self::scalar($condition['window_days'] ?? '?')
            ),
            'contract_signing_required' => 'Hợp đồng đối tác chỉ được chuyển sang có hiệu lực khi đã có chữ ký chủ sân và chữ ký SportGo.',
            'partner_termination_transition_30_days' => sprintf(
                'Sau %s ngày chuyển tiếp kể từ khi duyệt chấm dứt hợp tác, hệ thống thu quyền chủ sân và chặn quyền quản lý owner.',
                self::scalar($result['transition_days'] ?? 30)
            ),
            'partner_application_approve_requires_contract' => 'Sau khi admin duyệt hồ sơ đối tác, hệ thống phải sinh hợp đồng để chủ sân và SportGo ký trước khi hoàn tất hồ sơ.',
            'moderation_score_threshold' => sprintf(
                'Xác định ngưỡng xử lý và cảnh báo cho đối tượng kiểm duyệt trong vòng %s ngày (Cảnh báo khi đạt %s điểm từ %s người báo cáo, xử lý vi phạm khi đạt %s điểm).',
                self::scalar($condition['timeframe_days'] ?? '?'),
                self::scalar($result['warning_threshold'] ?? '?'),
                self::scalar($result['unique_reporters_threshold'] ?? '?'),
                self::scalar($result['action_threshold'] ?? '?')
            ),
            'penalty_escalation' => sprintf(
                'Tự động áp dụng hình phạt %s (%s ngày) khi tài khoản tái phạm lần thứ %s.',
                self::resultActionLabel($result['action'] ?? '?'),
                self::scalar($result['duration_days'] ?? 'vô thời hạn'),
                self::scalar($condition['violation_count'] ?? '?')
            ),
            default => $ruleType,
        };
    }

    public static function conditionSummary(string $ruleType, array $condition): string
    {
        return match ($ruleType) {
            'cancel_before_hours' => ($condition['uses_tier_table'] ?? false)
                ? 'Kiểm tra theo bảng 4 mốc thời gian trước giờ chơi.'
                : 'Thời gian trước giờ chơi: tối thiểu ' . self::conditionValue($condition, 'hours_before_start') . ' giờ.',
            'refund_percent_by_cancel_time' => ($condition['uses_tier_table'] ?? false)
                ? 'Tính theo bảng 4 mốc thời gian trước giờ chơi.'
                : 'Thời gian trước giờ chơi: tối thiểu ' . self::conditionValue($condition, 'hours_before_start') . ' giờ.',
            'owner_fault_full_refund' => 'Lý do hoàn tiền phát sinh từ lỗi phía sân.',
            'platform_fee_overdue_lock' => 'Quá hạn phí: tối thiểu ' . self::conditionValue($condition, 'overdue_days') . ' ngày.',
            'report_threshold_requires_review' => 'Báo cáo hợp lệ: từ ' . self::conditionValue($condition, 'report_count') . ' báo cáo, bởi ít nhất ' . self::conditionValue($condition, 'unique_reporters') . ' người, trong ' . self::scalar($condition['window_days'] ?? '?') . ' ngày.',
            'contract_signing_required' => 'Có đủ chữ ký chủ sân và chữ ký SportGo.',
            'partner_termination_transition_30_days' => 'Yêu cầu chấm dứt hợp tác đã được duyệt.',
            default => 'Điều kiện được kiểm tra theo mẫu nghiệp vụ đã chọn.',
        };
    }

    public static function resultSummary(string $ruleType, array $result): string
    {
        return match ($ruleType) {
            'cancel_before_hours' => isset($result['tiers']) && is_array($result['tiers'])
                ? self::cancellationTierSummary($result['tiers'])
                : (($result['allow_cancel'] ?? false) ? 'Cho hủy booking.' : 'Không cho hủy booking.'),
            'refund_percent_by_cancel_time' => isset($result['tiers']) && is_array($result['tiers'])
                ? self::refundTierSummary($result['tiers'])
                : 'Đề xuất hoàn ' . self::scalar($result['refund_percent'] ?? '?') . '% số tiền đã thanh toán.',
            'owner_fault_full_refund' => 'Hoàn 100% vào ví SportGo của khách, không áp dụng mốc hủy do khách.',
            'owner_confirm_required_before_admin_transfer' => 'Bắt buộc chủ sân xác nhận trước khi admin hoàn tất.',
            'platform_fee_overdue_warning' => 'Gửi thông báo nhắc phí cho chủ sân.',
            'platform_fee_overdue_lock' => 'Giới hạn quyền owner: chỉ được đóng phí, xem ví/rút tiền nếu được phép, xem hồ sơ/hợp đồng.',
            'venue_policy_override_limit' => 'Chính sách sân hợp lệ được duyệt; nếu vi phạm thì bị từ chối.',
            'report_threshold_requires_review' => 'Đưa nội dung vào chờ kiểm duyệt và thông báo cho admin.',
            'contract_signing_required' => 'Hợp đồng chuyển sang đã ký và có hiệu lực.',
            'partner_termination_transition_30_days' => 'Sau thời gian chuyển tiếp, owner bị chặn quyền quản lý cụm sân.',
            'partner_application_approve_requires_contract' => 'Sinh hợp đồng đối tác và chuyển sang bước ký.',
            default => 'Hệ thống xử lý theo kết quả đã cấu hình.',
        };
    }

    public static function resultActionLabel(mixed $action): string
    {
        if (! is_scalar($action) && $action !== null) {
            return 'xử lý theo cấu hình';
        }

        return [
            'notify_owner' => 'gửi nhắc nhở cho chủ sân',
            'warning' => 'gửi cảnh báo',
            'require_admin_review' => 'đưa vào chờ admin kiểm duyệt',
            'pending_review' => 'chuyển sang chờ kiểm duyệt',
            'hide_temporarily' => 'ẩn tạm nội dung',
            'notify_admin' => 'thông báo admin',
            'temporary_lock' => 'khóa tạm nếu hệ thống hỗ trợ',
            'hide_content' => 'ẩn nội dung vi phạm',
            'limit_owner_access' => 'giới hạn quyền chủ sân',
            'lock_venue' => 'khóa hoặc giới hạn cụm sân',
            'cancel_booking' => 'hủy booking',
        ][$action] ?? ($action ?: 'xử lý theo cấu hình');
    }

    private static function action(string $module, string $moduleLabel, string $code, string $label, string $description, array $policyTypes): array
    {
        return [
            'module' => $module,
            'module_label' => $moduleLabel,
            'action_code' => $code,
            'action_label' => $label,
            'action_label_vi' => $label,
            'description' => $description,
            'policy_types' => $policyTypes,
        ];
    }

    private static function defaultRefundTiers(): array
    {
        return [
            ['key' => 'from_24', 'label' => 'Từ 24 giờ trở lên', 'from_hours' => 24, 'to_hours' => null, 'refund_percent' => 100, 'allow_cancel' => true],
            ['key' => 'from_6_to_24', 'label' => 'Từ 6 đến dưới 24 giờ', 'from_hours' => 6, 'to_hours' => 24, 'refund_percent' => 80, 'allow_cancel' => true],
            ['key' => 'from_1_to_6', 'label' => 'Từ 1 đến dưới 6 giờ', 'from_hours' => 1, 'to_hours' => 6, 'refund_percent' => 50, 'allow_cancel' => true],
            ['key' => 'under_1', 'label' => 'Dưới 1 giờ', 'from_hours' => null, 'to_hours' => 1, 'refund_percent' => 0, 'allow_cancel' => true],
        ];
    }

    private static function defaultCancellationTiers(): array
    {
        return [
            ['key' => 'from_24', 'label' => 'Từ 24 giờ trở lên', 'from_hours' => 24, 'to_hours' => null, 'allow_cancel' => true],
            ['key' => 'from_6_to_24', 'label' => 'Từ 6 đến dưới 24 giờ', 'from_hours' => 6, 'to_hours' => 24, 'allow_cancel' => true],
            ['key' => 'from_1_to_6', 'label' => 'Từ 1 đến dưới 6 giờ', 'from_hours' => 1, 'to_hours' => 6, 'allow_cancel' => true],
            ['key' => 'under_1', 'label' => 'Dưới 1 giờ', 'from_hours' => null, 'to_hours' => 1, 'allow_cancel' => true],
        ];
    }

    private static function template(
        string $policyType,
        string $ruleType,
        string $label,
        string $description,
        string $actionCode,
        array $condition,
        array $result,
        string $decisionKey,
        string $conflictGroup,
        bool $venueOverridable,
        string $riskLevel,
        ?array $actionCodes = null
    ): array {
        $policyTypes = [$policyType];
        if (in_array($ruleType, ['refund_percent_by_cancel_time', 'owner_confirm_required_before_admin_transfer', 'owner_fault_full_refund'], true)) {
            $policyTypes = array_values(array_unique([...$policyTypes, 'booking_cancellation', 'refund']));
        }

        return [
            'policy_type' => $policyType,
            'rule_type' => $ruleType,
            'rule_code' => $ruleType,
            'label' => $label,
            'rule_label_vi' => $label,
            'rule_type_label' => $label,
            'description' => $description,
            'action_code' => $actionCode,
            'action_label_vi' => self::actionLabel($actionCode),
            'decision_key' => $decisionKey,
            'conflict_group' => $conflictGroup,
            'condition_json' => $condition,
            'result_json' => $result,
            'condition_summary_vi' => self::conditionSummary($ruleType, $condition),
            'result_summary_vi' => self::resultSummary($ruleType, $result),
            'business_summary_vi' => self::ruleSummary($ruleType, $condition, $result),
            'policy_types' => $policyTypes,
            'action_codes' => $actionCodes ?: [$actionCode],
            'is_venue_overridable' => $venueOverridable,
            'risk_level' => $riskLevel,
        ];
    }

    private static function conditionValue(array $condition, string $field): string
    {
        $value = $condition[$field] ?? '?';
        if (is_array($value)) {
            $value = $value['gte'] ?? $value['lte'] ?? $value['eq'] ?? $value['value'] ?? reset($value);
        }

        return self::scalar($value);
    }

    private static function refundTierSummary(array $tiers): string
    {
        $items = collect($tiers)->map(function (array $tier): string {
            $label = $tier['label'] ?? self::refundTierLabel($tier);
            $allowCancel = array_key_exists('allow_cancel', $tier) ? (bool) $tier['allow_cancel'] : true;
            $percent = self::scalar($tier['refund_percent'] ?? 0);
            $result = $allowCancel
                ? ((float) ($tier['refund_percent'] ?? 0) > 0 ? "hoàn {$percent}%" : 'cho hủy nhưng không hoàn')
                : 'không cho hủy';

            return "{$label}: {$result}";
        });

        return $items->implode('. ') . '.';
    }

    private static function cancellationTierSummary(array $tiers): string
    {
        $items = collect($tiers)->map(function (array $tier): string {
            $label = $tier['label'] ?? self::refundTierLabel($tier);
            $allowCancel = array_key_exists('allow_cancel', $tier) ? (bool) $tier['allow_cancel'] : true;
            $result = $allowCancel ? 'cho hủy' : 'không cho hủy';

            return "{$label}: {$result}";
        });

        return $items->implode('. ') . '.';
    }

    private static function refundTierLabel(array $tier): string
    {
        $from = $tier['from_hours'] ?? null;
        $to = $tier['to_hours'] ?? null;

        if ((int) $from === 24 && $to === null) {
            return 'Từ 24 giờ trở lên';
        }
        if ((int) $from === 6 && (int) $to === 24) {
            return 'Từ 6 đến dưới 24 giờ';
        }
        if ((int) $from === 1 && (int) $to === 6) {
            return 'Từ 1 đến dưới 6 giờ';
        }
        if (($from === null || $from === '') && (int) $to === 1) {
            return 'Dưới 1 giờ';
        }

        return 'Mốc hủy/hoàn';
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '?';
        }
        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'dữ liệu kỹ thuật';
    }
}
