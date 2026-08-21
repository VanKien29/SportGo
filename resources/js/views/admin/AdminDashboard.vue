<template>
  <section class="admin-dashboard-page">
    <!-- Error Alert -->
    <div v-if="error" class="od-alert od-alert--error" role="alert">
      <AppIcon name="alertCircle" size="16" />
      <span>{{ error }}</span>
      <button type="button" class="od-link-button" @click="loadDashboard">Thử lại</button>
    </div>

    <!-- Header Hero Bar -->
    <header class="od-hero">
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
          <span>{{ greeting }}, Quản trị viên</span>
        </div>
        <h1 class="od-hero-title">Trung tâm điều hành sàn SportGo</h1>
        <p class="od-hero-sub">Đối soát tài chính · Quản lý mạng lưới · {{ periodLabel }}</p>
      </div>

      <div class="od-controls">
        <div class="od-filter-pill-group">
          <button
            v-for="preset in periodPresets"
            :key="preset.key"
            type="button"
            class="od-filter-pill"
            :class="{ 'is-active': financePeriod === preset.key }"
            @click="setPeriod(preset.key)"
          >
            {{ preset.label }}
          </button>
        </div>
      </div>
    </header>

    <!-- 1. SPOTLIGHT BANNER -->
    <div class="od-spotlight-banner">
      <div class="od-spotlight-content">
        <div class="od-spotlight-badge">
          <span>TỔNG QUAN VẬN HÀNH SÀN</span>
        </div>
        <h2 class="od-spotlight-title">Điều phối hệ thống {{ periodLabel }}</h2>
        <p class="od-spotlight-desc">
          Hệ thống đang có <strong>{{ actionCount }}</strong> tác vụ nghiệp vụ cần xử lý và <strong>{{ unreadCount }}</strong> thông báo vận hành mới.
        </p>
        <div class="od-spotlight-actions">
          <router-link to="/admin/partner-applications" class="od-spotlight-btn od-btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            <span>Duyệt hồ sơ đối tác</span>
          </router-link>
          <router-link to="/admin/finance-operations" class="od-spotlight-btn od-btn-ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="5" width="20" height="14" rx="3"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <span>Vận hành tài chính</span>
          </router-link>
          <router-link to="/admin/venue-clusters" class="od-spotlight-btn od-btn-ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>Quản lý cụm sân</span>
          </router-link>
        </div>
      </div>
      <div class="od-spotlight-illustration">
        <img src="/images/admin_command_center.png" alt="Admin Dashboard" class="od-spotlight-img" />
      </div>
    </div>

    <!-- 2. PRIORITY ACTION ITEMS -->
    <section class="od-section od-attention-section" aria-labelledby="od-attention-title">
      <div class="od-section-heading">
        <div class="od-heading-group">
          <h2 id="od-attention-title" class="od-section-title">Cần xử lý ngay</h2>
          <span class="od-section-tag">Hành động nhanh</span>
        </div>
        <span class="od-updated">{{ isLoading ? 'Đang đồng bộ...' : `${actionCount} tác vụ đang chờ` }}</span>
      </div>

      <div class="od-attention-grid">
        <router-link to="/admin/partner-applications" class="od-attention-item">
          <div class="od-attention-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
          </div>
          <div class="od-attention-copy">
            <span class="od-attention-value">{{ pendingCounts.partner_applications || 0 }}</span>
            <span class="od-attention-label">Hồ sơ đối tác mới</span>
          </div>
          <AppIcon name="chevronRight" size="14" class="od-attention-arrow" />
        </router-link>

        <router-link to="/admin/venue-clusters" class="od-attention-item">
          <div class="od-attention-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
          <div class="od-attention-copy">
            <span class="od-attention-value">{{ pendingCounts.venue_clusters || 0 }}</span>
            <span class="od-attention-label">Yêu cầu cụm sân</span>
          </div>
          <AppIcon name="chevronRight" size="14" class="od-attention-arrow" />
        </router-link>

        <router-link to="/admin/finance-operations" class="od-attention-item">
          <div class="od-attention-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="5" width="20" height="14" rx="3"/>
              <circle cx="12" cy="12" r="3"/>
              <path d="M6 9h.01M18 15h.01"/>
            </svg>
          </div>
          <div class="od-attention-copy">
            <span class="od-attention-value">{{ pendingCounts.finance || 0 }}</span>
            <span class="od-attention-label">Tài chính & Hoàn tiền</span>
          </div>
          <AppIcon name="chevronRight" size="14" class="od-attention-arrow" />
        </router-link>

        <router-link to="/admin/reports-complaints" class="od-attention-item">
          <div class="od-attention-icon-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              <circle cx="9" cy="10" r="1" fill="currentColor"/>
              <circle cx="12" cy="10" r="1" fill="currentColor"/>
              <circle cx="15" cy="10" r="1" fill="currentColor"/>
            </svg>
          </div>
          <div class="od-attention-copy">
            <span class="od-attention-value">{{ pendingCounts.moderation_support || 0 }}</span>
            <span class="od-attention-label">Báo cáo & Khiếu nại</span>
          </div>
          <AppIcon name="chevronRight" size="14" class="od-attention-arrow" />
        </router-link>
      </div>
    </section>

    <!-- 3. KPI SUMMARY METRICS & GAUGE -->
    <section class="od-kpi-grid" aria-label="Chỉ số tài chính hệ thống">
      <!-- KPI 1: TIỀN MẶT HỆ THỐNG -->
      <article class="od-kpi-card">
        <div class="od-kpi-topline">
          <span class="od-kpi-label">Tiền mặt hệ thống khả dụng</span>
          <div class="od-kpi-icon-bubble">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="6" width="20" height="12" rx="2"/>
              <circle cx="12" cy="12" r="2"/>
              <path d="M6 12h.01M18 12h.01"/>
            </svg>
          </div>
        </div>
        <div class="od-kpi-val" :class="Number(overview.system_cash_balance || 0) >= 0 ? 'text-sage' : 'text-danger'">
          {{ isLoading ? '...' : formatCurrency(overview.system_cash_balance) }}
        </div>
        <small class="od-kpi-note">Quỹ tiền mặt sau khi trừ toàn bộ công nợ</small>
      </article>

      <!-- KPI 2: DOANH THU HỆ THỐNG -->
      <article class="od-kpi-card">
        <div class="od-kpi-topline">
          <span class="od-kpi-label">Doanh thu nền tảng kỳ này</span>
          <div class="od-kpi-icon-bubble">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
              <polyline points="17 6 23 6 23 12"/>
            </svg>
          </div>
        </div>
        <div class="od-kpi-val text-sage">{{ isLoading ? '...' : formatCurrency(overview.system_revenue) }}</div>
        <small class="od-kpi-note">Phí sàn booking & Gói hội viên VIP</small>
      </article>

      <!-- KPI 3: TỶ LỆ THANH KHOẢN (GAUGE) -->
      <article class="od-kpi-card od-kpi-card--gauge">
        <div class="od-gauge-left">
          <span class="od-kpi-label">Tỷ lệ thanh khoản</span>
          <div class="od-kpi-val">{{ liquidityRate }}%</div>
          <small class="od-kpi-note">{{ formatCurrency(overview.system_cash_balance) }} / {{ formatCurrency(overview.managed_total) }}</small>
        </div>
        <div class="od-gauge-right">
          <svg class="od-donut-gauge" viewBox="0 0 36 36">
            <path class="od-gauge-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            <path class="od-gauge-fill" :stroke-dasharray="`${liquidityRate}, 100`" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
          </svg>
        </div>
      </article>

      <!-- KPI 4: TỔNG CHI RA TRONG KỲ -->
      <article class="od-kpi-card">
        <div class="od-kpi-topline">
          <span class="od-kpi-label">Tổng chi ra trong kỳ</span>
          <div class="od-kpi-icon-bubble">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4v16a1 1 0 0 0 1.5.86L8 19l2.5 1.86a1 1 0 0 0 1 0L14 19l2.5 1.86a1 1 0 0 0 1.5-.86V4"/>
              <path d="M8 8h6m-6 4h4"/>
            </svg>
          </div>
        </div>
        <div class="od-kpi-val">{{ isLoading ? '...' : formatCurrency(overview.cash_out_total) }}</div>
        <small class="od-kpi-note">{{ formatCurrency(overview.withdrawal_total) }} rút ví · {{ formatCurrency(overview.voucher_cost_total) }} voucher bù</small>
      </article>
    </section>

    <!-- 4. MAIN GRID: TASK FEED & FINANCIAL SIDEBAR -->
    <div class="od-main-grid">
      <!-- Task Feed Panel -->
      <section class="od-panel od-schedule-panel" aria-labelledby="od-task-title">
        <div class="od-panel-heading">
          <div>
            <span class="od-panel-eyebrow">DÒNG CÔNG VIỆC THỰC THỜI</span>
            <h2 id="od-task-title" class="od-panel-title">Trung tâm điều phối tác vụ</h2>
          </div>
          <router-link to="/admin/partner-applications" class="od-text-link">Tất cả tác vụ</router-link>
        </div>

        <div class="od-today-summary">
          <div class="od-today-stat-pill">
            <span class="pill-lbl">Khẩn cấp</span>
            <span class="pill-val text-danger">{{ criticalCount }}</span>
          </div>
          <div class="od-today-stat-pill">
            <span class="pill-lbl">Ưu tiên cao</span>
            <span class="pill-val text-amber">{{ highCount }}</span>
          </div>
          <div class="od-today-stat-pill">
            <span class="pill-lbl">Hồ sơ chờ duyệt</span>
            <span class="pill-val">{{ pendingCounts.partner_applications || 0 }}</span>
          </div>
          <div class="od-today-stat-pill">
            <span class="pill-lbl">Hoàn tiền / Rút tiền</span>
            <span class="pill-val text-sage">{{ financeCount }}</span>
          </div>
        </div>

        <div v-if="isLoading && !tasks.length" class="od-loading-state">Đang đồng bộ dòng công việc...</div>
        <div v-else-if="!tasks.length" class="od-table-empty">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          <span>Hệ thống vận hành trơn tru. Không có tác vụ tồn đọng.</span>
        </div>
        <div v-else class="od-booking-list">
          <button
            v-for="task in visibleTasks"
            :key="task.id"
            type="button"
            class="od-booking-row od-task-row"
            :class="`priority-${task.priority}`"
            @click="openTask(task)"
          >
            <div class="od-booking-time-badge">
              <span class="od-status" :class="priorityTone(task.priority)">{{ priorityLabel(task.priority) }}</span>
            </div>
            <div class="od-booking-main">
              <span class="od-booking-court">{{ task.title }}</span>
              <span class="od-booking-cust">{{ task.description }}</span>
            </div>
            <div class="od-booking-meta">
              <time class="od-task-time">{{ formatRelative(task.created_at) }}</time>
            </div>
            <AppIcon name="chevronRight" size="14" class="od-row-arrow" />
          </button>
        </div>
      </section>

      <!-- SIDEBAR: FINANCIAL OVERVIEW & NETWORK HEALTH -->
      <aside class="od-side-column">
        <!-- Wallet / Financial Balance Panel -->
        <section class="od-panel od-wallet-panel" aria-labelledby="od-wallet-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-panel-eyebrow">Tài chính nền tảng</span>
              <h2 id="od-wallet-title" class="od-panel-title">Tổng tiền đang quản lý</h2>
            </div>
            <div class="od-panel-icon-bubble">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/>
                <path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/>
                <circle cx="16" cy="14" r="1.5" fill="currentColor"/>
              </svg>
            </div>
          </div>

          <div class="od-wallet-value">{{ formatCurrency(overview.managed_total) }}</div>

          <div class="od-wallet-details">
            <div class="od-wallet-detail-row">
              <span>Công nợ ví chủ sân</span>
              <span>{{ formatCurrency(overview.owner_debt_total) }}</span>
            </div>
            <div class="od-wallet-detail-row">
              <span>Số dư ví khách hàng</span>
              <span>{{ formatCurrency(overview.customer_debt_total) }}</span>
            </div>
            <div class="od-wallet-detail-row">
              <span>Tiền mặt hệ thống thặng dư</span>
              <span>{{ formatCurrency(overview.system_cash_balance) }}</span>
            </div>
          </div>

          <router-link to="/admin/finance-operations" class="od-wallet-btn">
            <span>Quản lý tài chính & Giải ngân</span>
            <AppIcon name="chevronRight" size="14" />
          </router-link>
        </section>

        <!-- Network Health Panel -->
        <section class="od-panel od-health-panel" aria-labelledby="od-network-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-panel-eyebrow">Mạng lưới sân</span>
              <h2 id="od-network-title" class="od-panel-title">Hạ tầng Đối tác & Cụm sân</h2>
            </div>
            <router-link to="/admin/venue-clusters" class="od-text-link">Quản lý</router-link>
          </div>
          <div class="od-health-total">
            <span class="od-health-num">{{ pendingCounts.venue_clusters || 0 }}</span>
            <span class="od-health-unit">yêu cầu cụm sân chờ xử lý</span>
          </div>
          <div class="od-health-list">
            <div class="health-row">
              <span class="health-lbl">Hồ sơ đối tác chờ duyệt</span>
              <span class="health-val">{{ pendingCounts.partner_applications || 0 }}</span>
            </div>
            <div class="health-row">
              <span class="health-lbl">Đổi quy mô / vị trí sân</span>
              <span class="health-val">{{ (pendingCounts.detail?.scale_approvals || 0) + (pendingCounts.detail?.location_changes || 0) }}</span>
            </div>
            <div class="health-row">
              <span class="health-lbl">Hoàn tiền / Rút tiền đang chờ</span>
              <span class="health-val">{{ financeCount }}</span>
            </div>
          </div>
        </section>
      </aside>
    </div>

    <!-- 5. 3-COLUMN BENTO LEADERBOARD -->
    <section class="od-bento-section">
      <div class="od-section-heading">
        <div>
          <span class="od-panel-eyebrow">ĐỐI SOÁT NHANH KỲ NÀY</span>
          <h2 class="od-section-title">Tổng hợp tài chính {{ periodLabel }}</h2>
        </div>
        <span class="od-updated">{{ periodLabel }}</span>
      </div>

      <div class="od-bento-grid">
        <!-- BENTO COL 1: BOOKING THU HỘ TOP CỤM SÂN -->
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
              <h3>BXH Cụm Sân Booking</h3>
            </div>
            <router-link to="/admin/venue-clusters" class="bento-more-link">Tất cả ›</router-link>
          </div>
          <div v-if="topVenues.length" class="od-bento-list">
            <div
              v-for="(venue, index) in topVenues"
              :key="venue.name"
              class="od-bento-item"
            >
              <span class="od-rank-num" :class="{ 'od-rank-num--first': index === 0 }">{{ index + 1 }}</span>
              <div class="od-bento-item-main">
                <span class="od-bento-item-name">{{ venue.name }}</span>
                <div class="od-bento-progress-track">
                  <div class="od-bento-progress-bar" :style="{ width: `${rankingWidth(venue.revenue, maxVenueRevenue)}%` }"></div>
                </div>
              </div>
              <span class="od-bento-item-val text-sage">{{ compactCurrency(venue.revenue) }}</span>
            </div>
          </div>
          <div v-else class="od-chart-empty">Chưa có dữ liệu booking cụm sân.</div>
        </div>

        <!-- BENTO COL 2: CƠ CẤU DOANH THU -->
        <div class="od-bento-card">
          <div class="od-bento-card-head">
            <div class="od-bento-card-title-group">
              <div class="bento-badge-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                  <polyline points="2 17 12 22 22 17"/>
                  <polyline points="2 12 12 17 22 12"/>
                </svg>
              </div>
              <h3>Cơ Cấu Nguồn Thu Sàn</h3>
            </div>
            <router-link to="/admin/platform-fee-ledgers" class="bento-more-link">Sổ phí ›</router-link>
          </div>
          <div class="od-bento-list">
            <div class="od-bento-item">
              <span class="od-rank-num od-rank-num--first">1</span>
              <div class="od-bento-item-main">
                <span class="od-bento-item-name">Phí nền tảng Booking</span>
                <span class="od-bento-sub">Tỷ lệ phí dịch vụ trên mỗi đơn</span>
              </div>
              <span class="od-bento-item-val text-sage">{{ formatCurrency(overview.platform_fee_revenue_total) }}</span>
            </div>
            <div class="od-bento-item">
              <span class="od-rank-num">2</span>
              <div class="od-bento-item-main">
                <span class="od-bento-item-name">Gói Hội Viên VIP SportGo</span>
                <span class="od-bento-sub">Nâng cấp tài khoản người chơi</span>
              </div>
              <span class="od-bento-item-val text-sage">{{ formatCurrency(overview.membership_revenue_total) }}</span>
            </div>
            <div class="od-bento-item">
              <span class="od-rank-num">3</span>
              <div class="od-bento-item-main">
                <span class="od-bento-item-name">Booking Thu Hộ Toàn Sàn</span>
                <span class="od-bento-sub">Dòng tiền qua cổng thanh toán</span>
              </div>
              <span class="od-bento-item-val">{{ compactCurrency(overview.booking_collected_total) }}</span>
            </div>
            <div class="od-bento-item">
              <span class="od-rank-num">4</span>
              <div class="od-bento-item-main">
                <span class="od-bento-item-name">Chi rút ví đối tác & người chơi</span>
                <span class="od-bento-sub">Giải ngân cho chủ sân & khách</span>
              </div>
              <span class="od-bento-item-val">{{ compactCurrency(overview.withdrawal_total) }}</span>
            </div>
            <div class="od-bento-item">
              <span class="od-rank-num">5</span>
              <div class="od-bento-item-main">
                <span class="od-bento-item-name">Voucher Hệ Thống Bù</span>
                <span class="od-bento-sub">Khoản bù chương trình khuyến mãi</span>
              </div>
              <span class="od-bento-item-val">{{ compactCurrency(overview.voucher_cost_total) }}</span>
            </div>
          </div>
        </div>

        <!-- BENTO COL 3: TOP KHÁCH HÀNG -->
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
            <router-link to="/admin/users" class="bento-more-link">Thành viên ›</router-link>
          </div>
          <div v-if="topCustomers.length" class="od-bento-list">
            <div
              v-for="(cust, index) in topCustomers"
              :key="cust.name"
              class="od-bento-item"
            >
              <span class="od-rank-num" :class="{ 'od-rank-num--first': index === 0 }">{{ index + 1 }}</span>
              <div class="od-bento-item-main">
                <span class="od-bento-item-name">{{ cust.name }}</span>
                <span class="od-bento-sub">{{ cust.count }} lượt đặt sân</span>
              </div>
              <span class="od-bento-item-val text-sage">{{ compactCurrency(cust.revenue) }}</span>
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
          <span class="od-panel-eyebrow">PHÂN TÍCH KẾ TOÁN & DÒNG TIỀN</span>
          <h2 class="od-section-title">Trung Tâm Dữ Liệu Tài Chính Sàn</h2>
        </div>
        <span class="od-updated">{{ periodLabel }}</span>
      </div>

      <div class="od-chart-grid">
        <!-- CHART 1: XU HƯỚNG DÒNG TIỀN (CSS BAR CHART) -->
        <article class="od-panel od-chart-panel" aria-labelledby="od-cashflow-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-panel-eyebrow">Biến động dòng tiền</span>
              <h3 id="od-cashflow-title" class="od-panel-title">
                {{ activeCashFlowMetric === 'money_in' ? 'Tiền vào theo kỳ' : 'Tiền ra theo kỳ' }}
              </h3>
            </div>
            <div class="od-chart-actions">
              <div class="od-mini-pill-toggle">
                <button
                  type="button"
                  class="od-mini-toggle-btn"
                  :class="{ 'is-active': activeCashFlowMetric === 'money_in' }"
                  @click="activeCashFlowMetric = 'money_in'"
                >
                  Tiền vào
                </button>
                <button
                  type="button"
                  class="od-mini-toggle-btn"
                  :class="{ 'is-active': activeCashFlowMetric === 'money_out' }"
                  @click="activeCashFlowMetric = 'money_out'"
                >
                  Tiền ra
                </button>
              </div>
            </div>
          </div>

          <div class="od-chart-hero-stat">
            <span class="od-hero-stat-val text-sage">
              {{ activeCashFlowMetric === 'money_in' ? formatCurrency(totalMoneyIn) : formatCurrency(totalMoneyOut) }}
            </span>
            <span class="od-hero-stat-sub">Tổng trong kỳ</span>
          </div>

          <div v-if="cashFlowBars.length" class="od-bar-chart" aria-label="Biểu đồ dòng tiền">
            <div v-for="bar in cashFlowBars" :key="bar.label" class="od-bar-column">
              <span class="od-bar-value">{{ compactCurrency(bar[activeCashFlowMetric]) }}</span>
              <span class="od-bar-track">
                <span
                  class="od-bar-fill"
                  :style="{ height: `${cashFlowBarHeight(bar[activeCashFlowMetric])}%` }"
                ></span>
              </span>
              <small class="od-bar-lbl">{{ bar.label }}</small>
            </div>
          </div>
          <div v-else class="od-chart-empty">Chưa có dữ liệu dòng tiền trong kỳ.</div>
        </article>

        <!-- CHART 2: CƠ CẤU SỐ DƯ QUẢN LÝ (SVG DONUT + LEGEND) -->
        <article class="od-panel od-chart-panel" aria-labelledby="od-composition-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-panel-eyebrow">Phân bổ dòng tiền</span>
              <h3 id="od-composition-title" class="od-panel-title">Cơ cấu số dư đang quản lý</h3>
            </div>
            <span class="od-chart-total">{{ formatCurrency(overview.managed_total) }}</span>
          </div>

          <div class="od-channel-layout">
            <div class="od-channel-donut-wrap">
              <svg class="od-channel-donut" viewBox="0 0 36 36">
                <path class="od-donut-base" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <path
                  class="od-donut-segment od-donut-online"
                  :stroke-dasharray="`${ownerDebtPercent}, 100`"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
                <path
                  class="od-donut-segment od-donut-counter"
                  :stroke-dasharray="`${customerDebtPercent}, 100`"
                  :stroke-dashoffset="`-${ownerDebtPercent}`"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
              </svg>
              <div class="od-donut-center-text">
                <span class="donut-center-num">{{ cashSurplusPercent }}%</span>
                <span class="donut-center-lbl">Thặng dư</span>
              </div>
            </div>

            <div class="od-channel-legend">
              <div class="od-channel-card">
                <div class="od-ch-top">
                  <span class="od-ch-bullet od-ch-bullet--online"></span>
                  <span class="od-ch-title">Công nợ ví chủ sân</span>
                  <span class="od-ch-pct">{{ ownerDebtPercent }}%</span>
                </div>
                <div class="od-ch-sub">
                  <span>Khả dụng + đang giữ</span>
                  <span class="od-ch-rev">{{ formatCurrency(overview.owner_debt_total) }}</span>
                </div>
              </div>

              <div class="od-channel-card">
                <div class="od-ch-top">
                  <span class="od-ch-bullet od-ch-bullet--counter"></span>
                  <span class="od-ch-title">Số dư ví khách hàng</span>
                  <span class="od-ch-pct">{{ customerDebtPercent }}%</span>
                </div>
                <div class="od-ch-sub">
                  <span>Số dư & đang khóa</span>
                  <span class="od-ch-rev">{{ formatCurrency(overview.customer_debt_total) }}</span>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- CHART 3: CƠ CẤU NGUỒN THU (SVG DONUT + LEGEND) -->
        <article class="od-panel od-chart-panel" aria-labelledby="od-revenue-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-panel-eyebrow">Nguồn doanh thu</span>
              <h3 id="od-revenue-title" class="od-panel-title">Cơ cấu doanh thu nền tảng</h3>
            </div>
            <span class="od-chart-total">{{ formatCurrency(overview.system_revenue) }}</span>
          </div>

          <div class="od-channel-layout">
            <div class="od-channel-donut-wrap">
              <svg class="od-channel-donut" viewBox="0 0 36 36">
                <path class="od-donut-base" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                <path
                  class="od-donut-segment od-donut-online"
                  :stroke-dasharray="`${platformFeePercent}, 100`"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
                <path
                  class="od-donut-segment"
                  style="stroke: #6366f1;"
                  :stroke-dasharray="`${vipPercent}, 100`"
                  :stroke-dashoffset="`-${platformFeePercent}`"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
              </svg>
              <div class="od-donut-center-text">
                <span class="donut-center-num">{{ platformFeePercent }}%</span>
                <span class="donut-center-lbl">Phí sàn</span>
              </div>
            </div>

            <div class="od-channel-legend">
              <div class="od-channel-card">
                <div class="od-ch-top">
                  <span class="od-ch-bullet od-ch-bullet--online"></span>
                  <span class="od-ch-title">Phí nền tảng Booking</span>
                  <span class="od-ch-pct">{{ platformFeePercent }}%</span>
                </div>
                <div class="od-ch-sub">
                  <span>Trích % giao dịch</span>
                  <span class="od-ch-rev">{{ formatCurrency(overview.platform_fee_revenue_total) }}</span>
                </div>
              </div>

              <div class="od-channel-card">
                <div class="od-ch-top">
                  <span class="od-ch-bullet" style="background: #6366f1;"></span>
                  <span class="od-ch-title">Gói Hội viên VIP</span>
                  <span class="od-ch-pct">{{ vipPercent }}%</span>
                </div>
                <div class="od-ch-sub">
                  <span>Thành viên nâng cấp</span>
                  <span class="od-ch-rev">{{ formatCurrency(overview.membership_revenue_total) }}</span>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- CHART 4: CƠ CẤU CÔNG NỢ (STATUS PIPELINE BARS) -->
        <article class="od-panel od-chart-panel" aria-labelledby="od-debt-title">
          <div class="od-panel-heading">
            <div>
              <span class="od-panel-eyebrow">Nghĩa vụ chi trả</span>
              <h3 id="od-debt-title" class="od-panel-title">Cơ cấu công nợ & thặng dư</h3>
            </div>
            <span class="od-chart-total">{{ cashSurplusPercent }}% an toàn</span>
          </div>

          <div class="od-status-chart">
            <div class="od-status-row">
              <div class="od-status-label-group">
                <span class="status-lbl">Công nợ chủ sân</span>
                <span class="status-pct">{{ ownerDebtPercent }}%</span>
              </div>
              <span class="od-status-track">
                <span class="od-status-fill od-status-fill--confirmed" :style="{ width: `${ownerDebtPercent}%` }"></span>
              </span>
              <span class="status-cnt">{{ compactCurrency(overview.owner_debt_total) }}</span>
            </div>

            <div class="od-status-row">
              <div class="od-status-label-group">
                <span class="status-lbl">Số dư ví khách</span>
                <span class="status-pct">{{ customerDebtPercent }}%</span>
              </div>
              <span class="od-status-track">
                <span class="od-status-fill od-status-fill--pending" :style="{ width: `${customerDebtPercent}%` }"></span>
              </span>
              <span class="status-cnt">{{ compactCurrency(overview.customer_debt_total) }}</span>
            </div>

            <div class="od-status-row">
              <div class="od-status-label-group">
                <span class="status-lbl">Tiền mặt thặng dư</span>
                <span class="status-pct">{{ cashSurplusPercent }}%</span>
              </div>
              <span class="od-status-track">
                <span class="od-status-fill od-status-fill--completed" :style="{ width: `${cashSurplusPercent}%` }"></span>
              </span>
              <span class="status-cnt">{{ compactCurrency(overview.system_cash_balance) }}</span>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- 7. RECENT ACTIVITY PANEL: LEDGER TABLE -->
    <section class="od-panel od-recent-panel" aria-labelledby="od-ledger-title">
      <div class="od-panel-heading">
        <div>
          <span class="od-panel-eyebrow">SỔ ĐỐI SOÁT CHI TIẾT</span>
          <h2 id="od-ledger-title" class="od-panel-title">{{ activeTable.title }}</h2>
        </div>
        <div class="od-ledger-tabs" role="tablist">
          <button
            v-for="tab in tableTabs"
            :key="tab.key"
            type="button"
            class="od-mini-toggle-btn"
            :class="{ 'is-active': currentTab === tab.key }"
            @click="currentTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div v-if="activeRows.length" class="od-recent-grid">
        <div
          v-for="row in activeRows"
          :key="row.id || row.code"
          class="od-recent-row"
          :style="{ 'grid-template-columns': activeTable.gridCols }"
        >
          <span v-for="column in activeTable.columns" :key="column.key" :class="{ 'od-recent-amount': column.type === 'money' }">
            <template v-if="column.type === 'status'">
              <span class="od-status" :class="statusTone(row[column.key])">{{ statusLabel(row[column.key]) }}</span>
            </template>
            <template v-else>{{ formatCell(row, column) }}</template>
          </span>
        </div>
      </div>
      <div v-else class="od-table-empty">
        <span>Chưa có dữ liệu trong kỳ này.</span>
      </div>
    </section>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';

const emptyAccounting = () => ({
  period_label: 'Kỳ hiện tại',
  overview: {},
  charts: { cash_flow: [], managed_composition: [] },
  tables: {},
});

const emptyPendingCounts = () => ({
  partner_applications: 0,
  venue_clusters: 0,
  finance: 0,
  moderation_support: 0,
  detail: { scale_approvals: 0, location_changes: 0, info_changes: 0, refunds: 0, withdrawals: 0, reports: 0, moderation_posts: 0 },
});

export default {
  name: 'AdminDashboard',
  components: { AppIcon },
  data() {
    return {
      financePeriod: 'month',
      accounting: emptyAccounting(),
      pendingCounts: emptyPendingCounts(),
      workCenter: { summary: {}, tasks: [], notifications: [] },
      isLoading: true,
      error: null,
      currentTab: 'booking_ledgers',
      activeCashFlowMetric: 'money_in',
      periodPresets: [
        { key: 'week', label: 'Tuần này' },
        { key: 'month', label: 'Tháng này' },
        { key: 'year', label: 'Năm nay' },
      ],
      tableTabs: [
        {
          key: 'booking_ledgers',
          label: 'Booking thu hộ',
          title: 'Tổng hợp booking thu hộ',
          gridCols: '90px 90px minmax(100px,1fr) minmax(100px,1fr) 100px 90px 130px',
          columns: [
            { key: 'code', label: 'Mã GD' },
            { key: 'booking_code', label: 'Booking' },
            { key: 'customer', label: 'Khách hàng' },
            { key: 'venue_cluster', label: 'Cụm sân' },
            { key: 'amount', label: 'Số tiền', type: 'money' },
            { key: 'method', label: 'Phương thức' },
            { key: 'paid_at', label: 'Thời gian', type: 'date' },
          ],
        },
        {
          key: 'withdrawal_ledgers',
          label: 'Yêu cầu rút',
          title: 'Yêu cầu rút tiền chủ sân & người chơi',
          gridCols: '100px 80px minmax(100px,1fr) 80px 100px 90px 130px',
          columns: [
            { key: 'code', label: 'Mã yêu cầu' },
            { key: 'type', label: 'Loại' },
            { key: 'requester', label: 'Người nhận' },
            { key: 'scope', label: 'Phạm vi' },
            { key: 'amount', label: 'Số tiền', type: 'money' },
            { key: 'status', label: 'Trạng thái', type: 'status' },
            { key: 'requested_at', label: 'Ngày', type: 'date' },
          ],
        },
        {
          key: 'owner_debts',
          label: 'Công nợ chủ sân',
          title: 'Công nợ ví chủ sân',
          gridCols: 'minmax(120px,1fr) minmax(120px,1fr) 120px 120px 120px',
          columns: [
            { key: 'owner', label: 'Chủ sân' },
            { key: 'venue_cluster', label: 'Cụm sân' },
            { key: 'available_balance', label: 'Có thể rút', type: 'money' },
            { key: 'pending_balance', label: 'Đang giữ', type: 'money' },
            { key: 'debt_total', label: 'Tổng nợ', type: 'money' },
          ],
        },
        {
          key: 'customer_debts',
          label: 'Công nợ khách',
          title: 'Công nợ ví khách hàng',
          gridCols: 'minmax(120px,1fr) 100px 100px 100px 100px 90px',
          columns: [
            { key: 'customer', label: 'Khách hàng' },
            { key: 'contact', label: 'Liên hệ' },
            { key: 'balance', label: 'Số dư', type: 'money' },
            { key: 'locked_balance', label: 'Đang khóa', type: 'money' },
            { key: 'debt_total', label: 'Tổng', type: 'money' },
            { key: 'status', label: 'Trạng thái', type: 'status' },
          ],
        },
        {
          key: 'voucher_ledgers',
          label: 'Voucher',
          title: 'Lịch sử trừ tiền voucher hệ thống',
          gridCols: '90px 100px 100px minmax(100px,1fr) minmax(100px,1fr) 130px',
          columns: [
            { key: 'code', label: 'Mã GD' },
            { key: 'amount', label: 'Số tiền', type: 'money' },
            { key: 'balance_after', label: 'Số dư sau', type: 'money' },
            { key: 'reference', label: 'Tham chiếu' },
            { key: 'description', label: 'Mô tả' },
            { key: 'transacted_at', label: 'Thời gian', type: 'date' },
          ],
        },
        {
          key: 'revenue_ledgers',
          label: 'Doanh thu',
          title: 'Lịch sử cộng doanh thu sàn',
          gridCols: 'minmax(100px,1fr) minmax(100px,1fr) 100px minmax(100px,1fr) 130px',
          columns: [
            { key: 'label', label: 'Nguồn thu' },
            { key: 'source', label: 'Đối tượng' },
            { key: 'amount', label: 'Số tiền', type: 'money' },
            { key: 'note', label: 'Ghi chú' },
            { key: 'paid_at', label: 'Thời gian', type: 'date' },
          ],
        },
      ],
    };
  },
  computed: {
    greeting() {
      const hour = new Date().getHours();
      return hour < 11 ? 'Chào buổi sáng' : hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối';
    },
    overview() { return this.accounting.overview || {}; },
    periodLabel() { return this.accounting.period_label || 'Kỳ hiện tại'; },
    actionCount() {
      return Number(this.workCenter.summary?.action_count || 0) ||
        (this.pendingCounts.partner_applications + this.pendingCounts.venue_clusters + this.pendingCounts.finance + this.pendingCounts.moderation_support);
    },
    unreadCount() { return Number(this.workCenter.summary?.unread_notification_count || 0); },
    tasks() { return this.workCenter.tasks || []; },
    visibleTasks() { return this.tasks.slice(0, 6); },
    criticalCount() { return this.tasks.filter((t) => t.priority === 'critical').length; },
    highCount() { return this.tasks.filter((t) => t.priority === 'high').length; },
    financeCount() { return (this.pendingCounts.detail?.refunds || 0) + (this.pendingCounts.detail?.withdrawals || 0); },

    // Liquidity & Debt Percentages
    liquidityRate() {
      const total = Number(this.overview.managed_total || 0);
      const cash = Number(this.overview.system_cash_balance || 0);
      return total > 0 && cash > 0 ? Math.min(100, Math.round((cash / total) * 100)) : 0;
    },
    ownerDebtPercent() {
      const total = Number(this.overview.managed_total || 0);
      return total > 0 ? Math.round((Number(this.overview.owner_debt_total || 0) / total) * 100) : 0;
    },
    customerDebtPercent() {
      const total = Number(this.overview.managed_total || 0);
      return total > 0 ? Math.round((Number(this.overview.customer_debt_total || 0) / total) * 100) : 0;
    },
    cashSurplusPercent() {
      const total = Number(this.overview.managed_total || 0);
      return total > 0 ? Math.max(0, 100 - this.ownerDebtPercent - this.customerDebtPercent) : 0;
    },
    platformFeePercent() {
      const total = Number(this.overview.system_revenue || 0);
      const fee = Number(this.overview.platform_fee_revenue_total || 0);
      return total > 0 && fee > 0 ? Math.round((fee / total) * 100) : 0;
    },
    vipPercent() { return Math.max(0, 100 - this.platformFeePercent); },

    // Cash Flow Bar Chart Data
    cashFlowBars() { return this.accounting.charts?.cash_flow || []; },
    maxMoneyIn() { return Math.max(...this.cashFlowBars.map((b) => Number(b.money_in || 0)), 0); },
    maxMoneyOut() { return Math.max(...this.cashFlowBars.map((b) => Number(b.money_out || 0)), 0); },
    totalMoneyIn() { return this.cashFlowBars.reduce((sum, b) => sum + Number(b.money_in || 0), 0); },
    totalMoneyOut() { return this.cashFlowBars.reduce((sum, b) => sum + Number(b.money_out || 0), 0); },

    // Leaderboard Data
    topVenues() {
      const ledgers = this.accounting.tables?.booking_ledgers || [];
      const map = {};
      ledgers.forEach((b) => {
        const name = b.venue_cluster || 'Cụm sân';
        if (!map[name]) map[name] = { name, revenue: 0 };
        map[name].revenue += Number(b.amount || 0);
      });
      return Object.values(map).sort((a, b) => b.revenue - a.revenue).slice(0, 5);
    },
    maxVenueRevenue() { return Math.max(...this.topVenues.map((v) => v.revenue), 0); },
    topCustomers() {
      const ledgers = this.accounting.tables?.booking_ledgers || [];
      const map = {};
      ledgers.forEach((b) => {
        const name = b.customer || 'Người chơi';
        if (!map[name]) map[name] = { name, revenue: 0, count: 0 };
        map[name].revenue += Number(b.amount || 0);
        map[name].count += 1;
      });
      return Object.values(map).sort((a, b) => b.count - a.count || b.revenue - a.revenue).slice(0, 5);
    },

    // Ledger Table
    activeTable() { return this.tableTabs.find((t) => t.key === this.currentTab) || this.tableTabs[0]; },
    activeRows() { return this.accounting.tables?.[this.currentTab] || []; },
  },
  async mounted() { await this.loadDashboard(); },
  methods: {
    setPeriod(period) {
      this.financePeriod = period;
      this.loadDashboard();
    },
    async loadDashboard() {
      this.isLoading = true;
      this.error = null;
      try {
        const results = await Promise.allSettled([
          api(`/api/admin/dashboard?finance_period=${this.financePeriod}`),
          api('/api/admin/pending-counts'),
          api('/api/admin/work-center'),
        ]);
        if (results[0].status === 'fulfilled') {
          this.accounting = { ...emptyAccounting(), ...(results[0].value?.accounting || {}) };
        } else {
          this.error = results[0].reason?.message || 'Không thể tải dữ liệu kế toán.';
        }
        if (results[1].status === 'fulfilled') {
          this.pendingCounts = {
            ...emptyPendingCounts(),
            ...(results[1].value?.data || {}),
            detail: { ...emptyPendingCounts().detail, ...(results[1].value?.data?.detail || {}) },
          };
        }
        if (results[2].status === 'fulfilled') {
          this.workCenter = results[2].value?.data || { summary: {}, tasks: [], notifications: [] };
        }
      } catch (err) {
        this.error = err.message || 'Không thể tải dữ liệu điều hành.';
      } finally {
        this.isLoading = false;
      }
    },
    async openTask(task) {
      if (task?.target) await this.$router.push(task.target).catch(() => {});
    },
    priorityLabel(priority) {
      return priority === 'critical' ? 'Khẩn cấp' : priority === 'high' ? 'Ưu tiên cao' : 'Theo dõi';
    },
    priorityTone(priority) {
      if (priority === 'critical') return 'od-status--danger';
      if (priority === 'high') return 'od-status--warning';
      return 'od-status--muted';
    },
    formatRelative(value) {
      if (!value) return '-';
      const minutes = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 60000));
      if (minutes < 60) return `${minutes || 1} phút trước`;
      if (minutes < 1440) return `${Math.floor(minutes / 60)} giờ trước`;
      return `${Math.floor(minutes / 1440)} ngày trước`;
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(amount || 0));
    },
    compactCurrency(value) {
      const n = Number(value || 0);
      if (Math.abs(n) >= 1e9) return `${(n / 1e9).toFixed(1)} tỷ`;
      if (Math.abs(n) >= 1e6) return `${(n / 1e6).toFixed(1)}tr`;
      if (Math.abs(n) >= 1e3) return `${Math.round(n / 1e3)}k`;
      return n.toLocaleString('vi-VN');
    },
    formatNumber(v) { return Number(v || 0).toLocaleString('vi-VN'); },
    formatDate(value) {
      if (!value) return '-';
      return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
    },
    formatCell(row, col) {
      const v = row[col.key];
      if (col.type === 'money') return this.formatCurrency(v);
      if (col.type === 'date') return this.formatDate(v);
      if (col.type === 'status') return this.statusLabel(v);
      return v || '-';
    },
    statusLabel(status) {
      return ({ pending: 'Chờ xử lý', approved: 'Đã duyệt', paid: 'Đã chi', completed: 'Hoàn tất', rejected: 'Từ chối', cancelled: 'Đã hủy', active: 'Hoạt động', locked: 'Đang khóa', suspended: 'Tạm ngưng', owner: 'Chủ sân', user: 'Người dùng' })[status] || status || '-';
    },
    statusTone(status) {
      if (['approved', 'paid', 'completed', 'active'].includes(status)) return 'od-status--sage';
      if (['pending'].includes(status)) return 'od-status--warning';
      if (['rejected', 'cancelled', 'locked', 'suspended'].includes(status)) return 'od-status--danger';
      return 'od-status--muted';
    },
    rankingWidth(value, max) { return max && value ? Math.max(10, Math.round((Number(value) / max) * 100)) : 0; },
    cashFlowBarHeight(value) {
      const max = this.activeCashFlowMetric === 'money_in' ? this.maxMoneyIn : this.maxMoneyOut;
      return max > 0 ? Math.max(3, Math.round((Number(value || 0) / max) * 100)) : 0;
    },
  },
};
</script>

<style scoped>
/* Re-use OwnerDashboard's exact design tokens & classes with `od-` prefix */

.admin-dashboard-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  color: #334155;
}

/* OwnerDashboard design system tokens used as-is */
.text-sage { color: #5c7e6e; }
.text-amber { color: #d97706; }
.text-danger { color: #dc2626; }

/* TASK ROW ADAPTATION */
.od-task-row {
  cursor: pointer;
  border: none;
  width: 100%;
}

.od-task-time {
  font-size: 10.5px;
  color: #94a3b8;
}

/* STATUS BADGE */
.od-status {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 500;
  white-space: nowrap;
  background: #f1f5f9;
  color: #64748b;
}

.od-status--sage { background: #edf4f0; color: #5c7e6e; }
.od-status--warning { background: #fffbeb; color: #b45309; }
.od-status--danger { background: #fef2f2; color: #b91c1c; }
.od-status--muted { background: #f1f5f9; color: #64748b; }

/* LEDGER TABLE TABS */
.od-ledger-tabs {
  display: inline-flex;
  align-items: center;
  background: #f1f5f9;
  padding: 2px;
  border-radius: 8px;
  gap: 2px;
  overflow-x: auto;
  max-width: 60%;
}

/* ===== COMPLETE OWNER DASHBOARD DESIGN SYSTEM (od-*) ===== */
/* All classes below are identical to OwnerDashboard.vue */

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
.od-hero-pattern { position: absolute; top: 0; right: 0; bottom: 0; width: 50%; pointer-events: none; overflow: hidden; display: flex; align-items: center; justify-content: flex-end; border-radius: inherit; }
.od-hero-pattern svg { height: 100%; width: auto; }
.od-hero-left { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
.od-greeting-pill { display: inline-flex; align-items: center; background: #ffffff; color: #5c7e6e; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 500; width: fit-content; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.03); }
.od-hero-title { margin: 0; color: #1e293b; font-size: clamp(20px, 2vw, 24px); font-weight: 600; letter-spacing: -0.01em; }
.od-hero-sub { margin: 0; color: #64748b; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.od-controls { position: relative; z-index: 2; display: flex; align-items: center; gap: 8px; flex-shrink: 0; flex-wrap: nowrap; justify-content: flex-end; }
.od-filter-pill-group { display: inline-flex; align-items: center; gap: 4px; background: #ffffff; padding: 4px; border-radius: 10px; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); }
.od-filter-pill { border: none; background: transparent; padding: 6px 12px; border-radius: 7px; color: #64748b; font-size: 12px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: all 0.15s ease; }
.od-filter-pill:hover { background: #f8fafc; color: #1e293b; }
.od-filter-pill.is-active { background: #edf4f0; color: #5c7e6e; font-weight: 600; }

.od-spotlight-banner { background: #ffffff; border-radius: 16px; padding: 20px 24px; display: grid; grid-template-columns: 1fr 160px; gap: 20px; align-items: center; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035); position: relative; overflow: hidden; }
.od-spotlight-content { display: flex; flex-direction: column; gap: 6px; }
.od-spotlight-badge { display: inline-flex; align-items: center; background: #edf4f0; color: #5c7e6e; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 600; letter-spacing: 0.05em; width: fit-content; }
.od-spotlight-title { margin: 0; font-size: 18px; font-weight: 600; color: #1e293b; line-height: 1.25; }
.od-spotlight-desc { margin: 0; font-size: 13.5px; line-height: 1.5; color: #64748b; }
.od-spotlight-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
.od-spotlight-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: 12px; font-weight: 500; text-decoration: none; transition: all 0.15s ease; }
.od-btn-primary { background: #edf4f0; color: #5c7e6e; }
.od-btn-primary:hover { background: #e2ede7; }
.od-btn-ghost { background: #f8fafc; color: #475569; }
.od-btn-ghost:hover { background: #f1f5f9; color: #1e293b; }
.od-spotlight-illustration { display: flex; align-items: center; justify-content: center; }
.od-spotlight-img { max-width: 140px; max-height: 110px; object-fit: contain; }

.od-alert { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 10px; font-size: 12.5px; }
.od-alert--error { background: #fef2f2; color: #b91c1c; }
.od-link-button { margin-left: auto; border: none; background: none; color: inherit; font-size: 12px; font-weight: 600; text-decoration: underline; cursor: pointer; }

.od-section, .od-panel { border: none; border-radius: 16px; background: #ffffff; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035); padding: 20px 24px; }
.od-section-heading, .od-panel-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.od-heading-group { display: flex; align-items: center; gap: 8px; }
.od-section-title, .od-panel-title { margin: 0; color: #1e293b; font-size: 15.5px; font-weight: 600; }
.od-section-tag { background: #edf4f0; color: #5c7e6e; font-size: 10.5px; font-weight: 500; padding: 2px 6px; border-radius: 4px; }
.od-panel-eyebrow { display: block; color: #94a3b8; font-size: 10.5px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px; }
.od-updated, .od-chart-total { color: #94a3b8; font-size: 11.5px; font-weight: 500; }
.od-text-link { color: #5c7e6e; font-size: 12px; font-weight: 500; text-decoration: none; white-space: nowrap; }
.od-text-link:hover { text-decoration: underline; }

.od-attention-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.od-attention-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; background: #f8fafc; color: #334155; text-decoration: none; transition: all 0.15s ease; }
.od-attention-item:hover { background: #f1f5f9; }
.od-attention-icon-wrap { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #ffffff; color: #5c7e6e; box-shadow: 0 1px 3px rgba(0,0,0,0.03); flex-shrink: 0; }
.od-attention-copy { display: flex; flex-direction: column; gap: 1px; flex: 1; }
.od-attention-value { color: #1e293b; font-size: 18px; font-weight: 600; line-height: 1.1; }
.od-attention-label { color: #64748b; font-size: 11.5px; font-weight: 450; }
.od-attention-arrow { color: #cbd5e1; }

.od-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.od-kpi-card { background: #ffffff; border-radius: 16px; padding: 20px 24px; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035); display: flex; flex-direction: column; }
.od-kpi-topline { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.od-kpi-label { color: #64748b; font-size: 12px; font-weight: 500; }
.od-kpi-icon-bubble { width: 30px; height: 30px; border-radius: 7px; background: #edf4f0; color: #5c7e6e; display: flex; align-items: center; justify-content: center; }
.od-kpi-val { color: #1e293b; font-size: 19px; font-weight: 600; line-height: 1.2; margin-bottom: 4px; }
.od-kpi-note { color: #94a3b8; font-size: 11px; }
.od-kpi-card--gauge { display: flex; flex-direction: row; align-items: center; justify-content: space-between; }
.od-gauge-left { display: flex; flex-direction: column; }
.od-gauge-right { width: 44px; height: 44px; flex-shrink: 0; }
.od-donut-gauge { width: 100%; height: 100%; }
.od-gauge-bg { fill: none; stroke: #f1f5f9; stroke-width: 4; }
.od-gauge-fill { fill: none; stroke: #5c7e6e; stroke-width: 4; stroke-linecap: round; transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dasharray 0.5s ease; }

.od-main-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.8fr); gap: 16px; align-items: start; }
.od-today-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
.od-today-stat-pill { background: #f8fafc; padding: 10px 14px; border-radius: 10px; display: flex; flex-direction: column; gap: 2px; }
.pill-lbl { font-size: 10.5px; color: #64748b; font-weight: 500; }
.pill-val { font-size: 14.5px; font-weight: 600; color: #1e293b; }
.od-loading-state { display: flex; align-items: center; justify-content: center; min-height: 110px; color: #94a3b8; font-size: 12.5px; }
.od-table-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; min-height: 100px; color: #94a3b8; font-size: 12.5px; }

.od-booking-list { display: flex; flex-direction: column; gap: 6px; }
.od-booking-row { display: grid; grid-template-columns: 90px minmax(0, 1fr) auto auto; align-items: center; gap: 12px; padding: 10px 14px; background: #f8fafc; border-radius: 10px; color: #334155; text-decoration: none; transition: all 0.15s ease; }
.od-booking-row:hover { background: #f1f5f9; }
.od-booking-time-badge { display: flex; align-items: center; }
.od-booking-main { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.od-booking-court { font-size: 12.5px; font-weight: 550; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.od-booking-cust { font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.od-booking-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.od-row-arrow { color: #cbd5e1; }

.od-side-column { display: flex; flex-direction: column; gap: 16px; }
.od-wallet-value { font-size: 22px; font-weight: 600; color: #5c7e6e; margin: 8px 0 10px; line-height: 1.2; }
.od-wallet-details { display: flex; flex-direction: column; gap: 6px; padding-top: 10px; border-top: 1px solid #f1f5f9; margin-bottom: 14px; }
.od-wallet-detail-row { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; }
.od-wallet-detail-row span:last-child { color: #334155; font-weight: 500; }
.od-wallet-btn { display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 8px 14px; background: #edf4f0; color: #5c7e6e; border-radius: 999px; font-size: 12px; font-weight: 500; text-decoration: none; transition: all 0.15s ease; }
.od-wallet-btn:hover { background: #e2ede7; }
.od-panel-icon-bubble { width: 30px; height: 30px; border-radius: 7px; background: #edf4f0; color: #5c7e6e; display: flex; align-items: center; justify-content: center; }

.od-health-total { display: flex; align-items: baseline; gap: 4px; margin: 2px 0 10px; }
.od-health-num { font-size: 20px; font-weight: 600; color: #1e293b; }
.od-health-unit { font-size: 11.5px; color: #64748b; }
.od-health-list { display: flex; flex-direction: column; gap: 6px; }
.health-row { display: flex; justify-content: space-between; align-items: center; font-size: 12px; background: #f8fafc; padding: 8px 12px; border-radius: 8px; }
.health-lbl { color: #64748b; font-weight: 450; }
.health-val { color: #1e293b; font-weight: 550; }

.od-bento-section { display: flex; flex-direction: column; gap: 14px; }
.od-bento-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.od-bento-card { background: #ffffff; border-radius: 16px; padding: 20px 24px; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035); display: flex; flex-direction: column; }
.od-bento-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.od-bento-card-title-group { display: flex; align-items: center; gap: 8px; }
.bento-badge-icon { width: 26px; height: 26px; border-radius: 6px; color: #5c7e6e; background: #edf4f0; display: flex; align-items: center; justify-content: center; }
.od-bento-card-head h3 { margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; }
.bento-more-link { font-size: 11.5px; color: #94a3b8; text-decoration: none; }
.bento-more-link:hover { color: #5c7e6e; }
.od-bento-list { display: flex; flex-direction: column; gap: 6px; }
.od-bento-item { display: grid; grid-template-columns: 22px 1fr auto; align-items: center; gap: 10px; background: #f8fafc; padding: 8px 12px; border-radius: 8px; }
.od-rank-num { font-size: 11.5px; font-weight: 600; color: #94a3b8; display: flex; align-items: center; justify-content: center; }
.od-rank-num--first { color: #5c7e6e; }
.od-bento-item-main { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.od-bento-item-name { font-size: 12px; font-weight: 500; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.od-bento-sub { font-size: 11px; color: #94a3b8; }
.od-bento-progress-track { width: 100%; height: 4px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
.od-bento-progress-bar { height: 100%; background: #8da89b; border-radius: inherit; }
.od-bento-item-val { font-size: 11.5px; font-weight: 550; color: #334155; text-align: right; white-space: nowrap; }
.od-chart-empty { display: flex; align-items: center; justify-content: center; min-height: 100px; color: #94a3b8; font-size: 12px; }

.od-analytics-section { display: flex; flex-direction: column; gap: 14px; }
.od-chart-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.od-chart-panel { min-height: 270px; display: flex; flex-direction: column; }
.od-chart-actions { display: flex; align-items: center; }
.od-mini-pill-toggle { display: inline-flex; align-items: center; background: #f1f5f9; padding: 2px; border-radius: 8px; gap: 2px; }
.od-mini-toggle-btn { border: none; background: transparent; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 500; color: #64748b; cursor: pointer; transition: all 0.12s ease; white-space: nowrap; }
.od-mini-toggle-btn.is-active { background: #ffffff; color: #5c7e6e; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.od-chart-hero-stat { display: flex; align-items: baseline; gap: 6px; margin: 2px 0 12px; }
.od-hero-stat-val { font-size: 20px; font-weight: 600; color: #1e293b; line-height: 1.2; }
.od-hero-stat-sub { font-size: 11.5px; color: #94a3b8; }

.od-bar-chart { display: flex; align-items: flex-end; gap: 6px; height: 140px; margin-top: auto; padding-bottom: 4px; border-bottom: 1px solid #f1f5f9; }
.od-bar-column { display: flex; flex: 1; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 4px; min-width: 0; }
.od-bar-value { font-size: 9px; color: #94a3b8; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.od-bar-track { width: 100%; max-width: 24px; height: 95px; background: #f1f5f9; border-radius: 4px 4px 0 0; display: flex; align-items: flex-end; }
.od-bar-fill { width: 100%; background: #8da89b; border-radius: 4px 4px 0 0; min-height: 3px; transition: height 0.3s ease; }
.od-bar-lbl { font-size: 9px; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.od-channel-layout { display: grid; grid-template-columns: 100px 1fr; gap: 20px; align-items: center; margin-top: auto; padding: 6px 0; }
.od-channel-donut-wrap { position: relative; width: 100px; height: 100px; flex-shrink: 0; }
.od-channel-donut { width: 100%; height: 100%; }
.od-donut-base { fill: none; stroke: #f1f5f9; stroke-width: 4.5; }
.od-donut-segment { fill: none; stroke-width: 4.5; stroke-linecap: butt; transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dasharray 0.5s ease; }
.od-donut-online { stroke: #5c7e6e; }
.od-donut-counter { stroke: #94a3b8; }
.od-donut-center-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; pointer-events: none; }
.donut-center-num { font-size: 15px; font-weight: 600; color: #1e293b; line-height: 1.1; }
.donut-center-lbl { font-size: 10px; color: #94a3b8; }
.od-channel-legend { display: flex; flex-direction: column; gap: 8px; }
.od-channel-card { background: #f8fafc; border-radius: 8px; padding: 8px 12px; display: flex; flex-direction: column; gap: 3px; }
.od-ch-top { display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; }
.od-ch-bullet { width: 7px; height: 7px; border-radius: 99px; display: inline-block; margin-right: 6px; flex-shrink: 0; }
.od-ch-bullet--online { background: #5c7e6e; }
.od-ch-bullet--counter { background: #94a3b8; }
.od-ch-title { font-weight: 550; color: #1e293b; flex: 1; }
.od-ch-pct { font-weight: 600; color: #5c7e6e; font-size: 11.5px; }
.od-ch-sub { display: flex; justify-content: space-between; font-size: 11px; color: #64748b; }
.od-ch-rev { font-weight: 500; color: #334155; }

.od-status-chart { display: flex; flex-direction: column; gap: 10px; margin-top: auto; padding-bottom: 4px; }
.od-status-row { display: grid; grid-template-columns: 130px 1fr 60px; align-items: center; gap: 10px; font-size: 11.5px; }
.od-status-label-group { display: flex; align-items: baseline; justify-content: space-between; padding-right: 6px; }
.status-lbl { color: #64748b; font-weight: 450; }
.status-pct { color: #94a3b8; font-size: 10.5px; font-weight: 500; }
.status-cnt { text-align: right; color: #1e293b; font-weight: 550; }
.od-status-track { height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden; display: block; }
.od-status-fill { height: 100%; border-radius: inherit; display: block; background: #cbd5e1; }
.od-status-fill--pending { background: #f59e0b; }
.od-status-fill--confirmed { background: #93c5fd; }
.od-status-fill--completed { background: #5c7e6e; }
.od-status-fill--cancelled { background: #fca5a5; }

.od-recent-grid { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
.od-recent-row { display: grid; align-items: center; gap: 10px; padding: 10px 14px; background: #f8fafc; border-radius: 8px; color: #64748b; font-size: 11.5px; text-decoration: none; transition: all 0.15s ease; }
.od-recent-row:hover { background: #f1f5f9; }
.od-recent-amount { text-align: right; color: #5c7e6e; font-weight: 600; }

/* RESPONSIVE */
@media (max-width: 1120px) {
  .od-spotlight-banner { grid-template-columns: 1fr; }
  .od-spotlight-illustration { display: none; }
  .od-bento-grid { grid-template-columns: 1fr; }
  .od-attention-grid { grid-template-columns: repeat(2, 1fr); }
  .od-main-grid { grid-template-columns: 1fr; }
  .od-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 760px) {
  .od-hero { align-items: flex-start; flex-direction: column; }
  .od-controls { width: 100%; }
  .od-filter-pill-group { width: 100%; justify-content: space-between; }
  .od-chart-grid { grid-template-columns: 1fr; }
  .od-channel-layout { grid-template-columns: 1fr; justify-items: center; text-align: center; }
  .od-booking-row { grid-template-columns: 75px minmax(0, 1fr) auto; }
  .od-ledger-tabs { max-width: 100%; width: 100%; }
}

@media (max-width: 520px) {
  .od-section, .od-panel, .od-spotlight-banner, .od-hero { padding: 14px 16px; border-radius: 12px; }
  .od-attention-grid, .od-kpi-grid { grid-template-columns: 1fr; }
  .od-today-summary { grid-template-columns: repeat(2, 1fr); }
}
</style>
