<template>
  <div class="sg-client-page sg3-offers-page">
    <PublicNavbar />
    <main class="sg3-container sg3-profile-main">
      <div class="sg3-page-head"><div><div class="sg3-breadcrumbs"><router-link to="/">Trang chủ</router-link><span>/</span><strong>Ưu đãi</strong></div><p class="sg3-kicker">Tiết kiệm cho buổi chơi tiếp theo</p><h1>Ưu đãi đang có</h1><p>Nhập mã ở bước thanh toán để áp dụng các chương trình đủ điều kiện cho booking của bạn.</p></div><router-link class="sg3-button sg3-button--primary" to="/venues"><AppIcon name="calendar" :size="17" />Chọn sân</router-link></div>
      <div v-if="loading" class="sg3-offer-grid"><article v-for="item in 3" :key="item" class="sg3-card sg3-offer-card"><div class="sg3-skeleton" style="height: 25px; width: 55%; border-radius: 6px"></div><div class="sg3-skeleton" style="height: 15px; width: 86%; border-radius: 5px"></div><div class="sg3-skeleton" style="height: 42px; width: 43%; border-radius: 8px"></div></article></div>
      <div v-else-if="error" class="sg3-error"><div><strong>Chưa tải được ưu đãi</strong><p>{{ error }}</p><button class="sg3-button sg3-button--primary" type="button" @click="load">Thử lại</button></div></div>
      <div v-else-if="offers.length === 0" class="sg3-empty"><div><strong>Hiện chưa có ưu đãi công khai</strong><p>Các voucher đủ điều kiện sẽ xuất hiện trực tiếp tại bước đặt sân.</p><router-link class="sg3-button sg3-button--primary" to="/venues">Tìm sân</router-link></div></div>
      <div v-else class="sg3-offer-grid"><article v-for="offer in offers" :key="offer.id" class="sg3-card sg3-offer-card"><div class="sg3-offer-card__top"><span class="sg3-kicker">{{ offer.owner_type === 'venue' ? 'Ưu đãi tại sân' : 'Ưu đãi SportGo' }}</span><strong>{{ offer.code }}</strong></div><h2>{{ offer.name }}</h2><p>{{ offer.description || summary(offer) }}</p><div class="sg3-offer-card__bottom"><span v-if="offer.valid_to">Hạn dùng {{ date(offer.valid_to) }}</span><button class="sg3-button sg3-button--secondary" type="button" @click="copy(offer.code)"><AppIcon name="copy" :size="16" />Sao chép mã</button></div></article></div>
    </main>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { api } from "../../services/api.js";
export default {
  name: "ClientOffers",
  components: { AppIcon, PublicNavbar },
  data: () => ({ offers: [], loading: true, error: "" }),
  mounted() { this.load(); },
  methods: {
    async load() { this.loading = true; this.error = ""; try { const response = await api("/api/offers"); this.offers = response.data || []; } catch (error) { this.error = error.message || "Không thể tải dữ liệu."; } finally { this.loading = false; } },
    summary(offer) { return offer.discount_type === "percent" ? `Giảm ${offer.discount_value}% cho đơn từ ${this.money(offer.min_order_amount)}.` : `Giảm ${this.money(offer.discount_value)} cho đơn từ ${this.money(offer.min_order_amount)}.`; },
    money(value) { return `${new Intl.NumberFormat("vi-VN").format(Number(value || 0))} đ`; },
    date(value) { return new Intl.DateTimeFormat("vi-VN").format(new Date(value)); },
    async copy(code) { try { await navigator.clipboard.writeText(code); this.$toast?.success?.("Đã sao chép mã voucher."); } catch { this.$toast?.info?.(`Mã voucher: ${code}`); } },
  },
};
</script>

<style scoped>
.sg3-offer-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; }
.sg3-offer-card { display: grid; align-content: start; gap: 13px; min-height: 230px; padding: 21px; }
.sg3-offer-card__top, .sg3-offer-card__bottom { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.sg3-offer-card__top .sg3-kicker { margin: 0; }
.sg3-offer-card__top strong { padding: 7px 9px; border: 1px dashed var(--sg3-green); border-radius: 7px; color: var(--sg3-green-dark); letter-spacing: .05em; }
.sg3-offer-card h2 { margin: 0; font-size: 19px; }
.sg3-offer-card p { margin: 0; color: var(--sg3-muted); font-size: 13px; line-height: 1.6; }
.sg3-offer-card__bottom { align-self: end; padding-top: 12px; border-top: 1px solid var(--sg3-line); color: var(--sg3-muted); font-size: 11px; }
@media (max-width: 900px) { .sg3-offer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .sg3-offer-grid { grid-template-columns: 1fr; } }
</style>
