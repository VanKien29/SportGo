<template>
  <footer class="sg3-footer">
    <div class="sg3-footer__main sg3-container">
      <section class="sg3-footer__brand">
        <router-link to="/" class="sg3-brand"><span class="sg3-brand__mark">{{ initials }}</span><span class="sg3-brand__name">{{ brandMain }}<strong>{{ brandAccent }}</strong></span></router-link>
        <p>Tìm sân, xem lịch trống và kết nối cộng đồng thể thao trong một hành trình rõ ràng.</p>
      </section>

      <section class="sg3-footer__links">
        <h3>Khám phá</h3>
        <router-link to="/venues">Tìm sân thể thao</router-link>
        <router-link to="/map">Bản đồ sân</router-link>
        <router-link to="/featured">Sân nổi bật</router-link>
        <router-link to="/offers">Ưu đãi</router-link>
        <router-link to="/community">Cộng đồng SportGo</router-link>
      </section>

      <section class="sg3-footer__links">
        <h3>Dành cho bạn</h3>
        <router-link to="/bookings">Lịch đã đặt</router-link>
        <router-link to="/profile">Tài khoản cá nhân</router-link>
        <router-link to="/wallet">Ví SportGo</router-link>
        <router-link to="/complaints">Trung tâm hỗ trợ</router-link>
        <router-link to="/become-partner">Trở thành đối tác</router-link>
      </section>

      <section class="sg3-footer__contact">
        <h3>Liên hệ {{ brandName }}</h3>
        <a :href="`tel:${supportPhoneRaw}`">
          <Phone :size="17" aria-hidden="true" />
          {{ supportPhone }}
        </a>
        <a v-if="supportEmail" :href="`mailto:${supportEmail}`">
          <Mail :size="17" aria-hidden="true" />
          {{ supportEmail }}
        </a>
        <span>
          <MapPin :size="17" aria-hidden="true" />
          {{ supportAddress }}
        </span>
      </section>
    </div>

    <div class="sg3-footer__bottom">
      <div class="sg3-container">
        <span>© {{ currentYear }} {{ brandName }}. Nền tảng đặt sân thể thao.</span>
        <span>Điều khoản · Chính sách bảo mật</span>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { Mail, MapPin, Phone } from "lucide-vue-next";
import { computed } from "vue";
import { systemName, systemProfileState } from "../stores/systemProfile.js";

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
const initials = computed(() => brandName.value.split(/\s+/).filter(Boolean).map((part) => part[0]).join("").slice(0, 2).toUpperCase() || "SG");
const supportPhone = computed(() => systemProfileState.profile.support_phone || "1900 6789");
const supportPhoneRaw = computed(() => String(supportPhone.value).replace(/[^\d+]/g, "") || "19006789");
const supportEmail = computed(() => systemProfileState.profile.support_email || "");
const supportAddress = computed(() => systemProfileState.profile.company_address || "Hà Nội, Việt Nam");
</script>
