<template>
  <footer class="alb-footer">
    <div class="sg-container alb-footer-grid">
      <!-- Col 1: Brand Info -->
      <div>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
          <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 16px;">SG</div>
          <span style="font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">Sport<span style="color: #34d399;">Go</span></span>
        </div>
        <p style="font-size: 14px; color: #cbd5e1; line-height: 1.6; margin-bottom: 20px; max-width: 320px;">
          Nền tảng chuyên biệt cho kết nối & đặt lịch trực tuyến các cụm sân Pickleball, Cầu Lông, Tennis, Bóng Đá hàng đầu Việt Nam.
        </p>
        <div style="font-size: 13.5px; color: #94a3b8; display: flex; flex-direction: column; gap: 8px;">
          <span>Hotline hỗ trợ: 1900 6789 (24/7)</span>
          <span>Email: support@sportgo.vn</span>
          <span>Trụ sở chính: Hà Nội & TP. Hồ Chí Minh</span>
        </div>
      </div>

      <!-- Col 2: DÀNH CHO NGƯỜI CHƠI -->
      <div class="alb-footer-col">
        <h4>HỆ THỐNG SÂN</h4>
        <div class="alb-footer-links">
          <router-link to="/venues">Tất Cả Cụm Sân</router-link>
          <router-link to="/venues?q=Pickleball">Sân Pickleball Pro</router-link>
          <router-link to="/venues?q=Cầu+Lông">Sân Cầu Lông BWF</router-link>
          <router-link to="/venues?q=Bóng+Đá">Sân Bóng Đá Cỏ FIFA</router-link>
          <router-link to="/venues?q=Tennis">Sân Tennis ITF</router-link>
        </div>
      </div>

      <!-- Col 3: DÀNH CHO CHỦ SÂN -->
      <div class="alb-footer-col">
        <h4>TÍNH NĂNG & DỊCH VỤ</h4>
        <div class="alb-footer-links">
          <router-link to="/become-partner">Dành Cho Chủ Sân</router-link>
          <router-link to="/bookings">Lịch Đặt Của Tôi</router-link>
          <router-link to="/offers">Ưu Đãi & Khuyến Mãi</router-link>
          <router-link to="/news">Tin Tức & Kinh Nghiệm</router-link>
          <router-link to="/community">Cộng Đồng Ghép Đội</router-link>
        </div>
      </div>

      <!-- Col 4: HỖ TRỢ & BẢO MẬT -->
      <div class="alb-footer-col">
        <h4>HỖ TRỢ KHÁCH HÀNG</h4>
        <div class="alb-footer-links">
          <a href="#support">Trung Tâm Hỗ Trợ 24/7</a>
          <a href="#">Điều Khoản Sử Dụng</a>
          <a href="#">Chính Sách Bảo Mật</a>
          <a href="#">Quy Định Hoàn Tiền</a>
          <a href="#">Tải App Mobile iOS / Android</a>
        </div>
      </div>
    </div>

    <!-- Bottom copyrights -->
    <div class="alb-footer-bottom">
      <div class="sg-container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <span>© {{ currentYear }} SportGo Platform. Tất cả quyền được bảo lưu.</span>
        <span>Phần mềm chuyên biệt đặt lịch & quản lý sân thể thao</span>
      </div>
    </div>
  </footer>
</template>

<script setup>
const currentYear = new Date().getFullYear();
</script>
