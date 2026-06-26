function addOwnerNotification(venueId, title, content, link = '/owner/platform-fees') {
  return {
    id: null,
    owner_id: null,
    venue_cluster_id: venueId,
    title,
    content,
    link,
    read_at: null,
    created_at: new Date().toISOString(),
    persisted: false,
    message: 'Notification phí nền tảng không còn ghi local. Cần API notifications nếu bật lại chức năng này.',
  };
}

export function notifyOwnerPlatformFeeCreated(ledger) {
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Kỳ phí duy trì mới',
    `Kỳ phí của cụm sân ${ledger.venue?.name || ''} đã được tạo và đang chờ thanh toán.`,
  );
}

export function notifyOwnerPlatformFeePaid(ledger) {
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Đã ghi nhận thanh toán phí duy trì',
    `Kỳ phí của cụm sân ${ledger.venue?.name || ''} đã được xác nhận thanh toán.`,
  );
}

export function notifyOwnerPlatformFeeOverdue(ledger) {
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phí duy trì đã quá hạn',
    `Kỳ phí của cụm sân ${ledger.venue?.name || ''} đã quá hạn thanh toán.`,
  );
}

export function notifyOwnerVenueLocked(venue) {
  return addOwnerNotification(
    venue.id,
    'Cụm sân đã bị khóa',
    `Cụm sân ${venue.name} đã bị khóa do quá hạn phí duy trì hệ thống.`,
  );
}

export function notifyOwnerVenueUnlocked(venue) {
  return addOwnerNotification(
    venue.id,
    'Cụm sân đã được mở khóa',
    `Cụm sân ${venue.name} đã được mở khóa sau khi xử lý phí duy trì.`,
  );
}

export function notifyOwnerFeeDueSoon(ledger) {
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phí duy trì sắp đến hạn',
    `Kỳ phí của cụm sân ${ledger.venue?.name || ''} sẽ đến hạn sau 7 ngày.`,
  );
}

export function notifyOwnerFeeDueToday(ledger) {
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Hôm nay là hạn đóng phí duy trì',
    `Vui lòng thanh toán kỳ phí của cụm sân ${ledger.venue?.name || ''} trong hôm nay.`,
  );
}

export function notifyOwnerFeeOverdueThreeDays(ledger) {
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phí duy trì đã quá hạn 3 ngày',
    `Cụm sân ${ledger.venue?.name || ''} có thể bị khóa nếu kỳ phí chưa được xử lý.`,
  );
}

export const notificationService = {
  notifyOwnerPlatformFeeCreated,
  notifyOwnerPlatformFeePaid,
  notifyOwnerPlatformFeeOverdue,
  notifyOwnerVenueLocked,
  notifyOwnerVenueUnlocked,
  notifyOwnerFeeDueSoon,
  notifyOwnerFeeDueToday,
  notifyOwnerFeeOverdueThreeDays,
};
