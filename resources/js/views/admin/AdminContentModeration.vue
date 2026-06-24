<template>
  <div class="moderation-page">
    <!-- Page Header -->
    <header class="page-header">
      <div class="page-header-left">
        <button class="btn-back" type="button" @click="$router.back()" title="Quay lại">
          <AppIcon name="arrowLeft" size="20" />
        </button>
        <div class="header-text">
          <h2 class="page-title">Kiểm duyệt nội dung</h2>
          <p class="page-desc">Duyệt bài viết, bình luận và xử lý báo cáo vi phạm từ người dùng.</p>
        </div>
      </div>
    </header>

    <!-- Controls Container -->
    <div class="controls-container">
      <!-- Segmented Tabs -->
      <div class="segmented-control">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          class="segment-btn"
          :class="{ active: activeTab === tab.value }"
          type="button"
          @click="changeTab(tab.value)"
        >
          <AppIcon :name="tab.icon" size="16" class="tab-icon" />
          <span class="tab-label">{{ tab.label }}</span>
        </button>
      </div>

      <!-- Filters -->
      <div class="filters-group">
        <div class="search-input-wrapper">
          <AppIcon name="search" size="16" class="search-icon" />
          <input
            v-model="filters.search"
            type="search"
            class="search-input"
            :placeholder="searchPlaceholder"
            @input="onFilterChange"
          />
        </div>

        <div v-if="activeTab !== 'reports'" class="select-wrapper">
          <select v-model="filters.status" class="modern-select" @change="loadData(1)">
            <option value="pending_review">Chờ duyệt</option>
            <option value="published">Đã duyệt (Xuất bản)</option>
            <option value="rejected">Bị từ chối</option>
            <option value="hidden">Đã ẩn</option>
            <option value="all">Tất cả trạng thái</option>
          </select>
          <AppIcon name="chevronDown" size="14" class="select-arrow" />
        </div>

        <div v-if="activeTab === 'reports'" class="select-wrapper">
          <select v-model="filters.reason" class="modern-select" @change="loadData(1)">
            <option value="">Tất cả lý do</option>
            <option v-for="opt in reasonOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
          <AppIcon name="chevronDown" size="14" class="select-arrow" />
        </div>

        <button class="btn-icon-modern" type="button" @click="refresh" title="Làm mới">
          <AppIcon name="refresh" size="16" />
        </button>
      </div>
    </div>

    <!-- Alerts -->
    <Transition name="fade-slide">
      <div v-if="message" class="alert-box success">
        <AppIcon name="circleCheck" size="18" />
        <span>{{ message }}</span>
      </div>
    </Transition>
    <Transition name="fade-slide">
      <div v-if="error" class="alert-box error">
        <AppIcon name="circleX" size="18" />
        <span>{{ error }}</span>
      </div>
    </Transition>

    <!-- Content Area -->
    <div class="content-card">
      <!-- Loading State -->
      <div v-if="loading" class="empty-state">
        <div class="modern-spinner"></div>
        <p>Đang tải dữ liệu...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="items.length === 0" class="empty-state">
        <div class="empty-icon-wrapper">
          <AppIcon name="fileText" size="32" />
        </div>
        <h3>Không có dữ liệu</h3>
        <p>Hiện không có nội dung nào cần kiểm duyệt.</p>
      </div>

      <!-- Data Table -->
      <div v-else class="table-container">
        <table class="modern-table">
          <thead>
            <tr v-if="activeTab === 'reports'">
              <th>Nội dung bị báo cáo</th>
              <th>Loại vi phạm</th>
              <th>Người báo cáo</th>
              <th>Thời gian</th>
              <th class="text-right">Thao tác</th>
            </tr>
            <tr v-else>
              <th>Tác giả</th>
              <th>Nội dung</th>
              <th>Đính kèm</th>
              <th>Thời gian tạo</th>
              <th class="text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <!-- Tab Reports -->
            <template v-if="activeTab === 'reports'">
              <tr v-for="item in items" :key="item.id" class="table-row">
                <td class="col-content">
                  <div class="content-wrap">
                    <span class="type-badge" :class="getReportableTypeClass(item.reportable_type)">
                      {{ getReportableTypeLabel(item.reportable_type) }}
                    </span>
                    <p class="content-preview">{{ getReportedContent(item) }}</p>
                  </div>
                </td>
                <td class="col-reason">
                  <div class="reason-tag">
                    <AppIcon name="alert" size="14" />
                    <span>{{ getReasonLabel(item.reason) }}</span>
                  </div>
                </td>
                <td class="col-author">
                  <div class="author-info">
                    <span class="author-name">{{ item.reporter?.full_name || item.reporter?.username || 'Ẩn danh' }}</span>
                  </div>
                </td>
                <td class="col-date">
                  <span class="date-text">{{ formatDate(item.created_at) }}</span>
                </td>
                <td class="col-actions text-right">
                  <div class="action-buttons">
                    <button class="action-btn view" title="Xem chi tiết" @click="openDetail(item)">
                      <AppIcon name="eye" size="16" />
                    </button>
                    <button class="action-btn resolve" title="Giải quyết" @click="openResolveReport(item)">
                      <AppIcon name="check" size="16" />
                    </button>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Tab Posts/Comments -->
            <template v-else>
              <tr v-for="item in items" :key="item.id" class="table-row">
                <td class="col-author">
                  <div class="author-info">
                    <span class="author-name">{{ item.author?.full_name || item.author?.username || 'Ẩn danh' }}</span>
                    <span v-if="item.venue_cluster" class="cluster-badge">
                      {{ item.venue_cluster?.name }}
                    </span>
                  </div>
                </td>
                <td class="col-content">
                  <div v-if="activeTab === 'venue_posts'" class="font-bold mb-1">{{ item.title }}</div>
                  <p class="content-preview">{{ activeTab === 'venue_posts' ? (item.short_description || stripTags(item.content)) : item.content }}</p>
                  <div v-if="item.hashtags && item.hashtags.length" class="hashtag-list">
                    <span v-for="tag in item.hashtags" :key="tag.id" class="hashtag-tiny">#{{ tag.name }}</span>
                  </div>
                </td>
                <td class="col-media">
                  <div v-if="item.media?.length" class="media-stack">
                    <img :src="item.media[0].file_path" alt="media" class="media-thumbnail" />
                    <span v-if="item.media.length > 1" class="media-overlay">+{{ item.media.length - 1 }}</span>
                  </div>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="col-date">
                  <span class="date-text">{{ formatDate(item.created_at) }}</span>
                </td>
                <td class="col-actions text-right">
                  <div class="action-buttons">
                    <button class="action-btn view" title="Xem chi tiết" @click="openDetail(item)">
                      <AppIcon name="eye" size="16" />
                    </button>
                    <button v-if="item.status === 'pending_review' || item.status === 'hidden' || item.status === 'rejected'" class="action-btn approve" title="Duyệt hiển thị" @click="approvePostDirect(item)">
                      <AppIcon name="check" size="16" />
                    </button>
                    <button class="action-btn reject" title="Hành động khác (Ẩn/Từ chối/Xóa)" @click="openActionModal(item)">
                      <AppIcon name="x" size="16" />
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="modern-pagination">
        <button
          class="page-btn"
          :disabled="pagination.current_page <= 1"
          @click="loadData(pagination.current_page - 1)"
        >
          <AppIcon name="chevronLeft" size="16" />
        </button>
        <span class="page-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          class="page-btn"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="loadData(pagination.current_page + 1)"
        >
          <AppIcon name="chevronRight" size="16" />
        </button>
      </div>
    </div>

    <!-- Modals (Detail, Action, Report Resolve) -->
    <!-- (Retained logical structure, but improved styling) -->
    
    <!-- Detail Modal -->
    <Transition name="fade">
      <div v-if="detailModal.open" class="modal-overlay" @click.self="closeDetail">
        <div class="modern-modal large-modal">
          <div class="modal-header">
            <h3>{{ activeTab === 'reports' ? 'Chi tiết báo cáo vi phạm' : 'Chi tiết nội dung chờ duyệt' }}</h3>
            <button class="close-btn" @click="closeDetail">
              <AppIcon name="x" size="20" />
            </button>
          </div>
          
          <div class="modal-body detail-layout">
            <!-- Left Side: Content Detail -->
            <div class="detail-content-side">
              <div class="info-section">
                <h4 class="section-heading">Nội dung gốc</h4>
                
                <div v-if="activeTab === 'reports' && activeItem.reportable_type === 'App\\Models\\CommunityPostComment'" class="context-box">
                  <span class="context-title">Bài viết liên quan:</span>
                  <p class="context-text">"{{ activeItem.reportable?.post?.content }}"</p>
                </div>

                <div class="author-profile">
                  <div class="avatar-circle">
                    {{ (getContentAuthor(activeItem).full_name || getContentAuthor(activeItem).username || '?').charAt(0).toUpperCase() }}
                  </div>
                  <div class="author-details">
                    <span class="author-name">{{ getContentAuthor(activeItem).full_name || getContentAuthor(activeItem).username || 'Ẩn danh' }}</span>
                    <span class="author-contact">{{ getContentAuthor(activeItem).email || 'Không có email' }}</span>
                  </div>
                </div>

                <div class="post-content-box">
                  <template v-if="activeTab === 'venue_posts' || (activeTab === 'reports' && activeItem.reportable_type?.includes('VenuePost'))">
                    <h5 class="font-bold text-lg mb-2">{{ activeItem.title || activeItem.reportable?.title }}</h5>
                    <p class="italic text-gray-500 mb-3">{{ activeItem.short_description || activeItem.reportable?.short_description }}</p>
                    <div class="main-text prose max-w-none" v-html="getContentText(activeItem)"></div>
                  </template>
                  <p v-else class="main-text">{{ getContentText(activeItem) }}</p>
                  
                  <div v-if="getContentMedia(activeItem).length" class="media-grid">
                    <img
                      v-for="med in getContentMedia(activeItem)"
                      :key="med.id"
                      :src="med.file_path"
                      class="grid-img"
                      @click="openLightbox(med.file_path)"
                      alt="media"
                    />
                  </div>
                  
                  <div v-if="getContentHashtags(activeItem).length" class="hashtag-list mt-3">
                    <span v-for="tag in getContentHashtags(activeItem)" :key="tag.id" class="hashtag-pill">#{{ tag.name }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Side: Actions/Report Details -->
            <div class="detail-action-side">
              <template v-if="activeTab === 'reports'">
                <div class="info-section highlight-section">
                  <h4 class="section-heading">Thông tin báo cáo</h4>
                  <div class="meta-list">
                    <div class="meta-item">
                      <span class="meta-label">Người báo cáo</span>
                      <span class="meta-value">{{ activeItem.reporter?.full_name || activeItem.reporter?.username }}</span>
                    </div>
                    <div class="meta-item">
                      <span class="meta-label">Lý do vi phạm</span>
                      <span class="meta-value text-danger font-bold">{{ getReasonLabel(activeItem.reason) }}</span>
                    </div>
                    <div class="meta-item">
                      <span class="meta-label">Mô tả thêm</span>
                      <span class="meta-value italic">{{ activeItem.description || 'Không có mô tả' }}</span>
                    </div>
                  </div>
                  
                  <div class="form-group mt-4">
                    <label class="form-label">Hướng giải quyết</label>
                    <div class="modern-select-wrapper">
                      <select v-model="reportForm.status" class="modern-input">
                        <option value="resolved">Chấp nhận báo cáo (Xử lý vi phạm)</option>
                        <option value="dismissed">Bác bỏ báo cáo (Giữ nguyên nội dung)</option>
                      </select>
                      <AppIcon name="chevronDown" size="14" class="select-arrow" />
                    </div>
                  </div>

                  <div v-if="reportForm.status === 'resolved'" class="form-group">
                    <label class="form-label">Hình thức xử phạt</label>
                    <div class="modern-select-wrapper">
                      <select v-model="reportForm.action_taken" class="modern-input">
                        <option value="content_hidden">Ẩn nội dung</option>
                        <option value="content_deleted">Xóa nội dung</option>
                        <option value="warning">Gửi cảnh báo</option>
                        <option value="account_locked">Khóa tài khoản</option>
                      </select>
                      <AppIcon name="chevronDown" size="14" class="select-arrow" />
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Ghi chú xử lý (Bắt buộc)</label>
                    <textarea v-model="reportForm.action_note" class="modern-textarea" rows="3" placeholder="Nhập ghi chú..."></textarea>
                  </div>
                </div>
              </template>

              <template v-else>
                <div class="info-section">
                  <h4 class="section-heading">Quyết định kiểm duyệt</h4>
                  <div class="form-group">
                    <label class="form-label">Lý do từ chối/ẩn (Nếu có)</label>
                    <textarea v-model="actionForm.reason" class="modern-textarea" rows="4" placeholder="Nhập lý do gửi đến tác giả..."></textarea>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-flat" @click="closeDetail">Đóng</button>
            <template v-if="activeTab === 'reports'">
              <button class="btn-solid primary" :disabled="savingAction || !reportForm.action_note" @click="submitResolveReport">
                {{ savingAction ? 'Đang xử lý...' : 'Xác nhận giải quyết' }}
              </button>
            </template>
            <template v-else>
              <div class="action-row">
                <button class="btn-solid danger" :disabled="savingAction || !actionForm.reason" @click="submitAction('delete')">Xóa vĩnh viễn</button>
                <button v-if="activeItem.status !== 'rejected'" class="btn-solid warning" :disabled="savingAction || !actionForm.reason" @click="submitAction('reject')">Từ chối</button>
                <button v-if="activeItem.status !== 'hidden'" class="btn-solid" style="background:#64748b; color:white" :disabled="savingAction || !actionForm.reason" @click="submitAction('hide')">Ẩn</button>
                <button v-if="activeItem.status !== 'published'" class="btn-solid success" :disabled="savingAction" @click="submitAction('approve')">Duyệt hiển thị</button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Quick Action Modal -->
    <Transition name="fade">
      <div v-if="actionModal.open" class="modal-overlay" @click.self="closeActionModal">
        <div class="modern-modal small-modal">
          <div class="modal-header">
            <h3>Xử lý nhanh</h3>
            <button class="close-btn" @click="closeActionModal"><AppIcon name="x" size="20" /></button>
          </div>
          <div class="modal-body">
            <div class="radio-group">
              <label class="modern-radio">
                <input type="radio" v-model="quickActionType" value="reject" />
                <span class="radio-mark"></span>
                <span class="radio-label">Từ chối bài viết</span>
              </label>
              <label class="modern-radio">
                <input type="radio" v-model="quickActionType" value="hide" />
                <span class="radio-mark"></span>
                <span class="radio-label">Ẩn bài viết</span>
              </label>
              <label class="modern-radio">
                <input type="radio" v-model="quickActionType" value="delete" />
                <span class="radio-mark"></span>
                <span class="radio-label">Xóa vĩnh viễn</span>
              </label>
            </div>
            <div class="form-group mt-4">
              <label class="form-label">Lý do (Bắt buộc)</label>
              <textarea v-model="actionForm.reason" class="modern-textarea" rows="3" placeholder="Lý do xử lý..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-flat" @click="closeActionModal">Hủy</button>
            <button class="btn-solid danger" :disabled="savingAction || !actionForm.reason" @click="submitQuickAction">Xác nhận</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Lightbox -->
    <Transition name="fade">
      <div v-if="lightbox.open" class="lightbox-overlay" @click="lightbox.open = false">
        <img :src="lightbox.img" class="lightbox-image" />
      </div>
    </Transition>

  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminModerationService } from '../../services/adminModeration.js';

export default {
  name: 'AdminContentModeration',
  components: { AppIcon },
  data() {
    return {
      activeTab: 'community_posts',
      items: [],
      loading: true,
      savingAction: false,
      error: '',
      message: '',
      filterTimer: null,
      filters: { search: '', reason: '', status: 'pending_review' },
      pagination: { current_page: 1, last_page: 1, total: 0 },
      tabs: [
        { label: 'Bài viết cộng đồng', value: 'community_posts', icon: 'users' },
        { label: 'Bài đăng cụm sân', value: 'venue_posts', icon: 'building' },
        { label: 'Báo cáo vi phạm', value: 'reports', icon: 'messageWarning' },
      ],
      reasonOptions: [
        { label: 'Spam quảng cáo', value: 'spam' },
        { label: 'Nội dung phản cảm', value: 'offensive' },
        { label: 'Thông tin giả mạo', value: 'fake' },
        { label: 'Quấy rối / Đả kích', value: 'harassment' },
        { label: 'Lý do khác', value: 'other' },
      ],
      detailModal: { open: false },
      actionModal: { open: false },
      resolveReportModal: { open: false },
      activeItem: null,
      actionForm: { reason: '' },
      reportForm: { status: 'resolved', action_taken: 'content_hidden', action_note: '' },
      quickActionType: 'reject',
      lightbox: { open: false, img: '' },
    };
  },
  computed: {
    searchPlaceholder() {
      return this.activeTab === 'reports' ? 'Tìm người báo cáo, mô tả...' : 'Tìm nội dung, tác giả...';
    },
  },
  mounted() {
    this.loadData();
  },
  methods: {
    async loadData(page = 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminModerationService.getQueue({ type: this.activeTab, ...this.filters, page });
        const paginator = response.data || {};
        this.items = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.items.length,
        };
      } catch (err) {
        this.error = err.message || 'Không thể tải hàng chờ kiểm duyệt.';
      } finally {
        this.loading = false;
      }
    },
    changeTab(tabValue) {
      if (this.activeTab === tabValue) return;
      this.activeTab = tabValue;
      this.clearAlerts();
      this.filters.search = '';
      this.filters.reason = '';
      this.filters.status = 'pending_review';
      this.loadData(1);
    },
    onFilterChange() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(() => this.loadData(1), 400);
    },
    refresh() {
      this.loadData(this.pagination.current_page);
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
    openDetail(item) {
      this.clearAlerts();
      this.activeItem = item;
      this.actionForm.reason = '';
      this.reportForm = {
        status: 'resolved',
        action_taken: 'content_hidden',
        action_note: item.description ? `Xử lý dựa trên báo cáo: ${item.description}` : '',
      };
      this.detailModal.open = true;
    },
    closeDetail() { this.detailModal.open = false; },
    openActionModal(item) {
      this.clearAlerts();
      this.activeItem = item;
      this.actionForm.reason = '';
      this.quickActionType = 'reject';
      this.actionModal.open = true;
    },
    closeActionModal() { this.actionModal.open = false; },
    openResolveReport(item) {
      this.clearAlerts();
      this.activeItem = item;
      this.reportForm = {
        status: 'resolved',
        action_taken: 'content_hidden',
        action_note: item.description ? `Xử lý vi phạm: ${item.description}` : '',
      };
      this.resolveReportModal.open = true;
    },
    closeResolveReport() { this.resolveReportModal.open = false; },
    openLightbox(filePath) {
      this.lightbox.img = filePath;
      this.lightbox.open = true;
    },

    async approvePostDirect(item) {
      this.clearAlerts();
      this.savingAction = true;
      try {
        await adminModerationService.approvePost(this.activeTab, item.id);
        this.message = 'Duyệt hiển thị bài viết thành công.';
        await this.loadData(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Duyệt bài viết thất bại.';
      } finally {
        this.savingAction = false;
      }
    },
    async submitQuickAction() {
      if (!this.activeItem) return;
      this.savingAction = true;
      this.clearAlerts();
      try {
        if (this.quickActionType === 'reject') {
          await adminModerationService.rejectPost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Đã từ chối bài viết thành công.';
        } else if (this.quickActionType === 'hide') {
          await adminModerationService.hidePost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Đã ẩn bài viết thành công.';
        } else if (this.quickActionType === 'delete') {
          await adminModerationService.deletePost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Đã gỡ bỏ bài viết thành công.';
        }
        this.closeActionModal();
        await this.loadData(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không thể thực thi hành động.';
      } finally {
        this.savingAction = false;
      }
    },
    async submitAction(actionType) {
      if (!this.activeItem) return;
      this.savingAction = true;
      this.clearAlerts();
      try {
        if (actionType === 'approve') {
          await adminModerationService.approvePost(this.activeTab, this.activeItem.id);
          this.message = 'Duyệt hiển thị bài viết thành công.';
        } else if (actionType === 'reject') {
          await adminModerationService.rejectPost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Từ chối bài viết thành công.';
        } else if (actionType === 'hide') {
          await adminModerationService.hidePost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Ẩn bài viết thành công.';
        } else if (actionType === 'delete') {
          await adminModerationService.deletePost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Gỡ bỏ bài viết thành công.';
        }
        this.closeDetail();
        await this.loadData(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Kiểm duyệt bài viết thất bại.';
      } finally {
        this.savingAction = false;
      }
    },
    async submitResolveReport() {
      if (!this.activeItem) return;
      this.savingAction = true;
      this.clearAlerts();
      try {
        await adminModerationService.resolveReport(this.activeItem.id, this.reportForm);
        this.message = 'Giải quyết báo cáo vi phạm thành công.';
        this.closeDetail();
        this.closeResolveReport();
        await this.loadData(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không thể giải quyết báo cáo.';
      } finally {
        this.savingAction = false;
      }
    },

    getReportableTypeLabel(type) {
      if (!type) return 'Nội dung';
      if (type.includes('CommunityPostComment')) return 'Bình luận';
      if (type.includes('VenuePost')) return 'Bài cụm sân';
      return 'Bài cộng đồng';
    },
    getReportableTypeClass(type) {
      if (!type) return 'badge-gray';
      if (type.includes('CommunityPostComment')) return 'badge-blue';
      if (type.includes('VenuePost')) return 'badge-purple';
      return 'badge-green';
    },
    getReportedContent(item) {
      if (!item.reportable) return '(Nội dung không còn tồn tại hoặc đã bị ẩn)';
      return item.reportable.content || '(Ảnh/File đính kèm)';
    },
    getReasonLabel(reason) {
      const map = { spam: 'Spam quảng cáo', offensive: 'Phản cảm', fake: 'Tin giả', harassment: 'Quấy rối', other: 'Khác' };
      return map[reason] || reason || '-';
    },
    getContentAuthor(item) {
      if (this.activeTab === 'reports') {
        const target = item.reportable;
        if (!target) return {};
        return target.author || target.user || {};
      }
      return item.author || {};
    },
    getContentText(item) {
      if (this.activeTab === 'reports') return item.reportable?.content || '(Không có nội dung chữ)';
      return item.content || '';
    },
    getContentMedia(item) {
      if (this.activeTab === 'reports') return item.reportable?.media || [];
      return item.media || [];
    },
    getContentHashtags(item) {
      if (this.activeTab === 'reports') return item.reportable?.hashtags || [];
      return item.hashtags || [];
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleDateString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit' }) + ' ' + date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    },
    stripTags(html) {
      if (!html) return '';
      const doc = new DOMParser().parseFromString(html, 'text/html');
      return doc.body.textContent || '';
    },
  },
};
</script>

<style scoped>
/* ==========================================
   GLOBAL PAGE STYLES
   Premium, vibrant, glassmorphism-inspired
========================================== */
.moderation-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1300px;
  margin: 0 auto;
  padding: 8px;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  color: #1e293b;
}

/* ---- Typography Helpers ---- */
.text-muted { color: #64748b; }
.text-danger { color: #ef4444; }
.font-bold { font-weight: 700; }
.italic { font-style: italic; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }

/* ---- Page Header ---- */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.btn-back {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.btn-back:hover {
  background: #f8fafc;
  color: #0f172a;
  transform: translateX(-2px);
  border-color: #cbd5e1;
}

.header-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
}

.page-desc {
  margin: 0;
  font-size: 14px;
  color: #64748b;
}

/* ---- Controls Container (Tabs & Filters) ---- */
.controls-container {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: #ffffff;
  padding: 12px 16px;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  border: 1px solid #f1f5f9;
}

/* Segmented Tabs */
.segmented-control {
  display: flex;
  background: #f1f5f9;
  padding: 4px;
  border-radius: 12px;
  gap: 4px;
}

.segment-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: none;
  background: transparent;
  color: #64748b;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.segment-btn:hover {
  color: #0f172a;
}

.segment-btn.active {
  background: #ffffff;
  color: #10b981;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Filters */
.filters-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.search-input-wrapper {
  position: relative;
  width: 260px;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.search-input {
  width: 100%;
  height: 42px;
  padding: 0 16px 0 40px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 14px;
  color: #1e293b;
  transition: all 0.2s ease;
}

.search-input:focus {
  outline: none;
  border-color: #10b981;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.select-wrapper {
  position: relative;
  min-width: 160px;
}

.select-arrow {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.modern-select {
  appearance: none;
  width: 100%;
  height: 42px;
  padding: 0 36px 0 16px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 14px;
  color: #1e293b;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modern-select:focus {
  outline: none;
  border-color: #10b981;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.btn-icon-modern {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-icon-modern:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #cbd5e1;
}

/* ---- Alerts ---- */
.alert-box {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 14px;
}

.alert-box.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.alert-box.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* ---- Content Area (Table & Empty States) ---- */
.content-card {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
  border: 1px solid #f1f5f9;
  overflow: hidden;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #64748b;
  text-align: center;
}

.empty-icon-wrapper {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  margin-bottom: 16px;
}

.empty-state h3 {
  margin: 0 0 8px;
  font-size: 18px;
  color: #1e293b;
}

.empty-state p {
  margin: 0;
  font-size: 14px;
}

.modern-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f1f5f9;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ---- Table Styles ---- */
.table-container {
  width: 100%;
  overflow-x: auto;
}

.modern-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  text-align: left;
}

.modern-table th {
  background: #f8fafc;
  padding: 16px 24px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #e2e8f0;
}

.table-row {
  transition: background-color 0.2s ease;
}

.table-row:hover {
  background: #f8fafc;
}

.table-row td {
  padding: 16px 24px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.table-row:last-child td {
  border-bottom: none;
}

/* Table Columns Specifics */
.col-content {
  max-width: 320px;
}

.content-preview {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  color: #334155;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.type-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  margin-bottom: 6px;
}
.badge-blue { background: #e0f2fe; color: #0284c7; }
.badge-purple { background: #f3e8ff; color: #7e22ce; }
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-gray { background: #f1f5f9; color: #475569; }

.reason-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #fef2f2;
  color: #dc2626;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.author-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.author-name {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}

.cluster-badge {
  display: inline-block;
  background: #f1f5f9;
  color: #475569;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  align-self: flex-start;
}

.date-text {
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
}

.hashtag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.hashtag-tiny {
  font-size: 11px;
  color: #64748b;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 500;
}

.media-stack {
  position: relative;
  width: 48px;
  height: 48px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.media-thumbnail {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.media-overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
}

/* Actions */
.action-buttons {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.action-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  background: #f8fafc;
  color: #64748b;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.action-btn.view:hover { background: #e0f2fe; color: #0284c7; }
.action-btn.resolve:hover, .action-btn.approve:hover { background: #dcfce7; color: #16a34a; }
.action-btn.reject:hover { background: #fef2f2; color: #dc2626; }

/* ---- Pagination ---- */
.modern-pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 16px;
  padding: 16px 24px;
  border-top: 1px solid #f1f5f9;
  background: #fcfcfd;
}

.page-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.page-btn:hover:not(:disabled) {
  background: #f8fafc;
  color: #0f172a;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f1f5f9;
}

.page-info {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
}

/* ==========================================
   MODALS
========================================== */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 20px;
}

.modern-modal {
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: 90vh;
}

.large-modal { width: 900px; max-width: 100%; }
.small-modal { width: 480px; max-width: 100%; }

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
}

.close-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: #f1f5f9;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
}

.modal-footer {
  padding: 20px 24px;
  background: #f8fafc;
  border-top: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}

/* Detail Layout */
.detail-layout {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 24px;
  padding: 24px;
}

@media (max-width: 768px) {
  .detail-layout { grid-template-columns: 1fr; }
}

.info-section {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.highlight-section {
  background: #f8fafc;
  border-color: #f1f5f9;
}

.section-heading {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 8px;
}
.section-heading::before {
  content: '';
  display: block;
  width: 4px;
  height: 16px;
  background: #10b981;
  border-radius: 4px;
}

.context-box {
  background: #fdf2f8;
  border-left: 3px solid #f472b6;
  padding: 12px 16px;
  border-radius: 0 8px 8px 0;
}

.context-title {
  font-size: 11px;
  font-weight: 800;
  color: #be185d;
  text-transform: uppercase;
}

.context-text {
  margin: 4px 0 0;
  font-size: 13px;
  font-style: italic;
  color: #831843;
}

.author-profile {
  display: flex;
  align-items: center;
  gap: 16px;
  padding-bottom: 16px;
  border-bottom: 1px dashed #e2e8f0;
}

.avatar-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #10b981;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 800;
}

.author-details {
  display: flex;
  flex-direction: column;
}

.author-contact {
  font-size: 13px;
  color: #64748b;
}

.post-content-box .main-text {
  font-size: 15px;
  line-height: 1.6;
  color: #1e293b;
  margin: 0 0 16px;
  white-space: pre-wrap;
}

.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 12px;
}

.grid-img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 10px;
  cursor: zoom-in;
  border: 1px solid #f1f5f9;
  transition: transform 0.2s;
}

.grid-img:hover { transform: scale(1.03); }

.hashtag-pill {
  background: #e0f2fe;
  color: #0284c7;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  display: inline-block;
  margin: 0 6px 6px 0;
}

/* Meta list for reports */
.meta-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.meta-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 600;
}

.meta-value {
  font-size: 14px;
  color: #0f172a;
}

/* Forms */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}

.modern-select-wrapper {
  position: relative;
}

.modern-input, .modern-textarea {
  width: 100%;
  padding: 12px 16px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  font-size: 14px;
  color: #1e293b;
  font-family: inherit;
  transition: all 0.2s;
}

.modern-input:focus, .modern-textarea:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

select.modern-input {
  appearance: none;
  cursor: pointer;
  padding-right: 36px;
}

.modern-textarea {
  resize: vertical;
}

/* Radio buttons */
.radio-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.modern-radio {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  padding: 12px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  transition: all 0.2s;
}

.modern-radio:hover {
  background: #f8fafc;
}

.modern-radio input {
  display: none;
}

.modern-radio input:checked + .radio-mark {
  background: #10b981;
  border-color: #10b981;
}
.modern-radio input:checked + .radio-mark::after {
  content: '';
  width: 8px;
  height: 8px;
  background: white;
  border-radius: 50%;
}

.modern-radio input:checked ~ .radio-label {
  font-weight: 700;
  color: #0f172a;
}

.radio-mark {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.radio-label {
  font-size: 14px;
  color: #475569;
}

/* Buttons */
.btn-flat {
  padding: 10px 20px;
  background: transparent;
  border: none;
  color: #64748b;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  border-radius: 8px;
  transition: all 0.2s;
}

.btn-flat:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.btn-solid {
  padding: 10px 20px;
  border: none;
  border-radius: 10px;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
  color: white;
}

.btn-solid:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-solid.primary { background: #0f172a; }
.btn-solid.primary:hover:not(:disabled) { background: #1e293b; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,23,42,0.2); }

.btn-solid.danger { background: #ef4444; }
.btn-solid.danger:hover:not(:disabled) { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,0.2); }

.btn-solid.warning { background: #f59e0b; }
.btn-solid.warning:hover:not(:disabled) { background: #d97706; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(245,158,11,0.2); }

.btn-solid.success { background: #10b981; }
.btn-solid.success:hover:not(:disabled) { background: #059669; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,185,129,0.2); }

.action-row {
  display: flex;
  gap: 12px;
}

/* Lightbox */
.lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  cursor: zoom-out;
  padding: 40px;
}

.lightbox-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: 8px;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
}

/* Animations */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-slide-enter-from, .fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
