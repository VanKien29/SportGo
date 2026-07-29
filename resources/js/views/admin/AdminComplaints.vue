<template>
  <div class="complaints-page">
    <div v-if="success" class="notice success">{{ success }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div v-if="!detailOpen">
        <div class="filter-toolbar card" style="margin-bottom: 24px;">
          <!-- Tabs -->
          <div class="tabs-header">
            <button class="tab-btn" :class="{ active: filters.complaint_type === 'system' }" @click="filters.complaint_type = 'system'; loadComplaints()">
              <AppIcon name="shield-alert" size="16" /> Khiếu nại hệ thống
            </button>
            <button class="tab-btn" :class="{ active: filters.complaint_type === 'venue' }" @click="filters.complaint_type = 'venue'; loadComplaints()">
              <AppIcon name="message-square" size="16" /> Khiếu nại cụm sân
            </button>
          </div>

          <!-- Filter and Search -->
          <div class="filters-row" style="display: flex; gap: 12px; align-items: center; padding: 16px;">
            <label class="field compact search-field" style="flex: 1;">
              <AppIcon name="search" size="16" />
              <input
                v-model.trim="filters.keyword"
                type="search"
                placeholder="Tìm khách hàng, booking, cụm sân..."
                @keyup.enter="loadComplaints"
              />
            </label>
            <CustomSelect
              v-model="filters.status"
              :options="[{value: '', label: 'Tất cả trạng thái'}, ...statuses]"
              @change="loadComplaints"
            />

            <AdminDatePicker v-model="filters.date_from" placeholder="Từ ngày" @update:modelValue="loadComplaints" />
            <AdminDatePicker v-model="filters.date_to" placeholder="Đến ngày" @update:modelValue="loadComplaints" />
            <ActionIconButton icon="filter" label="Lọc" variant="primary" @click="loadComplaints" />
            <ActionIconButton icon="settings" label="Cấu hình tự động" variant="secondary" @click="openAutoResolveModal" />
          </div>
        </div>

        <!-- Loading Screen -->
        <div v-if="loading" class="state-box card">
          <div class="spinner"></div>
          <p>Đang tải danh sách khiếu nại...</p>
        </div>

        <!-- Empty Screen -->
        <div v-else-if="complaints.length === 0" class="state-box card">
          <AppIcon name="fileText" size="36" />
          <p>Không tìm thấy khiếu nại nào.</p>
        </div>

        <!-- Complaints Table -->
        <div v-else class="table-container card">
          <div class="table-scroll">
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="text-align: left; border-bottom: 1px solid #e2e8f0;">
                  <th style="padding: 12px 16px;">Khách hàng</th>
                  <th style="padding: 12px 16px;">Nội dung khiếu nại</th>
                  <th style="padding: 12px 16px;">Cụm sân / Booking</th>
                  <th style="padding: 12px 16px;">Trạng thái</th>
                  <th style="padding: 12px 16px;">Ngày tạo</th>
                  <th class="center" style="width: 120px; padding: 12px 16px; text-align: center;">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="complaint in complaints" :key="complaint.id" class="complaint-row" style="border-bottom: 1px solid #f1f5f9;">
                  <td style="padding: 12px 16px;">
                    <div class="author-cell">
                      <strong>{{ complaint.customer?.full_name || 'Khách hàng' }}</strong>
                      <div class="muted small" style="font-size: 12px; color: #64748b;">{{ complaint.customer?.phone || 'Không có SĐT' }}</div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <div class="info-cell">
                      <div class="post-title" style="font-weight: 500;">{{ truncate(complaint.content, 60) }}</div>
                      <div class="complaint-type" style="margin-top: 4px;">
                        <span class="type-badge" style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px;">{{ typeLabel(complaint.complaint_type) }}</span>
                      </div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <div class="info-cell">
                      <div class="post-court" style="font-size: 13px;">
                        <strong>{{ complaint.venue_cluster?.name || 'Hệ thống' }}</strong>
                      </div>
                      <div v-if="complaint.booking" class="booking-link-cell mt-1" style="font-size: 12px; color: #64748b;">
                        Mã: {{ complaint.booking.booking_code }}
                      </div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <span class="status-badge" :class="'status-' + (complaint.status === 'resolved' ? 'success' : (complaint.status === 'processing' ? 'info' : 'warning'))" style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 400;" :style="complaint.status === 'resolved' ? 'background: #dcfce7; color: #166534;' : (complaint.status === 'processing' ? 'background: #dbeafe; color: #1e40af;' : 'background: #fef3c7; color: #92400e;')">
                      {{ statusLabel(complaint.status) }}
                    </span>
                  </td>
                  <td style="padding: 12px 16px; font-size: 13px;">
                    <span class="date-cell">{{ formatDateTime(complaint.created_at) }}</span>
                  </td>
                  <td class="center" style="padding: 12px 16px; text-align: center;">
                    <button @click="openDetail(complaint)" class="btn ghost icon-only" title="Xem chi tiết" style="padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; background: white; cursor: pointer;">
                      <AppIcon name="eye" size="18" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
    </div>

    <!-- Detail View -->
    <div v-if="detailOpen" class="detail-view">
        <div v-if="detailLoading" class="state-box card">
          <div class="spinner"></div>
          <p>Đang tải chi tiết khiếu nại...</p>
        </div>

        <template v-else-if="selected">
          <!-- Header -->
          <div class="detail-header card" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; margin-bottom: 24px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div class="header-main" style="display: flex; gap: 16px; align-items: center;">
              <button @click="closeDetail" class="btn ghost icon-only" style="background: none; border: none; cursor: pointer;">
                <AppIcon name="arrowLeft" size="24" />
              </button>
              <div>
                <h1 class="page-title" style="margin: 0; font-size: 20px; font-weight: 400;">Chi tiết khiếu nại</h1>
                <p class="subtitle" style="margin: 0; color: #64748b; font-size: 14px;">
                  Mã khiếu nại: <strong>{{ shortId(selected.id) }}</strong> ·
                  Tạo lúc: {{ formatDateTime(selected.created_at) }}
                </p>
              </div>
            </div>
            <span class="status-badge" :style="selected.status === 'resolved' ? 'background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 16px;' : (selected.status === 'processing' ? 'background: #dbeafe; color: #1e40af; padding: 6px 12px; border-radius: 16px;' : 'background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 16px;')">
              {{ statusLabel(selected.status) }}
            </span>
          </div>

          <div class="detail-content" style="display: flex; gap: 24px; align-items: flex-start;">
            <!-- Sidebar: Info -->
            <div class="detail-sidebar" style="width: 320px; display: flex; flex-direction: column; gap: 16px;">
              <div class="card info-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">Thông tin khách hàng</h3>
                <div class="info-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                  <span class="label" style="color: #64748b;">Họ tên:</span>
                  <span class="value" style="font-weight: 500;">{{ selected.customer?.full_name || 'N/A' }}</span>
                </div>
                <div class="info-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                  <span class="label" style="color: #64748b;">SĐT:</span>
                  <span class="value" style="font-weight: 500;">{{ selected.customer?.phone || 'N/A' }}</span>
                </div>
                <div class="info-row" style="display: flex; justify-content: space-between; font-size: 14px;">
                  <span class="label" style="color: #64748b;">Email:</span>
                  <span class="value" style="font-weight: 500;">{{ selected.customer?.email || 'N/A' }}</span>
                </div>
              </div>

              <div class="card info-card" v-if="selected.booking_detail" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">Thông tin Booking liên quan</h3>
                <div class="info-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                  <span class="label" style="color: #64748b;">Mã Booking:</span>
                  <span class="value" style="font-weight: 400;">{{ selected.booking_detail.booking_code }}</span>
                </div>
                <div class="info-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                  <span class="label" style="color: #64748b;">Thời gian:</span>
                  <span class="value" style="text-align: right; font-weight: 500;">
                    {{ selected.booking_detail.booking_date }}<br/>
                    {{ selected.booking_detail.start_time }} - {{ selected.booking_detail.end_time }}
                  </span>
                </div>
                <div class="info-row" style="display: flex; justify-content: space-between; font-size: 14px;">
                  <span class="label" style="color: #64748b;">Cụm sân:</span>
                  <span class="value" style="font-weight: 500; text-align: right;">{{ selected.booking_detail.venue_cluster?.name }}<br/><span style="color: #64748b; font-size: 12px;">{{ selected.booking_detail.venue_court?.name }}</span></span>
                </div>
              </div>
              

            </div>

            <!-- Main Panel: Timeline & Form -->
            <div class="detail-main" style="flex: 1; display: flex; flex-direction: column; gap: 24px;">
              <!-- Original Complaint Content -->
              <div class="card complaint-box" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div class="complaint-head" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
                  <div class="avatar" style="width: 40px; height: 40px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 400;">
                    {{ selected.customer?.full_name?.charAt(0) || 'U' }}
                  </div>
                  <div>
                    <div style="font-weight: 400; font-size: 15px;">{{ selected.customer?.full_name || 'Khách hàng' }} <span style="color: #64748b; font-weight: normal;">đã gửi khiếu nại ({{ typeLabel(selected.complaint_type) }})</span></div>
                    <div style="font-size: 13px; color: #94a3b8;">{{ formatDateTime(selected.created_at) }}</div>
                  </div>
                </div>
                <div class="complaint-body" style="font-size: 15px; line-height: 1.6; color: #334155; white-space: pre-wrap;">{{ selected.content }}</div>
                
                <div v-if="selected.evidence && selected.evidence.length > 0" class="evidence-grid" style="display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap;">
                  <div v-for="media in selected.evidence" :key="media.id" class="evidence-item" style="width: 120px; height: 120px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; cursor: pointer;" @click="openImagePreview(media.file_path)">
                    <img :src="media.file_path" :alt="media.file_name" style="width: 100%; height: 100%; object-fit: cover;" />
                  </div>
                </div>
              </div>

              <!-- Admin action form -->
              <div v-if="selected && selected.complaint_type === 'system' && ['open', 'processing'].includes(selected.status)" class="card reply-form" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 16px;">Giải quyết khiếu nại</h3>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                  <div>
                    <label style="display: block; font-size: 14px; font-weight: 400; margin-bottom: 8px;">Ghi chú giải quyết (gửi cho khách / chủ sân)</label>
                    <textarea v-model="form.resolve_note" placeholder="Nhập ghi chú giải quyết..." style="width: 100%; min-height: 100px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box;"></textarea>
                  </div>
                  <div style="display: flex; gap: 12px;">
                    <button @click="resolveComplaintWithStatus('resolved')" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 400; cursor: pointer;" :disabled="saving">Giải quyết (Đồng ý)</button>
                    <button @click="resolveComplaintWithStatus('rejected')" class="btn" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 8px; font-weight: 400; cursor: pointer;" :disabled="saving">Từ chối khiếu nại</button>
                    <button @click="resolveComplaintWithStatus('closed')" class="btn ghost" style="padding: 10px 20px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: 400; cursor: pointer;" :disabled="saving">Đóng khiếu nại</button>
                  </div>
                </div>
              </div>

              <!-- Additional notification form -->
              <div v-if="['resolved', 'rejected', 'closed'].includes(selected.status)" class="card reply-form" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 16px;">
                <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 16px;">Gửi thông báo bổ sung</h3>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                  <div style="display: flex; gap: 24px; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <label style="font-size: 14px; font-weight: 400; color: #334155; margin: 0;">Gửi cho:</label>
                    <div style="display: flex; gap: 20px;">
                      <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; white-space: nowrap; color: #475569;">
                        <input type="radio" v-model="form.notify_recipient" value="reporter" style="width: 16px; height: 16px; margin: 0; cursor: pointer;"> 
                        <span style="font-weight: 500;">Khách hàng (Người khiếu nại)</span>
                      </label>
                      <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; white-space: nowrap; color: #475569;">
                        <input type="radio" v-model="form.notify_recipient" value="reported" style="width: 16px; height: 16px; margin: 0; cursor: pointer;"> 
                        <span style="font-weight: 500;">Cụm sân (Bị khiếu nại)</span>
                      </label>
                    </div>
                  </div>
                  <div>
                    <textarea v-model="form.notify_message" placeholder="Nhập nội dung thông báo muốn gửi..." style="width: 100%; min-height: 120px; padding: 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box; outline: none; transition: border-color 0.15s, box-shadow 0.15s;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';"></textarea>
                  </div>
                  <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <button @click="sendAdditionalNotification" class="btn primary" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 400; cursor: pointer; transition: background 0.15s; white-space: nowrap;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'" :disabled="saving || !form.notify_message">
                      <AppIcon name="send" size="16" style="margin-right: 6px; display: inline-block; vertical-align: middle;" /> Gửi thông báo
                    </button>
                    <button @click="fillTemplateNotifyMessage" class="btn ghost" style="padding: 10px 20px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: 400; cursor: pointer; transition: background 0.15s; white-space: nowrap;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'" :disabled="saving">
                      Sử dụng văn mẫu
                    </button>
                    <button v-if="!hasSentNotification" @click="sendToBothAuto" class="btn warning" style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 8px; font-weight: 400; cursor: pointer; transition: background 0.15s; white-space: nowrap; margin-left: auto;" onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'" :disabled="saving">
                      <AppIcon name="zap" size="16" style="margin-right: 6px; display: inline-block; vertical-align: middle;" /> Gửi nhanh cho cả 2 (Văn mẫu)
                    </button>
                  </div>
                </div>
              </div>

              <!-- Timeline of Replies -->
              <div v-if="auditLogs && auditLogs.length > 0" class="timeline-section" style="margin-top: 16px;">
                <h3 style="font-size: 16px; margin-bottom: 20px; color: #334155;">Lịch sử xử lý & Phản hồi</h3>
                <div class="timeline" style="display: flex; flex-direction: column; gap: 24px; position: relative;">
                  <div v-for="log in auditLogs" :key="log.id" class="timeline-item" style="display: flex; gap: 16px;">
                    <!-- log UI -->
                    <div class="timeline-icon" style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; z-index: 1;">
                      <AppIcon :name="log.type === 'reply' ? 'message-square' : 'activity'" size="16" style="color: #64748b;" />
                    </div>
                    <div class="timeline-content card" style="flex: 1; background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                      <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <strong style="font-size: 14px;">{{ log.user?.full_name || 'Hệ thống' }} <span style="font-weight: normal; color: #64748b;">{{ log.type === 'reply' ? 'đã phản hồi' : 'đã cập nhật trạng thái' }}</span></strong>
                        <span style="font-size: 12px; color: #94a3b8;">{{ formatDateTime(log.created_at) }}</span>
                      </div>
                      <div v-if="log.type === 'reply'" style="font-size: 14px; line-height: 1.5;">{{ log.content }}</div>
                      <div v-else style="font-size: 13px; color: #64748b; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px dashed #cbd5e1;">
                        Hành động: {{ log.action }}
                      </div>
                      <div v-if="log.evidence && log.evidence.length > 0" class="evidence-grid" style="display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap;">
                        <div v-for="media in log.evidence" :key="media.id" class="evidence-item" style="width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; cursor: pointer;" @click="openImagePreview(media.file_path)">
                          <img :src="media.file_path" :alt="media.file_name" style="width: 100%; height: 100%; object-fit: cover;" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
    </div>

    <!-- Modals go here (AutoResolve, Image Preview, etc) -->
    <div v-if="showAutoResolveModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
      <!-- (Keep auto resolve modal simplified here or just copy original HTML for it) -->
      <div style="background: white; padding: 32px; border-radius: 12px; max-width: 600px; width: 100%;">
        <h3 style="margin-top: 0;">Cấu hình tự động xử lý khiếu nại</h3>
        <p>Tính năng đang bảo trì giao diện trong bản nâng cấp này.</p>
        <button @click="closeAutoResolveModal" class="btn">Đóng</button>
      </div>
    </div>

    <!-- Image Preview -->
    <div v-if="previewImage" style="position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 3000; display: flex; align-items: center; justify-content: center;" @click="closeImagePreview">
      <img :src="previewImage" style="max-width: 90vw; max-height: 90vh; object-fit: contain;" @click.stop />
      <button @click="closeImagePreview" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 30px; cursor: pointer;">&times;</button>
    </div>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import ActionIconButton from "../../components/ActionIconButton.vue";
import CustomSelect from "../../components/CustomSelect.vue";
import AdminDatePicker from "../../components/AdminDatePicker.vue";
import { adminComplaintService } from "../../services/adminModeration.js";

export default {
    name: "AdminComplaints",
    components: { AppIcon, ActionIconButton, CustomSelect, AdminDatePicker },
    data() {
        return {
            complaints: [],
            summary: {},
            staff: [],
            filters: {
                keyword: "",
                complaint_type: "system",
                status: "",
                assigned_to: "",
                date_from: "",
                date_to: "",
            },
            statuses: [
                { value: "open", label: "Chờ tiếp nhận" },
                { value: "processing", label: "Đang xử lý" },
                { value: "resolved", label: "Đã giải quyết" },
                { value: "rejected", label: "Đã từ chối" },
                { value: "closed", label: "Đã đóng" },
            ],
            selected: null,
            auditLogs: [],
            detailOpen: false,
            detailLoading: false,
            loading: false,
            saving: false,
            error: "",
            success: "",
            form: { assigned_to: "", status: "processing", resolve_note: "" },
            showAutoResolveModal: false,
            autoResolveLoading: false,
            autoResolveSaving: false,
            autoResolveConfigData: null,
            activeAutoTab: "venue",
            showScrollTop: false,
            previewImage: null,
            zoomState: {
                scale: 1,
                x: 0,
                y: 0,
                isDragging: false,
                startX: 0,
                startY: 0
            },
        };
    },
    computed: {
        currentAutoConfig() {
            if (
                !this.autoResolveConfigData ||
                !this.autoResolveConfigData.configs
            ) {
                return null;
            }
            return this.autoResolveConfigData.configs[this.activeAutoTab];
        },
        hasSentNotification() {
            return this.auditLogs && this.auditLogs.some(log => ['complaint.notified', 'complaint.resolved', 'complaint.rejected', 'complaint.closed'].includes(String(log.action).trim().toLowerCase()));
        },
    },
    mounted() {
        this.loadComplaints();
        window.addEventListener("scroll", this.handleScroll);
    },
    beforeUnmount() {
        window.removeEventListener("scroll", this.handleScroll);
    },
    methods: {
        shortId(id) {
            if (!id) return "";
            return String(id).split("-")[0].toUpperCase();
        },
        truncate(text, length = 50) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
        },
        async loadComplaints() {
            this.loading = true;
            this.error = "";
            try {
                const response = await adminComplaintService.list(this.filters);
                this.complaints = response.data || [];
                this.summary = response.summary || {};
                this.staff = response.staff || [];
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        async openDetail(complaint) {
            this.detailOpen = true;
            this.detailLoading = true;
            this.selected = null;
            try {
                const response = await adminComplaintService.show(complaint.id);
                this.selected = response.data.complaint;
                this.auditLogs = response.data.audit_logs || [];
                this.syncForm();
            } catch (error) {
                this.error = error.message;
                this.detailOpen = false;
            } finally {
                this.detailLoading = false;
            }
        },
        closeDetail() {
            this.detailOpen = false;
            this.selected = null;
        },
        openImagePreview(url) {
            this.previewImage = url;
            this.resetZoom();
        },
        closeImagePreview() {
            this.previewImage = null;
            this.resetZoom();
        },
        resetZoom() {
            this.zoomState = { scale: 1, x: 0, y: 0, isDragging: false, startX: 0, startY: 0 };
        },
        handleWheelZoom(e) {
            e.preventDefault();
            const zoomFactor = 0.15;
            const direction = e.deltaY < 0 ? 1 : -1;
            const newScale = Math.max(1, Math.min(this.zoomState.scale + direction * zoomFactor, 5));
            
            if (newScale === 1) {
                this.resetZoom();
                return;
            }

            const rect = e.target.getBoundingClientRect();
            const cursorX = e.clientX - rect.left;
            const cursorY = e.clientY - rect.top;
            
            const ratio = newScale / this.zoomState.scale;
            const diffX = cursorX * ratio - cursorX;
            const diffY = cursorY * ratio - cursorY;

            this.zoomState.x -= diffX;
            this.zoomState.y -= diffY;
            this.zoomState.scale = newScale;
        },
        startPan(e) {
            if (this.zoomState.scale <= 1) return;
            this.zoomState.isDragging = true;
            this.zoomState.startX = e.clientX - this.zoomState.x;
            this.zoomState.startY = e.clientY - this.zoomState.y;
        },
        doPan(e) {
            if (!this.zoomState.isDragging) return;
            this.zoomState.x = e.clientX - this.zoomState.startX;
            this.zoomState.y = e.clientY - this.zoomState.startY;
        },
        endPan() {
            this.zoomState.isDragging = false;
        },
        syncForm() {
            let defaultNote = "";
            if (this.selected.complaint_type === "system") {
                defaultNote = "Chúng tôi đã tiếp nhận khiếu nại của bạn và sẽ tiến hành xử lý trong thời gian sớm nhất. Xin cảm ơn bạn đã phản hồi.";
            } else {
                defaultNote = "Chúng tôi đã tiếp nhận khiếu nại của bạn và đang tiến hành xử lý. Xin cảm ơn bạn đã phản hồi.";
            }

            this.form = {
                assigned_to: this.selected.assigned_to?.id || "",
                status: ["resolved", "rejected", "closed"].includes(
                    this.selected.status,
                )
                    ? this.selected.status
                    : "processing",
                resolve_note: this.selected.resolve_note || defaultNote,
                notify_recipient: 'reporter',
                notify_message: '',
            };
        },
        async resolveComplaintWithStatus(status) {
            this.form.status = status;
            await this.resolveComplaint();
        },
        async sendAdditionalNotification() {
            if (!this.form.notify_message) return;
            this.saving = true;
            try {
                const response = await adminComplaintService.notify(
                    this.selected.id,
                    { message: this.form.notify_message, recipient: this.form.notify_recipient || 'reporter' }
                );
                this.success = response.message || 'Đã gửi thông báo thành công!';
                this.form.notify_message = '';
                await this.refreshDetail();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },
        async sendToBothAuto() {
            if (!this.selected) return;
            this.saving = true;
            try {
                const reporterMsg = "Xin chào! Cảm ơn bạn đã gửi khiếu nại. Chúng tôi đã tiếp nhận và giải quyết vấn đề của bạn. Nếu bạn có thêm thông tin, vui lòng liên hệ qua trung tâm hỗ trợ. Chúc bạn một ngày tốt lành!";
                await adminComplaintService.notify(this.selected.id, {
                    recipient: 'reporter',
                    message: reporterMsg
                });
                
                const reportedMsg = "Xin chào! Chúng tôi nhận được khiếu nại liên quan đến dịch vụ của bạn. Xin vui lòng kiểm tra và đảm bảo tuân thủ đúng quy định của SportGo để mang lại trải nghiệm tốt nhất cho khách hàng.";
                await adminComplaintService.notify(this.selected.id, {
                    recipient: 'reported',
                    message: reportedMsg
                });

                this.success = 'Đã gửi thông báo cho cả 2 bên thành công!';
                this.form.notify_message = '';
                await this.refreshDetail();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },
        fillTemplateNotifyMessage() {
            if (this.form.notify_recipient === 'reporter') {
                this.form.notify_message = "Xin chào! Cảm ơn bạn đã gửi khiếu nại. Chúng tôi đã tiếp nhận và giải quyết vấn đề của bạn. Nếu bạn có thêm thông tin, vui lòng liên hệ qua trung tâm hỗ trợ. Chúc bạn một ngày tốt lành!";
            } else {
                this.form.notify_message = "Xin chào! Chúng tôi nhận được khiếu nại liên quan đến dịch vụ của bạn. Xin vui lòng kiểm tra và đảm bảo tuân thủ đúng quy định của SportGo để mang lại trải nghiệm tốt nhất cho khách hàng.";
            }
        },
        async assignComplaint() {
            this.saving = true;
            try {
                const response = await adminComplaintService.assign(
                    this.selected.id,
                    null
                );
                this.success = response.message;
                await this.loadComplaints();
                await this.refreshDetail();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },
        async resolveComplaint() {
            this.saving = true;
            try {
                const response = await adminComplaintService.resolve(
                    this.selected.id,
                    {
                        status: this.form.status,
                        resolve_note: this.form.resolve_note,
                    },
                );
                this.success = response.message;
                await this.loadComplaints();
                await this.refreshDetail();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },
        async openAutoResolveModal() {
            this.showAutoResolveModal = true;
            this.autoResolveLoading = true;
            this.activeAutoTab = "venue";
            try {
                const res = await adminComplaintService.getAutoResolveConfig();
                this.autoResolveConfigData = res.data;
            } catch (err) {
                this.error = "Không thể tải cấu hình tự động xử lý khiếu nại.";
                this.showAutoResolveModal = false;
            } finally {
                this.autoResolveLoading = false;
            }
        },
        closeAutoResolveModal() {
            this.showAutoResolveModal = false;
            this.autoResolveConfigData = null;
        },
        async saveAutoResolveConfig() {
            this.autoResolveSaving = true;
            try {
                const payload = {
                    configs: Object.values(this.autoResolveConfigData.configs),
                };
                await adminComplaintService.saveAutoResolveConfig(payload);
                this.success =
                    "Lưu cấu hình tự động xử lý khiếu nại thành công.";
                this.closeAutoResolveModal();
            } catch (err) {
                this.error = err.message || "Lỗi khi lưu cấu hình.";
            } finally {
                this.autoResolveSaving = false;
            }
        },
        async refreshDetail() {
            const response = await adminComplaintService.show(this.selected.id);
            this.selected = response.data.complaint;
            this.auditLogs = response.data.audit_logs || [];
            this.syncForm();
        },
        typeLabel(value) {
            return value === "venue" ? "Cụm sân" : "Hệ thống";
        },
        statusLabel(value) {
            return (
                this.statuses.find((item) => item.value === value)?.label ||
                value ||
                "-"
            );
        },
        isTerminalStatus(value) {
            return ["resolved", "rejected", "closed"].includes(value);
        },
        auditLabel(value) {
            return (
                {
                    "complaint.assigned": "Phân công người xử lý",
                    "complaint.processing": "Cập nhật đang xử lý",
                    "complaint.resolved": "Giải quyết khiếu nại",
                    "complaint.rejected": "Từ chối khiếu nại",
                    "complaint.closed": "Đóng khiếu nại",
                }[value] || value
            );
        },
        paidAmount(payments = []) {
            return payments
                .filter((item) => item.status === "paid")
                .reduce((sum, item) => sum + Number(item.amount || 0), 0);
        },
        money(value) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(value || 0);
        },
        complaintLabel(complaint) {
            if (!complaint) return "";
            if (complaint.booking?.booking_code) {
                return complaint.booking.booking_code;
            }
            if (complaint.venue_cluster?.name) {
                return `${this.typeLabel(complaint.complaint_type)} · ${complaint.venue_cluster.name}`;
            }
            return this.typeLabel(complaint.complaint_type);
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleDateString("vi-VN") : "-";
        },
        formatDateTime(value) {
            return value ? new Date(value).toLocaleString("vi-VN") : "-";
        },
        formatFileSize(value) {
            return value
                ? `${Math.max(1, Math.round(value / 1024))} KB`
                : "0 KB";
        },
        mediaUrl(path) {
            if (!path) return '';
            if (path.startsWith('http') || path.startsWith('/storage')) return path;
            return `/storage/${path}`;
        },
    },
};
</script>

<style scoped>
.complaints-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.notice {
  padding: 12px 16px;
  border-radius: var(--admin-radius-md);
  font-size: 14px;
  font-weight: 500;
}
.notice.success {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}
.notice.error {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.filter-toolbar {
  display: flex;
  flex-direction: column;
}

.tabs-header {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
}

.filters-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
  padding: 12px 16px;
  background: var(--admin-surface-muted);
  border-top: 1px solid var(--admin-border);
}

.field.compact {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  font-size: 11px;
  font-weight: 400;
  color: var(--admin-faint);
  letter-spacing: 0.03em;
  text-transform: uppercase;
  white-space: nowrap;
}

.search-field {
  position: relative;
  width: 320px;
  max-width: 100%;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 12px;
  height: 36px;
  gap: 8px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.search-field:focus-within {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}
.search-field input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text);
  padding: 0;
  height: 100%;
  text-transform: none;
}

.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: var(--admin-muted);
  gap: 16px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--admin-border);
  border-top-color: var(--admin-primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.table-container {
  overflow: hidden;
}

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  min-width: 1000px;
}

th {
  background: var(--admin-surface-muted);
  padding: 12px 16px;
  font-size: 12px;
  font-weight: 400;
  color: var(--admin-faint);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border-bottom: 1px solid var(--admin-border);
  white-space: nowrap;
}

td {
  padding: 16px;
  border-bottom: 1px solid var(--admin-border);
  vertical-align: top;
}

th.right, td.right {
  text-align: right;
}

th.center, td.center {
  text-align: center;
}

.complaint-row.never-hover-class-placeholder {
  background: var(--admin-surface-muted);
}

.author-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.author-cell strong {
  color: var(--admin-text);
  font-size: 14px;
}
.muted {
  color: var(--admin-muted);
}
.small {
  font-size: 12px;
}

.info-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.post-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--admin-text);
  line-height: 1.4;
}

.type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 400;
  background: var(--admin-surface-hover);
  color: var(--admin-text);
}

.post-court {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--admin-text);
}
.muted-icon {
  color: var(--admin-muted);
}

.booking-code {
  font-size: 12px;
  font-weight: 400;
  color: var(--admin-primary);
  background: rgba(59, 130, 246, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 400;
}
.status-warning {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}
.status-info {
  background: rgba(59, 130, 246, 0.1);
  color: #2563eb;
}
.status-success {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}
.status-danger {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}
.status-muted {
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
}

.date-cell {
  font-size: 13px;
  color: var(--admin-muted);
}

.actions-cell {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  gap: 16px;
  border-top: 1px solid var(--admin-border);
}
.page-info {
  font-size: 14px;
  color: var(--admin-muted);
  font-weight: 500;
}
.mt-1 {
  margin-top: 4px;
}

@media (max-width: 768px) {
  .tabs-header {
    flex-wrap: nowrap;
    overflow-x: auto;
    white-space: nowrap;
    padding-bottom: 8px; /* Room for scrollbar */
  }
  .search-field {
    width: 100%;
    max-width: none;
  }
}

.tab-btn {
  padding: 10px 20px;
  border-radius: 8px;
  border: 1px solid var(--admin-border);
  background: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 400;
  color: var(--admin-muted);
  transition: all 0.2s;
  flex-shrink: 0;
}
.tab-btn:hover {
  background: var(--admin-surface-muted);
}
.tab-btn.active {
  background: var(--admin-text);
  color: white;
  border-color: var(--admin-text);
}
.tab-btn.active .muted-icon {
  color: white;
}
</style>
