<template>
    <section class="moderation-page">
        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="success" class="alert success">{{ success }}</div>



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
                    <span class="status-badge" :class="'status-' + (complaint.status === 'resolved' ? 'success' : (complaint.status === 'processing' ? 'info' : 'warning'))" style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;" :style="complaint.status === 'resolved' ? 'background: #dcfce7; color: #166534;' : (complaint.status === 'processing' ? 'background: #dbeafe; color: #1e40af;' : 'background: #fef3c7; color: #92400e;')">
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
                <h1 class="page-title" style="margin: 0; font-size: 20px; font-weight: 700;">Chi tiết khiếu nại</h1>
                <p class="subtitle" style="margin: 0; color: #64748b; font-size: 14px;">
                  Mã khiếu nại: <strong>{{ shortId(selected.id) }}</strong> ·
                  Tạo lúc: {{ formatDateTime(selected.created_at) }}
                </p>

                <div
                    v-if="autoResolveLoading"
                    class="state"
                    style="padding: 20px; text-align: center; color: #64748b"
                >
                    Đang tải cấu hình...
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
                  <span class="value" style="font-weight: 600;">{{ selected.booking_detail.booking_code }}</span>
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
                  <div class="avatar" style="width: 40px; height: 40px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                    {{ selected.customer?.full_name?.charAt(0) || 'U' }}
                  </div>
                  <div>
                    <div style="font-weight: 600; font-size: 15px;">{{ selected.customer?.full_name || 'Khách hàng' }} <span style="color: #64748b; font-weight: normal;">đã gửi khiếu nại ({{ typeLabel(selected.complaint_type) }})</span></div>
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
                    <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px;">Ghi chú giải quyết (gửi cho khách / chủ sân)</label>
                    <textarea v-model="form.resolve_note" placeholder="Nhập ghi chú giải quyết..." style="width: 100%; min-height: 100px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box;"></textarea>
                  </div>
                  <div style="display: flex; gap: 12px;">
                    <button @click="resolveComplaintWithStatus('resolved')" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;" :disabled="saving">Giải quyết (Đồng ý)</button>
                    <button @click="resolveComplaintWithStatus('rejected')" class="btn" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;" :disabled="saving">Từ chối khiếu nại</button>
                    <button @click="resolveComplaintWithStatus('closed')" class="btn ghost" style="padding: 10px 20px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;" :disabled="saving">Đóng khiếu nại</button>
                  </div>
                </div>
              </div>

              <!-- Additional notification form -->
              <div v-if="['resolved', 'rejected', 'closed'].includes(selected.status)" class="card reply-form" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 16px;">
                <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 16px;">Gửi thông báo bổ sung</h3>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                  <div style="display: flex; gap: 24px; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <label style="font-size: 14px; font-weight: 600; color: #334155; margin: 0;">Gửi cho:</label>
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
                    <button @click="sendAdditionalNotification" class="btn primary" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.15s; white-space: nowrap;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'" :disabled="saving || !form.notify_message">
                      <AppIcon name="send" size="16" style="margin-right: 6px; display: inline-block; vertical-align: middle;" /> Gửi thông báo
                    </button>
                    <button @click="fillTemplateNotifyMessage" class="btn ghost" style="padding: 10px 20px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.15s; white-space: nowrap;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'" :disabled="saving">
                      Sử dụng văn mẫu
                    </button>
                    <button v-if="!hasSentNotification" @click="sendToBothAuto" class="btn warning" style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.15s; white-space: nowrap; margin-left: auto;" onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'" :disabled="saving">
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

                    <div
                        v-if="currentAutoConfig"
                        class="auto-config-body"
                        style="display: grid; gap: 14px"
                    >
                        <!-- Cấu hình chỉnh sửa -->
                        <div
                            style="
                                background: #f8fafc;
                                border: 1px solid #e2e8f0;
                                border-radius: 8px;
                                padding: 16px;
                            "
                        >
                            <div
                                style="
                                    display: flex;
                                    justify-content: space-between;
                                    margin-bottom: 12px;
                                    align-items: center;
                                "
                            >
                                <span
                                    style="
                                        color: #334155;
                                        font-size: 0.9rem;
                                        font-weight: 600;
                                    "
                                    >Tự động xử lý khiếu nại:</span
                                >
                                <!-- Switch toggle -->
                                <div
                                    class="toggle-slider"
                                    :class="{
                                        on: currentAutoConfig.is_auto_resolve_enabled,
                                    }"
                                    @click="
                                        currentAutoConfig.is_auto_resolve_enabled =
                                            !currentAutoConfig.is_auto_resolve_enabled
                                    "
                                    style="
                                        width: 48px;
                                        height: 26px;
                                        border-radius: 13px;
                                        background: #e2e8f0;
                                        cursor: pointer;
                                        transition: background 0.2s;
                                        position: relative;
                                    "
                                    :style="
                                        currentAutoConfig.is_auto_resolve_enabled
                                            ? 'background: #16a34a;'
                                            : ''
                                    "
                                >
                                    <div
                                        style="
                                            position: absolute;
                                            top: 3px;
                                            left: 3px;
                                            width: 20px;
                                            height: 20px;
                                            border-radius: 50%;
                                            background: #fff;
                                            transition: transform 0.2s;
                                            box-shadow: 0 1px 3px
                                                rgba(0, 0, 0, 0.2);
                                        "
                                        :style="
                                            currentAutoConfig.is_auto_resolve_enabled
                                                ? 'transform: translateX(22px);'
                                                : ''
                                        "
                                    ></div>
                                </div>
                            </div>
                            <div
                                v-if="currentAutoConfig.is_auto_resolve_enabled"
                                style="
                                    display: flex;
                                    flex-direction: column;
                                    gap: 12px;
                                    margin-top: 12px;
                                    border-top: 1px solid #e2e8f0;
                                    padding-top: 12px;
                                "
                            >
                                <label
                                    style="
                                        display: flex;
                                        flex-direction: column;
                                        gap: 6px;
                                        font-weight: 800;
                                        font-size: 13px;
                                        color: #334155;
                                    "
                                >
                                    <span style="color: #64748b"
                                        >Phản hồi xử lý tự động:</span
                                    >
                                    <input
                                        type="text"
                                        v-model="currentAutoConfig.reason"
                                        style="
                                            padding: 8px;
                                            border: 1px solid #cbd5e1;
                                            border-radius: 6px;
                                            font-weight: 500;
                                        "
                                        placeholder="Ví dụ: Hệ thống tự động giải quyết khiếu nại."
                                    />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div
                        style="
                            margin-top: 4px;
                            padding: 10px 12px;
                            background: #eff6ff;
                            border-radius: 8px;
                            font-size: 0.85rem;
                            color: #1e40af;
                            display: flex;
                            align-items: flex-start;
                            gap: 8px;
                        "
                    >
                        <AppIcon
                            name="info"
                            size="16"
                            style="flex-shrink: 0; margin-top: 2px"
                        />
                        <div>
                            Khi tính năng
                            <strong>Tự động xử lý khiếu nại</strong> được bật,
                            các khiếu nại mới của đối tượng này khi được gửi lên
                            sẽ được hệ thống tự động giải quyết và gửi phản hồi
                            ngay lập tức.
                        </div>
                    </div>
                </template>

                <footer
                    style="
                        margin-top: 16px;
                        display: flex;
                        justify-content: flex-end;
                        gap: 8px;
                    "
                >
                    <button
                        type="button"
                        class="btn secondary"
                        @click="closeAutoResolveModal"
                        style="
                            border: 0;
                            background: #f1f5f9;
                            color: #334155;
                            padding: 10px 14px;
                            font-weight: 800;
                            border-radius: 8px;
                            cursor: pointer;
                        "
                    >
                        Hủy
                    </button>
                    <button
                        type="button"
                        class="btn primary"
                        style="
                            background: #10b981;
                            color: white;
                            border: 0;
                            padding: 10px 14px;
                            font-weight: 800;
                            border-radius: 8px;
                            cursor: pointer;
                        "
                        @click="saveAutoResolveConfig"
                        :disabled="autoResolveSaving"
                    >
                        Lưu cấu hình
                    </button>
                </footer>
            </div>
        </div>

        <!-- Nút cấu hình nổi (Floating Action Button) -->
        <div
            class="floating-config-container"
            :class="{ 'has-scroll': showScrollTop }"
        >
            <button
                class="floating-config-btn"
                @click="openAutoResolveModal"
                title="Cấu hình tự động xử lý khiếu nại"
            >
                <AppIcon name="settings" size="20" />
                <span class="floating-config-text">Cấu hình tự động xử lý</span>
            </button>
        </div>

        <!-- Image Preview Modal -->
        <div
            v-if="previewImage"
            style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.85); z-index: 9999; display: flex; align-items: center; justify-content: center; cursor: default; overflow: hidden;"
            @click="closeImagePreview"
        >
            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden;" @click.self="closeImagePreview">
                <img 
                    :src="previewImage" 
                    draggable="false"
                    :style="{
                        maxWidth: '90%', 
                        maxHeight: '90%', 
                        objectFit: 'contain', 
                        transformOrigin: '0 0',
                        cursor: zoomState.scale > 1 ? (zoomState.isDragging ? 'grabbing' : 'grab') : 'zoom-in',
                        transform: `translate(${zoomState.x}px, ${zoomState.y}px) scale(${zoomState.scale})`,
                        transition: zoomState.isDragging ? 'none' : 'transform 0.1s ease-out'
                    }" 
                    @wheel.stop="handleWheelZoom"
                    @mousedown.stop.prevent="startPan"
                    @mousemove.stop.prevent="doPan"
                    @mouseup.stop.prevent="endPan"
                    @mouseleave.stop.prevent="endPan"
                    @click.stop="zoomState.scale === 1 ? handleWheelZoom({ clientX: $event.clientX, clientY: $event.clientY, deltaY: -1, target: $event.target, preventDefault: () => {} }) : null"
                />
            </div>
            <button
                @click="closeImagePreview"
                style="position: absolute; top: 24px; right: 24px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer;"
            >
                <AppIcon name="x" size="24" />
            </button>
        </div>
    </section>
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
                complaint_type: "",
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
            notificationMessage: "",
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
                this.notificationMessage = "";
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
        async sendAdditionalNotification() {
            if (!this.selected?.id || !this.notificationMessage) return;
            this.saving = true;
            try {
                const response = await adminComplaintService.notify(
                    this.selected.id,
                    { message: this.notificationMessage },
                );
                this.success = response.message || "Đã gửi thông báo bổ sung.";
                this.notificationMessage = "";
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
            return path?.startsWith("http") ? path : `/storage/${path}`;
        },
    },
};
</script>

<style scoped>
.side-panel .modal-actions {
    flex-wrap: wrap;
}

.side-panel .modal-actions .btn {
    flex: 1 1 120px;
}

.floating-config-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9998;
    transition: right 0.25s ease;
}

.floating-config-container.has-scroll {
    right: 86px;
}

.floating-config-btn {
    width: 44px;
    height: 44px;
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #fff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    white-space: nowrap;
    padding: 0 11px;
}

.floating-config-btn .floating-config-text {
    max-width: 0;
    opacity: 0;
    margin-left: 0;
    font-weight: 700;
    font-size: 13px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
}

.floating-config-btn.never-hover-class-placeholder {
    width: 215px;
    justify-content: flex-start;
    padding-left: 14px;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    background-color: #f8fafc;
}

.floating-config-btn.never-hover-class-placeholder .floating-config-text {
    max-width: 170px;
    opacity: 1;
    margin-left: 6px;
}
</style>
