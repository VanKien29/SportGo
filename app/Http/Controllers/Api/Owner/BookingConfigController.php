<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BookingConfig;
use App\Models\VenueCluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $clusters = VenueCluster::query()
            ->with('bookingConfig')
            ->whereIn('id', $clusterIds)
            ->orderBy('name')
            ->get()
            ->map(fn (VenueCluster $cluster): array => [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'status' => $cluster->status,
                'booking_config' => $this->configPayload($cluster->bookingConfig, $cluster->id),
            ]);

        return response()->json(['data' => $clusters]);
    }

    public function update(Request $request, string $venueClusterId): JsonResponse
    {
        $this->ensureClusterAccess($request, $venueClusterId);

        $validated = $request->validate([
            'min_duration_minutes' => ['required', 'integer', 'min:30', 'max:120', 'multiple_of:30'],
            'max_duration_minutes' => ['nullable', 'integer', 'gte:min_duration_minutes', 'max:1440', 'multiple_of:30'],
            'min_advance_booking_minutes' => ['required', 'integer', 'min:30'],
            'fixed_open_time' => ['required', 'date_format:H:i'],
            'fixed_close_time' => ['required', 'regex:/^(?:([01]\d|2[0-3]):[0-5]\d|24:00)$/'],
            'special_operating_hours' => ['present', 'array', 'max:30'],
            'special_operating_hours.*.start_date' => ['required', 'date_format:Y-m-d'],
            'special_operating_hours.*.end_date' => ['required', 'date_format:Y-m-d'],
            'special_operating_hours.*.open_time' => ['required', 'date_format:H:i'],
            'special_operating_hours.*.close_time' => ['required', 'regex:/^(?:([01]\d|2[0-3]):[0-5]\d|24:00)$/'],
            'slot_hold_minutes' => ['required', 'integer', 'min:5', 'max:120', 'multiple_of:5'],
            'reminder_before_minutes' => ['required', 'integer', 'min:0', 'max:10080', 'multiple_of:5'],
            'allow_full_payment' => ['required', 'boolean'],
            'allow_deposit' => ['required', 'boolean'],
            'allow_no_prepay' => ['required', 'boolean'],
            'deposit_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ], [
            'min_advance_booking_minutes.min' => 'Thời gian đặt trước tối thiểu là 30 phút.',
            'special_operating_hours.max' => 'Chỉ được tạo tối đa 30 khoảng ngày tùy chỉnh.',
        ]);

        $this->validateOperatingHours($validated);

        if (
            ! $validated['allow_full_payment']
            && ! $validated['allow_deposit']
            && ! $validated['allow_no_prepay']
        ) {
            throw ValidationException::withMessages([
                'payment_methods' => 'Phải bật ít nhất một hình thức thanh toán.',
            ]);
        }

        if ($validated['allow_deposit'] && empty($validated['deposit_percent'])) {
            throw ValidationException::withMessages([
                'deposit_percent' => 'Vui lòng nhập phần trăm cọc khi bật hình thức đặt cọc.',
            ]);
        }

        $existing = BookingConfig::query()->where('venue_cluster_id', $venueClusterId)->first();
        $oldValues = $existing?->toArray() ?? [];

        $config = BookingConfig::query()->updateOrCreate(
            ['venue_cluster_id' => $venueClusterId],
            [
                ...$validated,
                'deposit_percent' => $validated['allow_deposit']
                    ? $validated['deposit_percent']
                    : null,
            ]
        );

        $this->audit($request, $venueClusterId, $oldValues, $config->fresh()->toArray());

        return response()->json([
            'message' => 'Đã lưu cấu hình đặt sân.',
            'data' => $config,
        ]);
    }

    private function configPayload(?BookingConfig $config, string $clusterId): array
    {
        return [
            'venue_cluster_id' => $clusterId,
            'min_duration_minutes' => $config?->min_duration_minutes ?? 30,
            'max_duration_minutes' => $config?->max_duration_minutes,
            'min_advance_booking_minutes' => $config?->min_advance_booking_minutes ?? 30,
            'fixed_open_time' => substr($config?->fixed_open_time ?? $this->legacyFixedHours($config)['open_time'], 0, 5),
            'fixed_close_time' => substr($config?->fixed_close_time ?? $this->legacyFixedHours($config)['close_time'], 0, 5),
            'special_operating_hours' => $config?->special_operating_hours ?? [],
            'slot_hold_minutes' => $config?->slot_hold_minutes ?? 20,
            'reminder_before_minutes' => $config?->reminder_before_minutes ?? 30,
            'allow_full_payment' => $config?->allow_full_payment ?? true,
            'allow_deposit' => $config?->allow_deposit ?? true,
            'allow_no_prepay' => $config?->allow_no_prepay ?? true,
            'deposit_percent' => $config?->deposit_percent ?? 30,
        ];
    }

    private function validateOperatingHours(array $validated): void
    {
        $errors = [];

        if (! $this->hasValidOperatingDuration($validated['fixed_open_time'], $validated['fixed_close_time'])) {
            $errors['fixed_close_time'] = 'Giờ mở cửa đến giờ đóng cửa phải từ 2 giờ đến 24 giờ.';
        }

        $specialHours = collect($validated['special_operating_hours'])
            ->sortBy('start_date')
            ->values();

        foreach ($specialHours as $index => $hours) {
            if ($hours['end_date'] < $hours['start_date']) {
                $errors["special_operating_hours.{$index}.end_date"] = 'Ngày kết thúc phải từ ngày bắt đầu trở đi.';
            }

            if (! $this->hasValidOperatingDuration($hours['open_time'], $hours['close_time'])) {
                $errors["special_operating_hours.{$index}.close_time"] = 'Khoảng giờ tùy chỉnh phải từ 2 giờ đến 24 giờ.';
            }

            if ($index > 0 && $hours['start_date'] <= $specialHours[$index - 1]['end_date']) {
                $errors["special_operating_hours.{$index}.start_date"] = 'Các khoảng ngày tùy chỉnh không được chồng lấn.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function hasValidOperatingDuration(string $openTime, string $closeTime): bool
    {
        $duration = $this->timeToMinutes($closeTime) - $this->timeToMinutes($openTime);

        return $duration >= 120 && $duration <= 1440;
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private function legacyFixedHours(?BookingConfig $config): array
    {
        $legacyHours = collect($config?->weekly_operating_hours ?? [])
            ->first(fn (array $hours): bool => (bool) ($hours['is_open'] ?? false));

        return [
            'open_time' => $legacyHours['open_time'] ?? '08:00',
            'close_time' => $legacyHours['close_time'] ?? '22:00',
        ];
    }

    private function ensureClusterAccess(Request $request, string $clusterId): void
    {
        abort_unless($this->visibleClusterIds($request->user()->id)->contains($clusterId), 403);
    }

    private function visibleClusterIds(string $userId): Collection
    {
        $owned = DB::table('venue_clusters')->where('owner_id', $userId)->pluck('id');
        $assigned = DB::table('venue_staff_assignments')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('venue_cluster_id');

        return $owned->merge($assigned)->unique()->values();
    }

    private function audit(Request $request, string $clusterId, array $oldValues, array $newValues): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'actor_type' => 'owner',
            'module' => 'booking',
            'action' => 'booking_config.updated',
            'entity_type' => BookingConfig::class,
            'entity_id' => $clusterId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues,
            'context' => 'owner',
            'metadata' => ['venue_cluster_id' => $clusterId],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
