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

  // Tạo link thanh toán VNPAY cho đơn đặt sân
  createVnpayPayment(id) {
    return api(`/api/bookings/${id}/payments/vnpay`, {
      method: 'POST',
    });
  },
};
