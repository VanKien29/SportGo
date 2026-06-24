<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BannerController extends Controller
{
    private const POSITIONS = [
        'home',
        'homepage_top',
        'homepage_middle',
        'homepage_bottom',
        'category_page',
        'venue_detail',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Banner::query()->with([
            'createdBy:id,full_name,username,email',
            'updatedBy:id,full_name,username,email',
        ]);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', $search)
                    ->orWhere('link_url', 'like', $search);
            });
        }

        if ($request->filled('position')) {
            $query->where('position', $request->input('position'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $banners = $query
            ->orderBy('position')
            ->orderBy('sort_order')
            ->latest()
            ->paginate($perPage)
            ->through(fn (Banner $banner) => $this->payload($banner));

        return response()->json([
            'status' => 'success',
            'data' => $banners,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(requireImage: true), $this->messages());

        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        if ($startsAt && $endsAt && strtotime($startsAt) > strtotime($endsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
            ]);
        }

        $imagePath = $request->file('image')->store('banners/' . now()->format('Y/m'), 'public');
        $actorId = $request->user()?->id;

        $position = $data['position'];
        $sortOrder = $data['sort_order'] ?? ((Banner::query()->where('position', $position)->max('sort_order') ?? 0) + 1);

        $this->shiftSortOrders($position, $sortOrder);

        $banner = Banner::create([
            'title' => $data['title'],
            'image_path' => $imagePath,
            'link_url' => $data['link_url'] ?? null,
            'position' => $position,
            'sort_order' => $sortOrder,
            'is_active' => $request->boolean('is_active', true),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo banner thành công.',
            'data' => $this->payload($banner->fresh(['createdBy', 'updatedBy'])),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $banner = Banner::query()->findOrFail($id);
        $data = $request->validate($this->rules(requireImage: false), $this->messages());

        $startsAt = array_key_exists('starts_at', $data) ? $data['starts_at'] : $banner->starts_at;
        $endsAt = array_key_exists('ends_at', $data) ? $data['ends_at'] : $banner->ends_at;

        if ($startsAt && $endsAt && strtotime($startsAt) > strtotime($endsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
            ]);
        }

        if ($request->hasFile('image')) {
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }

            $banner->image_path = $request->file('image')->store('banners/' . now()->format('Y/m'), 'public');
        }

        $oldPosition = $banner->position;
        $oldOrder = $banner->sort_order;
        $newPosition = $data['position'] ?? $oldPosition;
        $newOrder = $data['sort_order'] ?? $oldOrder;

        if ($oldPosition !== $newPosition || $oldOrder !== $newOrder) {
            $this->shiftSortOrders($newPosition, $newOrder, $oldOrder, $oldPosition);
        }

        $banner->fill([
            'title' => $data['title'] ?? $banner->title,
            'link_url' => array_key_exists('link_url', $data) ? $data['link_url'] : $banner->link_url,
            'position' => $newPosition,
            'sort_order' => $newOrder,
            'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $banner->starts_at,
            'ends_at' => array_key_exists('ends_at', $data) ? $data['ends_at'] : $banner->ends_at,
        ]);

        if ($request->has('is_active')) {
            $banner->is_active = $request->boolean('is_active');
        }

        $banner->updated_by = $request->user()?->id;
        $banner->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật banner thành công.',
            'data' => $this->payload($banner->fresh(['createdBy', 'updatedBy'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $banner = Banner::query()->findOrFail($id);

        $position = $banner->position;
        $sortOrder = $banner->sort_order;

        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();

        Banner::query()
            ->where('position', $position)
            ->where('sort_order', '>', $sortOrder)
            ->decrement('sort_order');

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa banner thành công.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'banner_ids' => ['required', 'array'],
            'banner_ids.*' => ['required', 'string', 'exists:banners,id'],
        ]);

        foreach ($data['banner_ids'] as $index => $bannerId) {
            Banner::query()
                ->whereKey($bannerId)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_by' => $request->user()?->id,
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sắp xếp banner thành công.',
        ]);
    }

    public function getActiveBanners(?string $position = null): JsonResponse
    {
        $query = Banner::query()
            ->where('is_active', true)
            ->where(function ($builder): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($builder): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });

        if ($position) {
            $query->where('position', $position);
        }

        $banners = $query
            ->orderBy('position')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Banner $banner) => $this->payload($banner))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $banners,
        ]);
    }

    private function rules(bool $requireImage): array
    {
        $titleRule = $requireImage ? ['required', 'string', 'max:255'] : ['sometimes', 'required', 'string', 'max:255'];
        $positionRule = $requireImage ? ['required', Rule::in(self::POSITIONS)] : ['sometimes', 'required', Rule::in(self::POSITIONS)];

        return [
            'title' => $titleRule,
            'image' => [$requireImage ? 'required' : 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'link_url' => ['nullable', 'url:http,https', 'max:1000'],
            'position' => $positionRule,
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    private function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề banner.',
            'image.required' => 'Vui lòng chọn ảnh banner.',
            'image.image' => 'File tải lên phải là ảnh.',
            'image.max' => 'Ảnh banner không được vượt quá 5MB.',
            'link_url.url' => 'Liên kết banner phải là URL hợp lệ.',
            'position.required' => 'Vui lòng chọn vị trí hiển thị.',
            'position.in' => 'Vị trí hiển thị không hợp lệ.',
            'ends_at.after_or_equal' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
        ];
    }

    private function payload(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'image_path' => $banner->image_path,
            'image_url' => $banner->image_path ? Storage::disk('public')->url($banner->image_path) : null,
            'link_url' => $banner->link_url,
            'position' => $banner->position,
            'sort_order' => $banner->sort_order,
            'is_active' => (bool) $banner->is_active,
            'starts_at' => $banner->starts_at,
            'ends_at' => $banner->ends_at,
            'created_by' => $banner->createdBy ? [
                'id' => $banner->createdBy->id,
                'full_name' => $banner->createdBy->full_name,
                'username' => $banner->createdBy->username,
            ] : null,
            'updated_by' => $banner->updatedBy ? [
                'id' => $banner->updatedBy->id,
                'full_name' => $banner->updatedBy->full_name,
                'username' => $banner->updatedBy->username,
            ] : null,
            'created_at' => $banner->created_at,
            'updated_at' => $banner->updated_at,
        ];
    }

    private function shiftSortOrders(string $position, int $newOrder, ?int $oldOrder = null, ?string $oldPosition = null): void
    {
        if ($oldPosition && $oldPosition !== $position) {
            Banner::query()
                ->where('position', $oldPosition)
                ->where('sort_order', '>', $oldOrder)
                ->decrement('sort_order');
            $oldOrder = null;
        }

        if ($oldOrder === null) {
            Banner::query()
                ->where('position', $position)
                ->where('sort_order', '>=', $newOrder)
                ->increment('sort_order');
        } else {
            if ($oldOrder > $newOrder) {
                Banner::query()
                    ->where('position', $position)
                    ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                    ->increment('sort_order');
            } elseif ($oldOrder < $newOrder) {
                Banner::query()
                    ->where('position', $position)
                    ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                    ->decrement('sort_order');
            }
        }
    }
}
