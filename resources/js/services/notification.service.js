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
    'Ky phi duy tri moi',
    `Ky phi cua cum san ${venue?.name || ''} da duoc tao va dang cho thanh toan.`,
  );
}

export function notifyOwnerPlatformFeePaid(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Da ghi nhan thanh toan phi duy tri',
    `Ky phi cua cum san ${venue?.name || ''} da duoc xac nhan thanh toan.`,
  );
}

export function notifyOwnerPlatformFeeOverdue(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phi duy tri da qua han',
    `Ky phi cua cum san ${venue?.name || ''} da qua han thanh toan.`,
  );
}

export function notifyOwnerVenueLocked(venue) {
  return addOwnerNotification(
    venue.id,
    'Cum san da bi khoa',
    `Cum san ${venue.name} da bi khoa do qua han phi duy tri he thong.`,
  );
}

export function notifyOwnerVenueUnlocked(venue) {
  return addOwnerNotification(
    venue.id,
    'Cum san da duoc mo khoa',
    `Cum san ${venue.name} da duoc mo khoa sau khi xu ly phi duy tri.`,
  );
}

export function notifyOwnerFeeDueSoon(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phi duy tri sap den han',
    `Ky phi cua cum san ${venue?.name || ''} se den han sau 7 ngay.`,
  );
}

export function notifyOwnerFeeDueToday(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Hom nay la han dong phi duy tri',
    `Vui long thanh toan ky phi cua cum san ${venue?.name || ''} trong hom nay.`,
  );
}

export function notifyOwnerFeeOverdueThreeDays(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return addOwnerNotification(
    ledger.venue_cluster_id,
    'Phi duy tri da qua han 3 ngay',
    `Cum san ${venue?.name || ''} co the bi khoa neu ky phi chua duoc xu ly.`,
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
