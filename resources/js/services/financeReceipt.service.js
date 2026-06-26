export function generatePlatformFeeReceipt(ledger) {
  return ledger.internal_receipt || {
    id: ledger.internal_receipt_id || null,
    code: ledger.receipt_code || null,
    ledger_id: ledger.id,
    venue_cluster_id: ledger.venue_cluster_id,
    owner_id: ledger.venue?.owner_id || null,
    amount: ledger.amount_paid,
    issued_at: ledger.paid_at || new Date().toISOString(),
    content: `Phiếu thu phí duy trì nền tảng cho ${ledger.venue?.name || ledger.venue_cluster_id}`,
    persisted: Boolean(ledger.internal_receipt_id || ledger.receipt_code || ledger.internal_receipt),
    message: 'Phiếu thu phí nền tảng được tạo ở BE khi xác nhận thanh toán, service FE không còn ghi local.',
  };
}

export const financeReceiptService = { generatePlatformFeeReceipt };
