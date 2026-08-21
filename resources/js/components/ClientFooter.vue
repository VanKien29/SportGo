<template>
  <footer class="alb-footer">
    <div class="sg-container">
      <div class="alb-footer-grid">
        <!-- Col 1: Brand Info -->
        <div class="alb-footer-brand">
          <div class="alb-footer-logo-row">
            <img v-if="brandLogo" :src="brandLogo" :alt="brandName" class="alb-footer-logo" />
            <div v-else class="alb-footer-fallback-logo">
              <span class="logo-txt">SportGo</span>
              <span class="logo-sub">Platform</span>
            </div>
          </div>
          
          <p class="alb-footer-desc">
            Nền tảng công nghệ chuyên biệt cho kết nối & quản lý vận hành cụm sân Pickleball, Cầu Lông, Tennis, Bóng Đá hàng đầu Việt Nam.
          </p>

          <div class="alb-footer-contacts">
            <div class="alb-footer-contact-item">
              <AppIcon name="phone" size="15" class="contact-icon" />
              <span>Hotline 24/7: <strong>1900 6789</strong></span>
            </div>
            <div class="alb-footer-contact-item">
              <AppIcon name="mail" size="15" class="contact-icon" />
              <span>Email: <strong>support@sportgo.vn</strong></span>
            </div>
            <div class="alb-footer-contact-item">
              <AppIcon name="mapPin" size="15" class="contact-icon" />
              <span>Trụ sở chính: Hà Nội & TP. Hồ Chí Minh</span>
            </div>
          </div>
        </div>

        <!-- Col 2: HỆ THỐNG SÂN -->
        <div class="alb-footer-col">
          <h4 class="alb-footer-heading">HỆ THỐNG SÂN</h4>
          <nav class="alb-footer-links" aria-label="Hệ thống sân">
            <router-link to="/venues">Tất Cả Cụm Sân</router-link>
            <router-link to="/venues?q=Pickleball">Sân Pickleball Pro</router-link>
            <router-link to="/venues?q=Cầu+Lông">Sân Cầu Lông BWF</router-link>
            <router-link to="/venues?q=Bóng+Đá">Sân Bóng Đá Cỏ FIFA</router-link>
            <router-link to="/venues?q=Tennis">Sân Tennis ITF</router-link>
          </nav>
        </div>

        <!-- Col 3: TÍNH NĂNG & DỊCH VỤ -->
        <div class="alb-footer-col">
          <h4 class="alb-footer-heading">TÍNH NĂNG & DỊCH VỤ</h4>
          <nav class="alb-footer-links" aria-label="Tính năng & dịch vụ">
            <router-link to="/become-partner">Dành Cho Chủ Sân</router-link>
            <router-link to="/about">Giới Thiệu SportGo</router-link>
            <router-link to="/offers">Ưu Đãi & Khuyến Mãi</router-link>
            <router-link to="/news">Tin Tức & Kinh Nghiệm</router-link>
            <router-link to="/community">Cộng Đồng Ghép Đội</router-link>
          </nav>
        </div>

        <!-- Col 4: HỖ TRỢ & PHÁP LÝ -->
        <div class="alb-footer-col">
          <h4 class="alb-footer-heading">HỖ TRỢ KHÁCH HÀNG</h4>
          <nav class="alb-footer-links" aria-label="Hỗ trợ khách hàng">
            <router-link to="/contact">Trung Tâm Hỗ Trợ & Liên Hệ</router-link>
            <router-link to="/policies">Điều Khoản Sử Dụng</router-link>
            <router-link to="/policies">Chính Sách Bảo Mật</router-link>
            <router-link to="/policies">Quy Định Hoàn Tiền</router-link>
            <a href="#app-download">Tải App Mobile iOS / Android</a>
          </nav>
        </div>
      </div>
    </div>

    <!-- Bottom Copyright & Compliance -->
    <div class="alb-footer-bottom">
      <div class="sg-container alb-footer-bottom-inner">
        <div class="alb-footer-copy">
          <span>© {{ currentYear }} SportGo Platform. Tất cả quyền được bảo lưu.</span>
        </div>
        <div class="alb-footer-badge-tag">
          <span class="status-live-dot"></span>
          <span>Hệ thống đặt lịch & quản lý thể thao thông minh</span>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed } from "vue";
import { resolveSystemAsset, systemName, systemProfileState } from "../stores/systemProfile.js";
import AppIcon from "./AppIcon.vue";

const currentYear = new Date().getFullYear();
const brandName = computed(() => systemName() || "SportGo");
const brandLogo = computed(() => resolveSystemAsset(systemProfileState.profile.logo_url));
</script>
