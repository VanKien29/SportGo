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

  // Gọi thêm dịch vụ tại sân vào booking
  addServices(id, data) {
    return api(`/api/bookings/${id}/services`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  listBookings(params = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        query.append(key, value);
      }
    });

    const suffix = query.toString();
    return api(`/api/bookings${suffix ? `?${suffix}` : ''}`, {
      cache: 'no-store',
      dedupe: false,
    });
  },

  // Lấy chi tiết đơn đặt sân
  getBooking(id, options = {}) {
    return api(`/api/bookings/${id}`, {
      cache: 'no-store',
      // Chi tiết booking không nên dùng chung promise với một lần tải cũ.
      // Nếu request trước bị kẹt, lần mở lại vẫn phải tạo request mới.
      dedupe: false,
      ...options,
    });
  },

  getRecurringGroup(groupCode) {
    return api(`/api/bookings/recurring-groups/${encodeURIComponent(groupCode)}`);
  },

  previewRecurringBooking(data) {
    return api('/api/bookings/recurring/preview', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  createRecurringBooking(data) {
    return api('/api/bookings/recurring', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  changeBookingCourt(id, data) {
    return api(`/api/bookings/${id}/court`, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
  },

  rescheduleBooking(id, data) {
    return api(`/api/bookings/${id}/reschedule`, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
  },

  cancelBookingItems(id, data) {
    return api(`/api/bookings/${id}/cancel-items`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  cancelBooking(id, reason = '') {
    return api(`/api/bookings/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },

  previewCancellation(id) {
    return api(`/api/bookings/${id}/cancel/preview`, { method: 'POST' });
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

  getPaymentReceipt(id) {
    return api(`/api/payments/${id}/receipt`);
  },

  getWallet() {
    return api('/api/user/wallet');
  },

  requestWithdrawal(data) {
    return api('/api/user/wallet/withdraw', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  listRefunds(params = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') query.append(key, value);
    });
    const suffix = query.toString();
    return api(`/api/refunds${suffix ? `?${suffix}` : ''}`, {
      cache: 'no-store',
      dedupe: false,
    });
  },

  requestRefund(data) {
    return api('/api/refunds', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  getRefund(id) {
    return api(`/api/refunds/${id}`);
  },
};
