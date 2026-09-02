<template>
  <div class="community-page">
    <PublicNavbar />

    <main class="community-shell">

      <div class="community-layout">
        <section class="community-main" aria-label="Bảng tin cộng đồng">
          <article v-if="canCreateCommunityPost" class="surface composer">
            <div class="composer__row">
              <span class="avatar avatar-current">{{ initial(user?.fullName) }}</span>
              <button type="button" class="composer__prompt" @click="showCommunityModal = true">
                <span>Bạn muốn chia sẻ điều gì với cộng đồng?</span>
                <AppIcon name="chevronRight" />
              </button>
            </div>
            <div class="composer__actions">
              <button type="button" class="text-action" @click="showCommunityModal = true"><AppIcon name="edit" />Bài chia sẻ</button>
              <button v-if="isPlayer" type="button" class="text-action" @click="showMeetupModal = true"><AppIcon name="users" />Tìm người chơi cùng</button>
            </div>
          </article>

          <nav v-if="user" class="surface community-tabs" aria-label="Khu vực cộng đồng">
            <button type="button" :class="{ active: feedTab === 'public' }" @click="switchFeedTab('public')">Bảng tin cộng đồng</button>
            <button type="button" :class="{ active: feedTab === 'my_posts' }" @click="switchFeedTab('my_posts')">Bài viết của tôi</button>
            <router-link :to="{ name: 'ClientMatchmakingRequests' }">Đơn tham gia giao lưu</router-link>
          </nav>

          <section class="surface filter-panel" aria-label="Bộ lọc bảng tin">
            <div class="filter-panel__heading">
              <h2>{{ feedTab === 'public' ? 'Bảng tin mới nhất' : 'Bài viết của tôi' }}</h2>
              <button v-if="searchQuery || selectedCategory" type="button" class="button button-quiet" @click="clearFilters">Xóa bộ lọc</button>
            </div>

            <form v-if="feedTab === 'public'" class="search-field" @submit.prevent="applyFilters">
              <AppIcon name="search" />
              <input v-model.trim="searchQuery" type="search" placeholder="Tìm bài viết, kinh nghiệm, kèo giao lưu..." aria-label="Tìm trong cộng đồng" />
              <button type="submit" class="button button-primary button-small">Tìm</button>
            </form>
            <div v-if="feedTab === 'public'" class="filter-nav" aria-label="Chủ đề bài viết">
              <button type="button" :class="{ active: !selectedCategory }" @click="setCategory('')">Tất cả</button>
              <button v-for="category in categories" :key="category" type="button" :class="{ active: selectedCategory === category }" @click="setCategory(category)">{{ category }}</button>
            </div>
            <div v-else class="filter-nav" aria-label="Trạng thái bài viết">
              <button type="button" :class="{ active: myPostStatus === 'all' }" @click="setMyPostStatus('all')">Tất cả</button>
              <button type="button" :class="{ active: myPostStatus === 'pending_review' }" @click="setMyPostStatus('pending_review')">Chờ duyệt</button>
              <button type="button" :class="{ active: myPostStatus === 'published' }" @click="setMyPostStatus('published')">Đã đăng</button>
              <button type="button" :class="{ active: myPostStatus === 'rejected' }" @click="setMyPostStatus('rejected')">Bị từ chối</button>
              <button type="button" :class="{ active: myPostStatus === 'deleted' }" @click="setMyPostStatus('deleted')">Thùng rác</button>
            </div>
          </section>

          <div v-if="loading" class="feed-state" aria-live="polite"><span class="loader" aria-hidden="true"></span><p>Đang tải bảng tin...</p></div>
          <div v-else-if="error" class="surface feed-state feed-state--error" role="alert">
            <AppIcon name="alert" />
            <div><strong>Không thể tải bảng tin</strong><p>{{ error }}</p></div>
            <button type="button" class="button button-secondary" @click="loadCurrentFeed({ page: 1 })">Thử lại</button>
          </div>
          <div v-else-if="!posts.length" class="surface feed-state">
            <AppIcon name="newspaper" />
            <strong>{{ feedTab === 'my_posts' ? (myPostStatus === 'deleted' ? 'Thùng rác đang trống' : 'Bạn chưa có bài viết trong mục này') : 'Chưa có bài viết phù hợp' }}</strong>
            <p>{{ feedTab === 'my_posts' ? (myPostStatus === 'deleted' ? 'Bài viết đã xóa sẽ xuất hiện ở đây.' : 'Chia sẻ điều bạn biết để bắt đầu kết nối.') : 'Thử một từ khóa hoặc chủ đề khác.' }}</p>
            <button v-if="feedTab === 'my_posts' && myPostStatus !== 'deleted'" type="button" class="button button-primary" @click="showCommunityModal = true">Đăng bài ngay</button>
          </div>

          <div v-else class="post-stream">
            <article v-for="post in posts" :key="post.id" class="surface post-card">
              <header class="post-card__header">
                <button type="button" class="author-button" @click="goToUser(post.author?.id)">
                  <span class="avatar">
                    <img v-if="post.author?.avatar_url" :src="assetUrl(post.author.avatar_url)" :alt="post.author.full_name || post.author.username" />
                    <span v-else>{{ initial(post.author?.full_name || post.author?.username) }}</span>
                  </span>
                  <span class="author-copy">
                    <strong class="author-name">{{ post.author?.full_name || post.author?.username || 'Thành viên SportGo' }} <ClientAuthorBadges :badges="post.author_badges" /></strong>
                    <small>
                      {{ timeAgo(post.published_at || post.created_at) }}
                      <template v-if="post.venue_cluster?.name"> · {{ post.venue_cluster.name }}</template>
                      <template v-if="post.is_edited"> · Đã chỉnh sửa</template>
                      <template v-if="post.is_deleted"> · <span class="post-status status-deleted">Đã chuyển vào thùng rác</span></template>
                      <template v-else-if="post.status && post.status !== 'published'">
                        · <span class="post-status" :class="'status-' + post.status">{{ post.status === 'pending_review' ? 'Chờ duyệt' : (post.status === 'rejected' ? (post.rejection_source === 'ai' ? 'Bị từ chối bởi AI' : 'Bị từ chối bởi Admin') : post.status) }}</span>
                      </template>
                      <template v-else-if="feedTab === 'my_posts' && post.status === 'published'"> · <span class="post-status status-published">Đã xuất bản</span></template>
                    </small>
                  </span>
                </button>

                <div class="post-menu-wrap">
                  <button type="button" class="icon-button" :aria-expanded="openMenuPostId === post.id" aria-label="Tùy chọn bài viết" @click.stop="togglePostMenu(post.id)"><AppIcon name="moreHorizontal" /></button>
                  <div v-if="openMenuPostId === post.id" class="post-menu" role="menu" @click.stop>
                    <template v-if="post.is_deleted">
                      <button type="button" role="menuitem" @click="restorePost(post)"><AppIcon name="refreshCw" />Khôi phục bài viết</button>
                      <button type="button" role="menuitem" class="menu-danger" @click="forceDeletePost(post)"><AppIcon name="trash" />Xóa vĩnh viễn</button>
                    </template>
                    <template v-else-if="isOwnPost(post)">
                      <button type="button" role="menuitem" @click="openEditPost(post)"><AppIcon name="edit" />Chỉnh sửa bài viết</button>
                      <button v-if="post.status === 'rejected'" type="button" role="menuitem" @click="openAppealModal(post)"><AppIcon name="refreshCw" />Đề xuất duyệt lại</button>
                      <button type="button" role="menuitem" @click="copyPostLink(post)"><AppIcon name="copy" />Sao chép liên kết</button>
                      <button type="button" role="menuitem" class="menu-danger" @click="deletePost(post)"><AppIcon name="trash" />Xóa bài viết</button>
                    </template>
                    <template v-else>
                      <button type="button" role="menuitem" @click="copyPostLink(post)"><AppIcon name="copy" />Sao chép liên kết</button>
                      <button type="button" role="menuitem" @click="openReport(post)"><AppIcon name="alert" />Báo cáo bài viết</button>
                    </template>
                  </div>
                </div>
              </header>

              <div v-if="post.status === 'rejected' && post.status_reason" class="post-notice post-notice--danger">
                <strong>Lý do từ chối</strong><span>{{ post.status_reason }}</span>
                <template v-if="isOwnPost(post)"><button type="button" @click="openEditPost(post)">Chỉnh sửa</button><button type="button" @click="openAppealModal(post)">Đề xuất duyệt lại</button></template>
              </div>
              <div v-if="post.status === 'pending_review' && post.appeal_note && isOwnPost(post)" class="post-notice"><strong>Lời nhắn gửi Admin</strong><span>{{ post.appeal_note }}</span></div>

              <div class="post-card__body">
                <div v-if="post.hashtags?.length" class="post-tags"><span v-for="tag in post.hashtags.slice(0, 3)" :key="tag.id || tag.name">#{{ tag.name }}</span></div>
                <button type="button" class="post-copy" @click="goToDetail(post.slug || post.id)">
                  <strong v-if="post.title && !titleRepeatsContent(post)">{{ post.title }}</strong>
                  <span>{{ plainText(post.content || post.short_description) }}</span>
                </button>
              </div>

              <button v-if="postMedia(post).length === 1" type="button" class="post-media media-single" @click="goToDetail(post.slug || post.id)">
                <img :src="postMedia(post)[0]" :alt="post.title || 'Ảnh bài viết'" @error="handlePostImageError" />
              </button>
              <button v-else-if="postMedia(post).length > 1" type="button" class="post-media media-grid" :class="'media-count-' + Math.min(postMedia(post).length, 4)" @click="goToDetail(post.slug || post.id)">
                <span v-for="(image, imageIndex) in postMedia(post).slice(0, 4)" :key="post.id + '-' + imageIndex">
                  <img :src="image" :alt="(post.title || 'Ảnh bài viết') + ' ' + (imageIndex + 1)" @error="handlePostImageError" />
                  <b v-if="imageIndex === 3 && postMedia(post).length > 4">+{{ postMedia(post).length - 4 }}</b>
                </span>
              </button>

              <div class="post-card__stats">
                <span><AppIcon name="heart" />{{ post.like_count || 0 }}</span>
                <button type="button" :disabled="!isPostPublished(post)" @click="isPostPublished(post) && toggleComments(post)">{{ post.comment_count || 0 }} bình luận</button>
                <span>{{ post.view_count || 0 }} lượt xem</span>
              </div>
              <div v-if="isPostPublished(post)" class="post-card__actions">
                <button type="button" :class="{ active: Boolean(post.is_liked) }" :disabled="likingPostIds.has(post.id) || !post.likes_available" :title="post.likes_available ? '' : 'Lượt thích đang tạm thời chưa khả dụng'" @click="toggleLike(post)"><AppIcon name="heart" />{{ post.is_liked ? 'Đã thích' : 'Thích' }}</button>
                <button type="button" :class="{ active: commentsOpen[post.id] }" @click="toggleComments(post)"><AppIcon name="messageCircle" />Bình luận</button>
                <button type="button" @click="sharePost(post)"><AppIcon name="share" />Chia sẻ</button>
              </div>

              <section v-if="isPostPublished(post) && commentsOpen[post.id]" class="comments-panel" aria-label="Bình luận bài viết">
                <div v-if="detailsLoading[post.id]" class="comments-loading"><span class="loader loader-small"></span>Đang tải bình luận...</div>
                <template v-else>
                  <div v-if="post.top_level_comments?.length" class="comment-list">
                    <article v-for="comment in visibleComments(post)" :key="comment.id" class="comment-item">
                      <span class="avatar avatar-comment"><img v-if="comment.user?.avatar_url" :src="assetUrl(comment.user.avatar_url)" :alt="comment.user.full_name || comment.user.username" /><span v-else>{{ initial(comment.user?.full_name || comment.user?.username) }}</span></span>
                      <div class="comment-content">
                        <div class="comment-bubble"><strong class="author-name">{{ comment.user?.full_name || comment.user?.username || 'Thành viên SportGo' }} <ClientAuthorBadges :badges="comment.user?.author_badges" /></strong><p>{{ comment.content }}</p></div>
                        <div class="comment-actions"><small>{{ timeAgo(comment.created_at) }}</small><button type="button" @click="setReply(post, comment)">Trả lời</button></div>
                        <div v-if="comment.replies?.length" class="comment-replies">
                          <article v-for="reply in comment.replies" :key="reply.id" class="comment-item">
                            <span class="avatar avatar-comment"><img v-if="reply.user?.avatar_url" :src="assetUrl(reply.user.avatar_url)" :alt="reply.user.full_name || reply.user.username" /><span v-else>{{ initial(reply.user?.full_name || reply.user?.username) }}</span></span>
                            <div class="comment-content">
                              <div class="comment-bubble"><strong class="author-name">{{ reply.user?.full_name || reply.user?.username || 'Thành viên SportGo' }} <ClientAuthorBadges :badges="reply.user?.author_badges" /></strong><p v-html="formatMention(reply.content)"></p></div>
                              <div class="comment-actions"><small>{{ timeAgo(reply.created_at) }}</small><button type="button" @click="setReply(post, comment, reply)">Trả lời</button></div>
                            </div>
                          </article>
                        </div>
                      </div>
                    </article>
                    <button v-if="post.top_level_comments.length > commentPreviewLimit && !showAllComments[post.id]" type="button" class="show-comments-button" @click="showAllComments[post.id] = true">Xem thêm {{ post.top_level_comments.length - commentPreviewLimit }} bình luận</button>
                  </div>
                  <p v-else class="no-comments">Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.</p>
                  <form v-if="user" class="comment-form-wrapper" @submit.prevent="submitComment(post)">
                    <div v-if="replyingTo[post.id]" class="replying-indicator"><span>Đang trả lời <strong>{{ replyingTo[post.id].user?.full_name || replyingTo[post.id].user?.username || 'Thành viên SportGo' }}</strong></span><button type="button" aria-label="Hủy trả lời" @click="replyingTo[post.id] = null"><AppIcon name="x" /></button></div>
                    <div class="comment-form">
                      <span class="avatar avatar-comment">{{ initial(user.fullName) }}</span>
                      <label><span class="sr-only">Viết bình luận</span><input :id="'comment-input-' + post.id" v-model.trim="commentDrafts[post.id]" type="text" maxlength="1000" :placeholder="replyingTo[post.id] ? 'Phản hồi ' + (replyingTo[post.id].user?.full_name || replyingTo[post.id].user?.username || 'Thành viên SportGo') + '...' : 'Viết bình luận...'" :disabled="commentSubmitting[post.id]" /></label>
                      <button type="submit" class="icon-button icon-button--filled" aria-label="Gửi bình luận" :disabled="commentSubmitting[post.id] || !commentDrafts[post.id]?.trim()"><AppIcon name="send" /></button>
                    </div>
                  </form>
                  <button v-else type="button" class="login-to-comment" @click="goToLogin">Đăng nhập để bình luận</button>
                </template>
              </section>
            </article>

            <button v-if="pagination.current_page < pagination.last_page" type="button" class="button button-secondary load-more-button" :disabled="loadingMore" @click="loadMorePosts"><span v-if="loadingMore" class="loader loader-small"></span>{{ loadingMore ? 'Đang tải thêm...' : 'Xem thêm bài viết' }}</button>
            <p v-else class="end-of-feed">Bạn đã xem hết các bài viết hiện có.</p>
          </div>
        </section>

        <aside class="community-rail" aria-label="Khám phá cộng đồng">
          <section class="surface rail-panel">
            <header class="rail-panel__heading">
              <h2>Kèo sắp tới</h2>
              <button v-if="isPlayer" type="button" class="icon-button" aria-label="Tạo bài giao lưu" @click="showMeetupModal = true"><AppIcon name="plus" /></button>
            </header>
            <div v-if="matchmakingLoading" class="rail-state"><span class="loader loader-small"></span>Đang tải kèo...</div>
            <div v-else-if="matchmakingError" class="rail-state rail-state--error" role="alert"><AppIcon name="alert" /><p>{{ matchmakingError }}</p><button type="button" class="text-link" @click="fetchMatchmakingPosts">Tải lại</button></div>
            <div v-else-if="matchmakingPosts.length" class="meetup-list">
              <article v-for="post in matchmakingPosts" :key="post.id" class="meetup-item">
                <header>
                  <button type="button" class="meetup-author" @click="goToUser(post.author?.id)">
                    <span class="avatar avatar-comment"><img v-if="post.author?.avatar" :src="assetUrl(post.author.avatar)" :alt="post.author.name" /><span v-else>{{ initial(post.author?.name) }}</span></span>
                    <span><strong>{{ post.author?.name || 'Người chơi SportGo' }}</strong><small>{{ timeAgo(post.created_at) }}</small></span>
                  </button>
                  <span class="meetup-needed">Cần {{ post.needed_players }} người</span>
                </header>

                <div v-if="post.booking?.sport_name" class="meetup-sport-tag">
                  <AppIcon :name="post.booking?.sport_icon || 'activity'" size="13" />
                  <strong>{{ post.booking.sport_name }}</strong>
                  <span v-if="post.booking?.court_type_name">({{ cleanCourtType(post.booking.court_type_name, post.booking.sport_name) }})</span>
                </div>

                <div class="meetup-facts">
                  <span><AppIcon name="mapPin" />{{ post.booking?.venue_name || 'Cụm sân' }}</span>
                  <span><AppIcon name="clock" />{{ formatDate(post.booking?.date) }}, {{ post.booking?.time }}</span>
                </div>

                <div class="meetup-badges-row">
                  <span class="meetup-badge meetup-badge--skill">{{ skillLabel(post.skill_level) }}</span>
                  <span class="meetup-badge meetup-badge--cost">{{ costLabel(post) }}</span>
                </div>

                <div v-if="post.image_url" class="meetup-cover">
                  <img :src="assetUrl(post.image_url)" :alt="post.booking?.venue_name || 'Ảnh bài giao lưu'" />
                </div>
                <p v-if="post.description">{{ post.description }}</p>
                <button v-if="!isOwnPost(post)" type="button" class="button button-secondary meetup-action" :disabled="joiningPostId === post.id || Boolean(post.user_status)" @click="joinMatchmaking(post)">{{ joinLabel(post) }}</button>
                <router-link v-else class="button button-secondary meetup-action" :to="'/matchmaking-posts/' + post.id + '/manage'">Quản lý yêu cầu</router-link>
              </article>
            </div>
            <div v-else class="rail-state"><AppIcon name="users" /><p>Chưa có kèo công khai sắp tới.</p><button v-if="isPlayer" type="button" class="text-link" @click="showMeetupModal = true">Tạo kèo đầu tiên</button></div>
          </section>
          <section class="surface rail-note"><AppIcon name="shield" /><div><strong>Không gian chơi lành mạnh</strong><p>Chia sẻ lịch sự, tôn trọng thông tin cá nhân và chỉ đăng nội dung liên quan thể thao.</p></div></section>
        </aside>
      </div>
    </main>

    <CommunityPostModal :is-open="showCommunityModal" :editing-post="editingPost" @close="closeCommunityModal" @success="handleCommunityPostSaved" />
    <MeetupPostModal :is-open="showMeetupModal" @close="showMeetupModal = false" @success="handleMeetupPostCreated" />
    <ReportModal :is-open="Boolean(reportTarget)" :target-type="reportTarget?.feed_type === 'community_post' ? 'community_post' : 'venue_post'" :target-id="reportTarget?.entity_id || reportTarget?.id || ''" :target-name="reportTarget?.title || 'Bài viết cộng đồng'" @close="reportTarget = null" @success="handleReportSuccess" />
    <ConfirmModal v-model="showDeleteConfirm" :title="isForceDelete ? 'Xóa vĩnh viễn bài viết' : 'Chuyển vào thùng rác'" :message="isForceDelete ? 'Bài viết và hình ảnh sẽ bị xóa vĩnh viễn khỏi hệ thống và không thể khôi phục.' : 'Bạn có chắc chắn muốn xóa bài viết này không? Bài viết sẽ được chuyển vào thùng rác và ẩn khỏi bảng tin.'" :confirm-text="isForceDelete ? 'Xóa vĩnh viễn' : 'Xóa bài viết'" cancel-text="Hủy" type="danger" @confirm="handleConfirmDelete" @cancel="deletePostTarget = null; isForceDelete = false" />

    <Teleport to="body">
      <div v-if="showAppealModal" class="appeal-backdrop" @click.self="showAppealModal = false">
        <div class="appeal-modal" role="dialog" aria-modal="true">
          <header><h3>Đề xuất duyệt lại bài viết</h3><button type="button" class="icon-button" aria-label="Đóng" @click="showAppealModal = false"><AppIcon name="x" /></button></header>
          <div class="appeal-modal__body">
            <p>Gửi lời nhắn giải trình tới Quản trị viên nếu bạn cho rằng bài viết phù hợp với tiêu chuẩn cộng đồng SportGo.</p>
            <label for="appeal-note-input">Lời nhắn gửi Quản trị viên</label>
            <textarea id="appeal-note-input" v-model.trim="appealNote" rows="5" maxlength="500" placeholder="Nhập lời giải trình của bạn..."></textarea>
            <span class="appeal-char-count">{{ appealNote.length }}/500</span>
          </div>
          <footer><button class="button button-secondary" type="button" :disabled="isSubmittingAppeal" @click="showAppealModal = false">Hủy</button><button class="button button-primary" type="button" :disabled="isSubmittingAppeal || appealNote.length < 5" @click="submitAppeal">{{ isSubmittingAppeal ? 'Đang gửi...' : 'Gửi yêu cầu' }}</button></footer>
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
import { businessDateLabel } from '@/utils/businessTime.js';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const user = getAuth();
// Owners and admins can participate as ordinary players. The API remains the
// authority for self-join, duplicate and capacity checks.
const isPlayer = computed(() => Boolean(user));
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
let matchmakingRequestController = null;
let matchmakingRequestId = 0;

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
  matchmakingRequestController?.abort();
  const requestId = ++matchmakingRequestId;
  const controller = new AbortController();
  matchmakingRequestController = controller;
  matchmakingLoading.value = true;
  matchmakingError.value = '';
  try {
    const response = await api('/api/matchmaking-posts', {
      signal: controller.signal,
      dedupe: false,
    });
    if (requestId !== matchmakingRequestId) return;
    const payload = response?.data;
    const items = Array.isArray(payload) ? payload : Array.isArray(payload?.data) ? payload.data : [];
    matchmakingPosts.value = items.slice(0, 5);
  } catch (requestError) {
    if (controller.signal.aborted || requestId !== matchmakingRequestId) return;
    matchmakingPosts.value = [];
    matchmakingError.value = requestError.message || 'Không thể tải các kèo sắp tới.';
  } finally {
    if (requestId === matchmakingRequestId) {
      matchmakingLoading.value = false;
      matchmakingRequestController = null;
    }
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

function isPostPublished(post) {
  if (!post) return false;
  if (post.is_deleted || post.deleted_at) return false;
  return post.status === 'published' || post.status === 'approved';
}

async function toggleComments(post) {
  if (!isPostPublished(post)) {
    toast.warning('Bài viết chưa được phê duyệt nên chưa thể xem hoặc viết bình luận.');
    return;
  }
  commentsOpen[post.id] = !commentsOpen[post.id];
  if (commentsOpen[post.id]) await ensurePostDetails(post);
}

async function toggleLike(post) {
  if (!isPostPublished(post)) {
    toast.warning('Bài viết chưa được phê duyệt nên chưa thể tương tác.');
    return;
  }
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

async function handleMeetupPostCreated() {
  showMeetupModal.value = false;
  toast.success('Bài giao lưu đã được tạo.');
  // Refresh in the background so the success state is not coupled to the
  // modal's submit lifecycle.
  void fetchMatchmakingPosts();
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
  return businessDateLabel(value) || 'Chưa rõ ngày';
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

function skillLabel(level) {
  return {
    all: 'Mọi trình độ',
    beginner: 'Mới chơi',
    intermediate: 'Trung bình',
    advanced: 'Nâng cao',
  }[level] || 'Mọi trình độ';
}

function costLabel(post) {
  if (post?.cost_type === 'free') return 'Miễn phí';
  if (Number(post?.cost_per_player) > 0) {
    const k = Math.round(Number(post.cost_per_player) / 1000);
    return `~${k}k / người`;
  }
  return 'Chia đều tiền sân';
}

function cleanCourtType(typeName, sportName) {
  if (!typeName) return 'Sân tiêu chuẩn';
  const match = String(typeName).match(/\((.*?)\)/);
  if (match && match[1]) return match[1].trim();
  if (sportName && String(typeName).toLowerCase().startsWith(String(sportName).toLowerCase())) {
    const cleaned = String(typeName).slice(sportName.length).trim().replace(/^[-·:() ]+/, '').replace(/\)$/, '');
    if (cleaned) return cleaned;
  }
  return typeName;
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

onBeforeUnmount(() => {
  matchmakingRequestId += 1;
  matchmakingRequestController?.abort();
  document.removeEventListener('click', closePostMenu);
});
</script>

<style scoped>
.community-page {
  --community-ink: #1e293b;
  --community-muted: #64748b;
  --community-soft: #f8fafc;
  --community-surface: #fff;
  --community-line: #e2e8f0;
  --community-accent: #5c7e6e;
  --community-accent-dark: #446153;
  --community-accent-soft: #edf4f0;
  --community-danger: #b42318;
  --community-danger-soft: #fff2f0;
  min-height: 100vh;
  color: var(--community-ink);
  background: var(--community-soft);
  font-family: var(--sportgo-font-body, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
}
.community-shell {
  width: min(1400px, calc(100% - 48px));
  margin: 0 auto;
  padding: 24px 0 64px;
}
.community-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 420px;
  align-items: start;
  gap: 28px;
}
.community-main, .community-rail { min-width: 0; }
.community-main { display: flex; flex-direction: column; gap: 16px; }
.community-rail { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 22px; }
.surface { background: var(--community-surface); border: 1.5px solid var(--community-line); border-radius: 12px; box-shadow: 0 4px 16px rgba(15, 23, 42, .03); }
.button, .text-action, .icon-button, .community-tabs button, .community-tabs a, .filter-nav button, .post-card__actions button, .text-link, .post-notice button, .comment-actions button { font: inherit; cursor: pointer; }
.button { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 18px; border: 1px solid transparent; border-radius: 999px; font-size: 13.5px; font-weight: 600; text-decoration: none; transition: background-color .16s ease, border-color .16s ease, color .16s ease, transform .16s ease, box-shadow .16s ease; }
.button:active { transform: translateY(1px); }
.button:focus-visible, button:focus-visible, a:focus-visible, input:focus-visible, textarea:focus-visible { outline: 3px solid rgba(92, 126, 110, .24); outline-offset: 2px; }
.button-primary { color: #fff; background: #54656f; box-shadow: 0 4px 14px rgba(84, 101, 111, 0.25); }
.button-primary:hover { background: #405059; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(84, 101, 111, 0.35); }
.button-secondary { color: #475569; background: #fff; border: 1.5px solid var(--community-line); }
.button-secondary:hover { background: #f8fafc; border-color: #54656f; color: #0f172a; }
.button-quiet { min-height: 36px; color: var(--community-muted); background: transparent; border-color: transparent; padding-inline: 8px; }
.button-quiet:hover { color: var(--community-accent-dark); background: var(--community-accent-soft); }
.button-small { min-height: 36px; padding-inline: 14px; }
.button:disabled, .icon-button:disabled { opacity: .55; cursor: not-allowed; transform: none; }
.icon-button { width: 40px; height: 40px; display: inline-grid; place-items: center; flex: 0 0 auto; padding: 0; border: 1.5px solid var(--community-line); border-radius: 999px; color: var(--community-ink); background: #fff; }
.icon-button:hover { color: #0f172a; background: var(--community-soft); border-color: #54656f; }
.icon-button--filled { color: #fff; background: #54656f; border-color: #54656f; }
.icon-button--filled:hover { color: #fff; background: #405059; }
.avatar { width: 44px; height: 44px; display: inline-grid; place-items: center; flex: 0 0 auto; overflow: hidden; border-radius: 50%; color: #fff; background: #54656f; font-size: 16px; font-weight: 700; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-comment { width: 32px; height: 32px; font-size: 12.5px; }
.composer { padding: 20px; }
.composer__row { display: flex; align-items: center; gap: 14px; }
.composer__prompt { min-height: 46px; flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 15px; border: 1.5px solid var(--community-line); border-radius: 8px; color: var(--community-muted); background: #fbfdfb; font: inherit; text-align: left; transition: border-color 0.15s ease; }
.composer__prompt:hover { color: var(--community-ink); border-color: #54656f; background: #f8fafc; }
.composer__actions { display: flex; gap: 24px; margin: 18px 0 0 58px; padding-top: 6px; }
.text-action { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0; border: 0; color: var(--community-ink); background: transparent; font-size: 13.5px; font-weight: 600; }
.text-action:hover { color: var(--community-accent); }
.community-tabs { display: flex; align-items: stretch; min-height: 52px; padding: 5px; }
.community-tabs button, .community-tabs a { flex: 1; min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 12px; border: 0; border-radius: 8px; color: var(--community-muted); background: transparent; font-size: 13.5px; font-weight: 600; text-align: center; text-decoration: none; transition: all 0.15s ease; }
.community-tabs button:hover, .community-tabs a:hover { color: var(--community-ink); background: var(--community-soft); }
.community-tabs .active { color: var(--community-accent-dark); background: var(--community-accent-soft); font-weight: 700; }
.filter-panel { padding: 22px; }
.filter-panel__heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.filter-panel h2, .rail-panel h2 { margin: 0; color: var(--community-ink); font-size: 18px; font-weight: 700; }
.search-field { min-height: 48px; display: flex; align-items: center; gap: 10px; margin-top: 20px; padding: 4px 5px 4px 14px; border: 1px solid var(--community-line); border-radius: 8px; color: var(--community-muted); background: #fbfdfb; }
.search-field:focus-within { border-color: var(--community-accent); box-shadow: 0 0 0 3px rgba(20, 122, 70, .1); }
.search-field input { min-width: 0; flex: 1; height: 38px; border: 0; outline: 0; color: var(--community-ink); background: transparent; font: inherit; }
.search-field input::placeholder { color: #8a9990; }
.filter-nav { display: flex; flex-wrap: wrap; gap: 4px 18px; margin-top: 17px; }
.filter-nav button { min-height: 36px; padding: 0; border: 0; color: var(--community-muted); background: transparent; font-size: 13px; font-weight: 600; }
.filter-nav button:hover { color: var(--community-accent-dark); }
.filter-nav button.active { color: var(--community-accent-dark); box-shadow: inset 0 -2px 0 var(--community-accent); }
.feed-state { min-height: 220px; display: flex; align-items: center; justify-content: center; flex-wrap: wrap; align-content: center; gap: 10px 14px; padding: 32px; text-align: center; color: var(--community-muted); }
.feed-state > svg { width: 30px; height: 30px; color: var(--community-accent); }
.feed-state strong { width: 100%; color: var(--community-ink); font-size: 16px; }
.feed-state p { width: 100%; margin: 0; line-height: 1.5; }
.feed-state--error { justify-content: flex-start; text-align: left; }
.feed-state--error > svg { color: var(--community-danger); }
.feed-state--error div { flex: 1; min-width: 180px; }
.feed-state--error strong, .feed-state--error p { display: block; width: auto; }
.loader { width: 24px; height: 24px; display: inline-block; border: 3px solid #d7e8dc; border-top-color: var(--community-accent); border-radius: 50%; animation: community-spin .8s linear infinite; }
.loader-small { width: 16px; height: 16px; border-width: 2px; }
@keyframes community-spin { to { transform: rotate(360deg); } }
.post-stream { display: flex; flex-direction: column; gap: 16px; }
.post-card { overflow: visible; }
.post-card__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 20px 22px 15px; }
.author-button, .meetup-author { display: flex; align-items: center; gap: 11px; min-width: 0; padding: 0; border: 0; color: inherit; background: transparent; text-align: left; cursor: pointer; }
.author-button { flex: 1; }
.author-copy, .meetup-author > span:last-child { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.author-name { display: flex; align-items: center; flex-wrap: wrap; gap: 5px; color: var(--community-ink); font-size: 14px; font-weight: 700; }
.author-copy small, .meetup-author small { color: var(--community-muted); font-size: 12px; line-height: 1.35; }
.author-button:hover .author-name, .meetup-author:hover strong { color: var(--community-accent-dark); }
.post-menu-wrap { position: relative; }
.post-menu { position: absolute; z-index: 10; top: 46px; right: 0; width: 210px; padding: 6px; border: 1px solid var(--community-line); border-radius: 10px; background: #fff; box-shadow: 0 12px 28px rgba(20, 43, 30, .14); }
.post-menu button { width: 100%; min-height: 40px; display: flex; align-items: center; gap: 9px; padding: 0 10px; border: 0; border-radius: 6px; color: var(--community-ink); background: transparent; font: inherit; font-size: 13px; text-align: left; cursor: pointer; }
.post-menu button:hover { background: var(--community-soft); }
.post-menu .menu-danger { color: var(--community-danger); }
.post-notice { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px 10px; margin: 0 22px 14px; padding: 12px 14px; border-radius: 8px; color: #6b4c00; background: #fff8e5; font-size: 13px; line-height: 1.45; }
.post-notice--danger { color: var(--community-danger); background: var(--community-danger-soft); }
.post-notice strong { font-weight: 700; }
.post-notice span { flex: 1 1 100%; }
.post-notice button { padding: 0; border: 0; color: inherit; background: transparent; font-size: 12px; font-weight: 700; text-decoration: underline; }
.post-card__body { padding: 0 22px 18px; }
.post-tags { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-bottom: 10px; color: var(--community-accent); font-size: 12px; }
.post-copy { width: 100%; display: flex; flex-direction: column; align-items: flex-start; gap: 7px; padding: 0; border: 0; color: var(--community-ink); background: transparent; font: inherit; line-height: 1.6; text-align: left; cursor: pointer; }
.post-copy:hover { color: var(--community-accent-dark); }
.post-copy strong { font-size: 18px; font-weight: 700; line-height: 1.3; }
.post-copy span { color: var(--community-muted); font-size: 14px; white-space: pre-line; }
.post-media { width: 100%; display: block; overflow: hidden; padding: 0; border: 0; background: #eaf0eb; cursor: pointer; }
.post-media img { width: 100%; height: 100%; display: block; object-fit: cover; transition: transform .2s ease; }
.post-media:hover img { transform: scale(1.015); }
.media-single { aspect-ratio: 16 / 9; }
.media-grid { display: grid; gap: 3px; aspect-ratio: 16 / 9; }
.media-grid.media-count-2 { grid-template-columns: repeat(2, 1fr); }
.media-grid.media-count-3, .media-grid.media-count-4 { grid-template-columns: repeat(2, 1fr); grid-template-rows: repeat(2, 1fr); }
.media-grid > span { position: relative; min-width: 0; min-height: 0; overflow: hidden; }
.media-grid b { position: absolute; inset: 0; display: grid; place-items: center; color: #fff; background: rgba(12, 30, 20, .58); font-size: 20px; }
.post-card__stats { display: flex; align-items: center; gap: 16px; padding: 13px 22px; color: var(--community-muted); font-size: 12px; }
.post-card__stats span, .post-card__stats button { display: inline-flex; align-items: center; gap: 5px; color: inherit; }
.post-card__stats svg { width: 15px; height: 15px; }
.post-card__stats button { padding: 0; border: 0; background: transparent; font: inherit; cursor: pointer; }
.post-card__stats button:hover { color: var(--community-accent-dark); }
.post-card__actions { display: grid; grid-template-columns: repeat(3, 1fr); margin: 0 22px; padding: 7px 0 10px; }
.post-card__actions button { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: 0; color: var(--community-muted); background: transparent; font-size: 13px; font-weight: 650; }
.post-card__actions button:hover, .post-card__actions button.active { color: var(--community-accent-dark); background: var(--community-accent-soft); }
.comments-panel { margin: 0 22px 18px; padding: 16px 0 0; background: #f8fbf9; }
.comments-loading { display: flex; align-items: center; gap: 8px; padding: 10px 14px 18px; color: var(--community-muted); font-size: 13px; }
.comment-list { display: flex; flex-direction: column; gap: 14px; padding: 0 14px; }
.comment-item { display: flex; align-items: flex-start; gap: 9px; }
.comment-content { min-width: 0; flex: 1; }
.comment-bubble { padding: 9px 12px; border-radius: 8px; background: #fff; }
.comment-bubble .author-name { font-size: 12px; }
.comment-bubble p { margin: 5px 0 0; color: var(--community-ink); font-size: 13px; line-height: 1.5; white-space: pre-line; }
.comment-actions { display: flex; align-items: center; gap: 10px; margin: 4px 0 0 3px; color: var(--community-muted); font-size: 11px; }
.comment-actions button, .show-comments-button, .login-to-comment { padding: 0; border: 0; color: var(--community-accent-dark); background: transparent; font: inherit; font-size: 12px; font-weight: 650; cursor: pointer; }
.comment-replies { display: flex; flex-direction: column; gap: 10px; margin: 11px 0 0 20px; }
.show-comments-button { margin: 0 0 2px 43px; }
.no-comments { margin: 0; padding: 8px 14px 18px; color: var(--community-muted); font-size: 13px; }
.comment-form-wrapper { padding: 14px; }
.replying-indicator { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 8px; color: var(--community-muted); font-size: 12px; }
.replying-indicator button { padding: 0; border: 0; color: var(--community-muted); background: transparent; cursor: pointer; }
.comment-form { display: flex; align-items: center; gap: 9px; }
.comment-form label { flex: 1; min-width: 0; }
.comment-form input { width: 100%; height: 40px; padding: 0 12px; border: 1px solid var(--community-line); border-radius: 8px; color: var(--community-ink); background: #fff; font: inherit; font-size: 13px; }
.login-to-comment { display: block; margin: 0 14px 16px; padding: 10px 0; text-align: left; }
.load-more-button { align-self: center; min-width: 190px; }
.end-of-feed { margin: 0; color: var(--community-muted); font-size: 12px; text-align: center; }
.rail-panel { padding: 22px; }
.rail-panel__heading { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
.meetup-list { display: flex; flex-direction: column; }
.meetup-item { padding: 18px 0; border-bottom: 1px solid var(--community-line); }
.meetup-item:last-child { border-bottom: none; }
.meetup-item header { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.meetup-author { flex: 1; min-width: 0; }
.meetup-author strong { color: var(--community-ink); font-size: 13.5px; font-weight: 600; line-height: 1.3; }
.meetup-needed { flex: 0 0 auto; color: var(--community-accent-dark); font-size: 11.5px; font-weight: 600; padding: 3px 9px; background: var(--community-accent-soft); border-radius: 4px; }
.meetup-sport-tag { display: inline-flex; align-items: center; gap: 6px; margin: 10px 0 6px 0; padding: 4px 10px; background: var(--community-accent-soft); border-radius: 6px; color: var(--community-accent-dark); font-size: 12px; }
.meetup-sport-tag strong { font-weight: 600; }
.meetup-sport-tag span { color: var(--community-accent); }
.meetup-facts { display: flex; flex-direction: column; gap: 6px; margin: 8px 0 0 0; color: var(--community-muted); font-size: 12.5px; line-height: 1.4; }
.meetup-facts span { display: flex; align-items: center; gap: 7px; }
.meetup-facts svg { width: 14px; height: 14px; flex: 0 0 auto; color: var(--community-accent); }
.meetup-badges-row { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0 0 0; }
.meetup-badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 4px; font-size: 11.5px; font-weight: 500; }
.meetup-badge--skill { background: #f1f5f9; color: #334155; }
.meetup-badge--cost { background: #fef3c7; color: #92400e; }
.meetup-cover { margin: 12px 0 0 0; border-radius: 8px; overflow: hidden; max-height: 180px; border: 1px solid var(--community-line); background: #f8fafc; }
.meetup-cover img { width: 100%; height: 100%; max-height: 180px; object-fit: cover; display: block; }
.meetup-item p { margin: 10px 0 0 0; color: var(--community-ink); font-size: 13px; line-height: 1.5; }
.meetup-action { width: 100%; margin: 14px 0 0 0; min-height: 38px; }
.rail-state { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 9px; min-height: 150px; padding: 12px 0; color: var(--community-muted); font-size: 13px; text-align: center; }
.rail-state > svg { width: 26px; height: 26px; color: var(--community-accent); }
.rail-state p { flex-basis: 100%; margin: 0; line-height: 1.5; }
.rail-state--error > svg { color: var(--community-danger); }
.text-link { padding: 0; border: 0; color: var(--community-accent-dark); background: transparent; font-size: 12px; font-weight: 700; }
.rail-note { display: flex; gap: 12px; padding: 18px 20px; color: var(--community-muted); }
.rail-note > svg { width: 22px; height: 22px; flex: 0 0 auto; color: var(--community-accent); }
.rail-note strong { display: block; margin-bottom: 5px; color: var(--community-ink); font-size: 13px; }
.rail-note p { margin: 0; font-size: 12px; line-height: 1.5; }
.post-status { font-weight: 650; }
.status-pending_review { color: #8a5a00; }
.status-rejected, .status-deleted { color: var(--community-danger); }
.status-published { color: var(--community-accent-dark); }
.appeal-backdrop { position: fixed; z-index: 1000; inset: 0; display: grid; place-items: center; padding: 20px; background: rgba(15, 31, 22, .42); }
.appeal-modal { width: min(520px, 100%); overflow: hidden; border-radius: 14px; background: #fff; box-shadow: 0 20px 60px rgba(10, 30, 18, .2); }
.appeal-modal header, .appeal-modal footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 22px; }
.appeal-modal header { padding-bottom: 12px; }
.appeal-modal h3 { margin: 0; font-size: 18px; }
.appeal-modal__body { padding: 20px 22px; }
.appeal-modal__body p { margin: 0 0 18px; color: var(--community-muted); font-size: 13px; line-height: 1.55; }
.appeal-modal__body label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 700; }
.appeal-modal textarea { width: 100%; box-sizing: border-box; resize: vertical; padding: 12px; border: 1px solid var(--community-line); border-radius: 8px; color: var(--community-ink); font: inherit; font-size: 13px; }
.appeal-char-count { display: block; margin-top: 6px; color: var(--community-muted); font-size: 11px; text-align: right; }
.appeal-modal footer { justify-content: flex-end; padding-top: 12px; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 1280px) {
  .community-shell { width: min(100% - 36px, 1200px); padding-top: 20px; }
  .community-layout { grid-template-columns: minmax(0, 1fr) 380px; gap: 20px; }
}

@media (max-width: 1024px) {
  .community-shell { width: min(100% - 32px, 860px); padding: 18px 0 48px; }
  .community-layout { grid-template-columns: 1fr; gap: 20px; }
  .community-rail { position: static; }
}

@media (max-width: 640px) {
  .community-shell { width: 100%; padding: 12px 10px 48px; }
  .surface { border-radius: 10px; }
  .composer { padding: 14px 12px; }
  .composer__prompt { min-height: 42px; padding: 0 12px; font-size: 13px; }
  .composer__actions { gap: 16px; margin: 12px 0 0 0; padding-top: 4px; justify-content: space-around; }
  .community-tabs { overflow-x: auto; padding: 4px; }
  .community-tabs button, .community-tabs a { min-width: 140px; padding: 0 8px; font-size: 13px; }
  .filter-panel { padding: 16px 12px; }
  .filter-panel__heading { align-items: center; }
  .filter-panel h2 { font-size: 17px; }
  .filter-nav { gap: 4px 12px; margin-top: 12px; }
  .post-card__header { padding: 14px 14px 10px; }
  .post-card__body { padding: 0 14px 14px; }
  .post-notice { margin: 0 14px 12px; padding: 10px 12px; }
  .post-card__stats { padding: 10px 14px; gap: 12px; }
  .post-card__actions { margin: 0 14px; padding: 5px 0 8px; }
  .comments-panel { margin: 0 14px 14px; padding: 12px 0 0; }
  .comment-list { padding: 0 10px; }
  .comment-replies { margin-left: 8px; }
  .meetup-facts, .meetup-item p { margin-left: 0; }
  .meetup-action { width: 100%; margin-left: 0; }
}
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; transition-duration: .01ms !important; } }
</style>
