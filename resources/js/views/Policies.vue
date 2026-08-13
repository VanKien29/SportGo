<template>
  <div class="sg-policy-page">
    <PublicNavbar />

    <!-- ───── HERO HEADER ───── -->
    <section class="sg-policy-hero">
      <div class="sg-container text-center">
        <h1 class="sg-policy-hero__title">Chính Sách & Điều Khoản SportGo</h1>
        <p class="sg-policy-hero__subtitle">
          Quy định minh bạch bảo vệ quyền lợi người chơi, chủ sân và đảm bảo trải nghiệm dịch vụ chuẩn mực.
        </p>

        <!-- Search Bar -->
        <div class="sg-policy-search">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tìm kiếm từ khóa trong điều khoản chính sách..."
            class="sg-policy-search__input"
          />
        </div>
      </div>
    </section>

    <!-- ───── MAIN POLICY LAYOUT ───── -->
    <section class="sg-policy-body">
      <div class="sg-container sg-policy-layout">
        <!-- Sidebar Navigation -->
        <aside class="sg-policy-sidebar">
          <div class="sg-sidebar-title">Danh Mục Chính Sách</div>
          <nav class="sg-sidebar-nav">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              class="sg-sidebar-item"
              :class="{ 'is-active': activeTab === tab.id }"
              @click="activeTab = tab.id"
            >
              <span>{{ tab.name }}</span>
            </button>
          </nav>
        </aside>

        <!-- Content Area -->
        <main class="sg-policy-content">
          <!-- TOP ACTION BAR -->
          <div class="sg-policy-action-bar">
            <span class="sg-last-updated">Cập nhật lần cuối: {{ currentPolicy.lastUpdated }}</span>
            <button @click="printPolicy" class="sg-btn-print">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
              </svg>
              <span>In / Tải PDF</span>
            </button>
          </div>

          <!-- POLICY ARTICLES -->
          <div class="sg-policy-document">
            <h2 class="sg-doc-title">{{ currentPolicy.title }}</h2>
            <div class="sg-doc-intro">{{ currentPolicy.intro }}</div>

            <div v-for="(section, idx) in filteredSections" :key="idx" class="sg-doc-section">
              <h3 class="sg-doc-section__heading">{{ idx + 1 }}. {{ section.heading }}</h3>
              <p v-for="(p, pIdx) in section.paragraphs" :key="pIdx" class="sg-doc-paragraph">
                {{ p }}
              </p>
              <ul v-if="section.bullets" class="sg-doc-list">
                <li v-for="(b, bIdx) in section.bullets" :key="bIdx">{{ b }}</li>
              </ul>
            </div>
          </div>
        </main>
      </div>
    </section>

    <ClientFooter />
  </div>
</template>

<script>
import PublicNavbar from "../components/PublicNavbar.vue";
import ClientFooter from "../components/ClientFooter.vue";

export default {
  name: "PoliciesView",
  components: { PublicNavbar, ClientFooter },
  data() {
    return {
      activeTab: "terms",
      searchQuery: "",
      tabs: [
        { id: "terms", name: "Điều Khoản Sử Dụng" },
        { id: "privacy", name: "Chính Sách Bảo Mật" },
        { id: "refund", name: "Hủy Lịch & Hoàn Tiền" },
        { id: "partner", name: "Quy Định Dành Cho Chủ Sân" },
        { id: "community", name: "Quy Tắc Ứng Xử Cộng Đồng" },
      ],
      policyData: {
        terms: {
          title: "Điều Khoản Sử Dụng Nền Tảng SportGo",
          lastUpdated: "01/01/2026",
          intro: "Chào mừng bạn đến với SportGo. Việc bạn sử dụng nền tảng đặt sân và kết nối cộng đồng thể thao đồng nghĩa với việc bạn chấp thuận hoàn toàn các điều khoản bên dưới.",
          sections: [
            {
              heading: "Tài Khoản & Đăng Ký Người Dùng",
              paragraphs: [
                "Người dùng cần cung cấp thông tin số điện thoại và họ tên chính xác khi đăng ký tài khoản.",
                "Bạn có trách nhiệm tự bảo mật mật khẩu và mã OTP xác thực. SportGo không chịu trách nhiệm đối với các tổn thất phát sinh do bạn làm lộ thông tin đăng nhập.",
              ],
            },
            {
              heading: "Quy Định Đặt Sân & Giữ Chỗ",
              paragraphs: [
                "Mọi đơn đặt sân chỉ được xác nhận chính thức sau khi hệ thống ghi nhận khoản tiền cọc hoặc thanh toán thành công.",
                "Hệ thống sẽ cấp mã vé QR điện tử có hiệu lực check-in tại cụm sân tương ứng.",
              ],
            },
            {
              heading: "Quyền & Trách Nhiệm Của SportGo",
              paragraphs: [
                "SportGo cam kết bảo trì hệ thống hoạt động ổn định 24/7 và minh bạch thông tin lịch trống.",
                "SportGo có quyền tạm khóa tài khoản có hành vi gian lận, cố tình tạo đơn ảo hoặc vi phạm quy tắc cộng đồng.",
              ],
            },
          ],
        },
        privacy: {
          title: "Chính Sách Bảo Mật Thông Tin Cá Nhân",
          lastUpdated: "01/01/2026",
          intro: "SportGo tôn trọng và cam kết bảo vệ tuyệt đối thông tin riêng tư của người dùng theo tiêu chuẩn mã hóa dữ liệu an toàn.",
          sections: [
            {
              heading: "Thu Thập Thông Tin",
              paragraphs: [
                "Chúng tôi chỉ thu thập các thông tin cần thiết phục vụ cho việc xác nhận lịch đặt sân: Họ tên, Số điện thoại, Email và Lịch sử giao dịch.",
              ],
            },
            {
              heading: "Mục Đích Sử Dụng Dữ Liệu",
              paragraphs: [
                "Gửi thông báo xác nhận đặt sân và mã vé QR check-in qua SMS/Notification.",
                "Xử lý hoàn tiền giao dịch và giải quyết khiếu nại nếu có sự cố xảy ra.",
              ],
            },
            {
              heading: "Cam Kết Bảo Mật",
              paragraphs: [
                "Dữ liệu người dùng được mã hóa SSL/TLS và lưu trữ trên hạ tầng điện toán đám mây bảo mật.",
                "SportGo không bán, chia sẻ hoặc tiết lộ dữ liệu người dùng cho bất kỳ bên thứ ba nào vì mục đích thương mại.",
              ],
            },
          ],
        },
        refund: {
          title: "Quy Định Hủy Lịch & Hoàn Tiền",
          lastUpdated: "01/01/2026",
          intro: "Chính sách hoàn tiền minh bạch giúp đảm bảo quyền lợi tối đa cho người chơi khi có thay đổi kế hoạch đột xuất.",
          sections: [
            {
              heading: "Khung Thời Gian Hủy Lịch",
              paragraphs: [
                "Hủy trước ca thi đấu từ 12 tiếng trở lên: Hoàn tiền 100% khoản cọc vào Ví SportGo.",
                "Hủy trước ca thi đấu từ 6 đến 12 tiếng: Hoàn tiền 50% khoản cọc.",
                "Hủy trong vòng 6 tiếng trước ca: Không hỗ trợ hoàn tiền nhằm bảo đảm bồi thường cho thời gian trống của cụm sân.",
              ],
            },
            {
              heading: "Trường Hợp Bất Khả Kháng (Thời Tiết / Sự Cố Sân)",
              paragraphs: [
                "Đối với sân ngoài trời bị mưa to hoặc sự cố kỹ thuật từ phía chủ sân, người chơi được HOÀN TIỀN 100% không phụ thuộc thời gian thông báo.",
              ],
            },
            {
              heading: "Hình Thức Nhận Tiền Hoàn",
              paragraphs: [
                "Tiền hoàn sẽ được chuyển tức thì vào Ví SportGo hoặc hoàn trả lại tài khoản ngân hàng trong 1-3 ngày làm việc.",
              ],
            },
          ],
        },
        partner: {
          title: "Chính Sách & Quy Định Dành Cho Chủ Sân Đối Tác",
          lastUpdated: "01/01/2026",
          intro: "Quy định đối tác nhằm thiết lập tiêu chuẩn vận hành chuyên nghiệp, nâng cao chất lượng dịch vụ cho toàn hệ thống.",
          sections: [
            {
              heading: "Trách Nhiệm Giữ Sân Đúng Ca",
              paragraphs: [
                "Chủ sân có trách nhiệm đảm bảo thảm đấu sẵn sàng đúng khung giờ khách hàng đã đặt qua hệ thống.",
                "Tuyệt đối không được bán lại ca sân đã được SportGo xác nhận thành công.",
              ],
            },
            {
              heading: "Phí Nền Tảng & Quyết Toán Doanh Thu",
              paragraphs: [
                "Doanh thu từ các ca đặt qua nền tảng sẽ được quyết toán định kỳ theo chu kỳ đã cam kết trong Hợp đồng đối tác.",
              ],
            },
          ],
        },
        community: {
          title: "Quy Tắc Ứng Xử Cộng Đồng Ghép Đội",
          lastUpdated: "01/01/2026",
          intro: "Hướng tới xây dựng văn hóa thể thao lành mạnh, lịch sự và văn minh trên mọi sân đấu.",
          sections: [
            {
              heading: "Văn Hóa Thi Đấu Lịch Sự",
              paragraphs: [
                "Tôn trọng đối thủ, trọng tài và các thành viên cùng nhóm ghép.",
                "Đến sân đúng giờ, mặc trang phục thể thao đạt chuẩn bộ môn.",
              ],
            },
            {
              heading: "Nghiêm Cấm Hành Vi Vi Phạm",
              paragraphs: [
                "Cấm tuyệt đối mọi hình thức cá độ, gian lận hoặc tranh chấp bạo lực tại khu vực thi đấu.",
              ],
            },
          ],
        },
      },
    };
  },

  computed: {
    currentPolicy() {
      return this.policyData[this.activeTab] || this.policyData.terms;
    },

    filteredSections() {
      if (!this.searchQuery.trim()) {
        return this.currentPolicy.sections;
      }
      const q = this.searchQuery.toLowerCase();
      return this.currentPolicy.sections.filter((sec) => {
        const titleMatch = sec.heading.toLowerCase().includes(q);
        const pMatch = sec.paragraphs.some((p) => p.toLowerCase().includes(q));
        return titleMatch || pMatch;
      });
    },
  },

  methods: {
    printPolicy() {
      window.print();
    },
  },
};
</script>

<style scoped>
/* =========================================================================
   POLICIES PAGE DESIGN SYSTEM
   ========================================================================= */

.sg-policy-page {
  font-family: var(--sg-font-main, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
  background: #f8fafc;
  color: #0f172a;
  min-height: 100vh;
}

/* ───── HERO HEADER ───── */
.sg-policy-hero {
  padding: 75px 0 45px;
  background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
  border-bottom: 1px solid #e2e8f0;
}

.sg-policy-hero__title {
  font-size: clamp(32px, 4.2vw, 48px);
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 14px;
}

.sg-policy-hero__subtitle {
  font-size: 17px;
  color: #334155;
  max-width: 680px;
  margin: 0 auto 32px;
  line-height: 1.6;
}

.sg-policy-search {
  position: relative;
  max-width: 580px;
  margin: 0 auto;
  display: flex;
  align-items: center;
}

.sg-policy-search svg {
  position: absolute;
  left: 18px;
  color: #64748b;
}

.sg-policy-search__input {
  width: 100%;
  padding: 14px 20px 14px 50px;
  border: 1px solid #cbd5e1;
  border-radius: 30px;
  font-size: 15px;
  color: #0f172a;
  background: #ffffff;
  outline: none;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.sg-policy-search__input:focus {
  border-color: #059669;
  box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}

/* ───── LAYOUT ───── */
.sg-policy-body {
  padding: 60px 0 90px;
}

.sg-policy-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 48px;
  align-items: flex-start;
}

/* ───── SIDEBAR ───── */
.sg-policy-sidebar {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px 16px;
  position: sticky;
  top: 90px;
}

.sg-sidebar-title {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #64748b;
  margin-bottom: 16px;
  padding-left: 12px;
}

.sg-sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sg-sidebar-item {
  display: flex;
  align-items: center;
  width: 100%;
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  color: #334155;
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: left;
  transition: background 0.2s ease, color 0.2s ease;
}

.sg-sidebar-item:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.sg-sidebar-item.is-active {
  background: #ecfdf5;
  color: #059669;
  font-weight: 600;
}

/* ───── DOCUMENT CONTENT ───── */
.sg-policy-content {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 40px 48px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.02);
}

.sg-policy-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 24px;
  margin-bottom: 32px;
  border-bottom: 1px solid #e2e8f0;
}

.sg-last-updated {
  font-size: 14px;
  color: #64748b;
}

.sg-btn-print {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  color: #334155;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s ease;
}

.sg-btn-print:hover {
  background: #f8fafc;
  color: #0f172a;
}

.sg-doc-title {
  font-size: 28px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 16px;
  line-height: 1.3;
}

.sg-doc-intro {
  font-size: 16.5px;
  color: #334155;
  line-height: 1.7;
  margin-bottom: 36px;
  padding: 18px 24px;
  background: #f8fafc;
  border-left: 4px solid #059669;
  border-radius: 0 8px 8px 0;
}

.sg-doc-section {
  margin-bottom: 36px;
}

.sg-doc-section__heading {
  font-size: 20px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 14px;
}

.sg-doc-paragraph {
  font-size: 15.5px;
  color: #334155;
  line-height: 1.75;
  margin: 0 0 12px;
}

.sg-doc-list {
  padding-left: 24px;
  margin: 12px 0;
}

.sg-doc-list li {
  font-size: 15px;
  color: #334155;
  line-height: 1.7;
  margin-bottom: 8px;
}

/* ───── RESPONSIVE ───── */
@media (max-width: 1024px) {
  .sg-policy-layout {
    grid-template-columns: 1fr;
  }
  .sg-policy-sidebar {
    position: static;
  }
  .sg-sidebar-nav {
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 8px;
  }
  .sg-sidebar-item {
    white-space: nowrap;
  }
  .sg-policy-content {
    padding: 28px 24px;
  }
}

@media print {
  .sg-policy-sidebar,
  .sg-policy-hero,
  .sg-policy-action-bar,
  PublicNavbar,
  ClientFooter {
    display: none !important;
  }
  .sg-policy-layout {
    grid-template-columns: 1fr;
  }
  .sg-policy-content {
    border: none;
    box-shadow: none;
    padding: 0;
  }
}
</style>
