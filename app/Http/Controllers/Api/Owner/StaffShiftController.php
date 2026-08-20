<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueStaffAssignment;
use App\Models\VenueStaffShift;
use App\Models\VenueStaffShiftSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffShiftController extends Controller
{
    // ==========================================
    // OWNER METHODS (CRUD CA TRỰC MẪU)
    // ==========================================

    public function listShifts(Request $request): JsonResponse
    {
        $cluster = $this->ownedCluster($request, $request->query('venue_cluster_id'));

        if (! $this->staffShiftTablesReady()) {
            return response()->json(['data' => []]);
        }

        $shifts = VenueStaffShift::query()
            ->where('venue_cluster_id', $cluster->id)
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'data' => $shifts,
        ]);
    }

    public function storeShift(Request $request): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $data = $request->validate([
            'venue_cluster_id' => ['required', 'exists:venue_clusters,id'],
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i', 'before:end_time'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);

        $shift = VenueStaffShift::query()->create([
            'venue_cluster_id' => $cluster->id,
            'name' => $data['name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit($request, 'owner.staff_shift.created', 'venue_staff_shifts', $shift->id, [], $shift->toArray());

        return response()->json([
            'message' => 'Đã tạo ca trực mẫu.',
            'data' => $shift,
        ], 201);
    }

    public function updateShift(Request $request, $id): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $shift = VenueStaffShift::query()->findOrFail($id);
        $cluster = $this->ownedCluster($request, $shift->venue_cluster_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i', 'before:end_time'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);

        $old = $shift->toArray();
        $shift->update($data);

        $this->audit($request, 'owner.staff_shift.updated', 'venue_staff_shifts', $shift->id, $old, $shift->toArray());

        return response()->json([
            'message' => 'Đã cập nhật ca trực mẫu.',
            'data' => $shift,
        ]);
    }

    public function destroyShift(Request $request, $id): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $shift = VenueStaffShift::query()->findOrFail($id);
        $this->ownedCluster($request, $shift->venue_cluster_id);

        // Check if there are schedules using this template shift
        $inUse = VenueStaffShiftSchedule::query()
            ->where('venue_staff_shift_id', $shift->id)
            ->exists();

        if ($inUse) {
            // Soft delete style: deactivate it
            $shift->update(['is_active' => false]);

            return response()->json([
                'message' => 'Ca trực đang được sử dụng trong lịch biểu, đã tự động chuyển sang trạng thái ngưng hoạt động.',
                'data' => $shift,
            ]);
        }

        $old = $shift->toArray();
        $shift->delete();

        $this->audit($request, 'owner.staff_shift.deleted', 'venue_staff_shifts', $id, $old, []);

        return response()->json([
            'message' => 'Đã xóa ca trực mẫu.',
        ]);
    }

    // ==========================================
    // OWNER METHODS (CRUD PHÂN LỊCH TRỰC)
    // ==========================================

    public function listSchedules(Request $request): JsonResponse
    {
        $cluster = $this->ownedCluster($request, $request->query('venue_cluster_id'));

        if (! $this->staffShiftTablesReady()) {
            return response()->json(['data' => []]);
        }
        $startDate = $request->query('start_date', Carbon::today()->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->toDateString());
        $userId = $request->query('user_id');

        $schedules = VenueStaffShiftSchedule::query()
            ->with(['user:id,full_name,username,email,phone', 'shift'])
            ->where('venue_cluster_id', $cluster->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'data' => $schedules,
        ]);
    }

    public function storeSchedules(Request $request): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $data = $request->validate([
            'venue_cluster_id' => ['required', 'exists:venue_clusters,id'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['required', 'exists:users,id'],
            'dates' => ['required', 'array'],
            'dates.*' => ['required', 'date_format:Y-m-d'],
            'venue_staff_shift_id' => ['nullable', 'integer', 'exists:venue_staff_shifts,id'],
            'start_time' => ['required_without:venue_staff_shift_id', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_without:venue_staff_shift_id', 'nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);

        // Verify if selected users are actually staff assigned to this cluster
        $assignedStaffIds = VenueStaffAssignment::query()
            ->where('venue_cluster_id', $cluster->id)
            ->pluck('user_id')
            ->toArray();

        $invalidUsers = array_diff($data['user_ids'], $assignedStaffIds);
        if (! empty($invalidUsers)) {
            throw ValidationException::withMessages([
                'user_ids' => 'Một số nhân viên được chọn không thuộc cụm sân này.',
            ]);
        }

        // Determine times
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        if ($data['venue_staff_shift_id']) {
            $templateShift = VenueStaffShift::query()->findOrFail($data['venue_staff_shift_id']);
            $startTime = Carbon::parse($templateShift->start_time)->format('H:i');
            $endTime = Carbon::parse($templateShift->end_time)->format('H:i');
        }

        $created = [];
        DB::transaction(function () use ($request, $data, $cluster, $startTime, $endTime, &$created) {
            foreach ($data['user_ids'] as $userId) {
                foreach ($data['dates'] as $date) {
                    // Avoid duplicate shift scheduling for same user, date, start_time
                    $exists = VenueStaffShiftSchedule::query()
                        ->where('user_id', $userId)
                        ->where('date', $date)
                        ->where('start_time', $startTime)
                        ->exists();

                    if (! $exists) {
                        $schedule = VenueStaffShiftSchedule::query()->create([
                            'venue_cluster_id' => $cluster->id,
                            'user_id' => $userId,
                            'venue_staff_shift_id' => $data['venue_staff_shift_id'] ?: null,
                            'date' => $date,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'status' => 'scheduled',
                            'notes' => $data['notes'] ?? null,
                            'created_by' => $request->user()->id,
                        ]);
                        $created[] = $schedule;
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Đã phân công lịch trực cho nhân viên.',
            'count' => count($created),
        ], 201);
    }

    public function updateSchedule(Request $request, $id): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $schedule = VenueStaffShiftSchedule::query()->findOrFail($id);
        $this->ownedCluster($request, $schedule->venue_cluster_id);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'status' => ['required', Rule::in(['scheduled', 'checked_in', 'checked_out', 'absent', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $old = $schedule->toArray();
        $schedule->update($data);

        $this->audit($request, 'owner.staff_schedule.updated', 'venue_staff_shift_schedules', $schedule->id, $old, $schedule->toArray());

        return response()->json([
            'message' => 'Đã cập nhật lịch trực.',
            'data' => $schedule,
        ]);
    }

    public function destroySchedule(Request $request, $id): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $schedule = VenueStaffShiftSchedule::query()->findOrFail($id);
        $this->ownedCluster($request, $schedule->venue_cluster_id);

        $old = $schedule->toArray();
        $schedule->delete();

        $this->audit($request, 'owner.staff_schedule.deleted', 'venue_staff_shift_schedules', $id, $old, []);

        return response()->json([
            'message' => 'Đã xóa lịch trực.',
        ]);
    }

    public function attendanceReport(Request $request): JsonResponse
    {
        $cluster = $this->ownedCluster($request, $request->query('venue_cluster_id'));

        if (! $this->staffShiftTablesReady()) {
            return response()->json(['data' => []]);
        }
        $startDate = $request->query('start_date', Carbon::today()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->endOfMonth()->toDateString());

        $schedules = VenueStaffShiftSchedule::query()
            ->with('user:id,full_name,username')
            ->where('venue_cluster_id', $cluster->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $report = $schedules->groupBy('user_id')->map(function ($userSchedules) {
            $user = $userSchedules->first()->user;
            $totalShifts = $userSchedules->count();
            $checkedIn = $userSchedules->whereIn('status', ['checked_in', 'checked_out'])->count();
            $absent = $userSchedules->where('status', 'absent')->count();
            $cancelled = $userSchedules->where('status', 'cancelled')->count();

            // Calculate late shifts
            $lateCount = 0;
            foreach ($userSchedules as $sch) {
                if ($sch->check_in_at) {
                    $schTime = Carbon::parse($sch->date)->setTimeFromTimeString($sch->start_time);
                    if ($sch->check_in_at->gt($schTime->addMinutes(10))) { // trễ hơn 10 phút
                        $lateCount++;
                    }
                }
            }

            return [
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'total_shifts' => $totalShifts,
                'checked_in' => $checkedIn,
                'absent' => $absent,
                'cancelled' => $cancelled,
                'late' => $lateCount,
            ];
        })->values();

        return response()->json([
            'data' => $report,
        ]);
    }

    // ==========================================
    // STAFF METHODS (NHÂN VIÊN CHẤM CÔNG)
    // ==========================================

    public function mySchedules(Request $request): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return response()->json(['data' => []]);
        }

        $userId = $request->user()->id;
        $startDate = $request->query('start_date', Carbon::today()->startOfWeek()->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->endOfWeek()->toDateString());

        $schedules = VenueStaffShiftSchedule::query()
            ->with(['venueCluster:id,name', 'shift'])
            ->where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'data' => $schedules,
        ]);
    }

    public function checkIn(Request $request, $id): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $userId = $request->user()->id;
        $schedule = VenueStaffShiftSchedule::query()
            ->where('user_id', $userId)
            ->findOrFail($id);

        if ($schedule->status !== 'scheduled') {
            return response()->json([
                'message' => 'Lịch trực này đã được chấm công hoặc đã bị hủy.',
            ], 422);
        }

        $scheduleDate = Carbon::parse($schedule->date);
        if (! $scheduleDate->isToday()) {
            return response()->json([
                'message' => 'Bạn chỉ có thể check-in vào đúng ngày của ca trực.',
            ], 422);
        }

        // Limit check-in to maximum 30 minutes before shift starts
        $shiftStart = Carbon::parse($schedule->date)->setTimeFromTimeString($schedule->start_time);
        if (now()->lt($shiftStart->copy()->subMinutes(30))) {
            return response()->json([
                'message' => 'Chỉ có thể check-in trước giờ bắt đầu tối đa 30 phút.',
            ], 422);
        }

        $schedule->update([
            'status' => 'checked_in',
            'check_in_at' => now(),
        ]);

        $this->audit($request, 'staff.attendance.check_in', 'venue_staff_shift_schedules', $schedule->id, [], $schedule->toArray());

        return response()->json([
            'message' => 'Check-in thành công!',
            'data' => $schedule,
        ]);
    }

    public function handoverSummary(Request $request, $id): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $userId = $request->user()->id;
        $schedule = VenueStaffShiftSchedule::query()
            ->with(['venueCluster:id,name', 'shift', 'user:id,full_name,username'])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $checkInAt = $schedule->check_in_at ? Carbon::parse($schedule->check_in_at) : Carbon::parse($schedule->date)->setTimeFromTimeString($schedule->start_time);
        $checkOutAt = $schedule->check_out_at ? Carbon::parse($schedule->check_out_at) : now();

        // Query bookings for this venue cluster on this shift date
        $bookings = Booking::query()
            ->where('venue_cluster_id', $schedule->venue_cluster_id)
            ->where('booking_date', $schedule->date)
            ->with(['payments'])
            ->get();

        $totalBookings = $bookings->count();
        $confirmedBookings = $bookings->whereIn('status', ['confirmed', 'checked_in', 'completed'])->count();

        $totalCash = 0;
        $totalTransfer = 0;
        $totalUnpaid = 0;

        foreach ($bookings as $b) {
            $paidPayments = $b->payments->where('status', 'paid');
            $cashPaid = (float) $paidPayments->where('method', 'cash')->sum('amount');
            $transferPaid = (float) $paidPayments->whereIn('method', ['sepay', 'vnpay', 'momo', 'bank_transfer', 'qr', 'transfer'])->sum('amount');
            $totalPaid = (float) $paidPayments->sum('amount');

            $totalCash += $cashPaid;
            $totalTransfer += $transferPaid;

            if (in_array($b->status, ['confirmed', 'checked_in', 'completed', 'pending_payment'])) {
                $finalAmount = (float) ($b->final_amount ?: $b->total_price);
                $outstanding = max(0, $finalAmount - $totalPaid);
                $totalUnpaid += $outstanding;
            }
        }

        $diffMinutes = max(0, $checkInAt->diffInMinutes($checkOutAt));
        $hours = floor($diffMinutes / 60);
        $mins = $diffMinutes % 60;
        $workedDurationLabel = "{$hours} giờ {$mins} phút";

        return response()->json([
            'data' => [
                'schedule_id' => $schedule->id,
                'staff_name' => $schedule->user?->full_name ?: $schedule->user?->username,
                'cluster_name' => $schedule->venueCluster?->name,
                'shift_name' => $schedule->shift?->name ?: 'Ca đặc biệt',
                'date' => $schedule->date,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
                'check_in_at' => $schedule->check_in_at ? Carbon::parse($schedule->check_in_at)->format('H:i:s d/m/Y') : null,
                'check_out_at' => $schedule->check_out_at ? Carbon::parse($schedule->check_out_at)->format('H:i:s d/m/Y') : now()->format('H:i:s d/m/Y'),
                'worked_duration_label' => $workedDurationLabel,
                'total_bookings' => $totalBookings,
                'confirmed_bookings' => $confirmedBookings,
                'total_cash_amount' => $totalCash,
                'total_transfer_amount' => $totalTransfer,
                'total_revenue' => $totalCash + $totalTransfer,
                'total_unpaid_amount' => $totalUnpaid,
                'notes' => $schedule->notes,
                'status' => $schedule->status,
            ],
        ]);
    }

    public function checkOut(Request $request, $id): JsonResponse
    {
        if (! $this->staffShiftTablesReady()) {
            return $this->featurePendingResponse();
        }

        $userId = $request->user()->id;
        $schedule = VenueStaffShiftSchedule::query()
            ->where('user_id', $userId)
            ->findOrFail($id);

        if ($schedule->status !== 'checked_in') {
            return response()->json([
                'message' => 'Lịch trực chưa được check-in hoặc đã hoàn thành.',
            ], 422);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $schedule->update([
            'status' => 'checked_out',
            'check_out_at' => now(),
            'notes' => $data['notes'] ?? $schedule->notes,
        ]);

        $this->audit($request, 'staff.attendance.check_out', 'venue_staff_shift_schedules', $schedule->id, [], $schedule->toArray());

        return response()->json([
            'message' => 'Check-out hoàn thành ca trực thành công!',
            'data' => $schedule,
        ]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function staffShiftTablesReady(): bool
    {
        return Schema::hasTable('venue_staff_shifts')
            && Schema::hasTable('venue_staff_shift_schedules');
    }

    private function featurePendingResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Tính năng ca làm việc đang chờ hoàn tất cập nhật cơ sở dữ liệu.',
        ], 409);
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
