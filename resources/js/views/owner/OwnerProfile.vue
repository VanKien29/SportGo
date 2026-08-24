<template>
  <main class="owner-profile-page" aria-labelledby="owner-profile-title">
    <header class="owner-profile-heading">
      <div>
        <p class="owner-profile-eyebrow">TÀI KHOẢN VẬN HÀNH</p>
        <h1 id="owner-profile-title">Thông tin cá nhân</h1>
        <p class="owner-profile-description">
          Quản lý thông tin đăng nhập, liên hệ và quyền truy cập workspace của bạn.
        </p>
      </div>
      <button type="button" class="owner-profile-back" @click="goBack">
        <AppIcon name="chevronLeft" :size="16" />
        <span>Quay lại workspace</span>
      </button>
    </header>

    <div v-if="error" class="owner-profile-alert" role="alert">
      <AppIcon name="alert" :size="17" />
      <span>{{ error }}</span>
    </div>

    <section class="owner-profile-hero">
      <div class="owner-profile-hero-glow" aria-hidden="true"></div>
      <div class="owner-profile-avatar" aria-hidden="true">{{ userInitial }}</div>
      <div class="owner-profile-hero-copy">
        <div class="owner-profile-verified">
          <AppIcon name="shieldCheck" :size="15" />
          Tài khoản {{ isStaff ? 'nhân viên' : 'chủ sân' }}
        </div>
        <h2>{{ displayName }}</h2>
        <p>{{ user?.email || 'Chưa cập nhật email' }}</p>
        <div class="owner-profile-badges">
          <span class="owner-profile-badge owner-profile-badge--role">
            <AppIcon name="building" :size="14" />
            {{ roleLabel }}
          </span>
          <span class="owner-profile-badge owner-profile-badge--active">
            <span class="owner-profile-status-dot"></span>
            {{ statusLabel }}
          </span>
        </div>
      </div>
      <div class="owner-profile-hero-meta">
        <span class="owner-profile-meta-label">TÊN ĐĂNG NHẬP</span>
        <strong>{{ user?.username || '—' }}</strong>
        <span class="owner-profile-meta-note">ID tài khoản #{{ user?.id || '—' }}</span>
      </div>
    </section>

    <div class="owner-profile-grid">
      <section class="owner-profile-card owner-profile-details-card" aria-labelledby="owner-details-title">
        <div class="owner-profile-card-heading">
          <div class="owner-profile-card-icon"><AppIcon name="userRound" :size="18" /></div>
          <div>
            <p class="owner-profile-card-kicker">HỒ SƠ</p>
            <h2 id="owner-details-title">Thông tin tài khoản</h2>
          </div>
        </div>

        <div class="owner-profile-detail-list">
          <div class="owner-profile-detail-row">
            <span class="owner-profile-detail-label">Họ và tên</span>
            <strong>{{ displayName }}</strong>
          </div>
          <div class="owner-profile-detail-row">
            <span class="owner-profile-detail-label">Email liên hệ</span>
            <strong>{{ user?.email || 'Chưa cập nhật' }}</strong>
          </div>
          <div class="owner-profile-detail-row">
            <span class="owner-profile-detail-label">Số điện thoại</span>
            <strong>{{ user?.phone || 'Chưa cập nhật' }}</strong>
          </div>
          <div class="owner-profile-detail-row">
            <span class="owner-profile-detail-label">Tên đăng nhập</span>
            <strong>{{ user?.username || 'Chưa cập nhật' }}</strong>
          </div>
        </div>
      </section>

      <aside class="owner-profile-side-column">
        <section class="owner-profile-card owner-profile-security-card" aria-labelledby="security-title">
          <div class="owner-profile-card-heading">
            <div class="owner-profile-card-icon owner-profile-card-icon--green"><AppIcon name="key" :size="18" /></div>
            <div>
              <p class="owner-profile-card-kicker">BẢO MẬT</p>
              <h2 id="security-title">Tài khoản an toàn</h2>
            </div>
          </div>
          <div class="owner-profile-security-state">
            <span class="owner-profile-security-check"><AppIcon name="check" :size="15" /></span>
            <div>
              <strong>Thông tin đăng nhập đang hoạt động</strong>
              <p>Đổi mật khẩu định kỳ để bảo vệ dữ liệu vận hành.</p>
            </div>
          </div>
          <router-link class="owner-profile-action" to="/owner/settings">
            <AppIcon name="settings" :size="16" />
            <span>Mở cài đặt tài khoản</span>
            <AppIcon name="chevronRight" :size="16" />
          </router-link>
        </section>

        <section class="owner-profile-card owner-profile-workspace-card" aria-labelledby="workspace-title">
          <div class="owner-profile-card-heading">
            <div class="owner-profile-card-icon owner-profile-card-icon--violet"><AppIcon name="building" :size="18" /></div>
            <div>
              <p class="owner-profile-card-kicker">WORKSPACE</p>
              <h2 id="workspace-title">Quyền vận hành</h2>
            </div>
          </div>
          <p class="owner-profile-workspace-copy">
            {{ isStaff
              ? 'Bạn đang sử dụng workspace theo phạm vi và menu được chủ sân phân công.'
              : 'Bạn có quyền quản lý cụm sân, lịch đặt và cấu hình vận hành của mình.' }}
          </p>
          <div class="owner-profile-workspace-foot">
            <span><AppIcon name="shieldCheck" :size="15" /> {{ roleLabel }}</span>
            <span>{{ isStaff ? 'Theo phân quyền' : 'Toàn quyền chủ sân' }}</span>
          </div>
        </section>
      </aside>
    </div>

    <div v-if="loading" class="owner-profile-sync" aria-live="polite">
      Đang đồng bộ thông tin tài khoản...
    </div>
  </main>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { authService } from '../../services/authService.js';
import { getAuth, saveAuth } from '../../stores/auth.js';

export default {
  name: 'OwnerProfile',
  components: { AppIcon },
  data() {
    return {
      user: getAuth(),
      loading: false,
      error: '',
    };
  },
  computed: {
    isStaff() {
      return this.user?.role_group === 'staff' || this.user?.role === 'staff';
    },
    displayName() {
      return this.user?.fullName || this.user?.full_name || this.user?.username || 'Người dùng SportGo';
    },
    userInitial() {
      return this.displayName.trim().charAt(0).toUpperCase() || 'S';
    },
    roleLabel() {
      return this.isStaff ? 'Nhân viên sân' : 'Chủ sân';
    },
    statusLabel() {
      return {
        active: 'Đang hoạt động',
        locked: 'Đã khóa',
        deactivated: 'Đã vô hiệu hóa',
      }[this.user?.status] || 'Đang hoạt động';
    },
  },
  created() {
    if (!this.user) {
      this.$router.replace({ name: 'login', query: { redirect: this.$route.fullPath } });
      return;
    }
    this.refreshProfile();
  },
  methods: {
    async refreshProfile() {
      this.loading = true;
      this.error = '';
      try {
        const payload = await authService.me();
        this.user = saveAuth(payload);
      } catch (error) {
        this.error = error.message || 'Không thể đồng bộ thông tin tài khoản.';
      } finally {
        this.loading = false;
      }
    },
    goBack() {
      this.$router.push(this.isStaff ? { name: 'staff-bookings' } : { name: 'owner-dashboard' });
    },
  },
};
</script>

<style scoped>
.owner-profile-page {
  width: min(1120px, 100%);
  margin: 0 auto;
  padding: 4px 18px 34px;
  color: #17231b;
}

.owner-profile-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.owner-profile-eyebrow,
.owner-profile-card-kicker {
  margin: 0 0 7px;
  color: #168542 !important;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .13em;
}

.owner-profile-heading h1 {
  margin: 0;
  color: #142019 !important;
  font-size: clamp(24px, 3vw, 34px);
  font-weight: 760 !important;
  letter-spacing: -.04em;
}

.owner-profile-description {
  max-width: 600px;
  margin: 8px 0 0;
  color: #64746a !important;
  font-size: 13px;
}

.owner-profile-back,
.owner-profile-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #d8e5dc;
  border-radius: 10px;
  background: #ffffff;
  color: #1f5d38;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: border-color .2s ease, background .2s ease, transform .2s ease;
}

.owner-profile-back {
  padding: 10px 13px;
  white-space: nowrap;
}

.owner-profile-back:hover,
.owner-profile-action:hover {
  border-color: #9fc9aa;
  background: #f3fbf5;
  transform: translateY(-1px);
}

.owner-profile-alert {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 16px;
  padding: 11px 13px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #b91c1c;
  background: #fff7f7;
  font-size: 12px;
}

.owner-profile-hero {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 20px;
  overflow: hidden;
  min-height: 170px;
  padding: 28px 30px;
  border: 1px solid #cce3d2;
  border-radius: 18px;
  background: linear-gradient(120deg, #f7fffa 0%, #eef9f1 62%, #e4f5e9 100%);
  box-shadow: 0 12px 28px rgba(31, 93, 56, .07);
}

.owner-profile-hero-glow {
  position: absolute;
  right: -55px;
  top: -105px;
  width: 300px;
  height: 300px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(64, 178, 100, .2), rgba(64, 178, 100, 0) 68%);
  pointer-events: none;
}

.owner-profile-avatar {
  position: relative;
  display: grid;
  place-items: center;
  width: 88px;
  height: 88px;
  border: 7px solid rgba(255, 255, 255, .9);
  border-radius: 50%;
  color: #ffffff;
  background: linear-gradient(145deg, #1b9a4d, #0e6e36);
  box-shadow: 0 8px 18px rgba(22, 133, 66, .2);
  font-size: 32px;
  font-weight: 800;
}

.owner-profile-hero-copy,
.owner-profile-hero-meta {
  position: relative;
}

.owner-profile-verified {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #168542;
  font-size: 11px;
  font-weight: 750;
}

.owner-profile-hero-copy h2 {
  margin: 5px 0 2px;
  color: #163a25 !important;
  font-size: clamp(22px, 3vw, 30px);
  font-weight: 780 !important;
  letter-spacing: -.04em;
}

.owner-profile-hero-copy p {
  margin: 0;
  color: #5d7464 !important;
  font-size: 13px;
}

.owner-profile-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  margin-top: 12px;
}

.owner-profile-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 25px;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 750;
}

.owner-profile-badge--role {
  color: #536463;
  background: rgba(255, 255, 255, .72);
  border: 1px solid rgba(181, 207, 189, .85);
}

.owner-profile-badge--active {
  color: #14733a;
  background: #dcfce7;
}

.owner-profile-status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.owner-profile-hero-meta {
  min-width: 178px;
  padding-left: 20px;
  border-left: 1px solid rgba(137, 181, 150, .45);
}

.owner-profile-meta-label {
  display: block;
  margin-bottom: 6px;
  color: #6c8373;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: .12em;
}

.owner-profile-hero-meta strong {
  display: block;
  color: #1f5d38;
  font-size: 17px;
  font-weight: 800;
}

.owner-profile-meta-note {
  display: block;
  margin-top: 3px;
  color: #789080;
  font-size: 10px;
}

.owner-profile-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(300px, .65fr);
  gap: 18px;
  margin-top: 18px;
}

.owner-profile-side-column {
  display: grid;
  align-content: start;
  gap: 18px;
}

.owner-profile-card {
  border: 1px solid #e2ebe4;
  border-radius: 15px;
  background: #ffffff;
  box-shadow: 0 8px 22px rgba(35, 65, 45, .045);
}

.owner-profile-details-card,
.owner-profile-security-card,
.owner-profile-workspace-card {
  padding: 22px;
}

.owner-profile-card-heading {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 21px;
}

.owner-profile-card-icon {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  color: #536463;
  background: #f1f6f2;
}

.owner-profile-card-icon--green {
  color: #168542;
  background: #e9f8ed;
}

.owner-profile-card-icon--violet {
  color: #6854ad;
  background: #f0edff;
}

.owner-profile-card-heading h2 {
  margin: 0;
  color: #1c2b22 !important;
  font-size: 16px;
  font-weight: 760 !important;
}

.owner-profile-card-kicker {
  margin-bottom: 3px;
  color: #7c9183 !important;
  font-size: 9px;
}

.owner-profile-detail-list {
  border-top: 1px solid #eef3ef;
}

.owner-profile-detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  min-height: 55px;
  border-bottom: 1px solid #eef3ef;
}

.owner-profile-detail-label {
  color: #789080;
  font-size: 12px;
}

.owner-profile-detail-row strong {
  max-width: 64%;
  overflow: hidden;
  color: #25372b !important;
  font-size: 13px;
  font-weight: 700 !important;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.owner-profile-security-card,
.owner-profile-workspace-card {
  padding: 20px;
}

.owner-profile-security-state {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border: 1px solid #d8efdd;
  border-radius: 11px;
  background: #f5fcf6;
}

.owner-profile-security-check {
  display: grid;
  place-items: center;
  flex: 0 0 24px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  color: #ffffff;
  background: #22a653;
}

.owner-profile-security-state strong {
  display: block;
  color: #246039 !important;
  font-size: 12px;
  font-weight: 750 !important;
}

.owner-profile-security-state p {
  margin: 3px 0 0;
  color: #6d8775 !important;
  font-size: 10.5px;
  line-height: 1.45;
}

.owner-profile-action {
  justify-content: flex-start;
  width: 100%;
  margin-top: 13px;
  padding: 10px 11px;
  color: #285e3b;
}

.owner-profile-action .app-icon:last-child {
  margin-left: auto;
}

.owner-profile-workspace-copy {
  margin: -3px 0 16px;
  color: #64746a !important;
  font-size: 11.5px;
  line-height: 1.6;
}

.owner-profile-workspace-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 13px;
  border-top: 1px solid #eef3ef;
  color: #7b8d80;
  font-size: 10.5px;
}

.owner-profile-workspace-foot span:first-child {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #5a6f61;
  font-weight: 700;
}

.owner-profile-sync {
  margin-top: 13px;
  color: #819188;
  font-size: 11px;
  text-align: right;
}

/* admin.css intentionally applies high-contrast dark-theme rules to every
   heading/paragraph in the shell. These surfaces are light by design, so
   keep their content readable in either shell theme. */
:global(.sg-shell-admin .content-area .owner-profile-page .owner-profile-hero h2) {
  color: #163a25 !important;
}

:global(.sg-shell-admin .content-area .owner-profile-page .owner-profile-hero p) {
  color: #5d7464 !important;
}

:global(.sg-shell-admin .content-area .owner-profile-page .owner-profile-card h2) {
  color: #1c2b22 !important;
}

:global(.sg-shell-admin .content-area .owner-profile-page .owner-profile-card-kicker) {
  color: #7c9183 !important;
}

:global(.sg-shell-admin .content-area .owner-profile-page .owner-profile-security-state strong) {
  color: #246039 !important;
}

:global(.sg-shell-admin .content-area .owner-profile-page .owner-profile-security-state p),
:global(.sg-shell-admin .content-area .owner-profile-page .owner-profile-workspace-copy) {
  color: #64746a !important;
}

@media (max-width: 780px) {
  .owner-profile-heading,
  .owner-profile-hero {
    align-items: flex-start;
  }

  .owner-profile-heading {
    flex-direction: column;
  }

  .owner-profile-hero {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .owner-profile-hero-meta {
    grid-column: 1 / -1;
    padding-top: 16px;
    padding-left: 0;
    border-top: 1px solid rgba(137, 181, 150, .45);
    border-left: 0;
  }

  .owner-profile-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .owner-profile-page {
    padding-bottom: 18px;
  }

  .owner-profile-hero {
    grid-template-columns: 1fr;
    padding: 22px;
  }

  .owner-profile-avatar {
    width: 68px;
    height: 68px;
    border-width: 5px;
    font-size: 25px;
  }

  .owner-profile-hero-meta {
    grid-column: auto;
  }

  .owner-profile-detail-row {
    align-items: flex-start;
    flex-direction: column;
    justify-content: center;
    gap: 3px;
    padding: 10px 0;
  }

  .owner-profile-detail-row strong {
    max-width: 100%;
    text-align: left;
  }
}
</style>
