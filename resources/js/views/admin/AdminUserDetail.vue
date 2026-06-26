<template>
  <section class="user-detail">
    <div class="back-action-bar">
      <BackButton to="/admin/users" />
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="state-card">Đang tải chi tiết tài khoản...</div>

    <template v-else-if="detail">
      <div class="detail-layout">
        <!-- SIDEBAR TRÁI -->
        <aside class="sidebar-panel">
          <div class="avatar">{{ initials(profile.full_name || profile.username) }}</div>
          <strong class="sidebar-name">{{ profile.full_name || '-' }}</strong>
          <span class="sidebar-meta">{{ profile.email || '-' }}</span>
          <span class="sidebar-meta">Tham gia: {{ date(profile.created_at) }}</span>

          <div class="sidebar-stats">
            <div class="sidebar-stat">
              <span>Report chưa xử lý</span>
              <strong :class="{ 'text-red': (detail.reports_summary?.reports_14_days || 0) >= 3 }">
                {{ detail.reports_summary?.reports_14_days || 0 }}
              </strong>
              <span v-if="(detail.reports_summary?.reports_14_days || 0) >= 3" class="badge-report">
                <AppIcon name="alert" size="12" style="margin-right: 4px;" /> Cảnh báo
              </span>
            </div>
            <div class="sidebar-stat">
              <span>Trạng thái</span>
              <span class="status" :class="profile.status">{{ profile.status_label || getAccountStatusLabel(profile.status) }}</span>
              <small v-if="profile.status === 'locked' && profile.locked_until" class="lock-until">đến {{ dateTime(profile.locked_until) }}</small>
              <small v-else-if="profile.status === 'locked'" class="lock-until">Vĩnh viễn</small>
            </div>
            <div class="sidebar-stat">
              <span>Tổng bình luận</span>
              <strong>{{ commentsMeta.total || detail.comments?.length || 0 }}</strong>
            </div>
            <div class="sidebar-stat">
              <span>Tổng bài đăng</span>
              <strong>{{ postsMeta.total || detail.posts?.length || 0 }}</strong>
            </div>
            <div class="sidebar-stat">
              <span>Lần bị khóa</span>
              <strong>{{ lockLogsMeta.total || 0 }}</strong>
            </div>
          </div>

          <div class="sidebar-actions">
            <button v-if="profile.status !== 'locked'" class="btn danger" type="button" @click="openLockModal">
              <AppIcon name="lock" size="16" style="margin-right: 6px; vertical-align: middle;" /> Khóa tài khoản
            </button>
            <button v-else class="btn" type="button" @click="openUnlockModal">
              <AppIcon name="unlock" size="16" style="margin-right: 6px; vertical-align: middle;" /> Mở khóa tài khoản
            </button>
          </div>
        </aside>

        <!-- CONTENT PHẢI + TABS -->
        <div class="content-panel">
          <nav class="tabs" aria-label="Tab chi tiết tài khoản">
            <button v-for="tab in tabs" :key="tab.value" type="button" :class="{ active: activeTab === tab.value }" @click="switchTab(tab.value)">
              {{ tab.label }}
            </button>
          </nav>

          <!-- Tab Tổng quan -->
          <section v-if="activeTab === 'overview'" class="panel">
            <h3>Tổng quan</h3>
            <div class="info-grid">
              <InfoItem label="Họ tên" :value="profile.full_name" />
              <InfoItem label="Username" :value="profile.username" />
              <InfoItem label="Email" :value="profile.email" />
              <InfoItem label="Số điện thoại" :value="profile.phone" />
              <InfoItem label="Trạng thái" :value="profile.status_label || getAccountStatusLabel(profile.status)" />
              <InfoItem label="Vai trò hiện tại" :value="profile.role_labels?.join(', ')" />
              <InfoItem label="Ngày tạo" :value="dateTime(profile.created_at)" />
              <InfoItem label="Cập nhật gần nhất" :value="dateTime(profile.updated_at)" />
              <InfoItem label="Lý do khóa" :value="profile.status_reason" />
              <InfoItem label="Người khóa" :value="profile.locked_by_name" />
              <InfoItem label="Khóa từ" :value="dateTime(profile.locked_at)" />
              <InfoItem label="Khóa đến" :value="dateTime(profile.locked_until)" />
            </div>
          </section>

          <!-- Tab Bình luận -->
          <section v-if="activeTab === 'comments'" class="panel">
            <h3>Bình luận của người dùng</h3>
            <div v-if="commentsLoading" class="state">Đang tải bình luận...</div>
            <template v-else>
              <div class="content-list">
                <article v-for="comment in comments" :key="comment.id" class="content-card">
                  <div class="content-card-body">
                    <p class="content-text">{{ truncate(comment.content, 150) }}</p>
                    <div v-if="comment.media && comment.media.length" class="content-media-preview">
                      <img v-for="m in comment.media.slice(0, 3)" :key="m.id" :src="m.url" class="media-thumb" />
                      <div v-if="comment.media.length > 3" class="media-more">+{{ comment.media.length - 3 }}</div>
                    </div>
                    <div class="content-meta">
                      <span class="status" :class="comment.status">{{ getPostStatusLabel(comment.status) }}</span>
                      <span>Bài viết: {{ comment.post_content ? truncate(comment.post_content, 40) : '-' }}</span>
                      <span><AppIcon name="messageSquare" size="14" style="margin-right: 4px; vertical-align: middle;" /> {{ comment.replies_count || 0 }} trả lời</span>
                      <span><AppIcon name="calendar" size="14" style="margin-right: 4px; vertical-align: middle;" /> {{ dateTime(comment.created_at) }}</span>
                    </div>
                  </div>
                  <div class="content-card-actions">
                    <button class="btn-sm icon-btn" type="button" title="Xem ngữ cảnh & Xử lý" @click="openContentViewer('comment', comment.id)">
                      <AppIcon name="messageCircle" size="16" />
                    </button>
                  </div>
                </article>
                <div v-if="comments.length === 0" class="state">Chưa có bình luận.</div>
              </div>
              <footer class="pagination" v-if="commentsMeta.total > 0">
                <span>{{ comments.length }} / {{ commentsMeta.total }}</span>
                <div>
                  <button class="btn-sm" :disabled="commentsMeta.current_page <= 1" @click="loadComments(commentsMeta.current_page - 1)">‹</button>
                  <span>{{ commentsMeta.current_page }} / {{ commentsMeta.last_page }}</span>
                  <button class="btn-sm" :disabled="commentsMeta.current_page >= commentsMeta.last_page" @click="loadComments(commentsMeta.current_page + 1)">›</button>
                </div>
              </footer>
            </template>
          </section>

          <!-- Tab Bài đăng -->
          <section v-if="activeTab === 'posts'" class="panel">
            <h3>Bài đăng của người dùng</h3>
            <div v-if="postsLoading" class="state">Đang tải bài đăng...</div>
            <template v-else>
              <div class="content-list">
                <article v-for="post in posts" :key="post.id" class="content-card">
                  <div class="content-card-body">
                    <p class="content-text">{{ truncate(post.content, 150) }}</p>
                    <div v-if="post.media && post.media.length" class="content-media-preview">
                      <img v-for="m in post.media.slice(0, 3)" :key="m.id" :src="m.url" class="media-thumb" />
                      <div v-if="post.media.length > 3" class="media-more">+{{ post.media.length - 3 }}</div>
                    </div>
                    <div class="content-meta">
                      <span class="status" :class="post.status">{{ getPostStatusLabel(post.status) }}</span>
                      <span><AppIcon name="messageSquare" size="14" style="margin-right: 4px; vertical-align: middle;" /> {{ post.comment_count || 0 }}</span>
                      <span><AppIcon name="heart" size="14" style="margin-right: 4px; vertical-align: middle;" /> {{ post.like_count || 0 }}</span>
                      <span><AppIcon name="calendar" size="14" style="margin-right: 4px; vertical-align: middle;" /> {{ dateTime(post.created_at) }}</span>
                    </div>
                  </div>
                  <div class="content-card-actions">
                    <button class="btn-sm icon-btn" type="button" title="Xem bài đăng & Xử lý" @click="openContentViewer('post', post.id)">
                      <AppIcon name="newspaper" size="16" />
                    </button>
                  </div>
                </article>
                <div v-if="posts.length === 0" class="state">Chưa có bài đăng.</div>
              </div>
              <footer class="pagination" v-if="postsMeta.total > 0">
                <span>{{ posts.length }} / {{ postsMeta.total }}</span>
                <div>
                  <button class="btn-sm" :disabled="postsMeta.current_page <= 1" @click="loadPosts(postsMeta.current_page - 1)">‹</button>
                  <span>{{ postsMeta.current_page }} / {{ postsMeta.last_page }}</span>
                  <button class="btn-sm" :disabled="postsMeta.current_page >= postsMeta.last_page" @click="loadPosts(postsMeta.current_page + 1)">›</button>
                </div>
              </footer>
            </template>
          </section>

          <!-- Tab Lịch sử khóa -->
          <section v-if="activeTab === 'lock-history'" class="panel">
            <h3>Lịch sử khóa / mở khóa</h3>
            <div v-if="lockLogsLoading" class="state">Đang tải lịch sử...</div>
            <template v-else>
              <div class="timeline">
                <article v-for="log in lockLogs" :key="log.id" class="timeline-item" :class="log.action">
                  <div class="timeline-icon">
                    <AppIcon :name="log.action === 'locked' ? 'lock' : 'unlock'" size="20" />
                  </div>
                  <div class="timeline-body">
                    <strong>{{ log.action_label }}</strong>
                    <span v-if="log.reason">{{ log.reason }}</span>
                    <span class="timeline-meta">
                      {{ log.performer_label }} · {{ dateTime(log.created_at) }}
                      <template v-if="log.action === 'locked'"> · {{ log.lock_until_label }}</template>
                    </span>
                    <span v-if="log.auto_triggered" class="badge-auto">Tự động</span>
                  </div>
                </article>
                <p v-if="lockLogs.length === 0" class="muted">Chưa có lịch sử khóa/mở khóa.</p>
              </div>
              <footer class="pagination" v-if="lockLogsMeta.total > 0">
                <span>{{ lockLogs.length }} / {{ lockLogsMeta.total }}</span>
                <div>
                  <button class="btn-sm" :disabled="lockLogsMeta.current_page <= 1" @click="loadLockLogs(lockLogsMeta.current_page - 1)">‹</button>
                  <span>{{ lockLogsMeta.current_page }} / {{ lockLogsMeta.last_page }}</span>
                  <button class="btn-sm" :disabled="lockLogsMeta.current_page >= lockLogsMeta.last_page" @click="loadLockLogs(lockLogsMeta.current_page + 1)">›</button>
                </div>
              </footer>
            </template>
          </section>

          <section v-if="activeTab === 'warnings'" class="panel">
            <h3>Cảnh báo & báo cáo về tài khoản</h3>
            <p class="notice">{{ detail.warning_summary?.near_lock_message || detail.warning_summary?.message }}</p>
            <div class="metric-row">
              <Metric label="Report 7 ngày" :value="detail.warning_summary?.reports_7_days || 0" />
              <Metric label="Report 14 ngày" :value="detail.warning_summary?.reports_14_days || 0" />
              <Metric label="Report 30 ngày" :value="detail.warning_summary?.reports_30_days || 0" />
              <Metric label="Khiếu nại mở" :value="detail.warning_summary?.complaints_open || 0" />
            </div>

            <div class="list-box" style="margin-top: 14px;">
              <article v-for="report in detail.reports_summary?.recent || []" :key="report.id" class="content-card">
                <div class="content-card-body">
                  <strong style="color: #b91c1c;">
                    Báo cáo tài khoản - {{ report.reason }}
                  </strong>
                  <span v-if="report.description" style="display: block; margin-top: 4px;">Chi tiết: {{ report.description }}</span>
                  <div class="content-meta" style="margin-top: 8px;">
                    <span class="status" :class="report.status === 'resolved' ? 'active' : 'pending'">
                      {{ report.status_label || report.status }}
                    </span>
                    <span><AppIcon name="calendar" size="14" style="margin-right: 4px; vertical-align: middle;" /> {{ dateTime(report.created_at) }}</span>
                  </div>
                </div>
              </article>
              <p v-if="!detail.reports_summary?.recent?.length" class="muted">Chưa có người báo cáo tài khoản này gần đây.</p>
            </div>

            <h3 style="margin-top: 20px;">Báo cáo về Bài đăng & Bình luận</h3>
            <div class="metric-row">
              <Metric label="Báo cáo bài đăng" :value="detail.content_reports_summary?.total_post_reports || 0" />
              <Metric label="Báo cáo bình luận" :value="detail.content_reports_summary?.total_comment_reports || 0" />
            </div>
            
            <div class="list-box" style="margin-top: 14px;">
              <article v-for="report in detail.content_reports_summary?.recent || []" :key="report.id" class="content-card">
                <div class="content-card-body">
                  <strong style="color: #b91c1c;">
                    {{ report.type === 'post' ? 'Bài đăng' : 'Bình luận' }} - {{ report.reason }}
                  </strong>
                  <span v-if="report.description" style="display: block; margin-top: 4px;">Chi tiết: {{ report.description }}</span>
                  <div class="content-meta" style="margin-top: 8px;">
                    <span class="status" :class="report.status === 'resolved' ? 'active' : 'pending'">
                      {{ getReportStatusLabel(report.status) }}
                    </span>
                    <span><AppIcon name="calendar" size="14" style="margin-right: 4px; vertical-align: middle;" /> {{ dateTime(report.created_at) }}</span>
                  </div>
                </div>
                <div class="content-card-actions">
                  <button class="btn-sm icon-btn" type="button" title="Xem chi tiết & Xử lý" @click="openContentViewer(report.type, report.target_id)">
                    <AppIcon name="fileSearch" size="16" />
                  </button>
                </div>
              </article>
              <p v-if="!detail.content_reports_summary?.recent?.length" class="muted">Chưa có báo cáo về bài đăng/bình luận.</p>
            </div>
          </section>

          <!-- Tab Audit log (giữ nguyên) -->
          <section v-if="activeTab === 'audit'" class="panel">
            <h3>Lịch sử thao tác / Audit log</h3>
            <div class="list-box">
              <article v-for="log in detail.audit_logs" :key="log.id">
                <strong>{{ log.action_label }}</strong>
                <span>{{ log.actor_name || 'Hệ thống' }} · {{ dateTime(log.created_at) }}</span>
                <span v-if="log.reason">Lý do: {{ log.reason }}</span>
              </article>
              <p v-if="!detail.audit_logs.length" class="muted">Chưa có audit log.</p>
            </div>
          </section>
        </div>
      </div>
    </template>

    <!-- Modal khóa tài khoản -->
    <div v-if="showLockModal" class="modal-backdrop" @click.self="showLockModal = false">
      <form class="modal" @submit.prevent="submitLock">
        <h3>Khóa tài khoản</h3>
        <p class="muted">{{ profile.full_name || profile.username }}</p>
        <label>
          <span>Lý do khóa *</span>
          <textarea v-model.trim="lockForm.reason" rows="4" required placeholder="Nhập lý do khóa tài khoản"></textarea>
        </label>
        <label>
          <span>Thời hạn khóa</span>
          <select v-model="lockForm.duration_hours">
            <option :value="1">1 giờ</option>
            <option :value="24">24 giờ</option>
            <option :value="168">7 ngày</option>
            <option :value="720">30 ngày</option>
            <option :value="null">Vĩnh viễn</option>
          </select>
        </label>
        <footer>
          <button type="button" class="btn secondary" @click="showLockModal = false">Hủy</button>
          <button type="submit" class="btn danger" :disabled="saving">Xác nhận khóa</button>
        </footer>
      </form>
    </div>

    <!-- Modal mở khóa (confirm dialog) -->
    <div v-if="showUnlockModal" class="modal-backdrop" @click.self="showUnlockModal = false">
      <form class="modal" @submit.prevent="submitUnlock">
        <h3>Mở khóa tài khoản</h3>
        <p class="muted">{{ profile.full_name || profile.username }}</p>
        <p>Bạn có chắc chắn muốn mở khóa tài khoản này?</p>
        <label>
          <span>Lý do mở khóa *</span>
          <textarea v-model.trim="unlockForm.reason" rows="3" required placeholder="Nhập lý do mở khóa"></textarea>
        </label>
        <footer>
          <button type="button" class="btn secondary" @click="showUnlockModal = false">Hủy</button>
          <button type="submit" class="btn" :disabled="saving">Xác nhận mở khóa</button>
        </footer>
      </form>
    </div>

    <!-- Modal Ngữ cảnh (Bài viết & Bình luận theo dạng Popup Dark UI) -->
    <div v-if="contentViewerData" class="modal-backdrop fb-backdrop" @click.self="closeContentViewer">
      <div class="fb-modal">
        <header class="fb-header">
          <div class="fb-header-spacer"></div>
          <h3>Bài viết của {{ contentViewerData.post.author_name }}</h3>
          <div class="fb-header-right">
            <button class="fb-close-btn" type="button" @click="closeContentViewer" title="Đóng">
              <AppIcon name="x" size="20" />
            </button>
          </div>
        </header>
        
        <div class="fb-body">
          <div v-if="contentViewerData.post" class="fb-post" :class="{ 'is-hidden': contentViewerData.post.status === 'hidden' }">
            <div class="post-status-banner" :class="contentViewerData.post.status">
              <AppIcon name="alert" size="16" v-if="contentViewerData.post.status === 'hidden'" />
              Trạng thái bài viết: <strong>{{ getPostStatusLabel(contentViewerData.post.status) }}</strong>
            </div>
            
            <div class="fb-post-header">
              <div class="fb-post-avatar">
                <img v-if="contentViewerData.post.author_avatar" :src="contentViewerData.post.author_avatar" />
                <div v-else class="fb-avatar-text">{{ initials(contentViewerData.post.author_name) }}</div>
              </div>
              <div class="fb-post-meta">
                <strong>{{ contentViewerData.post.author_name }}</strong>
                <span>{{ dateTime(contentViewerData.post.created_at) }}</span>
              </div>
            </div>
            <p class="fb-post-text">{{ contentViewerData.post.content }}</p>
            <div class="fb-media-container">
              <template v-if="contentViewerData.post.media && contentViewerData.post.media.length">
                <img v-for="m in contentViewerData.post.media" :key="m.url" :src="m.url" />
              </template>
              <template v-else>
                <!-- Fake image if there is no media to match the requested look -->
                <img src="https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Placeholder" class="fake-img" />
              </template>
            </div>
            <div class="fb-stats">
              <span class="like-button" @click="showLikesForPost(contentViewerData.post.id)" title="Xem người thả tim" style="cursor: pointer;">
                <AppIcon name="heart" size="18" /> {{ contentViewerData.post.like_count || 0 }}
              </span>
              <div class="fb-stats-right">
                <span>{{ contentViewerData.post.comment_count || contentViewerData.comments.length }} bình luận</span>
                <span style="cursor: pointer; display: flex; align-items: center; color: #64748b; margin-left: 10px;" @click="copyPostLink(contentViewerData.post.id)" title="Sao chép liên kết bài viết">
                  <AppIcon name="share" size="16" />
                </span>
              </div>
            </div>
            <div class="fb-actions">
              <!-- Nút thao tác bài viết chỉ bằng ICON -->
              <button 
                v-if="contentViewerData.post.status === 'hidden'" 
                class="fb-action-item" 
                title="Mở ẩn bài viết" 
                @click="quickContentAction('post', contentViewerData.post.id, 'unhide')"
              >
                <AppIcon name="eye" size="22" />
              </button>
              <button 
                v-else 
                class="fb-action-item" 
                title="Ẩn bài viết" 
                @click="quickContentAction('post', contentViewerData.post.id, 'hide')"
              >
                <AppIcon name="eyeOff" size="22" />
              </button>

              <button 
                class="fb-action-item danger" 
                title="Xóa bài viết" 
                @click="quickContentAction('post', contentViewerData.post.id, 'delete')"
              >
                <AppIcon name="trash" size="22" />
              </button>
            </div>
          </div>

          <div class="fb-comments">
            <div v-for="c in contentViewerData.comments" :key="c.id" class="fb-comment-group">
              <div 
                class="fb-comment-row"
                :class="{ 'highlighted': c.id === contentViewerData.target_comment_id }"
                :id="`comment-${c.id}`"
              >
                <div class="fb-comment-avatar">
                  <img v-if="c.user_avatar" :src="c.user_avatar" />
                  <div v-else class="fb-avatar-text">{{ initials(c.user_name) }}</div>
                </div>
                <div class="fb-comment-content" :class="{ 'is-hidden': c.status === 'hidden' }">
                  <div class="fb-bubble">
                    <strong>{{ c.user_name }}</strong>
                    <p>{{ c.content }}</p>
                    <span class="fb-bubble-status" :class="c.status" v-if="c.status !== 'visible' && c.status !== 'published'">
                      {{ getPostStatusLabel(c.status) }}
                    </span>
                  </div>
                  <div class="fb-comment-footer">
                    <span>{{ timeAgo(c.created_at) }}</span>
                    <span>Thích</span>
                    <span>Phản hồi</span>
                    <div class="fb-comment-tools">
                      <button 
                        v-if="c.status === 'hidden'" 
                        title="Mở ẩn bình luận" 
                        @click="quickContentAction('comment', c.id, 'unhide')"
                      >
                        <AppIcon name="eye" size="14" />
                      </button>
                      <button 
                        v-else 
                        title="Ẩn bình luận" 
                        @click="quickContentAction('comment', c.id, 'hide')"
                      >
                        <AppIcon name="eyeOff" size="14" />
                      </button>
                      <button 
                        title="Xóa bình luận" 
                        class="tool-danger" 
                        @click="quickContentAction('comment', c.id, 'delete')"
                      >
                        <AppIcon name="trash" size="14" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Nested replies -->
              <div v-if="c.replies && c.replies.length" class="fb-comment-replies">
                <div 
                  v-for="reply in c.replies" 
                  :key="reply.id" 
                  class="fb-comment-row reply-row"
                  :class="{ 'highlighted': reply.id === contentViewerData.target_comment_id }"
                  :id="`comment-${reply.id}`"
                >
                  <div class="fb-comment-avatar small">
                    <img v-if="reply.user_avatar" :src="reply.user_avatar" />
                    <div v-else class="fb-avatar-text">{{ initials(reply.user_name) }}</div>
                  </div>
                  <div class="fb-comment-content" :class="{ 'is-hidden': reply.status === 'hidden' }">
                    <div class="fb-bubble">
                      <strong>{{ reply.user_name }}</strong>
                      <p>{{ reply.content }}</p>
                      <span class="fb-bubble-status" :class="reply.status" v-if="reply.status !== 'visible' && reply.status !== 'published'">
                        {{ getPostStatusLabel(reply.status) }}
                      </span>
                    </div>
                    <div class="fb-comment-footer">
                      <span>{{ timeAgo(reply.created_at) }}</span>
                      <span>Thích</span>
                      <span>Phản hồi</span>
                      <div class="fb-comment-tools">
                        <button v-if="reply.status === 'hidden'" title="Mở ẩn" @click="quickContentAction('comment', reply.id, 'unhide')"><AppIcon name="eye" size="14" /></button>
                        <button v-else title="Ẩn" @click="quickContentAction('comment', reply.id, 'hide')"><AppIcon name="eyeOff" size="14" /></button>
                        <button title="Xóa" class="tool-danger" @click="quickContentAction('comment', reply.id, 'delete')"><AppIcon name="trash" size="14" /></button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="contentViewerData.comments.length === 0" class="fb-no-comments">Chưa có bình luận nào.</div>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Danh sách thả tim -->
    <PostLikesModal 
      :show="showLikesModal" 
      :postId="activeLikesPostId" 
      @close="showLikesModal = false" 
    />
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import BackButton from '../../components/BackButton.vue';
import PostLikesModal from '../../components/admin/PostLikesModal.vue';
import { adminUserService } from '../../services/adminUserService.js';
import { getAccountStatusLabel, getPostStatusLabel, getReportStatusLabel } from '../../utils/labelMaps.js';

const InfoItem = {
  props: { label: String, value: [String, Number] },
  template: '<div class="info-item"><span>{{ label }}</span><strong>{{ value || "-" }}</strong></div>',
};

const Metric = {
  props: { label: String, value: [String, Number] },
  template: '<div class="metric"><span>{{ label }}</span><strong>{{ value }}</strong></div>',
};

export default {
  name: 'AdminUserDetail',
  components: { AppIcon, InfoItem, Metric, BackButton, PostLikesModal },
  data() {
    return {
      detail: null,
      activeTab: 'overview',
      loading: false,
      saving: false,
      error: '',
      success: '',

      showLockModal: false,
      showUnlockModal: false,
      lockForm: { reason: '', duration_hours: 24 },
      unlockForm: { reason: '' },

      contentViewerData: null,
      contentViewerLoading: false,

      showLikesModal: false,
      activeLikesPostId: null,

      comments: [],
      commentsMeta: { current_page: 1, last_page: 1, total: 0 },
      commentsLoading: false,

      posts: [],
      postsMeta: { current_page: 1, last_page: 1, total: 0 },
      postsLoading: false,

      lockLogs: [],
      lockLogsMeta: { current_page: 1, last_page: 1, total: 0 },
      lockLogsLoading: false,

      tabs: [
        { value: 'overview', label: 'Tổng quan' },
        { value: 'comments', label: 'Bình luận' },
        { value: 'posts', label: 'Bài đăng' },
        { value: 'lock-history', label: 'Lịch sử khóa' },
        { value: 'warnings', label: 'Cảnh báo' },
        { value: 'audit', label: 'Audit log' },
      ],
    };
  },
  computed: {
    profile() {
      return this.detail?.profile || {};
    },
  },
  mounted() {
    this.loadDetail();
    this.loadLockLogs(1, true);
  },
  methods: {
    async copyPostLink(postId) {
      try {
        const link = window.location.origin + '/posts/' + postId;
        await navigator.clipboard.writeText(link);
        this.message = 'Đã sao chép liên kết bài viết.';
        this.messageType = 'success';
        setTimeout(() => { this.message = ''; }, 3000);
      } catch (err) {
        console.error('Failed to copy link:', err);
      }
    },
    switchTab(tab) {
      this.activeTab = tab;
      if (tab === 'comments' && this.comments.length === 0) this.loadComments(1);
      if (tab === 'posts' && this.posts.length === 0) this.loadPosts(1);
      if (tab === 'lock-history' && this.lockLogs.length === 0) this.loadLockLogs(1);
    },

    async loadDetail() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminUserService.show(this.$route.params.id);
        const data = response.data || {};
        if (!data.profile && data.user) data.profile = data.user;
        data.warning_summary = data.warning_summary || {};
        data.reports_summary = data.reports_summary || { recent: [] };
        data.complaints_summary = data.complaints_summary || { recent: [] };
        data.wallet_summary = data.wallet_summary || { ledgers: [] };
        data.booking_summary = data.booking_summary || {};
        data.recent_bookings = data.recent_bookings || [];
        data.roles = data.roles || [];
        data.permission_revokes = data.permission_revokes || [];
        data.audit_logs = data.audit_logs || [];
        this.detail = data;
        
        this.commentsMeta.total = data.comments?.length || 0;
        this.postsMeta.total = data.posts?.length || 0;
      } catch (err) {
        this.error = err.message || 'Không tải được chi tiết tài khoản.';
      } finally {
        this.loading = false;
      }
    },

    async loadComments(page = 1) {
      this.commentsLoading = true;
      try {
        const response = await adminUserService.show(this.$route.params.id);
        const allComments = response.data?.comments || [];
        const perPage = 20;
        const start = (page - 1) * perPage;
        this.comments = allComments.slice(start, start + perPage);
        this.commentsMeta = {
          current_page: page,
          last_page: Math.ceil(allComments.length / perPage) || 1,
          total: allComments.length,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được bình luận.';
      } finally {
        this.commentsLoading = false;
      }
    },

    async loadPosts(page = 1) {
      this.postsLoading = true;
      try {
        const response = await adminUserService.show(this.$route.params.id);
        const allPosts = response.data?.posts || [];
        const perPage = 20;
        const start = (page - 1) * perPage;
        this.posts = allPosts.slice(start, start + perPage);
        this.postsMeta = {
          current_page: page,
          last_page: Math.ceil(allPosts.length / perPage) || 1,
          total: allPosts.length,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được bài đăng.';
      } finally {
        this.postsLoading = false;
      }
    },

    async loadLockLogs(page = 1, hiddenLoad = false) {
      if (!hiddenLoad) this.lockLogsLoading = true;
      try {
        const response = await adminUserService.lockLogs(this.$route.params.id, page);
        this.lockLogs = response.data || [];
        this.lockLogsMeta = response.meta || { current_page: 1, last_page: 1, total: 0 };
      } catch (err) {
        if (!hiddenLoad) this.error = err.message || 'Không tải được lịch sử khóa.';
      } finally {
        if (!hiddenLoad) this.lockLogsLoading = false;
      }
    },

    async openContentViewer(type, id) {
      this.contentViewerLoading = true;
      try {
        let response;
        if (type === 'comment') {
          response = await adminUserService.commentDetail(id);
          this.contentViewerData = {
            target_comment_id: response.data.target_comment_id,
            post: response.data.post,
            comments: response.data.comments || [],
          };
        } else if (type === 'post') {
          response = await adminUserService.postDetail(id);
          const postData = response.data?.data || response.data;
          
          let comments = postData.comments || response.comments || [];

          this.contentViewerData = {
            target_comment_id: null,
            post: {
              id: postData.id,
              content: postData.content,
              status: postData.status,
              author_name: postData.author?.full_name || postData.author?.username || postData.author_name,
              media: postData.media || [],
              created_at: postData.created_at,
              like_count: postData.like_count || 0,
              comment_count: postData.comment_count || 0,
            },
            comments: comments,
          };
        }
        
        this.$nextTick(() => {
          if (this.contentViewerData?.target_comment_id) {
            const el = document.getElementById(`comment-${this.contentViewerData.target_comment_id}`);
            if (el) {
              el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }
        });
      } catch (err) {
        this.error = err.message || 'Không tải được nội dung.';
      } finally {
        this.contentViewerLoading = false;
      }
    },

    closeContentViewer() {
      this.contentViewerData = null;
    },

    showLikesForPost(postId) {
      this.activeLikesPostId = postId;
      this.showLikesModal = true;
    },

    async quickContentAction(type, id, action) {
      const actionName = action === 'delete' ? 'xóa' : (action === 'unhide' ? 'mở ẩn' : 'ẩn');
      if (!confirm(`Bạn có chắc chắn muốn ${actionName} nội dung này?`)) return;
      
      this.saving = true;
      this.error = '';
      try {
        const response = await adminUserService.processContentAction(type, id, action);
        this.success = response.message || 'Xử lý thành công.';
        
        if (type === 'post') {
          if (action === 'delete') {
            this.posts = this.posts.filter((p) => p.id !== id);
            this.postsMeta.total = Math.max(0, this.postsMeta.total - 1);
            if (this.contentViewerData?.post?.id === id) this.closeContentViewer();
            this.loadComments(this.commentsMeta.current_page || 1);
          } else {
            const newStatus = action === 'unhide' ? 'published' : 'hidden';
            const idx = this.posts.findIndex((p) => p.id === id);
            if (idx !== -1) this.posts[idx].status = newStatus;
            if (this.contentViewerData?.post?.id === id) this.contentViewerData.post.status = newStatus;
          }
        } else {
          if (action === 'delete') {
            this.comments = this.comments.filter((c) => c.id !== id);
            this.commentsMeta.total = Math.max(0, this.commentsMeta.total - 1);
            if (this.contentViewerData) {
              const isRoot = this.contentViewerData.comments.find(c => c.id === id);
              if (isRoot) {
                this.contentViewerData.comments = this.contentViewerData.comments.filter((c) => c.id !== id);
              } else {
                this.contentViewerData.comments.forEach(c => {
                  if (c.replies) c.replies = c.replies.filter(r => r.id !== id);
                });
              }
            }
          } else {
            const newStatus = action === 'unhide' ? 'visible' : 'hidden';
            const idx = this.comments.findIndex((c) => c.id === id);
            if (idx !== -1) this.comments[idx].status = newStatus;
            if (this.contentViewerData) {
              const cIdx = this.contentViewerData.comments.findIndex((c) => c.id === id);
              if (cIdx !== -1) {
                this.contentViewerData.comments[cIdx].status = newStatus;
                if (this.contentViewerData.comments[cIdx].replies) {
                  this.contentViewerData.comments[cIdx].replies.forEach(r => r.status = newStatus);
                }
              } else {
                this.contentViewerData.comments.forEach(c => {
                  if (c.replies) {
                    const rIdx = c.replies.findIndex(r => r.id === id);
                    if (rIdx !== -1) c.replies[rIdx].status = newStatus;
                  }
                });
              }
            }
          }
        }
        setTimeout(() => { this.success = ''; }, 3000);
      } catch (err) {
        this.error = err.message || 'Lỗi khi xử lý nội dung.';
      } finally {
        this.saving = false;
      }
    },

    openLockModal() {
      this.lockForm = { reason: '', duration_hours: 24 };
      this.showLockModal = true;
    },
    openUnlockModal() {
      this.unlockForm = { reason: '' };
      this.showUnlockModal = true;
    },

    async submitLock() {
      this.saving = true;
      this.error = '';
      try {
        const response = await adminUserService.lockUser(this.profile.id, {
          reason: this.lockForm.reason,
          duration_hours: this.lockForm.duration_hours,
        });
        this.success = response.message || 'Khóa tài khoản thành công.';
        this.showLockModal = false;
        await this.loadDetail();
        this.lockLogs = [];
        if (this.activeTab === 'lock-history') this.loadLockLogs(1);
      } catch (err) {
        this.error = err.message || 'Không thể khóa tài khoản.';
      } finally {
        this.saving = false;
      }
    },

    async submitUnlock() {
      this.saving = true;
      this.error = '';
      try {
        const response = await adminUserService.unlockUser(this.profile.id, {
          reason: this.unlockForm.reason,
        });
        this.success = response.message || 'Mở khóa tài khoản thành công.';
        this.showUnlockModal = false;
        await this.loadDetail();
        this.lockLogs = [];
        if (this.activeTab === 'lock-history') this.loadLockLogs(1);
      } catch (err) {
        this.error = err.message || 'Không thể mở khóa tài khoản.';
      } finally {
        this.saving = false;
      }
    },

    truncate(text, length) {
      if (!text) return '-';
      return text.length > length ? text.substring(0, length) + '...' : text;
    },
    getAccountStatusLabel,
    getPostStatusLabel,
    getReportStatusLabel,
    initials(name) {
      return String(name || 'SG').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    timeAgo(dateParam) {
      if (!dateParam) return '';
      const date = new Date(dateParam);
      const now = new Date();
      const seconds = Math.round((now - date) / 1000);
      const minutes = Math.round(seconds / 60);
      const hours = Math.round(minutes / 60);
      const days = Math.round(hours / 24);
      if (seconds < 60) return `${seconds} giây trước`;
      if (minutes < 60) return `${minutes} phút trước`;
      if (hours < 24) return `${hours} giờ trước`;
      if (days < 7) return `${days} ngày trước`;
      return date.toLocaleDateString('vi-VN');
    }
  },
};
</script>

<style scoped>
.user-detail { display: grid; gap: 16px; }
.page-head { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; }
.page-head h2 { margin: 6px 0; }
.page-head p, .muted, small { margin: 0; color: #64748b; }
.back-link { color: #15803d; font-weight: 800; text-decoration: none; }

.detail-layout { display: grid; grid-template-columns: 280px 1fr; gap: 16px; align-items: start; }

.sidebar-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 12px; align-items: center; text-align: center; position: sticky; top: 16px; }
.avatar { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; background: #16a34a; color: #fff; font-weight: 900; font-size: 22px; }
.sidebar-name { font-size: 16px; }
.sidebar-meta { font-size: 13px; color: #64748b; }
.sidebar-stats { width: 100%; display: grid; gap: 10px; }
.sidebar-stat { padding: 10px; background: #f8fafc; border-radius: 8px; display: grid; gap: 4px; text-align: center; }
.sidebar-stat span { font-size: 12px; color: #64748b; }
.sidebar-stat strong { font-size: 16px; }
.text-red { color: #b91c1c; }
.sidebar-actions { width: 100%; display: grid; gap: 8px; }

.content-panel { display: grid; gap: 16px; min-width: 0; }
.panel, .state-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; }
.panel { display: grid; gap: 14px; }
.panel h3, .panel h4 { margin: 0; }

.tabs, .metric-row { display: flex; gap: 8px; flex-wrap: wrap; }
.tabs button { border: 1px solid #dbe3ef; background: #fff; border-radius: 8px; padding: 10px 14px; font-weight: 800; cursor: pointer; }
.tabs button.active { background: #dcfce7; border-color: #22c55e; color: #166534; }

.info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
:deep(.info-item), .metric { display: grid; gap: 6px; padding: 12px; background: #f8fafc; border-radius: 10px; }
:deep(.info-item span), .metric span { color: #64748b; font-size: 13px; }
.metric strong { font-size: 20px; }

.notice { margin: 0; padding: 12px; border-radius: 10px; background: #f0fdf4; color: #166534; font-weight: 700; }
.list-box { display: grid; gap: 10px; }
.list-box article { display: grid; gap: 6px; padding: 12px; background: #f8fafc; border-radius: 10px; }

.state { color: #64748b; text-align: center; padding: 20px; }

.content-list { display: grid; gap: 12px; }
.content-card { display: flex; justify-content: space-between; gap: 16px; padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; transition: box-shadow 0.2s; }
.content-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-color: #cbd5e1; }
.content-card-body { display: grid; gap: 8px; flex: 1; min-width: 0; }
.content-text { margin: 0; color: #1e293b; font-size: 14px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.content-media-preview { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
.media-thumb { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; }
.media-more { width: 80px; height: 60px; display: grid; place-items: center; background: #e2e8f0; border-radius: 6px; font-weight: 800; color: #475569; font-size: 14px; }
.content-meta { display: flex; gap: 12px; font-size: 12px; color: #64748b; flex-wrap: wrap; align-items: center; }
.content-card-actions { display: flex; flex-direction: column; gap: 8px; justify-content: center; }

.timeline { display: grid; gap: 12px; }
.timeline-item { display: flex; gap: 12px; padding: 14px; background: #f8fafc; border-radius: 10px; border-left: 4px solid #e2e8f0; }
.timeline-item.locked { border-left-color: #ef4444; }
.timeline-item.unlocked { border-left-color: #22c55e; }
.timeline-icon { color: #64748b; margin-top: 2px; }
.timeline-body { display: grid; gap: 4px; }
.timeline-meta { font-size: 12px; color: #64748b; }
.badge-auto { display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; background: #dbeafe; color: #1e40af; width: fit-content; }
.badge-report { display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; background: #fee2e2; color: #b91c1c; }

.btn { border: 0; border-radius: 8px; font-weight: 800; cursor: pointer; padding: 10px 14px; background: #dcfce7; color: #166534; display: inline-flex; align-items: center; justify-content: center; }
.btn.secondary { background: #f1f5f9; color: #0f172a; }
.btn.danger { background: #fee2e2; color: #b91c1c; }
.btn-sm { border: 1px solid #dbe3ef; background: #fff; border-radius: 6px; padding: 6px 10px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; color: #334155; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
.btn-sm.danger { border-color: #fecaca; background: #fee2e2; color: #b91c1c; }
.btn-sm.danger:hover { background: #fef2f2; }
.btn-sm.icon-btn { padding: 8px; border-radius: 8px; color: #64748b; background: transparent; border-color: transparent; }
.btn-sm.icon-btn:hover { color: #1e293b; background: #f1f5f9; }
.btn-sm.icon-btn.danger { background: transparent; border-color: transparent; color: #ef4444; }
.btn-sm.icon-btn.danger:hover { background: #fef2f2; color: #b91c1c; }

.status { border-radius: 999px; padding: 4px 8px; font-size: 12px; font-weight: 800; background: #e2e8f0; }
.status.active, .status.visible, .status.published { background: #dcfce7; color: #166534; }
.status.locked, .status.hidden { background: #fee2e2; color: #b91c1c; }
.status.pending_verify, .status.pending, .status.draft { background: #fef3c7; color: #92400e; }
.lock-until { display: block; color: #b91c1c; font-size: 11px; }

.alert { padding: 12px; border-radius: 10px; font-weight: 700; }
.error { background: #fee2e2; color: #b91c1c; }
.success { background: #dcfce7; color: #166534; }
.pagination { display: flex; justify-content: space-between; gap: 12px; align-items: center; color: #64748b; font-size: 13px; }
.pagination div { display: flex; gap: 8px; align-items: center; }

/* Modal Khóa / Mở khóa */
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.56); display: grid; place-items: center; z-index: 500; padding: 20px; }
.modal { width: min(640px, calc(100vw - 32px)); padding: 22px; background: #fff; border-radius: 12px; display: grid; gap: 16px; }
.modal h3 { margin: 0; }
.modal footer { display: flex; justify-content: flex-end; gap: 10px; }
label { display: grid; gap: 6px; font-weight: 800; }
input, select, textarea { border: 1px solid #dbe3ef; border-radius: 8px; padding: 10px; font: inherit; }
textarea { resize: vertical; }

/* =========================================
   LIGHT THEME POPUP (Like Facebook Light)
   ========================================= */
.fb-backdrop { background: rgba(15, 23, 42, 0.56); }

.fb-modal { 
  width: min(720px, calc(100vw - 32px)); 
  height: min(85vh, 900px); 
  display: flex; 
  flex-direction: column; 
  background: #ffffff; 
  color: #1e293b;
  border-radius: 12px; 
  box-shadow: 0 12px 40px rgba(0,0,0,0.15); 
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

/* Header */
.fb-header { 
  display: flex; 
  justify-content: space-between; 
  align-items: center; 
  padding: 16px; 
  border-bottom: 1px solid #e2e8f0; 
  background: #ffffff;
  flex-shrink: 0;
}
.fb-header-spacer { width: 36px; }
.fb-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center; flex: 1; }
.fb-header-right { width: 36px; display: flex; justify-content: flex-end; }
.fb-close-btn { 
  width: 36px; height: 36px; 
  border-radius: 50%; border: 0; 
  background: #f1f5f9; 
  display: grid; place-items: center; 
  cursor: pointer; color: #64748b; 
  transition: all 0.2s; 
}
.fb-close-btn:hover { background: #e2e8f0; color: #1e293b; }

/* Body Area */
.fb-body {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.fb-body::-webkit-scrollbar { width: 8px; }
.fb-body::-webkit-scrollbar-track { background: transparent; }
.fb-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* Post Details */
.fb-post { padding: 16px; display: flex; flex-direction: column; gap: 14px; position: relative; }
.post-status-banner { padding: 8px 12px; border-radius: 6px; font-size: 13px; display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.post-status-banner.published { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.post-status-banner.pending_review { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
.post-status-banner.hidden { background: #fee2e2; color: #991b1b; border: 1px dashed #ef4444; }

.fb-post.is-hidden { background: #fef2f2; border: 1px dashed #fca5a5; border-radius: 8px; margin: 8px; }
.fb-post.is-hidden .fb-post-text { color: #7f1d1d; font-style: italic; text-decoration: line-through; }
.fb-post.is-hidden .fb-media-container img { filter: grayscale(0.8) opacity(0.7); }

.fb-post-header { display: flex; gap: 10px; align-items: center; }
.fb-post-avatar { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: grid; place-items: center; font-weight: 800; font-size: 14px; color: #475569; overflow: hidden; }
.fb-post-avatar img { width: 100%; height: 100%; object-fit: cover; }
.fb-post-meta { display: flex; flex-direction: column; line-height: 1.4; }
.fb-post-meta strong { font-size: 15px; color: #1e293b; font-weight: 700; }
.fb-post-meta span { font-size: 13px; color: #64748b; }
.fb-post-text { margin: 0; font-size: 16px; line-height: 1.5; color: #1e293b; white-space: pre-wrap; }

/* Media Container */
.fb-media-container { width: calc(100% + 32px); margin-left: -16px; margin-right: -16px; display: grid; gap: 2px; }
.fb-media-container img { width: 100%; max-height: 500px; object-fit: contain; background: #f8fafc; }
.fake-img { object-fit: cover !important; height: 400px; }

/* Stats */
.fb-stats { display: flex; justify-content: space-between; align-items: center; color: #64748b; font-size: 15px; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
.fb-stats-right { display: flex; gap: 12px; }

/* Post Actions (Icons Only) */
.fb-actions { display: flex; gap: 16px; justify-content: flex-end; padding-top: 8px; }
.fb-action-item { display: flex; justify-content: center; align-items: center; border: 0; border-radius: 6px; background: transparent !important; cursor: pointer; color: #64748b; transition: color 0.2s; padding: 4px; }
.fb-action-item:hover { color: #1e293b; }
.fb-action-item.danger:hover { color: #b91c1c; }

/* Comments Section */
.fb-comments { padding: 16px; display: flex; flex-direction: column; gap: 16px; border-top: 1px solid #e2e8f0; }
.fb-comment-group { display: flex; flex-direction: column; gap: 8px; }
.fb-comment-row { display: flex; gap: 10px; align-items: flex-start; transition: all 0.3s; padding: 8px; border-radius: 8px; margin: -8px; }

/* Highlight logic requested by user: đổi viền thành màu xanh lá */
.fb-comment-row.highlighted { border: 2px solid #22c55e; background: #dcfce7; border-radius: 8px; box-shadow: 0 0 12px rgba(34, 197, 94, 0.3); padding: 8px; }
.fb-comment-row.highlighted .fb-bubble { background: #ffffff; border: 2px solid #4ade80; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.15); }

.fb-comment-replies { margin-left: 46px; border-left: 2px solid #e2e8f0; padding-left: 12px; display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
.fb-comment-row.reply-row { margin: 0; padding: 4px 8px; }
.fb-comment-avatar.small { width: 28px; height: 28px; font-size: 11px; }

.fb-comment-avatar { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; background: #e2e8f0; flex-shrink: 0; }
.fb-comment-avatar img { width: 100%; height: 100%; object-fit: cover; }
.fb-avatar-text { width: 100%; height: 100%; display: grid; place-items: center; font-weight: 800; font-size: 13px; color: #475569; }

.fb-comment-content { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.fb-bubble { background: #f1f5f9; padding: 10px 14px; border-radius: 18px; display: inline-block; position: relative; max-width: 100%; transition: all 0.2s; }
.fb-bubble strong { display: block; font-size: 13px; color: #1e293b; margin-bottom: 2px; }
.fb-bubble p { margin: 0; font-size: 14px; color: #334155; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }

/* Styles for hidden comments */
.fb-comment-content.is-hidden { opacity: 0.85; }
.fb-comment-content.is-hidden .fb-bubble { background: #fee2e2; border: 1px dashed #ef4444; }
.fb-comment-content.is-hidden .fb-bubble strong { color: #991b1b; }
.fb-comment-content.is-hidden .fb-bubble p { color: #7f1d1d; font-style: italic; text-decoration: line-through; }
.fb-bubble-status { margin-top: 6px; font-size: 11px; padding: 2px 8px; background: #e2e8f0; border-radius: 999px; width: fit-content; }
.fb-bubble-status.hidden { color: #b91c1c; background: #fee2e2; }

.fb-comment-footer { display: flex; gap: 16px; align-items: center; padding: 0 12px; font-size: 13px; color: #64748b; font-weight: 600; cursor: default; }
.fb-comment-footer span:hover { text-decoration: underline; cursor: pointer; }
.fb-comment-tools { display: flex; gap: 14px; margin-left: 8px; }
.fb-comment-tools button { border: 0; background: transparent; padding: 0; cursor: pointer; color: #64748b; display: flex; align-items: center; transition: color 0.2s; }
.fb-comment-tools button:hover { color: #1e293b; }
.fb-comment-tools button.tool-danger:hover { color: #b91c1c; }

.fb-no-comments { text-align: center; color: #64748b; padding: 20px 0; font-size: 15px; }

@media (max-width: 900px) {
  .detail-layout { grid-template-columns: 1fr; }
  .sidebar-panel { position: static; }
  .info-grid { grid-template-columns: 1fr; }
}
</style>
