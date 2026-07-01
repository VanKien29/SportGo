<template>
    <div class="venue-clusters-container">


        <!-- Loading State -->
        <div v-if="loading" class="loading-state card">
            <div class="spinner"></div>
            <p>Đang tải danh sách cụm sân...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state card">
            <p class="error-message">{{ error }}</p>
            <button class="btn btn-outline" @click="fetchClusters">
                Thử lại
            </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="clusters.length === 0" class="empty-state card">
            <p>Bạn chưa sở hữu cụm sân nào trên hệ thống.</p>
        </div>

        <!-- Main Grid -->
        <div v-else class="clusters-grid">
            <!-- Cluster List Sidebar -->
            <div class="clusters-list card">
                <div
                    v-for="cluster in clusters"
                    :key="cluster.id"
                    class="cluster-item"
                    :class="{ active: selectedCluster?.id === cluster.id }"
                    @click="selectCluster(cluster)"
                >
                    <div class="cluster-info">
                        <h4 class="cluster-name">{{ cluster.name }}</h4>
                        <p class="cluster-address">
                            {{ formatFullAddress(cluster) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Cluster Detail with Tabs -->
            <div v-if="selectedCluster" class="cluster-detail">
                <!-- Tabs -->
                <div class="detail-tabs card" style="display: flex; justify-content: space-between; align-items: center; padding-right: 16px; position: relative;">
                    <div style="display: flex; gap: 8px;">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            class="tab-btn"
                            :class="{ active: activeTab === tab.key }"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                            <span
                                v-if="
                                    tab.key === 'approvals' &&
                                    pendingApprovalCount > 0
                                "
                                class="tab-badge"
                            >
                                {{ pendingApprovalCount }}
                            </span>
                            <span
                                v-if="
                                    tab.key === 'location' &&
                                    pendingLocationCount > 0
                                "
                                class="tab-badge tab-badge-location"
                            >
                                {{ pendingLocationCount }}
                            </span>
                            <span
                                v-if="
                                    tab.key === 'unlock' &&
                                    pendingUnlockCount > 0
                                "
                                class="tab-badge"
                                style="background-color: #dc2626;"
                            >
                                {{ pendingUnlockCount }}
                            </span>
                        </button>
                    </div>


                </div>

                <!-- ═══════════════════════════════════════════════════
                     TAB 1: THÔNG TIN CHUNG
                ═══════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'info'" class="cluster-edit card">

                    <div v-if="isClusterLocked" class="alert alert-danger" style="margin-bottom: 20px;">
                        Cụm sân này đang bị khóa. Bạn không thể cập nhật cấu hình cho đến khi cụm sân được mở khóa. Vui lòng chuyển sang tab <strong>Yêu cầu mở khóa</strong> để gửi giải trình.
                    </div>

                    <div class="readonly-detail-container">
                        <!-- Tên & Điện thoại -->
                        <div class="info-row-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div class="form-group-readonly">
                                <label class="info-label">Tên cụm sân</label>
                                <div class="info-value-text">{{ selectedCluster.name }}</div>
                            </div>
                            <div class="form-group-readonly">
                                <label class="info-label">Số điện thoại liên hệ</label>
                                <div class="info-value-text">{{ selectedCluster.phone_contact }}</div>
                            </div>
                        </div>

                        <!-- Vị trí hiện tại (chỉ đọc) -->
                        <div class="location-readonly-box" style="margin-bottom: 16px;">
                            <div class="location-readonly-header">
                                <div>
                                    <span class="location-readonly-title" style="font-weight:700;">Vị trí hiện tại</span>
                                    <span
                                        v-if="pendingLocationCount > 0"
                                        class="pending-location-badge"
                                    >
                                        ⏳ Đang có yêu cầu thay đổi chờ duyệt
                                    </span>
                                </div>
                            </div>
                            <div class="location-readonly-body" style="margin-top: 10px; display: flex; flex-direction: column; gap: 6px;">
                                <div class="location-info-row">
                                    <span class="location-label" style="font-weight:600;">Tỉnh/TP: </span>
                                    <span class="location-value">{{ selectedCluster.province || "—" }}</span>
                                </div>
                                <div class="location-info-row">
                                    <span class="location-label" style="font-weight:600;">Phường/Xã: </span>
                                    <span class="location-value">{{ selectedCluster.ward || "—" }}</span>
                                </div>
                                <div class="location-info-row">
                                    <span class="location-label" style="font-weight:600;">Địa chỉ: </span>
                                    <span class="location-value">{{ selectedCluster.address || "—" }}</span>
                                </div>
                            </div>
                            <!-- Bản đồ chỉ xem -->
                            <div
                                id="cluster-map"
                                class="map-container map-readonly"
                                style="margin-top: 12px;"
                            ></div>
                        </div>

                        <!-- Tiện ích cụm sân (Amenities) -->
                        <div class="amenities-management-section" style="margin-top: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label class="info-label" style="margin-bottom: 0;">Tiện ích cụm sân (Amenities)</label>
                                <span class="text-muted" style="font-size: 12px; font-style: italic;">Nhấp chọn để bật/tắt tiện ích. Nhấp vào bút chì để nhập mô tả.</span>
                            </div>
                            
                            <!-- Danh sách tất cả tiện ích hệ thống cung cấp -->
                            <div class="amenities-selector-grid" v-if="availableAmenities.length > 0" style="display: flex; flex-wrap: wrap; gap: 10px;">
                                <div
                                    v-for="item in availableAmenities"
                                    :key="item"
                                    class="amenity-select-tag"
                                    :class="{ active: form.amenities.includes(item) }"
                                    @click="toggleAmenity(item)"
                                >
                                    <span class="amenity-check-icon" v-if="form.amenities.includes(item)">
                                        <AppIcon name="check" size="12" />
                                    </span>
                                    <span class="amenity-name">{{ item }}</span>
                                    
                                    <!-- Nút sửa mô tả chỉ hiển thị khi tiện ích được chọn -->
                                    <button
                                        v-if="form.amenities.includes(item)"
                                        type="button"
                                        class="btn-edit-amenity-desc"
                                        @click.stop="openAmenityDescModal(item)"
                                        :title="form.amenity_descriptions[item] ? 'Sửa mô tả (đã có mô tả)' : 'Thêm mô tả'"
                                        :disabled="isClusterLocked"
                                    >
                                        <AppIcon name="pencil" size="12" />
                                        <span v-if="form.amenity_descriptions[item]" class="has-desc-dot"></span>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="text-muted" style="font-size: 13px; font-style: italic;">Không có tiện ích hệ thống nào khả dụng.</div>
                            
                            <!-- Nút Lưu cấu hình tiện ích -->
                            <div class="amenities-actions" style="margin-top: 16px; display: flex; align-items: center; gap: 12px;">
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    @click="handleUpdate"
                                    :disabled="updating || isClusterLocked"
                                >
                                    {{ updating ? "Đang lưu..." : "Lưu cấu hình tiện ích" }}
                                </button>
                                <span v-if="updateSuccess" class="text-success" style="font-size: 13.5px; font-weight: 600; color: var(--admin-primary, #000000);">Lưu cấu hình tiện ích thành công!</span>
                                <span v-if="updateError" class="text-danger" style="font-size: 13.5px; font-weight: 600; color: #dc2626;">{{ updateError }}</span>
                            </div>
                        </div>

                        <!-- Album ảnh (chỉ đọc) -->
                        <div class="form-group-readonly" style="margin-top: 20px;">
                            <label class="info-label">Hình ảnh cụm sân (Album/Gallery)</label>
                            <div
                                class="owner-gallery-grid"
                                v-if="imagesList.length > 0"
                                style="margin-top: 8px; margin-bottom: 0;"
                            >
                                <div
                                    v-for="img in imagesList"
                                    :key="img.id"
                                    class="owner-gallery-item"
                                >
                                    <img
                                        :src="imageUrl(img.file_path)"
                                        alt="Hình ảnh cụm sân"
                                        class="owner-gallery-img"
                                    />
                                </div>
                            </div>
                            <div v-else class="owner-gallery-empty" style="padding: 24px; border-radius: 8px; font-size: 13px; margin-top: 8px; text-align: center;">
                                Chưa có hình ảnh nào được tải lên cho cụm sân này.
                            </div>
                        </div>

                        <!-- Mô tả (chỉ đọc) -->
                        <div class="form-group-readonly" style="margin-top: 20px;">
                            <label class="info-label">Mô tả cụm sân</label>
                            <p class="info-description-text" style="margin-top: 8px;">{{ selectedCluster.description || 'Chưa có mô tả chi tiết.' }}</p>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════
                     TAB 2: SÂN CON
                ═══════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'courts'" class="courts-tab">
                    <!-- Tab court header -->
                    <div class="courts-header card">
                        <div class="courts-header-left">
                            <h3>Danh sách sân con ({{ courts.length }})</h3>
                            <p class="subtitle">
                                Quản lý các sân thi đấu chi tiết trong cụm sân
                            </p>
                        </div>
                        <div class="courts-header-actions">
                            <button
                                class="btn btn-outline"
                                @click="activeTab = 'approvals'"
                                :disabled="isClusterLocked"
                            >
                                <AppIcon name="plus" size="15" />
                                <span>Yêu cầu thêm sân mới</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="courtsLoading" class="loading-state card">
                        <div class="spinner"></div>
                        <p>Đang tải danh sách sân con...</p>
                    </div>
                    <div v-else-if="courtsError" class="error-state card">
                        <p class="error-message">{{ courtsError }}</p>
                    </div>
                    <div
                        v-else-if="courts.length === 0"
                        class="empty-state card"
                    >
                        <p>Cụm sân này chưa có sân con nào.</p>
                        <button
                            class="btn btn-primary"
                            @click="activeTab = 'approvals'"
                        >
                            Gửi yêu cầu thêm sân con
                        </button>
                    </div>
                    <div v-else class="view-content-wrapper">
                        <!-- Tabs Toggle -->
                        <div class="layout-toggle-tabs">
                            <button
                                class="tab-btn"
                                :class="{ active: courtView === 'list' }"
                                @click="courtView = 'list'"
                            >
                                <span>Danh sách sân con</span>
                            </button>
                            <button
                                class="tab-btn"
                                :class="{ active: courtView === 'layout' }"
                                @click="courtView = 'layout'"
                            >
                                <span>Sắp xếp sơ đồ trực quan</span>
                            </button>
                        </div>

                        <!-- List View -->
                        <div v-if="courtView === 'list'" class="courts-grid">
                            <div
                                v-for="court in courts"
                                :key="court.id"
                                class="court-card card"
                            >
                                <div class="court-header">
                                    <h3 class="court-name">{{ court.name }}</h3>
                                    <span
                                        class="status-badge"
                                        :class="court.status"
                                        >{{ formatStatus(court.status) }}</span
                                    >
                                </div>
                                <div class="court-body">
                                    <div class="info-row">
                                        <span class="label">Loại sân:</span>
                                        <span class="value">{{
                                            court.court_type?.name
                                        }}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Sơ đồ:</span>
                                        <span class="value">
                                            <span
                                                v-if="court.layout_x !== null"
                                                class="badge-placed"
                                                >Đã xếp ({{
                                                    formatToM(court.layout_x)
                                                }}m,
                                                {{
                                                    formatToM(court.layout_y)
                                                }}m)</span
                                            >
                                            <span v-else class="badge-unplaced"
                                                >Chưa xếp</span
                                            >
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Thứ tự:</span>
                                        <span class="value">{{
                                            court.sort_order
                                        }}</span>
                                    </div>
                                </div>
                                <div class="court-actions">
                                    <ActionIconButton
                                        icon="pencil"
                                        label="Sửa sân con"
                                        @click="openEditCourtModal(court)"
                                        :disabled="isClusterLocked"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Layout View -->
                        <div
                            v-else-if="courtView === 'layout'"
                            class="layout-editor-workspace"
                        >
                            <div class="editor-toolbar">
                                <div class="toolbar-left">

                                    <button
                                        class="btn btn-primary"
                                        @click="saveLayout"
                                        :disabled="savingLayout || isClusterLocked"
                                    >
                                        <span>{{
                                            savingLayout
                                                ? "Đang lưu..."
                                                : "Lưu sơ đồ"
                                        }}</span>
                                    </button>
                                    <button
                                        class="btn btn-outline"
                                        @click="autoArrange"
                                        :disabled="isClusterLocked"
                                    >
                                        <span>Tự động sắp xếp</span>
                                    </button>
                                    <button
                                        class="btn btn-outline btn-danger-outline"
                                        @click="clearLayout"
                                        :disabled="isClusterLocked"
                                    >
                                        <span>Xóa toàn bộ</span>
                                    </button>
                                </div>
                                <div class="toolbar-right">
                                    <span class="info-badge"
                                        >{{ editorTool === 'select' ? 'Chế độ Chọn — Click để chọn, kéo để di chuyển' : 'Chế độ Kéo — Kéo để di chuyển canvas' }}</span
                                    >
                                </div>
                            </div>
                            <div class="editor-body">
                                <div
                                    class="canvas-viewport"
                                    :class="[`tool-${editorTool}`, { panning: isPanning }]"
                                    ref="canvasViewport"
                                    @wheel.prevent="handleZoom"
                                    @mousedown="startPan"
                                    @mousemove="handleGlobalMove"
                                    @mouseup="handleGlobalUp"
                                    @mouseleave="handleGlobalUp"
                                    @click="onCanvasClick"
                                >


                                    <div class="zoom-controls">
                                        <button
                                            class="btn-zoom"
                                            @click.stop="setZoom(zoom - 0.1)"
                                            title="Thu nhỏ"
                                        >
                                            -
                                        </button>
                                        <span class="zoom-level"
                                            >{{ Math.round(zoom * 100) }}%</span
                                        >
                                        <button
                                            class="btn-zoom"
                                            @click.stop="setZoom(zoom + 0.1)"
                                            title="Phóng to"
                                        >
                                            +
                                        </button>
                                        <button
                                            class="btn-zoom fit"
                                            @click.stop="fitView"
                                            title="Căn giữa sơ đồ"
                                        >
                                            <span class="btn-icon">👁️</span> Căn
                                            giữa
                                        </button>
                                        <button
                                            class="btn-zoom reset"
                                            @click.stop="resetView"
                                            title="Đặt lại góc nhìn"
                                        >
                                            Reset
                                        </button>
                                    </div>
                                    <div
                                        class="canvas-content"
                                        :style="{
                                            transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
                                            transformOrigin: '0 0',
                                        }"
                                    >
                                        <div class="canvas-grid-bg"></div>

                                        <!-- Alignment Guidelines -->
                                        <div
                                            v-for="(xCoord, index) in activeGuidelines.x"
                                            :key="'gl-x-' + index"
                                            class="canvas-guideline vertical"
                                            :style="{ left: xCoord + 'px' }"
                                        ></div>
                                        <div
                                            v-for="(yCoord, index) in activeGuidelines.y"
                                            :key="'gl-y-' + index"
                                            class="canvas-guideline horizontal"
                                            :style="{ top: yCoord + 'px' }"
                                        ></div>
                                        <div
                                            v-for="court in placedCourts"
                                            :key="court.id"
                                            class="canvas-court-element"
                                            :class="{
                                                selected:
                                                    selectedCourtId ===
                                                    court.id,
                                                dragging:
                                                    draggingCourtId ===
                                                    court.id,
                                                resizing:
                                                    resizingCourtId ===
                                                    court.id,
                                                'has-collision':
                                                    collisions[court.id],
                                            }"
                                            :style="getCourtStyle(court)"
                                            @mousedown.stop="
                                                startDrag($event, court)
                                            "
                                            @click.stop="selectCourt(court)"
                                            data-type="court"
                                        >
                                            <CourtVisual
                                                :name="court.name"
                                                :court-type-name="
                                                    court.court_type?.name
                                                "
                                                status="active"
                                                :width="
                                                    court.layout_w ||
                                                    getDefaultWidth(court)
                                                "
                                                :height="
                                                    court.layout_h ||
                                                    getDefaultHeight(court)
                                                "
                                                :rotation="
                                                    court.layout_rotation || 0
                                                "
                                                :show-type="false"
                                            />
                                            <div
                                                v-if="collisions[court.id]"
                                                class="collision-badge"
                                                title="Sân đang bị chồng lấn!"
                                            >
                                                Chồng lấp
                                            </div>
                                            <template
                                                v-if="
                                                    selectedCourtId === court.id
                                                "
                                            >
                                                <div
                                                    class="resize-handle tl"
                                                    @mousedown.stop.prevent="
                                                        startResize(
                                                            $event,
                                                            court,
                                                            'tl',
                                                        )
                                                    "
                                                ></div>
                                                <div
                                                    class="resize-handle tr"
                                                    @mousedown.stop.prevent="
                                                        startResize(
                                                            $event,
                                                            court,
                                                            'tr',
                                                        )
                                                    "
                                                ></div>
                                                <div
                                                    class="resize-handle bl"
                                                    @mousedown.stop.prevent="
                                                        startResize(
                                                            $event,
                                                            court,
                                                            'bl',
                                                        )
                                                    "
                                                ></div>
                                                <div
                                                    class="resize-handle br"
                                                    @mousedown.stop.prevent="
                                                        startResize(
                                                            $event,
                                                            court,
                                                            'br',
                                                        )
                                                    "
                                                ></div>
                                            </template>
                                        </div>
                                        <div
                                            v-for="decor in decorations"
                                            :key="decor.id"
                                            class="canvas-decor-element"
                                            :class="{
                                                selected:
                                                    selectedDecorationId ===
                                                    decor.id,
                                                dragging:
                                                    draggingDecorationId ===
                                                    decor.id,
                                                resizing:
                                                    resizingDecorationId ===
                                                    decor.id,
                                            }"
                                            :style="getDecorStyle(decor)"
                                            @mousedown.stop="
                                                startDragDecor($event, decor)
                                            "
                                            @click.stop="selectDecor(decor)"
                                            data-type="decor"
                                        >
                                            <DecorationVisual
                                                :type="decor.type"
                                                :name="decor.name"
                                                :width="decor.layout_w"
                                                :height="decor.layout_h"
                                                :rotation="
                                                    decor.layout_rotation || 0
                                                "
                                            />
                                            <template
                                                v-if="
                                                    selectedDecorationId ===
                                                    decor.id
                                                "
                                            >
                                                <div
                                                    class="resize-handle tl"
                                                    @mousedown.stop.prevent="
                                                        startResizeDecor(
                                                            $event,
                                                            decor,
                                                            'tl',
                                                        )
                                                    "
                                                ></div>
                                                <div
                                                    class="resize-handle tr"
                                                    @mousedown.stop.prevent="
                                                        startResizeDecor(
                                                            $event,
                                                            decor,
                                                            'tr',
                                                        )
                                                    "
                                                ></div>
                                                <div
                                                    class="resize-handle bl"
                                                    @mousedown.stop.prevent="
                                                        startResizeDecor(
                                                            $event,
                                                            decor,
                                                            'bl',
                                                        )
                                                    "
                                                ></div>
                                                <div
                                                    class="resize-handle br"
                                                    @mousedown.stop.prevent="
                                                        startResizeDecor(
                                                            $event,
                                                            decor,
                                                            'br',
                                                        )
                                                    "
                                                ></div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div class="editor-sidebar">
                                    <!-- Inspector: Sân con -->
                                    <div
                                        v-if="selectedCourt"
                                        class="sidebar-section inspector-panel"
                                    >
                                        <h4 class="section-title">
                                            Thông tin: {{ selectedCourt.name }}
                                        </h4>
                                        <div
                                            v-if="collisions[selectedCourt.id]"
                                            class="inspector-warning-box"
                                        >
                                            Sân đang chồng lấn lên sân khác!
                                            Vui lòng dịch chuyển hoặc thay đổi
                                            kích thước để tránh va chạm.
                                        </div>
                                        <div class="inspector-fields">
                                            <div class="field-row">
                                                <span class="label"
                                                    >BỘ MÔN:</span
                                                ><span class="value">{{
                                                    selectedCourt.court_type
                                                        ?.name
                                                 }}</span>
                                            </div>
                                            <div class="field-group">
                                                <label>Kích thước (m):</label>
                                                <div class="input-row">
                                                    <input
                                                        type="number"
                                                        step="0.1"
                                                        :value="
                                                            formatToM(
                                                                selectedCourt.layout_w,
                                                            )
                                                        "
                                                        @input="
                                                            updateW(
                                                                selectedCourt,
                                                                $event.target
                                                                    .value,
                                                            )
                                                        "
                                                        placeholder="Ngang"
                                                    />
                                                    <span class="x">x</span>
                                                    <input
                                                        type="number"
                                                        step="0.1"
                                                        :value="
                                                            formatToM(
                                                                selectedCourt.layout_h,
                                                            )
                                                        "
                                                        @input="
                                                            updateH(
                                                                selectedCourt,
                                                                $event.target
                                                                    .value,
                                                            )
                                                        "
                                                        placeholder="Dọc"
                                                    />
                                                </div>
                                            </div>
                                            <div class="field-group">
                                                <label
                                                    >Vị trí cách lề Trái / Trên
                                                    (m):</label
                                                >
                                                <div class="input-row">
                                                    <input
                                                        type="number"
                                                        step="0.1"
                                                        :value="
                                                            formatToM(
                                                                selectedCourt.layout_x,
                                                            )
                                                        "
                                                        @input="
                                                            updateX(
                                                                selectedCourt,
                                                                $event.target
                                                                    .value,
                                                            )
                                                        "
                                                        placeholder="Trái (X)"
                                                    />
                                                    <span class="comma">,</span>
                                                    <input
                                                        type="number"
                                                        step="0.1"
                                                        :value="
                                                            formatToM(
                                                                selectedCourt.layout_y,
                                                            )
                                                        "
                                                        @input="
                                                            updateY(
                                                                selectedCourt,
                                                                $event.target
                                                                    .value,
                                                            )
                                                        "
                                                        placeholder="Trên (Y)"
                                                    />
                                                </div>
                                            </div>
                                            <div class="field-group">
                                                <label
                                                    >Góc xoay:
                                                    {{
                                                        selectedCourt.layout_rotation ||
                                                        0
                                                    }}°</label
                                                >
                                                <div class="rotation-control">
                                                    <input
                                                        type="range"
                                                        min="0"
                                                        max="359"
                                                        v-model.number="
                                                            selectedCourt.layout_rotation
                                                        "
                                                        class="rotation-slider"
                                                    />
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline btn-xs btn-rotate"
                                                        @click="
                                                            rotateSelected90
                                                        "
                                                    >
                                                        Xoay +90°
                                                    </button>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-outline btn-danger-outline btn-block"
                                                @click="
                                                    unplaceCourt(selectedCourt)
                                                "
                                            >
                                                Gỡ khỏi sơ đồ
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Inspector: Vật phẩm trang trí -->
                                    <div
                                        v-else-if="selectedDecoration"
                                        class="sidebar-section inspector-panel"
                                    >
                                        <h4 class="section-title">
                                            Vật phẩm: {{ selectedDecoration.name }}
                                        </h4>
                                        <div class="inspector-fields">
                                            <div class="field-row">
                                                <span class="label">LOẠI:</span>
                                                <span class="value font-bold uppercase">{{ selectedDecoration.type }}</span>
                                            </div>
                                            <div class="field-group">
                                                <label>Tên nhãn hiển thị:</label>
                                                <input
                                                    type="text"
                                                    v-model="selectedDecoration.name"
                                                    class="form-control"
                                                    placeholder="Nhãn hiển thị..."
                                                />
                                            </div>
                                            <div class="field-group">
                                                <label>Kích thước (px):</label>
                                                <div class="input-row">
                                                    <input
                                                        type="number"
                                                        v-model.number="selectedDecoration.layout_w"
                                                        placeholder="Rộng"
                                                        style="width: 70px;"
                                                    />
                                                    <span class="x">x</span>
                                                    <input
                                                        type="number"
                                                        v-model.number="selectedDecoration.layout_h"
                                                        placeholder="Dài"
                                                        style="width: 70px;"
                                                    />
                                                </div>
                                            </div>
                                            <div class="field-group">
                                                <label>Vị trí X / Y (px):</label>
                                                <div class="input-row">
                                                    <input
                                                        type="number"
                                                        v-model.number="selectedDecoration.layout_x"
                                                        placeholder="X"
                                                        style="width: 70px;"
                                                    />
                                                    <span class="comma">,</span>
                                                    <input
                                                        type="number"
                                                        v-model.number="selectedDecoration.layout_y"
                                                        placeholder="Y"
                                                        style="width: 70px;"
                                                     />
                                                </div>
                                            </div>
                                            <div class="field-group">
                                                <label>Góc xoay: {{ selectedDecoration.layout_rotation || 0 }}°</label>
                                                <div class="rotation-control">
                                                    <input
                                                        type="range"
                                                        min="0"
                                                        max="359"
                                                        v-model.number="selectedDecoration.layout_rotation"
                                                        class="rotation-slider"
                                                    />
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline btn-xs btn-rotate"
                                                        @click="rotateSelectedDecor90"
                                                    >
                                                        Xoay +90°
                                                    </button>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-outline btn-danger-outline btn-block"
                                                @click="deleteDecoration(selectedDecoration)"
                                            >
                                                Xóa khỏi sơ đồ
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Thư viện vật phẩm trang trí -->
                                    <div class="sidebar-section decoration-library-section">
                                        <h4 class="section-title">Thêm vật phẩm bổ trợ</h4>
                                        <p class="section-desc">Click để thêm các vật phẩm định vị không gian:</p>
                                        <div class="decor-library-grid">
                                            <button type="button" class="btn-add-decor" @click="addDecoration('entrance', 'Cửa ra vào')">
                                                Cửa ra vào
                                            </button>
                                            <button type="button" class="btn-add-decor" @click="addDecoration('reception', 'Lễ tân')">
                                                Quầy lễ tân
                                            </button>
                                            <button type="button" class="btn-add-decor" @click="addDecoration('restroom', 'WC')">
                                                Nhà vệ sinh
                                            </button>
                                            <button type="button" class="btn-add-decor" @click="addDecoration('seating', 'Ghế chờ')">
                                                Ghế ngồi chờ
                                            </button>
                                            <button type="button" class="btn-add-decor" @click="addDecoration('parking', 'Bãi đỗ xe')">
                                                Bãi đỗ xe
                                            </button>
                                            <button type="button" class="btn-add-decor" @click="addDecoration('custom', 'Khác')">
                                                Vật thể khác
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Sân chưa xếp -->
                                    <div
                                        class="sidebar-section unplaced-list-section"
                                    >
                                        <h4 class="section-title">
                                            Sân chưa xếp ({{
                                                unplacedCourts.length
                                            }})
                                        </h4>
                                        <p class="section-desc">
                                            Click vào sân để đưa vào bản đồ rồi
                                            kéo thả sắp xếp:
                                        </p>
                                        <div class="unplaced-items">
                                            <div
                                                v-for="court in unplacedCourts"
                                                :key="court.id"
                                                class="unplaced-court-item"
                                                @click="placeCourt(court)"
                                            >
                                                <div class="item-header">
                                                    <div class="item-name">
                                                        {{ court.name }}
                                                    </div>
                                                    <span class="item-add-hint"
                                                        >Xếp sân</span
                                                    >
                                                </div>
                                                <div class="item-type">
                                                    {{ court.court_type?.name }}
                                                </div>
                                            </div>
                                            <div
                                                v-if="
                                                    unplacedCourts.length === 0
                                                "
                                                class="empty-unplaced"
                                            >
                                                Đã xếp tất cả các sân vào sơ đồ.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Court Modal -->
                    <div
                        v-if="showEditCourtModal"
                        class="modal-backdrop"
                        @click.self="closeEditCourtModal"
                    >
                        <div class="modal card">
                            <div class="modal-header">
                                <h3>Cập nhật sân con</h3>
                                <button
                                    class="btn-close"
                                    @click="closeEditCourtModal"
                                >
                                    <AppIcon name="x" size="18" />
                                </button>
                            </div>
                            <form @submit.prevent="handleEditCourtSubmit">
                                <div class="modal-body">
                                    <div
                                        v-if="editCourtError"
                                        class="alert alert-danger"
                                    >
                                        {{ editCourtError }}
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-court-name"
                                            >Tên sân con
                                            <span class="required"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            id="edit-court-name"
                                            v-model="editCourtForm.name"
                                            type="text"
                                            class="form-control"
                                            required
                                        />
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-court-status"
                                            >Trạng thái sân
                                            <span class="required"
                                                >*</span
                                            ></label
                                        >
                                        <select
                                            id="edit-court-status"
                                            v-model="editCourtForm.status"
                                            class="form-control"
                                            required
                                        >
                                            <option value="active">
                                                Đang hoạt động
                                            </option>
                                            <option value="inactive">
                                                Tạm ngưng hoạt động
                                            </option>
                                            <option value="maintenance">
                                                Bảo trì
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-sort-order"
                                            >Thứ tự hiển thị</label
                                        >
                                        <input
                                            id="edit-sort-order"
                                            v-model.number="
                                                editCourtForm.sort_order
                                            "
                                            type="number"
                                            min="0"
                                            class="form-control"
                                        />
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button
                                        type="button"
                                        class="btn btn-outline"
                                        @click="closeEditCourtModal"
                                    >
                                        Hủy
                                    </button>
                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                        :disabled="editCourtSubmitting"
                                    >
                                        {{
                                            editCourtSubmitting
                                                ? "Đang lưu..."
                                                : "Lưu lại"
                                        }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════
                     TAB 3: YÊU CẦU QUY MÔ
                ═══════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'approvals'" class="approvals-tab">

                    <!-- Quy mô hiện tại -->
                    <div class="card current-scale-card">
                        <h4 class="card-section-title">Quy mô hiện tại</h4>
                        <div class="scale-summary-grid">
                            <div class="scale-stat-item">
                                <span class="scale-stat-label">Tổng số sân con:</span>
                                <span class="scale-stat-value">{{ courts.length }}</span>
                            </div>
                            <div class="scale-stat-item">
                                <span class="scale-stat-label">Chi tiết loại sân:</span>
                                <div class="scale-types-list" v-if="courtTypeStats.length > 0">
                                    <span v-for="stat in courtTypeStats" :key="stat.name" class="scale-type-tag">
                                        {{ stat.name }}: <strong>{{ stat.count }}</strong>
                                    </span>
                                </div>
                                <span class="scale-stat-value-empty" v-else>Chưa có sân con</span>
                            </div>
                        </div>
                    </div>

                    <!-- Lịch sử yêu cầu -->
                    <div class="card">
                        <div class="approval-list-header">
                            <h3 class="section-title">Lịch sử yêu cầu quy mô</h3>
                            <div class="approval-filter-tabs">
                                <button
                                    class="tab-sm"
                                    :class="{ active: approvalFilter === '' }"
                                    @click="setApprovalFilter('')"
                                >
                                    Tất cả
                                </button>
                                <button
                                    class="tab-sm"
                                    :class="{
                                        active: approvalFilter === 'pending',
                                    }"
                                    @click="setApprovalFilter('pending')"
                                >
                                    Chờ duyệt
                                </button>
                                <button
                                    class="tab-sm"
                                    :class="{
                                        active: approvalFilter === 'approved',
                                    }"
                                    @click="setApprovalFilter('approved')"
                                >
                                    Đã duyệt
                                </button>
                                <button
                                    class="tab-sm"
                                    :class="{
                                        active: approvalFilter === 'rejected',
                                    }"
                                    @click="setApprovalFilter('rejected')"
                                >
                                    Từ chối
                                </button>
                                <button
                                    class="tab-sm"
                                    :class="{
                                        active: approvalFilter === 'cancelled',
                                    }"
                                    @click="setApprovalFilter('cancelled')"
                                >
                                    Đã hủy
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="approvalsLoading"
                            class="loading-state"
                            style="padding: 40px 0"
                        >
                            <div class="spinner"></div>
                            <p>Đang tải...</p>
                        </div>
                        <div
                            v-else-if="filteredApprovals.length === 0"
                            class="empty-section"
                        >
                            Không có yêu cầu nào.
                        </div>
                        <div v-else class="approval-list">
                            <div
                                v-for="req in filteredApprovals"
                                :key="req.id"
                                class="approval-card"
                                :class="`approval-${req.status}`"
                            >
                                <div class="approval-row">
                                    <div class="approval-details">
                                        <div class="approval-name fw-bold">
                                            {{ req.name }}
                                        </div>
                                        <div class="approval-meta">
                                            Loại sân:
                                            {{ req.court_type?.name || "—" }}
                                        </div>
                                        <div class="approval-meta">
                                            Gửi lúc:
                                            {{ formatDate(req.created_at) }}
                                        </div>
                                        <div
                                            v-if="
                                                req.status_reason &&
                                                req.status === 'rejected'
                                            "
                                            class="approval-reason"
                                        >
                                            Lý do từ chối:
                                            {{ req.status_reason }}
                                        </div>
                                        <div
                                            v-if="req.evidence_image_url"
                                            class="approval-evidence"
                                        >
                                            <span class="approval-evidence-label"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:-2px;margin-right:3px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> Ảnh minh chứng:</span>
                                            <a :href="req.evidence_image_url" target="_blank" class="approval-evidence-link">
                                                <img :src="req.evidence_image_url" alt="Ảnh minh chứng" class="approval-evidence-thumb" />
                                            </a>
                                        </div>
                                        <div v-if="req.supplementary_documents?.length" class="supplement-documents">
                                            <span class="approval-evidence-label">Giấy tờ bổ sung:</span>
                                            <a
                                                v-for="doc in req.supplementary_documents"
                                                :key="doc.id || doc.file_path || doc.file_name"
                                                :href="doc.download_url"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                {{ doc.file_name || 'Tải file' }}
                                            </a>
                                        </div>
                                        <div
                                            v-if="
                                                req.reviewed_by &&
                                                req.reviewed_at
                                            "
                                            class="approval-meta"
                                        >
                                            Xử lý bởi:
                                            {{ req.reviewed_by?.full_name }} ·
                                            {{ formatDate(req.reviewed_at) }}
                                        </div>
                                    </div>
                                    <div class="approval-right">
                                        <span
                                            class="status-badge-approval"
                                            :class="`approval-status-${req.status}`"
                                        >
                                            {{
                                                approvalStatusLabel(req.status)
                                            }}
                                        </span>
                                        <button
                                            v-if="req.status === 'pending'"
                                            class="btn btn-outline btn-sm"
                                            :disabled="cancellingId === req.id"
                                            @click="handleCancelApproval(req)"
                                        >
                                            {{
                                                cancellingId === req.id
                                                    ? "..."
                                                    : "Hủy yêu cầu"
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>

                <!-- ═══════════════════════════════════════════════════
                     TAB 4: YÊU CẦU THAY ĐỔI VỊ TRÍ
                ═══════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'location'" class="location-tab">

                    <!-- Thông tin vị trí hiện tại -->
                    <div class="card location-current-card">
                        <h3 class="section-title">Vị trí hiện tại</h3>
                        <div class="location-current-grid">
                            <div class="location-info-row">
                                <span class="location-label">Tỉnh/TP:</span
                                ><span class="location-value">{{
                                    selectedCluster.province || "—"
                                }}</span>
                            </div>
                            <div class="location-info-row">
                                <span class="location-label">Phường/Xã:</span
                                ><span class="location-value">{{
                                    selectedCluster.ward || "—"
                                }}</span>
                            </div>
                            <div class="location-info-row">
                                <span class="location-label">Địa chỉ:</span
                                ><span class="location-value">{{
                                    selectedCluster.address || "—"
                                }}</span>
                            </div>
                        </div>

                    </div>

                    <!-- Lịch sử yêu cầu -->
                    <div class="card">
                        <div class="approval-list-header">
                            <h3 class="section-title">
                                Lịch sử yêu cầu thay đổi vị trí
                            </h3>
                            <div class="approval-filter-tabs">
                                <button
                                    class="tab-sm"
                                    :class="{ active: locationFilter === '' }"
                                    @click="setLocationFilter('')"
                                >
                                    Tất cả
                                </button>
                                <button
                                    class="tab-sm"
                                    :class="{
                                        active: locationFilter === 'pending',
                                    }"
                                    @click="setLocationFilter('pending')"
                                >
                                    Chờ duyệt
                                </button>
                                <button
                                    class="tab-sm"
                                    :class="{
                                        active: locationFilter === 'approved',
                                    }"
                                    @click="setLocationFilter('approved')"
                                >
                                    Đã duyệt
                                </button>
                                <button
                                    class="tab-sm"
                                    :class="{
                                        active: locationFilter === 'rejected',
                                    }"
                                    @click="setLocationFilter('rejected')"
                                >
                                    Từ chối
                                </button>
                                <button
                                    class="tab-sm"
                                    :class="{
                                        active: locationFilter === 'cancelled',
                                    }"
                                    @click="setLocationFilter('cancelled')"
                                >
                                    Đã hủy
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="locationLoading"
                            class="loading-state"
                            style="padding: 30px 0"
                        >
                            <div class="spinner"></div>
                            <p>Đang tải...</p>
                        </div>
                        <div
                            v-else-if="filteredLocationRequests.length === 0"
                            class="empty-section"
                        >
                            Không có yêu cầu nào.
                        </div>
                        <div v-else class="approval-list">
                            <div
                                v-for="req in filteredLocationRequests"
                                :key="req.id"
                                class="approval-card"
                                :class="`approval-${req.status}`"
                            >
                                <div class="approval-row">
                                    <div class="approval-details">
                                        <div class="approval-name fw-bold">
                                            Thay đổi vị trí
                                        </div>
                                        <div class="approval-meta">
                                            Địa chỉ mới:
                                            {{ req.new_address }},
                                            {{ req.new_ward }},
                                            {{ req.new_province }}
                                        </div>
                                        <div class="approval-meta">
                                            Tọa độ: {{ req.new_latitude }},
                                            {{ req.new_longitude }}
                                        </div>
                                        <div class="approval-meta">
                                            Lý do: {{ req.note }}
                                        </div>
                                        <div class="approval-meta">
                                            Gửi lúc:
                                            {{ formatDate(req.created_at) }}
                                        </div>
                                        <div
                                            v-if="
                                                req.status_reason &&
                                                req.status === 'rejected'
                                            "
                                            class="approval-reason"
                                        >
                                            Lý do từ chối:
                                            {{ req.status_reason }}
                                        </div>
                                        <div v-if="req.supplementary_documents?.length" class="supplement-documents">
                                            <span>Giấy tờ bổ sung:</span>
                                            <a
                                                v-for="doc in req.supplementary_documents"
                                                :key="doc.id || doc.file_path || doc.file_name"
                                                :href="doc.download_url"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                {{ doc.file_name || 'Tải file' }}
                                            </a>
                                        </div>
                                        <div
                                            v-if="
                                                req.reviewed_by &&
                                                req.reviewed_at
                                            "
                                            class="approval-meta"
                                        >
                                            Xử lý bởi:
                                            {{ req.reviewed_by?.full_name }} ·
                                            {{ formatDate(req.reviewed_at) }}
                                        </div>
                                    </div>
                                    <div class="approval-right">
                                        <span
                                            class="status-badge-approval"
                                            :class="`approval-status-${req.status}`"
                                        >
                                            {{
                                                approvalStatusLabel(req.status)
                                            }}
                                        </span>
                                        <button
                                            v-if="req.status === 'pending'"
                                            class="btn btn-outline btn-sm"
                                            :disabled="
                                                cancellingLocationId === req.id
                                            "
                                            @click="
                                                handleCancelLocationRequest(req)
                                            "
                                        >
                                            {{
                                                cancellingLocationId === req.id
                                                    ? "..."
                                                    : "Hủy yêu cầu"
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ═══════════════════════════════════════════════════
                     TAB 4: YÊU CẦU MỞ KHÓA
                ═══════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'unlock'" class="unlock-tab card">

                    <!-- Banner cảnh báo khóa -->
                    <div class="unlock-locked-banner">
                        <div class="banner-header">
                            <AppIcon name="lock" size="24" class="lock-icon-red" />
                            <div class="banner-text">
                                <h4>Cụm sân đang bị khóa</h4>
                                <p>
                                    Lý do: <strong>{{ selectedCluster.status_reason || 'Không có lý do chi tiết.' }}</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Đang có yêu cầu pending -->
                    <div v-if="pendingUnlockRequest" class="pending-alert-box">
                        <div class="alert-info-title">
                            <AppIcon name="info" size="18" />
                            <span>Yêu cầu mở khóa của bạn đang chờ Admin xử lý.</span>
                        </div>
                        <div class="pending-reason-preview">
                            <strong class="preview-label">Nội dung giải trình:</strong>
                            <p class="preview-content">{{ pendingUnlockRequest.reason }}</p>
                        </div>
                        <button class="btn btn-outline btn-danger-outline" type="button" :disabled="unlockSubmitting" @click="handleCancelUnlock(pendingUnlockRequest.id)">
                            {{ unlockSubmitting ? 'Đang hủy...' : 'Hủy yêu cầu này' }}
                        </button>
                    </div>

                    <!-- Chưa có yêu cầu pending -->
                    <div v-else class="new-appeal-form">
                        <h4 class="form-section-title">Gửi yêu cầu giải trình & mở khóa</h4>
                        <p class="form-section-desc">
                            Hãy cung cấp thông tin giải trình chi tiết về lý do vi phạm hoặc các biện pháp khắc phục để Admin phê duyệt mở khóa cụm sân.
                        </p>
                        <form @submit.prevent="handleUnlockSubmit">
                            <div class="form-group">
                                <label class="field-label-bold">
                                    Nội dung giải trình <span class="required">*</span>
                                </label>
                                <textarea
                                    v-model.trim="unlockForm.reason"
                                    rows="5"
                                    maxlength="2000"
                                    placeholder="Nhập nội dung giải trình của bạn (tối thiểu 10 ký tự)..."
                                    class="form-control text-area-appeal"
                                    required
                                ></textarea>
                                <small class="char-counter">{{ unlockForm.reason.length }}/2000 ký tự</small>
                            </div>

                            <div v-if="unlockError" class="alert alert-danger">{{ unlockError }}</div>
                            <div v-if="unlockSuccess" class="alert alert-success">{{ unlockSuccess }}</div>

                            <button class="btn btn-primary" type="submit" :disabled="unlockSubmitting || !unlockForm.reason || unlockForm.reason.length < 10">
                                {{ unlockSubmitting ? 'Đang gửi...' : 'Gửi yêu cầu giải trình' }}
                            </button>
                        </form>
                    </div>

                    <!-- Lịch sử yêu cầu -->
                    <div class="history-section">
                        <div class="history-header">
                            <h4 class="history-title">Lịch sử yêu cầu mở khóa</h4>
                            <button class="btn btn-outline btn-sm" :disabled="loadingUnlockRequests" @click="fetchUnlockRequests(selectedCluster.id)">
                                Tải lại
                            </button>
                        </div>

                        <div v-if="loadingUnlockRequests" class="loading-state" style="padding: 30px 0; text-align: center;">
                            <div class="spinner"></div>
                            <p>Đang tải lịch sử...</p>
                        </div>
                        <div v-else-if="unlockRequests.length === 0" class="empty-state-text">
                            Chưa có yêu cầu mở khóa nào được gửi cho cụm sân này.
                        </div>
                        <div v-else class="table-scroll border-rounded">
                            <table class="unlock-history-table">
                                <thead>
                                    <tr>
                                        <th class="col-code">Mã yêu cầu</th>
                                        <th class="col-time">Thời gian gửi</th>
                                        <th class="col-reason">Lý do giải trình</th>
                                        <th class="col-status">Trạng thái</th>
                                        <th class="col-response">Phản hồi Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="req in unlockRequests" :key="req.id" class="history-row">
                                        <td class="cell-code">{{ shortId(req.id) }}</td>
                                        <td class="cell-time">{{ formatDateTime(req.created_at) }}</td>
                                        <td class="cell-reason">{{ req.reason }}</td>
                                        <td class="cell-status">
                                            <span class="status-badge" :class="req.status">
                                                {{ statusLabel(req.status) }}
                                            </span>
                                        </td>
                                        <td class="cell-response">{{ req.admin_note || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal: Request Amenity -->
        <div
            v-if="showRequestModal"
            class="modal-backdrop"
            @click.self="closeRequestModal"
        >
            <div class="modal card">
                <div class="modal-header">
                    <h3>Gửi yêu cầu thêm tiện ích</h3>
                    <button class="btn-close" @click="closeRequestModal">
                        <AppIcon name="x" size="18" />
                    </button>
                </div>
                <form @submit.prevent="handleRequestSubmit">
                    <div class="modal-body">
                        <div v-if="requestError" class="alert alert-danger">
                            {{ requestError }}
                        </div>
                        <div
                            v-if="requestSuccessMsg"
                            class="alert alert-success"
                        >
                            {{ requestSuccessMsg }}
                        </div>
                        <div class="form-group">
                            <label for="req-name"
                                >Tên tiện ích
                                <span class="required">*</span></label
                            >
                            <input
                                id="req-name"
                                v-model="requestForm.name"
                                type="text"
                                class="form-control"
                                placeholder="Ví dụ: Máy bắn cầu tự động..."
                                required
                            />
                        </div>
                        <div class="form-group">
                            <label for="req-description">Mô tả tiện ích</label>
                            <textarea
                                id="req-description"
                                v-model="requestForm.description"
                                class="form-control"
                                placeholder="Nhập mô tả chi tiết của tiện ích..."
                                rows="3"
                            ></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline"
                            @click="closeRequestModal"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="requestSubmitting"
                        >
                            {{
                                requestSubmitting
                                    ? "Đang gửi..."
                                    : "Gửi yêu cầu"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Gửi yêu cầu mở rộng quy mô -->
        <div
            v-if="showCreateApprovalModal"
            class="modal-backdrop"
            @click.self="closeCreateApprovalModal"
        >
            <div class="modal card">
                <div class="modal-header">
                    <h3>Gửi yêu cầu mở rộng quy mô</h3>
                    <button class="btn-close" @click="closeCreateApprovalModal">
                        <AppIcon name="x" size="18" />
                    </button>
                </div>
                <form @submit.prevent="handleCreateApproval">
                    <div class="modal-body">
                        <div v-if="newReqError" class="alert alert-danger">
                            {{ newReqError }}
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label
                                    >Loại sân đề xuất
                                    <span class="required">*</span></label
                                >
                                <div class="custom-select-wrapper">
                                    <div
                                        class="custom-select-trigger"
                                        :class="{
                                            active: showTypeDropdown,
                                        }"
                                        @click.stop="
                                            showTypeDropdown =
                                                !showTypeDropdown
                                        "
                                    >
                                        <span v-if="selectedReqCourtType">
                                            <span class="parent-name">{{
                                                getParentTypeName(
                                                    selectedReqCourtType,
                                                )
                                            }}</span>
                                            <span class="separator">/</span>
                                            <span class="child-name">{{
                                                selectedReqCourtType.name
                                            }}</span>
                                        </span>
                                        <span v-else class="placeholder"
                                            >-- Chọn loại sân --</span
                                        >
                                        <span class="arrow">&#9662;</span>
                                    </div>
                                    <div
                                        v-if="showTypeDropdown"
                                        class="custom-options-container"
                                    >
                                        <div
                                            v-for="group in groupedCourtTypes"
                                            :key="group.id"
                                            class="custom-optgroup"
                                        >
                                            <div
                                                class="custom-optgroup-label"
                                            >
                                                {{ group.name }}
                                            </div>
                                            <div
                                                v-for="child in group.children"
                                                :key="child.id"
                                                class="custom-option"
                                                :class="{
                                                    selected:
                                                        newReqForm.court_type_id ===
                                                        child.id,
                                                }"
                                                @click="
                                                    selectReqCourtType(
                                                        child,
                                                    )
                                                "
                                            >
                                                <span class="option-text">{{
                                                    child.name
                                                }}</span>
                                                <span class="option-details"
                                                    >({{
                                                        child.player_count
                                                    }}
                                                    người)</span
                                                >
                                                <span
                                                    v-if="
                                                        newReqForm.court_type_id ===
                                                        child.id
                                                    "
                                                    class="check-mark"
                                                    >&#10003;</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label
                                    >Tên sân con đề xuất
                                    <span class="required">*</span></label
                                >
                                <input
                                    v-model="newReqForm.name"
                                    type="text"
                                    class="form-control"
                                    placeholder="Ví dụ: Sân số 5, Sân VIP..."
                                    required
                                />
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Ghi chú/Lý do mở rộng</label>
                            <textarea
                                v-model="newReqForm.note"
                                class="form-control"
                                rows="3"
                                placeholder="Mô tả lý do bạn muốn mở rộng thêm sân con..."
                            ></textarea>
                        </div>
                        <div class="form-group">
                            <label>Ảnh minh chứng <span class="text-muted">(không bắt buộc)</span></label>
                            <p class="section-desc" style="margin-top:0; margin-bottom: 8px; font-size: 12.5px;">
                                Gửi ảnh chụp thực tế sân (hỗ trợ: JPG, PNG, WebP — tối đa 5MB)
                            </p>
                            <div class="evidence-upload-area">
                                <div
                                    v-if="!evidencePreview"
                                    class="evidence-dropzone"
                                    @click="$refs.evidenceInput.click()"
                                    @dragover.prevent
                                    @drop.prevent="handleEvidenceDrop($event)"
                                >
                                    <div class="evidence-dropzone-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
                                    <div class="evidence-dropzone-text">Click hoặc kéo thả ảnh vào đây</div>
                                </div>
                                <div v-else class="evidence-preview-wrapper">
                                    <img :src="evidencePreview" alt="Ảnh minh chứng" class="evidence-preview-img" />
                                    <button type="button" class="btn-remove-evidence" @click="removeEvidence">
                                        <AppIcon name="x" size="12" />
                                    </button>
                                </div>
                                <input
                                    ref="evidenceInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    style="display:none"
                                    @change="handleEvidenceSelect"
                                />
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Giấy tờ bổ sung <span class="text-muted">(không bắt buộc)</span></label>
                            <p class="section-desc" style="margin-top:0; margin-bottom: 8px; font-size: 12.5px;">
                                Tải lên file PDF/DOC/DOCX hoặc ảnh liên quan đến yêu cầu mở rộng.
                            </p>
                            <input
                                ref="scaleSupplementInput"
                                type="file"
                                class="form-control"
                                multiple
                                accept=".pdf,.doc,.docx,image/jpeg,image/png,image/webp"
                                @change="handleScaleSupplementSelect"
                            />
                            <div v-if="scaleSupplementFiles.length" class="supplement-file-list">
                                <span v-for="file in scaleSupplementFiles" :key="file.name + file.size">{{ file.name }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline"
                            @click="closeCreateApprovalModal"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="creatingReq"
                        >
                            <AppIcon name="send" size="16" />
                            {{
                                creatingReq
                                    ? "Đang gửi..."
                                    : "Gửi yêu cầu"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Yêu cầu thay đổi vị trí -->
        <div
            v-if="showLocationModal"
            class="modal-backdrop"
            @click.self="closeLocationChangeModal"
        >
            <div class="modal card modal-location">
                <div class="modal-header">
                    <h3>Yêu cầu thay đổi vị trí cụm sân</h3>
                    <button class="btn-close" @click="closeLocationChangeModal">
                        <AppIcon name="x" size="18" />
                    </button>
                </div>
                <form @submit.prevent="handleLocationChangeSubmit">
                    <div class="modal-body">
                        <div
                            v-if="locationModalError"
                            class="alert alert-danger"
                        >
                            {{ locationModalError }}
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label
                                    >Tỉnh/Thành phố mới
                                    <span class="required">*</span></label
                                >
                                <div class="searchable-select-container">
                                    <input
                                        type="text"
                                        v-model="provinceSearch"
                                        class="form-control searchable-select-input"
                                        placeholder="Gõ để tìm Tỉnh/Thành..."
                                        required
                                        @focus="showProvinceDropdown = true"
                                        @blur="closeProvinceDropdown"
                                    />
                                    <span class="searchable-select-arrow" :class="{ open: showProvinceDropdown }">▼</span>
                                    <div v-if="showProvinceDropdown" class="searchable-select-dropdown">
                                        <div
                                            v-for="p in filteredProvinces"
                                            :key="p.code"
                                            class="searchable-select-option"
                                            :class="{ selected: p.name === locationForm.new_province }"
                                            @mousedown="selectProvince(p)"
                                        >
                                            {{ p.name }}
                                        </div>
                                        <div v-if="filteredProvinces.length === 0" class="searchable-select-option empty">
                                            Không tìm thấy kết quả
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label
                                    >Phường/Xã mới
                                    <span class="required">*</span></label
                                >
                                <div class="searchable-select-container">
                                    <input
                                        type="text"
                                        v-model="wardSearch"
                                        class="form-control searchable-select-input"
                                        placeholder="Gõ để tìm Phường/Xã..."
                                        required
                                        :disabled="!locationForm.new_province"
                                        @focus="showWardDropdown = true"
                                        @blur="closeWardDropdown"
                                    />
                                    <span class="searchable-select-arrow" :class="{ open: showWardDropdown }">▼</span>
                                    <div v-if="showWardDropdown" class="searchable-select-dropdown">
                                        <div
                                            v-for="w in filteredWards"
                                            :key="w.code"
                                            class="searchable-select-option"
                                            :class="{ selected: w.name === locationForm.new_ward }"
                                            @mousedown="selectWard(w)"
                                        >
                                            {{ w.name }}
                                        </div>
                                        <div v-if="filteredWards.length === 0" class="searchable-select-option empty">
                                            Không tìm thấy kết quả
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label
                                >Địa chỉ cụ thể mới
                                <span class="required">*</span></label
                            >
                            <input
                                v-model="locationForm.new_address"
                                type="text"
                                class="form-control"
                                placeholder="Số nhà, tên đường..."
                                required
                            />
                        </div>
                        <div class="form-group">
                            <label>Link Google Maps mới</label>
                            <div class="map-input-group">
                                <input
                                    v-model="locationForm.new_map_url"
                                    type="url"
                                    class="form-control"
                                    placeholder="https://maps.google.com/..."
                                />
                                <button
                                    type="button"
                                    class="btn btn-outline btn-extract"
                                    :disabled="resolvingLocationMap"
                                    @click="handleExtractLocationCoords"
                                >
                                    <AppIcon name="search" size="15" />
                                    {{
                                        resolvingLocationMap
                                            ? "Đang trích xuất..."
                                            : "Trích xuất tọa độ"
                                    }}
                                </button>
                            </div>
                            <p
                                v-if="locationMapMsg"
                                :class="[
                                    'map-extract-msg',
                                    locationMapMsg.type,
                                ]"
                            >
                                {{ locationMapMsg.text }}
                            </p>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label
                                    >Vĩ độ (Latitude) mới
                                    <span class="required">*</span></label
                                >
                                <input
                                    v-model.number="locationForm.new_latitude"
                                    type="number"
                                    step="0.0000001"
                                    class="form-control"
                                    required
                                />
                            </div>
                            <div class="form-group">
                                <label
                                    >Kinh độ (Longitude) mới
                                    <span class="required">*</span></label
                                >
                                <input
                                    v-model.number="locationForm.new_longitude"
                                    type="number"
                                    step="0.0000001"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Chọn vị trí mới trên bản đồ</label>
                            <p class="map-help-text">
                                Kéo marker hoặc click lên bản đồ để chọn vị trí
                                chính xác.
                            </p>
                            <div
                                id="location-change-modal-map"
                                class="map-container"
                            ></div>
                        </div>
                        <div class="form-group">
                            <label
                                >Lý do thay đổi vị trí
                                <span class="required">*</span></label
                            >
                            <textarea
                                v-model="locationForm.note"
                                class="form-control"
                                rows="3"
                                placeholder="Mô tả lý do bạn muốn đổi vị trí..."
                                required
                            ></textarea>
                        </div>
                        <div class="form-group">
                            <label>Giấy tờ bổ sung <span class="text-muted">(không bắt buộc)</span></label>
                            <p class="map-help-text">
                                Tải lên giấy tờ, hình ảnh hoặc xác nhận liên quan đến vị trí mới.
                            </p>
                            <input
                                ref="locationSupplementInput"
                                type="file"
                                class="form-control"
                                multiple
                                accept=".pdf,.doc,.docx,image/jpeg,image/png,image/webp"
                                @change="handleLocationSupplementSelect"
                            />
                            <div v-if="locationSupplementFiles.length" class="supplement-file-list">
                                <span v-for="file in locationSupplementFiles" :key="file.name + file.size">{{ file.name }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline"
                            @click="closeLocationChangeModal"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="locationSubmitting"
                        >
                            {{
                                locationSubmitting
                                    ? "Đang gửi..."
                                    : "Gửi yêu cầu"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Chỉnh sửa mô tả tiện ích -->
        <div
            v-if="showAmenityDescModal"
            class="modal-backdrop"
            @click.self="closeAmenityDescModal"
        >
            <div class="modal card modal-amenity-desc">
                <div class="modal-header">
                    <h3>Mô tả tiện ích: {{ editingAmenityName }}</h3>
                    <button class="btn-close" @click="closeAmenityDescModal">
                        <AppIcon name="x" size="18" />
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="temp-amenity-desc" class="form-label">Mô tả hiển thị cho khách hàng</label>
                        <textarea
                            id="temp-amenity-desc"
                            v-model="tempAmenityDesc"
                            class="form-control"
                            rows="4"
                            placeholder="Nhập mô tả cụ thể (ví dụ: Mật khẩu Wifi, vị trí bãi đỗ xe, có tính phí hay không...)"
                        ></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline"
                        @click="closeAmenityDescModal"
                    >
                        Hủy
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="saveAmenityDesc"
                    >
                        Đồng ý
                    </button>
                </div>
            </div>
        </div>
        <!-- Modal: Yêu cầu thêm loại sân mới -->
        <div
            v-if="showCourtTypeRequestModal"
            class="modal-backdrop"
            @click.self="closeCourtTypeRequestModal"
        >
            <div class="modal card">
                <div class="modal-header">
                    <h3>Yêu cầu thêm loại sân mới</h3>
                    <button class="btn-close" @click="closeCourtTypeRequestModal">
                        <AppIcon name="x" size="18" />
                    </button>
                </div>
                <form @submit.prevent="handleCourtTypeRequestSubmit">
                    <div class="modal-body">
                        <div v-if="courtTypeRequestError" class="alert alert-danger">
                            {{ courtTypeRequestError }}
                        </div>
                        <div v-if="courtTypeRequestSuccess" class="alert alert-success">
                            {{ courtTypeRequestSuccess }}
                        </div>
                        
                        <div class="form-group">
                            <label for="court-type-req-name">Tên loại sân đề xuất <span class="required">*</span></label>
                            <input
                                id="court-type-req-name"
                                v-model="courtTypeRequestForm.name"
                                type="text"
                                class="form-control"
                                placeholder="Ví dụ: Sân Pickleball đơn, Sân bóng đá 7 người..."
                                required
                            />
                        </div>

                        <div class="form-group">
                            <label>Nhóm bộ môn (Loại sân cha) <span class="required">*</span></label>
                            <div class="custom-select-wrapper req-parent-select">
                                <div
                                    class="custom-select-trigger"
                                    :class="{ active: showReqParentDropdown }"
                                    @click.stop="showReqParentDropdown = !showReqParentDropdown"
                                >
                                    <span v-if="selectedReqParentCourtType">
                                        {{ selectedReqParentCourtType.name }}
                                    </span>
                                    <span v-else class="placeholder">-- Chọn bộ môn --</span>
                                    <span class="arrow">&#9662;</span>
                                </div>
                                <div
                                    v-if="showReqParentDropdown"
                                    class="custom-options-container"
                                >
                                    <div
                                        v-for="parent in parentCourtTypes"
                                        :key="parent.id"
                                        class="custom-option"
                                        :class="{ selected: courtTypeRequestForm.parent_id === parent.id }"
                                        @click="selectReqParent(parent)"
                                    >
                                        {{ parent.name }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="court-type-req-players">Số người chơi đề xuất <span class="required">*</span></label>
                            <input
                                id="court-type-req-players"
                                v-model.number="courtTypeRequestForm.player_count"
                                type="number"
                                min="1"
                                class="form-control"
                                required
                            />
                        </div>

                        <!-- Kích thước sơ đồ đề xuất -->
                        <div class="form-group">
                            <label>Kích thước sơ đồ quy chuẩn đề xuất (m)</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input
                                    v-model.number="courtTypeRequestForm.default_layout_w"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="form-control"
                                    placeholder="Ngang (ví dụ: 6.1)"
                                    style="flex: 1; min-width: 0;"
                                />
                                <span class="text-muted">x</span>
                                <input
                                    v-model.number="courtTypeRequestForm.default_layout_h"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="form-control"
                                    placeholder="Dọc (ví dụ: 13.4)"
                                    style="flex: 1; min-width: 0;"
                                />
                            </div>
                            <small class="text-muted" style="margin-top: 4px; display: block;">
                                Chiều ngang và chiều dọc của loại sân (tính theo mét).
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="court-type-req-description">Mô tả/Lý do đề xuất</label>
                            <textarea
                                id="court-type-req-description"
                                v-model="courtTypeRequestForm.description"
                                class="form-control"
                                placeholder="Nhập mô tả hoặc lý do bạn cần thêm loại sân này..."
                                rows="3"
                            ></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline"
                            @click="closeCourtTypeRequestModal"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="courtTypeRequestSubmitting"
                        >
                            {{ courtTypeRequestSubmitting ? "Đang gửi..." : "Gửi yêu cầu" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Nut noi hanh dong cum san -->
        <ClusterActionFloating :is-locked="isClusterLocked" @action="triggerAction" />
    </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import ActionIconButton from "../../components/ActionIconButton.vue";
import CourtVisual from "../../components/CourtVisual.vue";
import DecorationVisual from "../../components/DecorationVisual.vue";
import FloatAddButton from "../../components/FloatAddButton.vue";
import ClusterActionFloating from "../../components/owner/ClusterActionFloating.vue";
import { venueClusterService } from "../../services/venueClusters";
import { amenityService } from "../../services/amenityService";
import { courtTypeService } from "../../services/courtTypes";
import { ownerUnlockRequestsService } from "../../services/ownerUnlockRequests";

export default {
    name: "OwnerVenueClusters",
    components: { AppIcon, ActionIconButton, CourtVisual, DecorationVisual, FloatAddButton, ClusterActionFloating },
    data() {
        return {
            // Cluster list
            clusters: [],
            selectedCluster: null,
            loading: true,
            error: null,
 
            // Tabs
            activeTab: "info",
            tabs: [
                { key: "info", label: "Thông tin chung" },
                { key: "approvals", label: "Yêu cầu quy mô" },
                { key: "location", label: "Yêu cầu vị trí" },
            ],
 
            // Info tab form
            updating: false,
            updateSuccess: false,
            updateError: null,
            resolvingMap: false,
            mapExtractMsg: null,
            availableAmenities: [],
            imagesList: [],
            uploadingImage: false,
            form: {
                name: "",
                phone_contact: "",
                province: "",
                ward: "",
                address: "",
                map_url: "",
                latitude: 21.0285,
                longitude: 105.8542,
                amenities: [],
                amenity_descriptions: {},
                description: "",
            },
            map: null,
            marker: null,
 
            // Amenity request modal
            showRequestModal: false,
            showAmenityDescModal: false,
            editingAmenityName: "",
            tempAmenityDesc: "",
            requestSubmitting: false,
            requestError: null,
            requestSuccessMsg: null,
            requestForm: { name: "", description: "" },

            // Court Type Requests
            showCourtTypeRequestModal: false,
            courtTypeRequestSubmitting: false,
            courtTypeRequestError: null,
            courtTypeRequestSuccess: null,
            showReqParentDropdown: false,
            selectedReqParentCourtType: null,
            courtTypeRequestForm: {
                name: "",
                parent_id: null,
                player_count: 2,
                description: "",
                default_layout_w: null,
                default_layout_h: null
            },
 
            // Courts tab
            courts: [],
            courtTypes: [],
            courtsLoading: false,
            courtsError: null,
            courtView: "list",
 
            // Edit court modal
            showEditCourtModal: false,
            editingCourtId: null,
            editCourtSubmitting: false,
            editCourtError: null,
            editCourtForm: { name: "", status: "active", sort_order: 0 },
 
            // Layout/Canvas
            decorations: [],
            selectedCourtId: null,
            selectedDecorationId: null,
            draggingCourtId: null,
            draggingDecorationId: null,
            dragStartX: 0,
            dragStartY: 0,
            savingLayout: false,
            resizingCourtId: null,
            resizingDecorationId: null,
            resizeDirection: "",
            resizeStartW: 0,
            resizeStartH: 0,
            resizeStartXCoord: 0,
            resizeStartYCoord: 0,
            panX: 0,
            panY: 0,
            zoom: 1,
            isPanning: false,
            panStartX: 0,
            panStartY: 0,
            editorTool: 'select',
            activeGuidelines: { x: [], y: [] },

            // Approvals tab
            approvalRequests: [],
            approvalFilter: "",
            approvalsLoading: false,
            newReqForm: { court_type_id: "", name: "", note: "" },
            evidenceFile: null,
            evidencePreview: null,
            scaleSupplementFiles: [],
            newReqSuccess: null,
            newReqError: null,
            creatingReq: false,
            cancellingId: null,
            showTypeDropdown: false,
            showCreateApprovalModal: false,

            // Location Change Requests
            locationRequests: [],
            locationFilter: "",
            locationLoading: false,
            cancellingLocationId: null,
            showLocationModal: false,
            locationSubmitting: false,
            locationModalError: null,
            locationSupplementFiles: [],
            resolvingLocationMap: false,
            locationMapMsg: null,
            locationForm: {
                new_province: "",
                new_ward: "",
                new_address: "",
                new_map_url: "",
                new_latitude: 21.0285,
                new_longitude: 105.8542,
                note: "",
            },
            locationMap: null,
            locationMarker: null,
            provincesList: [],
            wardsList: [],
            provinceSearch: "",
            wardSearch: "",
            showProvinceDropdown: false,
            showWardDropdown: false,

            // Unlock Requests State
            unlockRequests: [],
            loadingUnlockRequests: false,
            unlockForm: { reason: "" },
            unlockSubmitting: false,
            unlockError: "",
            unlockSuccess: "",
        };
    },

    computed: {
        isClusterLocked() {
            return this.selectedCluster && this.selectedCluster.status === 'locked';
        },
        tabs() {
            const list = [
                { key: "info", label: "Thông tin chung" },
                { key: "approvals", label: "Yêu cầu quy mô" },
                { key: "location", label: "Yêu cầu vị trí" },
            ];
            if (this.isClusterLocked) {
                list.push({ key: "unlock", label: "Yêu cầu mở khóa" });
            }
            return list;
        },
        parentCourtTypes() {
            if (!this.courtTypes) return [];
            return this.courtTypes.filter((t) => !t.parent_id);
        },
        pendingUnlockCount() {
            return this.unlockRequests.filter((r) => r.status === "pending").length;
        },
        pendingUnlockRequest() {
            return this.unlockRequests.find((r) => r.status === "pending") || null;
        },
        selectedDecoration() {
            return (
                this.decorations.find((d) => d.id === this.selectedDecorationId) || null
            );
        },
        selectedCourt() {
            return (
                this.courts.find((c) => c.id === this.selectedCourtId) || null
            );
        },
        placedCourts() {
            return this.courts.filter(
                (c) => c.layout_x !== null && c.layout_y !== null,
            );
        },
        unplacedCourts() {
            return this.courts.filter(
                (c) => c.layout_x === null || c.layout_y === null,
            );
        },
        collisions() {
            const collisionMap = {};
            const placed = this.placedCourts;
            for (let i = 0; i < placed.length; i++) {
                const polyA = this.getVertices(placed[i]);
                for (let j = i + 1; j < placed.length; j++) {
                    const polyB = this.getVertices(placed[j]);
                    if (this.polygonsIntersect(polyA, polyB)) {
                        collisionMap[placed[i].id] = true;
                        collisionMap[placed[j].id] = true;
                    }
                }
            }
            return collisionMap;
        },
        groupedCourtTypes() {
            const parents = this.courtTypes.filter((t) => !t.parent_id);
            return parents
                .map((p) => ({
                    id: p.id,
                    name: p.name,
                    children: this.courtTypes.filter(
                        (t) => t.parent_id === p.id,
                    ),
                }))
                .filter((g) => g.children.length > 0);
        },
        selectedReqCourtType() {
            return (
                this.courtTypes.find(
                    (t) => t.id === this.newReqForm.court_type_id,
                ) || null
            );
        },
        filteredApprovals() {
            if (!this.approvalFilter) return this.approvalRequests;
            return this.approvalRequests.filter(
                (r) => r.status === this.approvalFilter,
            );
        },
        pendingApprovalCount() {
            return this.approvalRequests.filter((r) => r.status === "pending")
                .length;
        },
        filteredLocationRequests() {
            if (!this.locationFilter) return this.locationRequests;
            return this.locationRequests.filter(
                (r) => r.status === this.locationFilter,
            );
        },
        pendingLocationCount() {
            return this.locationRequests.filter((r) => r.status === "pending")
                .length;
        },
        filteredProvinces() {
            const query = (this.provinceSearch || "").toLowerCase().trim();
            if (!query) return this.provincesList;
            return this.provincesList.filter(p => 
                p.name.toLowerCase().includes(query)
            );
        },
        filteredWards() {
            const query = (this.wardSearch || "").toLowerCase().trim();
            if (!query) return this.wardsList;
            return this.wardsList.filter(w => 
                w.name.toLowerCase().includes(query)
            );
        },
        courtTypeStats() {
            if (!this.courts) return [];
            const stats = {};
            this.courts.forEach((court) => {
                const name = court.court_type?.name || "Chưa phân loại";
                stats[name] = (stats[name] || 0) + 1;
            });
            return Object.entries(stats).map(([name, count]) => ({ name, count }));
        },
    },

    watch: {
        "form.latitude"() {
            this.updateMapMarker();
        },
        "form.longitude"() {
            this.updateMapMarker();
        },
        "locationForm.new_latitude"() {
            this.updateLocationModalMapMarker();
        },
        "locationForm.new_longitude"() {
            this.updateLocationModalMapMarker();
        },
        activeTab(newTab) {
            if (newTab === "courts" && this.selectedCluster) {
                this.fetchCourts(this.selectedCluster.id);
            }
            if (newTab === "approvals" && this.selectedCluster) {
                this.fetchApprovals(this.selectedCluster.id);
            }
            if (newTab === "location" && this.selectedCluster) {
                this.fetchLocationRequests(this.selectedCluster.id);
            }
            if (newTab === "unlock" && this.selectedCluster) {
                this.fetchUnlockRequests(this.selectedCluster.id);
            }
            if (newTab === "info") {
                this.$nextTick(() => this.initMap());
            }
        },
    },

    created() {
        this.fetchClusters();
        this.fetchAvailableAmenities();
        this.fetchProvinces();
        this.fetchCourtTypes();
    },

    mounted() {
        document.addEventListener("click", this.handleOutsideClick);
        window.addEventListener(
            "owner-cluster-changed",
            this.onOwnerClusterChanged,
        );
        window.addEventListener('keydown', this.handleCanvasKeydown);
    },

    beforeUnmount() {
        document.removeEventListener("click", this.handleOutsideClick);
        window.removeEventListener(
            "owner-cluster-changed",
            this.onOwnerClusterChanged,
        );
        window.removeEventListener('keydown', this.handleCanvasKeydown);
        this.destroyMap();
    },

    methods: {
        // ── Cluster list ──
        async fetchClusters() {
            this.loading = true;
            this.error = null;
            try {
                const res = await venueClusterService.getClusters();
                this.clusters = res.data || [];
                if (this.clusters.length > 0) {
                    this.selectCluster(this.clusters[0]);
                }
            } catch (err) {
                this.error = err.message || "Lỗi khi tải danh sách cụm sân.";
            } finally {
                this.loading = false;
            }
        },

        async fetchCourtTypes() {
            try {
                const res = await courtTypeService.getAll();
                this.courtTypes = res.data || [];
            } catch (err) {
                console.error("Lỗi khi tải danh mục bộ môn:", err.message);
            }
        },

        selectCluster(cluster) {
            this.selectedCluster = cluster;
            if (cluster && cluster.status === "locked") {
                this.activeTab = "unlock";
            } else {
                this.activeTab = "info";
            }
            localStorage.setItem("selected_cluster", cluster.id);
            window.dispatchEvent(
                new CustomEvent("owner-cluster-changed", { detail: cluster }),
            );
            this.updateSuccess = false;
            this.updateError = null;
            this.imagesList = (cluster.media || []).filter(
                (img) =>
                    img.file_path &&
                    !img.file_path.includes("default-home.jpg"),
            );
            const descriptions = {};
            if (Array.isArray(cluster.amenity_catalog)) {
                cluster.amenity_catalog.forEach(a => {
                    if (a.pivot) {
                        descriptions[a.name] = a.pivot.description || "";
                    }
                });
            }
            if (Array.isArray(this.availableAmenities)) {
                this.availableAmenities.forEach(name => {
                    if (descriptions[name] === undefined) {
                        descriptions[name] = "";
                    }
                });
            }

            this.form = {
                name: cluster.name,
                phone_contact: cluster.phone_contact || "",
                province: cluster.province || "",
                ward: cluster.ward || "",
                address: cluster.address,
                map_url: cluster.map_url || "",
                latitude: parseFloat(cluster.latitude || 21.0285),
                longitude: parseFloat(cluster.longitude || 105.8542),
                amenities: Array.isArray(cluster.amenities)
                    ? cluster.amenities
                    : [],
                amenity_descriptions: descriptions,
                description: cluster.description || "",
            };
            this.decorations = Array.isArray(cluster.layout_decorations)
                ? JSON.parse(JSON.stringify(cluster.layout_decorations))
                : [];
            this.selectedDecorationId = null;
            this.selectedCourtId = null;
            // Reset location requests khi đổi cluster
            this.locationRequests = [];
            this.locationFilter = "";
            // Reset unlock requests
            this.unlockRequests = [];
            this.unlockError = "";
            this.unlockSuccess = "";
            this.unlockForm.reason = "";
            // Load location requests ngay để hiển thị badge đúng
            this.fetchLocationRequests(cluster.id);
            this.showHamburgerMenu = false;
            this.showCourtTypeRequestModal = false;
            this.courtTypeRequestError = null;
            this.courtTypeRequestSuccess = null;
            this.$nextTick(() => this.initMap());
        },

        onOwnerClusterChanged(event) {
            const clusterId = event.detail?.id;
            if (
                !clusterId ||
                !this.selectedCluster ||
                String(clusterId) === String(this.selectedCluster.id)
            )
                return;
            const cluster = this.clusters.find(
                (c) => String(c.id) === String(clusterId),
            );
            if (cluster) this.selectCluster(cluster);
        },

        formatFullAddress(cluster) {
            if (!cluster) return "";
            return (
                [cluster.address, cluster.ward, cluster.province]
                    .filter(Boolean)
                    .join(", ") || "Chưa cấu hình địa chỉ"
            );
        },

        // ── Info Tab ──
        async handleUpdate() {
            this.updating = true;
            this.updateSuccess = false;
            this.updateError = null;
            try {
                const res = await venueClusterService.updateCluster(
                    this.selectedCluster.id,
                    this.form,
                );
                this.updateSuccess = true;
                const index = this.clusters.findIndex(
                    (c) => c.id === this.selectedCluster.id,
                );
                if (index !== -1) {
                    this.clusters[index] = {
                        ...this.clusters[index],
                        ...res.data,
                    };
                    this.selectedCluster = this.clusters[index];
                    window.dispatchEvent(
                        new CustomEvent("owner-cluster-changed", {
                            detail: this.selectedCluster,
                        }),
                    );
                }
            } catch (err) {
                this.updateError = err.message || "Lỗi khi cập nhật cụm sân.";
            } finally {
                this.updating = false;
            }
        },

        async fetchUnlockRequests(clusterId) {
            if (!clusterId) return;
            this.loadingUnlockRequests = true;
            this.unlockError = "";
            try {
                const response = await ownerUnlockRequestsService.list(clusterId);
                this.unlockRequests = response.data || [];
            } catch (err) {
                this.unlockError = err.message || "Không thể tải lịch sử yêu cầu mở khóa.";
            } finally {
                this.loadingUnlockRequests = false;
            }
        },

        async handleUnlockSubmit() {
            if (!this.selectedCluster || !this.unlockForm.reason) return;
            this.unlockSubmitting = true;
            this.unlockError = "";
            this.unlockSuccess = "";
            try {
                const response = await ownerUnlockRequestsService.create(this.selectedCluster.id, {
                    reason: this.unlockForm.reason,
                });
                this.unlockSuccess = response.message || "Gửi yêu cầu mở khóa thành công!";
                this.unlockForm.reason = "";
                await this.fetchUnlockRequests(this.selectedCluster.id);
            } catch (err) {
                this.unlockError = err.message || "Không thể gửi yêu cầu giải trình.";
            } finally {
                this.unlockSubmitting = false;
            }
        },

        async handleCancelUnlock(requestId) {
            if (!this.selectedCluster || !requestId) return;
            if (!confirm("Bạn có chắc chắn muốn hủy yêu cầu mở khóa này không?")) return;
            this.unlockSubmitting = true;
            this.unlockError = "";
            this.unlockSuccess = "";
            try {
                const response = await ownerUnlockRequestsService.cancel(this.selectedCluster.id, requestId);
                this.unlockSuccess = response.message || "Hủy yêu cầu mở khóa thành công!";
                await this.fetchUnlockRequests(this.selectedCluster.id);
            } catch (err) {
                this.unlockError = err.message || "Không thể hủy yêu cầu mở khóa.";
            } finally {
                this.unlockSubmitting = false;
            }
        },

        shortId(value) {
            return value ? String(value).slice(0, 8).toUpperCase() : "-";
        },

        formatDateTime(value) {
            if (!value) return "-";
            const d = new Date(value);
            return `${d.toLocaleDateString("vi-VN")} ${d.toLocaleTimeString("vi-VN", {
                hour: "2-digit",
                minute: "2-digit",
            })}`;
        },

        statusLabel(status) {
            return {
                pending: "Đang chờ duyệt",
                need_supplement: "Cần bổ sung",
                approved: "Đã chấp nhận",
                rejected: "Bị từ chối",
                cancelled: "Đã hủy",
            }[status] || status;
        },

        imageUrl(path) {
            if (!path || path.includes("default-home.jpg")) return "";
            if (/^https?:\/\//.test(path)) return path;
            return `/storage/${path}`;
        },

        async handleImageUpload(e) {
            const files = Array.from(e.target.files);
            if (files.length === 0) return;
            this.uploadingImage = true;
            this.updateError = null;
            try {
                for (const file of files) {
                    if (file.size > 5 * 1024 * 1024) {
                        alert(
                            `File ${file.name} vượt quá 5MB. Vui lòng chọn ảnh nhỏ hơn.`,
                        );
                        continue;
                    }
                    const formData = new FormData();
                    formData.append("image", file);
                    const res = await venueClusterService.uploadMedia(
                        this.selectedCluster.id,
                        formData,
                    );
                    this.imagesList.push(res.data);
                }
                this.selectedCluster.media = [...this.imagesList];
            } catch (err) {
                this.updateError = err.message || "Tải lên hình ảnh thất bại.";
            } finally {
                this.uploadingImage = false;
                e.target.value = "";
            }
        },

        async handleDeleteImage(mediaId) {
            if (!confirm("Bạn có chắc chắn muốn xóa hình ảnh này khỏi album?"))
                return;
            this.updateError = null;
            try {
                await venueClusterService.deleteMedia(
                    this.selectedCluster.id,
                    mediaId,
                );
                this.imagesList = this.imagesList.filter(
                    (img) => img.id !== mediaId,
                );
                this.selectedCluster.media = [...this.imagesList];
            } catch (err) {
                this.updateError = err.message || "Xóa hình ảnh thất bại.";
            }
        },

        async fetchAvailableAmenities() {
            try {
                const res = await amenityService.getAll(true);
                this.availableAmenities = (res.data || []).map((a) => a.name);
                if (this.form && this.form.amenity_descriptions) {
                    this.availableAmenities.forEach(name => {
                        if (this.form.amenity_descriptions[name] === undefined) {
                            this.form.amenity_descriptions[name] = "";
                        }
                    });
                }
            } catch (err) {
                console.error("Lỗi khi tải danh sách tiện ích:", err.message);
            }
        },

        openAmenityDescModal(amenityName) {
            this.editingAmenityName = amenityName;
            this.tempAmenityDesc = this.form.amenity_descriptions[amenityName] || "";
            this.showAmenityDescModal = true;
        },
        closeAmenityDescModal() {
            this.showAmenityDescModal = false;
            this.editingAmenityName = "";
            this.tempAmenityDesc = "";
        },
        async saveAmenityDesc() {
            if (this.editingAmenityName) {
                this.form.amenity_descriptions[this.editingAmenityName] = this.tempAmenityDesc;
            }
            this.closeAmenityDescModal();
            await this.handleUpdate();
        },
        toggleAmenity(item) {
            if (this.isClusterLocked) return;
            this.updateSuccess = false;
            this.updateError = null;
            const index = this.form.amenities.indexOf(item);
            if (index === -1) {
                this.form.amenities.push(item);
                if (this.form.amenity_descriptions[item] === undefined) {
                    this.form.amenity_descriptions[item] = "";
                }
            } else {
                this.form.amenities.splice(index, 1);
            }
        },

        async fetchProvinces() {
            try {
                const res = await fetch("/api/locations/provinces").then((r) => r.json());
                this.provincesList = res.data || [];
            } catch (err) {
                console.error("Lỗi khi tải danh mục tỉnh thành:", err);
            }
        },

        async fetchWards(provinceCode) {
            if (!provinceCode) {
                this.wardsList = [];
                return;
            }
            try {
                const res = await fetch(`/api/locations/wards?province_code=${provinceCode}`).then((r) => r.json());
                this.wardsList = res.data || [];
            } catch (err) {
                console.error("Lỗi khi tải danh mục xã phường:", err);
                this.wardsList = [];
            }
        },

        async onProvinceChange() {
            const selectedProvinceName = this.locationForm.new_province;
            const province = this.provincesList.find(
                (p) => p.name === selectedProvinceName,
            );
            this.locationForm.new_ward = "";
            this.wardSearch = "";
            if (province) {
                await this.fetchWards(province.code);
            } else {
                this.wardsList = [];
            }
        },

        closeProvinceDropdown() {
            setTimeout(() => {
                this.showProvinceDropdown = false;
                this.provinceSearch = this.locationForm.new_province;
            }, 200);
        },

        closeWardDropdown() {
            setTimeout(() => {
                this.showWardDropdown = false;
                this.wardSearch = this.locationForm.new_ward;
            }, 200);
        },

        selectProvince(province) {
            this.locationForm.new_province = province.name;
            this.provinceSearch = province.name;
            this.showProvinceDropdown = false;
            this.onProvinceChange();
        },

        selectWard(ward) {
            this.locationForm.new_ward = ward.name;
            this.wardSearch = ward.name;
            this.showWardDropdown = false;
        },

        openRequestModal() {
            this.showRequestModal = true;
            this.requestError = null;
            this.requestSuccessMsg = null;
            this.requestForm = { name: "", description: "" };
        },

        closeRequestModal() {
            this.showRequestModal = false;
        },

        async handleRequestSubmit() {
            this.requestSubmitting = true;
            this.requestError = null;
            this.requestSuccessMsg = null;
            try {
                await amenityService.request(this.requestForm);
                this.requestSuccessMsg =
                    "Gửi yêu cầu thành công. Vui lòng chờ admin duyệt.";
                setTimeout(() => this.closeRequestModal(), 2000);
            } catch (err) {
                this.requestError = err.message || "Lỗi gửi yêu cầu.";
            } finally {
                this.requestSubmitting = false;
            }
        },

        // ── Map ──
        async handleExtractCoordinates() {
            if (!this.form.map_url) {
                alert("Vui lòng nhập đường link Google Maps trước.");
                return;
            }
            this.resolvingMap = true;
            this.mapExtractMsg = null;
            try {
                await this.parseCoordinatesFromMapUrl(this.form.map_url);
            } catch (e) {
                this.mapExtractMsg = {
                    type: "error",
                    text: "Không thể trích xuất tọa độ. Vui lòng thử link khác.",
                };
            } finally {
                this.resolvingMap = false;
            }
        },

        async parseCoordinatesFromMapUrl(url) {
            let targetUrl = url;
            if (
                url.includes("maps.app.goo.gl") ||
                url.includes("goo.gl/maps")
            ) {
                try {
                    const res = await venueClusterService.resolveMapUrl(url);
                    const d = res.data;
                    if (d?.latitude && d?.longitude) {
                        this.form.latitude = d.latitude;
                        this.form.longitude = d.longitude;
                        this.mapExtractMsg = {
                            type: "success",
                            text: `Trích xuất thành công: Vĩ độ ${d.latitude}, Kinh độ ${d.longitude}`,
                        };
                        return;
                    }
                    if (d?.final_url) targetUrl = d.final_url;
                } catch (e) {
                    this.mapExtractMsg = {
                        type: "error",
                        text: `Lỗi: ${e.message || "Không thể giải mã link rút gọn từ server."}`,
                    };
                    return;
                }
            }
            let match = targetUrl.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
            if (match) {
                this.form.latitude = parseFloat(match[1]);
                this.form.longitude = parseFloat(match[2]);
                this.mapExtractMsg = {
                    type: "success",
                    text: `Trích xuất thành công: Vĩ độ ${match[1]}, Kinh độ ${match[2]}`,
                };
                return;
            }
            match = targetUrl.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
            if (match) {
                this.form.latitude = parseFloat(match[1]);
                this.form.longitude = parseFloat(match[2]);
                this.mapExtractMsg = {
                    type: "success",
                    text: `Trích xuất thành công: Vĩ độ ${match[1]}, Kinh độ ${match[2]}`,
                };
                return;
            }
            this.mapExtractMsg = {
                type: "error",
                text: "Không tìm thấy tọa độ trong link này. Hãy thử link đầy đủ từ Google Maps desktop.",
            };
        },

        initMap() {
            if (!window.L) return;
            const container = document.getElementById("cluster-map");
            if (!container) return;
            if (this.map && this.map.getContainer() !== container)
                this.destroyMap();
            const lat = parseFloat(this.form.latitude) || 21.0285;
            const lng = parseFloat(this.form.longitude) || 105.8542;
            const DefaultIcon = window.L.icon({
                iconUrl:
                    "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png",
                shadowUrl:
                    "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png",
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            });
            window.L.Marker.prototype.options.icon = DefaultIcon;
            if (!this.map) {
                this.map = window.L.map("cluster-map", { scrollWheelZoom: false }).setView([lat, lng], 15);
                window.L.tileLayer(
                    "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
                    { attribution: "&copy; OpenStreetMap contributors" },
                ).addTo(this.map);
                this.marker = window.L.marker([lat, lng], {
                    draggable: false,
                }).addTo(this.map);
            } else {
                this.map.setView([lat, lng], 15);
                this.marker.setLatLng([lat, lng]);
            }
            setTimeout(() => {
                if (this.map) this.map.invalidateSize();
            }, 100);
        },

        updateMapMarker() {
            if (this.map && this.marker) {
                const lat = parseFloat(this.form.latitude);
                const lng = parseFloat(this.form.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const cur = this.marker.getLatLng();
                    if (
                        Math.abs(cur.lat - lat) > 0.00001 ||
                        Math.abs(cur.lng - lng) > 0.00001
                    ) {
                        this.marker.setLatLng([lat, lng]);
                        this.map.setView([lat, lng], this.map.getZoom());
                    }
                }
            }
        },

        destroyMap() {
            if (this.map) {
                this.map.remove();
                this.map = null;
                this.marker = null;
            }
        },

        initLocationModalMap() {
            if (!window.L) return;
            const container = document.getElementById(
                "location-change-modal-map",
            );
            if (!container) return;
            if (
                this.locationMap &&
                this.locationMap.getContainer() !== container
            )
                this.destroyLocationMap();
            const lat = parseFloat(this.locationForm.new_latitude) || 21.0285;
            const lng = parseFloat(this.locationForm.new_longitude) || 105.8542;
            const DefaultIcon = window.L.icon({
                iconUrl:
                    "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png",
                shadowUrl:
                    "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png",
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            });
            window.L.Marker.prototype.options.icon = DefaultIcon;
            if (!this.locationMap) {
                this.locationMap = window.L.map(
                    "location-change-modal-map",
                ).setView([lat, lng], 15);
                window.L.tileLayer(
                    "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
                    { attribution: "&copy; OpenStreetMap contributors" },
                ).addTo(this.locationMap);
                this.locationMarker = window.L.marker([lat, lng], {
                    draggable: true,
                }).addTo(this.locationMap);
                this.locationMarker.on("dragend", (e) => {
                    const p = e.target.getLatLng();
                    this.locationForm.new_latitude = parseFloat(
                        p.lat.toFixed(7),
                    );
                    this.locationForm.new_longitude = parseFloat(
                        p.lng.toFixed(7),
                    );
                });
                this.locationMap.on("click", (e) => {
                    const p = e.latlng;
                    this.locationMarker.setLatLng(p);
                    this.locationForm.new_latitude = parseFloat(
                        p.lat.toFixed(7),
                    );
                    this.locationForm.new_longitude = parseFloat(
                        p.lng.toFixed(7),
                    );
                });
            } else {
                this.locationMap.setView([lat, lng], 15);
                this.locationMarker.setLatLng([lat, lng]);
            }
            setTimeout(() => {
                if (this.locationMap) this.locationMap.invalidateSize();
            }, 100);
        },

        updateLocationModalMapMarker() {
            if (this.locationMap && this.locationMarker) {
                const lat = parseFloat(this.locationForm.new_latitude);
                const lng = parseFloat(this.locationForm.new_longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const cur = this.locationMarker.getLatLng();
                    if (
                        Math.abs(cur.lat - lat) > 0.00001 ||
                        Math.abs(cur.lng - lng) > 0.00001
                    ) {
                        this.locationMarker.setLatLng([lat, lng]);
                        this.locationMap.setView(
                            [lat, lng],
                            this.locationMap.getZoom(),
                        );
                    }
                }
            }
        },

        destroyLocationMap() {
            if (this.locationMap) {
                this.locationMap.remove();
                this.locationMap = null;
                this.locationMarker = null;
            }
        },

        // ── Courts Tab ──
        async fetchCourts(clusterId) {
            this.courtsLoading = true;
            this.courtsError = null;
            try {
                const [courtsRes, typesRes] = await Promise.all([
                    venueClusterService.getCourts(clusterId),
                    courtTypeService.getAll(),
                ]);
                this.courts = courtsRes.data || [];
                this.courtTypes = typesRes.data || [];
            } catch (err) {
                this.courtsError =
                    err.message || "Lỗi khi tải danh sách sân con.";
            } finally {
                this.courtsLoading = false;
            }
        },

        formatStatus(status) {
            return (
                {
                    active: "Đang hoạt động",
                    inactive: "Tạm khóa",
                    maintenance: "Bảo trì",
                }[status] || status
            );
        },

        openEditCourtModal(court) {
            this.editingCourtId = court.id;
            this.editCourtError = null;
            this.editCourtForm = {
                name: court.name,
                status: court.status,
                sort_order: court.sort_order,
            };
            this.showEditCourtModal = true;
        },

        closeEditCourtModal() {
            this.showEditCourtModal = false;
            this.editingCourtId = null;
        },

        async handleEditCourtSubmit() {
            this.editCourtSubmitting = true;
            this.editCourtError = null;
            try {
                await venueClusterService.updateCourt(
                    this.editingCourtId,
                    this.editCourtForm,
                );
                await this.fetchCourts(this.selectedCluster.id);
                this.closeEditCourtModal();
            } catch (err) {
                this.editCourtError = err.message || "Lỗi cập nhật sân con.";
            } finally {
                this.editCourtSubmitting = false;
            }
        },

        // ── Layout/Canvas ──
        formatToM(val) {
            if (val === null || val === undefined) return 0;
            return Math.round(val) / 100;
        },
        updateW(court, value) {
            const p = parseFloat(value);
            court.layout_w = isNaN(p) ? 0 : p * 100;
            this.validateSize(court);
        },
        updateH(court, value) {
            const p = parseFloat(value);
            court.layout_h = isNaN(p) ? 0 : p * 100;
            this.validateSize(court);
        },
        updateX(court, value) {
            const p = parseFloat(value);
            court.layout_x = isNaN(p) ? 0 : p * 100;
        },
        updateY(court, value) {
            const p = parseFloat(value);
            court.layout_y = isNaN(p) ? 0 : p * 100;
        },
        validateSize(court) {
            if (!court) return;
            if (court.layout_w < 10) court.layout_w = 10;
            if (court.layout_h < 10) court.layout_h = 10;
        },

        getDefaultWidth(court) {
            return court?.court_type?.default_layout_w || 800;
        },
        getDefaultHeight(court) {
            return court?.court_type?.default_layout_h || 800;
        },

        getCourtStyle(court) {
            return {
                left: `${court.layout_x}px`,
                top: `${court.layout_y}px`,
                width: `${court.layout_w || this.getDefaultWidth(court)}px`,
                height: `${court.layout_h || this.getDefaultHeight(court)}px`,
            };
        },

        getVertices(court) {
            const w = court.layout_w || this.getDefaultWidth(court);
            const h = court.layout_h || this.getDefaultHeight(court);
            const cx = (court.layout_x || 0) + w / 2;
            const cy = (court.layout_y || 0) + h / 2;
            const angle = ((court.layout_rotation || 0) * Math.PI) / 180;
            const cos = Math.cos(angle);
            const sin = Math.sin(angle);
            return [
                { x: -w / 2, y: -h / 2 },
                { x: w / 2, y: -h / 2 },
                { x: w / 2, y: h / 2 },
                { x: -w / 2, y: h / 2 },
            ].map((p) => ({
                x: cx + p.x * cos - p.y * sin,
                y: cy + p.x * sin + p.y * cos,
            }));
        },

        polygonsIntersect(polyA, polyB) {
            const getEdges = (poly) =>
                poly.map((p, i) => {
                    const q = poly[(i + 1) % poly.length];
                    return { x: q.x - p.x, y: q.y - p.y };
                });
            const project = (poly, axis) => {
                const len = Math.sqrt(axis.x * axis.x + axis.y * axis.y);
                const ax = axis.x / len;
                const ay = axis.y / len;
                let min = Infinity;
                let max = -Infinity;
                for (const p of poly) {
                    const dot = p.x * ax + p.y * ay;
                    if (dot < min) min = dot;
                    if (dot > max) max = dot;
                }
                return { min, max };
            };
            const axes = [
                ...getEdges(polyA).map((e) => ({ x: -e.y, y: e.x })),
                ...getEdges(polyB).map((e) => ({ x: -e.y, y: e.x })),
            ];
            for (const axis of axes) {
                const pA = project(polyA, axis);
                const pB = project(polyB, axis);
                if (pA.max <= pB.min + 0.1 || pB.max <= pA.min + 0.1)
                    return false;
            }
            return true;
        },

        checkCollisionWithOthers(target) {
            const polyA = this.getVertices(target);
            for (const c of this.placedCourts) {
                if (c.id === target.id) continue;
                if (this.polygonsIntersect(polyA, this.getVertices(c)))
                    return true;
            }
            return false;
        },

        placeCourt(court) {
            court.layout_w = this.getDefaultWidth(court);
            court.layout_h = this.getDefaultHeight(court);
            court.layout_rotation = 0;
            const rect = this.$refs.canvasViewport?.getBoundingClientRect();
            const cx = rect ? rect.width / 2 : 500;
            const cy = rect ? rect.height / 2 : 300;
            court.layout_x = (cx - this.panX) / this.zoom - court.layout_w / 2;
            court.layout_y = (cy - this.panY) / this.zoom - court.layout_h / 2;
            let attempts = 0;
            while (this.checkCollisionWithOthers(court) && attempts++ < 50) {
                court.layout_x += 30;
                court.layout_y += 30;
            }
            this.selectedCourtId = court.id;
        },

        unplaceCourt(court) {
            court.layout_x = null;
            court.layout_y = null;
            if (this.selectedCourtId === court.id) this.selectedCourtId = null;
        },

        rotateSelected90() {
            const court = this.selectedCourt;
            if (court)
                court.layout_rotation =
                    ((court.layout_rotation || 0) + 90) % 360;
        },

        selectCourt(court) {
            this.selectedCourtId = court.id;
        },

        getLogicalCoords(event) {
            const rect = this.$refs.canvasViewport?.getBoundingClientRect();
            if (!rect) return { x: 0, y: 0 };
            return {
                x: (event.clientX - rect.left - this.panX) / this.zoom,
                y: (event.clientY - rect.top - this.panY) / this.zoom,
            };
        },

        onCanvasClick(e) {
            // Nếu đang ở mode select và click vào nền trống → bỏ chọn
            if (this.editorTool === 'select') {
                const hitDecor  = e.target.closest('[data-type="decor"]');
                const hitCourt  = e.target.closest('[data-type="court"]');
                const hitZoom   = e.target.closest('.zoom-controls');
                const hitResize = e.target.closest('.resize-handle');
                if (!hitDecor && !hitCourt && !hitZoom && !hitResize) {
                    this.selectedCourtId = null;
                    this.selectedDecorationId = null;
                }
            }
        },

        handleCanvasKeydown(e) {
            // Bỏ qua nếu đang focus vào input/textarea
            const tag = document.activeElement?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

            // V → chế độ Select, H → chế độ Pan (giống Figma)
            if (e.key === 'v' || e.key === 'V') {
                this.editorTool = 'select';
                return;
            }
            if (e.key === 'h' || e.key === 'H') {
                this.editorTool = 'pan';
                return;
            }
            // Escape → bỏ chọn
            if (e.key === 'Escape') {
                this.selectedCourtId = null;
                this.selectedDecorationId = null;
                return;
            }
            // Delete / Backspace → xóa vật phẩm bổ trợ được chọn
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedDecorationId) {
                const decor = this.selectedDecoration;
                if (decor) this.deleteDecoration(decor);
                return;
            }

            // Phím mũi tên (Nudging) kiểu Figma
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown' || e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                e.preventDefault();
                const amount = e.shiftKey ? 10 : 1;
                let dx = 0;
                let dy = 0;
                if (e.key === 'ArrowLeft') dx = -amount;
                if (e.key === 'ArrowRight') dx = amount;
                if (e.key === 'ArrowUp') dy = -amount;
                if (e.key === 'ArrowDown') dy = amount;

                if (this.selectedCourtId) {
                    const court = this.courts.find(c => c.id === this.selectedCourtId);
                    if (court) {
                        court.layout_x = (court.layout_x || 0) + dx;
                        court.layout_y = (court.layout_y || 0) + dy;
                    }
                } else if (this.selectedDecorationId) {
                    const decor = this.decorations.find(d => d.id === this.selectedDecorationId);
                    if (decor) {
                        decor.layout_x = (decor.layout_x || 0) + dx;
                        decor.layout_y = (decor.layout_y || 0) + dy;
                    }
                }
            }
        },

        startPan(e) {
            // Zoom controls không bao giờ pan
            if (e.target.closest('.zoom-controls')) return;

            if (this.editorTool === 'pan') {
                // Chế độ Pan: kéo mọi nơi
                this.isPanning = true;
                this.panStartX = e.clientX - this.panX;
                this.panStartY = e.clientY - this.panY;
                return;
            }

            // Chế độ Select: chỉ pan khi click vào ĐÚNG nền trống (không phải court/decor)
            if (
                e.target.closest('[data-type="court"]') ||
                e.target.closest('[data-type="decor"]') ||
                e.target.closest('.resize-handle')
            ) return;

            // Kéo nền trống ở mode Select cũng cho pan (như Figma: Space+drag)
            this.isPanning = true;
            this.panStartX = e.clientX - this.panX;
            this.panStartY = e.clientY - this.panY;
        },

        handleGlobalMove(e) {
            if (this.isPanning) {
                this.panX = e.clientX - this.panStartX;
                this.panY = e.clientY - this.panStartY;
                return;
            }
            if (this.draggingCourtId || this.resizingCourtId) {
                this.handleDrag(e);
                return;
            }
            if (this.draggingDecorationId || this.resizingDecorationId) {
                this.handleDragDecor(e);
            }
        },

        handleGlobalUp() {
            this.isPanning = false;
            if (this.draggingCourtId || this.resizingCourtId) this.endDrag();
            if (this.draggingDecorationId || this.resizingDecorationId) this.endDragDecor();
        },

        handleZoom(e) {
            this.setZoom(
                this.zoom + (e.deltaY > 0 ? -0.1 : 0.1),
                e.clientX,
                e.clientY,
            );
        },

        setZoom(val, clientX = null, clientY = null) {
            const newZoom = Math.max(0.2, Math.min(3, val));
            if (newZoom === this.zoom) return;
            const rect = this.$refs.canvasViewport?.getBoundingClientRect();
            if (!rect) {
                this.zoom = newZoom;
                return;
            }
            const tx = clientX !== null ? clientX - rect.left : rect.width / 2;
            const ty = clientY !== null ? clientY - rect.top : rect.height / 2;
            const lx = (tx - this.panX) / this.zoom;
            const ly = (ty - this.panY) / this.zoom;
            this.zoom = newZoom;
            this.panX = tx - lx * this.zoom;
            this.panY = ty - ly * this.zoom;
        },

        resetView() {
            this.zoom = 1;
            this.panX = 0;
            this.panY = 0;
        },

        fitView() {
            const placed = this.placedCourts;
            if (!placed.length) {
                this.resetView();
                return;
            }
            let minX = Infinity,
                minY = Infinity,
                maxX = -Infinity,
                maxY = -Infinity;
            placed.forEach((c) => {
                this.getVertices(c).forEach((v) => {
                    if (v.x < minX) minX = v.x;
                    if (v.y < minY) minY = v.y;
                    if (v.x > maxX) maxX = v.x;
                    if (v.y > maxY) maxY = v.y;
                });
            });
            const padding = 60;
            const viewport = this.$refs.canvasViewport;
            if (!viewport) return;
            const zx = viewport.clientWidth / (maxX - minX + padding * 2);
            const zy = viewport.clientHeight / (maxY - minY + padding * 2);
            this.zoom = Math.max(0.35, Math.min(1.5, Math.min(zx, zy)));
            this.panX =
                viewport.clientWidth / 2 -
                (minX + (maxX - minX) / 2) * this.zoom;
            this.panY =
                viewport.clientHeight / 2 -
                (minY + (maxY - minY) / 2) * this.zoom;
        },

        startResize(event, court, direction) {
            this.resizingCourtId = court.id;
            this.resizeDirection = direction;
            const l = this.getLogicalCoords(event);
            this.dragStartX = l.x;
            this.dragStartY = l.y;
            this.resizeStartW = court.layout_w || this.getDefaultWidth(court);
            this.resizeStartH = court.layout_h || this.getDefaultHeight(court);
            this.resizeStartXCoord = court.layout_x || 0;
            this.resizeStartYCoord = court.layout_y || 0;
        },
        calculateGuidelines(type, draggingId, newX, newY, w, h) {
            // Ngưỡng snap tính theo pixel màn hình (ví dụ 8px), tự động điều chỉnh theo mức zoom
            const baseThreshold = 8;
            const snapThreshold = baseThreshold / (this.zoom || 1);
            
            const guidelinesX = [];
            const guidelinesY = [];
            const targets = [];
            
            this.courts.forEach(court => {
                if (court.layout_x !== null && court.layout_y !== null) {
                    if (type !== 'court' || court.id !== draggingId) {
                        targets.push({
                            id: court.id,
                            type: 'court',
                            x: court.layout_x,
                            y: court.layout_y,
                            w: court.layout_w || this.getDefaultWidth(court),
                            h: court.layout_h || this.getDefaultHeight(court)
                        });
                    }
                }
            });

            this.decorations.forEach(decor => {
                if (type !== 'decor' || decor.id !== draggingId) {
                    targets.push({
                        id: decor.id,
                        type: 'decor',
                        x: decor.layout_x,
                        y: decor.layout_y,
                        w: decor.layout_w || 100,
                        h: decor.layout_h || 100
                    });
                }
            });

            let bestSnapX = null;
            let minDeltaX = snapThreshold;

            let bestSnapY = null;
            let minDeltaY = snapThreshold;

            // Tìm snap X tốt nhất (khoảng cách nhỏ nhất)
            for (const target of targets) {
                const targetL = target.x;
                const targetR = target.x + target.w;
                const targetC = target.x + target.w / 2;

                const myL = newX;
                const myR = newX + w;
                const myC = newX + w / 2;

                const checks = [
                    { diff: Math.abs(myL - targetL), value: targetL, targetValue: targetL },
                    { diff: Math.abs(myL - targetR), value: targetR, targetValue: targetR },
                    { diff: Math.abs(myR - targetL), value: targetL - w, targetValue: targetL },
                    { diff: Math.abs(myR - targetR), value: targetR - w, targetValue: targetR },
                    { diff: Math.abs(myC - targetC), value: targetC - w / 2, targetValue: targetC }
                ];

                for (const check of checks) {
                    if (check.diff < minDeltaX) {
                        minDeltaX = check.diff;
                        bestSnapX = check;
                    }
                }
            }

            // Tìm snap Y tốt nhất (khoảng cách nhỏ nhất)
            for (const target of targets) {
                const targetT = target.y;
                const targetB = target.y + target.h;
                const targetC = target.y + target.h / 2;

                const myT = newY;
                const myB = newY + h;
                const myC = newY + h / 2;

                const checks = [
                    { diff: Math.abs(myT - targetT), value: targetT, targetValue: targetT },
                    { diff: Math.abs(myT - targetB), value: targetB, targetValue: targetB },
                    { diff: Math.abs(myB - targetT), value: targetT - h, targetValue: targetT },
                    { diff: Math.abs(myB - targetB), value: targetB - h, targetValue: targetB },
                    { diff: Math.abs(myC - targetC), value: targetC - h / 2, targetValue: targetC }
                ];

                for (const check of checks) {
                    if (check.diff < minDeltaY) {
                        minDeltaY = check.diff;
                        bestSnapY = check;
                    }
                }
            }

            // Thực hiện snap nếu tìm thấy điểm snap hợp lệ
            if (bestSnapX) {
                newX = bestSnapX.value;
                guidelinesX.push(bestSnapX.targetValue);
            }
            if (bestSnapY) {
                newY = bestSnapY.value;
                guidelinesY.push(bestSnapY.targetValue);
            }

            return {
                x: newX,
                y: newY,
                guidelinesX,
                guidelinesY
            };
        },
        startDrag(event, court) {
            this.draggingCourtId = court.id;
            this.selectedCourtId = court.id;
            const l = this.getLogicalCoords(event);
            this.dragStartX = l.x - (court.layout_x || 0);
            this.dragStartY = l.y - (court.layout_y || 0);
        },

        handleDrag(event) {
            if (this.resizingCourtId) {
                const court = this.courts.find(
                    (c) => c.id === this.resizingCourtId,
                );
                if (!court) return;
                const l = this.getLogicalCoords(event);
                const dx = l.x - this.dragStartX;
                const dy = l.y - this.dragStartY;
                if (this.resizeDirection === "br") {
                    court.layout_w = Math.max(30, this.resizeStartW + dx);
                    court.layout_h = Math.max(30, this.resizeStartH + dy);
                } else if (this.resizeDirection === "bl") {
                    const nw = this.resizeStartW - dx;
                    if (nw >= 30) {
                        court.layout_x = this.resizeStartXCoord + dx;
                        court.layout_w = nw;
                    }
                    court.layout_h = Math.max(30, this.resizeStartH + dy);
                } else if (this.resizeDirection === "tr") {
                    court.layout_w = Math.max(30, this.resizeStartW + dx);
                    const nh = this.resizeStartH - dy;
                    if (nh >= 30) {
                        court.layout_y = this.resizeStartYCoord + dy;
                        court.layout_h = nh;
                    }
                } else if (this.resizeDirection === "tl") {
                    const nw = this.resizeStartW - dx;
                    const nh = this.resizeStartH - dy;
                    if (nw >= 30) {
                        court.layout_x = this.resizeStartXCoord + dx;
                        court.layout_w = nw;
                    }
                    if (nh >= 30) {
                        court.layout_y = this.resizeStartYCoord + dy;
                        court.layout_h = nh;
                    }
                }
                this.validateSize(court);
                return;
            }
            if (!this.draggingCourtId) return;
            const court = this.courts.find(
                (c) => c.id === this.draggingCourtId,
            );
            if (!court) return;
            const l = this.getLogicalCoords(event);
            let newX = l.x - this.dragStartX;
            let newY = l.y - this.dragStartY;

            const w = court.layout_w || this.getDefaultWidth(court);
            const h = court.layout_h || this.getDefaultHeight(court);
            const result = this.calculateGuidelines('court', court.id, newX, newY, w, h);

            court.layout_x = result.x;
            court.layout_y = result.y;
            this.activeGuidelines.x = result.guidelinesX;
            this.activeGuidelines.y = result.guidelinesY;
        },

        endDrag() {
            this.draggingCourtId = null;
            this.resizingCourtId = null;
            this.activeGuidelines.x = [];
            this.activeGuidelines.y = [];
        },

        async saveLayout() {
            if (Object.keys(this.collisions).length > 0) {
                if (
                    !confirm(
                        "Phát hiện có một số sân đang bị chồng lấn nhau (hiển thị màu đỏ). Bạn có chắc chắn vẫn muốn lưu sơ đồ này không?",
                    )
                )
                    return;
            }
            this.savingLayout = true;
            try {
                await venueClusterService.updateCourtsLayout({
                    venue_cluster_id: this.selectedCluster.id,
                    courts: this.courts.map((c) => ({
                        id: c.id,
                        layout_x: c.layout_x,
                        layout_y: c.layout_y,
                        layout_w: c.layout_w,
                        layout_h: c.layout_h,
                        layout_rotation: c.layout_rotation,
                    })),
                    layout_decorations: this.decorations.map((d) => ({
                        id: d.id,
                        type: d.type,
                        name: d.name,
                        layout_x: d.layout_x,
                        layout_y: d.layout_y,
                        layout_w: d.layout_w,
                        layout_h: d.layout_h,
                        layout_rotation: d.layout_rotation || 0,
                    })),
                });
                alert("Sơ đồ sân con và vật phẩm bổ trợ đã được lưu thành công.");
                this.selectedCluster.layout_decorations = JSON.parse(JSON.stringify(this.decorations));
                await this.fetchCourts(this.selectedCluster.id);
            } catch (err) {
                alert(err.message || "Lỗi khi lưu sơ đồ.");
            } finally {
                this.savingLayout = false;
            }
        },

        addDecoration(type, defaultName) {
            const rect = this.$refs.canvasViewport?.getBoundingClientRect();
            const cx = rect ? rect.width / 2 : 500;
            const cy = rect ? rect.height / 2 : 300;
            
            let defaultW = 100;
            let defaultH = 100;
            if (type === 'entrance') { defaultW = 120; defaultH = 60; }
            else if (type === 'reception') { defaultW = 120; defaultH = 80; }
            else if (type === 'restroom') { defaultW = 80; defaultH = 80; }
            else if (type === 'seating') { defaultW = 120; defaultH = 50; }
            
            const newDecor = {
                id: 'decor_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                type: type,
                name: defaultName,
                layout_x: Math.round((cx - this.panX) / this.zoom - defaultW / 2),
                layout_y: Math.round((cy - this.panY) / this.zoom - defaultH / 2),
                layout_w: defaultW,
                layout_h: defaultH,
                layout_rotation: 0
            };
            
            this.decorations.push(newDecor);
            this.selectedDecorationId = newDecor.id;
            this.selectedCourtId = null;
        },

        rotateSelectedDecor90() {
            const decor = this.selectedDecoration;
            if (decor) {
                decor.layout_rotation = ((decor.layout_rotation || 0) + 90) % 360;
            }
        },

        deleteDecoration(decor) {
            if (!decor) return;
            this.decorations = this.decorations.filter(d => d.id !== decor.id);
            if (this.selectedDecorationId === decor.id) {
                this.selectedDecorationId = null;
            }
        },

        selectDecor(decor) {
            this.selectedDecorationId = decor.id;
            this.selectedCourtId = null;
        },

        startDragDecor(event, decor) {
            this.draggingDecorationId = decor.id;
            this.selectedDecorationId = decor.id;
            this.selectedCourtId = null;
            const l = this.getLogicalCoords(event);
            this.dragStartX = l.x - (decor.layout_x || 0);
            this.dragStartY = l.y - (decor.layout_y || 0);
        },

        startResizeDecor(event, decor, direction) {
            this.resizingDecorationId = decor.id;
            this.resizeDirection = direction;
            const l = this.getLogicalCoords(event);
            this.dragStartX = l.x;
            this.dragStartY = l.y;
            this.resizeStartW = decor.layout_w;
            this.resizeStartH = decor.layout_h;
            this.resizeStartXCoord = decor.layout_x || 0;
            this.resizeStartYCoord = decor.layout_y || 0;
        },

        handleDragDecor(event) {
            if (this.resizingDecorationId) {
                const decor = this.decorations.find(
                    (d) => d.id === this.resizingDecorationId,
                );
                if (!decor) return;
                const l = this.getLogicalCoords(event);
                const dx = l.x - this.dragStartX;
                const dy = l.y - this.dragStartY;
                if (this.resizeDirection === "br") {
                    decor.layout_w = Math.max(30, this.resizeStartW + dx);
                    decor.layout_h = Math.max(30, this.resizeStartH + dy);
                } else if (this.resizeDirection === "bl") {
                    const nw = this.resizeStartW - dx;
                    if (nw >= 30) {
                        decor.layout_x = this.resizeStartXCoord + dx;
                        decor.layout_w = nw;
                    }
                    decor.layout_h = Math.max(30, this.resizeStartH + dy);
                } else if (this.resizeDirection === "tr") {
                    decor.layout_w = Math.max(30, this.resizeStartW + dx);
                    const nh = this.resizeStartH - dy;
                    if (nh >= 30) {
                        decor.layout_y = this.resizeStartYCoord + dy;
                        decor.layout_h = nh;
                    }
                } else if (this.resizeDirection === "tl") {
                    const nw = this.resizeStartW - dx;
                    const nh = this.resizeStartH - dy;
                    if (nw >= 30) {
                        decor.layout_x = this.resizeStartXCoord + dx;
                        decor.layout_w = nw;
                    }
                    if (nh >= 30) {
                        decor.layout_y = this.resizeStartYCoord + dy;
                        decor.layout_h = nh;
                    }
                }
                return;
            }
            const decor = this.decorations.find(
                (d) => d.id === this.draggingDecorationId,
            );
            if (!decor) return;
            const l = this.getLogicalCoords(event);
            let newX = l.x - this.dragStartX;
            let newY = l.y - this.dragStartY;

            const w = decor.layout_w || 100;
            const h = decor.layout_h || 100;
            const result = this.calculateGuidelines('decor', decor.id, newX, newY, w, h);

            decor.layout_x = result.x;
            decor.layout_y = result.y;
            this.activeGuidelines.x = result.guidelinesX;
            this.activeGuidelines.y = result.guidelinesY;
        },

        endDragDecor() {
            this.draggingDecorationId = null;
            this.resizingDecorationId = null;
            this.activeGuidelines.x = [];
            this.activeGuidelines.y = [];
        },

        getDecorStyle(decor) {
            return {
                left: `${decor.layout_x}px`,
                top: `${decor.layout_y}px`,
                width: `${decor.layout_w}px`,
                height: `${decor.layout_h}px`,
            };
        },

        autoArrange() {
            if (
                !confirm(
                    "Bạn có chắc chắn muốn tự động sắp xếp tất cả các sân không? Thao tác này sẽ ghi đè các vị trí hiện tại.",
                )
            )
                return;
            let currentX = 50,
                currentY = 50,
                maxRowH = 0;
            this.courts.forEach((c) => {
                const w = this.getDefaultWidth(c);
                const h = this.getDefaultHeight(c);
                if (currentX + w > 1500) {
                    currentX = 50;
                    currentY += maxRowH + 80;
                    maxRowH = 0;
                }
                c.layout_w = w;
                c.layout_h = h;
                c.layout_x = currentX;
                c.layout_y = currentY;
                c.layout_rotation = 0;
                currentX += w + 80;
                if (h > maxRowH) maxRowH = h;
            });
            this.$nextTick(() => this.fitView());
        },

        clearLayout() {
            if (
                confirm(
                    "Bạn có muốn gỡ bỏ toàn bộ sân con khỏi sơ đồ hiện tại không?",
                )
            ) {
                this.courts.forEach((c) => {
                    c.layout_x = null;
                    c.layout_y = null;
                });
                this.selectedCourtId = null;
            }
        },

        // ── Approvals Tab ──
        async fetchApprovals(clusterId) {
            this.approvalsLoading = true;
            try {
                const res =
                    await venueClusterService.getApprovalRequests(clusterId);
                this.approvalRequests = res.data || [];
            } catch (err) {
                console.error("Lỗi khi tải yêu cầu quy mô:", err.message);
            } finally {
                this.approvalsLoading = false;
            }
        },

        setApprovalFilter(filter) {
            this.approvalFilter = filter;
        },

        getParentTypeName(child) {
            if (!child.parent_id) return "";
            const parent = this.courtTypes.find(
                (t) => t.id === child.parent_id,
            );
            return parent ? parent.name : "";
        },

        selectReqCourtType(child) {
            this.newReqForm.court_type_id = child.id;
            this.showTypeDropdown = false;
        },

        handleOutsideClick(e) {
            const el = this.$el?.querySelector(".custom-select-wrapper:not(.req-parent-select)");
            if (el && !el.contains(e.target)) this.showTypeDropdown = false;

            const reqParentEl = this.$el?.querySelector(".req-parent-select");
            if (reqParentEl && !reqParentEl.contains(e.target)) this.showReqParentDropdown = false;
        },

        handleEvidenceSelect(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) {
                this.newReqError = 'Ảnh minh chứng không được quá 5MB.';
                return;
            }
            this.evidenceFile = file;
            this.evidencePreview = URL.createObjectURL(file);
        },
        handleEvidenceDrop(e) {
            const file = e.dataTransfer.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            if (file.size > 5 * 1024 * 1024) {
                this.newReqError = 'Ảnh minh chứng không được quá 5MB.';
                return;
            }
            this.evidenceFile = file;
            this.evidencePreview = URL.createObjectURL(file);
        },
        removeEvidence() {
            this.evidenceFile = null;
            if (this.evidencePreview) {
                URL.revokeObjectURL(this.evidencePreview);
                this.evidencePreview = null;
            }
            if (this.$refs.evidenceInput) {
                this.$refs.evidenceInput.value = '';
            }
        },
        handleScaleSupplementSelect(e) {
            this.scaleSupplementFiles = Array.from(e.target.files || []);
        },
        clearScaleSupplementFiles() {
            this.scaleSupplementFiles = [];
            if (this.$refs.scaleSupplementInput) {
                this.$refs.scaleSupplementInput.value = '';
            }
        },
        handleLocationSupplementSelect(e) {
            this.locationSupplementFiles = Array.from(e.target.files || []);
        },
        clearLocationSupplementFiles() {
            this.locationSupplementFiles = [];
            if (this.$refs.locationSupplementInput) {
                this.$refs.locationSupplementInput.value = '';
            }
        },

        openCreateApprovalModal() {
            this.newReqForm = { court_type_id: "", name: "", note: "" };
            this.removeEvidence();
            this.clearScaleSupplementFiles();
            this.showCreateApprovalModal = true;
            this.newReqSuccess = null;
            this.newReqError = null;
        },

        closeCreateApprovalModal() {
            this.showCreateApprovalModal = false;
        },

        async handleCreateApproval() {
            if (!this.newReqForm.court_type_id) {
                this.newReqError = "Vui lòng chọn loại sân.";
                return;
            }
            this.creatingReq = true;
            this.newReqError = null;
            this.newReqSuccess = null;
            try {
                const formData = new FormData();
                formData.append('court_type_id', this.newReqForm.court_type_id);
                formData.append('name', this.newReqForm.name);
                if (this.newReqForm.note) {
                    formData.append('note', this.newReqForm.note);
                }
                if (this.evidenceFile) {
                    formData.append('evidence_image', this.evidenceFile);
                }
                this.scaleSupplementFiles.forEach((file) => {
                    formData.append('supplementary_documents[]', file);
                });
                const res = await venueClusterService.createApprovalRequest(
                    this.selectedCluster.id,
                    formData,
                );
                this.newReqSuccess =
                    "Gửi yêu cầu thành công! Admin sẽ xem xét và phê duyệt sớm.";
                this.approvalRequests.unshift(res.data);
                this.newReqForm = { court_type_id: "", name: "", note: "" };
                this.removeEvidence();
                this.clearScaleSupplementFiles();
                this.closeCreateApprovalModal();
                if (!this.courtTypes.length) {
                    const typesRes = await courtTypeService.getAll();
                    this.courtTypes = typesRes.data || [];
                }
            } catch (err) {
                this.newReqError = err.message || "Lỗi khi gửi yêu cầu.";
            } finally {
                this.creatingReq = false;
            }
        },

        async handleCancelApproval(req) {
            if (!confirm(`Hủy yêu cầu "${req.name}"?`)) return;
            this.cancellingId = req.id;
            try {
                const res = await venueClusterService.cancelApprovalRequest(
                    this.selectedCluster.id,
                    req.id,
                );
                const idx = this.approvalRequests.findIndex(
                    (r) => r.id === req.id,
                );
                if (idx !== -1) this.approvalRequests.splice(idx, 1, res.data);
            } catch (err) {
                alert(err.message || "Hủy yêu cầu thất bại.");
            } finally {
                this.cancellingId = null;
            }
        },

        approvalStatusLabel(status) {
            return (
                {
                    pending: "Chờ duyệt",
                    need_supplement: "Cần bổ sung",
                    approved: "Đã duyệt",
                    rejected: "Từ chối",
                    cancelled: "Đã hủy",
                }[status] || status
            );
        },

        // ── Location Change Requests Tab ──
        async fetchLocationRequests(clusterId) {
            this.locationLoading = true;
            try {
                const res =
                    await venueClusterService.getLocationChangeRequests(
                        clusterId,
                    );
                this.locationRequests = res.data || [];
            } catch (err) {
                console.error("Lỗi khi tải yêu cầu vị trí:", err.message);
            } finally {
                this.locationLoading = false;
            }
        },

        setLocationFilter(filter) {
            this.locationFilter = filter;
        },

        async openLocationChangeModal() {
            this.locationModalError = null;
            this.locationMapMsg = null;
            this.clearLocationSupplementFiles();
            this.locationForm = {
                new_province: this.selectedCluster.province || "",
                new_ward: this.selectedCluster.ward || "",
                new_address: this.selectedCluster.address || "",
                new_map_url: this.selectedCluster.map_url || "",
                new_latitude:
                    parseFloat(this.selectedCluster.latitude) || 21.0285,
                new_longitude:
                    parseFloat(this.selectedCluster.longitude) || 105.8542,
                note: "",
            };
            this.showLocationModal = true;

            this.provinceSearch = this.locationForm.new_province;
            this.wardSearch = this.locationForm.new_ward;

            this.wardsList = [];
            if (this.locationForm.new_province) {
                const province = this.provincesList.find(
                    (p) => p.name === this.locationForm.new_province,
                );
                if (province) {
                    await this.fetchWards(province.code);
                }
            }

            this.$nextTick(() => {
                this.initLocationModalMap();
            });
        },

        closeLocationChangeModal() {
            this.showLocationModal = false;
            this.destroyLocationMap();
            this.clearLocationSupplementFiles();
        },

        async handleLocationChangeSubmit() {
            this.locationSubmitting = true;
            this.locationModalError = null;
            try {
                const formData = new FormData();
                Object.entries(this.locationForm).forEach(([key, value]) => {
                    if (value !== null && value !== undefined) {
                        formData.append(key, value);
                    }
                });
                this.locationSupplementFiles.forEach((file) => {
                    formData.append('supplementary_documents[]', file);
                });
                const res =
                    await venueClusterService.createLocationChangeRequest(
                        this.selectedCluster.id,
                        formData,
                    );
                this.locationRequests.unshift(res.data);
                this.closeLocationChangeModal();
                // Chuyển sang tab location để xem yêu cầu vừa gửi
                this.activeTab = "location";
            } catch (err) {
                this.locationModalError = err.message || "Lỗi khi gửi yêu cầu.";
            } finally {
                this.locationSubmitting = false;
            }
        },

        async handleExtractLocationCoords() {
            if (!this.locationForm.new_map_url) {
                alert("Vui lòng nhập đường link Google Maps trước.");
                return;
            }
            this.resolvingLocationMap = true;
            this.locationMapMsg = null;
            try {
                await this.parseLocationCoords(this.locationForm.new_map_url);
            } catch (e) {
                this.locationMapMsg = {
                    type: "error",
                    text: "Không thể trích xuất tọa độ.",
                };
            } finally {
                this.resolvingLocationMap = false;
            }
        },

        async parseLocationCoords(url) {
            let targetUrl = url;
            if (
                url.includes("maps.app.goo.gl") ||
                url.includes("goo.gl/maps")
            ) {
                try {
                    const res = await venueClusterService.resolveMapUrl(url);
                    const d = res.data;
                    if (d?.latitude && d?.longitude) {
                        this.locationForm.new_latitude = d.latitude;
                        this.locationForm.new_longitude = d.longitude;
                        this.locationMapMsg = {
                            type: "success",
                            text: `Trích xuất thành công: ${d.latitude}, ${d.longitude}`,
                        };
                        return;
                    }
                    if (d?.final_url) targetUrl = d.final_url;
                } catch (e) {
                    this.locationMapMsg = {
                        type: "error",
                        text: e.message || "Không giải mã được link.",
                    };
                    return;
                }
            }
            let match = targetUrl.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
            if (match) {
                this.locationForm.new_latitude = parseFloat(match[1]);
                this.locationForm.new_longitude = parseFloat(match[2]);
                this.locationMapMsg = {
                    type: "success",
                    text: `Trích xuất thành công: ${match[1]}, ${match[2]}`,
                };
                return;
            }
            match = targetUrl.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
            if (match) {
                this.locationForm.new_latitude = parseFloat(match[1]);
                this.locationForm.new_longitude = parseFloat(match[2]);
                this.locationMapMsg = {
                    type: "success",
                    text: `Trích xuất thành công: ${match[1]}, ${match[2]}`,
                };
                return;
            }
            this.locationMapMsg = {
                type: "error",
                text: "Không tìm thấy tọa độ trong link này.",
            };
        },

        async handleCancelLocationRequest(req) {
            if (!confirm("Hủy yêu cầu thay đổi vị trí này?")) return;
            this.cancellingLocationId = req.id;
            try {
                const res =
                    await venueClusterService.cancelLocationChangeRequest(
                        this.selectedCluster.id,
                        req.id,
                    );
                const idx = this.locationRequests.findIndex(
                    (r) => r.id === req.id,
                );
                if (idx !== -1) this.locationRequests.splice(idx, 1, res.data);
            } catch (err) {
                alert(err.message || "Hủy yêu cầu thất bại.");
            } finally {
                this.cancellingLocationId = null;
            }
        },

        formatDate(val) {
            if (!val) return "—";
            return new Date(val).toLocaleString("vi-VN");
        },

        triggerAction(type) {
            if (type === 'location') {
                this.openLocationChangeModal();
            } else if (type === 'scale') {
                this.openCreateApprovalModal();
            } else if (type === 'amenity') {
                this.openRequestModal();
            } else if (type === 'court_type') {
                this.openCourtTypeRequestModal();
            }
        },

        openCourtTypeRequestModal() {
            this.courtTypeRequestForm = {
                name: "",
                parent_id: null,
                player_count: 2,
                description: "",
                default_layout_w: null,
                default_layout_h: null
            };
            this.selectedReqParentCourtType = null;
            this.courtTypeRequestError = null;
            this.courtTypeRequestSuccess = null;
            this.showCourtTypeRequestModal = true;
            this.showReqParentDropdown = false;
        },

        closeCourtTypeRequestModal() {
            this.showCourtTypeRequestModal = false;
        },

        selectReqParent(parent) {
            this.courtTypeRequestForm.parent_id = parent.id;
            this.selectedReqParentCourtType = parent;
            this.showReqParentDropdown = false;
        },

        async handleCourtTypeRequestSubmit() {
            if (!this.courtTypeRequestForm.name || !this.courtTypeRequestForm.parent_id) {
                this.courtTypeRequestError = "Vui lòng nhập tên loại sân và chọn bộ môn.";
                return;
            }
            this.courtTypeRequestSubmitting = true;
            this.courtTypeRequestError = null;
            this.courtTypeRequestSuccess = null;

            const payload = {
                ...this.courtTypeRequestForm,
                default_layout_w: this.courtTypeRequestForm.default_layout_w ? parseFloat(this.courtTypeRequestForm.default_layout_w) * 100 : null,
                default_layout_h: this.courtTypeRequestForm.default_layout_h ? parseFloat(this.courtTypeRequestForm.default_layout_h) * 100 : null,
            };

            try {
                const res = await courtTypeService.requestNew(payload);
                this.courtTypeRequestSuccess = res.message || "Gửi yêu cầu thêm loại sân mới thành công!";
                setTimeout(() => {
                    this.closeCourtTypeRequestModal();
                }, 2000);
            } catch (err) {
                this.courtTypeRequestError = err.message || "Không thể gửi yêu cầu thêm loại sân mới.";
            } finally {
                this.courtTypeRequestSubmitting = false;
            }
        },
    },
};
</script>

<style scoped>
.venue-clusters-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.card {
    background: var(--admin-surface, #ffffff);
    border-radius: 12px;
    border: 1px solid var(--admin-border, #e5e7eb);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    padding: 24px;
}

/* ─── Layout ─── */
.clusters-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    align-items: start;
}

@media (max-width: 900px) {
    .clusters-grid {
        grid-template-columns: 1fr;
    }
}

.clusters-list {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: sticky;
    top: 20px;
}

.cluster-item {
    display: flex;
    align-items: center;
    padding: 14px 16px;
    border-radius: 8px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}
.cluster-item:hover {
    background: var(--admin-hover, var(--sg-surface));
}
.cluster-item.active {
    background: var(--admin-primary-soft, rgba(0, 0, 0, 0.05));
    border-color: var(--admin-border, rgba(0, 0, 0, 0.2));
}
.cluster-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--admin-text, var(--sg-text));
    margin: 0;
}
.cluster-address {
    font-size: 12px;
    color: var(--admin-faint, rgba(15, 23, 42, 0.5));
    margin-top: 4px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.cluster-detail {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ─── Tabs ─── */
.detail-tabs {
    display: flex;
    gap: 4px;
    padding: 8px;
    background: var(--admin-surface, #ffffff);
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 6px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--admin-muted, rgba(15, 23, 42, 0.6));
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.tab-btn:hover {
    background: var(--admin-hover, var(--sg-surface));
    color: var(--admin-text, var(--sg-text));
}
.tab-btn.active {
    background: var(--admin-primary, #000);
    color: var(--admin-bg, #fff);
    border-color: var(--admin-primary, #000);
}

.tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 800;
    background: #ef4444;
    color: #fff;
}
.tab-btn.active .tab-badge {
    background: rgba(255, 255, 255, 0.2);
}

/* ─── Info Tab ─── */
.cluster-edit {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.edit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--sg-border);
    padding-bottom: 16px;
    margin-bottom: 20px;
}
.edit-header h3 {
    font-size: 18px;
    font-weight: 800;
    color: var(--sg-text);
    margin: 0;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 576px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
}
.form-group label {
    font-size: 13px;
    font-weight: 700;
    color: var(--admin-text, var(--sg-text));
}
.required {
    color: #ef4444;
}
.form-control {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--admin-border, var(--sg-border));
    background: var(--admin-surface, #ffffff);
    font-size: 14px;
    color: var(--admin-text, var(--sg-text));
    outline: none;
    transition: border-color 0.2s;
}
.form-control:focus {
    border-color: var(--admin-primary, #000);
}

.map-input-group {
    display: flex;
    gap: 12px;
}
.map-input-group .form-control {
    flex: 1;
}
.btn-extract {
    white-space: nowrap;
}
.map-extract-msg {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}
.map-extract-msg.success {
    background: #f3f4f6;
    color: #000;
    border-left: 3px solid #000;
}
.map-extract-msg.error {
    background: #f3f4f6;
    color: #ef4444;
    border-left: 3px solid #ef4444;
}
.map-container {
    width: 100%;
    height: 320px;
    border-radius: 8px;
    border: 1px solid var(--sg-border);
    margin-top: 8px;
    z-index: 1;
}
.map-help-text {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.5);
}

.amenities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
    background: var(--sg-surface);
    padding: 16px;
    border-radius: 8px;
    border: 1px solid var(--sg-border);
}
.amenity-item-wrapper {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--sg-background);
    border: 1px solid var(--sg-border);
    transition: all 0.2s ease;
}
.amenity-item-wrapper.active {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary-soft, #f3f4f6);
}
.amenity-item-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 8px;
}
.amenity-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
}
.amenity-checkbox input {
    width: 16px;
    height: 16px;
    accent-color: var(--admin-primary, #000000);
}
.btn-edit-amenity-desc {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: background-color 0.2s;
    color: #64748b;
}
.btn-edit-amenity-desc:hover {
    background-color: rgba(15, 23, 42, 0.08);
    color: var(--admin-primary, #000000);
}
.btn-edit-amenity-desc .edit-icon {
    font-size: 13px;
}
.has-desc-dot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 6px;
    height: 6px;
    background-color: var(--admin-primary, #000000);
    border-radius: 50%;
}
.modal-amenity-desc {
    max-width: 450px;
    width: 90%;
}
.amenity-request-tip {
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
}
.link-request-amenity {
    color: #000;
    font-weight: 700;
    text-decoration: underline;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid var(--sg-border);
    padding-top: 20px;
    margin-top: 8px;
}

/* Images */
.owner-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}
.owner-gallery-item {
    position: relative;
    aspect-ratio: 4/3;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--sg-border);
    background: #f8fafc;
}
.owner-gallery-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.btn-delete-img {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(220, 38, 38, 0.85);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    z-index: 10;
}
.btn-delete-img:hover {
    background: rgb(220, 38, 38);
}
.owner-gallery-empty {
    padding: 18px;
    background: var(--admin-surface-muted, #f8fafc);
    border: 1px dashed var(--admin-border, var(--sg-border));
    border-radius: 8px;
    text-align: center;
    color: var(--admin-faint, rgba(15, 23, 42, 0.45));
    font-size: 13px;
    margin-bottom: 12px;
}
.owner-upload-zone {
    border: 2px dashed var(--admin-border, #cbd5e1);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
}
.owner-upload-zone:hover {
    border-color: var(--admin-primary, #000);
    background-color: var(--admin-surface-muted, #f8fafc);
}
.hidden-file-input {
    display: none;
}
.upload-label-zone {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    cursor: pointer;
    min-height: 60px;
}
.upload-status-text {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ─── Courts Tab ─── */
.courts-tab {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.courts-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.courts-header-left h3 {
    font-size: 18px;
    font-weight: 800;
    margin: 0;
}
.subtitle {
    color: rgba(15, 23, 42, 0.5);
    font-size: 14px;
    margin-top: 4px;
}
.courts-header-actions {
    display: flex;
    gap: 8px;
}

.view-content-wrapper {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.layout-toggle-tabs {
    display: flex;
    gap: 4px;
    background: #fff;
    border: 1px solid var(--sg-border);
    border-radius: 10px;
    padding: 6px;
}

.courts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
}
.court-card {
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}
.court-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
}
.court-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--sg-border);
    padding-bottom: 12px;
}
.court-name {
    font-size: 15px;
    font-weight: 800;
    margin: 0;
}
.court-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.info-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}
.info-row .label {
    color: rgba(15, 23, 42, 0.5);
    font-weight: 700;
}
.info-row .value {
    font-weight: 700;
}
.court-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    border-top: 1px solid var(--sg-border);
    padding-top: 12px;
}

.badge-placed {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 9999px;
    background: rgba(0, 0, 0, 0.05);
    color: #000;
    font-size: 12px;
    font-weight: 700;
}
.badge-unplaced {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 9999px;
    background: #f3f4f6;
    color: rgba(15, 23, 42, 0.4);
    font-size: 12px;
    font-weight: 700;
}

.status-badge {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid transparent;
}
.status-badge.active {
    background: rgba(0, 0, 0, 0.04);
    color: #000;
    border-color: rgba(0, 0, 0, 0.15);
}
.status-badge.inactive {
    background: #f3f4f6;
    color: rgba(0, 0, 0, 0.4);
    border-color: rgba(0, 0, 0, 0.08);
}
.status-badge.maintenance {
    background: #f3f4f6;
    color: rgba(0, 0, 0, 0.7);
    border-color: rgba(0, 0, 0, 0.12);
    border-style: dashed;
}

/* ─── Layout Editor (copy từ OwnerVenueCourts) ─── */
.layout-editor-workspace {
    display: flex;
    flex-direction: column;
}
.editor-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #fff;
    border-radius: 10px;
    border: 1px solid var(--sg-border);
    flex-wrap: wrap;
    gap: 8px;
}
.toolbar-left {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}
.info-badge {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.5);
    font-style: italic;
}
/* ─── Tool Switcher (Figma-style) ─── */
.tool-switcher {
    display: flex;
    background: #f1f5f9;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 3px;
    gap: 2px;
}
.tool-btn {
    width: 30px;
    height: 30px;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    transition: all 0.15s;
}
.tool-btn:hover {
    background: #e2e8f0;
    color: #1e293b;
}
.tool-btn.active {
    background: #fff;
    color: #3b82f6;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
.toolbar-divider {
    width: 1px;
    height: 28px;
    background: #e2e8f0;
    align-self: center;
    margin: 0 2px;
}
.editor-body {
    display: flex;
    gap: 12px;
    height: 600px;
    margin-top: 12px;
}
.canvas-viewport {
    flex: 1;
    background: #f0f2f5;
    border-radius: 10px;
    border: 1px solid var(--sg-border);
    overflow: hidden;
    position: relative;
    cursor: default;
    user-select: none;
}
/* Mode: select → con trỏ chuẩn */
.canvas-viewport.tool-select {
    cursor: default;
}
/* Mode: pan → con trỏ bàn tay */
.canvas-viewport.tool-pan {
    cursor: grab;
}
.canvas-viewport.tool-pan:active,
.canvas-viewport.tool-pan.panning {
    cursor: grabbing;
}
/* Khi đang pan ở mode select (kéo nền trống) */
.canvas-viewport.tool-select.panning {
    cursor: grabbing;
}
.canvas-interaction-guide {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 4px;
    pointer-events: none;
}
.guide-item {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(4px);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    color: rgba(15, 23, 42, 0.7);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}
.zoom-controls {
    position: absolute;
    bottom: 16px;
    right: 16px;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 4px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(8px);
    border: 1px solid var(--sg-border);
    padding: 6px 8px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.btn-zoom {
    padding: 4px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    transition: all 0.15s;
}
.btn-zoom:hover {
    background: #f8fafc;
}
.btn-zoom.fit,
.btn-zoom.reset {
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.zoom-level {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    min-width: 42px;
    text-align: center;
}
.canvas-content {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: 0 0;
}
.canvas-grid-bg {
    position: absolute;
    top: -500px;
    left: -500px;
    width: 3000px;
    height: 3000px;
    background-image: radial-gradient(circle, #9ba3af 1px, transparent 1px);
    background-size: 40px 40px;
    opacity: 0.35;
    pointer-events: none;
}
.canvas-guideline {
    position: absolute;
    pointer-events: none;
    z-index: 99;
}
.canvas-guideline.vertical {
    top: -3000px;
    bottom: -3000px;
    width: 1px;
    border-left: 1px dashed #ef4444;
    opacity: 0.8;
}
.canvas-guideline.horizontal {
    left: -3000px;
    right: -3000px;
    height: 1px;
    border-top: 1px dashed #ef4444;
    opacity: 0.8;
}
.canvas-court-element {
    position: absolute;
    cursor: pointer;
    box-sizing: border-box;
    transition: box-shadow 0.1s;
}
.canvas-court-element:hover {
    cursor: pointer;
}
.canvas-court-element:active {
    cursor: move;
}
.canvas-court-element.selected {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}
.canvas-court-element.has-collision {
    outline: 2px solid #ef4444;
}
.resize-handle {
    position: absolute;
    width: 12px;
    height: 12px;
    background: #3b82f6;
    border: 2px solid #fff;
    border-radius: 2px;
    z-index: 10;
}
.resize-handle.tl {
    top: -6px;
    left: -6px;
    cursor: nwse-resize;
}
.resize-handle.tr {
    top: -6px;
    right: -6px;
    cursor: nesw-resize;
}
.resize-handle.bl {
    bottom: -6px;
    left: -6px;
    cursor: nesw-resize;
}
.resize-handle.br {
    bottom: -6px;
    right: -6px;
    cursor: nwse-resize;
}
.collision-badge {
    position: absolute;
    top: 4px;
    left: 4px;
    background: rgba(239, 68, 68, 0.9);
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    pointer-events: none;
    z-index: 5;
}
.editor-sidebar {
    width: 260px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow-y: auto;
}
.sidebar-section {
    background: #fff;
    border-radius: 10px;
    border: 1px solid var(--sg-border);
    padding: 16px;
}
.section-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--sg-text);
    margin: 0 0 12px 0;
}
.section-desc {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.5);
    margin-bottom: 12px;
}
.inspector-warning-box {
    background: #fef9c3;
    border: 1px solid #fde047;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 12px;
    font-size: 12px;
    font-weight: 700;
    color: #713f12;
}
.inspector-fields {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.field-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}
.field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.field-group label {
    font-size: 12px;
    font-weight: 700;
    color: rgba(15, 23, 42, 0.6);
}
.input-row {
    display: flex;
    align-items: center;
    gap: 6px;
}
.input-row input {
    flex: 1;
    border: 1px solid var(--sg-border);
    border-radius: 6px;
    padding: 6px 8px;
    font-size: 13px;
    outline: none;
    width: 70px;
}
.input-row .x,
.input-row .comma {
    font-weight: 700;
    color: rgba(15, 23, 42, 0.4);
}
.rotation-control {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.rotation-slider {
    width: 100%;
}
.btn-xs {
    padding: 4px 8px;
    font-size: 12px;
}
.btn-block {
    width: 100%;
    justify-content: center;
}
.unplaced-items {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.unplaced-court-item {
    padding: 10px;
    border: 1px solid var(--sg-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
}
.unplaced-court-item:hover {
    background: var(--sg-surface);
    border-color: #000;
}
.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.item-name {
    font-size: 13px;
    font-weight: 700;
}
.item-add-hint {
    font-size: 11px;
    color: rgba(15, 23, 42, 0.45);
}
.item-type {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.5);
    margin-top: 4px;
}
.empty-unplaced {
    padding: 16px;
    text-align: center;
    color: rgba(15, 23, 42, 0.4);
    font-size: 13px;
}

/* ─── Approvals Tab ─── */
.approvals-tab {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.approval-form-card {
}
.approval-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}
.approval-filter-tabs {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.tab-sm {
    padding: 5px 12px;
    border: 1px solid var(--sg-border);
    border-radius: 6px;
    background: transparent;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    color: rgba(15, 23, 42, 0.6);
    transition: all 0.15s;
}
.tab-sm:hover {
    background: var(--sg-surface);
}
.tab-sm.active {
    background: #000;
    color: #fff;
    border-color: #000;
}
.empty-section {
    padding: 40px 0;
    text-align: center;
    color: rgba(15, 23, 42, 0.4);
}
.approval-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.approval-card {
    border: 1px solid var(--sg-border);
    border-radius: 10px;
    padding: 16px;
    transition: box-shadow 0.15s;
    border-left-width: 3px;
}
.approval-card:hover {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}
.approval-pending {
    border-left-color: #f59e0b;
}
.approval-need_supplement {
    border-left-color: #f59e0b;
}
.approval-approved {
    border-left-color: var(--admin-primary, #000000);
}
.approval-rejected {
    border-left-color: #ef4444;
}
.approval-cancelled {
    border-left-color: #94a3b8;
}
.approval-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}
.approval-details {
    flex: 1;
}
.approval-name {
    font-size: 15px;
    font-weight: 800;
    margin-bottom: 6px;
}
.approval-meta {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.5);
    margin-top: 2px;
}
.approval-reason {
    font-size: 13px;
    color: #ef4444;
    margin-top: 6px;
    font-weight: 600;
}
.approval-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}
.status-badge-approval {
    display: inline-flex;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 700;
}
.approval-status-pending {
    background: #fef9c3;
    color: #713f12;
}
.approval-status-need_supplement {
    background: #fffbeb;
    color: #92400e;
}
.approval-status-approved {
    background: var(--admin-primary, #000000);
    color: var(--admin-bg, #ffffff);
}
.approval-status-rejected {
    background: #fee2e2;
    color: #7f1d1d;
}
.approval-status-cancelled {
    background: #f1f5f9;
    color: #475569;
}

/* ─── Custom Select ─── */
.custom-select-wrapper {
    position: relative;
}
.custom-select-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border: 1px solid var(--sg-border);
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    background: #fff;
    transition: border-color 0.2s;
}
.custom-select-trigger:hover,
.custom-select-trigger.active {
    border-color: #000;
}
.custom-select-trigger .placeholder {
    color: rgba(15, 23, 42, 0.4);
}
.custom-select-trigger .arrow {
    color: rgba(15, 23, 42, 0.5);
}
.parent-name {
    color: rgba(15, 23, 42, 0.5);
}
.separator {
    margin: 0 6px;
    color: rgba(15, 23, 42, 0.3);
}
.child-name {
    font-weight: 700;
    color: var(--sg-text);
}
.custom-options-container {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    background: #fff;
    border: 1px solid var(--sg-border);
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    max-height: 260px;
    overflow-y: auto;
    margin-top: 4px;
}
.custom-optgroup-label {
    padding: 8px 12px 4px;
    font-size: 11px;
    font-weight: 800;
    color: rgba(15, 23, 42, 0.4);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-top: 1px solid var(--sg-border);
}
.custom-optgroup:first-child .custom-optgroup-label {
    border-top: none;
}
.custom-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.1s;
}
.custom-option:hover {
    background: var(--sg-surface);
}
.custom-option.selected {
    background: rgba(0, 0, 0, 0.04);
}
.option-text {
    font-weight: 700;
    font-size: 14px;
}
.option-details {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.5);
}
.check-mark {
    margin-left: auto;
    color: #000;
    font-weight: 800;
}

/* ─── Modal ─── */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 999;
}
.modal {
    width: min(500px, 95vw);
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--sg-border);
}
.modal-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
}
.btn-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #64748b;
}
.modal-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 20px;
    border-top: 1px solid var(--sg-border);
    background: #f8fafc;
}

/* ─── Buttons ─── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}
.btn-primary {
    background: #000;
    border-color: #000;
    color: #fff;
}
.btn-primary:hover {
    background: #222;
    border-color: #222;
}
.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.btn-outline {
    border: 1px solid var(--sg-border);
    background: transparent;
    color: var(--sg-text);
}
.btn-outline:hover {
    background: var(--sg-surface);
}
.btn-outline:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.btn-danger-outline {
    border: 1px solid rgba(0, 0, 0, 0.15);
    background: transparent;
    color: rgba(0, 0, 0, 0.7);
}
.btn-danger-outline:hover {
    background: rgba(0, 0, 0, 0.05);
}

/* ─── States ─── */
.loading-state,
.error-state,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 24px;
    text-align: center;
    gap: 16px;
    color: rgba(15, 23, 42, 0.6);
}
.error-message {
    color: #ef4444;
    font-weight: 700;
}
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 16px;
}
.alert-success {
    background: #f3f4f6;
    color: #000000;
    border: 1px solid #e5e7eb;
}
.alert-danger {
    background: #fef2f2;
    color: #7f1d1d;
    border: 1px solid #fecaca;
}
.fw-bold {
    font-weight: 700;
}
.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(0, 0, 0, 0.1);
    border-top-color: #000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
.spinner-sm {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-top-color: #000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ─── Location Readonly Box ─── */
.location-readonly-box {
    border: 1px solid var(--admin-border, var(--sg-border));
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 8px;
}
.location-readonly-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: var(--admin-surface-muted, #f8fafc);
    border-bottom: 1px solid var(--admin-border, var(--sg-border));
    gap: 12px;
    flex-wrap: wrap;
}
.location-readonly-title {
    font-weight: 700;
    font-size: 13px;
    color: var(--admin-text, var(--sg-text));
}
.pending-location-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    background: var(--admin-warning-soft, #fef3c7);
    color: var(--admin-warning, #92400e);
    border: 1px solid var(--admin-warning, #fde68a);
}
.location-readonly-body {
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: var(--admin-surface, #fff);
}
.location-info-row {
    display: flex;
    gap: 8px;
    font-size: 13px;
    align-items: baseline;
}
.location-label {
    font-weight: 700;
    color: var(--admin-faint, rgba(15, 23, 42, 0.5));
    min-width: 80px;
    flex-shrink: 0;
}
.location-value {
    color: var(--admin-text, var(--sg-text));
}
.location-coord {
    font-family: monospace;
    font-size: 12px;
}
.map-container {
    height: 280px;
    width: 100%;
    border-radius: 8px;
    overflow: hidden;
}
.map-readonly {
    opacity: 0.9;
}

/* ─── Location Tab ─── */
.location-tab {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.location-current-card {
}
.location-current-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
}
.location-actions {
    margin-top: 4px;
}

/* ─── Tab Badge ─── */
.tab-badge-location {
    background: #f59e0b;
    color: #fff;
}

/* ─── Modal Location ─── */
.modal-location {
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}
.modal-location form {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
    min-height: 0;
}
.modal-location .modal-body {
    overflow-y: auto;
    flex: 1;
}

/* ─── Empty section ─── */
.empty-section {
    padding: 32px 0;
    text-align: center;
    color: rgba(15, 23, 42, 0.45);
    font-size: 14px;
}

/* ─── Layout Decorations ─── */
.canvas-decor-element {
    position: absolute;
    cursor: pointer;
    box-sizing: border-box;
    transition: box-shadow 0.1s;
    z-index: 20;
}
.canvas-decor-element:hover {
    cursor: pointer;
}
.canvas-decor-element.dragging {
    cursor: move;
    z-index: 60;
}
.canvas-decor-element.selected {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
    z-index: 30;
}
.decor-library-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.btn-add-decor {
    padding: 8px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.btn-add-decor:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #1e293b;
}

/* ─── Searchable Select Custom styles ─── */
.searchable-select-container {
    position: relative;
    width: 100%;
}
.searchable-select-input {
    width: 100%;
    padding-right: 32px !important;
}
.searchable-select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
    transition: transform 0.2s;
    font-size: 10px;
}
.searchable-select-arrow.open {
    transform: translateY(-50%) rotate(180deg);
}
.searchable-select-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    z-index: 999;
    max-height: 200px;
    overflow-y: auto;
}
.searchable-select-option {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
    color: #1e293b;
    transition: background-color 0.15s, color 0.15s;
    text-align: left;
}
.searchable-select-option:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}
.searchable-select-option.selected {
    background-color: #e2e8f0;
    font-weight: 600;
}
.searchable-select-option.empty {
    color: #64748b;
    text-align: center;
    cursor: default;
}

/* ─── Evidence Upload ─── */
.evidence-upload-area {
    margin-top: 4px;
}
.evidence-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 28px 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.25s ease;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.evidence-dropzone:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}
.evidence-dropzone-icon {
    margin-bottom: 6px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
}
.evidence-dropzone-text {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}
.evidence-preview-wrapper {
    position: relative;
    display: inline-block;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    transition: border-color 0.2s;
}
.evidence-preview-wrapper:hover {
    border-color: #3b82f6;
}
.evidence-preview-img {
    max-width: 100%;
    max-height: 200px;
    display: block;
    object-fit: cover;
    border-radius: 10px;
}
.btn-remove-evidence {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: none;
    background: rgba(239, 68, 68, 0.9);
    color: #fff;
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    backdrop-filter: blur(4px);
}
.btn-remove-evidence:hover {
    background: #dc2626;
    transform: scale(1.15);
}

/* ─── Evidence in Approval History ─── */
.approval-evidence {
    margin-top: 8px;
}
.approval-evidence-label {
    display: block;
    font-size: 12.5px;
    color: rgba(15, 23, 42, 0.55);
    margin-bottom: 4px;
    font-weight: 500;
}
.approval-evidence-link {
    display: inline-block;
    border-radius: 10px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.approval-evidence-link:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}
.approval-evidence-thumb {
    max-width: 180px;
    max-height: 120px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    display: block;
}

/* ─── Tab Headers ─── */
.tab-header {
    border-bottom: 1px solid var(--sg-border);
    padding-bottom: 16px;
    margin-bottom: 20px;
}
.tab-header h3 {
    font-size: 18px;
    font-weight: 800;
    color: var(--sg-text);
    margin: 0;
}
.tab-header .subtitle {
    font-size: 13px;
    color: rgba(15, 23, 42, 0.55);
    margin: 4px 0 0 0;
}

/* ─── Current Scale & Statistics Cards ─── */
.current-scale-card, .affiliate-summary-card {
    margin-bottom: 16px;
}
.card-section-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--sg-text);
    margin: 0 0 12px 0;
}
.scale-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}
.scale-stat-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.scale-stat-label {
    font-size: 12px;
    font-weight: 700;
    color: rgba(15, 23, 42, 0.5);
}
.scale-stat-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--sg-text);
}
.scale-types-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.scale-type-tag {
    background: #f1f5f9;
    color: #475569;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
}
.scale-type-tag strong {
    color: #0f172a;
}
.scale-stat-value-empty {
    font-size: 13px;
    color: rgba(15, 23, 42, 0.4);
    font-style: italic;
}

/* ─── Affiliate Shop Styles ─── */
.affiliate-list-card {
    padding: 0 !important;
    overflow: hidden;
}
.affiliate-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.affiliate-table th {
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 16px;
    text-align: left;
    font-weight: 700;
    color: #475569;
}
.affiliate-table td {
    padding: 12px 16px;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}
.affiliate-table tr.product-row {
    transition: background-color 0.2s;
}
.affiliate-table tr.product-row:hover {
    background-color: #f8fafc;
}
.affiliate-table th.col-img { width: 80px; }
.affiliate-table th.col-platform { width: 120px; }
.affiliate-table th.col-price { width: 140px; text-align: right; }
.affiliate-table td.cell-price { text-align: right; }
.affiliate-table th.col-clicks { width: 100px; text-align: center; }
.affiliate-table td.cell-clicks { text-align: center; font-weight: 700; color: #475569; }
.affiliate-table th.col-status { width: 120px; text-align: center; }
.affiliate-table td.cell-status { text-align: center; }
.affiliate-table th.col-actions { width: 120px; text-align: center; }
.affiliate-table td.cell-actions { text-align: center; }

.product-img-box {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background-color: #f1f5f9;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
}
.product-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.placeholder-icon {
    color: #cbd5e1;
}
.product-title {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.product-desc {
    font-size: 12px;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.platform-badge {
    padding: 4px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    display: inline-block;
}
.platform-badge.shopee { background-color: #ffeae6; color: #ee4d2d; }
.platform-badge.lazada { background-color: #eef2ff; color: #3b82f6; }
.platform-badge.tiki { background-color: #e0f2fe; color: #0284c7; }
.platform-badge.tiktok-shop { background-color: #f3f4f6; color: #111827; }
.platform-badge.khac { background-color: #ecfdf5; color: #10b981; }

.price-discount {
    font-weight: 700;
    color: #10b981;
}
.price-original {
    font-size: 11px;
    color: #94a3b8;
    text-decoration: line-through;
    margin-top: 1px;
}
.price-empty {
    font-style: italic;
    font-weight: normal;
    color: #94a3b8;
    font-size: 12px;
}

/* Switch Toggle Custom */
.switch-toggle {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}
.switch-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.slider-round {
    position: relative;
    width: 36px;
    height: 20px;
    background-color: #cbd5e1;
    border-radius: 9999px;
    transition: background-color 0.2s;
}
.switch-toggle input:checked + .slider-round {
    background-color: #10b981;
}
.slider-round::after {
    content: "";
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background-color: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.switch-toggle input:checked + .slider-round::after {
    transform: translateX(16px);
}

.action-buttons-group {
    display: flex;
    gap: 8px;
    justify-content: center;
}
.btn-action-icon {
    background: transparent;
    padding: 6px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--sg-border);
    cursor: pointer;
    transition: all 0.2s;
}
.btn-action-icon:hover {
    background-color: var(--sg-surface);
}
.btn-action-icon.edit {
    color: var(--sg-text);
}
.btn-action-icon.delete {
    border-color: #fecaca;
    color: #dc2626;
}
.btn-action-icon.delete:hover {
    background-color: #fee2e2;
}

/* ─── Unlock Tab Styles ─── */
.unlock-locked-banner {
    border-left: 5px solid #dc2626;
    background-color: #fffafb;
    padding: 14px;
    margin-bottom: 20px;
    border-radius: 6px;
    border: 1px solid #fecaca;
    border-left-width: 5px;
}
.banner-header {
    display: flex;
    align-items: center;
    gap: 12px;
}
.lock-icon-red {
    color: #dc2626;
}
.banner-text h4 {
    margin: 0;
    font-size: 16px;
    color: #991b1b;
}
.banner-text p {
    margin: 4px 0 0;
    font-size: 13.5px;
    color: #b91c1c;
}
.pending-alert-box {
    background: #fffbeb;
    border: 1px solid #fde68a;
    padding: 16px;
    margin-bottom: 20px;
    border-radius: 8px;
}
.alert-info-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: #b45309;
    margin-bottom: 12px;
}
.pending-reason-preview {
    background: #fff;
    border: 1px solid #f3f4f6;
    padding: 10px 12px;
    border-radius: 4px;
    margin-bottom: 16px;
}
.preview-label {
    font-size: 13px;
    color: #4b5563;
    display: block;
}
.preview-content {
    margin: 4px 0 0;
    font-size: 14px;
    color: #1f2937;
    white-space: pre-wrap;
    line-height: 1.45;
}
.new-appeal-form {
    margin-bottom: 20px;
}
.form-section-title {
    margin: 0 0 6px;
    font-size: 16px;
    color: #1e293b;
    font-weight: 700;
}
.form-section-desc {
    margin: 0 0 16px;
    font-size: 13px;
    color: #64748b;
    line-height: 1.4;
}
.field-label-bold {
    font-weight: 700;
    font-size: 14px;
    color: #344238;
    display: block;
    margin-bottom: 6px;
}
.text-area-appeal {
    width: 100%;
    border: 1px solid #c0d1c1;
    border-radius: 6px;
    padding: 10px;
    font-size: 14px;
    resize: vertical;
}
.char-counter {
    display: block;
    text-align: right;
    color: #94a3b8;
    font-size: 11px;
    margin-top: 4px;
}
.history-section {
    margin-top: 24px;
}
.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 8px;
}
.history-title {
    margin: 0;
    font-size: 15px;
    color: #1e293b;
    font-weight: 700;
}
.empty-state-text {
    text-align: center;
    padding: 30px 0;
    color: #64748b;
    font-style: italic;
    font-size: 14px;
}
.border-rounded {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
}
.unlock-history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.unlock-history-table th {
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 12px;
    text-align: left;
    font-weight: 700;
    color: #475569;
}
.unlock-history-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
}
.unlock-history-table tr.history-row {
    border-bottom: 1px solid #e2e8f0;
}
.unlock-history-table th.col-code { width: 100px; }
.unlock-history-table td.cell-code { font-family: monospace; font-weight: 700; color: #475569; }
.unlock-history-table th.col-time { width: 150px; }
.unlock-history-table th.col-reason { max-width: 300px; }
.unlock-history-table td.cell-reason { max-width: 300px; white-space: normal; line-height: 1.45; word-break: break-word; }
.unlock-history-table th.col-status { width: 120px; }
.unlock-history-table th.col-response { max-width: 300px; }
.unlock-history-table td.cell-response { max-width: 300px; white-space: normal; line-height: 1.45; word-break: break-word; color: #475569; }

/* ─── Read-only Info Display ─── */
.readonly-detail-container {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.form-group-readonly {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.info-label {
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.info-value-text {
    padding: 10px 14px;
    border-radius: 8px;
    background: var(--admin-surface-muted, #f8fafc);
    border: 1px solid var(--admin-border, #e2e8f0);
    font-size: 14.5px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
}
.info-description-text {
    padding: 14px;
    border-radius: 8px;
    background: var(--admin-surface-muted, #f8fafc);
    border: 1px solid var(--admin-border, #e2e8f0);
    font-size: 14px;
    line-height: 1.6;
    color: var(--admin-text, #334155);
    white-space: pre-wrap;
}
.info-amenity-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: var(--admin-primary-soft, #f1f5f9);
    border: 1px solid var(--admin-border, #e2e8f0);
    border-radius: 9999px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--admin-text, #334155);
}
.btn-edit-amenity-desc-readonly {
    background: none;
    border: none;
    padding: 2px;
    cursor: pointer;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    border-radius: 4px;
    transition: all 0.2s;
}
.btn-edit-amenity-desc-readonly:hover {
    color: #0f172a;
    background: rgba(0, 0, 0, 0.05);
}
.btn-edit-amenity-desc-readonly:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

/* Amenities Selector Premium Styles */
.amenity-select-tag {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13.5px;
    font-weight: 500;
    color: var(--admin-muted, #64748b);
    background: var(--admin-surface-muted, #f8fafc);
    border: 1px dashed var(--admin-border, #cbd5e1);
    transition: all 0.2s ease-in-out;
    user-select: none;
}
.amenity-select-tag:hover {
    background: var(--admin-hover, #f1f5f9);
    border-color: var(--admin-faint, #94a3b8);
    color: var(--admin-text, #334155);
    transform: translateY(-1px);
}
.amenity-select-tag.active {
    background: var(--admin-primary, #000000);
    border: 1px solid var(--admin-primary, #000000);
    color: var(--admin-bg, #ffffff);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}
.amenity-select-tag.active:hover {
    background: var(--admin-primary-light, #1f1f22);
    border-color: var(--admin-primary-light, #1f1f22);
    color: var(--admin-bg, #ffffff);
    transform: translateY(-1px);
}
.amenity-check-icon {
    display: inline-flex;
    align-items: center;
    color: var(--admin-bg, #ffffff);
}
.btn-edit-amenity-desc {
    background: none;
    border: none;
    padding: 2px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
    border-radius: 4px;
    color: var(--admin-bg, #86efac);
    transition: all 0.2s;
}
.amenity-select-tag.active .btn-edit-amenity-desc {
    color: var(--admin-bg, #ffffff);
}
.btn-edit-amenity-desc:hover {
    color: var(--admin-bg, #ffffff);
    background: rgba(128, 128, 128, 0.25);
}
.btn-edit-amenity-desc .has-desc-dot {
    width: 6px;
    height: 6px;
    background: var(--admin-bg, #2563eb);
    border-radius: 50%;
    display: inline-block;
    margin-left: 2px;
}

.location-readonly-box {
    padding: 16px;
    border: 1px solid var(--admin-border, #e2e8f0);
    border-radius: 8px;
    background: var(--admin-surface-muted, #f8fafc);
}
.location-readonly-title {
    font-size: 14px;
    color: var(--admin-text, #0f172a);
}
.pending-location-badge {
    display: inline-block;
    margin-left: 10px;
    font-size: 12px;
    padding: 2px 8px;
    background: #fef3c7;
    color: #d97706;
    font-weight: 600;
    border-radius: 9999px;
}
.map-readonly {
    pointer-events: none;
    opacity: 0.85;
}

/* ─── Court Type Request Dropdown ─── */
.req-parent-select .custom-options-container {
    max-height: 200px;
    overflow-y: auto;
}
</style>
