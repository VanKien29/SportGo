<template>
  <div class="sg-contact-page">
    <PublicNavbar />

    <!-- ───── HERO SECTION ───── -->
    <section class="sg-contact-hero">
      <div class="sg-container text-center">
        <h1 class="sg-contact-hero__title">Liên Hệ Với SportGo</h1>
        <p class="sg-contact-hero__subtitle">
          Đội ngũ chăm sóc khách hàng và hỗ trợ đối tác của chúng tôi luôn sẵn sàng đồng hành cùng bạn 24/7.
        </p>
      </div>
    </section>

    <!-- ───── MAIN CONTACT CONTENT ───── -->
    <section class="sg-contact-body">
      <div class="sg-container">
        <!-- 3 Quick Contact Info Cards -->
        <div class="sg-contact-grid-info">
          <div class="sg-info-box">
            <div class="sg-info-box__icon">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
              </svg>
            </div>
            <h3 class="sg-info-box__title">Tổng Đài Hỗ Trợ 24/7</h3>
            <p class="sg-info-box__desc">Giải đáp thắc mắc đặt sân và xử lý sự cố tức thì.</p>
            <a href="tel:19006789" class="sg-info-box__link">1900 6789 (Miễn phí)</a>
          </div>

          <div class="sg-info-box">
            <div class="sg-info-box__icon">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
              </svg>
            </div>
            <h3 class="sg-info-box__title">Email Hỗ Trợ & Hợp Tác</h3>
            <p class="sg-info-box__desc">Gửi yêu cầu hỗ trợ chi tiết hoặc đề xuất hợp tác.</p>
            <a href="mailto:support@sportgo.vn" class="sg-info-box__link">support@sportgo.vn</a>
          </div>

          <div class="sg-info-box">
            <div class="sg-info-box__icon">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
            </div>
            <h3 class="sg-info-box__title">Trụ Sở Văn Phòng</h3>
            <p class="sg-info-box__desc">Tòa nhà SportGo Tower, Cầu Giấy, Hà Nội & Quận 1, TP. Hồ Chí Minh.</p>
            <span class="sg-info-box__subtext">Giờ mở cửa: 08:00 - 21:00 (T2 - CN)</span>
          </div>
        </div>

        <!-- Contact Form & Map Section -->
        <div class="sg-contact-layout">
          <!-- Left: Interactive Contact Form -->
          <div class="sg-contact-form-container">
            <h2 class="sg-form-title">Gửi Tin Nhắn Cho Chúng Tôi</h2>
            <p class="sg-form-sub">Vui lòng điền thông tin bên dưới, chúng tôi sẽ phản hồi trong vòng 15 phút.</p>

            <form @submit.prevent="submitForm" class="sg-contact-form">
              <div class="sg-form-row">
                <div class="sg-form-group">
                  <label for="c-name" class="sg-form-label">Họ và tên *</label>
                  <input
                    id="c-name"
                    v-model="form.name"
                    type="text"
                    required
                    placeholder="Nguyễn Văn A"
                    class="sg-form-input"
                  />
                </div>
                <div class="sg-form-group">
                  <label for="c-phone" class="sg-form-label">Số điện thoại *</label>
                  <input
                    id="c-phone"
                    v-model="form.phone"
                    type="tel"
                    required
                    placeholder="0988 123 456"
                    class="sg-form-input"
                  />
                </div>
              </div>

              <div class="sg-form-row">
                <div class="sg-form-group">
                  <label for="c-email" class="sg-form-label">Địa chỉ Email *</label>
                  <input
                    id="c-email"
                    v-model="form.email"
                    type="email"
                    required
                    placeholder="example@gmail.com"
                    class="sg-form-input"
                  />
                </div>
                <div class="sg-form-group">
                  <label for="c-topic" class="sg-form-label">Chủ đề cần hỗ trợ *</label>
                  <select id="c-topic" v-model="form.topic" class="sg-form-select">
                    <option value="booking">Hỗ trợ đặt sân & thanh toán</option>
                    <option value="refund">Yêu cầu hủy lịch & hoàn tiền</option>
                    <option value="partner">Hợp tác đăng ký chủ sân</option>
                    <option value="complaint">Góp ý dịch vụ & khiếu nại</option>
                    <option value="other">Chủ đề khác</option>
                  </select>
                </div>
              </div>

              <div class="sg-form-group">
                <label for="c-message" class="sg-form-label">Nội dung tin nhắn *</label>
                <textarea
                  id="c-message"
                  v-model="form.message"
                  rows="5"
                  required
                  placeholder="Mô tả chi tiết thắc mắc hoặc yêu cầu của bạn..."
                  class="sg-form-textarea"
                ></textarea>
              </div>

              <button type="submit" class="sg-btn-submit" :disabled="submitting">
                <span v-if="!submitting">Gửi Yêu Cầu Liên Hệ</span>
                <span v-else>Đang Gửi...</span>
              </button>

              <div v-if="submittedSuccess" class="sg-form-alert sg-alert-success">
                Cảm ơn bạn! Yêu cầu liên hệ đã được gửi thành công. Đội ngũ SportGo sẽ liên hệ lại ngay.
              </div>
            </form>
          </div>

          <!-- Right: Direct FAQs Accordion -->
          <div class="sg-faq-container">
            <h2 class="sg-faq-title">Câu Hỏi Thường Gặp</h2>

            <div class="sg-faq-list">
              <div
                v-for="(faq, index) in faqs"
                :key="index"
                class="sg-faq-item"
                :class="{ 'is-open': activeFaq === index }"
                @click="toggleFaq(index)"
              >
                <div class="sg-faq-question">
                  <span>{{ faq.q }}</span>
                </div>
                <div v-if="activeFaq === index" class="sg-faq-answer">
                  {{ faq.a }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </div>
</template>

<script>
import PublicNavbar from "../components/PublicNavbar.vue";

export default {
  name: "ContactView",
  components: { PublicNavbar },
  data() {
    return {
      submitting: false,
      submittedSuccess: false,
      activeFaq: 0,
      form: {
        name: "",
        phone: "",
        email: "",
        topic: "booking",
        message: "",
      },
      faqs: [
        {
          q: "Làm thế nào để hủy lịch đặt sân và nhận hoàn tiền?",
          a: "Bạn có thể vào mục 'Lịch Sử Đặt Sân' trong tài khoản cá nhân, chọn đơn đặt cần hủy và nhấn 'Hủy Lịch'. Số tiền cọc/thanh toán sẽ được hoàn trả tự động vào Ví SportGo hoặc tài khoản ngân hàng theo đúng Chính Sách Hoàn Tiền.",
        },
        {
          q: "Tôi là chủ sân, quy trình đăng ký đối tác mất bao lâu?",
          a: "Quy trình đăng ký và duyệt đối tác diễn ra trong vòng 24 giờ làm việc. Sau khi hoàn tất form 'Đăng Ký Chủ Sân', chuyên viên SportGo sẽ xác minh thông tin sân và hỗ trợ thiết lập sơ đồ ca trực tức thì.",
        },
        {
          q: "Đặt sân trên SportGo có phát sinh thêm phụ phí không?",
          a: "Hoàn toàn không. Bảng giá thuê sân, tiền cọc và tiện ích đi kèm được niêm yết minh bạch 100%. Người chơi không phải trả bất kỳ khoản phí dịch vụ hay phụ phí ẩn nào.",
        },
        {
          q: "Làm sao để tham gia cộng đồng ghép đội giao lưu?",
          a: "Bạn truy cập mục 'Cộng Đồng' trên ứng dụng/website, tìm bài đăng ghép đối phù hợp với trình độ & khu vực của bạn, sau đó nhấn 'Tham Gia Trận' để kết nối trực tiếp với nhóm.",
        },
      ],
    };
  },
  methods: {
    toggleFaq(index) {
      this.activeFaq = this.activeFaq === index ? null : index;
    },
    async submitForm() {
      this.submitting = true;
      this.submittedSuccess = false;

      // Simulate API response
      await new Promise((resolve) => setTimeout(resolve, 800));

      this.submitting = false;
      this.submittedSuccess = true;
      this.form = {
        name: "",
        phone: "",
        email: "",
        topic: "booking",
        message: "",
      };
    },
  },
};
</script>

<style scoped>
/* =========================================================================
   CONTACT PAGE DESIGN SYSTEM (CLEAN LIGHT THEME)
   ========================================================================= */

.sg-contact-page {
  font-family: var(--sg-font-main, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
  background: #f8fafc;
  color: #0f172a;
  min-height: 100vh;
}

/* ───── HERO SECTION ───── */
.sg-contact-hero {
  padding: 80px 0 50px;
  background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
}

.sg-contact-hero__title {
  font-size: clamp(34px, 4.5vw, 52px);
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 16px;
  letter-spacing: -0.02em;
}

.sg-contact-hero__subtitle {
  font-size: 18px;
  color: #334155;
  max-width: 680px;
  margin: 0 auto;
  line-height: 1.6;
}

/* ───── BODY CONTENT ───── */
.sg-contact-body {
  padding: 70px 0 100px;
}

/* ───── 3 INFO CARDS ───── */
.sg-contact-grid-info {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 32px;
  margin-bottom: 70px;
}

.sg-info-box {
  background: #ffffff;
  border-radius: 12px;
  padding: 32px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.sg-info-box:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}

.sg-info-box__icon {
  width: 54px;
  height: 54px;
  background: #ecfdf5;
  color: #059669;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

.sg-info-box__title {
  font-size: 20px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 10px;
}

.sg-info-box__desc {
  font-size: 15px;
  color: #475569;
  line-height: 1.6;
  margin: 0 0 16px;
  flex-grow: 1;
}

.sg-info-box__link {
  font-size: 16px;
  font-weight: 600;
  color: #059669;
  text-decoration: none;
}
.sg-info-box__link:hover {
  text-decoration: underline;
}

.sg-info-box__subtext {
  font-size: 14px;
  color: #64748b;
}

/* ───── FORM & FAQ LAYOUT ───── */
.sg-contact-layout {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 60px;
  align-items: flex-start;
}

.sg-contact-form-container {
  background: #ffffff;
  border-radius: 16px;
  padding: 40px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

.sg-form-title {
  font-size: 26px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 8px;
}

.sg-form-sub {
  font-size: 15px;
  color: #475569;
  margin: 0 0 32px;
}

.sg-contact-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sg-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.sg-form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sg-form-label {
  font-size: 14px;
  font-weight: 600;
  color: #334155;
}

.sg-form-input,
.sg-form-select,
.sg-form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 15px;
  color: #0f172a;
  background: #ffffff;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.sg-form-input:focus,
.sg-form-select:focus,
.sg-form-textarea:focus {
  border-color: #059669;
  box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}

.sg-btn-submit {
  padding: 15px;
  background: #059669;
  color: #ffffff;
  font-size: 16px;
  font-weight: 600;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.2s ease;
  margin-top: 8px;
}

.sg-btn-submit:hover:not(:disabled) {
  background: #047857;
  transform: translateY(-1px);
}

.sg-form-alert {
  padding: 16px;
  border-radius: 8px;
  font-size: 14.5px;
  margin-top: 16px;
}

.sg-alert-success {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
}

/* ───── FAQ ACCORDION ───── */
.sg-faq-container {
  background: #ffffff;
  border-radius: 16px;
  padding: 40px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

.sg-faq-title {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 24px;
}

.sg-faq-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sg-faq-item {
  border-radius: 10px;
  padding: 18px 20px;
  cursor: pointer;
  background: #f8fafc;
  transition: background 0.2s ease;
}

.sg-faq-item:hover,
.sg-faq-item.is-open {
  border-color: #059669;
  background: #f0fdf4;
}

.sg-faq-question {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
}

.sg-faq-toggle-icon {
  font-size: 20px;
  font-weight: 500;
  color: #059669;
}

.sg-faq-answer {
  font-size: 14.5px;
  color: #334155;
  line-height: 1.65;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(5, 150, 105, 0.15);
}

/* ───── RESPONSIVE ───── */
@media (max-width: 1024px) {
  .sg-contact-grid-info {
    grid-template-columns: 1fr;
  }
  .sg-contact-layout {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}

@media (max-width: 640px) {
  .sg-form-row {
    grid-template-columns: 1fr;
  }
}
</style>
