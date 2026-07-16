<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueStaffShift;
use App\Models\VenueStaffShiftSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
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

        $owner = User::query()->where('username', 'owner')->first();
        $staff = User::query()->where('username', 'venuestaff')->first();
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();

        if (! $owner || ! $staff || ! $cluster) {
            return;
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

        // 2. Seed Shift Schedules (Lịch trực thực tế & Chấm công)
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Yesterday shift - checked out (Đã hoàn thành) for main staff
        VenueStaffShiftSchedule::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'user_id' => $staff->id,
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

        // Fetch other created staff members
        $staff1 = User::query()->where('username', 'venuestaff1')->first();
        $staff2 = User::query()->where('username', 'venuestaff2')->first();
        $staff3 = User::query()->where('username', 'venuestaff3')->first();
        $staff4 = User::query()->where('username', 'venuestaff4')->first();
        $staff5 = User::query()->where('username', 'venuestaff5')->first();

        // --- TODAY SHIFTS (HÔM NAY) ---
        
        // 1. main staff: Ca Sáng (06:00 - 12:00) -> checked_out
        VenueStaffShiftSchedule::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'user_id' => $staff->id,
                'date' => $today->toDateString(),
                'venue_staff_shift_id' => $shiftMorning->id,
            ],
            [
                'start_time' => '06:00:00',
                'end_time' => '12:00:00',
                'status' => 'checked_out',
                'check_in_at' => $today->copy()->setTime(6, 2, 0),
                'check_out_at' => $today->copy()->setTime(12, 0, 0),
                'notes' => 'Ca sáng hoàn thành',
                'created_by' => $owner->id,
            ]
        );

        // 2. staff1: Ca Sáng (06:00 - 12:00) -> checked_in
        if ($staff1) {
            VenueStaffShiftSchedule::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'user_id' => $staff1->id,
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

        // 3. staff2: Ca Chiều (12:00 - 18:00) -> scheduled
        if ($staff2) {
            VenueStaffShiftSchedule::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'user_id' => $staff2->id,
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

        // 4. staff3: Ca Chiều (12:00 - 18:00) -> checked_in
        if ($staff3) {
            VenueStaffShiftSchedule::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'user_id' => $staff3->id,
                    'date' => $today->toDateString(),
                    'venue_staff_shift_id' => $shiftAfternoon->id,
                ],
                [
                    'start_time' => '12:00:00',
                    'end_time' => '18:00:00',
                    'status' => 'checked_in',
                    'check_in_at' => $today->copy()->setTime(11, 55, 0),
                    'check_out_at' => null,
                    'notes' => 'Ca chiều làm thay',
                    'created_by' => $owner->id,
                ]
            );
        }

        // 5. staff4: Ca Tối (18:00 - 22:00) -> scheduled
        if ($staff4) {
            VenueStaffShiftSchedule::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'user_id' => $staff4->id,
                    'date' => $today->toDateString(),
                    'venue_staff_shift_id' => $shiftEvening->id,
                ],
                [
                    'start_time' => '18:00:00',
                    'end_time' => '22:00:00',
                    'status' => 'scheduled',
                    'check_in_at' => null,
                    'check_out_at' => null,
                    'notes' => 'Trực tối',
                    'created_by' => $owner->id,
                ]
            );
        }

        // 6. staff5: Ca Đặc Biệt (08:00 - 16:00) -> checked_in (Không dùng template mẫu)
        if ($staff5) {
            VenueStaffShiftSchedule::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'user_id' => $staff5->id,
                    'date' => $today->toDateString(),
                    'venue_staff_shift_id' => null, // ca riêng
                ],
                [
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'status' => 'checked_in',
                    'check_in_at' => $today->copy()->setTime(8, 0, 0),
                    'check_out_at' => null,
                    'notes' => 'Hỗ trợ sự kiện đặc biệt tại sân',
                    'created_by' => $owner->id,
                ]
            );
        }

        // Tomorrow shift - scheduled (Lên lịch trước ngày mai)
        VenueStaffShiftSchedule::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'user_id' => $staff->id,
                'date' => $today->copy()->addDay()->toDateString(),
                'venue_staff_shift_id' => $shiftEvening->id,
            ],
            [
                'start_time' => '18:00:00',
                'end_time' => '22:00:00',
                'status' => 'scheduled',
                'check_in_at' => null,
                'check_out_at' => null,
                'notes' => 'Ca trực tối mai',
                'created_by' => $owner->id,
            ]
        );
    }
}
