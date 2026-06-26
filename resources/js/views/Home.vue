<template>
  <div class="home-container">
    <!-- Navbar with dark theme -->
    <PublicNavbar theme="dark" />
    <VipPromptToast v-if="showVipPrompt" :duration="9000" />
    
    <!-- Ambient glowing backgrounds in white/gray -->
    <div class="glow glow-top"></div>
    <div class="glow glow-center"></div>

    <!-- Main Hero Content -->
    <main class="hero-section">
      <div class="hero-content">
        <!-- Actions -->
        <div class="hero-actions animate-slide-up-delayed-more">
          <router-link to="/venues" class="btn-primary">
            Khám phá sân ngay
          </router-link>

          <router-link to="/become-partner" class="btn-secondary">
            <span class="btn-secondary-inner">
              Dành cho chủ sân
            </span>
          </router-link>
        </div>
      </div>

      <!-- Interactive Dashboard/Feature Section -->
      <div class="slider-wrapper animate-slide-up-delayed-more">
        <!-- Left Slider Arrow -->
        <button class="arrow-btn arrow-left" @click="prevTab" aria-label="Trở lại">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
        </button>

        <!-- Main Feature Mockup Card -->
        <div class="preview-card-container">
          <div class="preview-card-header">
            <div class="header-dots">
              <span class="dot"></span>
              <span class="dot"></span>
              <span class="dot"></span>
            </div>
            <div class="tab-selector">
              <button :class="['tab-btn', currentTab === 0 ? 'active' : '']" @click="currentTab = 0">Dashboard</button>
              <button :class="['tab-btn', currentTab === 1 ? 'active' : '']" @click="currentTab = 1">Lịch Đặt Sân</button>
              <button :class="['tab-btn', currentTab === 2 ? 'active' : '']" @click="currentTab = 2">Doanh Thu</button>
            </div>
          </div>

          <div class="preview-card-content">
            <transition name="tab-fade" mode="out-in">
              <div v-if="currentTab === 0" key="dashboard" class="dashboard-mockup">
                <!-- Sidebar Panel -->
                <div class="mockup-sidebar">
                  <nav class="sidebar-nav">
                    <div class="nav-item active">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                      Tổng quan
                    </div>
                    <div class="nav-item">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                      Sân đấu
                    </div>
                    <div class="nav-item">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                      Đội nhóm
                    </div>
                  </nav>
                </div>
              </div>
              <div v-else-if="currentTab === 1" key="calendar" class="calendar-mockup">
                <div class="calendar-grid-mockup">
                  <div class="calendar-timeline-row">
                    <div class="time-label">Khung giờ</div>
                    <div class="court-header">Sân Bóng Đá A</div>
                    <div class="court-header">Sân Tennis 1</div>
                    <div class="court-header">Sân Cầu Lông 3</div>
                  </div>

                  <div class="calendar-grid-row">
                    <div class="time-cell">17:00 - 18:30</div>
                    <div class="slot-cell">
                      <div class="booked-slot slot-soccer">FC Brother</div>
                    </div>
                    <div class="slot-cell">
                      <div class="booked-slot slot-tennis">Anh Tuấn Tennis</div>
                    </div>
                    <div class="slot-cell">
                      <div class="slot-empty">Còn trống</div>
                    </div>
                  </div>

                  <div class="calendar-grid-row">
                    <div class="time-cell">18:30 - 20:00</div>
                    <div class="slot-cell">
                      <div class="booked-slot slot-soccer">FC Friends</div>
                    </div>
                    <div class="slot-cell">
                      <div class="slot-empty">Còn trống</div>
                    </div>
                    <div class="slot-cell">
                      <div class="booked-slot slot-badminton">Club Cầu Lông Q1</div>
                    </div>
                  </div>

                  <div class="calendar-grid-row">
                    <div class="time-cell">20:00 - 21:30</div>
                    <div class="slot-cell">
                      <div class="booked-slot slot-soccer">FC Văn Phòng</div>
                    </div>
                    <div class="slot-cell">
                      <div class="booked-slot slot-tennis">Chị Vy & Bạn</div>
                    </div>
                    <div class="slot-cell">
                      <div class="booked-slot slot-badminton">Cầu lông tự do</div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else key="reports" class="reports-mockup">
                <div class="reports-grid">
                  <!-- Custom visual SVG bar graph -->
                  <div class="chart-panel">
                    <div class="chart-title-sub">Biểu đồ doanh thu tuần (Triệu VNĐ)</div>
                    <div class="chart-bars-flex">
                      <div class="chart-bar-container">
                        <div class="chart-bar" style="height: 45%;">
                          <span class="bar-value">9.2M</span>
                        </div>
                        <span class="bar-label">Tuần 1</span>
                      </div>
                      <div class="chart-bar-container">
                        <div class="chart-bar" style="height: 60%;">
                          <span class="bar-value">12.1M</span>
                        </div>
                        <span class="bar-label">Tuần 2</span>
                      </div>
                      <div class="chart-bar-container">
                        <div class="chart-bar" style="height: 75%;">
                          <span class="bar-value">15.5M</span>
                        </div>
                        <span class="bar-label">Tuần 3</span>
                      </div>
                      <div class="chart-bar-container">
                        <div class="chart-bar current-week-bar" style="height: 90%;">
                          <span class="bar-value">18.4M</span>
                        </div>
                        <span class="bar-label">Tuần này</span>
                      </div>
                    </div>
                  </div>

                  <!-- Share breakdown progress -->
                  <div class="distribution-panel">
                    <h5 class="dist-title">Doanh thu theo bộ môn</h5>
                    <div class="dist-list">
                      <div class="dist-item">
                        <div class="dist-label-flex">
                          <span>Sân Bóng Đá</span>
                          <span class="dist-pct">45%</span>
                        </div>
                        <div class="progress-track"><div class="progress-bar" style="width: 45%;"></div></div>
                      </div>

                      <div class="dist-item">
                        <div class="dist-label-flex">
                          <span>Sân Cầu Lông</span>
                          <span class="dist-pct">35%</span>
                        </div>
                        <div class="progress-track"><div class="progress-bar" style="width: 35%;"></div></div>
                      </div>

                      <div class="dist-item">
                        <div class="dist-label-flex">
                          <span>Sân Tennis</span>
                          <span class="dist-pct">20%</span>
                        </div>
                        <div class="progress-track"><div class="progress-bar" style="width: 20%;"></div></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </transition>
          </div>
        </div>

        <!-- Right Slider Arrow -->
        <button class="arrow-btn arrow-right" @click="nextTab" aria-label="Tiếp theo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </button>
      </div>
    </main>
    <HomeBannerCarousel position="home" />
  </div>
</template>

<script>
import HomeBannerCarousel from '../components/HomeBannerCarousel.vue';
import PublicNavbar from '../components/PublicNavbar.vue';
import VipPromptToast from '../components/VipPromptToast.vue';
import { getAuth } from '../stores/auth.js';

export default {
  name: 'HomeView',
  components: { HomeBannerCarousel, PublicNavbar, VipPromptToast },
  data() {
    return {
      user: getAuth(),
      currentTab: 0,
      totalTabs: 3
    };
  },
  methods: {
    nextTab() {
      this.currentTab = (this.currentTab + 1) % this.totalTabs;
    },
    prevTab() {
      this.currentTab = (this.currentTab - 1 + this.totalTabs) % this.totalTabs;
    }
  },
  computed: {
    showVipPrompt() {
      return !this.user || this.user.role === 'user';
    },
  },
};
</script>

<style scoped>
/* Base Dark Theme Overrides (strictly black/white/gray) */
.home-container {
  min-height: 100vh;
  background-color: #09090b;
  color: #ffffff;
  position: relative;
  overflow-x: hidden;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  box-sizing: border-box;
}

/* Ambient Radial Glows (strictly translucent whites/grays) */
.glow {
  position: absolute;
  pointer-events: none;
  border-radius: 50%;
  filter: blur(130px);
  z-index: 0;
}
.glow-top {
  top: -150px;
  left: 50%;
  transform: translateX(-50%);
  width: 600px;
  height: 300px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.04) 0%, transparent 80%);
}
.glow-center {
  top: 55%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 700px;
  height: 400px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.02) 0%, transparent 80%);
}

/* Hero Section */
.hero-section {
  position: relative;
  z-index: 1;
  max-width: 1200px;
  margin: 0 auto;
  padding: 130px 24px 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.hero-content {
  max-width: 780px;
  margin-bottom: 54px;
}
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.85);
  margin-bottom: 28px;
}
.badge-tag {
  color: rgba(255, 255, 255, 0.4);
}
.badge-text {
  color: #ffffff;
}
.hero-title {
  font-size: 64px;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -2px;
  color: #ffffff;
  margin-bottom: 22px;
}
.gradient-text {
  background: linear-gradient(to right, #ffffff 40%, rgba(255, 255, 255, 0.5) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.hero-subtitle {
  font-size: 18px;
  line-height: 1.65;
  color: rgba(255, 255, 255, 0.55);
  max-width: 620px;
  margin: 0 auto 36px;
}
.hero-actions {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-bottom: 20px;
}
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 28px;
  background: #ffffff;
  color: #09090b;
  font-weight: 600;
  font-size: 15px;
  border-radius: 9999px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 20px rgba(255, 255, 255, 0.12);
}
.btn-primary:hover {
  background: rgba(255, 255, 255, 0.9);
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(255, 255, 255, 0.2);
}
.btn-icon {
  width: 14px;
  height: 14px;
}
.btn-secondary {
  display: inline-flex;
  align-items: center;
  padding: 12px 28px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #ffffff;
  font-weight: 500;
  font-size: 15px;
  border-radius: 9999px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.07);
  border-color: rgba(255, 255, 255, 0.15);
  transform: translateY(-2px);
}
.btn-secondary-inner {
  display: flex;
  align-items: center;
  gap: 10px;
}
.shortcut-kbd {
  display: inline-flex;
  padding: 2px 6px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  font-size: 10px;
  color: rgba(255, 255, 255, 0.4);
  font-family: inherit;
}
.hero-meta-desc {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.35);
}

/* Slider Section */
.slider-wrapper {
  width: 100%;
  max-width: 1040px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  position: relative;
}
.arrow-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  color: rgba(255, 255, 255, 0.5);
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
  box-sizing: border-box;
}
.arrow-btn:hover {
  background: rgba(255, 255, 255, 0.07);
  border-color: rgba(255, 255, 255, 0.15);
  color: #ffffff;
}
.arrow-btn svg {
  width: 16px;
  height: 16px;
}

/* Mockup Card Container */
.preview-card-container {
  flex-grow: 1;
  max-width: 880px;
  background: rgba(15, 15, 17, 0.65);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.05);
  transition: border-color 0.3s;
}
.preview-card-container:hover {
  border-color: rgba(255, 255, 255, 0.12);
}
.preview-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.01);
}
.header-dots {
  display: flex;
  gap: 6px;
}
.header-dots .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
}
.tab-selector {
  display: flex;
  gap: 4px;
  background: rgba(255, 255, 255, 0.03);
  padding: 3px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}
.tab-btn {
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.5);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  box-sizing: border-box;
}
.tab-btn.active {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.preview-card-content {
  min-height: 380px;
  position: relative;
}

/* ──── Tab 0: Dashboard ──── */
.dashboard-mockup {
  display: flex;
  min-height: 380px;
  text-align: left;
}
.mockup-sidebar {
  width: 180px;
  border-right: 1px solid rgba(255, 255, 255, 0.06);
  padding: 18px;
  background: rgba(255, 255, 255, 0.01);
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 8px;
}
.sidebar-logo-circle {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: 2px solid #ffffff;
}
.sidebar-logo-text {
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: -0.3px;
}
.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  font-size: 12.5px;
  color: rgba(255, 255, 255, 0.45);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}
.nav-item svg {
  opacity: 0.5;
  transition: all 0.15s;
}
.nav-item.active {
  background: rgba(255, 255, 255, 0.06);
  color: #ffffff;
}
.nav-item.active svg {
  opacity: 1;
}
.nav-item:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.03);
}

.mockup-main {
  flex-grow: 1;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.mockup-main-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.mockup-main-title {
  font-size: 16px;
  font-weight: 700;
  color: #ffffff;
}
.mockup-quick-create {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #ffffff;
  color: #09090b;
  font-size: 12px;
  font-weight: 600;
  border-radius: 9999px;
  transition: opacity 0.15s;
}
.mockup-quick-create:hover {
  opacity: 0.9;
}
.mockup-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
.mockup-card {
  padding: 14px 16px;
  background: rgba(255, 255, 255, 0.01);
  border: 1px solid rgba(255, 255, 255, 0.04);
  border-radius: 8px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.mockup-card:hover {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.1);
  transform: translateY(-1px);
}
.card-header-flex {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.card-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.45);
}
.trend-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 1px 5px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #ffffff;
  border-radius: 4px;
}
.card-value {
  font-size: 20px;
  font-weight: 700;
  color: #ffffff;
}
.card-footer-text {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.35);
  margin-top: 4px;
}

/* ──── Tab 1: Calendar ──── */
.calendar-mockup {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-height: 380px;
  text-align: left;
}
.calendar-header-mockup {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.calendar-date-info {
  display: flex;
  align-items: center;
  gap: 16px;
}
.cal-month {
  font-size: 15px;
  font-weight: 700;
  color: #ffffff;
}
.cal-legend {
  display: flex;
  gap: 10px;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.45);
}
.legend-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
.legend-dot.soccer { background: #ffffff; }
.legend-dot.tennis { background: rgba(255, 255, 255, 0.6); }
.legend-dot.badminton { background: rgba(255, 255, 255, 0.3); }

.cal-actions {
  font-size: 11.5px;
  padding: 3px 8px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 6px;
  color: rgba(255, 255, 255, 0.8);
}
.calendar-grid-mockup {
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.01);
}
.calendar-timeline-row {
  display: grid;
  grid-template-columns: 100px repeat(3, 1fr);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  background: rgba(255, 255, 255, 0.02);
}
.time-label, .court-header {
  padding: 8px;
  font-size: 11.5px;
  font-weight: 600;
  text-align: center;
  color: rgba(255, 255, 255, 0.7);
}
.court-header {
  border-left: 1px solid rgba(255, 255, 255, 0.05);
}
.calendar-grid-row {
  display: grid;
  grid-template-columns: 100px repeat(3, 1fr);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.calendar-grid-row:last-child {
  border-bottom: none;
}
.time-cell {
  padding: 10px;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.4);
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
}
.slot-cell {
  border-left: 1px solid rgba(255, 255, 255, 0.05);
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.booked-slot {
  width: 100%;
  padding: 5px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  text-align: center;
  box-sizing: border-box;
}
.slot-soccer {
  background: rgba(255, 255, 255, 0.07);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.09);
}
.slot-tennis {
  background: rgba(255, 255, 255, 0.04);
  color: rgba(255, 255, 255, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.06);
}
.slot-badminton {
  background: rgba(255, 255, 255, 0.02);
  color: rgba(255, 255, 255, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.04);
}
.slot-empty {
  font-size: 10.5px;
  color: rgba(255, 255, 255, 0.15);
  font-style: italic;
}

/* ──── Tab 2: Reports ──── */
.reports-mockup {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-height: 380px;
  text-align: left;
}
.reports-header-flex {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.reports-heading h4 {
  font-size: 15px;
  color: #ffffff;
  margin: 0;
}
.reports-heading p {
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.4);
  margin: 2px 0 0;
}
.reports-period {
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.5);
}
.reports-grid {
  display: grid;
  grid-template-columns: 1.8fr 1.2fr;
  gap: 16px;
}
.chart-panel {
  background: rgba(255, 255, 255, 0.01);
  border: 1px solid rgba(255, 255, 255, 0.04);
  border-radius: 8px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.chart-title-sub {
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.4);
}
.chart-bars-flex {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 140px;
  padding: 0 4px;
}
.chart-bar-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  flex-grow: 1;
  max-width: 48px;
}
.chart-bar {
  width: 24px;
  background: rgba(255, 255, 255, 0.07);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 4px 4px 0 0;
  position: relative;
  display: flex;
  justify-content: center;
  transition: all 0.2s;
}
.chart-bar:hover {
  background: rgba(255, 255, 255, 0.18);
  border-color: #ffffff;
}
.current-week-bar {
  background: rgba(255, 255, 255, 0.15);
  border-color: rgba(255, 255, 255, 0.35);
  animation: pulse-bar 3s infinite ease-in-out;
}
@keyframes pulse-bar {
  0%, 100% { border-color: rgba(255, 255, 255, 0.35); background: rgba(255, 255, 255, 0.15); }
  50% { border-color: #ffffff; background: rgba(255, 255, 255, 0.22); }
}
.bar-value {
  position: absolute;
  top: -20px;
  font-size: 10.5px;
  font-weight: 600;
  color: #ffffff;
}
.bar-label {
  font-size: 10.5px;
  color: rgba(255, 255, 255, 0.4);
}

.distribution-panel {
  background: rgba(255, 255, 255, 0.01);
  border: 1px solid rgba(255, 255, 255, 0.04);
  border-radius: 8px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.dist-title {
  font-size: 13px;
  color: #ffffff;
  margin: 0;
}
.dist-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.dist-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.dist-label-flex {
  display: flex;
  justify-content: space-between;
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.55);
}
.dist-pct {
  font-weight: 600;
  color: #ffffff;
}
.progress-track {
  height: 4px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 2px;
  overflow: hidden;
}
.progress-bar {
  height: 100%;
  background: rgba(255, 255, 255, 0.22);
  border-radius: 2px;
}

/* ──── Vue Transitions & Animations ──── */
.tab-fade-enter-active,
.tab-fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.tab-fade-enter-from {
  opacity: 0;
  transform: translateY(4px);
}
.tab-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fadeIn 0.8s ease-out forwards;
}
.animate-slide-up {
  animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.animate-slide-up-delayed {
  animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.12s forwards;
  opacity: 0;
}
.animate-slide-up-delayed-more {
  animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.24s forwards;
  opacity: 0;
}

/* Responsive Rules */
@media (max-width: 800px) {
  .hero-title {
    font-size: 46px;
  }
  .hero-subtitle {
    font-size: 16px;
  }
  .dashboard-mockup {
    flex-direction: column;
  }
  .mockup-sidebar {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    flex-direction: row;
    overflow-x: auto;
    padding: 12px 18px;
    gap: 12px;
  }
  .sidebar-brand {
    display: none;
  }
  .sidebar-nav {
    flex-direction: row;
    gap: 8px;
  }
  .reports-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .hero-section {
    padding: 100px 16px 40px;
  }
  .hero-actions {
    flex-direction: column;
    gap: 12px;
    width: 100%;
    max-width: 280px;
    margin: 0 auto 20px;
  }
  .btn-primary, .btn-secondary {
    justify-content: center;
    width: 100%;
  }
  .mockup-grid {
    grid-template-columns: 1fr;
  }
  .arrow-btn {
    display: none; /* Hide slider arrows on small screens */
  }
}
</style>
