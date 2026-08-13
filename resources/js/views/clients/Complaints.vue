<template>
  <div class="sg-client-page wallet-white-page">
    <PublicNavbar />

    <main class="wallet-white-main">
      <div class="wallet-layout-grid">
        <!-- LEFT SIDEBAR NAVIGATION -->
        <ClientAccountNav />

        <!-- RIGHT PAGE CONTENT -->
        <div class="w2-white-content">
          <div class="sg3-page-head">
            <div>
              <p class="sg3-kicker">Trung tâm hỗ trợ</p>
              <h1 class="page-head-title">Khiếu nại của tôi</h1>
              <p class="page-head-desc">Theo dõi yêu cầu và trao đổi với ban quản trị SportGo hoặc chủ sân.</p>
            </div>
            <router-link class="w2-btn w2-btn--primary" :to="{ name:'client-complaint-create' }">
              <span>Gửi khiếu nại mới</span>
            </router-link>
          </div>

          <section v-if="loading" class="sg3-empty">
            <div>
              <strong>Đang tải khiếu nại...</strong>
            </div>
          </section>

          <section v-else-if="error" class="sg3-error">
            <div>
              <strong>Không tải được danh sách khiếu nại</strong>
              <p>{{ error }}</p>
              <button class="w2-btn w2-btn--primary" type="button" @click="load">Thử lại</button>
            </div>
          </section>

          <section v-else class="sg3-card sg3-request-card">
            <header class="cp-card-head">
              <span><strong>{{ total }}</strong> yêu cầu hỗ trợ</span>
              <button class="w2-btn w2-btn--outline" type="button" @click="load">Làm mới</button>
            </header>

            <div v-if="!complaints.length" class="sg3-empty sg3-empty--inline">
              <div>
                <strong>Bạn chưa gửi khiếu nại nào</strong>
                <p>Nếu gặp sự cố thanh toán hoặc sân bãi, hãy gửi khiếu nại để được xử lý nhanh nhất.</p>
                <router-link class="w2-btn w2-btn--outline" to="/bookings">Xem đơn đặt sân</router-link>
              </div>
            </div>

            <article v-for="complaint in complaints" :key="complaint.id" class="sg3-request-row">
              <div class="cp-info-col">
                <strong class="cp-code-title">{{ typeLabel(complaint.complaint_type) }} · #{{ complaint.id }}</strong>
                <p class="cp-content-text">{{ complaint.content }}</p>
                <div v-if="complaint.evidence && complaint.evidence.length" class="cp-thumbs-row">
                  <img
                    v-for="img in complaint.evidence"
                    :key="img.id"
                    :src="img.file_path"
                    :alt="img.file_name"
                    class="cp-thumb-item"
                  />
                </div>
                <small class="cp-date-text">{{ complaint.venue_cluster?.name || "Hỗ trợ hệ thống" }} · {{ formatDate(complaint.created_at) }}</small>
              </div>

              <div class="cp-status-col">
                <span class="sg3-status-pill" :class="`status-${complaint.status}`">{{ statusLabel(complaint.status) }}</span>
              </div>

              <router-link :to="{name:'client-complaint-detail',params:{id:complaint.id}}" class="cp-arrow-link" aria-label="Xem chi tiết">
                <AppIcon name="chevronRight" :size="18" />
              </router-link>
            </article>

            <footer v-if="lastPage>1" class="sg3-pagination">
              <button class="w2-btn w2-btn--outline" type="button" :disabled="page<=1" @click="goPage(page-1)">Trang trước</button>
              <span>Trang {{ page }} / {{ lastPage }}</span>
              <button class="w2-btn w2-btn--outline" type="button" :disabled="page>=lastPage" @click="goPage(page+1)">Trang sau</button>
            </footer>
          </section>
        </div>
      </div>
    </main>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import ClientAccountNav from "../../components/ClientAccountNav.vue";
import { complaintService } from "../../services/complaintService.js";

export default {
  name: "ClientComplaints",
  components: { AppIcon, PublicNavbar, ClientAccountNav },
  data() {
    return {
      complaints: [],
      page: 1,
      lastPage: 1,
      total: 0,
      loading: true,
      error: "",
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = "";
      try {
        const response = await complaintService.list({ page: this.page });
        this.complaints = response.data || [];
        this.page = Number(response.current_page || this.page);
        this.lastPage = Number(response.last_page || 1);
        this.total = Number(response.total || this.complaints.length);
      } catch (error) {
        this.error = error.message || "Vui lòng thử lại.";
      } finally {
        this.loading = false;
      }
    },
    goPage(page) {
      this.page = page;
      this.load();
    },
    typeLabel(type) {
      return type === "venue" ? "Khiếu nại cụm sân" : "Khiếu nại hệ thống";
    },
    statusLabel(status) {
      return (
        {
          open: "Mới gửi",
          processing: "Đang xử lý",
          resolved: "Đã xử lý",
          rejected: "Từ chối",
          closed: "Đã đóng",
        }[status] || status || "Chưa cập nhật"
      );
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString("vi-VN") : "-";
    },
  },
};
</script>

<style scoped>
* {
  font-weight: 400 !important;
}

.wallet-white-page {
  min-height: 100vh;
  background: #ffffff;
}

.wallet-white-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
  color: #0f172a;
}

.wallet-layout-grid {
  display: flex;
  gap: 32px;
  align-items: flex-start;
  width: 100%;
}

.w2-white-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sg3-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding-bottom: 12px;
}

.sg3-kicker {
  font-size: 12px;
  color: #475569;
  letter-spacing: 0.05em;
  margin-bottom: 4px;
}

.page-head-title {
  font-size: 24px;
  color: #0f172a;
  margin: 0 0 6px;
}

.page-head-desc {
  font-size: 13.5px;
  color: #475569;
  margin: 0;
}

.w2-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13.5px;
  border-radius: 4px;
  cursor: pointer;
  text-decoration: none;
  border: 1px solid transparent;
  transition: all 0.15s ease;
}

.w2-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.w2-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}

.sg3-card,
.sg3-empty,
.sg3-error {
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0 !important;
  border-radius: 0 !important;
}

.sg3-empty--inline {
  padding: 40px 0 !important;
  text-align: center;
}

.cp-card-head,
.sg3-request-card > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 0 12px 0 !important;
  font-size: 14px;
  color: #0f172a;
}

.sg3-request-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0 !important;
  gap: 16px;
}

.cp-info-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.cp-code-title {
  font-size: 15px;
  color: #0f172a;
}

.cp-content-text {
  font-size: 13.5px;
  color: #475569;
  margin: 0;
}

.cp-thumbs-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 4px 0;
}

.cp-thumb-item {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
}

.cp-date-text {
  font-size: 12px;
  color: #64748b;
}

.cp-status-col {
  display: flex;
  align-items: center;
}

.sg3-status-pill {
  font-size: 13px;
  color: #475569;
  background: transparent;
  border: none;
  padding: 0;
}

.sg3-status-pill.status-resolved {
  color: #15803d;
}

.sg3-status-pill.status-open,
.sg3-status-pill.status-processing {
  color: #d97706;
}

.sg3-status-pill.status-rejected,
.sg3-status-pill.status-closed {
  color: #dc2626;
}

.cp-arrow-link {
  color: #64748b;
  display: flex;
  align-items: center;
}

.sg3-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding-top: 24px;
  font-size: 13.5px;
  color: #334155;
}
</style>
