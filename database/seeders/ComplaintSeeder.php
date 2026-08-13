<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\Media;
use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ComplaintSeeder extends Seeder
{
    public function run(): void
    {
        $khachhang = User::where('email', 'khachhang@sportgo.vn')->first() ?? User::find(22);
        $userClient = User::where('email', 'user@sportgo.vn')->first() ?? User::find(12);
        $admin = User::where('email', 'systemstaff@sportgo.vn')->first() ?? User::find(3) ?? User::find(1);
        $venueCluster = VenueCluster::first();

        $bookingKhachHang = Booking::where('customer_id', $khachhang?->id)->first();
        $bookingUser = Booking::where('customer_id', $userClient?->id)->first();

        // 1. Complaint for khachhang@sportgo.vn (Processing - Venue) with image evidence
        if ($khachhang) {
            $c1 = Complaint::updateOrCreate(
                [
                    'customer_id' => $khachhang->id,
                    'content' => 'Phản ánh hệ thống đèn chiếu sáng sân cầu lông bị nhấp nháy trong ca chơi 17:30 - 18:30.',
                ],
                [
                    'complaint_type' => 'venue',
                    'is_vip_priority' => false,
                    'booking_id' => $bookingKhachHang?->id,
                    'venue_cluster_id' => $bookingKhachHang?->venue_cluster_id ?? $venueCluster?->id,
                    'status' => 'processing',
                    'assigned_to' => $admin?->id,
                    'created_at' => Carbon::now()->subHours(5),
                    'updated_at' => Carbon::now()->subHours(2),
                ]
            );

            // Evidence images for C1 (2 images)
            Media::updateOrCreate(
                [
                    'mediable_type' => Complaint::class,
                    'mediable_id' => $c1->id,
                    'file_name' => 'anh-su-co-den-san.webp',
                ],
                [
                    'collection' => 'complaint_evidence',
                    'file_path' => '/images/home/badminton-cover.webp',
                    'mime_type' => 'image/webp',
                    'file_size' => 70024,
                    'sort_order' => 0,
                ]
            );

            Media::updateOrCreate(
                [
                    'mediable_type' => Complaint::class,
                    'mediable_id' => $c1->id,
                    'file_name' => 'anh-san-bong-toi.png',
                ],
                [
                    'collection' => 'complaint_evidence',
                    'file_path' => '/images/home/anhbia2.webp',
                    'mime_type' => 'image/webp',
                    'file_size' => 82000,
                    'sort_order' => 1,
                ]
            );

            ComplaintReply::updateOrCreate(
                [
                    'complaint_id' => $c1->id,
                    'content' => 'Chào bạn, SportGo đã ghi nhận sự cố đèn tại sân và đã yêu cầu bộ phận kỹ thuật cụm sân kiểm tra sửa chữa khẩn cấp.',
                ],
                [
                    'user_id' => $admin?->id ?? 1,
                    'created_at' => Carbon::now()->subHours(3),
                ]
            );

            // 2. Complaint for khachhang@sportgo.vn (Resolved - System) with VietQR proof image
            $c2 = Complaint::updateOrCreate(
                [
                    'customer_id' => $khachhang->id,
                    'content' => 'Hỗ trợ kiểm tra mã giao dịch thanh toán VietQR chuyển khoản chưa thấy cập nhật tự động.',
                ],
                [
                    'complaint_type' => 'system',
                    'is_vip_priority' => true,
                    'booking_id' => $bookingKhachHang?->id,
                    'venue_cluster_id' => null,
                    'status' => 'resolved',
                    'assigned_to' => $admin?->id,
                    'resolved_by' => $admin?->id,
                    'resolve_note' => 'Hệ thống đã đối soát mã giao dịch VietQR thành công và cập nhật trạng thái đơn đặt sân.',
                    'resolved_at' => Carbon::now()->subDays(1),
                    'created_at' => Carbon::now()->subDays(2),
                    'updated_at' => Carbon::now()->subDays(1),
                ]
            );

            Media::updateOrCreate(
                [
                    'mediable_type' => Complaint::class,
                    'mediable_id' => $c2->id,
                    'file_name' => 'bien-lai-vietqr.png',
                ],
                [
                    'collection' => 'complaint_evidence',
                    'file_path' => '/images/partner_illus_qr_payment.png',
                    'mime_type' => 'image/png',
                    'file_size' => 84000,
                    'sort_order' => 0,
                ]
            );

            ComplaintReply::updateOrCreate(
                [
                    'complaint_id' => $c2->id,
                    'content' => 'Dạ chào bạn, giao dịch chuyển khoản VietQR của bạn đã được đối soát thành công và hệ thống đã cập nhật xác nhận đơn đặt sân. Chúc bạn có trải nghiệm chơi vui vẻ!',
                ],
                [
                    'user_id' => $admin?->id ?? 1,
                    'created_at' => Carbon::now()->subDays(1),
                ]
            );

            // 3. Complaint for khachhang@sportgo.vn (Open - Venue) with image
            $c3 = Complaint::updateOrCreate(
                [
                    'customer_id' => $khachhang->id,
                    'content' => 'Đề nghị hỗ trợ kiểm tra vệ sinh mặt sân và chuẩn bị sẵn thảm lau chân trước ca chơi tối nay.',
                ],
                [
                    'complaint_type' => 'venue',
                    'is_vip_priority' => false,
                    'booking_id' => $bookingKhachHang?->id,
                    'venue_cluster_id' => $bookingKhachHang?->venue_cluster_id ?? $venueCluster?->id,
                    'status' => 'open',
                    'assigned_to' => null,
                    'created_at' => Carbon::now()->subMinutes(30),
                    'updated_at' => Carbon::now()->subMinutes(30),
                ]
            );

            Media::updateOrCreate(
                [
                    'mediable_type' => Complaint::class,
                    'mediable_id' => $c3->id,
                    'file_name' => 'anh-ve-sinh-san.webp',
                ],
                [
                    'collection' => 'complaint_evidence',
                    'file_path' => '/images/home/sportgo-home-hero-v2.webp',
                    'mime_type' => 'image/webp',
                    'file_size' => 95000,
                    'sort_order' => 0,
                ]
            );
        }

        // 4. Complaints for user@sportgo.vn
        if ($userClient) {
            $c4 = Complaint::updateOrCreate(
                [
                    'customer_id' => $userClient->id,
                    'content' => 'Sân mở cửa muộn 10 phút so with giờ đặt trên ứng dụng.',
                ],
                [
                    'complaint_type' => 'venue',
                    'is_vip_priority' => false,
                    'booking_id' => $bookingUser?->id,
                    'venue_cluster_id' => $bookingUser?->venue_cluster_id ?? $venueCluster?->id,
                    'status' => 'resolved',
                    'assigned_to' => $admin?->id,
                    'resolved_by' => $admin?->id,
                    'resolve_note' => 'Nhân viên sân đã làm việc với khách và hỗ trợ tặng nước uống đền bù thời gian.',
                    'resolved_at' => Carbon::now()->subDays(3),
                    'created_at' => Carbon::now()->subDays(4),
                    'updated_at' => Carbon::now()->subDays(3),
                ]
            );

            Media::updateOrCreate(
                [
                    'mediable_type' => Complaint::class,
                    'mediable_id' => $c4->id,
                    'file_name' => 'anh-san-dong-cua.png',
                ],
                [
                    'collection' => 'complaint_evidence',
                    'file_path' => '/images/about_hero.png',
                    'mime_type' => 'image/png',
                    'file_size' => 110000,
                    'sort_order' => 0,
                ]
            );

            ComplaintReply::updateOrCreate(
                [
                    'complaint_id' => $c4->id,
                    'content' => 'SportGo xin lỗi bạn vì sự cố nhân viên mở cửa trễ. Quản lý cụm sân đã gửi tặng bạn nước uống miễn phí tại quầy.',
                ],
                [
                    'user_id' => $admin?->id ?? 1,
                    'created_at' => Carbon::now()->subDays(3),
                ]
            );
        }
    }
}
