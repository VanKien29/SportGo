<template>
  <div class="offers-page">
    <PublicNavbar />

    <main class="offers-container">
      <!-- SEO & Landing Hero Section -->
      <section class="offers-hero" aria-labelledby="offers-hero-title">
        <div class="hero-content">
          <nav class="breadcrumb" aria-label="Điều hướng">
            <router-link to="/">Trang chủ</router-link>
            <span>/</span>
            <strong>Ưu đãi & Mã giảm giá</strong>
          </nav>
          <span class="hero-kicker">TIẾT KIỆM CHI PHÍ ĐẶT SÂN</span>
          <h1 id="offers-hero-title">Mã giảm giá & Ưu đãi đặt sân thể thao SportGo</h1>
          <p class="hero-subtitle">
            Tổng hợp các mã ưu đãi hot nhất cho sân cầu lông, bóng đá, tennis, pickleball. Nhận ngay voucher giảm tới 30% khi đặt sân trực tuyến.
          </p>
          <div class="hero-actions">
            <router-link id="btn-explore-venues" class="primary-button" to="/venues">
              <AppIcon name="calendar" :size="16" />
              Đặt sân ngay
            </router-link>
            <a id="btn-scroll-vouchers" class="secondary-button" href="#vouchers-list">
              Xem tất cả voucher
            </a>
          </div>
        </div>

        <!-- Vector Illustration Graphic -->
        <div class="hero-illustration" aria-hidden="true">
          <svg width="280" height="200" viewBox="0 0 280 200" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="20" y="30" width="240" height="140" rx="12" fill="#ffffff" stroke="#e2e8f0" stroke-width="2" />
            <rect x="35" y="45" width="80" height="110" rx="8" fill="#16a34a" />
            <text x="75" y="95" font-family="sans-serif" font-size="22" font-weight="500" fill="#ffffff" text-anchor="middle">30%</text>
            <text x="75" y="115" font-family="sans-serif" font-size="11" font-weight="500" fill="#ffffff" text-anchor="middle">OFFER</text>
            <rect x="130" y="55" width="115" height="10" rx="3" fill="#0f172a" />
            <rect x="130" y="75" width="90" height="8" rx="2" fill="#1e293b" />
            <rect x="130" y="90" width="105" height="8" rx="2" fill="#1e293b" />
            <rect x="130" y="120" width="80" height="28" rx="6" fill="#ffffff" stroke="#16a34a" stroke-width="1.5" />
            <text x="170" y="138" font-family="monospace" font-size="12" font-weight="500" fill="#16a34a" text-anchor="middle">SPORTGO30</text>
            <circle cx="240" cy="30" r="16" fill="#dcfce7" stroke="#16a34a" stroke-width="1.5" />
            <circle cx="30" cy="170" r="22" fill="#dcfce7" stroke="#16a34a" stroke-width="1.5" />
          </svg>
        </div>
      </section>

      <!-- Landing: How it Works Section -->
      <section class="how-it-works" aria-labelledby="how-it-works-title">
        <h2 id="how-it-works-title">3 bước đơn giản để nhận ưu đãi</h2>
        <div class="steps-grid">
          <div class="step-card">
            <div class="step-number">1</div>
            <h3>Chọn mã phù hợp</h3>
            <p>Duyệt danh sách mã giảm giá từ SportGo hoặc đối tác cụm sân thể thao bên dưới.</p>
          </div>
          <div class="step-card">
            <div class="step-number">2</div>
            <h3>Sao chép hoặc Dùng ngay</h3>
            <p>Sao chép mã voucher hoặc nhấp nút Dùng ngay để chọn cụm sân bạn muốn đặt.</p>
          </div>
          <div class="step-card">
            <div class="step-number">3</div>
            <h3>Nhập mã & Thanh toán</h3>
            <p>Dán mã vào ô Voucher tại bước thanh toán để nhận ngay tiền giảm trực tiếp.</p>
          </div>
        </div>
      </section>

      <!-- Category Filter Tabs -->
      <section id="vouchers-list" class="filter-section" aria-label="Bộ lọc voucher">
        <div class="filter-tabs">
          <button
            id="tab-filter-all"
            type="button"
            :class="{ active: selectedTab === 'all' }"
            @click="selectedTab = 'all'"
          >
            Tất cả ưu đãi ({{ offers.length }})
          </button>
          <button
            id="tab-filter-platform"
            type="button"
            :class="{ active: selectedTab === 'platform' }"
            @click="selectedTab = 'platform'"
          >
            Voucher SportGo ({{ platformOffersCount }})
          </button>
          <button
            id="tab-filter-venue"
            type="button"
            :class="{ active: selectedTab === 'venue' }"
            @click="selectedTab = 'venue'"
          >
            Voucher cụm sân ({{ venueOffersCount }})
          </button>
        </div>
      </section>

      <!-- Loading State -->
      <div v-if="loading" class="state-block" aria-live="polite">
        <span class="spinner" aria-hidden="true"></span>
        <p>Đang tải danh sách ưu đãi mới nhất...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="state-block state-error" role="alert">
        <AppIcon name="alert" :size="24" />
        <strong>Chưa thể tải dữ liệu ưu đãi</strong>
        <p>{{ error }}</p>
        <button id="btn-retry-offers" type="button" class="retry-button" @click="load">Thử lại</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredOffers.length === 0" class="state-block">
        <AppIcon name="newspaper" :size="28" />
        <strong>Hiện chưa có mã ưu đãi trong danh mục này</strong>
        <p>Vui lòng chọn danh mục khác hoặc quay lại sau để cập nhật các ưu đãi mới nhất.</p>
        <router-link id="btn-empty-find-venue" class="primary-button" to="/venues">Tìm sân thể thao</router-link>
      </div>

      <!-- Coupon Ticket List Grid -->
      <div v-else class="coupon-grid">
        <article v-for="offer in filteredOffers" :key="offer.id" class="coupon-ticket">
          <!-- Coupon Left Block -->
          <div class="coupon-left">
            <span class="coupon-amount">
              {{ offer.discount_type === 'percent' ? `${offer.discount_value}%` : moneyShort(offer.discount_value) }}
            </span>
            <span class="coupon-label">GIẢM</span>
          </div>

          <!-- Coupon Right Block -->
          <div class="coupon-right">
            <div class="coupon-meta">
              <span class="coupon-source">
                {{ offer.owner_type === 'venue' ? 'Ưu đãi cụm sân' : 'Voucher SportGo' }}
              </span>
              <div class="code-box" title="Nhấp để sao chép" @click="copy(offer.code)">
                <code>{{ offer.code }}</code>
              </div>
            </div>

            <h3 class="coupon-title">{{ offer.name }}</h3>

            <p class="coupon-desc">{{ offer.description || summary(offer) }}</p>

            <div class="coupon-details">
              <span>Đơn từ <strong>{{ money(offer.min_order_amount) }}</strong></span>
              <span v-if="offer.max_discount_amount"> · Tối đa <strong>{{ money(offer.max_discount_amount) }}</strong></span>
              <span v-if="offer.valid_to"> · Hạn: <strong>{{ date(offer.valid_to) }}</strong></span>
            </div>

            <div class="coupon-actions">
              <button
                :id="`btn-copy-${offer.id}`"
                type="button"
                class="action-btn copy-btn"
                @click="copy(offer.code)"
              >
                <AppIcon name="copy" :size="14" />
                Sao chép mã
              </button>
              <router-link
                :id="`btn-use-${offer.id}`"
                class="action-btn use-btn"
                to="/venues"
              >
                Dùng ngay
              </router-link>
            </div>
          </div>
        </article>
      </div>

      <!-- SEO FAQ Section -->
      <section class="seo-faq-section" aria-labelledby="seo-faq-title">
        <h2 id="seo-faq-title">Câu hỏi thường gặp về mã giảm giá SportGo</h2>
        <div class="faq-list">
          <details class="faq-item">
            <summary class="faq-question">
              Làm thế nào để sử dụng mã giảm giá khi đặt sân trên SportGo?
            </summary>
            <div class="faq-answer">
              <p>
                Sau khi chọn sân, ngày và khung giờ chơi tại trang Tìm sân, bạn tiến hành đến màn hình Thanh toán. Tại bước này, hãy nhập hoặc chọn mã voucher phù hợp trong mục "Mã ưu đãi". Hệ thống sẽ tự động trừ số tiền giảm giá trực tiếp vào tổng tiền thanh toán.
              </p>
            </div>
          </details>

          <details class="faq-item">
            <summary class="faq-question">
              Mã ưu đãi áp dụng cho những bộ môn thể thao nào?
            </summary>
            <div class="faq-answer">
              <p>
                Mã giảm giá trên SportGo áp dụng linh hoạt cho đa dạng các bộ môn thể thao bao gồm sân cầu lông, sân bóng đá cỏ nhân tạo, sân tennis, sân pickleball và sân bóng rổ tùy thuộc vào từng chương trình khuyến mãi.
              </p>
            </div>
          </details>

          <details class="faq-item">
            <summary class="faq-question">
              Một đơn đặt sân có thể áp dụng đồng thời nhiều mã giảm giá không?
            </summary>
            <div class="faq-answer">
              <p>
                Mỗi đơn đặt sân áp dụng tối đa 1 mã ưu đãi từ SportGo hoặc 1 mã khuyến mãi do chủ cụm sân phát hành cho lượt đặt đó.
              </p>
            </div>
          </details>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';

const toast = useToast();
const offers = ref([]);
const loading = ref(true);
const error = ref('');
const selectedTab = ref('all');

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api('/api/offers');
    offers.value = Array.isArray(response.data) ? response.data : [];
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải danh sách ưu đãi.';
    offers.value = [];
  } finally {
    loading.value = false;
  }
}

const platformOffersCount = computed(() => offers.value.filter((o) => o.owner_type !== 'venue').length);
const venueOffersCount = computed(() => offers.value.filter((o) => o.owner_type === 'venue').length);

const filteredOffers = computed(() => {
  if (selectedTab.value === 'platform') {
    return offers.value.filter((o) => o.owner_type !== 'venue');
  }
  if (selectedTab.value === 'venue') {
    return offers.value.filter((o) => o.owner_type === 'venue');
  }
  return offers.value;
});

function summary(offer) {
  if (offer.discount_type === 'percent') {
    return `Giảm ${offer.discount_value}% cho đơn đặt sân từ ${money(offer.min_order_amount)}.`;
  }
  return `Giảm trực tiếp ${money(offer.discount_value)} cho đơn từ ${money(offer.min_order_amount)}.`;
}

function money(value) {
  return `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))} đ`;
}

function moneyShort(value) {
  const val = Number(value || 0);
  if (val >= 1000) {
    return `${Math.round(val / 1000)}K`;
  }
  return `${val}đ`;
}

function date(value) {
  if (!value) return '';
  const dateObj = new Date(value);
  if (Number.isNaN(dateObj.getTime())) return '';
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(dateObj);
}

async function copy(code) {
  try {
    await navigator.clipboard.writeText(code);
    toast.success(`Đã sao chép mã ${code}`);
  } catch {
    toast.info(`Mã ưu đãi: ${code}`);
  }
}

onMounted(() => {
  document.title = 'Mã Giảm Giá & Ưu Đãi Đặt Sân Thể Thao Mới Nhất | SportGo';
  load();
});
</script>

<style scoped>
/* ─── BASE ─── */
.offers-page {
  min-height: 100vh;
  background: #f8fafc;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #0f172a;
}

.offers-container {
  max-width: 1180px;
  margin: 0 auto;
  padding: 24px 20px 80px;
}

/* Force all text/headers to weight 500 max & dark color */
h1, h2, h3, strong, b, code {
  font-weight: 500 !important;
  color: #0f172a !important;
}

/* ─── BREADCRUMB ─── */
.breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #0f172a;
  margin-bottom: 10px;
}

.breadcrumb a {
  color: #16a34a;
  text-decoration: none;
  font-weight: 500;
}

.breadcrumb a:hover {
  text-decoration: underline;
}

.breadcrumb span, .breadcrumb strong {
  color: #0f172a;
  font-weight: 500;
}

/* ─── LANDING HERO SECTION ─── */
.offers-hero {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 32px 36px;
  margin-bottom: 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 32px;
  flex-wrap: wrap;
}

.hero-content {
  flex: 1;
  min-width: 300px;
}

.hero-kicker {
  display: inline-block;
  font-size: 12px;
  font-weight: 500;
  color: #16a34a;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.hero-content h1 {
  font-size: clamp(24px, 3.5vw, 32px);
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 10px;
  line-height: 1.25;
}

.hero-subtitle {
  font-size: 15px;
  color: #1e293b;
  font-weight: 400;
  margin: 0 0 20px;
  line-height: 1.55;
  max-width: 620px;
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.primary-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 22px;
  border: none;
  border-radius: 8px;
  background: #16a34a;
  color: #ffffff;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap;
}

.primary-button:hover {
  background: #15803d;
}

.secondary-button {
  display: inline-flex;
  align-items: center;
  padding: 11px 20px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
}

.secondary-button:hover {
  border-color: #16a34a;
  color: #16a34a;
}

.hero-illustration {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ─── HOW IT WORKS SECTION ─── */
.how-it-works {
  margin-bottom: 36px;
}

.how-it-works h2 {
  font-size: 20px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 18px;
}

.steps-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 768px) {
  .steps-grid {
    grid-template-columns: 1fr;
  }
}

.step-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.step-number {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #16a34a;
  font-size: 15px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
}

.step-card h3 {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.step-card p {
  font-size: 13.5px;
  color: #1e293b;
  font-weight: 400;
  margin: 0;
  line-height: 1.5;
}

/* ─── FILTER TABS ─── */
.filter-section {
  margin-bottom: 24px;
}

.filter-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-tabs button {
  padding: 8px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.filter-tabs button:hover {
  border-color: #16a34a;
  color: #16a34a;
}

.filter-tabs button.active {
  background: #16a34a;
  border-color: #16a34a;
  color: #ffffff;
}

/* ─── STATE BLOCKS ─── */
.state-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 64px 20px;
  text-align: center;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  color: #0f172a;
  font-size: 14px;
}

.state-block strong {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.state-block p {
  margin: 0;
  color: #1e293b;
  font-weight: 400;
}

.state-error {
  color: #dc2626;
}

.retry-button {
  padding: 8px 18px;
  border: 1px solid #dc2626;
  border-radius: 6px;
  background: #ffffff;
  color: #dc2626;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.spinner {
  display: block;
  width: 32px;
  height: 32px;
  border: 3px solid #dcfce7;
  border-top-color: #16a34a;
  border-radius: 50%;
  animation: cm-spin 0.75s linear infinite;
}

@keyframes cm-spin { to { transform: rotate(360deg); } }

/* ─── COUPON GRID ─── */
.coupon-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  margin-bottom: 48px;
}

@media (max-width: 860px) {
  .coupon-grid {
    grid-template-columns: 1fr;
  }
}

/* ─── COUPON TICKET LAYOUT ─── */
.coupon-ticket {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  display: flex;
  overflow: hidden;
}

.coupon-left {
  width: 110px;
  background: #16a34a;
  color: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 16px 8px;
  flex-shrink: 0;
}

.coupon-amount {
  font-size: 24px;
  font-weight: 500;
  line-height: 1.1;
  color: #ffffff !important;
}

.coupon-label {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  margin-top: 4px;
  color: #ffffff !important;
}

.coupon-right {
  flex: 1;
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.coupon-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.coupon-source {
  font-size: 12px;
  font-weight: 500;
  color: #16a34a;
}

.code-box {
  border: 1px dashed #16a34a;
  border-radius: 6px;
  padding: 3px 8px;
  background: #f0fdf4;
  cursor: pointer;
}

.code-box code {
  font-family: monospace;
  font-size: 13px;
  font-weight: 500;
  color: #16a34a !important;
}

.coupon-title {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
  line-height: 1.3;
}

.coupon-desc {
  font-size: 13px;
  color: #1e293b;
  font-weight: 400;
  margin: 0;
  line-height: 1.45;
}

.coupon-details {
  font-size: 12px;
  color: #1e293b;
  font-weight: 400;
  margin-top: 2px;
}

.coupon-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
}

.copy-btn {
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
}

.copy-btn:hover {
  border-color: #16a34a;
  color: #16a34a;
}

.use-btn {
  border: 1px solid #16a34a;
  background: #16a34a;
  color: #ffffff;
}

.use-btn:hover {
  background: #15803d;
  border-color: #15803d;
}

/* ─── SEO FAQ SECTION ─── */
.seo-faq-section {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 28px;
}

.seo-faq-section h2 {
  font-size: 20px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 20px;
}

.faq-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.faq-item {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 14px 16px;
  background: #ffffff;
}

.faq-question {
  font-size: 15px;
  font-weight: 500;
  color: #0f172a;
  cursor: pointer;
  outline: none;
}

.faq-question:hover {
  color: #16a34a;
}

.faq-answer {
  padding-top: 10px;
}

.faq-answer p {
  font-size: 14px;
  color: #1e293b;
  font-weight: 400;
  margin: 0;
  line-height: 1.6;
}
</style>
