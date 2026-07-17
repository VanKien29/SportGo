const TERMINATING_STATUSES = new Set(['termination_locked', 'termination_processing']);

function normalizeText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase();
}

export function venuePartnerState(cluster) {
  const status = String(cluster?.status || '');
  if (status === 'partner_terminated') return 'archived';
  if (TERMINATING_STATUSES.has(status)) return 'terminating';
  if (status !== 'locked') return 'normal';

  const reason = normalizeText(cluster?.status_reason);
  if (!reason.includes('cham dut')) return 'normal';

  return reason.includes('hoan tat') || reason.includes('thu hoi quyen')
    ? 'archived'
    : 'terminating';
}

export function venueDisplayStatus(cluster) {
  const partnerState = venuePartnerState(cluster);
  if (partnerState === 'archived') return 'partner_terminated';
  if (partnerState === 'terminating') return 'termination_processing';
  return String(cluster?.status || '');
}
