<template>
  <div class="cc-page">
    <PublicNavbar />

    <main class="cc-main">
      <div class="cc-container" :class="{ 'cc-container--with-info': showVenueSidebar && activeConversation && !activeConversation.is_ai }">
        <!-- LEFT SIDEBAR: CONVERSATIONS LIST -->
        <aside
          :class="[
            'cc-sidebar',
            mobileActiveView === 'chat' ? 'cc-sidebar--hidden-mobile' : ''
          ]"
        >
          <!-- SIDEBAR HEADER -->
          <div class="cc-sidebar-head">
            <div class="cc-sidebar-title-row">
              <h1 class="cc-sidebar-title">Hộp thư hỗ trợ</h1>
              <div class="cc-sidebar-actions">
                <button
                  type="button"
                  class="cc-icon-btn"
                  title="Tạo nhóm chat mới"
                  @click="openCreateGroupModal"
                >
                  <AppIcon name="userPlus" size="15" />
                </button>
                <button
                  type="button"
                  class="cc-icon-btn"
                  title="Mở tin nhắn đã lưu (Ghi chú)"
                  @click="openSavedMessages"
                >
                  <AppIcon name="bookmark" size="15" />
                </button>
                <button
                  type="button"
                  class="cc-icon-btn"
                  title="Làm mới danh sách"
                  @click="fetchConversations"
                >
                  <AppIcon name="refreshCw" size="14" />
                </button>
              </div>
            </div>

            <!-- SEARCH INPUT -->
            <div class="cc-search-box">
              <AppIcon name="search" size="14" class="cc-search-icon" />
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Tìm trò chuyện, cụm sân, người dùng..."
                class="cc-search-input"
                @input="handleGlobalSearch"
              />
              <button
                v-if="searchQuery"
                type="button"
                class="cc-search-clear"
                @click="searchQuery = ''; userSearchResults = []"
              >
                ✕
              </button>
            </div>

            <!-- FILTER TABS -->
            <div class="cc-filter-tabs">
              <button
                type="button"
                :class="['cc-tab', activeTab === 'all' ? 'active' : '']"
                @click="activeTab = 'all'"
              >
                Tất cả
              </button>
              <button
                type="button"
                :class="['cc-tab', activeTab === 'ai' ? 'active' : '']"
                @click="activeTab = 'ai'"
              >
                Trợ lý AI
              </button>
              <button
                type="button"
                :class="['cc-tab', activeTab === 'venue' ? 'active' : '']"
                @click="activeTab = 'venue'"
              >
                Sân đấu
              </button>
              <button
                type="button"
                :class="['cc-tab', activeTab === 'direct' ? 'active' : '']"
                @click="activeTab = 'direct'"
              >
                Cá nhân &amp; Nhóm
              </button>
            </div>
          </div>

          <!-- USER SEARCH RESULTS (When searching) -->
          <div v-if="searchQuery.trim().length >= 2 && userSearchResults.length > 0" class="cc-user-search-panel">
            <span class="cc-usp-title">KẾT NỐI NGƯỜI CHƠI MỚI</span>
            <button
              v-for="u in userSearchResults"
              :key="u.id"
              type="button"
              class="cc-usp-item"
              @click="startDirectChatWithUser(u)"
            >
              <div class="cc-avatar cc-avatar--sm" :style="{ backgroundColor: getAvatarColor(u.full_name || u.username) }">
                {{ getInitial(u.full_name || u.username) }}
              </div>
              <div class="cc-usp-info">
                <span class="cc-usp-name">{{ u.full_name || u.username }}</span>
                <span class="cc-usp-sub">{{ u.phone || u.email || 'Thành viên SportGo' }}</span>
              </div>
              <span class="cc-usp-btn">Nhắn tin</span>
            </button>
          </div>

          <!-- CONVERSATIONS SCROLL LIST -->
          <div class="cc-conv-list">
            <div v-if="loadingConversations" class="cc-state-msg">
              <span class="cc-spinner"></span>
              <span>Đang tải danh sách hộp thư...</span>
            </div>

            <div v-else-if="filteredConversations.length === 0" class="cc-state-msg">
              <svg width="44" height="44" viewBox="0 0 44 44" fill="none" class="cc-state-illustration">
                <circle cx="22" cy="22" r="20" fill="#f0fdf4" />
                <path d="M14 16h16a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3h-5l-4 4v-4h-7a3 3 0 0 1-3-3v-7a3 3 0 0 1 3-3z" stroke="#15803d" stroke-width="1.5" fill="#ffffff" />
                <line x1="18" y1="21" x2="26" y2="21" stroke="#86efac" stroke-width="1.5" stroke-linecap="round" />
              </svg>
              <span>Không tìm thấy cuộc trò chuyện nào.</span>
            </div>

            <button
              v-for="conv in filteredConversations"
              :key="conv.id"
              type="button"
              :class="[
                'cc-conv-item',
                activeConversation?.id === conv.id ? 'active' : ''
              ]"
              @click="selectConversation(conv)"
            >
              <!-- AVATAR / ICON -->
              <div class="cc-avatar-wrap">
                <div v-if="conv.is_ai" class="cc-avatar cc-avatar--ai">
                  <AppIcon name="sparkles" size="18" />
                </div>
                <div v-else-if="conv.type === 'saved_messages' || (conv.type === 'direct' && !conv.other_user)" class="cc-avatar cc-avatar--saved">
                  <AppIcon name="bookmark" size="16" />
                </div>
                <div v-else-if="conv.avatar_url" class="cc-avatar">
                  <img :src="conv.avatar_url" class="cc-avatar-img" alt="Avatar" />
                </div>
                <div
                  v-else
                  class="cc-avatar"
                  :style="{ backgroundColor: getAvatarColor(conv.title) }"
                >
                  {{ getInitial(conv.title) }}
                </div>
                <span v-if="!conv.is_ai && isParticipantOnline(conv)" class="cc-online-dot"></span>
              </div>

              <!-- CONTENT PREVIEW -->
              <div class="cc-conv-info">
                <div class="cc-conv-top">
                  <span class="cc-conv-title">{{ conv.title }}</span>
                  <span class="cc-conv-time">{{ formatTime(conv.last_message?.created_at || conv.last_message_at) }}</span>
                </div>
                <div class="cc-conv-sub">
                  <span class="cc-conv-preview">{{ conv.last_message?.content || "Bắt đầu trò chuyện" }}</span>
                  <span v-if="conv.unread_count > 0" class="cc-unread-badge">{{ conv.unread_count }}</span>
                </div>
              </div>
            </button>
          </div>
        </aside>

        <!-- RIGHT CHAT WORKSPACE -->
        <section
          :class="[
            'cc-workspace',
            mobileActiveView === 'list' ? 'cc-workspace--hidden-mobile' : ''
          ]"
        >
          <!-- NO ACTIVE CONVERSATION -->
          <div v-if="!activeConversation" class="cc-empty-workspace">
            <svg class="cc-empty-illustration" width="160" height="120" viewBox="0 0 160 120" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="80" cy="60" r="50" fill="#f0fdf4" />
              <path d="M48 42h44a8 8 0 0 1 8 8v22a8 8 0 0 1-8 8H62l-14 10v-10h-0a8 8 0 0 1-8-8V50a8 8 0 0 1 8-8z" fill="#ffffff" stroke="#15803d" stroke-width="1.8" />
              <line x1="56" y1="54" x2="84" y2="54" stroke="#15803d" stroke-width="1.8" stroke-linecap="round" />
              <line x1="56" y1="62" x2="74" y2="62" stroke="#86efac" stroke-width="1.8" stroke-linecap="round" />
              <path d="M92 34h26a6 6 0 0 1 6 6v14a6 6 0 0 1-6 6h-6l-8 6v-6h-12a6 6 0 0 1-6-6V40a6 6 0 0 1 6-6z" fill="#15803d" />
              <circle cx="101" cy="47" r="1.5" fill="#ffffff" />
              <circle cx="106" cy="47" r="1.5" fill="#ffffff" />
              <circle cx="111" cy="47" r="1.5" fill="#ffffff" />
              <path d="M120 74l-6 12 12-6-6-6z" fill="#15803d" opacity="0.8" />
              <circle cx="114" cy="86" r="3" fill="#15803d" />
            </svg>
            <h2 class="cc-empty-title">Chọn cuộc trò chuyện</h2>
            <p class="cc-empty-desc">Chọn từ danh sách bên trái hoặc sử dụng Trợ lý AI để bắt đầu nhắn tin nhờ hỗ trợ.</p>
          </div>

          <!-- ACTIVE CHAT VIEW -->
          <div v-else class="cc-chat-box">
            <!-- CHAT HEADER -->
            <div class="cc-chat-head">
              <div class="cc-head-left">
                <button
                  type="button"
                  class="cc-back-btn"
                  @click="mobileActiveView = 'list'"
                >
                  ←
                </button>

                <div class="cc-head-user">
                  <div v-if="activeConversation.is_ai" class="cc-avatar cc-avatar--ai cc-avatar--sm">
                    <AppIcon name="sparkles" size="16" />
                  </div>
                  <div v-else-if="activeConversation.type === 'saved_messages' || (activeConversation.type === 'direct' && !activeConversation.other_user)" class="cc-avatar cc-avatar--saved cc-avatar--sm">
                    <AppIcon name="bookmark" size="14" />
                  </div>
                  <div v-else-if="activeConversation.avatar_url" class="cc-avatar cc-avatar--sm">
                    <img :src="activeConversation.avatar_url" class="cc-avatar-img" alt="Avatar" />
                  </div>
                  <div
                    v-else
                    class="cc-avatar cc-avatar--sm"
                    :style="{ backgroundColor: getAvatarColor(activeConversation.title) }"
                  >
                    {{ getInitial(activeConversation.title) }}
                  </div>

                  <div>
                    <h2 class="cc-head-name">{{ activeConversation.title }}</h2>
                    <span class="cc-head-status">
                      <template v-if="activeConversation.is_ai">
                        Trợ lý trí tuệ nhân tạo SportGo 24/7
                      </template>
                      <template v-else-if="activeConversation.type === 'saved_messages' || (activeConversation.type === 'direct' && !activeConversation.other_user)">
                        Không gian lưu trữ ghi chú cá nhân
                      </template>
                      <template v-else-if="isTyping">
                        <span class="cc-typing-text">Đang soạn tin nhắn...</span>
                      </template>
                      <template v-else-if="isParticipantOnline(activeConversation)">
                        <span class="cc-status-online">Trực tuyến</span>
                      </template>
                      <template v-else-if="activeConversation.type === 'venue_contact'">
                        Cụm sân thể thao
                      </template>
                      <template v-else-if="activeConversation.is_group || activeConversation.type === 'group' || activeConversation.type === 'player_post'">
                        Nhóm trò chuyện ({{ activeConversation.participants?.length || 'Nhiều' }} thành viên)
                      </template>
                      <template v-else>
                        Thành viên SportGo
                      </template>
                    </span>
                  </div>
                </div>
                <span v-if="activeConversation.type === 'venue_contact'" class="cc-venue-context">
                  <AppIcon name="mapPin" size="13" />
                  Cụm sân: {{ activeConversation.title }}
                </span>
              </div>

              <!-- HEADER ACTIONS -->
              <div class="cc-head-actions">
                <!-- Sound Toggle Button -->
                <button
                  type="button"
                  class="cc-icon-btn"
                  :title="isMuted ? 'Bật chuông thông báo' : 'Tắt chuông thông báo'"
                  @click="isMuted = !isMuted"
                >
                  <AppIcon :name="isMuted ? 'volumeX' : 'volume2'" size="15" />
                </button>

                <!-- Support / Share Booking Button (Venue contact only) -->
                <button
                  v-if="activeConversation.type === 'venue_contact'"
                  type="button"
                  class="cc-btn-ghost-sm"
                  title="Gửi đơn đặt sân & yêu cầu hỗ trợ"
                  @click="openBookingPicker"
                >
                  <AppIcon name="calendar" size="14" />
                  <span class="cc-hide-mobile">Hỗ trợ đặt sân</span>
                </button>

                <!-- Attached Booking link if direct from booking -->
                <router-link
                  v-if="attachedBooking"
                  :to="{ name: 'booking-detail', params: { id: attachedBooking.id } }"
                  class="cc-btn-ghost-sm"
                  title="Xem đơn đặt sân liên quan"
                >
                  <AppIcon name="fileText" size="14" />
                  <span>Đơn #{{ attachedBooking.booking_code }}</span>
                </router-link>

                <!-- Venue Info Sidebar Toggle (Venue contact only) -->
                <button
                  v-if="activeConversation.type === 'venue_contact'"
                  type="button"
                  class="cc-icon-btn"
                  :class="{ 'is-active': showVenueSidebar }"
                  title="Thông tin cụm sân"
                  @click="showVenueSidebar = !showVenueSidebar"
                >
                  <AppIcon name="info" size="16" />
                </button>

                <!-- Options Dropdown Menu -->
                <div class="cc-menu-wrap">
                  <button
                    type="button"
                    class="cc-icon-btn"
                    title="Tùy chọn cuộc trò chuyện"
                    @click="showChatActionsMenu = !showChatActionsMenu"
                  >
                    <AppIcon name="moreVertical" size="16" />
                  </button>

                  <div v-if="showChatActionsMenu" class="cc-dropdown-menu" @click.stop>
                    <button
                      v-if="activeConversation.is_group || activeConversation.type === 'group' || activeConversation.type === 'player_post'"
                      type="button"
                      class="cc-dropdown-item"
                      @click="openGroupInfoModal"
                    >
                      <AppIcon name="users" size="14" />
                      <span>Thông tin nhóm &amp; Thành viên</span>
                    </button>

                    <button
                      v-if="(activeConversation.is_group || activeConversation.type === 'group' || activeConversation.type === 'player_post') && String(activeConversation.created_by) !== String(currentUser?.id) && activeConversation.is_active !== false"
                      type="button"
                      class="cc-dropdown-item cc-dropdown-item--danger"
                      @click="leaveGroupConversation"
                    >
                      <AppIcon name="logOut" size="14" />
                      <span>Rời nhóm trò chuyện</span>
                    </button>

                    <button
                      v-if="isMatchmakingGroup && String(activeConversation.created_by) === String(currentUser?.id)"
                      type="button"
                      class="cc-dropdown-item cc-dropdown-item--danger"
                      @click="dissolveMatchmakingGroup"
                    >
                      <AppIcon name="trash" size="14" />
                      <span>Giải tán nhóm giao lưu</span>
                    </button>

                    <button
                      v-if="!activeConversation.is_ai"
                      type="button"
                      class="cc-dropdown-item cc-dropdown-item--danger"
                      @click="clearChatHistory"
                    >
                      <AppIcon name="trash" size="14" />
                      <span>Xóa lịch sử tin nhắn</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- PINNED MESSAGE BANNER -->
            <div v-if="pinnedMessage" class="cc-pinned-banner">
              <div class="cc-pinned-info" @click="scrollToMessage(pinnedMessage.id)">
                <AppIcon name="pin" size="13" class="cc-pinned-icon" />
                <div class="cc-pinned-text">
                  <span class="cc-pinned-author">Tin nhắn đã ghim:</span>
                  <span class="cc-pinned-snippet">{{ pinnedMessage.content || "[Hình ảnh]" }}</span>
                </div>
              </div>
              <button
                type="button"
                class="cc-pinned-close"
                title="Bỏ ghim tin nhắn"
                @click="togglePin(pinnedMessage)"
              >
                ✕
              </button>
            </div>

            <!-- MESSAGES FEED -->
            <div ref="messagesFeed" class="cc-messages-feed" @click="activeMsgMenuId = null; showChatActionsMenu = false; showEmojiPicker = false">
              <!-- ATTACHED BOOKING INFO CARD -->
              <div v-if="attachedBooking" class="cc-attached-card">
                <div class="cc-att-head">
                  <AppIcon name="calendar" size="15" />
                  <span>Đơn đặt sân đang mở hỗ trợ #{{ attachedBooking.booking_code }}</span>
                </div>
                <div class="cc-att-body">
                  <span class="cc-att-name">{{ attachedBooking.venueCluster?.name || attachedBooking.venue_court?.name || "Sân thể thao" }}</span>
                  <p class="cc-att-sub">Ngày {{ formatDate(attachedBooking.booking_date) }} ({{ formatTime(attachedBooking.start_time) }} - {{ formatTime(attachedBooking.end_time) }})</p>
                </div>
              </div>

              <!-- MESSAGES LIST -->
              <div
                v-for="msg in messages"
                :id="`msg-${msg.id}`"
                :key="msg.id"
                :class="[
                  'cc-msg-row',
                  isMyMessage(msg) ? 'cc-msg-row--me' : 'cc-msg-row--other',
                  { 'is-recalled': msg.is_recalled }
                ]"
              >
                <!-- BOT AVATAR IF AI -->
                <div v-if="!isMyMessage(msg) && activeConversation.is_ai" class="cc-msg-avatar">
                  <AppIcon name="sparkles" size="14" />
                </div>

                <!-- MESSAGE CONTENT WRAPPER -->
                <div class="cc-msg-wrap">
                  <!-- FLOATING ACTION TOOLBAR (Hover on message) -->
                  <div v-if="!msg.is_recalled && !activeConversation.is_ai" class="cc-msg-hover-bar">
                    <!-- Quick Reactions -->
                    <button
                      v-for="emoji in ['❤️', '👍', '😂', '😮', '😢', '🔥']"
                      :key="emoji"
                      type="button"
                      class="cc-reaction-quick-btn"
                      @click="toggleReaction(msg, emoji)"
                    >
                      {{ emoji }}
                    </button>

                    <!-- Reply -->
                    <button
                      type="button"
                      class="cc-action-icon-btn"
                      title="Trả lời tin nhắn"
                      @click="setReplyMessage(msg)"
                    >
                      <AppIcon name="cornerUpLeft" size="13" />
                    </button>

                    <!-- Pin / Unpin -->
                    <button
                      type="button"
                      class="cc-action-icon-btn"
                      :title="msg.is_pinned ? 'Bỏ ghim' : 'Ghim tin nhắn'"
                      @click="togglePin(msg)"
                    >
                      <AppIcon name="pin" size="13" />
                    </button>

                    <!-- More Actions (Recall / Delete) -->
                    <button
                      type="button"
                      class="cc-action-icon-btn"
                      title="Tùy chọn khác"
                      @click.stop="activeMsgMenuId = activeMsgMenuId === msg.id ? null : msg.id"
                    >
                      <AppIcon name="moreHorizontal" size="13" />
                    </button>

                    <!-- Message Dropdown Menu -->
                    <div v-if="activeMsgMenuId === msg.id" class="cc-msg-dropdown" @click.stop>
                      <button
                        v-if="isMyMessage(msg) && canRecallMessage(msg)"
                        type="button"
                        class="cc-msg-dropdown-item"
                        @click="recallMessage(msg)"
                      >
                        <AppIcon name="rotateCcw" size="13" />
                        <span>Thu hồi tin nhắn</span>
                      </button>
                      <button
                        type="button"
                        class="cc-msg-dropdown-item cc-dropdown-item--danger"
                        @click="deleteForSelf(msg)"
                      >
                        <AppIcon name="trash" size="13" />
                        <span>Xóa ở phía tôi</span>
                      </button>
                    </div>
                  </div>

                  <!-- MESSAGE BUBBLE -->
                  <div class="cc-msg-bubble">
                    <!-- Sender Name for Group Chat -->
                    <span v-if="(activeConversation.is_group || activeConversation.type === 'group' || activeConversation.type === 'player_post') && !isMyMessage(msg) && msg.sender" class="cc-group-sender-name">
                      {{ msg.sender.full_name || msg.sender.username }}
                    </span>

                    <!-- Recalled message notice -->
                    <div v-if="msg.is_recalled" class="cc-recalled-text">
                      <AppIcon name="slash" size="12" />
                      <span>Tin nhắn đã bị thu hồi</span>
                    </div>

                    <template v-else>
                      <!-- QUOTE / REPLY-TO BOX -->
                      <div
                        v-if="msg.reply_to"
                        class="cc-reply-quote"
                        @click="scrollToMessage(msg.reply_to.id)"
                      >
                        <span class="cc-reply-quote-sender">{{ msg.reply_to.sender?.full_name || "Tin nhắn gốc" }}</span>
                        <p class="cc-reply-quote-text">{{ msg.reply_to.content || "[Hình ảnh]" }}</p>
                      </div>

                      <!-- BOOKING CARD / SUPPORT REQUEST PREVIEW -->
                      <div v-if="msg.reference_type === 'booking_support_request' || msg.support_request" class="cc-booking-request-card">
                        <div class="cc-br-head">
                          <AppIcon name="helpCircle" size="14" />
                          <span>Yêu cầu hỗ trợ booking</span>
                        </div>
                        <div class="cc-br-body">
                          <span class="cc-br-type">Loại yêu cầu: {{ formatSupportType(msg.support_request?.request_type || 'other') }}</span>
                          <span v-if="msg.support_request?.note" class="cc-br-note">{{ msg.support_request.note }}</span>
                          <span class="cc-br-status" :class="`cc-status--${msg.support_request?.status || 'pending'}`">
                            {{ formatSupportStatus(msg.support_request?.status || 'pending') }}
                          </span>
                        </div>
                      </div>

                      <div v-else-if="msg.reference_type === 'booking' || msg.booking" class="cc-booking-share-card">
                        <div class="cc-bs-head">
                          <AppIcon name="calendar" size="14" />
                          <span>Thông tin đặt sân</span>
                        </div>
                        <div class="cc-bs-body">
                          <span class="cc-bs-code">Mã đơn: #{{ msg.booking?.booking_code || msg.reference_id }}</span>
                          <span v-if="msg.booking" class="cc-bs-details">
                            {{ formatDate(msg.booking.booking_date) }} · {{ formatTime(msg.booking.start_time) }} - {{ formatTime(msg.booking.end_time) }}
                          </span>
                        </div>
                      </div>

                      <!-- TEXT CONTENT -->
                      <div class="cc-msg-text" v-html="renderContent(msg)"></div>

                      <!-- IMAGE ATTACHMENT -->
                      <div v-if="msg.image_url" class="cc-msg-img-wrap" @click="openLightbox(msg.image_url)">
                        <img :src="msg.image_url" class="cc-msg-img" alt="Ảnh tin nhắn" />
                      </div>

                      <!-- PINNED ICON BADGE -->
                      <span v-if="msg.is_pinned" class="cc-pinned-tag" title="Tin nhắn đã ghim">
                        <AppIcon name="pin" size="10" />
                      </span>
                    </template>

                    <span class="cc-msg-time">{{ formatTime(msg.created_at) }}</span>
                  </div>

                  <!-- REACTION COUNTER PILLS -->
                  <div v-if="msg.reactions && msg.reactions.length > 0 && !msg.is_recalled" class="cc-msg-reactions">
                    <button
                      v-for="(grp, emojiKey) in groupReactions(msg.reactions)"
                      :key="emojiKey"
                      type="button"
                      class="cc-reaction-pill"
                      :class="{ 'is-my-reaction': grp.userReacted }"
                      @click="toggleReaction(msg, emojiKey)"
                    >
                      <span>{{ emojiKey }}</span>
                      <span class="cc-reaction-count">{{ grp.count }}</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- LOADING AI TYPING -->
              <div v-if="sendingAi" class="cc-msg-row cc-msg-row--other">
                <div class="cc-msg-avatar">
                  <AppIcon name="sparkles" size="14" />
                </div>
                <div class="cc-msg-bubble cc-msg-bubble--typing">
                  <span class="cc-typing-dot"></span>
                  <span class="cc-typing-dot"></span>
                  <span class="cc-typing-dot"></span>
                </div>
              </div>
            </div>

            <!-- SMART QUICK PROMPT CHIPS (NO EMOJIS) -->
            <div class="cc-quick-chips">
              <button
                v-for="(chip, idx) in quickChips"
                :key="idx"
                type="button"
                class="cc-chip-btn"
                @click="sendQuickChip(chip)"
              >
                <span>{{ chip }}</span>
              </button>
            </div>

            <!-- REPLY PREVIEW BAR (When replying to a message) -->
            <div v-if="replyingToMessage" class="cc-reply-bar">
              <div class="cc-reply-bar-info">
                <AppIcon name="cornerUpLeft" size="13" class="cc-reply-icon" />
                <div>
                  <span class="cc-reply-label">Đang trả lời {{ replyingToMessage.sender?.full_name || "tin nhắn" }}</span>
                  <p class="cc-reply-preview-text">{{ replyingToMessage.content || "[Hình ảnh]" }}</p>
                </div>
              </div>
              <button type="button" class="cc-reply-cancel-btn" title="Hủy trả lời" @click="replyingToMessage = null">✕</button>
            </div>

            <!-- EMOJI PICKER POPOVER -->
            <div v-if="showEmojiPicker" class="cc-emoji-popover" @click.stop>
              <div class="cc-ep-tabs">
                <button
                  v-for="cat in emojiCategories"
                  :key="cat.id"
                  type="button"
                  class="cc-ep-tab"
                  :class="{ active: selectedEmojiCategory === cat.id }"
                  @click="selectedEmojiCategory = cat.id"
                >
                  {{ cat.icon }}
                </button>
              </div>
              <div class="cc-ep-grid">
                <button
                  v-for="em in currentCategoryEmojis"
                  :key="em"
                  type="button"
                  class="cc-ep-item"
                  @click="insertEmoji(em)"
                >
                  {{ em }}
                </button>
              </div>
            </div>

            <!-- INPUT BAR -->
            <div class="cc-input-bar">
              <div v-if="isMatchmakingGroup && activeConversation.is_active === false" class="cc-group-left-note">
                Bạn đã rời nhóm. Lịch sử và thời điểm vào/rời nhóm vẫn được lưu để tra cứu.
              </div>
              <label class="cc-attach-btn" title="Đính kèm ảnh">
                <AppIcon name="image" size="18" />
                <input
                  type="file"
                  accept="image/*"
                  class="cc-file-input"
                  @change="handleFileSelect"
                />
              </label>

              <!-- Emoji Picker Trigger Button -->
              <button
                type="button"
                class="cc-btn-input-action"
                :class="{ 'is-active': showEmojiPicker }"
                title="Chọn biểu tượng cảm xúc"
                @click.stop="showEmojiPicker = !showEmojiPicker"
              >
                <AppIcon name="smile" size="18" />
              </button>

              <!-- Share Booking Button in input bar (Venue contact only) -->
              <button
                v-if="activeConversation.type === 'venue_contact'"
                type="button"
                class="cc-btn-input-action"
                title="Gửi thông tin đơn đặt sân"
                @click="openBookingPicker"
              >
                <AppIcon name="calendar" size="16" />
              </button>

              <div v-if="selectedFile" class="cc-file-preview">
                <span>{{ selectedFile.name }}</span>
                <button type="button" @click="selectedFile = null">✕</button>
              </div>

              <input
                ref="chatInputRef"
                v-model="inputContent"
                type="text"
                placeholder="Nhập nội dung tin nhắn..."
                class="cc-chat-input"
                  :disabled="sendingAi || !canSendChat"
                @input="handleTypingInput"
                @keyup.enter="sendMessage"
              />

              <button
                type="button"
                class="cc-send-btn"
                :disabled="(!inputContent.trim() && !selectedFile) || sendingAi || !canSendChat"
                @click="sendMessage"
              >
                <span>Gửi</span>
              </button>
            </div>
          </div>
        </section>

        <!-- RIGHT VENUE INFO SIDEBAR (Desktop/Tablet) -->
        <aside v-if="showVenueSidebar && activeConversation && activeConversation.type === 'venue_contact'" class="cc-venue-info-sidebar">
          <div class="cc-vis-head">
            <h3 class="cc-vis-title">Thông tin cụm sân</h3>
            <button type="button" class="cc-icon-btn" title="Đóng" @click="showVenueSidebar = false">✕</button>
          </div>

          <div class="cc-vis-content">
            <div class="cc-vis-hero">
              <div class="cc-vis-avatar" :style="{ backgroundColor: getAvatarColor(activeConversation.title) }">
                {{ getInitial(activeConversation.title) }}
              </div>
              <span class="cc-vis-name">{{ activeConversation.title }}</span>
              <span class="cc-vis-sub">Kênh hỗ trợ chính thức</span>
            </div>

            <!-- BOOKINGS HISTORY AT THIS VENUE -->
            <div class="cc-vis-section">
              <span class="cc-vis-section-label">ĐƠN ĐẶT SÂN GẦN ĐÂY</span>
              <div v-if="loadingVenueBookings" class="cc-vis-loading">Đang tải lịch sử...</div>
              <div v-else-if="venueBookingsHistory.length === 0" class="cc-vis-empty">
                Chưa có đơn đặt sân nào tại sân này.
              </div>
              <div v-else class="cc-vis-bookings-list">
                <article
                  v-for="b in venueBookingsHistory"
                  :key="b.id"
                  class="cc-vis-booking-item"
                >
                  <div class="cc-vis-booking-top">
                    <span class="cc-vis-booking-code">#{{ b.booking_code }}</span>
                    <span class="cc-vis-booking-status" :class="`status--${b.status}`">{{ formatBookingStatus(b.status) }}</span>
                  </div>
                  <p class="cc-vis-booking-time">
                    Ngày {{ formatDate(b.booking_date) }} ({{ formatTime(b.start_time) }} - {{ formatTime(b.end_time) }})
                  </p>
                  <div class="cc-vis-booking-actions">
                    <router-link :to="{ name: 'booking-detail', params: { id: b.id } }" class="cc-vis-link">
                      Xem chi tiết đơn
                    </router-link>
                    <button type="button" class="cc-vis-btn" @click="sendBookingCardDirectly(b)">
                      Gửi vào chat
                    </button>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </main>

    <!-- CREATE GROUP CHAT MODAL -->
    <div v-if="showCreateGroupModal" class="cc-modal-overlay" @click.self="showCreateGroupModal = false">
      <div class="cc-modal-card">
        <div class="cc-modal-head">
          <h3 class="cc-modal-title">Tạo nhóm trò chuyện mới</h3>
          <button type="button" class="cc-icon-btn" @click="showCreateGroupModal = false">✕</button>
        </div>

        <div class="cc-modal-body">
          <div class="cc-form-group">
            <label class="cc-form-label">Tên nhóm:</label>
            <input
              v-model="groupName"
              type="text"
              placeholder="VD: Hội cầu lông tối thứ 6..."
              class="cc-form-input"
            />
          </div>

          <div class="cc-form-group">
            <label class="cc-form-label">Ảnh đại diện nhóm (Tùy chọn):</label>
            <input
              type="file"
              accept="image/*"
              class="cc-form-file"
              @change="handleGroupAvatarSelect"
            />
          </div>

          <div class="cc-form-group">
            <label class="cc-form-label">Tìm và thêm thành viên vào nhóm:</label>
            <div class="cc-search-box">
              <AppIcon name="search" size="14" class="cc-search-icon" />
              <input
                v-model="groupMemberSearch"
                type="text"
                placeholder="Nhập tên, số điện thoại hoặc email..."
                class="cc-search-input"
                @input="handleGroupMemberSearch"
              />
            </div>
          </div>

          <!-- SELECTED MEMBERS CHIPS -->
          <div v-if="selectedGroupMembers.length > 0" class="cc-selected-chips">
            <span class="cc-form-label">Thành viên đã chọn ({{ selectedGroupMembers.length }}):</span>
            <div class="cc-chips-wrap">
              <span
                v-for="m in selectedGroupMembers"
                :key="m.id"
                class="cc-member-chip"
              >
                <span>{{ m.full_name || m.username }}</span>
                <button type="button" @click="removeGroupMember(m.id)">✕</button>
              </span>
            </div>
          </div>

          <!-- SEARCH RESULTS FOR GROUP MEMBERS -->
          <div v-if="groupMemberResults.length > 0" class="cc-group-search-results">
            <button
              v-for="u in groupMemberResults"
              :key="u.id"
              type="button"
              class="cc-gsr-item"
              :disabled="selectedGroupMembers.some((m) => m.id === u.id)"
              @click="addGroupMember(u)"
            >
              <div class="cc-avatar cc-avatar--sm" :style="{ backgroundColor: getAvatarColor(u.full_name) }">
                {{ getInitial(u.full_name) }}
              </div>
              <div class="cc-gsr-info">
                <span class="cc-gsr-name">{{ u.full_name || u.username }}</span>
                <span class="cc-gsr-sub">{{ u.phone || u.email || '' }}</span>
              </div>
              <span v-if="selectedGroupMembers.some((m) => m.id === u.id)" class="cc-gsr-added">Đã thêm</span>
              <span v-else class="cc-gsr-add-btn">+ Thêm</span>
            </button>
          </div>
        </div>

        <div class="cc-modal-footer">
          <button type="button" class="cc-btn-ghost" @click="showCreateGroupModal = false">
            Hủy
          </button>
          <button
            type="button"
            class="cc-btn-primary"
            :disabled="!groupName.trim() || selectedGroupMembers.length === 0 || creatingGroup"
            @click="submitCreateGroup"
          >
            <span>{{ creatingGroup ? 'Đang tạo nhóm...' : 'Tạo nhóm chat' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- GROUP INFO & PARTICIPANTS MODAL (SPACIOUS 2-COLUMN DESIGN) -->
    <div v-if="showGroupInfoModal" class="cc-modal-overlay" @click.self="showGroupInfoModal = false">
      <div class="cc-modal-card cc-modal-card--wide">
        <div class="cc-modal-head">
          <h3 class="cc-modal-title">Thông tin nhóm trò chuyện</h3>
          <button type="button" class="cc-icon-btn" @click="showGroupInfoModal = false" aria-label="Đóng">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <div class="cc-modal-body cc-modal-body--grid">
          <!-- LEFT COLUMN: GROUP HERO & PRIMARY ACTIONS -->
          <div class="cc-group-side-info">
            <div class="cc-vis-hero cc-vis-hero--stacked">
              <div class="cc-vis-avatar cc-vis-avatar--lg" :style="{ backgroundColor: getAvatarColor(activeConversation?.title) }">
                <img v-if="activeConversation?.avatar_url" :src="activeConversation.avatar_url" :alt="activeConversation.title" class="cc-avatar-img" />
                <span v-else>{{ getInitial(activeConversation?.title) }}</span>
              </div>
              <h4 class="cc-vis-name">{{ activeConversation?.title }}</h4>
              <span class="cc-vis-sub">Nhóm trò chuyện SportGo</span>
            </div>

            <div class="cc-group-side-actions">
              <button
                v-if="isGroupLeader && activeConversation?.type === 'group'"
                type="button"
                class="cc-btn-outline-danger"
                @click="dissolveMatchmakingGroup"
              >
                Giải tán nhóm
              </button>
              <button
                v-else-if="String(activeConversation?.created_by) !== String(currentUser?.id) && activeConversation?.is_active !== false"
                type="button"
                class="cc-btn-outline-danger"
                @click="leaveGroupConversation"
              >
                Rời khỏi nhóm
              </button>
            </div>
          </div>

          <!-- RIGHT COLUMN: PARTICIPANTS & MANAGEMENTS -->
          <div class="cc-group-main-content">
            <div class="cc-participants-header">
              <span class="cc-participants-title">Thành viên trong nhóm ({{ activeConversation?.participants?.length || 0 }})</span>
              <button
                v-if="isGroupLeader && activeConversation?.type === 'group'"
                type="button"
                class="cc-btn-add-member-trigger"
                @click="showAddMemberInput = !showAddMemberInput"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>{{ showAddMemberInput ? 'Đóng tìm kiếm' : 'Thêm thành viên' }}</span>
              </button>
            </div>

            <!-- ADD MEMBER SEARCH SECTION (FOR LEADER) -->
            <div v-if="showAddMemberInput && isGroupLeader" class="cc-add-member-box">
              <input
                v-model="addMemberQuery"
                type="text"
                class="cc-form-input cc-input-sm"
                placeholder="Nhập tên, số điện thoại hoặc email..."
                @input="handleSearchAddMembers"
              />
              <div v-if="addMemberResults.length > 0" class="cc-add-member-results">
                <div
                  v-for="u in addMemberResults"
                  :key="u.id"
                  class="cc-add-user-row"
                  @click="submitAddMember(u)"
                >
                  <div class="cc-avatar cc-avatar--xs" :style="{ backgroundColor: getAvatarColor(u.full_name || u.username) }">
                    <img v-if="u.avatar_url" :src="u.avatar_url" :alt="u.full_name || u.username" class="cc-avatar-img" />
                    <span v-else>{{ getInitial(u.full_name || u.username) }}</span>
                  </div>
                  <span class="cc-aur-name">{{ u.full_name || u.username }}</span>
                  <span class="cc-aur-action">Thêm vào nhóm</span>
                </div>
              </div>
            </div>

            <!-- PARTICIPANTS FRAMELESS LIST -->
            <div class="cc-group-flat-list">
              <template v-if="activeConversation?.participants && activeConversation.participants.length > 0">
                <div
                  v-for="p in activeConversation.participants"
                  :key="p.user_id || p.id"
                  class="cc-flat-item"
                >
                  <div class="cc-avatar cc-avatar--sm" :style="{ backgroundColor: getAvatarColor(p.user?.full_name || p.user?.username || 'U') }">
                    <img v-if="p.user?.avatar_url" :src="p.user.avatar_url" :alt="p.user?.full_name || p.user?.username" class="cc-avatar-img" />
                    <span v-else>{{ getInitial(p.user?.full_name || p.user?.username || 'U') }}</span>
                  </div>
                  <div class="cc-flat-info">
                    <span class="cc-flat-name">{{ p.user?.full_name || p.user?.username || 'Thành viên' }}</span>
                    <span class="cc-flat-sub">{{ p.left_at ? 'Đã rời nhóm' : 'Đang hoạt động' }}</span>
                  </div>

                  <div class="cc-flat-actions">
                    <span v-if="String(p.user_id) === String(activeConversation?.created_by)" class="cc-badge-leader">Trưởng nhóm</span>

                    <button
                      v-else-if="String(p.user_id) !== String(currentUser?.id)"
                      type="button"
                      class="cc-btn-member-action"
                      title="Nhắn tin riêng"
                      @click="startDirectChatFromGroup(p.user)"
                    >
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                      </svg>
                      <span>Nhắn tin</span>
                    </button>

                    <button
                      v-if="isGroupLeader && String(p.user_id) !== String(currentUser?.id) && !p.left_at"
                      type="button"
                      class="cc-btn-member-kick"
                      title="Xóa khỏi nhóm"
                      @click="handleKickMember(p)"
                    >
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                      </svg>
                      <span>Xóa</span>
                    </button>
                  </div>
                </div>
              </template>
              <div v-else class="cc-empty-participants">
                <span>Chưa có dữ liệu danh sách thành viên</span>
              </div>
            </div>
          </div>
        </div>

        <div class="cc-modal-footer">
          <button type="button" class="cc-btn-ghost" @click="showGroupInfoModal = false">
            Đóng
          </button>
        </div>
      </div>
    </div>

    <!-- BOOKING PICKER & SUPPORT REQUEST MODAL -->
    <div v-if="showBookingPickerModal" class="cc-modal-overlay" @click.self="showBookingPickerModal = false">
      <div class="cc-modal-card">
        <div class="cc-modal-head">
          <h3 class="cc-modal-title">Gửi thông tin &amp; Yêu cầu hỗ trợ Booking</h3>
          <button type="button" class="cc-icon-btn" @click="showBookingPickerModal = false">✕</button>
        </div>

        <div class="cc-modal-body">
          <div v-if="loadingEligibleBookings" class="cc-modal-loading">
            <span class="cc-spinner"></span>
            <span>Đang tải danh sách đặt sân của bạn...</span>
          </div>

          <div v-else-if="eligibleBookings.length === 0" class="cc-modal-empty">
            <svg width="52" height="52" viewBox="0 0 52 52" fill="none" class="cc-state-illustration">
              <circle cx="26" cy="26" r="24" fill="#f0fdf4" />
              <rect x="15" y="16" width="22" height="22" rx="3" stroke="#15803d" stroke-width="1.5" fill="#ffffff" />
              <line x1="15" y1="23" x2="37" y2="23" stroke="#15803d" stroke-width="1.5" />
              <line x1="21" y1="13" x2="21" y2="17" stroke="#15803d" stroke-width="1.5" stroke-linecap="round" />
              <line x1="31" y1="13" x2="31" y2="17" stroke="#15803d" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            <p>Bạn không có đơn đặt sân nào gần đây tại cụm sân này.</p>
          </div>

          <template v-else>
            <div class="cc-form-group">
              <label class="cc-form-label">Chọn đơn đặt sân cần hỗ trợ:</label>
              <div class="cc-custom-select" :class="{ 'is-open': isBookingSelectOpen }">
                <button
                  type="button"
                  class="cc-select-trigger"
                  @click="isBookingSelectOpen = !isBookingSelectOpen; isTypeSelectOpen = false"
                >
                  <span class="cc-select-val">{{ selectedBookingLabel }}</span>
                  <AppIcon name="chevronDown" size="14" class="cc-select-chevron" :class="{ 'is-flipped': isBookingSelectOpen }" />
                </button>

                <div v-if="isBookingSelectOpen" class="cc-select-menu" @click.stop>
                  <div
                    v-for="bk in eligibleBookings"
                    :key="bk.id"
                    class="cc-select-opt"
                    :class="{ 'is-selected': selectedBookingForSupport?.id === bk.id }"
                    @click="selectedBookingForSupport = bk; isBookingSelectOpen = false"
                  >
                    <div class="cc-opt-body">
                      <span class="cc-opt-title">#{{ bk.booking_code }}</span>
                      <span class="cc-opt-sub">Ngày {{ formatDate(bk.booking_date) }} ({{ formatTime(bk.start_time) }} - {{ formatTime(bk.end_time) }})</span>
                    </div>
                    <AppIcon v-if="selectedBookingForSupport?.id === bk.id" name="check" size="14" class="cc-opt-check" />
                  </div>
                </div>
              </div>
            </div>

            <template v-if="selectedBookingForSupport">
              <div class="cc-form-group">
                <label class="cc-form-label">Loại yêu cầu hỗ trợ:</label>
                <div class="cc-custom-select" :class="{ 'is-open': isTypeSelectOpen }">
                  <button
                    type="button"
                    class="cc-select-trigger"
                    @click="isTypeSelectOpen = !isTypeSelectOpen; isBookingSelectOpen = false"
                  >
                    <span class="cc-select-val">{{ selectedTypeLabel }}</span>
                    <AppIcon name="chevronDown" size="14" class="cc-select-chevron" :class="{ 'is-flipped': isTypeSelectOpen }" />
                  </button>

                  <div v-if="isTypeSelectOpen" class="cc-select-menu" @click.stop>
                    <div
                      v-for="typeOpt in supportRequestTypeOptions"
                      :key="typeOpt.value"
                      class="cc-select-opt"
                      :class="{ 'is-selected': supportRequestType === typeOpt.value }"
                      @click="supportRequestType = typeOpt.value; isTypeSelectOpen = false"
                    >
                      <div class="cc-opt-body">
                        <span class="cc-opt-title">{{ typeOpt.label }}</span>
                        <span class="cc-opt-sub">{{ typeOpt.sublabel }}</span>
                      </div>
                      <AppIcon v-if="supportRequestType === typeOpt.value" name="check" size="14" class="cc-opt-check" />
                    </div>
                  </div>
                </div>
              </div>

              <div class="cc-form-group">
                <label class="cc-form-label">Ghi chú chi tiết cho sân:</label>
                <textarea
                  v-model="supportRequestNote"
                  rows="3"
                  placeholder="Nhập thời gian muốn đổi sang, lý do hoặc thông tin cần hỗ trợ..."
                  class="cc-form-textarea"
                ></textarea>
              </div>
            </template>
          </template>
        </div>

        <div class="cc-modal-footer">
          <button type="button" class="cc-btn-ghost" @click="showBookingPickerModal = false">
            Đóng
          </button>

          <button
            v-if="selectedBookingForSupport"
            type="button"
            class="cc-btn-ghost"
            @click="sendBookingCardDirectly(selectedBookingForSupport); showBookingPickerModal = false"
          >
            Chỉ gửi thẻ đơn
          </button>

          <button
            type="button"
            class="cc-btn-primary"
            :disabled="!selectedBookingForSupport || submittingSupport"
            @click="submitSupportRequest"
          >
            <span>{{ submittingSupport ? 'Đang gửi...' : 'Gửi yêu cầu hỗ trợ' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MEDIA LIGHTBOX MODAL -->
    <div v-if="lightboxImage" class="cc-lightbox-overlay" @click.self="closeLightbox">
      <div class="cc-lightbox-toolbar">
        <button type="button" class="cc-lightbox-btn" title="Thu nhỏ" @click="lightboxZoom = Math.max(0.5, lightboxZoom - 0.25)">-</button>
        <span class="cc-lightbox-zoom-val">{{ Math.round(lightboxZoom * 100) }}%</span>
        <button type="button" class="cc-lightbox-btn" title="Phóng to" @click="lightboxZoom = Math.min(3, lightboxZoom + 0.25)">+</button>
        <button type="button" class="cc-lightbox-btn" title="Xoay 90 độ" @click="lightboxRotation = (lightboxRotation + 90) % 360">↻</button>
        <a :href="lightboxImage" download="chat_image" target="_blank" class="cc-lightbox-btn" title="Tải ảnh về máy">↓</a>
        <button type="button" class="cc-lightbox-btn cc-lightbox-btn--close" title="Đóng" @click="closeLightbox">✕</button>
      </div>

      <div class="cc-lightbox-stage" @click.self="closeLightbox">
        <img
          :src="lightboxImage"
          class="cc-lightbox-img"
          :style="{
            transform: `scale(${lightboxZoom}) rotate(${lightboxRotation}deg)`
          }"
          alt="Xem ảnh lớn"
        />
      </div>
    </div>

    <!-- MODAL XÁC NHẬN GIẢI TÁN NHÓM -->
    <div v-if="showDissolveConfirmModal" class="cc-modal-overlay" @click.self="showDissolveConfirmModal = false">
      <div class="cc-modal-card cc-confirm-card" role="dialog" aria-modal="true">
        <div class="cc-confirm-icon cc-confirm-icon--danger">
          <AppIcon name="alert" size="28" />
        </div>
        <h3 class="cc-confirm-title">Giải tán nhóm giao lưu?</h3>
        <p class="cc-confirm-desc">Cuộc trò chuyện nhóm sẽ bị xóa và bài giao lưu sẽ được đóng. Bạn có chắc chắn muốn giải tán nhóm?</p>
        <div class="cc-confirm-actions">
          <button type="button" class="cc-btn-ghost" :disabled="dissolvingGroup" @click="showDissolveConfirmModal = false">
            Hủy bỏ
          </button>
          <button type="button" class="cc-btn-primary cc-btn-primary--danger" :disabled="dissolvingGroup" @click="confirmDissolveMatchmakingGroup">
            <span>{{ dissolvingGroup ? 'Đang giải tán...' : 'Xác nhận giải tán' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useToast } from "vue-toastification";
import echo from "../../echo.js";
import AppIcon from "../../components/AppIcon.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { bookingService } from "../../services/bookingService.js";
import { chatService } from "../../services/chat.service.js";

export default {
  name: "ClientChat",
  components: { PublicNavbar, AppIcon },
  data() {
    return {
      toast: useToast(),
      activeTab: "all",
      searchQuery: "",
      conversations: [],
      activeConversation: null,
      messages: [],
      inputContent: "",
      selectedFile: null,
      loadingConversations: true,
      sendingAi: false,
      attachedBooking: null,
      mobileActiveView: "list",
      currentUser: null,

      // Advanced Chat Features
      replyingToMessage: null,
      activeMsgMenuId: null,
      showChatActionsMenu: false,
      showVenueSidebar: false,
      showGroupInfoModal: false,
      showDissolveConfirmModal: false,
      dissolvingGroup: false,
      showAddMemberInput: false,
      addMemberQuery: "",
      addMemberResults: [],
      isTyping: false,
      typingTimeout: null,
      onlineUsers: new Set(),
      isMuted: false,

      // User Search & Group Creation
      userSearchResults: [],
      searchTimeout: null,
      showCreateGroupModal: false,
      groupName: "",
      groupAvatarFile: null,
      groupMemberSearch: "",
      groupMemberResults: [],
      selectedGroupMembers: [],
      creatingGroup: false,

      // Emoji Picker
      showEmojiPicker: false,
      selectedEmojiCategory: "smileys",
      emojiCategories: [
        { id: "smileys", icon: "😃" },
        { id: "sports", icon: "🏸" },
        { id: "animals", icon: "🐶" },
        { id: "food", icon: "🍔" },
        { id: "symbols", icon: "❤️" },
      ],
      emojiMap: {
        smileys: ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕'],
        sports: ['🏸','🎾','⚽','🏀','🏐','🏈','⚾','🥎','🏓','⛳','🥊','🥋','🎽','🛹','🛼','🏋️','🤺','🤼','🤸','⛹️','🏌️','🏄','🏊','🤽','🚣','🧗','🚵','🚴','🏆','🥇','🥈','🥉','🏅','🎖️','🎯','🎪'],
        animals: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🐧','🐦','🐤','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🐛','🦋','🐌','🐞','🐜','🦟','🐢','🐍','🦎','🐙','🦑','🦐','🦞','🦀','🐡','🐠','🐟','🐬','🐳','🦈','🐊','🐅','🐆','🦓','🦍','🦧','🦣','🐘','🦛','🦏','🐪','🐫','🦒','🦘','🦬','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🦙','🐐','🦌','🐕','🐩','🦮','🐕‍🦺','🐈','🐈‍⬛','🪶','🐓','🦃','🦤','🦚','🦜','🦢','🦩','🕊️','🐇','🦝','🦨','🦡','🦦','🦥','🐁','🐀','🐿️','🦔','🐾','🌲','🌳','🌴','🌱','🌿','☘️','🍀','🎍','🎋','🍃','🍂','🍁','🌾','🌺','🌸','🌼','🌻','🌞','🌝','⭐','🌟','✨','⚡','🔥','💧','🌊'],
        food: ['🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🫐','🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🥑','🥦','🥬','🥒','🌶️','🌽','🥕','🧄','🧅','🥔','🍠','🥐','🥯','🍞','🥖','🥨','🧀','🥚','🍳','🧈','🥞','🧇','🥓','🥩','🍗','🍖','🦴','🌭','🍔','🍟','🍕','🥪','🥙','🧆','🌮','🌯','🥗','🥘','🥫','🍝','🍜','🍲','🍛','🍣','🍱','🥟','🦪','🍤','🍙','🍚','🍘','🍥','🥠','🥮','🍢','🍡','🍧','🍨','🍦','🥧','🧁','🍰','🎂','🍮','🍭','🍬','🍫','🍿','🍩','🍪','🌰','🥜','🍯','🥛','🍼','☕','🫖','🍵','🧃','🥤','🧋','🍶','🍺','🍻','🥂','🍷','🥃','🍸','🍹','🧉','🍾','🧊'],
        symbols: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','☮️','✝️','☪️','🕉️','☸️','✡️','🔯','🕎','☯️','☦️','🛐','⛎','♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓','🆔','⚛️','🉑','☢️','☣️','📴','📳','🈶','🈚','🈸','🈺','🈷️','✴️','🆚','💮','🉐','㊙️','㊗️','🈴','🈵','🈹','🈲','🅰️','🅱️','🆎','🆑','🅾️','🆘','❌','⭕','🛑','⛔','📛','🚫','💯','💢','♨️','🚷','🚯','🚳','🚱','🔞','📵','🚭','❗','❕','❓','❔','‼️','⁉️','🔅','🔆','〽️','⚠️','🚸','🔱','⚜️','🔰','♻️','✅','🈯','💹','❇️','✳️','❎','🌐','💠','Ⓜ️','🌀','💤','🏧','🚾','♿','🅿️','🈳','🈂️','🛂','🛃','🛄','🛅','🚹','🚺','🚼','⚧','🚻','🚮','🎦','📶','🈁','🆖','🆗','🆙','🆒','🆕','🆓','0️⃣','1️⃣','2️⃣','3️⃣','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣','9️⃣','🔟','🔢','#️⃣','*️⃣','⏏️','▶️','⏸️','⏯️','⏹️','⏺️','⏭️','⏮️','⏩','⏪','🔀','🔁','🔂','◀️','🔼','🔽','⏫','⏬','➡️','⬅️','⬆️','⬇️','↗️','↘️','↙️','↖️','↕️','↔️','🔄','↪️','↩️','⤴️','⤵️'],
      },

      // Support & Bookings
      showBookingPickerModal: false,
      loadingEligibleBookings: false,
      eligibleBookings: [],
      selectedBookingForSupport: null,
      supportRequestType: "reschedule",
      supportRequestNote: "",
      submittingSupport: false,
      loadingVenueBookings: false,
      venueBookingsHistory: [],
      isBookingSelectOpen: false,
      isTypeSelectOpen: false,
      supportRequestTypeOptions: [
        { value: "reschedule", label: "Đổi giờ chơi", sublabel: "Lùi hoặc đẩy sớm khung giờ đã đặt" },
        { value: "change_court", label: "Đổi sân con", sublabel: "Chuyển sang sân số khác trong cụm" },
        { value: "cancel_booking", label: "Hủy đặt sân & Hoàn tiền", sublabel: "Hủy đơn theo chính sách hoàn ví" },
        { value: "late_arrival", label: "Báo đến muộn", sublabel: "Báo nhân viên giữ sân khi đến trễ" },
        { value: "payment", label: "Thanh toán & Hóa đơn", sublabel: "Xác nhận giao dịch hoặc xuất hóa đơn" },
        { value: "other", label: "Yêu cầu khác", sublabel: "Hỗ trợ các vấn đề phát sinh khác" },
      ],

      // Lightbox
      lightboxImage: null,
      lightboxZoom: 1,
      lightboxRotation: 0,

      // Echo channels
      activeEchoChannel: null,
      presenceEchoChannel: null,
    };
  },
  computed: {
    filteredConversations() {
      let list = this.conversations;
      if (this.activeTab === "ai") {
        list = list.filter((c) => c.is_ai);
      } else if (this.activeTab === "venue") {
        list = list.filter((c) => c.type === "venue_contact" || c.reference_type === "venue_cluster");
      } else if (this.activeTab === "direct") {
        list = list.filter((c) => !c.is_ai && c.type !== "venue_contact" && c.reference_type !== "venue_cluster");
      }

      const q = this.searchQuery.trim().toLowerCase();
      if (q) {
        list = list.filter((c) => (c.title || "").toLowerCase().includes(q));
      }
      return list;
    },
    pinnedMessage() {
      if (!this.messages.length) return null;
      return this.messages.find((m) => m.is_pinned && !m.is_recalled) || null;
    },
    isMatchmakingGroup() {
      return this.activeConversation?.type === 'player_post';
    },
    isGroupLeader() {
      if (!this.activeConversation || !this.currentUser) return false;
      return String(this.activeConversation.created_by) === String(this.currentUser.id);
    },
    canSendChat() {
      return !this.isMatchmakingGroup || this.activeConversation?.is_active !== false;
    },
    currentCategoryEmojis() {
      return this.emojiMap[this.selectedEmojiCategory] || [];
    },
    quickChips() {
      if (this.activeConversation?.is_ai) {
        return [
          "Tìm cụm sân còn trống tối nay",
          "Hướng dẫn quy định hoàn tiền ví",
          "Hỗ trợ đổi khung giờ chơi",
        ];
      }
      if (this.activeConversation?.type === "saved_messages" || (this.activeConversation?.type === "direct" && !this.activeConversation?.other_user)) {
        return [
          "Ghi chú lịch thi đấu tuần này",
          "Lưu số hotline cụm sân",
          "Lưu ảnh biên lai đặt cọc",
        ];
      }
      return [
        "Hỏi thông tin gửi xe ô tô",
        "Tôi muốn lùi giờ chơi 30 phút",
        "Sân có cho thuê thêm vợt không",
      ];
    },
    selectedBookingLabel() {
      if (!this.selectedBookingForSupport) return "-- Chọn đơn đặt sân --";
      const bk = this.selectedBookingForSupport;
      return `#${bk.booking_code} · ${this.formatDate(bk.booking_date)} (${this.formatTime(bk.start_time)} - ${this.formatTime(bk.end_time)})`;
    },
    selectedTypeLabel() {
      const found = this.supportRequestTypeOptions.find((o) => o.value === this.supportRequestType);
      return found ? found.label : "Chọn loại yêu cầu...";
    },
  },
  watch: {
    "$route.query": {
      handler() {
        this.handleQueryParams();
      },
      deep: true,
    },
  },
  async mounted() {
    this.loadCurrentUser();
    await this.fetchConversations();
    await this.handleQueryParams();
    this.initPresenceChannel();
    document.addEventListener("click", this.closeCustomDropdowns);
  },
  beforeUnmount() {
    document.removeEventListener("click", this.closeCustomDropdowns);
    this.unsubscribeActiveChannel();
    if (this.presenceEchoChannel && echo) {
      echo.leave("chat.online");
    }
  },
  methods: {
    loadCurrentUser() {
      try {
        const raw = localStorage.getItem("auth_user") || localStorage.getItem("user");
        if (raw) {
          const parsed = JSON.parse(raw);
          this.currentUser = parsed?.user || parsed;
        }
      } catch (e) {
        this.currentUser = null;
      }
    },
    initPresenceChannel() {
      if (!echo || !this.currentUser) return;
      try {
        this.presenceEchoChannel = echo.join("chat.online")
          .here((users) => {
            this.onlineUsers = new Set(users.map((u) => String(u.id)));
          })
          .joining((user) => {
            this.onlineUsers.add(String(user.id));
          })
          .leaving((user) => {
            this.onlineUsers.delete(String(user.id));
          });
      } catch (err) {
        console.warn("Lỗi kết nối presence channel", err);
      }
    },
    isParticipantOnline(conv) {
      if (!conv || conv.is_ai || conv.type === "saved_messages") return false;
      if (conv.other_user?.id) {
        return this.onlineUsers.has(String(conv.other_user.id));
      }
      return false;
    },
    playNotificationSound() {
      if (this.isMuted) return;
      try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = "sine";
        osc.frequency.setValueAtTime(587.33, ctx.currentTime);
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.08);
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
      } catch (e) {
        // audio ignored
      }
    },
    async fetchConversations() {
      this.loadingConversations = true;
      try {
        const res = await chatService.getConversations();
        const baseList = res || [];

        const aiConv = {
          id: "ai_assistant",
          title: "Trợ lý AI SportGo",
          type: "ai",
          is_ai: true,
          avatar_url: null,
          last_message: {
            content: "Trợ lý AI tư vấn và giải đáp thắc mắc 24/7",
            created_at: new Date().toISOString(),
          },
        };

        this.conversations = [aiConv, ...baseList];
      } catch (err) {
        console.error("Không thể tải danh sách hộp thư", err);
      } finally {
        this.loadingConversations = false;
      }
    },
    async handleQueryParams() {
      const q = this.$route.query;
      if (q.booking_id) {
        try {
          this.attachedBooking = await bookingService.getBooking(q.booking_id);
        } catch (e) {
          console.warn("Không thể tải thông tin đính kèm booking", e);
        }
      }

      const targetConvId = q.conversation_id || q.conversationId;
      const targetVenueId = q.venue_id || q.venueId || q.venue_cluster_id;
      const targetUserId = q.user_id || q.userId;

      if (targetConvId) {
        const target = this.conversations.find((c) => String(c.id) === String(targetConvId));
        if (target) {
          if (!target.is_ai) this.activeTab = target.type === "venue_contact" ? "venue" : "direct";
          this.selectConversation(target);
          return;
        }
      }

      if (targetVenueId) {
        try {
          const res = await chatService.startConversation({
            type: "venue_contact",
            venue_id: targetVenueId,
            venue_cluster_id: targetVenueId,
          });
          await this.fetchConversations();
          const found = this.conversations.find(
            (c) =>
              String(c.id) === String(res?.id) ||
              (c.type === "venue_contact" && String(c.reference_id) === String(targetVenueId))
          );
          if (found) {
            this.activeTab = "venue";
            this.selectConversation(found);
            return;
          }
        } catch (e) {
          console.error("Lỗi tạo cuộc trò chuyện với cụm sân", e);
        }
      } else if (targetUserId) {
        try {
          const res = await chatService.startConversation({
            type: "direct",
            user_id: targetUserId,
          });
          await this.fetchConversations();
          const found = this.conversations.find((c) => String(c.id) === String(res?.id));
          if (found) {
            this.activeTab = "direct";
            this.selectConversation(found);
            return;
          }
        } catch (e) {
          console.error("Lỗi tạo cuộc trò chuyện mới", e);
        }
      }

      // Default select AI only if nothing specified and no active conversation
      if (!this.activeConversation && this.conversations.length > 0) {
        this.selectConversation(this.conversations[0]);
      }
    },
    selectConversation(conv) {
      if (this.activeConversation?.id === conv.id) return;
      this.unsubscribeActiveChannel();

      this.activeConversation = conv;
      conv.unread_count = 0;
      this.mobileActiveView = "chat";
      this.replyingToMessage = null;
      this.activeMsgMenuId = null;
      this.showChatActionsMenu = false;
      this.showEmojiPicker = false;
      this.messages = [];

      this.fetchMessages();

      if (!conv.is_ai) {
        this.subscribeActiveChannel(conv.id);
        if (conv.type === "venue_contact") {
          this.loadVenueBookingsHistory(conv.id);
        }
      }
    },
    subscribeActiveChannel(convId) {
      if (!echo || !convId) return;
      try {
        this.activeEchoChannel = echo.private(`conversation.${convId}`)
          .listen(".message.sent", (event) => {
            const msg = event.message;
            if (!this.messages.some((m) => m.id === msg.id)) {
              this.messages.push(msg);
              this.scrollToBottom();
              if (String(msg.sender_id) !== String(this.currentUser?.id)) {
                this.playNotificationSound();
              }

              // Update conversation snippet in sidebar list
              const conv = this.conversations.find((c) => String(c.id) === String(msg.conversation_id));
              if (conv) {
                conv.last_message = { content: msg.content, created_at: msg.created_at };
                conv.last_message_at = msg.created_at;
                if (this.activeConversation?.id !== conv.id) {
                  conv.unread_count = (conv.unread_count || 0) + 1;
                }
              }
            }
          })
          .listen(".message.reacted", (event) => {
            const target = this.messages.find((m) => m.id === event.message_id);
            if (target) target.reactions = event.reactions;
          })
          .listen(".message.pinned", (event) => {
            const target = this.messages.find((m) => m.id === event.message_id);
            if (target) target.is_pinned = event.is_pinned;
          })
          .listen(".message.recalled", (event) => {
            const target = this.messages.find((m) => m.id === event.message_id);
            if (target) {
              target.is_recalled = true;
              target.content = "Tin nhắn đã bị thu hồi";
              target.image_url = null;
              target.is_pinned = false;
            }
          })
          .listenForWhisper("typing", (e) => {
            if (String(e.user_id) !== String(this.currentUser?.id)) {
              this.isTyping = true;
              if (this.typingTimeout) clearTimeout(this.typingTimeout);
              this.typingTimeout = setTimeout(() => {
                this.isTyping = false;
              }, 2500);
            }
          });
      } catch (err) {
        console.warn("Lỗi đăng ký Echo channel", err);
      }
    },
    unsubscribeActiveChannel() {
      if (this.activeEchoChannel && echo && this.activeConversation) {
        echo.leave(`conversation.${this.activeConversation.id}`);
        this.activeEchoChannel = null;
      }
    },
    handleTypingInput() {
      if (!this.activeEchoChannel || !this.activeConversation || this.activeConversation.is_ai) return;
      try {
        this.activeEchoChannel.whisper("typing", {
          user_id: this.currentUser?.id,
          name: this.currentUser?.full_name,
        });
      } catch (e) {
        // whisper failed silently
      }
    },
    handleGlobalSearch() {
      const q = this.searchQuery.trim();
      if (this.searchTimeout) clearTimeout(this.searchTimeout);
      if (q.length < 2) {
        this.userSearchResults = [];
        return;
      }
      this.searchTimeout = setTimeout(async () => {
        try {
          const res = await chatService.searchUsers(q);
          this.userSearchResults = (res || []).filter((u) => String(u.id) !== String(this.currentUser?.id));
        } catch (e) {
          this.userSearchResults = [];
        }
      }, 300);
    },
    async startDirectChatWithUser(user) {
      try {
        const res = await chatService.startConversation({
          type: "direct",
          user_id: user.id,
        });
        this.searchQuery = "";
        this.userSearchResults = [];
        await this.fetchConversations();
        const found = this.conversations.find((c) => String(c.id) === String(res?.id));
        if (found) {
          this.activeTab = "direct";
          this.selectConversation(found);
        }
      } catch (err) {
        console.error("Lỗi mở cuộc trò chuyện với người dùng", err);
      }
    },
    openCreateGroupModal() {
      this.showCreateGroupModal = true;
      this.groupName = "";
      this.groupAvatarFile = null;
      this.groupMemberSearch = "";
      this.groupMemberResults = [];
      this.selectedGroupMembers = [];
    },
    handleGroupAvatarSelect(e) {
      const file = e.target.files[0];
      if (file) this.groupAvatarFile = file;
    },
    handleGroupMemberSearch() {
      const q = this.groupMemberSearch.trim();
      if (q.length < 2) {
        this.groupMemberResults = [];
        return;
      }
      chatService.searchUsers(q)
        .then((res) => {
          this.groupMemberResults = (res || []).filter((u) => String(u.id) !== String(this.currentUser?.id));
        })
        .catch(() => {
          this.groupMemberResults = [];
        });
    },
    addGroupMember(user) {
      if (!this.selectedGroupMembers.some((m) => m.id === user.id)) {
        this.selectedGroupMembers.push(user);
      }
    },
    removeGroupMember(userId) {
      this.selectedGroupMembers = this.selectedGroupMembers.filter((m) => m.id !== userId);
    },
    async submitCreateGroup() {
      if (!this.groupName.trim() || this.selectedGroupMembers.length === 0) return;
      this.creatingGroup = true;
      try {
        const memberIds = this.selectedGroupMembers.map((m) => m.id);
        const res = await chatService.createGroupConversation(this.groupName.trim(), memberIds, this.groupAvatarFile);
        this.showCreateGroupModal = false;
        await this.fetchConversations();
        const found = this.conversations.find((c) => String(c.id) === String(res?.id));
        if (found) {
          this.activeTab = "direct";
          this.selectConversation(found);
        }
      } catch (err) {
        console.error("Lỗi tạo nhóm trò chuyện", err);
      } finally {
        this.creatingGroup = false;
      }
    },
    openGroupInfoModal() {
      this.showChatActionsMenu = false;
      this.showAddMemberInput = false;
      this.addMemberQuery = "";
      this.addMemberResults = [];
      this.showGroupInfoModal = true;
    },
    handleSearchAddMembers() {
      const q = this.addMemberQuery.trim();
      if (q.length < 2) {
        this.addMemberResults = [];
        return;
      }
      chatService.searchUsers(q)
        .then((res) => {
          const currentParticipantUserIds = (this.activeConversation?.participants || []).map((p) => String(p.user_id));
          this.addMemberResults = (res || []).filter(
            (u) => String(u.id) !== String(this.currentUser?.id) && !currentParticipantUserIds.includes(String(u.id))
          );
        })
        .catch(() => {
          this.addMemberResults = [];
        });
    },
    async submitAddMember(user) {
      if (!this.activeConversation || !user) return;
      try {
        await chatService.addMembers(this.activeConversation.id, [user.id]);
        this.addMemberQuery = "";
        this.addMemberResults = [];
        this.showAddMemberInput = false;

        if (!this.activeConversation.participants) {
          this.activeConversation.participants = [];
        }
        const existing = this.activeConversation.participants.find((p) => String(p.user_id) === String(user.id));
        if (existing) {
          existing.left_at = null;
        } else {
          this.activeConversation.participants.push({
            id: Date.now(),
            user_id: user.id,
            joined_at: new Date().toISOString(),
            left_at: null,
            user: {
              id: user.id,
              full_name: user.full_name,
              username: user.username,
              avatar_url: user.avatar_url,
              email: user.email,
              phone: user.phone,
            },
          });
        }
        await this.fetchConversations();
      } catch (err) {
        console.error("Lỗi thêm thành viên vào nhóm", err);
      }
    },
    async handleKickMember(participant) {
      if (!this.activeConversation || !participant) return;
      const memberName = participant.user?.full_name || participant.user?.username || "thành viên này";
      if (!confirm(`Bạn có chắc chắn muốn xóa "${memberName}" khỏi nhóm?`)) return;

      try {
        await chatService.removeMember(this.activeConversation.id, participant.user_id);
        if (this.activeConversation.participants) {
          this.activeConversation.participants = this.activeConversation.participants.filter(
            (p) => String(p.user_id) !== String(participant.user_id)
          );
        }
        await this.fetchConversations();
      } catch (err) {
        console.error("Lỗi xóa thành viên", err);
      }
    },
    async startDirectChatFromGroup(user) {
      if (!user || String(user.id) === String(this.currentUser?.id)) return;
      this.showGroupInfoModal = false;
      await this.startDirectChatWithUser(user);
    },
    async leaveGroupConversation() {
      this.showChatActionsMenu = false;
      this.showGroupInfoModal = false;
      try {
        const conversationId = this.activeConversation.id;
        if (this.isMatchmakingGroup) {
          await chatService.leaveConversation(conversationId);
          this.toast.success("Bạn đã rời nhóm giao lưu.");
          await this.fetchConversations();
          const updated = this.conversations.find((c) => String(c.id) === String(conversationId));
          if (updated) this.selectConversation(updated);
        } else {
          await chatService.deleteConversation(conversationId);
          this.toast.success("Đã rời khỏi cuộc trò chuyện.");
          this.conversations = this.conversations.filter((c) => c.id !== conversationId);
          this.activeConversation = null;
          if (this.conversations.length > 0) this.selectConversation(this.conversations[0]);
        }
      } catch (err) {
        console.error("Lỗi rời nhóm", err);
        this.toast.error(err.message || "Không thể rời nhóm.");
      }
    },
    dissolveMatchmakingGroup() {
      this.showChatActionsMenu = false;
      if (!this.isMatchmakingGroup || !this.activeConversation) return;
      this.showDissolveConfirmModal = true;
    },
    async confirmDissolveMatchmakingGroup() {
      if (!this.activeConversation || this.dissolvingGroup) return;
      this.dissolvingGroup = true;
      try {
        const conversationId = this.activeConversation.id;
        await chatService.dissolveConversation(conversationId);
        this.showDissolveConfirmModal = false;
        this.conversations = this.conversations.filter((c) => String(c.id) !== String(conversationId));
        this.activeConversation = null;
        this.messages = [];
        if (this.conversations.length > 0) {
          this.selectConversation(this.conversations[0]);
        }
      } catch (err) {
        console.error("Lỗi giải tán nhóm giao lưu", err);
      } finally {
        this.dissolvingGroup = false;
      }
    },
    async openSavedMessages() {
      try {
        const res = await chatService.startConversation({
          type: "saved_messages",
          user_id: this.currentUser?.id,
        });
        await this.fetchConversations();
        const found = this.conversations.find((c) => String(c.id) === String(res?.id) || c.type === "saved_messages" || (c.type === "direct" && !c.other_user));
        if (found) {
          this.activeTab = "direct";
          this.selectConversation(found);
        }
      } catch (err) {
        console.error("Lỗi mở tin nhắn đã lưu", err);
      }
    },
    insertEmoji(emoji) {
      this.inputContent += emoji;
      this.$nextTick(() => {
        if (this.$refs.chatInputRef) this.$refs.chatInputRef.focus();
      });
    },
    fetchMessages() {
      if (!this.activeConversation) return;

      const currentConvId = this.activeConversation.id;
      const isAiConv = !!this.activeConversation.is_ai || currentConvId === "ai_assistant";

      if (isAiConv) {
        chatService.getAiHistory()
          .then((res) => {
            if (String(this.activeConversation?.id) !== String(currentConvId)) return;
            if (res.messages && Array.isArray(res.messages) && res.messages.length > 0) {
              this.messages = res.messages.map((m) => ({
                ...m,
                sender_id: (m.sender_id === "me" || m.role === "user") ? "me" : "ai_assistant",
              }));
              this.saveAiMessages();
              this.scrollToBottom();
              return;
            }
            this.loadSavedLocalAiMessages();
          })
          .catch(() => {
            if (String(this.activeConversation?.id) === String(currentConvId)) {
              this.loadSavedLocalAiMessages();
            }
          });
        return;
      }

      try {
        chatService.getMessages(currentConvId).then((res) => {
          if (String(this.activeConversation?.id) !== String(currentConvId)) return;
          this.messages = res?.messages || (Array.isArray(res) ? res : []);
          if (Array.isArray(res?.participants)) {
            this.activeConversation.participants = res.participants;
            const self = res.participants.find((participant) => String(participant.user_id) === String(this.currentUser?.id || this.currentUser?.user?.id));
            if (self) {
              this.activeConversation.is_active = self.is_active;
              this.activeConversation.joined_at = self.joined_at;
              this.activeConversation.left_at = self.left_at;
            }
          }
          this.scrollToBottom();
          chatService.markAsRead(currentConvId).catch(() => {});
        }).catch((err) => {
          console.error("Lỗi getMessages API", err);
        });
      } catch (err) {
        console.error("Không thể tải danh sách tin nhắn", err);
      }
    },
    loadSavedLocalAiMessages() {
      if (!this.activeConversation?.is_ai && String(this.activeConversation?.id) !== "ai_assistant") return;
      try {
        const saved = localStorage.getItem("sportgo_ai_messages");
        if (saved) {
          const parsed = JSON.parse(saved);
          if (Array.isArray(parsed) && parsed.length > 0) {
            this.messages = parsed.map((m) => ({
              ...m,
              sender_id: (m.sender_id === "me" || m.role === "user") ? "me" : "ai_assistant",
            }));
            this.scrollToBottom();
            return;
          }
        }
      } catch (e) {
        console.warn("Lỗi đọc lịch sử chat AI local", e);
      }

      this.messages = [
        {
          id: "ai_welcome",
          sender_id: "ai_assistant",
          role: "assistant",
          content: "Xin chào! Tôi là Trợ lý AI của SportGo. Bạn cần hỗ trợ tìm kiếm sân đấu, tư vấn khung giờ chơi hay giải đáp thắc mắc nào hôm nay?",
          created_at: new Date().toISOString(),
        },
      ];
      this.saveAiMessages();
    },
    saveAiMessages() {
      try {
        localStorage.setItem("sportgo_ai_messages", JSON.stringify(this.messages));
      } catch (e) {
        console.warn("Lỗi lưu lịch sử chat AI", e);
      }
    },
    async sendMessage() {
      if (!this.canSendChat) return;
      const text = this.inputContent.trim();
      if (!text && !this.selectedFile) return;

      const replyId = this.replyingToMessage?.id || null;

      // Handle AI Conversation
      if (this.activeConversation?.is_ai) {
        const userMsg = {
          id: "user_" + Date.now(),
          sender_id: "me",
          role: "user",
          content: text,
          created_at: new Date().toISOString(),
        };
        this.messages.push(userMsg);
        this.inputContent = "";
        this.showEmojiPicker = false;
        this.saveAiMessages();
        this.scrollToBottom();

        this.sendingAi = true;
        try {
          const res = await chatService.askAiAssistant({
            prompt: text,
            booking_id: this.attachedBooking?.id || null,
          });

          const replyText = res?.reply || res?.data?.reply;
          if (replyText) {
            this.messages.push({
              id: "ai_" + Date.now(),
              sender_id: "ai_assistant",
              role: "assistant",
              content: replyText,
              created_at: new Date().toISOString(),
            });
            this.playNotificationSound();
          } else if (res?.message) {
            this.messages.push({
              id: "ai_err_" + Date.now(),
              sender_id: "ai_assistant",
              role: "assistant",
              content: res.message,
              created_at: new Date().toISOString(),
            });
          }
          this.saveAiMessages();
          this.scrollToBottom();
        } catch (e) {
          console.error("Lỗi kết nối AI:", e);
          this.messages.push({
            id: "ai_err_" + Date.now(),
            sender_id: "ai_assistant",
            role: "assistant",
            content: "Không thể kết nối tới máy chủ AI. Vui lòng thử lại sau giây lát.",
            created_at: new Date().toISOString(),
          });
          this.saveAiMessages();
          this.scrollToBottom();
        } finally {
          this.sendingAi = false;
        }
        return;
      }

      // Handle Regular Chat
      try {
        const res = await chatService.sendMessage(
          this.activeConversation.id,
          text,
          this.selectedFile,
          replyId
        );
        const newMsg = res?.data || res;
        if (newMsg && (newMsg.id || newMsg.content)) {
          const myUser = this.currentUser?.user || this.currentUser || {};
          const msgObj = {
            ...newMsg,
            sender_id: newMsg.sender_id || myUser.id || "me",
            sender: newMsg.sender || {
              id: myUser.id,
              full_name: myUser.full_name || myUser.username || "Tôi",
              avatar_url: myUser.avatar_url || null,
            },
          };
          if (!this.messages.some((m) => String(m.id) === String(msgObj.id))) {
            this.messages.push(msgObj);
          }
        }

        // Update local conversation last message preview
        if (this.activeConversation) {
          this.activeConversation.last_message = { content: text || "[Hình ảnh]", created_at: new Date().toISOString() };
          this.activeConversation.last_message_at = new Date().toISOString();
        }

        this.inputContent = "";
        this.selectedFile = null;
        this.replyingToMessage = null;
        this.showEmojiPicker = false;
        this.scrollToBottom();
      } catch (err) {
        console.error("Lỗi gửi tin nhắn", err);
      }
    },
    sendQuickChip(chipText) {
      this.inputContent = chipText;
      this.sendMessage();
    },
    handleFileSelect(e) {
      const file = e.target.files[0];
      if (file) this.selectedFile = file;
    },
    setReplyMessage(msg) {
      this.replyingToMessage = msg;
      this.activeMsgMenuId = null;
    },
    async toggleReaction(msg, emoji) {
      this.activeMsgMenuId = null;
      try {
        const res = await chatService.reactToMessage(msg.id, emoji);
        if (res?.reactions) {
          msg.reactions = res.reactions;
        }
      } catch (err) {
        console.error("Lỗi thả cảm xúc", err);
      }
    },
    groupReactions(reactions) {
      if (!reactions || !Array.isArray(reactions)) return {};
      const grouped = {};
      const currentUid = String(this.currentUser?.id || "");
      reactions.forEach((r) => {
        const em = r.emoji;
        if (!grouped[em]) {
          grouped[em] = { count: 0, userReacted: false };
        }
        grouped[em].count++;
        if (String(r.user_id) === currentUid) {
          grouped[em].userReacted = true;
        }
      });
      return grouped;
    },
    async togglePin(msg) {
      this.activeMsgMenuId = null;
      try {
        const res = await chatService.togglePinMessage(msg.id);
        msg.is_pinned = res.is_pinned;
      } catch (err) {
        console.error("Lỗi ghim tin nhắn", err);
      }
    },
    canRecallMessage(msg) {
      if (!msg || msg.is_recalled) return false;
      const created = new Date(msg.created_at).getTime();
      const diffMinutes = (Date.now() - created) / (1000 * 60);
      return diffMinutes <= 60; // Allowed within 60 minutes
    },
    async recallMessage(msg) {
      this.activeMsgMenuId = null;
      try {
        await chatService.recallMessage(msg.id);
        msg.is_recalled = true;
        msg.content = "Tin nhắn đã bị thu hồi";
        msg.image_url = null;
        msg.is_pinned = false;
      } catch (err) {
        console.error("Lỗi thu hồi tin nhắn", err);
      }
    },
    async deleteForSelf(msg) {
      this.activeMsgMenuId = null;
      try {
        await chatService.deleteMessageForSelf(msg.id);
        this.messages = this.messages.filter((m) => m.id !== msg.id);
      } catch (err) {
        console.error("Lỗi xóa tin nhắn", err);
      }
    },
    async clearChatHistory() {
      this.showChatActionsMenu = false;
      if (!confirm("Bạn có chắc chắn muốn xóa toàn bộ lịch sử tin nhắn trong cuộc trò chuyện này?")) return;
      try {
        await chatService.clearConversation(this.activeConversation.id);
        this.messages = [];
      } catch (err) {
        console.error("Lỗi xóa lịch sử trò chuyện", err);
      }
    },
    scrollToMessage(msgId) {
      if (!msgId) return;
      const el = document.getElementById(`msg-${msgId}`);
      if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "center" });
        el.classList.add("cc-msg-highlight");
        setTimeout(() => el.classList.remove("cc-msg-highlight"), 1500);
      }
    },
    async openBookingPicker() {
      if (!this.activeConversation || this.activeConversation.is_ai) return;
      this.showBookingPickerModal = true;
      this.loadingEligibleBookings = true;
      this.selectedBookingForSupport = null;
      try {
        const res = await chatService.getEligibleBookings(this.activeConversation.id);
        this.eligibleBookings = res || [];
        if (this.eligibleBookings.length > 0) {
          this.selectedBookingForSupport = this.eligibleBookings[0];
        }
      } catch (err) {
        console.error("Lỗi tải danh sách booking", err);
        this.eligibleBookings = [];
      } finally {
        this.loadingEligibleBookings = false;
      }
    },
    async sendBookingCardDirectly(booking) {
      if (!this.activeConversation || !booking) return;
      try {
        const res = await chatService.sendBooking(this.activeConversation.id, booking.id);
        if (!this.messages.some((m) => m.id === res.id)) {
          this.messages.push(res);
        }
        this.scrollToBottom();
      } catch (err) {
        console.error("Lỗi gửi thẻ booking", err);
      }
    },
    async submitSupportRequest() {
      if (!this.activeConversation || !this.selectedBookingForSupport) return;
      this.submittingSupport = true;
      try {
        const res = await chatService.createBookingSupportRequest(this.activeConversation.id, {
          booking_id: this.selectedBookingForSupport.id,
          request_type: this.supportRequestType,
          note: this.supportRequestNote.trim() || null,
        });
        if (!this.messages.some((m) => m.id === res.id)) {
          this.messages.push(res);
        }
        this.showBookingPickerModal = false;
        this.supportRequestNote = "";
        this.scrollToBottom();
      } catch (err) {
        console.error("Lỗi tạo yêu cầu hỗ trợ", err);
      } finally {
        this.submittingSupport = false;
      }
    },
    async loadVenueBookingsHistory(convId) {
      this.loadingVenueBookings = true;
      try {
        const res = await chatService.getRelatedBookings(convId);
        this.venueBookingsHistory = res || [];
      } catch (err) {
        this.venueBookingsHistory = [];
      } finally {
        this.loadingVenueBookings = false;
      }
    },
    openLightbox(url) {
      this.lightboxImage = url;
      this.lightboxZoom = 1;
      this.lightboxRotation = 0;
    },
    closeLightbox() {
      this.lightboxImage = null;
    },
    isMyMessage(msg) {
      if (!msg) return false;
      if (
        msg.sender_id === "ai_assistant" ||
        msg.role === "assistant" ||
        msg.role === "system" ||
        msg.is_ai
      ) {
        return false;
      }
      if (
        msg.sender_id === "me" ||
        msg.role === "user" ||
        msg.is_user
      ) {
        return true;
      }
      if (this.currentUser?.id && String(msg.sender_id) === String(this.currentUser.id)) {
        return true;
      }
      if (this.activeConversation?.is_ai && msg.sender_id !== "ai_assistant") {
        return true;
      }
      return String(msg.sender_id) === String(this.currentUser?.id);
    },
    renderContent(msg) {
      const raw = msg.content || "";

      // Process line-by-line to correctly handle lists and paragraphs
      const lines = raw.split("\n");
      const outputParts = [];
      let inOrderedList = false;
      let inBulletList = false;

      const escapeLine = (str) =>
        str
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;");

      const applyInline = (str) => {
        // Bold **text**
        str = str.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        // Bold __text__
        str = str.replace(/__([^_]+)__/g, "<strong>$1</strong>");
        // Inline code
        str = str.replace(/`([^`]+)`/g, "<code>$1</code>");
        return str;
      };

      const closeLists = () => {
        if (inOrderedList) { outputParts.push("</ol>"); inOrderedList = false; }
        if (inBulletList)  { outputParts.push("</ul>"); inBulletList = false; }
      };

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // Numbered list: "1. " "2. " etc.
        const numMatch = line.match(/^(\d+)\. (.+)$/);
        if (numMatch) {
          if (inBulletList) { outputParts.push("</ul>"); inBulletList = false; }
          if (!inOrderedList) { outputParts.push('<ol class="cc-ordered-list">'); inOrderedList = true; }
          outputParts.push('<li class="cc-list-item">' + applyInline(escapeLine(numMatch[2])) + "</li>");
          continue;
        }

        // Bullet list: "- " or "* "
        const bulletMatch = line.match(/^[-*] (.+)$/);
        if (bulletMatch) {
          if (inOrderedList) { outputParts.push("</ol>"); inOrderedList = false; }
          if (!inBulletList) { outputParts.push('<ul class="cc-unordered-list">'); inBulletList = true; }
          outputParts.push('<li class="cc-list-item">' + applyInline(escapeLine(bulletMatch[1])) + "</li>");
          continue;
        }

        // Empty line = paragraph break
        if (line.trim() === "") {
          closeLists();
          outputParts.push("<br>");
          continue;
        }

        // Normal text line
        closeLists();
        outputParts.push(applyInline(escapeLine(line)) + "<br>");
      }

      closeLists();

      // Trim trailing <br> tags
      let html = outputParts.join("");
      html = html.replace(/(<br>\s*)+$/, "");
      return html;
    },
    scrollToBottom() {
      this.$nextTick(() => {
        const el = this.$refs.messagesFeed;
        if (el) el.scrollTop = el.scrollHeight;
      });
    },
    getInitial(name) {
      if (!name) return "S";
      return String(name).charAt(0).toUpperCase();
    },
    getAvatarColor(name) {
      if (!name) return "#15803d";
      const colors = ["#f97316", "#10b981", "#3b82f6", "#8b5cf6", "#ec4899", "#06b6d4"];
      let hash = 0;
      for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
      }
      return colors[Math.abs(hash) % colors.length];
    },
    formatDate(dateStr) {
      if (!dateStr) return "-";
      return new Date(dateStr).toLocaleDateString("vi-VN");
    },
    formatTime(timeStr) {
      if (!timeStr) return "";
      if (timeStr.includes("T")) {
        const d = new Date(timeStr);
        return d.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit", hour12: false });
      }
      return timeStr.substring(0, 5);
    },
    formatDateTime(value) {
      if (!value) return 'chưa rõ';
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
    },
    formatSupportType(type) {
      const map = {
        reschedule: "Đổi giờ chơi",
        change_court: "Đổi sân con",
        cancel_booking: "Hủy đơn & hoàn tiền",
        late_arrival: "Báo đến muộn",
        payment: "Thanh toán",
        other: "Hỗ trợ khác",
      };
      return map[type] || type;
    },
    formatSupportStatus(status) {
      const map = {
        pending: "Đang chờ sân xử lý",
        acknowledged: "Sân đã tiếp nhận",
        resolved: "Đã xử lý xong",
        rejected: "Sân từ chối",
      };
      return map[status] || status;
    },
    formatBookingStatus(status) {
      const map = {
        confirmed: "Đã xác nhận",
        pending: "Chờ thanh toán",
        completed: "Đã hoàn thành",
        cancelled: "Đã hủy",
      };
      return map[status] || status;
    },
    closeCustomDropdowns(e) {
      if (!e || !e.target) return;
      if (!e.target.closest(".cc-custom-select")) {
        this.isBookingSelectOpen = false;
        this.isTypeSelectOpen = false;
      }
      if (!e.target.closest(".cc-emoji-popover") && !e.target.closest(".cc-btn-input-action")) {
        this.showEmojiPicker = false;
      }
      if (!e.target.closest(".cc-menu-wrap")) {
        this.showChatActionsMenu = false;
      }
    },
  },
};
</script>

<style scoped>
.cc-page {
  height: 100vh;
  max-height: 100vh;
  overflow: hidden;
  background: #ffffff;
  display: flex;
  flex-direction: column;
}

.cc-page *,
.cc-page span,
.cc-page p,
.cc-page strong,
.cc-page button,
.cc-page input,
.cc-page select,
.cc-page textarea {
  font-weight: 400 !important;
  background-image: none !important;
}

.cc-main {
  flex: 1;
  max-width: 1300px;
  width: 100%;
  margin: 0 auto;
  padding: 12px 16px;
  display: flex;
  overflow: hidden;
  min-height: 0;
}

.cc-container {
  display: grid;
  grid-template-columns: 320px 1fr;
  width: 100%;
  height: 100%;
  min-height: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  background: #ffffff;
  transition: all 0.2s ease;
}

.cc-container--with-info {
  grid-template-columns: 300px 1fr 280px;
}

@media (max-width: 900px) {
  .cc-container {
    grid-template-columns: 1fr;
  }
  .cc-container--with-info {
    grid-template-columns: 1fr;
  }
  .cc-sidebar--hidden-mobile {
    display: none !important;
  }
  .cc-workspace--hidden-mobile {
    display: none !important;
  }
  .cc-venue-info-sidebar {
    display: none !important;
  }
}

/* SIDEBAR */
.cc-sidebar {
  border-right: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  position: relative;
}

.cc-sidebar-head {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cc-sidebar-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cc-sidebar-title {
  font-size: 17px;
  color: #0f172a;
  margin: 0;
}

.cc-sidebar-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

.cc-search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.cc-search-icon {
  position: absolute;
  left: 10px;
  color: #94a3b8;
}

.cc-search-input {
  width: 100%;
  padding: 8px 10px 8px 32px;
  font-size: 13px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
}

.cc-search-clear {
  position: absolute;
  right: 8px;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
}

.cc-filter-tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
}

.cc-tab {
  padding: 5px 10px;
  font-size: 12px;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  white-space: nowrap;
}

.cc-tab.active {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

/* USER SEARCH PANEL */
.cc-user-search-panel {
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cc-usp-title {
  font-size: 10.5px;
  color: #64748b;
  letter-spacing: 0.04em;
}

.cc-usp-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s;
}

.cc-usp-item:hover {
  background: #f0fdf4;
  border-color: #15803d;
}

.cc-usp-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.cc-usp-name {
  font-size: 13px;
  color: #0f172a;
}

.cc-usp-sub {
  font-size: 11px;
  color: #64748b;
}

.cc-usp-btn {
  font-size: 11.5px;
  color: #15803d;
}

.cc-conv-list {
  flex: 1;
  overflow-y: auto;
}

.cc-state-msg {
  padding: 24px;
  text-align: center;
  font-size: 13px;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.cc-conv-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: none;
  background: #ffffff;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}

.cc-conv-item:hover,
.cc-conv-item.active {
  background: #f8fafc;
}

.cc-avatar-wrap {
  position: relative;
  flex-shrink: 0;
  width: 38px;
  height: 38px;
}

.cc-avatar {
  width: 38px;
  height: 38px;
  max-width: 38px;
  max-height: 38px;
  border-radius: 50%;
  aspect-ratio: 1 / 1;
  flex-shrink: 0;
  background: #15803d;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  box-sizing: border-box;
  overflow: hidden;
}

.cc-avatar--ai {
  background: #0f172a;
}

.cc-avatar--saved {
  background: #0284c7;
}

.cc-avatar--sm {
  width: 32px;
  height: 32px;
  max-width: 32px;
  max-height: 32px;
  font-size: 12px;
  flex-shrink: 0;
}

.cc-avatar--xs {
  width: 24px;
  height: 24px;
  max-width: 24px;
  max-height: 24px;
  font-size: 10px;
  flex-shrink: 0;
}

.cc-avatar-img {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  aspect-ratio: 1 / 1;
  flex-shrink: 0;
  object-fit: cover;
}

.cc-online-dot {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #15803d;
  border: 2px solid #ffffff;
}

.cc-conv-info {
  flex: 1;
  min-width: 0;
}

.cc-conv-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.cc-conv-title {
  font-size: 13.5px;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cc-conv-time {
  font-size: 11px;
  color: #94a3b8;
}

.cc-conv-sub {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 2px;
}

.cc-conv-preview {
  font-size: 12.5px;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cc-unread-badge {
  background: #15803d;
  color: #ffffff;
  font-size: 10.5px;
  border-radius: 10px;
  padding: 1px 6px;
}

/* WORKSPACE */
.cc-workspace {
  display: flex;
  flex-direction: column;
  background: #ffffff;
  min-height: 0;
  overflow: hidden;
  height: 100%;
}

.cc-empty-workspace {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
  text-align: center;
  color: #475569;
}

.cc-empty-illustration {
  margin-bottom: 16px;
  display: block;
}

.cc-state-illustration {
  margin-bottom: 8px;
  display: block;
}

.cc-empty-title {
  font-size: 16px;
  color: #0f172a;
  margin: 0 0 6px;
}

.cc-empty-desc {
  font-size: 13px;
  color: #475569;
  margin: 0;
  max-width: 320px;
  line-height: 1.5;
}

.cc-chat-box {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  overflow: hidden;
  position: relative;
}

.cc-chat-head {
  padding: 10px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cc-head-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cc-back-btn {
  display: none;
  background: transparent;
  border: none;
  color: #15803d;
  font-size: 16px;
  cursor: pointer;
  padding: 0;
}

@media (max-width: 800px) {
  .cc-back-btn {
    display: inline-block;
  }
}

.cc-head-user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cc-head-name {
  font-size: 14.5px;
  color: #0f172a;
  margin: 0;
}

.cc-head-status {
  font-size: 11.5px;
  color: #64748b;
}

.cc-status-online {
  color: #15803d;
}

.cc-typing-text {
  color: #15803d;
  font-style: italic;
}

.cc-head-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.cc-btn-ghost-sm {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #15803d;
  background: transparent;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 4px 8px;
  text-decoration: none;
  cursor: pointer;
}

.cc-btn-ghost-sm:hover {
  background: #f8fafc;
  border-color: #15803d;
}

.cc-icon-btn {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 6px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cc-icon-btn:hover,
.cc-icon-btn.is-active {
  color: #15803d;
  background: #f8fafc;
}

/* PINNED BANNER */
.cc-pinned-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 16px;
  background: #f8fafc;
  font-size: 12px;
}

.cc-pinned-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  flex: 1;
  min-width: 0;
}

.cc-pinned-icon {
  color: #15803d;
  flex-shrink: 0;
}

.cc-pinned-text {
  display: flex;
  align-items: baseline;
  gap: 6px;
  min-width: 0;
}

.cc-pinned-author {
  color: #15803d;
  flex-shrink: 0;
}

.cc-pinned-snippet {
  color: #475569;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cc-pinned-close {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
}

/* MESSAGES FEED */
.cc-messages-feed {
  flex: 1;
  min-height: 0;
  padding: 16px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: #ffffff;
}

.cc-attached-card {
  background: #f8fafc;
  border-radius: 6px;
  padding: 10px 14px;
  font-size: 12.5px;
  border-left: 2px solid #15803d;
}

.cc-att-head {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #15803d;
  margin-bottom: 4px;
}

.cc-att-name {
  color: #0f172a;
}

.cc-att-sub {
  margin: 2px 0 0;
  color: #64748b;
  font-size: 11.5px;
}

/* MESSAGE ROWS */
.cc-msg-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  max-width: 78%;
  position: relative;
}

.cc-msg-row--me {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.cc-msg-row--other {
  align-self: flex-start;
}

.cc-msg-wrap {
  position: relative;
  display: flex;
  flex-direction: column;
  max-width: 100%;
}

.cc-group-sender-name {
  display: block;
  font-size: 11px;
  color: #15803d;
  margin-bottom: 2px;
}

.cc-msg-avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  aspect-ratio: 1 / 1;
  flex-shrink: 0;
  background: #0f172a;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 4px;
}

/* FLOATING HOVER ACTION BAR */
.cc-msg-hover-bar {
  display: none;
  position: absolute;
  top: -26px;
  right: 0;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 2px 6px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  align-items: center;
  gap: 4px;
  z-index: 10;
}

.cc-msg-row--me .cc-msg-hover-bar {
  right: 0;
}

.cc-msg-row--other .cc-msg-hover-bar {
  left: 0;
  right: auto;
}

.cc-msg-wrap:hover .cc-msg-hover-bar {
  display: flex;
}

.cc-reaction-quick-btn {
  background: transparent;
  border: none;
  font-size: 13px;
  cursor: pointer;
  padding: 1px 3px;
  transition: transform 0.1s;
}

.cc-reaction-quick-btn:hover {
  transform: scale(1.25);
}

.cc-action-icon-btn {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 2px 4px;
  display: flex;
  align-items: center;
}

.cc-action-icon-btn:hover {
  color: #15803d;
}

/* MESSAGE BUBBLE */
.cc-msg-bubble {
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13.5px;
  line-height: 1.45;
  position: relative;
  box-sizing: border-box;
}

.cc-msg-row--me .cc-msg-bubble {
  background: #15803d;
  color: #ffffff;
}

.cc-msg-row--other .cc-msg-bubble {
  background: #f8fafc;
  color: #0f172a;
}

.cc-msg-highlight .cc-msg-bubble {
  animation: pulseHighlight 1.5s ease;
}

@keyframes pulseHighlight {
  0%, 100% { box-shadow: 0 0 0 0 rgba(21, 128, 61, 0); }
  50% { box-shadow: 0 0 0 4px rgba(21, 128, 61, 0.35); }
}

.cc-recalled-text {
  display: flex;
  align-items: center;
  gap: 6px;
  font-style: italic;
  font-size: 12px;
  color: #94a3b8;
}

.cc-reply-quote {
  background: rgba(0, 0, 0, 0.05);
  border-left: 2px solid #ffffff;
  padding: 4px 8px;
  border-radius: 0 4px 4px 0;
  margin-bottom: 6px;
  cursor: pointer;
  font-size: 11.5px;
}

.cc-msg-row--other .cc-reply-quote {
  border-left-color: #15803d;
  background: #ffffff;
}

.cc-reply-quote-sender {
  font-size: 11px;
  opacity: 0.85;
}

.cc-reply-quote-text {
  margin: 1px 0 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  opacity: 0.75;
}

/* BOOKING ACTION CARDS IN CHAT */
.cc-booking-request-card,
.cc-booking-share-card {
  background: #ffffff;
  border-radius: 6px;
  padding: 8px 10px;
  margin-bottom: 6px;
  color: #0f172a;
}

.cc-br-head,
.cc-bs-head {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #15803d;
  font-size: 12px;
  margin-bottom: 4px;
}

.cc-br-body,
.cc-bs-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.cc-br-type,
.cc-bs-code {
  color: #0f172a;
}

.cc-br-note {
  color: #475569;
  font-size: 11.5px;
}

.cc-br-status {
  margin-top: 4px;
  font-size: 11px;
  color: #15803d;
}

.cc-pinned-tag {
  position: absolute;
  top: 4px;
  right: 6px;
  color: #94a3b8;
}

.cc-msg-time {
  display: block;
  font-size: 10px;
  margin-top: 4px;
  opacity: 0.65;
  text-align: right;
}

/* REACTION PILLS */
.cc-msg-reactions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 3px;
}

.cc-reaction-pill {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px 6px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  font-size: 11px;
  color: #475569;
  cursor: pointer;
}

.cc-reaction-pill.is-my-reaction {
  border-color: #15803d;
  background: #f0fdf4;
}

.cc-reaction-count {
  font-size: 10.5px;
}

/* Rendered markdown inside bubbles */
.cc-msg-text :deep(strong) {
  font-weight: 500;
}

.cc-msg-text :deep(code) {
  background: rgba(0, 0, 0, 0.08);
  border-radius: 3px;
  padding: 1px 5px;
  font-family: monospace;
  font-size: 12.5px;
}

.cc-msg-row--me .cc-msg-text :deep(code) {
  background: rgba(255, 255, 255, 0.2);
}

.cc-msg-text :deep(.cc-ordered-list),
.cc-msg-text :deep(.cc-unordered-list) {
  margin: 6px 0 2px;
  padding-left: 20px;
}

.cc-msg-text :deep(.cc-ordered-list) {
  list-style-type: decimal;
}

.cc-msg-text :deep(.cc-unordered-list) {
  list-style-type: disc;
}

.cc-msg-text :deep(.cc-list-item) {
  margin-bottom: 3px;
  line-height: 1.5;
}

.cc-msg-img-wrap {
  margin-top: 6px;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
}

.cc-msg-img {
  max-width: 200px;
  max-height: 200px;
  display: block;
}

.cc-msg-bubble--typing {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 12px 16px;
}

.cc-typing-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #94a3b8;
  animation: typing 1s infinite alternate;
}

.cc-typing-dot:nth-child(2) {
  animation-delay: 0.2s;
}

.cc-typing-dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing {
  from { opacity: 0.3; }
  to { opacity: 1; }
}

/* QUICK CHIPS */
.cc-quick-chips {
  padding: 6px 16px;
  display: flex;
  gap: 8px;
  overflow-x: auto;
  background: #ffffff;
}

.cc-chip-btn {
  padding: 4px 12px;
  font-size: 12px;
  border-radius: 16px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  white-space: nowrap;
}

.cc-chip-btn:hover {
  border-color: #15803d;
  color: #15803d;
}

/* REPLY BAR (Above input) */
.cc-reply-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 16px;
  background: #f8fafc;
  font-size: 12px;
}

.cc-reply-bar-info {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.cc-reply-icon {
  color: #15803d;
  flex-shrink: 0;
}

.cc-reply-label {
  color: #15803d;
  font-size: 11px;
}

.cc-reply-preview-text {
  margin: 0;
  color: #475569;
  font-size: 11.5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cc-reply-cancel-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
}

/* EMOJI POPOVER */
.cc-emoji-popover {
  position: absolute;
  bottom: 58px;
  left: 16px;
  width: 320px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
  display: flex;
  flex-direction: column;
  z-index: 50;
  overflow: hidden;
}

.cc-ep-tabs {
  display: flex;
  gap: 4px;
  padding: 8px 10px;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}

.cc-ep-tab {
  flex: 1;
  background: transparent;
  border: none;
  font-size: 16px;
  cursor: pointer;
  padding: 4px 0;
  border-radius: 4px;
}

.cc-ep-tab.active,
.cc-ep-tab:hover {
  background: #ffffff;
}

.cc-ep-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  padding: 10px;
  max-height: 180px;
  overflow-y: auto;
}

.cc-ep-item {
  background: transparent;
  border: none;
  font-size: 18px;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.1s;
}

.cc-ep-item:hover {
  background: #f1f5f9;
  transform: scale(1.15);
}

/* INPUT BAR */
.cc-input-bar {
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
}

.cc-attach-btn,
.cc-btn-input-action {
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  background: transparent;
  border: none;
  padding: 0;
}

.cc-attach-btn:hover,
.cc-btn-input-action:hover,
.cc-btn-input-action.is-active {
  color: #15803d;
}

.cc-file-input {
  display: none;
}

.cc-file-preview {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  background: #f8fafc;
  padding: 4px 8px;
  border-radius: 4px;
}

.cc-file-preview button {
  background: transparent;
  border: none;
  color: #ef4444;
  cursor: pointer;
}

.cc-chat-input {
  flex: 1;
  padding: 8px 14px;
  font-size: 13px;
  border: 1px solid #cbd5e1;
  border-radius: 20px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
}

.cc-chat-input:focus {
  border-color: #15803d;
}

.cc-send-btn {
  padding: 7px 16px;
  font-size: 13px;
  border-radius: 18px;
  border: none;
  background: #15803d;
  color: #ffffff;
  cursor: pointer;
}

.cc-send-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* DROPDOWN MENUS */
.cc-menu-wrap {
  position: relative;
}

.cc-dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  padding: 4px;
  min-width: 170px;
  z-index: 20;
}

.cc-dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  background: transparent;
  border: none;
  font-size: 12.5px;
  color: #334155;
  cursor: pointer;
  border-radius: 4px;
}

.cc-dropdown-item:hover {
  background: #f8fafc;
}

.cc-dropdown-item--danger {
  color: #ef4444;
}

.cc-msg-dropdown {
  position: absolute;
  bottom: 100%;
  right: 0;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  padding: 4px;
  min-width: 150px;
  z-index: 25;
}

.cc-msg-dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  background: transparent;
  border: none;
  font-size: 12px;
  color: #334155;
  cursor: pointer;
  border-radius: 4px;
}

.cc-msg-dropdown-item:hover {
  background: #f8fafc;
}

/* VENUE INFO SIDEBAR */
.cc-venue-info-sidebar {
  border-left: 1px solid #e2e8f0;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.cc-vis-head {
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cc-vis-title {
  font-size: 14px;
  color: #0f172a;
  margin: 0;
}

.cc-vis-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.cc-vis-hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 4px;
}

.cc-vis-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: #15803d;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-bottom: 6px;
}

.cc-vis-name {
  font-size: 15px;
  color: #0f172a;
}

.cc-vis-sub {
  font-size: 11.5px;
  color: #64748b;
}

.cc-vis-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cc-vis-section-label {
  font-size: 11px;
  color: #94a3b8;
  letter-spacing: 0.04em;
}

.cc-vis-loading,
.cc-vis-empty {
  font-size: 12px;
  color: #64748b;
  padding: 10px 0;
}

.cc-vis-bookings-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cc-vis-booking-item {
  background: #f8fafc;
  border-radius: 6px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
}

.cc-vis-booking-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cc-vis-booking-code {
  color: #0f172a;
}

.cc-vis-booking-status {
  font-size: 11px;
  color: #15803d;
}

.cc-vis-booking-time {
  margin: 0;
  color: #64748b;
  font-size: 11px;
}

.cc-vis-booking-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 6px;
}

.cc-vis-link {
  color: #15803d;
  font-size: 11.5px;
  text-decoration: none;
}

.cc-vis-btn {
  background: transparent;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 11px;
  color: #475569;
  cursor: pointer;
  padding: 2px 6px;
}

/* MODALS */
.cc-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 16px;
}

.cc-modal-card {
  background: #ffffff;
  border-radius: 8px;
  width: 100%;
  max-width: 500px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  position: relative;
}

.cc-modal-head {
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cc-modal-title {
  font-size: 15px;
  color: #0f172a;
  margin: 0;
}

.cc-modal-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-height: 75vh;
  overflow-y: auto;
}

.cc-modal-loading,
.cc-modal-empty {
  padding: 20px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
}

.cc-form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
  position: relative;
}

.cc-form-label {
  font-size: 12.5px;
  color: #475569;
}

.cc-form-input,
.cc-form-file {
  width: 100%;
  padding: 8px 10px;
  font-size: 13px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
  box-sizing: border-box;
}

.cc-form-input:focus {
  border-color: #15803d;
}

.cc-chips-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}

.cc-member-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #15803d;
  font-size: 12px;
  border-radius: 4px;
}

.cc-member-chip button {
  background: transparent;
  border: none;
  color: #15803d;
  cursor: pointer;
}

.cc-group-search-results {
  max-height: 180px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 4px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cc-gsr-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: left;
  border-radius: 4px;
}

.cc-gsr-item:hover:not(:disabled) {
  background: #f8fafc;
}

.cc-gsr-item:disabled {
  opacity: 0.6;
  cursor: default;
}

.cc-gsr-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.cc-gsr-name {
  font-size: 12.5px;
  color: #0f172a;
}

.cc-gsr-sub {
  font-size: 11px;
  color: #64748b;
}

.cc-gsr-add-btn {
  font-size: 12px;
  color: #15803d;
}

.cc-gsr-added {
  font-size: 11.5px;
  color: #94a3b8;
}

/* CUSTOM DROPDOWN SELECT */
.cc-custom-select {
  position: relative;
  width: 100%;
}

.cc-select-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 9px 12px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 13px;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
  box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.cc-custom-select.is-open .cc-select-trigger,
.cc-select-trigger:focus {
  border-color: #15803d;
  outline: none;
}

.cc-select-val {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
  color: #0f172a;
  font-size: 13px;
}

.cc-select-chevron {
  color: #64748b;
  flex-shrink: 0;
  transition: transform 0.2s ease, color 0.15s;
}

.cc-select-chevron.is-flipped {
  transform: rotate(180deg);
  color: #15803d;
}

.cc-select-menu {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  max-height: 220px;
  overflow-y: auto;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  z-index: 100;
  padding: 4px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cc-select-opt {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.12s;
}

.cc-select-opt:hover {
  background: #f8fafc;
}

.cc-select-opt.is-selected {
  background: #f0fdf4;
}

.cc-opt-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.cc-opt-title {
  font-size: 13px;
  color: #0f172a;
}

.cc-select-opt.is-selected .cc-opt-title {
  color: #15803d;
}

.cc-opt-sub {
  font-size: 11.5px;
  color: #64748b;
}

.cc-opt-check {
  color: #15803d;
  flex-shrink: 0;
}

.cc-form-textarea {
  width: 100%;
  padding: 8px 10px;
  font-size: 13px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
  box-sizing: border-box;
}

.cc-form-textarea:focus {
  border-color: #15803d;
}

.cc-modal-footer {
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.cc-btn-ghost {
  padding: 6px 12px;
  font-size: 12.5px;
  background: transparent;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  color: #475569;
  cursor: pointer;
}

.cc-btn-primary {
  padding: 6px 14px;
  font-size: 12.5px;
  background: #15803d;
  border: none;
  border-radius: 4px;
  color: #ffffff;
  cursor: pointer;
}

.cc-btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* LIGHTBOX */
.cc-lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  flex-direction: column;
  z-index: 60;
}

.cc-lightbox-toolbar {
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.cc-lightbox-btn {
  background: rgba(255, 255, 255, 0.15);
  border: none;
  color: #ffffff;
  width: 32px;
  height: 32px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  text-decoration: none;
}

.cc-lightbox-btn:hover {
  background: rgba(255, 255, 255, 0.25);
}

.cc-lightbox-zoom-val {
  color: #ffffff;
  font-size: 12px;
  min-width: 44px;
  text-align: center;
}

.cc-lightbox-stage {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: 20px;
}

.cc-lightbox-img {
  max-width: 90%;
  max-height: 90%;
  object-fit: contain;
  transition: transform 0.2s ease;
}

.cc-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #cbd5e1;
  border-top-color: #15803d;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
  .cc-hide-mobile {
    display: none;
  }
}
.cc-group-left-note { width: 100%; margin-bottom: 8px; padding: 7px 10px; border: 1px solid #fde68a; border-radius: 7px; background: #fffbeb; color: #92400e; font-size: 12px; }

/* WIDE 2-COLUMN GROUP MODAL */
.cc-modal-card--wide {
  width: 92%;
  max-width: 720px;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.12);
  background: #ffffff;
}

.cc-modal-body--grid {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 28px;
  padding: 24px;
}

.cc-group-side-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  border-right: 1px solid #f1f5f9;
  padding-right: 24px;
}

.cc-vis-hero--stacked {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
}

.cc-vis-avatar--lg {
  width: 72px;
  height: 72px;
  max-width: 72px;
  max-height: 72px;
  font-size: 28px;
  font-weight: 700;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  overflow: hidden;
  flex-shrink: 0;
}

.cc-avatar-img {
  width: 100% !important;
  height: 100% !important;
  max-width: 100% !important;
  max-height: 100% !important;
  object-fit: cover !important;
  border-radius: 50% !important;
  display: block !important;
  flex-shrink: 0 !important;
}

.cc-vis-name {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.cc-vis-sub {
  font-size: 12.5px;
  color: #64748b;
}

.cc-group-side-actions {
  margin-top: auto;
  width: 100%;
  padding-top: 16px;
}

.cc-btn-outline-danger {
  width: 100%;
  padding: 9px 14px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #ffffff;
  color: #dc2626;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.cc-btn-outline-danger:hover {
  background: #fef2f2;
  border-color: #fca5a5;
}

/* RIGHT COLUMN: FLAT FRAMELESS LIST */
.cc-group-main-content {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.cc-participants-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  width: 100%;
}

.cc-participants-title {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap !important;
  margin: 0 !important;
  padding: 0 !important;
  line-height: 1.4;
  display: inline-block;
}

.cc-btn-add-member-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 7px;
  background: #15803d;
  color: #ffffff;
  font-size: 12.5px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: background 0.18s ease;
}

.cc-btn-add-member-trigger:hover {
  background: #166534;
}

.cc-add-member-box {
  margin-bottom: 12px;
}

.cc-input-sm {
  padding: 8px 12px;
  font-size: 13px;
  border-radius: 7px;
  border: 1px solid #cbd5e1;
  width: 100%;
  box-sizing: border-box;
}

.cc-add-member-results {
  max-height: 140px;
  overflow-y: auto;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  margin-top: 4px;
  background: #ffffff;
}

.cc-add-user-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
}

.cc-add-user-row:hover {
  background-color: #f8fafc;
}

.cc-aur-name {
  flex: 1;
  font-size: 13px;
  font-weight: 500;
  color: #334155;
}

.cc-aur-action {
  font-size: 12px;
  font-weight: 600;
  color: #15803d;
}

.cc-group-flat-list {
  display: flex;
  flex-direction: column;
  max-height: 320px;
  overflow-y: auto;
}

.cc-flat-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 0;
  border-bottom: 1px solid #f1f5f9;
}

.cc-flat-item:last-child {
  border-bottom: none;
}

.cc-flat-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.cc-flat-name {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.cc-flat-sub {
  font-size: 12px;
  color: #64748b;
}

.cc-flat-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.cc-badge-leader {
  font-size: 11.5px;
  font-weight: 600;
  color: #15803d;
  background: #dcfce7;
  padding: 3px 10px;
  border-radius: 9999px;
}

.cc-btn-member-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cc-btn-member-action:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}

.cc-btn-member-kick {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border-radius: 6px;
  border: 1px solid #fecaca;
  background: #ffffff;
  color: #dc2626;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cc-btn-member-kick:hover {
  background: #fef2f2;
  border-color: #fca5a5;
}

.cc-empty-participants {
  padding: 24px;
  text-align: center;
  font-size: 13px;
  color: #94a3b8;
}

@media (max-width: 640px) {
  .cc-modal-body--grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .cc-group-side-info {
    border-right: none;
    border-bottom: 1px solid #f1f5f9;
    padding-right: 0;
    padding-bottom: 16px;
  }
}

/* CONFIRM MODAL DIALOG */
.cc-confirm-card {
  max-width: 420px;
  padding: 28px 24px 22px;
  text-align: center;
  border-radius: 16px;
}

.cc-confirm-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  margin: 0 auto 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cc-confirm-icon--danger {
  background: #fee2e2;
  color: #dc2626;
}

.cc-confirm-title {
  margin: 0 0 8px;
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
}

.cc-confirm-desc {
  margin: 0 0 24px;
  font-size: 14px;
  line-height: 1.55;
  color: #64748b;
}

.cc-confirm-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.cc-confirm-actions button {
  flex: 1;
  min-height: 42px;
}

.cc-btn-primary--danger {
  background: #dc2626 !important;
  border-color: #dc2626 !important;
  color: #ffffff !important;
}

.cc-btn-primary--danger:hover {
  background: #b91c1c !important;
  border-color: #b91c1c !important;
}
</style>
