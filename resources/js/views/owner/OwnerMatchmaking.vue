<template>
  <div class="matchmaking-page">

    <div class="page-header sg-page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
      <div class="header-left sg-page-heading">
        <nav class="sg-breadcrumbs" aria-label="Breadcrumb">
          <router-link to="/owner/dashboard">Dashboard</router-link>
          <span>/</span>
          <span>Giao lưu tại sân</span>
        </nav>
        <h2 style="margin: 8px 0;">Giao lưu tại sân</h2>
        <p class="muted" style="margin: 0;">Quản lý và đăng tải các bài viết ghép kèo, tìm người chơi.</p>
      </div>
      <button class="btn btn-create primary sg-primary-action" type="button" @click="openCreateModal">
        <AppIcon name="plus" size="16" />
        <span>Tạo bài giao lưu</span>
      </button>
    </div>

    <!-- Alerts -->
    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>
 
    <div class="filter-toolbar card">
      <!-- Tabs -->
      <div class="tabs-header">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          class="tab-btn"
          :class="{ active: activeTab === tab.value }"
          type="button"
          @click="changeTab(tab.value)"
        >
          <AppIcon :name="tab.icon" size="16" />
          <span>{{ tab.label }}</span>
        </button>
      </div>
 
      <!-- Filter and Search -->
      <div class="filters-row">
        <label class="field compact search-field">
          <AppIcon name="search" size="16" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Tìm theo tiêu đề, người đăng..."
            @input="onSearchInput"
          />
        </label>
 
        <label class="field compact select-field">
          <span>Lọc theo cụm sân</span>
          <select v-model="filterClusterId" @change="loadPosts(1)">
            <option value="">Tất cả cụm sân</option>
            <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
              {{ cluster.name }}
            </option>
          </select>
        </label>
      </div>
    </div>
 
    <!-- Loading Screen -->
    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải danh sách bài giao lưu...</p>
    </div>
 
    <!-- Empty Screen -->
    <div v-else-if="posts.length === 0" class="state-box card">
      <AppIcon name="fileText" size="36" />
      <p>Không tìm thấy bài giao lưu nào.</p>
    </div>
 
    <!-- Matchmaking Posts Table -->
    <div v-else class="table-container card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Người đăng</th>
              <th>Thông tin buổi chơi</th>
              <th>Yêu cầu ghép cặp</th>
              <th>Trạng thái</th>
              <th>Booking liên quan</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in posts" :key="post.id" class="post-row">
              <td>
                <div class="author-cell">
                  <strong>{{ post.author?.full_name || post.author?.username || 'Người chơi' }}</strong>
                  <span class="muted small">{{ post.author?.phone || 'Không có SĐT' }}</span>
                  <span class="muted small">{{ post.author?.email || '' }}</span>
                </div>
              </td>
              <td>
                <div class="info-cell">
                  <div class="post-title">{{ post.title }}</div>
                  <p class="post-desc" v-if="post.description">{{ post.description }}</p>
                  <div class="post-time-location">
                    <AppIcon name="clock" size="14" class="muted-icon" />
                    <span>
                      {{ formatDate(post.booking?.booking_date) }}
                      ({{ formatTime(post.booking?.start_time) }} - {{ formatTime(post.booking?.end_time) }})
                    </span>
                  </div>
                  <div class="post-court">
                    <AppIcon name="building" size="14" class="muted-icon" />
                    <span>{{ post.booking?.venueCluster?.name }} · <strong>{{ post.booking?.venueCourt?.name }}</strong></span>
                  </div>
                </div>
              </td>
              <td>
                <div class="needed-cell">
                  <span class="needed-badge">Cần thêm: <strong>{{ post.needed_players }} người</strong></span>
                  <span class="cost-badge" v-if="post.cost_per_player > 0">
                    Chi phí: {{ formatCurrency(post.cost_per_player) }}/người
                  </span>
                </div>
              </td>
              <td>
                <span class="status-badge" :class="getStatusClass(post.status)">
                  {{ getStatusLabel(post.status) }}
                </span>
                <div v-if="post.status_reason" class="status-reason" :title="post.status_reason">
                  Lý do: {{ post.status_reason }}
                </div>
              </td>
              <td>
                <div v-if="post.booking" class="booking-link-cell">
                  <span class="booking-code">Mã: {{ post.booking.booking_code }}</span>
                  <router-link
                    :to="{
                      name: 'owner-counter-booking',
                      query: {
                        venue_cluster_id: post.booking.venue_cluster_id,
                        booking_date: post.booking.booking_date,
                        venue_court_id: post.booking.venue_court_id,
                        booking_id: post.booking.id,
                        booking_code: post.booking.booking_code,
                      }
                    }"
                    class="btn-link"
                  >
                    Xem lịch đặt sân
                  </router-link>
                </div>
                <span v-else class="muted">-</span>
              </td>
              <td class="right">
                <div class="actions-cell" v-if="post.status === 'open' || post.status === 'full'">
                  <button class="btn ghost btn-sm" type="button" @click="openHideModal(post)">
                    <span>Ẩn bài</span>
                  </button>
                  <button class="btn ghost danger btn-sm" type="button" @click="openReportModal(post)">
                    <span>Báo cáo</span>
                  </button>
                </div>
                <span v-else class="muted">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
 
      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="pagination-bar">
        <button
          class="btn ghost btn-sm"
          type="button"
          :disabled="pagination.current_page <= 1"
          @click="loadPosts(pagination.current_page - 1)"
        >
          Trước
        </button>
        <span class="page-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          class="btn ghost btn-sm"
          type="button"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="loadPosts(pagination.current_page + 1)"
        >
          Sau
        </button>
      </div>
    </div>
 
    <!-- MODAL ẨN BÀI GIAO LƯU -->
    <div v-if="hideModal.open" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeHideModal)">
      <div class="modal small" @mousedown.stop>
        <div class="modal-header">
          <h3>Ẩn bài viết giao lưu</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeHideModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>
        <form @submit.prevent="submitHide">
          <div class="modal-body form-grid">
            <p class="warning-text">Lưu ý: Hành động này sẽ chuyển trạng thái bài viết giao lưu thành <strong>Đóng</strong> và không thể hoàn tác.</p>
            <label class="field">
              <span>Lý do ẩn bài viết <span class="required">*</span></span>
              <textarea
                v-model="hideForm.reason"
                rows="4"
                placeholder="Nhập lý do ẩn gửi tới người chơi..."
                required
              ></textarea>
            </label>
          </div>
          <div class="modal-footer">
            <button class="btn ghost" type="button" @click="closeHideModal" :disabled="saving">Hủy</button>
            <button class="btn primary" type="submit" :disabled="saving || !hideForm.reason">
              <span>{{ saving ? 'Đang lưu...' : 'Ẩn bài' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
 
    <!-- MODAL BÁO CÁO VI PHẠM -->
    <div v-if="reportModal.open" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeReportModal)">
      <div class="modal small" @mousedown.stop>
        <div class="modal-header">
          <h3>Báo cáo vi phạm bài giao lưu</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeReportModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>
        <form @submit.prevent="submitReport">
          <div class="modal-body form-grid">
            <label class="field">
              <span>Lý do vi phạm <span class="required">*</span></span>
              <select v-model="reportForm.reason" required>
                <option value="" disabled>-- Chọn lý do --</option>
                <option value="spam">Spam quảng cáo</option>
                <option value="offensive">Nội dung phản cảm</option>
                <option value="fake">Thông tin giả mạo</option>
                <option value="harassment">Quấy rối / Đả kích</option>
                <option value="other">Lý do khác</option>
              </select>
            </label>
            <label class="field">
              <span>Mô tả chi tiết</span>
              <textarea
                v-model="reportForm.description"
                rows="4"
                placeholder="Mô tả cụ thể vi phạm để quản trị viên xử lý..."
              ></textarea>
            </label>
          </div>
          <div class="modal-footer">
            <button class="btn ghost" type="button" @click="closeReportModal" :disabled="saving">Hủy</button>
            <button class="btn primary danger" type="submit" :disabled="saving || !reportForm.reason">
              <span>{{ saving ? 'Đang gửi...' : 'Gửi báo cáo' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL TẠO BÀI GIAO LƯU (STYLE FACEBOOK LIGHT MODE) -->
    <div v-if="createModal.open" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeCreateModal)">
      <div class="modal fb-post-modal" @mousedown.stop style="max-width: 500px; width: 100%; border-radius: 8px; padding: 0; background: #ffffff; color: #050505; font-family: inherit; box-shadow: 0 12px 28px 0 rgba(0,0,0,0.2), 0 2px 4px 0 rgba(0,0,0,0.1); border: none;">
        <!-- Header -->
        <div class="fb-modal-header" style="border-bottom: 1px solid #ced0d4; padding: 16px; position: relative; text-align: center;">
          <h3 style="margin: 0; font-size: 20px; font-weight: 700; color: #050505;">Tạo bài viết</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeCreateModal" style="position: absolute; right: 16px; top: 16px; background: #e4e6eb; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: #606770; border: none; cursor: pointer; transition: background 0.2s;">
            <AppIcon name="x" size="20" />
          </button>
        </div>

        <form @submit.prevent="submitCreate">
          <div class="fb-modal-body" style="padding: 16px; max-height: calc(100vh - 200px); overflow-y: auto;">
            <!-- User Info -->
            <div class="fb-user-info" style="display: flex; align-items: center; margin-bottom: 16px; gap: 10px; position: relative; z-index: 101;">
              <div class="fb-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: #e4e6eb; display: flex; align-items: center; justify-content: center;">
                <AppIcon name="user" size="24" color="#606770" />
              </div>
              <div class="fb-user-name">
                <div style="font-weight: 600; font-size: 15px; color: #050505;">Chủ sân (Quản trị viên)</div>
                <div class="fb-tags" style="display: flex; gap: 6px; margin-top: 4px;">
                  <!-- Custom Select Cluster -->
                  <div style="position: relative;">
                    <div v-if="clusterDropdownOpen" @click="clusterDropdownOpen = false" style="position: fixed; inset: 0; z-index: 99;"></div>
                    <div @click="clusterDropdownOpen = !clusterDropdownOpen" style="background: #e4e6eb; border-radius: 6px; padding: 4px 8px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; color: #050505; cursor: pointer; user-select: none; position: relative; z-index: 100;">
                      <AppIcon name="building" size="12" />
                      <span>{{ selectedClusterName || 'Chọn cụm sân' }}</span>
                      <AppIcon name="chevronDown" size="12" />
                    </div>
                    
                    <div v-if="clusterDropdownOpen" style="position: absolute; top: 100%; left: 0; margin-top: 6px; background: #ffffff; border: 1px solid #ced0d4; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100; min-width: 200px; max-height: 250px; overflow-y: auto; padding: 4px;">
                      <div v-if="clusters.length === 0" style="padding: 8px 12px; font-size: 13px; color: #65676b; text-align: center;">Không có dữ liệu</div>
                      <div v-for="cluster in clusters" :key="cluster.id" @click="selectCluster(cluster.id)" class="custom-dropdown-item" :class="{ active: createForm.venue_cluster_id === cluster.id }">
                        {{ cluster.name }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Content Area -->
            <textarea
              v-model="createForm.description"
              rows="2"
              placeholder="Bạn muốn chia sẻ điều gì về trận giao lưu này?"
              style="width: 100%; border: none; outline: none; resize: none; font-size: 24px; margin-bottom: 8px; font-family: inherit; background: transparent; color: #050505;"
            ></textarea>
            
            <!-- Title is auto-generated on submit -->

            <!-- Image Preview -->
            <div v-if="createForm.imagePreview" class="fb-image-preview" style="position: relative; margin-bottom: 16px; border-radius: 8px; overflow: hidden; border: 1px solid #ced0d4;">
              <img :src="createForm.imagePreview" style="width: 100%; display: block;" />
              <button type="button" @click="removeImage" style="position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.2); color: #050505;">
                <AppIcon name="x" size="16" />
              </button>
            </div>

            <!-- Extra Fields Area (Collapsed into nicely styled inputs) -->
            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
              <!-- Select Booking Area -->
              <div class="fb-booking-select" style="background: #f0f2f5; border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 8px; border: 1px solid #ced0d4;">
                <div style="font-weight: 600; font-size: 13px; color: #050505; display: flex; align-items: center; gap: 6px;">
                  <AppIcon name="calendar" size="16" color="#f5533d" /> Lịch đặt sân <span class="required" style="color: #f02849;">*</span>
                </div>
                
                <!-- Custom Booking Select -->
                <div style="position: relative;" :style="{ opacity: (!createForm.venue_cluster_id || eligibleBookingsLoading) ? 0.6 : 1, pointerEvents: (!createForm.venue_cluster_id || eligibleBookingsLoading) ? 'none' : 'auto' }">
                  <div v-if="bookingDropdownOpen" @click="bookingDropdownOpen = false" style="position: fixed; inset: 0; z-index: 99;"></div>
                  <div @click="bookingDropdownOpen = !bookingDropdownOpen" style="width: 100%; border: 1px solid #ced0d4; border-radius: 6px; padding: 10px 12px; font-size: 14px; background: #ffffff; color: #050505; display: flex; align-items: center; justify-content: space-between; cursor: pointer; position: relative; z-index: 100;">
                    <span :style="{ color: createForm.booking_id ? '#050505' : '#65676b' }">{{ selectedBookingText || '-- Chọn lịch sắp tới (chưa có người ghép) --' }}</span>
                    <AppIcon name="chevronDown" size="16" color="#65676b" />
                  </div>

                  <div v-if="bookingDropdownOpen" style="position: absolute; top: 100%; left: 0; right: 0; margin-top: 6px; background: #ffffff; border: 1px solid #ced0d4; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100; max-height: 250px; overflow-y: auto; padding: 4px;">
                    <div v-if="eligibleBookings.length === 0" style="padding: 12px; font-size: 13px; color: #65676b; text-align: center;">Không có lịch nào phù hợp</div>
                    <div v-for="bk in eligibleBookings" :key="bk.id" @click="selectBooking(bk.id)" class="custom-dropdown-item" :class="{ active: createForm.booking_id === bk.id }">
                      {{ bk.venueCourt?.name || 'Sân trống' }} {{ bk.venueCourt?.courtType ? '(' + bk.venueCourt.courtType.name + ')' : '' }} | {{ formatDate(bk.booking_date) }} ({{ formatTime(bk.start_time) }} - {{ formatTime(bk.end_time) }})
                    </div>
                  </div>
                </div>

                <div v-if="eligibleBookingsLoading" style="font-size: 12px; color: #65676b; margin-top: 4px;">Đang tải danh sách lịch...</div>
                <div v-else-if="createForm.venue_cluster_id && eligibleBookings.length === 0" style="font-size: 12px; color: #f02849; margin-top: 4px; white-space: normal; line-height: 1.4;">
                  Không có lịch đặt sân nào sắp diễn ra có thể dùng để tạo bài giao lưu.
                </div>
              </div>

              <!-- Players & Cost -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <label style="background: #f0f2f5; border-radius: 8px; padding: 8px 12px; border: 1px solid #ced0d4; cursor: text; display: block;">
                  <div style="font-size: 12px; font-weight: 600; color: #65676b; display: flex; align-items: center; gap: 6px;">
                    <AppIcon name="users" size="14" color="#1877f2" /> Số người cần <span style="color: #f02849;">*</span>
                  </div>
                  <input v-model.number="createForm.needed_players" type="number" min="1" required style="width: 100%; border: none; outline: none; font-size: 14px; margin-top: 4px; padding: 4px 0; font-family: inherit; background: transparent; color: #050505;" />
                </label>
                <label style="background: #f0f2f5; border-radius: 8px; padding: 8px 12px; border: 1px solid #ced0d4; cursor: text; display: block;">
                  <div style="font-size: 12px; font-weight: 600; color: #65676b; display: flex; align-items: center; gap: 6px;">
                    <AppIcon name="banknote" size="14" color="#f7b928" /> Chi phí (VND)
                  </div>
                  <input v-model.number="createForm.cost_per_player" type="number" min="0" placeholder="0 = Miễn phí" style="width: 100%; border: none; outline: none; font-size: 14px; margin-top: 4px; padding: 4px 0; font-family: inherit; background: transparent; color: #050505;" />
                </label>
              </div>
            </div>

            <!-- Add to Post -->
            <div class="fb-add-to-post" style="border: 1px solid #ced0d4; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
              <span style="font-weight: 600; font-size: 15px; color: #050505;">Thêm vào bài viết của bạn</span>
              <div style="display: flex; gap: 16px; align-items: center;">
                <label style="cursor: pointer; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s;" title="Thêm ảnh">
                  <AppIcon name="image" size="24" color="#45bd62" />
                  <input type="file" accept="image/jpeg,image/png,image/jpg,image/webp" @change="onImageSelected" style="display: none;" />
                </label>
                <div style="cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Số người cần tuyển">
                  <AppIcon name="users" size="24" color="#1877f2" />
                </div>
                <div style="cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Cảm xúc">
                  <AppIcon name="star" size="24" color="#f7b928" />
                </div>
                <div style="cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Lịch đặt sân">
                  <AppIcon name="calendar" size="24" color="#f5533d" />
                </div>
                <div style="cursor: pointer; display: flex; align-items: center; justify-content: center; background: #e4e6eb; border-radius: 50%; width: 24px; height: 24px;" title="Thêm">
                  <AppIcon name="moreHorizontal" size="16" color="#606770" />
                </div>
              </div>
            </div>
          </div>

          <div class="fb-modal-footer" style="padding: 0 16px 16px;">
            <button class="btn primary fb-submit-btn" type="submit" :disabled="saving || !createForm.booking_id" style="width: 100%; font-size: 15px; font-weight: 600; padding: 10px; border-radius: 6px; background: var(--admin-primary, #1b74e4); border: none; color: #fff; cursor: pointer; transition: background 0.2s;">
              <span>{{ saving ? 'Đang tạo...' : 'Đăng' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
 
<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerMatchmakingService } from '../../services/ownerMatchmaking.js';
import { venueClusterService } from '../../services/venueClusters.js';
 
export default {
  name: 'OwnerMatchmaking',
  components: { AppIcon },
  computed: {
    selectedClusterName() {
      const cluster = this.clusters.find(c => c.id === this.createForm.venue_cluster_id);
      return cluster ? cluster.name : '';
    },
    selectedBookingText() {
      const bk = this.eligibleBookings.find(b => b.id === this.createForm.booking_id);
      if (!bk) return '';
      const courtName = bk.venueCourt?.name || 'Sân trống';
      const sportName = bk.venueCourt?.courtType ? ` (${bk.venueCourt.courtType.name})` : '';
      return `${courtName}${sportName} | ${this.formatDate(bk.booking_date)} (${this.formatTime(bk.start_time)} - ${this.formatTime(bk.end_time)})`;
    },
    reportReasons() {
      return [
        { value: 'spam', label: 'Spam quảng cáo' },
        { value: 'offensive', label: 'Nội dung phản cảm' },
        { value: 'fake', label: 'Thông tin giả mạo' },
        { value: 'harassment', label: 'Quấy rối / Đả kích' },
        { value: 'other', label: 'Lý do khác' }
      ];
    }
  },
  data() {
    return {
      posts: [],
      clusters: [],
      loading: true,
      saving: false,
      message: '',
      error: '',
      activeTab: 'all',
      filterClusterId: '',
      searchQuery: '',
      searchTimer: null,
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      tabs: [
        { label: 'Tất cả', value: 'all', icon: 'layers' },
        { label: 'Đang mở', value: 'open', icon: 'clock' },
        { label: 'Đã đủ', value: 'full', icon: 'circleCheck' },
        { label: 'Đã đóng', value: 'closed', icon: 'lock' },
        { label: 'Đã hủy', value: 'cancelled', icon: 'circleX' },
      ],
      hideModal: {
        open: false,
        postId: null,
      },
      hideForm: {
        reason: '',
      },
      reportModal: {
        open: false,
        postId: null,
      },
      reportForm: {
        reason: '',
        description: '',
      },
      createModal: {
        open: false,
      },
      clusterDropdownOpen: false,
      bookingDropdownOpen: false,
      createForm: {
        venue_cluster_id: '',
        booking_id: '',
        title: '',
        description: '',
        needed_players: 1,
        cost_per_player: 0,
        imageFile: null,
        imagePreview: null,
      },
      eligibleBookings: [],
      eligibleBookingsLoading: false,
      mousedownWasOnBackdrop: false,
    };
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.onClusterChangedEvent);
    await this.loadClusters();
    await this.loadPosts();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.onClusterChangedEvent);
  },
  methods: {
    async loadClusters() {
      try {
        const response = await venueClusterService.getClusters();
        this.clusters = response.data || [];
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách cụm sân.';
      }
    },
    async loadPosts(page = 1) {
      this.loading = true;
      this.clearAlerts();
      try {
        const params = {
          page,
          venue_cluster_id: this.filterClusterId,
          search: this.searchQuery,
        };
        if (this.activeTab !== 'all') {
          params.status = this.activeTab;
        }
 
        const response = await ownerMatchmakingService.list(params);
        const paginator = response.data || {};
        this.posts = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.posts.length,
        };
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách bài giao lưu.';
      } finally {
        this.loading = false;
      }
    },
    onClusterChangedEvent() {
      this.loadPosts(1);
    },
    changeTab(tabValue) {
      this.activeTab = tabValue;
      this.loadPosts(1);
    },
    onSearchInput() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.loadPosts(1);
      }, 400);
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      });
    },
    formatTime(time) {
      return (time || '').slice(0, 5);
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
    getStatusLabel(status) {
      const map = {
        open: 'Đang mở',
        full: 'Đã đủ người',
        closed: 'Đã đóng',
        cancelled: 'Đã hủy',
      };
      return map[status] || status;
    },
    getStatusClass(status) {
      const map = {
        open: 'status-open',
        full: 'status-full',
        closed: 'status-closed',
        cancelled: 'status-cancelled',
      };
      return map[status] || '';
    },
 
    // Backdrop selection logic
    handleBackdropMousedown(event) {
      this.mousedownWasOnBackdrop = event.target === event.currentTarget;
    },
    handleBackdropClick(event, closeFn) {
      if (this.mousedownWasOnBackdrop && event.target === event.currentTarget) {
        closeFn();
      }
      this.mousedownWasOnBackdrop = false;
    },
 
    // Modal Hide logic
    openHideModal(post) {
      this.clearAlerts();
      this.hideForm.reason = '';
      this.hideModal.postId = post.id;
      this.hideModal.open = true;
    },
    closeHideModal() {
      this.hideModal.open = false;
    },
    async submitHide() {
      this.saving = true;
      this.clearAlerts();
      try {
        await ownerMatchmakingService.hide(this.hideModal.postId, this.hideForm);
        this.message = 'Ẩn bài giao lưu thành công.';
        this.closeHideModal();
        await this.loadPosts(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Ẩn bài giao lưu thất bại.';
      } finally {
        this.saving = false;
      }
    },
 
    // Modal Report logic
    openReportModal(post) {
      this.clearAlerts();
      this.reportForm.reason = '';
      this.reportForm.description = '';
      this.reportModal.postId = post.id;
      this.reportModal.open = true;
    },
    closeReportModal() {
      this.reportModal.open = false;
    },
    async submitReport() {
      this.saving = true;
      this.clearAlerts();
      try {
        await ownerMatchmakingService.report(this.reportModal.postId, this.reportForm);
        this.message = 'Gửi báo cáo vi phạm thành công. Admin sẽ sớm xem xét xử lý.';
        this.closeReportModal();
        await this.loadPosts(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Gửi báo cáo thất bại.';
      } finally {
        this.saving = false;
      }
    },

    // Modal Create logic
    openCreateModal() {
      this.clearAlerts();
      this.createForm = {
        venue_cluster_id: this.clusters.length === 1 ? this.clusters[0].id : '',
        booking_id: '',
        title: '',
        description: '',
        needed_players: 1,
        cost_per_player: 0,
        imageFile: null,
        imagePreview: null,
      };
      this.eligibleBookings = [];
      this.createModal.open = true;
      if (this.createForm.venue_cluster_id) {
        this.loadEligibleBookings();
      }
    },
    selectCluster(id) {
      this.createForm.venue_cluster_id = id;
      this.clusterDropdownOpen = false;
      this.loadEligibleBookings();
    },
    selectBooking(id) {
      this.createForm.booking_id = id;
      this.bookingDropdownOpen = false;
    },
    closeCreateModal() {
      this.createModal.open = false;
      this.clusterDropdownOpen = false;
      this.bookingDropdownOpen = false;
      this.removeImage(); // Dọn dẹp URL object URL
    },
    onImageSelected(event) {
      const file = event.target.files[0];
      if (file) {
        if (file.size > 5 * 1024 * 1024) {
          alert('Vui lòng chọn ảnh nhỏ hơn 5MB.');
          return;
        }
        this.createForm.imageFile = file;
        this.createForm.imagePreview = URL.createObjectURL(file);
      }
      event.target.value = null; // Reset input
    },
    removeImage() {
      this.createForm.imageFile = null;
      if (this.createForm.imagePreview) {
        URL.revokeObjectURL(this.createForm.imagePreview);
        this.createForm.imagePreview = null;
      }
    },
    async loadEligibleBookings() {
      if (!this.createForm.venue_cluster_id) {
        this.eligibleBookings = [];
        return;
      }
      this.eligibleBookingsLoading = true;
      try {
        const response = await ownerMatchmakingService.getEligibleBookings(this.createForm.venue_cluster_id);
        this.eligibleBookings = response.data || [];
        this.createForm.booking_id = '';
      } catch (err) {
        this.error = 'Lỗi tải danh sách lịch đặt sân: ' + err.message;
        this.eligibleBookings = [];
      } finally {
        this.eligibleBookingsLoading = false;
      }
    },
    async submitCreate() {
      this.saving = true;
      this.clearAlerts();
      try {
        const formData = new FormData();
        formData.append('venue_cluster_id', this.createForm.venue_cluster_id);
        formData.append('booking_id', this.createForm.booking_id);
        
        // Tự động tạo Tiêu đề vì giao diện FB không có tiêu đề
        const autoTitle = this.createForm.description ? this.createForm.description.substring(0, 50) + (this.createForm.description.length > 50 ? '...' : '') : 'Tìm trận giao lưu';
        formData.append('title', autoTitle);
        
        formData.append('description', this.createForm.description);
        formData.append('needed_players', this.createForm.needed_players);
        formData.append('cost_per_player', this.createForm.cost_per_player);
        
        if (this.createForm.imageFile) {
          formData.append('image', this.createForm.imageFile);
        }

        await ownerMatchmakingService.create(formData);
        this.message = 'Tạo bài giao lưu thành công. Bài viết đã được hiển thị ở khu vực cộng đồng.';
        this.closeCreateModal();
        await this.loadPosts(1);
      } catch (err) {
        this.error = err.message || 'Tạo bài giao lưu thất bại.';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>
 
<style scoped>
/* ===================================================
   Matchmaking Page - CSS Variables Design System
   =================================================== */
 
.matchmaking-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1280px;
  margin: 0 auto;
}
 
/* ---- Notice banners ---- */
.notice {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
}
 
.notice.success {
  background: var(--admin-primary-soft);
  color: var(--admin-text);
  border: 1px solid var(--admin-border);
}
 
.notice.error {
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
  border: 1px solid var(--admin-danger);
}
.page-header h2 {
  font-size: 24px;
  font-weight: 850;
  color: #0f172a;
  margin: 0;
}
 
.muted {
  color: #64748b;
  margin: 4px 0 0;
  font-size: 14px;
}
 
.muted-icon {
  color: #94a3b8;
}
 
/* ---- Card base ---- */
.card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 12px;
  box-shadow: var(--admin-shadow-card);
}
 
/* ---- Filter toolbar ---- */
.filter-toolbar {
  display: flex;
  flex-direction: column;
  gap: 0;
  overflow: hidden;
}
 
.tabs-header {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
  flex-wrap: wrap;
  background: var(--admin-surface);
}
 
.filters-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
  padding: 12px 16px;
  background: var(--admin-surface-muted);
  border-top: 1px solid var(--admin-border);
}
 
.field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 11px;
  font-weight: 700;
  color: var(--admin-faint);
  letter-spacing: 0.03em;
  text-transform: uppercase;
  white-space: nowrap;
}
 
.field.compact {
  flex-direction: row;
  align-items: center;
  gap: 10px;
}
 
.search-field {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 12px;
  min-width: 260px;
  height: 36px;
  gap: 8px;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.search-field:focus-within {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}

.search-field input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text);
  padding: 0;
  height: 100%;
}

 
.select-field select {
  min-width: 180px;
  height: 36px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 10px;
  font-size: 13px;
  font-weight: 500;
  background: var(--admin-surface);
  color: var(--admin-text);
  outline: none;
  transition: border-color 0.15s;
}
 
.select-field select:focus {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}
 
/* ---- States ---- */
.state-box {
  display: flex;
  min-height: 220px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--admin-faint);
  text-align: center;
  padding: 32px;
}
 
.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--admin-border);
  border-top-color: var(--admin-text);
  border-radius: 50%;
  animation: spin 0.9s infinite linear;
}
 
@keyframes spin {
  to { transform: rotate(360deg); }
}
 
/* ---- Table ---- */
.table-container {
  padding: 0;
  overflow: hidden;
}
 
.table-scroll {
  overflow-x: auto;
}
 
table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1000px;
}
 
th, td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid var(--admin-border);
  font-size: 13px;
  vertical-align: middle;
}
 
th {
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  font-weight: 900;
  font-size: 10px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
 
.post-row:hover {
  background: var(--admin-hover);
}
 
tbody tr:last-child td {
  border-bottom: 0;
}
 
/* ---- Author cell ---- */
.author-cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
 
.author-cell strong {
  color: var(--admin-text);
  font-weight: 700;
  font-size: 13px;
}
 
.muted {
  color: var(--admin-faint);
  font-size: 12px;
}
 
.muted-icon {
  color: var(--admin-faint);
}
 
/* ---- Info cell ---- */
.info-cell {
  display: flex;
  flex-direction: column;
  gap: 5px;
  max-width: 380px;
}
 
.post-title {
  font-weight: 800;
  color: var(--admin-text);
  font-size: 14px;
  line-height: 1.3;
}
 
.post-desc {
  margin: 0;
  color: var(--admin-faint);
  font-size: 12px;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
 
.post-time-location, .post-court {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--admin-muted);
}
 
/* ---- Needed cell ---- */
.needed-cell {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
 
.needed-badge {
  display: inline-block;
  background: var(--admin-blue-soft);
  color: var(--admin-blue);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  width: fit-content;
}
 
.cost-badge {
  display: inline-block;
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  width: fit-content;
  border: 1px solid var(--admin-border);
}
 
/* ---- Status badges ---- */
.status-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
 
.status-open {
  background: var(--admin-warning-soft);
  color: var(--admin-warning);
}
 
.status-full {
  background: var(--admin-primary-soft);
  color: var(--admin-text);
}
 
.status-closed {
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  border: 1px solid var(--admin-border);
}
 
.status-cancelled {
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
}
 
.status-reason {
  margin-top: 4px;
  font-size: 11px;
  color: var(--admin-danger);
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
 
/* ---- Booking link cell ---- */
.booking-link-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
 
.booking-code {
  font-weight: 700;
  font-size: 12px;
  color: var(--admin-text);
  font-family: monospace;
  background: var(--admin-surface-muted);
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid var(--admin-border);
  width: fit-content;
}
 
.btn-link {
  color: var(--admin-blue);
  font-weight: 700;
  font-size: 12px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
 
.btn-link:hover {
  text-decoration: underline;
}
 
/* ---- Actions ---- */
.right {
  text-align: right;
}
 
.actions-cell {
  display: inline-flex;
  gap: 6px;
  justify-content: flex-end;
}
 
/* ---- Buttons ---- */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 34px;
  padding: 0 14px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.15s;
  white-space: nowrap;
}
 
.btn.primary {
  background: var(--admin-primary);
  color: var(--admin-bg);
  border-color: var(--admin-primary);
}
 
.btn.primary:hover {
  background: var(--admin-primary-light);
}
 
.btn.primary.danger {
  background: var(--admin-danger);
  color: #fff;
  border-color: var(--admin-danger);
}
 
.btn.primary.danger:hover {
  opacity: 0.85;
}
 
.btn.ghost {
  background: var(--admin-surface);
  border-color: var(--admin-border);
  color: var(--admin-text);
}
 
.btn.ghost:hover {
  background: var(--admin-hover);
}
 
.btn.ghost.danger {
  color: var(--admin-danger);
  border-color: var(--admin-danger);
}
 
.btn.ghost.danger:hover {
  background: var(--admin-danger-soft);
}
 
.btn-sm {
  height: 28px;
  padding: 0 10px;
  font-size: 12px;
  border-radius: 6px;
}
 
.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
 
/* ---- Icon button ---- */
.icon-btn {
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--admin-surface-muted);
  border: 1px solid var(--admin-border);
  border-radius: 50%;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}
 
.icon-btn:hover {
  background: var(--admin-hover);
  color: var(--admin-text);
}
 
/* ---- Pagination ---- */
.pagination-bar {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  border-top: 1px solid var(--admin-border);
}
 
.page-info {
  font-size: 13px;
  font-weight: 700;
  color: #475569;
}
 
/* ====== MODAL ====== */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(3px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 500;
  padding: 16px;
}
 
.modal {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 14px;
  box-shadow: var(--admin-shadow-lg);
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
 
.modal.small {
  max-width: 460px;
}
 
.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--admin-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
 
.modal-header h3 {
  font-size: 16px;
  font-weight: 800;
  color: var(--admin-text);
  margin: 0;
}
 
.modal-body {
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
 
.required {
  color: var(--admin-danger);
}
 
.warning-text {
  margin: 0;
  color: var(--admin-warning);
  background: var(--admin-warning-soft);
  border: 1px solid var(--admin-warning);
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.5;
}
 
.form-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field textarea,
.field select {
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 500;
  background: var(--admin-surface);
  color: var(--admin-text);
  outline: none;
  transition: border-color 0.15s;
  width: 100%;
}

.field textarea:focus,
.field select:focus {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}


.modal-footer {
  padding: 14px 20px;
  border-top: 1px solid var(--admin-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: var(--admin-surface-muted);
}
 
.small {
  font-size: 12px;
}
 
.fb-submit-btn:disabled {
  background: #505151 !important;
  color: #bcc0c4 !important;
  cursor: not-allowed !important;
}

.custom-dropdown-item {
  padding: 10px 12px;
  font-size: 14px;
  color: #050505;
  cursor: pointer;
  border-radius: 6px;
  transition: background 0.1s ease;
}

.custom-dropdown-item:hover {
  background: #f0f2f5;
}

.custom-dropdown-item.active {
  background: var(--admin-primary-soft, #e6f2ff);
  color: var(--admin-primary, #1877f2);
  font-weight: 600;
}
</style>