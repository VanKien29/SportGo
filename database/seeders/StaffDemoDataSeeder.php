<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueStaffShift;
use App\Models\VenueStaffShiftSchedule;
use App\Models\VenueStaffAssignment;
use App\Models\ServiceCategory;
use App\Models\VenueClusterService;
use App\Models\VenueBasePrice;
use App\Models\PriceSlot;
use App\Models\SlotLock;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StaffDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $now = Carbon::now();

        // 1. Ensure Role 'venue_staff' exists
        $staffRole = Role::firstOrCreate(['name' => 'venue_staff'], [
            'display_name' => 'Nhân viên sân',
            'description' => 'Nhân viên trực sân và điều phối POS',
        ]);

        // 2. Ensure Staff Users Exist
        $staffAccounts = [
            ['username' => 'venuestaff', 'full_name' => 'Nguyễn Văn An (NV Trực Sân)', 'email' => 'venuestaff@sportgo.vn', 'phone' => '0903112233'],
            ['username' => 'venuestaff1', 'full_name' => 'Trần Thị Bình (NV Thu Ngân)', 'email' => 'an.nguyen@sportgo.vn', 'phone' => '0903223344'],
            ['username' => 'venuestaff2', 'full_name' => 'Lê Hoàng Cường (NV Điều Phối)', 'email' => 'binh.tran@sportgo.vn', 'phone' => '0903334455'],
            ['username' => 'staff_manager', 'full_name' => 'Phạm Minh Đức (Quản Lý Ca)', 'email' => 'staff_manager@sportgo.vn', 'phone' => '0903445566'],
        ];

        $staffUsers = [];
        foreach ($staffAccounts as $acc) {
            $user = User::query()->updateOrCreate(
                ['username' => $acc['username']],
                [
                    'full_name' => $acc['full_name'],
                    'email' => $acc['email'],
                    'phone' => $acc['phone'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ]
            );

            if (!$user->roles()->where('roles.name', 'venue_staff')->exists()) {
                $user->roles()->attach($staffRole->id);
            }

            $staffUsers[] = $user;
        }

        // 3. Ensure Clusters
        $clusters = VenueCluster::all();
        if ($clusters->isEmpty()) {
            return;
        }

        $owner = User::where('username', 'owner')->first() ?: $staffUsers[0];

        // 4. Ensure Service Categories & F&B Products exist for Quick Retail Modal
        $catBeverage = ServiceCategory::firstOrCreate(['name' => 'Nước uống & Giải khát'], [
            'status' => 'active',
            'description' => 'Nước suối, nước khoáng, nước điện giải và nước ngọt giải khát',
        ]);
        $catEquipment = ServiceCategory::firstOrCreate(['name' => 'Dụng cụ & Phụ kiện'], [
            'status' => 'active',
            'description' => 'Cầu lông, bóng đá, bóng pickleball và phụ kiện thi đấu',
        ]);
        $catRental = ServiceCategory::firstOrCreate(['name' => 'Cho thuê thiết bị'], [
            'status' => 'active',
            'description' => 'Cho thuê vợt, giày thi đấu, áo bib và trọng tài',
        ]);

        $retailProducts = [
            // Nước uống
            ['cat' => $catBeverage->id, 'name' => 'Nước suối Lavie 500ml', 'price' => 10000, 'unit' => 'Chai', 'desc' => 'Nước khoáng thiên nhiên 500ml'],
            ['cat' => $catBeverage->id, 'name' => 'Nước điện giải Pocari Sweat 500ml', 'price' => 18000, 'unit' => 'Chai', 'desc' => 'Bổ sung ion khoáng nhanh chóng'],
            ['cat' => $catBeverage->id, 'name' => 'Nước tăng lực RedBull Thái', 'price' => 20000, 'unit' => 'Lon', 'desc' => 'Redbull nắp vàng chính hãng'],
            ['cat' => $catBeverage->id, 'name' => 'Nước khoáng Revive Chanh Muối', 'price' => 15000, 'unit' => 'Chai', 'desc' => 'Bù khoáng vị chanh muối mát lạnh'],
            ['cat' => $catBeverage->id, 'name' => 'Nước ngọt Coca-Cola 330ml', 'price' => 15000, 'unit' => 'Lon', 'desc' => 'Lon 330ml ướp lạnh'],
            ['cat' => $catBeverage->id, 'name' => 'Trà chanh mật ong C2', 'price' => 12000, 'unit' => 'Chai', 'desc' => 'Trà xanh thanh mát giải nhiệt'],

            // Dụng cụ & Phụ kiện
            ['cat' => $catEquipment->id, 'name' => 'Ống cầu lông Yonex Aerosensa 50', 'price' => 380000, 'unit' => 'Ống 12 quả', 'desc' => 'Cầu lông tiêu chuẩn thi đấu quốc tế'],
            ['cat' => $catEquipment->id, 'name' => 'Quả cầu lông Thành Công Pro', 'price' => 25000, 'unit' => 'Quả', 'desc' => 'Cầu lông độ bền cao'],
            ['cat' => $catEquipment->id, 'name' => 'Quả bóng đá sân 5 Động Lực FIFA', 'price' => 350000, 'unit' => 'Quả', 'desc' => 'Bóng da cao cấp chuẩn FIFA Quality'],
            ['cat' => $catEquipment->id, 'name' => 'Quả bóng Pickleball Franklin X-40', 'price' => 55000, 'unit' => 'Quả', 'desc' => 'Bóng pickleball ngoài trời tiêu chuẩn'],
            ['cat' => $catEquipment->id, 'name' => 'Băng keo cơ / Quấn cán Yonex', 'price' => 30000, 'unit' => 'Cuộn', 'desc' => 'Thấm hút mồ hôi và chống trượt'],

            // Cho thuê
            ['cat' => $catRental->id, 'name' => 'Thuê vợt Cầu lông Yonex Carbon Pro', 'price' => 30000, 'unit' => 'Cây / Trận', 'desc' => 'Vợt căng cước 10.5kg sẵn sàng thi đấu'],
            ['cat' => $catRental->id, 'name' => 'Thuê vợt Pickleball cao cấp', 'price' => 40000, 'unit' => 'Cây / Trận', 'desc' => 'Vợt Carbon T700 kiểm soát bóng tốt'],
            ['cat' => $catRental->id, 'name' => 'Thuê giày thể thao thi đấu', 'price' => 40000, 'unit' => 'Đôi / Buổi', 'desc' => 'Đủ size từ 39 đến 44'],
            ['cat' => $catRental->id, 'name' => 'Thuê bộ áo lưới tập (10 áo)', 'price' => 50000, 'unit' => 'Bộ / Trận', 'desc' => 'Bộ bib 2 màu chia đội'],
        ];

        foreach ($clusters as $cluster) {
            // Seed staff assignments
            foreach ($staffUsers as $staffUser) {
                VenueStaffAssignment::query()->updateOrCreate(
                    [
                        'user_id' => $staffUser->id,
                        'venue_cluster_id' => $cluster->id,
                        'scope_key' => 'all',
                    ],
                    [
                        'scope_type' => 'all_cluster',
                        'assigned_by' => $owner->id,
                        'status' => 'active',
                    ]
                );
            }

            // Seed Shift Templates
            $shiftMorning = VenueStaffShift::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => 'Ca Sáng (06:00 - 14:00)',
                ],
                [
                    'start_time' => '06:00:00',
                    'end_time' => '14:00:00',
                    'description' => 'Ca trực buổi sáng đón khách tập luyện',
                    'is_active' => true,
                ]
            );

            $shiftAfternoon = VenueStaffShift::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => 'Ca Chiều Tối (14:00 - 22:00)',
                ],
                [
                    'start_time' => '14:00:00',
                    'end_time' => '22:00:00',
                    'description' => 'Ca trực cao điểm chiều tối và giao lưu',
                    'is_active' => true,
                ]
            );

            // Seed Services for this cluster
            foreach ($retailProducts as $p) {
                VenueClusterService::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'name' => $p['name'],
                    ],
                    [
                        'category_id' => $p['cat'],
                        'price' => $p['price'],
                        'unit' => $p['unit'],
                        'status' => 'active',
                        'description' => $p['desc'],
                    ]
                );
            }
        }

        // 5. Seed Shift Schedules for This Week
        $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
        $primaryStaff = $staffUsers[0];
        $secondaryStaff = $staffUsers[1] ?? $staffUsers[0];
        $mainCluster = $clusters->first();

        for ($i = 0; $i < 7; $i++) {
            $currentDay = $startOfWeek->copy()->addDays($i);
            $dateStr = $currentDay->toDateString();

            $shifts = VenueStaffShift::where('venue_cluster_id', $mainCluster->id)->get();
            if ($shifts->isEmpty()) continue;

            $morningShift = $shifts[0];
            $afternoonShift = $shifts[1] ?? $shifts[0];

            if ($currentDay->isPast() && !$currentDay->isToday()) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'user_id' => $primaryStaff->id,
                        'date' => $dateStr,
                        'venue_staff_shift_id' => $morningShift->id,
                    ],
                    [
                        'venue_cluster_id' => $mainCluster->id,
                        'start_time' => '06:00:00',
                        'end_time' => '14:00:00',
                        'status' => 'checked_out',
                        'check_in_at' => $currentDay->copy()->setTime(6, 2, 0),
                        'check_out_at' => $currentDay->copy()->setTime(14, 5, 0),
                        'notes' => 'Hoàn thành trực ca sáng xuất sắc',
                        'created_by' => $owner->id,
                    ]
                );
            } elseif ($currentDay->isToday()) {
                // Today: Checked in currently
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'user_id' => $primaryStaff->id,
                        'date' => $dateStr,
                        'venue_staff_shift_id' => $morningShift->id,
                    ],
                    [
                        'venue_cluster_id' => $mainCluster->id,
                        'start_time' => '06:00:00',
                        'end_time' => '14:00:00',
                        'status' => 'checked_in',
                        'check_in_at' => $currentDay->copy()->setTime(6, 0, 0),
                        'check_out_at' => null,
                        'notes' => 'Đang trong ca trực chính hôm nay',
                        'created_by' => $owner->id,
                    ]
                );

                // Afternoon shift
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'user_id' => $secondaryStaff->id,
                        'date' => $dateStr,
                        'venue_staff_shift_id' => $afternoonShift->id,
                    ],
                    [
                        'venue_cluster_id' => $mainCluster->id,
                        'start_time' => '14:00:00',
                        'end_time' => '22:00:00',
                        'status' => 'scheduled',
                        'check_in_at' => null,
                        'check_out_at' => null,
                        'notes' => 'Ca trực chiều tối cao điểm',
                        'created_by' => $owner->id,
                    ]
                );
            } else {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'user_id' => $primaryStaff->id,
                        'date' => $dateStr,
                        'venue_staff_shift_id' => ($i % 2 === 0) ? $morningShift->id : $afternoonShift->id,
                    ],
                    [
                        'venue_cluster_id' => $mainCluster->id,
                        'start_time' => ($i % 2 === 0) ? '06:00:00' : '14:00:00',
                        'end_time' => ($i % 2 === 0) ? '14:00:00' : '22:00:00',
                        'status' => 'scheduled',
                        'check_in_at' => null,
                        'check_out_at' => null,
                        'notes' => 'Ca trực phân công theo tuần',
                        'created_by' => $owner->id,
                    ]
                );
            }
        }

        // 6. Seed Rich Bookings for TODAY on courts
        $customers = User::whereNotIn('id', collect($staffUsers)->pluck('id'))->take(10)->get();
        if ($customers->isEmpty()) {
            $customers = collect([$staffUsers[0]]);
        }

        // Sample rich booking dataset representing real-world club and walk-in activities
        $sampleBookings = [
            [
                'code' => 'BK-1001',
                'name' => 'CLB Cầu Lông Bình Minh',
                'phone' => '0988112233',
                'court_idx' => 0,
                'start' => '06:00:00',
                'end' => '08:00:00',
                'price' => 240000,
                'paid' => 240000,
                'status' => 'completed',
                'source' => 'online',
                'type' => 'recurring',
            ],
            [
                'code' => 'BK-1002',
                'name' => 'Anh Trần Nam (Giao Hữu)',
                'phone' => '0912345678',
                'court_idx' => 1,
                'start' => '07:00:00',
                'end' => '09:00:00',
                'price' => 260000,
                'paid' => 260000,
                'status' => 'completed',
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'BK-1003',
                'name' => 'FC Lão Tướng Bách Khoa',
                'phone' => '0977889900',
                'court_idx' => 2,
                'start' => '08:00:00',
                'end' => '10:00:00',
                'price' => 380000,
                'paid' => 380000,
                'status' => 'completed',
                'source' => 'counter',
                'type' => 'single',
            ],
            [
                'code' => 'BK-1004',
                'name' => 'Anh Hoàng Hải (FC Bách Khoa)',
                'phone' => '0903114477',
                'court_idx' => 0,
                'start' => '13:00:00',
                'end' => '15:00:00',
                'price' => 300000,
                'paid' => 200000, // Còn thiếu 100k
                'status' => 'checked_in', // ĐANG CHƠI
                'source' => 'counter',
                'type' => 'single',
            ],
            [
                'code' => 'BK-1005',
                'name' => 'Chị Mai Lan (CLB Pickleball Nữ)',
                'phone' => '0909876543',
                'court_idx' => 1,
                'start' => '14:00:00',
                'end' => '16:00:00',
                'price' => 280000,
                'paid' => 280000,
                'status' => 'checked_in', // ĐANG CHƠI
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'BK-1006',
                'name' => 'Nguyễn Minh Tuấn (FC Trẻ)',
                'phone' => '0987654321',
                'court_idx' => 0,
                'start' => '15:00:00',
                'end' => '17:00:00',
                'price' => 350000,
                'paid' => 150000, // Còn thiếu 200k
                'status' => 'confirmed', // CHỜ CHECK-IN
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'BK-1007',
                'name' => 'Đỗ Văn Thành (Khách Vãng Lai)',
                'phone' => '0934567890',
                'court_idx' => 2,
                'start' => '16:00:00',
                'end' => '18:00:00',
                'price' => 300000,
                'paid' => 0, // Chưa thanh toán
                'status' => 'confirmed', // CHỜ CHECK-IN
                'source' => 'counter',
                'type' => 'single',
            ],
            [
                'code' => 'BK-1008',
                'name' => 'Trần Quang Huy (Đơn Vé Online)',
                'phone' => '0911223344',
                'court_idx' => 1,
                'start' => '17:00:00',
                'end' => '19:00:00',
                'price' => 280000,
                'paid' => 280000,
                'status' => 'confirmed', // CHỜ CHECK-IN
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'BK-1009',
                'name' => 'FC Cơn Lốc Sân Cỏ',
                'phone' => '0944556677',
                'court_idx' => 0,
                'start' => '18:00:00',
                'end' => '20:00:00',
                'price' => 450000,
                'paid' => 0, // Chờ thanh toán
                'status' => 'pending_payment',
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'BK-1010',
                'name' => 'Lê Thanh Bình (FC Xây Dựng - Lịch Cố Định)',
                'phone' => '0966778899',
                'court_idx' => 2,
                'start' => '19:00:00',
                'end' => '21:00:00',
                'price' => 450000,
                'paid' => 450000,
                'status' => 'confirmed',
                'source' => 'online',
                'type' => 'recurring',
            ],
            [
                'code' => 'BK-1011',
                'name' => 'FC Đại Học Y Hà Nội',
                'phone' => '0977113355',
                'court_idx' => 1,
                'start' => '20:00:00',
                'end' => '22:00:00',
                'price' => 380000,
                'paid' => 200000, // Cọc 200k
                'status' => 'confirmed',
                'source' => 'counter',
                'type' => 'single',
            ],
        ];

        foreach ($clusters as $cluster) {
            $courts = VenueCourt::where('venue_cluster_id', $cluster->id)->where('status', 'active')->orderBy('id')->get();
            if ($courts->isEmpty()) {
                $courts = VenueCourt::where('venue_cluster_id', $cluster->id)->get();
            }
            if ($courts->isEmpty()) continue;

            // Seed a maintenance slot lock on Court 3 from 10:00 to 12:00
            if ($courts->count() >= 4) {
                SlotLock::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'venue_court_id' => $courts[3]->id,
                        'booking_date' => $today->toDateString(),
                        'start_time' => '10:00:00',
                        'end_time' => '12:00:00',
                    ],
                    [
                        'lock_scope' => 'court',
                        'locked_by' => (string) $owner->id,
                        'lock_type' => 'manual',
                        'reason' => 'Bảo dưỡng hệ thống đèn LED & căng lưới thi đấu',
                        'expires_at' => $today->copy()->endOfDay(),
                    ]
                );
            }

            /** @var \App\Services\BookingService $bookingService */
            $bookingService = app(\App\Services\BookingService::class);

            foreach ($sampleBookings as $bIndex => $sample) {
                $court = $courts[$sample['court_idx'] % $courts->count()];
                $customer = $customers[$bIndex % $customers->count()];

                $durationMins = abs((int) Carbon::parse($sample['start'])->diffInMinutes(Carbon::parse($sample['end'])));
                $durationHours = max(1, (int) round($durationMins / 60));

                // 1. Calculate standard price strictly using official BookingService engine
                $rateInfo = $bookingService->resolveHourlyRate(
                    (string) $cluster->id,
                    (int) $court->court_type_id,
                    $today->toDateString(),
                    $sample['start'],
                    $sample['end'],
                    $sample['type']
                );

                $hourlyRate = (float) ($rateInfo['hourly_rate'] ?? 100000);
                $totalPrice = $hourlyRate * $durationHours;

                // Determine paid amount based on booking payment flow
                $paidAmount = match ($sample['status']) {
                    'completed' => $totalPrice,
                    'checked_in' => ($sample['paid'] > 0 && $sample['paid'] < $sample['price']) ? ($totalPrice / 2) : $totalPrice,
                    'confirmed' => ($sample['paid'] > 0 && $sample['paid'] < $sample['price']) ? ($totalPrice / 2) : ($sample['paid'] > 0 ? $totalPrice : 0),
                    'pending_payment' => 0,
                    default => 0,
                };

                $booking = Booking::query()->updateOrCreate(
                    [
                        'booking_code' => $sample['code'] . '-C' . $cluster->id,
                    ],
                    [
                        'customer_id' => $customer->id,
                        'walk_in_name' => $sample['name'],
                        'walk_in_phone' => $sample['phone'],
                        'venue_court_id' => $court->id,
                        'requested_venue_court_id' => $court->id,
                        'venue_cluster_id' => $cluster->id,
                        'booking_date' => $today->toDateString(),
                        'start_time' => $sample['start'],
                        'end_time' => $sample['end'],
                        'duration_minutes' => $durationMins,
                        'total_price' => $totalPrice,
                        'original_amount' => $totalPrice,
                        'discount_amount' => 0,
                        'final_amount' => $totalPrice,
                        'payment_option' => $paidAmount >= $totalPrice ? 'full_payment' : ($paidAmount > 0 ? 'deposit' : 'no_prepay'),
                        'required_payment_amount' => $totalPrice,
                        'source' => $sample['source'],
                        'booking_type' => $sample['type'],
                        'recurring_group_code' => $sample['type'] === 'recurring' ? 'REC-GRP-' . $cluster->id . '-' . $bIndex : null,
                        'status' => $sample['status'],
                        'created_by' => $staffUsers[0]->id,
                        'created_at' => $now->copy()->subHours(5),
                        'updated_at' => $now,
                    ]
                );

                // Booking Item
                BookingItem::query()->updateOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'venue_court_id' => $court->id,
                    ],
                    [
                        'requested_venue_court_id' => $court->id,
                        'start_time' => $sample['start'],
                        'end_time' => $sample['end'],
                        'duration_minutes' => $durationMins,
                        'unit_price' => $totalPrice,
                        'subtotal' => $totalPrice,
                        'status' => 'active',
                    ]
                );

                // Payment record
                if ($paidAmount > 0) {
                    Payment::query()->updateOrCreate(
                        [
                            'booking_id' => $booking->id,
                            'payment_code' => 'PAY-' . $booking->booking_code,
                        ],
                        [
                            'payment_context' => 'booking',
                            'amount' => $paidAmount,
                            'gateway_amount' => $paidAmount,
                            'payment_kind' => $paidAmount >= $totalPrice ? 'full' : 'deposit',
                            'method' => $sample['source'] === 'counter' ? 'cash' : 'sepay',
                            'status' => 'paid',
                            'paid_at' => $now->copy()->subHours(3),
                            'created_at' => $now->copy()->subHours(3),
                        ]
                    );
                }
            }
        }
    }
}
