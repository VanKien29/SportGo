import { createId, platformFeeStore } from '../stores/platformFee.store.js';

export function generatePlatformFeeReceipt(ledger) {
  const venue = platformFeeStore.state.venues.find((item) => item.id === ledger.venue_cluster_id);
  const receipt = {
    id: createId('receipt'),
    code: `RC-${new Date().getFullYear()}-${String(platformFeeStore.state.receipts.length + 1).padStart(4, '0')}`,
    ledger_id: ledger.id,
    venue_cluster_id: ledger.venue_cluster_id,
    owner_id: venue?.owner?.id || null,
    amount: ledger.amount_paid,
    issued_at: new Date().toISOString(),
    content: `Phieu thu phi duy tri nen tang cho ${venue?.name || ledger.venue_cluster_id}`,
  };

  platformFeeStore.state.receipts.unshift(receipt);
  platformFeeStore.save();
  return receipt;
}

export const financeReceiptService = { generatePlatformFeeReceipt };
