<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 pb-20">
    <PublicNavbar />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="relative flex flex-col md:flex-row md:items-center justify-between gap-6 overflow-hidden p-8 md:p-10 border border-slate-800 rounded-2xl bg-slate-900 shadow-xl mb-8">
        <div class="absolute top-0 inset-x-0 h-1 bg-emerald-500"></div>
        <div class="max-w-2xl relative z-10">
          <span class="block mb-2 text-xs font-extrabold uppercase tracking-wide text-emerald-400">Cộng đồng SportGo</span>
          <h1 class="text-3xl md:text-4xl font-bold text-white leading-tight">Cùng chơi, cùng chia sẻ</h1>
          <p class="mt-3 text-slate-300 text-base md:text-lg leading-relaxed">Theo dõi câu chuyện thể thao, trao đổi kinh nghiệm và tìm đồng đội cho trận đấu tiếp theo.</p>
        </div>
        <button v-if="!user" type="button" class="relative z-10 inline-flex min-h-[44px] items-center gap-2 px-5 text-sm font-bold text-slate-900 bg-emerald-400 border border-transparent rounded-lg hover:bg-emerald-300 hover:shadow-md transition-all whitespace-nowrap" @click="goToLogin">
          Đăng nhập để tham gia
          <AppIcon name="chevronRight" class="w-4 h-4" />
        </button>
      </header>

      <nav class="community-section-nav" aria-label="Khu vực cộng đồng">
        <router-link to="/community" exact-active-class="is-active">Bảng tin</router-link>
        <router-link to="/community/matchmaking" active-class="is-active"><AppIcon name="users" class="w-4 h-4" /> Tuyển giao lưu</router-link>
      </nav>

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8 items-start">
        <section class="min-w-0 space-y-6" aria-label="Bảng tin cộng đồng">
          <article v-if="canCreateCommunityPost" class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <div class="flex items-center gap-3">
              <span class="flex-none grid place-items-center w-10 h-10 rounded-full bg-emerald-600 text-white text-sm font-bold overflow-hidden">
                {{ initial(user?.fullName) }}
              </span>
              <button type="button" class="flex-1 min-h-[44px] px-4 text-sm text-left text-slate-500 bg-slate-50 border border-slate-200 rounded-lg hover:bg-emerald-50 hover:text-slate-700 hover:border-emerald-200 transition-colors" @click="showCommunityModal = true">
                Bạn muốn chia sẻ điều gì với cộng đồng?
              </button>
            </div>
            <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100">
              <button type="button" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-slate-700 rounded-lg hover:bg-emerald-50 hover:text-emerald-700 transition-colors" @click="showCommunityModal = true">
                <AppIcon name="edit" class="w-4 h-4" />
                Bài chia sẻ
              </button>
              <button v-if="isPlayer" type="button" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-slate-700 rounded-lg hover:bg-emerald-50 hover:text-emerald-700 transition-colors" @click="showMeetupModal = true">
                <AppIcon name="users" class="w-4 h-4" />
                Tìm người chơi cùng
              </button>
            </div>
          </article>

          <section class="p-5 bg-white border-l-4 border-emerald-600 border-y border-r border-slate-200 rounded-xl shadow-sm mb-2" aria-label="Tiêu đề bảng tin">
            <div>
              <span class="block text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Dành cho bạn</span>
              <h2 class="text-lg font-bold text-slate-900">Bảng tin mới nhất</h2>
            </div>
          </section>

          <div v-if="loading" class="grid place-items-center min-h-[250px] p-8 text-slate-500 border border-dashed border-slate-300 rounded-xl bg-white text-center" aria-live="polite">
            <span class="w-8 h-8 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin mb-3" aria-hidden="true"></span>
            <p class="text-sm">Đang tải bảng tin...</p>
          </div>
          <div v-else-if="error" class="grid place-items-center min-h-[250px] p-8 text-red-600 border border-dashed border-red-300 rounded-xl bg-red-50 text-center" role="alert">
            <AppIcon name="alert" class="w-8 h-8 mb-2" />
            <strong class="text-red-800">Không thể tải bảng tin</strong>
            <p class="text-sm mt-1 mb-4">{{ error }}</p>
            <button type="button" class="px-4 py-2 text-sm font-bold text-white bg-red-600 rounded-lg hover:bg-red-700" @click="fetchPosts({ page: 1 })">Thử lại</button>
          </div>
          <div v-else-if="!posts.length" class="grid place-items-center min-h-[250px] p-8 text-slate-500 border border-dashed border-slate-300 rounded-xl bg-white text-center">
            <AppIcon name="newspaper" class="w-8 h-8 mb-2" />
            <strong class="text-slate-700">Chưa có bài viết phù hợp</strong>
            <p class="text-sm mt-1">Hãy đổi chủ đề hoặc từ khóa để xem thêm nội dung.</p>
          </div>

          <div v-else class="space-y-4">
            <article v-for="post in posts" :key="post.id" class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md hover:border-emerald-200 transition-all duration-200">
              <header class="flex items-center justify-between gap-3 p-5 pb-3">
                <button type="button" class="flex flex-1 items-center gap-3 text-left" @click="goToUser(post.author?.id)">
                  <span class="flex-none grid place-items-center w-10 h-10 rounded-full bg-emerald-600 text-white text-sm font-bold overflow-hidden">
                    <img
                      v-if="post.author?.avatar_url"
                      :src="assetUrl(post.author.avatar_url)"
                      :alt="post.author.full_name || post.author.username"
                      class="w-full h-full object-cover"
                    />
                    <span v-else>{{ initial(post.author?.full_name || post.author?.username) }}</span>
                  </span>
                  <span class="grid gap-0.5">
                    <strong class="flex items-center gap-1.5 text-sm font-bold text-slate-900">
                      {{ post.author?.full_name || post.author?.username || 'Thành viên SportGo' }}
                      <ClientAuthorBadges :badges="post.author_badges" />
                    </strong>
                    <small class="text-xs text-slate-500">
                      {{ timeAgo(post.published_at || post.created_at) }}
                      <template v-if="post.venue_cluster?.name"> · {{ post.venue_cluster.name }}</template>
                    </small>
                  </span>
                </button>

                <div class="relative">
                  <button
                    type="button"
                    class="grid place-items-center w-8 h-8 text-slate-500 rounded-full hover:bg-slate-100 transition-colors"
                    :aria-expanded="openMenuPostId === post.id"
                    aria-label="Tùy chọn bài viết"
                    @click.stop="togglePostMenu(post.id)"
                  >
                    <AppIcon name="moreHorizontal" class="w-5 h-5" />
                  </button>
                  <div v-if="openMenuPostId === post.id" class="absolute z-20 top-full right-0 mt-1 w-48 p-1.5 bg-white border border-slate-200 rounded-lg shadow-lg" role="menu" @click.stop>
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-50" role="menuitem" @click="openReport(post)">
                      <AppIcon name="alert" class="w-4 h-4" />
                      Báo cáo bài viết
                    </button>
                  </div>
                </div>
              </header>

              <div class="px-5 pb-3">
                <div v-if="post.hashtags?.length" class="flex flex-wrap gap-2 mb-2">
                  <span v-for="tag in post.hashtags.slice(0, 3)" :key="tag.id || tag.name" class="text-xs font-bold text-emerald-700">#{{ tag.name }}</span>
                </div>
                <button type="button" class="grid gap-1 text-left w-full" @click="goToDetail(post.slug || post.id)">
                  <strong v-if="post.title && !titleRepeatsContent(post)" class="text-base text-slate-900 font-bold leading-snug">{{ post.title }}</strong>
                  <span class="text-sm text-slate-700 line-clamp-4 leading-relaxed">{{ plainText(post.content || post.short_description) }}</span>
                </button>
              </div>

              <button
                v-if="postMedia(post).length === 1"
                type="button"
                class="block w-full aspect-[16/10] bg-slate-100 overflow-hidden"
                @click="goToDetail(post.slug || post.id)"
              >
                <img :src="postMedia(post)[0]" :alt="post.title || 'Ảnh bài viết'" class="w-full h-full object-cover" @error="handlePostImageError" />
              </button>
              <button
                v-else-if="postMedia(post).length > 1"
                type="button"
                :class="['grid w-full bg-slate-100 overflow-hidden gap-0.5', postMedia(post).length === 2 ? 'grid-cols-2 aspect-[16/8]' : 'grid-cols-2 aspect-[16/10]']"
                @click="goToDetail(post.slug || post.id)"
              >
                <span v-for="(image, imageIndex) in postMedia(post).slice(0, 4)" :key="`${post.id}-${imageIndex}`" class="relative block w-full h-full min-h-[150px]">
                  <img :src="image" :alt="`${post.title || 'Ảnh bài viết'} ${imageIndex + 1}`" class="w-full h-full object-cover absolute inset-0" @error="handlePostImageError" />
                  <b v-if="imageIndex === 3 && postMedia(post).length > 4" class="absolute inset-0 grid place-items-center text-white text-2xl bg-black/60">+{{ postMedia(post).length - 4 }}</b>
                </span>
              </button>

              <div class="flex items-center gap-4 px-5 py-3 text-xs font-medium text-slate-500 border-b border-slate-100">
                <span class="flex items-center gap-1.5"><AppIcon name="heart" class="w-4 h-4" /> {{ post.like_count || 0 }}</span>
                <button type="button" class="ml-auto hover:text-slate-800 transition-colors cursor-pointer" @click="toggleComments(post)">{{ post.comment_count || 0 }} bình luận</button>
                <span>{{ post.view_count || 0 }} lượt xem</span>
              </div>

              <div class="grid grid-cols-3 gap-1 p-2">
                <button
                  type="button"
                  :class="['inline-flex items-center justify-center gap-2 py-2 text-sm font-semibold rounded-lg transition-colors cursor-pointer', post.is_liked ? 'text-emerald-700 bg-emerald-50' : 'text-slate-600 hover:bg-emerald-50 hover:text-emerald-700', {'opacity-50 cursor-not-allowed': likingPostIds.has(post.id) || !post.likes_available}]"
                  :disabled="likingPostIds.has(post.id) || !post.likes_available"
                  :title="post.likes_available ? '' : 'Lượt thích của bài cụm sân đang chờ cập nhật dữ liệu hệ thống'"
                  @click="toggleLike(post)"
                >
                  <AppIcon name="heart" class="w-4 h-4" />
                  {{ post.is_liked ? 'Đã thích' : 'Thích' }}
                </button>
                <button type="button" :class="['inline-flex items-center justify-center gap-2 py-2 text-sm font-semibold rounded-lg cursor-pointer transition-colors', commentsOpen[post.id] ? 'text-emerald-700 bg-emerald-50' : 'text-slate-600 hover:bg-emerald-50 hover:text-emerald-700']" @click="toggleComments(post)">
                  <AppIcon name="messageCircle" class="w-4 h-4" />
                  Bình luận
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-2 py-2 text-sm font-semibold cursor-pointer text-slate-600 rounded-lg hover:bg-emerald-50 hover:text-emerald-700 transition-colors" @click="sharePost(post)">
                  <AppIcon name="share" class="w-4 h-4" />
                  Chia sẻ
                </button>
              </div>

              <section v-if="commentsOpen[post.id]" class="p-5 bg-slate-50 border-t border-slate-100" aria-label="Bình luận bài viết">
                <div v-if="detailsLoading[post.id]" class="flex items-center justify-center gap-2 min-h-[72px] text-xs text-slate-500">
                  <span class="w-4 h-4 border-2 border-emerald-200 border-t-emerald-600 rounded-full animate-spin" aria-hidden="true"></span>
                  Đang tải bình luận...
                </div>
                <template v-else>
                  <div v-if="post.top_level_comments?.length" class="space-y-3 mb-4">
                    <article v-for="comment in visibleComments(post)" :key="comment.id" class="grid grid-cols-[32px_1fr] items-start gap-3">
                      <span class="grid place-items-center w-8 h-8 rounded-full bg-emerald-600 text-white text-[10px] font-bold overflow-hidden">
                        <img
                          v-if="comment.user?.avatar_url"
                          :src="assetUrl(comment.user.avatar_url)"
                          :alt="comment.user.full_name || comment.user.username"
                          class="w-full h-full object-cover"
                        />
                        <span v-else>{{ initial(comment.user?.full_name || comment.user?.username) }}</span>
                      </span>
                      <div>
                        <div class="inline-grid gap-1 px-3 py-2 bg-white border border-slate-200 rounded-xl max-w-full">
                          <strong class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                            {{ comment.user?.full_name || comment.user?.username || 'Thành viên SportGo' }}
                            <ClientAuthorBadges :badges="comment.user?.author_badges" />
                          </strong>
                          <p class="text-sm text-slate-700 whitespace-pre-wrap break-words">{{ comment.content }}</p>
                        </div>
                        <div class="flex items-center gap-3 mt-1 ml-2">
                          <small class="text-xs text-slate-500">{{ timeAgo(comment.created_at) }}</small>
                          <button type="button" class="text-xs font-bold text-slate-500 hover:text-emerald-700 cursor-pointer" @click="setReply(post, comment)">Trả lời</button>
                        </div>
                        <div v-if="comment.replies?.length" class="mt-3 space-y-3">
                          <article v-for="reply in comment.replies" :key="reply.id" class="grid grid-cols-[28px_1fr] items-start gap-2">
                            <span class="grid place-items-center w-7 h-7 rounded-full bg-emerald-600 text-white text-[9px] font-bold overflow-hidden">
                              <img
                                v-if="reply.user?.avatar_url"
                                :src="assetUrl(reply.user.avatar_url)"
                                :alt="reply.user.full_name || reply.user.username"
                                class="w-full h-full object-cover"
                              />
                              <span v-else>{{ initial(reply.user?.full_name || reply.user?.username) }}</span>
                            </span>
                            <div>
                              <div class="inline-grid gap-1 px-3 py-2 bg-white border border-slate-200 rounded-xl max-w-full">
                                <strong class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                                  {{ reply.user?.full_name || reply.user?.username || 'Thành viên SportGo' }}
                                  <ClientAuthorBadges :badges="reply.user?.author_badges" />
                                </strong>
                                <p class="text-sm text-slate-700 whitespace-pre-wrap break-words" v-html="formatMention(reply.content)"></p>
                              </div>
                              <div class="flex items-center gap-3 mt-1 ml-2">
                                <small class="text-xs text-slate-500">{{ timeAgo(reply.created_at) }}</small>
                                <button type="button" class="text-xs font-bold text-slate-500 hover:text-emerald-700 cursor-pointer" @click="setReply(post, comment, reply)">Trả lời</button>
                              </div>
                            </div>
                          </article>
                        </div>
                      </div>
                    </article>
                    <button
                      v-if="post.top_level_comments.length > commentPreviewLimit && !showAllComments[post.id]"
                      type="button"
                      class="px-2 py-1 text-xs font-bold text-emerald-700 hover:underline"
                      @click="showAllComments[post.id] = true"
                    >
                      Xem thêm {{ post.top_level_comments.length - commentPreviewLimit }} bình luận
                    </button>
                  </div>
                  <p v-else class="flex justify-center py-4 text-xs text-slate-500">Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.</p>

                  <form v-if="user" class="mt-4" @submit.prevent="submitComment(post)">
                    <div v-if="replyingTo[post.id]" class="flex items-center justify-between px-3 py-1.5 mb-2 text-xs bg-slate-200 text-slate-700 rounded-md">
                      <span>Đang trả lời <strong>{{ replyingTo[post.id].user?.full_name || replyingTo[post.id].user?.username || 'Thành viên SportGo' }}</strong></span>
                      <button type="button" class="hover:text-red-600" aria-label="Hủy trả lời" @click="replyingTo[post.id] = null"><AppIcon name="x" class="w-3 h-3" /></button>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="flex-none grid place-items-center w-8 h-8 rounded-full bg-emerald-600 text-white text-[10px] font-bold">{{ initial(user.fullName) }}</span>
                      <label class="flex-1 min-w-0">
                        <span class="sr-only">Viết bình luận</span>
                        <input
                          :id="`comment-input-${post.id}`"
                          v-model.trim="commentDrafts[post.id]"
                          type="text"
                          maxlength="1000"
                          class="w-full h-9 px-3 text-sm text-slate-900 bg-white border border-slate-300 rounded-full focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 placeholder:text-slate-400"
                          :placeholder="replyingTo[post.id] ? `Phản hồi ${replyingTo[post.id].user?.full_name || replyingTo[post.id].user?.username || 'Thành viên SportGo'}...` : 'Viết bình luận...'"
                          :disabled="commentSubmitting[post.id]"
                        />
                      </label>
                      <button
                        type="submit"
                        class="flex-none grid place-items-center w-9 h-9 text-white bg-emerald-600 hover:bg-emerald-700 rounded-full disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        aria-label="Gửi bình luận"
                        :disabled="commentSubmitting[post.id] || !commentDrafts[post.id]?.trim()"
                      >
                        <AppIcon name="send" class="w-4 h-4 ml-[-2px]" />
                      </button>
                    </div>
                  </form>
                  <button v-else type="button" class="block w-full py-2 text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg text-center transition-colors" @click="goToLogin">
                    Đăng nhập để bình luận
                  </button>
                </template>
              </section>
            </article>

            <button
              v-if="pagination.current_page < pagination.last_page"
              type="button"
              class="flex items-center justify-center w-full min-h-[44px] gap-2 px-4 py-2 mt-4 text-sm font-bold text-emerald-800 bg-white border border-emerald-200 rounded-xl shadow-sm hover:bg-emerald-50 hover:border-emerald-300 transition-all"
              :disabled="loadingMore"
              @click="loadMorePosts"
            >
              <span v-if="loadingMore" class="w-4 h-4 border-2 border-emerald-200 border-t-emerald-700 rounded-full animate-spin" aria-hidden="true"></span>
              {{ loadingMore ? 'Đang tải thêm...' : 'Xem thêm bài viết' }}
            </button>
            <p v-else class="text-center py-4 text-sm text-slate-500">Bạn đã xem hết các bài viết hiện có.</p>
          </div>
        </section>

        <aside class="sticky top-24 space-y-6" aria-label="Khám phá cộng đồng">
          <section class="hidden lg:block bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Khám phá</h2>
            <form class="mb-4" @submit.prevent="applyFilters">
              <div class="relative w-full">
                <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
                <input v-model.trim="searchQuery" type="text" placeholder="Tìm trong cộng đồng" aria-label="Tìm trong cộng đồng" class="w-full h-10 !pl-10 !pr-14 text-sm text-slate-900 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 placeholder:text-slate-400" />
                <button type="submit" class="absolute right-1 top-1 bottom-1 px-3 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-md transition-colors">Tìm</button>
              </div>
            </form>
            <div class="flex flex-wrap gap-2" aria-label="Chủ đề bài viết">
              <button type="button" :class="['px-3 py-1 text-xs font-semibold rounded-full border transition-colors', !selectedCategory ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800']" @click="setCategory('')">Tất cả</button>
              <button
                v-for="category in categories"
                :key="category"
                type="button"
                :class="['px-3 py-1 text-xs font-semibold rounded-full border transition-colors', selectedCategory === category ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800']"
                @click="setCategory(category)"
              >
                {{ category }}
              </button>
              <button
                v-if="searchQuery || selectedCategory"
                type="button"
                class="px-3 py-1 text-xs font-semibold rounded-full border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 transition-colors"
                @click="clearFilters"
              >
                Xóa lọc
              </button>
            </div>
          </section>

          <section class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <header class="flex items-start justify-between mb-4">
              <div>
                <span class="block text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Ghép kèo</span>
                <h2 class="text-lg font-bold text-slate-900">Kèo sắp tới</h2>
              </div>
              <button v-if="isPlayer" type="button" class="grid place-items-center w-8 h-8 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors" aria-label="Tạo bài giao lưu" @click="showMeetupModal = true">
                <AppIcon name="plus" class="w-4 h-4" />
              </button>
            </header>

            <div v-if="matchmakingLoading" class="flex items-center justify-center gap-2 py-6 text-sm text-slate-500">
              <span class="w-4 h-4 border-2 border-emerald-200 border-t-emerald-600 rounded-full animate-spin" aria-hidden="true"></span>
              Đang tải kèo...
            </div>
            <div v-else-if="matchmakingError" class="p-4 text-center bg-red-50 border border-dashed border-red-200 rounded-lg text-red-600" role="alert">
              <AppIcon name="alert" class="w-6 h-6 mx-auto mb-2" />
              <p class="text-sm mb-3">{{ matchmakingError }}</p>
              <button type="button" class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors" @click="fetchMatchmakingPosts">Tải lại</button>
            </div>
            <div v-else-if="matchmakingPosts.length" class="space-y-3">
              <article v-for="post in matchmakingPosts" :key="post.id" class="p-4 bg-slate-50 border border-slate-200 rounded-xl hover:border-emerald-300 transition-colors">
                <header class="flex items-start justify-between gap-2 mb-3">
                  <button type="button" class="flex items-center gap-2 text-left" @click="goToUser(post.author?.id)">
                    <span class="grid place-items-center w-8 h-8 rounded-full bg-emerald-600 text-white text-xs font-bold overflow-hidden">
                      <img v-if="post.author?.avatar" :src="assetUrl(post.author.avatar)" :alt="post.author.name" class="w-full h-full object-cover" />
                      <span v-else>{{ initial(post.author?.name) }}</span>
                    </span>
                    <span class="grid gap-0.5">
                      <strong class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                        {{ post.author?.name || 'Người chơi SportGo' }}
                        <ClientAuthorBadges :badges="post.author?.author_badges" />
                      </strong>
                      <small class="text-[10px] text-slate-500">{{ timeAgo(post.created_at) }}</small>
                    </span>
                  </button>
                  <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold text-amber-800 bg-amber-100 rounded border border-amber-200">Cần {{ post.needed_players }}</span>
                </header>
                <div class="space-y-1 mb-3 text-xs text-slate-600">
                  <span class="flex items-center gap-1.5"><AppIcon name="mapPin" class="w-3.5 h-3.5 text-slate-400" /> <span class="truncate">{{ post.booking?.venue_name || 'Cụm sân' }}</span></span>
                  <span class="flex items-center gap-1.5"><AppIcon name="clock" class="w-3.5 h-3.5 text-slate-400" /> {{ formatDate(post.booking?.date) }} · {{ post.booking?.time }}</span>
                </div>
                <p v-if="post.description" class="text-sm text-slate-700 line-clamp-2 mb-3">{{ post.description }}</p>
                <button
                  v-if="!isOwnPost(post)"
                  type="button"
                  class="w-full py-2 text-sm font-bold rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                  :class="post.user_status ? 'bg-slate-200 text-slate-600' : 'bg-emerald-600 hover:bg-emerald-700 text-white'"
                  :disabled="joiningPostId === post.id || Boolean(post.user_status)"
                  @click="joinMatchmaking(post)"
                >
                  {{ joinLabel(post) }}
                </button>
                <router-link v-else class="flex justify-center w-full py-2 text-sm font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 rounded-lg transition-colors" :to="`/matchmaking-posts/${post.id}/manage`">Quản lý yêu cầu</router-link>
              </article>
            </div>
            <div v-else class="py-8 text-center bg-slate-50 border border-dashed border-slate-200 rounded-xl">
              <AppIcon name="users" class="w-8 h-8 mx-auto mb-2 text-slate-400" />
              <p class="text-sm text-slate-600 mb-3">Chưa có kèo công khai sắp tới.</p>
              <button v-if="isPlayer" type="button" class="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors" @click="showMeetupModal = true">Tạo kèo đầu tiên</button>
            </div>
          </section>
        </aside>
      </div>
    </main>

    <CommunityPostModal
      :is-open="showCommunityModal"
      @close="showCommunityModal = false"
      @success="handleCommunityPostCreated"
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
  </div>
</template>


<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import ClientAuthorBadges from '@/components/ClientAuthorBadges.vue';
import CommunityPostModal from '@/components/CommunityPostModal.vue';
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
const posts = ref([]);
const loading = ref(true);
const loadingMore = ref(false);
const error = ref('');
const searchQuery = ref(String(route.query.q || ''));
const selectedCategory = ref(String(route.query.category || ''));
const showMobileFilters = ref(false);
const showCommunityModal = ref(false);
const showMeetupModal = ref(false);
const openMenuPostId = ref(null);
const reportTarget = ref(null);
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
  fetchPosts({ page: 1 });
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
  fetchPosts({ page: pagination.value.current_page + 1, append: true });
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
  return String(user?.id || '') === String(post.author?.id || '');
}

function handleCommunityPostCreated(response) {
  showCommunityModal.value = false;
  if (response?.data?.status === 'published') {
    toast.success('Bài viết đã được đăng.');
    fetchPosts({ page: 1 });
    return;
  }
  toast.success(response?.message || 'Bài viết đã được gửi và đang chờ kiểm duyệt.');
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
  fetchPosts();
  fetchMatchmakingPosts();
  document.addEventListener('click', closePostMenu);
});

onBeforeUnmount(() => document.removeEventListener('click', closePostMenu));
</script>
