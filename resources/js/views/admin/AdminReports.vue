<template>
  <section class="moderation-page">

    <div v-if="!detailOpen">
        <div class="filter-toolbar card" style="margin-bottom: 24px;">
          <!-- Tabs -->
          <div class="tabs-header">
            <button class="tab-btn" :class="{ active: filters.target_group === 'content' }" @click="setTargetGroup('content')">
              <AppIcon name="file-text" size="16" /> Báo cáo nội dung
            </button>
            <button class="tab-btn" :class="{ active: filters.target_group === 'user' }" @click="setTargetGroup('user')">
              <AppIcon name="user" size="16" /> Báo cáo người dùng
            </button>
            <button class="tab-btn" :class="{ active: filters.target_group === 'venue' }" @click="setTargetGroup('venue')">
              <AppIcon name="map-pin" size="16" /> Báo cáo cụm sân
            </button>
          </div>

          <!-- Filter and Search -->
          <div class="filters-row" style="display: flex; gap: 12px; align-items: center; padding: 16px;">
            <label class="field compact search-field" style="flex: 1;">
              <AppIcon name="search" size="16" />
              <input
                v-model.trim="filters.keyword"
                type="search"
                placeholder="Tìm người gửi, nội dung hoặc mã..."
                @keyup.enter="loadReports"
              />
            </label>
            <CustomSelect 
              v-if="filters.target_group === 'content'"
              v-model="filters.target_type" 
              :options="[{value: '', label: 'Tất cả đối tượng'}, ...filteredTargetTypes]" 
              @change="loadReports" 
            />
            <CustomSelect 
              v-model="filters.reason" 
              :options="[{value: '', label: 'Tất cả lý do'}, ...reasons]" 
              @change="loadReports" 
            />
            <CustomSelect 
              v-model="filters.status" 
              :options="[{value: '', label: 'Tất cả trạng thái'}, ...statuses]" 
              @change="loadReports" 
            />
            <AdminDatePicker v-model="filters.date_from" placeholder="Từ ngày" @update:modelValue="loadReports" />
            <AdminDatePicker v-model="filters.date_to" placeholder="Đến ngày" @update:modelValue="loadReports" />
            <ActionIconButton icon="filter" label="Lọc" variant="primary" @click="loadReports" />
          </div>
        </div>

        <!-- Loading Screen -->
        <div v-if="loading" class="state-box card">
          <div class="spinner"></div>
          <p>Đang tải danh sách báo cáo...</p>
        </div>

        <!-- Empty Screen -->
        <div v-else-if="reports.length === 0" class="state-box card">
          <AppIcon name="fileText" size="36" />
          <p>Không tìm thấy báo cáo nào.</p>
        </div>

        <!-- Reports Table -->
        <div v-else class="table-container card">
          <div class="table-scroll">
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="text-align: left; border-bottom: 1px solid #e2e8f0;">
                  <th style="padding: 12px 16px;">Người báo cáo</th>
                  <th style="padding: 12px 16px;">Đối tượng bị báo cáo</th>
                  <th style="padding: 12px 16px;">Lý do / Nội dung</th>
                  <th style="padding: 12px 16px;">Trạng thái</th>
                  <th style="padding: 12px 16px;">Ngày tạo</th>
                  <th class="center" style="width: 120px; padding: 12px 16px; text-align: center;">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="report in reports" :key="report.id" class="complaint-row" style="border-bottom: 1px solid #f1f5f9;">
                  <td style="padding: 12px 16px;">
                    <div class="author-cell">
                      <strong>{{ report.reporter?.full_name || 'Khách hàng' }}</strong>
                      <div class="muted small" style="font-size: 12px; color: #64748b;">{{ report.reporter?.email || '' }}</div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <div class="info-cell">
                      <div class="post-title" style="font-weight: 500;">
                        {{ report.target_label }}
                        <a v-if="getTargetUrl(report)" :href="getTargetUrl(report)" target="_blank" style="color: #2563eb; text-decoration: none; margin-left: 8px;">
                          <AppIcon name="external-link" size="12" />
                        </a>
                      </div>
                      <div class="complaint-type" style="margin-top: 4px;">
                        <span class="type-badge" style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px;">{{ targetLabel(report.target_type) }}</span>
                      </div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <div class="info-cell">
                      <div class="post-court" style="font-size: 13px; font-weight: 500; color: #dc2626;">
                        {{ reasonLabel(report.reason) }}
                      </div>
                      <div class="mt-1" style="font-size: 13px; color: #334155;">
                        {{ truncate(report.description, 50) }}
                      </div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <span class="status-badge" :class="report.status" style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;" :style="report.status === 'resolved' ? 'background: #dcfce7; color: #166534;' : (report.status === 'processing' ? 'background: #dbeafe; color: #1e40af;' : 'background: #fef3c7; color: #92400e;')">
                      {{ statusLabel(report.status) }}
                    </span>
                  </td>
                  <td style="padding: 12px 16px; font-size: 13px;">
                    <span class="date-cell">{{ formatDateTime(report.created_at) }}</span>
                  </td>
                  <td class="center" style="padding: 12px 16px; text-align: center;">
                    <button @click="openDetail(report)" class="btn ghost icon-only" title="Xem chi tiết" style="padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; background: white; cursor: pointer;">
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
          <p>Đang tải chi tiết báo cáo...</p>
        </div>

        <template v-else-if="selected">
          <!-- Header -->
          <div class="detail-header card" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; margin-bottom: 24px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div class="header-main" style="display: flex; gap: 16px; align-items: center;">
              <button @click="closeDetail" class="btn ghost icon-only" style="background: none; border: none; cursor: pointer;">
                <AppIcon name="arrowLeft" size="24" />
              </button>
              <div>
                <h1 class="page-title" style="margin: 0; font-size: 20px; font-weight: 700;">Chi tiết báo cáo</h1>
                <p class="subtitle" style="margin: 0; color: #64748b; font-size: 14px;">
                  Mã báo cáo: <strong>{{ shortId(selected.id) }}</strong> ·
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
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">Người gửi báo cáo</h3>
                <div class="info-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                  <span class="label" style="color: #64748b;">Họ tên:</span>
                  <span class="value" style="font-weight: 500;">{{ selected.reporter?.full_name || 'N/A' }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b; font-size: 0.9rem;">Ngưỡng thực hiện thao tác (Ẩn/Khóa):</span>
                  <strong style="color: #dc2626;">{{ currentAutoConfig.action_threshold }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b; font-size: 0.9rem;">Số người báo cáo khác nhau:</span>
                  <strong style="color: #2563eb;">{{ currentAutoConfig.unique_reporters_threshold }} người</strong>
                </div>
              </div>
              

            </div>

            <!-- Main Panel: Timeline & Form -->
            <div class="detail-main" style="flex: 1; display: flex; flex-direction: column; gap: 24px;">
              <!-- Original Report Content -->
              <div class="card complaint-box" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div class="complaint-head" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
                  <div class="avatar" style="width: 40px; height: 40px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                    <AppIcon name="flag" size="20" />
                  </div>
                  <div>
                    <div style="font-weight: 600; font-size: 15px;">{{ reasonLabel(selected.reason) }} <span style="color: #64748b; font-weight: normal;">(Báo cáo {{ targetLabel(selected.target_type) }})</span></div>
                    <div style="font-size: 13px; color: #94a3b8;">{{ formatDateTime(selected.created_at) }}</div>
                  </div>
                </div>
                <div class="complaint-body" style="font-size: 15px; line-height: 1.6; color: #334155; white-space: pre-wrap;">{{ selected.description }}</div>
                
                <div style="margin-top: 16px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 14px;">
                  <strong>Đối tượng:</strong> {{ selected.target_label }}
                  <a v-if="getTargetUrl(selected)" :href="getTargetUrl(selected)" target="_blank" style="color: #2563eb; text-decoration: none; margin-left: 8px; font-weight: 500;">
                    [Xem trực tiếp]
                  </a>
                </div>
              </div>

              <!-- Admin action form -->
              <div v-if="['pending', 'reviewing'].includes(selected.status)" class="card reply-form" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 16px;">Giải quyết báo cáo</h3>
                
                <div style="display: flex; flex-direction: column; gap: 16px;">
                  <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                      <label style="display: block; font-size: 14px; font-weight: 600;">Ghi chú xử lý</label>
                      <button @click="fillTemplateActionNote" class="btn ghost" style="padding: 4px 8px; font-size: 12px; background: #f1f5f9; color: #475569; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">Sử dụng văn mẫu</button>
                    </div>
                    <textarea v-model="form.action_note" placeholder="Nhập ghi chú giải quyết (nội dung này sẽ được gửi kèm thông báo cho người báo cáo)..." style="width: 100%; min-height: 100px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box;"></textarea>
                  </div>
                  <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <CustomSelect 
                        v-model="form.action_taken" 
                        :options="[{value: '', label: 'Chọn hình thức xử lý...'}, {value: 'warning', label: 'Cảnh cáo người dùng'}, {value: 'content_hidden', label: 'Ẩn nội dung'}, {value: 'content_deleted', label: 'Gỡ bỏ nội dung'}, {value: 'account_locked', label: 'Khóa tài khoản'}, {value: 'venue_locked', label: 'Khóa cụm sân'}]" 
                    />
                    <button @click="submitDecision('resolved')" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;" :disabled="saving || !form.action_taken">Xác nhận xử lý</button>
                    <button @click="submitDecision('dismissed')" class="btn ghost" style="padding: 10px 20px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;" :disabled="saving">Bỏ qua (Báo cáo sai)</button>
                  </div>
                </div>
              </div>

              <!-- Additional notification form -->
              <div v-if="['resolved', 'dismissed'].includes(selected.status)" class="card reply-form" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 16px;">
                <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 16px;">Gửi thông báo bổ sung</h3>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                  <div style="display: flex; gap: 24px; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <label style="font-size: 14px; font-weight: 600; color: #334155; margin: 0;">Gửi cho:</label>
                    <div style="display: flex; gap: 20px;">
                      <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; white-space: nowrap; color: #475569;">
                        <input type="radio" v-model="form.notify_recipient" value="reporter" style="width: 16px; height: 16px; margin: 0; cursor: pointer;"> 
                        <span style="font-weight: 500;">Người gửi báo cáo</span>
                      </label>
                      <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; white-space: nowrap; color: #475569;">
                        <input type="radio" v-model="form.notify_recipient" value="reported" style="width: 16px; height: 16px; margin: 0; cursor: pointer;"> 
                        <span style="font-weight: 500;">Người bị báo cáo</span>
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
                <h3 style="font-size: 16px; margin-bottom: 20px; color: #334155;">Lịch sử xử lý</h3>
                <div class="timeline" style="display: flex; flex-direction: column; gap: 24px; position: relative;">
                  <div v-for="log in auditLogs" :key="log.id" class="timeline-item" style="display: flex; gap: 16px;">
                    <!-- log UI -->
                    <div class="timeline-icon" style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; z-index: 1;">
                      <AppIcon name="activity" size="16" style="color: #64748b;" />
                    </div>
                    <div class="timeline-content card" style="flex: 1; background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                      <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <strong style="font-size: 14px;">{{ log.user?.full_name || 'Hệ thống' }} <span style="font-weight: normal; color: #64748b;">đã cập nhật trạng thái</span></strong>
                        <span style="font-size: 12px; color: #94a3b8;">{{ formatDateTime(log.created_at) }}</span>
                      </div>
                      <div style="font-size: 13px; color: #64748b; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px dashed #cbd5e1;">
                        Hành động: {{ auditLabel(log.action) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Cấu hình chỉnh sửa -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                <span style="color: #334155; font-size: 0.9rem; font-weight: 600;">Tự động xử lý vi phạm:</span>
                <!-- Switch toggle -->
                <div 
                  class="toggle-slider" 
                  :class="{ on: currentAutoConfig.is_auto_resolve_enabled }" 
                  @click="currentAutoConfig.is_auto_resolve_enabled = !currentAutoConfig.is_auto_resolve_enabled"
                  style="width: 48px; height: 26px; border-radius: 13px; background: #e2e8f0; cursor: pointer; transition: background 0.2s; position: relative;"
                  :style="currentAutoConfig.is_auto_resolve_enabled ? 'background: #16a34a;' : ''"
                >
                  <div 
                    style="position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"
                    :style="currentAutoConfig.is_auto_resolve_enabled ? 'transform: translateX(22px);' : ''"
                  ></div>
                </div>
              </div>
              <div v-if="currentAutoConfig.is_auto_resolve_enabled" style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
                <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 800; font-size: 13px; color: #334155;">
                  <span style="color: #64748b;">Lý do xử lý tự động:</span>
                  <input type="text" v-model="currentAutoConfig.reason" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 500;" placeholder="Ví dụ: Vi phạm tiêu chuẩn cộng đồng" />
                </label>
              </div>
            </div>
          </div>
          
          <div style="margin-top: 4px; padding: 10px 12px; background: #eff6ff; border-radius: 8px; font-size: 0.85rem; color: #1e40af; display: flex; align-items: flex-start; gap: 8px;">
            <AppIcon name="info" size="16" style="flex-shrink: 0; margin-top: 2px;" />
            <div>
              Khi số người báo cáo khác nhau đạt <strong>ngưỡng thực hiện thao tác</strong> và tự động xử lý đang bật, hệ thống sẽ tự động thực thi ẩn bài viết/bình luận hoặc khóa cụm sân.
            </div>
          </div>
          
          <div style="text-align: center; margin-top: 8px;">
            <router-link v-if="autoResolveConfigData.policy_id" :to="`/admin/policies/${autoResolveConfigData.policy_id}`" class="btn secondary" style="text-decoration: none; display: inline-block; font-size: 0.85rem; padding: 8px 12px; font-weight: 800; border-radius: 6px; background: #f1f5f9; color: #334155;">
              Chỉnh ngưỡng tại Chính sách hệ thống →
            </router-link>
          </div>
        </template>

        <footer style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
          <button type="button" class="btn secondary" @click="closeAutoResolveModal" style="border: 0; background: #f1f5f9; color: #334155; padding: 10px 14px; font-weight: 800; border-radius: 8px; cursor: pointer;">Hủy</button>
          <button type="button" class="btn primary" style="background: #10b981; color: white; border: 0; padding: 10px 14px; font-weight: 800; border-radius: 8px; cursor: pointer;" @click="saveAutoResolveConfig" :disabled="autoResolveSaving">Lưu cấu hình</button>
        </footer>
      </div>
    </div>

    <!-- Modal Khóa Tài Khoản -->
    <div v-if="showLockUserModal" class="detail-backdrop" @click.self="closeLockUserModal" style="z-index: 10000;">
      <div class="modal" style="max-width: 450px; background: #fff; border-radius: 12px; padding: 22px; display: grid; gap: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <h3 style="margin: 0;">Khóa tài khoản</h3>
        <p class="muted" style="margin: 0; color: #64748b; font-size: 14px;">Bạn đang khóa tài khoản <strong>{{ selected?.reported_user?.full_name }}</strong>.</p>
        
        <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; font-size: 13px; color: #334155;">
          <span>Thời hạn khóa (để trống nếu khóa vĩnh viễn):</span>
          <input type="datetime-local" v-model="lockUserForm.locked_until" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" />
        </label>
        
        <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; font-size: 13px; color: #334155;">
          <span>Lý do khóa:</span>
          <textarea v-model="lockUserForm.status_reason" rows="3" placeholder="Nhập lý do khóa..." style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;"></textarea>
        </label>

        <footer style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
          <button type="button" class="btn secondary" @click="closeLockUserModal" style="border: 0; background: #f1f5f9; color: #334155; padding: 10px 14px; font-weight: 800; border-radius: 8px; cursor: pointer;">Hủy</button>
          <button type="button" class="btn danger" style="background: #dc2626; color: white; border: 0; padding: 10px 14px; font-weight: 800; border-radius: 8px; cursor: pointer;" @click="submitLockUser" :disabled="lockUserSaving">Khóa tài khoản</button>
        </footer>
      </div>
    </div>

    <!-- Nút cấu hình nổi (Floating Action Button) -->
    <div class="floating-config-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="floating-config-btn" @click="openAutoResolveModal" title="Cấu hình tự động xử lý báo cáo">
        <AppIcon name="settings" size="20" />
        <span class="floating-config-text">Cấu hình tự động xử lý</span>
      </button>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ActionIconButton from '../../components/ActionIconButton.vue';
import CustomSelect from '../../components/CustomSelect.vue';
import AdminDatePicker from '../../components/AdminDatePicker.vue';
import { adminReportService } from '../../services/adminModeration.js';
import { adminUserService } from '../../services/adminUserService.js';

export default {
  name: 'AdminReports',
  components: { AppIcon, ActionIconButton, CustomSelect, AdminDatePicker },
  data() {
    return {
      reports: [],
      summary: {},
      filters: { keyword: '', target_type: '', reason: '', status: '', date_from: '', date_to: '', target_group: 'content' },
      showLockUserModal: false,
      lockUserForm: {
        status_reason: 'Nhận quá nhiều báo cáo vi phạm',
        locked_until: '',
      },
      lockUserSaving: false,
      reportedUserViolation: null,
      userLockThreshold: 10,
      targetTypes: [
        { value: 'post', label: 'Bài viết cộng đồng' },
        { value: 'comment', label: 'Bình luận' },
        { value: 'venue_post', label: 'Bài viết cụm sân' },
        { value: 'player_post', label: 'Bài kèo' },
        { value: 'user', label: 'Người dùng' },
        { value: 'venue', label: 'Cụm sân' },
      ],
      reasons: [
        { value: 'spam', label: 'Spam' },
        { value: 'offensive', label: 'Nội dung phản cảm' },
        { value: 'fake', label: 'Giả mạo / lừa đảo' },
        { value: 'harassment', label: 'Quấy rối' },
        { value: 'other', label: 'Khác' },
      ],
      statuses: [
        { value: 'pending', label: 'Chờ xử lý' },
        { value: 'reviewing', label: 'Đang kiểm duyệt' },
        { value: 'resolved', label: 'Đã xử lý' },
        { value: 'dismissed', label: 'Đã bỏ qua' },
      ],
      reportActionOptions: [
        { value: '', label: 'Không áp dụng thao tác bổ sung' },
        { value: 'warning', label: 'Cảnh báo người dùng' },
        { value: 'content_hidden', label: 'Ẩn nội dung' },
        { value: 'content_deleted', label: 'Gỡ nội dung' },
        { value: 'account_locked', label: 'Khóa tài khoản' },
        { value: 'venue_locked', label: 'Khóa cụm sân' },
      ],
      selected: null,
      auditLogs: [],
      detailOpen: false,
      detailLoading: false,
      loading: false,
      saving: false,
      error: '',
      success: '',
      form: { action_taken: '', action_note: '', lock_days: null },
      notificationForm: { recipient: 'reporter', message: '' },
      showAutoResolveModal: false,
      autoResolveLoading: false,
      autoResolveSaving: false,
      autoResolveConfigData: null,
      activeAutoTab: 'community_post',
      showScrollTop: false,
    };
  },
  computed: {
    hasSentNotification() {
      return this.auditLogs && this.auditLogs.some(log => ['report.notified', 'report.resolved', 'report.dismissed'].includes(String(log.action).trim().toLowerCase()));
    },
    filteredTargetTypes() {
      if (this.filters.target_group === 'content') {
        return this.targetTypes.filter(t => ['post', 'comment', 'venue_post', 'player_post'].includes(t.value));
      }
      return [];
    },
    currentAutoConfig() {
      if (!this.autoResolveConfigData || !this.autoResolveConfigData.configs) {
        return null;
      }
      return this.autoResolveConfigData.configs[this.activeAutoTab];
    },
  },
  async mounted() {
    this.loadReports();
    window.addEventListener('scroll', this.handleScroll);
    try {
      const policyResponse = await adminUserService.getLockPolicy();
      this.userLockThreshold = policyResponse.data?.lock_threshold || 10;
    } catch (e) {
      console.error(e);
    }
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll);
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
    setTargetGroup(group) {
      this.filters.target_group = group;
      this.filters.target_type = ''; // Reset specific target type
      this.loadReports();
    },
    getTargetUrl(report) {
      if (!report || !report.target_id) return null;
      const id = report.target_id;
      const slug = report.target?.slug || id;
      
      switch (report.target_type) {
        case 'post':
        case 'venue_post':
        case 'player_post':
          return window.location.origin + '/community/' + slug;
        case 'comment':
          return window.location.origin + '/community/' + (report.target?.post?.slug || report.parent_id || slug);
        case 'user':
          return this.$router.resolve({ name: 'admin-user-detail', params: { id } }).href;
        case 'venue':
          return this.$router.resolve({ name: 'admin-venue-cluster-detail', params: { id } }).href;
        default:
          return null;
      }
    },
    handleScroll() {
      this.showScrollTop = window.scrollY > 250;
    },
    async loadReports() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminReportService.list(this.filters);
        this.reports = response.data || [];
        this.summary = response.summary || {};
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },
    async openDetail(report) {
      this.detailOpen = true;
      this.detailLoading = true;
      this.selected = null;
      this.auditLogs = [];
      this.reportedUserViolation = null;
      try {
        const response = await adminReportService.show(report.id);
        this.selected = response.data.report;
        this.auditLogs = response.data.audit_logs || [];
        this.form = {
          action_taken: this.selected.action_taken || '',
          action_note: this.selected.action_note || '',
          lock_days: null,
        };
        this.notificationForm = { recipient: 'reporter', message: '' };
        
        if (this.selected.reported_user) {
          try {
            const vrResponse = await adminReportService.getViolationRecord('user', this.selected.reported_user.id);
            this.reportedUserViolation = vrResponse.data;
          } catch (e) {
            console.error('Lỗi khi lấy thông tin vi phạm:', e);
          }
        }
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
    async takeReview() {
      this.saving = true;
      try {
        const response = await adminReportService.review(this.selected.id);
        this.form = { action_taken: '', action_note: '', lock_days: null };
        await this.refreshDetail();
        this.success = response.message || 'Đã nhận kiểm duyệt báo cáo.';
        await this.loadReports();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.saving = false;
      }
    },
    async submitDecision(decision) {
      this.saving = true;
      try {
        const response = await adminReportService.resolve(this.selected.id, { ...this.form, decision });
        this.success = response.message;
        await this.loadReports();
        await this.refreshDetail();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.saving = false;
      }
    },
    async sendAdditionalNotification() {
      if (!this.selected?.id || !this.notificationForm.message) return;
      this.saving = true;
      try {
        const payload = {
          message: this.form.notify_message,
          recipient: this.form.notify_recipient || 'reporter',
        };
        await adminReportService.notify(this.selected.id, payload);
        this.success = 'Đã gửi thông báo thành công!';
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
        const reporterMsg = "Xin chào! Cảm ơn bạn đã gửi báo cáo. Chúng tôi đã tiếp nhận và xử lý vi phạm liên quan đến nội dung này. Nếu bạn có thêm thông tin, vui lòng liên hệ qua trung tâm hỗ trợ. Chúc bạn một ngày tốt lành!";
        await adminReportService.notify(this.selected.id, {
          recipient: 'reporter',
          message: reporterMsg
        });
        
        const reportedMsg = "Xin chào! Chúng tôi gửi thông báo này để nhắc nhở bạn về việc vi phạm chính sách của SportGo. Vui lòng rà soát lại các nội dung/hoạt động của bạn và tuân thủ đúng quy định. Nếu có thắc mắc, vui lòng liên hệ trung tâm hỗ trợ.";
        await adminReportService.notify(this.selected.id, {
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
    async refreshDetail() {
      const response = await adminReportService.show(this.selected.id);
      this.selected = response.data.report;
      this.auditLogs = response.data.audit_logs || [];
    },
    targetLabel(value) {
      return this.targetTypes.find((item) => item.value === value)?.label || value || '-';
    },
    reasonLabel(value) {
      return this.reasons.find((item) => item.value === value)?.label || value || '-';
    },
    statusLabel(value) {
      return this.statuses.find((item) => item.value === value)?.label || value || '-';
    },
    openLockUserModal() {
      this.showLockUserModal = true;
      this.lockUserForm.status_reason = 'Nhận quá nhiều báo cáo vi phạm';
      this.lockUserForm.locked_until = '';
    },
    closeLockUserModal() {
      this.showLockUserModal = false;
    },
    async submitLockUser() {
      if (!this.selected?.reported_user?.id) return;
      this.lockUserSaving = true;
      try {
        const payload = {
          status_reason: this.lockUserForm.status_reason,
        };
        if (this.lockUserForm.locked_until) {
          const date = new Date(this.lockUserForm.locked_until);
          const isoString = date.toISOString().slice(0, 19).replace('T', ' ');
          payload.locked_until = isoString;
        }
        await adminUserService.lockUser(this.selected.reported_user.id, payload);
        this.success = 'Khóa tài khoản thành công!';
        this.closeLockUserModal();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.lockUserSaving = false;
      }
    },
    isTerminalStatus(value) {
      return ['resolved', 'dismissed'].includes(value);
    },
    actionLabel(value) {
      return {
        warning: 'Cảnh báo',
        content_hidden: 'Ẩn nội dung',
        content_deleted: 'Xóa nội dung',
        account_locked: 'Khóa tài khoản',
        venue_locked: 'Khóa cụm sân',
      }[value] || (value ? value : 'Chưa xử lý');
    },
    auditLabel(value) {
      return {
        'report.reviewing': 'Nhận kiểm duyệt',
        'report.resolved': 'Xử lý báo cáo',
        'report.dismissed': 'Bỏ qua báo cáo',
        'content.hidden': 'Ẩn nội dung',
        'content.deleted': 'Xóa nội dung',
        'user.locked_by_report': 'Khóa tài khoản',
        'venue.locked_by_report': 'Khóa cụm sân',
      }[value] || value;
    },
    reportLabel(report) {
      if (!report) return '';
      const target = this.targetLabel(report.target_type);
      const reason = this.reasonLabel(report.reason);
      return reason ? `${target} · ${reason}` : target;
    },
    formatDateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    formatFileSize(value) {
      return value ? `${Math.max(1, Math.round(value / 1024))} KB` : '0 KB';
    },
    mediaUrl(path) {
      return path?.startsWith('http') ? path : `/storage/${path}`;
    },
    async openAutoResolveModal() {
      this.showAutoResolveModal = true;
      this.autoResolveLoading = true;
      this.activeAutoTab = 'community_post';
      try {
        const res = await adminReportService.getAutoResolveConfig();
        this.autoResolveConfigData = res.data;
      } catch (err) {
        this.error = 'Không thể tải cấu hình tự động xử lý báo cáo.';
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
        await adminReportService.saveAutoResolveConfig(payload);
        this.success = 'Lưu cấu hình tự động xử lý báo cáo thành công.';
        this.closeAutoResolveModal();
      } catch (err) {
        this.error = err.message || 'Lỗi khi lưu cấu hình.';
      } finally {
        this.autoResolveSaving = false;
      }
    },
  },
};
</script>

<style scoped>
.moderation-page .card-title {
  width: 100%;
  max-width: 100%;
}

.moderation-page .card-title strong {
  white-space: normal;
  overflow-wrap: anywhere;
}

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
  z-index: 999;
  transition: right 0.3s ease;
}

.floating-config-container.has-scroll {
  right: 84px;
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
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
  background-color: #f8fafc;
}

.floating-config-btn.never-hover-class-placeholder .floating-config-text {
  max-width: 170px;
  opacity: 1;
  margin-left: 6px;
}
</style>
