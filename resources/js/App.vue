<template>
  <router-view />
  <SetPasswordModal v-if="showSetPasswordModal" @done="handlePasswordSetupDone" />
  <PolicyAcceptanceModal
    v-else-if="requiredPolicies.length"
    :policies="requiredPolicies"
    @accepted="handlePoliciesAccepted"
  />
  <FloatingActions />
</template>

<script>
import PolicyAcceptanceModal from './components/PolicyAcceptanceModal.vue';
import SetPasswordModal from './components/SetPasswordModal.vue';
import FloatingActions from './components/FloatingActions.vue';
import { getAuth, needsPasswordSetup } from './stores/auth.js';
import { policyService } from './services/policies.js';
import { applyCustomThemeStyles } from './utils/theme.js';

export default {
  name: 'App',
  components: { PolicyAcceptanceModal, SetPasswordModal, FloatingActions },
  data() {
    return {
      showSetPasswordModal: false,
      requiredPolicies: [],
      checkingPolicies: false,
    };
  },
  mounted() {
    // Apply custom theme configuration globally on load/refresh
    applyCustomThemeStyles();
    this.showSetPasswordModal = needsPasswordSetup();
    this.checkRequiredPolicies();
  },
  watch: {
    $route() {
      this.showSetPasswordModal = needsPasswordSetup();
      this.checkRequiredPolicies();
    },
  },
  methods: {
    shouldCheckPolicies() {
      const auth = getAuth();
      if (!auth?.token) return false;
      if (auth.role_group === 'admin') return false;
      if (this.$route.path.startsWith('/admin')) return false;
      return true;
    },
    async checkRequiredPolicies() {
      if (this.checkingPolicies) return;
      if (!this.shouldCheckPolicies()) {
        this.requiredPolicies = [];
        return;
      }

      this.checkingPolicies = true;

      try {
        const response = await policyService.required();
        this.requiredPolicies = response.data || response.policies || [];
      } catch {
        this.requiredPolicies = [];
      } finally {
        this.checkingPolicies = false;
      }
    },
    handlePasswordSetupDone() {
      this.showSetPasswordModal = false;
      this.checkRequiredPolicies();
    },
    handlePoliciesAccepted() {
      this.requiredPolicies = [];
    },
  },
};
</script>

<style>
:root {
  --sg-green: #22c55e;
  --sg-green-dark: #16a34a;
  --sg-green-light: #4ade80;
  --sg-green-pale: #dcfce7;
  --sg-dark: #111827;
  --sg-darker: #0a0f1a;
  --sg-surface: #f8fafc;
  --sg-white: #ffffff;
  --sg-text: #1e293b;
  --sg-text-muted: #64748b;
  --sg-border: #e2e8f0;
  --sg-danger: #ef4444;
  --sg-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1);
  --sg-shadow-lg: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1);
  --sg-shadow-xl: 0 20px 25px -5px rgba(0,0,0,.1), 0 8px 10px -6px rgba(0,0,0,.1);
  --sg-radius: 12px;
  --sg-radius-sm: 8px;
  --sg-radius-full: 9999px;
  --sg-transition: all .2s cubic-bezier(.4,0,.2,1);
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: var(--sg-text);
  background: var(--sg-surface);
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a {
  text-decoration: none;
  color: inherit;
}

button {
  cursor: pointer;
  border: none;
  background: none;
  font-family: inherit;
}

input {
  font-family: inherit;
}

/* ─── Global Light Mode Overrides for Client Pages ─── */
.light {
  --bg-page: #f8fafc;
  --bg-card: #ffffff;
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --border-color: #e2e8f0;
}

.light body {
  background: #f8fafc;
  color: #1e293b;
}

.light .venue-list-page,
.light .venue-detail-page,
.light .booking-container,
.light .detail-container {
  background: #f8fafc !important;
  color: #0f172a !important;
}

.light .search-bar-wrapper {
  background: rgba(255, 255, 255, 0.85) !important;
  border-bottom-color: rgba(0, 0, 0, 0.08) !important;
}

.light .search-input {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.15) !important;
  color: #0f172a !important;
}
.light .search-input::placeholder {
  color: rgba(0, 0, 0, 0.4) !important;
}

.light .sport-tag {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
  color: rgba(0, 0, 0, 0.7) !important;
}
.light .sport-tag:hover {
  background: rgba(0, 0, 0, 0.04) !important;
}
.light .sport-tag.active {
  background: #0f172a !important;
  color: #ffffff !important;
  border-color: #0f172a !important;
}

.light .venue-card {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.06) !important;
  box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;
}
.light .venue-card:hover {
  box-shadow: 0 10px 24px rgba(0,0,0,0.08) !important;
  border-color: rgba(0, 0, 0, 0.12) !important;
  background: #ffffff !important;
}
.light .card-name {
  color: #0f172a !important;
}
.light .price-value {
  color: #0f172a !important;
}
.light .card-address {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .card-footer {
  border-top-color: rgba(0, 0, 0, 0.05) !important;
}
.light .card-tags .card-tag {
  background: rgba(255, 255, 255, 0.9) !important;
  color: #0f172a !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
}

/* Map sidebar */
.light .map-sidebar {
  background: #ffffff !important;
  border-right-color: rgba(0, 0, 0, 0.08) !important;
}
.light .map-sidebar-header {
  border-bottom-color: rgba(0, 0, 0, 0.08) !important;
  background: #ffffff !important;
}
.light .map-sidebar-count {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .map-list-item {
  border-bottom-color: rgba(0, 0, 0, 0.05) !important;
}
.light .map-list-item:hover,
.light .map-list-item.highlighted {
  background: rgba(0, 0, 0, 0.02) !important;
}
.light .map-item-name {
  color: #0f172a !important;
}
.light .map-item-address {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .map-item-tag {
  background: rgba(0, 0, 0, 0.03) !important;
  color: rgba(0, 0, 0, 0.6) !important;
}
.light .map-item-price {
  color: rgba(0, 0, 0, 0.7) !important;
}
.light .map-item-img {
  background: rgba(0, 0, 0, 0.02) !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
}

/* Detail page overrides */
.light .hero {
  border-bottom-color: rgba(0, 0, 0, 0.08) !important;
}
.light .venue-name {
  color: #0f172a !important;
}
.light .meta-item {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .stat-value {
  color: #0f172a !important;
}
.light .stat-divider {
  background: rgba(0, 0, 0, 0.08) !important;
}
.light .hero-stats {
  border-top-color: rgba(0, 0, 0, 0.08) !important;
  border-bottom-color: rgba(0, 0, 0, 0.08) !important;
}
.light .gallery-main {
  background: rgba(0, 0, 0, 0.02) !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
}
.light .detail-section {
  border-bottom-color: rgba(0, 0, 0, 0.08) !important;
}
.light .section-title {
  color: #0f172a !important;
}
.light .description-text {
  color: rgba(0, 0, 0, 0.6) !important;
}
.light .amenity-item {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
}
.light .amenity-name {
  color: #0f172a !important;
}
.light .court-chip {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
  color: #0f172a !important;
}
.light .price-row {
  background: #ffffff !important;
  color: rgba(0, 0, 0, 0.6) !important;
}
.light .price-row.header-row {
  background: rgba(0, 0, 0, 0.03) !important;
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .price-val {
  color: #0f172a !important;
}

/* Booking panel in Detail page */
.light .booking-panel {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
  box-shadow: 0 10px 30px rgba(0,0,0,0.04) !important;
}
.light .panel-title,
.light .panel-price {
  color: #0f172a !important;
}
.light .booking-panel-header {
  border-bottom-color: rgba(0, 0, 0, 0.08) !important;
}
.light .bform-input {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.12) !important;
  color: #0f172a !important;
}
.light .bform-input:focus {
  border-color: #0f172a !important;
}
.light .btn-primary {
  background: #0f172a !important;
  color: #ffffff !important;
}
.light .btn-primary:hover {
  background: rgba(15, 23, 42, 0.9) !important;
}

/* Booking wizard form */
.light .card {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
  box-shadow: 0 4px 12px rgba(0,0,0,0.02) !important;
}
.light .card-header h2 {
  color: #0f172a !important;
}
.light .card-icon {
  background: #0f172a !important;
  color: #ffffff !important;
}
.light .form-group label {
  color: rgba(0, 0, 0, 0.7) !important;
}
.light .form-control {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.12) !important;
  color: #0f172a !important;
}
.light .form-control:focus {
  border-color: #0f172a !important;
}
.light .form-control option {
  background: #ffffff !important;
  color: #0f172a !important;
}
.light .schedule-controls {
  background: rgba(0, 0, 0, 0.02) !important;
}
.light .schedule-legend {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .schedule-legend i {
  border-color: rgba(0, 0, 0, 0.12) !important;
}
.light .legend-free {
  background: #ffffff !important;
}
.light .legend-busy {
  background: rgba(0, 0, 0, 0.08) !important;
}
.light .legend-selected {
  background: #0f172a !important;
}
.light .schedule-state {
  background: rgba(0, 0, 0, 0.02) !important;
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .schedule-wrap {
  border-color: rgba(0, 0, 0, 0.08) !important;
  background: #ffffff !important;
}
.light .schedule-head,
.light .schedule-court,
.light .schedule-cell {
  border-right-color: rgba(0, 0, 0, 0.08) !important;
  border-bottom-color: rgba(0, 0, 0, 0.08) !important;
}
.light .schedule-head {
  background: rgba(0, 0, 0, 0.02) !important;
  color: rgba(0, 0, 0, 0.7) !important;
}
.light .schedule-court {
  background: #ffffff !important;
}
.light .schedule-court strong {
  color: #0f172a !important;
}
.light .schedule-court span {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .schedule-cell {
  background: #ffffff !important;
}
.light .schedule-cell:hover:not(.busy) {
  background: rgba(0, 0, 0, 0.05) !important;
}
.light .schedule-cell.busy {
  background: rgba(0, 0, 0, 0.08) !important;
}
.light .schedule-cell.selected {
  background: #0f172a !important;
  box-shadow: inset 0 0 0 2px #0f172a !important;
}

/* Payment Option Card */
.light .payment-option-card {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.12) !important;
}
.light .payment-option-card:hover {
  background: rgba(0, 0, 0, 0.02) !important;
}
.light .payment-option-card.active {
  background: rgba(15, 23, 42, 0.05) !important;
  border-color: #0f172a !important;
}
.light .option-title {
  color: #0f172a !important;
}
.light .option-desc {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .summary-card h2 {
  color: #0f172a !important;
}
.light .divider {
  background: rgba(0, 0, 0, 0.08) !important;
}
.light .summary-row .label {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .summary-row .val {
  color: #0f172a !important;
}
.light .total-row .price {
  color: #0f172a !important;
}
.light .deposit-row .required-price {
  color: #0f172a !important;
}
.light .btn-submit {
  background: #0f172a !important;
  color: #ffffff !important;
}
.light .btn-submit:hover:not(:disabled) {
  background: rgba(15, 23, 42, 0.9) !important;
}
.light .btn-submit:disabled {
  background: rgba(0, 0, 0, 0.08) !important;
  color: rgba(0, 0, 0, 0.3) !important;
}
.light .hold-notice {
  color: rgba(0, 0, 0, 0.4) !important;
}

/* Booking Detail */
.light .status-banner {
  background: #ffffff !important;
  border-color: rgba(0, 0, 0, 0.08) !important;
}
.light .banner-text h2 {
  color: #0f172a !important;
}
.light .banner-text p {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .card-header-simple h2 {
  color: #0f172a !important;
}
.light .price-card h3 {
  color: #0f172a !important;
}
.light .price-row .val {
  color: #0f172a !important;
}
.light .price-row.highlighted {
  border-top-color: rgba(0, 0, 0, 0.08) !important;
  color: #0f172a !important;
}
.light .price-row.highlighted .price {
  color: #0f172a !important;
}
.light .btn-sepay {
  background: #0f172a !important;
  color: #ffffff !important;
}
.light .btn-sepay:hover:not(:disabled) {
  background: rgba(15, 23, 42, 0.9) !important;
}
.light .transfer-row {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light .transfer-row strong {
  color: #0f172a !important;
}
.light .copy-value {
  color: #0f172a !important;
}
.light .payment-waiting {
  background: rgba(0, 0, 0, 0.03) !important;
  color: #0f172a !important;
}
.light .mini-spinner {
  border-color: rgba(0, 0, 0, 0.1) !important;
  border-top-color: #0f172a !important;
}
.light .btn-back {
  background: #0f172a !important;
  color: #ffffff !important;
}

/* Navbar light mode overrides */
.light .navbar-dark {
  background: rgba(255, 255, 255, 0.8) !important;
  border-bottom-color: rgba(0, 0, 0, 0.08) !important;
}
.light .navbar-dark .brand-text {
  color: #0f172a !important;
}
.light .navbar-dark .nav-link {
  color: rgba(0, 0, 0, 0.7) !important;
}
.light .navbar-dark .nav-link:hover,
.light .navbar-dark .nav-link.active-link {
  color: #0f172a !important;
}
.light .navbar-dark .login-btn {
  background: #0f172a !important;
  color: #ffffff !important;
}
.light .navbar-dark .login-btn:hover {
  background: rgba(15, 23, 42, 0.9) !important;
}
.light .navbar-dark .user-avatar {
  background: #0f172a !important;
  color: #ffffff !important;
}
.light .navbar-dark .user-btn {
  color: #0f172a !important;
}
.light .navbar-dark .theme-toggle-btn {
  border-color: rgba(0, 0, 0, 0.1) !important;
  color: rgba(0, 0, 0, 0.7) !important;
}
.light .navbar-dark .theme-toggle-btn:hover {
  background: rgba(0, 0, 0, 0.04) !important;
  color: #0f172a !important;
}
</style>
