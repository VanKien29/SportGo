<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\HolidayPrice;
use App\Models\PriceSlot;
use App\Models\SystemPolicy;
use App\Models\VenueBasePrice;
use App\Models\VenueCluster;
use App\Models\VenuePolicyRule;
use App\Services\BookingService;
use App\Services\Memberships\VenueMembershipService;
use App\Services\Policies\RefundCancellationPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VenueController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly RefundCancellationPolicyService $refundPolicies,
        private readonly VenueMembershipService $venueMemberships,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'court_type_id' => ['nullable', 'string', 'max:50'],
            'area' => ['nullable', 'string', 'max:100'],
            'province_code' => ['nullable', 'string', 'max:50'],
            'province_name' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'ward_code' => ['nullable', 'string', 'max:50'],
            'ward_name' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'amenity_id' => ['nullable', 'integer', 'exists:amenities,id'],
            'min_courts' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'has_services' => ['nullable', 'boolean'],
            'has_map' => ['nullable', 'boolean'],
            'payment_option' => ['nullable', 'in:full_payment,deposit,no_prepay,wallet'],
            'sort' => ['nullable', 'in:recommended,name,price,courts,rating,distance'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['nullable', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
        ]);

        if (isset($validated['min_price'], $validated['max_price'])
            && (float) $validated['max_price'] < (float) $validated['min_price']) {
            throw ValidationException::withMessages([
                'max_price' => 'Giá tối đa phải lớn hơn hoặc bằng giá tối thiểu.',
            ]);
        }

        if (! empty($validated['start_time']) && ! empty($validated['end_time'])
            && $this->timeToMinutes($validated['end_time']) <= $this->timeToMinutes($validated['start_time'])) {
            throw ValidationException::withMessages([
                'end_time' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
            ]);
        }

        $query = VenueCluster::query()
            ->with(['venueCourts' => function ($query) {
                $query->with([
                    'courtType:id,name,parent_id,icon_key',
                    'courtType.parent:id,name,parent_id,icon_key',
                ])
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }, 'amenityCatalog' => function ($query) {
                $query->where('amenities.status', 'active')
                    ->wherePivot('is_visible', true)
                    ->orderBy('amenities.name');
            }, 'services' => function ($query) {
                $query->where('status', 'active');
            }, 'bookingConfig'])
            ->where('status', 'active');

        if (! empty($validated['q'])) {
            $keyword = $validated['q'];
            $query->where(function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%")
                    ->orWhere('ward', 'like', "%{$keyword}%")
                    ->orWhere('province', 'like', "%{$keyword}%");
            });
        }

        if (! empty($validated['area'])) {
            $area = $validated['area'];
            $query->where(function ($areaQuery) use ($area) {
                $areaQuery->where('address', 'like', "%{$area}%")
                    ->orWhere('ward', 'like', "%{$area}%")
                    ->orWhere('province', 'like', "%{$area}%");
            });
        }

        if (! empty($validated['province_code']) || ! empty($validated['province_name']) || ! empty($validated['province'])) {
            $pCode = (string) ($validated['province_code'] ?? '');
            $pName = $validated['province_name'] ?? $validated['province'] ?? '';
            $dbName = $pCode ? DB::table('vn_provinces')->where('code', $pCode)->value('name') : null;

            $query->where(function ($q) use ($pCode, $pName, $dbName) {
                $matched = false;
                if ($pCode) {
                    $q->where('province_code', $pCode);
                    $matched = true;
                }
                $namesToMatch = array_filter([$pName, $dbName]);
                foreach ($namesToMatch as $name) {
                    $clean = trim(preg_replace('/^(Tỉnh|Thành phố|TP\.)\s+/ui', '', $name));
                    if ($matched) {
                        $q->orWhere('province', 'like', "%{$name}%")
                          ->orWhere('province', 'like', "%{$clean}%")
                          ->orWhere('address', 'like', "%{$clean}%");
                    } else {
                        $q->where(function ($sub) use ($name, $clean) {
                            $sub->where('province', 'like', "%{$name}%")
                                ->orWhere('province', 'like', "%{$clean}%")
                                ->orWhere('address', 'like', "%{$clean}%");
                        });
                        $matched = true;
                    }
                }
            });
        }

        if (! empty($validated['ward_code']) || ! empty($validated['ward_name']) || ! empty($validated['ward'])) {
            $wCode = (string) ($validated['ward_code'] ?? '');
            $wName = $validated['ward_name'] ?? $validated['ward'] ?? '';
            $dbWardName = $wCode ? DB::table('vn_wards')->where('code', $wCode)->value('name') : null;

            $query->where(function ($q) use ($wCode, $wName, $dbWardName) {
                $matched = false;
                if ($wCode) {
                    $q->where('ward_code', $wCode);
                    $matched = true;
                }
                $namesToMatch = array_filter([$wName, $dbWardName]);
                foreach ($namesToMatch as $name) {
                    $clean = trim(preg_replace('/^(Phường|Xã|Thị trấn)\s+/ui', '', $name));
                    if ($matched) {
                        $q->orWhere('ward', 'like', "%{$name}%")
                          ->orWhere('ward', 'like', "%{$clean}%")
                          ->orWhere('address', 'like', "%{$clean}%");
                    } else {
                        $q->where(function ($sub) use ($name, $clean) {
                            $sub->where('ward', 'like', "%{$name}%")
                                ->orWhere('ward', 'like', "%{$clean}%")
                                ->orWhere('address', 'like', "%{$clean}%");
                        });
                        $matched = true;
                    }
                }
            });
        }

        if (isset($validated['min_rating'])) {
            $query->where('rating_avg', '>=', $validated['min_rating']);
        }

        if (! empty($validated['amenity_id'])) {
            $query->whereHas('amenityCatalog', function ($query) use ($validated) {
                $query->where('amenities.id', (int) $validated['amenity_id'])
                    ->where('amenities.status', 'active')
                    ->where('venue_cluster_amenities.is_visible', true);
            });
        }

        if (filter_var($validated['has_services'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
            $query->whereHas('services', fn ($query) => $query->where('status', 'active'));
        }

        if (filter_var($validated['has_map'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
            $query->whereNotNull('latitude')->whereNotNull('longitude');
        }

        if (! empty($validated['payment_option'])) {
            $paymentOption = $validated['payment_option'];
            $query->whereHas('bookingConfig', function ($query) use ($paymentOption) {
                $column = match ($paymentOption) {
                    'full_payment', 'wallet' => 'allow_full_payment',
                    'deposit' => 'allow_deposit',
                    'no_prepay' => 'allow_no_prepay',
                };
                $query->where($column, true);
            });
        }

        $availabilityCourtTypeIds = null;
        if (! empty($validated['court_type_id'])) {
            $courtTypeVal = $validated['court_type_id'];
            if (is_numeric($courtTypeVal)) {
                $courtTypeId = (int) $courtTypeVal;
            } else {
                $ct = DB::table('court_types')->where('code', $courtTypeVal)->orWhere('name', 'like', "%{$courtTypeVal}%")->first();
                $courtTypeId = $ct ? $ct->id : 0;
            }
            if ($courtTypeId > 0) {
                $courtTypeIds = $this->courtTypeSelfAndDescendants($courtTypeId);
                $availabilityCourtTypeIds = $courtTypeIds;

                $query->whereHas('venueCourts', function ($query) use ($courtTypeIds) {
                    $query->whereIn('court_type_id', $courtTypeIds)->where('status', 'active');
                });
            }
        }

        $clusters = $query
            ->orderByDesc('rating_avg')
            ->orderBy('name');

        if (! empty($validated['limit'])
            && ($validated['sort'] ?? 'recommended') !== 'distance'
            && (empty($validated['booking_date']) || empty($validated['start_time']) || empty($validated['end_time']))) {
            $clusters->limit((int) $validated['limit']);
        }

        $clusters = $clusters->get();

        if (! empty($validated['booking_date']) && ! empty($validated['start_time']) && ! empty($validated['end_time'])) {
            $clusters = $clusters->filter(function (VenueCluster $cluster) use ($validated, $availabilityCourtTypeIds) {
                $courts = $availabilityCourtTypeIds === null
                    ? $cluster->venueCourts
                    : $cluster->venueCourts->filter(fn ($court) => in_array((int) $court->court_type_id, $availabilityCourtTypeIds, true));

                return $courts->contains(function ($court) use ($cluster, $validated) {
                    return $this->bookingService->checkAvailability(
                        $court->id,
                        $validated['booking_date'],
                        $validated['start_time'],
                        $validated['end_time'],
                    ) && $this->bookingService->meetsMinimumAdvanceNotice(
                        $cluster->id,
                        $validated['booking_date'],
                        $validated['start_time'],
                    );
                });
            })->values();
        }

        $referenceLatitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $referenceLongitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;
        $hasReferencePoint = $referenceLatitude !== null && $referenceLongitude !== null;
        $selectedCourtTypeId = isset($validated['court_type_id']) ? (int) $validated['court_type_id'] : null;

        $clusters = $clusters
            ->map(function (VenueCluster $cluster) use ($referenceLatitude, $referenceLongitude, $hasReferencePoint, $selectedCourtTypeId): array {
                $payload = $this->summaryPayload($cluster, $selectedCourtTypeId);

                if ($hasReferencePoint && $cluster->latitude !== null && $cluster->longitude !== null) {
                    $payload['distance_km'] = round($this->distanceKm(
                        $referenceLatitude,
                        $referenceLongitude,
                        (float) $cluster->latitude,
                        (float) $cluster->longitude,
                    ), 2);
                }

                return $payload;
            })
            ->filter(function (array $cluster) use ($validated) {
                return empty($validated['min_courts'])
                    || (int) $cluster['court_count'] >= (int) $validated['min_courts'];
            })
            ->filter(function (array $cluster) use ($validated) {
                $price = $cluster['min_price'];

                if (isset($validated['min_price']) && ($price === null || $price < (float) $validated['min_price'])) {
                    return false;
                }

                if (isset($validated['max_price']) && ($price === null || $price > (float) $validated['max_price'])) {
                    return false;
                }

                return true;
            })
            ->sort(function (array $left, array $right) use ($validated) {
                return match ($validated['sort'] ?? 'recommended') {
                    'name' => strnatcasecmp($left['name'] ?? '', $right['name'] ?? ''),
                    'price' => ($left['min_price'] ?? PHP_INT_MAX) <=> ($right['min_price'] ?? PHP_INT_MAX),
                    'courts' => ((int) ($right['court_count'] ?? 0)) <=> ((int) ($left['court_count'] ?? 0)),
                    'rating' => ((float) ($right['rating_avg'] ?? 0)) <=> ((float) ($left['rating_avg'] ?? 0)),
                    'distance' => ($left['distance_km'] ?? PHP_INT_MAX) <=> ($right['distance_km'] ?? PHP_INT_MAX),
                    default => ((float) ($right['rating_avg'] ?? 0)) <=> ((float) ($left['rating_avg'] ?? 0))
                        ?: (($left['min_price'] ?? PHP_INT_MAX) <=> ($right['min_price'] ?? PHP_INT_MAX))
                        ?: strnatcasecmp($left['name'] ?? '', $right['name'] ?? ''),
                };
            })
            ->values()
            ->when(! empty($validated['limit']), fn (Collection $clusters) => $clusters->take((int) $validated['limit']));

        return response()->json(['data' => $clusters]);
    }

    public function filterOptions(): JsonResponse
    {
        $amenities = DB::table('amenities')
            ->join('venue_cluster_amenities', 'venue_cluster_amenities.amenity_id', '=', 'amenities.id')
            ->join('venue_clusters', 'venue_clusters.id', '=', 'venue_cluster_amenities.venue_cluster_id')
            ->where('amenities.status', 'active')
            ->where('venue_cluster_amenities.is_visible', true)
            ->where('venue_clusters.status', 'active')
            ->distinct()
            ->orderBy('amenities.name')
            ->get(['amenities.id', 'amenities.name']);

        return response()->json([
            'data' => [
                'amenities' => $amenities,
                'court_counts' => [1, 2, 4, 6, 8, 10],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $cluster = VenueCluster::query()
            ->with([
                'bookingConfig',
                'amenityCatalog',
                'venueCourts' => function ($query) {
                    $query->with([
                        'courtType:id,name,parent_id,icon_key',
                        'courtType.parent:id,name,parent_id,icon_key',
                    ])
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->orderBy('name');
                },
                'affiliateProducts' => function ($query) {
                    $query->where('is_active', true)->latest();
                },
                'services' => function ($query) {
                    $query->with('category')->where('status', 'active')->latest();
                },
            ])
            ->where(function ($query) use ($id) {
                $query->whereKey($id)->orWhere('slug', $id);
            })
            ->firstOrFail();

        $amenitiesDetail = $cluster->amenityCatalog
            ->where('status', 'active')
            ->map(fn ($amenity) => [
                'id' => $amenity->id,
                'name' => $amenity->name,
                'description' => $amenity->pivot->description ?? '',
            ])->values()->all();

        return response()->json([
            'data' => array_merge($this->summaryPayload($cluster), [
                'description' => $cluster->description,
                'phone_contact' => $cluster->phone_contact,
                'map_url' => $cluster->map_url,
                'latitude' => $cluster->latitude,
                'longitude' => $cluster->longitude,
                'layout_decorations' => $cluster->layout_decorations,
                'amenities' => $cluster->amenities ?? [],
                'amenities_detail' => $amenitiesDetail,
                'services' => $cluster->services,
                'booking_config' => $cluster->bookingConfig,
                'membership' => [
                    'enabled' => $this->venueMemberships->hasSettings((string) $cluster->id),
                    'tiers' => $this->venueMemberships->publicSettingsPayload((string) $cluster->id),
                ],
                'operating_hours' => $this->operatingHoursPayload($cluster),
                'policies' => $this->policyPayload($cluster),
                'venue_courts' => $cluster->venueCourts,
                'system_default_price' => 10000,
                'price_slots' => PriceSlot::query()
                    ->with([
                        'courtType:id,name,parent_id,icon_key',
                        'courtType.parent:id,name,parent_id,icon_key',
                    ])
                    ->where('venue_cluster_id', $cluster->id)
                    ->where('is_active', true)
                    ->orderBy('court_type_id')
                    ->orderBy('start_time')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->get(),
                'holiday_prices' => HolidayPrice::query()
                    ->with([
                        'courtType:id,name,parent_id,icon_key',
                        'courtType.parent:id,name,parent_id,icon_key',
                    ])
                    ->where('venue_cluster_id', $cluster->id)
                    ->where('is_active', true)
                    ->orderBy('holiday_date')
                    ->orderBy('start_time')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->get(),
                'base_prices' => VenueBasePrice::query()
                    ->with([
                        'courtType:id,name,parent_id,icon_key',
                        'courtType.parent:id,name,parent_id,icon_key',
                    ])
                    ->where('venue_cluster_id', $cluster->id)
                    ->orderBy('court_type_id')
                    ->get(),
                'gallery' => $this->gallery($cluster),
                'affiliate_products' => $cluster->affiliateProducts ?? [],
                'reviews' => $this->reviewPreview($cluster),
            ]),
        ]);
    }

    public function schedule(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::query()
            ->where(function ($query) use ($id) {
                $query->whereKey($id)->orWhere('slug', $id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'court_type_id' => ['nullable', 'integer', 'exists:court_types,id'],
            'booking_type' => ['nullable', 'in:single,recurring'],
        ]);

        return response()->json($this->bookingService->getAvailabilitySchedule(
            $cluster->id,
            $validated['booking_date'],
            isset($validated['court_type_id']) ? (int) $validated['court_type_id'] : null,
            $validated['booking_type'] ?? 'single',
        ));
    }

    private function summaryPayload(VenueCluster $cluster, ?int $selectedCourtTypeId = null): array
    {
        $bookingAccess = $this->bookingService->bookingAccessState((string) $cluster->id);
        $clusterCourtTypeIds = $cluster->venueCourts
            ->pluck('court_type_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedCourtTypeId !== null) {
            $targetIds = $this->courtTypeIdsWithDescendants($selectedCourtTypeId);
            $filteredIds = $clusterCourtTypeIds->filter(fn ($id) => in_array($id, $targetIds, true))->values();
            $effectiveCourtTypeIds = $filteredIds->isNotEmpty() ? $filteredIds : $clusterCourtTypeIds;
        } else {
            $effectiveCourtTypeIds = $clusterCourtTypeIds;
        }

        $priceCourtTypeIds = $this->courtTypeIdsWithAncestors($effectiveCourtTypeIds);

        $minPrice = null;
        $minCourtTypeId = null;

        if ($effectiveCourtTypeIds->isNotEmpty()) {
            $slotPrices = PriceSlot::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereIn('court_type_id', $priceCourtTypeIds)
                ->where('is_active', true)
                ->select('court_type_id', 'price')
                ->get();

            foreach ($slotPrices as $slot) {
                $priceVal = (float) $slot->price;
                if ($minPrice === null || $priceVal < $minPrice) {
                    $minPrice = $priceVal;
                    $minCourtTypeId = (int) $slot->court_type_id;
                }
            }

            $basePrices = VenueBasePrice::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereIn('court_type_id', $priceCourtTypeIds)
                ->select('court_type_id', 'price')
                ->get();

            foreach ($basePrices as $base) {
                $priceVal = (float) $base->price;
                if ($minPrice === null || $priceVal < $minPrice) {
                    $minPrice = $priceVal;
                    $minCourtTypeId = (int) $base->court_type_id;
                }
            }
        }

        $minPriceCourtTypeName = null;
        if ($minCourtTypeId !== null) {
            $courtTypeObj = DB::table('court_types')->where('id', $minCourtTypeId)->first(['name']);
            $minPriceCourtTypeName = $courtTypeObj?->name;
        }

        $courtTypes = $cluster->venueCourts
            ->pluck('courtType')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'parent_id' => $type->parent_id,
                'icon_key' => ($type->icon_key && $type->icon_key !== 'activity')
                    ? $type->icon_key
                    : ($type->parent?->icon_key ?: 'activity'),
            ]);

        return [
            'id' => $cluster->id,
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'province' => $cluster->province,
            'ward' => $cluster->ward,
            'address' => $cluster->address,
            'status' => $cluster->status,
            'latitude' => $cluster->latitude,
            'longitude' => $cluster->longitude,
            'rating_avg' => (float) $cluster->rating_avg,
            'rating_count' => (int) $cluster->rating_count,
            'court_count' => $cluster->venueCourts->count(),
            'court_types' => $courtTypes,
            'min_price' => $minPrice,
            'min_price_court_type_name' => $minPriceCourtTypeName,
            'amenities' => $cluster->amenityCatalog
                ->where('status', 'active')
                ->filter(fn ($amenity) => (bool) ($amenity->pivot?->is_visible ?? true))
                ->map(fn ($amenity) => ['id' => $amenity->id, 'name' => $amenity->name])
                ->values(),
            'service_count' => $cluster->services->where('status', 'active')->count(),
            'payment_options' => $this->paymentOptions($cluster),
            'has_map' => $cluster->latitude !== null && $cluster->longitude !== null,
            'image_path' => $this->coverImage($cluster),
            'availability_hint' => ! $bookingAccess['can_book']
                ? 'blocked'
                : ($cluster->venueCourts->isNotEmpty() ? 'available' : 'closed'),
            'booking_access' => $bookingAccess,
        ];
    }

    private function paymentOptions(VenueCluster $cluster): array
    {
        $config = $cluster->bookingConfig;
        if (! $config) {
            return ['full_payment', 'deposit', 'no_prepay', 'wallet'];
        }

        $options = [];
        if ($config->allow_full_payment) {
            $options[] = 'full_payment';
            $options[] = 'wallet';
        }
        if ($config->allow_deposit) $options[] = 'deposit';
        if ($config->allow_no_prepay) $options[] = 'no_prepay';

        return $options;
    }

    private function operatingHoursPayload(VenueCluster $cluster): array
    {
        $config = $cluster->bookingConfig;

        return [
            'fixed_open_time' => $config?->fixed_open_time,
            'fixed_close_time' => $config?->fixed_close_time,
            'weekly_operating_hours' => $config?->weekly_operating_hours ?? [],
            'min_duration_minutes' => $config?->min_duration_minutes,
            'max_duration_minutes' => $config?->max_duration_minutes,
        ];
    }

    private function policyPayload(VenueCluster $cluster): array
    {
        $config = $cluster->bookingConfig;

        $now = now();
        $systemVenuePolicy = SystemPolicy::query()
            ->where('policy_type', 'venue_policy')
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
            })
            ->orderByDesc('priority')
            ->orderByDesc('version')
            ->first();

        $systemVenuePayload = $systemVenuePolicy ? [
            'id' => $systemVenuePolicy->id,
            'title' => $systemVenuePolicy->title,
            'content' => $systemVenuePolicy->content,
            'policy_type' => $systemVenuePolicy->policy_type,
            'version' => $systemVenuePolicy->version,
            'source' => 'system',
            'source_label' => 'Chính sách hệ thống',
            'status' => 'system_default',
        ] : null;

        $venueNotices = VenuePolicyRule::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('rule_type', 'customer_notice')
            ->where('status', 'active')
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
            })
            ->latest()
            ->get()
            ->map(fn (VenuePolicyRule $rule): array => [
                'id' => $rule->id,
                'title' => $rule->rule_name,
                'content' => $rule->result_json['content'] ?? null,
                'status' => $rule->status,
                'source' => 'venue',
                'source_label' => 'Chính sách riêng của sân',
            ])
            ->values()
            ->all();

        // Customer-facing notices inherit the system venue policy when the owner
        // has not published an active notice for this cluster.
        $effectiveNotices = $venueNotices ?: ($systemVenuePayload ? [$systemVenuePayload] : []);

        $systemCancellationPolicy = SystemPolicy::query()
            ->with(['rules' => fn ($query) => $query->where('is_active', true)->orderByDesc('priority')])
            ->where('policy_type', 'booking_cancellation')
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
            })
            ->orderByDesc('priority')
            ->orderByDesc('version')
            ->first();
        $systemCancellationRule = $systemCancellationPolicy
            ? $systemCancellationPolicy->rules->firstWhere('rule_type', RefundCancellationPolicyService::CANCELLATION_RULE_TYPE)
            : null;
        $venueCancellationRule = $systemCancellationRule
            ? VenuePolicyRule::query()
                ->where('venue_cluster_id', $cluster->id)
                ->where('base_policy_rule_id', $systemCancellationRule->id)
                ->where('rule_type', RefundCancellationPolicyService::CANCELLATION_RULE_TYPE)
                ->where('status', 'active')
                ->where(function ($query) use ($now): void {
                    $query->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
                })
                ->where(function ($query) use ($now): void {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
                })
                ->latest()
                ->first()
            : null;
        $systemCancellationTiers = $systemCancellationRule
            ? $this->refundPolicies->cancelRefundTiersFromRule($systemCancellationRule)
            : [];
        $effectiveCancellationTiers = $venueCancellationRule
            ? $this->refundPolicies->cancelRefundTiersFromVenueRule($venueCancellationRule, $systemCancellationTiers)
            : $systemCancellationTiers;
        $effectiveCancellationSummary = $effectiveCancellationTiers
            ? $this->refundPolicies->cancelRefundSummary($effectiveCancellationTiers)
            : null;

        return [
            'allow_full_payment' => (bool) ($config?->allow_full_payment ?? true),
            'allow_wallet' => (bool) ($config?->allow_full_payment ?? true),
            'allow_deposit' => (bool) ($config?->allow_deposit ?? true),
            'allow_no_prepay' => (bool) ($config?->allow_no_prepay ?? true),
            'deposit_percent' => $config?->deposit_percent !== null ? (float) $config->deposit_percent : null,
            'cancel_before_hours' => $config?->cancel_before_hours,
            'refund_percent' => $config?->refund_percent,
            'min_advance_booking_minutes' => $config?->min_advance_booking_minutes,
            'slot_hold_minutes' => $config?->slot_hold_minutes,
            'display_notices' => $effectiveNotices,
            'display_notice_source' => $venueNotices ? 'venue' : 'system',
            'display_notice_source_label' => $venueNotices
                ? 'Đang áp dụng chính sách riêng của sân'
                : 'Đang hiển thị chính sách hệ thống',
            'system_venue_policy' => $systemVenuePayload,
            'cancellation_refund' => [
                'source' => $venueCancellationRule ? 'venue' : 'system',
                'source_label' => $venueCancellationRule
                    ? 'Đang áp dụng chính sách riêng của sân'
                    : 'Đang kế thừa chính sách hệ thống',
                'system_policy' => $systemCancellationPolicy ? [
                    'id' => $systemCancellationPolicy->id,
                    'title' => $systemCancellationPolicy->title,
                    'version' => $systemCancellationPolicy->version,
                ] : null,
                'venue_rule_id' => $venueCancellationRule?->id,
                'effective_summary' => $effectiveCancellationSummary,
                'effective_tiers' => $effectiveCancellationTiers,
            ],
        ];
    }

    private function reviewPreview(VenueCluster $cluster): array
    {
        if (! Schema::hasTable('reviews')) {
            return [];
        }

        return DB::table('reviews')
            ->leftJoin('users', 'users.id', '=', 'reviews.customer_id')
            ->where('reviews.venue_cluster_id', $cluster->id)
            ->where('reviews.is_visible', true)
            ->latest('reviews.created_at')
            ->limit(10)
            ->get([
                'reviews.id',
                'reviews.rating',
                'reviews.comment',
                'reviews.reply_content',
                'reviews.replied_at',
                'reviews.created_at',
                'users.full_name as author_name',
                'users.username as author_username',
            ])
            ->map(fn (object $review): array => [
                'id' => $review->id,
                'author_name' => $review->author_name ?: $review->author_username ?: 'Khách hàng SportGo',
                'rating' => (float) $review->rating,
                'content' => $review->comment,
                'reply_content' => $review->reply_content,
                'replied_at' => $review->replied_at
                    ? Carbon::parse($review->replied_at, config('app.timezone'))->toIso8601String()
                    : null,
                'created_at' => Carbon::parse($review->created_at, config('app.timezone'))->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function coverImage(VenueCluster $cluster): ?string
    {
        return DB::table('media')
            ->where('mediable_type', VenueCluster::class)
            ->where('mediable_id', $cluster->id)
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('file_path');
    }

    private function gallery(VenueCluster $cluster): array
    {
        $paths = DB::table('media')
            ->where('mediable_type', VenueCluster::class)
            ->where('mediable_id', $cluster->id)
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('file_path')
            ->all();

        return $paths ?: [];
    }

    private function courtTypeSelfAndDescendants(int $courtTypeId): array
    {
        $types = DB::table('court_types')
            ->whereNull('deleted_at')
            ->get(['id', 'parent_id']);

        $childrenByParent = $types
            ->filter(fn ($type) => $type->parent_id !== null)
            ->groupBy(fn ($type) => (int) $type->parent_id);

        $ids = [$courtTypeId];
        $stack = [$courtTypeId];

        while ($stack !== []) {
            $parentId = array_pop($stack);

            foreach (($childrenByParent[$parentId] ?? collect()) as $child) {
                $childId = (int) $child->id;
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }

    private function distanceKm(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371.0;
        $deltaLatitude = deg2rad($latitudeB - $latitudeA);
        $deltaLongitude = deg2rad($longitudeB - $longitudeA);
        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($deltaLongitude / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a))));
    }

    private function courtTypeIdsWithDescendants(int $courtTypeId): array
    {
        $childrenByType = DB::table('court_types')
            ->whereNull('deleted_at')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [$courtTypeId];
        $queue = [$courtTypeId];

        while (! empty($queue)) {
            $currentId = array_shift($queue);
            if (isset($childrenByType[$currentId])) {
                foreach ($childrenByType[$currentId] as $child) {
                    $childId = (int) $child->id;
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function courtTypeIdsWithAncestors(Collection $courtTypeIds): array
    {
        $parentByType = DB::table('court_types')
            ->whereNull('deleted_at')
            ->pluck('parent_id', 'id')
            ->all();

        $ids = $courtTypeIds
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        foreach ($ids as $courtTypeId) {
            $currentTypeId = $courtTypeId;
            $guard = 0;

            while ($guard < 20 && isset($parentByType[$currentTypeId]) && $parentByType[$currentTypeId] !== null) {
                $parentTypeId = (int) $parentByType[$currentTypeId];
                $ids[] = $parentTypeId;
                $currentTypeId = $parentTypeId;
                $guard++;
            }
        }

        return array_values(array_unique($ids));
    }
}
