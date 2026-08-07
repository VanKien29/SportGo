<template>
  <div class="matchmaking-master-workspace">

    <!-- Toast Notifications -->
    <Transition name="fade">
      <div v-if="error" class="global-toast alert-error">
        <span>{{ error }}</span>
        <button type="button" class="toast-close-btn" @click="error = ''">✕</button>
      </div>
    </Transition>

    <Transition name="fade">
      <div v-if="message" class="global-toast alert-success">
        <span>{{ message }}</span>
        <button type="button" class="toast-close-btn" @click="message = ''">✕</button>
      </div>
    </Transition>

    <!-- Master Unified Surface Container -->
    <div class="cluster-profile-surface standalone">

      <!-- PART 1: Top Hero Surface -->
      <MatchmakingHeaderHero
        :tabs="tabs"
        :active-tab="activeTab"
        @open-create-modal="openCreateModal"
        @tab-change="changeTab"
      />

      <!-- PART 2: Single Unified Content Surface Card -->
      <div class="profile-section-card matchmaking-main-content">
        <MatchmakingTable
          :posts="posts"
          :clusters="clusters"
          :is-loading="loading"
          :search-query="searchQuery"
          :filter-cluster-id="filterClusterId"
          :pagination="pagination"
          @update:search-query="onSearchInput"
          @update:filter-cluster-id="onClusterFilterChange"
          @open-hide-modal="openHideModal"
          @open-report-modal="openReportModal"
          @change-page="loadPosts"
        />
      </div>

    </div>

    <!-- Teleported Form / Dialog Modal -->
    <MatchmakingFormModal
      :modal-mode="activeModalMode"
      :clusters="clusters"
      :eligible-bookings="eligibleBookings"
      :eligible-bookings-loading="eligibleBookingsLoading"
      :create-form="createForm"
      :hide-reason="hideForm.reason"
      :report-form="reportForm"
      :saving="saving"
      @close="closeAllModals"
      @submit-create="submitCreate"
      @submit-hide="submitHide"
      @submit-report="submitReport"
      @update:create-form="createForm = $event"
      @update:hide-reason="hideForm.reason = $event"
      @update:report-form="reportForm = $event"
      @cluster-select-changed="loadEligibleBookings"
    />
  </div>
</template>

<script>
import MatchmakingHeaderHero from '../../components/owner/matchmaking/MatchmakingHeaderHero.vue';
import MatchmakingTable from '../../components/owner/matchmaking/MatchmakingTable.vue';
import MatchmakingFormModal from '../../components/owner/matchmaking/MatchmakingFormModal.vue';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerMatchmaking',
  components: {
    MatchmakingHeaderHero,
    MatchmakingTable,
    MatchmakingFormModal,
  },
  data() {
    return {
      posts: [],
      clusters: [],
      eligibleBookings: [],
      eligibleBookingsLoading: false,
      loading: false,
      saving: false,
      message: '',
      error: '',
      activeTab: 'all',
      searchQuery: '',
      filterClusterId: '',
      searchTimer: null,
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      tabs: [
        { key: 'all', value: 'all', label: 'Tất cả' },
        { key: 'open', value: 'open', label: 'Đang tìm người' },
        { key: 'full', value: 'full', label: 'Đã đủ người' },
        { key: 'closed', value: 'closed', label: 'Đã đóng' },
      ],
      activeModalMode: null, // 'create' | 'hide' | 'report' | null
      selectedPost: null,
      createForm: {
        venue_cluster_id: '',
        booking_id: '',
        description: '',
        needed_players: 1,
        cost_per_player: 0,
      },
      hideForm: {
        reason: '',
      },
      reportForm: {
        reason: '',
        description: '',
      },
    };
  },
  mounted() {
    this.loadClusters();
    this.loadPosts(1);
  },
  methods: {
    async loadClusters() {
      try {
        const res = await api('/api/owner/venue-clusters?compact=1');
        this.clusters = res.data || [];
      } catch (err) {
        console.error('Lỗi khi tải cụm sân:', err);
      }
    },
    async loadPosts(page = 1) {
      this.loading = true;
      this.error = '';
      try {
        const params = new URLSearchParams({
          page: String(page),
          status: this.activeTab,
        });
        if (this.searchQuery) params.append('q', this.searchQuery);
        if (this.filterClusterId) params.append('venue_cluster_id', String(this.filterClusterId));

        const res = await api(`/api/owner/matchmaking?${params.toString()}`);
        this.posts = res.data || [];
        if (res.meta) {
          this.pagination = {
            current_page: res.meta.current_page || 1,
            last_page: res.meta.last_page || 1,
            total: res.meta.total || 0,
          };
        }
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách bài giao lưu.';
      } finally {
        this.loading = false;
      }
    },
    changeTab(tabValue) {
      this.activeTab = tabValue;
      this.loadPosts(1);
    },
    onSearchInput(val) {
      this.searchQuery = val;
      if (this.searchTimer) clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.loadPosts(1);
      }, 350);
    },
    onClusterFilterChange(val) {
      this.filterClusterId = val;
      this.loadPosts(1);
    },

    /* Modal Triggers */
    openCreateModal() {
      this.activeModalMode = 'create';
      this.createForm = {
        venue_cluster_id: this.clusters[0]?.id || '',
        booking_id: '',
        description: '',
        needed_players: 1,
        cost_per_player: 0,
      };
      if (this.createForm.venue_cluster_id) {
        this.loadEligibleBookings(this.createForm.venue_cluster_id);
      }
    },
    async loadEligibleBookings(clusterId) {
      if (!clusterId) {
        this.eligibleBookings = [];
        return;
      }
      this.eligibleBookingsLoading = true;
      try {
        const res = await api(`/api/owner/matchmaking/eligible-bookings?venue_cluster_id=${clusterId}`);
        this.eligibleBookings = res.data || [];
      } catch (err) {
        console.error('Lỗi khi tải lịch đặt sân:', err);
        this.eligibleBookings = [];
      } finally {
        this.eligibleBookingsLoading = false;
      }
    },
    openHideModal(post) {
      this.selectedPost = post;
      this.hideForm.reason = '';
      this.activeModalMode = 'hide';
    },
    openReportModal(post) {
      this.selectedPost = post;
      this.reportForm = { reason: '', description: '' };
      this.activeModalMode = 'report';
    },
    closeAllModals() {
      this.activeModalMode = null;
      this.selectedPost = null;
    },

    /* Form Submissions */
    async submitCreate() {
      if (!this.createForm.booking_id) return;
      this.saving = true;
      this.error = '';
      try {
        const res = await api('/api/owner/matchmaking', {
          method: 'POST',
          body: JSON.stringify(this.createForm),
        });
        this.message = 'Tạo bài đăng giao lưu mới thành công!';
        this.closeAllModals();
        this.loadPosts(1);
      } catch (err) {
        this.error = err.message || 'Không thể tạo bài giao lưu.';
      } finally {
        this.saving = false;
      }
    },
    async submitHide() {
      if (!this.selectedPost || !this.hideForm.reason) return;
      this.saving = true;
      this.error = '';
      try {
        await api(`/api/owner/matchmaking/${this.selectedPost.id}/hide`, {
          method: 'POST',
          body: JSON.stringify(this.hideForm),
        });
        this.message = 'Đã ẩn bài giao lưu thành công.';
        this.closeAllModals();
        this.loadPosts(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Lỗi khi ẩn bài giao lưu.';
      } finally {
        this.saving = false;
      }
    },
    async submitReport() {
      if (!this.selectedPost || !this.reportForm.reason) return;
      this.saving = true;
      this.error = '';
      try {
        await api(`/api/owner/matchmaking/${this.selectedPost.id}/report`, {
          method: 'POST',
          body: JSON.stringify(this.reportForm),
        });
        this.message = 'Đã gửi báo cáo vi phạm bài viết thành công.';
        this.closeAllModals();
      } catch (err) {
        this.error = err.message || 'Lỗi khi gửi báo cáo.';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.matchmaking-master-workspace {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cluster-profile-surface.standalone {
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  border: none;
  box-shadow: none;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 0 !important;
}

.matchmaking-main-content {
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 24px;
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  border: none;
  box-shadow: none;
  margin-top: 0 !important;
}

/* Global Toasts */
.global-toast {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border, #cfded1);
  box-shadow: var(--admin-shadow-sm, 0 1px 2px rgba(23, 34, 27, 0.06));
}

.toast-close-btn {
  margin-left: auto;
  background: transparent;
  border: none;
  color: inherit;
  cursor: pointer;
  font-size: 14px;
  opacity: 0.8;
}

.toast-close-btn:hover {
  opacity: 1;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>