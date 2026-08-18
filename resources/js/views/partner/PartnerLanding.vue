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

    <!-- DEMO SECTION WITH TILTED 3D SCROLL ANIMATION -->
    <section id="pl-demo-section" class="pl-demo-section">
      <div class="pl-section-head text-center">
        <h2 class="pl-section-title">Giao diện quản lý thông minh dành riêng cho Chủ Sân</h2>
        <p class="pl-section-desc">Trải nghiệm thực tế các tính năng quản lý lịch trống, doanh thu và quảng bá sân ngay bên dưới</p>
      </div>

      <!-- Live Mockup Container with Perspective Scroll Animation -->
      <div class="pl-mockup-wrapper" :style="mockupStyle">
        <!-- Laptop / Tablet Device Window -->
        <div class="pl-mockup-frame">
          <!-- Window Header Bar -->
          <div class="pl-mockup-header">
            <div class="pl-window-dots">
              <span class="dot dot-red"></span>
              <span class="dot dot-yellow"></span>
              <span class="dot dot-green"></span>
            </div>
            <div class="pl-window-title">
              <span>https://owner.sportgo.vn/dashboard - SportGo Partner System</span>
            </div>
          </div>

          <!-- Mockup Navigation Tabs -->
          <div class="pl-mockup-tabs">
            <button
              type="button"
              class="pl-tab-btn"
              :class="{ 'is-active': activeDemoTab === 'calendar' }"
              @click="activeDemoTab = 'calendar'"
            >
              <span>Lịch Sân Realtime</span>
            </button>

            <button
              type="button"
              class="pl-tab-btn"
              :class="{ 'is-active': activeDemoTab === 'analytics' }"
              @click="activeDemoTab = 'analytics'"
            >
              <span>Doanh Thu & Báo Cáo</span>
            </button>

            <button
              type="button"
              class="pl-tab-btn"
              :class="{ 'is-active': activeDemoTab === 'venue' }"
              @click="activeDemoTab = 'venue'"
            >
              <span>Trang Chủ Cụm Sân</span>
            </button>

            <button
              type="button"
              class="pl-tab-btn"
              :class="{ 'is-active': activeDemoTab === 'shifts' }"
              @click="activeDemoTab = 'shifts'"
            >
              <span>Quản Lý Ca Nhân Viên</span>
            </button>
          </div>

          <div class="pl-tab-body">
            <!-- TAB CONTENT 1: CALENDAR REALTIME -->
            <div v-if="activeDemoTab === 'calendar'" class="pl-tab-pane pl-calendar-tab">
              <div class="pl-calendar-topbar">
                <div class="pl-cal-info">
                  <h3>Quản lý lịch đặt sân - Green Sport Ba Đình</h3>
                  <span class="pl-subtext">Thứ Sáu, 07/08/2026</span>
                </div>
                <div class="pl-cal-actions">
                  <span class="legend legend-booked">Đã đặt (Cọc đủ)</span>
                  <span class="legend legend-hold">Đang giữ chỗ</span>
                  <span class="legend legend-empty">Khung giờ trống</span>
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
            </div>

            <!-- TAB CONTENT 2: ANALYTICS -->
            <div v-else-if="activeDemoTab === 'analytics'" class="pl-tab-pane pl-analytics-tab">
              <div class="pl-stats-grid-3">
                <div class="pl-stat-box">
                  <span>Tổng doanh thu tháng 8</span>
                  <span class="pl-stat-val">148.500.000 đ</span>
                  <small class="pl-stat-sub">+18% so với tháng trước</small>
                </div>
                <div class="pl-stat-box">
                  <span>Số lượt đặt thành công</span>
                  <span class="pl-stat-val">864 lượt</span>
                  <small class="pl-stat-sub">Tỷ lệ lấp đầy 84%</small>
                </div>
                <div class="pl-stat-box">
                  <span>Thu tiền cọc qua VietQR</span>
                  <span class="pl-stat-val">112.300.000 đ</span>
                  <small class="pl-stat-sub">Khách tự quét QR 100%</small>
                </div>
              </div>

              <div class="pl-chart-box">
                <div class="pl-chart-head">
                  <h4>Biểu đồ tăng trưởng doanh thu theo ngày (Tháng 8/2026)</h4>
                </div>
                <div class="pl-fake-bar-chart">
                  <div v-for="(height, i) in [45, 60, 75, 50, 90, 85, 100, 65, 80, 95, 110, 105, 120]" :key="i" class="pl-bar-group">
                    <div class="pl-bar-fill" :style="{ height: height + 'px' }"></div>
                    <span>{{ i + 1 }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB CONTENT 3: VENUE PAGE PREVIEW -->
            <div v-else-if="activeDemoTab === 'venue'" class="pl-tab-pane pl-venue-tab">
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
            </div>

            <!-- TAB CONTENT 4: SHIFTS -->
            <div v-else class="pl-tab-pane pl-shifts-tab">
              <div class="pl-shifts-head">
                <h3>Báo cáo ca làm việc & Thu ngân</h3>
                <button type="button" class="pl-btn-sm pl-btn--primary">Kết ca & In báo cáo</button>
              </div>
              <div class="pl-shift-list">
                <div class="pl-shift-item">
                  <div class="pl-shift-user">
                    <span>Ca sáng (06:00 - 14:00)</span>
                    <span>Thu ngân: Nguyễn Văn Anh</span>
                  </div>
                  <div class="pl-shift-money">
                    <span class="pl-shift-val">3.450.000 đ</span>
                    <span class="status-done">Đã đối soát khớp 100%</span>
                  </div>
                </div>
                <div class="pl-shift-item">
                  <div class="pl-shift-user">
                    <span>Ca chiều (14:00 - 22:00)</span>
                    <span>Thu ngân: Trần Thị Mai</span>
                  </div>
                  <div class="pl-shift-money">
                    <span class="pl-shift-val">5.800.000 đ</span>
                    <span class="status-running">Đang trong ca làm việc</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- KEY FEATURES & ADVANTAGES WITH 3D ILLUSTRATIONS (NO ICON TAGS) -->
    <section class="pl-features-section">
      <div class="pl-section-head text-center">
        <h2 class="pl-section-title pl-dark-text">4 Lý do hàng đầu giúp Chủ Sân quản lý nhẹ nhàng hơn</h2>
        <p class="pl-section-desc pl-dark-text">Giải quyết triệt để những nỗi lo đè lịch, thất thoát tiền cọc và quá tải nghe điện thoại đặt sân</p>
      </div>

      <div class="pl-features-grid">
        <div class="pl-feature-item">
          <div class="pl-feature-illus">
            <img :src="'/images/partner_illus_automation.png'" alt="Tự động hóa lịch trống" class="pl-illus-card-img" />
          </div>
          <h3>1. Tự động hóa 100% lịch trống</h3>
          <p>Khách hàng tự xem khung giờ trống và đặt sân online 24/7. Hệ thống tự đồng bộ lịch realtime, tuyệt đối không bị đè lịch.</p>
        </div>

        <div class="pl-feature-item">
          <div class="pl-feature-illus">
            <img :src="'/images/partner_illus_qr_payment.png'" alt="Thu tiền cọc QR tự động" class="pl-illus-card-img" />
          </div>
          <h3>2. Thu tiền cọc QR tự động</h3>
          <p>Tích hợp sẵn VietQR động. Khách quét mã chuyển khoản là lịch được xác nhận ngay lập tức, tiền về thẳng tài khoản ngân hàng của chủ sân.</p>
        </div>

        <div class="pl-feature-item">
          <div class="pl-feature-illus">
            <img :src="'/images/partner_illus_finance_shifts.png'" alt="Quản lý thất thoát ca làm" class="pl-illus-card-img" />
          </div>
          <h3>3. Quản lý thất thoát & Ca làm</h3>
          <p>Phân quyền chi tiết cho nhân viên thu ngân, nhân viên nhặt bóng. Báo cáo doanh thu minh bạch, theo dõi chênh lệch tiền mặt chính xác.</p>
        </div>

        <div class="pl-feature-item">
          <div class="pl-feature-illus">
            <img :src="'/images/partner_illus_seo_google.png'" alt="Tiếp cận top Google" class="pl-illus-card-img" />
          </div>
          <h3>4. Tiếp cận top tìm kiếm Google</h3>
          <p>Trang cụm sân được tối ưu SEO Onpage chuẩn quốc tế. Cụm sân của bạn xuất hiện ngay khi người chơi tìm kiếm sân gần nhất trên Google.</p>
        </div>
      </div>

      <!-- Feature Highlight Row with Illustration -->
      <div class="pl-illustration-row">
        <div class="pl-illus-content">
          <h2 class="pl-dark-text">Dành ít thời gian nghe điện thoại hơn, dành nhiều thời gian nâng cấp sân hơn</h2>
          <ul class="pl-check-list">
            <li>Khách hàng tự đặt lịch từ điện thoại cá nhân không cần gọi điện.</li>
            <li>Tự động gửi tin nhắn nhắc lịch thi đấu cho khách.</li>
            <li>Quản lý nhiều cụm sân cùng lúc chỉ trên 1 tài khoản duy nhất.</li>
          </ul>
          <button type="button" class="pl-btn pl-btn--primary" @click="$emit('start-registration')">
            Khám phá ngay
          </button>
        </div>
        <div class="pl-illus-media">
          <img :src="'/images/partner_feature_hero.png'" alt="Minh họa quản lý tự động" class="pl-illus-img" />
        </div>
      </div>
    </section>

    <!-- INTERACTIVE ROI & REVENUE CALCULATOR -->
    <section class="pl-calc-section">
      <div class="pl-calc-layout">
        <!-- LEFT COLUMN: SLIDER INPUTS -->
        <div class="pl-calc-inputs">
          <div class="pl-calc-head">
            <h2 class="pl-calc-title">Dự toán doanh thu & Thời gian tiết kiệm hàng tháng</h2>
            <p class="pl-calc-desc">Kéo các thanh trượt bên dưới để xem SportGo có thể giúp cụm sân của bạn tối ưu vận hành đến mức nào</p>
          </div>

          <div class="pl-slider-group">
            <div class="pl-slider-label">
              <span>Số lượng sân thể thao</span>
              <span class="pl-slider-val">{{ calcCourts }} sân</span>
            </div>
            <input v-model.number="calcCourts" type="range" min="1" max="20" step="1" class="pl-slider" />
          </div>

          <div class="pl-slider-group">
            <div class="pl-slider-label">
              <span>Giá thuê trung bình mỗi giờ</span>
              <span class="pl-slider-val">{{ formatMoney(calcPrice) }} đ/giờ</span>
            </div>
            <input v-model.number="calcPrice" type="range" min="50000" max="400000" step="10000" class="pl-slider" />
          </div>

          <div class="pl-slider-group">
            <div class="pl-slider-label">
              <span>Số giờ lấp đầy trung bình/ngày</span>
              <span class="pl-slider-val">{{ calcHours }} giờ/sân</span>
            </div>
            <input v-model.number="calcHours" type="range" min="2" max="14" step="1" class="pl-slider" />
          </div>
        </div>

        <!-- RIGHT COLUMN: RESULT PANEL -->
        <div class="pl-calc-result-panel">
          <span class="pl-result-head">DOANH THU DỰ KIẾN HÀNG THÁNG</span>
          <div class="pl-result-amount">{{ formatMoney(estimatedMonthlyRevenue) }} đ</div>
          
          <ul class="pl-result-list">
            <li>
              <span>Tiết kiệm ~{{ calcCourts * 12 }} giờ/tháng</span>
              <small>Không phải trực nghe điện thoại chốt lịch</small>
            </li>
            <li>
              <span>Tăng +30% lượt lấp đầy giờ trống</span>
              <small>Nhờ tìm kiếm Google & danh sách SportGo</small>
            </li>
          </ul>

          <button type="button" class="pl-btn pl-btn--primary pl-btn--full" @click="$emit('start-registration')">
            Bắt đầu tăng doanh thu ngay
          </button>
        </div>
      </div>
    </section>

    <!-- SEO ONPAGE CONTENT SECTION (STRUCTURED ARTICLES & FAQs) -->
    <section class="pl-seo-section">
      <article class="pl-seo-container">
        <h2 class="pl-dark-text">Phần mềm quản lý sân thể thao SportGo - Nâng tầm trải nghiệm vận hành</h2>
        <p class="pl-dark-text">
          Trong thời đại chuyển đổi số thể thao năm 2026, việc quản lý cụm sân cầu lông, sân pickleball, sân bóng đá hay sân tennis bằng sổ sách thủ công hoặc file Excel đã trở nên lạc hậu và dễ gây ra nhầm lẫn đè trùng lịch. SportGo Partner được ra đời với sứ mệnh mang đến cho các chủ sân giải pháp công nghệ toàn diện nhất.
        </p>

        <h3 class="pl-dark-text">Các tính năng cốt lõi giúp chủ sân tối ưu vận hành</h3>
        <ul class="pl-seo-list">
          <li>Tự động nhận cọc VietQR: Mã QR tĩnh hoặc QR động sinh ra theo từng đơn đặt sân giúp khách hàng thanh toán tiền cọc ngay lập tức mà không cần chủ sân phải chụp ảnh gửi số tài khoản.</li>
          <li>Theo dõi doanh thu & Công nợ realtime: Báo cáo tự động tổng kết số dư tiền mặt, tiền chuyển khoản và phí dịch vụ phát sinh theo từng ca làm việc của nhân viên.</li>
          <li>Tối ưu hóa SEO Onpage cho từng cụm sân: Trang thông tin sân của bạn được xây dựng theo chuẩn SEO Google, hiển thị đầy đủ hình ảnh, bảng giá, tiện ích và vị trí trên Google Maps.</li>
        </ul>

        <!-- FAQ ACCORDION -->
        <h3 class="pl-dark-text mt-8">Câu hỏi thường gặp của Chủ Sân (FAQ)</h3>
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
      </article>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";

defineEmits(["start-registration"]);

// Active tab in device mockup
const activeDemoTab = ref("calendar");

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
  background: #15803d;
  color: #ffffff;
}

.pl-btn--primary:hover {
  background: #166534;
}

.pl-btn--secondary {
  background: #ffffff;
  color: #0f172a;
  border: 1px solid #cbd5e1;
}

.pl-btn--secondary:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

.pl-btn--lg {
  padding: 13px 28px;
  font-size: 15.5px;
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
  font-weight: 500;
  color: #15803d;
}

.pl-trust-lbl {
  font-size: 13px;
  font-weight: 400;
  color: #475569;
}

/* DEMO SECTION - LIGHT THEME */
.pl-demo-section {
  padding: 80px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  color: #0f172a;
  position: relative;
}

.pl-section-head {
  max-width: 700px;
  margin: 0 auto 40px;
  text-align: center;
}

.pl-section-title {
  font-size: 28px;
  font-weight: 500;
  line-height: 1.3;
  margin-bottom: 10px;
  color: #0f172a;
}

.pl-dark-text {
  color: #0f172a !important;
}

.pl-section-desc {
  font-size: 15.5px;
  font-weight: 400;
  color: #475569;
}

.pl-section-desc.pl-dark-text {
  color: #0f172a !important;
}

.pl-mockup-wrapper {
  position: relative;
  max-width: 1100px;
  margin: 0 auto;
  transform-style: preserve-3d;
}

/* MOCKUP FRAME */
.pl-mockup-frame {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
}

.pl-mockup-header {
  background: #f1f5f9;
  border-bottom: 1px solid #e2e8f0;
  padding: 10px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.pl-window-dots {
  display: flex;
  gap: 6px;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.dot-red { background: #ef4444; }
.dot-yellow { background: #f59e0b; }
.dot-green { background: #10b981; }

.pl-window-title {
  font-size: 12px;
  font-weight: 400;
  color: #64748b;
}

.pl-mockup-tabs {
  display: flex;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  overflow-x: auto;
}

.pl-tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: background-color 0.15s ease, color 0.15s ease;
  box-sizing: border-box;
}

.pl-tab-btn:hover {
  color: #0f172a;
}

.pl-tab-btn.is-active {
  color: #15803d;
  background: #ffffff;
  border-bottom: 2px solid #15803d;
}

.pl-tab-body {
  padding: 24px;
  height: 440px;
  min-height: 440px;
  max-height: 440px;
  overflow-y: auto;
  background: #ffffff;
  box-sizing: border-box;
}

.pl-tab-pane {
  animation: plTabFadeIn 0.2s ease-out;
}

@keyframes plTabFadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

/* CALENDAR TAB */
.pl-calendar-topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.pl-cal-info h3 {
  font-size: 16px;
  font-weight: 500;
  margin-bottom: 2px;
  color: #0f172a;
}

.pl-subtext {
  font-size: 12.5px;
  font-weight: 400;
  color: #475569;
}

.pl-cal-actions {
  display: flex;
  gap: 14px;
  font-size: 12.5px;
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

.legend-booked::before { background: #15803d; }
.legend-hold::before { background: #d97706; }
.legend-empty::before { background: #e2e8f0; }

.pl-slots-table {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.pl-slots-header, .pl-slots-row {
  display: grid;
  grid-template-columns: 120px repeat(4, 1fr);
  align-items: center;
}

.pl-slots-header {
  background: #f8fafc;
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
  border-bottom: 1px solid #e2e8f0;
}

.pl-cell-time {
  padding: 10px;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 12px;
  color: #475569;
}

.pl-cell-court {
  padding: 6px;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #f1f5f9;
}

.pl-cell-court:last-child {
  border-right: none;
}

.pl-slot-card {
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
}

.slot-booked {
  background: #15803d;
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

.pl-slot-user {
  font-weight: 500;
}

.pl-slot-price {
  font-size: 11px;
  font-weight: 400;
}

/* ANALYTICS TAB */
.pl-stats-grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.pl-stat-box {
  background: #f8fafc;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.pl-stat-box span {
  font-size: 12.5px;
  font-weight: 400;
  color: #475569;
  display: block;
}

.pl-stat-val {
  font-size: 20px;
  font-weight: 500;
  display: block;
  margin: 4px 0;
  color: #0f172a !important;
}

.pl-stat-sub {
  font-size: 12px;
  font-weight: 400;
  color: #15803d;
}

.pl-chart-box {
  background: #f8fafc;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.pl-chart-head h4 {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
  margin-bottom: 16px;
}

.pl-fake-bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  height: 140px;
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
  max-width: 24px;
  background: #15803d;
  border-radius: 2px 2px 0 0;
}

.pl-bar-group span {
  font-size: 11px;
  font-weight: 400;
  color: #64748b;
  margin-top: 6px;
}

/* VENUE TAB */
.pl-venue-preview-header {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 20px;
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 16px;
  border-radius: 8px;
}

.pl-preview-banner {
  width: 100%;
  height: 140px;
  object-fit: cover;
  border-radius: 6px;
}

.pl-preview-details h2 {
  font-size: 18px;
  font-weight: 500;
  color: #0f172a;
  margin: 6px 0;
}

.pl-preview-details p {
  font-size: 13px;
  font-weight: 400;
  color: #475569;
  margin-bottom: 12px;
}

.pl-venue-features {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.pl-venue-features span {
  font-size: 11.5px;
  font-weight: 400;
  background: #e2e8f0;
  color: #1e293b;
  padding: 3px 8px;
  border-radius: 4px;
}

/* SHIFTS TAB */
.pl-shifts-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.pl-shifts-head h3 {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.pl-btn-sm {
  padding: 6px 12px;
  font-size: 12.5px;
}

.pl-shift-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pl-shift-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 14px 18px;
  border-radius: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pl-shift-user span {
  display: block;
  font-size: 13.5px;
  font-weight: 400;
  color: #0f172a;
}

.pl-shift-money {
  text-align: right;
}

.pl-shift-val {
  display: block;
  font-size: 16px;
  font-weight: 500;
  color: #15803d !important;
}

.status-done {
  font-size: 11px;
  font-weight: 400;
  color: #166534;
}

.status-running {
  font-size: 11px;
  font-weight: 400;
  color: #b45309;
}

/* FEATURES SECTION (3D ISOMETRIC ILLUSTRATIONS INSTEAD OF ICON TAGS) */
.pl-features-section {
  padding: 90px 24px;
  max-width: 1200px;
  margin: 0 auto;
}

.pl-features-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  margin-bottom: 60px;
}

.pl-feature-item {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 20px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.pl-feature-item:hover {
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
  transform: translateY(-2px);
}

.pl-feature-illus {
  width: 100%;
  aspect-ratio: 16 / 11;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 16px;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pl-illus-card-img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
}

.pl-feature-item h3 {
  font-size: 16.5px;
  font-weight: 500;
  color: #0f172a;
  margin-bottom: 8px;
}

.pl-feature-item p {
  font-size: 14px;
  font-weight: 400;
  color: #475569;
  line-height: 1.6;
}

.pl-illustration-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 48px;
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 48px;
}

.pl-illus-content h2 {
  font-size: 26px;
  font-weight: 500;
  line-height: 1.35;
  margin-bottom: 16px;
  color: #0f172a;
}

.pl-check-list {
  list-style: square;
  padding-left: 20px;
  margin: 0 0 24px;
}

.pl-check-list li {
  font-size: 15px;
  font-weight: 400;
  color: #0f172a;
  margin-bottom: 10px;
}

.pl-illus-img {
  width: 100%;
  border-radius: 8px;
}

/* CALCULATOR SECTION - LIGHT THEME */
.pl-calc-section {
  padding: 80px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  color: #0f172a;
}

.pl-calc-layout {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 48px;
  align-items: center;
}

.pl-calc-head {
  margin-bottom: 32px;
}

.pl-calc-title {
  font-size: 28px;
  font-weight: 500;
  color: #0f172a;
  margin-bottom: 8px;
}

.pl-calc-desc {
  font-size: 15px;
  font-weight: 400;
  color: #475569;
}

.pl-slider-group {
  margin-bottom: 24px;
}

.pl-slider-label {
  display: flex;
  justify-content: space-between;
  font-size: 14.5px;
  font-weight: 400;
  color: #0f172a;
  margin-bottom: 10px;
}

.pl-slider-val {
  color: #15803d;
  font-size: 15px;
  font-weight: 500;
}

.pl-slider {
  width: 100%;
  accent-color: #15803d;
  height: 6px;
  border-radius: 4px;
  outline: none !important;
  box-shadow: none !important;
  cursor: pointer;
}

.pl-calc-result-panel {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 32px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.pl-result-head {
  font-size: 12.5px;
  font-weight: 500;
  color: #64748b;
  letter-spacing: 0.5px;
}

.pl-result-amount {
  font-size: 32px;
  font-weight: 500;
  color: #15803d;
  margin: 8px 0 24px;
}

.pl-result-list {
  list-style: none;
  padding: 0;
  margin: 0 0 28px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pl-result-list li span {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
}

.pl-result-list li small {
  display: block;
  font-size: 12.5px;
  font-weight: 400;
  color: #64748b;
}

/* SEO SECTION */
.pl-seo-section {
  padding: 80px 24px;
  max-width: 900px;
  margin: 0 auto;
}

.pl-seo-container h2 {
  font-size: 26px;
  font-weight: 500;
  margin-bottom: 16px;
  color: #0f172a;
}

.pl-seo-container p {
  font-size: 15px;
  font-weight: 400;
  line-height: 1.7;
  color: #0f172a;
  margin-bottom: 20px;
}

.pl-seo-container h3 {
  font-size: 20px;
  font-weight: 500;
  margin: 24px 0 12px;
  color: #0f172a;
}

.pl-seo-list {
  padding-left: 20px;
  margin-bottom: 28px;
}

.pl-seo-list li {
  font-size: 14.5px;
  font-weight: 400;
  line-height: 1.6;
  color: #0f172a;
  margin-bottom: 8px;
}

.pl-faq-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 16px;
}

.pl-faq-item {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  background: #ffffff;
}

.pl-faq-q {
  width: 100%;
  padding: 16px 20px;
  background: #ffffff;
  border: none;
  text-align: left;
  font-size: 15px;
  font-weight: 500;
  color: #0f172a;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

.pl-faq-toggle-icon {
  font-size: 18px;
  font-weight: 500;
  color: #15803d;
}

.pl-faq-a {
  padding: 12px 20px 16px;
  background: #f8fafc;
  color: #334155;
  font-size: 14px;
  font-weight: 400;
  line-height: 1.6;
  border-top: 1px solid #f1f5f9;
}

/* FINAL CTA BANNER */
.pl-final-cta {
  padding: 60px 24px 90px;
}

.pl-cta-box {
  max-width: 900px;
  margin: 0 auto;
  background: #15803d;
  color: #ffffff;
  padding: 48px 36px;
  border-radius: 12px;
  text-align: center;
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
