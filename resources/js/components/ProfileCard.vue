<template>
  <div class="pcard">
    <!-- ── Hero Header ── -->
    <div class="pcard-hero">
      <div class="hero-bg-decor">
        <div class="hero-circle hero-circle-1"></div>
        <div class="hero-circle hero-circle-2"></div>
        <div class="hero-grid"></div>
      </div>

      <div class="hero-body">
        <div class="avatar-ring">
          <div class="avatar">{{ userInitial }}</div>
          <div class="avatar-status"></div>
        </div>
        <div class="hero-name-row">
          <h1 class="hero-name">{{ user?.fullName || '—' }}</h1>
          <button
            v-if="showMembershipTier"
            class="membership-pill"
            :class="membershipBadgeClass"
            type="button"
            :title="membershipTooltip"
            @click="showMembershipModal = true"
          >
            <span>{{ membershipTier.label }}</span>
            <span class="membership-chevron">&rsaquo;</span>
          </button>
        </div>
        <div class="role-badge" :class="user?.role">
          <span class="role-dot"></span>
          {{ roleLabel }}
        </div>
        <div
          v-if="showVipBadge"
          class="vip-badge"
          :class="vipBadgeClass"
          :title="vipTooltip"
        >
          <span class="vip-mark">VIP</span>
          <strong>{{ vipBadgeLabel }}</strong>
          <small>{{ vipExpiresText }}</small>
        </div>
      </div>
    </div>

    <!-- ── Info rows ── -->
    <div class="pcard-body">
      <div class="section-title">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Thông tin tài khoản
      </div>

      <div class="info-list">
        <!-- Username -->
        <div class="info-item">
          <div class="info-icon info-icon-user">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
          <div class="info-body">
            <div class="info-label">Tên tài khoản</div>
            <div class="info-value">{{ user?.username || '—' }}</div>
          </div>
        </div>

        <!-- Email -->
        <div class="info-item">
          <div class="info-icon info-icon-mail">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
          </div>
          <div class="info-body">
            <div class="info-label">Email</div>
            <div class="info-value">{{ user?.email || '—' }}</div>
          </div>
        </div>

        <!-- Phone -->
        <div class="info-item">
          <div class="info-icon info-icon-phone">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </div>
          <div class="info-body">
            <div class="info-label">Số điện thoại</div>
            <div class="info-value">{{ user?.phone || '—' }}</div>
          </div>
        </div>

        <!-- Role -->
        <div class="info-item">
          <div class="info-icon info-icon-role">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </div>
          <div class="info-body">
            <div class="info-label">Vai trò</div>
            <div class="info-value">{{ roleLabel }}</div>
          </div>
        </div>

        <!-- Membership -->
        <div v-if="showMembershipTier" class="membership-card" :class="membershipCardClass">
          <div class="membership-card-head">
            <div>
              <div class="membership-label">Hạng thành viên</div>
              <div class="membership-title">{{ membershipTier.label }}</div>
              <div v-if="membershipTier.venue_name" class="membership-venue">{{ membershipTier.venue_name }}</div>
            </div>
            <div class="membership-bookings">
              {{ membershipTier.completed_bookings || 0 }}
              <span>booking thành công</span>
            </div>
          </div>
          <div class="membership-summary">
            <div>
              <span>Tổng chi tiêu</span>
              <strong>{{ formatMoney(membershipTier.total_spend_amount || membershipTier.total_spent || 0) }}</strong>
            </div>
            <div v-if="membershipTier.next_tier">
              <span>Cần thêm</span>
              <strong>{{ membershipTier.remaining_bookings || 0 }} booking / {{ formatMoney(membershipTier.remaining_spend_amount || 0) }}</strong>
            </div>
          </div>
          <div class="membership-progress">
            <span :style="{ width: `${membershipProgress}%` }"></span>
          </div>
          <div class="membership-note">{{ membershipNote }}</div>
          <div v-if="membershipVenues.length > 1" class="membership-venues">
            <div v-for="item in membershipVenues" :key="item.venue_cluster_id" class="membership-venue-row">
              <span>{{ item.venue_name || 'Cụm sân' }}</span>
              <strong class="membership-mini-pill" :class="tierClass(item.key)">{{ item.label }}</strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Footer actions ── -->
    <div class="pcard-footer">
      <div class="footer-actions">
        <button class="btn-back" @click="$emit('go-back')">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
          </svg>
          Quay lại
        </button>
        <router-link v-if="user?.role === 'user'" class="btn-vip" to="/vip-membership">
          {{ vipActionLabel }}
        </router-link>
      </div>
      <div class="status-indicator">
        <span class="status-dot"></span>
        Đang hoạt động
      </div>
    </div>
  </div>

  <div
    v-if="showMembershipModal"
    class="membership-modal-backdrop"
    @click.self="showMembershipModal = false"
  >
    <section class="membership-modal" role="dialog" aria-modal="true">
      <header class="membership-modal-head">
        <div>
          <p>Hạng thành viên</p>
          <h2>{{ membershipTier.label }}</h2>
          <span v-if="membershipTier.venue_name">{{ membershipTier.venue_name }}</span>
        </div>
        <button type="button" title="Đóng" @click="showMembershipModal = false">×</button>
      </header>

      <div class="membership-modal-body">
      <div class="membership-modal-stats">
        <div>
          <span>Booking thành công</span>
          <strong>{{ membershipTier.completed_bookings || 0 }}</strong>
        </div>
        <div>
          <span>Tổng chi tiêu</span>
          <strong>{{ formatMoney(membershipTier.total_spend_amount || membershipTier.total_spent || 0) }}</strong>
        </div>
        <div>
          <span>Ưu đãi hiện tại</span>
          <strong>Giảm {{ membershipTier.discount_percent || 0 }}%</strong>
        </div>
      </div>

      <div class="membership-modal-table-wrap">
        <table class="membership-modal-table">
          <thead>
            <tr>
              <th>Cụm sân</th>
              <th>Hạng hiện tại</th>
              <th>Đã đạt</th>
              <th>Hạng kế tiếp</th>
              <th>Cần thêm</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in membershipModalRows" :key="item.venue_cluster_id">
              <td>{{ item.venue_name || 'Cụm sân' }}</td>
              <td>
                <span class="membership-mini-pill" :class="tierClass(item.key)">
                  {{ item.label }}
                </span>
              </td>
              <td>
                {{ item.completed_bookings || 0 }} booking<br>
                {{ formatMoney(item.total_spend_amount || item.total_spent || 0) }}
              </td>
              <td>
                <template v-if="item.next_tier">
                  {{ item.next_tier.label }}
                  <small>{{ item.next_tier.discount_percent || 0 }}% giảm</small>
                </template>
                <template v-else>Cao nhất</template>
              </td>
              <td>
                <template v-if="item.next_tier">
                  {{ item.remaining_bookings || 0 }} booking<br>
                  {{ formatMoney(item.remaining_spend_amount || 0) }}
                </template>
                <template v-else>Đã đủ điều kiện cao nhất</template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <button
        class="membership-rank-toggle"
        type="button"
        @click="showAllTierRules = !showAllTierRules"
      >
        {{ showAllTierRules ? 'Ẩn thông tin các hạng' : 'Thông tin các hạng còn lại' }}
      </button>

      <div v-if="showAllTierRules" class="membership-modal-table-wrap">
        <table class="membership-modal-table membership-rules-table">
          <thead>
            <tr>
              <th>Hạng</th>
              <th>Ưu đãi</th>
              <th>Điều kiện lên hạng</th>
              <th>Duy trì</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tier in membershipTierRules" :key="tier.key">
              <td>
                <span class="membership-mini-pill" :class="tierClass(tier.key)">
                  {{ tier.label }}
                </span>
              </td>
              <td>Giảm {{ tier.discount_percent || 0 }}% mỗi lần đặt sân</td>
              <td>
                {{ tier.min_completed_bookings || tier.min_bookings || 0 }} booking<br>
                {{ formatMoney(tier.min_spend_amount || tier.min_spent_amount || 0) }}
              </td>
              <td>
                <template v-if="tier.maintain_period_months">
                  Mỗi {{ tier.maintain_period_months }} tháng cần duy trì<br>
                  - {{ tier.maintain_min_bookings || 0 }} booking<br>
                  - {{ formatMoney(tier.maintain_min_spend_amount || tier.maintain_min_spent || 0) }}
                </template>
                <template v-else>Không yêu cầu</template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      </div>
    </section>
  </div>
</template>

<script>
export default {
  name: 'ProfileCard',
  props: {
    user: { type: Object, default: null },
  },
  emits: ['go-back'],
  data() {
    return {
      showMembershipModal: false,
      showAllTierRules: false,
    };
  },
  computed: {
    userInitial() {
      return this.user?.fullName?.charAt(0)?.toUpperCase() || '?';
    },
    membershipTier() {
      return this.normalizeMembership(this.user?.membership_tier);
    },
    vipSubscription() {
      return this.user?.vip_subscription || null;
    },
    vipPackage() {
      return this.vipSubscription?.package || null;
    },
    showVipBadge() {
      return this.user?.role === 'user' && Boolean(this.vipSubscription && this.vipPackage);
    },
    vipBadgeLabel() {
      return this.vipSubscription?.badge?.label
        || this.vipPackage?.badge_name
        || this.vipPackage?.label
        || this.vipPackage?.name
        || 'SportGo VIP';
    },
    vipBadgeClass() {
      return `vip-badge-${this.vipPackage?.type || this.vipSubscription?.badge?.type || 'saving'}`;
    },
    vipExpiresText() {
      if (!this.vipSubscription?.expires_at) return 'Đang hiệu lực';
      return `Đến ${this.formatDate(this.vipSubscription.expires_at)}`;
    },
    vipTooltip() {
      const cashback = Number(this.vipPackage?.cashback_percent || 0);
      const parts = [];
      if (cashback > 0) parts.push(`${cashback}% cashback`);
      return parts.length ? parts.join(' · ') : 'Gói VIP hệ thống đang hiệu lực';
    },
    vipActionLabel() {
      return this.showVipBadge ? 'Quản lý VIP' : 'Mua gói VIP';
    },
    membershipBadgeClass() {
      return this.tierClass(this.membershipTier?.key);
    },
    membershipCardClass() {
      return this.tierCardClass(this.membershipTier?.key);
    },
    showMembershipTier() {
      return this.user?.role === 'user' && Boolean(this.membershipTier);
    },
    membershipVenues() {
      return (this.user?.venue_memberships || [])
        .map((item) => this.normalizeMembership(item))
        .filter(Boolean);
    },
    membershipModalRows() {
      return this.membershipVenues.length ? this.membershipVenues : [this.membershipTier].filter(Boolean);
    },
    membershipTierRules() {
      const tiers = this.membershipTier?.tiers || this.membershipModalRows[0]?.tiers || [];
      return tiers
        .map((tier) => ({
          ...tier,
          key: tier.key || tier.tier_key || tier.tier,
          label: tier.label || tier.tier_label,
        }))
        .sort((a, b) => Number(a.tier_order || 0) - Number(b.tier_order || 0));
    },
    membershipProgress() {
      return Math.min(100, Math.max(0, Number(this.membershipTier?.progress_percent || 0)));
    },
    membershipTooltip() {
      const discount = Number(this.membershipTier?.discount_percent || 0);
      return discount > 0 ? `Giảm ${discount}% khi đặt sân` : 'Hạng mặc định';
    },
    membershipNote() {
      const nextTier = this.membershipTier?.next_tier;
      if (!nextTier) return 'Bạn đã đạt hạng cao nhất.';

      const remaining = Number(this.membershipTier?.remaining_bookings || 0);
      const spend = Number(this.membershipTier?.remaining_spend_amount || 0);
      const spendText = spend > 0 ? ` và ${this.formatMoney(spend)}` : '';
      return `Còn ${remaining} booking thành công${spendText} để lên ${nextTier.label}.`;
    },
    roleLabel() {
      const map = { admin: 'Quản trị viên', owner: 'Chủ sân', user: 'Người dùng' };
      return map[this.user?.role] || 'Khách';
    },
  },
  methods: {
    normalizeMembership(item) {
      if (!item) return null;
      const tier = item.tier || item;
      const nextTier = item.next_tier
        ? {
            ...item.next_tier,
            label: item.next_tier.label || item.next_tier.tier_label,
          }
        : null;

      return {
        ...item,
        key: tier.key || tier.tier_key || tier.tier,
        label: tier.label || tier.tier_label,
        discount_percent: Number(tier.discount_percent || 0),
        completed_bookings: Number(item.completed_bookings || 0),
        remaining_bookings: Number(item.remaining_bookings || 0),
        remaining_spend_amount: Number(item.remaining_spend_amount || 0),
        progress_percent: Number(item.progress_percent || 0),
        next_tier: nextTier,
      };
    },
    formatMoney(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '';
    },
    tierClass(key) {
      return `tier-${key || 'standard'}`;
    },
    tierCardClass(key) {
      return `membership-card-${key || 'standard'}`;
    },
  },
};
</script>

<style scoped>
/* ── Card shell ── */
.pcard {
  background: #fff;
  border-radius: 20px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(0,0,0,.06), 0 1px 4px rgba(0,0,0,.04);
  max-width: 540px;
  width: 100%;
}

/* ── Hero ── */
.pcard-hero {
  position: relative;
  padding: 48px 32px 36px;
  background: linear-gradient(135deg, #0d1f16 0%, #111827 60%, #0f2318 100%);
  overflow: hidden;
  text-align: center;
}
.hero-bg-decor {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.hero-circle {
  position: absolute;
  border-radius: 50%;
  filter: blur(60px);
}
.hero-circle-1 {
  width: 300px;
  height: 300px;
  background: rgba(34,197,94,.18);
  top: -80px;
  right: -60px;
}
.hero-circle-2 {
  width: 200px;
  height: 200px;
  background: rgba(34,197,94,.1);
  bottom: -60px;
  left: -40px;
}
.hero-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 32px 32px;
}

.hero-body {
  position: relative;
  z-index: 1;
}

/* Avatar */
.avatar-ring {
  display: inline-block;
  position: relative;
  margin-bottom: 18px;
}
.avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -1px;
  border: 3px solid rgba(255,255,255,.15);
  box-shadow: 0 0 0 6px rgba(34,197,94,.12);
}
.avatar-status {
  position: absolute;
  bottom: 4px;
  right: 4px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #22c55e;
  border: 2px solid #111827;
  box-shadow: 0 0 0 2px rgba(34,197,94,.4);
}

.hero-name-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.hero-name {
  font-size: 22px;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0;
  line-height: 1.2;
  margin: 0;
}

.membership-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 34px;
  padding: 6px 12px 6px 14px;
  border: 1px solid var(--tier-border, #e5e7eb);
  border-radius: 999px;
  background: var(--tier-bg, #fff);
  color: var(--tier-text, #111827);
  box-shadow: 0 8px 24px rgba(15,23,42,.18);
  font-size: 14px;
  font-weight: 800;
  line-height: 1;
  white-space: nowrap;
}
.membership-chevron {
  color: currentColor;
  font-size: 21px;
  line-height: 1;
}
.tier-standard {
  --tier-bg: #f8fafc;
  --tier-border: #cbd5e1;
  --tier-text: #334155;
}
.tier-silver {
  --tier-bg: #f3f4f6;
  --tier-border: #cbd5e1;
  --tier-text: #111827;
}
.tier-gold {
  --tier-bg: #fef3c7;
  --tier-border: #f59e0b;
  --tier-text: #78350f;
}
.tier-diamond {
  --tier-bg: #e0f2fe;
  --tier-border: #38bdf8;
  --tier-text: #075985;
}

/* Role badge */
.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
}
.role-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
.role-badge.admin {
  background: rgba(239,68,68,.15);
  color: #fca5a5;
}
.role-badge.admin .role-dot { background: #f87171; }
.role-badge.owner {
  background: rgba(59,130,246,.15);
  color: #93c5fd;
}
.role-badge.owner .role-dot { background: #60a5fa; }
.role-badge.user {
  background: rgba(34,197,94,.15);
  color: #86efac;
}
.role-badge.user .role-dot { background: #4ade80; }

.vip-badge {
  display: inline-grid;
  grid-template-columns: auto auto;
  align-items: center;
  gap: 5px 8px;
  margin-top: 10px;
  padding: 8px 12px;
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 12px;
  background: rgba(255,255,255,.1);
  color: #fff;
  box-shadow: 0 12px 28px rgba(15,23,42,.18);
}
.vip-mark {
  padding: 3px 6px;
  border-radius: 6px;
  background: rgba(255,255,255,.18);
  font-size: 10px;
  font-weight: 900;
  line-height: 1;
}
.vip-badge strong {
  font-size: 13px;
  font-weight: 900;
  line-height: 1.1;
}
.vip-badge small {
  grid-column: 1 / -1;
  color: rgba(255,255,255,.76);
  font-size: 11px;
  font-weight: 750;
}
.vip-badge-saving {
  background: rgba(14,165,233,.18);
  border-color: rgba(125,211,252,.42);
}
.vip-badge-pro {
  background: rgba(245,158,11,.2);
  border-color: rgba(251,191,36,.5);
}

/* ── Body ── */
.pcard-body {
  padding: 28px 32px 8px;
}
.section-title {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 11px;
  font-weight: 700;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: .7px;
  margin-bottom: 20px;
}
.section-title svg { color: #9ca3af; }

/* Info list */
.info-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.info-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border-radius: 12px;
  background: #f9fafb;
  border: 1px solid #f3f4f6;
  transition: all .15s ease;
}
.info-item:hover {
  background: #f0fdf4;
  border-color: #bbf7d0;
}
.info-icon {
  width: 38px;
  height: 38px;
  min-width: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.info-icon-user {
  background: #f0fdf4;
  color: #16a34a;
}
.info-icon-mail {
  background: #eff6ff;
  color: #2563eb;
}
.info-icon-phone {
  background: #fff7ed;
  color: #ea580c;
}
.info-icon-role {
  background: #faf5ff;
  color: #7c3aed;
}
.info-item:hover .info-icon-user { background: #dcfce7; }
.info-item:hover .info-icon-mail { background: #dbeafe; }
.info-item:hover .info-icon-phone { background: #ffedd5; }
.info-item:hover .info-icon-role { background: #ede9fe; }

.info-body { flex: 1; min-width: 0; }
.info-label {
  font-size: 11px;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: .4px;
  margin-bottom: 3px;
}
.info-value {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Membership */
.membership-card {
  --member-bg: #f0fdf4;
  --member-border: #d1fae5;
  --member-strong: #064e3b;
  --member-text: #047857;
  --member-track: #bbf7d0;
  --member-accent: linear-gradient(90deg, #16a34a, #0f766e);
  display: grid;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--member-border);
  border-radius: 12px;
  background: var(--member-bg);
}
.membership-card-standard {
  --member-bg: #f8fafc;
  --member-border: #e2e8f0;
  --member-strong: #334155;
  --member-text: #64748b;
  --member-track: #e2e8f0;
  --member-accent: linear-gradient(90deg, #64748b, #334155);
}
.membership-card-silver {
  --member-bg: #f9fafb;
  --member-border: #d1d5db;
  --member-strong: #111827;
  --member-text: #4b5563;
  --member-track: #e5e7eb;
  --member-accent: linear-gradient(90deg, #9ca3af, #4b5563);
}
.membership-card-gold {
  --member-bg: #fffbeb;
  --member-border: #fde68a;
  --member-strong: #78350f;
  --member-text: #92400e;
  --member-track: #fde68a;
  --member-accent: linear-gradient(90deg, #f59e0b, #b45309);
}
.membership-card-diamond {
  --member-bg: #f0f9ff;
  --member-border: #bae6fd;
  --member-strong: #075985;
  --member-text: #0369a1;
  --member-track: #bae6fd;
  --member-accent: linear-gradient(90deg, #38bdf8, #2563eb);
}
.membership-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}
.membership-label {
  color: var(--member-text);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}
.membership-title {
  margin-top: 3px;
  color: var(--member-strong);
  font-size: 18px;
  font-weight: 850;
}
.membership-venue {
  margin-top: 3px;
  color: var(--member-text);
  font-size: 12px;
  font-weight: 700;
}
.membership-bookings {
  display: grid;
  justify-items: end;
  color: var(--member-strong);
  font-size: 18px;
  font-weight: 850;
  line-height: 1.1;
  white-space: nowrap;
}
.membership-bookings span {
  margin-top: 3px;
  color: var(--member-text);
  font-size: 11px;
  font-weight: 700;
}
.membership-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.membership-summary div {
  display: grid;
  gap: 4px;
  min-width: 0;
  padding: 10px;
  border: 1px solid color-mix(in srgb, var(--member-border) 70%, #ffffff);
  border-radius: 8px;
  background: rgba(255,255,255,.62);
}
.membership-summary span {
  color: var(--member-text);
  font-size: 10px;
  font-weight: 850;
  text-transform: uppercase;
}
.membership-summary strong {
  min-width: 0;
  color: var(--member-strong);
  font-size: 12px;
  font-weight: 850;
  line-height: 1.25;
  overflow-wrap: anywhere;
}
.membership-progress {
  height: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--member-track);
}
.membership-progress span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--member-accent);
}
.membership-note {
  color: var(--member-text);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.35;
}
.membership-venues {
  display: grid;
  gap: 6px;
  padding-top: 2px;
}
.membership-venue-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 10px;
  border-radius: 8px;
  background: rgba(255,255,255,.58);
  color: var(--member-strong);
  font-size: 12px;
  font-weight: 700;
}
.membership-venue-row span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.membership-mini-pill {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  padding: 5px 10px;
  border: 1px solid var(--tier-border, #e5e7eb);
  border-radius: 999px;
  background: var(--tier-bg, #fff);
  color: var(--tier-text, #111827);
  font-size: 12px;
  font-weight: 850;
  line-height: 1;
  white-space: nowrap;
}

/* ── Footer ── */
.membership-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgba(15,23,42,.56);
}
.membership-modal {
  width: min(760px, 100%);
  max-height: min(90vh, 760px);
  overflow: hidden;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 16px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15,23,42,.28);
}
.membership-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  padding-bottom: 14px;
  border-bottom: 1px solid #e2e8f0;
}
.membership-modal-head p {
  margin: 0 0 3px;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}
.membership-modal-head h2 {
  margin: 0;
  color: #0f172a;
  font-size: 24px;
  font-weight: 900;
}
.membership-modal-head span {
  display: block;
  margin-top: 4px;
  color: #475569;
  font-size: 13px;
  font-weight: 750;
}
.membership-modal-head button {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #334155;
  font-size: 24px;
  line-height: 1;
}
.membership-modal-body {
  display: grid;
  gap: 14px;
  min-height: 0;
  overflow-y: auto;
  padding: 0 4px 10px 0;
}
.membership-modal-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.membership-modal-stats div {
  display: grid;
  gap: 4px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}
.membership-modal-stats span {
  color: #64748b;
  font-size: 11px;
  font-weight: 850;
  text-transform: uppercase;
}
.membership-modal-stats strong {
  color: #0f172a;
  font-size: 18px;
  font-weight: 900;
}
.membership-modal-table-wrap {
  overflow: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}
.membership-modal-table {
  width: 100%;
  min-width: 680px;
  border-collapse: collapse;
}
.membership-modal-table th,
.membership-modal-table td {
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top;
  color: #0f172a;
  font-size: 13px;
}
.membership-modal-table th {
  background: #f8fafc;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}
.membership-modal-table tr:last-child td {
  border-bottom: 0;
}
.membership-modal-table small {
  display: block;
  margin-top: 3px;
  color: #64748b;
  font-weight: 800;
}
.membership-rank-toggle {
  justify-self: end;
  min-height: 38px;
  padding: 9px 13px;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  background: #ecfdf5;
  color: #047857;
  font-size: 13px;
  font-weight: 850;
}
.membership-rank-toggle:hover {
  background: #dcfce7;
}
.membership-rules-table td {
  line-height: 1.45;
}

.pcard-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 32px 28px;
}
.footer-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  transition: all .15s ease;
}
.btn-back:hover {
  background: #f0fdf4;
  border-color: #86efac;
  color: #15803d;
}
.btn-back svg { color: currentColor; }
.btn-vip {
  display: inline-flex;
  align-items: center;
  min-height: 40px;
  padding: 10px 14px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #047857;
  font-size: 14px;
  font-weight: 800;
  text-decoration: none;
}
.btn-vip:hover {
  background: #dcfce7;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
}
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 2px rgba(34,197,94,.25);
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 2px rgba(34,197,94,.25); }
  50% { box-shadow: 0 0 0 5px rgba(34,197,94,.1); }
}

/* Responsive */
@media (max-width: 480px) {
  .pcard-hero { padding: 36px 20px 28px; }
  .pcard-body { padding: 20px 20px 8px; }
  .pcard-footer { padding: 16px 20px 24px; }
  .info-item { padding: 12px; }
  .hero-name { font-size: 20px; }
  .membership-card-head { display: grid; }
  .membership-bookings { justify-items: start; }
  .membership-summary { grid-template-columns: 1fr; }
  .avatar { width: 68px; height: 68px; font-size: 26px; }
  .membership-modal { padding: 16px; }
  .membership-modal-stats { grid-template-columns: 1fr; }
}
</style>
