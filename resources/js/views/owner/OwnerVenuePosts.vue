<template>
  <div class="posts-page">
    <div class="page-header sg-page-header">
      <div class="header-left sg-page-heading">
        <nav class="sg-breadcrumbs" aria-label="Breadcrumb">
          <router-link to="/owner/dashboard">Dashboard</router-link>
          <span>/</span>
          <span>Quản lý bài viết</span>
        </nav>
        <h2>Quản lý bài viết</h2>
        <p class="muted">Đăng tin tức, hướng dẫn sử dụng, quảng bá sân bãi và giải đấu.</p>
      </div>
      <button class="btn btn-create primary sg-primary-action" type="button" @click="openForm()">
        <i class="fas fa-plus mr-2"></i>
        <span>Tạo bài viết mới</span>
      </button>
    </div>

    <!-- Filters & Search -->
    <div class="filter-toolbar card premium-card flex gap-4 sg-filter-panel" style="display: flex; gap: 16px; flex-wrap: wrap;">
      <div class="filters" style="display: flex; gap: 16px; flex: 1; flex-wrap: wrap; align-items: flex-end;">
        <label class="field compact" style="flex: 1; min-width: 200px;">
          <span style="white-space: nowrap;">Tìm kiếm</span>
          <input 
            v-model="filters.keyword" 
            @keyup.enter="fetchPosts(1)"
            type="text" 
            class="modern-input"
            placeholder="Nhập tiêu đề tìm kiếm..." 
          />
        </label>
        <label class="field compact" style="flex: 1; min-width: 200px;">
          <span style="white-space: nowrap;">Danh mục</span>
          <select v-model="filters.post_type" class="modern-select" @change="fetchPosts(1)" style="width: 100%; min-width: 150px;">
            <option value="">Tất cả danh mục</option>
            <option value="promotion">Khuyến mãi</option>
            <option value="tournament">Giải đấu</option>
            <option value="news">Tin tức</option>
            <option value="notice">Thông báo</option>
            <option value="recruitment">Tuyển dụng</option>
          </select>
        </label>
        <div style="display: flex; gap: 8px; align-items: center; min-width: max-content;">
          <button @click="resetFilters" class="btn ghost compact" type="button" style="height: 42px;">Xóa lọc</button>
          <button @click="fetchPosts(1)" class="btn primary compact" type="button" style="height: 42px; background: #0f172a; color: white;">Tìm kiếm</button>
        </div>
      </div>
    </div>

    <!-- Toolbar: Tabs and Sort -->
    <div class="toolbar-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <div class="tabs-header modern-tabs">
        <button @click="setStatusFilter('')" :class="['tab-btn pill', { active: filters.status === '' }]">Tất cả</button>
        <button @click="setStatusFilter('published')" :class="['tab-btn pill', { active: filters.status === 'published' }]">Đã duyệt</button>
        <button @click="setStatusFilter('pending_review')" :class="['tab-btn pill', { active: filters.status === 'pending_review' }]">Chờ duyệt</button>
        <button @click="setStatusFilter('draft')" :class="['tab-btn pill', { active: filters.status === 'draft' }]">Bản nháp</button>
        <button @click="setStatusFilter('rejected')" :class="['tab-btn pill', { active: filters.status === 'rejected' }]">Từ chối</button>
        <button @click="setStatusFilter('hidden')" :class="['tab-btn pill', { active: filters.status === 'hidden' }]">Đã ẩn</button>
      </div>

      <div class="sort-options modern-tabs" style="border-radius: 12px; padding: 4px; background: rgba(255,255,255,0.6); backdrop-filter: blur(8px);">
        <button @click="toggleSort('created_at')" :class="['tab-btn pill', { active: sorting.by === 'created_at' }]" style="font-size: 13px;">
          Mới nhất <i v-if="sorting.by === 'created_at'" :class="sorting.order === 'desc' ? 'fas fa-arrow-down' : 'fas fa-arrow-up'"></i>
        </button>
        <button @click="toggleSort('view_count')" :class="['tab-btn pill', { active: sorting.by === 'view_count' }]" style="font-size: 13px;">
          Lượt xem <i v-if="sorting.by === 'view_count'" :class="sorting.order === 'desc' ? 'fas fa-arrow-down' : 'fas fa-arrow-up'"></i>
        </button>
      </div>
    </div>

    <!-- Loading Screen -->
    <div v-if="loading" class="state-box card premium-card sg-state-box">
      <div class="spinner"></div>
      <p>Đang tải danh sách bài viết...</p>
    </div>

    <!-- Empty Screen -->
    <div v-else-if="posts.length === 0" class="state-box card premium-card empty-state sg-state-box">
      <div class="empty-icon" style="font-size: 48px; display:flex; justify-content:center; align-items:center;">📰</div>
      <p>Chưa có bài viết nào khớp với bộ lọc hoặc bạn chưa tạo bài viết.</p>
      <button @click="openForm()" class="btn btn-create primary" style="margin-top: 16px;">Tạo bài viết đầu tiên</button>
    </div>

    <!-- Posts Grid -->
    <transition-group v-else name="list" tag="div" class="posts-list grid-view" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
      <div v-for="post in posts" :key="post.id" class="post-card card premium-card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
        
        <!-- Image Cover -->
        <div class="cover-image" style="height: 200px; position: relative; background: #f8fafc; cursor: pointer; border-bottom: 1px solid #f1f5f9; overflow: hidden;">
          <img
            v-if="hasThumbnail(post)"
            :src="getThumbnail(post)"
            style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease;"
            class="hover-scale"
            @error="handleThumbnailError(post.id)"
          />
          <div v-else style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #94a3b8; background: #f1f5f9;"><AppIcon name="image" size="36" /></div>
          
          <span class="status-badge" :class="post.status || 'draft'" style="position: absolute; top: 12px; right: 12px; font-size: 11px; font-weight: 800; background: rgba(255,255,255,0.95); padding: 6px 10px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); backdrop-filter: blur(4px);">
            {{ statusLabel(post.status) }}
          </span>
          <span style="position: absolute; top: 12px; left: 12px; font-size: 11px; font-weight: 800; background: rgba(15,23,42,0.85); padding: 6px 10px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); color: #fff; backdrop-filter: blur(4px);">
            {{ formatCategory(post.post_type) }}
          </span>
        </div>

        <div class="post-body" style="padding: 24px; flex: 1; display: flex; flex-direction: column;">
          <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 10px; color: #0f172a; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; transition: color 0.2s;">{{ post.title }}</h3>
          <p class="post-content" style="font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 20px; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ post.short_description || 'Không có mô tả...' }}</p>

          <div class="post-meta-info" style="display: flex; flex-direction: row; align-items: center; justify-content: space-between; font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 20px; gap: 8px;">
            <span style="display: flex; align-items: center; gap: 6px; white-space: nowrap;"><AppIcon name="clock" size="14" style="color: #94a3b8;" /> {{ formatDate(post.created_at) }}</span>
            <span style="display: flex; align-items: center; gap: 6px; color: #10b981; background: #ecfdf5; padding: 4px 12px; border-radius: 99px; white-space: nowrap; font-weight: 700;"><AppIcon name="eye" size="14" /> {{ post.view_count || 0 }}</span>
          </div>

          <!-- Rejection Reason -->
          <div v-if="post.status === 'rejected' && post.status_reason" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 14px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-start;">
            <AppIcon name="alert-circle" size="18" style="color: #ef4444; margin-top: 2px;" />
            <div>
              <strong style="display: block; font-size: 13px; color: #991b1b; margin-bottom: 4px;">Lý do từ chối:</strong>
              <span style="font-size: 13px; color: #b91c1c; line-height: 1.5;">{{ post.status_reason }}</span>
            </div>
          </div>

          <!-- Lock Warning -->
          <div v-if="post.status === 'hidden'" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 14px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-start;">
            <AppIcon name="lock" size="18" style="color: #f59e0b; margin-top: 2px;" />
            <div>
              <strong style="display: block; font-size: 13px; color: #b45309; margin-bottom: 4px;">Bài viết đã bị khóa</strong>
              <span style="font-size: 13px; color: #d97706; line-height: 1.5;">{{ post.status_reason || 'Bài viết bị ẩn bởi Quản trị viên và không thể thao tác.' }}</span>
            </div>
          </div>

          <div class="post-footer" style="padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 13px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 10px;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; justify-content: center; align-items: center; color: #64748b;"><AppIcon name="user" size="14" /></div>
              {{ post.author?.full_name || 'Bạn' }}
            </div>
            <div class="post-actions" style="display: flex; gap: 8px;">
              <a v-if="post.status === 'published'" :href="`/venues/${post.venue_cluster_id}?tab=posts`" target="_blank" class="btn ghost btn-sm action-btn" style="padding: 0; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid #e2e8f0; background: white;" title="Xem trên trang cụm sân">
                <AppIcon name="external-link" size="16" style="color: #3b82f6;" />
              </a>
              <!-- Pending review: chỉ xem, icon mắt -->
              <button v-if="post.status === 'pending_review'" class="btn ghost btn-sm action-btn" type="button" @click="openForm(post)" style="padding: 0; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid #bfdbfe; background: #eff6ff; transition: all 0.2s;" title="Xem chi tiết bài viết">
                <AppIcon name="eye" size="16" style="color: #3b82f6;" />
              </button>
              <!-- Rejected: được phép sửa lại, icon edit màu cam -->
              <button v-else-if="post.status === 'rejected'" class="btn ghost btn-sm action-btn" type="button" @click="openForm(post)" style="padding: 0; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid #fed7aa; background: #fff7ed; transition: all 0.2s;" title="Chỉnh sửa và gửi lại duyệt">
                <AppIcon name="edit" size="16" style="color: #f97316;" />
              </button>
              <!-- Draft / Published / Hidden: sửa bình thường, icon edit xanh -->
              <button v-else class="btn ghost btn-sm action-btn" type="button" @click="openForm(post)" style="padding: 0; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid #e2e8f0; background: white; transition: all 0.2s;" title="Chỉnh sửa bài viết">
                <AppIcon name="edit" size="16" style="color: #10b981;" />
              </button>
              <button class="btn ghost danger btn-sm action-btn" type="button" @click="confirmDelete(post)" style="padding: 0; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid #fecaca; background: #fef2f2; transition: all 0.2s;" title="Xóa bài viết">
                <AppIcon name="trash" size="16" style="color: #ef4444;" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition-group>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="pagination-bar">
      <button class="btn ghost btn-sm" type="button" :disabled="pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)">Trước</button>
      <span class="page-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button class="btn ghost btn-sm" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">Sau</button>
    </div>

    <!-- MODAL TẠO / SỬA BÀI ĐĂNG (NATIVE DIALOG STYLE) -->
    <dialog id="post_form_modal" class="modal-dialog-custom" @click="handleDialogClick">
      <div class="modal medium glass-modal" @click.stop style="max-width: 900px; width: 900px; padding: 0;">
        <div class="modal-header" style="padding: 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
          <h3 style="margin: 0; font-size: 20px; font-weight: 800; display: flex; align-items: center;">
            <AppIcon v-if="editingPostStatus === 'pending_review'" name="eye" size="20" style="color: #3b82f6; margin-right: 8px;" />
            <AppIcon v-else-if="editingPostStatus === 'rejected'" name="edit" size="20" style="color: #f97316; margin-right: 8px;" />
            <AppIcon v-else name="edit" size="20" style="color: #10b981; margin-right: 8px;" />
            {{ editingPostStatus === 'pending_review' ? 'Xem chi tiết bài viết' : (editingPostStatus === 'rejected' ? 'Chỉnh sửa & Gửi lại duyệt' : (isEditing ? 'Chỉnh sửa bài viết' : 'Tạo bài viết mới')) }}
          </h3>
          <button class="icon-btn close-btn" type="button" @click="closeForm"><AppIcon name="x" size="20" /></button>
        </div>

        <form @submit.prevent="submitForm">
          <div class="modal-body" style="padding: 24px; display: flex; flex-direction: column; gap: 0; max-height: 70vh; overflow-y: auto;">

            <!-- Banner: Đang chờ duyệt / Bị từ chối -->
            <template v-if="editingPostStatus === 'pending_review' || editingPostStatus === 'rejected'">
              <div style="margin: -24px -24px 20px -24px; padding: 14px 24px; display: flex; align-items: center; gap: 12px;"
                :style="editingPostStatus === 'pending_review' ?
                  'background: linear-gradient(135deg, #eff6ff, #dbeafe); border-bottom: 2px solid #93c5fd;' :
                  'background: linear-gradient(135deg, #fff7ed, #ffedd5); border-bottom: 2px solid #fb923c;'"
              >
                <div style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"
                  :style="editingPostStatus === 'pending_review' ? 'background: #3b82f6;' : 'background: #f97316;'"
                >
                  <AppIcon :name="editingPostStatus === 'pending_review' ? 'clock' : 'alert-triangle'" size="18" style="color: white;" />
                </div>
                <div>
                  <strong style="font-size: 14px; display: block;"
                    :style="editingPostStatus === 'pending_review' ? 'color: #1d4ed8;' : 'color: #c2410c;'"
                  >
                    {{ editingPostStatus === 'pending_review' ? 'Bài viết đang chờ Admin duyệt' : 'Bài viết bị từ chối — Hãy chỉnh sửa và gửi lại' }}
                  </strong>
                  <span style="font-size: 13px;"
                    :style="editingPostStatus === 'pending_review' ? 'color: #3b82f6;' : 'color: #ea580c;'"
                  >
                    {{ editingPostStatus === 'pending_review' ? 'Bạn không thể chỉnh sửa trong thời gian chờ duyệt.' : 'Sau khi chỉnh sửa, bài viết sẽ được tự động gửi lại để Admin xét duyệt.' }}
                  </span>
                </div>
              </div>
            </template>

            <!-- Form Row: Left + Right -->
            <div style="display: flex; flex-direction: row; gap: 24px;">
              <!-- Left Form -->
              <div style="flex: 2; display: flex; flex-direction: column; gap: 16px;">
              <label class="field" style="display: flex; flex-direction: column; gap: 6px;">
                <span style="font-size: 13px; font-weight: 700; color: #475569;">Tiêu đề bài viết <span class="required" style="color: #ef4444;">*</span></span>
                <input v-model="form.title" name="title" type="text" class="modern-input" :class="{ 'is-invalid': errors.title }" placeholder="Tiêu đề ấn tượng (5-200 ký tự)" required :disabled="editingPostStatus === 'pending_review'" />
                <p class="error-msg" v-if="errors.title" style="color: #ef4444; font-size: 12px; margin: 0; font-weight: 600;">{{ errors.title[0] }}</p>
              </label>

              <label class="field" style="display: flex; flex-direction: column; gap: 6px;">
                <span style="font-size: 13px; font-weight: 700; color: #475569;">Mô tả ngắn <span class="required" style="color: #ef4444;">*</span></span>
                <textarea v-model="form.short_description" name="short_description" :class="{ 'is-invalid': errors.short_description }" rows="2" class="modern-textarea" placeholder="Tóm tắt nội dung hấp dẫn người đọc..." required :disabled="editingPostStatus === 'pending_review'"></textarea>
                <p class="error-msg" v-if="errors.short_description" style="color: #ef4444; font-size: 12px; margin: 0; font-weight: 600;">{{ errors.short_description[0] }}</p>
              </label>

              <label class="field" style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
                <span style="font-size: 13px; font-weight: 700; color: #475569;">Nội dung chi tiết <span class="required" style="color: #ef4444;">*</span></span>
                <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; flex: 1; min-height: 350px;">
                  <RichTextEditor v-model="form.content" placeholder="Viết nội dung phong phú..." style="min-height: 350px;" :disabled="editingPostStatus === 'pending_review'" />
                </div>
                <p class="error-msg" v-if="errors.content" style="color: #ef4444; font-size: 12px; margin: 0; font-weight: 600;">{{ errors.content[0] }}</p>
              </label>
            </div>

            <!-- Right Sidebar -->
            <div style="flex: 1; display: flex; flex-direction: column; gap: 16px; background: #f8fafc; padding: 16px; border-radius: 16px;">
              <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                <span style="font-size: 13px; font-weight: 700; color: #475569;">Ảnh đại diện (Thumbnail)</span>
                <div class="upload-zone" style="aspect-ratio: 16/10; border: 2px dashed #cbd5e1; border-radius: 12px; position: relative; cursor: pointer; overflow: hidden; background: white;" :style="editingPostStatus === 'pending_review' ? 'cursor: not-allowed; opacity: 0.8;' : ''" @click="editingPostStatus !== 'pending_review' && !thumbnailPreview && $refs.fileInputRef.click()">
                  <div v-if="thumbnailPreview" style="position: absolute; inset: 0;">
                    <img :src="thumbnailPreview" style="width: 100%; height: 100%; object-fit: cover;" />
                    <button type="button" v-if="editingPostStatus !== 'pending_review'" @click.stop="clearThumbnail" style="position: absolute; top: 8px; right: 8px; background: rgba(239,68,68,0.9); color: white; border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10;"><AppIcon name="trash" size="14" /></button>
                  </div>
                  <div v-else style="position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #94a3b8;">
                    <AppIcon name="upload" size="24" style="margin-bottom: 8px;" />
                    <span style="font-size: 13px; font-weight: 700;">Tải ảnh lên</span>
                  </div>
                  <input type="file" ref="fileInputRef" style="display: none;" @click.stop @change="handleFileUpload" accept="image/*" />
                </div>
                <p class="error-msg" v-if="errors.thumbnail" style="color: #ef4444; font-size: 12px; margin: 0; font-weight: 600;">{{ errors.thumbnail[0] }}</p>
              </div>

              <label class="field compact" style="display: flex; flex-direction: column; gap: 6px;">
                <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #94a3b8;">Cơ sở / Cụm sân <span class="required" style="color: #ef4444;">*</span></span>
                <select v-model="form.venue_cluster_id" class="modern-select" :class="{ 'is-invalid': errors.venue_cluster_id }" required :disabled="editingPostStatus === 'pending_review'">
                  <option value="" disabled>-- Chọn cụm sân --</option>
                  <option v-for="cluster in venueClusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
                </select>
                <p class="error-msg" v-if="errors.venue_cluster_id" style="color: #ef4444; font-size: 12px; margin: 0; font-weight: 600;">{{ errors.venue_cluster_id[0] }}</p>
              </label>

              <label class="field compact" style="display: flex; flex-direction: column; gap: 6px;">
                <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #94a3b8;">Danh mục <span class="required" style="color: #ef4444;">*</span></span>
                <select v-model="form.post_type" class="modern-select" :class="{ 'is-invalid': errors.post_type }" required :disabled="editingPostStatus === 'pending_review'">
                  <option value="" disabled>-- Chọn danh mục --</option>
                  <option value="promotion">Khuyến mãi</option>
                  <option value="tournament">Giải đấu</option>
                  <option value="news">Tin tức</option>
                  <option value="notice">Thông báo</option>
                  <option value="recruitment">Tuyển dụng</option>
                </select>
                <p class="error-msg" v-if="errors.post_type" style="color: #ef4444; font-size: 12px; margin: 0; font-weight: 600;">{{ errors.post_type[0] }}</p>
              </label>



              <label class="field checkbox" v-if="editingPostStatus !== 'pending_review'" style="display: flex; flex-direction: row; align-items: center; gap: 8px; cursor: pointer; padding: 12px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 8px;">
                <input type="checkbox" v-model="form.is_draft" style="width: 18px; height: 18px; accent-color: #10b981;" />
                <span style="font-weight: 700; color: #1e293b; font-size: 14px;">Lưu làm bản nháp</span>
              </label>
            </div>
            <!-- /Form Row -->
            </div>

          </div>

          <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc;">
            <button class="btn ghost" type="button" @click="closeForm">{{ editingPostStatus === 'pending_review' ? 'Đóng' : 'Hủy bỏ' }}</button>
            <!-- Không hiện nút submit khi đang pending_review -->
            <button
              v-if="editingPostStatus !== 'pending_review'"
              class="btn primary"
              type="submit"
              :disabled="submitting"
              :style="editingPostStatus === 'rejected' ? 'background: #f97316; color: white;' : 'background: #0f172a; color: white;'"
            >
              <AppIcon v-if="submitting" name="loader" size="16" class="spin" style="margin-right: 8px;" />
              <AppIcon v-else-if="editingPostStatus === 'rejected'" name="send" size="16" style="margin-right: 8px;" />
              {{ submitting ? 'Đang xử lý...' : (editingPostStatus === 'rejected' ? 'Lưu & Gửi lại duyệt' : (isEditing ? 'Lưu thay đổi' : 'Đăng bài viết')) }}
            </button>
          </div>
        </form>
      </div>
    </dialog>

    <!-- DELETE MODAL -->
    <dialog id="delete_confirm_modal" class="modal-dialog-custom" @click="handleDeleteDialogClick">
      <div class="modal medium glass-modal" @click.stop style="max-width: 400px; padding: 0;">
        <div class="modal-body" style="padding: 32px 24px; text-align: center;">
          <div style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"><i class="fas fa-exclamation-triangle"></i></div>
          <h3 style="font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 8px;">Xác nhận xóa</h3>
          <p style="color: #64748b; font-size: 14px; margin-bottom: 24px;">Bạn có chắc chắn muốn xóa bài viết này không? Hành động không thể hoàn tác.</p>
          <div style="display: flex; gap: 12px;">
            <button class="btn ghost" style="flex: 1;" @click="closeDeleteModal">Hủy</button>
            <button class="btn danger" style="flex: 1; background: #ef4444; color: white;" @click="executeDelete">Xóa ngay</button>
          </div>
        </div>
      </div>
    </dialog>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { api, apiFormData } from '../../services/api';
import { useToast } from 'vue-toastification';
import RichTextEditor from '../../components/RichTextEditor.vue';
import AppIcon from '../../components/AppIcon.vue';
import { normalizeMediaUrl } from '../../utils/mediaUrl.js';

const toast = useToast();

const posts = ref([]);
const venueClusters = ref([]);
const loading = ref(false);
const submitting = ref(false);
const isEditing = ref(false);
const editingPostId = ref(null);
const editingPostStatus = ref('');
const fileInputRef = ref(null);

const thumbnailPreview = ref('');
const deletingPost = ref(null);

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
});

const filters = reactive({
  keyword: '',
  post_type: '',
  status: ''
});

const sorting = reactive({
  by: 'created_at',
  order: 'desc'
});

const form = reactive({
  venue_cluster_id: '',
  title: '',
  short_description: '',
  content: '',
  post_type: '',
  is_draft: false,
  thumbnail: null,
  tags: []
});

const errors = ref({});
const brokenThumbnails = ref(new Set());

onMounted(async () => {
  await Promise.all([
    fetchVenueClusters(),
    fetchPosts(),
  ]);
});

const fetchVenueClusters = async () => {
  try {
    const res = await api('/api/owner/venue-clusters?compact=1');
    venueClusters.value = res.data;
  } catch (error) {
    console.error('Error fetching venue clusters', error);
  }
};

const fetchPosts = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      keyword: filters.keyword,
      post_type: filters.post_type,
      status: filters.status,
      sort_by: sorting.by,
      sort_order: sorting.order,
      page
    };
    
    const res = await api('/api/owner/venue-posts' + (Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : ''));
    posts.value = res.data;
    brokenThumbnails.value = new Set();
    pagination.current_page = res.current_page;
    pagination.last_page = res.last_page;
    pagination.per_page = res.per_page;
    pagination.total = res.total;
  } catch (error) {
    toast.error('Lỗi khi tải danh sách bài viết');
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  fetchPosts(page);
};

const resetFilters = () => {
  filters.keyword = '';
  filters.post_type = '';
  filters.status = '';
  fetchPosts(1);
};

const setStatusFilter = (status) => {
  filters.status = status;
  fetchPosts(1);
};

const toggleSort = (field) => {
  if (sorting.by === field) {
    sorting.order = sorting.order === 'desc' ? 'asc' : 'desc';
  } else {
    sorting.by = field;
    sorting.order = 'desc';
  }
  fetchPosts(1);
};

const MAX_SOURCE_IMAGE_BYTES = 5 * 1024 * 1024;
const MAX_UPLOAD_IMAGE_BYTES = 2 * 1024 * 1024;

const handleFileUpload = async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    errors.value.thumbnail = ['Chỉ chấp nhận ảnh dạng JPG, JPEG, PNG, WEBP.'];
    toast.error('Chỉ chấp nhận ảnh đại diện dạng JPG, JPEG, PNG, WEBP.');
    if (fileInputRef.value) fileInputRef.value.value = '';
    return;
  }

  if (file.size > MAX_SOURCE_IMAGE_BYTES) {
    errors.value.thumbnail = ['Kích thước ảnh không được vượt quá 5MB.'];
    toast.error('Kích thước ảnh không được vượt quá 5MB.');
    if (fileInputRef.value) fileInputRef.value.value = '';
    return;
  }

  try {
    const uploadFile = file.size > MAX_UPLOAD_IMAGE_BYTES ? await compressImage(file, MAX_UPLOAD_IMAGE_BYTES) : file;
    form.thumbnail = uploadFile;
    thumbnailPreview.value = URL.createObjectURL(uploadFile);
    errors.value.thumbnail = null;
  } catch (error) {
    errors.value.thumbnail = ['Không thể xử lý ảnh. Vui lòng chọn ảnh JPG, PNG hoặc WEBP khác.'];
    toast.error('Không thể xử lý ảnh. Vui lòng chọn ảnh khác.');
    if (fileInputRef.value) fileInputRef.value.value = '';
  }
};

const compressImage = (file, maxBytes) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = reject;
    reader.onload = () => {
      const image = new Image();
      image.onerror = reject;
      image.onload = async () => {
        const canvas = document.createElement('canvas');
        const maxDimension = 1600;
        const scale = Math.min(1, maxDimension / Math.max(image.width, image.height));
        canvas.width = Math.max(1, Math.round(image.width * scale));
        canvas.height = Math.max(1, Math.round(image.height * scale));

        const context = canvas.getContext('2d');
        if (!context) {
          reject(new Error('Canvas is not supported.'));
          return;
        }

        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        for (const quality of [0.86, 0.78, 0.7, 0.62, 0.54]) {
          const blob = await canvasToBlob(canvas, 'image/webp', quality);
          if (blob && blob.size <= maxBytes) {
            resolve(new File([blob], replaceExtension(file.name, 'webp'), { type: 'image/webp' }));
            return;
          }
        }

        reject(new Error('Compressed image is still too large.'));
      };
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
};

const canvasToBlob = (canvas, type, quality) => {
  return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
};

const replaceExtension = (fileName, extension) => {
  const baseName = fileName.replace(/\.[^.]+$/, '') || 'thumbnail';
  return `${baseName}.${extension}`;
};

const clearThumbnail = () => {
  form.thumbnail = null;
  thumbnailPreview.value = '';
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
};

const openForm = (post = null) => {
  errors.value = {};
  clearThumbnail();

  if (post) {
    isEditing.value = true;
    editingPostId.value = post.id;
    editingPostStatus.value = post.status;
    
    form.venue_cluster_id = post.venue_cluster_id;
    const contentText = plainTextFromHtml(post.content);
    form.title = normalizeExistingTitle(post.title, contentText);
    form.short_description = normalizeExistingDescription(post.short_description, contentText);
    form.content = post.content;
    form.post_type = post.post_type;
    form.is_draft = post.status === 'draft';
    form.thumbnail = null;
    form.tags = post.hashtags ? post.hashtags.map(h => h.name) : [];
    form.meta_title = post.meta_title || '';
    form.meta_description = post.meta_description || '';
    
    // Existing image preview
    thumbnailPreview.value = getThumbnail(post);
  } else {
    isEditing.value = false;
    editingPostId.value = null;
    editingPostStatus.value = '';
    
    form.venue_cluster_id = venueClusters.value.length > 0 ? venueClusters.value[0].id : '';
    form.title = '';
    form.short_description = '';
    form.content = '';
    form.post_type = '';
    form.is_draft = false;
    form.thumbnail = null;
    form.tags = [];
  }
  document.getElementById('post_form_modal').showModal();
};

const closeForm = () => {
  const modal = document.getElementById('post_form_modal');
  if (modal) modal.close();
};

const submitForm = async () => {
  submitting.value = true;
  errors.value = {};

  const valErrors = {};
  if (!form.title || form.title.trim().length < 5 || form.title.trim().length > 200) {
    valErrors.title = ['Tiêu đề phải từ 5 đến 200 ký tự.'];
  }
  if (!form.short_description || form.short_description.trim().length < 10 || form.short_description.trim().length > 500) {
    valErrors.short_description = ['Mô tả ngắn phải từ 10 đến 500 ký tự.'];
  }
  
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = form.content;
  const rawText = (tempDiv.innerText || '').trim();
  if (!form.content || rawText.length < 20) {
    valErrors.content = ['Nội dung thực tế phải từ 20 ký tự trở lên.'];
  }
  
  if (!form.venue_cluster_id) {
    valErrors.venue_cluster_id = ['Vui lòng chọn cụm sân.'];
  }
  
  if (!form.post_type) {
    valErrors.post_type = ['Vui lòng chọn danh mục bài viết.'];
  }
  if (!isEditing.value && !form.thumbnail) {
    valErrors.thumbnail = ['Ảnh đại diện bài viết là bắt buộc khi tạo mới.'];
  }

  if (Object.keys(valErrors).length > 0) {
    errors.value = valErrors;
    submitting.value = false;
    toast.error('Vui lòng kiểm tra lại thông tin nhập.');
    focusFirstError();
    return;
  }
  
  const formData = new FormData();
  if (form.venue_cluster_id) {
    formData.append('venue_cluster_id', form.venue_cluster_id);
  }
  formData.append('title', form.title.trim());
  formData.append('short_description', form.short_description.trim());
  formData.append('content', form.content);
  formData.append('post_type', form.post_type);
  formData.append('is_draft', form.is_draft ? 1 : 0);
  
  if (form.thumbnail) {
    formData.append('thumbnail', form.thumbnail);
  }
  
  try {
    if (isEditing.value) {
      formData.append('_method', 'PUT');
      await apiFormData(`/api/owner/venue-posts/${editingPostId.value}`, formData);
      toast.success('Cập nhật bài viết thành công');
    } else {
      await apiFormData('/api/owner/venue-posts', formData);
      toast.success('Đăng bài viết mới thành công');
    }
    closeForm();
    fetchPosts(pagination.current_page);
  } catch (error) {
    if (error.response && error.response.status === 422) {
      errors.value = error.response.data.errors || {};
      toast.error(firstValidationMessage(error.response.data) || 'Dữ liệu nhập không hợp lệ, vui lòng kiểm tra lại.');
      focusFirstError();
    } else {
      toast.error('Có lỗi xảy ra trong quá trình xử lý, vui lòng thử lại.');
    }
  } finally {
    submitting.value = false;
  }
};

const focusFirstError = () => {
  setTimeout(() => {
    const firstErrKey = Object.keys(errors.value)[0];
    if (firstErrKey) {
      const element = document.querySelector(`[name="${firstErrKey}"]`);
      if (element) {
        element.focus();
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  }, 100);
};

const handleDialogClick = (e) => { if(e.target.id === 'post_form_modal') closeForm(); };
const handleDeleteDialogClick = (e) => { if(e.target.id === 'delete_confirm_modal') closeDeleteModal(); };

const confirmDelete = (post) => {
  deletingPost.value = post;
  document.getElementById('delete_confirm_modal').showModal();
};

const closeDeleteModal = () => {
  document.getElementById('delete_confirm_modal').close();
  deletingPost.value = null;
};

const executeDelete = async () => {
  if (!deletingPost.value) return;
  try {
    await api(`/api/owner/venue-posts/${deletingPost.value.id}`, { method: 'DELETE' });
    toast.success('Đã xóa bài viết thành công.');
    closeDeleteModal();
    fetchPosts(pagination.current_page);
  } catch (error) {
    if (error.response && error.response.status === 422) {
      toast.error(error.response.data.message || 'Lỗi khi xóa bài viết.');
    } else {
      toast.error('Lỗi khi thực hiện xóa bài viết, vui lòng thử lại.');
    }
    closeDeleteModal();
  }
};

const getThumbnail = (post) => {
  if (!post.media?.length) return '';
  const thumb = post.media.find((m) => m.collection === 'thumbnail') || post.media[0];
  return normalizeMediaUrl(thumb);
};

const plainTextFromHtml = (html = '') => {
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = html || '';
  return (tempDiv.innerText || tempDiv.textContent || '').replace(/\s+/g, ' ').trim();
};

const normalizeExistingTitle = (title, contentText = '') => {
  const normalized = String(title || '').trim();
  if (normalized.length >= 5 && normalized.length <= 200) return normalized;

  const fallback = contentText.slice(0, 80).trim();
  return fallback.length >= 5 ? fallback : 'Bài viết sân thể thao';
};

const normalizeExistingDescription = (description, contentText = '') => {
  const normalized = String(description || '').trim();
  if (normalized.length >= 10 && normalized.length <= 500) return normalized;

  const fallback = contentText.slice(0, 160).trim();
  return fallback.length >= 10 ? fallback : 'Thông tin cập nhật từ chủ sân.';
};

const firstValidationMessage = (data) => {
  const first = data?.errors ? Object.values(data.errors)[0] : null;
  if (Array.isArray(first) && first[0]) return first[0];
  return data?.message || '';
};

const hasThumbnail = (post) => {
  return Boolean(getThumbnail(post)) && !brokenThumbnails.value.has(post.id);
};

const handleThumbnailError = (postId) => {
  const next = new Set(brokenThumbnails.value);
  next.add(postId);
  brokenThumbnails.value = next;
};

const statusLabel = (status) => {
  const map = {
    'draft': 'Bản nháp',
    'pending_review': 'Chờ duyệt',
    'published': 'Đã xuất bản',
    'rejected': 'Bị từ chối',
    'hidden': 'Đã ẩn'
  };
  return map[status] || status;
};

const formatCategory = (type) => {
  const map = {
    'promotion': 'Khuyến mãi',
    'tournament': 'Giải đấu',
    'news': 'Tin tức',
    'notice': 'Thông báo',
    'recruitment': 'Tuyển dụng'
  };
  return map[type] || type;
};

const formatDate = (dateString) => {
  if (!dateString) return '—';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return dateString;
  return date.toLocaleDateString('vi-VN', {
    year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
  });
};
</script>
<style scoped>
.posts-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1200px;
  margin: 0 auto;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 8px;
}

.page-header h2 {
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 5px 0;
  letter-spacing: 0;
}

.muted {
  color: #64748b;
  margin: 0;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.55;
}

/* Premium Cards */
.premium-card {
  background: #ffffff;
  border: 1px solid rgba(226, 232, 240, 0.8);
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 42px;
  padding: 0 20px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-create {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-create:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.btn-create:active {
  transform: translateY(0);
}

.btn.primary.btn-submit {
  background: #0f172a;
  color: #fff;
  border-radius: 8px;
}
.btn.primary.btn-submit:hover {
  background: #1e293b;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}

.btn.ghost {
  background: transparent;
  border-color: #e2e8f0;
  color: #334155;
}

.btn.ghost:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.btn.ghost.danger {
  color: #ef4444;
  border-color: #fecaca;
  background: #fef2f2;
}

.btn.ghost.danger:hover {
  background: #fee2e2;
  border-color: #fca5a5;
}

.btn-sm {
  height: 32px;
  padding: 0 12px;
  font-size: 13px;
  border-radius: 6px;
}

/* Notices */
.notice {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  animation: slideDown 0.3s ease-out;
}

.notice.success {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.notice.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Filter Toolbar */
.filter-toolbar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
}

.modern-tabs {
  display: flex;
  gap: 12px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
  flex-wrap: wrap;
}

.tab-btn.pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 1px solid transparent;
  background: #f8fafc;
  color: #64748b;
  font-size: 14px;
  font-weight: 600;
  border-radius: 99px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.tab-btn.pill:hover {
  background: #f1f5f9;
  color: #334155;
}

.tab-btn.pill.active {
  background: #10b981;
  color: #fff;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
}

.filters {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.field.compact {
  flex-direction: row;
  align-items: center;
  gap: 12px;
}

.field.compact span {
  font-size: 14px;
  font-weight: 600;
  color: #475569;
}

.modern-select {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 36px 8px 12px;
  font-size: 14px;
  font-weight: 500;
  background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") no-repeat right 10px center/16px;
  color: #0f172a;
  appearance: none;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 200px;
}

.modern-select:hover {
  border-color: #cbd5e1;
}
.modern-select:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

/* Empty State / Loading */
.state-box {
  display: flex;
  min-height: 168px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  color: #64748b;
  text-align: center;
  padding: 40px;
}

.empty-state {
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.empty-icon {
  width: 80px;
  height: 80px;
  background: #f1f5f9;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  margin-bottom: 8px;
}

.state-box p {
  font-size: 16px;
  font-weight: 500;
}

.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid rgba(16, 185, 129, 0.1);
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 1s infinite linear;
}

/* Posts List */
.posts-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.post-card {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.post-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
}

.post-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.post-meta-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cluster-badge {
  background: #f1f5f9;
  color: #334155;
  font-weight: 700;
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 6px;
  align-self: flex-start;
  letter-spacing: 0.3px;
}

.post-time {
  color: #94a3b8;
  font-size: 13px;
  font-weight: 500;
}

.status-badge {
  font-size: 12px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 99px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.status-pending { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
.status-approved { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
.status-rejected { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
.status-hidden { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

.post-content {
  font-size: 15px;
  line-height: 1.7;
  color: #1e293b;
  margin: 0;
  white-space: pre-wrap;
}

/* Hashtags */
.hashtags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.hashtag.pill {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
  font-weight: 600;
  font-size: 13px;
  padding: 4px 10px;
  border-radius: 6px;
  transition: background 0.2s;
  cursor: default;
}
.hashtag.pill:hover {
  background: rgba(16, 185, 129, 0.15);
}

/* Media Gallery Grid */
.media-gallery {
  display: grid;
  gap: 8px;
  margin-top: 12px;
  border-radius: 12px;
  overflow: hidden;
}

.media-gallery.count-1 {
  grid-template-columns: 1fr;
}
.media-gallery.count-1 .media-item {
  aspect-ratio: 16/9;
}

.media-gallery.count-2 {
  grid-template-columns: 1fr 1fr;
}
.media-gallery.count-2 .media-item {
  aspect-ratio: 4/3;
}

.media-gallery.count-3 {
  grid-template-columns: 2fr 1fr;
  grid-template-rows: 1fr 1fr;
}
.media-gallery.count-3 .media-item:first-child {
  grid-row: span 2;
  aspect-ratio: auto;
  height: 100%;
}
.media-gallery.count-3 .media-item:not(:first-child) {
  aspect-ratio: 1;
}

.media-gallery.count-4 {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}
.media-gallery.count-4 .media-item {
  aspect-ratio: 1;
}

.media-item {
  position: relative;
  overflow: hidden;
  cursor: pointer;
}

.media-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.media-item:hover img {
  transform: scale(1.05);
}

.media-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  backdrop-filter: blur(2px);
}

/* Banners */
.reason-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  border-radius: 10px;
  font-size: 14px;
  line-height: 1.5;
}

.reason-banner.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.reason-banner.warning {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
}

/* Footer Stats */
.post-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #f1f5f9;
  padding-top: 16px;
  margin-top: 4px;
}

.post-stats {
  display: flex;
  gap: 20px;
}

.stat-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #64748b;
  font-size: 14px;
  font-weight: 500;
  transition: color 0.2s;
  cursor: pointer;
}
.stat-item:hover {
  color: #0ea5e9;
}

.action-btn {
  border: none;
  background: #f8fafc;
}
.action-btn:hover {
  background: #e2e8f0;
}

/* Modal */
.glass-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 20px;
}

.glass-modal {
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal.medium {
  max-width: 600px;
}

.modal-header {
  padding: 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.close-btn {
  background: #f1f5f9;
  color: #64748b;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
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
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.field > span {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.modern-textarea {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 15px;
  line-height: 1.6;
  resize: vertical;
  outline: none;
  transition: all 0.2s;
  font-family: inherit;
}
.modern-textarea:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.required {
  color: #ef4444;
}

/* Image Uploader */
.image-uploader-section > span {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 8px;
  display: block;
}

.modern-upload {
  border: 2px dashed #cbd5e1;
  border-radius: 16px;
  padding: 32px;
  text-align: center;
  cursor: pointer;
  background: #f8fafc;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.modern-upload:hover {
  border-color: #10b981;
  background: #ecfdf5;
}

.upload-icon-wrap {
  width: 56px;
  height: 56px;
  background: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.upload-icon {
  color: #10b981;
}

.modern-upload p {
  margin: 0;
  font-size: 14px;
  color: #64748b;
  font-weight: 500;
}

.media-preview-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 12px;
}

.preview-item {
  position: relative;
  width: 96px;
  height: 96px;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  border: 1px solid #e2e8f0;
}

.preview-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.remove-btn {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.6);
  color: #fff;
  border: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  opacity: 0;
  transition: all 0.2s;
  backdrop-filter: blur(4px);
}
.preview-item:hover .remove-btn {
  opacity: 1;
}
.remove-btn:hover {
  background: #ef4444;
  transform: scale(1.1);
}

.modal-footer {
  padding: 20px 24px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: #f8fafc;
  border-bottom-left-radius: 20px;
  border-bottom-right-radius: 20px;
}

.btn-cancel {
  background: white;
}

.spin {
  animation: spin 1s infinite linear;
}

/* Lightbox */
.lightbox-img {
  max-width: 90vw;
  max-height: 90vh;
  border-radius: 8px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  object-fit: contain;
}

/* Modal Native Dialog Styles */
.modal-dialog-custom {
  background: transparent;
  width: 100%;
  height: 100%;
  max-width: 100%;
  max-height: 100%;
  margin: 0;
  padding: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
}
.modal-dialog-custom::backdrop {
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
}
.modal-dialog-custom:not([open]) {
  display: none !important;
}

/* Transitions */
.list-enter-active, .list-leave-active {
  transition: all 0.4s ease;
}
.list-enter-from {
  opacity: 0;
  transform: translateY(20px);
}
.list-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.3s ease;
}
.fade-slide-enter-from, .fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.modal-fade-enter-active, .modal-fade-leave-active {
  transition: opacity 0.3s ease;
}
.modal-fade-enter-from, .modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-active .glass-modal {
  animation: modal-pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-fade-leave-active .glass-modal {
  animation: modal-pop 0.3s ease reverse;
}

@keyframes modal-pop {
  0% { opacity: 0; transform: scale(0.95); }
  100% { opacity: 1; transform: scale(1); }
}

.is-invalid {
  border-color: #ef4444 !important;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}
</style>
