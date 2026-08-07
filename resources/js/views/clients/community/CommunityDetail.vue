<template>
    <div class="sg-community-detail-page">
        <PublicNavbar />

        <main class="sg-community-detail-main">
            <nav class="sg-community-breadcrumb" aria-label="Đường dẫn">
                <router-link to="/community">
                    <ArrowLeft :size="17" />
                    Cộng đồng
                </router-link>
                <span>/</span>
                <span>Bài chia sẻ</span>
            </nav>

            <section v-if="loading" class="sg-community-detail-state" aria-live="polite">
                <div class="sg-community-detail-skeleton">
                    <span class="skeleton-avatar"></span>
                    <span class="skeleton-line skeleton-line--short"></span>
                    <span class="skeleton-line"></span>
                    <span class="skeleton-line"></span>
                    <span class="skeleton-block"></span>
                </div>
            </section>

            <section v-else-if="error" class="sg-community-detail-state">
                <CircleAlert :size="28" />
                <h1>Không thể mở bài viết</h1>
                <p>{{ error }}</p>
                <button type="button" class="sg-community-primary-btn" @click="loadPost">
                    <RefreshCw :size="17" />
                    Tải lại
                </button>
            </section>

            <div v-else-if="post" class="sg-community-detail-layout">
                <article class="sg-community-post-detail">
                    <header class="sg-community-post-header">
                        <router-link
                            v-if="post.author?.id"
                            class="sg-community-author-avatar"
                            :to="`/user/${post.author.id}`"
                            :aria-label="`Xem trang của ${authorName}`"
                        >
                            <img
                                v-if="post.author.avatar_url"
                                :src="post.author.avatar_url"
                                :alt="authorName"
                            />
                            <span v-else>{{ initials(authorName) }}</span>
                        </router-link>
                        <div v-else class="sg-community-author-avatar">
                            <span>{{ initials(authorName) }}</span>
                        </div>

                        <div class="sg-community-author-copy">
                            <router-link
                                v-if="post.author?.id"
                                :to="`/user/${post.author.id}`"
                                class="sg-community-author-name"
                            >
                                {{ authorName }}
                            </router-link>
                            <strong v-else class="sg-community-author-name">{{ authorName }}</strong>
                            <div class="sg-community-post-meta">
                                <span>{{ formatDate(post.created_at || post.published_at) }}</span>
                                <span aria-hidden="true">·</span>
                                <span>Bài chia sẻ cộng đồng</span>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="sg-community-icon-btn"
                            aria-label="Báo cáo bài viết"
                            title="Báo cáo bài viết"
                            @click="openReport(postReportType, post.entity_id, post.title)"
                        >
                            <Flag :size="18" />
                        </button>
                    </header>

                    <div class="sg-community-post-body">
                        <p class="sg-community-eyebrow">{{ postKindLabel }}</p>
                        <h1>{{ post.title }}</h1>
                        <div class="sg-community-post-content" v-html="post.content"></div>

                        <div v-if="post.media?.length" class="sg-community-media">
                            <img
                                v-for="media in post.media"
                                :key="media.id || media.file_path"
                                :src="normalizeMediaUrl(media)"
                                :alt="post.title"
                                loading="lazy"
                            />
                        </div>
                    </div>

                    <div class="sg-community-post-stats">
                        <span><Heart :size="16" /> {{ post.like_count || 0 }} lượt thích</span>
                        <span><MessageCircle :size="16" /> {{ post.comment_count || 0 }} bình luận</span>
                        <span><Eye :size="16" /> {{ post.view_count || 0 }} lượt xem</span>
                    </div>

                    <div class="sg-community-post-actions">
                        <button
                            type="button"
                            :class="{ active: post.is_liked }"
                            :disabled="isSubmittingLike"
                            @click="toggleLike"
                        >
                            <Heart :size="19" :fill="post.is_liked ? 'currentColor' : 'none'" />
                            {{ post.is_liked ? "Đã thích" : "Thích" }}
                        </button>
                        <button type="button" @click="focusComment">
                            <MessageCircle :size="19" />
                            Bình luận
                        </button>
                        <button type="button" @click="copyLink">
                            <Share2 :size="19" />
                            Chia sẻ
                        </button>
                    </div>

                    <section class="sg-community-comments" aria-labelledby="community-comments-title">
                        <div class="sg-community-comments-heading">
                            <div>
                                <p class="sg-community-eyebrow">Trao đổi</p>
                                <h2 id="community-comments-title">Bình luận</h2>
                            </div>
                            <span>{{ post.comment_count || 0 }} phản hồi</span>
                        </div>

                        <form class="sg-community-comment-form" @submit.prevent="submitComment">
                            <div class="sg-community-comment-avatar">
                                {{ initials(currentUser?.full_name || currentUser?.username || "Bạn") }}
                            </div>
                            <div class="sg-community-comment-input">
                                <textarea
                                    ref="commentInput"
                                    v-model.trim="newComment"
                                    rows="2"
                                    maxlength="1000"
                                    :placeholder="
                                        currentUser
                                            ? 'Viết bình luận của bạn...'
                                            : 'Đăng nhập để tham gia thảo luận'
                                    "
                                    :disabled="!currentUser || isSubmittingComment"
                                ></textarea>
                                <button
                                    v-if="currentUser"
                                    type="submit"
                                    :disabled="newComment.length < 2 || isSubmittingComment"
                                    aria-label="Gửi bình luận"
                                >
                                    <LoaderCircle v-if="isSubmittingComment" class="spin" :size="18" />
                                    <Send v-else :size="18" />
                                </button>
                                <router-link v-else to="/login">Đăng nhập</router-link>
                            </div>
                        </form>

                        <p v-if="commentError" class="sg-community-inline-error">{{ commentError }}</p>

                        <div v-if="comments.length" class="sg-community-comment-list">
                            <article
                                v-for="comment in comments"
                                :id="`comment-${comment.id}`"
                                :key="comment.id"
                                class="sg-community-comment"
                            >
                                <div class="sg-community-comment-avatar">
                                    {{ initials(comment.user?.full_name || comment.user?.username || "?") }}
                                </div>
                                <div class="sg-community-comment-copy">
                                    <div class="sg-community-comment-topline">
                                        <strong>
                                            {{ comment.user?.full_name || comment.user?.username || "Thành viên SportGo" }}
                                        </strong>
                                        <span>{{ timeAgo(comment.created_at) }}</span>
                                        <button
                                            type="button"
                                            aria-label="Báo cáo bình luận"
                                            title="Báo cáo bình luận"
                                            @click="
                                                openReport(
                                                    commentReportType,
                                                    comment.id,
                                                    'Bình luận của ' +
                                                        (comment.user?.full_name || comment.user?.username || 'thành viên'),
                                                )
                                            "
                                        >
                                            <Flag :size="15" />
                                        </button>
                                    </div>
                                    <p>{{ comment.content }}</p>

                                    <div v-if="comment.replies?.length" class="sg-community-replies">
                                        <div v-for="reply in comment.replies" :key="reply.id">
                                            <strong>
                                                {{ reply.user?.full_name || reply.user?.username || "Thành viên SportGo" }}
                                            </strong>
                                            <span>{{ reply.content }}</span>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div v-else class="sg-community-comments-empty">
                            <MessageCircle :size="24" />
                            <p>Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.</p>
                        </div>
                    </section>
                </article>

                <aside class="sg-community-detail-aside">
                    <p class="sg-community-eyebrow">SportGo Community</p>
                    <h2>Thêm một người chơi, thêm một cuộc vui</h2>
                    <p>
                        Chia sẻ kinh nghiệm, tìm bạn chơi và theo dõi những câu chuyện mới
                        từ cộng đồng thể thao.
                    </p>
                    <router-link to="/community">
                        Xem bảng tin
                        <ArrowRight :size="17" />
                    </router-link>
                </aside>
            </div>
        </main>

        <div v-if="notice" class="sg-community-toast" role="status">{{ notice }}</div>

        <ReportModal
            :is-open="reportModal.open"
            :target-type="reportModal.targetType"
            :target-id="reportModal.targetId"
            :target-name="reportModal.targetName"
            @close="reportModal.open = false"
            @success="handleReportSuccess"
        />
    </div>
</template>

<script setup>
import {
    ArrowLeft,
    ArrowRight,
    CircleAlert,
    Eye,
    Flag,
    Heart,
    LoaderCircle,
    MessageCircle,
    RefreshCw,
    Send,
    Share2,
} from "lucide-vue-next";
import { computed, nextTick, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import PublicNavbar from "@/components/PublicNavbar.vue";
import ReportModal from "@/components/ReportModal.vue";
import { api } from "@/services/api.js";
import { getAuth } from "@/stores/auth.js";
import { normalizeMediaUrl } from "@/utils/mediaUrl.js";

const route = useRoute();
const router = useRouter();

const post = ref(null);
const loading = ref(true);
const error = ref("");
const newComment = ref("");
const commentError = ref("");
const isSubmittingComment = ref(false);
const isSubmittingLike = ref(false);
const commentInput = ref(null);
const notice = ref("");
const reportModal = reactive({
    open: false,
    targetType: "community_post",
    targetId: "",
    targetName: "",
});

const currentUser = computed(() => getAuth()?.user || null);
const authorName = computed(
    () => post.value?.author?.full_name || post.value?.author?.username || "Thành viên SportGo",
);
const comments = computed(() =>
    Array.isArray(post.value?.top_level_comments) ? post.value.top_level_comments : [],
);
const isCommunityPost = computed(() => post.value?.feed_type === "community_post");
const postKindLabel = computed(() =>
    isCommunityPost.value
        ? "Câu chuyện từ sân đấu"
        : `Bài viết từ ${post.value?.venue_cluster?.name || "cụm sân"}`,
);
const postReportType = computed(() =>
    isCommunityPost.value ? "community_post" : "venue_post",
);
const commentReportType = computed(() =>
    isCommunityPost.value ? "community_post_comment" : "comment",
);

let noticeTimer;

function showNotice(message) {
    notice.value = message;
    window.clearTimeout(noticeTimer);
    noticeTimer = window.setTimeout(() => {
        notice.value = "";
    }, 2800);
}

async function loadPost() {
    loading.value = true;
    error.value = "";

    try {
        const response = await api(`/api/venue-posts/${route.params.slug}`);
        post.value = response.data;
    } catch (requestError) {
        post.value = null;
        error.value = requestError.message || "Bài viết không tồn tại hoặc chưa được xuất bản.";
    } finally {
        loading.value = false;
    }
}

async function toggleLike() {
    if (!currentUser.value) {
        router.push({ name: "login", query: { redirect: route.fullPath } });
        return;
    }
    if (isSubmittingLike.value) return;

    const previousLiked = Boolean(post.value.is_liked);
    const previousCount = Number(post.value.like_count || 0);
    const nextLiked = !previousLiked;

    // Keep the detail page responsive while the server persists the toggle.
    post.value.is_liked = nextLiked;
    post.value.like_count = Math.max(0, previousCount + (nextLiked ? 1 : -1));
    isSubmittingLike.value = true;
    try {
        const response = await api(`/api/venue-posts/${post.value.id}/likes`, {
            method: "POST",
        });
        const result = response.data || {};
        post.value.is_liked = Boolean(result.is_liked);
        post.value.like_count = Number(result.like_count ?? post.value.like_count ?? 0);
    } catch (requestError) {
        post.value.is_liked = previousLiked;
        post.value.like_count = previousCount;
        showNotice(requestError.message || "Không thể cập nhật lượt thích.");
    } finally {
        isSubmittingLike.value = false;
    }
}

async function submitComment() {
    if (!currentUser.value) {
        router.push({ name: "login", query: { redirect: route.fullPath } });
        return;
    }
    if (newComment.value.length < 2 || isSubmittingComment.value) return;

    isSubmittingComment.value = true;
    commentError.value = "";
    try {
        const response = await api(`/api/venue-posts/${post.value.id}/comments`, {
            method: "POST",
            body: JSON.stringify({ content: newComment.value }),
        });
        const createdComment = response.data || null;
        if (createdComment) {
            post.value.top_level_comments = [createdComment, ...comments.value];
        }
        post.value.comment_count = Number(
            createdComment?.comment_count ?? Number(post.value.comment_count || 0) + 1,
        );
        newComment.value = "";
    } catch (requestError) {
        commentError.value = requestError.message || "Không thể gửi bình luận.";
    } finally {
        isSubmittingComment.value = false;
    }
}

function focusComment() {
    if (!currentUser.value) {
        router.push({ name: "login", query: { redirect: route.fullPath } });
        return;
    }
    nextTick(() => commentInput.value?.focus());
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(window.location.href);
        showNotice("Đã sao chép liên kết bài viết.");
    } catch {
        showNotice("Không thể sao chép tự động. Hãy sao chép địa chỉ trên trình duyệt.");
    }
}

function openReport(targetType, targetId, targetName) {
    if (!currentUser.value) {
        router.push({ name: "login", query: { redirect: route.fullPath } });
        return;
    }
    reportModal.targetType = targetType;
    reportModal.targetId = targetId;
    reportModal.targetName = targetName || "";
    reportModal.open = true;
}

function handleReportSuccess() {
    reportModal.open = false;
    showNotice("Báo cáo đã được gửi đến đội ngũ SportGo.");
}

function initials(name) {
    return String(name || "?")
        .split(/\s+/)
        .filter(Boolean)
        .slice(-2)
        .map((part) => part[0])
        .join("")
        .toUpperCase();
}

function formatDate(value) {
    if (!value) return "";
    return new Intl.DateTimeFormat("vi-VN", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}

function timeAgo(value) {
    if (!value) return "";
    const diff = Math.max(0, Date.now() - new Date(value).getTime());
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return "Vừa xong";
    if (minutes < 60) return `${minutes} phút trước`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} giờ trước`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days} ngày trước`;
    return formatDate(value);
}

watch(
    () => route.params.slug,
    () => loadPost(),
    { immediate: true },
);
</script>

<style scoped>
.sg-community-detail-page {
    min-height: 100vh;
    background: #f4f8f5;
    color: #14241a;
}

.sg-community-detail-main {
    width: min(1180px, calc(100% - 40px));
    margin: 0 auto;
    padding: 104px 0 80px;
}

.sg-community-breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
    color: #6a7c70;
    font-size: 14px;
    font-weight: 600;
}

.sg-community-breadcrumb a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #087f3e;
    text-decoration: none;
}

.sg-community-detail-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 56px;
    align-items: start;
}

.sg-community-post-detail {
    min-width: 0;
    padding: 34px 40px 44px;
    border: 1px solid #d7e5da;
    border-radius: 8px;
    background: #fff;
}

.sg-community-post-header {
    display: grid;
    grid-template-columns: 48px minmax(0, 1fr) 40px;
    gap: 14px;
    align-items: center;
    padding-bottom: 26px;
    border-bottom: 1px solid #e3ece5;
}

.sg-community-author-avatar,
.sg-community-comment-avatar {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    overflow: hidden;
    border-radius: 50%;
    background: #dff4e6;
    color: #087f3e;
    font-weight: 800;
    text-decoration: none;
}

.sg-community-author-avatar {
    width: 48px;
    height: 48px;
}

.sg-community-author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sg-community-author-copy {
    min-width: 0;
}

.sg-community-author-name {
    color: #14241a;
    font-size: 15px;
    font-weight: 800;
    text-decoration: none;
}

.sg-community-post-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 4px;
    color: #748379;
    font-size: 13px;
}

.sg-community-icon-btn {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border: 1px solid #d7e5da;
    border-radius: 6px;
    background: #fff;
    color: #5d7163;
    cursor: pointer;
}

.sg-community-icon-btn:hover {
    border-color: #0aa052;
    color: #087f3e;
}

.sg-community-post-body {
    padding: 38px 0 30px;
}

.sg-community-eyebrow {
    margin: 0 0 10px;
    color: #078542;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
}

.sg-community-post-body h1 {
    max-width: 800px;
    margin: 0;
    color: #102118;
    font-family: "Outfit", "Inter", sans-serif;
    font-size: 48px;
    line-height: 1.1;
}

.sg-community-post-content {
    margin: 28px 0 0;
    color: #34493a;
    font-size: 17px;
    line-height: 1.8;
    white-space: pre-line;
}

.sg-community-post-content :deep(p) {
    margin: 0 0 16px;
}

.sg-community-post-content :deep(p:last-child) {
    margin-bottom: 0;
}

.sg-community-media {
    display: grid;
    gap: 12px;
    margin-top: 30px;
}

.sg-community-media img {
    width: 100%;
    max-height: 620px;
    border-radius: 6px;
    object-fit: cover;
}

.sg-community-post-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 22px;
    padding: 17px 0;
    border-top: 1px solid #e3ece5;
    border-bottom: 1px solid #e3ece5;
    color: #64766a;
    font-size: 13px;
}

.sg-community-post-stats span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.sg-community-post-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    padding: 14px 0 34px;
}

.sg-community-post-actions button {
    display: inline-flex;
    min-height: 42px;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #53675a;
    font-weight: 700;
    cursor: pointer;
}

.sg-community-post-actions button:hover {
    background: #eef7f1;
    color: #087f3e;
}

.sg-community-post-actions button.active {
    color: #078542;
}

.sg-community-comments {
    padding-top: 30px;
    border-top: 1px solid #d7e5da;
}

.sg-community-comments-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 20px;
}

.sg-community-comments-heading h2 {
    margin: 0;
    font-family: "Outfit", "Inter", sans-serif;
    font-size: 25px;
}

.sg-community-comments-heading > span {
    color: #718077;
    font-size: 13px;
}

.sg-community-comment-form {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.sg-community-comment-avatar {
    width: 38px;
    height: 38px;
    font-size: 12px;
}

.sg-community-comment-input {
    position: relative;
    flex: 1;
}

.sg-community-comment-input textarea {
    width: 100%;
    min-height: 76px;
    resize: vertical;
    border: 1px solid #cfe0d3;
    border-radius: 6px;
    background: #f9fcfa;
    color: #14241a;
    font: inherit;
    line-height: 1.5;
    padding: 12px 48px 12px 14px;
}

.sg-community-comment-input textarea:focus {
    border-color: #0aa052;
    outline: 3px solid rgba(10, 160, 82, 0.12);
}

.sg-community-comment-input button {
    position: absolute;
    right: 9px;
    bottom: 9px;
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border: 0;
    border-radius: 6px;
    background: #079447;
    color: #fff;
    cursor: pointer;
}

.sg-community-comment-input button:disabled {
    background: #b8c9bd;
    cursor: not-allowed;
}

.sg-community-comment-input a {
    position: absolute;
    right: 12px;
    bottom: 12px;
    color: #087f3e;
    font-size: 13px;
    font-weight: 800;
}

.sg-community-comment-list {
    display: grid;
    gap: 22px;
    margin-top: 28px;
}

.sg-community-comment {
    display: flex;
    gap: 12px;
}

.sg-community-comment-copy {
    min-width: 0;
    flex: 1;
    padding-bottom: 20px;
    border-bottom: 1px solid #edf2ee;
}

.sg-community-comment-topline {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sg-community-comment-topline strong {
    color: #1c3022;
    font-size: 14px;
}

.sg-community-comment-topline span {
    color: #869389;
    font-size: 12px;
}

.sg-community-comment-topline button {
    display: grid;
    width: 28px;
    height: 28px;
    margin-left: auto;
    place-items: center;
    border: 0;
    background: transparent;
    color: #89978d;
    cursor: pointer;
}

.sg-community-comment-copy > p {
    margin: 8px 0 0;
    color: #3f5245;
    line-height: 1.6;
}

.sg-community-replies {
    display: grid;
    gap: 8px;
    margin-top: 14px;
    padding-left: 16px;
    border-left: 2px solid #d9eadf;
}

.sg-community-replies div {
    display: grid;
    gap: 3px;
    color: #53675a;
    font-size: 13px;
}

.sg-community-comments-empty {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 28px;
    padding: 20px 0;
    color: #75857a;
}

.sg-community-comments-empty p {
    margin: 0;
}

.sg-community-detail-aside {
    position: sticky;
    top: 102px;
    padding: 6px 0 0 28px;
    border-left: 2px solid #0aa052;
}

.sg-community-detail-aside h2 {
    margin: 0;
    font-family: "Outfit", "Inter", sans-serif;
    font-size: 28px;
    line-height: 1.12;
}

.sg-community-detail-aside > p:not(.sg-community-eyebrow) {
    margin: 16px 0 22px;
    color: #607166;
    font-size: 14px;
    line-height: 1.7;
}

.sg-community-detail-aside a {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: #087f3e;
    font-weight: 800;
    text-decoration: none;
}

.sg-community-detail-state {
    display: grid;
    min-height: 420px;
    place-items: center;
    align-content: center;
    gap: 12px;
    border: 1px solid #d7e5da;
    border-radius: 8px;
    background: #fff;
    color: #6b7c71;
    text-align: center;
}

.sg-community-detail-state h1,
.sg-community-detail-state p {
    margin: 0;
}

.sg-community-primary-btn {
    display: inline-flex;
    min-height: 42px;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    border: 0;
    border-radius: 6px;
    background: #079447;
    color: #fff;
    font-weight: 800;
    padding: 0 18px;
    cursor: pointer;
}

.sg-community-detail-skeleton {
    display: grid;
    grid-template-columns: 48px 1fr;
    gap: 14px;
    width: min(720px, calc(100% - 48px));
}

.skeleton-avatar,
.skeleton-line,
.skeleton-block {
    display: block;
    border-radius: 5px;
    background: linear-gradient(90deg, #edf3ef 25%, #f8fbf9 50%, #edf3ef 75%);
    background-size: 200% 100%;
    animation: skeleton-shift 1.4s ease-in-out infinite;
}

.skeleton-avatar {
    grid-row: span 2;
    width: 48px;
    height: 48px;
    border-radius: 50%;
}

.skeleton-line {
    height: 14px;
}

.skeleton-line--short {
    width: 42%;
}

.skeleton-block {
    grid-column: 1 / -1;
    height: 230px;
    margin-top: 18px;
}

.sg-community-inline-error {
    margin: 10px 0 0 50px;
    color: #b33a3a;
    font-size: 13px;
}

.sg-community-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 1200;
    max-width: min(380px, calc(100% - 32px));
    border-radius: 6px;
    background: #173522;
    color: #fff;
    padding: 13px 18px;
    box-shadow: 0 14px 34px rgba(13, 43, 25, 0.2);
    font-weight: 700;
}

.spin {
    animation: spin 0.8s linear infinite;
}

@keyframes skeleton-shift {
    to {
        background-position: -200% 0;
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 900px) {
    .sg-community-detail-layout {
        grid-template-columns: 1fr;
    }

    .sg-community-detail-aside {
        position: static;
        padding: 0 0 0 22px;
    }
}

@media (max-width: 640px) {
    .sg-community-detail-main {
        width: min(100% - 24px, 1180px);
        padding-top: 88px;
    }

    .sg-community-post-detail {
        padding: 24px 18px 32px;
    }

    .sg-community-post-header {
        grid-template-columns: 42px minmax(0, 1fr) 36px;
        gap: 10px;
    }

    .sg-community-author-avatar {
        width: 42px;
        height: 42px;
    }

    .sg-community-post-body {
        padding-top: 30px;
    }

    .sg-community-post-body h1 {
        font-size: 31px;
    }

    .sg-community-post-content {
        font-size: 16px;
    }

    .sg-community-post-stats {
        gap: 12px 18px;
    }

    .sg-community-post-actions button {
        min-width: 0;
        font-size: 13px;
    }

    .sg-community-comments-heading {
        align-items: start;
    }
}
</style>
