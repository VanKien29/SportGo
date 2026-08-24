<template>
  <div class="partner-landing-container">
    <!-- HERO SECTION -->
    <header class="pl-hero">
      <div class="pl-hero-content">
        <h1 class="pl-hero-title">
          Bứt phá doanh thu cụm sân với Nền Tảng Quản Lý Thế Hệ Mới
        </h1>

        <p class="pl-hero-subtitle">
          Giải pháp toàn diện tự động hóa 100% lịch đặt sân, nhận cọc QR tự động, chống đè trùng lịch và tiếp cận hơn 120.000+ người chơi năng động trên toàn quốc.
        </p>

        <div class="pl-hero-cta">
          <button type="button" class="pl-btn pl-btn--primary pl-btn--lg" @click="$emit('start-registration')">
            <span>Đăng ký đối tác ngay (Miễn phí 30 ngày)</span>
          </button>
          <button type="button" class="pl-btn pl-btn--secondary pl-btn--lg" @click="scrollToDemo">
            <span>Xem Demo Dashboard</span>
          </button>
        </div>

        <!-- Trust Stats Bar -->
        <div class="pl-trust-bar">
          <div class="pl-trust-item">
            <span class="pl-trust-num">500+</span>
            <span class="pl-trust-lbl">Cụm sân tin dùng</span>
          </div>
          <div class="pl-trust-item">
            <span class="pl-trust-num">120.000+</span>
            <span class="pl-trust-lbl">Người chơi hàng tháng</span>
          </div>
          <div class="pl-trust-item">
            <span class="pl-trust-num">99.9%</span>
            <span class="pl-trust-lbl">Thời gian hoạt động</span>
          </div>
          <div class="pl-trust-item">
            <span class="pl-trust-num">0đ</span>
            <span class="pl-trust-lbl">Phí cài đặt ban đầu</span>
          </div>
        </div>
      </div>
    </header>

    <!-- INTERACTIVE COMMAND CENTER SHOWCASE (FASTMOSS BENTO STYLE) -->
    <section id="pl-demo-section" class="pl-bento-showcase-section">
      <div class="pl-bento-container">
        <!-- Section Title & Subtitle -->
        <div class="pl-bento-head text-center">
          <span class="pl-bento-badge">TRUNG TÂM CHỈ HUY SPORTGO</span>
          <h2 class="pl-bento-title">Trung tâm điều hành & Tăng trưởng sân thể thao của bạn</h2>
          <p class="pl-bento-desc">
            Trao quyền cho chủ sân thể thao tối ưu hóa công suất giờ trống, theo dõi hiệu suất từng thảm sân và hành động dựa trên dữ liệu chính xác thúc đẩy tăng trưởng doanh số
          </p>
        </div>

        <!-- 2-Column Bento Interactive Grid -->
        <div class="pl-bento-layout">
          <!-- LEFT COLUMN: Vertical Accordion Feature Cards -->
          <div class="pl-bento-accordion" role="tablist">
            <!-- Item 1: Calendar -->
            <div
              class="pl-bento-card"
              :class="{ 'is-active': activeDemoTab === 'calendar' }"
              @click="activeDemoTab = 'calendar'"
            >
              <div class="pl-bento-card__header">
                <h3 class="pl-bento-card__title">Theo dõi lịch sân & Khóa giờ tức thì</h3>
              </div>
              <p class="pl-bento-card__text" v-if="activeDemoTab === 'calendar'">
                Khách tự đặt lịch trực tuyến 24/7. Hệ thống tự động khóa giờ trống và ngăn chặn 100% tình trạng đè lịch giữa các kênh đặt sân.
              </p>
            </div>

            <!-- Item 2: Analytics -->
            <div
              class="pl-bento-card"
              :class="{ 'is-active': activeDemoTab === 'analytics' }"
              @click="activeDemoTab = 'analytics'"
            >
              <div class="pl-bento-card__header">
                <h3 class="pl-bento-card__title">Báo cáo doanh thu & Phân tích giờ vàng</h3>
              </div>
              <p class="pl-bento-card__text" v-if="activeDemoTab === 'analytics'">
                Theo dõi các khung giờ sinh lời cao nhất, biểu đồ doanh thu VietQR tự động và đo lường tỷ lệ lấp đầy giờ vàng để tối ưu giá thuê.
              </p>
            </div>

            <!-- Item 3: POS Retail -->
            <div
              class="pl-bento-card"
              :class="{ 'is-active': activeDemoTab === 'pos' }"
              @click="activeDemoTab = 'pos'"
            >
              <div class="pl-bento-card__header">
                <h3 class="pl-bento-card__title">Tích hợp POS bán lẻ & Cho thuê vợt</h3>
              </div>
              <p class="pl-bento-card__text" v-if="activeDemoTab === 'pos'">
                Tạo đơn bán nước giải khát, phụ kiện thể thao, căng vợt và cho thuê dụng cụ ngay trên giao diện ca trực của nhân viên.
              </p>
            </div>

            <!-- Item 4: Shifts & Cashier -->
            <div
              class="pl-bento-card"
              :class="{ 'is-active': activeDemoTab === 'shifts' }"
              @click="activeDemoTab = 'shifts'"
            >
              <div class="pl-bento-card__header">
                <h3 class="pl-bento-card__title">Đối soát ca làm việc & Thu ngân minh bạch</h3>
              </div>
              <p class="pl-bento-card__text" v-if="activeDemoTab === 'shifts'">
                Tự động chốt ca bàn giao tiền mặt, đối soát chuyển khoản ngân hàng và phân quyền chi tiết cho từng nhân viên trực sân.
              </p>
            </div>

            <!-- Item 5: Venue Profile -->
            <div
              class="pl-bento-card"
              :class="{ 'is-active': activeDemoTab === 'venue' }"
              @click="activeDemoTab = 'venue'"
            >
              <div class="pl-bento-card__header">
                <h3 class="pl-bento-card__title">Trang giới thiệu cụm sân chuẩn SEO Google</h3>
              </div>
              <p class="pl-bento-card__text" v-if="activeDemoTab === 'venue'">
                Cụm sân của bạn xuất hiện nổi bật trên bản đồ tìm kiếm SportGo và Google, thu hút hàng ngàn người chơi thể thao quanh khu vực.
              </p>
            </div>
          </div>

          <!-- RIGHT COLUMN: Dynamic Live Preview Canvas -->
          <div class="pl-bento-canvas">
            <!-- TAB 1: CALENDAR REALTIME -->
            <div v-if="activeDemoTab === 'calendar'" class="pl-canvas-pane">
              <div class="pl-canvas-top-chip">
                <span>Lịch đặt sân thời gian thực - Green Sport Ba Đình</span>
              </div>

              <div class="pl-calendar-topbar">
                <div class="pl-cal-info">
                  <h3>Sơ đồ sân Thứ Sáu, 07/08/2026</h3>
                  <span class="pl-subtext">Click vào ô bất kỳ để xem thao tác giữ chỗ / hủy chỗ</span>
                </div>
                <div class="pl-cal-actions">
                  <span class="legend legend-booked">Đã đặt (Cọc đủ)</span>
                  <span class="legend legend-hold">Đang giữ chỗ</span>
                  <span class="legend legend-empty">Giờ trống</span>
                </div>
              </div>

              <!-- Court Slots Grid -->
              <div class="pl-slots-table">
                <div class="pl-slots-header">
                  <div class="pl-cell-time">Khung giờ</div>
                  <div class="pl-cell-court">Sân Pickleball #01</div>
                  <div class="pl-cell-court">Sân Pickleball #02</div>
                  <div class="pl-cell-court">Sân Cầu Lông VIP 1</div>
                  <div class="pl-cell-court">Sân Bóng Đá Mini 7</div>
                </div>

                <div v-for="(slot, idx) in demoTimeSlots" :key="idx" class="pl-slots-row">
                  <div class="pl-cell-time"><span>{{ slot.time }}</span></div>
                  
                  <div class="pl-cell-court">
                    <div class="pl-slot-card" :class="slot.c1.type" @click="toggleSlot('c1', idx)">
                      <span class="pl-slot-user">{{ slot.c1.title }}</span>
                      <span class="pl-slot-price">{{ slot.c1.price }}</span>
                    </div>
                  </div>

                  <div class="pl-cell-court">
                    <div class="pl-slot-card" :class="slot.c2.type" @click="toggleSlot('c2', idx)">
                      <span class="pl-slot-user">{{ slot.c2.title }}</span>
                      <span class="pl-slot-price">{{ slot.c2.price }}</span>
                    </div>
                  </div>

                  <div class="pl-cell-court">
                    <div class="pl-slot-card" :class="slot.c3.type" @click="toggleSlot('c3', idx)">
                      <span class="pl-slot-user">{{ slot.c3.title }}</span>
                      <span class="pl-slot-price">{{ slot.c3.price }}</span>
                    </div>
                  </div>

                  <div class="pl-cell-court">
                    <div class="pl-slot-card" :class="slot.c4.type" @click="toggleSlot('c4', idx)">
                      <span class="pl-slot-user">{{ slot.c4.title }}</span>
                      <span class="pl-slot-price">{{ slot.c4.price }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Floating Bottom Chip -->
              <div class="pl-canvas-floating-kpi">
                <span>Tỷ lệ lấp đầy hôm nay:</span>
                <strong>84% (Cao điểm)</strong>
              </div>
            </div>

            <!-- TAB 2: ANALYTICS -->
            <div v-else-if="activeDemoTab === 'analytics'" class="pl-canvas-pane">
              <div class="pl-canvas-top-chip">
                <span>Báo cáo vận hành cụm sân tháng 08/2026</span>
              </div>

              <div class="pl-stats-grid-3">
                <div class="pl-stat-box">
                  <span>Tổng doanh thu</span>
                  <span class="pl-stat-val">148.500.000 đ</span>
                  <small class="pl-stat-sub">+18% so với tháng trước</small>
                </div>
                <div class="pl-stat-box">
                  <span>Lượt đặt thành công</span>
                  <span class="pl-stat-val">864 lượt</span>
                  <small class="pl-stat-sub">Tỷ lệ lấp đầy 84%</small>
                </div>
                <div class="pl-stat-box">
                  <span>Cọc qua VietQR</span>
                  <span class="pl-stat-val">112.300.000 đ</span>
                  <small class="pl-stat-sub">Tự động 100%</small>
                </div>
              </div>

              <div class="pl-chart-box">
                <div class="pl-chart-head">
                  <h4>Xu hướng doanh thu theo ngày (Tháng 8/2026)</h4>
                </div>
                <div class="pl-fake-bar-chart">
                  <div v-for="(height, i) in [45, 60, 75, 50, 90, 85, 100, 65, 80, 95, 110, 105, 120]" :key="i" class="pl-bar-group">
                    <div class="pl-bar-fill" :style="{ height: height + 'px' }"></div>
                    <span>{{ i + 1 }}</span>
                  </div>
                </div>
              </div>

              <div class="pl-canvas-floating-kpi">
                <span>Doanh thu trung bình/ngày:</span>
                <strong>4.950.000 đ</strong>
              </div>
            </div>

            <!-- TAB 3: POS RETAIL -->
            <div v-else-if="activeDemoTab === 'pos'" class="pl-canvas-pane">
              <div class="pl-canvas-top-chip">
                <span>Giao diện bán hàng & Dịch vụ tại sân (POS)</span>
              </div>

              <div class="pl-pos-layout">
                <div class="pl-pos-products">
                  <div class="pl-pos-prod-card" v-for="item in demoPosItems" :key="item.name">
                    <div class="pl-pos-prod-info">
                      <strong>{{ item.name }}</strong>
                      <span>{{ item.category }}</span>
                    </div>
                    <div class="pl-pos-prod-price">{{ item.price }}</div>
                  </div>
                </div>

                <div class="pl-pos-cart">
                  <div class="pl-pos-cart-head">
                    <h4>Đơn hàng sân #02</h4>
                    <span>Bàn giao ca</span>
                  </div>
                  <div class="pl-pos-cart-items">
                    <div class="pl-pos-cart-row">
                      <span>2x Nước Revive chanh muối</span>
                      <strong>30.000 đ</strong>
                    </div>
                    <div class="pl-pos-cart-row">
                      <span>1x Hộp bóng Pickleball Franklin</span>
                      <strong>110.000 đ</strong>
                    </div>
                    <div class="pl-pos-cart-row">
                      <span>2x Thuê vợt thi đấu Yonex</span>
                      <strong>60.000 đ</strong>
                    </div>
                  </div>
                  <div class="pl-pos-cart-total">
                    <span>Tổng hóa đơn:</span>
                    <strong>200.000 đ</strong>
                  </div>
                  <button type="button" class="pl-pos-pay-btn">
                    Tạo VietQR thu tiền ngay
                  </button>
                </div>
              </div>
            </div>

            <!-- TAB 4: SHIFTS -->
            <div v-else-if="activeDemoTab === 'shifts'" class="pl-canvas-pane">
              <div class="pl-canvas-top-chip">
                <span>Sổ bàn giao ca thu ngân & Nhân viên trực sân</span>
              </div>

              <div class="pl-shifts-head">
                <h3>Báo cáo ca làm việc ngày hôm nay</h3>
                <button type="button" class="pl-btn-sm pl-btn--primary">Kết ca & In báo cáo</button>
              </div>

              <div class="pl-shift-list">
                <div class="pl-shift-item">
                  <div class="pl-shift-user">
                    <span class="pl-shift-name">Ca sáng (06:00 - 14:00)</span>
                    <span class="pl-shift-sub">Thu ngân: Nguyễn Văn Anh</span>
                  </div>
                  <div class="pl-shift-money">
                    <span class="pl-shift-val">3.450.000 đ</span>
                    <span class="status-done">Đã đối soát khớp 100%</span>
                  </div>
                </div>

                <div class="pl-shift-item">
                  <div class="pl-shift-user">
                    <span class="pl-shift-name">Ca chiều (14:00 - 22:00)</span>
                    <span class="pl-shift-sub">Thu ngân: Trần Thị Mai</span>
                  </div>
                  <div class="pl-shift-money">
                    <span class="pl-shift-val">5.800.000 đ</span>
                    <span class="status-running">Đang trực ca làm việc</span>
                  </div>
                </div>
              </div>

              <div class="pl-canvas-floating-kpi">
                <span>Tổng tiền mặt & Chuyển khoản trong ngày:</span>
                <strong>9.250.000 đ</strong>
              </div>
            </div>

            <!-- TAB 5: VENUE PROFILE -->
            <div v-else class="pl-canvas-pane">
              <div class="pl-canvas-top-chip">
                <span>Trang cụm sân được tối ưu SEO Google & Tìm kiếm</span>
              </div>

              <div class="pl-venue-preview-header">
                <img :src="'/images/partner_dashboard_hero.png'" alt="Cụm sân demo" class="pl-preview-banner" />
                <div class="pl-preview-details">
                  <h2>Green Sport Ba Đình - Tổ Hợp Thể Thao Đa Năng</h2>
                  <p>Số 12 Kim Mã, Phường Kim Mã, Quận Ba Đình, Hà Nội</p>
                  <div class="pl-venue-features">
                    <span>Sân Pickleball</span>
                    <span>Sân Cầu Lông</span>
                    <span>Đèn Chiếu Sáng Đêm</span>
                    <span>Wifi Miễn Phí</span>
                    <span>Bãi Đỗ Xe Ô Tô</span>
                  </div>
                </div>
              </div>

              <div class="pl-canvas-floating-kpi">
                <span>Lượt xem trên SportGo & Google tháng này:</span>
                <strong>12.400+ lượt</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- KEY FEATURES & ADVANTAGES WITH 3D ILLUSTRATIONS (NO ICON TAGS) -->
    <!-- KEY ADVANTAGES SECTION (MODERN SAGE & SLATE CARDS) -->
    <section class="pl-features-section">
      <div class="pl-features-container">
        <div class="pl-features-head">
          <span class="pl-features-badge">LỢI THẾ VƯỢT TRỘI</span>
          <h2 class="pl-features-title">Giải pháp toàn diện giúp quản lý sân nhẹ nhàng hơn</h2>
          <p class="pl-features-desc">
            Giải quyết triệt để nỗi lo trùng lịch, kiểm soát 100% dòng tiền cọc và giải phóng bạn khỏi các cuộc gọi thủ công
          </p>
        </div>

        <div class="pl-features-grid">
          <div class="pl-feature-item">
            <div class="pl-feature-illus-wrap">
              <img
                :src="'/images/partner/partner_adv_automation.png'"
                alt="Tự động hóa lịch trống"
                class="pl-feature-illus-img"
                loading="lazy"
              />
            </div>
            <div class="pl-feature-item__top">
              <span class="pl-feature-step-num">01</span>
              <span class="pl-feature-tag">Thời gian thực</span>
            </div>
            <h3 class="pl-feature-item__title">Tự động hóa 100% lịch trống</h3>
            <p class="pl-feature-item__desc">
              Khách hàng tự tra cứu khung giờ trống và đặt chỗ 24/7. Hệ thống tự động đồng bộ realtime giữa các sân, ngăn chặn hoàn toàn tình trạng đè lịch.
            </p>
          </div>

          <div class="pl-feature-item">
            <div class="pl-feature-illus-wrap">
              <img
                :src="'/images/partner/partner_adv_payment.png'"
                alt="Thu tiền cọc VietQR"
                class="pl-feature-illus-img"
                loading="lazy"
              />
            </div>
            <div class="pl-feature-item__top">
              <span class="pl-feature-step-num">02</span>
              <span class="pl-feature-tag">Thanh toán VietQR</span>
            </div>
            <h3 class="pl-feature-item__title">Thu tiền cọc tự động tức thì</h3>
            <p class="pl-feature-item__desc">
              Tích hợp sẵn cổng VietQR động cho từng lượt đặt. Khi khách thanh toán thành công, lịch được xác nhận ngay và tiền về thẳng tài khoản của chủ sân.
            </p>
          </div>

          <div class="pl-feature-item">
            <div class="pl-feature-illus-wrap">
              <img
                :src="'/images/partner/partner_adv_finance.png'"
                alt="Quản lý ca trực và chống thất thoát"
                class="pl-feature-illus-img"
                loading="lazy"
              />
            </div>
            <div class="pl-feature-item__top">
              <span class="pl-feature-step-num">03</span>
              <span class="pl-feature-tag">Kiểm soát dòng tiền</span>
            </div>
            <h3 class="pl-feature-item__title">Quản lý ca trực & Chống thất thoát</h3>
            <p class="pl-feature-item__desc">
              Phân quyền chi tiết cho nhân viên thu ngân và trực ca. Báo cáo doanh thu minh bạch, tự động đối soát tiền mặt và chuyển khoản theo từng ca làm việc.
            </p>
          </div>

          <div class="pl-feature-item">
            <div class="pl-feature-illus-wrap">
              <img
                :src="'/images/partner/partner_adv_discovery.png'"
                alt="Tiếp cận khách chơi thể thao"
                class="pl-feature-illus-img"
                loading="lazy"
              />
            </div>
            <div class="pl-feature-item__top">
              <span class="pl-feature-step-num">04</span>
              <span class="pl-feature-tag">Tăng trưởng khách</span>
            </div>
            <h3 class="pl-feature-item__title">Tiếp cận khách chơi quanh khu vực</h3>
            <p class="pl-feature-item__desc">
              Trang cụm sân được tối ưu SEO Onpage chuẩn quốc tế. Cụm sân của bạn xuất hiện nổi bật ngay khi người chơi tìm kiếm sân gần nhất trên SportGo và Google.
            </p>
          </div>
        </div>

        <!-- Highlight Banner Row -->
        <div class="pl-highlight-box">
          <div class="pl-highlight-box__content">
            <h3 class="pl-highlight-box__title">Dành ít thời gian quản lý thủ công, dành nhiều thời gian mở rộng chuỗi sân</h3>
            <ul class="pl-highlight-list">
              <li>
                <strong>Đặt lịch tự phục vụ</strong>
                <span>Người chơi tự thao tác trên điện thoại cá nhân không cần nhân viên trực máy nghe gọi.</span>
              </li>
              <li>
                <strong>Nhắc lịch thông minh</strong>
                <span>Tự động gửi thông báo lịch thi đấu cho người chơi trước giờ bóng lăn.</span>
              </li>
              <li>
                <strong>Quản lý đa cụm sân</strong>
                <span>Theo dõi và điều hành nhiều chi nhánh cụm sân đồng thời trên một tài khoản duy nhất.</span>
              </li>
            </ul>
            <div class="pl-highlight-box__cta text-center">
              <button type="button" class="pl-btn pl-btn--primary" @click="$emit('start-registration')">
                Đăng ký hợp tác ngay
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- INTERACTIVE ROI & REVENUE CALCULATOR (MODERN SAGE & SLATE) -->
    <section class="pl-calc-section">
      <div class="pl-calc-container">
        <div class="pl-calc-layout">
          <!-- LEFT COLUMN: SLIDER INPUTS -->
          <div class="pl-calc-inputs">
            <div class="pl-calc-head">
              <h2 class="pl-calc-title">Ước tính doanh thu & thời gian tiết kiệm mỗi tháng</h2>
              <p class="pl-calc-desc">
                Kéo các thanh trượt bên dưới để mô phỏng mức tối ưu doanh thu và giải phóng thời gian quản lý cho cụm sân của bạn
              </p>
            </div>

            <div class="pl-slider-group">
              <div class="pl-slider-label">
                <span class="pl-slider-name">Số lượng sân thể thao</span>
                <span class="pl-slider-badge">{{ calcCourts }} sân</span>
              </div>
              <input v-model.number="calcCourts" type="range" min="1" max="20" step="1" class="pl-slider" />
            </div>

            <div class="pl-slider-group">
              <div class="pl-slider-label">
                <span class="pl-slider-name">Giá thuê trung bình mỗi giờ</span>
                <span class="pl-slider-badge">{{ formatMoney(calcPrice) }} đ/giờ</span>
              </div>
              <input v-model.number="calcPrice" type="range" min="50000" max="400000" step="10000" class="pl-slider" />
            </div>

            <div class="pl-slider-group">
              <div class="pl-slider-label">
                <span class="pl-slider-name">Số giờ lấp đầy trung bình mỗi ngày</span>
                <span class="pl-slider-badge">{{ calcHours }} giờ/sân</span>
              </div>
              <input v-model.number="calcHours" type="range" min="2" max="14" step="1" class="pl-slider" />
            </div>
          </div>

          <!-- RIGHT COLUMN: RESULT PANEL (INFOGRAPHIC ROI CARD) -->
          <div class="pl-calc-result-panel">
            <div class="pl-result-top">
              <span class="pl-result-head">DOANH THU DỰ KIẾN HÀNG THÁNG</span>
              <div class="pl-result-amount">{{ formatMoney(estimatedMonthlyRevenue) }} đ</div>
            </div>
            
            <div class="pl-result-kpi-grid">
              <div class="pl-result-kpi-item">
                <div class="pl-kpi-val">~{{ calcCourts * 12 }} giờ/tháng</div>
                <div class="pl-kpi-lbl">Tiết kiệm thời gian nghe máy chốt lịch</div>
              </div>
              <div class="pl-result-kpi-item">
                <div class="pl-kpi-val">+30% công suất</div>
                <div class="pl-kpi-lbl">Lấp đầy giờ vắng nhờ người chơi tìm sân</div>
              </div>
              <div class="pl-result-kpi-item">
                <div class="pl-kpi-val">100% tự động</div>
                <div class="pl-kpi-lbl">Cọc VietQR chuyển thẳng về tài khoản</div>
              </div>
            </div>

            <button type="button" class="pl-btn pl-btn--primary pl-btn--full" @click="$emit('start-registration')">
              Bắt đầu tối ưu doanh thu ngay
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- SEO ONPAGE CONTENT SECTION (STRUCTURED ARTICLES & FAQs) -->
    <!-- CORE CAPABILITIES SHOWCASE (2-COLUMN FAST-MOSS INTERACTIVE STYLE) -->
    <section class="pl-core-section">
      <div class="pl-core-container">
        <div class="pl-core-head text-center">
          <h2 class="pl-core-title">Phần mềm quản lý sân thể thao SportGo - Nâng tầm trải nghiệm vận hành</h2>
          <p class="pl-core-desc">
            Trong thời đại chuyển đổi số thể thao năm 2026, SportGo giải quyết triệt để sự cố trùng đè lịch và quản lý tài chính thủ công bằng nền tảng tự động hóa toàn diện.
          </p>
        </div>

        <div class="pl-core-layout">
          <!-- LEFT COLUMN: Vertical Accordion Feature Selectors -->
          <div class="pl-core-tabs" role="tablist">
            <!-- TAB 1: VIETQR -->
            <div
              class="pl-core-tab-card"
              :class="{ 'is-active': activeCoreTab === 'vietqr' }"
              @click="activeCoreTab = 'vietqr'"
            >
              <h3 class="pl-core-tab-card__title">Tự động nhận cọc VietQR động</h3>
              <p class="pl-core-tab-card__text" v-if="activeCoreTab === 'vietqr'">
                Mã VietQR động sinh ra theo từng đơn đặt sân chính xác đến từng đồng. Khách thanh toán là lịch được khóa ngay lập tức, tiền về thẳng tài khoản chủ sân mà không cần gửi số tài khoản thủ công.
              </p>
            </div>

            <!-- TAB 2: REVENUE & DEBT -->
            <div
              class="pl-core-tab-card"
              :class="{ 'is-active': activeCoreTab === 'revenue' }"
              @click="activeCoreTab = 'revenue'"
            >
              <h3 class="pl-core-tab-card__title">Theo dõi doanh thu & Công nợ realtime</h3>
              <p class="pl-core-tab-card__text" v-if="activeCoreTab === 'revenue'">
                Báo cáo tự động tổng kết số dư tiền mặt, tiền chuyển khoản và doanh thu dịch vụ (bán nước, thuê vợt) theo từng ca làm việc của nhân viên với độ chính xác tuyệt đối.
              </p>
            </div>

            <!-- TAB 3: SEO ONPAGE -->
            <div
              class="pl-core-tab-card"
              :class="{ 'is-active': activeCoreTab === 'seo' }"
              @click="activeCoreTab = 'seo'"
            >
              <h3 class="pl-core-tab-card__title">Tối ưu hóa SEO Onpage cho từng cụm sân</h3>
              <p class="pl-core-tab-card__text" v-if="activeCoreTab === 'seo'">
                Trang thông tin cụm sân được xây dựng chuẩn SEO Google, hiển thị đầy đủ hình ảnh, bảng giá, tiện ích và vị trí dẫn đường trên Google Maps giúp người chơi dễ dàng tìm thấy bạn.
              </p>
            </div>
          </div>

          <!-- RIGHT COLUMN: Dynamic Visual Preview Canvas -->
          <div class="pl-core-canvas">
            <!-- PREVIEW 1: VIETQR DEMO -->
            <div v-if="activeCoreTab === 'vietqr'" class="pl-core-preview-pane">
              <div class="pl-qr-demo-card">
                <div class="pl-qr-demo-top">
                  <div class="pl-qr-badge">VIETQR ĐỘNG TỰ ĐỘNG</div>
                  <span class="pl-qr-time">Hạn thanh toán: 09:59</span>
                </div>
                <div class="pl-qr-demo-body">
                  <div class="pl-qr-code-box">
                    <img :src="'/images/partner/partner_adv_payment.png'" alt="VietQR Demo" class="pl-qr-img-preview" />
                  </div>
                  <div class="pl-qr-details">
                    <h4>Đơn đặt sân Pickleball #02</h4>
                    <p>Khung giờ: 17:30 - 19:30 (Thứ Bảy)</p>
                    <div class="pl-qr-amount-row">
                      <span>Tiền cọc cần thanh toán:</span>
                      <strong>150.000 đ</strong>
                    </div>
                    <div class="pl-qr-status-tag">
                      Trạng thái: Đã khớp lệnh qua ngân hàng
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- PREVIEW 2: REVENUE DEMO -->
            <div v-else-if="activeCoreTab === 'revenue'" class="pl-core-preview-pane">
              <div class="pl-rev-demo-card">
                <div class="pl-rev-head">
                  <h4>Bảng đối soát ca chiều (14:00 - 22:00)</h4>
                  <span class="status-done">Đã khớp 100%</span>
                </div>
                <div class="pl-rev-table">
                  <div class="pl-rev-row">
                    <span>Tiền sân (VietQR):</span>
                    <strong>4.200.000 đ</strong>
                  </div>
                  <div class="pl-rev-row">
                    <span>Tiền sân (Tiền mặt):</span>
                    <strong>1.100.000 đ</strong>
                  </div>
                  <div class="pl-rev-row">
                    <span>Dịch vụ bán lẻ nước & bóng:</span>
                    <strong>500.000 đ</strong>
                  </div>
                  <div class="pl-rev-row total">
                    <span>Tổng tiền bàn giao ca:</span>
                    <strong class="text-sage">5.800.000 đ</strong>
                  </div>
                </div>
              </div>
            </div>

            <!-- PREVIEW 3: SEO DEMO -->
            <div v-else class="pl-core-preview-pane">
              <div class="pl-seo-demo-card">
                <div class="pl-google-search-bar">
                  <span class="pl-google-icon">Google</span>
                  <span class="pl-google-query">sân pickleball ba đình gần nhất</span>
                </div>
                <div class="pl-google-result-item">
                  <span class="pl-google-url">https://sportgo.vn/venues/green-sport-ba-dinh</span>
                  <h4>Green Sport Ba Đình - Cụm Sân Thể Thao Đạt Chuẩn</h4>
                  <div class="pl-google-rating">
                    <span>5.0 ★★★★★ (128 đánh giá)</span> · <span>Mở cửa 24/7</span>
                  </div>
                  <p class="pl-google-snippet">
                    Đặt sân trực tuyến tức thì không cần gọi điện. Sân Pickleball, Cầu Lông có đèn LED đêm, gửi xe ô tô miễn phí tại Số 12 Kim Mã, Ba Đình...
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- FAQ ACCORDION -->
        <div class="pl-faq-wrap">
          <div class="pl-section-head text-center mb-8">
            <h3 class="pl-faq-title">Câu hỏi thường gặp của Chủ Sân (FAQ)</h3>
            <p class="pl-faq-desc">Giải đáp nhanh các thắc mắc phổ biến khi đăng ký trở thành đối tác SportGo</p>
          </div>
          
          <div class="pl-faq-list">
            <div v-for="(faq, fIdx) in faqList" :key="fIdx" class="pl-faq-item" :class="{ 'is-open': openFaqIndex === fIdx }">
              <button type="button" class="pl-faq-q" @click="toggleFaq(fIdx)">
                <span>{{ faq.q }}</span>
                <span class="pl-faq-toggle-icon">{{ openFaqIndex === fIdx ? '−' : '+' }}</span>
              </button>
              <div v-if="openFaqIndex === fIdx" class="pl-faq-a">
                <p>{{ faq.a }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";

defineEmits(["start-registration"]);

// Active tab in top bento showcase
const activeDemoTab = ref("calendar");

// Active tab in core capabilities showcase
const activeCoreTab = ref("vietqr");

// Scroll Animation for 3D Device Mockup
const scrollY = ref(0);
const handleScroll = () => {
  scrollY.value = window.scrollY || document.documentElement.scrollTop;
};

onMounted(() => {
  window.addEventListener("scroll", handleScroll, { passive: true });
});

onUnmounted(() => {
  window.removeEventListener("scroll", handleScroll);
});

const mockupStyle = computed(() => {
  const progress = Math.min(1, Math.max(0, (scrollY.value - 200) / 400));
  const rotateX = 12 * (1 - progress);
  const scale = 0.94 + 0.06 * progress;
  return {
    transform: `perspective(1000px) rotateX(${rotateX}deg) scale(${scale})`,
    transition: "transform 0.1s ease-out",
  };
});

const scrollToDemo = () => {
  const el = document.getElementById("pl-demo-section");
  if (el) el.scrollIntoView({ behavior: "smooth" });
};

// Demo Time Slots Data
const demoTimeSlots = ref([
  {
    time: "07:00 - 09:00",
    c1: { title: "Anh Tuấn (Cọc 100k)", price: "120.000 đ", type: "slot-booked" },
    c2: { title: "Khung giờ trống", price: "100.000 đ", type: "slot-empty" },
    c3: { title: "CLB Ba Đình", price: "140.000 đ", type: "slot-booked" },
    c4: { title: "Khung giờ trống", price: "250.000 đ", type: "slot-empty" },
  },
  {
    time: "09:00 - 11:00",
    c1: { title: "Khung giờ trống", price: "120.000 đ", type: "slot-empty" },
    c2: { title: "Chị Hà (Giữ chỗ)", price: "100.000 đ", type: "slot-hold" },
    c3: { title: "Khung giờ trống", price: "140.000 đ", type: "slot-empty" },
    c4: { title: "Sân FC Hà Nội", price: "300.000 đ", type: "slot-booked" },
  },
  {
    time: "17:00 - 19:00 (Vàng)",
    c1: { title: "Nhóm Pickleball VN", price: "180.000 đ", type: "slot-booked" },
    c2: { title: "Anh Hùng (Đã cọc)", price: "180.000 đ", type: "slot-booked" },
    c3: { title: "Đội Cầu Lông Vàng", price: "200.000 đ", type: "slot-booked" },
    c4: { title: "Trận Ghép Kèo 7v7", price: "400.000 đ", type: "slot-booked" },
  },
  {
    time: "19:00 - 21:00 (Vàng)",
    c1: { title: "Sân Cố Định Tháng", price: "180.000 đ", type: "slot-booked" },
    c2: { title: "Sân Cố Định Tháng", price: "180.000 đ", type: "slot-booked" },
    c3: { title: "Sân Cố Định Tháng", price: "200.000 đ", type: "slot-booked" },
    c4: { title: "Khung giờ trống", price: "400.000 đ", type: "slot-empty" },
  },
]);

// Demo POS items
const demoPosItems = ref([
  { name: "Nước Revive bù khoáng 500ml", category: "Đồ uống giải khát", price: "15.000 đ" },
  { name: "Nước điện giải Pocari Sweat", category: "Đồ uống thể thao", price: "20.000 đ" },
  { name: "Hộp bóng Pickleball Franklin", category: "Phụ kiện thi đấu", price: "110.000 đ" },
  { name: "Thuê vợt Yonex / Carbon Pro", category: "Dịch vụ cho thuê", price: "30.000 đ/giờ" },
]);

const toggleSlot = (courtKey, idx) => {
  const slot = demoTimeSlots.value[idx][courtKey];
  if (slot.type === "slot-empty") {
    slot.type = "slot-booked";
    slot.title = "Khách đặt nhanh";
  } else if (slot.type === "slot-booked") {
    slot.type = "slot-empty";
    slot.title = "Khung giờ trống";
  }
};

// ROI Calculator State
const calcCourts = ref(6);
const calcPrice = ref(120000);
const calcHours = ref(7);

const estimatedMonthlyRevenue = computed(() => {
  return calcCourts.value * calcPrice.value * calcHours.value * 30;
});

const formatMoney = (val) => {
  return new Intl.NumberFormat("vi-VN").format(val);
};

// FAQ Accordion State
const openFaqIndex = ref(0);
const toggleFaq = (idx) => {
  openFaqIndex.value = openFaqIndex.value === idx ? -1 : idx;
};

const faqList = [
  {
    q: "SportGo Partner có thu phí cố định ban đầu không?",
    a: "Hoàn toàn KHÔNG! Bạn được sử dụng miễn phí 30 ngày đầu tiên để trải nghiệm toàn bộ tính năng. Sau đó, SportGo chỉ áp dụng mức phí dịch vụ rất nhỏ trên các giao dịch thực tế phát sinh.",
  },
  {
    q: "Cụm sân của tôi có cần mua thiết bị máy tính đắt tiền không?",
    a: "Không cần thiết bị đắt tiền. Hệ thống chạy trực tiếp trên trình duyệt web của bất kỳ điện thoại thông minh, máy tính bảng hoặc máy tính có sẵn nào.",
  },
  {
    q: "Làm thế nào để nhận tiền cọc của khách hàng?",
    a: "Tiền cọc của khách hàng được quét qua VietQR và chuyển thẳng 100% vào tài khoản ngân hàng chính chủ của bạn mà không qua trung gian ngâm vốn.",
  },
  {
    q: "Tôi có thể phân quyền cho nhân viên thu ngân không?",
    a: "Có! Bạn có thể tạo nhiều tài khoản nhân viên với các quyền hạn riêng biệt như: Chỉ được xem lịch, chỉ được thu tiền ca làm việc, không có quyền xóa hay chỉnh sửa doanh thu.",
  },
];
</script>

<style scoped>
/* 
  STRICT RULES COMPLIANCE:
  1. No gradients
  2. No chip / pill / badge
  3. No card nested inside card
  4. No emoji
  5. No gray text on white background
  6. No heavy bold fonts
  7. No border-top / border-bottom lines
  8. NO ICON TAGS (<AppIcon>) - Use high-quality 3D illustrations instead!
*/

.partner-landing-container {
  font-family: inherit;
  color: #0f172a;
  background: #ffffff;
  overflow-x: hidden;
}

/* HERO SECTION - LIGHT THEME */
.pl-hero {
  position: relative;
  padding: 80px 24px 60px;
  text-align: center;
  background: #ffffff;
  color: #0f172a;
}

.pl-hero-content {
  position: relative;
  max-width: 900px;
  margin: 0 auto;
  z-index: 2;
}

.pl-hero-title {
  font-size: 40px;
  font-weight: 500;
  line-height: 1.25;
  margin-bottom: 20px;
  color: #0f172a;
}

.pl-hero-subtitle {
  font-size: 17.5px;
  font-weight: 400;
  color: #475569;
  line-height: 1.6;
  max-width: 720px;
  margin: 0 auto 36px;
}

.pl-hero-cta {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-bottom: 48px;
  flex-wrap: wrap;
}

.pl-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 500;
  padding: 12px 24px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pl-btn--primary {
  background: #54656f;
  color: #ffffff;
  border-radius: 999px;
  box-shadow: 0 4px 14px rgba(84, 101, 111, 0.25);
}

.pl-btn--primary:hover {
  background: #405059;
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(84, 101, 111, 0.35);
}

.pl-btn--secondary {
  background: #ffffff;
  color: #475569;
  border: 1.5px solid #cbd5e1;
  border-radius: 999px;
}

.pl-btn--secondary:hover {
  background: #f8fafc;
  border-color: #54656f;
  color: #0f172a;
}

.pl-btn--lg {
  padding: 13px 28px;
  font-size: 15px;
  font-weight: 600;
}

.pl-btn--full {
  width: 100%;
}

.pl-trust-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 40px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 20px 32px;
  border-radius: 12px;
  flex-wrap: wrap;
}

.pl-trust-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.pl-trust-num {
  font-size: 24px;
  font-weight: 700;
  color: #5c7e6e;
}

.pl-trust-lbl {
  font-size: 13px;
  font-weight: 400;
  color: #475569;
}

/* ==========================================================================
   FASTMOSS STYLE BENTO INTERACTIVE SHOWCASE
   ========================================================================== */
.pl-bento-showcase-section {
  padding: 88px 24px 96px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  color: #0f172a;
}

.pl-bento-container {
  max-width: 1200px;
  margin: 0 auto;
}

.pl-bento-head {
  max-width: 780px;
  margin: 0 auto 48px;
  text-align: center;
}

.pl-bento-badge {
  display: inline-block;
  padding: 6px 14px;
  background: #edf4f0;
  color: #5c7e6e;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.8px;
  margin-bottom: 14px;
}

.pl-bento-title {
  font-size: clamp(26px, 3.5vw, 36px);
  font-weight: 700;
  line-height: 1.25;
  color: #0f172a;
  margin-bottom: 14px;
}

.pl-bento-desc {
  font-size: 15.5px;
  color: #475569;
  line-height: 1.6;
  font-weight: 400;
}

.pl-bento-layout {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 28px;
  align-items: stretch;
}

@media (max-width: 960px) {
  .pl-bento-layout {
    grid-template-columns: 1fr;
  }
}

/* LEFT COLUMN: Accordion Cards */
.pl-bento-accordion {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pl-bento-card {
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  padding: 18px 20px;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
  text-align: left;
  user-select: none;
}

.pl-bento-card:hover:not(.is-active) {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.pl-bento-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.pl-bento-card__title {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
  line-height: 1.35;
}

.pl-bento-card.is-active {
  background: #8da89b;
  border-color: #8da89b;
  box-shadow: 0 8px 20px rgba(141, 168, 155, 0.22);
  padding: 22px 22px;
  cursor: default;
}

.pl-bento-card.is-active .pl-bento-card__title {
  color: #ffffff;
  font-size: 16px;
  font-weight: 700;
}

.pl-bento-card__text {
  margin: 10px 0 0;
  font-size: 13px;
  line-height: 1.55;
  color: rgba(255, 255, 255, 0.95);
  font-weight: 400;
  animation: plFadeIn 0.2s ease-out;
}

/* RIGHT COLUMN: Live Canvas */
.pl-bento-canvas {
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 18px;
  padding: 28px;
  box-shadow: 0 12px 36px rgba(15, 23, 42, 0.04);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 520px;
  position: relative;
  overflow: hidden;
}

.pl-canvas-pane {
  animation: plFadeIn 0.25s ease-out;
  display: flex;
  flex-direction: column;
  gap: 16px;
  height: 100%;
}

@keyframes plFadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

.pl-canvas-top-chip {
  align-self: flex-start;
  padding: 6px 14px;
  background: #edf4f0;
  border: 1px solid #c9ded3;
  color: #3b5c4e;
  font-size: 12.5px;
  font-weight: 600;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.pl-canvas-floating-kpi {
  margin-top: auto;
  padding: 12px 18px;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  color: #475569;
}

.pl-canvas-floating-kpi strong {
  color: #5c7e6e;
  font-size: 14.5px;
}

/* CALENDAR TAB */
.pl-calendar-topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  flex-wrap: wrap;
  gap: 10px;
}

.pl-cal-info h3 {
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 2px;
  color: #0f172a;
}

.pl-subtext {
  font-size: 12px;
  font-weight: 400;
  color: #64748b;
}

.pl-cal-actions {
  display: flex;
  gap: 14px;
  font-size: 12px;
  font-weight: 400;
  color: #0f172a;
}

.legend {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #334155;
}

.legend::before {
  content: "";
  width: 10px;
  height: 10px;
  border-radius: 2px;
}

.legend-booked::before { background: #5c7e6e; }
.legend-hold::before { background: #d97706; }
.legend-empty::before { background: #e2e8f0; }

.pl-slots-table {
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}

.pl-slots-header, .pl-slots-row {
  display: grid;
  grid-template-columns: 110px repeat(4, 1fr);
  align-items: center;
}

.pl-slots-header {
  background: #f8fafc;
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  border-bottom: 1px solid #e2e8f0;
}

.pl-cell-time {
  padding: 8px 10px;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 11.5px;
  color: #475569;
  font-weight: 500;
}

.pl-cell-court {
  padding: 5px;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #f1f5f9;
}

.pl-cell-court:last-child {
  border-right: none;
}

.pl-slot-card {
  padding: 6px 8px;
  border-radius: 6px;
  font-size: 11.5px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  transition: all 0.15s ease;
}

.slot-booked {
  background: #5c7e6e;
  color: #ffffff;
}

.slot-hold {
  background: #d97706;
  color: #ffffff;
}

.slot-empty {
  background: #f8fafc;
  color: #94a3b8;
  border: 1px dashed #cbd5e1;
}

.slot-empty:hover {
  background: #edf4f0;
  border-color: #5c7e6e;
  color: #5c7e6e;
}

.pl-slot-user {
  font-weight: 600;
}

.pl-slot-price {
  font-size: 10.5px;
  font-weight: 400;
}

/* ANALYTICS TAB */
.pl-stats-grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.pl-stat-box {
  background: #f8fafc;
  padding: 14px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
}

.pl-stat-box span {
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  display: block;
}

.pl-stat-val {
  font-size: 18px;
  font-weight: 700;
  display: block;
  margin: 3px 0;
  color: #0f172a !important;
}

.pl-stat-sub {
  font-size: 11.5px;
  font-weight: 500;
  color: #5c7e6e;
}

.pl-chart-box {
  background: #f8fafc;
  padding: 16px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
}

.pl-chart-head h4 {
  font-size: 13.5px;
  font-weight: 600;
  color: #0f172a;
  margin-bottom: 12px;
}

.pl-fake-bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  height: 120px;
}

.pl-bar-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  justify-content: flex-end;
}

.pl-bar-fill {
  width: 100%;
  max-width: 22px;
  background: #5c7e6e;
  border-radius: 3px 3px 0 0;
}

.pl-bar-group span {
  font-size: 10.5px;
  font-weight: 500;
  color: #64748b;
  margin-top: 4px;
}

/* POS LAYOUT */
.pl-pos-layout {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 16px;
}

@media (max-width: 768px) {
  .pl-pos-layout {
    grid-template-columns: 1fr;
  }
}

.pl-pos-products {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.pl-pos-prod-card {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pl-pos-prod-card:hover {
  border-color: #5c7e6e;
  background: #edf4f0;
}

.pl-pos-prod-info strong {
  display: block;
  font-size: 12.5px;
  color: #0f172a;
  line-height: 1.3;
}

.pl-pos-prod-info span {
  font-size: 11px;
  color: #64748b;
}

.pl-pos-prod-price {
  font-size: 13px;
  font-weight: 700;
  color: #5c7e6e;
}

.pl-pos-cart {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.pl-pos-cart-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 6px;
  margin-bottom: 10px;
}

.pl-pos-cart-head h4 {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.pl-pos-cart-head span {
  font-size: 11px;
  color: #64748b;
}

.pl-pos-cart-items {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 10px;
}

.pl-pos-cart-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #334155;
}

.pl-pos-cart-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1.5px dashed #cbd5e1;
  padding-top: 8px;
  margin-bottom: 10px;
  font-size: 12.5px;
}

.pl-pos-cart-total strong {
  font-size: 15px;
  color: #0f172a;
}

.pl-pos-pay-btn {
  width: 100%;
  padding: 10px 14px;
  background: #54656f;
  color: #ffffff;
  border: none;
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pl-pos-pay-btn:hover {
  background: #405059;
}

/* SHIFTS TAB */
.pl-shifts-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.pl-shifts-head h3 {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.pl-btn-sm {
  padding: 6px 14px;
  font-size: 12px;
  border-radius: 999px;
}

.pl-shift-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pl-shift-item {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  padding: 12px 16px;
  border-radius: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pl-shift-name {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}

.pl-shift-sub {
  display: block;
  font-size: 11.5px;
  color: #64748b;
}

.pl-shift-money {
  text-align: right;
}

.pl-shift-val {
  display: block;
  font-size: 15px;
  font-weight: 700;
  color: #5c7e6e !important;
}

.status-done {
  font-size: 11px;
  font-weight: 500;
  color: #5c7e6e;
}

.status-running {
  font-size: 11px;
  font-weight: 500;
  color: #b45309;
}

/* VENUE TAB */
.pl-venue-preview-header {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 16px;
  align-items: center;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  padding: 14px;
  border-radius: 10px;
}

.pl-preview-banner {
  width: 100%;
  height: 120px;
  object-fit: cover;
  border-radius: 8px;
}

.pl-preview-details h2 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 4px 0;
}

.pl-preview-details p {
  font-size: 12.5px;
  font-weight: 400;
  color: #475569;
  margin-bottom: 8px;
}

.pl-venue-features {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.pl-venue-features span {
  font-size: 11px;
  font-weight: 500;
  background: #edf4f0;
  color: #5c7e6e;
  padding: 3px 8px;
  border-radius: 6px;
}

/* KEY ADVANTAGES SECTION (MODERN SAGE & SLATE) */
.pl-features-section {
  padding: 96px 24px 104px;
  background: #ffffff;
  color: #0f172a;
}

.pl-features-container {
  max-width: 1200px;
  margin: 0 auto;
}

.pl-features-head {
  max-width: 820px;
  margin: 0 auto 52px;
  text-align: center;
}

.pl-features-badge {
  display: inline-block;
  padding: 6px 16px;
  background: #edf4f0;
  color: #5c7e6e;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.8px;
  margin-bottom: 18px;
}

.pl-features-title {
  font-size: clamp(28px, 3.8vw, 36px);
  font-weight: 700;
  line-height: 1.3;
  color: #0f172a;
  margin: 0 0 16px;
}

.pl-features-desc {
  font-size: 16px;
  line-height: 1.65;
  color: #475569;
  font-weight: 400;
  margin: 0;
}

.pl-features-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  margin-bottom: 56px;
}

@media (max-width: 1024px) {
  .pl-features-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .pl-features-grid {
    grid-template-columns: 1fr;
  }
}

.pl-feature-item {
  background: #f8fafc;
  border: none;
  border-radius: 18px;
  padding: 26px 22px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.02);
  transition: background-color 0.15s ease;
}

.pl-feature-item:hover {
  background: #f1f5f9;
}

.pl-feature-illus-wrap {
  width: 100%;
  height: 170px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  margin-bottom: 6px;
}

.pl-feature-illus-img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  filter: drop-shadow(0 10px 20px rgba(84, 101, 111, 0.1));
}

.pl-feature-item__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.pl-feature-step-num {
  font-size: 24px;
  font-weight: 800;
  color: #8da89b;
  line-height: 1;
}

.pl-feature-tag {
  padding: 3px 8px;
  background: #edf4f0;
  color: #5c7e6e;
  font-size: 11px;
  font-weight: 600;
  border-radius: 6px;
}

.pl-feature-item__title {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  line-height: 1.4;
}

.pl-feature-item__desc {
  font-size: 14px;
  line-height: 1.65;
  color: #475569;
  margin: 0;
  font-weight: 400;
}

/* HIGHLIGHT BOX */
.pl-highlight-box {
  background: #f8fafc;
  border: none;
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 6px 24px rgba(15, 23, 42, 0.03);
}

.pl-highlight-box__title {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 24px;
  line-height: 1.35;
  text-align: center;
}

.pl-highlight-list {
  list-style: none;
  padding: 0;
  margin: 0 0 32px;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

@media (max-width: 860px) {
  .pl-highlight-list {
    grid-template-columns: 1fr;
  }
}

.pl-highlight-list li {
  font-size: 13.5px;
  color: #475569;
  line-height: 1.6;
  background: #ffffff;
  border: none;
  padding: 20px;
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.02);
}

.pl-highlight-list li strong {
  color: #0f172a;
  font-size: 15px;
  font-weight: 700;
}

.pl-highlight-box__cta {
  display: flex;
  justify-content: center;
}

/* CALCULATOR SECTION - MODERN SAGE & SLATE */
.pl-calc-section {
  padding: 96px 24px 104px;
  background: #ffffff;
  color: #0f172a;
}

.pl-calc-container {
  max-width: 1200px;
  margin: 0 auto;
}

.pl-calc-layout {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 56px;
  align-items: center;
}

@media (max-width: 960px) {
  .pl-calc-layout {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}

.pl-calc-head {
  margin-bottom: 36px;
}

.pl-calc-badge {
  display: inline-block;
  padding: 6px 16px;
  background: #edf4f0;
  color: #5c7e6e;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.8px;
  margin-bottom: 16px;
}

.pl-calc-title {
  font-size: clamp(26px, 3.2vw, 34px);
  font-weight: 700;
  line-height: 1.3;
  color: #0f172a;
  margin: 0 0 14px;
}

.pl-calc-desc {
  font-size: 15.5px;
  line-height: 1.6;
  color: #475569;
  font-weight: 400;
  margin: 0;
}

.pl-slider-group {
  margin-bottom: 28px;
}

.pl-slider-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.pl-slider-name {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}

.pl-slider-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  background: #edf4f0;
  color: #5c7e6e;
  font-size: 13.5px;
  font-weight: 700;
  border-radius: 8px;
}

.pl-slider {
  width: 100%;
  accent-color: #8da89b;
  height: 6px;
  border-radius: 999px;
  background: #e2e8f0;
  outline: none !important;
  box-shadow: none !important;
  cursor: pointer;
}

.pl-calc-result-panel {
  background: #f8fafc;
  border: none;
  border-radius: 24px;
  padding: 36px 32px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 12px 36px rgba(15, 23, 42, 0.04);
}

.pl-result-top {
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 20px;
  margin-bottom: 24px;
}

.pl-result-head {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.8px;
  margin-bottom: 8px;
}

.pl-result-amount {
  font-size: 36px;
  font-weight: 800;
  color: #5c7e6e;
  line-height: 1.15;
}

.pl-result-kpi-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 28px;
}

.pl-result-kpi-item {
  background: #ffffff;
  padding: 14px 16px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
}

.pl-kpi-val {
  font-size: 14.5px;
  font-weight: 700;
  color: #0f172a;
}

.pl-kpi-lbl {
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.4;
}

/* CORE SYSTEM CAPABILITIES (2-COLUMN FAST-MOSS STYLE) */
.pl-core-section {
  padding: 96px 24px 104px;
  background: #f8fafc;
  color: #0f172a;
}

.pl-core-container {
  max-width: 1200px;
  margin: 0 auto;
}

.pl-core-head {
  max-width: 820px;
  margin: 0 auto 52px;
  text-align: center;
}

.pl-core-title {
  font-size: clamp(26px, 3.2vw, 34px);
  font-weight: 700;
  line-height: 1.3;
  color: #0f172a;
  margin-bottom: 14px;
}

.pl-core-desc {
  font-size: 15.5px;
  line-height: 1.6;
  color: #475569;
  font-weight: 400;
  margin: 0;
}

.pl-core-layout {
  display: grid;
  grid-template-columns: 420px 1fr;
  gap: 36px;
  align-items: stretch;
  margin-bottom: 80px;
}

@media (max-width: 960px) {
  .pl-core-layout {
    grid-template-columns: 1fr;
  }
}

.pl-core-tabs {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pl-core-tab-card {
  background: #ffffff;
  border-radius: 18px;
  padding: 22px 24px;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.02);
  transition: background-color 0.15s ease, box-shadow 0.15s ease;
  user-select: none;
}

.pl-core-tab-card:hover:not(.is-active) {
  background: #f1f5f9;
}

.pl-core-tab-card.is-active {
  background: #8da89b;
  box-shadow: 0 8px 24px rgba(141, 168, 155, 0.25);
  cursor: default;
}

.pl-core-tab-card__title {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  line-height: 1.35;
}

.pl-core-tab-card.is-active .pl-core-tab-card__title {
  color: #ffffff;
}

.pl-core-tab-card__text {
  margin: 10px 0 0;
  font-size: 13.5px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.95);
  font-weight: 400;
  animation: plFadeIn 0.2s ease-out;
}

/* RIGHT CANVAS */
.pl-core-canvas {
  background: #ffffff;
  border-radius: 24px;
  padding: 36px;
  box-shadow: 0 12px 36px rgba(15, 23, 42, 0.04);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pl-core-preview-pane {
  width: 100%;
  animation: plFadeIn 0.25s ease-out;
}

/* PREVIEW 1: VIETQR */
.pl-qr-demo-card {
  background: #f8fafc;
  padding: 24px;
  border-radius: 18px;
}

.pl-qr-demo-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.pl-qr-badge {
  font-size: 11px;
  font-weight: 700;
  background: #edf4f0;
  color: #5c7e6e;
  padding: 4px 10px;
  border-radius: 6px;
}

.pl-qr-time {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

.pl-qr-demo-body {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 24px;
  align-items: center;
}

.pl-qr-code-box {
  width: 140px;
  height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  border-radius: 14px;
  padding: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.pl-qr-img-preview {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.pl-qr-details h4 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 6px;
}

.pl-qr-details p {
  font-size: 13px;
  color: #64748b;
  margin: 0 0 12px;
}

.pl-qr-amount-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 13.5px;
  color: #334155;
}

.pl-qr-amount-row strong {
  font-size: 20px;
  color: #5c7e6e;
  font-weight: 800;
}

.pl-qr-status-tag {
  display: inline-block;
  font-size: 12px;
  font-weight: 600;
  background: #edf4f0;
  color: #5c7e6e;
  padding: 4px 10px;
  border-radius: 6px;
}

/* PREVIEW 2: REVENUE DEMO */
.pl-rev-demo-card {
  background: #f8fafc;
  padding: 24px;
  border-radius: 18px;
}

.pl-rev-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.pl-rev-head h4 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.pl-rev-table {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pl-rev-row {
  display: flex;
  justify-content: space-between;
  font-size: 13.5px;
  color: #475569;
  background: #ffffff;
  padding: 12px 16px;
  border-radius: 10px;
}

.pl-rev-row.total {
  background: #edf4f0;
  color: #0f172a;
  font-weight: 700;
  font-size: 15px;
}

.text-sage {
  color: #5c7e6e;
}

/* PREVIEW 3: SEO DEMO */
.pl-seo-demo-card {
  background: #f8fafc;
  padding: 24px;
  border-radius: 18px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pl-google-search-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
  padding: 10px 16px;
  border-radius: 999px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.pl-google-icon {
  font-weight: 800;
  color: #4285f4;
  font-size: 13px;
}

.pl-google-query {
  font-size: 13.5px;
  color: #1e293b;
}

.pl-google-result-item {
  background: #ffffff;
  padding: 20px;
  border-radius: 14px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.pl-google-url {
  font-size: 11.5px;
  color: #64748b;
  display: block;
  margin-bottom: 4px;
}

.pl-google-result-item h4 {
  font-size: 16px;
  font-weight: 700;
  color: #1a0dab;
  margin: 0 0 6px;
}

.pl-google-rating {
  font-size: 12px;
  color: #e37400;
  font-weight: 600;
  margin-bottom: 8px;
}

.pl-google-snippet {
  font-size: 13px;
  line-height: 1.55;
  color: #475569;
  margin: 0;
}

/* FAQ SECTION */
.pl-faq-wrap {
  max-width: 860px;
  margin: 0 auto;
}

.pl-faq-title {
  font-size: clamp(24px, 3vw, 30px);
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 10px;
}

.pl-faq-desc {
  font-size: 15px;
  color: #64748b;
  margin: 0;
}

.pl-faq-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.pl-faq-item {
  background: #ffffff;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.02);
  transition: all 0.15s ease;
}

.pl-faq-q {
  width: 100%;
  padding: 20px 24px;
  background: #ffffff;
  border: none;
  text-align: left;
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

.pl-faq-toggle-icon {
  font-size: 20px;
  font-weight: 700;
  color: #5c7e6e;
}

.pl-faq-a {
  padding: 0 24px 20px;
  background: #ffffff;
  color: #475569;
  font-size: 14px;
  font-weight: 400;
  line-height: 1.65;
}

.pl-faq-a p {
  margin: 0;
}

/* FINAL CTA BANNER */
.pl-final-cta {
  padding: 60px 24px 90px;
}

.pl-cta-box {
  max-width: 900px;
  margin: 0 auto;
  background: #54656f;
  color: #ffffff;
  padding: 48px 36px;
  border-radius: 16px;
  text-align: center;
  box-shadow: 0 10px 30px rgba(84, 101, 111, 0.25);
}

.pl-cta-box h2 {
  font-size: 30px;
  font-weight: 500;
  margin-bottom: 14px;
  color: #ffffff;
}

.pl-cta-box p {
  font-size: 16px;
  font-weight: 400;
  color: #ffffff;
  max-width: 600px;
  margin: 0 auto 28px;
}

@media (max-width: 1024px) {
  .pl-features-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .pl-illustration-row {
    grid-template-columns: 1fr;
  }
  .pl-calc-container {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .pl-hero-title {
    font-size: 30px;
  }
  .pl-features-grid {
    grid-template-columns: 1fr;
  }
  .pl-venue-preview-header {
    grid-template-columns: 1fr;
  }
  .pl-slots-header, .pl-slots-row {
    grid-template-columns: 80px repeat(4, 120px);
    overflow-x: auto;
  }
}
</style>
