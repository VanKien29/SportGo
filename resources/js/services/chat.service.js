import { api } from './api.js';

export const chatService = {
  getConversations() {
    return api('/api/chat/conversations');
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
  markAsRead(conversationId) {
    return api(`/api/chat/conversations/${conversationId}/read`, {
      method: 'POST'
    });
  },
  deleteConversation(conversationId) {
    return api(`/api/chat/conversations/${conversationId}`, {
      method: 'DELETE'
    });
  },
  clearConversation(conversationId) {
    return api(`/api/chat/conversations/${conversationId}/clear`, {
      method: 'POST'
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
  }
};
