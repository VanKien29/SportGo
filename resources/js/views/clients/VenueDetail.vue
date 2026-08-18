<template>
  <div class="venue-detail-page">
    <PublicNavbar />

    <!-- Loading State -->
    <div v-if="loading" class="state-screen loading-screen">
      <div class="spinner"></div>
      <p class="loading-desc-text">Đang tải thông tin sân...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="state-screen error-screen">
      <p class="error-msg">{{ error }}</p>
      <button class="btn-outline" @click="$router.back()">Quay lại</button>
    </div>

    <!-- Content chính -->
    <template v-else-if="venue">
      <!-- HERO BAND KHU VỰC ĐẦU TRANG -->
      <section class="sg-hero-band">
        <div class="sg-container">


          <!-- Hero Grid Layout -->
          <div class="sg-hero-grid">
            <!-- Left Column: Gallery Stage -->
            <div class="sg-hero-gallery">
              <div class="sg-gallery-main-stage">
                <img v-if="activeImage" :src="activeImage" :alt="venue.name" class="sg-main-stage-img" @error="removeGalleryImage(activeImage)" />
                <div v-else class="sg-gallery-empty-stage">
                  <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                  <strong class="sg-empty-stage-name">{{ initials(venue.name) }}</strong>
                  <span class="sg-empty-stage-desc">Hình ảnh đang được cập nhật</span>
                </div>
              </div>

              <!-- Thumbnails Row -->
              <div v-if="gallery.length > 1" class="sg-gallery-thumbs-row">
                <button
                  v-for="(image, idx) in gallery"
                  :key="idx"
                  type="button"
                  class="sg-gallery-thumb-btn"
                  :class="{ 'is-active': image === activeImage }"
                  @click="activeImage = image"
                >
                  <img :src="image" :alt="venue.name" class="sg-thumb-img" @error="removeGalleryImage(image)" />
                </button>
              </div>
            </div>

            <!-- Right Column: Venue Primary Info & Actions (Đồng bộ 100% với bên bản đồ) -->
            <div class="sg-hero-info-col">
              <h1 class="sg-hero-venue-name">{{ venue.name }}</h1>
              
              <!-- Danh sách thông tin dạng văn bản phẳng, 0 Icon -->
              <div class="sg-detail-meta-list">
                <div class="sg-meta-item">
                  <span>{{ fullAddress }}</span>
                </div>

                <div class="sg-meta-item">
                  <span>Bộ môn: {{ courtTypeSummaryText }}</span>
                </div>

                <div class="sg-meta-item">
                  <span>Quy mô: {{ courtCount }} sân hoạt động</span>
                </div>

                <div class="sg-meta-item">
                  <span>Đánh giá: {{ ratingLabel }} ({{ ratingCountLabel }})</span>
                </div>

                <div class="sg-meta-item">
                  <span>Giá từ: <span class="sg-meta-price-val">{{ priceLabel }}</span></span>
                </div>
              </div>

              <!-- Hero Actions Group -->
              <div class="sg-hero-cta-group">
                <button type="button" class="sg-btn-primary-action" @click="scrollToBooking">
                  <span>Chọn lịch trống & đặt ngay</span>
                </button>
                <button type="button" class="sg-btn-ghost-action" @click="chatWithVenue">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                  <span>Nhắn tin</span>
                </button>
                <button type="button" class="sg-btn-ghost-action" title="Chia sẻ sân" @click="copyVenueLink">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
                  <span>Chia sẻ</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- NAVIGATION TABS BAR -->
      <div class="sg-detail-tabs-wrapper">
        <nav class="sg-container sg-detail-tabs-bar" aria-label="Nội dung chi tiết sân">
          <button
            v-for="tab in visibleVenueTabs"
            :key="tab.id"
            type="button"
            class="sg-detail-tab-btn"
            :class="{ 'is-active': activeTab === tab.id }"
            :aria-pressed="activeTab === tab.id"
            @click="setActiveTab(tab.id)"
          >
            {{ tab.label }}
            <span v-if="tab.id === 'reviews' && reviewCount" class="sg-tab-count-badge">{{ reviewCount }}</span>
          </button>
        </nav>
      </div>

      <!-- MAIN BODY CONTENT LAYOUT (2 COLUMNS: CONTENT MAIN 68% + BOOKING WIDGET 32%) -->
      <section class="sg-container sg-detail-layout">
        <!-- LEFT CONTENT MAIN -->
        <div class="sg-detail-main-column">
          <!-- TAB 1: TỔNG QUAN & TIỆN ÍCH -->
          <template v-if="activeTab === 'overview'">
            <!-- Mô tả sân -->
            <div v-if="venue.description" class="sg-detail-block">
              <h2 class="sg-section-title">Mô tả địa điểm</h2>
              <p class="sg-section-desc">{{ venue.description }}</p>
            </div>

            <!-- Tiện ích phẳng 2 cột -->
            <div v-if="amenities.length" class="sg-detail-block">
              <h2 class="sg-section-title">Tiện ích nổi bật</h2>
              <div class="sg-amenities-flat-grid">
                <article v-for="amenity in amenities" :key="amenity.id || amenity.name" class="sg-amenity-flat-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  <div class="sg-amenity-copy">
                    <span class="sg-amenity-name">{{ amenity.name || amenity }}</span>
                    <span v-if="amenity.description" class="sg-amenity-sub">{{ amenity.description }}</span>
                  </div>
                </article>
              </div>
            </div>

            <!-- Dịch vụ & Sản phẩm tại sân -->
            <div v-if="groupedServices.length" class="sg-detail-block">
              <h2 class="sg-section-title">Dịch vụ &amp; Sản phẩm tại sân</h2>
              <div class="sg-services-category-wrap">
                <div v-for="group in groupedServices" :key="group.key" class="sg-service-cat-block">
                  <h3 class="sg-service-cat-title">{{ group.label }}</h3>
                  <div class="sg-services-flat-list">
                    <div v-for="item in group.items" :key="item.id" class="sg-service-item-row">
                      <div class="sg-service-copy">
                        <span class="sg-service-name">{{ item.name }}</span>
                        <span v-if="item.description" class="sg-service-desc">{{ item.description }}</span>
                      </div>
                      <div class="sg-service-price-wrap">
                        <span class="sg-service-price">{{ formatPrice(item.price) }}</span>
                        <span class="sg-service-unit">/ {{ item.unit }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="!venue.description && !amenities.length && !groupedServices.length" class="sg-detail-block sg-empty-block">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              <h3 class="sg-empty-title">Thông tin tổng quan đang cập nhật</h3>
              <p class="sg-empty-desc">Bạn vẫn có thể xem lịch trống và đặt sân trực tiếp bên phải.</p>
            </div>
          </template>

          <!-- TAB 2: HỘI VIÊN SÂN -->
          <template v-else-if="activeTab === 'membership'">
            <div class="sg-membership-detail">
              <!-- Intro Banner with Illustration (No gradient, no nested boxes) -->
              <section class="sg-membership-intro">
                <div class="sg-membership-intro-copy">
                  <span class="sg-membership-eyebrow">CHƯƠNG TRÌNH THÀNH VIÊN SÂN</span>
                  <h2 class="sg-section-title">Chơi càng đều, quyền lợi càng tốt</h2>
                  <p>Hạng hội viên được tính riêng tại {{ venue.name }} dựa trên số lượt đặt hoàn tất và tổng chi tiêu. Quyền lợi giảm giá áp dụng trực tiếp mỗi khi bạn đặt sân.</p>
                  
                  <div class="sg-membership-status-row">
                    <span class="sg-membership-status-label">Trạng thái tích lũy:</span>
                    <span v-if="currentVenueMembership" class="sg-membership-status-val">
                      {{ currentVenueMembership.tier?.label || currentVenueMembership.tier?.tier_label || "Thường" }} · Giảm {{ formatPercent(currentVenueMembership.tier?.discount_percent) }}% khi đặt sân
                    </span>
                    <span v-else class="sg-membership-status-val">
                      Bắt đầu từ Thường (Đặt sân để bắt đầu tích lũy hạng)
                    </span>
                  </div>
                </div>

                <div class="sg-membership-intro-visual" aria-hidden="true">
                  <svg class="sg-membership-illus" viewBox="0 0 200 130" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="150" cy="65" r="45" fill="#f0fdf4" />
                    <circle cx="45" cy="85" r="22" fill="#f8fafc" />
                    <rect x="25" y="75" width="28" height="40" rx="3" fill="#e2e8f0" />
                    <rect x="60" y="55" width="28" height="60" rx="3" fill="#cbd5e1" />
                    <rect x="95" y="40" width="28" height="75" rx="3" fill="#15803d" opacity="0.2" />
                    <rect x="130" y="25" width="34" height="90" rx="3" fill="#15803d" />
                    <text x="39" y="100" text-anchor="middle" fill="#64748b" font-size="10" font-family="sans-serif">1</text>
                    <text x="74" y="90" text-anchor="middle" fill="#475569" font-size="10" font-family="sans-serif">2</text>
                    <text x="109" y="80" text-anchor="middle" fill="#15803d" font-size="10" font-family="sans-serif">3</text>
                    <text x="147" y="75" text-anchor="middle" fill="#ffffff" font-size="11" font-family="sans-serif">★</text>
                    <path d="M140 10 h14 v6 a7 7 0 0 1 -7 7 a7 7 0 0 1 -7 -7 v-6 z" fill="#15803d" />
                    <path d="M137 12 h3 v3 a4 4 0 0 1 -3 -3 z M157 12 h-3 v3 a4 4 0 0 0 3 -3 z" stroke="#15803d" stroke-width="1.2" fill="none" />
                    <path d="M147 23 v4 M143 27 h8" stroke="#15803d" stroke-width="1.5" stroke-linecap="round" />
                    <path d="M175 35 l2 4 4 1 -3 3 1 4 -4 -2 -4 2 1 -4 -3 -3 4 -1 z" fill="#15803d" opacity="0.4" />
                    <path d="M20 40 l1.5 3 3 0.7 -2.2 2.2 0.7 3 -3 -1.5 -3 1.5 0.7 -3 -2.2 -2.2 3 -0.7 z" fill="#94a3b8" opacity="0.5" />
                  </svg>
                </div>
              </section>

              <!-- Tiers Flat Columns (Rich Full-Scale Vector Illustrations) -->
              <div class="sg-membership-tier-grid">
                <article
                  v-for="tier in membershipTiers"
                  :key="tier.tier_key || tier.tier"
                  class="sg-membership-tier-col"
                  :class="{ 'is-current': currentVenueMembership?.tier?.tier_key === (tier.tier_key || tier.tier) }"
                >
                  <div class="sg-membership-tier-topline">
                    <span class="sg-membership-tier-index">0{{ Number(tier.tier_order || 0) + 1 }}</span>
                    <span v-if="currentVenueMembership?.tier?.tier_key === (tier.tier_key || tier.tier)" class="sg-membership-current-label">(Hạng của bạn)</span>
                  </div>

                  <!-- Rich Vector Illustration (NO enclosing circle, expansive artwork) -->
                  <div class="sg-tier-illustration-wrap" aria-hidden="true">
                    <!-- Diamond Tier Elite Illustration -->
                    <svg v-if="(tier.tier_key || tier.tier) === 'diamond' || (tier.tier_key || tier.tier) === 'platinum'" class="sg-tier-illus" viewBox="0 0 90 75" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <ellipse cx="45" cy="67" rx="38" ry="5" fill="#ecfdf5" />
                      <path d="M22 48 C28 54 38 58 45 58 C52 58 62 54 68 48" stroke="#15803d" stroke-width="1.6" stroke-linecap="round" fill="none" />
                      <path d="M16 42 C24 48 35 52 45 52 C55 52 66 48 74 42" stroke="#10b981" stroke-width="1.2" stroke-linecap="round" fill="none" opacity="0.6" />
                      <rect x="36" y="58" width="18" height="6" rx="2" fill="#0f172a" />
                      <path d="M30 26 L45 12 L60 26 L45 54 Z" fill="#ffffff" stroke="#0f172a" stroke-width="1.6" stroke-linejoin="round" />
                      <path d="M20 26 L30 26 L45 54 L20 26 Z" fill="#dcfce7" stroke="#0f172a" stroke-width="1.3" stroke-linejoin="round" />
                      <path d="M70 26 L60 26 L45 54 L70 26 Z" fill="#dcfce7" stroke="#0f172a" stroke-width="1.3" stroke-linejoin="round" />
                      <path d="M20 26 L30 26 L45 12 L20 26 Z" fill="#ffffff" stroke="#0f172a" stroke-width="1.3" stroke-linejoin="round" />
                      <path d="M70 26 L60 26 L45 12 L70 26 Z" fill="#ffffff" stroke="#0f172a" stroke-width="1.3" stroke-linejoin="round" />
                      <path d="M30 26 L45 26 L45 54 L30 26 Z" fill="#a7f3d0" stroke="#0f172a" stroke-width="1" />
                      <path d="M60 26 L45 26 L45 54 L60 26 Z" fill="#6ee7b7" stroke="#0f172a" stroke-width="1" />
                      <path d="M30 26 L45 12 L45 26 Z" fill="#ffffff" stroke="#0f172a" stroke-width="1" />
                      <path d="M60 26 L45 12 L45 26 Z" fill="#dcfce7" stroke="#0f172a" stroke-width="1" />
                      <path d="M37 12 L34 5 L39 8 L45 3 L51 8 L56 5 L53 12 Z" fill="#f59e0b" stroke="#b45309" stroke-width="1" stroke-linejoin="round" />
                      <circle cx="45" cy="5" r="1.2" fill="#ffffff" />
                      <path d="M45 0 V2 M10 26 H12 M78 26 H80" stroke="#15803d" stroke-width="2" stroke-linecap="round" />
                      <path d="M12 14 L14 18 L18 19 L14 20 L12 24 L10 20 L6 19 L10 18 Z" fill="#15803d" />
                      <path d="M76 10 L77.5 13 L80.5 14 L77.5 15 L76 18 L74.5 15 L71.5 14 L74.5 13 Z" fill="#15803d" />
                    </svg>

                    <!-- Gold Tier Championship Illustration -->
                    <svg v-else-if="(tier.tier_key || tier.tier) === 'gold'" class="sg-tier-illus" viewBox="0 0 90 75" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <ellipse cx="45" cy="67" rx="36" ry="5" fill="#fef3c7" />
                      <path d="M26 48 C18 42 18 26 28 18" stroke="#d97706" stroke-width="1.5" stroke-linecap="round" fill="none" />
                      <path d="M21 44 C19 41 21 38 24 39" fill="#f59e0b" />
                      <path d="M18 34 C16 31 18 28 21 29" fill="#f59e0b" />
                      <path d="M21 24 C20 21 23 19 25 21" fill="#f59e0b" />
                      <path d="M64 48 C72 42 72 26 62 18" stroke="#d97706" stroke-width="1.5" stroke-linecap="round" fill="none" />
                      <path d="M69 44 C71 41 69 38 66 39" fill="#f59e0b" />
                      <path d="M72 34 C74 31 72 28 69 29" fill="#f59e0b" />
                      <path d="M69 24 C70 21 67 19 65 21" fill="#f59e0b" />
                      <rect x="34" y="56" width="22" height="7" rx="2" fill="#b45309" />
                      <rect x="37" y="50" width="16" height="6" fill="#d97706" />
                      <path d="M41 44 H49 L47 50 H43 Z" fill="#f59e0b" />
                      <path d="M31 16 H59 V28 C59 38 52 44 45 44 C38 44 31 38 31 28 Z" fill="#fef3c7" stroke="#b45309" stroke-width="1.6" stroke-linejoin="round" />
                      <path d="M36 16 H54 V26 C54 33 50 38 45 38 C40 38 36 33 36 26 Z" fill="#fde68a" />
                      <path d="M31 20 C24 20 23 30 32 32" stroke="#b45309" stroke-width="1.6" stroke-linecap="round" fill="none" />
                      <path d="M59 20 C66 20 67 30 58 32" stroke="#b45309" stroke-width="1.6" stroke-linecap="round" fill="none" />
                      <path d="M45 8 L47 13 L52 13.5 L48 17 L49.5 22 L45 19.5 L40.5 22 L42 17 L38 13.5 L43 13 Z" fill="#b45309" stroke="#f59e0b" stroke-width="0.8" />
                      <circle cx="28" cy="10" r="1.5" fill="#f59e0b" />
                      <circle cx="62" cy="12" r="1.5" fill="#f59e0b" />
                      <rect x="23" y="28" width="2" height="2" transform="rotate(45 23 28)" fill="#d97706" />
                      <rect x="66" y="26" width="2" height="2" transform="rotate(45 66 26)" fill="#d97706" />
                    </svg>

                    <!-- Silver Tier Active Milestone Illustration -->
                    <svg v-else-if="(tier.tier_key || tier.tier) === 'silver'" class="sg-tier-illus" viewBox="0 0 90 75" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <ellipse cx="45" cy="67" rx="36" ry="5" fill="#f1f5f9" />
                      <path d="M24 64 L30 46 H60 L66 64 Z" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="1.2" />
                      <path d="M30 46 H60 V64 H30 Z" fill="#f8fafc" />
                      <line x1="20" y1="18" x2="70" y2="58" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" />
                      <line x1="70" y1="18" x2="20" y2="58" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" />
                      <ellipse cx="22" cy="20" rx="9" ry="7" transform="rotate(-35 22 20)" fill="#ffffff" stroke="#94a3b8" stroke-width="1.3" />
                      <ellipse cx="68" cy="20" rx="9" ry="7" transform="rotate(35 68 20)" fill="#ffffff" stroke="#94a3b8" stroke-width="1.3" />
                      <path d="M38 34 L30 56 L38 52 L44 56 L42 34" fill="#cbd5e1" stroke="#94a3b8" stroke-width="1" stroke-linejoin="round" />
                      <path d="M52 34 L60 56 L52 52 L46 56 L48 34" fill="#94a3b8" stroke="#64748b" stroke-width="1" stroke-linejoin="round" />
                      <circle cx="45" cy="30" r="14" fill="#ffffff" stroke="#475569" stroke-width="1.8" />
                      <circle cx="45" cy="30" r="10" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1" />
                      <path d="M45 23 L47 27.5 L52 28 L48 31.5 L49.5 36.5 L45 34 L40.5 36.5 L42 31.5 L38 28 L43 27.5 Z" fill="#475569" />
                      <path d="M14 34 L16 38 L20 39 L16 40 L14 44 L12 40 L8 39 L12 38 Z" fill="#94a3b8" opacity="0.6" />
                      <path d="M74 24 L75.5 27 L78.5 28 L75.5 29 L74 32 L72.5 29 L69.5 28 L72.5 27 Z" fill="#94a3b8" opacity="0.6" />
                    </svg>

                    <!-- Bronze / Standard Tier Starter Gear Illustration -->
                    <svg v-else class="sg-tier-illus" viewBox="0 0 90 75" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <ellipse cx="45" cy="67" rx="38" ry="5" fill="#f1f5f9" />
                      <rect x="18" y="38" width="38" height="24" rx="7" fill="#334155" />
                      <path d="M22 38 C22 31 34 31 34 38" stroke="#15803d" stroke-width="2" fill="none" stroke-linecap="round" />
                      <rect x="18" y="46" width="38" height="3" fill="#15803d" />
                      <rect x="25" y="44" width="7" height="8" rx="2" fill="#475569" />
                      <rect x="10" y="44" width="7" height="18" rx="3" fill="#cbd5e1" />
                      <rect x="11.5" y="40" width="4" height="4" rx="1" fill="#15803d" />
                      <path d="M52 20 L76 58" stroke="#0f172a" stroke-width="2.5" stroke-linecap="round" />
                      <ellipse cx="46" cy="18" rx="14" ry="11" transform="rotate(-30 46 18)" fill="#ffffff" stroke="#0f172a" stroke-width="1.8" />
                      <line x1="38" y1="12" x2="52" y2="24" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="1 1" />
                      <line x1="42" y1="9" x2="56" y2="21" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="1 1" />
                      <line x1="38" y1="21" x2="50" y2="10" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="1 1" />
                      <path d="M60 52 L68 44 L72 50 Z" fill="#ffffff" stroke="#94a3b8" stroke-width="1" stroke-linejoin="round" />
                      <ellipse cx="59" cy="53" rx="2.5" ry="3" fill="#15803d" />
                    </svg>
                  </div>

                  <h3 class="sg-tier-name">{{ tier.label || tier.tier_label }}</h3>

                  <div class="sg-membership-tier-discount">
                    <span class="sg-discount-number">{{ formatPercent(tier.discount_percent) }}%</span>
                    <span class="sg-discount-unit">ưu đãi đặt sân</span>
                  </div>

                  <ul class="sg-membership-tier-benefits">
                    <li><AppIcon name="check" :size="14" class="sg-benefit-check" /> Từ {{ Number(tier.min_bookings || tier.min_completed_bookings || 0) }} lượt đặt hoàn tất</li>
                    <li><AppIcon name="check" :size="14" class="sg-benefit-check" /> Chi tiêu từ {{ formatCurrency(tier.min_spent_amount || tier.min_spend_amount) }}</li>
                    <li v-if="tier.has_voucher || tier.voucher_id || tier.voucher"><AppIcon name="ticket" :size="14" class="sg-benefit-check" /> Có voucher ưu đãi riêng</li>
                    <li v-if="tier.maintain_period_months"><AppIcon name="calendar" :size="14" class="sg-benefit-check" /> Duy trì trong {{ tier.maintain_period_months }} tháng</li>
                  </ul>
                </article>
              </div>

              <!-- User Progress Section (Flat minimalist) -->
              <section v-if="currentVenueMembership" class="sg-membership-progress">
                <div class="sg-membership-progress-head">
                  <div class="sg-membership-progress-titles">
                    <span class="sg-membership-eyebrow">TIẾN ĐỘ NÂNG HẠNG</span>
                    <h3 class="sg-progress-title">{{ currentVenueMembership.next_tier ? "Tiến tới hạng " + (currentVenueMembership.next_tier.label || currentVenueMembership.next_tier.tier_label) : "Bạn đang ở hạng cao nhất" }}</h3>
                  </div>
                  <span class="sg-progress-percent-val">{{ Number(currentVenueMembership.progress_percent || 0) }}%</span>
                </div>
                <div class="sg-membership-progress-track">
                  <span :style="{ width: Math.min(100, Math.max(0, Number(currentVenueMembership.progress_percent || 0))) + '%' }"></span>
                </div>
                <div v-if="currentVenueMembership.next_tier" class="sg-membership-progress-meta">
                  <span>Còn {{ Number(currentVenueMembership.remaining_bookings || 0) }} lượt đặt hoàn tất</span>
                  <span>Còn {{ formatCurrency(currentVenueMembership.remaining_spend_amount) }} chi tiêu</span>
                </div>
                <p v-else class="sg-membership-progress-note">Tiếp tục duy trì lịch đặt để giữ trọn quyền lợi hiện tại.</p>
              </section>
            </div>
          </template>

          <!-- TAB 3: SÂN & BẢNG GIÁ -->
          <template v-else-if="activeTab === 'courts'">
            <!-- Sơ đồ sân -->
            <div v-if="courtGroups.length" class="sg-detail-block">
              <div class="sg-court-heading-row">
                <div>
                  <h2 class="sg-section-title">Sơ đồ vị trí sân</h2>
                  <p class="sg-section-sub">Nhấp trực tiếp trên sơ đồ để xem vị trí sân con.</p>
                </div>
                <span class="sg-court-legend-dot">Sân đang hoạt động</span>
              </div>

              <div class="sg-court-canvas" :style="layoutCanvasStyle" aria-label="Sơ đồ trực quan các sân">
                <button
                  v-for="court in courtLayoutItems"
                  :key="court.id"
                  type="button"
                  class="sg-court-node-btn"
                  :style="courtLayoutStyle(court)"
                  @click="selectLayoutCourt(court)"
                >
                  <strong class="sg-court-node-name">{{ court.shortName }}</strong>
                  <span class="sg-court-node-type">{{ court.court_type?.name || "Sân thể thao" }}</span>
                </button>
                <div
                  v-for="decoration in layoutDecorationItems"
                  :key="decoration.id"
                  class="sg-court-deco-item"
                  :style="courtLayoutStyle(decoration)"
                >
                  <AppIcon :name="decorationIcon(decoration.type)" :size="15" />
                  <span>{{ decoration.name }}</span>
                </div>
              </div>

              <p v-if="selectedLayoutCourt" class="sg-court-selected-note">
                Đang xem: <strong>{{ selectedLayoutCourt.name }}</strong> ({{ selectedLayoutCourt.court_type?.name || "Sân thể thao" }})
              </p>

              <!-- Phân loại sân con -->
              <h3 class="sg-subsection-title" style="margin-top: 20px;">Danh sách loại sân con</h3>
              <div class="sg-court-groups-grid">
                <article v-for="group in courtGroups" :key="group.typeId" class="sg-court-group-card">
                  <div class="sg-court-group-header">
                    <span class="sg-court-group-type">{{ group.typeName }}</span>
                    <span class="sg-court-group-count">{{ group.courts.length }} sân</span>
                  </div>
                  <p class="sg-court-group-names">{{ group.courts.map((court) => court.name).join(", ") }}</p>
                </article>
              </div>
            </div>

            <!-- Bảng giá -->
            <div v-if="basePrices.length || priceSlots.length || holidayPrices.length" class="sg-detail-block">
              <h2 class="sg-section-title">Bảng giá thuê sân</h2>
              <div class="sg-price-table-flat">
                <article v-for="price in basePrices" :key="`base-${price.id}`" class="sg-price-row">
                  <span class="sg-price-type">{{ price.court_type?.name || "Tất cả loại sân" }} (Giờ thường)</span>
                  <span class="sg-price-val">{{ formatCurrency(price.price) }}/giờ</span>
                </article>
                <article v-for="slot in priceSlots" :key="`slot-${slot.id}`" class="sg-price-row">
                  <span class="sg-price-type">{{ slot.court_type?.name || "Tất cả loại sân" }} · {{ timeLabel(slot.start_time) }} - {{ timeLabel(slot.end_time) }}</span>
                  <span class="sg-price-val">{{ formatCurrency(slot.price) }}/giờ</span>
                </article>
                <article v-for="holiday in holidayPrices" :key="`holiday-${holiday.id}`" class="sg-price-row sg-price-holiday">
                  <span class="sg-price-type">Ngày {{ formatDate(holiday.holiday_date) }} · {{ holiday.court_type?.name || "Tất cả loại sân" }}</span>
                  <span class="sg-price-val">{{ formatCurrency(holiday.price) }}/giờ</span>
                </article>
              </div>
            </div>


            <!-- Chính sách sân -->
            <div class="sg-detail-block">
              <h2 class="sg-section-title">Chính sách &amp; Quy định</h2>
              <div class="sg-policy-grid">
                <article v-for="policy in policies" :key="policy.label" class="sg-policy-item">
                  <span class="sg-policy-lbl">{{ policy.label }}</span>
                  <span class="sg-policy-val">{{ policy.value }}</span>
                </article>
              </div>
              <div v-if="policyNotices.length" class="sg-policy-notices">
                <div class="sg-policy-notices-head">
                  <strong>Thông tin áp dụng</strong>
                  <span>{{ policyNoticeSourceLabel }}</span>
                </div>
                <article v-for="notice in policyNotices" :key="notice.id" class="sg-policy-notice">
                  <strong>{{ notice.title }}</strong>
                  <p>{{ notice.content }}</p>
                </article>
              </div>
            </div>
          </template>

          <!-- TAB 3: BÀI VIẾT -->
          <template v-else-if="activeTab === 'posts'">
            <VenuePostsTab
              :venue-id="venue.id"
              :venue-name="venue.name"
            />
          </template>

          <!-- TAB 4: ĐÁNH GIÁ -->
          <template v-else-if="activeTab === 'reviews'">
            <div class="sg-detail-block">
              <h2 class="sg-section-title">Đánh giá từ khách hàng</h2>
              <p v-if="reviews.length && reviewCount > reviews.length" class="sg-review-preview-note">
                Hiển thị {{ reviews.length }} đánh giá gần nhất trong tổng số {{ reviewCount }} lượt.
              </p>

              <div v-if="reviews.length" class="sg-reviews-flat-list">
                <article v-for="review in reviews" :key="review.id" class="sg-review-item-card">
                  <div class="sg-review-header-row">
                    <div class="sg-review-user">
                      <div class="sg-review-avatar">{{ initials(review.author_name || 'Khách hàng') }}</div>
                      <div class="sg-review-user-info">
                        <span class="sg-review-author-name">{{ review.author_name || "Khách hàng" }}</span>
                        <small v-if="review.created_at" class="sg-review-date">{{ formatDate(review.created_at) }}</small>
                      </div>
                    </div>
                    <div class="sg-review-stars-badge">
                      <span>{{ Number(review.rating || 0).toFixed(1) }} ★</span>
                    </div>
                  </div>
                  <p v-if="review.content" class="sg-review-comment">{{ review.content }}</p>
                  
                  <div v-if="review.reply_content" class="sg-review-reply-box">
                    <span class="sg-reply-label">Phản hồi từ chủ sân</span>
                    <p class="sg-reply-text">{{ review.reply_content }}</p>
                  </div>
                </article>
              </div>
              <p v-else class="sg-muted-text">Sân chưa có đánh giá công khai.</p>
            </div>
          </template>

          <!-- TAB 5: VỊ TRÍ & BẢN ĐỒ -->
          <template v-else-if="activeTab === 'location'">
            <div class="sg-detail-block">
              <h2 class="sg-section-title">Vị trí &amp; Chỉ đường</h2>
              <div class="sg-location-summary-bar">
                <div class="sg-loc-info">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <span>{{ fullAddress }}</span>
                </div>
                <div class="sg-loc-actions">
                  <a v-if="mapExternalUrl" :href="mapExternalUrl" target="_blank" rel="noopener noreferrer" class="sg-btn-link-action">
                    <span>Mở Google Maps</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
                  </a>
                  <a v-if="phoneUrl" :href="phoneUrl" class="sg-btn-link-action">
                    Gọi {{ venue.phone_contact }}
                  </a>
                </div>
              </div>

              <iframe
                v-if="mapEmbedUrl"
                class="sg-map-embed-iframe"
                :src="mapEmbedUrl"
                :title="`Bản đồ ${venue.name}`"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>

              <div v-if="!mapEmbedUrl && !mapExternalUrl" class="sg-empty-block">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <h3 class="sg-empty-title">Vị trí đang được cập nhật</h3>
                <p class="sg-empty-desc">Hãy nhắn tin với cụm sân để được hướng dẫn đường đi.</p>
              </div>
            </div>
          </template>
        </div>

        <!-- RIGHT SIDEBAR BOOKING PANEL -->
        <aside class="sg-booking-sidebar-col" ref="bookingPanelRef">
          <div class="sg-booking-sticky-card">
            <header class="sg-booking-header">
              <div class="sg-booking-title-row">
                <span class="sg-booking-card-title">Bảng giá & Đặt sân</span>
                <span class="sg-booking-price-amount">{{ priceLabel }}</span>
              </div>
              <p class="sg-booking-card-sub">Trang đặt sân riêng biệt với sơ đồ lịch sân trực quan theo từng khung giờ và ngày chơi.</p>
            </header>

            <div class="sg-booking-form-body">
              <!-- Primary CTA to dedicated booking workspace page -->
              <button
                id="btn-view-schedule"
                class="sg-btn-primary-cta"
                @click="goToBooking()"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Mở trang Đặt sân ngay</span>
              </button>

              <button
                class="sg-btn-secondary-action"
                @click="chatWithVenue"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                <span>Nhắn tin với cụm sân</span>
              </button>
            </div>

            <footer class="sg-booking-support-footer">
              <button type="button" class="sg-support-btn" @click="openComplaint">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>Khiếu nại sân</span>
              </button>
              <button type="button" class="sg-support-btn" @click="openReport">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Báo cáo sân</span>
              </button>
            </footer>
          </div>
        </aside>
      </section>
    </template>

    <!-- Complaint & Report Modals -->
    <ComplaintModal
      :is-open="showComplaintModal"
      initial-type="venue"
      :initial-venue-id="venue?.id || ''"
      :initial-venue-name="venue?.name || ''"
      @close="showComplaintModal = false"
      @success="onComplaintSuccess"
    />
    <ReportModal
      :is-open="showReportModal"
      target-type="venue"
      :target-id="venue?.id || ''"
      :target-name="venue?.name || ''"
      @close="showReportModal = false"
      @success="onReportSuccess"
    />
  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import AppIcon from "../../components/AppIcon.vue";
import ComplaintModal from "../../components/ComplaintModal.vue";
import ReportModal from "../../components/ReportModal.vue";
import VenuePostsTab from "../../components/VenuePostsTab.vue";
import { venueService } from "../../services/venues.js";
import { getAuth, restoreAuth } from "../../stores/auth.js";
import { useToast } from "vue-toastification";

export default {
  name: "VenueDetail",
  components: { PublicNavbar, AppIcon, ComplaintModal, ReportModal, VenuePostsTab },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      venue: null,
      loading: true,
      error: "",
      gallery: [],
      activeImage: "",
      activeTab: "overview",
      venueTabs: [
        { id: "overview", label: "Tổng quan & tiện ích" },
        { id: "courts", label: "Sân & bảng giá" },
        { id: "membership", label: "Hội viên sân" },
        { id: "posts", label: "Bài viết" },
        { id: "reviews", label: "Đánh giá" },
        { id: "location", label: "Vị trí & bản đồ" },
      ],
      bookDate: this.todayStr(),
      bookCourtType: '',
      previewSchedule: {
        time_slots: [],
        courts: [],
        slot_statuses: [],
      },
      previewLoading: false,
      previewError: "",
      venueRequestId: 0,
      scheduleRequestId: 0,
      selectedLayoutCourtId: null,
      showComplaintModal: false,
      showReportModal: false
    };
  },
  computed: {
    searchQuery() {
      const query = { ...this.$route.query };
      delete query.id;
      delete query.tab;
      return query;
    },
    amenities() {
      return this.venue?.amenities_detail?.length ? this.venue.amenities_detail : (this.venue?.amenities || []);
    },
    courtTypes() {
      return this.venue?.court_types || [];
    },
    courtTypeSummaryText() {
      const names = this.courtTypes.map((type) => type.name).filter(Boolean);
      return names.length ? names.join(" • ") : "Đa môn";
    },
    courtCount() {
      return Number(this.venue?.court_count || this.venue?.venue_courts?.length || 0);
    },
    fullAddress() {
      return [this.venue?.address, this.venue?.ward, this.venue?.province].filter(Boolean).join(", ") || "Đang cập nhật địa chỉ";
    },
    ratingLabel() {
      const rating = Number(this.venue?.rating_avg || 0);
      return rating > 0 ? `${rating.toFixed(1)} ★` : "Mới";
    },
    ratingCountLabel() {
      const count = Number(this.venue?.rating_count || 0);
      return count > 0 ? `${count.toLocaleString('vi-VN')} đánh giá` : 'Chưa có đánh giá';
    },
    priceLabel() {
      return this.venue?.min_price ? `Từ ${this.formatCurrency(this.venue.min_price)}/giờ` : "Liên hệ giá";
    },
    courtGroups() {
      const groups = {};
      (this.venue?.venue_courts || []).forEach((court) => {
        const typeId = court.court_type?.id || "other";
        const typeName = court.court_type?.name || "Khác";
        if (!groups[typeId]) groups[typeId] = { typeId, typeName, courts: [] };
        groups[typeId].courts.push(court);
      });
      return Object.values(groups);
    },
    courtLayoutItems() {
      return (this.venue?.venue_courts || []).map((court, index) => ({
        ...court,
        shortName: String(court.name || `Sân ${index + 1}`).replace(/^Sân\s+/i, ""),
        layoutIndex: index,
      }));
    },
    selectedLayoutCourt() {
      return this.courtLayoutItems.find((court) => court.id === this.selectedLayoutCourtId) || null;
    },
    layoutDecorationItems() {
      return Array.isArray(this.venue?.layout_decorations) ? this.venue.layout_decorations : [];
    },
    layoutBounds() {
      const items = [...this.courtLayoutItems, ...this.layoutDecorationItems];
      const placedItems = items.filter((item) => item.layout_x !== null && item.layout_x !== undefined && item.layout_y !== null && item.layout_y !== undefined);
      if (!placedItems.length) return { minX: 0, minY: 0, width: 1200, height: 700 };

      const minX = Math.min(...placedItems.map((item) => Number(item.layout_x) || 0));
      const minY = Math.min(...placedItems.map((item) => Number(item.layout_y) || 0));
      const maxX = Math.max(...placedItems.map((item) => (Number(item.layout_x) || 0) + Math.max(80, Number(item.layout_w) || 180)));
      const maxY = Math.max(...placedItems.map((item) => (Number(item.layout_y) || 0) + Math.max(60, Number(item.layout_h) || 110)));
      return { minX: minX - 80, minY: minY - 80, width: Math.max(900, maxX - minX + 160), height: Math.max(560, maxY - minY + 160) };
    },
    layoutCanvasStyle() {
      return { aspectRatio: `${this.layoutBounds.width} / ${this.layoutBounds.height}` };
    },
    groupedServices() {
      const services = this.venue?.services || [];
      const groups = {};
      services.forEach(item => {
        const catId = item.category_id || 'other';
        const catName = item.category?.name || 'Dịch vụ khác';
        if (!groups[catId]) groups[catId] = { key: catId, label: catName, items: [] };
        groups[catId].items.push(item);
      });
      return Object.values(groups);
    },
    membershipTiers() {
      const tiers = this.venue?.membership?.tiers || this.venue?.membership_tiers || [];
      return Array.isArray(tiers) ? tiers.filter((tier) => tier?.is_active !== false) : [];
    },
    membershipEnabled() {
      return Boolean(this.venue?.membership?.enabled && this.membershipTiers.length);
    },
    visibleVenueTabs() {
      return this.venueTabs.filter((tab) => tab.id !== 'membership' || this.membershipEnabled);
    },
    currentVenueMembership() {
      const memberships = getAuth()?.venue_memberships;
      const venueId = String(this.venue?.id || "");
      return Array.isArray(memberships)
        ? memberships.find((membership) => String(membership?.venue_cluster_id || "") === venueId) || null
        : null;
    },
    basePrices() { return this.venue?.base_prices || []; },
    priceSlots() { return this.venue?.price_slots || []; },
    holidayPrices() { return this.venue?.holiday_prices || []; },
    policyNotices() { return this.venue?.policies?.display_notices || []; },
    policyNoticeSourceLabel() {
      return this.venue?.policies?.display_notice_source_label || 'Theo chính sách hệ thống';
    },
    policies() {
      const policy = this.venue?.policies || {};
      const hours = this.venue?.operating_hours || {};
      const cancellationRefund = policy.cancellation_refund || {};
      return [
        { label: "Giờ mở cửa", value: hours.fixed_open_time && hours.fixed_close_time ? `${this.timeLabel(hours.fixed_open_time)} - ${this.timeLabel(hours.fixed_close_time)}` : "Theo lịch ngày" },
        { label: "Đặt trước", value: this.durationLabel(policy.min_advance_booking_minutes) },
        { label: "Hủy & hoàn tiền", value: cancellationRefund.effective_summary || (policy.cancel_before_hours != null ? `Trước ${policy.cancel_before_hours}h · ${Number(policy.refund_percent || 0)}%` : "Theo chính sách hệ thống") },
        { label: "Nguồn chính sách hủy", value: cancellationRefund.source_label || "Theo chính sách hệ thống" }
      ];
    },
    reviews() { return this.venue?.reviews || []; },
    reviewCount() { return Number(this.venue?.rating_count || this.reviews.length || 0); },
    minDate() { return this.todayStr(); },
    mapEmbedUrl() {
      if (this.venue?.map_url?.includes("google.com/maps/embed")) return this.venue.map_url;
      if (this.venue?.latitude && this.venue?.longitude) return `https://maps.google.com/maps?q=${this.venue.latitude},${this.venue.longitude}&z=15&output=embed`;
      return "";
    },
    mapExternalUrl() {
      if (this.venue?.latitude && this.venue?.longitude) return `https://www.google.com/maps/search/?api=1&query=${this.venue.latitude},${this.venue.longitude}`;
      return "";
    },
    phoneUrl() {
      const phone = String(this.venue?.phone_contact || '').replace(/[^\d+]/g, '');
      return phone ? `tel:${phone}` : '';
    },
    miniScheduleSlots() {
      const slots = this.previewSchedule.time_slots || [];
      const statuses = this.previewSchedule.slot_statuses || [];
      const courts = this.previewSchedule.courts || [];
      return slots.map(slot => {
        const available = statuses.filter(s => s.start_time === slot.start_time && s.is_available).length;
        return { ...slot, available_count: available, total_count: courts.length, is_past: this.isPreviewSlotPast(slot) };
      }).filter(slot => !slot.is_past).slice(0, 10);
    },
    previewAvailableCourtCount() {
      return this.miniScheduleSlots.reduce((acc, slot) => acc + slot.available_count, 0);
    },
    miniScheduleEmptyMessage() {
      return !(this.previewSchedule.time_slots || []).length ? 'Cụm sân không mở cửa ngày này.' : 'Không còn khung giờ trống.';
    }
  },
  async mounted() {
    this.activeTab = this.normalizeTab(this.$route.query.tab);
    await this.refreshAuthMemberships();
    this.fetchVenue();
  },
  watch: {
    '$route.params.id'(nextId, previousId) {
      if (nextId !== previousId) {
        this.venue = null;
        this.gallery = [];
        this.activeImage = '';
        this.fetchVenue();
      }
    },
    '$route.query.tab'(tab) {
      this.activeTab = this.normalizeTab(tab);
    },
    bookDate(value) {
      const normalized = this.normalizeBookingDate(value);
      if (value !== normalized) {
        this.bookDate = normalized;
        return;
      }
      if (this.venue) this.loadMiniSchedule();
    },
    bookCourtType() {
      if (this.venue) this.loadMiniSchedule();
    },
  },

  methods: {
    async refreshAuthMemberships() {
      if (!getAuth()) return;

      try {
        await restoreAuth();
      } catch {
        // Guest venue pages should remain usable even when auth refresh fails.
      }
    },
    normalizeBookingDate(value) {
      const today = this.todayStr();
      const candidate = String(value || today);
      if (!/^\d{4}-\d{2}-\d{2}$/.test(candidate) || candidate < today) return today;
      const date = new Date(`${candidate}T00:00:00`);
      if (Number.isNaN(date.getTime()) || date.toLocaleDateString('en-CA') !== candidate) return today;
      return candidate;
    },
    normalizeTab(tab) {
      const value = String(tab || 'overview');
      return this.venueTabs.some((item) => item.id === value && (item.id !== 'membership' || this.membershipEnabled)) ? value : 'overview';
    },

    setActiveTab(tab) {
      const nextTab = this.normalizeTab(tab);
      this.activeTab = nextTab;
      const query = { ...this.$route.query };
      if (nextTab === 'overview') delete query.tab;
      else query.tab = nextTab;
      this.$router.replace({ name: 'venue-detail', params: { id: this.$route.params.id }, query });
    },

    courtLayoutStyle(court) {
      const hasLayout = court.layout_x !== null && court.layout_x !== undefined
        && court.layout_y !== null && court.layout_y !== undefined;
      const fallbackIndex = Number(court.layoutIndex || 0);
      const fallbackColumn = fallbackIndex % 4;
      const fallbackRow = Math.floor(fallbackIndex / 4);
      const fallbackX = 80 + fallbackColumn * 220;
      const fallbackY = 80 + fallbackRow * 150;
      const left = ((hasLayout ? Number(court.layout_x) : fallbackX) - this.layoutBounds.minX) / this.layoutBounds.width * 100;
      const top = ((hasLayout ? Number(court.layout_y) : fallbackY) - this.layoutBounds.minY) / this.layoutBounds.height * 100;
      const width = Math.max(8, Number(court.layout_w || 180) / this.layoutBounds.width * 100);
      const height = Math.max(8, Number(court.layout_h || 110) / this.layoutBounds.height * 100);
      return {
        left: `${Math.min(94, Math.max(1, left))}%`,
        top: `${Math.min(90, Math.max(1, top))}%`,
        width: `${Math.min(45, Math.max(8, width))}%`,
        height: `${Math.min(40, Math.max(8, height))}%`,
        transform: `rotate(${Number(court.layout_rotation || 0)}deg)`,
      };
    },
    decorationIcon(type) {
      return { entrance: 'building', reception: 'building', restroom: 'settings', seating: 'users', parking: 'mapPin', custom: 'layers' }[type] || 'layers';
    },
    selectLayoutCourt(court) {
      this.selectedLayoutCourtId = court.id;
      this.bookCourtType = court.court_type_id || this.bookCourtType;
      this.$nextTick(() => this.scrollToBooking());
    },

    initials(name) {
      return String(name || "SG")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
    },

    formatCurrency(value) {
      const amount = Number(value);
      if (!Number.isFinite(amount)) return "Đang cập nhật";
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(amount);
    },

    formatPercent(value) {
      return Number(value || 0).toLocaleString("vi-VN", { maximumFractionDigits: 2 });
    },

    membershipTierIcon(tierKey) {
      return {
        standard: "shieldCheck",
        silver: "star",
        gold: "crown",
        diamond: "sparkles",
      }[tierKey] || "shieldCheck";
    },

    formatDate(value) {
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      }).format(date);
    },

    timeLabel(value) {
      return String(value || "").slice(0, 5) || "--:--";
    },

    shortTime(value) {
      return String(value || "").slice(0, 5) || "--:--";
    },

    isPreviewSlotPast(slot) {
      if (this.bookDate !== this.todayStr()) return false;
      const endTime = String(slot?.end_time || slot?.start_time || "");
      if (!endTime) return false;
      const end = new Date(`${this.bookDate}T${endTime}`);
      return !Number.isNaN(end.getTime()) && end.getTime() <= Date.now();
    },

    durationLabel(minutes) {
      const total = Number(minutes);
      if (!Number.isFinite(total) || total <= 0) return "Không yêu cầu";
      if (total % 1440 === 0) return `${total / 1440} ngày`;
      if (total % 60 === 0) return `${total / 60} giờ`;
      return `${total} phút`;
    },

    async fetchVenue() {
      const requestId = ++this.venueRequestId;
      this.scheduleRequestId += 1;
      this.loading = true;
      this.error = "";
      try {
        const id = this.$route.params.id;
        const res = await venueService.show(id);
        if (requestId !== this.venueRequestId) return;
        this.venue = res.data || res;
        this.activeTab = this.normalizeTab(this.$route.query.tab);

        // Build gallery
        const g = [
          this.venue.image_path,
          this.venue.cover_image,
          this.venue.thumbnail,
          ...(this.venue.gallery || []),
        ];
        this.gallery = [...new Set(g.map(path => this.imageUrl(path)).filter(Boolean))];
        this.activeImage = this.gallery[0] || null;
        this.loadMiniSchedule();
      } catch (err) {
        if (requestId !== this.venueRequestId) return;
        this.error = err.message || 'Không thể tải thông tin sân.';
      } finally {
        if (requestId === this.venueRequestId) this.loading = false;
      }
    },

    async loadMiniSchedule() {
      if (!this.venue?.id || !this.bookDate) return;

      const requestId = ++this.scheduleRequestId;
      this.previewLoading = true;
      this.previewError = "";

      try {
        const response = await venueService.schedule(this.venue.id, {
          booking_date: this.bookDate,
          court_type_id: this.bookCourtType || undefined,
        });
        if (requestId !== this.scheduleRequestId) return;

        const payload = response.data || response;
        this.previewSchedule = {
          time_slots: payload.time_slots || [],
          courts: payload.courts || [],
          slot_statuses: payload.slot_statuses || [],
        };
      } catch (error) {
        if (requestId !== this.scheduleRequestId) return;
        this.previewSchedule = {
          time_slots: [],
          courts: [],
          slot_statuses: [],
        };
        this.previewError =
          error?.message || "Không thể kiểm tra lịch trống lúc này.";
      } finally {
        if (requestId === this.scheduleRequestId) {
          this.previewLoading = false;
        }
      }
    },

    imageUrl(path) {
      if (!path) return null;
      if (path.startsWith('http')) return path;
      if (path.startsWith('/')) return path;
      return `/storage/${path}`;
    },

    removeGalleryImage(image) {
      this.gallery = this.gallery.filter((item) => item !== image);
      if (this.activeImage === image) this.activeImage = this.gallery[0] || '';
    },

    onImgError(e) {
      e.target.style.display = 'none';
    },

    todayStr() {
      const today = new Date();
      const year = today.getFullYear();
      const month = String(today.getMonth() + 1).padStart(2, '0');
      const day = String(today.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },

    formatPrice(val) {
      if (!val) return '';
      return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
    },

    formatDays(days) {
      if (!days || !days.length) return 'Tất cả';
      return days.map(d => DAY_NAMES[d] ?? d).join(', ');
    },

    scrollToBooking() {
      const el = this.$refs.bookingPanelRef;
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    async submitReport() {
      this.isSubmittingReport = true;
      try {
        const formData = new FormData();
        formData.append('target_type', 'venue');
        formData.append('target_id', this.venue.id);
        formData.append('reason', this.reportForm.reason);
        if (this.reportForm.content.trim()) {
            formData.append('description', this.reportForm.content);
        }

        const files = this.$refs.reportEvidence?.files;
        if (files && files.length > 0) {
          // The backend expects 'evidence_image' as a single file, so we just take the first one
          formData.append('evidence_image', files[0]);
        }

        const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token') || localStorage.getItem('token') || sessionStorage.getItem('token');
        const response = await fetch('/api/reports', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          },
          body: formData
        });

        if (!response.ok) {
            if (response.status === 401) {
                throw new Error('Vui lòng đăng nhập để gửi báo cáo.');
            }
            
            if (response.status === 422) {
                const data = await response.json();
                const firstError = Object.values(data.errors)[0][0];
                throw new Error(firstError || 'Thông tin không hợp lệ.');
            }

            throw new Error('Có lỗi xảy ra, vui lòng thử lại.');
        }

        this.toastMessage = 'Báo cáo của bạn đã được gửi thành công.';
        this.toastType = 'success';
        setTimeout(() => this.toastMessage = null, 3000);

        this.showReportModal = false;
        this.reportForm.content = '';
        if (this.$refs.reportEvidence) this.$refs.reportEvidence.value = '';
      } catch (err) {
        this.toastMessage = err.message;
        this.toastType = 'error';
        setTimeout(() => this.toastMessage = null, 3000);
      } finally {
        this.isSubmittingReport = false;
      }
    },

    goToBooking(slot = null) {
      if (!this.bookDate) return;
      const query = {
        venue_cluster_id: this.venue.id,
        cluster: this.venue.id,
        booking_date: this.bookDate,
        date: this.bookDate,
        start_time: this.$route.query.start_time || "18:00:00",
        end_time: this.$route.query.end_time || "19:00:00",
        return_to: this.$route.fullPath,
      };
      if (this.bookCourtType) query.court_type = this.bookCourtType;
      if (slot?.venue_court_id) query.venue_court_id = slot.venue_court_id;
      if (slot?.start_time) query.start_time = slot.start_time;
      if (slot?.end_time) query.end_time = slot.end_time;
      this.$router.push({ name: 'booking-create', query });
    },

    chatWithVenue() {
      if (!this.venue) return;
      this.$router.push({
        path: '/chat',
        query: { venue_id: this.venue.id }
      });
    },

    requirePlayer() {
      const auth = getAuth();
      if (!auth) {
        this.toast.info("Vui lòng đăng nhập để sử dụng chức năng hỗ trợ.");
        this.$router.push({ name: "login", query: { redirect: this.$route.fullPath } });
        return false;
      }
      if (auth.role_group !== "user") {
        this.toast.info("Chức năng này dành cho tài khoản người dùng.");
        return false;
      }
      return true;
    },

    openComplaint() {
      if (this.requirePlayer()) this.showComplaintModal = true;
    },

    openReport() {
      if (this.requirePlayer()) this.showReportModal = true;
    },

    onComplaintSuccess() {
      this.showComplaintModal = false;
      this.toast.success("Khiếu nại đã được ghi nhận. SportGo sẽ phản hồi sau khi kiểm tra.");
    },

    onReportSuccess() {
      this.showReportModal = false;
      this.toast.success("Báo cáo đã được ghi nhận để đội ngũ SportGo kiểm tra.");
    },
  },
};
</script>

<style scoped>
.venue-detail-page {
  background: #ffffff;
  min-height: 100vh;
  color: #0f172a;
  padding-bottom: 60px;
}

.sg-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 16px;
}

.state-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 50vh;
  gap: 12px;
}

.loading-desc-text {
  font-size: 13.5px;
  color: #64748b;
  font-weight: 400;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #15803d;
  border-radius: 50%;
  animation: sgSpin 0.7s linear infinite;
}

@keyframes sgSpin {
  to { transform: rotate(360deg); }
}

.error-msg {
  font-size: 14px;
  color: #ef4444;
  font-weight: 400;
}

/* HERO BAND STYLES */
.sg-hero-band {
  background: #ffffff;
  border-bottom: none;
  padding: 16px 0 24px;
}

.sg-hero-nav-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.sg-back-link {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #15803d;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.15s ease;
}

.sg-back-link:hover {
  color: #166534;
}

.sg-breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: #64748b;
  font-weight: 400;
}

.sg-breadcrumbs a {
  color: #64748b;
  text-decoration: none;
}

.sg-breadcrumbs a:hover {
  color: #15803d;
}

.sg-bc-sep {
  color: #cbd5e1;
}

.sg-bc-current {
  color: #0f172a;
  font-weight: 500;
}

.sg-hero-grid {
  display: grid;
  grid-template-columns: 460px 1fr;
  gap: 24px;
  align-items: start;
}

/* Gallery */
.sg-hero-gallery {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sg-gallery-main-stage {
  width: 100%;
  height: 280px;
  border-radius: 12px;
  overflow: hidden;
  background: #f1f5f9;
  position: relative;
}

.sg-main-stage-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sg-gallery-empty-stage {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 6px;
  color: #94a3b8;
}

.sg-empty-stage-name {
  font-size: 20px;
  font-weight: 500;
  color: #64748b;
}

.sg-empty-stage-desc {
  font-size: 12px;
}

.sg-gallery-thumbs-row {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.sg-gallery-thumbs-row::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.sg-gallery-thumb-btn {
  width: 68px;
  height: 48px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid transparent;
  padding: 0;
  background: #f1f5f9;
  cursor: pointer;
  flex-shrink: 0;
  opacity: 0.7;
  transition: opacity 0.15s ease, border-color 0.15s ease;
}

.sg-gallery-thumb-btn:hover,
.sg-gallery-thumb-btn.is-active {
  opacity: 1;
}

.sg-gallery-thumb-btn.is-active {
  border-color: #15803d;
}

.sg-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Info Column */
.sg-hero-info-col {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sg-hero-type-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #15803d;
}

.sg-sport-flat-text {
  color: #334155;
  font-weight: 400;
}

.sg-hero-venue-name {
  font-size: 24px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
  line-height: 1.3;
}

.sg-hero-address-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  color: #475569;
  font-weight: 400;
}

.sg-detail-meta-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 4px;
}

.sg-meta-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: #1e293b;
  line-height: 1.4;
  font-weight: 400;
}

.sg-meta-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.sg-meta-price-val {
  color: #15803d;
  font-weight: 500;
}

.sg-hero-cta-group {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}

.sg-btn-primary-action {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #15803d;
  color: #ffffff;
  font-size: 13px;
  font-weight: 500;
  padding: 10px 18px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
}

.sg-btn-primary-action:hover {
  background: #15803d;
}

.sg-btn-ghost-action {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  color: #334155;
  font-size: 13px;
  font-weight: 500;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.sg-btn-ghost-action:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

/* NAVIGATION TABS WRAPPER */
.sg-detail-tabs-wrapper {
  background: #ffffff;
  border-bottom: none;
  position: sticky;
  top: 0;
  z-index: 20;
}

.sg-detail-tabs-bar {
  display: flex;
  align-items: center;
  gap: 24px;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.sg-detail-tabs-bar::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.sg-detail-tab-btn {
  background: transparent;
  border: none;
  padding: 14px 0;
  font-size: 13.5px;
  font-weight: 400;
  color: #334155;
  cursor: pointer;
  position: relative;
  transition: color 0.15s ease;
  white-space: nowrap;
  flex-shrink: 0;
}

.sg-detail-tab-btn:focus,
.sg-detail-tab-btn:focus-visible {
  outline: none !important;
  box-shadow: none !important;
}

.sg-detail-tab-btn.is-active {
  color: #15803d;
  font-weight: 500;
}

.sg-detail-tab-btn.is-active::after {
  content: "";
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: #15803d;
  border-radius: 2px 2px 0 0;
}

.sg-tab-count-badge {
  display: inline-block;
  background: #f1f5f9;
  color: #475569;
  font-size: 11px;
  font-weight: 500;
  padding: 1px 6px;
  border-radius: 9999px;
  margin-left: 4px;
}

.sg-detail-tab-btn.is-active .sg-tab-count-badge {
  background: #dcfce7;
  color: #15803d;
}

/* MAIN LAYOUT 2 COLUMNS */
.sg-detail-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
  padding-top: 24px;
  padding-bottom: 40px;
}

.sg-detail-main-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sg-detail-block {
  background: #ffffff;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.sg-section-title {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 12px;
}

.sg-section-sub,
.sg-section-desc {
  font-size: 13.5px;
  line-height: 1.6;
  color: #1e293b;
  margin: 0;
  font-weight: 400;
}

.sg-subsection-title {
  font-size: 14.5px;
  font-weight: 500;
  color: #1e293b;
  margin: 0 0 10px;
}

/* Amenities Flat 2-Column Grid */
.sg-amenities-flat-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.sg-amenity-flat-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.sg-amenity-copy {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sg-amenity-name {
  font-size: 13px;
  font-weight: 400;
  color: #1e293b;
}

.sg-amenity-sub {
  font-size: 11.5px;
  color: #334155;
}

/* Services */
.sg-services-category-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sg-service-cat-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sg-service-cat-title {
  font-size: 13.5px;
  font-weight: 500;
  color: #15803d;
  margin: 0;
}

.sg-services-flat-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sg-service-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px dashed #f1f5f9;
}

.sg-service-copy {
  display: flex;
  flex-direction: column;
}

.sg-service-name {
  font-size: 13px;
  font-weight: 400;
  color: #334155;
}

.sg-service-desc {
  font-size: 11.5px;
  color: #334155;
}

.sg-service-price-wrap {
  display: flex;
  align-items: baseline;
  gap: 2px;
}

.sg-service-price {
  font-size: 13px;
  font-weight: 500;
  color: #15803d;
}

.sg-service-unit {
  font-size: 11.5px;
  color: #334155;
}

/* Court Canvas & Groups */
.sg-court-heading-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.sg-court-legend-dot {
  font-size: 12px;
  color: #15803d;
  display: flex;
  align-items: center;
  gap: 6px;
}

.sg-court-legend-dot::before {
  content: "";
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #15803d;
}

.sg-court-canvas {
  position: relative;
  width: 100%;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}

.sg-court-node-btn {
  position: absolute;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 4px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.sg-court-node-btn:hover {
  border-color: #15803d;
  box-shadow: 0 2px 8px rgba(21, 128, 61, 0.15);
}

.sg-court-node-name {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
}

.sg-court-node-type {
  font-size: 10.5px;
  color: #64748b;
}

.sg-court-deco-item {
  position: absolute;
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: #64748b;
}

.sg-court-selected-note {
  font-size: 12.5px;
  color: #334155;
  margin-top: 10px;
}

.sg-court-groups-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.sg-court-group-card {
  background: #f8fafc;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.sg-court-group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sg-court-group-type {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
}

.sg-court-group-count {
  font-size: 11.5px;
  color: #15803d;
}

.sg-court-group-names {
  font-size: 12px;
  color: #334155;
  margin: 4px 0 0;
}

/* Prices & Policies */
.sg-price-table-flat {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sg-price-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #f8fafc;
  border-radius: 6px;
  font-size: 13px;
}

.sg-price-type {
  color: #334155;
}

.sg-price-val {
  color: #15803d;
  font-weight: 500;
}

.sg-price-holiday {
  background: #fffbebf5;
}

.sg-price-holiday .sg-price-val {
  color: #d97706;
}

.sg-policy-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.sg-policy-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: #f8fafc;
  padding: 10px 12px;
  border-radius: 8px;
}

.sg-policy-lbl {
  font-size: 11.5px;
  color: #334155;
}

.sg-policy-val {
  font-size: 13px;
  color: #0f172a;
  font-weight: 400;
}

.sg-policy-notices {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #e2e8f0;
}

.sg-policy-notices-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
  color: #334155;
  font-size: 12px;
}

.sg-policy-notices-head span {
  color: #15803d;
  font-size: 11px;
}

.sg-policy-notice {
  padding: 10px 12px;
  background: #f8fafc;
  border-left: 3px solid #16a34a;
  border-radius: 6px;
}

.sg-policy-notice + .sg-policy-notice {
  margin-top: 8px;
}

.sg-policy-notice strong {
  display: block;
  color: #0f172a;
  font-size: 13px;
}

.sg-policy-notice p {
  margin: 4px 0 0;
  color: #475569;
  font-size: 12px;
  line-height: 1.55;
  white-space: pre-line;
}

/* Reviews List */
.sg-review-preview-note {
  font-size: 12px;
  color: #64748b;
  margin: 0 0 12px;
}

.sg-reviews-flat-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.sg-review-item-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.sg-review-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sg-review-user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sg-review-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #15803d;
  font-size: 12px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sg-review-user-info {
  display: flex;
  flex-direction: column;
}

.sg-review-author-name {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
}

.sg-review-date {
  font-size: 11px;
  color: #475569;
}

.sg-review-stars-badge {
  font-size: 12px;
  color: #d97706;
  font-weight: 500;
}

.sg-review-comment {
  font-size: 13px;
  color: #1e293b;
  line-height: 1.5;
  margin: 0;
}

.sg-review-reply-box {
  background: #f8fafc;
  padding: 8px 12px;
  border-radius: 6px;
  margin-top: 4px;
}

.sg-reply-label {
  font-size: 11.5px;
  font-weight: 500;
  color: #15803d;
}

.sg-reply-text {
  font-size: 12.5px;
  color: #475569;
  margin: 2px 0 0;
}

/* Location Embed */
.sg-location-summary-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.sg-loc-info {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  color: #334155;
}

.sg-loc-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sg-btn-link-action {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #15803d;
  font-size: 12.5px;
  font-weight: 500;
  text-decoration: none;
}

.sg-btn-link-action:hover {
  text-decoration: underline;
}

.sg-map-embed-iframe {
  width: 100%;
  height: 320px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.sg-empty-block {
  text-align: center;
  padding: 30px 0;
}

.sg-empty-title {
  font-size: 15px;
  font-weight: 500;
  color: #334155;
  margin: 8px 0 4px;
}

.sg-empty-desc {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
}

/* RIGHT SIDEBAR BOOKING WIDGET STYLES */
.sg-booking-sidebar-col {
  position: relative;
}

.sg-booking-sticky-card {
  position: sticky;
  top: 68px;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sg-booking-header {
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 12px;
}

.sg-booking-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sg-booking-card-title {
  font-size: 15px;
  font-weight: 500;
  color: #0f172a;
}

.sg-booking-price-amount {
  font-size: 14px;
  font-weight: 500;
  color: #15803d;
}

.sg-booking-card-sub {
  font-size: 12px;
  color: #334155;
  margin: 4px 0 0;
}

.sg-booking-form-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sg-form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sg-form-label {
  font-size: 12px;
  font-weight: 500;
  color: #1e293b;
}

.sg-form-input {
  width: 100%;
  padding: 8px 10px;
  font-size: 13px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  outline: none;
  transition: border-color 0.15s ease;
}

.sg-form-input:focus {
  border-color: #15803d;
}

/* Mini Schedule Slots Grid */
.sg-mini-schedule-box {
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 8px;
  padding: 10px;
  margin-top: 2px;
}

.sg-mini-schedule-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.sg-mini-title {
  font-size: 12px;
  font-weight: 500;
  color: #334155;
}

.sg-mini-count {
  font-size: 11px;
  color: #15803d;
  font-weight: 500;
}

.sg-mini-state-text {
  font-size: 12px;
  color: #64748b;
  text-align: center;
  padding: 10px 0;
}

.sg-error-text {
  color: #ef4444;
}

.sg-mini-slots-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px;

  max-height: 180px;
  overflow-y: auto;
  scrollbar-width: none;
}

.sg-mini-slots-grid::-webkit-scrollbar {
  display: none;
}

.sg-mini-slot-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 6px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.sg-mini-slot-btn:hover:not(:disabled) {
  border-color: #15803d;
  background: #f0fdf4;
}

.sg-mini-slot-btn.is-full,
.sg-mini-slot-btn.is-past {
  opacity: 0.6;
  cursor: not-allowed;
  background: #f1f5f9;
}

.sg-slot-time {
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
}

.sg-slot-sub {
  font-size: 10.5px;
  color: #64748b;
}

.sg-slot-avail {
  color: #15803d;
}

.sg-slot-full {
  color: #ef4444;
}

.sg-btn-primary-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 10px;
  background: #15803d;
  color: #ffffff;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  border: none;
  cursor: pointer;
}

.sg-btn-primary-cta:hover:not(:disabled) {
  background: #15803d;
}

.sg-btn-primary-cta:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.sg-btn-secondary-action {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 9px;
  background: #ffffff;
  color: #334155;
  font-size: 12.5px;
  font-weight: 500;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  cursor: pointer;
  transition: background 0.15s ease;
}

.sg-btn-secondary-action:hover {
  background: #f8fafc;
}

.sg-booking-support-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.sg-support-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  background: transparent;
  border: none;
  font-size: 11.5px;
  color: #64748b;
  cursor: pointer;
  padding: 0;
}

.sg-support-btn:hover {
  color: #ef4444;
}

/* VENUE MEMBERSHIP PROGRAM - MINIMALIST FLAT STYLING */
.sg-membership-detail {
  display: flex;
  flex-direction: column;
  gap: 28px;
  background: #ffffff !important;
}

.sg-membership-detail *,
.sg-membership-detail h2,
.sg-membership-detail h3,
.sg-membership-detail span,
.sg-membership-detail p,
.sg-membership-detail li {
  font-weight: 400 !important;
  background-image: none !important;
}

/* Membership Intro Banner */
.sg-membership-intro {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 16px 0 20px;
  background: transparent !important;
  border: none !important;
}

.sg-membership-intro-copy {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 620px;
}

.sg-membership-eyebrow {
  color: #15803d;
  font-size: 11px;
  letter-spacing: 0.08em;
}

.sg-membership-intro .sg-section-title {
  font-size: 22px;
  color: #0f172a;
  margin: 0;
  line-height: 1.3;
}

.sg-membership-intro p {
  color: #334155;
  font-size: 13.5px;
  line-height: 1.55;
  margin: 0;
}

.sg-membership-status-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-top: 6px;
  font-size: 13px;
}

.sg-membership-status-label {
  color: #64748b;
}

.sg-membership-status-val {
  color: #0f172a;
}

.sg-membership-intro-visual {
  flex-shrink: 0;
}

.sg-membership-illus {
  width: 170px;
  height: 110px;
  display: block;
}

/* Tier Grid (4 Columns, NO multi-color cards, NO icon wrapper boxes) */
.sg-membership-tier-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 24px;
}

.sg-membership-tier-col {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px 0;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
}

.sg-membership-tier-topline {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}

.sg-membership-tier-index {
  color: #64748b;
  font-size: 12px;
}

.sg-membership-current-label {
  color: #15803d;
  font-size: 12px;
}

.sg-tier-illustration-wrap {
  display: flex;
  align-items: center;
  margin: 4px 0 2px;
}

.sg-tier-illus {
  width: 86px;
  height: 72px;
  display: block;
}

.sg-tier-name {
  font-size: 18px;
  color: #0f172a;
  margin: 4px 0 0 0;
}

.sg-membership-tier-discount {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.sg-discount-number {
  font-size: 24px;
  color: #0f172a;
}

.sg-discount-unit {
  font-size: 12px;
  color: #475569;
}

.sg-membership-tier-benefits {
  list-style: none;
  margin: 4px 0 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sg-membership-tier-benefits li {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  color: #475569;
  font-size: 12.5px;
  line-height: 1.45;
}

.sg-benefit-check {
  color: #0f172a;
  flex-shrink: 0;
  margin-top: 2px;
}

/* User Progress Section */
.sg-membership-progress {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 0;
  background: transparent !important;
  border: none !important;
}

.sg-membership-progress-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.sg-membership-progress-titles {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.sg-progress-title {
  font-size: 16px;
  color: #0f172a;
  margin: 0;
}

.sg-progress-percent-val {
  font-size: 18px;
  color: #15803d;
}

.sg-membership-progress-track {
  height: 6px;
  overflow: hidden;
  border-radius: 999px;
  background: #e2e8f0;
}

.sg-membership-progress-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #15803d;
}

.sg-membership-progress-meta {
  display: flex;
  align-items: center;
  gap: 18px;
  color: #475569;
  font-size: 12.5px;
}

.sg-membership-progress-note {
  color: #475569;
  font-size: 12.5px;
  margin: 0;
}

@media (max-width: 860px) {
  .sg-membership-tier-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
  }

  .sg-membership-intro {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 540px) {
  .sg-membership-tier-grid {
    grid-template-columns: 1fr;
  }
}

/* RESPONSIVE BREAKPOINTS */
@media (max-width: 992px) {
  .sg-membership-tier-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .sg-hero-grid {
    grid-template-columns: 1fr;
  }

  .sg-detail-layout {
    grid-template-columns: 1fr;
  }

  .sg-booking-sticky-card {
    position: static;
  }
}

@media (max-width: 620px) {
  .sg-membership-intro {
    grid-template-columns: 1fr;
    padding: 18px;
  }

  .sg-membership-current-badge {
    text-align: left;
  }

  .sg-membership-tier-grid {
    grid-template-columns: 1fr;
  }

  .sg-membership-progress {
    padding: 16px;
  }

  .sg-membership-progress-meta {
    flex-direction: column;
    gap: 4px;
  }
}
</style>
