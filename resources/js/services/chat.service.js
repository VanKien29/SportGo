import { api } from './api.js';

export const chatService = {
  getConversations() {
    return api('/api/chat/conversations');
  },
  createGroupConversation(name, userIds, avatarFile = null) {
    const formData = new FormData();
    formData.append('type', 'group');
    formData.append('name', name);
    userIds.forEach(id => formData.append('user_ids[]', id));
    if (avatarFile) {
      formData.append('avatar', avatarFile);
    }
    return api('/api/chat/conversations', {
      method: 'POST',
      body: formData
    });
  },
  getMessages(conversationId) {
    return api(`/api/chat/conversations/${conversationId}/messages`);
  },
  sendMessage(conversationId, content, imageFile = null, replyToId = null) {
    if (imageFile) {
      const formData = new FormData();
      if (content) {
        formData.append('content', content);
      }
      formData.append('image', imageFile);
      if (replyToId) {
        formData.append('reply_to_id', replyToId);
      }
      return api(`/api/chat/conversations/${conversationId}/messages`, {
        method: 'POST',
        body: formData
      });
    }
    return api(`/api/chat/conversations/${conversationId}/messages`, {
      method: 'POST',
      body: JSON.stringify({ content, reply_to_id: replyToId })
    });
  },
  reactToMessage(messageId, emoji) {
    return api(`/api/chat/messages/${messageId}/react`, {
      method: 'POST',
      body: JSON.stringify({ emoji })
    });
  },
  togglePinMessage(messageId) {
    return api(`/api/chat/messages/${messageId}/pin`, {
      method: 'POST'
    });
  },
  recallMessage(messageId) {
    return api(`/api/chat/messages/${messageId}/recall`, {
      method: 'POST'
    });
  },
  deleteMessageForSelf(messageId) {
    return api(`/api/chat/messages/${messageId}`, {
      method: 'DELETE'
    });
  },
  markAsRead(conversationId) {
    return api(`/api/chat/conversations/${conversationId}/read`, {
      method: 'POST'
    });
  },
  deleteConversation(conversationId, options = {}) {
    return api(`/api/chat/conversations/${conversationId}`, {
      method: 'DELETE',
      body: JSON.stringify(options)
    });
  },
  clearConversation(conversationId, options = {}) {
    return api(`/api/chat/conversations/${conversationId}/clear`, {
      method: 'POST',
      body: JSON.stringify(options)
    });
  },
  searchUsers(query) {
    return api(`/api/chat/users/search?query=${encodeURIComponent(query)}`);
  },
  startConversation(payload) {
    return api('/api/chat/conversations', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  },
  getEligibleBookings(conversationId) {
    return api(`/api/chat/conversations/${conversationId}/bookings`);
  },
  getRelatedBookings(conversationId) {
    return api('/api/chat/conversations/' + conversationId + '/related-bookings');
  },
  createBookingSupportRequest(conversationId, payload) {
    return api('/api/chat/conversations/' + conversationId + '/support-requests', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  },
  updateBookingSupportRequest(requestId, payload) {
    return api('/api/chat/support-requests/' + requestId, {
      method: 'PATCH',
      body: JSON.stringify(payload)
    });
  },
  sendBooking(conversationId, bookingId) {
    return api(`/api/chat/conversations/${conversationId}/bookings`, {
      method: 'POST',
      body: JSON.stringify({ booking_id: bookingId })
    });
  },
  getGuestToken() {
    let token = localStorage.getItem('sportgo_guest_token');
    if (!token) {
      token = 'guest_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
      localStorage.setItem('sportgo_guest_token', token);
    }
    return token;
  },
  async getAiHistory() {
    try {
      const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
      const guestToken = this.getGuestToken();
      const headers = {
        'Accept': 'application/json',
        'X-Guest-Token': guestToken,
      };
      if (token) headers['Authorization'] = `Bearer ${token}`;

      const res = await fetch(`/api/chat/ai-history?v=${Date.now()}`, { headers });
      return await res.json().catch(() => ({ messages: [] }));
    } catch (e) {
      console.error('Lỗi getAiHistory:', e);
      return { messages: [] };
    }
  },
  async askAiAssistant(payload) {
    try {
      const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
      const guestToken = this.getGuestToken();
      const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Guest-Token': guestToken,
      };
      if (token) headers['Authorization'] = `Bearer ${token}`;

      const fullPayload = {
        ...payload,
        session_token: guestToken,
      };

      const res = await fetch(`/api/chat/ai-assistant?v=${Date.now()}`, {
        method: 'POST',
        headers,
        body: JSON.stringify(fullPayload)
      });
      const data = await res.json().catch(() => ({}));
      if (data.session_token && data.session_token !== guestToken) {
        localStorage.setItem('sportgo_guest_token', data.session_token);
      }
      return data;
    } catch (e) {
      console.error('Lỗi askAiAssistant:', e);
      return { success: false, reply: null };
    }
  }
};
