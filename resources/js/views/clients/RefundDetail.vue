<template>
  <div class="client-utility-page sg-client-page">
    <PublicNavbar />
    <main class="sg-client-shell utility-shell">
      <button type="button" class="back-link" @click="$router.push({ name: 'client-refunds' })"><AppIcon name="arrowLeft" :size="17" /> Hoàn tiền</button>
      <section v-if="loading" class="sg-client-card utility-state"><span class="spinner"></span> Đang tải chi tiết...</section>
      <section v-else-if="error" class="sg-client-card utility-state" role="alert"><AppIcon name="alert" :size="20" /><span>{{ error }}</span><button type="button" class="sg-client-button" @click="load">Thử lại</button></section>
      <template v-else>
        <section class="utility-header"><div><p class="sg-client-eyebrow">Chi tiết hoàn tiền</p><h1>{{ refund.booking?.booking_code || refund.booking?.code || `Yêu cầu #${refund.id}` }}</h1><p>{{ statusLabel(refund.status) }} · {{ formatDate(refund.created_at) }}</p></div><router-link v-if="bookingId" :to="{ name:'booking-detail', params:{ id: bookingId } }" class="sg-client-button sg-client-button--primary"><AppIcon name="calendar" :size="16" /> Xem booking</router-link></section>
        <section class="refund-detail-grid"><article class="sg-client-card detail-card"><h2>Thông tin hoàn tiền</h2><dl><div><dt>Số tiền yêu cầu</dt><dd>{{ money(refund.refund_amount ?? refund.amount) }}</dd></div><div><dt>Phương thức hoàn</dt><dd>{{ refund.method || refund.refund_method || 'Ví SportGo' }}</dd></div><div><dt>Lý do</dt><dd>{{ refund.reason || 'Theo chính sách booking' }}</dd></div><div><dt>Cập nhật gần nhất</dt><dd>{{ formatDate(refund.updated_at || refund.created_at) }}</dd></div></dl></article><article class="sg-client-card detail-card"><h2>Lịch sử xử lý</h2><div v-if="!histories.length" class="detail-empty">Chưa có cập nhật lịch sử.</div><ol v-else class="timeline"><li v-for="item in histories" :key="item.id"><strong>{{ statusLabel(item.status) }}</strong><span>{{ item.note || item.reason || 'Hệ thống cập nhật trạng thái' }}</span><small>{{ formatDate(item.created_at) }}</small></li></ol></article></section>
      </template>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import { bookingService } from '../../services/bookingService.js';
export default { name:'ClientRefundDetail', components:{ AppIcon, PublicNavbar }, data(){ return { refund:null, loading:true, error:'' }; }, computed:{ bookingId(){ return this.refund?.booking_id || this.refund?.booking?.id || null; }, histories(){ return this.refund?.status_histories || this.refund?.statusHistories || []; } }, mounted(){ this.load(); }, methods:{ async load(){ this.loading=true; this.error=''; try{ this.refund=await bookingService.getRefund(this.$route.params.id); }catch(error){ this.error=error.message || 'Vui lòng thử lại.'; }finally{ this.loading=false; } }, money(value){ return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND',maximumFractionDigits:0}).format(Number(value||0)); }, formatDate(value){ return value ? new Date(value).toLocaleString('vi-VN') : '-'; }, statusLabel(status){ return {pending:'Đang xử lý',approved:'Đã duyệt',processing:'Đang hoàn',completed:'Đã hoàn',rejected:'Từ chối',cancelled:'Đã hủy'}[status] || status || 'Chưa cập nhật'; } } };
</script>

<style scoped>
.utility-shell{padding-top:112px;padding-bottom:64px}.back-link{display:inline-flex;align-items:center;gap:8px;border:0;background:transparent;color:#087642;font-weight:700;cursor:pointer;margin-bottom:18px}.utility-header{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-bottom:24px}.utility-header h1{margin:8px 0}.utility-header p:last-child{margin:0;color:#64748b}.utility-state{min-height:180px;display:flex;align-items:center;justify-content:center;gap:12px;color:#64748b}.refund-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.detail-card{padding:24px}.detail-card h2{font-size:18px;margin:0 0 20px;padding-bottom:16px;border-bottom:1px solid #d9eee1}.detail-card dl{display:grid;gap:16px;margin:0}.detail-card dl div{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid #edf5ef;padding-bottom:12px}.detail-card dt{color:#64748b}.detail-card dd{margin:0;text-align:right;font-weight:700}.timeline{list-style:none;margin:0;padding:0;display:grid;gap:18px}.timeline li{display:grid;gap:4px;padding-left:18px;border-left:2px solid #b6e5c8}.timeline span,.timeline small,.detail-empty{color:#64748b}.timeline small{font-size:12px}@media(max-width:720px){.utility-header{align-items:stretch;flex-direction:column}.refund-detail-grid{grid-template-columns:1fr}.detail-card dl div{display:grid;gap:4px}.detail-card dd{text-align:left}}
</style>
