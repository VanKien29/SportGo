<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueStaffShift;
use App\Models\VenueStaffShiftSchedule;
use App\Models\VenueStaffAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class VenueStaffShiftsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('venue_clusters')
            || ! Schema::hasTable('venue_staff_shifts')
            || ! Schema::hasTable('venue_staff_shift_schedules')
        ) {
            return;
        }

        // Get all clusters
        $clusters = VenueCluster::all();
        if ($clusters->isEmpty()) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first() ?: User::first();

        // Ensure standard venue staff users exist
        $staffUsersData = [
            ['username' => 'venuestaff', 'full_name' => 'Nhân viên sân Green Sport', 'email' => 'venuestaff@sportgo.vn'],
            ['username' => 'venuestaff1', 'full_name' => 'Nguyễn Văn An (NV Sân)', 'email' => 'an.nguyen@sportgo.vn'],
            ['username' => 'venuestaff2', 'full_name' => 'Trần Thị Bình (NV Sân)', 'email' => 'binh.tran@sportgo.vn'],
            ['username' => 'venuestaff3', 'full_name' => 'Lê Hoàng Cường (NV Sân)', 'email' => 'cuong.le@sportgo.vn'],
            ['username' => 'venuestaff4', 'full_name' => 'Phạm Minh Đức (NV Sân)', 'email' => 'duc.pham@sportgo.vn'],
            ['username' => 'venuestaff5', 'full_name' => 'Vũ Thu Trang (NV Sân)', 'email' => 'trang.vu@sportgo.vn'],
        ];

        $staffUsers = [];
        foreach ($staffUsersData as $staffData) {
            $user = User::query()->updateOrCreate(
                ['username' => $staffData['username']],
                [
                    'full_name' => $staffData['full_name'],
                    'email' => $staffData['email'],
                    'phone' => '0903' . rand(100000, 999999),
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ]
            );
            $staffUsers[] = $user;
        }

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        foreach ($clusters as $cluster) {
            // Assign staff users to cluster
            foreach ($staffUsers as $staffUser) {
                if (Schema::hasTable('venue_staff_assignments')) {
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
            }

            // 1. Seed Shifts Templates (Ca trực mẫu)
            $shiftMorning = VenueStaffShift::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => 'Ca Sáng',
                ],
                [
                    'start_time' => '06:00:00',
                    'end_time' => '12:00:00',
                    'description' => 'Ca trực buổi sáng chuẩn',
                    'is_active' => true,
                ]
            );

            $shiftAfternoon = VenueStaffShift::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => 'Ca Chiều',
                ],
                [
                    'start_time' => '12:00:00',
                    'end_time' => '18:00:00',
                    'description' => 'Ca trực buổi chiều chuẩn',
                    'is_active' => true,
                ]
            );

            $shiftEvening = VenueStaffShift::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => 'Ca Tối',
                ],
                [
                    'start_time' => '18:00:00',
                    'end_time' => '22:00:00',
                    'description' => 'Ca trực buổi tối chuẩn',
                    'is_active' => true,
                ]
            );

            // 2. Seed Shift Schedules for Yesterday
            if (isset($staffUsers[0])) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'user_id' => $staffUsers[0]->id,
                        'date' => $yesterday->toDateString(),
                        'venue_staff_shift_id' => $shiftMorning->id,
                    ],
                    [
                        'start_time' => '06:00:00',
                        'end_time' => '12:00:00',
                        'status' => 'checked_out',
                        'check_in_at' => $yesterday->copy()->setTime(5, 55, 0),
                        'check_out_at' => $yesterday->copy()->setTime(12, 5, 0),
                        'notes' => 'Chấm công hoàn thành đúng giờ',
                        'created_by' => $owner->id,
                    ]
                );
            }

            if (isset($staffUsers[1])) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'user_id' => $staffUsers[1]->id,
                        'date' => $yesterday->toDateString(),
                        'venue_staff_shift_id' => $shiftAfternoon->id,
                    ],
                    [
                        'start_time' => '12:00:00',
                        'end_time' => '18:00:00',
                        'status' => 'checked_out',
                        'check_in_at' => $yesterday->copy()->setTime(12, 10, 0),
                        'check_out_at' => $yesterday->copy()->setTime(18, 0, 0),
                        'notes' => 'Vào ca trễ 10p',
                        'created_by' => $owner->id,
                    ]
                );
            }

            // 3. Seed Shift Schedules for TODAY
            // staff 0: Ca sáng -> checked_out
            if (isset($staffUsers[0])) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'user_id' => $staffUsers[0]->id,
                        'date' => $today->toDateString(),
                        'venue_staff_shift_id' => $shiftMorning->id,
                    ],
                    [
                        'start_time' => '06:00:00',
                        'end_time' => '12:00:00',
                        'status' => 'checked_out',
                        'check_in_at' => $today->copy()->setTime(6, 0, 0),
                        'check_out_at' => $today->copy()->setTime(12, 0, 0),
                        'notes' => 'Ca sáng hoàn thành tốt',
                        'created_by' => $owner->id,
                    ]
                );
            }

            // staff 1: Ca sáng -> checked_in
            if (isset($staffUsers[1])) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'user_id' => $staffUsers[1]->id,
                        'date' => $today->toDateString(),
                        'venue_staff_shift_id' => $shiftMorning->id,
                    ],
                    [
                        'start_time' => '06:00:00',
                        'end_time' => '12:00:00',
                        'status' => 'checked_in',
                        'check_in_at' => $today->copy()->setTime(5, 58, 0),
                        'check_out_at' => null,
                        'notes' => 'Đã vào ca sáng',
                        'created_by' => $owner->id,
                    ]
                );
            }

            // staff 2: Ca chiều -> scheduled
            if (isset($staffUsers[2])) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'user_id' => $staffUsers[2]->id,
                        'date' => $today->toDateString(),
                        'venue_staff_shift_id' => $shiftAfternoon->id,
                    ],
                    [
                        'start_time' => '12:00:00',
                        'end_time' => '18:00:00',
                        'status' => 'scheduled',
                        'check_in_at' => null,
                        'check_out_at' => null,
                        'notes' => 'Chờ check-in ca chiều',
                        'created_by' => $owner->id,
                    ]
                );
            }

            // staff 3: Ca chiều -> checked_in
            if (isset($staffUsers[3])) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'user_id' => $staffUsers[3]->id,
                        'date' => $today->toDateString(),
                        'venue_staff_shift_id' => $shiftAfternoon->id,
                    ],
                    [
                        'start_time' => '12:00:00',
                        'end_time' => '18:00:00',
                        'status' => 'checked_in',
                        'check_in_at' => $today->copy()->setTime(11, 55, 0),
                        'check_out_at' => null,
                        'notes' => 'Đã vào ca chiều',
                        'created_by' => $owner->id,
                    ]
                );
            }

            // staff 4: Ca tối -> scheduled
            if (isset($staffUsers[4])) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'user_id' => $staffUsers[4]->id,
                        'date' => $today->toDateString(),
                        'venue_staff_shift_id' => $shiftEvening->id,
                    ],
                    [
                        'start_time' => '18:00:00',
                        'end_time' => '22:00:00',
                        'status' => 'scheduled',
                        'check_in_at' => null,
                        'check_out_at' => null,
                        'notes' => 'Ca tối',
                        'created_by' => $owner->id,
                    ]
                );
            }

            // staff 5: Ca đặc biệt -> checked_in
            if (isset($staffUsers[5])) {
                VenueStaffShiftSchedule::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'user_id' => $staffUsers[5]->id,
                        'date' => $today->toDateString(),
                        'venue_staff_shift_id' => null,
                    ],
                    [
                        'start_time' => '08:00:00',
                        'end_time' => '16:00:00',
                        'status' => 'checked_in',
                        'check_in_at' => $today->copy()->setTime(8, 0, 0),
                        'check_out_at' => null,
                        'notes' => 'Hỗ trợ giải đấu thể thao',
                        'created_by' => $owner->id,
                    ]
                );
            }

            // 4. Seed Shift Schedules for Days in Current Week
            $startOfWeek = $today->copy()->startOfWeek();
            for ($i = 0; $i < 7; $i++) {
                $dayDate = $startOfWeek->copy()->addDays($i);
                // Skip if yesterday or today already seeded
                if ($dayDate->toDateString() === $yesterday->toDateString() || $dayDate->toDateString() === $today->toDateString()) {
                    continue;
                }

                $status = $dayDate->isPast() ? 'checked_out' : 'scheduled';

                foreach ($staffUsers as $idx => $sUser) {
                    $shiftToAssign = ($idx % 3 === 0) ? $shiftMorning : (($idx % 3 === 1) ? $shiftAfternoon : $shiftEvening);

                    VenueStaffShiftSchedule::query()->updateOrCreate(
                        [
                            'venue_cluster_id' => $cluster->id,
                            'user_id' => $sUser->id,
                            'date' => $dayDate->toDateString(),
                            'venue_staff_shift_id' => $shiftToAssign->id,
                        ],
                        [
                            'start_time' => $shiftToAssign->start_time,
                            'end_time' => $shiftToAssign->end_time,
                            'status' => $status,
                            'check_in_at' => $status === 'checked_out' ? $dayDate->copy()->setTimeFromTimeString($shiftToAssign->start_time) : null,
                            'check_out_at' => $status === 'checked_out' ? $dayDate->copy()->setTimeFromTimeString($shiftToAssign->end_time) : null,
                            'notes' => 'Phân ca tự động theo tuần',
                            'created_by' => $owner->id,
                        ]
                    );
                }
            }
        }
    }
}

