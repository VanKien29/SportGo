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

  // Giả lập thanh toán thành công
  simulatePayment(id, method) {
    return api(`/api/bookings/${id}/simulate-payment`, {
      method: 'POST',
      body: JSON.stringify({ method }),
    });
  },
};
