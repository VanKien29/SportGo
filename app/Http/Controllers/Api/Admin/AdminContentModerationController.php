<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\VenuePost;
use App\Models\SystemPost;
use App\Models\CommunityPostComment;
use App\Models\Report;
use App\Models\Notification;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminContentModerationController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    /**
     * Lấy hàng chờ duyệt (Bài viết cộng đồng, bài cụm sân, báo cáo vi phạm)
     */
    public function queue(Request $request): JsonResponse
    {
        $type = $request->query('type', 'community_posts');
        $statusFilter = $request->query('status', 'all');

        if (in_array($type, ['report', 'reports'], true)) {
            $this->authorizePermission($request, 'report.view');

            $query = Report::query()
                ->with([
                    'reporter:id,username,full_name,email,phone',
                    'reviewedBy:id,username,full_name',
                    'reportable' => function ($morphTo): void {
                        $morphTo->morphWith([
                            CommunityPost::class => ['author:id,username,full_name,email,phone,avatar_url', 'media', 'hashtags'],
                            VenuePost::class => ['author:id,username,full_name,email,phone,avatar_url', 'venueCluster:id,name,slug', 'media', 'hashtags'],
                            CommunityPostComment::class => ['user:id,username,full_name,email,phone', 'post:id,content,author_id', 'post.author:id,username,full_name']
                        ]);
                    }
                ])
                ->whereIn('status', ['pending', 'reviewing']);

            if ($request->filled('search')) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function ($q) use ($search): void {
                    $q->where('description', 'like', $search)
                        ->orWhere('reason', 'like', $search)
                        ->orWhereHas('reporter', function ($rq) use ($search): void {
                            $rq->where('username', 'like', $search)
                                ->orWhere('full_name', 'like', $search);
                        });
                });
            }

            if ($request->filled('reason')) {
                $query->where('reason', $request->input('reason'));
            }

            $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
            $reports = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $reports,
            ]);
        }

        $this->authorizePermission($request, 'moderation.view');

        if (in_array($type, ['system_post', 'system_posts'], true)) {
            $query = SystemPost::query()
                ->with(['author:id,username,full_name,email,phone,avatar_url']);

            if ($statusFilter === 'published') {
                $query->where('status', 'published');
            } elseif ($statusFilter === 'pending') {
                $query->whereIn('status', ['pending_review', 'pending', 'draft']);
            } elseif ($statusFilter === 'hidden') {
                $query->whereIn('status', ['hidden', 'rejected']);
            }

            if ($request->filled('search')) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', $search)
                        ->orWhere('content', 'like', $search)
                        ->orWhereHas('author', function ($aq) use ($search): void {
                            $aq->where('username', 'like', $search)
                                ->orWhere('full_name', 'like', $search);
                        });
                });
            }

            $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
            $posts = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $posts,
            ]);
        }

        if (in_array($type, ['venue_post', 'venue_posts'], true)) {
            $query = VenuePost::query()
                ->with(['author:id,username,full_name,email,phone,avatar_url', 'venueCluster:id,name,slug', 'media', 'hashtags']);

            if ($statusFilter === 'published') {
                $query->where('status', 'published');
            } elseif ($statusFilter === 'pending') {
                $query->where('status', 'pending_review');
            } elseif ($statusFilter === 'hidden') {
                $query->whereIn('status', ['hidden', 'rejected']);
            }

            if ($request->filled('search')) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function ($q) use ($search): void {
                    $q->where('content', 'like', $search)
                        ->orWhereHas('author', function ($aq) use ($search): void {
                            $aq->where('username', 'like', $search)
                                ->orWhere('full_name', 'like', $search);
                        });
                });
            }

            $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
            $posts = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $posts,
            ]);
        }

        // Mặc định là community_posts
        $query = CommunityPost::query()
            ->with(['author:id,username,full_name,email,phone,avatar_url', 'media', 'hashtags']);

        if ($statusFilter === 'published') {
            $query->where('status', 'published');
        } elseif ($statusFilter === 'pending') {
            $query->where('status', 'pending_review');
        } elseif ($statusFilter === 'hidden') {
            $query->whereIn('status', ['hidden', 'rejected']);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search): void {
                $q->where('content', 'like', $search)
                    ->orWhereHas('author', function ($aq) use ($search): void {
                        $aq->where('username', 'like', $search)
                            ->orWhere('full_name', 'like', $search);
                    });
            });
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $posts = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $posts,
        ]);
    }

    /**
     * Duyệt bài viết
     */
    public function approvePost(Request $request, string $type, string $id): JsonResponse
    {
        $this->authorizePermission($request, ['moderation.approve', 'moderation.manage']);

        $post = $this->findPost($type, $id);
        $tableName = $this->getPostTableName($type);

        if (!in_array($post->status, ['pending_review', 'pending', 'draft'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bài viết không ở trạng thái chờ duyệt.',
            ], 422);
        }

        $oldValues = $post->toArray();

        $post->status = 'published';
        $post->reviewed_by = $request->user()?->id;
        $post->reviewed_at = now();
        $post->save();

        // Gửi thông báo in-app cho tác giả
        $this->sendNotification(
            $post->author_id,
            'post_approved',
            'Bài viết của bạn đã được duyệt',
            'Bài viết của bạn đã được phê duyệt và hiển thị công khai.',
            $tableName,
            $post->id
        );

        // Ghi Audit Log
        $this->audit->log($request, 'moderation', 'post.approved', $tableName, $post->id, $oldValues, $post->fresh()->toArray(), [
            'severity' => 'info'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã duyệt bài viết thành công.',
            'data' => $post,
        ]);
    }

    /**
     * Từ chối bài viết (Yêu cầu nhập lý do)
     */
    public function rejectPost(Request $request, string $type, string $id): JsonResponse
    {
        $this->authorizePermission($request, ['moderation.reject', 'moderation.manage']);

        $post = $this->findPost($type, $id);
        $tableName = $this->getPostTableName($type);

        if (!in_array($post->status, ['pending_review', 'pending', 'draft'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bài viết không ở trạng thái chờ duyệt.',
            ], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        $oldValues = $post->toArray();

        $post->status = 'rejected';
        $post->status_reason = $data['reason'];
        $post->reviewed_by = $request->user()?->id;
        $post->reviewed_at = now();
        $post->save();

        // Gửi thông báo in-app cho tác giả
        $this->sendNotification(
            $post->author_id,
            'post_rejected',
            'Bài viết bị từ chối kiểm duyệt',
            'Bài viết bị từ chối hiển thị. Lý do: ' . $data['reason'],
            $tableName,
            $post->id
        );

        // Ghi Audit Log
        $this->audit->log($request, 'moderation', 'post.rejected', $tableName, $post->id, $oldValues, $post->fresh()->toArray(), [
            'reason' => $data['reason'],
            'severity' => 'warning'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã từ chối bài viết.',
            'data' => $post,
        ]);
    }

    /**
     * Ẩn bài viết (Yêu cầu nhập lý do)
     */
    public function hidePost(Request $request, string $type, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'moderation.manage');

        $post = $this->findPost($type, $id);
        $tableName = $this->getPostTableName($type);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do ẩn bài viết.',
        ]);

        $oldValues = $post->toArray();

        $post->status = 'hidden';
        $post->status_reason = $data['reason'];
        $post->reviewed_by = $request->user()?->id;
        $post->reviewed_at = now();
        $post->save();

        // Gửi thông báo in-app cho tác giả
        $this->sendNotification(
            $post->author_id,
            'post_hidden',
            'Bài viết đã bị ẩn',
            'Bài viết của bạn đã bị ẩn bởi quản trị viên. Lý do: ' . $data['reason'],
            $tableName,
            $post->id
        );

        // Ghi Audit Log
        $this->audit->log($request, 'moderation', 'post.hidden', $tableName, $post->id, $oldValues, $post->fresh()->toArray(), [
            'reason' => $data['reason'],
            'severity' => 'warning'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã ẩn bài viết.',
            'data' => $post,
        ]);
    }

    /**
     * Xóa bài viết (Tạm thời chuyển sang trạng thái hidden để tránh xóa hẳn DB theo yêu cầu của user)
     */
    public function deletePost(Request $request, string $type, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'moderation.manage');

        $post = $this->findPost($type, $id);
        $tableName = $this->getPostTableName($type);
        $oldValues = $post->toArray();

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do xóa/gỡ bài viết.',
        ]);

        $reason = $data['reason'];

        // Không xóa cứng DB mà chỉ ẩn đi theo comment "cứ để tạm phần đó là k xóa đi"
        $post->status = 'hidden';
        $post->status_reason = $reason;
        $post->reviewed_by = $request->user()?->id;
        $post->reviewed_at = now();
        $post->save();

        // Gửi thông báo in-app cho tác giả
        $this->sendNotification(
            $post->author_id,
            'post_deleted',
            'Bài viết của bạn đã bị gỡ',
            'Bài viết của bạn đã bị gỡ bởi quản trị viên. Lý do: ' . $reason,
            $tableName,
            $post->id
        );

        // Ghi Audit Log
        $this->audit->log($request, 'moderation', 'post.deleted', $tableName, $post->id, $oldValues, $post->fresh()->toArray(), [
            'reason' => $reason,
            'severity' => 'warning'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gỡ bài viết thành công (chuyển trạng thái ẩn).',
            'data' => $post,
        ]);
    }

    /**
     * Giải quyết báo cáo vi phạm
     */
    public function resolveReport(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'report.resolve');

        $report = Report::query()->findOrFail($id);

        if (in_array($report->status, ['resolved', 'dismissed'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Báo cáo này đã được xử lý từ trước.',
            ], 422);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:resolved,dismissed'],
            'action_taken' => ['required_if:status,resolved', 'nullable', 'string', 'in:warning,content_hidden,content_deleted,account_locked,venue_locked'],
            'action_note' => ['required', 'string', 'max:2000'],
        ], [
            'status.required' => 'Vui lòng chọn trạng thái giải quyết.',
            'action_taken.required_if' => 'Vui lòng chọn hình thức xử lý khi giải quyết báo cáo.',
            'action_note.required' => 'Vui lòng nhập ghi chú hoặc lý do xử lý.',
        ]);

        $oldValues = $report->toArray();

        $actionNote = $data['action_note'];
        $actor = $request->user();

        DB::transaction(function () use ($report, $data, $actionNote, $actor): void {
            $report->status = $data['status'];
            $report->action_taken = $data['status'] === 'resolved' ? $data['action_taken'] : null;
            $report->action_note = $actionNote;
            $report->reviewed_by = $actor?->id;
            $report->reviewed_at = now();
            $report->save();

            if ($data['status'] === 'resolved') {
                $target = $report->reportable;

                if ($target) {
                    $authorId = null;
                    if (method_exists($target, 'author')) {
                        $authorId = $target->author_id;
                    } elseif (isset($target->user_id)) {
                        $authorId = $target->user_id;
                    }

                    // Thực thi các hành động phạt
                    if ($data['action_taken'] === 'content_hidden') {
                        if (Schema::hasColumn($target->getTable(), 'status')) {
                            $target->status = 'hidden';
                            $target->status_reason = 'Nội dung bị ẩn do báo cáo vi phạm: ' . $actionNote;
                            if (Schema::hasColumn($target->getTable(), 'reviewed_by')) {
                                $target->reviewed_by = $actor?->id;
                                $target->reviewed_at = now();
                            }
                            $target->save();
                        }
                        if ($authorId) {
                            $this->sendNotification(
                                $authorId,
                                'report_punishment',
                                'Nội dung bị ẩn do vi phạm',
                                'Nội dung của bạn đã bị ẩn bởi quản trị viên sau khi nhận báo cáo vi phạm. Lý do: ' . $actionNote,
                                $target->getTable(),
                                $target->id
                            );
                        }
                    } elseif ($data['action_taken'] === 'content_deleted') {
                        // Tương tự, chuyển sang ẩn để không xóa hẳn DB theo yêu cầu của user
                        if (Schema::hasColumn($target->getTable(), 'status')) {
                            $target->status = 'hidden';
                            $target->status_reason = 'Nội dung bị gỡ do báo cáo vi phạm: ' . $actionNote;
                            if (Schema::hasColumn($target->getTable(), 'reviewed_by')) {
                                $target->reviewed_by = $actor?->id;
                                $target->reviewed_at = now();
                            }
                            $target->save();
                        }
                        if ($authorId) {
                            $this->sendNotification(
                                $authorId,
                                'report_punishment',
                                'Nội dung đã bị gỡ bỏ',
                                'Nội dung của bạn đã bị gỡ bỏ bởi quản trị viên sau khi nhận báo cáo vi phạm. Lý do: ' . $actionNote,
                                $target->getTable(),
                                $target->id
                            );
                        }
                    } elseif ($data['action_taken'] === 'account_locked') {
                        if ($authorId) {
                            $user = User::query()->find($authorId);
                            if ($user && $user->status !== 'locked') {
                                $user->status = 'locked';
                                $user->locked_at = now();
                                $user->locked_by = $actor?->id;
                                $user->status_reason = 'Tài khoản bị khóa do vi phạm báo cáo: ' . $actionNote;
                                $user->locked_until = now()->addDays(7); // Khóa mặc định 7 ngày
                                $user->save();

                                // Thu hồi token hiện tại
                                $user->tokens()->delete();

                                $this->sendNotification(
                                    $authorId,
                                    'account_locked',
                                    'Tài khoản của bạn đã bị khóa',
                                    'Tài khoản của bạn bị khóa 7 ngày do vi phạm nhiều lần. Lý do: ' . $actionNote
                                );
                            }
                        }
                    }
                }
            }

            // Gửi thông báo phản hồi cho người báo cáo
            $this->sendNotification(
                $report->reporter_id,
                'report_resolved',
                'Kết quả xử lý báo cáo vi phạm',
                $data['status'] === 'resolved'
                    ? 'Báo cáo vi phạm của bạn đã được xử lý. Lý do: ' . $actionNote
                    : 'Báo cáo vi phạm của bạn đã được xem xét và bác bỏ. Lý do: ' . $actionNote,
                'reports',
                $report->id
            );
        });

        // Ghi Audit Log
        $this->audit->log($request, 'report', 'report.resolved', 'reports', $report->id, $oldValues, $report->fresh()->toArray(), [
            'reason' => $actionNote,
            'severity' => 'warning'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xử lý báo cáo vi phạm thành công.',
            'data' => $report->fresh(),
        ]);
    }

    /**
     * Tìm bài viết dựa trên loại (community_posts, venue_posts, system_posts)
     */
    private function findPost(string $type, string $id)
    {
        if (in_array($type, ['venue_post', 'venue_posts'], true)) {
            return VenuePost::query()->findOrFail($id);
        }
        if (in_array($type, ['system_post', 'system_posts'], true)) {
            return SystemPost::query()->findOrFail($id);
        }
        return CommunityPost::query()->findOrFail($id);
    }

    /**
     * Lấy tên bảng dựa trên loại bài viết
     */
    private function getPostTableName(string $type): string
    {
        if (in_array($type, ['venue_post', 'venue_posts'], true)) {
            return 'venue_posts';
        }
        if (in_array($type, ['system_post', 'system_posts'], true)) {
            return 'system_posts';
        }
        return 'community_posts';
    }

    /**
     * Gửi thông báo in-app
     */
    private function sendNotification(
        string $userId,
        string $type,
        string $title,
        string $body,
        ?string $refType = null,
        ?string $refId = null
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Notification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'data' => null,
            'is_read' => false,
        ]);
    }

    /**
     * Phân quyền kiểm duyệt
     */
    private function authorizePermission(Request $request, string|array $permissions): void
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthorizationException('Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        $roles = $user->roles()->pluck('roles.name')->all();

        if (array_intersect($roles, ['super_admin', 'admin'])) {
            return;
        }

        $permissionList = (array) $permissions;
        $hasPermission = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->whereIn('permissions.code', $permissionList)
            ->exists();

        if (! $hasPermission) {
            throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
        }
    }
}
