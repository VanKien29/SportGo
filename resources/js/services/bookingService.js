import { api } from './api.js';

export const bookingService = {
  // Lấy dữ liệu khởi tạo (danh sách cụm sân và sân con) để đặt sân
  getInitData() {
    return api('/api/bookings/init');
  },

  // Kiểm tra lịch trống của sân
  checkAvailability(params) {
    const query = new URLSearchParams(params).toString();
    return api(`/api/bookings/check-availability?${query}`);
  },

  // Lấy lịch ngày dạng interval để FE sinh bảng 30 phút
  getSchedule(params) {
    const query = new URLSearchParams(params).toString();
    return api(`/api/bookings/schedule?${query}`);
  },

  // Lấy voucher đủ điều kiện cho slot đang chọn
  eligibleVouchers(params) {
    const query = new URLSearchParams(params).toString();
    return api(`/api/bookings/eligible-vouchers?${query}`);
  },

  // Tạo đơn đặt sân mới
  createBooking(data) {
    return api('/api/bookings', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  // Lấy chi tiết đơn đặt sân
  getBooking(id) {
    return api(`/api/bookings/${id}`);
  },

  // Tạo thông tin thanh toán SePay cho đơn đặt sân
  createSepayPayment(id) {
    return api(`/api/bookings/${id}/payments/sepay`, {
      method: 'POST',
    });
  },

  // Hủy thanh toán đang chờ và giải phóng đơn đặt sân
  cancelPayment(id) {
    return api(`/api/bookings/${id}/payments/cancel`, {
      method: 'POST',
    });
  },
};
