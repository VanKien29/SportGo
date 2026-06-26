<template>
  <div class="home">
    <PublicNavbar />
    <VipPromptToast v-if="showVipPrompt" :duration="9000" />

    <!-- Hero Section -->
    <section class="hero">
      <div class="hero-bg">
        <div class="hero-pattern"></div>
        <div class="hero-gradient"></div>
      </div>
      <div class="hero-content">
        <div class="hero-badge">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
          </svg>
          Nền tảng đặt sân #1 Việt Nam
        </div>
        <h1 class="hero-title">
          Đặt sân thể thao<br>
          <span class="hero-accent">nhanh chóng & dễ dàng</span>
        </h1>
        <p class="hero-desc">
          Tìm kiếm, đặt sân và kết nối với cộng đồng thể thao trên khắp cả nước.
          SportGo giúp bạn tận hưởng trải nghiệm thể thao tuyệt vời nhất.
        </p>
        <div class="hero-actions">
          <router-link to="/login" class="btn-primary" v-if="!user">
            Bắt đầu ngay
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="5" y1="12" x2="19" y2="12"/>
              <polyline points="12 5 19 12 12 19"/>
            </svg>
          </router-link>
        </div>

        <!-- Stats -->
        <div class="hero-stats">
          <div class="stat">
            <span class="stat-number">500+</span>
            <span class="stat-label">Sân thể thao</span>
          </div>
          <div class="stat-divider"></div>
          <div class="stat">
            <span class="stat-number">10K+</span>
            <span class="stat-label">Người chơi</span>
          </div>
          <div class="stat-divider"></div>
          <div class="stat">
            <span class="stat-number">50K+</span>
            <span class="stat-label">Lượt đặt sân</span>
          </div>
        </div>
      </div>
    </section>

    <HomeBannerCarousel position="home" />

    <!-- Features -->
    <section class="features">
      <div class="features-inner">
        <div class="feature-card">
          <div class="feature-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"/>
              <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
          </div>
          <h3>Tìm sân dễ dàng</h3>
          <p>Tìm kiếm sân theo vị trí, loại sân, giá cả và thời gian phù hợp với bạn.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
          <h3>Đặt lịch tức thì</h3>
          <p>Đặt sân online 24/7, xác nhận nhanh chóng, thanh toán tiện lợi.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <h3>Kết nối cộng đồng</h3>
          <p>Tuyển bạn chơi, chia sẻ trải nghiệm và xây dựng cộng đồng thể thao.</p>
        </div>
      </div>
    </section>
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
    return { user: getAuth() };
  },
  computed: {
    showVipPrompt() {
      return !this.user || this.user.role === 'user';
    },
  },
};
</script>

<style scoped>
.home {
  min-height: 100vh;
  background: var(--sg-white);
}

/* ── Hero ── */
.hero {
  position: relative;
  min-height: 600px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 120px 24px 80px;
  overflow: hidden;
}
.hero-bg {
  position: absolute;
  inset: 0;
}
.hero-pattern {
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(circle at 20% 50%, rgba(34,197,94,.08) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(34,197,94,.06) 0%, transparent 40%),
    radial-gradient(circle at 60% 80%, rgba(34,197,94,.04) 0%, transparent 40%);
}
.hero-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(255,255,255,.8) 0%, rgba(248,250,252,1) 100%);
}
.hero-content {
  position: relative;
  z-index: 1;
  text-align: center;
  max-width: 700px;
}
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
  border-radius: var(--sg-radius-full);
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 24px;
}
.hero-title {
  font-size: 52px;
  font-weight: 800;
  line-height: 1.15;
  color: var(--sg-dark);
  letter-spacing: -1px;
  margin-bottom: 20px;
}
.hero-accent {
  background: linear-gradient(135deg, var(--sg-green), var(--sg-green-dark));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero-desc {
  font-size: 17px;
  line-height: 1.7;
  color: var(--sg-text-muted);
  max-width: 540px;
  margin: 0 auto 36px;
}
.hero-actions {
  margin-bottom: 48px;
}
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 32px;
  background: var(--sg-green);
  color: #fff;
  border-radius: var(--sg-radius-full);
  font-size: 15px;
  font-weight: 700;
  transition: var(--sg-transition);
  box-shadow: 0 4px 14px rgba(34,197,94,.3);
}
.btn-primary:hover {
  background: var(--sg-green-dark);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(34,197,94,.4);
}

/* Stats */
.hero-stats {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 32px;
}
.stat {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.stat-number {
  font-size: 28px;
  font-weight: 800;
  color: var(--sg-dark);
}
.stat-label {
  font-size: 13px;
  color: var(--sg-text-muted);
  margin-top: 4px;
}
.stat-divider {
  width: 1px;
  height: 40px;
  background: var(--sg-border);
}

/* ── Features ── */
.features {
  padding: 60px 24px 80px;
  background: var(--sg-surface);
}
.features-inner {
  max-width: 1080px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}
.feature-card {
  padding: 32px;
  background: var(--sg-white);
  border-radius: var(--sg-radius);
  border: 1px solid var(--sg-border);
  transition: var(--sg-transition);
}
.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--sg-shadow-lg);
  border-color: var(--sg-green);
}
.feature-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}
.feature-card h3 {
  font-size: 17px;
  font-weight: 700;
  color: var(--sg-dark);
  margin-bottom: 10px;
}
.feature-card p {
  font-size: 14px;
  line-height: 1.7;
  color: var(--sg-text-muted);
}

@media (max-width: 768px) {
  .hero { min-height: auto; padding: 100px 20px 60px; }
  .hero-title { font-size: 32px; }
  .hero-desc { font-size: 15px; }
  .hero-stats { gap: 20px; }
  .stat-number { font-size: 22px; }
  .features-inner { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .hero-title { font-size: 28px; }
  .hero-stats { flex-direction: column; gap: 16px; }
  .stat-divider { width: 40px; height: 1px; }
}
</style>
