<template>
  <footer class="alb-footer">
    <div class="sg-container alb-footer-grid">
      <!-- Col 1: Brand Info -->
      <div>
        <div style="margin-bottom: 16px;">
          <img v-if="brandLogo" :src="brandLogo" :alt="brandName" style="max-height: 44px; object-fit: contain;" />
          <div v-else style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 18px;">SG</div>
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
          <router-link to="/about">Giới Thiệu SportGo</router-link>
          <router-link to="/offers">Ưu Đãi & Khuyến Mãi</router-link>
          <router-link to="/news">Tin Tức & Kinh Nghiệm</router-link>
          <router-link to="/community">Cộng Đồng Ghép Đội</router-link>
        </div>
      </div>

      <!-- Col 4: HỖ TRỢ & BẢO MẬT -->
      <div class="alb-footer-col">
        <h4>HỖ TRỢ KHÁCH HÀNG</h4>
        <div class="alb-footer-links">
          <router-link to="/contact">Trung Tâm Hỗ Trợ & Liên Hệ</router-link>
          <router-link to="/policies">Điều Khoản Sử Dụng</router-link>
          <router-link to="/policies">Chính Sách Bảo Mật</router-link>
          <router-link to="/policies">Quy Định Hoàn Tiền</router-link>
          <a href="#app-download">Tải App Mobile iOS / Android</a>
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
import { computed } from "vue";
import { resolveSystemAsset, systemName, systemProfileState } from "../stores/systemProfile.js";

const currentYear = new Date().getFullYear();
const brandName = computed(() => systemName() || "SportGo");
const brandLogo = computed(() => resolveSystemAsset(systemProfileState.profile.logo_url));
</script>
