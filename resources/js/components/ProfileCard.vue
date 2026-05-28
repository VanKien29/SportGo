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
        <h1 class="hero-name">{{ user?.fullName || '—' }}</h1>
        <div class="role-badge" :class="user?.role">
          <span class="role-dot"></span>
          {{ roleLabel }}
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
      </div>
    </div>

    <!-- ── Footer actions ── -->
    <div class="pcard-footer">
      <button class="btn-back" @click="$emit('go-back')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="19" y1="12" x2="5" y2="12"/>
          <polyline points="12 19 5 12 12 5"/>
        </svg>
        Quay lại
      </button>
      <div class="status-indicator">
        <span class="status-dot"></span>
        Đang hoạt động
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProfileCard',
  props: {
    user: { type: Object, default: null },
  },
  emits: ['go-back'],
  computed: {
    userInitial() {
      return this.user?.fullName?.charAt(0)?.toUpperCase() || '?';
    },
    roleLabel() {
      const map = { admin: 'Quản trị viên', owner: 'Chủ sân', user: 'Người dùng' };
      return map[this.user?.role] || 'Khách';
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

.hero-name {
  font-size: 22px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.4px;
  margin-bottom: 12px;
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
  gap: 4px;
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

/* ── Footer ── */
.pcard-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 32px 28px;
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
  .avatar { width: 68px; height: 68px; font-size: 26px; }
}
</style>
