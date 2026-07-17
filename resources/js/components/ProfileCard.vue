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
          <progress
            class="membership-progress"
            :value="membershipProgress"
            max="100"
            :aria-label="`Tiến độ hạng thành viên ${membershipProgress}%`"
          >
            {{ membershipProgress }}%
          </progress>
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
        <button type="button" title="Đóng" aria-label="Đóng" @click="showMembershipModal = false">×</button>
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
      const map = { admin: 'Quản trị viên', owner: 'Chủ sân', staff: 'Nhân viên sân', user: 'Người dùng' };
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

<style scoped src="../../css/components/client-profile-card.css"></style>
