import { createId, platformFeeStore } from '../stores/platformFee.store.js';

function findVenue(venueId) {
  return platformFeeStore.state.venues.find((venue) => venue.id === venueId);
}

function addOwnerNotification(venueId, title, content, link = '/owner/platform-fees') {
  const venue = findVenue(venueId);
  const notification = {
    id: createId('notification'),
    owner_id: venue?.owner?.id || null,
    venue_cluster_id: venueId,
    title,
    content,
    link,
    read_at: null,
    created_at: new Date().toISOString(),
  };

  platformFeeStore.state.notifications.unshift(notification);
  platformFeeStore.save();
  return notification;
}

export function notifyOwnerPlatformFeeCreated(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Kỳ phí duy trì mới',
    `Kỳ phí của cụm sân ${venue?.name || ''} đã được tạo và đang chờ thanh toán.`,
  );
}

export function notifyOwnerPlatformFeePaid(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Đã ghi nhận thanh toán phí duy trì',
    `Kỳ phí của cụm sân ${venue?.name || ''} đã được xác nhận thanh toán.`,
  );
}

export function notifyOwnerPlatformFeeOverdue(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phí duy trì đã quá hạn',
    `Kỳ phí của cụm sân ${venue?.name || ''} đã quá hạn thanh toán.`,
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
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phí duy trì sắp đến hạn',
    `Kỳ phí của cụm sân ${venue?.name || ''} sẽ đến hạn sau 7 ngày.`,
  );
}

export function notifyOwnerFeeDueToday(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Hôm nay là hạn đóng phí duy trì',
    `Vui lòng thanh toán kỳ phí của cụm sân ${venue?.name || ''} trong hôm nay.`,
  );
}

export function notifyOwnerFeeOverdueThreeDays(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phí duy trì đã quá hạn 3 ngày',
    `Cụm sân ${venue?.name || ''} có thể bị khóa nếu kỳ phí chưa được xử lý.`,
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
