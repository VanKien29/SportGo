<template>
  <footer class="spg-client-footer">
    <div class="spg-client-footer__main">
      <section class="spg-client-footer__brand">
        <h2>{{ brandMain }}<strong>{{ brandAccent }}</strong></h2>
        <p>
          Tìm sân, xem lịch trống và kết nối cộng đồng thể thao trong một hành
          trình rõ ràng.
        </p>
      </section>

      <section class="spg-client-footer__links">
        <h3>Khám phá</h3>
        <router-link to="/venues">Tìm sân thể thao</router-link>
        <router-link to="/community">Cộng đồng SportGo</router-link>
        <router-link to="/news">Tin tức & kinh nghiệm</router-link>
      </section>

      <section class="spg-client-footer__links">
        <h3>Dành cho bạn</h3>
        <router-link to="/bookings">Lịch đã đặt</router-link>
        <router-link to="/profile">Tài khoản cá nhân</router-link>
        <router-link to="/become-partner">Trở thành đối tác</router-link>
      </section>

      <section class="spg-client-footer__contact">
        <h3>Liên hệ SportGo</h3>
        <a href="tel:19006789">
          <Phone :size="17" aria-hidden="true" />
          1900 6789
        </a>
        <a href="mailto:hello@sportgo.vn">
          <Mail :size="17" aria-hidden="true" />
          hello@sportgo.vn
        </a>
        <span>
          <MapPin :size="17" aria-hidden="true" />
          Hà Nội, Việt Nam
        </span>
      </section>
    </div>

    <div class="spg-client-footer__bottom">
      <div>
        <span>© {{ currentYear }} SportGo. Nền tảng đặt sân thể thao.</span>
        <span>Điều khoản · Chính sách bảo mật</span>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { Mail, MapPin, Phone } from "lucide-vue-next";
import { computed } from "vue";
import { systemName } from "../stores/systemProfile.js";

const currentYear = new Date().getFullYear();
const brandName = computed(() => systemName() || "SportGo");
const brandMain = computed(() => {
  const match = brandName.value.match(/^(.*?)(go)$/i);
  return match ? match[1] : brandName.value;
});
const brandAccent = computed(() => {
  const match = brandName.value.match(/^(.*?)(go)$/i);
  return match ? match[2] : "";
});
</script>
