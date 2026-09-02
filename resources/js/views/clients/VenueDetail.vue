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

            <!-- Dụng cụ & Phụ kiện gợi ý (Tiếp thị liên kết) -->
            <div v-if="affiliateProducts.length" class="sg-detail-block">
              <div class="sg-section-title-row">
                <h2 class="sg-section-title">Dụng cụ &amp; Phụ kiện khuyên dùng</h2>
                <button type="button" class="sg-link-btn" @click="setActiveTab('products')">
                  Xem tất cả ({{ affiliateProducts.length }}) →
                </button>
              </div>
              <div class="sg-affiliate-grid sg-affiliate-grid--preview">
                <article v-for="prod in affiliateProducts.slice(0, 4)" :key="prod.id" class="sg-affiliate-card">
                  <div class="sg-affiliate-img-wrap">
                    <img :src="productImage(prod)" :alt="prod.name" class="sg-affiliate-img" />
                    <span v-if="prod.platform_name || prod.platform" class="sg-affiliate-platform-tag">{{ platformLabel(prod.platform_name || prod.platform) }}</span>
                  </div>
                  <div class="sg-affiliate-body">
                    <h3 class="sg-affiliate-name" :title="prod.name">{{ prod.name }}</h3>
                    <div class="sg-affiliate-price-row">
                      <span class="sg-affiliate-price">{{ formatCurrency(prod.price || prod.discount_price || prod.original_price) }}</span>
                      <span v-if="prod.original_price && (prod.price || prod.discount_price) && Number(prod.price || prod.discount_price) < Number(prod.original_price)" class="sg-affiliate-old-price">
                        {{ formatCurrency(prod.original_price) }}
                      </span>
                    </div>
                    <button type="button" class="sg-affiliate-buy-btn" @click="openAffiliateLink(prod)">
                      <span>Mua trên {{ platformLabel(prod.platform_name || prod.platform) }}</span>
                    </button>
                  </div>
                </article>
              </div>
            </div>

            <div v-if="!venue.description && !amenities.length && !groupedServices.length && !affiliateProducts.length" class="sg-detail-block sg-empty-block">
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

          <!-- TAB 2: SÂN & BẢNG GIÁ -->
          <template v-else-if="activeTab === 'courts'">
            <div class="sg-courts-pricing-section">
              <!-- Sơ đồ vị trí sân -->
              <div v-if="courtGroups.length" class="sg-courts-block">
                <div class="sg-court-heading-row">
                  <div>
                    <h2 class="sg-section-title">Sơ đồ vị trí sân</h2>
                    <p class="sg-section-sub">Nhấp trực tiếp trên sơ đồ để xem vị trí sân con tương ứng.</p>
                  </div>
                  <span class="sg-court-legend-dot">Sân đang hoạt động</span>
                </div>

                <div class="sg-court-canvas" :style="layoutCanvasStyle" aria-label="Sơ đồ trực quan các sân">
                  <button
                    v-for="court in courtLayoutItems"
                    :key="court.id"
                    type="button"
                    class="sg-court-node-btn"
                    :class="{ 'is-selected': selectedLayoutCourt?.id === court.id }"
                    :style="courtLayoutStyle(court)"
                    @click="selectLayoutCourt(court)"
                  >
                    <CourtVisual
                      :name="court.name || `Sân ${court.layoutIndex + 1}`"
                      :court-type-name="court.court_type?.name"
                      :image-url="imageUrl(court.image_url || court.custom_image_url || court.image)"
                      status="active"
                      :show-type="false"
                    />
                  </button>
                  <div
                    v-for="decoration in layoutDecorationItems"
                    :key="decoration.id"
                    class="sg-court-deco-item"
                    :style="courtLayoutStyle(decoration)"
                  >
                    <DecorationVisual
                      :type="decoration.type"
                      :name="decoration.name"
                      :rotation="decoration.layout_rotation || 0"
                    />
                  </div>
                </div>

                <p v-if="selectedLayoutCourt" class="sg-court-selected-note">
                  Đang chọn xem: <span class="sg-court-selected-highlight">{{ selectedLayoutCourt.name }}</span> ({{ selectedLayoutCourt.court_type?.name || "Sân thể thao" }})
                </p>
              </div>

              <!-- Phân loại loại sân & Danh sách sân con (Flat clean list) -->
              <div v-if="courtGroups.length" class="sg-courts-block">
                <h2 class="sg-section-title">Danh sách sân hoạt động</h2>
                <div class="sg-court-groups-flat">
                  <div v-for="group in courtGroups" :key="group.typeId" class="sg-court-group-row">
                    <div class="sg-court-group-info">
                      <div class="sg-court-group-title-line">
                        <span class="sg-court-group-type">{{ group.typeName }}</span>
                        <span class="sg-court-group-count">({{ group.courts.length }} sân)</span>
                      </div>
                      <p class="sg-court-group-subnames">Gồm: {{ group.courts.map((court) => court.name).join(" · ") }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bảng giá thuê sân -->
              <div v-if="pricingRows.length" class="sg-courts-block">
                <div class="sg-price-heading-row">
                  <div>
                    <h2 class="sg-section-title">Bảng giá thuê sân</h2>
                    <p class="sg-section-sub">Giá tham khảo theo từng ngày và khung giờ. Khi đặt sân, hệ thống sẽ áp dụng mức giá phù hợp nhất.</p>
                  </div>
                  <span class="sg-price-count">{{ pricingRows.length }} mức giá</span>
                </div>

                <div class="sg-price-table-wrap">
                  <table class="sg-price-table">
                    <thead>
                      <tr>
                        <th scope="col">Loại sân</th>
                        <th scope="col">Ngày áp dụng</th>
                        <th scope="col">Khung giờ</th>
                        <th scope="col" class="sg-price-cell-right">Giá thuê / giờ</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in pricingRows" :key="row.key" :class="`is-${row.source}`">
                        <td>
                          <strong class="sg-price-court-name">{{ row.courtName }}</strong>
                          <span v-if="row.isSpecial" class="sg-price-promo">Ngày đặc biệt</span>
                        </td>
                        <td>{{ row.daysLabel }}</td>
                        <td>{{ row.timeLabel }}</td>
                        <td class="sg-price-cell-right">
                          <strong class="sg-price-value">{{ formatCurrency(row.price) }}</strong>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <p class="sg-price-footnote">Giá cuối cùng có thể thay đổi theo ưu đãi hoặc hạng thành viên của bạn ở bước xác nhận đặt sân.</p>
              </div>

              <!-- Chính sách & Quy định -->
              <div class="sg-courts-block">
                <h2 class="sg-section-title">Chính sách &amp; Quy định sân</h2>
                <div class="sg-policy-grid-flat">
                  <article v-for="policy in policies" :key="policy.label" class="sg-policy-item-flat">
                    <span class="sg-policy-lbl">{{ policy.label }}</span>
                    <span class="sg-policy-val">{{ policy.value }}</span>
                  </article>
                </div>
                
                <div v-if="policyNotices.length" class="sg-policy-notices">
                  <div class="sg-policy-notices-head">
                    <strong>Thông tin áp dụng</strong>
                  </div>
                  <article v-for="notice in policyNotices" :key="notice.id" class="sg-policy-notice">
                    <strong>{{ notice.title }}</strong>
                    <p>{{ notice.content }}</p>
                  </article>
                </div>
              </div>
            </div>
          </template>

          <!-- TAB: CỬA HÀNG PHỤ KIỆN (TIẾP THỊ LIÊN KẾT) -->
          <template v-else-if="activeTab === 'products'">
            <div class="sg-affiliate-section">
              <div class="sg-affiliate-head">
                <h2 class="sg-section-title">Cửa hàng Dụng cụ &amp; Phụ kiện thể thao</h2>
                <p class="sg-section-desc">Sản phẩm chính hãng, phụ kiện thi đấu và trang thiết bị chất lượng do ban quản lý cụm sân chọn lọc và giới thiệu.</p>
              </div>

              <!-- Danh sách sản phẩm -->
              <div v-if="affiliateProducts.length" class="sg-affiliate-grid">
                <article v-for="prod in affiliateProducts" :key="prod.id" class="sg-affiliate-card">
                  <div class="sg-affiliate-img-wrap">
                    <img :src="productImage(prod)" :alt="prod.name" class="sg-affiliate-img" />
                    <span v-if="prod.platform_name || prod.platform" class="sg-affiliate-platform-tag">{{ platformLabel(prod.platform_name || prod.platform) }}</span>
                  </div>
                  <div class="sg-affiliate-body">
                    <h3 class="sg-affiliate-name" :title="prod.name">{{ prod.name }}</h3>
                    <div class="sg-affiliate-price-row">
                      <span class="sg-affiliate-price">{{ formatCurrency(prod.price || prod.discount_price || prod.original_price) }}</span>
                      <span v-if="prod.original_price && (prod.price || prod.discount_price) && Number(prod.price || prod.discount_price) < Number(prod.original_price)" class="sg-affiliate-old-price">
                        {{ formatCurrency(prod.original_price) }}
                      </span>
                    </div>
                    <button type="button" class="sg-affiliate-buy-btn" @click="openAffiliateLink(prod)">
                      <span>Mua trên {{ platformLabel(prod.platform_name || prod.platform) }}</span>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
                    </button>
                  </div>
                </article>
              </div>

              <!-- Trạng thái trống -->
              <div v-else class="sg-affiliate-empty">
                <svg width="160" height="120" viewBox="0 0 160 120" fill="none" class="sg-empty-illustration">
                  <circle cx="80" cy="60" r="50" fill="#f0fdf4" />
                  <rect x="52" y="44" width="56" height="42" rx="4" fill="#ffffff" stroke="#15803d" stroke-width="1.8" />
                  <path d="M68 44V36a12 12 0 0 1 24 0v8" stroke="#15803d" stroke-width="1.8" stroke-linecap="round" />
                  <circle cx="80" cy="65" r="5" stroke="#15803d" stroke-width="1.8" />
                  <line x1="80" y1="70" x2="80" y2="76" stroke="#15803d" stroke-width="1.8" stroke-linecap="round" />
                </svg>
                <h3 class="sg-empty-title">Chưa có sản phẩm phụ kiện nào</h3>
                <p class="sg-empty-desc">Cụm sân đang cập nhật các sản phẩm vợt, cầu và phụ kiện chính hãng để phục vụ người chơi.</p>
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
            <div class="sg-reviews-section">
              <!-- Reviews Summary Header (Flat, no card box, high contrast) -->
              <div class="sg-reviews-overview">
                <div class="sg-reviews-score-block">
                  <div class="sg-reviews-score-big">{{ reviewStats.avg }}</div>
                  <div class="sg-reviews-score-meta">
                    <div class="sg-reviews-stars-visual">
                      <span v-for="i in 5" :key="i" class="sg-star-char" :class="{ 'sg-star-filled': i <= Math.round(Number(reviewStats.avg)) }">★</span>
                    </div>
                    <span class="sg-reviews-total-text">Dựa trên {{ reviewStats.total }} đánh giá từ người chơi</span>
                  </div>
                </div>

                <!-- Rating Bars Breakdown -->
                <div class="sg-reviews-breakdown">
                  <div v-for="item in reviewStats.breakdown" :key="item.star" class="sg-rating-bar-row">
                    <span class="sg-rating-bar-star">{{ item.star }} sao</span>
                    <div class="sg-rating-bar-track">
                      <div class="sg-rating-bar-fill" :style="{ width: item.percent + '%' }"></div>
                    </div>
                    <span class="sg-rating-bar-count">{{ item.count }}</span>
                  </div>
                </div>
              </div>

              <!-- Reviews List -->
              <div v-if="filteredReviews.length" class="sg-reviews-list">
                <article v-for="review in filteredReviews" :key="review.id" class="sg-review-entry">
                  <div class="sg-review-entry-header">
                    <div class="sg-review-user-block">
                      <div class="sg-review-avatar-text">{{ initials(review.author_name || 'Khách hàng') }}</div>
                      <div class="sg-review-meta-text">
                        <span class="sg-review-name">{{ review.author_name || "Khách hàng SportGo" }}</span>
                        <span v-if="review.created_at" class="sg-review-time">{{ formatDate(review.created_at) }}</span>
                      </div>
                    </div>

                    <div class="sg-review-stars-line">
                      <span v-for="i in 5" :key="i" class="sg-star-mini" :class="{ 'sg-star-mini-active': i <= Number(review.rating || 5) }">★</span>
                    </div>
                  </div>

                  <p v-if="review.content" class="sg-review-body">{{ review.content }}</p>
                  
                  <!-- Owner Reply (Flat indented block) -->
                  <div v-if="review.reply_content" class="sg-review-owner-reply">
                    <div class="sg-reply-header">
                      <span class="sg-reply-author">Phản hồi từ Ban quản lý {{ venue.name }}</span>
                      <span v-if="review.replied_at" class="sg-reply-time">{{ formatDate(review.replied_at) }}</span>
                    </div>
                    <p class="sg-reply-body">{{ review.reply_content }}</p>
                  </div>
                </article>
              </div>

              <p v-else class="sg-empty-reviews-text">Chưa có đánh giá nào được ghi nhận.</p>
            </div>
          </template>

          <!-- TAB 5: VỊ TRÍ & BẢN ĐỒ -->
          <template v-else-if="activeTab === 'location'">
            <div class="sg-location-section">
              <!-- Location Action & Info Bar (Flat 3-column minimalist) -->
              <div class="sg-location-info-grid">
                <article class="sg-loc-card-flat">
                  <span class="sg-loc-card-label">ĐỊA CHỈ CỤM SÂN</span>
                  <p class="sg-loc-card-value">{{ fullAddress }}</p>
                  <button type="button" class="sg-loc-btn-action" @click="copyAddress">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    <span>Sao chép địa chỉ</span>
                  </button>
                </article>

                <article class="sg-loc-card-flat">
                  <span class="sg-loc-card-label">HƯỚNG DẪN DI CHUYỂN &amp; GỬI XE</span>
                  <p class="sg-loc-card-value">Giao thông thông thoáng, có bãi gửi xe máy miễn phí &amp; chỗ đỗ ô tô có bảo vệ trông giữ.</p>
                  <a v-if="mapExternalUrl" :href="mapExternalUrl" target="_blank" rel="noopener noreferrer" class="sg-loc-btn-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
                    <span>Mở chỉ đường trên Google Maps</span>
                  </a>
                </article>

                <article class="sg-loc-card-flat">
                  <span class="sg-loc-card-label">LIÊN HỆ TRỰC TIẾP</span>
                  <p class="sg-loc-card-value">Hotline hỗ trợ: {{ venue.phone_contact || "Đang cập nhật" }}</p>
                  <a v-if="phoneUrl" :href="phoneUrl" class="sg-loc-btn-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                    <span>Gọi điện cho cụm sân</span>
                  </a>
                </article>
              </div>

              <!-- Interactive Map Container -->
              <div class="sg-location-map-wrapper">
                <div ref="venueMapContainer" class="sg-venue-map-canvas"></div>
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
              <div v-if="bookingBlocked" class="sg-booking-access-alert" role="alert">
                <strong>{{ bookingAccess.title || 'Cụm sân đang bị khóa' }}</strong>
                <span>{{ bookingAccess.message || 'Cụm sân hiện không nhận booking mới.' }}</span>
              </div>

              <!-- Primary CTA to dedicated booking workspace page -->
              <button
                id="btn-view-schedule"
                class="sg-btn-primary-cta"
                @click="goToBooking()"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>{{ bookingBlocked ? 'Xem trạng thái đặt sân' : 'Mở trang Đặt sân ngay' }}</span>
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
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import PublicNavbar from "../../components/PublicNavbar.vue";
import AppIcon from "../../components/AppIcon.vue";
import CourtVisual from "../../components/CourtVisual.vue";
import DecorationVisual from "../../components/DecorationVisual.vue";
import ComplaintModal from "../../components/ComplaintModal.vue";
import ReportModal from "../../components/ReportModal.vue";
import VenuePostsTab from "../../components/VenuePostsTab.vue";
import { venueService } from "../../services/venues.js";
import { chatService } from "../../services/chat.service.js";
import { affiliateProductService } from "../../services/affiliateProducts.js";
import { getAuth, restoreAuth } from "../../stores/auth.js";
import { BUSINESS_TIMEZONE, businessDateLabel, businessDateString, businessDateTime } from "../../utils/businessTime.js";
import { useToast } from "vue-toastification";

export default {
  name: "VenueDetail",
  components: { PublicNavbar, AppIcon, CourtVisual, DecorationVisual, ComplaintModal, ReportModal, VenuePostsTab },
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
      venueLeafletMap: null,
      activeTab: "overview",
      venueTabs: [
        { id: "overview", label: "Tổng quan & tiện ích" },
        { id: "courts", label: "Sân & bảng giá" },
        { id: "products", label: "Cửa hàng phụ kiện" },
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
    affiliateProducts() {
      return this.venue?.affiliate_products || [];
    },
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
      return {};
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
    pricingRows() {
      const baseRows = this.basePrices.map((price) => ({
        key: `base-${price.id}`,
        source: 'base',
        courtName: price.court_type?.name || 'Tất cả loại sân',
        daysLabel: 'Mọi ngày',
        timeLabel: 'Mọi khung giờ',
        price: Number(price.price || 0),
        isSpecial: false,
      }));

      const weeklyRows = this.priceSlots.map((slot) => ({
        key: `slot-${slot.id}`,
        source: 'weekly',
        courtName: slot.court_type?.name || 'Tất cả loại sân',
        daysLabel: this.daysLabel(slot.apply_to_days),
        timeLabel: `${this.timeLabel(slot.start_time)} - ${this.timeLabel(slot.end_time)}`,
        price: Number(slot.price || 0),
        isSpecial: false,
      }));

      const specialRows = this.holidayPrices.map((holiday) => ({
        key: `holiday-${holiday.id}`,
        source: 'special',
        courtName: holiday.court_type?.name || 'Tất cả loại sân',
        daysLabel: `${holiday.date_type === 'special_date' ? 'Ngày đặc biệt' : 'Ngày lễ'} ${this.formatDate(holiday.holiday_date)}`,
        timeLabel: `${this.timeLabel(holiday.start_time)} - ${this.timeLabel(holiday.end_time)}`,
        price: Number(holiday.price || 0),
        isSpecial: true,
      }));

      return [...baseRows, ...weeklyRows, ...specialRows];
    },
    policyNotices() { return this.venue?.policies?.display_notices || []; },
    policies() {
      const policy = this.venue?.policies || {};
      const hours = this.venue?.operating_hours || {};
      const cancellationRefund = policy.cancellation_refund || {};
      return [
        { label: "Giờ mở cửa", value: hours.fixed_open_time && hours.fixed_close_time ? `${this.timeLabel(hours.fixed_open_time)} - ${this.timeLabel(hours.fixed_close_time)}` : "Theo lịch ngày" },
        { label: "Đặt trước", value: this.durationLabel(policy.min_advance_booking_minutes) },
        { label: "Hủy & hoàn tiền", value: cancellationRefund.effective_summary || (policy.cancel_before_hours != null ? `Trước ${policy.cancel_before_hours}h · ${Number(policy.refund_percent || 0)}%` : "Theo chính sách hệ thống") },
      ];
    },
    reviews() { return this.venue?.reviews || []; },
    reviewCount() { return Number(this.venue?.rating_count || this.reviews.length || 0); },
    reviewStats() {
      const list = this.reviews || [];
      const total = Number(this.venue?.rating_count || list.length || 0);
      const avg = Number(this.venue?.rating_avg || 0) > 0 
        ? Number(this.venue.rating_avg).toFixed(1)
        : (list.length ? (list.reduce((sum, r) => sum + Number(r.rating || 5), 0) / list.length).toFixed(1) : '5.0');
      
      const counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
      list.forEach((r) => {
        const star = Math.min(5, Math.max(1, Math.round(Number(r.rating || 5))));
        counts[star] = (counts[star] || 0) + 1;
      });
      
      const baseTotal = list.length || 1;
      const breakdown = [5, 4, 3, 2, 1].map((star) => ({
        star,
        count: counts[star] || 0,
        percent: Math.round(((counts[star] || 0) / baseTotal) * 100),
      }));

      return { avg, total: total || list.length, breakdown };
    },
    filteredReviews() {
      return this.reviews || [];
    },
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
      if (this.bookingBlocked) return this.bookingAccess.message || 'Cụm sân hiện không nhận booking mới.';
      return !(this.previewSchedule.time_slots || []).length ? 'Cụm sân không mở cửa ngày này.' : 'Không còn khung giờ trống.';
    },
    bookingAccess() {
      return this.venue?.booking_access || { can_book: true, title: '', message: '' };
    },
    bookingBlocked() {
      return this.bookingAccess?.can_book === false;
    }
  },
  async mounted() {
    this.activeTab = this.normalizeTab(this.$route.query.tab);
    await this.refreshAuthMemberships();
    await this.fetchVenue();
    if (this.activeTab === 'location') {
      this.$nextTick(() => {
        this.initVenueLocationMap();
      });
    }
  },
  beforeUnmount() {
    if (this.venueLeafletMap) {
      this.venueLeafletMap.remove();
      this.venueLeafletMap = null;
    }
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
      if (this.activeTab === 'location') {
        this.$nextTick(() => {
          this.initVenueLocationMap();
        });
      }
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
      const date = businessDateTime(candidate, '00:00');
      if (Number.isNaN(date.getTime()) || businessDateString(date) !== candidate) return today;
      return candidate;
    },
    normalizeTab(tab) {
      const value = String(tab || 'overview');
      return this.venueTabs.some((item) => item.id === value && (item.id !== 'membership' || this.membershipEnabled)) ? value : 'overview';
    },

    setActiveTab(tab) {
      const nextTab = this.normalizeTab(tab);
      this.activeTab = nextTab;
      if (nextTab === 'location') {
        this.$nextTick(() => {
          this.initVenueLocationMap();
        });
      }
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

    platformLabel(platform) {
      const map = {
        shopee: "Shopee",
        tiktok: "TikTok Shop",
        lazada: "Lazada",
        tiki: "Tiki",
        other: "Gian hàng",
      };
      return map[String(platform).toLowerCase()] || "Shopee";
    },

    productImage(prod) {
      const img = prod?.image_path || prod?.image;
      if (!img) return "/images/home/badminton-cover.webp";
      if (/^https?:\/\//i.test(img)) return img;
      return img.startsWith("/") ? img : `/storage/${img}`;
    },

    async openAffiliateLink(prod) {
      if (!prod?.affiliate_url) return;
      try {
        if (prod.id) {
          affiliateProductService.trackClick(prod.id).catch(() => {});
        }
      } catch (e) {
        // ignore tracking error
      }
      window.open(prod.affiliate_url, "_blank", "noopener,noreferrer");
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
      if (!value) return '';
      const raw = String(value);
      if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return businessDateLabel(raw);
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: BUSINESS_TIMEZONE,
      }).format(date);
    },

    timeLabel(value) {
      return String(value || "").slice(0, 5) || "--:--";
    },

    daysLabel(days) {
      let values = days;
      if (typeof values === 'string') {
        try { values = JSON.parse(values); } catch { values = []; }
      }
      const labels = {
        1: 'T2',
        2: 'T3',
        3: 'T4',
        4: 'T5',
        5: 'T6',
        6: 'T7',
        7: 'CN',
        0: 'CN',
      };
      const normalized = [...new Set((Array.isArray(values) ? values : [])
        .map((day) => Number(day))
        .filter((day) => Number.isInteger(day) && day >= 0 && day <= 7)
        .map((day) => (day === 0 ? 7 : day))
        .sort((a, b) => a - b)
        .map((day) => labels[day]))];
      return normalized.length ? normalized.join(', ') : 'Mọi ngày';
    },

    bookingTypeLabel(type) {
      return {
        all: 'Mọi loại đặt',
        single: 'Đặt lẻ',
        recurring: 'Đặt định kỳ',
      }[String(type || 'all')] || 'Mọi loại đặt';
    },

    shortTime(value) {
      return String(value || "").slice(0, 5) || "--:--";
    },

    isPreviewSlotPast(slot) {
      if (this.bookDate !== this.todayStr()) return false;
      const endTime = String(slot?.end_time || slot?.start_time || "");
      if (!endTime) return false;
      const end = businessDateTime(this.bookDate, endTime);
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
        if (!this.gallery.length) {
          this.gallery = [this.fallbackVenueImage(this.venue.id)];
        }
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
        if (payload.booking_access) {
          this.venue.booking_access = payload.booking_access;
        }
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
      const value = String(path).trim();
      if (/^(https?:|data:|blob:)/i.test(value)) return value;
      if (value.startsWith('/')) return value;
      if (value.startsWith('storage/')) return `/${value}`;
      if (value.startsWith('public/')) return `/storage/${value.slice(7)}`;
      return `/storage/${value}`;
    },

    fallbackVenueImage(venueId = 0) {
      const pool = [
        "/images/home/badminton-cover.webp",
        "/images/home/anhbia2.webp",
        "/images/home/sportgo-home-hero-v2.webp",
        "/images/about_hero.png",
      ];
      return pool[Math.abs(Number(venueId) || 0) % pool.length];
    },

    removeGalleryImage(image) {
      this.gallery = this.gallery.filter((item) => item !== image);
      if (!this.gallery.length && !String(image || '').startsWith('/images/')) {
        this.gallery = [this.fallbackVenueImage(this.venue?.id)];
      }
      if (this.activeImage === image) this.activeImage = this.gallery[0] || '';
    },

    onImgError(e) {
      e.target.style.display = 'none';
    },

    todayStr() {
      return businessDateString();
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
      if (this.bookingBlocked) {
        this.toast.error(this.bookingAccess.message || 'Cụm sân hiện không nhận booking mới.');
        return;
      }
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

    async chatWithVenue() {
      if (!this.venue) return;
      if (!this.requirePlayer()) return;

      try {
        const res = await chatService.startConversation({
          type: "venue_contact",
          venue_id: this.venue.id,
          venue_cluster_id: this.venue.id,
        });
        if (res && res.id) {
          this.$router.push({
            path: "/chat",
            query: { conversation_id: res.id, venue_id: this.venue.id },
          });
          return;
        }
      } catch (err) {
        console.warn("Chuyển tới trang chat theo tham số venue_id", err);
      }

      this.$router.push({
        path: "/chat",
        query: { venue_id: this.venue.id, venueId: this.venue.id },
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

    initVenueLocationMap() {
      if (!this.$refs.venueMapContainer) return;
      if (this.venueLeafletMap) {
        this.venueLeafletMap.invalidateSize();
        return;
      }

      const lat = parseFloat(this.venue?.latitude) || 21.036236;
      const lng = parseFloat(this.venue?.longitude) || 105.790583;

      try {
        const map = L.map(this.$refs.venueMapContainer, {
          center: [lat, lng],
          zoom: 15,
          minZoom: 4,
          zoomControl: true,
          scrollWheelZoom: false,
          maxBounds: [[-90, -180], [90, 180]],
        });

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          minZoom: 4,
          maxZoom: 19,
          noWrap: true,
          attribution: '© OpenStreetMap contributors',
        }).addTo(map);

        const customIcon = L.divIcon({
          className: 'sg-venue-map-custom-marker',
          html: `<div class="sg-map-marker-pin"><svg width="32" height="32" viewBox="0 0 24 24" fill="#15803d" stroke="#ffffff" stroke-width="1.5"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5" fill="#ffffff"/></svg></div>`,
          iconSize: [32, 32],
          iconAnchor: [16, 32],
          popupAnchor: [0, -32],
        });

        const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
        marker.bindPopup(`
          <div class="sg-map-popup-content">
            <div class="sg-map-popup-title">${this.venue?.name || 'Cụm sân'}</div>
            <div class="sg-map-popup-address">${this.fullAddress}</div>
          </div>
        `).openPopup();

        this.venueLeafletMap = map;
      } catch (err) {
        console.warn("Could not init Leaflet map:", err);
      }
    },

    async copyAddress() {
      if (!this.fullAddress) return;
      try {
        await navigator.clipboard.writeText(this.fullAddress);
        this.toast.success("Đã sao chép địa chỉ cụm sân!");
      } catch {
        this.toast.info(`Địa chỉ: ${this.fullAddress}`);
      }
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
  background: #54656f;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 600;
  padding: 10px 20px;
  border-radius: 999px;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(84, 101, 111, 0.25);
  transition: all 0.15s ease;
}

.sg-btn-primary-action:hover {
  background: #405059;
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(84, 101, 111, 0.35);
}

.sg-btn-ghost-action {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  color: #475569;
  font-size: 13px;
  font-weight: 600;
  padding: 10px 16px;
  border-radius: 999px;
  border: 1.5px solid #cbd5e1;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sg-btn-ghost-action:hover {
  background: #f8fafc;
  border-color: #54656f;
  color: #0f172a;
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
  font-weight: 500;
  color: #475569;
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
  color: #5c7e6e;
  font-weight: 600;
}

.sg-detail-tab-btn.is-active::after {
  content: "";
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: #5c7e6e;
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
  background: #edf4f0;
  color: #5c7e6e;
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
  border-radius: 0;
  padding: 0;
  border: none;
  box-shadow: none;
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
  border-bottom: none;
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

/* COURTS & PRICING SECTION - MINIMALIST FLAT STYLING */
.sg-courts-pricing-section {
  display: flex;
  flex-direction: column;
  gap: 32px;
  background: #ffffff !important;
}

.sg-courts-pricing-section *,
.sg-courts-pricing-section span,
.sg-courts-pricing-section p,
.sg-courts-pricing-section strong {
  font-weight: 400 !important;
  background-image: none !important;
}

.sg-courts-block {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Court Canvas */
.sg-court-heading-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.sg-court-legend-dot {
  font-size: 12.5px;
  color: #15803d;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.sg-court-legend-dot::before {
  content: "";
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #15803d;
}

.sg-court-canvas {
  position: relative;
  width: 100%;
  height: clamp(300px, 36vw, 420px);
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  background-image: radial-gradient(#cbd5e1 0.8px, transparent 0.8px);
  background-size: 18px 18px;
}

.sg-court-node-btn {
  position: absolute;
  display: block;
  padding: 0;
  border: 0;
  border-radius: 10px;
  background: transparent;
  overflow: visible;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sg-court-node-btn:hover,
.sg-court-node-btn.is-selected {
  z-index: 20;
}

.sg-court-node-btn:hover,
.sg-court-node-btn.is-selected {
  filter: drop-shadow(0 0 3px #15803d) drop-shadow(0 8px 8px rgba(15, 23, 42, 0.18));
}

.sg-court-deco-item {
  position: absolute;
  padding: 0;
  overflow: visible;
}

.sg-court-selected-note {
  font-size: 12.5px;
  color: #475569;
  margin: 0;
}

.sg-court-selected-highlight {
  color: #15803d;
}

/* Court Groups Flat */
.sg-court-groups-flat {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sg-court-group-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px 0;
  border-bottom: none;
}

.sg-court-group-title-line {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.sg-court-group-type {
  font-size: 14px;
  color: #0f172a;
}

.sg-court-group-count {
  font-size: 12px;
  color: #15803d;
}

.sg-court-group-subnames {
  font-size: 12.5px;
  color: #475569;
  margin: 0;
}

/* Compact pricing table */
.sg-price-heading-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.sg-price-heading-row .sg-section-sub {
  max-width: 760px;
  margin: 4px 0 0;
}

.sg-price-count {
  flex-shrink: 0;
  padding: 4px 8px;
  border: 1px solid #dbe5df;
  border-radius: 999px;
  color: #166534;
  background: #f0fdf4;
  font-size: 11px;
  font-weight: 600 !important;
  white-space: nowrap;
}

.sg-price-table-wrap {
  width: 100%;
  overflow-x: auto;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
}

.sg-price-table {
  width: 100%;
  min-width: 620px;
  border-collapse: collapse;
  table-layout: fixed;
  color: #334155;
  font-size: 12px;
}

.sg-price-table th,
.sg-price-table td {
  padding: 7px 9px;
  border: 1px solid #dbe3ea;
  text-align: left;
  vertical-align: middle;
  line-height: 1.35;
}

.sg-price-table th {
  background: #f1f5f9 !important;
  color: #475569;
  font-size: 11px;
  font-weight: 700 !important;
  letter-spacing: 0.01em;
  white-space: nowrap;
}

.sg-price-table tbody tr:nth-child(even) td {
  background: #fbfdff !important;
}

.sg-price-table tbody tr:hover td {
  background: #f0fdf4 !important;
}

.sg-price-table th:nth-child(1),
.sg-price-table td:nth-child(1) { width: 30%; }

.sg-price-table th:nth-child(2),
.sg-price-table td:nth-child(2) { width: 27%; }

.sg-price-table th:nth-child(3),
.sg-price-table td:nth-child(3) { width: 25%; }

.sg-price-table th:nth-child(4),
.sg-price-table td:nth-child(4) { width: 18%; }

.sg-price-court-name,
.sg-price-promo {
  display: block;
}

.sg-price-court-name {
  color: #0f172a;
  font-weight: 650 !important;
}

.sg-price-cell-right {
  text-align: right !important;
}

.sg-price-value {
  color: #15803d;
  font-size: 12.5px;
  font-weight: 700 !important;
  white-space: nowrap;
}

.sg-price-promo {
  margin-top: 2px;
  color: #b45309;
  font-size: 10.5px;
}

.sg-price-footnote {
  margin: -3px 0 0;
  color: #64748b;
  font-size: 11.5px;
}

/* Policy Flat Grid */
.sg-policy-grid-flat {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px 28px;
  padding-bottom: 8px;
}

.sg-policy-item-flat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-bottom: 8px;
  border-bottom: none;
}

.sg-policy-lbl {
  font-size: 11.5px;
  color: #64748b;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.sg-policy-val {
  font-size: 13.5px;
  color: #0f172a;
  line-height: 1.5;
}

.sg-policy-notices {
  margin-top: 14px;
  padding-top: 0;
  border-top: none;
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
  padding: 10px 14px;
  background: #f8fafc;
  border-left: 2px solid #15803d;
  border-radius: 0 6px 6px 0;
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

@media (max-width: 640px) {
  .sg-price-heading-row {
    align-items: flex-start;
    flex-direction: column;
    gap: 6px;
  }

  .sg-policy-grid-flat {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}

/* REVIEWS SECTION - MINIMALIST FLAT STYLING */
.sg-reviews-section {
  display: flex;
  flex-direction: column;
  gap: 28px;
  background: #ffffff !important;
}

.sg-reviews-section *,
.sg-reviews-section h2,
.sg-reviews-section span,
.sg-reviews-section p {
  font-weight: 400 !important;
  background-image: none !important;
}

/* Reviews Overview */
.sg-reviews-overview {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 36px;
  align-items: center;
  padding: 8px 0 16px;
  border-bottom: none;
}

.sg-reviews-score-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sg-reviews-score-big {
  font-size: 42px;
  color: #0f172a;
  line-height: 1;
}

.sg-reviews-score-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sg-reviews-stars-visual {
  display: flex;
  gap: 2px;
}

.sg-star-char {
  font-size: 18px;
  color: #e2e8f0;
}

.sg-star-char.sg-star-filled {
  color: #15803d;
}

.sg-reviews-total-text {
  font-size: 12.5px;
  color: #475569;
}

/* Rating breakdown */
.sg-reviews-breakdown {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 380px;
}

.sg-rating-bar-row {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
}

.sg-rating-bar-star {
  width: 40px;
  color: #475569;
  flex-shrink: 0;
}

.sg-rating-bar-track {
  flex: 1;
  height: 6px;
  background: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
}

.sg-rating-bar-fill {
  height: 100%;
  background: #15803d;
  border-radius: 999px;
  transition: width 0.3s ease;
}

.sg-rating-bar-count {
  width: 24px;
  text-align: right;
  color: #64748b;
  flex-shrink: 0;
}

/* Reviews List */
.sg-reviews-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sg-review-entry {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 16px;
  border-bottom: none;
}

.sg-review-entry-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sg-review-user-block {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sg-review-avatar-text {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #15803d;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12.5px;
  flex-shrink: 0;
}

.sg-review-meta-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sg-review-name {
  font-size: 13.5px;
  color: #0f172a;
}

.sg-review-time {
  font-size: 11.5px;
  color: #64748b;
}

.sg-review-stars-line {
  display: flex;
  gap: 2px;
}

.sg-star-mini {
  font-size: 14px;
  color: #e2e8f0;
}

.sg-star-mini.sg-star-mini-active {
  color: #15803d;
}

.sg-review-body {
  font-size: 13.5px;
  color: #334155;
  line-height: 1.6;
  margin: 0;
}

/* Owner Reply */
.sg-review-owner-reply {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 14px;
  background: #f8fafc;
  border-left: 2px solid #15803d;
  border-radius: 0 6px 6px 0;
  margin-top: 4px;
}

.sg-reply-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.sg-reply-author {
  font-size: 12px;
  color: #15803d;
}

.sg-reply-time {
  font-size: 11px;
  color: #64748b;
}

.sg-reply-body {
  font-size: 13px;
  color: #334155;
  line-height: 1.5;
  margin: 0;
}

.sg-empty-reviews-text {
  color: #64748b;
  font-size: 13.5px;
  margin: 12px 0;
}

@media (max-width: 640px) {
  .sg-reviews-overview {
    grid-template-columns: 1fr;
    gap: 20px;
  }
}

/* LOCATION & MAP SECTION - MINIMALIST FLAT STYLING */
.sg-location-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
  background: #ffffff !important;
}

.sg-location-section *,
.sg-location-section span,
.sg-location-section p,
.sg-location-section a,
.sg-location-section button {
  font-weight: 400 !important;
  background-image: none !important;
}

.sg-location-info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.sg-loc-card-flat {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 8px 0;
  border-bottom: none;
}

.sg-loc-card-label {
  font-size: 11.5px;
  color: #64748b;
  letter-spacing: 0.03em;
}

.sg-loc-card-value {
  font-size: 13.5px;
  color: #0f172a;
  line-height: 1.55;
  margin: 0;
  flex: 1;
}

.sg-loc-btn-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #15803d;
  font-size: 12.5px;
  background: transparent;
  border: none;
  padding: 0;
  cursor: pointer;
  text-decoration: none;
  width: fit-content;
  margin-top: 4px;
}

.sg-loc-btn-action:hover {
  text-decoration: underline;
}

.sg-location-map-wrapper {
  width: 100%;
  height: 420px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  position: relative;
  background: #f8fafc;
}

.sg-venue-map-canvas {
  width: 100%;
  height: 100%;
  z-index: 1;
}

.sg-map-marker-pin {
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.25));
}

.sg-map-popup-content {
  font-family: inherit;
  padding: 4px 2px;
}

.sg-map-popup-title {
  font-size: 13.5px;
  color: #0f172a;
  margin-bottom: 4px;
}

.sg-map-popup-address {
  font-size: 12px;
  color: #475569;
  line-height: 1.4;
}

@media (max-width: 768px) {
  .sg-location-info-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .sg-location-map-wrapper {
    height: 320px;
  }
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
  border-bottom: none;
  padding-bottom: 6px;
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

.sg-booking-access-alert {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 13px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fef2f2;
  color: #991b1b;
  font-size: 12px;
  line-height: 1.5;
}

.sg-booking-access-alert strong {
  font-size: 13px;
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
  border-top: none;
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

/* =========================================================================
   AFFILIATE SHOP / PRODUCTS STYLES
   ========================================================================= */
.sg-affiliate-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sg-affiliate-head {
  margin-bottom: 4px;
}

.sg-affiliate-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.sg-affiliate-grid--preview {
  margin-top: 14px;
}

.sg-affiliate-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.sg-affiliate-card:hover {
  border-color: #15803d;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
  transform: translateY(-2px);
}

.sg-affiliate-img-wrap {
  width: 100%;
  aspect-ratio: 1 / 1;
  position: relative;
  background: #f8fafc;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sg-affiliate-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.25s ease;
}

.sg-affiliate-card:hover .sg-affiliate-img {
  transform: scale(1.03);
}

.sg-affiliate-platform-tag {
  position: absolute;
  top: 8px;
  left: 8px;
  font-size: 10.5px;
  padding: 2px 7px;
  background: rgba(15, 23, 42, 0.78);
  backdrop-filter: blur(4px);
  color: #ffffff;
  border-radius: 4px;
  letter-spacing: 0.02em;
}

.sg-affiliate-body {
  padding: 12px;
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 8px;
}

.sg-affiliate-name {
  font-size: 13.5px;
  color: #0f172a;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin: 0;
  min-height: 38px;
}

.sg-affiliate-price-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-top: auto;
  flex-wrap: wrap;
}

.sg-affiliate-price {
  font-size: 14.5px;
  color: #15803d;
}

.sg-affiliate-old-price {
  font-size: 11.5px;
  color: #94a3b8;
  text-decoration: line-through;
}

.sg-affiliate-buy-btn {
  width: 100%;
  padding: 7px 10px;
  font-size: 12px;
  border-radius: 6px;
  border: 1px solid #15803d;
  background: #f0fdf4;
  color: #15803d;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.15s ease;
  margin-top: 4px;
}

.sg-affiliate-buy-btn:hover {
  background: #15803d;
  color: #ffffff;
}

.sg-affiliate-empty {
  text-align: center;
  padding: 48px 24px;
  color: #475569;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.sg-section-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.sg-link-btn {
  background: transparent;
  border: none;
  color: #15803d;
  font-size: 13px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: background 0.15s ease;
}

.sg-link-btn:hover {
  background: #f0fdf4;
}

@media (max-width: 1100px) {
  .sg-affiliate-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .sg-affiliate-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
}

@media (max-width: 440px) {
  .sg-affiliate-grid {
    grid-template-columns: 1fr;
  }
}
</style>
