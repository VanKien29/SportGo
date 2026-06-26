<template>
  <div class="avcd-page">
    <!-- ── Loading / Error ── -->
    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải dữ liệu cụm sân...</p>
    </div>
    <div v-else-if="error" class="state-box card error-box">
      <p>{{ error }}</p>
      <button class="btn btn-outline" @click="loadDetail">Thử lại</button>
    </div>

    <!-- ── Main content ── -->
    <template v-else-if="cluster">
      <!-- Header -->
      <div class="avcd-header card">
        <div class="avcd-title-row">
          <div>
            <h2 class="avcd-title">{{ cluster.name }}</h2>
            <p class="avcd-sub">Quản lý và theo dõi thông tin chi tiết cụm sân của đối tác.</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="avcd-tabs card">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="tab-btn"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
          <span v-if="tab.key === 'location_changes' && pendingLocationChangeCount > 0" class="tab-badge-admin">
            {{ pendingLocationChangeCount }}
          </span>
          <span v-if="tab.key === 'unlock_appeals' && pendingUnlockAppealCount > 0" class="tab-badge-admin">
            {{ pendingUnlockAppealCount }}
          </span>
        </button>
      </div>

      <!-- ┌ Tab: Thông tin ──────────────────────────────────────────── -->
      <div v-if="activeTab === 'info'" class="info-tab-content">
        
        <div class="info-flex-layout">
          <!-- Cột bên trái: Chi tiết cụm sân -->
          <div class="info-main-col card">
            <div class="info-header-row-premium">
              <h3 class="section-title-inline">Thông tin cụm sân</h3>
            </div>
            
            <div class="info-detail-list">
              <div class="info-detail-item">
                <div class="info-detail-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                </div>
                <div class="info-detail-body">
                  <span class="info-detail-label">Tên cụm sân</span>
                  <span class="info-detail-value fw-bold text-lg">{{ cluster.name }}</span>
                </div>
              </div>

              <div class="info-detail-item">
                <div class="info-detail-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <div class="info-detail-body">
                  <span class="info-detail-label">Chủ sân</span>
                  <span class="info-detail-value">
                    {{ cluster.owner?.full_name }}
                    <span class="muted-username">@{{ cluster.owner?.username }}</span>
                  </span>
                </div>
              </div>

              <div class="info-detail-item">
                <div class="info-detail-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.907c.961 0 1.371 1.24.588 1.81l-3.97 2.883a1 1 0 00-.364 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.971-2.883a1 1 0 00-1.175 0l-3.97 2.883c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.364-1.118l-3.97-2.883c-.783-.57-.372-1.81.588-1.81h4.907a1 1 0 00.95-.69l1.519-4.674z" />
                  </svg>
                </div>
                <div class="info-detail-body">
                  <span class="info-detail-label">Đánh giá</span>
                  <div class="rating-stars">
                    <span class="rating-num">{{ Number(cluster.rating_avg || 0).toFixed(1) }}</span>
                    <span class="rating-max">/ 5</span>
                    <span class="rating-count">({{ cluster.rating_count }} lượt đánh giá)</span>
                  </div>
                </div>
              </div>

              <div class="info-detail-item">
                <div class="info-detail-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div class="info-detail-body">
                  <span class="info-detail-label">Địa chỉ</span>
                  <span class="info-detail-value">{{ formatFullAddress(cluster) }}</span>
                </div>
              </div>
            </div>

            <!-- Bản đồ vị trí cụm sân -->
            <div class="map-section" style="margin-top: 24px; border-top: 1px solid rgba(15, 23, 42, 0.06); padding-top: 20px;">
              <div class="map-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; width: 100%;">
                <span class="info-detail-label" style="margin-bottom: 0; display: flex; align-items: center; gap: 6px; font-weight: 700; color: rgba(15, 23, 42, 0.55);">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L16 4m0 13V4m0 0L9 7" />
                  </svg>
                  Bản đồ vị trí
                </span>
                <a v-if="cluster.map_url" :href="cluster.map_url" target="_blank" rel="noopener" class="btn-map-link" style="padding: 4px 10px; font-size: 12px;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                  Mở Google Maps
                </a>
              </div>
              <div v-if="cluster.latitude && cluster.longitude" class="map-embed-container" style="width: 100%; border-radius: 8px; overflow: hidden; border: 1px solid rgba(15, 23, 42, 0.08); line-height: 0; box-shadow: var(--admin-shadow-sm);">
                <iframe
                  width="100%"
                  height="300"
                  style="border:0;"
                  loading="lazy"
                  allowfullscreen
                  referrerpolicy="no-referrer-when-downgrade"
                  :src="`https://maps.google.com/maps?q=${cluster.latitude},${cluster.longitude}&hl=vi&z=15&output=embed`"
                ></iframe>
              </div>
              <div v-else class="muted" style="font-size: 13px; font-style: italic;">
                Chưa cấu hình tọa độ cho cụm sân này.
              </div>
            </div>
          </div>

          <!-- Cột bên phải: Quản lý & Tiện ích -->
          <div class="info-side-col">
            <!-- Box Quản trị & Trạng thái -->
            <div class="card status-card">
              <h4 class="side-card-title">Quản trị & Liên hệ</h4>
              
              <div class="side-item-group">
                <div class="side-info-item">
                  <span class="side-label">Trạng thái cụm sân</span>
                  <div class="status-action-row">
                    <span class="custom-status-badge" :class="`custom-status-${cluster.status}`">
                      {{ statusLabel(cluster.status) }}
                    </span>
                    <button
                      v-if="cluster.status !== 'locked'"
                      id="btn-lock-cluster"
                      class="btn btn-danger btn-sm"
                      style="padding: 4px 10px;"
                      @click="openLockModal"
                    >Khóa</button>
                    <button
                      v-else
                      id="btn-unlock-cluster"
                      class="btn btn-success btn-sm"
                      style="padding: 4px 10px;"
                      :disabled="unlocking"
                      @click="handleUnlock"
                    >{{ unlocking ? '...' : 'Mở khóa' }}</button>
                  </div>
                </div>

                <div class="side-info-item">
                  <span class="side-label">Số điện thoại liên hệ</span>
                  <span class="side-value">{{ cluster.phone_contact || '—' }}</span>
                </div>

                <div class="side-info-item">
                  <span class="side-label">Email liên hệ</span>
                  <span class="side-value">{{ cluster.owner?.email || '—' }}</span>
                </div>
              </div>
            </div>

            <!-- Box Tiện ích -->
            <div class="card amenities-card">
              <h4 class="side-card-title" style="border-bottom: 1px solid rgba(15, 23, 42, 0.06); padding-bottom: 8px;">Tiện ích dịch vụ</h4>
              
              <div class="amenities-content">
                <div v-if="cluster.amenities && cluster.amenities.length" class="amenity-chips">
                  <span v-for="(a, i) in cluster.amenities" :key="i" class="amenity-chip-premium">{{ a }}</span>
                </div>
                <div v-else class="empty-amenities">
                  Chưa có tiện ích nào được thiết lập.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Khối 5: Mô tả & Album ảnh -->
        <div class="avcd-card card" style="margin-top: 20px;">
          <div class="info-item full-width" v-if="cluster.description" style="margin-bottom: 20px;">
            <span class="info-label">Mô tả cụm sân</span>
            <span class="info-value" style="margin-top: 6px; display: block; line-height: 1.6; white-space: pre-wrap;">{{ cluster.description }}</span>
          </div>

          <div class="info-item full-width">
            <span class="info-label">Hình ảnh cụm sân (Album)</span>
            <div v-if="cluster.images && cluster.images.length" class="gallery-grid">
              <div v-for="img in cluster.images" :key="img.id" class="gallery-item">
                <img :src="imageUrl(img.file_path)" alt="Hình ảnh cụm sân" class="gallery-img" @error="hideBrokenImage" />
              </div>
            </div>
            <div v-else class="empty-gallery">
              Chưa có hình ảnh nào được tải lên cho cụm sân này.
            </div>
          </div>
          
          <!-- Lock Alert Banner -->
          <div v-if="cluster.status === 'locked'" class="lock-alert-banner">
            <div class="lock-alert-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <div class="lock-alert-content">
              <h4 class="lock-alert-title">Cụm sân này đang bị tạm khóa</h4>
              <p class="lock-alert-reason"><strong>Lý do khóa:</strong> {{ cluster.status_reason || 'Không có lý do cụ thể.' }}</p>
              
              <div class="lock-alert-meta" v-if="cluster.locked_at || cluster.locked_by">
                <div class="lock-meta-item" v-if="cluster.locked_at">
                  <span class="lock-meta-label">Khóa lúc:</span>
                  <span class="lock-meta-val">{{ formatDate(cluster.locked_at) }}</span>
                </div>
                <div class="lock-meta-item" v-if="cluster.locked_until">
                  <span class="lock-meta-label">Khóa đến:</span>
                  <span class="lock-meta-val">{{ formatDate(cluster.locked_until) }}</span>
                </div>
                <div class="lock-meta-item" v-if="cluster.locked_by">
                  <span class="lock-meta-label">Khóa bởi:</span>
                  <span class="lock-meta-val">{{ cluster.locked_by?.full_name || cluster.locked_by }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ┌ Tab: Sân con ──────────────────────────────────────────── -->
      <div v-if="activeTab === 'courts'" class="avcd-card card">
        <h3 class="section-title">Danh sách sân con ({{ (cluster.courts || []).length }})</h3>
        <div v-if="!cluster.courts || cluster.courts.length === 0" class="empty-section">
          Chưa có sân con nào.
        </div>
        <table v-else class="simple-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Tên sân</th>
              <th>Loại sân</th>
              <th class="text-center">Thứ tự</th>
              <th class="text-center">Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(court, idx) in cluster.courts" :key="court.id">
              <td class="muted">{{ idx + 1 }}</td>
              <td class="fw-bold">{{ court.name }}</td>
              <td>{{ court.court_type?.name || '—' }}</td>
              <td class="text-center">{{ court.sort_order ?? '—' }}</td>
              <td class="text-center">
                <span class="status-badge" :class="court.status === 'active' ? 'status-active' : 'status-locked'">
                  {{ court.status === 'active' ? 'Hoạt động' : court.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ┌ Tab: Booking ──────────────────────────────────────────── -->
      <div v-if="activeTab === 'bookings'" class="avcd-card card">
        <h3 class="section-title">Lịch sử đặt sân (20 gần nhất)</h3>
        <div v-if="!bookings || bookings.length === 0" class="empty-section">Chưa có lượt đặt sân.</div>
        <table v-else class="simple-table">
          <thead>
            <tr>
              <th>Mã booking</th>
              <th>Khách hàng</th>
              <th>Sân</th>
              <th>Ngày</th>
              <th>Giờ</th>
              <th class="text-right">Tổng tiền</th>
              <th class="text-center">Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in bookings" :key="b.id">
              <td class="mono">{{ b.booking_code }}</td>
              <td>
                <div class="fw-bold">{{ b.customer?.full_name || '—' }}</div>
                <div class="muted">{{ b.customer?.phone }}</div>
              </td>
              <td>{{ b.venue_court?.name || '—' }}</td>
              <td>{{ formatDate(b.booking_date, false) }}</td>
              <td class="mono">{{ b.start_time }} – {{ b.end_time }}</td>
              <td class="text-right fw-bold">{{ formatCurrency(b.total_price) }}</td>
              <td class="text-center">
                <span class="booking-status" :class="`bs-${b.status}`">{{ b.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ┌ Tab: Phí ──────────────────────────────────────────── -->
      <div v-if="activeTab === 'fees'" class="fees-tab">

        <!-- Empty state -->
        <div v-if="!fees || fees.length === 0" class="fees-empty-state">
          <div class="fees-empty-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
          </div>
          <p class="fees-empty-text">Chưa có bản ghi phí nền tảng nào.</p>
        </div>

        <template v-else>
          <!-- Summary stats -->
          <div class="fees-summary-grid">
            <div class="fees-stat-card fees-stat-total">
              <div class="fees-stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 20h16a2 2 0 002-2V6a2 2 0 00-2-2H4a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div class="fees-stat-body">
                <span class="fees-stat-label">Tổng phí phát sinh</span>
                <span class="fees-stat-value">{{ formatCurrency(fees.reduce((s, f) => s + (Number(f.amount_due) || 0), 0)) }}</span>
                <span class="fees-stat-sub">{{ fees.length }} kỳ thanh toán</span>
              </div>
            </div>
            <div class="fees-stat-card fees-stat-paid">
              <div class="fees-stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="fees-stat-body">
                <span class="fees-stat-label">Đã thanh toán</span>
                <span class="fees-stat-value fees-stat-value-green">{{ formatCurrency(fees.reduce((s, f) => s + (Number(f.amount_paid) || 0), 0)) }}</span>
                <span class="fees-stat-sub">{{ fees.filter(f => f.status === 'paid').length }} kỳ hoàn tất</span>
              </div>
            </div>
            <div class="fees-stat-card fees-stat-pending">
              <div class="fees-stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="fees-stat-body">
                <span class="fees-stat-label">Còn nợ</span>
                <span class="fees-stat-value fees-stat-value-orange">{{ formatCurrency(fees.reduce((s, f) => s + Math.max(0, Number(f.amount_due) - Number(f.amount_paid)), 0)) }}</span>
                <span class="fees-stat-sub">{{ fees.filter(f => f.status !== 'paid').length }} kỳ chưa xong</span>
              </div>
            </div>
            <div class="fees-stat-card fees-stat-overdue">
              <div class="fees-stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div class="fees-stat-body">
                <span class="fees-stat-label">Kỳ quá hạn</span>
                <span class="fees-stat-value fees-stat-value-red">{{ fees.filter(f => isOverdue(f)).length }}</span>
                <span class="fees-stat-sub">cần xử lý khẩn</span>
              </div>
            </div>
          </div>

          <!-- Fee cards list -->
          <div class="fees-card-wrap card">
            <div class="fees-card-header">
              <div class="fees-card-title-group">
                <div class="fees-card-icon-wrap">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                  </svg>
                </div>
                <div>
                  <h3 class="fees-card-title">Lịch sử phí nền tảng</h3>
                  <p class="fees-card-subtitle">Danh sách các kỳ thanh toán phí theo gói dịch vụ</p>
                </div>
              </div>
              <span class="fees-count-badge">{{ fees.length }} kỳ</span>
            </div>

            <!-- Table Header -->
            <div class="fee-table-header">
              <div class="fee-col-pkg">Gói & Kỳ</div>
              <div class="fee-col-period">Chu kỳ</div>
              <div class="fee-col-due">Hạn TT</div>
              <div class="fee-col-amount">Số tiền</div>
              <div class="fee-col-paid">Đã trả</div>
              <div class="fee-col-progress">Tiến độ</div>
              <div class="fee-col-status">Trạng thái</div>
            </div>

            <div class="fee-items-list">
              <div
                v-for="f in fees"
                :key="f.id"
                class="fee-row"
                :class="{ 'fee-row-overdue': isOverdue(f), 'fee-row-paid': f.status === 'paid' }"
              >
                <!-- Gói & Số sân -->
                <div class="fee-col-pkg">
                  <div class="fee-pkg-name">
                    <div class="fee-pkg-dot" :class="f.status === 'paid' ? 'dot-green' : isOverdue(f) ? 'dot-red' : 'dot-amber'"></div>
                    {{ f.tier?.name || 'Không rõ gói' }}
                  </div>
                  <div class="fee-court-count">
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    {{ f.court_count }} sân
                  </div>
                </div>

                <!-- Chu kỳ -->
                <div class="fee-col-period">
                  <span class="fee-period-text">{{ formatDate(f.period_start, false) }}</span>
                  <span class="fee-period-sep">→</span>
                  <span class="fee-period-text">{{ formatDate(f.period_end, false) }}</span>
                </div>

                <!-- Hạn TT -->
                <div class="fee-col-due">
                  <span class="fee-due-date" :class="isOverdue(f) ? 'text-red-600' : ''">
                    {{ formatDate(f.due_date, false) }}
                  </span>
                  <span v-if="isOverdue(f)" class="fee-overdue-chip">Quá hạn</span>
                </div>

                <!-- Số tiền -->
                <div class="fee-col-amount">
                  <span class="fee-amount-num">{{ formatCurrency(f.amount_due) }}</span>
                </div>

                <!-- Đã trả -->
                <div class="fee-col-paid">
                  <span class="fee-paid-num" :class="f.status === 'paid' ? 'text-green' : ''">
                    {{ formatCurrency(f.amount_paid) }}
                  </span>
                </div>

                <!-- Tiến độ -->
                <div class="fee-col-progress">
                  <div class="fee-progress-wrap">
                    <div class="fee-progress-track">
                      <div
                        class="fee-progress-bar"
                        :class="f.status === 'paid' ? 'fee-progress-full' : isOverdue(f) ? 'fee-progress-overdue' : 'fee-progress-partial'"
                        :style="{ width: Math.min(100, Math.round((Number(f.amount_paid) / Number(f.amount_due)) * 100)) + '%' }"
                      ></div>
                    </div>
                    <span class="fee-progress-pct">{{ Math.min(100, Math.round((Number(f.amount_paid) / Number(f.amount_due)) * 100)) }}%</span>
                  </div>
                </div>

                <!-- Trạng thái -->
                <div class="fee-col-status">
                  <span class="fee-status-badge" :class="`fee-status-${f.status}`">
                    <span class="fee-status-dot"></span>
                    {{ { paid: 'Đã thanh toán', unpaid: 'Chưa TT', partial: 'Một phần', overdue: 'Quá hạn' }[f.status] || f.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- ┌ Tab: Lịch sử khóa ──────────────────────────────────────── -->
      <div v-if="activeTab === 'lock_history'" class="avcd-card card">
        <h3 class="section-title">Lịch sử khóa / mở khóa</h3>
        <div v-if="!lockHistory || lockHistory.length === 0" class="empty-section">Chưa có lịch sử khóa.</div>
        <div v-else class="timeline-container">
          <div v-for="log in lockHistory" :key="log.id" class="timeline-item">
            <!-- Badge/Icon -->
            <div class="timeline-badge" :class="`badge-${getLogActionDetails(log.action).type}`">
              <!-- Lock Icon -->
              <svg v-if="getLogActionDetails(log.action).type === 'lock'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              <!-- Unlock Icon -->
              <svg v-else-if="getLogActionDetails(log.action).type === 'unlock'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
              </svg>
              <!-- Update/Config Icon -->
              <svg v-else-if="getLogActionDetails(log.action).type === 'update'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <!-- Default Icon -->
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
            </div>

            <!-- Content Card -->
            <div class="timeline-content">
              <div class="timeline-header">
                <span class="timeline-action-label" :class="`text-${getLogActionDetails(log.action).type}`">
                  {{ getLogActionDetails(log.action).label }}
                </span>
                
                <div class="timeline-meta">
                  <span class="timeline-actor">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ log.actor?.full_name || 'Hệ thống' }}
                  </span>
                  <span class="timeline-time">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ formatDate(log.created_at) }}
                  </span>
                </div>
              </div>

              <!-- Reason (if Lock/Unlock) -->
              <div v-if="log.reason || log.new_values?.status_reason" class="timeline-reason-box">
                <span class="reason-title">Lý do:</span>
                <p class="reason-content">{{ log.reason || log.new_values?.status_reason }}</p>
              </div>

              <!-- Lock duration details -->
              <div v-if="log.action === 'venue_cluster.locked' && log.new_values && log.new_values.locked_until" class="timeline-sub-details">
                <span class="duration-label">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Khóa đến: <strong>{{ formatDate(log.new_values.locked_until) }}</strong>
                </span>
              </div>
              <div v-else-if="log.action === 'venue_cluster.locked'" class="timeline-sub-details">
                <span class="duration-label">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Thời hạn: <strong>Khóa vĩnh viễn (Cho đến khi mở lại)</strong>
                </span>
              </div>

              <!-- New Amenities (if update) -->
              <div v-if="log.action === 'venue_cluster.amenities_updated' && log.new_values && log.new_values.amenities" class="timeline-amenities-box">
                <span class="amenities-title">Danh sách tiện ích mới:</span>
                <div class="timeline-amenity-chips">
                  <span v-for="(a, idx) in log.new_values.amenities" :key="idx" class="timeline-amenity-chip">
                    {{ a }}
                  </span>
                  <span v-if="!log.new_values.amenities.length" class="timeline-no-amenities">Không có tiện ích nào</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ┌ Tab: Yêu cầu quy mô ──────────────────────────────────────── -->
      <div v-if="activeTab === 'approvals'" class="avcd-card card">
        <h3 class="section-title">Yêu cầu mở rộng / thu hẹp quy mô</h3>

        <div class="approval-tabs">
          <button class="tab-sm" :class="{ active: approvalFilter === '' }" @click="approvalFilter = ''">Tất cả</button>
          <button class="tab-sm" :class="{ active: approvalFilter === 'pending' }" @click="approvalFilter = 'pending'">Chờ duyệt</button>
          <button class="tab-sm" :class="{ active: approvalFilter === 'approved' }" @click="approvalFilter = 'approved'">Đã duyệt</button>
          <button class="tab-sm" :class="{ active: approvalFilter === 'rejected' }" @click="approvalFilter = 'rejected'">Từ chối</button>
        </div>

        <div v-if="filteredApprovals.length === 0" class="empty-section">Không có yêu cầu nào.</div>

        <div v-else class="approval-list">
          <div v-for="req in filteredApprovals" :key="req.id" class="approval-card" :class="`approval-${req.status}`">
            <div class="approval-row">
              <div>
                <div class="approval-name fw-bold">{{ req.name }}</div>
                <div class="muted">Loại: {{ req.court_type?.name || '—' }}</div>
                <div class="muted">Yêu cầu bởi: {{ req.requested_by?.full_name || '—' }} · {{ formatDate(req.created_at) }}</div>
                <div v-if="req.reviewed_by" class="muted">Xử lý bởi: {{ req.reviewed_by?.full_name }} · {{ formatDate(req.reviewed_at) }}</div>
                <div v-if="req.status_reason" class="reason-text">Lý do: {{ req.status_reason }}</div>
                <div v-if="req.evidence_image_url" class="approval-evidence" style="margin-top: 8px;">
                  <span class="approval-evidence-label" style="display:block; font-size:12.5px; color:var(--text-secondary); margin-bottom:4px;"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:-2px;margin-right:3px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> Ảnh minh chứng:</span>
                  <a :href="req.evidence_image_url" target="_blank" style="display:inline-block;">
                    <img :src="req.evidence_image_url" alt="Ảnh minh chứng" style="max-width:200px; max-height:140px; border-radius:8px; border:1px solid var(--border-color); object-fit:cover; cursor:pointer; transition: transform 0.2s;" @mouseover="$event.target.style.transform='scale(1.05)'" @mouseout="$event.target.style.transform='scale(1)'" />
                  </a>
                </div>
              </div>
              <div class="approval-right">
                <span class="status-badge" :class="`status-${req.status}`">{{ approvalStatusLabel(req.status) }}</span>
                <div v-if="req.status === 'pending'" class="approval-btns">
                  <button class="btn btn-success btn-sm" :disabled="processingId === req.id" @click="handleApprove(req)">{{ processingId === req.id ? '...' : 'Duyệt' }}</button>
                  <button class="btn btn-danger btn-sm" :disabled="processingId === req.id" @click="openRejectModal(req)">Từ chối</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ┌ Tab: Yêu cầu thay đổi vị trí ──────────────────────────────── -->
      <div v-if="activeTab === 'location_changes'" class="avcd-card card">
        <h3 class="section-title">Yêu cầu thay đổi vị trí cụm sân</h3>
        <div class="approval-tabs">
          <button class="tab-sm" :class="{ active: locationChangeFilter === '' }" @click="locationChangeFilter = ''">Tất cả</button>
          <button class="tab-sm" :class="{ active: locationChangeFilter === 'pending' }" @click="locationChangeFilter = 'pending'">Chờ duyệt</button>
          <button class="tab-sm" :class="{ active: locationChangeFilter === 'approved' }" @click="locationChangeFilter = 'approved'">Đã duyệt</button>
          <button class="tab-sm" :class="{ active: locationChangeFilter === 'rejected' }" @click="locationChangeFilter = 'rejected'">Từ chối</button>
        </div>
        <div v-if="filteredLocationChanges.length === 0" class="empty-section">Không có yêu cầu nào.</div>
        <div v-else class="approval-list">
          <div v-for="req in filteredLocationChanges" :key="req.id" class="approval-card" :class="`approval-${req.status}`">
            <div class="approval-row">
              <div style="flex:1">
                <div class="approval-name fw-bold">Yêu cầu thay đổi vị trí</div>
                <div class="muted">Địa chỉ mới: {{ req.new_address }}, {{ req.new_ward }}, {{ req.new_province }}</div>
                <div class="muted">Tọa độ mới: {{ req.new_latitude }}, {{ req.new_longitude }}</div>
                <div v-if="req.new_map_url" class="muted">Map URL: <a :href="req.new_map_url" target="_blank" style="color:#2563eb">Xem bản đồ</a></div>
                <div class="muted">Lý do: {{ req.note }}</div>
                <div class="muted">Yêu cầu bởi: {{ req.requested_by?.full_name || '—' }} · {{ formatDate(req.created_at) }}</div>
                <div v-if="req.reviewed_by" class="muted">Xử lý bởi: {{ req.reviewed_by?.full_name }} · {{ formatDate(req.reviewed_at) }}</div>
                <div v-if="req.status_reason && req.status === 'rejected'" class="reason-text">Lý do từ chối: {{ req.status_reason }}</div>
              </div>
              <div class="approval-right">
                <span class="status-badge" :class="`status-${req.status}`">{{ approvalStatusLabel(req.status) }}</span>
                <div v-if="req.status === 'pending'" class="approval-btns">
                  <button class="btn btn-success btn-sm" :disabled="processingLocationId === req.id" @click="handleApproveLocation(req)">
                    {{ processingLocationId === req.id ? '...' : 'Duyệt' }}
                  </button>
                  <button class="btn btn-danger btn-sm" :disabled="processingLocationId === req.id" @click="openRejectLocationModal(req)">Từ chối</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ┌ Tab: Yêu cầu mở khóa ──────────────────────────────── -->
      <div v-if="activeTab === 'unlock_appeals'" class="avcd-card card">
        <h3 class="section-title">Yêu cầu mở khóa cụm sân</h3>
        <div class="approval-tabs">
          <button class="tab-sm" :class="{ active: unlockAppealFilter === '' }" @click="unlockAppealFilter = ''">Tất cả</button>
          <button class="tab-sm" :class="{ active: unlockAppealFilter === 'pending' }" @click="unlockAppealFilter = 'pending'">Chờ duyệt</button>
          <button class="tab-sm" :class="{ active: unlockAppealFilter === 'approved' }" @click="unlockAppealFilter = 'approved'">Đã duyệt</button>
          <button class="tab-sm" :class="{ active: unlockAppealFilter === 'rejected' }" @click="unlockAppealFilter = 'rejected'">Từ chối</button>
        </div>
        <div v-if="filteredUnlockAppeals.length === 0" class="empty-section">Không có yêu cầu nào.</div>
        <div v-else class="approval-list">
          <div v-for="req in filteredUnlockAppeals" :key="req.id" class="approval-card" :class="`approval-${req.status}`">
            <div class="approval-row">
              <div style="flex:1">
                <div class="approval-name fw-bold">Yêu cầu mở khóa từ chủ sân</div>
                <div class="muted" style="margin-top: 6px;">
                  <strong>Lý do giải trình của chủ sân:</strong>
                  <p style="margin: 4px 0; line-height: 1.5; white-space: pre-wrap; background: var(--admin-surface, #fff); padding: 10px; border: 1px solid var(--admin-border); border-radius: 6px;">
                    {{ req.reason }}
                  </p>
                </div>
                <div class="muted">Người yêu cầu: {{ req.requested_by?.full_name || '—' }} · {{ formatDate(req.created_at) }}</div>
                <div v-if="req.reviewed_by" class="muted">Người duyệt: {{ req.reviewed_by?.full_name }} · {{ formatDate(req.reviewed_at) }}</div>
                <div v-if="req.admin_note" class="reason-text" style="color: #475569; font-style: normal; margin-top: 6px;">
                  <strong>Phản hồi của Admin:</strong> {{ req.admin_note }}
                </div>
              </div>
              <div class="approval-right">
                <span class="status-badge" :class="`status-${req.status}`">{{ approvalStatusLabel(req.status) }}</span>
                <div v-if="req.status === 'pending'" class="approval-btns">
                  <button class="btn btn-success btn-sm" :disabled="processingUnlockId === req.id" @click="handleApproveUnlock(req)">
                    {{ processingUnlockId === req.id ? '...' : 'Duyệt mở khóa' }}
                  </button>
                  <button class="btn btn-danger btn-sm" :disabled="processingUnlockId === req.id" @click="openRejectUnlockModal(req)">Từ chối</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>


    <!-- ── Modal: Khóa cụm sân ── -->
    <div v-if="showLockModal" class="modal-backdrop" @click.self="closeLockModal">
      <form class="modal-box card" @submit.prevent="handleLock">
        <div class="modal-header">
          <h3>Khóa cụm sân</h3>
          <button type="button" class="btn-close" @click="closeLockModal">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="lockError" class="alert-error">{{ lockError }}</div>
          <label class="form-label">
            Lý do khóa <span class="required">*</span>
            <textarea
              v-model="lockForm.status_reason"
              rows="4"
              required
              placeholder="Nhập lý do khóa cụm sân..."
              class="form-control"
            ></textarea>
          </label>
          <label class="form-label">
            Khóa đến (để trống = khóa vĩnh viễn)
            <input
              v-model="lockForm.locked_until"
              type="datetime-local"
              class="form-control"
            />
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="closeLockModal">Hủy</button>
          <button type="submit" class="btn btn-danger" :disabled="locking">
            {{ locking ? 'Đang khóa...' : 'Xác nhận khóa' }}
          </button>
        </div>
      </form>
    </div>

    <!-- ── Modal: Từ chối yêu cầu ── -->
    <div v-if="rejectTarget" class="modal-backdrop" @click.self="closeRejectModal">
      <form class="modal-box card" @submit.prevent="handleReject">
        <div class="modal-header">
          <h3>Từ chối yêu cầu</h3>
          <button type="button" class="btn-close" @click="closeRejectModal">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <p class="muted">Yêu cầu: <strong>{{ rejectTarget.name }}</strong></p>
          <div v-if="rejectError" class="alert-error">{{ rejectError }}</div>
          <label class="form-label">
            Lý do từ chối <span class="required">*</span>
            <textarea
              v-model="rejectReason"
              rows="4"
              required
              placeholder="Nhập lý do từ chối..."
              class="form-control"
            ></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="closeRejectModal">Hủy</button>
          <button type="submit" class="btn btn-danger" :disabled="rejecting">
            {{ rejecting ? 'Đang từ chối...' : 'Xác nhận từ chối' }}
          </button>
        </div>
      </form>
    </div>

    <!-- ── Modal: Từ chối yêu cầu vị trí ── -->
    <div v-if="rejectLocationTarget" class="modal-backdrop" @click.self="closeRejectLocationModal">
      <form class="modal-box card" @submit.prevent="handleRejectLocation">
        <div class="modal-header">
          <h3>Từ chối yêu cầu thay đổi vị trí</h3>
          <button type="button" class="btn-close" @click="closeRejectLocationModal">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <p class="muted">Địa chỉ mới: <strong>{{ rejectLocationTarget.new_address }}, {{ rejectLocationTarget.new_province }}</strong></p>
          <div v-if="rejectLocationError" class="alert-error">{{ rejectLocationError }}</div>
          <label class="form-label">
            Lý do từ chối <span class="required">*</span>
            <textarea
              v-model="rejectLocationReason"
              rows="4"
              required
              placeholder="Nhập lý do từ chối..."
              class="form-control"
            ></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="closeRejectLocationModal">Hủy</button>
          <button type="submit" class="btn btn-danger" :disabled="rejectingLocation">
            {{ rejectingLocation ? 'Đang từ chối...' : 'Xác nhận từ chối' }}
          </button>
        </div>
      </form>
    </div>

    <!-- ── Modal: Từ chối yêu cầu mở khóa ── -->
    <div v-if="rejectUnlockTarget" class="modal-backdrop" @click.self="closeRejectUnlockModal">
      <form class="modal-box card" @submit.prevent="handleRejectUnlock">
        <div class="modal-header">
          <h3>Từ chối yêu cầu mở khóa</h3>
          <button type="button" class="btn-close" @click="closeRejectUnlockModal">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <p class="muted">Giải trình của chủ sân: <strong>{{ rejectUnlockTarget.reason }}</strong></p>
          <div v-if="rejectUnlockError" class="alert-error">{{ rejectUnlockError }}</div>
          <label class="form-label">
            Lý do từ chối <span class="required">*</span>
            <textarea
              v-model="rejectUnlockReason"
              rows="4"
              required
              placeholder="Nhập phản hồi từ chối..."
              class="form-control"
            ></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="closeRejectUnlockModal">Hủy</button>
          <button type="submit" class="btn btn-danger" :disabled="rejectingUnlock">
            {{ rejectingUnlock ? 'Đang từ chối...' : 'Xác nhận từ chối' }}
          </button>
        </div>
      </form>
    </div>

    </template>

    <!-- ── Global alert ── -->
    <transition name="fade">
      <div v-if="globalMsg" class="global-alert" :class="globalMsgType">
        {{ globalMsg }}
      </div>
    </transition>
  </div>
</template>

<script>
import { adminVenueClusterService } from '../../services/adminVenueClusterService.js';

export default {
  name: 'AdminVenueClusterDetail',
  data() {
    return {
      cluster: null,
      bookings: [],
      fees: [],
      lockHistory: [],
      approvalRequests: [],
      loading: true,
      error: '',

      activeTab: 'info',
      tabs: [
        { key: 'info', label: 'Thông tin' },
        { key: 'courts', label: 'Sân con' },
        { key: 'bookings', label: 'Booking' },
        { key: 'fees', label: 'Phí' },
        { key: 'lock_history', label: 'Lịch sử khóa' },
        { key: 'approvals', label: 'Yêu cầu quy mô' },
        { key: 'location_changes', label: 'Yêu cầu vị trí' },
        { key: 'unlock_appeals', label: 'Yêu cầu mở khóa' },
      ],

      approvalFilter: '',
      processingId: null,

      locationChangeRequests: [],
      locationChangeFilter: '',
      processingLocationId: null,
      rejectLocationTarget: null,
      rejectLocationReason: '',
      rejectLocationError: '',
      rejectingLocation: false,

      // Lock modal
      showLockModal: false,
      locking: false,
      lockError: '',
      lockForm: { status_reason: '', locked_until: '' },

      // Unlock
      unlocking: false,

      // Reject modal
      rejectTarget: null,
      rejectReason: '',
      rejectError: '',
      rejecting: false,



      // Unlock requests state
      unlockRequests: [],
      unlockAppealFilter: '',
      processingUnlockId: null,
      rejectUnlockTarget: null,
      rejectUnlockReason: '',
      rejectUnlockError: '',
      rejectingUnlock: false,

      // Global message
      globalMsg: '',
      globalMsgType: 'msg-success',
      globalTimer: null,
    };
  },
  computed: {
    filteredApprovals() {
      if (!this.approvalFilter) return this.approvalRequests;
      return this.approvalRequests.filter((r) => r.status === this.approvalFilter);
    },
    filteredLocationChanges() {
      if (!this.locationChangeFilter) return this.locationChangeRequests;
      return this.locationChangeRequests.filter((r) => r.status === this.locationChangeFilter);
    },
    pendingLocationChangeCount() {
      return this.locationChangeRequests.filter((r) => r.status === 'pending').length;
    },
    filteredUnlockAppeals() {
      if (!this.unlockAppealFilter) return this.unlockRequests;
      return this.unlockRequests.filter((r) => r.status === this.unlockAppealFilter);
    },
    pendingUnlockAppealCount() {
      return this.unlockRequests.filter((r) => r.status === 'pending').length;
    },
  },
  mounted() {
    this.loadDetail();
  },
  methods: {
    async loadDetail() {
      this.loading = true;
      this.error = '';
      try {
        const res = await adminVenueClusterService.show(this.$route.params.id);
        const data = res.data;
        this.cluster = data.cluster;
        if (this.cluster && this.cluster.images) {
          this.cluster.images = this.cluster.images.filter(
            (img) => img.file_path && !img.file_path.includes('default-home.jpg')
          );
        }
        this.bookings = data.bookings || [];
        this.fees = data.fees || [];
        this.lockHistory = data.lock_history || [];
        this.approvalRequests = data.approval_requests || [];
        this.locationChangeRequests = data.location_change_requests || [];
        this.unlockRequests = data.unlock_requests || [];
      } catch (err) {
        this.error = err.message || 'Không tải được dữ liệu.';
      } finally {
        this.loading = false;
      }
    },

    // ── Lock / Unlock ──
    openLockModal() {
      this.lockForm = { status_reason: '', locked_until: '' };
      this.lockError = '';
      this.showLockModal = true;
    },
    closeLockModal() {
      this.showLockModal = false;
    },
    async handleLock() {
      this.locking = true;
      this.lockError = '';
      try {
        const payload = { status_reason: this.lockForm.status_reason };
        if (this.lockForm.locked_until) payload.locked_until = this.lockForm.locked_until;
        const res = await adminVenueClusterService.lock(this.cluster.id, payload);
        this.cluster = res.cluster;
        this.closeLockModal();
        this.showMsg('Khóa cụm sân thành công.', 'msg-success');
        await this.loadDetail();
      } catch (err) {
        this.lockError = err.message || 'Khóa không thành công.';
      } finally {
        this.locking = false;
      }
    },
    async handleUnlock() {
      if (!confirm('Mở khóa cụm sân này?')) return;
      this.unlocking = true;
      try {
        const res = await adminVenueClusterService.unlock(this.cluster.id);
        this.cluster = res.cluster;
        this.showMsg('Mở khóa thành công.', 'msg-success');
        await this.loadDetail();
      } catch (err) {
        this.showMsg(err.message || 'Mở khóa không thành công.', 'msg-error');
      } finally {
        this.unlocking = false;
      }
    },

    // ── Approve / Reject ──
    async handleApprove(req) {
      if (!confirm(`Duyệt yêu cầu "${req.name}"?`)) return;
      this.processingId = req.id;
      try {
        const res = await adminVenueClusterService.approveRequest(this.cluster.id, req.id);
        const idx = this.approvalRequests.findIndex((r) => r.id === req.id);
        if (idx !== -1) this.approvalRequests.splice(idx, 1, res.request);
        this.showMsg('Duyệt yêu cầu thành công.', 'msg-success');
        // Reload để cập nhật danh sách sân con
        await this.loadDetail();
      } catch (err) {
        this.showMsg(err.message || 'Duyệt không thành công.', 'msg-error');
      } finally {
        this.processingId = null;
      }
    },
    openRejectModal(req) {
      this.rejectTarget = req;
      this.rejectReason = '';
      this.rejectError = '';
    },
    closeRejectModal() {
      this.rejectTarget = null;
    },
    async handleReject() {
      this.rejecting = true;
      this.rejectError = '';
      try {
        const res = await adminVenueClusterService.rejectRequest(
          this.cluster.id,
          this.rejectTarget.id,
          { status_reason: this.rejectReason },
        );
        const idx = this.approvalRequests.findIndex((r) => r.id === this.rejectTarget.id);
        if (idx !== -1) this.approvalRequests.splice(idx, 1, res.request);
        this.closeRejectModal();
        this.showMsg('Đã từ chối yêu cầu.', 'msg-success');
      } catch (err) {
        this.rejectError = err.message || 'Từ chối không thành công.';
      } finally {
        this.rejecting = false;
      }
    },

    // ── Approve / Reject Location Change ──
    async handleApproveLocation(req) {
      if (!confirm('Duyệt yêu cầu thay đổi vị trí này? Vị trí cụm sân sẽ được cập nhật ngay.')) return;
      this.processingLocationId = req.id;
      try {
        const res = await adminVenueClusterService.approveLocationChange(this.cluster.id, req.id);
        const idx = this.locationChangeRequests.findIndex((r) => r.id === req.id);
        if (idx !== -1) this.locationChangeRequests.splice(idx, 1, res.request);
        this.showMsg('Duyệt yêu cầu thành công. Vị trí đã được cập nhật.', 'msg-success');
        await this.loadDetail();
      } catch (err) {
        this.showMsg(err.message || 'Duyệt không thành công.', 'msg-error');
      } finally {
        this.processingLocationId = null;
      }
    },
    openRejectLocationModal(req) {
      this.rejectLocationTarget = req;
      this.rejectLocationReason = '';
      this.rejectLocationError = '';
    },
    closeRejectLocationModal() {
      this.rejectLocationTarget = null;
    },
    async handleRejectLocation() {
      this.rejectingLocation = true;
      this.rejectLocationError = '';
      try {
        const res = await adminVenueClusterService.rejectLocationChange(
          this.cluster.id,
          this.rejectLocationTarget.id,
          { status_reason: this.rejectLocationReason },
        );
        const idx = this.locationChangeRequests.findIndex((r) => r.id === this.rejectLocationTarget.id);
        if (idx !== -1) this.locationChangeRequests.splice(idx, 1, res.request);
        this.closeRejectLocationModal();
        this.showMsg('Đã từ chối yêu cầu vị trí.', 'msg-success');
      } catch (err) {
        this.rejectLocationError = err.message || 'Từ chối không thành công.';
      } finally {
        this.rejectingLocation = false;
      }
    },

    // ── Approve / Reject Unlock Requests ──
    async handleApproveUnlock(req) {
      if (!confirm('Duyệt yêu cầu mở khóa này? Cụm sân sẽ được kích hoạt lại ngay lập tức.')) return;
      this.processingUnlockId = req.id;
      try {
        const res = await adminVenueClusterService.approveUnlockRequest(this.cluster.id, req.id);
        const idx = this.unlockRequests.findIndex((r) => r.id === req.id);
        if (idx !== -1) this.unlockRequests.splice(idx, 1, res.data);
        this.showMsg('Duyệt mở khóa thành công. Cụm sân đã hoạt động trở lại.', 'msg-success');
        await this.loadDetail();
      } catch (err) {
        this.showMsg(err.message || 'Duyệt không thành công.', 'msg-error');
      } finally {
        this.processingUnlockId = null;
      }
    },
    openRejectUnlockModal(req) {
      this.rejectUnlockTarget = req;
      this.rejectUnlockReason = '';
      this.rejectUnlockError = '';
    },
    closeRejectUnlockModal() {
      this.rejectUnlockTarget = null;
    },
    async handleRejectUnlock() {
      this.rejectingUnlock = true;
      this.rejectUnlockError = '';
      try {
        const res = await adminVenueClusterService.rejectUnlockRequest(
          this.cluster.id,
          this.rejectUnlockTarget.id,
          { admin_note: this.rejectUnlockReason },
        );
        const idx = this.unlockRequests.findIndex((r) => r.id === this.rejectUnlockTarget.id);
        if (idx !== -1) this.unlockRequests.splice(idx, 1, res.data);
        this.closeRejectUnlockModal();
        this.showMsg('Đã từ chối yêu cầu mở khóa.', 'msg-success');
      } catch (err) {
        this.rejectUnlockError = err.message || 'Từ chối không thành công.';
      } finally {
        this.rejectingUnlock = false;
      }
    },

    // ── Helpers ──
    getLogActionDetails(action) {
      switch (action) {
        case 'venue_cluster.locked':
          return { label: 'Khóa cụm sân', type: 'lock' };
        case 'venue_cluster.unlocked':
          return { label: 'Mở khóa cụm sân', type: 'unlock' };
        case 'venue_cluster.amenities_updated':
          return { label: 'Cập nhật tiện ích cụm sân', type: 'update' };
        default:
          return { label: action, type: 'default' };
      }
    },
    statusLabel(status) {
      return { pending: 'Chờ duyệt', active: 'Hoạt động', locked: 'Đã khóa' }[status] || status;
    },
    approvalStatusLabel(status) {
      return { pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối', cancelled: 'Hủy' }[status] || status;
    },
    imageUrl(path) {
      if (!path || path.includes('default-home.jpg')) return '';
      if (/^https?:\/\//.test(path)) return path;
      return `/storage/${path}`;
    },
    hideBrokenImage(e) {
      if (e.target.parentElement) {
        e.target.parentElement.style.display = 'none';
      }
    },
    formatDate(val, showTime = true) {
      if (!val) return '—';
      const d = new Date(val);
      if (showTime) return d.toLocaleString('vi-VN');
      return d.toLocaleDateString('vi-VN');
    },
    formatCurrency(val) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
    },
    isOverdue(fee) {
      if (!fee.due_date || fee.status === 'paid') return false;
      return new Date(fee.due_date) < new Date();
    },
    showMsg(msg, type = 'msg-success') {
      clearTimeout(this.globalTimer);
      this.globalMsg = msg;
      this.globalMsgType = type;
      this.globalTimer = setTimeout(() => { this.globalMsg = ''; }, 3500);
    },
    formatFullAddress(cluster) {
      if (!cluster) return "";
      const parts = [
        cluster.address,
        cluster.ward,
        cluster.province
      ].filter(Boolean);
      return parts.join(', ') || '—';
    },
  },
};
</script>

<style scoped>
.avcd-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.card {
  background: var(--admin-surface, #fff);
  border-radius: 12px;
  border: 1px solid var(--admin-border);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  padding: 20px 24px;
}

/* Header */
.avcd-header { display: flex; flex-direction: column; gap: 12px; }
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #15803d;
  font-weight: 850;
  text-decoration: none;
  font-size: 14px;
  margin-bottom: 12px;
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  transition: opacity 0.15s;
}
.back-link:hover {
  opacity: 0.8;
  text-decoration: underline;
}
.avcd-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
}
.avcd-title { font-size: 22px; font-weight: 800; margin: 0; }
.avcd-sub { margin: 4px 0 0; font-size: 14px; color: rgba(15, 23, 42, 0.5); }
.avcd-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Tabs */
.avcd-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  padding: 14px 20px;
}
.tab-btn {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--admin-border);
  background: var(--sg-surface, #f8fafc);
  color: rgba(15, 23, 42, 0.6);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.18s;
}
.tab-btn.active {
  background: #0f172a;
  border-color: var(--admin-text);
  color: #fff;
}
.tab-btn:not(.active):hover { background: var(--admin-surface-muted); }

/* Card */
.avcd-card {}
.section-title {
  font-size: 16px;
  font-weight: 800;
  margin: 0 0 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--sg-border);
}

/* Layout 2 cột mới thay thế .info-layout-grid */
.info-flex-layout {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}
.info-main-col {
  flex: 1.6; /* Chiếm khoảng 62% */
  display: flex;
  flex-direction: column;
  background: var(--admin-surface, #fff);
  border-radius: 12px;
  border: 1px solid var(--admin-border);
  padding: 24px;
}
.info-side-col {
  flex: 1; /* Chiếm khoảng 38% */
  display: flex;
  flex-direction: column;
  gap: 20px;
}

@media (max-width: 900px) {
  .info-flex-layout {
    flex-direction: column;
    align-items: stretch;
  }
}

.section-title-inline {
  font-size: 16px;
  font-weight: 800;
  margin: 0;
  color: var(--sg-text, #0f172a);
}

.info-header-row-premium {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  margin-bottom: 14px;
}

.info-detail-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-detail-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}

.info-detail-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.info-detail-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-detail-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.info-detail-label {
  font-size: 11px;
  font-weight: 700;
  color: rgba(15, 23, 42, 0.4);
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

.info-detail-value {
  font-size: 14px;
  color: var(--admin-text);
  line-height: 1.4;
}

.info-detail-value.text-lg {
  font-size: 16px;
}

.muted-username {
  color: rgba(15, 23, 42, 0.4);
  font-size: 13px;
  margin-left: 6px;
  font-weight: 500;
}

/* Rating */
.rating-stars {
  display: flex;
  align-items: baseline;
  gap: 4px;
}
.rating-num {
  font-size: 16px;
  font-weight: 800;
  color: #eab308;
}
.rating-max {
  font-size: 12px;
  color: rgba(15, 23, 42, 0.45);
  font-weight: 600;
}
.rating-count {
  font-size: 13px;
  color: rgba(15, 23, 42, 0.45);
  margin-left: 6px;
}

/* Bản đồ */
.map-coord-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.coord-text {
  font-family: monospace;
}
.btn-map-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 700;
  color: #2563eb;
  text-decoration: none;
  background: #eff6ff;
  padding: 4px 10px;
  border-radius: 6px;
  transition: all 0.15s;
}
.btn-map-link:hover {
  background: #dbeafe;
  color: #1d4ed8;
}

/* Sidebar & Cards */
.side-card-title {
  font-size: 13px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: rgba(15, 23, 42, 0.55);
  margin: 0 0 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}

.side-item-group {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.side-info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.side-label {
  font-size: 11px;
  font-weight: 700;
  color: rgba(15, 23, 42, 0.4);
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

.side-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--admin-text);
}

.status-action-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 2px;
}

/* Tiện ích */
.amenities-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  padding-bottom: 8px;
}

.amenities-header .side-card-title {
  flex: 1;
  border-bottom: none;
  padding-bottom: 0;
  margin-bottom: 0;
}

.btn-edit-link {
  font-size: 12px;
  font-weight: 700;
  color: #2563eb;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: color 0.15s;
}

.btn-edit-link:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.amenities-card {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
}

.amenities-content {
  margin-top: 4px;
}

.amenity-chip-premium {
  display: inline-block;
  padding: 5px 12px;
  background: var(--admin-surface-muted);
  border-radius: 20px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--admin-text);
  border: 1px solid rgba(15, 23, 42, 0.05);
}

.empty-amenities {
  font-size: 13px;
  color: rgba(15, 23, 42, 0.4);
  font-style: italic;
}
.btn-remove-custom {
  border: none;
  background: none;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
  color: var(--admin-muted);
  padding: 0 2px;
  transition: color 0.15s;
}
.btn-remove-custom:hover {
  color: #ef4444;
}

/* Info grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.info-item { display: flex; flex-direction: column; gap: 4px; }
.full-width { grid-column: 1 / -1; }
.info-label { font-size: 12px; font-weight: 700; color: rgba(15, 23, 42, 0.4); text-transform: uppercase; letter-spacing: 0.5px; }
.info-value { font-size: 14px; color: var(--admin-text); }
.muted { color: rgba(15, 23, 42, 0.45); font-size: 13px; }
.lock-reason { color: #dc2626; font-weight: 600; }
.amenity-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.amenity-chip {
  padding: 4px 10px;
  background: var(--admin-surface-muted);
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--admin-text);
}
.map-link { margin-top: 18px; }

/* Status badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.4;
  border: 1px solid transparent;
}
.status-pending { background: #fef3c7; color: #92400e; }
.status-active  { background: #dcfce7; color: #166534; }
.status-locked  { background: #fee2e2; color: #991b1b; }
.status-approved { background: #dcfce7; color: #166534; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.status-cancelled { background: var(--admin-surface-muted); color: #6b7280; }

/* Custom Status badges for Venue Cluster status box */
.custom-status-badge {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  min-height: 38px !important;
  padding: 9px 13px !important;
  border-radius: 6px !important;
  font-size: 14px !important;
  font-weight: 700 !important;
  line-height: 1 !important;
  box-sizing: border-box !important;
  border: 1px solid transparent !important;
}
.custom-status-pending { background: #fffbeb !important; color: #b45309 !important; border: 1px solid #fde68a !important; }
.custom-status-active  { background: #f0fdf4 !important; color: #15803d !important; border: 1px solid #bbf7d0 !important; }
.custom-status-locked  { background: #fef2f2 !important; color: #b91c1c !important; border: 1px solid #fecaca !important; }
.custom-status-approved { background: #f0fdf4 !important; color: #15803d !important; border: 1px solid #bbf7d0 !important; }
.custom-status-rejected { background: #fef2f2 !important; color: #b91c1c !important; border: 1px solid #fecaca !important; }
.custom-status-cancelled { background: var(--admin-surface-muted) !important; color: var(--admin-faint) !important; border: 1px solid var(--admin-border) !important; }
.fee-paid { background: #dcfce7; color: #166534; }
.fee-unpaid, .fee-overdue { background: #fee2e2; color: #991b1b; }
.fee-partial { background: #fef3c7; color: #92400e; }

/* Lock Alert Banner Style */
.lock-alert-banner {
  display: flex;
  gap: 16px;
  background: #fef2f2 !important;
  border: 1px solid #fee2e2 !important;
  border-radius: 10px !important;
  padding: 16px 20px !important;
  margin-top: 20px;
  align-items: flex-start;
}
.lock-alert-icon {
  width: 40px;
  height: 40px;
  border-radius: 50% !important;
  background: #fee2e2 !important;
  color: #ef4444 !important;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.lock-alert-content {
  flex: 1;
}
.lock-alert-title {
  margin: 0 0 6px !important;
  font-size: 15px !important;
  font-weight: 700 !important;
  color: #991b1b !important;
}
.lock-alert-reason {
  margin: 0 0 12px !important;
  font-size: 13.5px !important;
  color: #b91c1c !important;
  line-height: 1.5 !important;
}
.lock-alert-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 24px;
  padding-top: 10px !important;
  border-top: 1px dashed rgba(239, 68, 68, 0.2) !important;
}
.lock-meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px !important;
  color: #7f1d1d !important;
}
.lock-meta-label {
  font-weight: 500 !important;
  opacity: 0.8;
}
.lock-meta-val {
  font-weight: 700 !important;
}

/* ── Fee Tab Redesign ── */
.fees-tab {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Empty state */
.fees-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  gap: 14px;
  background: var(--admin-surface, #fff);
  border-radius: 14px;
  border: 1px dashed var(--admin-border);
}
.fees-empty-icon {
  width: 72px;
  height: 72px;
  background: var(--admin-surface-muted);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--admin-faint);
}
.fees-empty-text {
  font-size: 14px;
  color: rgba(15,23,42,0.4);
  margin: 0;
  font-style: italic;
}

/* Summary stats grid */
.fees-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
@media (max-width: 860px) { .fees-summary-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .fees-summary-grid { grid-template-columns: 1fr; } }

.fees-stat-card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 20px;
  border-radius: 14px;
  border: 1px solid transparent;
  background: var(--admin-surface, #fff);
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  transition: transform 0.18s, box-shadow 0.18s;
  position: relative;
  overflow: hidden;
}
.fees-stat-card::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
}
.fees-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.fees-stat-total { border-color: #e0e7ff; }
.fees-stat-total::after { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
.fees-stat-total .fees-stat-icon { background: #eef2ff; color: #6366f1; }
.fees-stat-paid  { border-color: #bbf7d0; }
.fees-stat-paid::after  { background: linear-gradient(90deg, #22c55e, #16a34a); }
.fees-stat-paid  .fees-stat-icon { background: #dcfce7; color: #16a34a; }
.fees-stat-pending { border-color: #fed7aa; }
.fees-stat-pending::after { background: linear-gradient(90deg, #f97316, #ea580c); }
.fees-stat-pending .fees-stat-icon { background: #ffedd5; color: #ea580c; }
.fees-stat-overdue { border-color: #fecaca; }
.fees-stat-overdue::after { background: linear-gradient(90deg, #f87171, #dc2626); }
.fees-stat-overdue .fees-stat-icon { background: #fee2e2; color: #dc2626; }

.fees-stat-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 2px;
}
.fees-stat-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}
.fees-stat-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: rgba(15, 23, 42, 0.45);
  white-space: nowrap;
}
.fees-stat-value {
  font-size: 16px;
  font-weight: 800;
  color: var(--admin-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
  margin-top: 2px;
}
.fees-stat-value-green { color: #15803d; }
.fees-stat-value-orange { color: #c2410c; }
.fees-stat-value-red { color: #dc2626; font-size: 22px; }
.fees-stat-sub {
  font-size: 11px;
  color: rgba(15,23,42,0.35);
  font-weight: 500;
  margin-top: 2px;
}

/* Fee card wrap */
.fees-card-wrap {
  padding: 0;
  overflow: hidden;
}
.fees-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--sg-border);
  gap: 12px;
}
.fees-card-title-group {
  display: flex;
  align-items: center;
  gap: 14px;
}
.fees-card-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.fees-card-title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--admin-text);
  line-height: 1.2;
}
.fees-card-subtitle {
  margin: 2px 0 0;
  font-size: 12px;
  color: rgba(15,23,42,0.4);
  font-weight: 500;
}
.fees-count-badge {
  font-size: 12px;
  font-weight: 700;
  background: #0f172a;
  color: #fff;
  padding: 5px 14px;
  border-radius: 20px;
  white-space: nowrap;
}

/* Table header row */
.fee-table-header {
  display: grid;
  grid-template-columns: 160px 170px 110px 130px 130px 1fr 140px;
  gap: 0;
  padding: 10px 24px;
  background: var(--admin-surface-muted);
  border-bottom: 1px solid var(--sg-border);
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: rgba(15,23,42,0.4);
}

/* Fee items list */
.fee-items-list {
  display: flex;
  flex-direction: column;
}

.fee-row {
  display: grid;
  grid-template-columns: 160px 170px 110px 130px 130px 1fr 140px;
  gap: 0;
  padding: 16px 24px;
  border-bottom: 1px solid var(--sg-border);
  align-items: center;
  transition: background 0.15s;
}
.fee-row:last-child { border-bottom: none; }
.fee-row:hover { background: #fafbfc; }
.fee-row-overdue { background: linear-gradient(to right, #fff5f5, #fff); }
.fee-row-overdue:hover { background: linear-gradient(to right, #fef0f0, #fafbfc); }
.fee-row-paid { background: linear-gradient(to right, #f0fdf4, #fff); }
.fee-row-paid:hover { background: linear-gradient(to right, #e8fdf0, #fafbfc); }

/* Column: Gói */
.fee-col-pkg {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.fee-pkg-name {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: var(--admin-text);
}
.fee-pkg-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.dot-green { background: #22c55e; }
.dot-red   { background: #ef4444; }
.dot-amber { background: #f59e0b; }

.fee-court-count {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  color: rgba(15, 23, 42, 0.45);
  font-weight: 600;
  padding-left: 16px;
}

/* Column: Period */
.fee-col-period {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fee-period-text {
  font-size: 12px;
  color: var(--admin-faint);
  font-weight: 600;
}
.fee-period-sep {
  font-size: 10px;
  color: rgba(15,23,42,0.3);
  line-height: 1;
}

/* Column: Due date */
.fee-col-due {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.fee-due-date {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--admin-text);
}
.fee-due-date.text-red-600 { color: #dc2626; }
.fee-overdue-chip {
  display: inline-block;
  background: #fee2e2;
  color: #dc2626;
  font-size: 9.5px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  width: fit-content;
}

/* Column: Amount */
.fee-col-amount .fee-amount-num {
  font-size: 13px;
  font-weight: 800;
  color: var(--admin-text);
}

/* Column: Paid */
.fee-col-paid .fee-paid-num {
  font-size: 13px;
  font-weight: 700;
  color: var(--admin-muted);
}
.fee-col-paid .fee-paid-num.text-green { color: #16a34a; }

/* Column: Progress */
.fee-col-progress {}
.fee-progress-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.fee-progress-track {
  width: 100%;
  height: 5px;
  background: var(--admin-border);
  border-radius: 99px;
  overflow: hidden;
}
.fee-progress-bar {
  height: 100%;
  border-radius: 99px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.fee-progress-full    { background: linear-gradient(90deg, #22c55e, #16a34a); }
.fee-progress-partial { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.fee-progress-overdue { background: linear-gradient(90deg, #f87171, #dc2626); }
.fee-progress-pct {
  font-size: 10.5px;
  color: rgba(15, 23, 42, 0.4);
  font-weight: 600;
}

/* Column: Status */
.fee-col-status {
  display: flex;
  justify-content: flex-end;
}
.fee-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 700;
  padding: 5px 11px;
  border-radius: 8px;
  white-space: nowrap;
}
.fee-status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
.fee-status-paid    { background: #dcfce7; color: #15803d; }
.fee-status-paid    .fee-status-dot { background: #22c55e; }
.fee-status-unpaid  { background: #fef3c7; color: #92400e; }
.fee-status-unpaid  .fee-status-dot { background: #f59e0b; }
.fee-status-partial { background: #ffedd5; color: #9a3412; }
.fee-status-partial .fee-status-dot { background: #f97316; }
.fee-status-overdue { background: #fee2e2; color: #991b1b; }
.fee-status-overdue .fee-status-dot { background: #ef4444; }

/* Buttons */
.btn {
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.18s;
}
.btn-sm { padding: 6px 12px; font-size: 13px; }
.btn-outline { background: transparent; border-color: var(--sg-border); color: var(--admin-text); }
.btn-outline:hover { background: var(--admin-surface-muted); }
.btn-danger { background: #dc2626; color: #fff; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-success { background: #16a34a; color: #fff; }
.btn-success:hover:not(:disabled) { background: #15803d; }
.btn-success:disabled { opacity: 0.55; cursor: not-allowed; }

/* Tables */
.simple-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.simple-table th,
.simple-table td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--sg-border);
  text-align: left;
}
.simple-table th {
  background: var(--sg-surface, #f8fafc);
  font-weight: 700;
  font-size: 13px;
}
.fw-bold { font-weight: 700; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.mono { font-family: monospace; }
.overdue { color: #dc2626; font-weight: 700; }

/* Booking status */
.booking-status {
  display: inline-flex;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  background: var(--admin-surface-muted);
  color: var(--admin-text);
}
.bs-confirmed { background: #dcfce7; color: #166534; }
.bs-pending { background: #fef3c7; color: #92400e; }
.bs-cancelled { background: #fee2e2; color: #991b1b; }
.bs-completed { background: #e0f2fe; color: #0369a1; }

/* State box */
.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  gap: 14px;
  text-align: center;
  color: rgba(15, 23, 42, 0.5);
}
.error-box { color: #dc2626; background: #fef2f2; border-color: #fecaca; }
.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid rgba(0, 0, 0, 0.08);
  border-top-color: var(--admin-text);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.empty-section { padding: 40px 0; text-align: center; color: rgba(15, 23, 42, 0.4); font-size: 14px; }

/* Timeline Styling */
.timeline-container {
  position: relative;
  padding-left: 32px;
  margin: 10px 0;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Vertical line */
.timeline-container::before {
  content: '';
  position: absolute;
  left: 14px;
  top: 8px;
  bottom: 8px;
  width: 2px;
  background: var(--admin-border);
}

.timeline-item {
  position: relative;
  display: flex;
  align-items: flex-start;
}

/* Icon Badge */
.timeline-badge {
  position: absolute;
  left: -32px;
  transform: translateX(0);
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--admin-surface, #fff);
  border: 2px solid var(--admin-border);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1;
  transition: all 0.2s;
  box-shadow: 0 0 0 4px #fff;
}

.timeline-badge svg {
  width: 14px;
  height: 14px;
}

/* Colors for badge types */
.badge-lock {
  border-color: #fca5a5;
  background: #fee2e2;
  color: #dc2626;
}

.badge-unlock {
  border-color: #86efac;
  background: #dcfce7;
  color: #16a34a;
}

.badge-update {
  border-color: #93c5fd;
  background: #dbeafe;
  color: #2563eb;
}

.badge-default {
  border-color: var(--admin-border);
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
}

/* Timeline content card */
.timeline-content {
  flex: 1;
  background: var(--admin-surface, #fff);
  border: 1px solid var(--sg-border, var(--admin-border));
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  transition: all 0.2s ease-in-out;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.timeline-content:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
  border-color: var(--admin-border);
}

/* Header inside content card */
.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.timeline-action-label {
  font-weight: 700;
  font-size: 14px;
}

.text-lock { color: #dc2626; }
.text-unlock { color: #16a34a; }
.text-update { color: #2563eb; }
.text-default { color: var(--admin-faint); }

.timeline-meta {
  display: flex;
  align-items: center;
  gap: 16px;
  font-size: 13px;
  color: rgba(15, 23, 42, 0.45);
}

.timeline-actor, .timeline-time {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.timeline-actor svg, .timeline-time svg {
  color: rgba(15, 23, 42, 0.3);
}

/* Reason box stylings */
.timeline-reason-box {
  background: var(--admin-surface-muted);
  border-left: 3px solid var(--admin-border);
  padding: 8px 12px;
  border-radius: 0 6px 6px 0;
}

.reason-title {
  font-size: 11px;
  font-weight: 700;
  color: rgba(15, 23, 42, 0.4);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.reason-content {
  margin: 4px 0 0 0;
  font-size: 13px;
  color: var(--admin-text);
  line-height: 1.5;
  white-space: pre-wrap;
}

/* Sub-details (Lock dates) */
.timeline-sub-details {
  font-size: 13px;
  color: var(--admin-faint);
  display: flex;
  align-items: center;
}

.duration-label {
  display: inline-flex;
  align-items: center;
  background: var(--admin-surface-muted);
  padding: 4px 8px;
  border-radius: 6px;
}

/* Amenities updated stylings */
.timeline-amenities-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.amenities-title {
  font-size: 11px;
  font-weight: 700;
  color: rgba(15, 23, 42, 0.4);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.timeline-amenity-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.timeline-amenity-chip {
  font-size: 12px;
  font-weight: 600;
  background: var(--admin-surface-muted);
  color: var(--admin-text);
  padding: 4px 10px;
  border-radius: 20px;
  border: 1px solid rgba(15, 23, 42, 0.05);
}

.timeline-no-amenities {
  font-style: italic;
  font-size: 13px;
  color: rgba(15, 23, 42, 0.4);
}

/* Approvals */
.approval-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.tab-sm {
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid var(--admin-border);
  background: var(--admin-surface-muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.tab-sm.active { background: #0f172a; border-color: var(--admin-text); color: #fff; }
.approval-list { display: flex; flex-direction: column; gap: 12px; }
.approval-card {
  padding: 16px;
  border-radius: 10px;
  border: 1px solid var(--admin-border);
  background: var(--admin-surface-muted);
  transition: box-shadow 0.18s;
}
.approval-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
.approval-pending { border-left: 3px solid #f59e0b; }
.approval-approved { border-left: 3px solid #22c55e; }
.approval-rejected { border-left: 3px solid #ef4444; }
.approval-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.approval-right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.approval-btns { display: flex; gap: 8px; }
.approval-name { margin-bottom: 4px; }
.reason-text { font-size: 13px; color: #dc2626; font-style: italic; margin-top: 4px; }

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  display: grid;
  place-items: center;
  z-index: 600;
  padding: 20px;
}
.modal-box {
  width: min(520px, calc(100vw - 32px));
  padding: 0;
  overflow: hidden;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--sg-border);
}
.modal-header h3 { margin: 0; font-size: 18px; font-weight: 800; }
.btn-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: rgba(15, 23, 42, 0.4);
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: grid;
  place-items: center;
}
.btn-close:hover { background: var(--admin-surface-muted); }
.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--sg-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: var(--sg-surface, #f8fafc);
}
.form-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--admin-text);
}
.form-control {
  padding: 10px 14px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  outline: none;
  color: var(--admin-text);
  background: var(--admin-surface, #fff);
  transition: border-color 0.18s;
}
.form-control:focus { border-color: var(--admin-text); }
.required { color: #ef4444; }
.alert-error {
  padding: 10px 14px;
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}

/* Global alert */
.global-alert {
  position: fixed;
  bottom: 28px;
  right: 28px;
  padding: 14px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  z-index: 999;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}
.msg-success { background: #166534; color: #fff; }
.msg-error   { background: #dc2626; color: #fff; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.empty-gallery {
  padding: 24px;
  background: var(--admin-surface-muted);
  border: 1px dashed var(--sg-border);
  border-radius: 8px;
  text-align: center;
  color: rgba(15, 23, 42, 0.4);
  font-size: 13.5px;
  margin-top: 8px;
}

.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 14px;
  margin-top: 8px;
}
.gallery-item {
  aspect-ratio: 4 / 3;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  background: var(--admin-surface-muted);
  transition: transform 0.2s, box-shadow 0.2s;
}
.gallery-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.gallery-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.info-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}
.info-divider {
  border: 0;
  border-top: 1px solid var(--sg-border);
  margin: 0 0 20px;
}

@media (max-width: 640px) {
  .info-grid { grid-template-columns: 1fr; }
}

.tab-badge-admin {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: #f59e0b;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  margin-left: 5px;
}
</style>
