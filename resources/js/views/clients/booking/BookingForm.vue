<template>
    <ClientBookingWorkspace />
</template>

<script>
import ClientBookingWorkspace from "./ClientBookingWorkspace.vue";

export default {
    name: "BookingForm",
    components: { ClientBookingWorkspace },
};
</script>

<style scoped>
.booking-container {
    min-height: 100vh;
    background: #09090b;
    color: #ffffff;
    overflow-x: hidden;
}

.booking-main {
    width: min(100%, 1440px);
    margin: 0 auto;
    padding: 88px 16px 36px;
}

.booking-header {
    margin-bottom: 32px;
}

.page-title {
    font-size: 32px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.page-desc {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 8px;
}

.booking-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 20px;
    align-items: start;
}

.form-section,
.summary-section {
    min-width: 0;
}

.card {
    background: rgba(255, 255, 255, 0.03);
    border-radius: var(--sg-radius);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: none;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #ffffff;
    color: #09090b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.card-header h2 {
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 6px;
}

.form-control {
    width: 100%;
    height: 42px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0 14px;
    font-size: 14px;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.04);
    transition: var(--sg-transition);
}

.form-control:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.25);
    box-shadow: none;
}

.form-control option {
    background: #18181b;
    color: #ffffff;
}

.form-control:disabled {
    color: #64748b;
    background: #f1f5f9;
    cursor: not-allowed;
}

.locked-hint {
    display: block;
    margin-top: 6px;
    color: #059669;
    font-size: 12px;
    font-weight: 700;
}

.time-range-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.availability-status {
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
}

.schedule-card {
    overflow: hidden;
    min-width: 0;
}

.schedule-header {
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.schedule-header > div:first-child {
    display: flex;
    align-items: center;
    gap: 12px;
}

.schedule-filter {
    width: 220px;
}

.schedule-filter label {
    display: block;
    margin-bottom: 6px;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
}

.schedule-controls {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 160px 200px;
    gap: 14px;
    margin-bottom: 14px;
    padding: 12px;
    border-radius: var(--sg-radius-sm);
    background: rgba(255, 255, 255, 0.02);
}

.schedule-legend {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    color: rgba(255, 255, 255, 0.4);
    font-size: 12px;
}

.schedule-legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
}

.schedule-legend i {
    width: 14px;
    height: 14px;
    display: inline-block;
    border-radius: 4px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.legend-free {
    background: rgba(255, 255, 255, 0.03);
}

.legend-busy {
    background: rgba(255, 255, 255, 0.1);
}

.legend-selected {
    background: #ffffff;
}

.schedule-state {
    padding: 28px 16px;
    border-radius: var(--sg-radius-sm);
    background: rgba(255, 255, 255, 0.02);
    color: rgba(255, 255, 255, 0.4);
    font-size: 13px;
    font-weight: 700;
    text-align: center;
}

.schedule-state.error {
    background: rgba(239, 68, 68, 0.05);
    color: var(--sg-danger);
}

.schedule-wrap {
    overflow: auto;
    max-width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--sg-radius-sm);
    background: rgba(255, 255, 255, 0.02);
    overscroll-behavior-x: contain;
}

.schedule-grid {
    display: grid;
    min-width: max-content;
}

.schedule-head,
.schedule-court,
.schedule-cell {
    min-height: 32px;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.schedule-head {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.6);
    font-size: 10px;
    font-weight: 800;
}

.time-head {
    min-width: 36px;
}

.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
}

.schedule-court {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    padding: 6px 8px;
    background: rgba(255, 255, 255, 0.02);
}

.schedule-court strong {
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
}

.schedule-court span {
    color: rgba(255, 255, 255, 0.4);
    font-size: 10px;
    white-space: nowrap;
}

.schedule-cell {
    width: 36px;
    background: rgba(255, 255, 255, 0.03);
    border: none;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    transition: background 0.15s;
}

.schedule-cell.never-hover-class-placeholder:not(.busy) {
    background: rgba(255, 255, 255, 0.15);
}

.schedule-cell.busy {
    background: rgba(255, 255, 255, 0.08);
    cursor: not-allowed;
}

.schedule-cell.past {
    background:
        repeating-linear-gradient(
            -45deg,
            #f1f5f9,
            #f1f5f9 6px,
            #e2e8f0 6px,
            #e2e8f0 12px
        );
    cursor: not-allowed;
}

.schedule-cell.selected {
    background: #ffffff;
    box-shadow: inset 0 0 0 2px #ffffff;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: var(--sg-radius-sm);
    font-size: 13px;
    font-weight: 600;
}

.status-badge.success {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.status-badge.danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--sg-danger);
}

.voucher-picker-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.voucher-column {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.voucher-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
    color: var(--sg-dark);
}

.voucher-heading span {
    color: var(--sg-text-muted);
    font-weight: 700;
}

.voucher-card {
    width: 100%;
    min-height: 78px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 4px 12px;
    align-items: center;
    padding: 12px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-border);
    background: #fff;
    text-align: left;
    transition: var(--sg-transition);
}

.voucher-card.never-hover-class-placeholder {
    background: var(--sg-surface);
    border-color: var(--sg-green-light);
}

.voucher-card.active {
    background: var(--sg-green-pale);
    border-color: var(--sg-green);
}

.voucher-name {
    min-width: 0;
    color: var(--sg-dark);
    font-size: 13px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.voucher-meta {
    min-width: 0;
    color: var(--sg-text-muted);
    font-size: 12px;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.voucher-discount {
    grid-row: 1 / span 2;
    grid-column: 2;
    color: #047857;
    font-size: 13px;
    font-weight: 900;
    white-space: nowrap;
}

.voucher-empty,
.voucher-state {
    margin: 0;
    padding: 14px;
    border-radius: var(--sg-radius-sm);
    background: var(--sg-surface);
    color: var(--sg-text-muted);
    font-size: 13px;
    font-weight: 700;
}

.voucher-state.error {
    background: #fef2f2;
    color: var(--sg-danger);
}

.payment-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-option-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.02);
    cursor: pointer;
    transition: var(--sg-transition);
}

.payment-option-card.never-hover-class-placeholder {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.2);
}

.payment-option-card.active {
    background: rgba(255, 255, 255, 0.08);
    border-color: #ffffff;
}

.option-info {
    display: flex;
    flex-direction: column;
}

.option-title {
    font-weight: 700;
    font-size: 14px;
    color: #ffffff;
}

.option-desc {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 4px;
}

.hidden-radio {
    display: none;
}

/* Summary Panel */
.sticky-card {
    position: sticky;
    top: 84px;
}

.summary-card h2 {
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 16px;
}

.divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: 16px 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 14px;
}

.summary-row .label {
    color: rgba(255, 255, 255, 0.4);
}

.summary-row .val {
    font-weight: 600;
    color: #ffffff;
}

.total-row {
    margin-top: 16px;
    font-size: 16px;
}

.total-row .price {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}

.discount-row .label,
.discount-row .val {
    color: #047857;
    font-weight: 800;
}

.discount-row small {
    color: #059669;
    font-size: 11px;
    font-weight: 800;
}

.deposit-row {
    margin-top: 8px;
    font-size: 14px;
}

.deposit-row .required-price {
    font-size: 16px;
    font-weight: 800;
    color: #ffffff;
}

.btn-voucher-summary {
    width: 100%;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    margin: 4px 0 12px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-green-light);
    background: var(--sg-green-pale);
    color: var(--sg-dark);
    text-align: left;
    transition: var(--sg-transition);
}

.btn-voucher-summary.never-hover-class-placeholder:not(:disabled) {
    border-color: var(--sg-green);
    box-shadow: 0 4px 14px rgba(34, 197, 94, 0.12);
}

.btn-voucher-summary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-voucher-summary span,
.btn-voucher-summary strong {
    font-size: 13px;
    font-weight: 900;
}

.btn-voucher-summary strong {
    color: #047857;
    white-space: nowrap;
}

.btn-voucher-summary small {
    color: var(--sg-text-muted);
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.btn-submit {
    width: 100%;
    height: 48px;
    border-radius: var(--sg-radius);
    background: #ffffff;
    color: #09090b;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 24px;
    box-shadow: none;
    transition: var(--sg-transition);
}

.btn-submit.never-hover-class-placeholder:not(:disabled) {
    background: rgba(255, 255, 255, 0.88);
    transform: translateY(-1px);
}

.btn-submit:disabled {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.3);
    box-shadow: none;
    cursor: not-allowed;
}

.hold-notice {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.3);
    margin-top: 14px;
    line-height: 1.5;
    text-align: center;
}

.error-msg {
    padding: 10px 14px;
    background: rgba(239, 68, 68, 0.05);
    border-radius: var(--sg-radius-sm);
    color: var(--sg-danger);
    font-size: 13px;
    font-weight: 500;
    margin-top: 14px;
}

.voucher-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15, 23, 42, 0.45);
}

.voucher-modal {
    width: min(860px, 100%);
    max-height: min(760px, calc(100vh - 36px));
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: var(--sg-radius);
    background: #fff;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
}

.voucher-modal-header,
.voucher-modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--sg-border);
}

.voucher-modal-header h2 {
    margin: 0;
    color: var(--sg-dark);
    font-size: 18px;
    font-weight: 900;
}

.voucher-modal-header p,
.voucher-modal-footer span {
    margin: 4px 0 0;
    color: var(--sg-text-muted);
    font-size: 12px;
    font-weight: 800;
}

.voucher-modal-close {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: var(--sg-text-muted);
    font-size: 28px;
    line-height: 1;
}

.voucher-modal-footer {
    border-top: 1px solid var(--sg-border);
    border-bottom: 0;
}

.voucher-modal-footer button {
    min-width: 86px;
    height: 38px;
    border-radius: var(--sg-radius-sm);
    background: var(--sg-green);
    color: #fff;
    font-size: 13px;
    font-weight: 900;
}

.voucher-table-wrap {
    overflow: auto;
    padding: 14px 18px 18px;
}

.voucher-table-section + .voucher-table-section {
    margin-top: 18px;
}

.voucher-table-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.voucher-table-title strong {
    color: var(--sg-dark);
    font-size: 14px;
    font-weight: 900;
}

.voucher-table-title button {
    color: var(--sg-green-dark);
    font-size: 12px;
    font-weight: 900;
}

.voucher-table-title button:disabled {
    color: var(--sg-text-muted);
    cursor: not-allowed;
}

.voucher-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid var(--sg-border);
    border-radius: var(--sg-radius-sm);
    overflow: hidden;
}

.voucher-table th,
.voucher-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--sg-border);
    color: var(--sg-text);
    font-size: 12px;
    text-align: left;
    vertical-align: middle;
}

.voucher-table th {
    background: var(--sg-surface);
    color: var(--sg-text-muted);
    font-weight: 900;
}

.voucher-table tr:last-child td {
    border-bottom: 0;
}

.voucher-table tr.active td {
    background: var(--sg-green-pale);
}

.voucher-table td span,
.voucher-table td small {
    display: block;
}

.voucher-table td span {
    color: var(--sg-dark);
    font-weight: 800;
}

.voucher-table td small {
    margin-top: 3px;
    color: var(--sg-text-muted);
    font-weight: 700;
}

.voucher-table-discount {
    color: #047857 !important;
    font-weight: 900;
    white-space: nowrap;
}

.voucher-select-btn {
    min-width: 76px;
    height: 32px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-green-light);
    color: var(--sg-green-dark);
    font-size: 12px;
    font-weight: 900;
}

.voucher-table tr.active .voucher-select-btn {
    background: var(--sg-green);
    color: #fff;
    border-color: var(--sg-green);
}

.voucher-table-empty {
    color: var(--sg-text-muted) !important;
    font-weight: 800;
    text-align: center !important;
}

/* Loading state */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 0;
    color: rgba(255, 255, 255, 0.4);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.1);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 16px;
}

.spinner-small {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 900px) {
    .booking-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .sticky-card {
        position: static;
    }
    .schedule-controls {
        grid-template-columns: 1fr;
    }
    .voucher-picker-grid {
        grid-template-columns: 1fr;
    }
    .voucher-modal-backdrop {
        align-items: flex-end;
        padding: 10px;
    }
    .voucher-modal {
        max-height: calc(100vh - 20px);
    }
    .voucher-table {
        min-width: 620px;
    }
}
</style>
