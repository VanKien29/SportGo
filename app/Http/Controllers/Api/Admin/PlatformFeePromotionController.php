<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeePromotion;
use App\Models\PlatformFeePromotionAssignment;
use App\Models\PlatformFeeServicePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformFeePromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $items = PlatformFeePromotion::query()
            ->with(['assignments.venueCluster:id,name'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(trim((string) ($data['q'] ?? '')), function ($query, string $term): void {
                $query->where(fn ($search) => $search
                    ->where('code', 'like', '%'.$term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%'));
            })
            ->latest()
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $promotion = DB::transaction(function () use ($data, $request): PlatformFeePromotion {
            $promotion = PlatformFeePromotion::query()->create($this->attributes($data, $request->user()?->id));
            $this->syncAssignments($promotion, $data, $request->user()?->id);

            return $promotion->fresh(['assignments.venueCluster:id,name']);
        }, 3);

        return response()->json(['message' => 'Đã tạo ưu đãi ở trạng thái nháp.', 'data' => $promotion], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rules(false, $id));
        $promotion = DB::transaction(function () use ($data, $id, $request): PlatformFeePromotion {
            $promotion = PlatformFeePromotion::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($promotion);
            $promotion->forceFill($this->attributes($data, $promotion->created_by, false))->save();
            $this->syncAssignments($promotion, $data, $request->user()?->id);

            return $promotion->fresh(['assignments.venueCluster:id,name']);
        }, 3);

        return response()->json(['message' => 'Đã lưu ưu đãi nháp.', 'data' => $promotion]);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $promotion = DB::transaction(function () use ($id, $request): PlatformFeePromotion {
            $promotion = PlatformFeePromotion::query()->with('assignments')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($promotion);
            if (! $promotion->applies_to_all_clusters && $promotion->assignments->where('status', 'active')->isEmpty()) {
                throw ValidationException::withMessages(['venue_cluster_ids' => ['Ưu đãi theo cụm sân phải có ít nhất một cụm được chỉ định.']]);
            }
            $promotion->forceFill([
                'status' => 'active',
                'published_by' => $request->user()?->id,
            ])->save();

            return $promotion->fresh(['assignments.venueCluster:id,name']);
        }, 3);

        return response()->json(['message' => 'Đã công bố ưu đãi. Hệ thống chỉ áp dụng cho kỳ mới trong thời gian hiệu lực.', 'data' => $promotion]);
    }

    public function deactivate(int $id): JsonResponse
    {
        $promotion = PlatformFeePromotion::query()->findOrFail($id);
        if ($promotion->status !== 'active') {
            abort(409, 'Chỉ ưu đãi đang công bố mới được ngừng áp dụng.');
        }
        $promotion->forceFill(['status' => 'inactive'])->save();

        return response()->json(['message' => 'Đã ngừng ưu đãi cho các kỳ tạo mới.', 'data' => $promotion->fresh(['assignments.venueCluster:id,name'])]);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::transaction(function () use ($id): void {
            $promotion = PlatformFeePromotion::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($promotion);
            if (PlatformFeeServicePeriod::query()->where('promotion_id', $promotion->id)->exists()) {
                abort(409, 'Ưu đãi đã được kỳ phí tham chiếu nên không thể xóa.');
            }
            $promotion->assignments()->delete();
            $promotion->delete();
        }, 3);

        return response()->json(['message' => 'Đã xóa ưu đãi nháp.']);
    }

    private function rules(bool $creating, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', Rule::unique('platform_fee_promotions', 'code')->ignore($id)],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:1', 'max:999999999999'],
            'duration_cycles' => ['required', 'integer', 'min:1', 'max:36'],
            'applies_to_all_clusters' => ['required', 'boolean'],
            'stackable_with_prepay' => ['required', 'boolean'],
            'applies_to_deferred' => ['required', 'boolean'],
            'applies_to_bridge' => ['required', 'boolean'],
            'budget_amount' => ['nullable', 'numeric', 'min:1', 'max:999999999999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'venue_cluster_ids' => ['sometimes', 'array', 'max:500'],
            'venue_cluster_ids.*' => ['integer', 'distinct', 'exists:venue_clusters,id'],
            'cycles_per_cluster' => ['nullable', 'integer', 'min:1', 'max:36'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function attributes(array $data, ?int $creatorId, bool $includeCreator = true): array
    {
        if ($data['discount_type'] === 'percent' && (float) $data['discount_value'] > 100) {
            throw ValidationException::withMessages(['discount_value' => ['Ưu đãi phần trăm không được vượt quá 100%.']]);
        }
        $attributes = [
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'status' => 'draft',
            'discount_type' => $data['discount_type'],
            'discount_value' => round((float) $data['discount_value'], 2),
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'duration_cycles' => (int) $data['duration_cycles'],
            'applies_to_all_clusters' => (bool) $data['applies_to_all_clusters'],
            'stackable_with_prepay' => (bool) $data['stackable_with_prepay'],
            'applies_to_deferred' => (bool) $data['applies_to_deferred'],
            'applies_to_bridge' => (bool) $data['applies_to_bridge'],
            'budget_amount' => $data['budget_amount'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ];
        if ($includeCreator) {
            $attributes['created_by'] = $creatorId;
        }

        return $attributes;
    }

    private function syncAssignments(PlatformFeePromotion $promotion, array $data, ?int $actorId): void
    {
        $clusterIds = (bool) $data['applies_to_all_clusters'] ? [] : collect($data['venue_cluster_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $cycles = min((int) ($data['cycles_per_cluster'] ?? $data['duration_cycles']), (int) $data['duration_cycles']);
        $promotion->assignments()->whereNotIn('venue_cluster_id', $clusterIds ?: [0])->delete();
        foreach ($clusterIds as $clusterId) {
            PlatformFeePromotionAssignment::query()->updateOrCreate(
                ['promotion_id' => $promotion->id, 'venue_cluster_id' => $clusterId],
                ['initial_cycles' => $cycles, 'remaining_cycles' => $cycles, 'status' => 'active', 'assigned_by' => $actorId, 'assigned_at' => now(), 'consumed_at' => null],
            );
        }
    }

    private function assertDraft(PlatformFeePromotion $promotion): void
    {
        if ($promotion->status !== 'draft') {
            abort(409, 'Ưu đãi đã công bố không được sửa trực tiếp. Hãy tạo ưu đãi mới.');
        }
    }
}
