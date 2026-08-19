<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueStaffShift;
use App\Models\VenueStaffShiftSchedule;
use App\Models\VenueStaffAssignment;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

        // 2. Ensure Staff Users Exist and have role 'venue_staff'
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

            // Assign role
            if (!$user->roles()->where('roles.name', 'venue_staff')->exists()) {
                $user->roles()->attach($staffRole->id);
            }

            $staffUsers[] = $user;
        }

        // 3. Ensure All Clusters have active Staff Assignments
        $clusters = VenueCluster::all();
        if ($clusters->isEmpty()) {
            return;
        }

        $owner = User::where('username', 'owner')->first() ?: $staffUsers[0];

        foreach ($clusters as $cluster) {
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

            // 4. Create Standard Shift Templates for each cluster
            $shiftMorning = VenueStaffShift::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => 'Ca Sáng (06:00 - 14:00)',
                ],
                [
                    'start_time' => '06:00:00',
                    'end_time' => '14:00:00',
                    'description' => 'Ca trực buổi sáng chuẩn đón khách sớm',
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
                    'description' => 'Ca trực cao điểm chiều tối',
                    'is_active' => true,
                ]
            );

            $shiftNight = VenueStaffShift::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => 'Ca Đêm (22:00 - 06:00)',
                ],
                [
                    'start_time' => '22:00:00',
                    'end_time' => '06:00:00',
                    'description' => 'Ca trực đêm và bàn giao',
                    'is_active' => true,
                ]
            );
        }

        // 5. Seed Shift Schedules for This Week (Monday to Sunday)
        $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
        $primaryStaff = $staffUsers[0]; // 'venuestaff'
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
                // Past days: Checked out
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

                // Afternoon shift scheduled
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
                // Future days: Scheduled
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

        // 6. Seed Rich Bookings on Courts for Today & Surrounding Days
        $customers = User::whereNotIn('id', collect($staffUsers)->pluck('id'))->take(6)->get();
        if ($customers->isEmpty()) {
            $customers = collect([$staffUsers[0]]);
        }

        $sampleBookings = [
            [
                'code' => 'SPG-0801',
                'name' => 'Anh Hoàng Hải (FC Bách Khoa)',
                'phone' => '0988112233',
                'court_idx' => 0,
                'start' => '06:00:00',
                'end' => '07:30:00',
                'price' => 250000,
                'paid' => 250000,
                'status' => 'completed',
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'SPG-0802',
                'name' => 'Chị Mai Lan (CLB Cầu Lông)',
                'phone' => '0912345678',
                'court_idx' => 1,
                'start' => '07:30:00',
                'end' => '09:00:00',
                'price' => 180000,
                'paid' => 180000,
                'status' => 'completed',
                'source' => 'online',
                'type' => 'recurring',
            ],
            [
                'code' => 'SPG-0803',
                'name' => 'Nguyễn Minh Tuấn (FC Trẻ)',
                'phone' => '0977889900',
                'court_idx' => 0,
                'start' => '09:00:00',
                'end' => '11:00:00',
                'price' => 350000,
                'paid' => 150000,
                'status' => 'confirmed',
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'SPG-0804',
                'name' => 'Đỗ Văn Thành (Khách Vãng Lai)',
                'phone' => '0934567890',
                'court_idx' => 2,
                'start' => '14:00:00',
                'end' => '16:00:00',
                'price' => 300000,
                'paid' => 0,
                'status' => 'confirmed',
                'source' => 'counter',
                'type' => 'single',
            ],
            [
                'code' => 'SPG-0805',
                'name' => 'FC Cơn Lốc Sân Cỏ (Trận Đang Đấu)',
                'phone' => '0909876543',
                'court_idx' => 0,
                'start' => '16:00:00',
                'end' => '18:00:00',
                'price' => 400000,
                'paid' => 400000,
                'status' => 'checked_in',
                'source' => 'counter',
                'type' => 'single',
            ],
            [
                'code' => 'SPG-0806',
                'name' => 'Trần Quang Huy (Trận Đang Đấu)',
                'phone' => '0987654321',
                'court_idx' => 1,
                'start' => '16:30:00',
                'end' => '18:00:00',
                'price' => 220000,
                'paid' => 100000,
                'status' => 'checked_in',
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'SPG-0807',
                'name' => 'Lê Thanh Bình (FC Xây Dựng)',
                'phone' => '0911223344',
                'court_idx' => 0,
                'start' => '18:00:00',
                'end' => '20:00:00',
                'price' => 450000,
                'paid' => 450000,
                'status' => 'confirmed',
                'source' => 'online',
                'type' => 'recurring',
            ],
            [
                'code' => 'SPG-0808',
                'name' => 'Vũ Thu Trang (Giải Giao Hữu Tối)',
                'phone' => '0944556677',
                'court_idx' => 1,
                'start' => '18:00:00',
                'end' => '20:00:00',
                'price' => 280000,
                'paid' => 0,
                'status' => 'pending_payment',
                'source' => 'online',
                'type' => 'single',
            ],
            [
                'code' => 'SPG-0809',
                'name' => 'FC Đại Học Y Hà Nội',
                'phone' => '0966778899',
                'court_idx' => 2,
                'start' => '20:00:00',
                'end' => '22:00:00',
                'price' => 400000,
                'paid' => 200000,
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

            foreach ($sampleBookings as $bIndex => $sample) {
                $court = $courts[$sample['court_idx'] % $courts->count()];
                $customer = $customers[$bIndex % $customers->count()];

                $durationMins = abs((int) Carbon::parse($sample['start'])->diffInMinutes(Carbon::parse($sample['end'])));

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
                        'total_price' => $sample['price'],
                        'original_amount' => $sample['price'],
                        'discount_amount' => 0,
                        'final_amount' => $sample['price'],
                        'payment_option' => $sample['paid'] >= $sample['price'] ? 'full_payment' : ($sample['paid'] > 0 ? 'deposit' : 'no_prepay'),
                        'required_payment_amount' => $sample['price'],
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
                        'unit_price' => $sample['price'],
                        'subtotal' => $sample['price'],
                        'status' => 'active',
                    ]
                );

                // Payment
                if ($sample['paid'] > 0) {
                    Payment::query()->updateOrCreate(
                        [
                            'booking_id' => $booking->id,
                            'payment_code' => 'PAY-' . $booking->booking_code,
                        ],
                        [
                            'payment_context' => 'booking',
                            'amount' => $sample['paid'],
                            'gateway_amount' => $sample['paid'],
                            'payment_kind' => $sample['paid'] >= $sample['price'] ? 'full' : 'deposit',
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
