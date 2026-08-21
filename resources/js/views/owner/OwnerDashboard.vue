<template>
  <section class="owner-dashboard-page">
    <!-- Error Alert -->
    <div v-if="error" class="od-alert od-alert--error" role="alert">
      <AppIcon name="alertCircle" size="16" />
      <span>{{ error }}</span>
      <button type="button" class="od-link-button" @click="loadStats">Thử lại</button>
    </div>

    <!-- Header Hero Bar with Graphic Background -->
    <header class="od-hero">
      <!-- DECORATIVE GRAPHIC SVG BACKGROUND PATTERN -->
      <div class="od-hero-pattern" aria-hidden="true">
        <svg viewBox="0 0 500 120" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="440" cy="60" r="85" stroke="rgba(92, 126, 110, 0.12)" stroke-width="1.5" stroke-dasharray="4 4" />
          <circle cx="440" cy="60" r="45" stroke="rgba(92, 126, 110, 0.15)" stroke-width="1.5" />
          <path d="M260 120 C320 60, 390 10, 480 30" stroke="rgba(92, 126, 110, 0.1)" stroke-width="2" />
          <path d="M220 120 C290 50, 360 -10, 460 -10" stroke="rgba(92, 126, 110, 0.07)" stroke-width="2" />
          <rect x="340" y="20" width="140" height="80" rx="10" stroke="rgba(92, 126, 110, 0.08)" stroke-width="1.2" />
          <line x1="410" y1="20" x2="410" y2="100" stroke="rgba(92, 126, 110, 0.08)" stroke-width="1.2" />
        </svg>
      </div>

      <div class="od-hero-left">
        <div class="od-greeting-pill">
          <span>{{ greeting }}, {{ userName }}</span>
        </div>
        <h1 class="od-hero-title">Bảng điều hành cụm sân</h1>
        <p class="od-hero-sub">{{ selectedClusterLabel }} · {{ periodCaption }}</p>
      </div>

      <!-- Clean Horizontal Date Filter Bar -->
      <div class="od-controls">
        <div class="od-filter-pill-group">
          <button
            v-for="preset in calendarPresets"
            :key="preset.key"
            type="button"
            class="od-filter-pill"
            :class="{ 'is-active': periodKey === preset.key }"
            @click="choosePreset(preset.key)"
          >
            {{ preset.label }}
          </button>
          
          <button
            type="button"
            class="od-filter-pill od-filter-pill--custom"
            :class="{ 'is-active': periodKey === 'custom' }"
            @click="openCalendarModal"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="3" ry="3"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span>{{ customRangeLabel }}</span>
          </button>
        </div>
      </div>
    </header>

    <!-- 1. SPOTLIGHT BANNER -->
    <div class="od-spotlight-banner">
      <div class="od-spotlight-content">
        <div class="od-spotlight-badge">
          <span>TỔNG QUAN NGÀY THI ĐẤU</span>
        </div>
        <h2 class="od-spotlight-title">Vận hành sân hôm nay</h2>
        <p class="od-spotlight-desc">
          Cụm sân đang có {{ stats.today_booking_summary.total }} lượt đặt sân với {{ stats.court_statuses.active }}/{{ stats.court_statuses.total }} sân sẵn sàng phục vụ người chơi.
        </p>
        <div class="od-spotlight-actions">
          <router-link to="/owner/counter-booking" class="od-spotlight-btn od-btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            <span>Tạo booking tại quầy</span>
          </router-link>
          <router-link to="/owner/booking-list" class="od-spotlight-btn od-btn-ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span>Sơ đồ lịch sân</span>
          </router-link>
          <router-link to="/owner/finance" class="od-spotlight-btn od-btn-ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/>
              <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/>
              <circle cx="16" cy="14" r="1.5" fill="currentColor"/>
            </svg>
            <span>Quản lý dòng tiền</span>
          </router-link>
        </div>
      </div>
      <div class="od-spotlight-illustration">
        <img :src="'/images/partner/partner_adv_automation.png'" alt="Dashboard Illustration" class="od-spotlight-img" />
      </div>
    </div>

    <!-- 2. PRIORITY ACTION ITEMS (CẦN XỬ LÝ HÔM NAY) -->
    <section class="od-section od-attention-section" aria-labelledby="od-attention-title">
      <div class="od-section-heading">
        <div class="od-heading-group">
          <h2 id="od-attention-title" class="od-section-title">Cần xử lý hôm nay</h2>
          <span class="od-section-tag">Hành động nhanh</span>
        </div>
        <span class="od-updated">{{ isLoading ? 'Đang đồng bộ...' : 'Cập nhật theo thời gian thực' }}</span>
      </div>

      <div class="od-attention-grid">
        <!-- ACTION 1: BOOKING CẦN XỬ LÝ -->
        <router-link to="/owner/booking-list?status=pending" class="od-attention-item">
          <div class="od-attention-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
              <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
              <path d="M9 14l2 2 4-4"/>
            </svg>
          </div>
          <div class="od-attention-copy">
            <span class="od-attention-value">{{ stats.operations?.pending_bookings || 0 }}</span>
            <span class="od-attention-label">Booking cần xử lý</span>
          </div>
          <AppIcon name="chevronRight" size="14" class="od-attention-arrow" />
        </router-link>

        <!-- ACTION 2: HOÀN TIỀN -->
        <router-link to="/owner/refunds?status=pending_owner_confirmation" class="od-attention-item">
          <div class="od-attention-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              <path d="M9 11h6m-6 0l2.5-2.5M9 11l2.5 2.5"/>
            </svg>
          </div>
          <div class="od-attention-copy">
            <span class="od-attention-value">{{ stats.operations?.pending_refunds || 0 }}</span>
            <span class="od-attention-label">Yêu cầu hoàn tiền</span>
          </div>
          <AppIcon name="chevronRight" size="14" class="od-attention-arrow" />
        </router-link>

        <!-- ACTION 3: LỆNH RÚT TIỀN -->
        <router-link to="/owner/finance" class="od-attention-item">
          <div class="od-attention-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="5" width="20" height="14" rx="3"/>
              <circle cx="12" cy="12" r="3"/>
              <path d="M6 9h.01M18 15h.01"/>
            </svg>
          </div>
          <div class="od-attention-copy">
            <span class="od-attention-value">{{ stats.operations?.pending_withdrawals || 0 }}</span>
            <span class="od-attention-label">Lệnh rút đang xử lý</span>
          </div>
          <AppIcon name="chevronRight" size="14" class="od-attention-arrow" />
        </router-link>

        <!-- ACTION 4: KHIẾU NẠI -->
        <router-link to="/owner/complaints?status=open" class="od-attention-item">
          <div class="od-attention-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              <circle cx="9" cy="10" r="1" fill="currentColor"/>
              <circle cx="12" cy="10" r="1" fill="currentColor"/>
              <circle cx="15" cy="10" r="1" fill="currentColor"/>
            </svg>
          </div>
          <div class="od-attention-copy">
            <span class="od-attention-value">{{ stats.operations?.open_complaints || 0 }}</span>
            <span class="od-attention-label">Khiếu nại đang mở</span>
          </div>
          <AppIcon name="chevronRight" size="14" class="od-attention-arrow" />
        </router-link>
      </div>
    </section>

    <!-- Empty State -->
    <div v-if="!hasClusters" class="od-empty-state">
      <span class="od-empty-icon"><AppIcon name="building" size="22" /></span>
      <div>
        <h2>Chưa có cụm sân để vận hành</h2>
        <p>Hoàn thiện hồ sơ đối tác hoặc tạo cụm sân để bắt đầu theo dõi booking và doanh thu.</p>
      </div>
      <router-link to="/owner/venue-clusters" class="od-primary-button">Quản lý cụm sân</router-link>
    </div>

    <!-- Dashboard Content -->
    <template v-else>
      <!-- 3. KPI SUMMARY METRICS & GAUGE -->
      <section class="od-kpi-grid" aria-label="Chỉ số kinh doanh">
        <!-- KPI 1: TỔNG BOOKING -->
        <article class="od-kpi-card">
          <div class="od-kpi-topline">
            <span class="od-kpi-label">Tổng lượt đặt sân</span>
            <div class="od-kpi-icon-bubble">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="7" rx="9" ry="4"/>
                <path d="M3 7v10c0 2.2 4 4 9 4s9-1.8 9-4V7"/>
                <path d="M3 12c0 2.2 4 4 9 4s9-1.8 9-4"/>
              </svg>
            </div>
          </div>
          <div class="od-kpi-val">{{ isLoading ? '...' : formatNumber(stats.period_summary.bookings) }}</div>
          <small class="od-kpi-note">{{ stats.period_summary.online_bookings || 0 }} online · {{ stats.period_summary.counter_bookings || 0 }} tại quầy</small>
        </article>

        <!-- KPI 2: DOANH THU ĐÃ THU -->
        <article class="od-kpi-card">
          <div class="od-kpi-topline">
            <span class="od-kpi-label">Doanh thu kỳ này</span>
            <div class="od-kpi-icon-bubble">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M14.5 9.5a2.5 2.5 0 0 0-5 0c0 2 3 2 3 4a2 2 0 0 1-4 0"/>
                <path d="M12 7v1.5m0 7V17"/>
              </svg>
            </div>
          </div>
          <div class="od-kpi-val text-sage">{{ isLoading ? '...' : formatCurrency(stats.period_summary.revenue) }}</div>
          <small class="od-kpi-note">Giao dịch đã thanh toán / nhận cọc</small>
        </article>

        <!-- KPI 3: TỶ LỆ HOÀN THÀNH -->
        <article class="od-kpi-card od-kpi-card--gauge">
          <div class="od-gauge-left">
            <span class="od-kpi-label">Tỷ lệ hoàn thành</span>
            <div class="od-kpi-val">{{ completionRate }}%</div>
            <small class="od-kpi-note">{{ stats.period_summary.completed || 0 }} hoàn tất · {{ stats.period_summary.cancelled || 0 }} hủy</small>
          </div>
          <div class="od-gauge-right">
            <svg class="od-donut-gauge" viewBox="0 0 36 36">
              <path class="od-gauge-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
              <path class="od-gauge-fill" :stroke-dasharray="`${completionRate}, 100`" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            </svg>
          </div>
        </article>

        <!-- KPI 4: GIÁ TRỊ TRUNG BÌNH -->
        <article class="od-kpi-card">
          <div class="od-kpi-topline">
            <span class="od-kpi-label">Giá trị trung bình / đơn</span>
            <div class="od-kpi-icon-bubble">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4v16a1 1 0 0 0 1.5.86L8 19l2.5 1.86a1 1 0 0 0 1 0L14 19l2.5 1.86a1 1 0 0 0 1.5-.86V4"/>
                <path d="M8 8h6m-6 4h4"/>
              </svg>
            </div>
          </div>
          <div class="od-kpi-val">{{ isLoading ? '...' : formatCurrency(stats.period_summary.average_booking_value) }}</div>
          <small class="od-kpi-note">Trung bình trên mỗi lượt chơi</small>
        </article>
      </section>

      <!-- 4. MAIN GRID: TODAY'S SCHEDULE & FINANCIAL SIDEBAR -->
      <div class="od-main-grid">
        <section class="od-panel od-schedule-panel" aria-labelledby="od-schedule-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-panel-eyebrow">{{ formatLongDate(today) }}</span>
              <h2 id="od-schedule-title" class="od-panel-title">Lịch sân hôm nay</h2>
            </div>
            <router-link to="/owner/booking-list" class="od-text-link">Mở danh sách lịch</router-link>
          </div>

          <div class="od-today-summary">
            <div class="od-today-stat-pill">
              <span class="pill-lbl">Tổng lịch</span>
              <span class="pill-val">{{ stats.today_booking_summary.total }}</span>
            </div>
            <div class="od-today-stat-pill">
              <span class="pill-lbl">Chờ xử lý</span>
              <span class="pill-val">{{ todayPendingCount }}</span>
            </div>
            <div class="od-today-stat-pill">
              <span class="pill-lbl">Đã thanh toán</span>
              <span class="pill-val">{{ stats.today_booking_summary.paid }}</span>
            </div>
            <div class="od-today-stat-pill">
              <span class="pill-lbl">Doanh thu hôm nay</span>
              <span class="pill-val text-sage">{{ formatCurrency(stats.today_booking_summary.revenue) }}</span>
            </div>
          </div>

          <div v-if="isLoading" class="od-loading-state">Đang tải lịch hôm nay...</div>
          <div v-else-if="!todayBookings.length" class="od-table-empty">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span>Hôm nay chưa có booking.</span>
            <router-link to="/owner/counter-booking" class="od-primary-button">Tạo booking tại quầy</router-link>
          </div>
          <div v-else class="od-booking-list">
            <router-link
              v-for="booking in todayBookings"
              :key="booking.id"
              :to="`/owner/booking-list?keyword=${encodeURIComponent(booking.booking_code)}`"
              class="od-booking-row"
            >
              <div class="od-booking-time-badge">
                <time>{{ booking.time_label }}</time>
              </div>
              <div class="od-booking-main">
                <span class="od-booking-court">{{ booking.court_label }}</span>
                <span class="od-booking-cust">{{ booking.customer_name }} · {{ booking.source_label }}</span>
              </div>
              <div class="od-booking-meta">
                <span class="od-status" :class="statusTone(booking.status)">{{ booking.status_label }}</span>
                <small class="od-payment-tag">{{ booking.payment_state_label }}</small>
              </div>
              <AppIcon name="chevronRight" size="14" class="od-row-arrow" />
            </router-link>
          </div>
        </section>

        <aside class="od-side-column">
          <!-- WALLET LIGHT PANEL -->
          <section class="od-panel od-wallet-panel" aria-labelledby="od-wallet-title">
            <div class="od-panel-heading">
              <div>
                <span class="od-panel-eyebrow">Tài chính cụm sân</span>
                <h2 id="od-wallet-title" class="od-panel-title">Số dư có thể rút</h2>
              </div>
              <div class="od-panel-icon-bubble">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/>
                  <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/>
                  <circle cx="16" cy="14" r="1.5" fill="currentColor"/>
                </svg>
              </div>
            </div>
            
            <div class="od-wallet-value">{{ formatCurrency(stats.wallet.available_balance) }}</div>
            
            <div class="od-wallet-details">
              <div class="od-wallet-detail-row">
                <span>Đang chờ giải ngân</span>
                <span>{{ formatCurrency(stats.wallet.pending_withdrawal_balance) }}</span>
              </div>
              <div class="od-wallet-detail-row">
                <span>Tổng doanh thu tích lũy</span>
                <span>{{ formatCurrency(stats.wallet.total_earned) }}</span>
              </div>
            </div>
            
            <router-link to="/owner/finance" class="od-wallet-btn">
              <span>Quản lý tài chính & Rút tiền</span>
              <AppIcon name="chevronRight" size="14" />
            </router-link>
          </section>

          <!-- COURT HEALTH PANEL -->
          <section class="od-panel od-health-panel" aria-labelledby="od-health-title">
            <div class="od-panel-heading">
              <div>
                <span class="od-panel-eyebrow">Trạng thái sân con</span>
                <h2 id="od-health-title" class="od-panel-title">Sức khỏe sân</h2>
              </div>
              <router-link to="/owner/venue-courts" class="od-text-link">Quản lý</router-link>
            </div>
            <div class="od-health-total">
              <span class="od-health-num">{{ stats.court_statuses.total }}</span>
              <span class="od-health-unit">sân thể thao</span>
            </div>
            <div class="od-health-list">
              <div class="health-row">
                <span class="health-lbl">Đang hoạt động</span>
                <span class="health-val">{{ stats.court_statuses.active }}</span>
              </div>
              <div class="health-row">
                <span class="health-lbl">Bảo trì / Sửa chữa</span>
                <span class="health-val">{{ stats.court_statuses.maintenance }}</span>
              </div>
              <div class="health-row">
                <span class="health-lbl">Tạm ngưng</span>
                <span class="health-val">{{ stats.court_statuses.inactive }}</span>
              </div>
            </div>
          </section>
        </aside>
      </div>

      <!-- 5. 3-COLUMN BENTO LEADERBOARD -->
      <section class="od-bento-section">
        <div class="od-section-heading">
          <div>
            <span class="od-panel-eyebrow">BẢNG XẾP HẠNG & HIỆU SUẤT</span>
            <h2 class="od-section-title">Top Hiệu Quả Vận Hành Cụm Sân</h2>
          </div>
          <span class="od-updated">Dữ liệu tính theo {{ periodCaption }}</span>
        </div>

        <div class="od-bento-grid">
          <!-- BENTO COL 1: BXH DOANH THU SÂN CON -->
          <div class="od-bento-card">
            <div class="od-bento-card-head">
              <div class="od-bento-card-title-group">
                <div class="bento-badge-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h2"/>
                    <path d="M18 9h2a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-2"/>
                    <path d="M6 3h12v7a6 6 0 0 1-12 0V3z"/>
                    <path d="M12 16v4m-3 0h6"/>
                  </svg>
                </div>
                <h3>BXH Doanh Thu Sân</h3>
              </div>
              <router-link to="/owner/venue-courts" class="bento-more-link">Tất cả ›</router-link>
            </div>
            <div v-if="stats.court_revenues.length" class="od-bento-list">
              <div
                v-for="(court, index) in stats.court_revenues.slice(0, 5)"
                :key="court.court_name"
                class="od-bento-item"
              >
                <span class="od-rank-num" :class="{ 'od-rank-num--first': index === 0 }">{{ index + 1 }}</span>
                <div class="od-bento-item-main">
                  <span class="od-bento-item-name">{{ court.court_name }}</span>
                  <div class="od-bento-progress-track">
                    <div class="od-bento-progress-bar" :style="{ width: `${rankingWidth(court.revenue, maxCourtRevenue)}%` }"></div>
                  </div>
                </div>
                <span class="od-bento-item-val">{{ compactCurrency(court.revenue) }}</span>
              </div>
            </div>
            <div v-else class="od-chart-empty">Chưa có dữ liệu doanh thu theo sân.</div>
          </div>

          <!-- BENTO COL 2: BXH KHUNG GIỜ VÀNG -->
          <div class="od-bento-card">
            <div class="od-bento-card-head">
              <div class="od-bento-card-title-group">
                <div class="bento-badge-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"/>
                    <polyline points="12 6 12 12 16 14"/>
                    <path d="M12 2v2m0 16v2M2 12h2m16 0h2"/>
                  </svg>
                </div>
                <h3>BXH Khung Giờ Vàng</h3>
              </div>
              <router-link to="/owner/booking-list" class="bento-more-link">Lịch ›</router-link>
            </div>
            <div v-if="stats.golden_hours.length" class="od-bento-list">
              <div
                v-for="(slot, index) in stats.golden_hours.slice(0, 5)"
                :key="slot.time_slot"
                class="od-bento-item"
              >
                <span class="od-rank-num" :class="{ 'od-rank-num--first': index === 0 }">{{ index + 1 }}</span>
                <div class="od-bento-item-main">
                  <span class="od-bento-item-name">{{ slot.time_slot }}</span>
                  <div class="od-bento-progress-track">
                    <div class="od-bento-progress-bar" :style="{ width: `${rankingWidth(slot.count, maxGoldenHour)}%` }"></div>
                  </div>
                </div>
                <span class="od-bento-item-val">{{ slot.count }} lượt</span>
              </div>
            </div>
            <div v-else class="od-chart-empty">Chưa có dữ liệu khung giờ.</div>
          </div>

          <!-- BENTO COL 3: TOP KHÁCH HÀNG & CLB THÂN THIẾT -->
          <div class="od-bento-card">
            <div class="od-bento-card-head">
              <div class="od-bento-card-title-group">
                <div class="bento-badge-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                  </svg>
                </div>
                <h3>Top Khách Hàng / CLB</h3>
              </div>
              <router-link to="/owner/booking-list" class="bento-more-link">Khách ›</router-link>
            </div>
            <div v-if="topCustomers.length" class="od-bento-list">
              <div
                v-for="(cust, index) in topCustomers.slice(0, 5)"
                :key="cust.name"
                class="od-bento-item"
              >
                <span class="od-rank-num" :class="{ 'od-rank-num--first': index === 0 }">{{ index + 1 }}</span>
                <div class="od-bento-item-main">
                  <span class="od-bento-item-name">{{ cust.name }}</span>
                  <span class="od-bento-sub">{{ cust.source }}</span>
                </div>
                <span class="od-bento-item-val text-sage">{{ cust.count }} đơn</span>
              </div>
            </div>
            <div v-else class="od-chart-empty">Chưa có dữ liệu khách hàng.</div>
          </div>
        </div>
      </section>

      <!-- 6. ANALYTICS & CHARTS SUITE (4 CHARTS GRID) -->
      <section class="od-analytics-section" aria-label="Phân tích chuyên sâu">
        <div class="od-section-heading">
          <div>
            <span class="od-panel-eyebrow">PHÂN TÍCH & BÁO CÁO KINH DOANH</span>
            <h2 class="od-section-title">Trung Tâm Dữ Liệu Vận Hành</h2>
          </div>
          <span class="od-updated">{{ periodCaption }}</span>
        </div>

        <div class="od-chart-grid">
          <!-- CHART 1: XU HƯỚNG DOANH THU & BOOKING (DUAL-MODE) -->
          <article class="od-panel od-chart-panel" aria-labelledby="od-trend-title">
            <div class="od-panel-heading">
              <div>
                <span class="od-panel-eyebrow">Biểu đồ xu hướng</span>
                <h3 id="od-trend-title" class="od-panel-title">
                  {{ activeTrendMetric === 'revenue' ? 'Doanh thu theo ngày' : 'Số lượt booking theo ngày' }}
                </h3>
              </div>
              <div class="od-chart-actions">
                <div class="od-mini-pill-toggle">
                  <button
                    type="button"
                    class="od-mini-toggle-btn"
                    :class="{ 'is-active': activeTrendMetric === 'revenue' }"
                    @click="activeTrendMetric = 'revenue'"
                  >
                    Doanh thu
                  </button>
                  <button
                    type="button"
                    class="od-mini-toggle-btn"
                    :class="{ 'is-active': activeTrendMetric === 'bookings' }"
                    @click="activeTrendMetric = 'bookings'"
                  >
                    Booking
                  </button>
                </div>
              </div>
            </div>
            
            <div class="od-chart-hero-stat">
              <span class="od-hero-stat-val text-sage">
                {{ activeTrendMetric === 'revenue' ? formatCurrency(stats.period_summary.revenue) : `${formatNumber(stats.period_summary.bookings)} lượt` }}
              </span>
              <span class="od-hero-stat-sub">Tổng trong kỳ</span>
            </div>

            <div v-if="trendHasData" class="od-bar-chart" :aria-label="trendAriaLabel">
              <div v-for="bar in trendBars" :key="bar.date_from" class="od-bar-column">
                <span class="od-bar-value">
                  {{ activeTrendMetric === 'revenue' ? compactCurrency(bar.revenue) : bar.bookings }}
                </span>
                <span class="od-bar-track">
                  <span
                    class="od-bar-fill"
                    :style="{
                      height: `${activeTrendMetric === 'revenue' ? trendHeight(bar.revenue) : trendBookingHeight(bar.bookings)}%`
                    }"
                  ></span>
                </span>
                <small class="od-bar-lbl">{{ bar.label }}</small>
              </div>
            </div>
            <div v-else class="od-chart-empty">Chưa có dữ liệu trong khoảng thời gian này.</div>
          </article>

          <!-- CHART 2: MẬT ĐỘ GIỜ CAO ĐIỂM (06h - 23h HOURLY HEATMAP) -->
          <article class="od-panel od-chart-panel" aria-labelledby="od-hourly-title">
            <div class="od-panel-heading">
              <div>
                <span class="od-panel-eyebrow">Phân bổ theo giờ</span>
                <h3 id="od-hourly-title" class="od-panel-title">Mật độ đặt sân (06h - 23h)</h3>
              </div>
              <span class="od-chart-total">{{ peakHourLabel }}</span>
            </div>

            <div class="od-chart-hero-stat">
              <span class="od-hero-stat-val">{{ peakHourCount }} lượt</span>
              <span class="od-hero-stat-sub">Khung giờ cao điểm nhất</span>
            </div>

            <div v-if="hourlyHasData" class="od-bar-chart od-bar-chart--dense">
              <div
                v-for="item in hourlyList"
                :key="item.hour"
                class="od-bar-column"
                :class="{ 'is-peak': item.count === maxHourlyCount && item.count > 0 }"
              >
                <span class="od-bar-value" v-if="item.count > 0">{{ item.count }}</span>
                <span class="od-bar-track">
                  <span
                    class="od-bar-fill od-bar-fill--hourly"
                    :style="{ height: `${hourlyHeight(item.count)}%` }"
                  ></span>
                </span>
                <small class="od-bar-lbl">{{ item.label }}</small>
              </div>
            </div>
            <div v-else class="od-chart-empty">Chưa có lịch đặt trong các khung giờ.</div>
          </article>

          <!-- CHART 3: CƠ CẤU KÊNH ĐẶT SÂN (CHANNELS DONUT & STATS) -->
          <article class="od-panel od-chart-panel" aria-labelledby="od-channel-title">
            <div class="od-panel-heading">
              <div>
                <span class="od-panel-eyebrow">Nguồn đặt sân</span>
                <h3 id="od-channel-title" class="od-panel-title">Cơ cấu kênh đặt sân</h3>
              </div>
              <span class="od-chart-total">{{ formatNumber(stats.period_summary.bookings) }} đơn</span>
            </div>

            <div class="od-channel-layout">
              <div class="od-channel-donut-wrap">
                <svg class="od-channel-donut" viewBox="0 0 36 36">
                  <path class="od-donut-base" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                  <path
                    class="od-donut-segment od-donut-online"
                    :stroke-dasharray="`${onlinePercent}, 100`"
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  />
                  <path
                    class="od-donut-segment od-donut-counter"
                    :stroke-dasharray="`${counterPercent}, 100`"
                    :stroke-dashoffset="`-${onlinePercent}`"
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  />
                </svg>
                <div class="od-donut-center-text">
                  <span class="donut-center-num">{{ onlinePercent }}%</span>
                  <span class="donut-center-lbl">Online</span>
                </div>
              </div>

              <div class="od-channel-legend">
                <div class="od-channel-card od-channel-card--online">
                  <div class="od-ch-top">
                    <span class="od-ch-bullet od-ch-bullet--online"></span>
                    <span class="od-ch-title">App / Web SportGo</span>
                    <span class="od-ch-pct">{{ onlinePercent }}%</span>
                  </div>
                  <div class="od-ch-sub">
                    <span>{{ stats.period_summary.online_bookings || 0 }} booking</span>
                    <span class="od-ch-rev">{{ formatCurrency(onlineRevenueValue) }}</span>
                  </div>
                </div>

                <div class="od-channel-card od-channel-card--counter">
                  <div class="od-ch-top">
                    <span class="od-ch-bullet od-ch-bullet--counter"></span>
                    <span class="od-ch-title">Tại quầy / Vãng lai</span>
                    <span class="od-ch-pct">{{ counterPercent }}%</span>
                  </div>
                  <div class="od-ch-sub">
                    <span>{{ stats.period_summary.counter_bookings || 0 }} booking</span>
                    <span class="od-ch-rev">{{ formatCurrency(counterRevenueValue) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <!-- CHART 4: TRẠNG THÁI BOOKING & TỶ LỆ HOÀN THÀNH -->
          <article class="od-panel od-chart-panel" aria-labelledby="od-status-title">
            <div class="od-panel-heading">
              <div>
                <span class="od-panel-eyebrow">Tiến trình phục vụ</span>
                <h3 id="od-status-title" class="od-panel-title">Trạng thái vận hành booking</h3>
              </div>
              <span class="od-chart-total">{{ completionRate }}% hoàn tất</span>
            </div>

            <div class="od-status-chart">
              <div v-for="status in stats.booking_statuses" :key="status.key" class="od-status-row">
                <div class="od-status-label-group">
                  <span class="status-lbl">{{ status.label }}</span>
                  <span class="status-pct">{{ statusPercent(status.count) }}%</span>
                </div>
                <span class="od-status-track">
                  <span
                    :class="`od-status-fill od-status-fill--${status.key}`"
                    :style="{ width: `${statusPercent(status.count)}%` }"
                  ></span>
                </span>
                <span class="status-cnt">{{ status.count }}</span>
              </div>
            </div>
          </article>
        </div>
      </section>

      <!-- 7. RECENT ACTIVITY PANEL -->
      <section class="od-panel od-recent-panel" aria-labelledby="od-recent-title">
        <div class="od-panel-heading">
          <div>
            <span class="od-panel-eyebrow">Hoạt động gần đây</span>
            <h2 id="od-recent-title" class="od-panel-title">Booking trong kỳ</h2>
          </div>
          <router-link to="/owner/booking-list" class="od-text-link">Xem tất cả</router-link>
        </div>
        <div v-if="stats.recent_bookings.length" class="od-recent-grid">
          <router-link
            v-for="booking in stats.recent_bookings"
            :key="booking.id"
            :to="`/owner/booking-list?keyword=${encodeURIComponent(booking.booking_code)}`"
            class="od-recent-row"
          >
            <span class="od-recent-code">{{ booking.booking_code }}</span>
            <span class="od-recent-cust">{{ booking.customer_name }}</span>
            <span class="od-recent-court">{{ booking.court_label }}</span>
            <span class="od-recent-time">{{ formatDate(booking.booking_date) }} · {{ booking.time_label }}</span>
            <span class="od-status" :class="statusTone(booking.status)">{{ booking.status_label }}</span>
            <span class="od-recent-amount">{{ formatCurrency(booking.total_price) }}</span>
          </router-link>
        </div>
        <div v-else class="od-table-empty">Chưa có booking trong kỳ.</div>
      </section>
    </template>

    <!-- CENTERED CALENDAR MODAL -->
    <Teleport to="body">
      <div v-if="isCalendarModalOpen" class="od-modal-backdrop" @click="closeCalendarModal">
        <div class="od-modal-dialog" @click.stop>
          <div class="od-modal-header">
            <div class="od-modal-title-group">
              <div class="od-modal-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                  <line x1="16" y1="2" x2="16" y2="6"/>
                  <line x1="8" y1="2" x2="8" y2="6"/>
                  <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
              </div>
              <div>
                <h3 class="od-modal-title">Chọn khoảng ngày tùy chỉnh</h3>
                <span class="od-modal-subtitle">Nhấp chọn ngày bắt đầu và kết thúc</span>
              </div>
            </div>
            <button type="button" class="od-modal-close" @click="closeCalendarModal">✕</button>
          </div>

          <!-- Month & Year Navigation -->
          <div class="od-cal-month-nav">
            <button type="button" class="od-cal-nav-btn" title="Tháng trước" @click="prevMonth">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
              </svg>
            </button>
            <span class="od-cal-month-title">{{ calMonthLabel }}</span>
            <button type="button" class="od-cal-nav-btn" title="Tháng sau" @click="nextMonth">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
              </svg>
            </button>
          </div>

          <!-- Weekdays Row -->
          <div class="od-cal-weekdays">
            <span>T2</span>
            <span>T3</span>
            <span>T4</span>
            <span>T5</span>
            <span>T6</span>
            <span>T7</span>
            <span>CN</span>
          </div>

          <!-- Calendar Days Grid -->
          <div class="od-cal-days-grid">
            <div
              v-for="(day, idx) in calendarDays"
              :key="idx"
              class="od-cal-day-cell"
              :class="{
                'is-empty': day.isEmpty,
                'is-today': day.isToday,
                'is-selected': day.isSelected,
                'is-in-range': day.isInRange,
                'is-range-start': day.isRangeStart,
                'is-range-end': day.isRangeEnd,
                'is-future': day.isFuture
              }"
              @click="onDayClick(day)"
            >
              <span v-if="!day.isEmpty">{{ day.dayNumber }}</span>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="od-modal-footer">
            <div class="od-modal-range-display">
              <span class="lbl">Khoảng ngày:</span>
              <span class="val" v-if="tempDateFrom && tempDateTo">
                {{ formatDate(tempDateFrom) }} – {{ formatDate(tempDateTo) }}
              </span>
              <span class="val" v-else-if="tempDateFrom">
                Từ {{ formatDate(tempDateFrom) }}
              </span>
              <span class="val text-muted" v-else>
                Chưa chọn
              </span>
            </div>
            <div class="od-modal-btn-group">
              <button type="button" class="od-btn-modal-cancel" @click="closeCalendarModal">Hủy</button>
              <button
                type="button"
                class="od-btn-modal-apply"
                :disabled="!tempDateFrom"
                @click="applyCustomDateRange"
              >
                Áp dụng
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';
import { venueClusterService } from '../../services/venueClusters.js';
import { getAuth } from '../../stores/auth.js';

const emptyStats = () => ({
  bookings: 0,
  revenue: 0,
  rating: 0,
  venue_cluster_id: null,
  period: { key: 'today', label: 'Hôm nay', date_from: null, date_to: null },
  period_summary: { bookings: 0, revenue: 0, average_booking_value: 0, completed: 0, cancelled: 0, online_bookings: 0, counter_bookings: 0 },
  booking_statuses: [
    { key: 'pending', label: 'Đang chờ xử lý', count: 0 },
    { key: 'confirmed', label: 'Đã xác nhận', count: 0 },
    { key: 'playing', label: 'Đang chơi', count: 0 },
    { key: 'completed', label: 'Hoàn thành', count: 0 },
    { key: 'cancelled', label: 'Hủy / từ chối', count: 0 },
  ],
  revenue_trend: [],
  operations: { pending_bookings: 0, pending_refunds: 0, pending_refund_amount: 0, pending_withdrawals: 0, pending_withdrawal_amount: 0, open_complaints: 0, latest_refunds: [], latest_withdrawals: [] },
  recent_bookings: [],
  court_statuses: { total: 0, active: 0, maintenance: 0, inactive: 0 },
  wallet: { available_balance: 0, pending_withdrawal_balance: 0, total_earned: 0, total_withdrawn: 0 },
  today_booking_summary: { date: null, total: 0, pending_approval: 0, pending_payment: 0, paid: 0, cancelled: 0, revenue: 0 },
  today_bookings: [],
  pending_bookings: [],
  cancelled_today: [],
  golden_hours: [],
  court_revenues: [],
  published_posts: [],
  channel_distribution: null,
  hourly_distribution: [],
});

export default {
  name: 'OwnerDashboard',
  components: { AppIcon },
  data() {
    const todayStr = this.localDateString();
    const now = new Date();
    return {
      user: getAuth() || {},
      selectedCluster: null,
      periodKey: 'today',
      activeTrendMetric: 'revenue',
      customDateFrom: todayStr,
      customDateTo: todayStr,
      tempDateFrom: todayStr,
      tempDateTo: todayStr,
      calMonth: now.getMonth(),
      calYear: now.getFullYear(),
      isCalendarModalOpen: false,
      isLoading: true,
      error: '',
      stats: emptyStats(),
      calendarPresets: [
        { key: 'today', label: 'Hôm nay' },
        { key: '7_days', label: '7 ngày qua' },
        { key: '30_days', label: '30 ngày qua' },
        { key: 'this_month', label: 'Tháng này' },
      ],
    };
  },
  computed: {
    today() {
      return this.localDateString();
    },
    calMonthLabel() {
      return `Tháng ${this.calMonth + 1}, ${this.calYear}`;
    },
    customRangeLabel() {
      if (this.periodKey === 'custom' && this.customDateFrom && this.customDateTo) {
        return this.customDateFrom === this.customDateTo
          ? this.formatDate(this.customDateFrom)
          : `${this.formatDate(this.customDateFrom)} – ${this.formatDate(this.customDateTo)}`;
      }
      return 'Tùy chọn ngày';
    },
    calendarDays() {
      const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
      const firstDayIndex = (new Date(this.calYear, this.calMonth, 1).getDay() + 6) % 7;
      const days = [];

      for (let i = 0; i < firstDayIndex; i++) {
        days.push({ dayNumber: '', dateStr: '', isEmpty: true });
      }

      for (let d = 1; d <= daysInMonth; d++) {
        const monthStr = String(this.calMonth + 1).padStart(2, '0');
        const dayStr = String(d).padStart(2, '0');
        const dateStr = `${this.calYear}-${monthStr}-${dayStr}`;

        const isStart = dateStr === this.tempDateFrom;
        const isEnd = dateStr === this.tempDateTo;
        const inRange = Boolean(
          this.tempDateFrom &&
          this.tempDateTo &&
          dateStr > this.tempDateFrom &&
          dateStr < this.tempDateTo
        );

        days.push({
          dayNumber: d,
          dateStr,
          isEmpty: false,
          isToday: dateStr === this.today,
          isFuture: dateStr > this.today,
          isSelected: isStart || isEnd,
          isInRange: inRange,
          isRangeStart: isStart,
          isRangeEnd: isEnd,
        });
      }

      return days;
    },
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'Chủ sân';
    },
    greeting() {
      const hour = new Date().getHours();
      return hour < 11 ? 'Chào buổi sáng' : hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối';
    },
    hasClusters() {
      return Boolean(this.selectedCluster?.id || this.stats.venue_cluster_id);
    },
    selectedClusterLabel() {
      return this.selectedCluster?.name || (this.stats.venue_cluster_id ? 'Cụm sân đang chọn' : 'Tất cả cụm sân');
    },
    periodCaption() {
      if (!this.stats.period?.date_from) return 'Đang cập nhật';
      return `${this.stats.period.label} · ${this.formatDateRange(this.stats.period.date_from, this.stats.period.date_to)}`;
    },
    todayBookings() {
      return this.stats.today_bookings || [];
    },
    todayPendingCount() {
      return Number(this.stats.today_booking_summary.pending_approval || 0) + Number(this.stats.today_booking_summary.pending_payment || 0);
    },
    completionRate() {
      const summary = this.stats.period_summary || {};
      return summary.bookings ? Math.round((Number(summary.completed || 0) / Number(summary.bookings)) * 100) : 0;
    },
    topCustomers() {
      const counts = {};
      (this.stats.recent_bookings || []).forEach((b) => {
        if (!b.customer_name) return;
        if (!counts[b.customer_name]) {
          counts[b.customer_name] = { name: b.customer_name, count: 0, revenue: 0, source: b.source_label || 'App SportGo' };
        }
        counts[b.customer_name].count += 1;
        counts[b.customer_name].revenue += Number(b.total_price || 0);
      });
      return Object.values(counts)
        .sort((a, b) => b.count - a.count || b.revenue - a.revenue)
        .slice(0, 5);
    },
    trendBars() {
      const rows = this.stats.revenue_trend || [];
      if (rows.length <= 16) return rows.map((row) => ({ ...row, date_from: row.date, date_to: row.date }));
      const size = Math.ceil(rows.length / 16);
      const grouped = [];
      for (let i = 0; i < rows.length; i += size) {
        const chunk = rows.slice(i, i + size);
        grouped.push({ date_from: chunk[0].date, date_to: chunk.at(-1).date, label: `${chunk[0].label}–${chunk.at(-1).label}`, bookings: chunk.reduce((sum, row) => sum + Number(row.bookings || 0), 0), revenue: chunk.reduce((sum, row) => sum + Number(row.revenue || 0), 0) });
      }
      return grouped;
    },
    maxTrendRevenue() {
      return Math.max(...this.trendBars.map((row) => Number(row.revenue || 0)), 0);
    },
    maxTrendBookings() {
      return Math.max(...this.trendBars.map((row) => Number(row.bookings || 0)), 0);
    },
    trendHasData() {
      return this.maxTrendRevenue > 0 || this.maxTrendBookings > 0;
    },
    trendAriaLabel() {
      return `Biểu đồ xu hướng ${this.stats.period?.label || ''}, tổng ${this.formatCurrency(this.stats.period_summary.revenue)}`;
    },
    hourlyList() {
      return this.stats.hourly_distribution && this.stats.hourly_distribution.length
        ? this.stats.hourly_distribution
        : [];
    },
    maxHourlyCount() {
      return Math.max(...this.hourlyList.map((i) => Number(i.count || 0)), 0);
    },
    hourlyHasData() {
      return this.maxHourlyCount > 0;
    },
    peakHourItem() {
      if (!this.hourlyList.length) return null;
      return this.hourlyList.slice().sort((a, b) => b.count - a.count)[0] || null;
    },
    peakHourLabel() {
      return this.peakHourItem && this.peakHourItem.count > 0 ? `Cao điểm: ${this.peakHourItem.slot}` : 'Chưa có cao điểm';
    },
    peakHourCount() {
      return this.peakHourItem ? this.peakHourItem.count : 0;
    },
    onlinePercent() {
      const total = Number(this.stats.period_summary?.bookings || 0);
      return total ? Math.round((Number(this.stats.period_summary?.online_bookings || 0) / total) * 100) : 0;
    },
    counterPercent() {
      const total = Number(this.stats.period_summary?.bookings || 0);
      return total ? Math.max(0, 100 - this.onlinePercent) : 0;
    },
    onlineRevenueValue() {
      return this.stats.channel_distribution?.online?.revenue ?? Math.round(Number(this.stats.period_summary.revenue || 0) * (this.onlinePercent / 100));
    },
    counterRevenueValue() {
      return this.stats.channel_distribution?.counter?.revenue ?? Math.round(Number(this.stats.period_summary.revenue || 0) * (this.counterPercent / 100));
    },
    maxCourtRevenue() {
      return Math.max(...(this.stats.court_revenues || []).map((row) => Number(row.revenue || 0)), 0);
    },
    maxGoldenHour() {
      return Math.max(...(this.stats.golden_hours || []).map((row) => Number(row.count || 0)), 0);
    },
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChange);
    this.loadStats();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChange);
  },
  methods: {
    choosePreset(key) {
      this.periodKey = key;
      this.loadStats();
    },
    openCalendarModal() {
      this.tempDateFrom = this.customDateFrom || this.today;
      this.tempDateTo = this.customDateTo || this.today;
      const d = new Date(this.tempDateFrom || this.today);
      this.calMonth = d.getMonth();
      this.calYear = d.getFullYear();
      this.isCalendarModalOpen = true;
    },
    closeCalendarModal() {
      this.isCalendarModalOpen = false;
    },
    prevMonth() {
      if (this.calMonth === 0) {
        this.calMonth = 11;
        this.calYear -= 1;
      } else {
        this.calMonth -= 1;
      }
    },
    nextMonth() {
      if (this.calMonth === 11) {
        this.calMonth = 0;
        this.calYear += 1;
      } else {
        this.calMonth += 1;
      }
    },
    onDayClick(day) {
      if (day.isEmpty || day.isFuture) return;

      if (!this.tempDateFrom || (this.tempDateFrom && this.tempDateTo)) {
        this.tempDateFrom = day.dateStr;
        this.tempDateTo = '';
      } else if (this.tempDateFrom && !this.tempDateTo) {
        if (day.dateStr < this.tempDateFrom) {
          this.tempDateTo = this.tempDateFrom;
          this.tempDateFrom = day.dateStr;
        } else {
          this.tempDateTo = day.dateStr;
        }
      }
    },
    applyCustomDateRange() {
      if (!this.tempDateFrom) return;
      this.customDateFrom = this.tempDateFrom;
      this.customDateTo = this.tempDateTo || this.tempDateFrom;
      this.periodKey = 'custom';
      this.isCalendarModalOpen = false;
      this.loadStats();
    },
    async handleClusterChange(event) {
      this.selectedCluster = event?.detail || null;
      await this.loadStats();
    },
    async resolveSelectedCluster(clusterId) {
      if (!clusterId || this.selectedCluster?.id) return;
      try {
        const response = await venueClusterService.getClusters({ compact: 1 });
        const clusters = response?.data || [];
        this.selectedCluster = clusters.find((cluster) => String(cluster.id) === String(clusterId)) || null;
      } catch {
        this.selectedCluster = null;
      }
    },
    async loadStats() {
      if (this.periodKey === 'custom' && (!this.customDateFrom || !this.customDateTo || this.customDateFrom > this.customDateTo)) return;
      this.isLoading = true;
      this.error = '';
      const clusterId = this.selectedCluster?.id || localStorage.getItem('selected_cluster');
      const clusterPromise = this.resolveSelectedCluster(clusterId);
      const params = new URLSearchParams({ period: this.periodKey });
      if (clusterId) params.set('venue_cluster_id', clusterId);
      if (this.periodKey === 'custom') {
        params.set('date_from', this.customDateFrom);
        params.set('date_to', this.customDateTo);
      }
      try {
        const [response] = await Promise.all([
          api(`/api/owner/dashboard?${params.toString()}`),
          clusterPromise,
        ]);
        const base = emptyStats();
        this.stats = {
          ...base,
          ...response,
          period_summary: { ...base.period_summary, ...(response.period_summary || {}) },
          operations: { ...base.operations, ...(response.operations || {}) },
          court_statuses: { ...base.court_statuses, ...(response.court_statuses || {}) },
          wallet: { ...base.wallet, ...(response.wallet || {}) },
          today_booking_summary: { ...base.today_booking_summary, ...(response.today_booking_summary || {}) },
          booking_statuses: response.booking_statuses || base.booking_statuses,
          revenue_trend: response.revenue_trend || base.revenue_trend,
          today_bookings: response.today_bookings || [],
          recent_bookings: response.recent_bookings || [],
          court_revenues: response.court_revenues || [],
          golden_hours: response.golden_hours || [],
          channel_distribution: response.channel_distribution || base.channel_distribution,
          hourly_distribution: response.hourly_distribution || base.hourly_distribution,
        };
      } catch (requestError) {
        this.error = requestError.message || 'Không thể tải dữ liệu bảng điều hành.';
      } finally {
        this.isLoading = false;
      }
    },
    statusTone(status) {
      if (['confirmed', 'completed'].includes(status)) return 'od-status--success';
      if (status === 'checked_in') return 'od-status--info';
      if (['cancelled', 'rejected', 'expired', 'no_show'].includes(status)) return 'od-status--danger';
      return 'od-status--warning';
    },
    statusPercent(count) {
      const total = Number(this.stats.period_summary?.bookings || 0);
      return total && count ? Math.max(4, Math.round((Number(count) / total) * 100)) : 0;
    },
    trendHeight(value) {
      return this.maxTrendRevenue && value ? Math.max(8, Math.round((Number(value) / this.maxTrendRevenue) * 100)) : 0;
    },
    trendBookingHeight(value) {
      return this.maxTrendBookings && value ? Math.max(8, Math.round((Number(value) / this.maxTrendBookings) * 100)) : 0;
    },
    hourlyHeight(value) {
      return this.maxHourlyCount && value ? Math.max(8, Math.round((Number(value) / this.maxHourlyCount) * 100)) : 0;
    },
    rankingWidth(value, max) {
      return max && value ? Math.max(8, Math.round((Number(value) / max) * 100)) : 0;
    },
    compactCurrency(value) {
      const amount = Number(value || 0);
      if (Math.abs(amount) >= 1000000000) return `${(amount / 1000000000).toFixed(1)} tỷ`;
      if (Math.abs(amount) >= 1000000) return `${(amount / 1000000).toFixed(1)}tr`;
      if (Math.abs(amount) >= 1000) return `${Math.round(amount / 1000)}k`;
      return amount.toLocaleString('vi-VN');
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    formatNumber(value) {
      return Number(value || 0).toLocaleString('vi-VN');
    },
    formatDate(value) {
      if (!value) return 'Chưa rõ ngày';
      return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`));
    },
    formatLongDate(value) {
      return new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`));
    },
    formatDateRange(from, to) {
      if (!from || !to) return 'Chưa chọn khoảng';
      return from === to ? this.formatDate(from) : `${this.formatDate(from)} – ${this.formatDate(to)}`;
    },
    localDateString(value = new Date()) {
      const year = value.getFullYear();
      const month = String(value.getMonth() + 1).padStart(2, '0');
      const day = String(value.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },
  },
};
</script>

<style scoped>
/* UNIFIED SPACING SYSTEM */
.owner-dashboard-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  color: #334155;
}

/* HERO HEADER WITH GRAPHIC BACKGROUND */
.od-hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: linear-gradient(135deg, #edf4f0 0%, #f4f8f6 60%, #e8f1ec 100%);
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035);
  overflow: hidden;
}

.od-hero-pattern {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 50%;
  pointer-events: none;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  border-radius: inherit;
}

.od-hero-pattern svg {
  height: 100%;
  width: auto;
}

.od-hero-left {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  flex: 1;
}

.od-greeting-pill {
  display: inline-flex;
  align-items: center;
  background: #ffffff;
  color: #5c7e6e;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 500;
  width: fit-content;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.03);
}

.od-hero-title {
  margin: 0;
  color: #1e293b;
  font-size: clamp(20px, 2vw, 24px);
  font-weight: 600;
  letter-spacing: -0.01em;
}

.od-hero-sub {
  margin: 0;
  color: #64748b;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* HORIZONTAL FILTER PILL GROUP */
.od-controls {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  flex-wrap: nowrap;
  justify-content: flex-end;
}

.od-filter-pill-group {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #ffffff;
  padding: 4px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.od-filter-pill {
  border: none;
  background: transparent;
  padding: 6px 12px;
  border-radius: 7px;
  color: #64748b;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.od-filter-pill:hover {
  background: #f8fafc;
  color: #1e293b;
}

.od-filter-pill.is-active {
  background: #edf4f0;
  color: #5c7e6e;
  font-weight: 600;
}

.od-filter-pill--custom {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

/* 1. SPOTLIGHT BANNER */
.od-spotlight-banner {
  background: #ffffff;
  border-radius: 16px;
  padding: 20px 24px;
  display: grid;
  grid-template-columns: 1fr 160px;
  gap: 20px;
  align-items: center;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035);
  position: relative;
  overflow: hidden;
}

.od-spotlight-content {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.od-spotlight-badge {
  display: inline-flex;
  align-items: center;
  background: #edf4f0;
  color: #5c7e6e;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.05em;
  width: fit-content;
}

.od-spotlight-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.25;
}

.od-spotlight-desc {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.5;
  color: #64748b;
}

.od-spotlight-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.od-spotlight-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.15s ease;
}

.od-btn-primary {
  background: #edf4f0;
  color: #5c7e6e;
}

.od-btn-primary:hover {
  background: #e2ede7;
}

.od-btn-ghost {
  background: #f8fafc;
  color: #475569;
}

.od-btn-ghost:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.od-spotlight-illustration {
  display: flex;
  align-items: center;
  justify-content: center;
}

.od-spotlight-img {
  max-width: 140px;
  max-height: 110px;
  object-fit: contain;
}

/* ALERT */
.od-alert {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  font-size: 12.5px;
}

.od-alert--error {
  background: #fef2f2;
  color: #b91c1c;
}

.od-link-button {
  margin-left: auto;
  border: none;
  background: none;
  color: inherit;
  font-size: 12px;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

/* COMMON SECTION & PANELS */
.od-section,
.od-panel,
.od-empty-state {
  border: none;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035);
  padding: 20px 24px;
}

.od-section-heading,
.od-panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.od-heading-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.od-section-title,
.od-panel-title {
  margin: 0;
  color: #1e293b;
  font-size: 15.5px;
  font-weight: 600;
}

.od-section-tag {
  background: #edf4f0;
  color: #5c7e6e;
  font-size: 10.5px;
  font-weight: 500;
  padding: 2px 6px;
  border-radius: 4px;
}

.od-panel-eyebrow {
  display: block;
  color: #94a3b8;
  font-size: 10.5px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 2px;
}

.od-updated,
.od-chart-total {
  color: #94a3b8;
  font-size: 11.5px;
  font-weight: 500;
}

/* 2. ATTENTION GRID */
.od-attention-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}

.od-attention-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 10px;
  background: #f8fafc;
  color: #334155;
  text-decoration: none;
  transition: all 0.15s ease;
}

.od-attention-item:hover {
  background: #f1f5f9;
}

.od-attention-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  color: #5c7e6e;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
  flex-shrink: 0;
}

.od-attention-copy {
  display: flex;
  flex-direction: column;
  gap: 1px;
  flex: 1;
}

.od-attention-value {
  color: #1e293b;
  font-size: 18px;
  font-weight: 600;
  line-height: 1.1;
}

.od-attention-label {
  color: #64748b;
  font-size: 11.5px;
  font-weight: 450;
}

.od-attention-arrow {
  color: #cbd5e1;
}

/* EMPTY STATE */
.od-empty-state {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
}

.od-empty-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: #edf4f0;
  color: #5c7e6e;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.od-empty-state h2 {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}

.od-empty-state p {
  margin: 0;
  color: #64748b;
  font-size: 13px;
}

.od-primary-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 36px;
  padding: 0 16px;
  border: none;
  border-radius: 999px;
  background: #edf4f0;
  color: #5c7e6e;
  font-size: 12px;
  font-weight: 500;
  text-decoration: none;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.od-primary-button:hover {
  background: #e2ede7;
}

/* 3. KPI SUMMARY CARDS */
.od-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}

.od-kpi-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035);
  display: flex;
  flex-direction: column;
}

.od-kpi-topline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.od-kpi-label {
  color: #64748b;
  font-size: 12px;
  font-weight: 500;
}

.od-kpi-icon-bubble,
.od-panel-icon-bubble {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  background: #edf4f0;
  color: #5c7e6e;
  display: flex;
  align-items: center;
  justify-content: center;
}

.od-kpi-val {
  color: #1e293b;
  font-size: 19px;
  font-weight: 600;
  line-height: 1.2;
  margin-bottom: 4px;
}

.od-kpi-note {
  color: #94a3b8;
  font-size: 11.5px;
}

.text-sage {
  color: #5c7e6e;
}

/* GAUGE CARD */
.od-kpi-card--gauge {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
}

.od-gauge-left {
  display: flex;
  flex-direction: column;
}

.od-gauge-right {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}

.od-donut-gauge {
  width: 100%;
  height: 100%;
}

.od-gauge-bg {
  fill: none;
  stroke: #f1f5f9;
  stroke-width: 4;
}

.od-gauge-fill {
  fill: none;
  stroke: #5c7e6e;
  stroke-width: 4;
  stroke-linecap: round;
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
  transition: stroke-dasharray 0.5s ease;
}

/* 4. MAIN GRID */
.od-main-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.8fr);
  gap: 16px;
  align-items: start;
}

.od-text-link {
  color: #5c7e6e;
  font-size: 12px;
  font-weight: 500;
  text-decoration: none;
  white-space: nowrap;
}

.od-text-link:hover {
  text-decoration: underline;
}

.od-today-summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.od-today-stat-pill {
  background: #f8fafc;
  padding: 10px 14px;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pill-lbl {
  font-size: 10.5px;
  color: #64748b;
  font-weight: 500;
}

.pill-val {
  font-size: 14.5px;
  font-weight: 600;
  color: #1e293b;
}

.od-loading-state,
.od-chart-empty,
.od-table-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 110px;
  color: #94a3b8;
  font-size: 12px;
}

.od-booking-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.od-booking-row {
  display: grid;
  grid-template-columns: 85px minmax(0, 1fr) auto 16px;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #f8fafc;
  border-radius: 10px;
  color: #334155;
  text-decoration: none;
  transition: all 0.15s ease;
}

.od-booking-row:hover {
  background: #f1f5f9;
}

.od-booking-time-badge {
  color: #1e293b;
  font-size: 12px;
  font-weight: 500;
}

.od-booking-main {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.od-booking-court {
  color: #1e293b;
  font-size: 12.5px;
  font-weight: 550;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.od-booking-cust {
  color: #64748b;
  font-size: 11.5px;
}

.od-booking-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.od-status {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 500;
  white-space: nowrap;
}

.od-status--success {
  background: #edf4f0;
  color: #5c7e6e;
}
.od-status--info {
  background: #eff6ff;
  color: #2563eb;
}
.od-status--warning {
  background: #fffbeb;
  color: #b45309;
}
.od-status--danger {
  background: #fef2f2;
  color: #b91c1c;
}

.od-payment-tag {
  font-size: 10.5px;
  color: #94a3b8;
}

.od-row-arrow {
  color: #cbd5e1;
}

/* SIDE COLUMN: WALLET & COURT HEALTH */
.od-side-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.od-wallet-value {
  font-size: 22px;
  font-weight: 600;
  color: #5c7e6e;
  margin: 8px 0 10px;
  line-height: 1.2;
}

.od-wallet-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
  margin-bottom: 14px;
}

.od-wallet-detail-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #64748b;
}

.od-wallet-detail-row span:last-child {
  color: #334155;
  font-weight: 500;
}

.od-wallet-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 8px 14px;
  background: #edf4f0;
  color: #5c7e6e;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.15s ease;
}

.od-wallet-btn:hover {
  background: #e2ede7;
}

/* COURT HEALTH */
.od-health-total {
  display: flex;
  align-items: baseline;
  gap: 4px;
  margin: 2px 0 10px;
}

.od-health-num {
  font-size: 20px;
  font-weight: 600;
  color: #1e293b;
}

.od-health-unit {
  font-size: 11.5px;
  color: #64748b;
}

.od-health-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.health-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  background: #f8fafc;
  padding: 8px 12px;
  border-radius: 8px;
}

.health-lbl {
  color: #64748b;
  font-weight: 450;
}

.health-val {
  color: #1e293b;
  font-weight: 550;
}

/* 5. 3-COLUMN BENTO LEADERBOARD */
.od-bento-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.od-bento-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.od-bento-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035);
  display: flex;
  flex-direction: column;
}

.od-bento-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.od-bento-card-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.bento-badge-icon {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  color: #5c7e6e;
  background: #edf4f0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.od-bento-card-head h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.bento-more-link {
  font-size: 11.5px;
  color: #94a3b8;
  text-decoration: none;
}

.bento-more-link:hover {
  color: #5c7e6e;
}

.od-bento-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.od-bento-item {
  display: grid;
  grid-template-columns: 22px 1fr auto;
  align-items: center;
  gap: 10px;
  background: #f8fafc;
  padding: 8px 12px;
  border-radius: 8px;
}

.od-rank-num {
  font-size: 11.5px;
  font-weight: 600;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
}

.od-rank-num--first {
  color: #5c7e6e;
}

.od-bento-item-main {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.od-bento-item-name {
  font-size: 12px;
  font-weight: 500;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.od-bento-sub {
  font-size: 11px;
  color: #94a3b8;
}

.od-bento-progress-track {
  width: 100%;
  height: 4px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}

.od-bento-progress-bar {
  height: 100%;
  background: #8da89b;
  border-radius: inherit;
}

.od-bento-item-val {
  font-size: 11.5px;
  font-weight: 550;
  color: #334155;
  text-align: right;
  white-space: nowrap;
}

/* 6. ANALYTICS & CHARTS SUITE (4 CHARTS GRID) */
.od-analytics-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.od-chart-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.od-chart-panel {
  min-height: 270px;
  display: flex;
  flex-direction: column;
}

.od-chart-actions {
  display: flex;
  align-items: center;
}

.od-mini-pill-toggle {
  display: inline-flex;
  align-items: center;
  background: #f1f5f9;
  padding: 2px;
  border-radius: 8px;
  gap: 2px;
}

.od-mini-toggle-btn {
  border: none;
  background: transparent;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.12s ease;
}

.od-mini-toggle-btn.is-active {
  background: #ffffff;
  color: #5c7e6e;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.od-chart-hero-stat {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin: 2px 0 12px;
}

.od-hero-stat-val {
  font-size: 20px;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.2;
}

.od-hero-stat-sub {
  font-size: 11.5px;
  color: #94a3b8;
}

.od-bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 6px;
  height: 140px;
  margin-top: auto;
  padding-bottom: 4px;
  border-bottom: 1px solid #f1f5f9;
}

.od-bar-chart--dense {
  gap: 2px;
}

.od-bar-column {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  height: 100%;
  gap: 4px;
  min-width: 0;
}

.od-bar-value {
  font-size: 9px;
  color: #94a3b8;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.od-bar-track {
  width: 100%;
  max-width: 24px;
  height: 95px;
  background: #f1f5f9;
  border-radius: 4px 4px 0 0;
  display: flex;
  align-items: flex-end;
}

.od-bar-fill {
  width: 100%;
  background: #8da89b;
  border-radius: 4px 4px 0 0;
  min-height: 3px;
  transition: height 0.3s ease;
}

.od-bar-fill--hourly {
  background: #8da89b;
}

.od-bar-column.is-peak .od-bar-fill--hourly {
  background: #5c7e6e;
}

.od-bar-column.is-peak .od-bar-value {
  color: #5c7e6e;
  font-weight: 600;
}

.od-bar-lbl {
  font-size: 9px;
  color: #94a3b8;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* CHANNEL DONUT CHART */
.od-channel-layout {
  display: grid;
  grid-template-columns: 100px 1fr;
  gap: 20px;
  align-items: center;
  margin-top: auto;
  padding: 6px 0;
}

.od-channel-donut-wrap {
  position: relative;
  width: 100px;
  height: 100px;
  flex-shrink: 0;
}

.od-channel-donut {
  width: 100%;
  height: 100%;
}

.od-donut-base {
  fill: none;
  stroke: #f1f5f9;
  stroke-width: 4.5;
}

.od-donut-segment {
  fill: none;
  stroke-width: 4.5;
  stroke-linecap: butt;
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
  transition: stroke-dasharray 0.5s ease;
}

.od-donut-online {
  stroke: #5c7e6e;
}

.od-donut-counter {
  stroke: #94a3b8;
}

.od-donut-center-text {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  pointer-events: none;
}

.donut-center-num {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.1;
}

.donut-center-lbl {
  font-size: 10px;
  color: #94a3b8;
}

.od-channel-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.od-channel-card {
  background: #f8fafc;
  border-radius: 8px;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.od-ch-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11.5px;
}

.od-ch-bullet {
  width: 7px;
  height: 7px;
  border-radius: 99px;
  display: inline-block;
  margin-right: 6px;
  flex-shrink: 0;
}

.od-ch-bullet--online { background: #5c7e6e; }
.od-ch-bullet--counter { background: #94a3b8; }

.od-ch-title {
  font-weight: 550;
  color: #1e293b;
  flex: 1;
}

.od-ch-pct {
  font-weight: 600;
  color: #5c7e6e;
  font-size: 11.5px;
}

.od-ch-sub {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #64748b;
}

.od-ch-rev {
  font-weight: 500;
  color: #334155;
}

/* STATUS CHART */
.od-status-chart {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: auto;
  padding-bottom: 4px;
}

.od-status-row {
  display: grid;
  grid-template-columns: 130px 1fr 30px;
  align-items: center;
  gap: 10px;
  font-size: 11.5px;
}

.od-status-label-group {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding-right: 6px;
}

.status-lbl {
  color: #64748b;
  font-weight: 450;
}

.status-pct {
  color: #94a3b8;
  font-size: 10.5px;
  font-weight: 500;
}

.status-cnt {
  text-align: right;
  color: #1e293b;
  font-weight: 550;
}

.od-status-track {
  height: 6px;
  background: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
  display: block;
}

.od-status-fill {
  height: 100%;
  border-radius: inherit;
  display: block;
  background: #cbd5e1;
}

.od-status-fill--pending { background: #f59e0b; }
.od-status-fill--confirmed { background: #93c5fd; }
.od-status-fill--playing { background: #86efac; }
.od-status-fill--completed { background: #5c7e6e; }
.od-status-fill--cancelled { background: #fca5a5; }

/* 7. RECENT ACTIVITY GRID */
.od-recent-grid {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 10px;
}

.od-recent-row {
  display: grid;
  grid-template-columns: 100px minmax(120px, 1fr) minmax(120px, 1fr) 160px 105px 105px;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #f8fafc;
  border-radius: 8px;
  color: #64748b;
  font-size: 11.5px;
  text-decoration: none;
  transition: all 0.15s ease;
}

.od-recent-row:hover {
  background: #f1f5f9;
}

.od-recent-code {
  color: #1e293b;
  font-weight: 550;
}

.od-recent-cust {
  color: #334155;
  font-weight: 500;
}

.od-recent-amount {
  text-align: right;
  color: #5c7e6e;
  font-weight: 600;
}

/* CENTERED CALENDAR MODAL */
.od-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: odModalFade 0.2s ease-out;
}

@keyframes odModalFade {
  from { opacity: 0; }
  to { opacity: 1; }
}

.od-modal-dialog {
  background: #ffffff;
  border-radius: 18px;
  padding: 24px;
  width: 100%;
  max-width: 360px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
  display: flex;
  flex-direction: column;
  gap: 16px;
  animation: odModalZoom 0.2s ease-out;
}

@keyframes odModalZoom {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.od-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.od-modal-title-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.od-modal-icon {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: #edf4f0;
  color: #5c7e6e;
  display: flex;
  align-items: center;
  justify-content: center;
}

.od-modal-title {
  margin: 0;
  font-size: 14.5px;
  font-weight: 600;
  color: #1e293b;
}

.od-modal-subtitle {
  font-size: 11.5px;
  color: #94a3b8;
}

.od-modal-close {
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 16px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.od-modal-close:hover {
  background: #f1f5f9;
  color: #1e293b;
}

/* MONTH NAVIGATION */
.od-cal-month-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 4px;
}

.od-cal-month-title {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.od-cal-nav-btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border: none;
  border-radius: 8px;
  color: #64748b;
  cursor: pointer;
  transition: all 0.12s ease;
}

.od-cal-nav-btn:hover {
  background: #edf4f0;
  color: #5c7e6e;
}

/* WEEKDAYS */
.od-cal-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  font-size: 11.5px;
  font-weight: 600;
  color: #94a3b8;
  padding: 2px 0;
}

/* DAYS GRID */
.od-cal-days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 3px;
  row-gap: 4px;
}

.od-cal-day-cell {
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12.5px;
  color: #334155;
  border-radius: 8px;
  cursor: pointer;
  position: relative;
  transition: all 0.1s ease;
}

.od-cal-day-cell:hover:not(.is-empty):not(.is-future) {
  background: #f1f5f9;
}

.od-cal-day-cell.is-empty {
  cursor: default;
}

.od-cal-day-cell.is-today:not(.is-selected) {
  font-weight: 600;
  color: #5c7e6e;
  border: 1px solid #5c7e6e;
}

.od-cal-day-cell.is-selected {
  background: #5c7e6e !important;
  color: #ffffff !important;
  font-weight: 600;
  border-radius: 8px;
}

.od-cal-day-cell.is-in-range {
  background: #edf4f0;
  color: #5c7e6e;
  border-radius: 0;
}

.od-cal-day-cell.is-range-start {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}

.od-cal-day-cell.is-range-end {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}

.od-cal-day-cell.is-future {
  color: #cbd5e1;
  cursor: not-allowed;
}

/* MODAL FOOTER */
.od-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
}

.od-modal-range-display {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.od-modal-range-display .lbl {
  font-size: 10.5px;
  color: #94a3b8;
}

.od-modal-range-display .val {
  font-size: 12px;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.od-modal-btn-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.od-btn-modal-cancel {
  padding: 8px 14px;
  background: #f8fafc;
  color: #64748b;
  border: none;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.12s ease;
}

.od-btn-modal-cancel:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.od-btn-modal-apply {
  padding: 8px 16px;
  background: #5c7e6e;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.12s ease;
}

.od-btn-modal-apply:hover:not(:disabled) {
  background: #4a6759;
}

.od-btn-modal-apply:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* RESPONSIVE */
@media (max-width: 1120px) {
  .od-spotlight-banner { grid-template-columns: 1fr; }
  .od-spotlight-illustration { display: none; }
  .od-bento-grid { grid-template-columns: 1fr; }
  .od-attention-grid { grid-template-columns: repeat(2, 1fr); }
  .od-main-grid { grid-template-columns: 1fr; }
  .od-side-column { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .od-recent-row { grid-template-columns: 100px minmax(120px, 1fr) 105px 105px; }
  .od-recent-row > span:nth-child(3),
  .od-recent-row > span:nth-child(4) { display: none; }
}

@media (max-width: 760px) {
  .od-hero { align-items: flex-start; flex-direction: column; }
  .od-controls { justify-content: flex-start; width: 100%; }
  .od-filter-pill-group { flex-wrap: wrap; width: 100%; }
  .od-kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .od-chart-grid { grid-template-columns: 1fr; }
  .od-channel-layout { grid-template-columns: 1fr; justify-items: center; text-align: center; }
  .od-recent-row { grid-template-columns: 90px minmax(0, 1fr) 95px; }
  .od-recent-row > span, .od-recent-row > .od-status { display: none; }
}

@media (max-width: 520px) {
  .od-section, .od-panel, .od-empty-state, .od-spotlight-banner, .od-hero { padding: 14px 16px; border-radius: 12px; }
  .od-attention-grid, .od-kpi-grid, .od-side-column { grid-template-columns: 1fr; }
  .od-today-summary { grid-template-columns: repeat(2, 1fr); }
  .od-booking-row { grid-template-columns: 70px minmax(0, 1fr) 16px; }
  .od-booking-meta { display: none; }
}
</style>
