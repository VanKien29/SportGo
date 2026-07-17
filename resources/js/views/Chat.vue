<template>
  <div
    :class="[
      'chat-page flex flex-col font-sans',
      usesAdminChatTheme ? 'admin-chat-page admin-chat' : 'bg-zinc-950',
      isAdmin ? '' : 'client-chat-page min-h-screen text-zinc-100'
    ]"
    :data-admin-chat="usesAdminChatTheme ? '' : undefined"
  >
    <!-- Navbar -->
    <PublicNavbar v-if="!isAdmin" />

    <!-- Chat Workspace -->
    <!-- Chat Workspace -->
    <div
      :class="[
        'flex-1 flex overflow-hidden relative',
        usesAdminChatTheme ? 'admin-chat-workspace' : 'border-t border-zinc-800 h-[calc(100vh-64px)]'
      ]"
    >
      <!-- Telegram Menu Drawer Overlay -->
      <div
        v-if="showTelegramMenu"
        class="fixed inset-0 bg-transparent z-[9998]"
        @click="closeTelegramMenu"
      ></div>

      <!-- Telegram Menu Drawer Panel -->
      <div
        :class="[
          'fixed inset-y-0 left-0 w-[280px] z-[9999] flex flex-col transition-transform duration-300 tg-drawer-panel',
          showTelegramMenu ? 'translate-x-0' : '-translate-x-full'
        ]"
      >
        <!-- Drawer Profile Header -->
        <div class="tg-drawer-header">
          <div class="h-11 w-11 rounded-full bg-orange-500 flex items-center justify-center font-semibold text-sm text-white select-none">
            {{ (currentUser?.full_name || currentUser?.username || 'U').charAt(0).toUpperCase() }}
          </div>

          <div class="flex items-center justify-between mt-1.5">
            <div class="min-w-0">
              <div class="font-semibold text-[13px] tg-drawer-header-name truncate">{{ currentUser?.full_name }}</div>
              <div class="text-[11px] tg-drawer-header-sub mt-0.5">{{ currentUser?.email || currentUser?.phone || '' }}</div>
            </div>
          </div>
        </div>

        <!-- Drawer Navigation Items -->
        <div class="flex-1 overflow-y-auto tg-drawer-nav">
          <div class="py-2 px-2">
            <!-- Tin nhắn đã lưu (Saved Messages - self chat) -->
            <button @click="openSavedMessages" class="tg-drawer-item text-left w-full">
              <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
              <span>Tin nhắn đã lưu</span>
            </button>

          </div>

          <!-- Divider -->
          <div class="tg-drawer-divider"></div>

          <!-- Theme Toggling Option -->
          <div v-if="!usesAdminChatTheme" class="tg-drawer-toggle-row">
            <div class="flex items-center gap-4 select-none">
              <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
              </svg>
              <span class="text-sm">Chế độ tối</span>
            </div>

            <!-- Custom Toggle Switch -->
            <button
              type="button"
              @click="toggleNightMode"
              class="tg-toggle-switch"
              :class="isNightMode ? 'tg-toggle-on' : 'tg-toggle-off'"
              aria-label="Chế độ tối"
            >
              <span
                class="tg-toggle-knob"
                :class="isNightMode ? 'tg-knob-on' : 'tg-knob-off'"
              ></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Left Sidebar: Chat List -->
      <div
        :class="[
          'w-full md:w-[360px] shrink-0 border-r border-zinc-800 flex flex-col h-full bg-zinc-900/50 backdrop-blur-md md:flex relative overflow-hidden',
          mobileShowChat ? 'hidden' : 'flex'
        ]"
      >
        <!-- Sidebar Header / Search -->
        <div class="p-3 flex items-center gap-2 tg-sidebar-header shrink-0">
          <button
            @click="toggleTelegramMenu"
            class="p-2 -ml-1 rounded-full text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/40 transition-colors shrink-0"
            aria-label="Menu"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <div class="relative flex-1">
            <BaseInput
              v-model="searchQuery"
              @input="handleSearch"
              placeholder="Tìm kiếm..."
              size="sm"
              no-ring
              custom-class="w-full rounded-full bg-zinc-950/60 border-zinc-800 text-xs placeholder-zinc-400 text-zinc-100 tg-search-input"
            />
          </div>
        </div>

        <!-- Search Results List -->
        <div v-if="searchQuery" class="flex-1 overflow-y-auto divide-y divide-zinc-800/40">
          <div v-if="searching" class="p-4 text-center text-xs text-zinc-500">
            Đang tìm kiếm...
          </div>
          <div v-else-if="searchResults.length === 0" class="p-4 text-center text-xs text-zinc-500">
            Không tìm thấy thành viên nào.
          </div>
          <button
            v-for="user in searchResults"
            :key="user.id"
            @click="clickSearchResult(user)"
            class="tg-conv-item w-full transition-all"
          >
            <div class="tg-avatar tg-avatar-small">
              {{ (user?.full_name || 'U').charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-medium text-sm text-zinc-200 truncate">{{ user.full_name }}</div>
              <div class="text-xs text-zinc-500 truncate">@{{ user.username || 'user' }}</div>
            </div>
          </button>
        </div>

        <!-- Main Conversations List -->
        <div v-else class="flex-1 overflow-y-auto divide-y divide-zinc-800/30">
          <div v-if="loadingConversations && conversations.length === 0" class="p-4 text-center text-xs text-zinc-500">
            Đang tải hộp thư...
          </div>
          <div v-else-if="filteredConversations.length === 0" class="chat-empty-sidebar">
            <div class="chat-empty-sidebar__title">Chưa có cuộc trò chuyện</div>
            <div class="chat-empty-sidebar__sub">Tìm kiếm thành viên để bắt đầu nhắn tin</div>
          </div>

          <button
            v-for="conv in filteredConversations"
            :key="conv.id"
            @click="selectConversation(conv)"
            :class="[
              'tg-conv-item w-full transition-all',
              activeConversation?.id === conv.id ? 'active' : ''
            ]"
          >
            <!-- Avatar -->
            <div class="tg-avatar">
              {{ (conv.title || 'U').charAt(0).toUpperCase() }}
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-1 mb-1">
                <div class="font-medium text-sm text-zinc-200 truncate flex-1">{{ conv.title }}</div>
                <div class="text-[10px] text-zinc-500 shrink-0">{{ formatTime(conv.last_message?.created_at || conv.last_message_at) }}</div>
              </div>
              <div class="flex items-center justify-between gap-2">
                <div class="text-xs text-zinc-400 truncate flex-1">
                  <span v-if="conv.last_message?.sender_id === currentUser?.id" class="text-green-500">Bạn: </span>
                  {{ conv.last_message?.content || 'Chưa có tin nhắn' }}
                </div>
                <!-- Badge count -->
                <div v-if="conv.unread_count > 0" class="h-5 min-w-5 px-1.5 bg-green-500 text-zinc-950 font-bold text-[10px] rounded-full flex items-center justify-center shrink-0">
                  {{ conv.unread_count }}
                </div>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Right Main Chat Workspace -->
      <div
        :class="[
          'flex-1 flex flex-col h-full bg-zinc-950 relative md:flex',
          mobileShowChat ? 'flex' : 'hidden'
        ]"
      >
        <!-- No Active Conversation state -->
        <div v-if="!activeConversation" class="chat-empty-main">
          <div class="chat-empty-main__title">Chọn cuộc trò chuyện</div>
          <div class="chat-empty-main__sub">Chọn từ danh sách bên trái hoặc tìm kiếm thành viên để bắt đầu nhắn tin</div>
        </div>

        <!-- Main Chat Area -->
        <div v-else class="flex-1 flex h-full relative overflow-hidden">

          <!-- Chat Messages Pane -->
          <div class="flex-1 flex flex-col h-full bg-zinc-950 relative min-w-0">
          <!-- Active Conversation Header -->
          <div class="tg-chat-header flex items-center justify-between shrink-0">
            <div @click="showProfileSidebar = !showProfileSidebar" class="flex items-center gap-3 min-w-0 cursor-pointer hover:opacity-90 select-none">
              <!-- Back button on Mobile -->
              <button
                @click.stop="backToList"
                class="p-2 -ml-2 rounded-lg text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/40 md:hidden transition-colors"
                aria-label="Back to conversations list"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
              </button>

              <div class="tg-avatar tg-avatar-small">
                {{ profileInitial }}
              </div>
              <div class="min-w-0">
                <div class="font-medium text-sm text-zinc-100 flex items-center gap-2 min-w-0">
                  <span class="truncate">{{ profileDisplayName }}</span>
                  <span v-if="activeConversation.type === 'venue_contact'" class="px-1.5 py-0.5 bg-green-500/10 text-green-400 text-[9px] font-bold rounded border border-green-500/20 uppercase tracking-wider shrink-0">
                    Sân đấu
                  </span>
                </div>
                <div class="text-[10px] text-zinc-500 flex items-center gap-1">
                  <span class="h-1.5 w-1.5 bg-green-500 rounded-full shrink-0"></span>
                  <span>{{ profileStatusText }}</span>
                </div>
              </div>
            </div>

            <!-- Header Action Controls -->
            <div class="flex items-center gap-1 relative">
              <!-- Sidebar Toggle Button -->
              <button
                @click="showProfileSidebar = !showProfileSidebar"
                class="p-2 rounded-lg text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/30 transition-colors"
                :class="{ 'text-zinc-200 bg-zinc-800/40': showProfileSidebar }"
                title="Thông tin hội thoại"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="18" height="18" rx="2" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v18" />
                </svg>
              </button>

              <button
                @click="showChatMenu = !showChatMenu"
                class="p-2 rounded-lg text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/30 transition-colors"
                title="Tùy chọn cuộc trò chuyện"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                </svg>
              </button>

              <!-- Chat Options Menu Dropdown -->
              <div
                v-if="showChatMenu"
                class="tg-dropdown-menu absolute right-0 top-11 w-56 z-50 py-1"
              >
                <!-- View profile -->
                <button @click="viewProfile" class="tg-dropdown-item">
                  <svg class="tg-dropdown-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  <span>Xem thông tin hội thoại</span>
                </button>

                <div class="tg-dropdown-divider"></div>

                <!-- Clear history -->
                <button @click="clearChatHistory" class="tg-dropdown-item">
                  <svg class="tg-dropdown-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  <span>Xóa lịch sử tin nhắn</span>
                </button>

                <!-- Delete chat -->
                <button @click="deleteActiveConversation" class="tg-dropdown-item tg-dropdown-item-danger">
                  <svg class="tg-dropdown-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  <span>Xóa cuộc trò chuyện</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Pinned Messages Banner -->
          <div
            v-if="pinnedMessages.length > 0"
            class="pinned-messages-banner pl-3 pr-4 py-2 border-b border-zinc-800/80 border-l-2 border-l-[var(--tg-accent,var(--admin-primary,#22a653))] bg-zinc-900/80 backdrop-blur-md text-sm shrink-0 flex items-center justify-between select-none"
          >
            <div class="flex items-center gap-2 min-w-0 cursor-pointer" @click="scrollToMessage(pinnedMessages[0].id)">
              <svg class="h-3.5 w-3.5 text-[var(--tg-accent,var(--admin-primary,#22a653))] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
              <div class="truncate">
                <span class="text-zinc-500 font-normal">Tin nhắn đã ghim: </span>
                <span class="text-zinc-300 font-normal">{{ pinnedMessages[0].content }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2.5">
              <button
                v-if="pinnedMessages.length > 1"
                type="button"
                class="text-zinc-400 hover:text-zinc-200 text-xs bg-zinc-800/50 hover:bg-zinc-850 px-2 py-0.5 rounded-full transition-colors border border-zinc-800/40 cursor-pointer font-normal"
                @click="scrollToMessage(pinnedMessages[pinnedMessages.length - 1].id)"
              >
                +{{ pinnedMessages.length - 1 }} tin nhắn khác
              </button>
              <button
                type="button"
                class="text-zinc-500 hover:text-zinc-300 p-1 rounded-full hover:bg-zinc-800/60 transition-colors bg-transparent border-0 cursor-pointer flex items-center justify-center"
                @click="handleTogglePin(pinnedMessages[0])"
                title="Bỏ ghim"
              >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Messages Scroll View Area -->
          <div ref="messageContainer" class="tg-message-container flex-1 overflow-y-auto bg-zinc-950">
            <div v-if="loadingMessages && messages.length === 0" class="text-center text-xs text-zinc-500 py-4">
              Đang tải tin nhắn...
            </div>

            <div v-else-if="messages.length === 0" class="text-center text-xs text-zinc-600 py-12 flex flex-col items-center justify-center">
              <div>Chưa có tin nhắn nào. Hãy gửi lời chào đầu tiên!</div>
            </div>

            <!-- Grouped Messages -->
            <div
              v-else
              v-for="group in groupedMessages"
              :key="group.date"
              class="space-y-4"
            >
              <!-- Date Divider Separator -->
              <div class="flex justify-center sticky top-0 z-10 py-3 my-2">
                <span class="tg-date-divider">
                  {{ group.date }}
                </span>
              </div>

              <!-- Message Bubbles -->
              <div
                v-for="msg in group.messages"
                :key="msg.id"
                :id="'msg-' + msg.id"
                :data-message-id="msg.id"
                :class="[
                  'bubble-row flex relative',
                  msg.sender_id === currentUser.id ? 'justify-end' : 'justify-start',
                  highlightedMessageId === msg.id ? 'bubble-row-highlight' : ''
                ]"
                @mouseenter="activeHoverMessageId = msg.id"
                @mouseleave="activeHoverMessageId = null; hoverReactionTargetId = null"
              >
                <div
                  :class="[
                    'bubble max-w-[70%] px-3 py-2 shadow-sm text-sm break-words relative group',
                    msg.sender_id === currentUser.id ? 'bubble-sent' : 'bubble-received'
                  ]"
                >
                  <!-- Quick Hover Action Toolbar (Positioned relative to .bubble) -->
                  <div
                    v-if="activeHoverMessageId === msg.id && !msg.is_system"
                    :class="[
                      'absolute bottom-2 flex items-center gap-1.5 z-10 transition-opacity duration-150',
                      msg.sender_id === currentUser.id ? 'right-full mr-3.5' : 'left-full ml-3.5'
                    ]"
                  >
                    <!-- Reply Button -->
                    <button
                      type="button"
                      class="bg-zinc-800 hover:bg-zinc-700 border border-zinc-700/50 rounded-full text-zinc-300 hover:text-zinc-100 transition-all w-7 h-7 flex items-center justify-center shadow-sm"
                      title="Trả lời"
                      @click.stop="handleReply(msg)"
                    >
                      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                      </svg>
                    </button>

                    <!-- React Button -->
                    <button
                      type="button"
                      class="bg-zinc-800 hover:bg-zinc-700 border border-zinc-700/50 rounded-full text-zinc-300 hover:text-zinc-100 transition-all relative w-7 h-7 flex items-center justify-center shadow-sm"
                      title="Bày tỏ cảm xúc"
                      @click.stop="toggleHoverReactionPicker(msg.id)"
                    >
                      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>

                      <!-- Emoji quick picker -->
                      <div
                        v-if="hoverReactionTargetId === msg.id"
                        class="absolute bottom-full mb-2 flex items-center bg-zinc-900 border border-zinc-800 rounded-full px-3 py-1.5 shadow-lg gap-2 z-20 left-1/2 -translate-x-1/2 whitespace-nowrap"
                      >
                        <button
                          v-for="emoji in ['👍', '❤️', '😂', '😮', '😢', '🙏']"
                          :key="emoji"
                          type="button"
                          class="hover:scale-130 active:scale-95 transition-all duration-150 text-lg hover:bg-zinc-800 rounded-full flex items-center justify-center w-8 h-8"
                          @click="submitReaction(msg, emoji)"
                        >
                          {{ emoji }}
                        </button>
                      </div>
                    </button>

                    <!-- More Options (3 dots) Button -->
                    <button
                      type="button"
                      class="context-menu-trigger bg-zinc-800 hover:bg-zinc-700 border border-zinc-700/50 rounded-full text-zinc-300 hover:text-zinc-100 transition-all w-7 h-7 flex items-center justify-center shadow-sm"
                      title="Tùy chọn khác"
                      @click.stop="openContextMenu($event, msg)"
                    >
                      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2" />
                        <circle cx="12" cy="12" r="2" />
                        <circle cx="12" cy="19" r="2" />
                      </svg>
                    </button>
                  </div>

                  <!-- Pinned state small icon -->
                  <div v-if="msg.is_pinned" class="absolute top-1 right-2 text-zinc-500 opacity-60" title="Tin nhắn đã ghim">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                    </svg>
                  </div>

                  <!-- Reply Quote Block -->
                  <div
                    v-if="msg.reply_to"
                    class="reply-quote-block mb-1.5 border-l-2 border-emerald-500 pl-2 py-0.5 bg-black/10 rounded-r text-[11px] cursor-pointer hover:bg-black/20 transition-colors select-none"
                    @click="scrollToMessage(msg.reply_to.id)"
                  >
                    <div class="reply-quote-block__sender font-medium text-emerald-500">
                      {{ msg.reply_to.sender?.full_name || 'Người dùng' }}
                    </div>
                    <div class="reply-quote-block__content text-zinc-400 truncate max-w-[200px]">
                      {{ msg.reply_to.content }}
                    </div>
                  </div>

                  <!-- Image Attachments -->
                  <div v-if="msg.image_url" class="mb-2 max-w-full rounded-lg overflow-hidden border border-black/10 cursor-pointer hover:opacity-95 transition-all" @click="openLightbox(msg.image_url, msg)">
                    <img :src="msg.image_url" class="max-h-60 w-auto object-contain max-w-full rounded-lg" alt="Hình ảnh" />
                  </div>

                  <!-- Booking Card Attachment -->
                  <div v-if="msg.reference_type === 'booking' && msg.booking" class="booking-message-card rounded-lg mb-1.5 overflow-hidden">
                    <div class="booking-message-card__top">
                      <span class="booking-message-card__eyebrow">Booking đặt sân</span>
                      <span class="booking-message-card__code">#{{ msg.booking.booking_code }}</span>
                    </div>
                    <div class="booking-message-card__venue">{{ bookingClusterName(msg.booking) }}</div>
                    <div class="booking-message-card__meta">
                      <span>Sân: {{ bookingCourtText(msg.booking) }}</span>
                      <span>Ngày: {{ bookingDateLabel(msg.booking.booking_date) }}</span>
                      <span>Giờ: {{ bookingTimeRange(msg.booking) }}</span>
                    </div>
                    <div class="booking-message-card__footer">
                      <span class="booking-message-card__amount">{{ bookingCurrency(msg.booking.total_price) }}</span>
                      <span :class="['booking-message-card__status', statusTextClass(msg.booking.status)]">{{ bookingStatusLabel(msg.booking.status) }}</span>
                    </div>
                    <div v-if="canCreateSupportRequest" class="booking-message-card__actions">
                      <button type="button" class="booking-message-card__action" @click="openSupportRequestModal(msg.booking)">
                        Y&#234;u c&#7847;u h&#7895; tr&#7907;
                      </button>
                    </div>
                  </div>

                  <!-- Support Request Card Attachment -->
                  <div v-if="msg.reference_type === 'booking_support_request' && msg.support_request" class="support-request-card rounded-lg mb-1.5 overflow-hidden">
                    <div class="support-request-card__top">
                      <span class="support-request-card__eyebrow">Y&#234;u c&#7847;u booking</span>
                      <span :class="['support-request-card__status', supportRequestStatusClass(msg.support_request.status)]">
                        {{ supportRequestStatusLabel(msg.support_request.status) }}
                      </span>
                    </div>
                    <div class="support-request-card__title">
                      {{ supportRequestTypeLabel(msg.support_request.request_type) }}
                      <span v-if="msg.support_request.booking" class="support-request-card__code">#{{ msg.support_request.booking.booking_code }}</span>
                    </div>
                    <div v-if="msg.support_request.booking" class="support-request-card__meta">
                      <span>{{ bookingCourtText(msg.support_request.booking) }}</span>
                      <span>{{ bookingDateLabel(msg.support_request.booking.booking_date) }}</span>
                      <span>{{ bookingTimeRange(msg.support_request.booking) }}</span>
                    </div>
                    <div v-if="msg.support_request.note" class="support-request-card__note">
                      {{ msg.support_request.note }}
                    </div>
                    <div v-if="msg.support_request.resolution_note" class="support-request-card__note support-request-card__note--resolution">
                      {{ msg.support_request.resolution_note }}
                    </div>
                    <div v-if="canHandleSupportRequest && msg.support_request.status === 'pending'" class="support-request-card__actions">
                      <button type="button" class="support-request-card__action" :disabled="updatingSupportRequestId === msg.support_request.id" @click="updateSupportRequestStatus(msg.support_request, 'acknowledged')">Ti&#7871;p nh&#7853;n</button>
                      <button type="button" class="support-request-card__action" :disabled="updatingSupportRequestId === msg.support_request.id" @click="updateSupportRequestStatus(msg.support_request, 'resolved')">Ho&#224;n t&#7845;t</button>
                      <button type="button" class="support-request-card__action support-request-card__action--danger" :disabled="updatingSupportRequestId === msg.support_request.id" @click="updateSupportRequestStatus(msg.support_request, 'rejected')">T&#7915; ch&#7889;i</button>
                    </div>
                    <div v-else-if="canHandleSupportRequest && msg.support_request.status === 'acknowledged'" class="support-request-card__actions">
                      <button type="button" class="support-request-card__action" :disabled="updatingSupportRequestId === msg.support_request.id" @click="updateSupportRequestStatus(msg.support_request, 'resolved')">Ho&#224;n t&#7845;t</button>
                      <button type="button" class="support-request-card__action support-request-card__action--danger" :disabled="updatingSupportRequestId === msg.support_request.id" @click="updateSupportRequestStatus(msg.support_request, 'rejected')">T&#7915; ch&#7889;i</button>
                    </div>
                  </div>

                  <!-- Content text -->
                  <div class="bubble-text">
                    <span v-if="msg.content !== '[Hình ảnh]' && msg.content !== '[H??nh ???nh]' && msg.reference_type !== 'booking' && msg.reference_type !== 'booking_support_request'">{{ msg.content }}</span>
                    <span class="bubble-meta">
                       <span class="bubble-time">{{ formatTimeOnly(msg.created_at) }}</span>

                       <!-- Read checkmarks logic for sent messages -->
                       <span v-if="msg.sender_id === currentUser?.id" class="bubble-ticks inline-flex">
                         <!-- Read (Double check) -->
                         <svg v-if="isMessageRead(msg)" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                         </svg>
                         <!-- Sent but unread (Single check) -->
                         <svg v-else class="h-3 w-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                         </svg>
                       </span>
                    </span>
                  </div>

                  <!-- Reactions List -->
                  <div
                    v-if="msg.reactions && msg.reactions.length > 0"
                    class="flex items-center flex-wrap gap-1 mt-1.5 self-start select-none"
                  >
                    <button
                      v-for="(count, emoji) in groupReactions(msg.reactions)"
                      :key="emoji"
                      type="button"
                      :class="[
                        'flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full border transition-all duration-150',
                        hasUserReacted(msg.reactions, emoji)
                          ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                          : 'bg-zinc-900/40 text-zinc-400 border-zinc-800/40 hover:bg-zinc-800/40 hover:border-zinc-700/50'
                      ]"
                      @click="submitReaction(msg, emoji)"
                    >
                      <span>{{ emoji }}</span>
                      <span>{{ count }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Bottom Message Input Editor -->
          <div class="tg-input-bar-container p-3 shrink-0 flex flex-col items-center bg-transparent">
            <!-- Hidden Image File Input -->
            <input
              type="file"
              ref="imageInput"
              accept="image/*"
              multiple
              class="hidden"
              @change="handleImagesSelected"
            />

            <form @submit.prevent="submitMessage" class="zalo-chat-box w-full max-w-3xl flex flex-col">
              <!-- Reply Active Banner -->
              <div v-if="replyTarget" class="flex items-center justify-between px-4 py-2 border-b border-zinc-800/40 bg-zinc-900/20 text-xs shrink-0 select-none">
                <div class="flex items-center gap-2 min-w-0">
                  <svg class="h-3.5 w-3.5 text-zinc-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                  </svg>
                  <div class="truncate">
                    <span class="text-zinc-500">Đang trả lời </span>
                    <span class="font-medium text-emerald-500">{{ replyTarget.sender?.full_name || 'Người dùng' }}</span>
                    <span class="text-zinc-400 ml-1.5">{{ replyTarget.content }}</span>
                  </div>
                </div>
                <button type="button" @click="replyTarget = null" class="text-zinc-400 hover:text-zinc-200 p-0.5 rounded transition-colors bg-transparent border-0 cursor-pointer">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Text input row -->
              <div class="zalo-input-row flex items-center px-4 py-2.5">
                <input
                  v-model="newMessage"
                  type="text"
                  placeholder="Nhập tin nhắn..."
                  @paste="handlePaste"
                  class="zalo-input w-full bg-transparent text-sm focus:outline-none"
                />

                <!-- Left Action: Share Booking -->
                <button
                  v-if="canShareBooking"
                  type="button"
                  @click="openBookingPicker"
                  class="zalo-attach-btn p-1.5 rounded-full transition-colors shrink-0 mr-1"
                  title="Chia sẻ booking"
                >
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                  </svg>
                </button>

                <!-- Left Action: File Attachment -->
                <button
                  type="button"
                  @click="clickAttachment"
                  class="zalo-attach-btn p-1.5 rounded-full transition-colors shrink-0 mr-1"
                  title="Thêm ảnh"
                >
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                  </svg>
                </button>

                <!-- Right Action: Circular Send Button -->
                <button
                  type="submit"
                  :disabled="!newMessage.trim() && selectedImageFiles.length === 0"
                  class="zalo-send-btn h-8 w-8 rounded-full transition-all shrink-0 flex items-center justify-center"
                >
                  <svg class="h-4 w-4 fill-current text-current" viewBox="0 0 24 24">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                  </svg>
                </button>
              </div>

              <!-- Divider -->
              <div v-if="selectedImageFiles.length > 0" class="zalo-divider mx-4"></div>

              <!-- Image Preview area (below text input) -->
              <div v-if="selectedImageFiles.length > 0" class="zalo-preview-area px-4 py-3 flex flex-col gap-2">
                <!-- Header of preview area -->
                <div class="flex items-center justify-between text-xs font-medium zalo-preview-header">
                  <span>{{ selectedImageFiles.length }} ảnh</span>
                  <button type="button" @click="clearSelectedImages" class="zalo-clear-all hover:text-red-500 transition-colors bg-transparent border-0 p-0 text-xs font-medium">
                    Xóa tất cả
                  </button>
                </div>

                <!-- Thumbnails list -->
                <div class="flex flex-wrap gap-2.5 items-center mt-1">
                  <div
                    v-for="(file, index) in selectedImageFiles"
                    :key="index"
                    class="relative w-14 h-14 rounded-lg overflow-hidden border zalo-thumb-item shrink-0 bg-black/20 group"
                  >
                    <img :src="imagePreviewUrls[index]" class="w-full h-full object-cover" />
                    <!-- Delete button -->
                    <button
                      type="button"
                      @click="removeSelectedImage(index)"
                      class="absolute top-0.5 right-0.5 bg-black/60 hover:bg-red-650 text-white p-0.5 rounded-full transition-all md:opacity-0 md:group-hover:opacity-100"
                      title="Xóa ảnh"
                    >
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>

                  <!-- Dashed Plus button to add more images -->
                  <button
                    type="button"
                    @click="clickAttachment"
                    class="w-14 h-14 border border-dashed zalo-plus-btn rounded-lg flex items-center justify-center transition-all shrink-0"
                    title="Thêm ảnh"
                  >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                  </button>
                </div>
              </div>
            </form>
          </div>
          </div>

          <div v-if="showBookingPicker" class="booking-picker-backdrop" @click.self="closeBookingPicker">
            <section class="booking-picker-panel" role="dialog" aria-modal="true" aria-label="Chọn booking để gửi">
              <header class="booking-picker-header">
                <div>
                  <h3>Gửi thông tin đặt sân</h3>
                  <p>Chọn đặt sân bạn đã đặt tại cụm sân này để gửi cho chủ sân/nhân viên.</p>
                </div>
                <button type="button" class="booking-picker-close" @click="closeBookingPicker" aria-label="Đóng">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </header>

              <div v-if="loadingBookingOptions" class="booking-picker-state">Đang tải danh sách đặt sân...</div>
              <div v-else-if="bookingPickerError" class="booking-picker-state booking-picker-state--error">{{ bookingPickerError }}</div>
              <div v-else-if="bookingOptions.length === 0" class="booking-picker-state flex flex-col items-center justify-center py-10 gap-3">
                <svg class="h-12 w-12 text-zinc-650" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
                <span>Không có lịch đặt sân phù hợp tại cụm sân này.</span>
              </div>
              <div v-else class="booking-picker-list">
                <div
                  v-for="booking in bookingOptions"
                  :key="booking.id"
                  class="booking-picker-row"
                >
                  <div class="booking-picker-row__main">
                    <div class="booking-picker-row__title">
                      <span class="booking-picker-row__code">#{{ booking.booking_code }}</span>
                      <span class="booking-picker-row__venue">{{ bookingClusterName(booking) }}</span>
                    </div>
                    <div class="booking-picker-row__details">
                      <span>{{ bookingCourtText(booking) }}</span>
                      <span class="bullet-dot">•</span>
                      <span>{{ bookingDateLabel(booking.booking_date) }}</span>
                      <span class="bullet-dot">•</span>
                      <span>{{ bookingTimeRange(booking) }}</span>
                    </div>
                  </div>
                  
                  <div class="booking-picker-row__side">
                    <span class="booking-picker-row__price">{{ bookingCurrency(booking.total_price) }}</span>
                    <span :class="['booking-picker-row__status', statusTextClass(booking.status)]">
                      {{ bookingStatusLabel(booking.status) }}
                    </span>
                  </div>

                  <div class="booking-picker-row__actions">
                    <button
                      type="button"
                      class="booking-picker-action-btn booking-picker-action-btn--share"
                      :disabled="sendingBookingId === booking.id"
                      @click="sendBookingMessage(booking)"
                    >
                      {{ sendingBookingId === booking.id ? 'Đang gửi' : 'Chia sẻ' }}
                    </button>
                    <button
                      v-if="canCreateSupportRequest"
                      type="button"
                      class="booking-picker-action-btn booking-picker-action-btn--support"
                      @click="openSupportRequestFromPicker(booking)"
                    >
                      Hỗ trợ
                    </button>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div v-if="showSupportRequestModal" class="booking-picker-backdrop" @click.self="closeSupportRequestModal">
            <section class="booking-picker-panel support-request-modal" role="dialog" aria-modal="true" aria-label="Tao yeu cau ho tro booking">
              <header class="booking-picker-header">
                <div>
                  <h3>T&#7841;o y&#234;u c&#7847;u h&#7895; tr&#7907;</h3>
                  <p v-if="supportRequestBooking">#{{ supportRequestBooking.booking_code }} - {{ bookingCourtText(supportRequestBooking) }}</p>
                </div>
                <button type="button" class="booking-picker-close" @click="closeSupportRequestModal" aria-label="Dong">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </header>

              <form class="support-request-form" @submit.prevent="submitSupportRequest">
                <div class="support-request-field">
                  <label for="support-request-type">Lo&#7841;i y&#234;u c&#7847;u</label>
                  <select id="support-request-type" v-model="supportRequestForm.request_type" class="support-request-input">
                    <option v-for="option in supportRequestTypeOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                  <p>Ch&#7885;n v&#7845;n &#273;&#7873; b&#7841;n mu&#7889;n s&#226;n h&#7895; tr&#7907;.</p>
                </div>

                <div class="support-request-field">
                  <label for="support-request-note">Ghi ch&#250;</label>
                  <textarea
                    id="support-request-note"
                    v-model.trim="supportRequestForm.note"
                    class="support-request-input support-request-textarea"
                    rows="4"
                    maxlength="1000"
                    placeholder="Ví dụ: Mình muốn đổi sang khung 19:00 nếu còn sân."
                  ></textarea>
                  <p>M&#244; t&#7843; ng&#7855;n mong mu&#7889;n c&#7911;a b&#7841;n &#273;&#7875; nh&#226;n vi&#234;n x&#7917; l&#253; nhanh h&#417;n.</p>
                </div>

                <div v-if="supportRequestError" class="support-request-error" role="alert">
                  {{ supportRequestError }}
                </div>

                <div class="support-request-actions">
                  <button type="button" class="support-request-secondary" @click="closeSupportRequestModal">H&#7911;y</button>
                  <button type="submit" class="support-request-primary" :disabled="!isSupportRequestFormValid || creatingSupportRequest">
                    {{ creatingSupportRequest ? '&#272;ang g&#7917;i...' : 'G&#7917;i y&#234;u c&#7847;u' }}
                  </button>
                </div>
              </form>
            </section>
          </div>
          <!-- Right Sidebar (Profile Info Panel) -->
          <div
            v-if="showProfileSidebar"
            class="tg-profile-sidebar w-80 flex flex-col h-full shrink-0 relative transition-all duration-200 border-l border-zinc-800"
          >
            <!-- Absolute Close Button -->
            <button
              @click="showProfileSidebar = false"
              class="absolute right-4 top-4 text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/30 p-1.5 rounded-full transition-colors bg-transparent border-none cursor-pointer z-10"
              title="Đóng thông tin"
            >
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <!-- Profile Info Body -->
            <div v-if="profileSidebarView === 'profile'" class="tg-profile-body flex-1 overflow-y-auto pb-5">
              <!-- Header Section -->
              <div class="flex flex-col items-center px-6 pb-5">
                <div class="tg-profile-avatar">
                  {{ profileInitial }}
                </div>
                <h3 class="tg-profile-value text-base font-semibold text-center truncate w-full mb-1">{{ profileDisplayName }}</h3>
                <p class="text-[11px] text-zinc-500 font-medium">
                  {{ profileStatusText }}
                </p>
              </div>

              <!-- Quick Actions Row -->
              <div class="flex items-center justify-center gap-3 px-6 pb-6">
                <button @click="showProfileSidebar = false" class="tg-profile-action-btn">
                  <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                  </svg>
                  <span>Nhắn tin</span>
                </button>
              </div>

              <!-- Info Details (Padded left to align with sections below) -->
              <div class="flex flex-col gap-4 py-1">
                <div v-if="profilePrimaryContact" class="tg-profile-info-block">
                  <span class="tg-profile-value">{{ profilePrimaryContact }}</span>
                  <span class="tg-profile-label">{{ profilePrimaryContactLabel }}</span>
                </div>

                <div class="tg-profile-info-block relative">
                  <div class="flex flex-col">
                    <span class="tg-profile-value text-blue-400">{{ profileUsername }}</span>
                    <span class="tg-profile-label">Tên tài khoản</span>
                  </div>
                </div>
              </div>

              <!-- Related Bookings -->
              <div v-if="canViewRelatedBookings" class="tg-profile-section pt-5">
                <div class="tg-related-bookings-head">
                  <div>
                    <div class="tg-profile-section-title">Booking c&#7911;a kh&#225;ch</div>
                    <p class="tg-related-bookings-sub">C&#225;c l&#7883;ch &#273;&#7863;t t&#7841;i c&#7909;m s&#226;n trong h&#7897;i tho&#7841;i n&#224;y.</p>
                  </div>
                  <button
                    type="button"
                    class="tg-related-bookings-refresh"
                    :disabled="loadingRelatedBookings"
                    aria-label="T&#7843;i l&#7841;i booking li&#234;n quan"
                    title="T&#7843;i l&#7841;i"
                    @click="loadRelatedBookings"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-15.64 6.13M3 12a9 9 0 0115.64-6.13M21 3v6h-6M3 21v-6h6" />
                    </svg>
                  </button>
                </div>

                <div v-if="loadingRelatedBookings" class="tg-related-bookings-state" role="status">
                  &#272;ang t&#7843;i booking...
                </div>
                <div v-else-if="relatedBookingsError" class="tg-related-bookings-state tg-related-bookings-state--error" role="status">
                  {{ relatedBookingsError }}
                </div>
                <div v-else-if="relatedBookings.length === 0" class="tg-related-bookings-state">
                  Ch&#432;a c&#243; booking n&#224;o c&#7911;a kh&#225;ch t&#7841;i c&#7909;m s&#226;n n&#224;y.
                </div>
                <div v-else class="tg-related-bookings-list">
                  <article
                    v-for="booking in relatedBookings"
                    :key="booking.id"
                    class="tg-related-booking-row"
                  >
                    <div class="tg-related-booking-main">
                      <div class="tg-related-booking-top">
                        <span class="tg-related-booking-code">#{{ booking.booking_code }}</span>
                        <span :class="['tg-related-booking-status', statusTextClass(booking.status)]">
                          {{ bookingStatusLabel(booking.status) }}
                        </span>
                      </div>
                      <div class="tg-related-booking-venue">{{ bookingCourtText(booking) }}</div>
                      <div class="tg-related-booking-meta">
                        <span>{{ bookingDateLabel(booking.booking_date) }}</span>
                        <span>{{ bookingTimeRange(booking) }}</span>
                      </div>
                      <div class="tg-related-booking-price">{{ bookingCurrency(booking.total_price) }}</div>
                    </div>
                    <button
                      type="button"
                      class="tg-related-booking-send"
                      :disabled="sendingBookingId === booking.id"
                      @click="sendBookingMessage(booking)"
                    >
                      {{ sendingBookingId === booking.id ? '\u0110ang g\u1eedi' : 'G\u1eedi' }}
                    </button>
                  </article>
                </div>
              </div>
              <!-- Shared Items -->
              <div class="tg-profile-section pt-5">
                <button
                  type="button"
                  class="tg-profile-item tg-profile-item-button"
                  :disabled="sharedImageCount === 0"
                  @click="openSharedImages"
                >
                  <svg class="tg-profile-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <div class="tg-profile-item-content">
                    <span class="tg-profile-value text-sm">{{ sharedImageCount }} ảnh</span>
                  </div>
                </button>
                <div class="tg-profile-item cursor-default">
                  <svg class="tg-profile-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                  </svg>
                  <div class="tg-profile-item-content">
                    <span class="tg-profile-value text-sm">0 liên kết đã chia sẻ</span>
                  </div>
                </div>
                <div class="tg-profile-item cursor-default">
                  <svg class="tg-profile-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                  </svg>
                  <div class="tg-profile-item-content">
                    <span class="tg-profile-value text-sm">0 ảnh GIF</span>
                  </div>
                </div>
              </div>

              <!-- Actions Section -->
              <div class="tg-profile-section pt-5 pb-8">
                <button @click="deleteActiveConversation" class="tg-profile-action-row">
                  <svg class="tg-profile-item-icon text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  <span>Xóa cuộc trò chuyện</span>
                </button>

              </div>
            </div>

            <div v-else class="tg-media-browser flex-1 flex flex-col min-h-0">
              <div class="tg-media-header">
                <button type="button" class="tg-media-back" aria-label="Quay lại thông tin" @click="profileSidebarView = 'profile'">
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                  </svg>
                </button>
                <span>Ảnh</span>
              </div>
              <div v-if="sharedImageGroups.length" class="tg-media-scroll flex-1 overflow-y-auto">
                <section v-for="group in sharedImageGroups" :key="group.key" class="tg-media-group">
                  <h4>{{ group.label }}</h4>
                  <div class="tg-media-grid">
                    <button
                      v-for="item in group.items"
                      :key="item.key"
                      type="button"
                      class="tg-media-thumb"
                      @click="openSharedImageAt(item.index)"
                    >
                      <img :src="item.url" alt="Ảnh đã gửi" loading="lazy" />
                    </button>
                  </div>
                </section>
              </div>
              <div v-else class="tg-media-empty">
                Chưa có ảnh nào trong cuộc trò chuyện này.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lightbox Modal for Image Zoom -->
      <div
        v-if="lightboxImage"
        class="tg-lightbox fixed inset-0 z-[9999] flex items-center justify-center cursor-pointer"
        @click="closeLightbox"
      >
        <div class="tg-lightbox-topbar">
          <div class="tg-lightbox-count">
            Ảnh {{ lightboxIndex + 1 }} / {{ lightboxImages.length || 1 }}
          </div>
          <button type="button" class="tg-lightbox-close" aria-label="Đóng" @click.stop="closeLightbox">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <button
          v-if="lightboxImages.length > 1"
          type="button"
          class="lightbox-nav lightbox-nav-prev"
          aria-label="Ảnh trước"
          @click.stop="showPreviousLightboxImage"
        >
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <img
          :src="lightboxImage"
          class="tg-lightbox-image"
          :style="{ transform: `rotate(${lightboxRotation}deg)` }"
          @click.stop
        />
        <div class="tg-lightbox-info" @click.stop>
          <div class="tg-lightbox-info-title">Ảnh {{ lightboxIndex + 1 }} / {{ lightboxImages.length || 1 }}</div>
          <div class="tg-lightbox-info-meta">{{ currentLightboxSenderName }} • {{ currentLightboxSentAtLabel }}</div>
        </div>
        <button
          v-if="lightboxImages.length > 1"
          type="button"
          class="lightbox-nav lightbox-nav-next"
          aria-label="Ảnh sau"
          @click.stop="showNextLightboxImage"
        >
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <div class="tg-lightbox-actions" @click.stop>
          <button type="button" class="tg-lightbox-action-btn" title="Tải xuống" aria-label="Tải xuống" @click="downloadLightboxImage">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 10l5 5 5-5" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 21h14" />
            </svg>
          </button>
          <button type="button" class="tg-lightbox-action-btn" title="Xoay" aria-label="Xoay" @click="rotateLightboxImage">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v5h5" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 17v-5h-5" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 12a5.5 5.5 0 0 1 9.2-4" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12a5.5 5.5 0 0 1-9.2 4" />
            </svg>
          </button>
          <div class="tg-lightbox-more-wrap">
            <button type="button" class="tg-lightbox-action-btn" title="Thêm" aria-label="Thêm" @click="toggleLightboxMenu">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="12" cy="6" r="1.35" />
                <circle cx="12" cy="12" r="1.35" />
                <circle cx="12" cy="18" r="1.35" />
              </svg>
            </button>
            <div v-if="showLightboxMenu" class="tg-lightbox-menu">
              <button type="button" @click="goToLightboxMessage">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5h14v11H8l-3 3V5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 10h6"/></svg>
                <span>Đến tin nhắn</span>
              </button>
              <button type="button" @click="copyLightboxImage">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="8" y="8" width="10" height="12" rx="1"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>
                <span>Sao chép</span>
              </button>
              <button type="button" @click="downloadLightboxImage">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 10l5 5 5-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 21h14"/></svg>
                <span>Lưu ảnh...</span>
              </button>
              <button type="button" @click="viewAllLightboxPhotos">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
                <span>Xem tất cả ảnh</span>
              </button>
            </div>
          </div>
        </div>
        <div v-if="lightboxImages.length > 1" class="tg-lightbox-filmstrip" @click.stop>
          <button
            v-for="(url, index) in lightboxImages"
            :key="url + index"
            type="button"
            class="tg-lightbox-thumb"
            :class="{ active: index === lightboxIndex }"
            @click="openSharedImageAt(index)"
          >
            <img :src="url" alt="Ảnh thu nhỏ" loading="lazy" />
          </button>
        </div>
      </div>

      <!-- Custom Context Menu -->
      <div
        v-if="showContextMenu"
        class="custom-context-menu"
        :style="{ top: contextMenuY + 'px', left: contextMenuX + 'px' }"
      >
        <button type="button" class="context-menu-item" @click="handleReply(contextMenuMessage)">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
          </svg>
          <span>Trả lời</span>
        </button>
        <button type="button" class="context-menu-item" @click="handleCopy(contextMenuMessage)">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
          </svg>
          <span>Copy tin nhắn</span>
        </button>
        <button type="button" class="context-menu-item" @click="handleTogglePin(contextMenuMessage)">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
          <span>{{ contextMenuMessage.is_pinned ? 'Bỏ ghim' : 'Ghim tin nhắn' }}</span>
        </button>
      </div>

    </div>

    <ConfirmActionModal
      :is-open="Boolean(pendingChatAction)"
      :title="pendingChatAction?.title || 'Xác nhận thao tác'"
      :description="pendingChatAction?.description || ''"
      :confirm-text="pendingChatAction?.confirmText || 'Xác nhận'"
      :loading="confirmChatLoading"
      :error="confirmChatError"
      @close="closeChatConfirmation"
      @confirm="confirmChatAction"
    />
  </div>
</template>

<script>
import { useToast } from 'vue-toastification';
import ConfirmActionModal from '../components/ConfirmActionModal.vue';
import PublicNavbar from '../components/PublicNavbar.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import echo from '../echo.js';
import { getAuth } from '../stores/auth.js';
import { chatService } from '../services/chat.service.js';

export default {
  name: 'Chat',
  components: {
    ConfirmActionModal,
    PublicNavbar,
    BaseInput
  },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      currentUser: getAuth(),
      conversations: [],
      activeConversation: null,
      messages: [],
      activeConversationParticipants: [],
      newMessage: '',
      searchQuery: '',
      searchResults: [],
      searching: false,
      loadingConversations: false,
      loadingMessages: false,
      selectedTab: 'all', // 'all', 'direct', 'venue_contact'
      mobileShowChat: false,
      echoConversationChannel: null,
      echoUserChannel: null,
      fallbackPoll: null,
      pendingChatAction: null,
      confirmChatLoading: false,
      confirmChatError: '',

      showTelegramMenu: false,
      isNightMode: false,
      selectedImageFiles: [],
      imagePreviewUrls: [],
      lightboxImage: null,
      lightboxImages: [],
      lightboxIndex: 0,
      lightboxRotation: 0,
      showLightboxMenu: false,
      showChatMenu: false,
      showProfileSidebar: false,
      profileSidebarView: 'profile',
      highlightedMessageId: null,
      showBookingPicker: false,
      bookingOptions: [],
      loadingBookingOptions: false,
      bookingPickerError: '',
      sendingBookingId: '',
      relatedBookings: [],
      loadingRelatedBookings: false,
      relatedBookingsError: '',
      showSupportRequestModal: false,
      supportRequestBooking: null,
      supportRequestForm: { request_type: 'reschedule', note: '' },
      supportRequestError: '',
      creatingSupportRequest: false,
      updatingSupportRequestId: '',
      replyTarget: null,
      showContextMenu: false,
      contextMenuX: 0,
      contextMenuY: 0,
      contextMenuMessage: null,
      activeHoverMessageId: null,
      hoverReactionTargetId: null
    };
  },
  computed: {
    filteredConversations() {
      if (this.selectedTab === 'all') return this.conversations;
      return this.conversations.filter(c => c.type === this.selectedTab);
    },
    groupedMessages() {
      return this.groupMessages(this.messages);
    },
    pinnedMessages() {
      return this.messages.filter(m => m.is_pinned);
    },
    isAdmin() {
      return this.$route.path.startsWith('/admin') || this.$route.path.startsWith('/owner') || this.$route.path.startsWith('/staff');
    },
    usesAdminChatTheme() {
      return true;
    },
    canShareBooking() {
      if (!this.activeConversation || !this.currentUser) return false;
      if (this.currentUser.role_group === 'admin') return false;
      return this.activeConversation.type !== 'direct' || Boolean(this.activeConversation.other_user);
    },
    canViewRelatedBookings() {
      if (!this.activeConversation || !this.currentUser) return false;
      return this.currentUser.role_group === 'owner' || this.$route.path.startsWith('/owner');
    },
    canCreateSupportRequest() {
      if (!this.activeConversation || !this.currentUser) return false;
      return this.currentUser.role_group !== 'owner' && this.currentUser.role_group !== 'admin';
    },
    canHandleSupportRequest() {
      return this.canViewRelatedBookings;
    },
    supportRequestTypeOptions() {
      return [
        { value: 'reschedule', label: 'Đổi giờ' },
        { value: 'change_court', label: 'Đổi sân' },
        { value: 'cancel_booking', label: 'Hủy đặt sân' },
        { value: 'payment', label: 'Hỏi thanh toán/cọc' },
        { value: 'late_arrival', label: 'Báo đến muộn' },
        { value: 'refund', label: 'Yêu cầu hoàn tiền' },
        { value: 'other', label: 'Khác' },
      ];
    },
    isSupportRequestFormValid() {
      return Boolean(this.supportRequestBooking?.id && this.supportRequestForm.request_type);
    },
    profileUser() {
      if (!this.activeConversation) return null;
      if (this.activeConversation.other_user) return this.activeConversation.other_user;

      const participants = this.activeConversationParticipants || [];
      const otherParticipant = participants.find((participant) => String(participant.user_id) !== String(this.currentUser?.id));
      if (otherParticipant?.user) return otherParticipant.user;

      if (participants.length <= 1) return this.currentUser;
      return null;
    },
    profileDisplayName() {
      return this.profileUser?.full_name
        || this.profileUser?.name
        || this.activeConversation?.title
        || 'SportGo';
    },
    profileInitial() {
      return (this.profileDisplayName || 'S').charAt(0).toUpperCase();
    },
    profileStatusText() {
      if (this.activeConversation?.type === 'venue_contact') return 'Hội thoại Sân đấu';
      return 'trực tuyến';
    },
    profilePrimaryContact() {
      return this.profileUser?.phone || this.profileUser?.email || '';
    },
    profilePrimaryContactLabel() {
      if (this.profileUser?.phone) return 'Di động';
      if (this.profileUser?.email) return 'Email';
      return 'Liên hệ';
    },
    profileUsername() {
      return this.profileUser?.username ? `@${this.profileUser.username}` : '@user';
    },
    sharedImages() {
      return this.messages
        .map((message, index) => ({
          key: message.id || `image-${index}`,
          url: message.image_url,
          createdAt: message.created_at || message.createdAt || message.sent_at || message.updated_at,
          content: message.content,
          messageId: message.id,
          senderId: message.sender_id,
          senderName: this.resolveMessageSenderName(message),
        }))
        .filter((item) => Boolean(item.url));
    },
    sharedImageUrls() {
      return this.sharedImages.map((item) => item.url);
    },
    sharedImageGroups() {
      const groups = new Map();
      this.sharedImages.forEach((item, index) => {
        const date = item.createdAt ? new Date(item.createdAt) : new Date();
        const validDate = Number.isNaN(date.getTime()) ? new Date() : date;
        const key = `${validDate.getFullYear()}-${String(validDate.getMonth() + 1).padStart(2, '0')}`;
        const label = validDate.toLocaleDateString('vi-VN', { month: 'long', year: 'numeric' });
        if (!groups.has(key)) {
          groups.set(key, { key, label, items: [] });
        }
        groups.get(key).items.push({ ...item, index });
      });
      return Array.from(groups.values()).sort((a, b) => b.key.localeCompare(a.key));
    },
    currentLightboxImageInfo() {
      return this.sharedImages[this.lightboxIndex] || null;
    },
    currentLightboxSenderName() {
      return this.currentLightboxImageInfo?.senderName || this.activeConversation?.title || 'SportGo';
    },
    currentLightboxSentAtLabel() {
      return this.formatLightboxSentAt(this.currentLightboxImageInfo?.createdAt);
    },
    sharedImageCount() {
      return this.sharedImages.length;
    }
  },
  created() {
    // If not authenticated, redirect to login page
    if (!this.currentUser) {
      this.$router.push('/login');
      return;
    }

    // Chat follows the admin design tokens across admin, owner, and client surfaces.
    if (this.usesAdminChatTheme) {
      this.isNightMode = false;
    } else {
      const chatThemePref = localStorage.getItem('chat-theme');
      if (chatThemePref === 'dark') {
        this.isNightMode = true;
      } else if (chatThemePref === 'light') {
        this.isNightMode = false;
      } else {
        this.isNightMode = document.documentElement.getAttribute('data-theme') === 'dark';
      }
    }

    // Load list of conversations on creation
    this.fetchConversations(true);

    // Subscribe to user's personal channel for conversation list updates
    this.subscribeUserChannel();
    if (!echo) {
      this.fallbackPoll = window.setInterval(() => {
        this.fetchConversations(false);
        if (this.activeConversation) this.fetchMessages(false);
      }, 10000);
    }
  },
  mounted() {
    // Apply chat-local theme only when the standalone chat theme is enabled.
    const chatPage = this.$el?.closest('.chat-page') || this.$el;
    if (chatPage && this.usesAdminChatTheme) {
      chatPage.classList.remove('chat-dark', 'chat-light');
    } else if (chatPage && this.isNightMode) {
      chatPage.classList.add('chat-dark');
      chatPage.classList.remove('chat-light');
    }

    // If target query parameter exists (e.g. starting chat with a user or venue)
    const targetUserId = this.$route.query.userId;
    const targetVenueId = this.$route.query.venueId;
    if (targetUserId) {
      this.startChat({ type: 'direct', user_id: targetUserId });
    } else if (targetVenueId) {
      this.startChat({ type: 'venue_contact', venue_id: targetVenueId });
    }

    document.addEventListener('click', this.handleDocumentClick);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleDocumentClick);
    if (this.fallbackPoll) window.clearInterval(this.fallbackPoll);

    // Leave WebSocket channels
    this.unsubscribeConversationChannel();
    this.unsubscribeUserChannel();
  },
  watch: {
    // Subscribe to new conversation channel and clear/restart fallback poll
    activeConversation(newVal, oldVal) {
      this.profileSidebarView = 'profile';
      this.closeBookingPicker();
      this.closeSupportRequestModal();
      this.closeLightbox();

      // Unsubscribe from previous conversation channel
      this.unsubscribeConversationChannel();

      if (newVal) {
        this.fetchMessages(true);
        this.loadRelatedBookings();
        // Subscribe to real-time messages on this conversation channel
        this.subscribeConversationChannel(newVal.id);
      } else {
        this.messages = [];
        this.activeConversationParticipants = [];
        this.relatedBookings = [];
        this.relatedBookingsError = '';
      }
    }
  },
  methods: {
    // ---- WebSocket Channel Subscriptions ----
    subscribeConversationChannel(conversationId) {
      if (!echo) return;
      this.echoConversationChannel = echo
        .private(`conversation.${conversationId}`)
        .listen('.message.sent', (event) => {
          const msg = event.message;
          this.applySupportRequestUpdate(msg.support_request);
          // Avoid duplicate if we already have the message (e.g. optimistic insert)
          const exists = this.messages.some(m => m.id === msg.id);
          if (!exists) {
            this.messages.push(msg);
            this.scrollToBottom();
            this.markConversationAsRead();
          }
          // Update last message in conversation list
          const conv = this.conversations.find(c => c.id === conversationId);
          if (conv) {
            conv.last_message = { content: msg.content, created_at: msg.created_at, sender_id: msg.sender_id };
            conv.last_message_at = msg.created_at;
            this.conversations.sort((a, b) => new Date(b.last_message_at) - new Date(a.last_message_at));
          }
        })
        .listen('.message.reacted', (event) => {
          const msg = this.messages.find(m => m.id === event.message_id);
          if (msg) {
            msg.reactions = event.reactions;
          }
        })
        .listen('.message.pinned', (event) => {
          const msg = this.messages.find(m => m.id === event.message_id);
          if (msg) {
            msg.is_pinned = event.is_pinned;
          }
        });
    },

    unsubscribeConversationChannel() {
      if (this.echoConversationChannel) {
        this.echoConversationChannel.stopListening('.message.sent');
        this.echoConversationChannel.stopListening('.message.reacted');
        this.echoConversationChannel.stopListening('.message.pinned');
        echo?.leave(this.echoConversationChannel.name ?? '');
        this.echoConversationChannel = null;
      }
    },

    subscribeUserChannel() {
      if (!this.currentUser || !echo) return;
      this.echoUserChannel = echo
        .private(`user.${this.currentUser.id}`)
        .listen('.conversation.updated', (event) => {
          const updated = event.conversation;
          const conv = this.conversations.find(c => c.id === updated.id);
          if (conv) {
            conv.last_message = updated.last_message;
            conv.last_message_at = updated.last_message_at;
            conv.unread_count = (conv.unread_count || 0) + 1;
            this.conversations.sort((a, b) => new Date(b.last_message_at) - new Date(a.last_message_at));
          } else {
            // New conversation we didn't have yet — do a full refresh
            this.fetchConversations(false);
          }
        });
    },

    unsubscribeUserChannel() {
      if (this.echoUserChannel) {
        this.echoUserChannel.stopListening('.conversation.updated');
        echo?.leave(this.echoUserChannel.name ?? '');
        this.echoUserChannel = null;
      }
    },

    // ---- End WebSocket Methods ----
    toggleTelegramMenu() {
      this.showTelegramMenu = !this.showTelegramMenu;
    },
    closeTelegramMenu() {
      this.showTelegramMenu = false;
    },
    openSavedMessages() {
      this.closeTelegramMenu();
      this.startChat({ type: 'saved' });
    },
    toggleNightMode() {
      if (this.usesAdminChatTheme) return;

      this.isNightMode = !this.isNightMode;
      const chatTheme = this.isNightMode ? 'dark' : 'light';
      localStorage.setItem('chat-theme', chatTheme);
      this.$nextTick(() => {
        const chatPage = this.$el?.closest('.chat-page') || this.$el;
        if (chatPage) {
          chatPage.classList.toggle('chat-dark', this.isNightMode);
          chatPage.classList.toggle('chat-light', !this.isNightMode);
        }
      });
    },
    async fetchConversations(showLoader = false) {
      if (showLoader) this.loadingConversations = true;
      try {
        const response = await chatService.getConversations();
        this.conversations = response || [];

        // If there's an active conversation, update its metadata locally
        if (this.activeConversation) {
          const updated = this.conversations.find(c => c.id === this.activeConversation.id);
          if (updated) {
            this.activeConversation.unread_count = 0; // It's currently active/open
          }
        }
      } catch (err) {
        console.error('Failed to load conversations', err);
      } finally {
        this.loadingConversations = false;
      }
    },

    async fetchMessages(showLoader = false) {
      if (!this.activeConversation) return;
      if (showLoader) this.loadingMessages = true;
      try {
        const response = await chatService.getMessages(this.activeConversation.id);

        const previousLength = this.messages.length;
        this.messages = response.messages || [];
        this.activeConversationParticipants = response.participants || [];

        // Scroll to bottom if new messages arrived
        if (this.messages.length > previousLength) {
          this.scrollToBottom();
        }

        // If there are unread messages from the other user, mark the chat as read
        const hasUnread = this.conversations.some(c => c.id === this.activeConversation.id && c.unread_count > 0);
        if (hasUnread || showLoader) {
          this.markConversationAsRead();
        }
      } catch (err) {
        console.error('Failed to load messages', err);
      } finally {
        this.loadingMessages = false;
      }
    },

    async selectConversation(conv) {
      this.activeConversation = conv;
      this.mobileShowChat = true;
      this.newMessage = '';

      // Instantly clear unread count for local visual responsiveness
      conv.unread_count = 0;
      this.scrollToBottom();
    },

    async markConversationAsRead() {
      if (!this.activeConversation) return;
      try {
        await chatService.markAsRead(this.activeConversation.id);
        // Sync local list
        const conv = this.conversations.find(c => c.id === this.activeConversation.id);
        if (conv) conv.unread_count = 0;
      } catch (err) {
        console.error('Failed to mark conversation as read', err);
      }
    },

    async submitMessage() {
      if ((!this.newMessage.trim() && this.selectedImageFiles.length === 0) || !this.activeConversation) return;

      const content = this.newMessage.trim();
      const filesToSend = [...this.selectedImageFiles];
      const replyToId = this.replyTarget?.id || null;

      this.newMessage = ''; // clear input instantly for native feel
      this.clearSelectedImages();
      this.replyTarget = null; // clear reply target

      try {
        if (filesToSend.length > 0) {
          for (let i = 0; i < filesToSend.length; i++) {
            const currentContent = (i === 0) ? content : '';
            const response = await chatService.sendMessage(this.activeConversation.id, currentContent, filesToSend[i], replyToId);
            const exists = this.messages.some(m => m.id === response.id);
            if (!exists) {
              this.messages.push(response);
            }
          }
        } else {
          const response = await chatService.sendMessage(this.activeConversation.id, content, null, replyToId);
          const exists = this.messages.some(m => m.id === response.id);
          if (!exists) {
            this.messages.push(response);
          }
        }

        // Update last message in the conversations list locally
        const conv = this.conversations.find(c => c.id === this.activeConversation.id);
        if (conv) {
          conv.last_message = {
            content: content || '[Hình ảnh]',
            created_at: new Date().toISOString(),
            sender_id: this.currentUser.id
          };
          conv.last_message_at = new Date().toISOString();
        }

        // Re-sort conversations list
        this.conversations.sort((a, b) => new Date(b.last_message_at) - new Date(a.last_message_at));

        this.scrollToBottom();
      } catch (err) {
        this.toast.error(err.message || 'Không thể gửi tin nhắn.');
      }
    },

    async handleSearch() {
      const query = this.searchQuery.trim();
      if (query.length < 2) {
        this.searchResults = [];
        return;
      }

      this.searching = true;
      try {
        const response = await chatService.searchUsers(query);
        this.searchResults = response || [];
      } catch (err) {
        console.error('Failed to search members', err);
      } finally {
        this.searching = false;
      }
    },

    clearSearch() {
      this.searchQuery = '';
      this.searchResults = [];
    },

    async clickSearchResult(user) {
      this.clearSearch();
      this.startChat({ type: 'direct', user_id: user.id });
    },

    async startChat(payload) {
      try {
        const response = await chatService.startConversation(payload);
        const conversationId = response.id;

        // Refresh conversations list to include new chat if not loaded
        await this.fetchConversations(true);

        const conv = this.conversations.find(c => c.id === conversationId);
        if (conv) {
          this.selectConversation(conv);
        } else {
          // Fallback if missing
          this.activeConversation = {
            id: conversationId,
            title: payload.type === 'direct' ? 'Người dùng' : 'Sân đấu',
            type: payload.type
          };
          this.mobileShowChat = true;
        }
      } catch (err) {
        this.toast.error(err.message || 'Không thể tạo phòng trò chuyện.');
      }
    },

    backToList() {
      this.mobileShowChat = false;
    },

    async loadRelatedBookings() {
      this.relatedBookings = [];
      this.relatedBookingsError = '';
      if (!this.canViewRelatedBookings || !this.activeConversation) return;

      this.loadingRelatedBookings = true;
      try {
        this.relatedBookings = await chatService.getRelatedBookings(this.activeConversation.id) || [];
      } catch (error) {
        this.relatedBookingsError = error.message || 'Kh\u00f4ng th\u1ec3 t\u1ea3i booking li\u00ean quan.';
      } finally {
        this.loadingRelatedBookings = false;
      }
    },
    openSupportRequestModal(booking) {
      if (!this.canCreateSupportRequest || !booking?.id) return;
      this.supportRequestBooking = booking;
      this.supportRequestForm = { request_type: 'reschedule', note: '' };
      this.supportRequestError = '';
      this.showSupportRequestModal = true;
    },

    closeSupportRequestModal() {
      this.showSupportRequestModal = false;
      this.supportRequestBooking = null;
      this.supportRequestForm = { request_type: 'reschedule', note: '' };
      this.supportRequestError = '';
      this.creatingSupportRequest = false;
    },

    async submitSupportRequest() {
      if (!this.activeConversation || !this.isSupportRequestFormValid || this.creatingSupportRequest) return;
      this.creatingSupportRequest = true;
      this.supportRequestError = '';
      try {
        const response = await chatService.createBookingSupportRequest(this.activeConversation.id, {
          booking_id: this.supportRequestBooking.id,
          request_type: this.supportRequestForm.request_type,
          note: this.supportRequestForm.note?.trim() || null,
        });
        const exists = this.messages.some(m => m.id === response.id);
        if (!exists) {
          this.messages.push(response);
        }
        this.applySupportRequestUpdate(response.support_request);
        this.updateConversationLastMessage(response);
        this.closeSupportRequestModal();
        this.scrollToBottom();
      } catch (error) {
        this.supportRequestError = error.message || 'Không thể gửi yêu cầu hỗ trợ.';
      } finally {
        this.creatingSupportRequest = false;
      }
    },

    async updateSupportRequestStatus(supportRequest, status) {
      if (!supportRequest?.id || this.updatingSupportRequestId) return;
      this.updatingSupportRequestId = supportRequest.id;
      try {
        const response = await chatService.updateBookingSupportRequest(supportRequest.id, { status });
        const exists = this.messages.some(m => m.id === response.id);
        if (!exists) {
          this.messages.push(response);
        }
        this.applySupportRequestUpdate(response.support_request);
        this.updateConversationLastMessage(response);
        this.scrollToBottom();
      } catch (error) {
        this.toast.error(error.message || 'Không thể cập nhật yêu cầu hỗ trợ.');
      } finally {
        this.updatingSupportRequestId = '';
      }
    },

    applySupportRequestUpdate(supportRequest) {
      if (!supportRequest?.id) return;
      this.messages.forEach((message) => {
        if (message.support_request?.id === supportRequest.id) {
          message.support_request = { ...message.support_request, ...supportRequest };
        }
      });
    },
    updateConversationLastMessage(message) {
      const conv = this.conversations.find(c => c.id === this.activeConversation?.id);
      if (!conv || !message) return;
      conv.last_message = {
        content: message.content || 'Cap nhat hoi thoai',
        created_at: message.created_at || new Date().toISOString(),
        sender_id: message.sender_id || this.currentUser.id,
      };
      conv.last_message_at = conv.last_message.created_at;
      this.conversations.sort((a, b) => new Date(b.last_message_at) - new Date(a.last_message_at));
    },
    async openBookingPicker() {
      if (!this.canShareBooking || !this.activeConversation) return;
      this.showBookingPicker = true;
      await this.loadEligibleBookings();
    },

    openSupportRequestFromPicker(booking) {
      this.closeBookingPicker();
      this.openSupportRequestModal(booking);
    },

    closeBookingPicker() {
      this.showBookingPicker = false;
      this.bookingPickerError = '';
      this.sendingBookingId = '';
    },

    async loadEligibleBookings() {
      if (!this.activeConversation) return;
      this.loadingBookingOptions = true;
      this.bookingPickerError = '';
      try {
        this.bookingOptions = await chatService.getEligibleBookings(this.activeConversation.id) || [];
      } catch (error) {
        this.bookingPickerError = error.message || 'Không thể tải danh sách booking.';
        this.bookingOptions = [];
      } finally {
        this.loadingBookingOptions = false;
      }
    },

    async sendBookingMessage(booking) {
      if (!this.activeConversation || !booking?.id) return;
      this.sendingBookingId = booking.id;
      this.bookingPickerError = '';
      try {
        const response = await chatService.sendBooking(this.activeConversation.id, booking.id);
        const exists = this.messages.some(m => m.id === response.id);
        if (!exists) {
          this.messages.push(response);
        }

        const conv = this.conversations.find(c => c.id === this.activeConversation.id);
        if (conv) {
          conv.last_message = {
            content: response.content || 'Đã gửi booking',
            created_at: response.created_at || new Date().toISOString(),
            sender_id: this.currentUser.id,
          };
          conv.last_message_at = conv.last_message.created_at;
        }
        this.conversations.sort((a, b) => new Date(b.last_message_at) - new Date(a.last_message_at));
        this.closeBookingPicker();
        this.scrollToBottom();
      } catch (error) {
        this.bookingPickerError = error.message || 'Không thể gửi booking.';
      } finally {
        this.sendingBookingId = '';
      }
    },

    clickAttachment() {
      this.$refs.imageInput.click();
    },

    convertToWebP(file) {
      if (file.type === 'image/webp') return Promise.resolve(file);

      return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = new Image();
          img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);

            canvas.toBlob((blob) => {
              if (blob) {
                const nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                const webpFile = new File([blob], `${nameWithoutExt}.webp`, { type: 'image/webp' });
                resolve(webpFile);
              } else {
                resolve(file);
              }
            }, 'image/webp', 0.8);
          };
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
      });
    },

    async handleImagesSelected(event) {
      const files = Array.from(event.target.files);
      if (!files.length) return;

      for (const file of files) {
        if (file.size > 10 * 1024 * 1024) {
          this.toast.error(`Dung lượng ảnh ${file.name} vượt quá 10MB.`);
          continue;
        }
        try {
          const webpFile = await this.convertToWebP(file);
          this.selectedImageFiles.push(webpFile);
          this.imagePreviewUrls.push(URL.createObjectURL(webpFile));
        } catch (err) {
          this.selectedImageFiles.push(file);
          this.imagePreviewUrls.push(URL.createObjectURL(file));
        }
      }
    },

    async handlePaste(event) {
      const items = (event.clipboardData || event.originalEvent.clipboardData)?.items;
      if (!items) return;

      for (const item of items) {
        if (item.type.indexOf('image') === 0) {
          const file = item.getAsFile();
          if (file) {
            if (file.size > 10 * 1024 * 1024) {
              this.toast.error('Dung lượng ảnh tối đa là 10MB.');
              return;
            }

            const extension = file.type.split('/')[1] || 'png';
            const namedFile = new File([file], `paste_${Date.now()}.${extension}`, { type: file.type });

            try {
              const webpFile = await this.convertToWebP(namedFile);
              this.selectedImageFiles.push(webpFile);
              this.imagePreviewUrls.push(URL.createObjectURL(webpFile));
            } catch (err) {
              this.selectedImageFiles.push(namedFile);
              this.imagePreviewUrls.push(URL.createObjectURL(namedFile));
            }
            event.preventDefault();
          }
        }
      }
    },

    removeSelectedImage(index) {
      const url = this.imagePreviewUrls[index];
      if (url) {
        URL.revokeObjectURL(url);
      }
      this.selectedImageFiles.splice(index, 1);
      this.imagePreviewUrls.splice(index, 1);
      if (this.selectedImageFiles.length === 0 && this.$refs.imageInput) {
        this.$refs.imageInput.value = '';
      }
    },

    clearSelectedImages() {
      this.imagePreviewUrls.forEach(url => URL.revokeObjectURL(url));
      this.selectedImageFiles = [];
      this.imagePreviewUrls = [];
      if (this.$refs.imageInput) {
        this.$refs.imageInput.value = '';
      }
    },

    deleteActiveConversation() {
      if (!this.activeConversation) return;
      this.showChatMenu = false;
      this.pendingChatAction = {
        type: 'delete-conversation',
        targetId: this.activeConversation.id,
        title: 'Xóa cuộc trò chuyện?',
        description: 'Toàn bộ tin nhắn trong cuộc trò chuyện sẽ bị xóa vĩnh viễn.',
        confirmText: 'Xóa cuộc trò chuyện',
      };
      this.confirmChatError = '';
    },

    clearChatHistory() {
      if (!this.activeConversation) return;
      this.showChatMenu = false;
      this.pendingChatAction = {
        type: 'clear-history',
        targetId: this.activeConversation.id,
        title: 'Xóa lịch sử tin nhắn?',
        description: 'Các tin nhắn hiện có sẽ bị xóa nhưng cuộc trò chuyện vẫn được giữ lại.',
        confirmText: 'Xóa lịch sử',
      };
      this.confirmChatError = '';
    },

    closeChatConfirmation() {
      if (this.confirmChatLoading) return;
      this.pendingChatAction = null;
      this.confirmChatError = '';
    },

    async confirmChatAction() {
      if (!this.pendingChatAction || this.confirmChatLoading) return;
      const action = { ...this.pendingChatAction };
      this.confirmChatLoading = true;
      this.confirmChatError = '';
      try {
        if (action.type === 'delete-conversation') {
          await chatService.deleteConversation(action.targetId);
          this.conversations = this.conversations.filter(c => c.id !== action.targetId);
          this.activeConversation = null;
          this.messages = [];
          this.toast.success('Đã xóa cuộc trò chuyện.');
        } else if (action.type === 'clear-history') {
          await chatService.clearConversation(action.targetId);
          this.messages = [];
          const conversation = this.conversations.find(c => c.id === action.targetId);
          if (conversation) conversation.last_message = null;
          this.toast.success('Đã xóa lịch sử tin nhắn.');
        }
        this.pendingChatAction = null;
      } catch (error) {
        this.confirmChatError = error.message || 'Không thể thực hiện thao tác.';
      } finally {
        this.confirmChatLoading = false;
      }
    },

    viewProfile() {
      this.showChatMenu = false;
      this.profileSidebarView = 'profile';
      this.showProfileSidebar = true;
    },

    resolveMessageSenderName(message) {
      if (!message) return this.activeConversation?.title || 'SportGo';
      if (message.sender?.full_name) return message.sender.full_name;
      if (message.sender?.name) return message.sender.name;
      if (message.sender_name) return message.sender_name;
      if (message.user?.full_name) return message.user.full_name;
      if (message.user?.name) return message.user.name;
      if (message.sender_id === this.currentUser?.id) return this.currentUser.full_name || this.currentUser.username || 'Bạn';

      const participants = this.activeConversationParticipants || [];
      const participant = participants.find((item) => item.user_id === message.sender_id || item.id === message.sender_id);
      return participant?.user?.full_name
        || participant?.user?.name
        || participant?.full_name
        || participant?.name
        || this.activeConversation?.other_user?.full_name
        || this.activeConversation?.other_user?.name
        || this.activeConversation?.title
        || 'SportGo';
    },

    formatLightboxSentAt(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';

      const today = new Date();
      const startOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();
      const startOfDate = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
      const dayDiff = Math.round((startOfToday - startOfDate) / 86400000);
      const time = date.toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      });

      if (dayDiff === 0) return `hôm nay lúc ${time}`;
      if (dayDiff === 1) return `hôm qua lúc ${time}`;
      return `${date.toLocaleDateString('vi-VN')} lúc ${time}`;
    },

    openLightbox(url, message = null) {
      const images = this.sharedImageUrls;
      const imageIndex = message?.id
        ? this.sharedImages.findIndex((item) => item.messageId === message.id)
        : images.indexOf(url);
      this.lightboxImages = imageIndex >= 0 ? images : [url];
      this.lightboxIndex = imageIndex >= 0 ? imageIndex : 0;
      this.lightboxImage = url;
      this.resetLightboxControls();
    },

    openSharedImages() {
      if (this.sharedImageCount === 0) return;
      this.profileSidebarView = 'photos';
      this.showProfileSidebar = true;
    },

    openSharedImageAt(index) {
      const images = this.sharedImageUrls;
      if (!images[index]) return;
      this.lightboxImages = images;
      this.lightboxIndex = index;
      this.lightboxImage = images[index];
      this.resetLightboxControls();
    },

    showPreviousLightboxImage() {
      if (this.lightboxImages.length <= 1) return;
      this.lightboxIndex = (this.lightboxIndex - 1 + this.lightboxImages.length) % this.lightboxImages.length;
      this.lightboxImage = this.lightboxImages[this.lightboxIndex];
      this.resetLightboxControls();
    },

    showNextLightboxImage() {
      if (this.lightboxImages.length <= 1) return;
      this.lightboxIndex = (this.lightboxIndex + 1) % this.lightboxImages.length;
      this.lightboxImage = this.lightboxImages[this.lightboxIndex];
      this.resetLightboxControls();
    },

    resetLightboxControls() {
      this.lightboxRotation = 0;
      this.showLightboxMenu = false;
    },

    toggleLightboxMenu() {
      this.showLightboxMenu = !this.showLightboxMenu;
    },

    rotateLightboxImage() {
      this.lightboxRotation = (this.lightboxRotation + 90) % 360;
      this.showLightboxMenu = false;
    },


    downloadLightboxImage() {
      if (!this.lightboxImage) return;
      const link = document.createElement('a');
      link.href = this.lightboxImage;
      link.download = `sportgo-anh-${this.lightboxIndex + 1}.jpg`;
      link.target = '_blank';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      this.showLightboxMenu = false;
    },

    async copyLightboxImage() {
      if (!this.lightboxImage) return;
      try {
        await navigator.clipboard.writeText(this.lightboxImage);
      } catch (error) {
        console.warn('Không thể sao chép liên kết ảnh', error);
      } finally {
        this.showLightboxMenu = false;
      }
    },

    goToLightboxMessage() {
      const messageId = this.currentLightboxImageInfo?.messageId;
      this.closeLightbox();
      this.showProfileSidebar = false;
      if (!messageId) {
        this.$nextTick(() => this.scrollToBottom());
        return;
      }

      this.$nextTick(() => this.scrollToMessage(messageId));
    },

    scrollToMessage(messageId) {
      const container = this.$refs.messageContainer;
      if (!container) return;

      const target = container.querySelector('[data-message-id="' + messageId + '"]');
      if (!target) {
        this.scrollToBottom();
        return;
      }

      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      this.highlightedMessageId = messageId;
      window.setTimeout(() => {
        if (this.highlightedMessageId === messageId) {
          this.highlightedMessageId = null;
        }
      }, 1800);
    },

    viewAllLightboxPhotos() {
      this.closeLightbox();
      this.profileSidebarView = 'photos';
      this.showProfileSidebar = true;
    },

    closeLightbox() {
      this.lightboxImage = null;
      this.lightboxImages = [];
      this.lightboxIndex = 0;
      this.lightboxRotation = 0;
      this.showLightboxMenu = false;
    },

    scrollToBottom() {
      this.$nextTick(() => {
        const container = this.$refs.messageContainer;
        if (container) {
          container.scrollTo({
            top: container.scrollHeight,
            behavior: 'smooth'
          });
        }
      });
    },

    isMessageRead(msg) {
      if (!this.activeConversation || msg.sender_id !== this.currentUser.id) return false;

      const otherParticipant = this.activeConversationParticipants.find(
        p => p.user_id !== this.currentUser.id
      );
      if (!otherParticipant || !otherParticipant.last_read_at) return false;

      const msgTime = new Date(msg.created_at).getTime();
      const readTime = new Date(otherParticipant.last_read_at).getTime();
      return readTime >= msgTime;
    },

    groupMessages(messages) {
      if (!messages || messages.length === 0) return [];
      const groups = [];
      let currentGroup = null;

      messages.forEach(msg => {
        const dateStr = this.formatGroupDate(msg.created_at);
        if (!currentGroup || currentGroup.date !== dateStr) {
          currentGroup = {
            date: dateStr,
            messages: []
          };
          groups.push(currentGroup);
        }
        currentGroup.messages.push(msg);
      });
      return groups;
    },

    formatGroupDate(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      const today = new Date();
      const yesterday = new Date();
      yesterday.setDate(today.getDate() - 1);

      if (date.toDateString() === today.toDateString()) {
        return 'Hôm nay';
      } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Hôm qua';
      } else {
        return date.toLocaleDateString('vi-VN', {
          day: 'numeric',
          month: 'long',
          year: 'numeric'
        });
      }
    },

    formatTime(timeStr) {
      if (!timeStr) return '';
      const date = new Date(timeStr);
      const today = new Date();
      if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('vi-VN', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: false
        });
      }
      return date.toLocaleDateString('vi-VN', {
        day: 'numeric',
        month: 'short'
      });
    },

    formatTimeOnly(timeStr) {
      if (!timeStr) return '';
      const date = new Date(timeStr);
      return date.toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      });
    },

    supportRequestTypeLabel(type) {
      const option = this.supportRequestTypeOptions.find(item => item.value === type);
      return option?.label || type || '-';
    },

    supportRequestStatusLabel(status) {
      return {
        pending: 'Chờ xử lý',
        acknowledged: 'Đã tiếp nhận',
        resolved: 'Đã xử lý',
        rejected: 'Từ chối',
      }[status] || status || '-';
    },

    supportRequestStatusClass(status) {
      return {
        pending: 'text-blue-500',
        acknowledged: 'text-emerald-500',
        resolved: 'text-green-500',
        rejected: 'text-red-500',
      }[status] || 'text-zinc-400';
    },
    bookingStatusLabel(status) {
      return {
        pending_approval: "Chờ duyệt",
        pending_payment: "Chờ thanh toán",
        confirmed: "Đã xác nhận",
        checked_in: "Đã check-in",
        completed: "Hoàn thành",
        cancelled: "Đã hủy",
        rejected: "Từ chối",
        expired: "Hết hạn",
      }[status] || status || "-";
    },

    bookingClusterName(booking) {
      return booking.venue_cluster?.name || "Sân đấu";
    },

    bookingCourtText(booking) {
      return booking.venue_court?.name || "Sân";
    },

    bookingDateLabel(dateStr) {
      if (!dateStr) return "";
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString("vi-VN");
    },

    bookingTimeRange(booking) {
      if (!booking.start_time || !booking.end_time) return "";
      const start = booking.start_time.split(":").slice(0, 2).join(":");
      const end = booking.end_time.split(":").slice(0, 2).join(":");
      return `${start} - ${end}`;
    },

    bookingCurrency(amount) {
      return this.formatCurrency(amount);
    },

    formatCurrency(value) {
      if (value === undefined || value === null) return "0 đ";
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
      }).format(value);
    },

    statusTextClass(status) {
      return {
        pending_approval: "text-amber-500",
        pending_payment: "text-yellow-500",
        confirmed: "text-green-500",
        checked_in: "text-emerald-500",
        completed: "text-blue-500",
        cancelled: "text-red-500",
        rejected: "text-rose-500",
        expired: "text-zinc-500",
      }[status] || "text-zinc-400";
    },

    handleDocumentClick(e) {
      if (this.showContextMenu) {
        if (e.target.closest('.context-menu-trigger') || e.target.closest('.custom-context-menu')) {
          return;
        }
        this.closeContextMenu();
      }
    },

    openContextMenu(e, message) {
      if (this.showContextMenu && this.contextMenuMessage?.id === message.id) {
        this.closeContextMenu();
        return;
      }
      this.contextMenuMessage = message;
      
      const menuWidth = 160;
      const menuHeight = 130;
      const windowWidth = window.innerWidth;
      const windowHeight = window.innerHeight;
      
      // Offset vertically downward from click cursor by 18px to avoid covering the 3-dots button
      let x = e.clientX;
      let y = e.clientY + 18;
      
      if (x + menuWidth > windowWidth) {
        x = Math.max(10, windowWidth - menuWidth - 10);
      }
      if (y + menuHeight > windowHeight) {
        y = Math.max(10, e.clientY - menuHeight - 10);
      }
      
      this.contextMenuX = x;
      this.contextMenuY = y;
      this.showContextMenu = true;
    },

    closeContextMenu() {
      this.showContextMenu = false;
      this.contextMenuMessage = null;
    },

    handleReply(message) {
      this.replyTarget = message;
      this.closeContextMenu();
      this.$nextTick(() => {
        const inputEl = this.$el.querySelector('.zalo-input');
        if (inputEl) inputEl.focus();
      });
    },

    async handleCopy(message) {
      this.closeContextMenu();
      if (!message.content) return;
      try {
        await navigator.clipboard.writeText(message.content);
      } catch (err) {
        console.error('Failed to copy text:', err);
      }
    },

    async handleTogglePin(message) {
      this.closeContextMenu();
      try {
        const response = await chatService.togglePinMessage(message.id);
        message.is_pinned = response.is_pinned;
      } catch (error) {
        this.toast.error(error.message || 'Không thể thực hiện ghim tin nhắn.');
      }
    },

    toggleHoverReactionPicker(messageId) {
      if (this.hoverReactionTargetId === messageId) {
        this.hoverReactionTargetId = null;
      } else {
        this.hoverReactionTargetId = messageId;
      }
    },

    async submitReaction(message, emoji) {
      this.hoverReactionTargetId = null;
      try {
        const response = await chatService.reactToMessage(message.id, emoji);
        message.reactions = response.reactions;
      } catch (error) {
        console.error('Failed to react to message:', error);
      }
    },

    groupReactions(reactions) {
      if (!reactions || !Array.isArray(reactions)) return {};
      const groups = {};
      reactions.forEach(r => {
        groups[r.emoji] = (groups[r.emoji] || 0) + 1;
      });
      return groups;
    },

    hasUserReacted(reactions, emoji) {
      if (!reactions || !Array.isArray(reactions) || !this.currentUser) return false;
      return reactions.some(r => r.user_id === this.currentUser.id && r.emoji === emoji);
    },

    scrollToMessage(messageId) {
      const el = document.getElementById(`msg-${messageId}`);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        this.highlightedMessageId = messageId;
        setTimeout(() => {
          this.highlightedMessageId = null;
        }, 2000);
      }
    }
  }
};
</script>

<style scoped>
.chat-page {
  /* Default Light Theme Variables */
  --tg-chat-bg: #e7ebf0;
  --tg-sent-bg: #e2f7cb;
  --tg-sent-text: #1a2510;
  --tg-received-bg: #ffffff;
  --tg-received-text: #1f1f1f;
  --tg-meta: #8c9094;
  --tg-meta-sent: #508531;
  --tg-input-bg: #ffffff;
  --tg-input-text: #1f1f1f;
  --tg-sidebar-bg: #ffffff;
  --tg-active-row: #f1f5f9;
  --tg-header-bg: #ffffff;
  --tg-border: #e2e8f0;
  --tg-ticks: #4fae4e;
  --tg-accent: var(--admin-primary, #22a653);

  height: 100vh;
  overflow: hidden;
  background-color: var(--tg-sidebar-bg);
}

.chat-page:not(.admin-chat-page) {
  height: 100vh;
  overflow: hidden;
}

.client-chat-page.admin-chat-page {
  --client-chat-nav-height: 64px;
  --admin-bg: #eef6f0;
  --admin-surface: #ffffff;
  --admin-surface-muted: #f3f8f1;
  --admin-hover: #edf7ed;
  --admin-border: #cfded1;
  --admin-text: #101c15;
  --admin-faint: #45564a;
  --admin-primary: #22a653;
  --admin-primary-text: #ffffff;
  --admin-primary-dark: #15733a;
  --admin-primary-soft: #e2f6e8;
  --admin-primary-ring: rgba(34, 166, 83, 0.22);
  --admin-success-text: #15733a;
  --admin-danger: #dc2626;
  --admin-danger-soft: #fef2f2;
  --admin-danger-hover-text: #7f1d1d;
  --admin-shadow-sm: 0 1px 2px rgba(23, 34, 27, 0.06);
  --admin-shadow-card: 0 6px 18px rgba(23, 34, 27, 0.045);
  --admin-shadow-lg: 0 24px 70px rgba(23, 34, 27, 0.16);
  --admin-card-bg: var(--admin-surface);
  height: 100vh;
  padding-top: var(--client-chat-nav-height);
  border: 0;
  background: var(--admin-bg);
  box-shadow: none;
}
.admin-chat-page {
  --tg-chat-bg: var(--admin-bg);
  --tg-sent-bg: var(--admin-primary-soft);
  --tg-sent-text: var(--admin-text);
  --tg-received-bg: var(--admin-surface);
  --tg-received-text: var(--admin-text);
  --tg-meta: var(--admin-faint);
  --tg-meta-sent: var(--admin-primary-dark);
  --tg-input-bg: var(--admin-surface);
  --tg-input-text: var(--admin-text);
  --tg-sidebar-bg: var(--admin-surface-muted);
  --tg-active-row: var(--admin-hover);
  --tg-header-bg: var(--admin-surface);
  --tg-border: var(--admin-border);
  --tg-ticks: var(--admin-success-text);
  --tg-accent: var(--admin-primary);

  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
  height: auto;
  border: 1px solid var(--admin-border);
  border-radius: 0;
  background: var(--admin-card-bg);
  box-shadow: var(--admin-shadow-card);
}

.admin-chat-workspace {
  flex: 1;
  min-height: 0;
  background: var(--tg-chat-bg) !important;
}

.client-chat-page .admin-chat-workspace {
  height: calc(100vh - var(--client-chat-nav-height));
  border-top: 1px solid var(--admin-border);
  box-shadow: var(--admin-shadow-card);
}

/* Group container classes */
.tg-sidebar-header {
  height: 64px;
  background-color: var(--tg-sidebar-bg);
  border-bottom: 1px solid var(--tg-border);
  padding: 0 16px;
  display: flex;
  align-items: center;
  box-sizing: border-box;
}
.tg-search-input {
  background-color: var(--tg-chat-bg) !important;
  border-color: var(--tg-border) !important;
  color: var(--tg-received-text) !important;
}
.tg-search-input::placeholder {
  color: var(--tg-meta) !important;
}

.admin-chat-page :is(.tg-search-input, .zalo-chat-box, .tg-dropdown-menu, .tg-profile-action-btn) {
  border-radius: 0 !important;
}

.admin-chat-page .tg-sidebar-header > button,
.admin-chat-page :is(.tg-chat-header button, .zalo-attach-btn) {
  border-radius: 0 !important;
  color: var(--tg-meta) !important;
}

.admin-chat-page .tg-sidebar-header > button.never-hover-class-placeholder,
.admin-chat-page :is(.tg-chat-header button, .zalo-attach-btn).never-hover-class-placeholder {
  background: var(--tg-active-row) !important;
  color: var(--tg-received-text) !important;
}

.admin-chat-page .tg-conv-item.active {
  box-shadow: inset 3px 0 0 var(--tg-accent);
}

.admin-chat-page .tg-search-input {
  border-radius: 0 !important;
  background: var(--tg-input-bg) !important;
}

/* Sidebar conversation row spacing */
.tg-conv-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background-color: transparent;
  border-bottom: 1px solid var(--tg-border);
  text-align: left;
}
.tg-conv-item.never-hover-class-placeholder {
  background-color: var(--tg-active-row) !important;
}
.tg-conv-item.active {
  background-color: var(--tg-active-row) !important;
}

/* Chat Header layout spacing */
.tg-chat-header {
  height: 64px;
  background-color: var(--tg-header-bg);
  border-bottom: 1px solid var(--tg-border);
  padding: 0 20px;
  display: flex;
  align-items: center;
}

/* Messages container spacing */
.tg-message-container {
  display: flex;
  flex-direction: column;
  padding: 24px 28px;
  gap: 16px;
  background-color: var(--tg-chat-bg);
}

/* Chat bubble styling exactly like Telegram */
.bubble-row {
  margin-bottom: 12px;
  width: 100%;
}

.bubble-row-highlight .bubble {
  outline: 2px solid var(--tg-accent);
  outline-offset: 3px;
  box-shadow: 0 0 0 6px var(--admin-primary-ring), var(--admin-shadow-sm);
}

.bubble {
  border-radius: 12px;
  box-shadow: var(--admin-shadow-sm);
  font-size: 14px;
  line-height: 1.5;
  display: inline-block;
  padding: 10px 14px;
}

.bubble-sent {
  background-color: var(--tg-sent-bg) !important;
  color: var(--tg-sent-text) !important;
  border-bottom-right-radius: 4px;
}
.bubble-sent .bubble-text {
  color: var(--tg-sent-text) !important;
}

.bubble-received {
  background-color: var(--tg-received-bg) !important;
  color: var(--tg-received-text) !important;
  border-bottom-left-radius: 4px;
  border: 1px solid var(--tg-border);
}
.bubble-received .bubble-text {
  color: var(--tg-received-text) !important;
}

.bubble-text {
  word-wrap: break-word;
  white-space: pre-wrap;
}

.bubble-meta {
  float: right;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: 8px;
  margin-top: 4px;
  position: relative;
  bottom: -2px;
  font-size: 9px;
  user-select: none;
  pointer-events: none;
}

.bubble-sent .bubble-time {
  color: var(--tg-meta-sent) !important;
}
.bubble-sent .bubble-ticks {
  color: var(--tg-ticks) !important;
}

.bubble-received .bubble-time {
  color: var(--tg-meta) !important;
}

/* Input elements spacing */
.tg-input-bar-container {
  background-color: var(--tg-header-bg);
  border-top: 1px solid var(--tg-border);
  padding: 12px 20px;
}

.tg-input {
  background-color: var(--tg-chat-bg) !important;
  border: 1px solid var(--tg-border) !important;
  color: var(--tg-received-text) !important;
}
.tg-input::placeholder {
  color: var(--tg-meta) !important;
}
.tg-input:focus {
  border-color: var(--tg-accent) !important;
}

.tg-send-btn {
  background-color: var(--tg-accent) !important;
}
.tg-send-btn.never-hover-class-placeholder {
  opacity: 0.9;
}

.tg-attach-btn {
  color: var(--tg-meta) !important;
}
.tg-attach-btn.never-hover-class-placeholder {
  color: var(--tg-received-text) !important;
  background-color: var(--tg-active-row) !important;
}

/* Empty States */
.chat-empty-sidebar {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 40px 20px;
  text-align: center;
}
.chat-empty-sidebar__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--tg-meta);
}
.chat-empty-sidebar__sub {
  font-size: 11px;
  color: var(--tg-meta);
  opacity: 0.8;
  line-height: 1.5;
}

.chat-empty-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 48px 32px;
  text-align: center;
  background-color: var(--tg-chat-bg);
}
.chat-empty-main__title {
  font-size: 15px;
  font-weight: 600;
  color: var(--tg-received-text);
}
.chat-empty-main__sub {
  font-size: 12px;
  color: var(--tg-meta);
  max-width: 280px;
  line-height: 1.6;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
  width: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: var(--tg-border);
  border-radius: 0;
}
::-webkit-scrollbar-thumb:hover {
  background: var(--tg-meta);
}

/* ── Avatars ────────────────────────────────── */
.tg-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background-color: var(--tg-accent) !important;
  color: var(--admin-primary-text) !important;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
  border: 1px solid var(--tg-border);
}

.tg-avatar-small {
  width: 40px;
  height: 40px;
  font-size: 14px;
}

/* ── Date Divider ───────────────────────────── */
.tg-date-divider {
  display: inline-block;
  padding: 4px 14px;
  background-color: transparent !important;
  color: var(--tg-meta, #8c9094) !important;
  font-size: 11px;
  font-weight: 600;
  border-radius: 0;
  box-shadow: none;
  backdrop-filter: none;
}

/* Dark theme overrides for date divider text */
.chat-page.bg-zinc-950 .tg-date-divider,
[data-theme="dark"] .chat-page .tg-date-divider {
  background-color: transparent !important;
  color: var(--tg-meta, #7a8e9e) !important;
}

/* ── Telegram Menu Drawer ────────────────────── */
.tg-drawer-panel {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: none !important;
  background-color: var(--tg-sidebar-bg) !important;
  border-right: 1px solid var(--tg-border) !important;
}

.tg-drawer-header {
  background-color: var(--tg-header-bg) !important;
  border-bottom: 1px solid var(--tg-border) !important;
  padding: 14px 16px 12px !important;
}

.tg-drawer-header-name {
  color: var(--tg-received-text) !important;
}

.tg-drawer-header-sub {
  color: var(--tg-meta) !important;
}

.tg-drawer-nav {
  background-color: var(--tg-sidebar-bg) !important;
}

.tg-drawer-item {
  display: flex !important;
  align-items: center !important;
  justify-content: flex-start !important;
  gap: 16px !important;
  padding: 9px 16px !important;
  color: var(--tg-received-text) !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  transition: background-color 150ms ease !important;
  width: 100% !important;
  min-height: auto !important;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  border-radius: 6px !important;
  white-space: nowrap !important;
  cursor: pointer !important;
  text-decoration: none !important;
}

.tg-drawer-item.never-hover-class-placeholder {
  background-color: var(--tg-active-row) !important;
  color: var(--tg-received-text) !important;
}

.tg-drawer-icon {
  color: var(--tg-meta) !important;
  opacity: 0.75 !important;
  flex-shrink: 0 !important;
  width: 18px !important;
  height: 18px !important;
}

.tg-drawer-divider {
  height: 1px !important;
  background-color: var(--tg-border) !important;
  margin: 4px 16px !important;
}

.tg-drawer-toggle-row {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  padding: 9px 16px !important;
  color: var(--tg-received-text) !important;
  font-size: 13px !important;
}

.tg-toggle-switch {
  position: relative !important;
  width: 34px !important;
  height: 20px !important;
  min-height: auto !important;
  min-width: auto !important;
  border-radius: 10px !important;
  padding: 2px !important;
  border: none !important;
  cursor: pointer !important;
  transition: background-color 200ms ease !important;
  box-shadow: none !important;
}
.tg-toggle-on {
  background-color: var(--tg-accent) !important;
}
.tg-toggle-off {
  background-color: var(--admin-border) !important;
}
.tg-toggle-knob {
  display: block !important;
  width: 16px !important;
  height: 16px !important;
  border-radius: 50% !important;
  background: var(--admin-primary-text) !important;
  box-shadow: var(--admin-shadow-sm) !important;
  transition: transform 200ms ease !important;
}
.tg-knob-on {
  transform: translateX(14px) !important;
}
.tg-knob-off {
  transform: translateX(0) !important;
}

/* Zalo style input box styling */
.zalo-chat-box {
  background-color: var(--tg-received-bg) !important;
  border: 1px solid var(--tg-border) !important;
  border-radius: 16px !important;
  overflow: hidden !important;
  box-shadow: var(--admin-shadow-sm) !important;
  transition: border-color 150ms ease;
}

.zalo-chat-box:focus-within {
  border-color: var(--tg-accent) !important;
}

.zalo-input-row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 16px !important;
  box-sizing: border-box;
}

.sg-shell-admin .content-area input.zalo-input {
  color: var(--tg-received-text) !important;
  background-color: transparent !important;
  border: none !important;
  box-shadow: none !important;
  min-height: auto !important;
  padding: 8px 0 !important;
  width: 100% !important;
}

.sg-shell-admin .content-area input.zalo-input:focus {
  border: none !important;
  box-shadow: none !important;
  outline: none !important;
}

.zalo-input::placeholder {
  color: var(--tg-meta) !important;
}

.zalo-attach-btn {
  color: var(--tg-meta) !important;
  background: transparent !important;
  border: none !important;
  cursor: pointer !important;
}
.zalo-attach-btn.never-hover-class-placeholder {
  color: var(--tg-received-text) !important;
  background-color: var(--tg-active-row) !important;
}

.zalo-send-btn {
  background-color: var(--admin-primary) !important;
  color: var(--admin-primary-text) !important;
  border: none !important;
  cursor: pointer !important;
  border-radius: 9999px !important;
  transition: all 0.2s ease;
}

.zalo-send-btn.never-hover-class-placeholder:not(:disabled) {
  opacity: 0.9;
  transform: scale(1.05);
}

.zalo-send-btn:disabled {
  opacity: 0.45 !important;
  cursor: not-allowed !important;
}

.zalo-divider {
  border-top: 1px solid var(--tg-border) !important;
  margin: 0 16px !important;
}

.zalo-preview-area {
  background-color: color-mix(in srgb, var(--tg-chat-bg) 25%, var(--tg-received-bg)) !important;
  padding: 12px 16px !important;
}

.zalo-preview-header {
  color: var(--tg-meta) !important;
}

.zalo-clear-all {
  color: var(--tg-meta) !important;
  background: transparent !important;
  cursor: pointer !important;
}

.zalo-clear-all.never-hover-class-placeholder {
  color: var(--admin-danger) !important;
}

.zalo-thumb-item {
  border-color: var(--tg-border) !important;
}

.zalo-plus-btn {
  border-color: var(--tg-border) !important;
  color: var(--tg-meta) !important;
  background-color: transparent !important;
  cursor: pointer !important;
}

.zalo-plus-btn.never-hover-class-placeholder {
  background-color: var(--tg-active-row) !important;
  color: var(--tg-received-text) !important;
  border-color: var(--tg-meta) !important;
}

.booking-message-card {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: min(280px, 100%);
  border: 1px solid var(--tg-border);
  background: color-mix(in srgb, var(--tg-received-bg) 88%, var(--tg-accent));
  color: var(--tg-received-text);
  padding: 12px;
}

.bubble-sent .booking-message-card {
  background: color-mix(in srgb, var(--tg-sent-bg) 82%, var(--tg-received-bg));
  color: var(--tg-sent-text);
}

.booking-message-card__top,
.booking-message-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.booking-message-card__eyebrow {
  color: var(--tg-meta);
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.booking-message-card__code {
  font-size: 12px;
  font-weight: 500;
  color: var(--tg-accent);
  font-family: monospace;
}

.booking-message-card__amount {
  font-size: 14px;
  font-weight: 500;
  color: var(--tg-accent);
}

.booking-message-card__venue {
  font-size: 14px;
  font-weight: 500;
}

.booking-message-card__meta {
  display: flex;
  flex-direction: column;
  gap: 3px;
  color: var(--tg-meta);
  font-size: 12px;
  line-height: 1.35;
}

.booking-message-card__status {
  font-size: 12px;
  font-weight: 500;
}

.booking-message-card__actions,
.support-request-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.booking-message-card__action,
.support-request-card__action {
  min-height: 30px;
  border: 1px solid var(--tg-accent);
  border-radius: 8px;
  background: color-mix(in srgb, var(--tg-accent) 14%, transparent);
  color: var(--tg-accent);
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
  padding: 0 10px;
}

.booking-message-card__action.never-hover-class-placeholder:not(:disabled),
.support-request-card__action.never-hover-class-placeholder:not(:disabled),
.booking-message-card__action:focus-visible,
.support-request-card__action:focus-visible {
  background: var(--tg-accent);
  color: var(--admin-primary-text, #ffffff);
  outline: none;
}

.support-request-card__action:disabled {
  cursor: wait;
  opacity: 0.6;
}

.support-request-card__action--danger {
  border-color: var(--admin-danger, #dc2626);
  background: color-mix(in srgb, var(--admin-danger, #dc2626) 12%, transparent);
  color: var(--admin-danger, #dc2626);
}

.support-request-card__action--danger.never-hover-class-placeholder:not(:disabled),
.support-request-card__action--danger:focus-visible {
  background: var(--admin-danger, #dc2626);
  color: #ffffff;
}

.support-request-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: min(300px, 100%);
  border: 1px solid var(--tg-border);
  background: color-mix(in srgb, var(--tg-received-bg) 90%, var(--tg-accent));
  color: var(--tg-received-text);
  padding: 12px;
}

.bubble-sent .support-request-card {
  background: color-mix(in srgb, var(--tg-sent-bg) 82%, var(--tg-received-bg));
  color: var(--tg-sent-text);
}

.support-request-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.support-request-card__eyebrow {
  color: var(--tg-meta);
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.support-request-card__status,
.support-request-card__code {
  font-size: 12px;
  font-weight: 700;
}

.support-request-card__code {
  color: var(--tg-accent);
  font-family: monospace;
  margin-left: 6px;
}

.support-request-card__title {
  font-size: 14px;
  font-weight: 700;
}

.support-request-card__meta,
.support-request-card__note {
  color: var(--tg-meta);
  font-size: 12px;
  line-height: 1.45;
}

.support-request-card__meta {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.support-request-card__note {
  border-left: 2px solid var(--tg-accent);
  padding-left: 8px;
}

.support-request-card__note--resolution {
  border-left-color: var(--admin-success, #16a34a);
}

.support-request-modal {
  max-width: 480px;
}

.support-request-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 18px 20px 20px;
}

.support-request-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.support-request-field label {
  color: var(--tg-received-text);
  font-size: 12px;
  font-weight: 700;
}

.support-request-field p {
  margin: 0;
  color: var(--tg-meta);
  font-size: 11px;
  line-height: 1.45;
}

.support-request-input {
  width: 100%;
  border: 1px solid var(--tg-border);
  border-radius: 8px;
  background: var(--tg-input-bg);
  color: var(--tg-input-text);
  font-size: 13px;
  outline: none;
  padding: 10px 12px;
}

.support-request-input:focus {
  border-color: var(--tg-accent);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--tg-accent) 18%, transparent);
}

.support-request-textarea {
  min-height: 96px;
  resize: vertical;
}

.support-request-error {
  border: 1px solid color-mix(in srgb, var(--admin-danger, #dc2626) 42%, transparent);
  border-radius: 8px;
  background: color-mix(in srgb, var(--admin-danger, #dc2626) 10%, transparent);
  color: var(--admin-danger, #dc2626);
  font-size: 12px;
  line-height: 1.4;
  padding: 10px 12px;
}

.support-request-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.support-request-primary,
.support-request-secondary {
  min-height: 36px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
  padding: 0 14px;
}

.support-request-primary {
  border: 1px solid var(--tg-accent);
  background: var(--tg-accent);
  color: var(--admin-primary-text, #ffffff);
}

.support-request-primary:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.support-request-secondary {
  border: 1px solid var(--tg-border);
  background: transparent;
  color: var(--tg-received-text);
}
/* Premium Booking Picker Styles */
.booking-picker-backdrop {
  position: absolute;
  inset: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(9, 9, 11, 0.7) !important;
  backdrop-filter: blur(8px);
  padding: 24px;
}

.booking-picker-panel {
  width: min(520px, 100%);
  max-height: min(600px, calc(100vh - 48px));
  border: 1px solid var(--tg-border) !important;
  border-radius: 12px;
  background: var(--tg-input-bg) !important;
  box-shadow: var(--admin-shadow-lg) !important;
  color: var(--tg-received-text);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.booking-picker-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--tg-border);
  padding: 16px 20px;
}

.booking-picker-header h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--tg-received-text);
}

.booking-picker-header p {
  margin: 2px 0 0;
  color: var(--tg-meta);
  font-size: 12px;
}

.booking-picker-close {
  display: inline-flex;
  width: 28px;
  height: 28px;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--tg-meta);
  cursor: pointer;
  transition: background-color 150ms ease, color 150ms ease;
}

.booking-picker-close.never-hover-class-placeholder {
  background: var(--tg-active-row);
  color: var(--tg-received-text);
}

.booking-picker-close svg {
  width: 14px;
  height: 14px;
}

.booking-picker-list {
  overflow-y: auto;
  padding: 8px 0;
}

.booking-picker-row {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border: 0;
  border-bottom: 1px solid var(--tg-border) !important;
  background: transparent;
  padding: 12px 20px;
  text-align: left;
  cursor: default;
  transition: background-color 150ms ease;
}

.booking-picker-row:last-child {
  border-bottom: 0 !important;
}

.booking-picker-row.never-hover-class-placeholder {
  background-color: var(--tg-active-row) !important;
}

.booking-picker-row__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: 12px;
  flex-shrink: 0;
}

.booking-picker-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 500;
  border-radius: 6px;
  cursor: pointer;
  transition: all 150ms ease;
  min-height: 28px;
  white-space: nowrap;
}

.booking-picker-action-btn--share {
  background: var(--tg-accent);
  color: var(--admin-primary-text, #ffffff);
  border: 1px solid var(--tg-accent);
}

.booking-picker-action-btn--share.never-hover-class-placeholder:not(:disabled) {
  opacity: 0.9;
}

.booking-picker-action-btn--share:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.booking-picker-action-btn--support {
  background: transparent;
  color: var(--tg-received-text);
  border: 1px solid var(--tg-border);
}

.booking-picker-action-btn--support.never-hover-class-placeholder {
  background-color: var(--tg-active-row);
  border-color: var(--tg-accent);
  color: var(--tg-accent);
}

.booking-picker-row__main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  flex: 1;
}

.booking-picker-row__title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.booking-picker-row__code {
  font-size: 12px;
  font-weight: 500;
  color: var(--tg-accent);
  font-family: monospace;
}

.booking-picker-row__venue {
  font-size: 13px;
  color: var(--tg-received-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.booking-picker-row__details {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 11px;
  color: var(--tg-meta);
}

.bullet-dot {
  color: var(--tg-border);
  font-size: 8px;
}

.booking-picker-row__side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}

.booking-picker-row__price {
  font-size: 13px;
  font-weight: 500;
  color: var(--tg-received-text);
}

.booking-picker-row__status {
  font-size: 11px;
}

.booking-picker-state {
  padding: 32px 20px;
  color: var(--tg-meta);
  font-size: 12px;
  text-align: center;
}

.tg-related-bookings-head {
  display: flex !important;
  align-items: flex-start !important;
  justify-content: space-between !important;
  gap: 12px !important;
}

.tg-related-bookings-sub {
  margin: -4px 0 0 !important;
  color: var(--tg-meta) !important;
  font-size: 11px !important;
  line-height: 1.4 !important;
}

.tg-related-bookings-refresh {
  display: inline-flex !important;
  width: 32px !important;
  height: 32px !important;
  align-items: center !important;
  justify-content: center !important;
  border: 1px solid var(--tg-border) !important;
  border-radius: 8px !important;
  background: var(--tg-active-row) !important;
  color: var(--tg-meta) !important;
  cursor: pointer !important;
  flex: 0 0 auto !important;
  transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease !important;
}

.tg-related-bookings-refresh.never-hover-class-placeholder:not(:disabled),
.tg-related-bookings-refresh:focus-visible {
  border-color: var(--tg-accent) !important;
  color: var(--tg-accent) !important;
  outline: none !important;
}

.tg-related-bookings-refresh:disabled {
  cursor: wait !important;
  opacity: 0.6 !important;
}

.tg-related-bookings-refresh svg {
  width: 16px !important;
  height: 16px !important;
}

.tg-related-bookings-list {
  display: flex !important;
  flex-direction: column !important;
  gap: 8px !important;
}

.tg-related-bookings-state {
  border: 1px solid var(--tg-border) !important;
  border-radius: 8px !important;
  background: color-mix(in srgb, var(--tg-active-row) 72%, transparent) !important;
  color: var(--tg-meta) !important;
  font-size: 12px !important;
  line-height: 1.45 !important;
  padding: 12px !important;
}

.tg-related-bookings-state--error {
  color: var(--admin-danger, #dc2626) !important;
}

.tg-related-booking-row {
  display: flex !important;
  align-items: stretch !important;
  gap: 10px !important;
  width: 100% !important;
  border: 1px solid var(--tg-border) !important;
  border-radius: 8px !important;
  background: var(--tg-active-row) !important;
  color: var(--tg-received-text) !important;
  padding: 10px !important;
  box-sizing: border-box !important;
}

.tg-related-booking-main {
  display: flex !important;
  min-width: 0 !important;
  flex: 1 !important;
  flex-direction: column !important;
  gap: 4px !important;
}

.tg-related-booking-top {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  gap: 8px !important;
}

.tg-related-booking-code {
  color: var(--tg-accent) !important;
  font-family: monospace !important;
  font-size: 12px !important;
  font-weight: 700 !important;
}

.tg-related-booking-status {
  flex: 0 0 auto !important;
  font-size: 11px !important;
  font-weight: 600 !important;
}

.tg-related-booking-venue,
.tg-related-booking-price {
  color: var(--tg-received-text) !important;
  font-size: 12px !important;
  font-weight: 600 !important;
}

.tg-related-booking-meta {
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 6px 10px !important;
  color: var(--tg-meta) !important;
  font-size: 11px !important;
  line-height: 1.35 !important;
}

.tg-related-booking-send {
  align-self: center !important;
  min-width: 48px !important;
  min-height: 34px !important;
  border: 1px solid var(--tg-accent) !important;
  border-radius: 8px !important;
  background: color-mix(in srgb, var(--tg-accent) 14%, transparent) !important;
  color: var(--tg-accent) !important;
  cursor: pointer !important;
  font-size: 12px !important;
  font-weight: 700 !important;
  padding: 0 10px !important;
  transition: background-color 150ms ease, color 150ms ease !important;
}

.tg-related-booking-send.never-hover-class-placeholder:not(:disabled),
.tg-related-booking-send:focus-visible {
  background: var(--tg-accent) !important;
  color: var(--admin-primary-text, #ffffff) !important;
  outline: none !important;
}

.tg-related-booking-send:disabled {
  cursor: wait !important;
  opacity: 0.6 !important;
}
/* Telegram Dropdown Menu styling */
.tg-dropdown-menu {
  background-color: var(--tg-header-bg) !important;
  border: 1px solid var(--tg-border) !important;
  border-radius: 12px !important;
  box-shadow: var(--admin-shadow-lg) !important;
  overflow: hidden !important;
}

.tg-dropdown-item {
  color: var(--tg-received-text) !important;
  display: flex !important;
  align-items: center !important;
  width: 100% !important;
  padding: 8px 16px !important;
  font-size: 13px !important;
  background: transparent !important;
  border: none !important;
  cursor: pointer !important;
  text-align: left !important;
  transition: background-color 150ms ease !important;
  box-sizing: border-box !important;
}

.tg-dropdown-item.never-hover-class-placeholder {
  background-color: var(--tg-active-row) !important;
  color: var(--tg-received-text) !important;
}

.tg-dropdown-icon {
  color: var(--tg-meta) !important;
  width: 16px !important;
  height: 16px !important;
  flex-shrink: 0 !important;
  margin-right: 12px !important;
}

.tg-dropdown-item-danger {
  color: var(--admin-danger) !important;
}

.tg-dropdown-item-danger.never-hover-class-placeholder {
  background-color: var(--admin-danger-soft) !important;
}

.tg-dropdown-item-danger .tg-dropdown-icon {
  color: var(--admin-danger) !important;
}

.tg-dropdown-divider {
  height: 1px !important;
  background-color: var(--tg-border) !important;
  margin: 4px 0 !important;
}

/* Profile Sidebar Styling */
.tg-profile-sidebar {
  background-color: var(--tg-sidebar-bg) !important;
  border-left: 1px solid var(--tg-border) !important;
  color: var(--tg-received-text) !important;
}

@media (max-width: 768px) {
  .tg-profile-sidebar {
    position: absolute !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100% !important;
    max-width: 320px !important;
    z-index: 40 !important;
    box-shadow: var(--admin-shadow-lg) !important;
    border-left: 1px solid var(--tg-border) !important;
  }
}

.tg-profile-body {
  padding-top: 48px !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 24px !important;
}

.tg-profile-title {
  color: var(--tg-received-text) !important;
  font-size: 15px;
  font-weight: 600;
}

.tg-profile-section-title {
  color: var(--tg-meta) !important;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px !important;
}

.tg-profile-section {
  padding: 0 24px !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 16px !important;
  width: 100% !important;
  box-sizing: border-box !important;
}

.tg-profile-item {
  display: flex !important;
  align-items: flex-start !important;
  gap: 16px !important;
  width: 100% !important;
}

.tg-profile-item-button {
  border: 0 !important;
  background: transparent !important;
  color: var(--tg-received-text) !important;
  cursor: pointer !important;
  padding: 0 !important;
  text-align: left !important;
}

.tg-profile-item-button.never-hover-class-placeholder:not(:disabled) .tg-profile-value,
.tg-profile-item-button.never-hover-class-placeholder:not(:disabled) .tg-profile-item-icon {
  color: var(--tg-accent) !important;
}

.tg-profile-item-button:disabled {
  cursor: default !important;
  opacity: 0.65;
}

.tg-profile-item-icon {
  color: var(--tg-meta) !important;
  width: 18px !important;
  height: 18px !important;
  flex-shrink: 0 !important;
  margin-top: 2px !important;
}

.tg-profile-item-content {
  display: flex !important;
  flex-direction: column !important;
  gap: 2px !important;
  min-width: 0 !important;
  flex: 1 !important;
}

.tg-profile-label {
  color: var(--tg-meta) !important;
  font-size: 11px;
  opacity: 0.8;
}

.tg-profile-value {
  color: var(--tg-received-text) !important;
  font-size: 14px;
  font-weight: 500;
  word-wrap: break-word !important;
  word-break: break-all !important;
}

/* Dynamic theme avatar in profile info panel */
.tg-profile-avatar {
  width: 64px !important;
  height: 64px !important;
  border-radius: 50% !important;
  background-color: var(--tg-accent) !important;
  color: var(--admin-primary-text, #ffffff) !important;
  font-size: 24px !important;
  font-weight: 700 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  box-shadow: var(--admin-shadow-card) !important;
  margin-bottom: 12px !important;
  user-select: none !important;
}

/* Quick action buttons row */
.tg-profile-action-btn {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  gap: 6px !important;
  background-color: var(--tg-active-row) !important;
  color: var(--tg-accent) !important;
  border: none !important;
  border-radius: 8px !important;
  padding: 8px 0 !important;
  width: 72px !important;
  cursor: pointer !important;
  font-size: 11px !important;
  font-weight: 500 !important;
  transition: background-color 150ms ease !important;
}
.tg-profile-action-btn.never-hover-class-placeholder {
  background-color: color-mix(in srgb, var(--tg-active-row) 80%, var(--tg-received-text)) !important;
}
.tg-profile-action-btn span {
  color: var(--tg-received-text) !important;
}

/* Indented details block */
.tg-profile-info-block {
  padding-left: 56px !important;
  padding-right: 24px !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 2px !important;
  width: 100% !important;
  box-sizing: border-box !important;
}

/* Action Rows */
.tg-profile-action-row {
  display: flex !important;
  align-items: center !important;
  gap: 16px !important;
  width: 100% !important;
  background: transparent !important;
  border: none !important;
  color: var(--tg-received-text) !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  padding: 0 !important;
  cursor: pointer !important;
  text-align: left !important;
  box-sizing: border-box !important;
}
.tg-profile-action-row.never-hover-class-placeholder {
  color: var(--tg-accent) !important;
}
.tg-profile-action-row-danger {
  color: var(--admin-danger) !important;
}
.tg-profile-action-row-danger .tg-profile-item-icon {
  color: var(--admin-danger) !important;
}
.tg-profile-action-row-danger.never-hover-class-placeholder {
  color: var(--admin-danger-hover-text) !important;
}
.tg-media-browser {
  background: var(--tg-sidebar-bg) !important;
  color: var(--tg-received-text) !important;
}

.tg-media-header {
  display: flex !important;
  height: 56px !important;
  align-items: center !important;
  gap: 14px !important;
  border-bottom: 1px solid var(--tg-border) !important;
  background: var(--tg-header-bg) !important;
  padding: 0 14px !important;
  color: var(--tg-received-text) !important;
  font-size: 15px !important;
  font-weight: 700 !important;
}

.tg-media-back {
  display: inline-flex !important;
  width: 34px !important;
  height: 34px !important;
  align-items: center !important;
  justify-content: center !important;
  border: 0 !important;
  border-radius: 0 !important;
  background: transparent !important;
  color: var(--tg-meta) !important;
  cursor: pointer !important;
}

.tg-media-back.never-hover-class-placeholder {
  background: var(--tg-active-row) !important;
  color: var(--tg-received-text) !important;
}

.tg-media-scroll {
  padding: 12px 0 18px !important;
}

.tg-media-group {
  padding: 0 4px 14px !important;
}

.tg-media-group h4 {
  margin: 0 !important;
  padding: 10px 10px 8px !important;
  color: var(--tg-received-text) !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  text-transform: capitalize !important;
}

.tg-media-grid {
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: 2px !important;
}

.tg-media-thumb {
  position: relative !important;
  display: block !important;
  aspect-ratio: 1 / 1 !important;
  overflow: hidden !important;
  border: 0 !important;
  border-radius: 0 !important;
  background: var(--tg-active-row) !important;
  cursor: pointer !important;
  padding: 0 !important;
}

.tg-media-thumb img {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover !important;
}


.tg-media-empty {
  display: grid !important;
  flex: 1 !important;
  place-items: center !important;
  color: var(--tg-meta) !important;
  font-size: 13px !important;
  line-height: 1.5 !important;
  padding: 28px !important;
  text-align: center !important;
}

.tg-lightbox {
  --tg-lightbox-scrim: var(--admin-bg, #09090b);
  --tg-lightbox-fg: var(--admin-primary-text, #ffffff);
  --tg-lightbox-muted: var(--admin-primary-text, #ffffff);
  --tg-lightbox-subtle: var(--admin-primary-text, #ffffff);
  --tg-lightbox-hover: var(--admin-primary-soft, rgba(255, 255, 255, 0.14));
  --tg-lightbox-control-bg: var(--admin-surface, var(--tg-header-bg));
  --tg-lightbox-control-text: var(--admin-text, var(--tg-received-text));
  --tg-lightbox-control-muted: var(--admin-muted, var(--tg-meta));
  --tg-lightbox-menu-bg: var(--admin-surface, var(--tg-header-bg));
  --tg-lightbox-menu-text: var(--admin-text, var(--tg-received-text));
  --tg-lightbox-menu-hover: var(--tg-active-row);
  --tg-lightbox-menu-icon: var(--tg-meta);

  background: color-mix(in srgb, var(--tg-lightbox-scrim) 60%, transparent) !important;
  backdrop-filter: none;
  padding: 72px 24px 118px !important;
}

.tg-lightbox-topbar {
  position: absolute;
  inset: 0 0 auto;
  z-index: 2;
  display: flex;
  height: 56px;
  align-items: center;
  justify-content: space-between;
  padding: 0 18px;
  color: var(--tg-lightbox-fg);
  pointer-events: none;
}

.tg-lightbox-count {
  font-size: 13px;
  font-weight: 700;
}

.tg-lightbox-close {
  display: inline-flex;
  width: 40px;
  height: 40px;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--tg-border);
  border-radius: 0;
  background: var(--tg-lightbox-control-bg);
  color: var(--tg-lightbox-control-text);
  cursor: pointer;
  pointer-events: auto;
}

.tg-lightbox-close.never-hover-class-placeholder {
  background: var(--tg-lightbox-menu-hover);
  color: var(--tg-lightbox-control-text);
}

.tg-lightbox-image {
  max-width: min(100%, 1280px);
  max-height: calc(100vh - 210px);
  object-fit: contain;
  box-shadow: var(--admin-shadow-lg);
}

.tg-lightbox-info {
  position: absolute;
  left: 18px;
  bottom: 18px;
  z-index: 3;
  max-width: min(420px, calc(100vw - 36px));
  border: 1px solid var(--tg-border);
  background: var(--tg-lightbox-control-bg);
  box-shadow: var(--admin-shadow-lg);
  color: var(--tg-lightbox-control-text);
  font-size: 13px;
  line-height: 1.55;
  padding: 8px 10px;
  pointer-events: auto;
  user-select: none;
}

.tg-lightbox-info-title {
  color: var(--tg-lightbox-control-text);
  font-weight: 700;
}

.tg-lightbox-info-meta {
  color: var(--tg-lightbox-control-muted);
  opacity: 1;
}

.tg-lightbox-actions {
  position: absolute;
  right: 18px;
  bottom: 18px;
  z-index: 4;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--tg-lightbox-control-text);
  pointer-events: auto;
}

.tg-lightbox-action-btn {
  display: inline-flex;
  width: 34px;
  height: 34px;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--tg-border);
  border-radius: 0;
  background: var(--tg-lightbox-control-bg);
  box-shadow: var(--admin-shadow-sm);
  color: currentColor;
  cursor: pointer;
  opacity: 1;
  padding: 0;
}

.tg-lightbox-action-btn.never-hover-class-placeholder {
  background: var(--tg-lightbox-menu-hover);
  color: var(--tg-lightbox-control-text);
  opacity: 1;
}

.tg-lightbox-action-btn svg {
  width: 22px;
  height: 22px;
}

.tg-lightbox-more-wrap {
  position: relative;
}

.tg-lightbox-menu {
  position: absolute;
  right: 0;
  bottom: calc(100% + 10px);
  width: 210px;
  border: 1px solid var(--tg-border);
  border-radius: 0;
  background: var(--tg-lightbox-menu-bg);
  box-shadow: var(--admin-shadow-lg);
  color: var(--tg-lightbox-menu-text);
  padding: 8px 0;
}

.tg-lightbox-menu button {
  display: flex;
  width: 100%;
  min-height: 36px;
  align-items: center;
  gap: 12px;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: inherit;
  cursor: pointer;
  font-size: 14px;
  padding: 8px 14px;
  text-align: left;
}

.tg-lightbox-menu button.never-hover-class-placeholder {
  background: var(--tg-lightbox-menu-hover);
  color: var(--tg-received-text);
}

.tg-lightbox-menu svg {
  width: 20px;
  height: 20px;
  flex: 0 0 auto;
  color: var(--tg-lightbox-menu-icon);
}

.tg-lightbox-filmstrip {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 2;
  display: flex;
  justify-content: center;
  gap: 8px;
  overflow-x: auto;
  background: transparent;
  padding: 16px 20px 18px;
}

.tg-lightbox-thumb {
  width: 76px;
  height: 56px;
  flex: 0 0 auto;
  overflow: hidden;
  border: 2px solid transparent;
  border-radius: 0;
  background: var(--tg-lightbox-hover);
  cursor: pointer;
  opacity: 0.62;
  padding: 0;
}

.tg-lightbox-thumb.active {
  border-color: var(--tg-accent);
  opacity: 1;
}

.tg-lightbox-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.lightbox-nav {
  position: absolute;
  top: 50%;
  display: inline-flex;
  width: 44px;
  height: 44px;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--tg-border);
  background: var(--tg-lightbox-control-bg);
  box-shadow: var(--admin-shadow-sm);
  color: var(--tg-lightbox-control-text);
  opacity: 1;
  transform: translateY(-50%);
  transition: background-color 150ms ease, color 150ms ease;
}

.lightbox-nav.never-hover-class-placeholder {
  background: var(--tg-lightbox-menu-hover);
  color: var(--tg-lightbox-control-text);
}

.lightbox-nav-prev {
  left: 24px;
}

.lightbox-nav-next {
  right: 24px;
}

/* Custom Context Menu */
.custom-context-menu {
  position: fixed;
  background: rgba(30, 30, 30, 0.9);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
  padding: 4px;
  min-width: 160px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.context-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 12px;
  font-size: 12px;
  color: #d1d5db;
  background: transparent;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: left;
}
.context-menu-item.never-hover-class-placeholder {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
}
.context-menu-item svg {
  color: #9ca3af;
  transition: color 0.15s ease;
}
.context-menu-item.never-hover-class-placeholder svg {
  color: var(--tg-accent, var(--admin-primary, #22a653));
}

/* Reply Quote Block inside bubble */
.reply-quote-block {
  max-width: 100%;
  border-radius: 4px;
  font-size: 11px;
}

/* Highlight Animation */
.bubble-row-highlight .bubble {
  animation: messageHighlight 2s ease;
}
@keyframes messageHighlight {
  0% {
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.5);
    background-color: rgba(16, 185, 129, 0.15);
  }
  100% {
    box-shadow: none;
  }
}

</style>

<!-- Global style override using [data-admin-chat] for scoping layout values -->
<style src="/resources/css/views/client-chat-themes.css"></style>
