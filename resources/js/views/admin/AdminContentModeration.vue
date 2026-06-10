<template>
  <div class="moderation-page">
    <!-- Toolbar bộ lọc và chuyển tab -->
    <div class="toolbar card">
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

      <div class="filters">
        <label class="field compact">
          <span>Tìm kiếm</span>
          <input
            v-model="filters.search"
            type="search"
            :placeholder="searchPlaceholder"
            @input="onFilterChange"
          />
        </label>

        <label v-if="activeTab === 'reports'" class="field compact">
          <span>Lý do báo cáo</span>
          <select v-model="filters.reason" @change="loadData(1)">
            <option value="">Tất cả</option>
            <option v-for="opt in reasonOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </label>

        <button class="btn ghost btn-refresh" type="button" @click="refresh">
          <AppIcon name="refresh" size="16" />
          <span>Làm mới</span>
        </button>
      </div>
    </div>

    <!-- Thông báo kết quả -->
    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <!-- Màn hình loading -->
    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải danh sách chờ kiểm duyệt...</p>
    </div>

    <!-- Màn hình trống -->
    <div v-else-if="items.length === 0" class="state-box card">
      <p>Không có nội dung nào cần kiểm duyệt.</p>
    </div>

    <!-- Bảng hiển thị danh sách -->
    <div v-else class="moderation-table card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr v-if="activeTab === 'reports'">
              <th>Nội dung bị báo cáo</th>
              <th>Loại vi phạm</th>
              <th>Mô tả báo cáo</th>
              <th>Người báo cáo</th>
              <th>Thời gian</th>
              <th class="right">Thao tác</th>
            </tr>
            <tr v-else>
              <th>Tác giả</th>
              <th>Nội dung</th>
              <th>Hashtag</th>
              <th>Ảnh / File</th>
              <th>Thời gian tạo</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <!-- Tab Báo Cáo Vi Phạm -->
            <template v-if="activeTab === 'reports'">
              <tr v-for="item in items" :key="item.id">
                <td>
                  <div class="reported-brief">
                    <span class="badge" :class="getReportableTypeClass(item.reportable_type)">
                      {{ getReportableTypeLabel(item.reportable_type) }}
                    </span>
                    <span class="text-truncate">{{ getReportedContent(item) }}</span>
                  </div>
                </td>
                <td>
                  <span class="status status-rejected">{{ getReasonLabel(item.reason) }}</span>
                </td>
                <td>
                  <div class="desc-text">{{ item.description || '(Không có mô tả)' }}</div>
                </td>
                <td>
                  <div class="main-title">{{ item.reporter?.full_name || item.reporter?.username || '-' }}</div>
                  <div class="muted">{{ item.reporter?.email || '' }}</div>
                </td>
                <td>{{ formatDate(item.created_at) }}</td>
                <td class="right">
                  <div class="actions">
                    <button class="icon-btn" type="button" title="Chi tiết báo cáo" @click="openDetail(item)">
                      <AppIcon name="eye" size="16" />
                    </button>
                    <button class="icon-btn approve" type="button" title="Xử lý vi phạm" @click="openResolveReport(item)">
                      <AppIcon name="check" size="16" />
                    </button>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Tab Bài đăng cộng đồng / Cụm sân -->
            <template v-else>
              <tr v-for="item in items" :key="item.id">
                <td>
                  <div class="main-title">{{ item.author?.full_name || item.author?.username || '-' }}</div>
                  <div class="muted">{{ item.author?.phone || item.author?.email || '' }}</div>
                  <div v-if="item.venue_cluster" class="badge-cluster">
                    {{ item.venue_cluster?.name }}
                  </div>
                </td>
                <td>
                  <div class="content-preview">{{ item.content }}</div>
                </td>
                <td>
                  <div class="hashtags-list">
                    <span v-for="tag in item.hashtags" :key="tag.id" class="tag">
                      #{{ tag.name }}
                    </span>
                    <span v-if="!item.hashtags?.length" class="muted">-</span>
                  </div>
                </td>
                <td>
                  <div v-if="item.media?.length" class="media-preview-box">
                    <img :src="item.media[0].file_path" alt="preview" class="media-thumb" />
                    <span v-if="item.media.length > 1" class="media-count">+{{ item.media.length - 1 }}</span>
                  </div>
                  <span v-else class="muted">Không có</span>
                </td>
                <td>{{ formatDate(item.created_at) }}</td>
                <td class="right">
                  <div class="actions">
                    <button class="icon-btn" type="button" title="Xem chi tiết" @click="openDetail(item)">
                      <AppIcon name="eye" size="16" />
                    </button>
                    <button class="icon-btn approve" type="button" title="Duyệt bài" @click="approvePostDirect(item)">
                      <AppIcon name="check" size="16" />
                    </button>
                    <button class="icon-btn danger" type="button" title="Từ chối/Ẩn/Xóa" @click="openActionModal(item)">
                      <AppIcon name="x" size="16" />
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Phân trang -->
      <div v-if="pagination.last_page > 1" class="pagination">
        <button
          class="btn ghost"
          type="button"
          :disabled="pagination.current_page <= 1"
          @click="loadData(pagination.current_page - 1)"
        >
          Trước
        </button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          class="btn ghost"
          type="button"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="loadData(pagination.current_page + 1)"
        >
          Sau
        </button>
      </div>
    </div>

    <!-- MODAL CHI TIẾT BÀI VIẾT / BÁO CÁO -->
    <div v-if="detailModal.open" class="modal-backdrop" @click.self="closeDetail">
      <div class="modal large">
        <div class="modal-header">
          <h3>
            {{ activeTab === 'reports' ? 'Chi tiết báo cáo vi phạm' : 'Chi tiết bài viết chờ duyệt' }}
          </h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeDetail">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <div class="modal-body detail-grid">
          <!-- Phần bên trái: Chi tiết nội dung bài đăng/bình luận -->
          <section class="detail-left">
            <div class="detail-card">
              <h4 class="section-title">Nội dung đăng tải</h4>
              
              <div v-if="activeTab === 'reports' && activeItem.reportable_type === 'App\\Models\\CommunityPostComment'" class="reported-comment-context">
                <div class="context-label">Bình luận trên bài viết gốc:</div>
                <blockquote class="parent-post-quote">
                  "{{ activeItem.reportable?.post?.content }}"
                  <cite>- {{ activeItem.reportable?.post?.author?.full_name || activeItem.reportable?.post?.author?.username || 'Tác giả' }}</cite>
                </blockquote>
              </div>

              <div class="author-block">
                <img
                  :src="getContentAuthor(activeItem).avatar_url || '/images/default-avatar.png'"
                  alt="avatar"
                  class="author-avatar"
                  @error="$event.target.src='/images/default-avatar.png'"
                />
                <div class="author-info">
                  <strong>{{ getContentAuthor(activeItem).full_name || getContentAuthor(activeItem).username || '-' }}</strong>
                  <span class="muted">{{ getContentAuthor(activeItem).email || getContentAuthor(activeItem).phone || 'Không có liên hệ' }}</span>
                </div>
              </div>

              <div class="main-content-body">
                <p class="content-text">{{ getContentText(activeItem) }}</p>

                <!-- Media Gallery -->
                <div v-if="getContentMedia(activeItem).length > 0" class="media-gallery">
                  <div
                    v-for="(med, idx) in getContentMedia(activeItem)"
                    :key="med.id"
                    class="media-item"
                    @click="openLightbox(med.file_path)"
                  >
                    <img :src="med.file_path" alt="media upload" />
                  </div>
                </div>

                <!-- Hashtags -->
                <div v-if="getContentHashtags(activeItem).length > 0" class="hashtags-box">
                  <span v-for="tag in getContentHashtags(activeItem)" :key="tag.id" class="hashtag-item">
                    #{{ tag.name }}
                  </span>
                </div>
              </div>
            </div>
          </section>

          <!-- Phần bên phải: Chi tiết báo cáo vi phạm và nút hành động -->
          <section class="detail-right-side">
            <div v-if="activeTab === 'reports'" class="detail-card highlight">
              <h4 class="section-title">Thông tin báo cáo</h4>
              <dl class="report-meta">
                <dt>Người báo cáo:</dt>
                <dd>{{ activeItem.reporter?.full_name }} ({{ activeItem.reporter?.username }})</dd>
                <dt>Lý do vi phạm:</dt>
                <dd>
                  <span class="status status-rejected">{{ getReasonLabel(activeItem.reason) }}</span>
                </dd>
                <dt>Thời gian báo cáo:</dt>
                <dd>{{ formatDate(activeItem.created_at) }}</dd>
                <dt>Mô tả chi tiết:</dt>
                <dd class="desc-box">{{ activeItem.description || '(Không có mô tả chi tiết)' }}</dd>
              </dl>
              
              <div class="resolve-form">
                <label class="field">
                  <span>Trạng thái giải quyết</span>
                  <select v-model="reportForm.status">
                    <option value="resolved">Resolved (Đồng ý báo cáo, xử lý vi phạm)</option>
                    <option value="dismissed">Dismissed (Bác bỏ báo cáo, giữ nội dung)</option>
                  </select>
                </label>

                <label v-if="reportForm.status === 'resolved'" class="field">
                  <span>Hình thức xử lý vi phạm</span>
                  <select v-model="reportForm.action_taken">
                    <option value="content_hidden">Ẩn nội dung vi phạm</option>
                    <option value="content_deleted">Xóa/Gỡ nội dung vi phạm</option>
                    <option value="account_locked">Khóa tài khoản người vi phạm (7 ngày)</option>
                    <option value="warning">Gửi cảnh báo tới người vi phạm</option>
                  </select>
                </label>

                <label class="field">
                  <span>Ghi chú xử lý / Lý do gửi người vi phạm (Bắt buộc)</span>
                  <textarea
                    v-model.trim="reportForm.action_note"
                    rows="3"
                    placeholder="Ghi chú chi tiết lý do và thông tin giải quyết..."
                    required
                  ></textarea>
                </label>
              </div>
            </div>

            <div v-else class="detail-card">
              <h4 class="section-title">Hành động kiểm duyệt</h4>
              <div class="action-form">
                <label class="field">
                  <span>Lý do ẩn/từ chối (Bắt buộc nếu ẩn/từ chối)</span>
                  <textarea
                    v-model.trim="actionForm.reason"
                    rows="4"
                    placeholder="Nhập lý do kiểm duyệt gửi tới tác giả..."
                  ></textarea>
                </label>
              </div>
            </div>
          </section>
        </div>

        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeDetail">Đóng</button>
          
          <template v-if="activeTab === 'reports'">
            <button
              class="btn primary"
              type="button"
              :disabled="savingAction || !reportForm.action_note"
              @click="submitResolveReport"
            >
              <AppIcon name="check" size="16" />
              <span>{{ savingAction ? 'Đang giải quyết...' : 'Giải quyết báo cáo' }}</span>
            </button>
          </template>

          <template v-else>
            <button class="btn danger" type="button" :disabled="savingAction || !actionForm.reason" @click="submitAction('delete')">
              <AppIcon name="trash" size="16" />
              <span>Xóa/Ẩn</span>
            </button>
            <button class="btn ghost text-danger" type="button" :disabled="savingAction || !actionForm.reason" @click="submitAction('reject')">
              <AppIcon name="x" size="16" />
              <span>Từ chối</span>
            </button>
            <button class="btn ghost text-warning" type="button" :disabled="savingAction || !actionForm.reason" @click="submitAction('hide')">
              <AppIcon name="lock" size="16" />
              <span>Ẩn bài</span>
            </button>
            <button class="btn primary" type="button" :disabled="savingAction" @click="submitAction('approve')">
              <AppIcon name="check" size="16" />
              <span>Duyệt hiển thị</span>
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- MODAL XỬ LÝ NHANH HÀNH ĐỘNG CHO POST (Từ chối, Ẩn, Xóa) -->
    <div v-if="actionModal.open" class="modal-backdrop" @click.self="closeActionModal">
      <div class="modal small">
        <div class="modal-header">
          <h3>Xử lý kiểm duyệt nội dung</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeActionModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <div class="modal-body">
          <p class="muted">Chọn hành động xử lý bài viết vi phạm:</p>
          <div class="action-options-group">
            <label class="action-radio">
              <input v-model="quickActionType" type="radio" value="reject" />
              <span>Từ chối bài viết (Yêu cầu nhập lý do)</span>
            </label>
            <label class="action-radio">
              <input v-model="quickActionType" type="radio" value="hide" />
              <span>Ẩn bài viết khỏi hệ thống (Yêu cầu nhập lý do)</span>
            </label>
            <label class="action-radio">
              <input v-model="quickActionType" type="radio" value="delete" />
              <span>Ẩn & Gỡ bài viết vĩnh viễn (Yêu cầu nhập lý do)</span>
            </label>
          </div>

          <label class="field">
            <span>Lý do xử lý (Bắt buộc)</span>
            <textarea
              v-model.trim="actionForm.reason"
              rows="4"
              placeholder="Nhập lý do gửi tới người chơi..."
            ></textarea>
          </label>
        </div>

        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeActionModal">Hủy</button>
          <button
            class="btn danger"
            type="button"
            :disabled="savingAction || !actionForm.reason"
            @click="submitQuickAction"
          >
            <span>Xác nhận xử lý</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL XỬ LÝ NHANH CHO REPORT -->
    <div v-if="resolveReportModal.open" class="modal-backdrop" @click.self="closeResolveReport">
      <div class="modal small">
        <div class="modal-header">
          <h3>Giải quyết báo cáo vi phạm</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeResolveReport">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <div class="modal-body">
          <div class="resolve-form">
            <label class="field">
              <span>Trạng thái giải quyết</span>
              <select v-model="reportForm.status">
                <option value="resolved">Resolved (Đồng ý báo cáo, xử lý vi phạm)</option>
                <option value="dismissed">Dismissed (Bác bỏ báo cáo, giữ nội dung)</option>
              </select>
            </label>

            <label v-if="reportForm.status === 'resolved'" class="field">
              <span>Hình thức xử lý vi phạm</span>
              <select v-model="reportForm.action_taken">
                <option value="content_hidden">Ẩn nội dung vi phạm</option>
                <option value="content_deleted">Xóa/Gỡ nội dung vi phạm</option>
                <option value="account_locked">Khóa tài khoản người vi phạm (7 ngày)</option>
                <option value="warning">Gửi cảnh báo tới người vi phạm</option>
              </select>
            </label>

            <label class="field">
              <span>Ghi chú xử lý / Lý do gửi người báo cáo (Bắt buộc)</span>
              <textarea
                v-model.trim="reportForm.action_note"
                rows="4"
                placeholder="Nhập lý do và ghi chú chi tiết giải quyết..."
              ></textarea>
            </label>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeResolveReport">Hủy</button>
          <button
            class="btn primary"
            type="button"
            :disabled="savingAction || !reportForm.action_note"
            @click="submitResolveReport"
          >
            <span>Giải quyết</span>
          </button>
        </div>
      </div>
    </div>

    <!-- LIGHTBOX XEM HÌNH ẢNH TO -->
    <div v-if="lightbox.open" class="lightbox-backdrop" @click="lightbox.open = false">
      <img :src="lightbox.img" alt="zoom" class="lightbox-img" />
    </div>
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
      filters: {
        search: '',
        reason: '',
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
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
      actionForm: {
        reason: '',
      },
      reportForm: {
        status: 'resolved',
        action_taken: 'content_hidden',
        action_note: '',
      },
      quickActionType: 'reject',
      lightbox: {
        open: false,
        img: '',
      },
    };
  },
  computed: {
    searchPlaceholder() {
      if (this.activeTab === 'reports') {
        return 'Tìm người báo cáo, mô tả...';
      }
      return 'Tìm nội dung bài đăng, tác giả...';
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
        const response = await adminModerationService.getQueue({
          type: this.activeTab,
          ...this.filters,
          page,
        });
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
      this.activeTab = tabValue;
      this.clearAlerts();
      this.filters.search = '';
      this.filters.reason = '';
      this.loadData(1);
    },
    onFilterChange() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(() => this.loadData(1), 300);
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
    closeDetail() {
      this.detailModal.open = false;
    },
    openActionModal(item) {
      this.clearAlerts();
      this.activeItem = item;
      this.actionForm.reason = '';
      this.quickActionType = 'reject';
      this.actionModal.open = true;
    },
    closeActionModal() {
      this.actionModal.open = false;
    },
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
    closeResolveReport() {
      this.resolveReportModal.open = false;
    },
    openLightbox(filePath) {
      this.lightbox.img = filePath;
      this.lightbox.open = true;
    },

    // Duyệt trực tiếp bài viết ở danh sách
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

    // Hành động xử lý nhanh cho Post
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

    // Xử lý kiểm duyệt từ trong Modal chi tiết
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

    // Giải quyết báo cáo vi phạm
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

    // Helpers dữ liệu động
    getReportableTypeLabel(type) {
      if (!type) return 'Nội dung';
      if (type.includes('CommunityPostComment')) return 'Bình luận';
      if (type.includes('VenuePost')) return 'Bài cụm sân';
      return 'Bài cộng đồng';
    },
    getReportableTypeClass(type) {
      if (!type) return '';
      if (type.includes('CommunityPostComment')) return 'type-comment';
      if (type.includes('VenuePost')) return 'type-venue';
      return 'type-community';
    },
    getReportedContent(item) {
      if (!item.reportable) return '(Nội dung không còn tồn tại hoặc đã bị ẩn)';
      return item.reportable.content || '(Ảnh/File đính kèm)';
    },
    getReasonLabel(reason) {
      const map = {
        spam: 'Spam quảng cáo',
        offensive: 'Phản cảm',
        fake: 'Tin giả',
        harassment: 'Quấy rối',
        other: 'Khác',
      };
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
      if (this.activeTab === 'reports') {
        return item.reportable?.content || '(Không có nội dung chữ)';
      }
      return item.content || '';
    },
    getContentMedia(item) {
      if (this.activeTab === 'reports') {
        return item.reportable?.media || [];
      }
      return item.media || [];
    },
    getContentHashtags(item) {
      if (this.activeTab === 'reports') {
        return item.reportable?.hashtags || [];
      }
      return item.hashtags || [];
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
  },
};
</script>

<style scoped>
.moderation-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1400px;
  margin: 0 auto;
}

.card {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
}

.toolbar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px;
}

.tabs-header {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 12px;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.2s;
}

.tab-btn:hover {
  background: #f8fafc;
  color: #0f172a;
}

.tab-btn.active {
  background: #f1f5f9;
  color: #0f172a;
}

.filters {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 800;
  color: var(--sg-text);
  min-width: 200px;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 0 12px;
  font-size: 14px;
  font-weight: 500;
  background: #fff;
  color: var(--sg-text);
}

.field input,
.field select {
  height: 40px;
}

.field textarea {
  padding-top: 10px;
  resize: vertical;
}

.btn,
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.15s;
}

.btn {
  height: 40px;
  padding: 0 16px;
  white-space: nowrap;
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.primary:hover {
  background: #1e293b;
}

.btn.ghost {
  background: #fff;
  border-color: var(--sg-border);
  color: var(--sg-text);
}

.btn.ghost:hover {
  background: #f8fafc;
}

.btn.danger {
  background: #dc2626;
  color: #fff;
}

.btn.danger:hover {
  background: #b91c1c;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-btn {
  width: 34px;
  height: 34px;
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #334155;
}

.icon-btn:hover {
  background: #f1f5f9;
}

.icon-btn.approve {
  color: #15803d;
}

.icon-btn.approve:hover {
  background: #f0fdf4;
}

.icon-btn.danger {
  color: #dc2626;
}

.icon-btn.danger:hover {
  background: #fef2f2;
}

.notice {
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 800;
}

.notice.success {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.notice.error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.state-box {
  display: flex;
  min-height: 240px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #64748b;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(15, 23, 42, 0.08);
  border-top-color: #0f172a;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.moderation-table {
  overflow: hidden;
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1000px;
  border-collapse: collapse;
}

th,
td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--sg-border);
  text-align: left;
  vertical-align: middle;
}

th {
  background: #f8fafc;
  font-size: 11px;
  font-weight: 900;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.main-title {
  color: var(--sg-text);
  font-weight: 800;
}

.muted {
  color: #94a3b8;
  font-size: 12px;
}

.content-preview,
.desc-text {
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  font-size: 14px;
}

.reported-brief {
  display: flex;
  align-items: center;
  gap: 8px;
}

.text-truncate {
  max-width: 240px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.badge {
  display: inline-flex;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.badge.type-comment {
  background: #e0f2fe;
  color: #0369a1;
}

.badge.type-venue {
  background: #f3e8ff;
  color: #6b21a8;
}

.badge.type-community {
  background: #dcfce7;
  color: #166534;
}

.badge-cluster {
  display: inline-block;
  margin-top: 4px;
  padding: 2px 6px;
  background: #f1f5f9;
  color: #475569;
  font-size: 11px;
  font-weight: 800;
  border-radius: 4px;
}

.tag {
  display: inline-block;
  background: #f1f5f9;
  color: #0f172a;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
  margin-right: 4px;
  margin-bottom: 4px;
}

.media-preview-box {
  position: relative;
  width: 48px;
  height: 48px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--sg-border);
}

.media-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.media-count {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 900;
}

.status {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
}

.status-rejected {
  background: #fee2e2;
  color: #991b1b;
}

.actions {
  display: inline-flex;
  gap: 8px;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px;
  border-top: 1px solid var(--sg-border);
  background: #f8fafc;
}

/* MODAL & DETAIL GRID */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  width: min(1000px, 94vw);
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal.small {
  width: min(550px, 92vw);
}

.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--sg-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 20px;
}

@media (max-width: 768px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

.detail-card {
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 16px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-card.highlight {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.section-title {
  margin: 0;
  font-size: 15px;
  font-weight: 900;
  color: #0f172a;
  border-left: 3px solid #10b981;
  padding-left: 8px;
}

.reported-comment-context {
  background: #fdf2f8;
  border: 1px solid #fbcfe8;
  border-radius: 6px;
  padding: 12px;
}

.context-label {
  font-size: 11px;
  font-weight: 900;
  color: #9d174d;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.parent-post-quote {
  margin: 0;
  font-style: italic;
  font-size: 13px;
  color: #475569;
}

.parent-post-quote cite {
  display: block;
  font-style: normal;
  font-weight: 800;
  font-size: 11px;
  margin-top: 4px;
  color: #64748b;
}

.author-block {
  display: flex;
  align-items: center;
  gap: 12px;
}

.author-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid var(--sg-border);
}

.content-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.6;
  color: #0f172a;
  white-space: pre-line;
}

.media-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 8px;
  margin-top: 12px;
}

.media-item {
  aspect-ratio: 1;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--sg-border);
  cursor: zoom-in;
}

.media-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s;
}

.media-item img:hover {
  transform: scale(1.05);
}

.hashtags-box {
  margin-top: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.hashtag-item {
  background: #f1f5f9;
  color: #0f172a;
  font-size: 12px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 4px;
}

.report-meta {
  margin: 0;
  display: grid;
  grid-template-columns: auto 1fr;
  row-gap: 8px;
  column-gap: 12px;
  font-size: 13.5px;
}

.report-meta dt {
  font-weight: 800;
  color: #64748b;
}

.report-meta dd {
  margin: 0;
  color: #0f172a;
}

.desc-box {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 6px;
  padding: 8px 12px;
  font-style: italic;
  max-height: 100px;
  overflow-y: auto;
}

.resolve-form,
.action-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 8px;
}

.action-options-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 12px 0;
}

.action-radio {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #334155;
  cursor: pointer;
}

.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid var(--sg-border);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  background: #f8fafc;
  border-bottom-left-radius: 12px;
  border-bottom-right-radius: 12px;
}



.text-danger {
  color: #dc2626 !important;
}

.text-warning {
  color: #d97706 !important;
}

/* LIGHTBOX */
.lightbox-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  cursor: zoom-out;
}

.lightbox-img {
  max-width: 90vw;
  max-height: 90vh;
  object-fit: contain;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  border-radius: 4px;
}
</style>
