<template>
  <div class="community-page sg-client-page">
    <PublicNavbar />

    <main class="sg-container community-container">
      <div class="community-layout">
        <section class="feed-column" aria-label="Bảng tin cộng đồng">
          <article v-if="canCreateCommunityPost" class="composer-card">
            <div class="composer-start">
              <span class="avatar avatar-current">{{ initial(user?.fullName) }}</span>
              <button type="button" class="composer-prompt" @click="showCommunityModal = true">
                Bạn muốn chia sẻ điều gì với cộng đồng?
              </button>
            </div>
            <div class="composer-actions">
              <button type="button" @click="showCommunityModal = true">
                <AppIcon name="edit" />
                Bài chia sẻ
              </button>
              <button v-if="isPlayer" type="button" @click="showMeetupModal = true">
                <AppIcon name="users" />
                Tìm người chơi cùng
              </button>
            </div>
          </article>

          <div v-if="user" class="community-main-tabs">
            <button
              type="button"
              class="main-tab-item"
              :class="{ active: feedTab === 'public' }"
              @click="switchFeedTab('public')"
            >
              Bảng tin cộng đồng
            </button>
            <button
              type="button"
              class="main-tab-item"
              :class="{ active: feedTab === 'my_posts' }"
              @click="switchFeedTab('my_posts')"
            >
              Bài viết của tôi
            </button>
          </div>

          <section v-if="feedTab === 'public'" class="feed-toolbar" aria-label="Lọc bảng tin">
            <div class="toolbar-title">
              <h2>Bảng tin mới nhất</h2>
            </div>
            <div class="feed-filters">
              <form class="search-form" @submit.prevent="applyFilters">
                <AppIcon name="search" />
                <input v-model.trim="searchQuery" type="search" placeholder="Tìm trong cộng đồng" aria-label="Tìm trong cộng đồng" />
                <button type="submit">Tìm</button>
              </form>
              <div class="category-list" aria-label="Chủ đề bài viết">
                <button type="button" :class="{ active: !selectedCategory }" @click="setCategory('')">Tất cả</button>
                <button
                  v-for="category in categories"
                  :key="category"
                  type="button"
                  :class="{ active: selectedCategory === category }"
                  @click="setCategory(category)"
                >
                  {{ category }}
                </button>
                <button
                  v-if="searchQuery || selectedCategory"
                  type="button"
                  class="filter-clear"
                  @click="clearFilters"
                >
                  Xóa lọc
                </button>
              </div>
            </div>
          </section>

          <section v-else class="feed-toolbar" aria-label="Lọc bài viết của tôi">
            <div class="toolbar-title">
              <h2>Bài viết của tôi</h2>
            </div>
            <div class="feed-filters">
              <div class="category-list" aria-label="Lọc trạng thái bài viết">
                <button type="button" :class="{ active: myPostStatus === 'all' }" @click="setMyPostStatus('all')">Tất cả</button>
                <button type="button" :class="{ active: myPostStatus === 'pending_review' }" @click="setMyPostStatus('pending_review')">Chờ duyệt</button>
                <button type="button" :class="{ active: myPostStatus === 'published' }" @click="setMyPostStatus('published')">Đã đăng</button>
                <button type="button" :class="{ active: myPostStatus === 'rejected' }" @click="setMyPostStatus('rejected')">Bị từ chối</button>
                <button type="button" :class="{ active: myPostStatus === 'deleted' }" @click="setMyPostStatus('deleted')">Thùng rác</button>
              </div>
            </div>
          </section>

          <div v-if="loading" class="feed-state" aria-live="polite">
            <p>Đang tải bài viết...</p>
          </div>
          <div v-else-if="error" class="feed-state state-error" role="alert">
            <AppIcon name="alert" />
            <strong>Không thể tải bài viết</strong>
            <p>{{ error }}</p>
            <button type="button" @click="loadCurrentFeed({ page: 1 })">Thử lại</button>
          </div>
          <div v-else-if="!posts.length" class="feed-state">
            <AppIcon name="newspaper" />
            <strong>{{ feedTab === 'my_posts' ? (myPostStatus === 'deleted' ? 'Thùng rác trống' : 'Bạn chưa có bài viết nào trong mục này') : 'Chưa có bài viết phù hợp' }}</strong>
            <p>{{ feedTab === 'my_posts' ? (myPostStatus === 'deleted' ? 'Các bài viết bị xóa sẽ tạm thời lưu ở đây trước khi xóa vĩnh viễn.' : 'Hãy tạo bài viết mới để chia sẻ với cộng đồng.') : 'Hãy đổi chủ đề hoặc từ khóa để xem thêm nội dung.' }}</p>
            <button v-if="feedTab === 'my_posts' && myPostStatus !== 'deleted'" type="button" class="create-first-post-btn" @click="showCommunityModal = true">Đăng bài ngay</button>
          </div>

          <div v-else class="post-stream">
            <article v-for="post in posts" :key="post.id" class="post-card">
              <header class="post-header">
                <button type="button" class="author-button" @click="goToUser(post.author?.id)">
                  <span class="avatar">
                    <img
                      v-if="post.author?.avatar_url"
                      :src="assetUrl(post.author.avatar_url)"
                      :alt="post.author.full_name || post.author.username"
                    />
                    <span v-else>{{ initial(post.author?.full_name || post.author?.username) }}</span>
                  </span>
                  <span class="author-copy">
                    <strong class="client-author-line">
                      {{ post.author?.full_name || post.author?.username || 'Thành viên SportGo' }}
                      <ClientAuthorBadges :badges="post.author_badges" />
                    </strong>
                    <small>
                      {{ timeAgo(post.published_at || post.created_at) }}
                      <template v-if="post.venue_cluster?.name"> · {{ post.venue_cluster.name }}</template>
                      <template v-if="post.is_edited"> · <span>Đã chỉnh sửa</span></template>
                      <template v-if="post.is_deleted">
                        · <span class="post-status-text text-rejected">Đã chuyển vào thùng rác</span>
                      </template>
                      <template v-else-if="post.status && post.status !== 'published'">
                        · <span class="post-status-text" :class="`text-${post.status}`">
                          {{ post.status === 'pending_review' ? 'Chờ duyệt' : (post.status === 'rejected' ? (post.rejection_source === 'ai' ? 'Bị từ chối bởi AI' : 'Bị từ chối bởi Admin') : post.status) }}
                        </span>
                      </template>
                      <template v-else-if="feedTab === 'my_posts' && post.status === 'published'">
                        · <span class="post-status-text text-published">Đã xuất bản</span>
                      </template>
                    </small>
                  </span>
                </button>

                <div class="post-menu-wrap">
                  <button
                    type="button"
                    class="icon-button"
                    :aria-expanded="openMenuPostId === post.id"
                    aria-label="Tùy chọn bài viết"
                    @click.stop="togglePostMenu(post.id)"
                  >
                    <AppIcon name="moreHorizontal" />
                  </button>
                  <div v-if="openMenuPostId === post.id" class="post-menu" role="menu" @click.stop>
                    <template v-if="post.is_deleted">
                      <button type="button" role="menuitem" @click="restorePost(post)">
                        <AppIcon name="refreshCw" />
                        Khôi phục bài viết
                      </button>
                      <button type="button" role="menuitem" class="delete-menuitem" @click="forceDeletePost(post)">
                        <AppIcon name="trash" />
                        Xóa vĩnh viễn
                      </button>
                    </template>
                    <template v-else-if="isOwnPost(post)">
                      <button type="button" role="menuitem" @click="openEditPost(post)">
                        <AppIcon name="edit" />
                        Chỉnh sửa bài viết
                      </button>
                      <button v-if="post.status === 'rejected'" type="button" role="menuitem" @click="openAppealModal(post)">
                        <AppIcon name="refreshCw" />
                        Đề xuất duyệt lại
                      </button>
                      <button type="button" role="menuitem" @click="copyPostLink(post)">
                        <AppIcon name="copy" />
                        Sao chép liên kết
                      </button>
                      <button type="button" role="menuitem" class="delete-menuitem" @click="deletePost(post)">
                        <AppIcon name="trash" />
                        Xóa bài viết
                      </button>
                    </template>
                    <template v-else>
                      <button type="button" role="menuitem" @click="copyPostLink(post)">
                        <AppIcon name="copy" />
                        Sao chép liên kết
                      </button>
                      <button type="button" role="menuitem" @click="openReport(post)">
                        <AppIcon name="alert" />
                        Báo cáo bài viết
                      </button>
                    </template>
                  </div>
                </div>
              </header>

              <div v-if="post.status === 'rejected' && post.status_reason" class="post-rejection-inline">
                <span class="rejection-label">Lý do từ chối:</span>
                <span class="rejection-msg">{{ post.status_reason }}</span>
                <span v-if="isOwnPost(post)" class="rejection-actions">
                  <span class="rejection-dot">·</span>
                  <button type="button" class="rejection-action-btn" @click="openEditPost(post)">Chỉnh sửa</button>
                  <span class="rejection-dot">·</span>
                  <button type="button" class="rejection-action-btn" @click="openAppealModal(post)">Đề xuất duyệt lại</button>
                </span>
              </div>

              <div v-if="post.status === 'pending_review' && post.appeal_note && isOwnPost(post)" class="post-appeal-inline">
                <span class="appeal-label">Lời nhắn gửi Admin:</span>
                <span class="appeal-msg">{{ post.appeal_note }}</span>
              </div>

              <div class="post-body">
                <div v-if="post.hashtags?.length" class="post-tags">
                  <span v-for="tag in post.hashtags.slice(0, 3)" :key="tag.id || tag.name">#{{ tag.name }}</span>
                </div>
                <button type="button" class="post-copy" @click="goToDetail(post.slug || post.id)">
                  <strong v-if="post.title && !titleRepeatsContent(post)">{{ post.title }}</strong>
                  <span>{{ plainText(post.content || post.short_description) }}</span>
                </button>
              </div>

              <button
                v-if="postMedia(post).length === 1"
                type="button"
                class="post-media media-single"
                @click="goToDetail(post.slug || post.id)"
              >
                <img :src="postMedia(post)[0]" :alt="post.title || 'Ảnh bài viết'" @error="handlePostImageError" />
              </button>
              <button
                v-else-if="postMedia(post).length > 1"
                type="button"
                class="post-media media-grid"
                :class="`media-count-${Math.min(postMedia(post).length, 4)}`"
                @click="goToDetail(post.slug || post.id)"
              >
                <span v-for="(image, imageIndex) in postMedia(post).slice(0, 4)" :key="`${post.id}-${imageIndex}`">
                  <img :src="image" :alt="`${post.title || 'Ảnh bài viết'} ${imageIndex + 1}`" @error="handlePostImageError" />
                  <b v-if="imageIndex === 3 && postMedia(post).length > 4">+{{ postMedia(post).length - 4 }}</b>
                </span>
              </button>

              <div class="post-stats">
                <span><AppIcon name="heart" /> {{ post.like_count || 0 }}</span>
                <button type="button" @click="toggleComments(post)">{{ post.comment_count || 0 }} bình luận</button>
                <span>{{ post.view_count || 0 }} lượt xem</span>
              </div>

              <div class="post-actions">
                <button
                  type="button"
                  :class="{ active: Boolean(post.is_liked) }"
                  :disabled="likingPostIds.has(post.id) || !post.likes_available"
                  :title="post.likes_available ? '' : 'Lượt thích của bài cụm sân đang chờ cập nhật dữ liệu hệ thống'"
                  @click="toggleLike(post)"
                >
                  <AppIcon name="heart" />
                  {{ post.is_liked ? 'Đã thích' : 'Thích' }}
                </button>
                <button type="button" :class="{ active: commentsOpen[post.id] }" @click="toggleComments(post)">
                  <AppIcon name="messageCircle" />
                  Bình luận
                </button>
                <button type="button" @click="sharePost(post)">
                  <AppIcon name="share" />
                  Chia sẻ
                </button>
              </div>

              <section v-if="commentsOpen[post.id]" class="comments-panel" aria-label="Bình luận bài viết">
                <div v-if="detailsLoading[post.id]" class="comments-loading">
                  <span class="spinner spinner-small" aria-hidden="true"></span>
                  Đang tải bình luận...
                </div>
                <template v-else>
                  <div v-if="post.top_level_comments?.length" class="comment-list">
                    <article v-for="comment in visibleComments(post)" :key="comment.id" class="comment-item">
                      <span class="avatar avatar-comment">
                        <img
                          v-if="comment.user?.avatar_url"
                          :src="assetUrl(comment.user.avatar_url)"
                          :alt="comment.user.full_name || comment.user.username"
                        />
                        <span v-else>{{ initial(comment.user?.full_name || comment.user?.username) }}</span>
                      </span>
                      <div>
                        <div class="comment-bubble">
                          <strong class="client-author-line">
                            {{ comment.user?.full_name || comment.user?.username || 'Thành viên SportGo' }}
                            <ClientAuthorBadges :badges="comment.user?.author_badges" />
                          </strong>
                          <p>{{ comment.content }}</p>
                        </div>
                        <div class="comment-actions">
                          <small>{{ timeAgo(comment.created_at) }}</small>
                          <button type="button" class="reply-button" @click="setReply(post, comment)">Trả lời</button>
                        </div>
                        <div v-if="comment.replies?.length" class="comment-replies">
                          <article v-for="reply in comment.replies" :key="reply.id" class="comment-item reply-item">
                            <span class="avatar avatar-comment">
                              <img
                                v-if="reply.user?.avatar_url"
                                :src="assetUrl(reply.user.avatar_url)"
                                :alt="reply.user.full_name || reply.user.username"
                              />
                              <span v-else>{{ initial(reply.user?.full_name || reply.user?.username) }}</span>
                            </span>
                            <div>
                              <div class="comment-bubble">
                                <strong class="client-author-line">
                                  {{ reply.user?.full_name || reply.user?.username || 'Thành viên SportGo' }}
                                  <ClientAuthorBadges :badges="reply.user?.author_badges" />
                                </strong>
                                <p v-html="formatMention(reply.content)"></p>
                              </div>
                              <div class="comment-actions">
                                <small>{{ timeAgo(reply.created_at) }}</small>
                                <button type="button" class="reply-button" @click="setReply(post, comment, reply)">Trả lời</button>
                              </div>
                            </div>
                          </article>
                        </div>
                      </div>
                    </article>
                    <button
                      v-if="post.top_level_comments.length > commentPreviewLimit && !showAllComments[post.id]"
                      type="button"
                      class="show-comments-button"
                      @click="showAllComments[post.id] = true"
                    >
                      Xem thêm {{ post.top_level_comments.length - commentPreviewLimit }} bình luận
                    </button>
                  </div>
                  <p v-else class="no-comments">Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.</p>

                  <form v-if="user" class="comment-form-wrapper" @submit.prevent="submitComment(post)">
                    <div v-if="replyingTo[post.id]" class="replying-indicator">
                      <span>Đang trả lời <strong>{{ replyingTo[post.id].user?.full_name || replyingTo[post.id].user?.username || 'Thành viên SportGo' }}</strong></span>
                      <button type="button" aria-label="Hủy trả lời" @click="replyingTo[post.id] = null"><AppIcon name="x" /></button>
                    </div>
                    <div class="comment-form">
                      <span class="avatar avatar-comment">{{ initial(user.fullName) }}</span>
                      <label>
                        <span class="sr-only">Viết bình luận</span>
                        <input
                          :id="`comment-input-${post.id}`"
                          v-model.trim="commentDrafts[post.id]"
                          type="text"
                          maxlength="1000"
                          :placeholder="replyingTo[post.id] ? `Phản hồi ${replyingTo[post.id].user?.full_name || replyingTo[post.id].user?.username || 'Thành viên SportGo'}...` : 'Viết bình luận...'"
                          :disabled="commentSubmitting[post.id]"
                        />
                      </label>
                    <button
                      type="submit"
                      class="send-comment"
                      aria-label="Gửi bình luận"
                      :disabled="commentSubmitting[post.id] || !commentDrafts[post.id]?.trim()"
                    >
                        <AppIcon name="send" />
                      </button>
                    </div>
                  </form>
                  <button v-else type="button" class="login-to-comment" @click="goToLogin">
                    Đăng nhập để bình luận
                  </button>
                </template>
              </section>
            </article>

            <button
              v-if="pagination.current_page < pagination.last_page"
              type="button"
              class="load-more-button"
              :disabled="loadingMore"
              @click="loadMorePosts"
            >
              <span v-if="loadingMore" class="spinner spinner-small" aria-hidden="true"></span>
              {{ loadingMore ? 'Đang tải thêm...' : 'Xem thêm bài viết' }}
            </button>
            <p v-else class="end-of-feed">Bạn đã xem hết các bài viết hiện có.</p>
          </div>
        </section>

        <aside class="community-sidebar" aria-label="Khám phá cộng đồng">
          <section class="sidebar-card meetup-sidebar">
            <header class="sidebar-heading">
              <h2>Kèo sắp tới</h2>
              <button v-if="isPlayer" type="button" class="icon-button" aria-label="Tạo bài giao lưu" @click="showMeetupModal = true">
                <AppIcon name="plus" />
              </button>
            </header>

            <div v-if="matchmakingLoading" class="meetup-loading">
              <span class="spinner spinner-small" aria-hidden="true"></span>
              Đang tải kèo...
            </div>
            <div v-else-if="matchmakingError" class="meetup-empty meetup-empty-error" role="alert">
              <AppIcon name="alert" />
              <p>{{ matchmakingError }}</p>
              <button type="button" @click="fetchMatchmakingPosts">Tải lại</button>
            </div>
            <div v-else-if="matchmakingPosts.length" class="meetup-list">
              <article v-for="post in matchmakingPosts" :key="post.id" class="meetup-card">
                <header>
                  <button type="button" class="meetup-author" @click="goToUser(post.author?.id)">
                    <span class="avatar avatar-comment">
                      <img v-if="post.author?.avatar" :src="assetUrl(post.author.avatar)" :alt="post.author.name" />
                      <span v-else>{{ initial(post.author?.name) }}</span>
                    </span>
                    <span>
                      <strong class="client-author-line">
                        {{ post.author?.name || 'Người chơi SportGo' }}
                        <ClientAuthorBadges :badges="post.author?.author_badges" />
                      </strong>
                      <small>{{ timeAgo(post.created_at) }}</small>
                    </span>
                  </button>
                  <span class="needed-badge">Cần {{ post.needed_players }} người</span>
                </header>
                <div class="meetup-facts">
                  <span><AppIcon name="mapPin" /> {{ post.booking?.venue_name || 'Cụm sân' }}</span>
                  <span><AppIcon name="clock" /> {{ formatDate(post.booking?.date) }} · {{ post.booking?.time }}</span>
                </div>
                <p v-if="post.description">{{ post.description }}</p>
                <button
                  v-if="!isOwnPost(post)"
                  type="button"
                  class="meetup-action"
                  :disabled="joiningPostId === post.id || Boolean(post.user_status)"
                  @click="joinMatchmaking(post)"
                >
                  {{ joinLabel(post) }}
                </button>
                <router-link v-else class="meetup-action" :to="`/matchmaking-posts/${post.id}/manage`">Quản lý yêu cầu</router-link>
              </article>
            </div>
            <div v-else class="meetup-empty">
              <AppIcon name="users" />
              <p>Chưa có kèo công khai sắp tới.</p>
              <button v-if="isPlayer" type="button" @click="showMeetupModal = true">Tạo kèo đầu tiên</button>
            </div>
          </section>
        </aside>
      </div>
    </main>

    <CommunityPostModal
      :is-open="showCommunityModal"
      :editing-post="editingPost"
      @close="closeCommunityModal"
      @success="handleCommunityPostSaved"
    />
    <MeetupPostModal
      :is-open="showMeetupModal"
      @close="showMeetupModal = false"
      @success="handleMeetupPostCreated"
    />
    <ReportModal
      :is-open="Boolean(reportTarget)"
      :target-type="reportTarget?.feed_type === 'community_post' ? 'community_post' : 'venue_post'"
      :target-id="reportTarget?.entity_id || reportTarget?.id || ''"
      :target-name="reportTarget?.title || 'Bài viết cộng đồng'"
      @close="reportTarget = null"
      @success="handleReportSuccess"
    />

    <ConfirmModal
      v-model="showDeleteConfirm"
      :title="isForceDelete ? 'Xóa vĩnh viễn bài viết' : 'Chuyển vào thùng rác'"
      :message="isForceDelete ? 'Bài viết và hình ảnh sẽ bị xóa vĩnh viễn khỏi hệ thống và không thể khôi phục.' : 'Bạn có chắc chắn muốn xóa bài viết này không? Bài viết sẽ được chuyển vào thùng rác và ẩn khỏi bảng tin.'"
      :confirm-text="isForceDelete ? 'Xóa vĩnh viễn' : 'Xóa bài viết'"
      cancel-text="Hủy"
      type="danger"
      @confirm="handleConfirmDelete"
      @cancel="deletePostTarget = null; isForceDelete = false"
    />

    <!-- MODAL ĐỀ XUẤT DUYỆT LẠI (APPEAL MODAL) -->
    <Teleport to="body">
      <div v-if="showAppealModal" class="appeal-backdrop" @click.self="showAppealModal = false">
        <div class="appeal-modal" role="dialog" aria-modal="true">
          <div class="appeal-modal-header">
            <h3>Đề xuất duyệt lại bài viết</h3>
          </div>
          <div class="appeal-modal-body">
            <p class="appeal-modal-desc">
              Gửi lời nhắn giải trình tới Quản trị viên nếu bạn cho rằng bài viết phù hợp với tiêu chuẩn cộng đồng thể thao SportGo.
            </p>
            <div class="appeal-form-group">
              <label for="appeal-note-input">Lời nhắn gửi Quản trị viên (bắt buộc):</label>
              <textarea
                id="appeal-note-input"
                v-model.trim="appealNote"
                rows="4"
                maxlength="500"
                placeholder="Ví dụ: Bài viết chia sẻ kinh nghiệm chọn vợt cầu lông thực tế, từ ngữ không có ý xúc phạm, mong Quản trị viên xem xét duyệt lại giúp tôi..."
              ></textarea>
              <div class="appeal-char-count">{{ appealNote.length }}/500</div>
            </div>
          </div>
          <div class="appeal-modal-footer">
            <button class="btn secondary" type="button" :disabled="isSubmittingAppeal" @click="showAppealModal = false">
              Hủy
            </button>
            <button class="btn primary" type="button" :disabled="isSubmittingAppeal || appealNote.length < 5" @click="submitAppeal">
              {{ isSubmittingAppeal ? 'Đang gửi...' : 'Gửi yêu cầu' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import ClientAuthorBadges from '@/components/ClientAuthorBadges.vue';
import CommunityPostModal from '@/components/CommunityPostModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import MeetupPostModal from '@/components/MeetupPostModal.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import ReportModal from '@/components/ReportModal.vue';
import { api } from '@/services/api.js';
import { getAuth } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const user = getAuth();
const isPlayer = computed(() => user?.role_group === 'user');
const canCreateCommunityPost = computed(() => Boolean(user && ['user', 'owner'].includes(user.role_group)));
const feedTab = ref(String(route.query.tab || 'public'));
const myPostStatus = ref(String(route.query.status || 'all'));
const posts = ref([]);
const loading = ref(true);
const loadingMore = ref(false);
const error = ref('');
const searchQuery = ref(String(route.query.q || ''));
const selectedCategory = ref(String(route.query.category || ''));
const showMobileFilters = ref(false);
const showCommunityModal = ref(false);
const editingPost = ref(null);
const showMeetupModal = ref(false);
const openMenuPostId = ref(null);
const reportTarget = ref(null);
const showDeleteConfirm = ref(false);
const deletePostTarget = ref(null);
const isForceDelete = ref(false);
const showAppealModal = ref(false);
const appealTargetPost = ref(null);
const appealNote = ref('');
const isSubmittingAppeal = ref(false);
const pagination = ref({ current_page: 1, last_page: 1 });
const categories = ['Kinh nghiệm', 'Giao lưu', 'Hỏi đáp', 'Sự kiện', 'Cụm sân mới', 'Ưu đãi'];
const fallbackPostImage = '/images/home/badminton-cover.webp';
const commentPreviewLimit = 3;
const commentsOpen = reactive({});
const detailsLoading = reactive({});
const commentDrafts = reactive({});
const commentSubmitting = reactive({});
const showAllComments = reactive({});
const likingPostIds = reactive(new Set());
const replyingTo = reactive({});
const matchmakingPosts = ref([]);
const matchmakingLoading = ref(true);
const matchmakingError = ref('');
const joiningPostId = ref(null);

async function loadCurrentFeed({ page = 1, append = false } = {}) {
  if (feedTab.value === 'my_posts') {
    await fetchMyPosts({ page, append });
  } else {
    await fetchPosts({ page, append });
  }
}

async function fetchPosts({ page = 1, append = false } = {}) {
  if (append) loadingMore.value = true;
  else loading.value = true;
  error.value = '';

  try {
    const params = new URLSearchParams({ page: String(page), per_page: '10', feed_type: 'community_post' });
    if (searchQuery.value) params.set('keyword', searchQuery.value);
    if (selectedCategory.value) params.set('category', selectedCategory.value);
    const response = await api(`/api/venue-posts?${params.toString()}`);
    const incoming = Array.isArray(response.data) ? response.data : [];
    posts.value = append ? [...posts.value, ...incoming] : incoming;
    pagination.value = {
      current_page: Number(response.current_page || page),
      last_page: Number(response.last_page || 1),
    };
  } catch (requestError) {
    if (append) {
      toast.error(requestError.message || 'Không thể tải thêm bài viết.');
    } else {
      posts.value = [];
      error.value = requestError.message || 'Không thể tải bài viết cộng đồng.';
    }
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

async function fetchMyPosts({ page = 1, append = false } = {}) {
  if (!user) return;
  if (append) loadingMore.value = true;
  else loading.value = true;
  error.value = '';

  try {
    const params = new URLSearchParams({ page: String(page), per_page: '10' });
    if (myPostStatus.value && myPostStatus.value !== 'all') {
      params.set('status', myPostStatus.value);
    }
    const response = await api(`/api/my-community-posts?${params.toString()}`);
    const incoming = Array.isArray(response.data) ? response.data : [];
    posts.value = append ? [...posts.value, ...incoming] : incoming;
    pagination.value = {
      current_page: Number(response.current_page || page),
      last_page: Number(response.last_page || 1),
    };
  } catch (requestError) {
    if (append) {
      toast.error(requestError.message || 'Không thể tải thêm bài viết.');
    } else {
      posts.value = [];
      error.value = requestError.message || 'Không thể tải danh sách bài viết của bạn.';
    }
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function switchFeedTab(tab) {
  if (feedTab.value === tab) return;
  feedTab.value = tab;
  posts.value = [];
  pagination.value = { current_page: 1, last_page: 1 };
  if (tab === 'my_posts') {
    fetchMyPosts({ page: 1 });
  } else {
    fetchPosts({ page: 1 });
  }
}

function setMyPostStatus(status) {
  if (myPostStatus.value === status) return;
  myPostStatus.value = status;
  fetchMyPosts({ page: 1 });
}

async function fetchMatchmakingPosts() {
  matchmakingLoading.value = true;
  matchmakingError.value = '';
  try {
    const response = await api('/api/matchmaking-posts');
    matchmakingPosts.value = Array.isArray(response.data) ? response.data.slice(0, 5) : [];
  } catch (requestError) {
    matchmakingPosts.value = [];
    matchmakingError.value = requestError.message || 'Không thể tải các kèo sắp tới.';
  } finally {
    matchmakingLoading.value = false;
  }
}

function applyFilters() {
  showMobileFilters.value = false;
  router.replace({
    query: {
      ...(searchQuery.value ? { q: searchQuery.value } : {}),
      ...(selectedCategory.value ? { category: selectedCategory.value } : {}),
    },
  });
}

function setCategory(category) {
  selectedCategory.value = category;
  applyFilters();
}

function clearFilters() {
  searchQuery.value = '';
  selectedCategory.value = '';
  applyFilters();
}

function loadMorePosts() {
  if (loadingMore.value || pagination.value.current_page >= pagination.value.last_page) return;
  loadCurrentFeed({ page: pagination.value.current_page + 1, append: true });
}

async function ensurePostDetails(post) {
  if (Array.isArray(post.top_level_comments)) return;
  detailsLoading[post.id] = true;
  try {
    const response = await api(`/api/venue-posts/${post.slug || post.id}`);
    const detail = response.data || {};
    post.top_level_comments = Array.isArray(detail.top_level_comments) ? detail.top_level_comments : [];
    post.comment_count = Number(detail.comment_count || post.comment_count || 0);
    post.like_count = Number(detail.like_count || post.like_count || 0);
    post.is_liked = Boolean(detail.is_liked);
    post.likes_available = detail.likes_available !== false;
  } catch (requestError) {
    commentsOpen[post.id] = false;
    toast.error(requestError.message || 'Không thể tải bình luận của bài viết.');
  } finally {
    detailsLoading[post.id] = false;
  }
}

async function toggleComments(post) {
  commentsOpen[post.id] = !commentsOpen[post.id];
  if (commentsOpen[post.id]) await ensurePostDetails(post);
}

async function toggleLike(post) {
  if (!user) {
    toast.info('Vui lòng đăng nhập để thích bài viết.');
    goToLogin();
    return;
  }
  if (post.likes_available === false) {
    toast.info('Tính năng thích đang tạm thời chưa khả dụng.');
    return;
  }
  if (likingPostIds.has(post.id)) return;

  likingPostIds.add(post.id);
  try {
    const response = await api(`/api/venue-posts/${post.id}/likes`, { method: 'POST' });
    post.is_liked = Boolean(response.data?.is_liked);
    post.like_count = Number(response.data?.like_count ?? post.like_count ?? 0);
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể cập nhật lượt thích.');
  } finally {
    likingPostIds.delete(post.id);
  }
}

async function submitComment(post) {
  if (!user) {
    goToLogin();
    return;
  }
  const content = commentDrafts[post.id]?.trim();
  if (!content || commentSubmitting[post.id]) return;

  commentSubmitting[post.id] = true;
  const parentComment = replyingTo[post.id];
  try {
    const payload = { content };
    if (parentComment) payload.parent_id = parentComment.id;
    
    const response = await api(`/api/venue-posts/${post.id}/comments`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    
    const newComment = response.data || {
      id: `new-${Date.now()}`,
      content,
      created_at: new Date().toISOString(),
      user: {
        id: user.id,
        full_name: user.fullName,
        username: user.username,
        avatar_url: user.user?.avatar_url || null,
      },
    };

    if (parentComment) {
      if (!Array.isArray(parentComment.replies)) parentComment.replies = [];
      parentComment.replies.push(newComment);
    } else {
      if (!Array.isArray(post.top_level_comments)) post.top_level_comments = [];
      post.top_level_comments.unshift(newComment);
    }

    post.comment_count = Number(post.comment_count || 0) + 1;
    commentDrafts[post.id] = '';
    replyingTo[post.id] = null;
    toast.success('Đã đăng bình luận.');
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể gửi bình luận.');
  } finally {
    commentSubmitting[post.id] = false;
  }
}

function setReply(post, comment, targetReply = null) {
  if (!user) {
    toast.info('Vui lòng đăng nhập để bình luận.');
    goToLogin();
    return;
  }
  const targetUser = targetReply ? (targetReply.user?.full_name || targetReply.user?.username || 'Thành viên SportGo') : null;
  replyingTo[post.id] = comment;
  if (targetUser) {
    commentDrafts[post.id] = `@${targetUser} `;
  } else {
    // If just replying to main comment and there's a draft starting with @, leave it or clear it
  }
  nextTick(() => {
    const input = document.getElementById(`comment-input-${post.id}`);
    if (input) input.focus();
  });
}

async function sharePost(post) {
  openMenuPostId.value = null;
  const href = router.resolve({ name: 'community-post-detail', params: { slug: post.slug || post.id } }).href;
  const url = new URL(href, window.location.origin).toString();
  const shareData = { title: post.title || 'Bài viết SportGo', text: post.short_description || plainText(post.content), url };

  try {
    if (navigator.share) {
      await navigator.share(shareData);
      return;
    }
    if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
    await navigator.clipboard.writeText(url);
    toast.success('Đã sao chép liên kết bài viết.');
  } catch (shareError) {
    if (shareError?.name !== 'AbortError') toast.error('Không thể chia sẻ bài viết trên trình duyệt này.');
  }
}

function openReport(post) {
  openMenuPostId.value = null;
  if (!user) {
    toast.info('Vui lòng đăng nhập để gửi báo cáo.');
    goToLogin();
    return;
  }
  reportTarget.value = post;
}

function handleReportSuccess() {
  reportTarget.value = null;
  toast.success('Báo cáo đã được gửi để SportGo kiểm tra.');
}

async function joinMatchmaking(post) {
  if (!user) {
    toast.info('Vui lòng đăng nhập để tham gia giao lưu.');
    goToLogin();
    return;
  }
  if (!isPlayer.value) {
    toast.info('Chức năng này dành cho tài khoản người dùng.');
    return;
  }
  joiningPostId.value = post.id;
  try {
    await api(`/api/matchmaking-posts/${post.id}/join`, { method: 'POST' });
    post.user_status = 'pending';
    toast.success('Đã gửi yêu cầu tham gia.');
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể gửi yêu cầu tham gia.');
  } finally {
    joiningPostId.value = null;
  }
}

function joinLabel(post) {
  if (joiningPostId.value === post.id) return 'Đang gửi...';
  return {
    pending: 'Đang chờ duyệt',
    approved: 'Đã tham gia',
    rejected: 'Đã bị từ chối',
  }[post.user_status] || 'Xin tham gia';
}

function isOwnPost(post) {
  if (!user) return false;
  const authorId = post.author?.id ?? post.author_id;
  return String(user.id) === String(authorId);
}

function openEditPost(post) {
  openMenuPostId.value = null;
  editingPost.value = post;
  showCommunityModal.value = true;
}

function closeCommunityModal() {
  showCommunityModal.value = false;
  editingPost.value = null;
}

function openAppealModal(post) {
  openMenuPostId.value = null;
  appealTargetPost.value = post;
  appealNote.value = '';
  showAppealModal.value = true;
}

async function submitAppeal() {
  if (!appealTargetPost.value || appealNote.value.trim().length < 5) return;
  isSubmittingAppeal.value = true;
  try {
    const targetId = appealTargetPost.value.id || appealTargetPost.value.entity_id;
    const response = await api(`/api/my-community-posts/${targetId}/appeal`, {
      method: 'POST',
      body: JSON.stringify({ note: appealNote.value.trim() }),
    });
    toast.success(response?.message || 'Đã gửi yêu cầu xem xét lại tới Ban quản trị.');
    showAppealModal.value = false;
    const updated = response?.data;
    if (updated) {
      const idx = posts.value.findIndex((p) => String(p.id) === String(updated.id));
      if (idx >= 0) {
        posts.value[idx] = updated;
      }
    }
  } catch (err) {
    toast.error(err.message || 'Không thể gửi yêu cầu xem xét lại.');
  } finally {
    isSubmittingAppeal.value = false;
  }
}

async function copyPostLink(post) {
  openMenuPostId.value = null;
  const href = router.resolve({ name: 'community-post-detail', params: { slug: post.slug || post.id } }).href;
  const url = new URL(href, window.location.origin).toString();
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(url);
      toast.success('Đã sao chép liên kết bài viết.');
    } else {
      toast.info(`Liên kết bài viết: ${url}`);
    }
  } catch (err) {
    toast.info(`Liên kết bài viết: ${url}`);
  }
}

async function restorePost(post) {
  openMenuPostId.value = null;
  try {
    const targetId = post.id || post.entity_id;
    await api(`/api/my-community-posts/${targetId}/restore`, { method: 'POST' });
    toast.success('Đã khôi phục bài viết thành công.');
    posts.value = posts.value.filter((p) => p.id !== post.id);
  } catch (err) {
    toast.error(err.message || 'Không thể khôi phục bài viết.');
  }
}

function forceDeletePost(post) {
  openMenuPostId.value = null;
  isForceDelete.value = true;
  deletePostTarget.value = post;
  showDeleteConfirm.value = true;
}

function deletePost(post) {
  openMenuPostId.value = null;
  isForceDelete.value = false;
  deletePostTarget.value = post;
  showDeleteConfirm.value = true;
}

async function handleConfirmDelete() {
  if (!deletePostTarget.value) return;
  const post = deletePostTarget.value;
  try {
    const targetId = post.id || post.entity_id;
    const url = isForceDelete.value ? `/api/venue-posts/${targetId}?force=true` : `/api/venue-posts/${targetId}`;
    await api(url, { method: 'DELETE' });
    toast.success(isForceDelete.value ? 'Đã xóa vĩnh viễn bài viết.' : 'Đã chuyển bài viết vào thùng rác.');
    posts.value = posts.value.filter((p) => p.id !== post.id);
  } catch (err) {
    toast.error(err.message || 'Không thể xóa bài viết.');
  } finally {
    deletePostTarget.value = null;
    showDeleteConfirm.value = false;
    isForceDelete.value = false;
  }
}

function handleCommunityPostSaved(response) {
  const isEdit = Boolean(editingPost.value);
  closeCommunityModal();
  const updated = response?.data;

  if (isEdit && updated) {
    toast.success(response?.message || 'Bài viết đã được cập nhật thành công.');
    const index = posts.value.findIndex((p) => p.id === updated.id || (p.entity_id && p.entity_id === updated.entity_id));
    if (index >= 0) {
      posts.value[index] = updated;
    } else {
      loadCurrentFeed({ page: 1 });
    }
    return;
  }

  if (updated?.status === 'published') {
    toast.success('Bài viết đã được đăng công khai.');
    if (feedTab.value === 'public') {
      fetchPosts({ page: 1 });
    } else {
      switchFeedTab('public');
    }
    return;
  }
  toast.info(response?.message || 'Bài viết đã được gửi và đang chờ kiểm duyệt.');
  feedTab.value = 'my_posts';
  myPostStatus.value = 'pending_review';
  fetchMyPosts({ page: 1 });
}

function handleMeetupPostCreated() {
  showMeetupModal.value = false;
  toast.success('Bài giao lưu đã được tạo.');
  fetchMatchmakingPosts();
}

function visibleComments(post) {
  const comments = Array.isArray(post.top_level_comments) ? post.top_level_comments : [];
  return showAllComments[post.id] ? comments : comments.slice(0, commentPreviewLimit);
}

function titleRepeatsContent(post) {
  const title = plainText(post.title).replace(/\.{3}$/, '').trim().toLocaleLowerCase('vi-VN');
  const content = plainText(post.content).trim().toLocaleLowerCase('vi-VN');
  return Boolean(title && content.startsWith(title));
}

function plainText(value) {
  if (!value) return 'Bài chia sẻ từ cộng đồng SportGo.';
  const documentFragment = new DOMParser().parseFromString(String(value), 'text/html');
  return (documentFragment.body.textContent || '').trim();
}

function postMedia(post) {
  const items = Array.isArray(post?.media) ? post.media : [];
  const sorted = [...items].sort((left, right) => {
    if (left.collection === 'thumbnail' && right.collection !== 'thumbnail') return -1;
    if (right.collection === 'thumbnail' && left.collection !== 'thumbnail') return 1;
    return Number(left.sort_order || 0) - Number(right.sort_order || 0);
  });
  return [...new Set(sorted.map((item) => assetUrl(item.url || item.file_url || item.full_url || item.file_path || item.path)).filter(Boolean))];
}

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

function handlePostImageError(event) {
  const image = event.currentTarget;
  if (image?.getAttribute('src') !== fallbackPostImage) image.src = fallbackPostImage;
}

function initial(name) {
  return String(name || 'S').trim().charAt(0).toUpperCase();
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Chưa rõ ngày';
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

function timeAgo(value) {
  if (!value) return '';
  const timestamp = new Date(value).getTime();
  if (Number.isNaN(timestamp)) return '';
  const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
  if (seconds < 60) return 'Vừa xong';
  if (seconds < 3600) return `${Math.floor(seconds / 60)} phút trước`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} giờ trước`;
  if (seconds < 604800) return `${Math.floor(seconds / 86400)} ngày trước`;
  return formatDate(value);
}

function formatMention(text) {
  if (!text) return '';
  return text.replace(/^(@[^\s]+\s*(?:[^\s]+\s*)*?)(?=\s|$)/, '<strong style="color: #10b981;">$1</strong>').replace(/\n/g, '<br>');
}

function goToDetail(slug) {
  router.push({ name: 'community-post-detail', params: { slug } });
}

function goToUser(id) {
  if (id) router.push(`/user/${id}`);
}

function goToLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } });
}

function togglePostMenu(postId) {
  openMenuPostId.value = openMenuPostId.value === postId ? null : postId;
}

function closePostMenu() {
  openMenuPostId.value = null;
}

onMounted(() => {
  loadCurrentFeed();
  fetchMatchmakingPosts();
  document.addEventListener('click', closePostMenu);
});

onBeforeUnmount(() => document.removeEventListener('click', closePostMenu));
</script>


<style scoped>
/* â”€â”€â”€ BASE â”€â”€â”€ */
.community-page {
  min-height: 100vh;
  background: #f8fafc;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: #0f172a;
}

.community-container {
  max-width: 1180px;
  margin: 0 auto;
  padding: 24px 20px 80px;
}

/* All text & headers override to normal weight & dark color */
h1, h2, h3, h4, strong, b, .client-author-line {
  font-weight: 500 !important;
  color: #0f172a !important;
}

/* â”€â”€â”€ HEADING â”€â”€â”€ */
.community-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 36px 0 28px;
  flex-wrap: wrap;
}

.eyebrow {
  display: block;
  color: #16a34a;
  font-size: 13px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.community-heading h1 {
  font-size: clamp(24px, 4vw, 32px);
  font-weight: 500;
  color: #0f172a;
  line-height: 1.25;
  margin: 0 0 8px;
}

.community-heading p {
  font-size: 15px;
  color: #1e293b;
  margin: 0;
  line-height: 1.55;
  font-weight: 400;
}

.heading-login {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 20px;
  border: 1px solid #16a34a;
  border-radius: 8px;
  background: #ffffff;
  color: #16a34a;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}

.heading-login:hover {
  background: #16a34a;
  color: #ffffff;
}

/* â”€â”€â”€ LAYOUT â”€â”€â”€ */
.community-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
  align-items: start;
}

@media (max-width: 960px) {
  .community-layout {
    grid-template-columns: 1fr;
  }
  .community-sidebar {
    display: none;
  }
}

/* â”€â”€â”€ FEED COLUMN â”€â”€â”€ */
.feed-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

/* ─── COMMUNITY MAIN TABS ─── */
.community-main-tabs {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 4px;
}

.main-tab-item {
  flex: 1;
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: #1e293b;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  text-align: center;
  transition: all 0.15s ease;
}

.main-tab-item.active {
  background: #15803d;
  color: #ffffff !important;
}

.main-tab-item:hover:not(.active) {
  background: #f1f5f9;
  color: #0f172a;
}

/* ─── COMPOSER CARD ─── */
.composer-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 18px 20px;
}

.composer-start {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}

.composer-prompt {
  flex: 1;
  text-align: left;
  padding: 10px 16px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  color: #1e293b;
  font-size: 14px;
  font-weight: 400;
  cursor: pointer;
}

.composer-prompt:hover {
  border-color: #16a34a;
}

.composer-actions {
  display: flex;
  gap: 8px;
  padding-top: 14px;
}

.composer-actions button {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.composer-actions button:hover {
  background: #f8fafc;
  border-color: #16a34a;
}

/* â”€â”€â”€ FEED TOOLBAR â”€â”€â”€ */
.feed-toolbar {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 16px 20px;
}

.toolbar-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.toolbar-title h2 {
  font-size: 17px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.feed-filters {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.mobile-filter-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.mobile-filter-toggle:hover {
  border-color: #16a34a;
  color: #16a34a;
}

.mobile-filters {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 14px;
}

/* â”€â”€â”€ SEARCH FORM â”€â”€â”€ */
.search-form {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 6px 6px 6px 14px;
  color: #0f172a;
}

.search-form:focus-within {
  border-color: #16a34a;
}

.search-form input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: #0f172a;
  font-weight: 400;
  min-width: 0;
}

.search-form input::placeholder {
  color: #334155;
}

.search-form button {
  padding: 6px 16px;
  background: #16a34a;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  flex-shrink: 0;
}

.search-form button:hover {
  background: #15803d;
}

/* â”€â”€â”€ CATEGORY LIST â”€â”€â”€ */
.category-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.category-list button {
  padding: 6px 14px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #0f172a;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.category-list button:hover {
  border-color: #16a34a;
  color: #16a34a;
}

.category-list button.active {
  background: #16a34a;
  border-color: #16a34a;
  color: #ffffff;
}

.filter-clear {
  padding: 6px 14px;
  border-radius: 6px;
  border: 1px solid #dc2626 !important;
  background: #ffffff !important;
  color: #dc2626 !important;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

/* â”€â”€â”€ FEED STATES â”€â”€â”€ */
.feed-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 64px 20px;
  text-align: center;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  color: #0f172a;
  font-size: 14px;
  font-weight: 400;
}

.feed-state strong {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.feed-state p {
  margin: 0;
  color: #1e293b;
}

.state-error {
  color: #dc2626;
}

/* â”€â”€â”€ SPINNER â”€â”€â”€ */
.spinner {
  display: block;
  width: 34px;
  height: 34px;
  border: 3px solid #dcfce7;
  border-top-color: #16a34a;
  border-radius: 50%;
  animation: cm-spin 0.75s linear infinite;
}

.spinner-small {
  width: 18px;
  height: 18px;
  border-width: 2px;
}

@keyframes cm-spin { to { transform: rotate(360deg); } }

/* â”€â”€â”€ POST STREAM â”€â”€â”€ */
.post-stream {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* â”€â”€â”€ POST CARD â”€â”€â”€ */
.post-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}

.post-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 18px 0;
}

.author-button {
  display: flex;
  align-items: center;
  gap: 10px;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  text-align: left;
}

.author-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.client-author-line {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 5px;
}

.author-copy small {
  font-size: 12px;
  color: #1e293b;
  font-weight: 400;
}

/* â”€â”€â”€ AVATAR â”€â”€â”€ */
.avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #16a34a;
  color: #ffffff;
  font-size: 15px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-current {
  background: #16a34a;
}

.avatar-comment {
  width: 32px;
  height: 32px;
  font-size: 12px;
  flex-shrink: 0;
}

/* â”€â”€â”€ POST MENU â”€â”€â”€ */
.post-menu-wrap {
  position: relative;
}

.icon-button {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  color: #0f172a;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.icon-button:hover {
  background: #f8fafc;
}

.post-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 4px);
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.08);
  padding: 6px;
  min-width: 180px;
  z-index: 50;
}

.post-status-text {
  font-weight: 500;
}

.post-status-text.text-pending_review {
  color: #64748b;
}

.post-status-text.text-published {
  color: #15803d;
}

.post-status-text.text-rejected {
  color: #dc2626;
}

.post-rejection-note {
  margin: 8px 18px 0;
  font-size: 13px;
  color: #dc2626;
  font-weight: 400;
}

.delete-menuitem {
  color: #dc2626 !important;
}

.delete-menuitem:hover {
  background: #fee2e2 !important;
}

.create-first-post-btn {
  margin-top: 6px;
  padding: 8px 18px;
  border: 1px solid #15803d;
  border-radius: 6px;
  background: #15803d;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.create-first-post-btn:hover {
  background: #166534;
}

.post-menu button {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  background: none;
  color: #0f172a;
  font-size: 13.5px;
  font-weight: 400;
  cursor: pointer;
}

.post-menu button:hover {
  background: #f8fafc;
  color: #dc2626;
}

/* â”€â”€â”€ POST BODY â”€â”€â”€ */
.post-body {
  padding: 14px 18px 4px;
}

.post-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}

.post-tags span {
  font-size: 12px;
  font-weight: 500;
  color: #16a34a;
}

.post-copy {
  background: none;
  border: none;
  padding: 0;
  text-align: left;
  cursor: pointer;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.post-copy strong {
  font-size: 15px;
  font-weight: 500;
  color: #0f172a;
  display: block;
}

.post-copy span {
  font-size: 14px;
  color: #1e293b;
  font-weight: 400;
  line-height: 1.55;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* â”€â”€â”€ POST MEDIA â”€â”€â”€ */
.post-media {
  display: block;
  width: 100%;
  border: none;
  padding: 0;
  background: none;
  cursor: pointer;
  margin-top: 10px;
}

.media-single img {
  width: 100%;
  max-height: 400px;
  object-fit: cover;
  display: block;
}

.media-grid {
  display: grid;
  gap: 2px;
}

.media-count-2 { grid-template-columns: 1fr 1fr; }
.media-count-3 { grid-template-columns: 1fr 1fr 1fr; }
.media-count-4 { grid-template-columns: 1fr 1fr; }

.media-grid span {
  position: relative;
  height: 180px;
  display: block;
  overflow: hidden;
}

.media-grid span img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.media-grid span b {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.6);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 500;
}

/* â”€â”€â”€ POST STATS â”€â”€â”€ */
.post-stats {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 18px;
  font-size: 13px;
  color: #1e293b;
  font-weight: 400;
}

.post-stats span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.post-stats button {
  background: none;
  border: none;
  color: #1e293b;
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
  padding: 0;
}

.post-stats button:hover {
  color: #16a34a;
}

/* â”€â”€â”€ POST ACTIONS â”€â”€â”€ */
.post-actions {
  display: flex;
  gap: 4px;
  padding: 6px 12px 12px;
}

.post-actions button {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.post-actions button:hover {
  background: #f8fafc;
  border-color: #16a34a;
}

.post-actions button.active {
  color: #dc2626;
  border-color: #fca5a5;
}

.post-actions button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* â”€â”€â”€ COMMENTS PANEL â”€â”€â”€ */
.comments-panel {
  padding: 14px 18px 18px;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.comments-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #1e293b;
  font-size: 13px;
  font-weight: 400;
}

.comment-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.comment-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.comment-bubble {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 13px;
  flex: 1;
}

.comment-bubble strong {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 4px;
}

.comment-bubble p {
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 400;
  line-height: 1.5;
  margin: 0;
}

.comment-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 5px;
}

.comment-actions small {
  font-size: 12px;
  color: #1e293b;
  font-weight: 400;
}

.reply-button {
  background: none;
  border: none;
  color: #16a34a;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
}

.reply-button:hover {
  text-decoration: underline;
}

.comment-replies {
  margin-top: 10px;
  padding-left: 14px;
  border-left: 2px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.no-comments {
  font-size: 13px;
  color: #1e293b;
  font-weight: 400;
  text-align: center;
  padding: 12px 0;
  margin: 0;
}

.show-comments-button {
  background: none;
  border: none;
  color: #16a34a;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  padding: 4px 0;
}

/* â”€â”€â”€ COMMENT FORM â”€â”€â”€ */
.comment-form-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 4px;
}

.replying-indicator {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 7px 12px;
  border-radius: 6px;
  font-size: 13px;
  color: #0f172a;
}

.replying-indicator button {
  background: none;
  border: none;
  color: #dc2626;
  cursor: pointer;
  display: flex;
  align-items: center;
  padding: 4px;
}

.comment-form {
  display: flex;
  align-items: center;
  gap: 10px;
}

.comment-form label {
  flex: 1;
  display: block;
  margin: 0;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  border: 0;
}

.comment-form input {
  width: 100%;
  padding: 9px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 400;
  background: #ffffff;
  outline: none;
}

.comment-form input:focus {
  border-color: #16a34a;
}

.send-comment {
  width: 34px;
  height: 34px;
  background: #16a34a;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

.send-comment:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.send-comment:not(:disabled):hover {
  background: #15803d;
}

.login-to-comment {
  background: #ffffff;
  border: 1px solid #16a34a;
  border-radius: 8px;
  color: #16a34a;
  font-size: 13.5px;
  font-weight: 500;
  padding: 10px;
  cursor: pointer;
  width: 100%;
}

.login-to-comment:hover {
  background: #f0fdf4;
}

/* â”€â”€â”€ LOAD MORE / END OF FEED â”€â”€â”€ */
.load-more-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.load-more-button:hover:not(:disabled) {
  border-color: #16a34a;
  color: #16a34a;
}

.load-more-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.end-of-feed {
  text-align: center;
  font-size: 13.5px;
  color: #1e293b;
  font-weight: 400;
  padding: 16px;
  margin: 0;
}

/* â”€â”€â”€ SIDEBAR â”€â”€â”€ */
.community-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 80px;
}

.sidebar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 18px 20px;
}

.sidebar-card h2 {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 14px;
}

/* â”€â”€â”€ SIDEBAR MEETUP â”€â”€â”€ */
.sidebar-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.sidebar-heading h2 {
  margin: 0;
}

.meetup-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #1e293b;
  font-size: 13px;
  font-weight: 400;
  padding: 8px 0;
}

.meetup-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.meetup-card {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 13px;
  background: #ffffff;
}

.meetup-card header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}

.meetup-author {
  display: flex;
  align-items: center;
  gap: 8px;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  text-align: left;
}

.meetup-author strong {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 4px;
}

.meetup-author small {
  font-size: 11.5px;
  color: #1e293b;
  font-weight: 400;
  display: block;
}

.needed-badge {
  flex-shrink: 0;
  color: #16a34a;
  font-size: 12px;
  font-weight: 500;
}

.meetup-facts {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 10px;
}

.meetup-facts span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12.5px;
  color: #0f172a;
  font-weight: 400;
}

.meetup-card p {
  font-size: 13px;
  color: #1e293b;
  font-weight: 400;
  margin: 0 0 10px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.meetup-action {
  display: block;
  width: 100%;
  padding: 8px;
  text-align: center;
  background: #16a34a;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
}

.meetup-action:hover:not(:disabled) {
  background: #15803d;
}

.meetup-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.meetup-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 0;
  text-align: center;
}

.meetup-empty p {
  font-size: 13px;
  color: #1e293b;
  font-weight: 400;
  margin: 0;
}

.meetup-empty button {
  padding: 8px 18px;
  background: #16a34a;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.meetup-empty-error p {
  color: #dc2626;
}

.meetup-empty-error button {
  background: #ffffff;
  border: 1px solid #dc2626;
  color: #dc2626;
}

.desktop-filters .search-form {
  margin-bottom: 12px;
}

/* REJECTION NOTE & APPEAL STYLES */
.post-rejection-inline {
  margin: 10px 18px 0;
  font-size: 13px;
  line-height: 1.5;
  color: #334155;
  font-weight: 400;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.rejection-label {
  color: #dc2626;
  font-weight: 500;
}

.rejection-msg {
  color: #334155;
}

.rejection-actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.rejection-dot {
  color: #94a3b8;
  user-select: none;
}

.rejection-action-btn {
  background: none;
  border: none;
  padding: 0;
  color: #16a34a;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: color 0.15s ease;
}

.rejection-action-btn:hover {
  color: #15803d;
}

.post-appeal-inline {
  margin: 10px 18px 0;
  font-size: 13px;
  line-height: 1.5;
  color: #334155;
  font-weight: 400;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.appeal-label {
  color: #0284c7;
  font-weight: 500;
}

.appeal-msg {
  color: #334155;
}

/* APPEAL MODAL */
.appeal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 16px;
}

.appeal-modal {
  width: 100%;
  max-width: 480px;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.appeal-modal-header {
  padding: 18px 20px 0;
}

.appeal-modal-header h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 500;
  color: #0f172a;
}

.appeal-modal-body {
  padding: 14px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.appeal-modal-desc {
  margin: 0;
  font-size: 13px;
  color: #475569;
  line-height: 1.5;
}

.appeal-form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.appeal-form-group label {
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
}

.appeal-form-group textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13.5px;
  color: #0f172a;
  line-height: 1.5;
  resize: vertical;
  outline: none;
  font-family: inherit;
  box-sizing: border-box;
}

.appeal-form-group textarea:focus {
  border-color: #16a34a;
  box-shadow: 0 0 0 1px #16a34a;
}

.appeal-char-count {
  font-size: 11.5px;
  color: #64748b;
  text-align: right;
}

.appeal-modal-footer {
  padding: 12px 20px 18px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  background: #ffffff;
}

.appeal-modal-footer .btn {
  padding: 8px 18px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.15s ease;
}

.appeal-modal-footer .btn.secondary {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
}

.appeal-modal-footer .btn.secondary:hover {
  background: #f8fafc;
}

.appeal-modal-footer .btn.primary {
  background: #16a34a;
  color: #ffffff;
}

.appeal-modal-footer .btn.primary:hover:not(:disabled) {
  background: #15803d;
}

.appeal-modal-footer .btn.primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
