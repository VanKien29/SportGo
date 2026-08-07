<template>
  <div class="favorite-venues-page sg-client-page">
    <PublicNavbar />
    <main class="sg3-container sg-client-shell">
      <header class="sg3-page-head">
        <div><p class="sg3-kicker">SÂN ĐÃ LƯU</p><h1>Sân yêu thích</h1><p>Lưu lại những sân bạn muốn quay lại để đặt lịch nhanh hơn.</p></div>
        <router-link class="sg-client-button sg-client-button--primary" :to="{ name: 'venues' }"><AppIcon name="search" :size="16" /> Tìm sân</router-link>
      </header>
      <section v-if="loading" class="sg-client-card favorite-state">Đang tải danh sách sân đã lưu...</section>
      <section v-else-if="error" class="sg-client-card favorite-state error">{{ error }}</section>
      <section v-else-if="!favorites.length" class="sg-client-card favorite-state"><AppIcon name="heart" :size="28" /><strong>Bạn chưa lưu sân nào</strong><p>Nhấn biểu tượng yêu thích ở trang chi tiết sân để lưu lại.</p></section>
      <section v-else class="favorite-grid">
        <article v-for="favorite in favorites" :key="favorite.id" class="sg-client-card favorite-card">
          <div class="favorite-card-top"><span class="favorite-mark"><AppIcon name="heart" :size="18" /></span><span>{{ favorite.venue_cluster?.rating_avg ? `${Number(favorite.venue_cluster.rating_avg).toFixed(1)} ★` : 'Mới' }}</span></div>
          <h2>{{ favorite.venue_cluster?.name || 'Cụm sân' }}</h2>
          <p>{{ [favorite.venue_cluster?.address, favorite.venue_cluster?.ward, favorite.venue_cluster?.province].filter(Boolean).join(', ') || 'Đang cập nhật địa chỉ' }}</p>
          <footer><router-link class="sg-client-button" :to="{ name: 'venue-detail', params: { id: favorite.venue_cluster?.id } }">Xem sân</router-link><router-link class="sg-client-button sg-client-button--primary" :to="{ name: 'booking-create', query: { venue_cluster_id: favorite.venue_cluster?.id } }">Đặt lịch</router-link></footer>
        </article>
      </section>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import { favoriteService } from '../../services/favoriteService.js';

export default {
  name: 'FavoriteVenues',
  components: { AppIcon, PublicNavbar },
  data() { return { favorites: [], loading: true, error: '' }; },
  async mounted() {
    try { const response = await favoriteService.list(); this.favorites = response.data || []; }
    catch (error) { this.error = error.message || 'Không thể tải sân yêu thích.'; }
    finally { this.loading = false; }
  },
};
</script>
