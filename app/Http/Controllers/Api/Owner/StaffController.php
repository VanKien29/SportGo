<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueStaffAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cluster = $this->ownedCluster($request, $request->query('venue_cluster_id'));

        $staff = VenueStaffAssignment::query()
            ->with(['user.roles:id,name,display_name', 'courtType:id,name'])
            ->where('venue_cluster_id', $cluster->id)
            ->latest()
            ->get()
            ->groupBy('user_id')
            ->map(function ($assignments): array {
                $first = $assignments->first();
                $user = $first->user;

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'roles' => $user->roles->pluck('name')->values()->all(),
                    'assignments' => $assignments->map(fn (VenueStaffAssignment $assignment): array => [
                        'id' => $assignment->id,
                        'scope_type' => $assignment->scope_type,
                        'court_type_id' => $assignment->court_type_id,
                        'court_type_name' => $assignment->courtType?->name,
                        'scope_key' => $assignment->scope_key,
                        'status' => $assignment->status,
                        'created_at' => $assignment->created_at,
                    ])->values()->all(),
                ];
            })
            ->values();

        $courtTypes = DB::table('venue_courts')
            ->join('court_types', 'court_types.id', '=', 'venue_courts.court_type_id')
            ->where('venue_courts.venue_cluster_id', $cluster->id)
            ->select('court_types.id', 'court_types.name')
            ->distinct()
            ->orderBy('court_types.name')
            ->get();

        return response()->json([
            'data' => $staff,
            'meta' => [
                'venue_cluster' => $cluster,
                'court_types' => $courtTypes,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'scope_type' => ['required', Rule::in(['all_cluster', 'court_type'])],
            'court_type_ids' => ['array'],
            'court_type_ids.*' => ['integer', 'exists:court_types,id'],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $this->validateScope($cluster->id, $data['scope_type'], $data['court_type_ids'] ?? []);

        $role = Role::query()->where('name', 'venue_staff')->first();
        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Chưa có role venue_staff để tạo nhân viên sân.',
            ]);
        }

        $user = DB::transaction(function () use ($request, $data, $cluster, $role): User {
            $user = User::query()->create([
                'username' => $data['username'],
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'verification_channel' => $data['email'] ? 'email' : null,
                'email_verified_at' => $data['email'] ? now() : null,
            ]);

            UserRole::query()->firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => 'venue',
                'scope_id' => $cluster->id,
            ], [
                'granted_by' => $request->user()->id,
            ]);

            $this->syncAssignments($request, $user, $cluster->id, $data['scope_type'], $data['court_type_ids'] ?? []);

            return $user;
        });

        $this->audit($request, 'owner.staff.created', 'users', $user->id, [], [
            'venue_cluster_id' => $cluster->id,
            'username' => $user->username,
            'scope_type' => $data['scope_type'],
            'court_type_ids' => $data['court_type_ids'] ?? [],
        ]);

        return response()->json([
            'message' => 'Đã tạo nhân viên sân.',
            'data' => $user->fresh('roles'),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'status' => ['required', Rule::in(['active', 'locked', 'deactivated'])],
            'scope_type' => ['required', Rule::in(['all_cluster', 'court_type'])],
            'court_type_ids' => ['array'],
            'court_type_ids.*' => ['integer', 'exists:court_types,id'],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $staff = $this->staffInCluster($id, $cluster->id);
        $this->validateScope($cluster->id, $data['scope_type'], $data['court_type_ids'] ?? []);
        $old = $staff->only(['full_name', 'phone', 'email', 'status']);

        DB::transaction(function () use ($request, $staff, $cluster, $data): void {
            $staff->update([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => $data['status'],
            ]);

            VenueStaffAssignment::query()
                ->where('user_id', $staff->id)
                ->where('venue_cluster_id', $cluster->id)
                ->update(['status' => 'inactive']);

            $this->syncAssignments($request, $staff, $cluster->id, $data['scope_type'], $data['court_type_ids'] ?? []);
        });

        $this->audit($request, 'owner.staff.updated', 'users', $staff->id, $old, [
            ...$staff->fresh()->only(['full_name', 'phone', 'email', 'status']),
            'venue_cluster_id' => $cluster->id,
            'scope_type' => $data['scope_type'],
            'court_type_ids' => $data['court_type_ids'] ?? [],
        ]);

        return response()->json([
            'message' => 'Đã cập nhật nhân viên sân.',
            'data' => $staff->fresh('roles'),
        ]);
    }

    public function deactivate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $staff = $this->staffInCluster($id, $cluster->id);
        $old = ['status' => $staff->status];

        $staff->update([
            'status' => 'deactivated',
            'status_reason' => $data['reason'] ?? 'Chủ sân tạm ngưng tài khoản nhân viên.',
        ]);
        VenueStaffAssignment::query()
            ->where('user_id', $staff->id)
            ->where('venue_cluster_id', $cluster->id)
            ->update(['status' => 'inactive']);

        $this->audit($request, 'owner.staff.deactivated', 'users', $staff->id, $old, [
            'status' => 'deactivated',
            'venue_cluster_id' => $cluster->id,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['message' => 'Đã tạm ngưng nhân viên sân.']);
    }

    private function ownedCluster(Request $request, ?string $clusterId): VenueCluster
    {
        if (! $clusterId) {
            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Vui lòng chọn cụm sân.',
            ]);
        }

        return VenueCluster::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($clusterId);
    }

    private function staffInCluster(string $userId, string $clusterId): User
    {
        $exists = VenueStaffAssignment::query()
            ->where('user_id', $userId)
            ->where('venue_cluster_id', $clusterId)
            ->exists();

        if (! $exists) {
            abort(404, 'Không tìm thấy nhân viên trong cụm sân này.');
        }

        return User::query()->findOrFail($userId);
    }

    private function validateScope(string $clusterId, string $scopeType, array $courtTypeIds): void
    {
        if ($scopeType === 'all_cluster') {
            return;
        }

        if ($courtTypeIds === []) {
            throw ValidationException::withMessages([
                'court_type_ids' => 'Vui lòng chọn ít nhất một loại sân.',
            ]);
        }

        $validIds = DB::table('venue_courts')
            ->where('venue_cluster_id', $clusterId)
            ->whereIn('court_type_id', $courtTypeIds)
            ->distinct()
            ->pluck('court_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count(array_diff(array_map('intval', $courtTypeIds), $validIds)) > 0) {
            throw ValidationException::withMessages([
                'court_type_ids' => 'Loại sân được chọn không thuộc cụm sân này.',
            ]);
        }
    }

    private function syncAssignments(Request $request, User $staff, string $clusterId, string $scopeType, array $courtTypeIds): void
    {
        if ($scopeType === 'all_cluster') {
            VenueStaffAssignment::query()->updateOrCreate([
                'user_id' => $staff->id,
                'venue_cluster_id' => $clusterId,
                'scope_key' => 'all',
            ], [
                'scope_type' => 'all_cluster',
                'court_type_id' => null,
                'assigned_by' => $request->user()->id,
                'status' => 'active',
            ]);

            return;
        }

        foreach ($courtTypeIds as $courtTypeId) {
            VenueStaffAssignment::query()->updateOrCreate([
                'user_id' => $staff->id,
                'venue_cluster_id' => $clusterId,
                'scope_key' => 'court_type:' . $courtTypeId,
            ], [
                'scope_type' => 'court_type',
                'court_type_id' => $courtTypeId,
                'assigned_by' => $request->user()->id,
                'status' => 'active',
            ]);
        }
    }

    private function audit(Request $request, string $action, string $entityType, string $entityId, array $oldValues, array $newValues): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'actor_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'context' => 'owner',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
