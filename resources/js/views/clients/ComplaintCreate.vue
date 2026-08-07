<template>
  <div class="client-utility-page sg-client-page">
    <PublicNavbar />
    <main class="sg-client-shell utility-shell">
      <button type="button" class="back-link" @click="$router.push({ name:'client-complaints' })"><AppIcon name="arrowLeft" :size="17" /> Khiếu nại của tôi</button>
      <section class="utility-header"><div><p class="sg-client-eyebrow">Hỗ trợ khách hàng</p><h1>Tạo khiếu nại</h1><p>Gửi đủ thông tin để SportGo hoặc chủ sân xử lý nhanh hơn.</p></div></section>
      <section class="sg-client-card form-card">
        <form @submit.prevent="submit">
          <label><span>Loại khiếu nại</span><select v-model="form.complaint_type"><option value="venue">Vấn đề tại cụm sân</option><option value="system">Vấn đề hệ thống/booking</option></select></label>
          <div v-if="bookingLoading" class="context-note">Đang lấy thông tin booking...</div>
          <div v-else-if="booking" class="context-note"><strong>Booking liên quan</strong><span>#{{ booking.booking_code }} · {{ booking.venueCluster?.name || booking.venue_cluster?.name || 'Cụm sân' }}</span></div>
          <p v-if="bookingError" class="field-error">{{ bookingError }}</p>
          <label v-if="form.complaint_type === 'venue'"><span>Cụm sân</span><input :value="booking?.venueCluster?.name || booking?.venue_cluster?.name || ''" type="text" placeholder="Chọn từ booking" readonly /><small>Vui lòng tạo khiếu nại từ booking có liên quan để tự điền đúng cụm sân.</small></label>
          <label><span>Nội dung <b>*</b></span><textarea v-model.trim="form.content" rows="7" maxlength="2000" placeholder="Mô tả vấn đề, thời điểm xảy ra và mong muốn được hỗ trợ..."></textarea><small>{{ form.content.length }}/2000 ký tự</small></label>
          <p v-if="error" class="field-error">{{ error }}</p>
          <div class="form-actions"><button type="button" class="sg-client-button" @click="$router.back()">Hủy</button><button type="submit" class="sg-client-button sg-client-button--primary" :disabled="submitting || !isValid"><AppIcon name="send" :size="16" /> {{ submitting ? 'Đang gửi...' : 'Gửi khiếu nại' }}</button></div>
        </form>
      </section>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import { bookingService } from '../../services/bookingService.js';
import { complaintService } from '../../services/complaintService.js';
export default { name:'ComplaintCreate', components:{ AppIcon, PublicNavbar }, data(){return {form:{complaint_type:this.$route.query.booking_id?'venue':'system',booking_id:this.$route.query.booking_id||'',venue_cluster_id:'',content:''},booking:null,bookingLoading:false,bookingError:'',error:'',submitting:false}}, computed:{isValid(){return this.form.content.length>=10 && (this.form.complaint_type==='system' || Boolean(this.form.booking_id && this.form.venue_cluster_id))}}, mounted(){if(this.form.booking_id)this.loadBooking()}, methods:{async loadBooking(){this.bookingLoading=true;this.bookingError='';try{this.booking=await bookingService.getBooking(this.form.booking_id);this.form.venue_cluster_id=this.booking.venue_cluster_id||this.booking.venueCluster?.id||this.booking.venue_court?.venue_cluster_id||''}catch(error){this.bookingError=error.message||'Không tải được booking.'}finally{this.bookingLoading=false}},async submit(){this.error='';if(this.form.content.length<10){this.error='Nội dung cần ít nhất 10 ký tự.';return}if(this.form.complaint_type==='venue'&&!this.form.venue_cluster_id){this.error='Cần chọn booking có cụm sân liên quan.';return}this.submitting=true;try{const response=await complaintService.create(this.form);const id=response.data?.id||response.data?.complaint?.id;if(id)this.$router.replace({name:'client-complaint-detail',params:{id}});else this.$router.replace({name:'client-complaints'})}catch(error){this.error=error.message||'Không thể gửi khiếu nại.'}finally{this.submitting=false}}}};
</script>

<style scoped>
.utility-shell{padding-top:112px;padding-bottom:64px}.back-link{display:inline-flex;align-items:center;gap:8px;border:0;background:transparent;color:#087642;font-weight:700;cursor:pointer;margin-bottom:18px}.utility-header h1{margin:8px 0}.utility-header p:last-child{margin:0;color:#64748b}.form-card{padding:26px;max-width:760px}.form-card form{display:grid;gap:20px}.form-card label{display:grid;gap:8px}.form-card label>span{font-weight:700}.form-card label b{color:#b42318}.form-card input,.form-card select,.form-card textarea{width:100%;box-sizing:border-box;border:1px solid #b7e6c8;border-radius:8px;padding:12px;background:#fff;color:#193027;font:inherit}.form-card textarea{resize:vertical}.form-card small{color:#64748b}.context-note{display:grid;gap:5px;padding:14px;background:#f1fbf5;border-left:3px solid #0b9b55}.field-error{margin:0;color:#b42318}.form-actions{display:flex;justify-content:flex-end;gap:12px;padding-top:6px}
</style>
