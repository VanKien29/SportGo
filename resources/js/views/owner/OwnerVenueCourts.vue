<template>
    <div class="venue-courts-container">
        <!-- Floating Add Button -->
        <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
            <button class="btn-float-add" type="button" :disabled="!cluster" @click="openCreateModal" title="Thêm sân con">
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Thêm sân con</span>
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state card">
            <div class="spinner"></div>
            <p>Đang tải danh sách sân con...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state card">
            <p class="error-message">{{ error }}</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="courts.length === 0" class="empty-state card">
            <p>Cụm sân này chưa có sân con nào.</p>
            <button class="btn btn-primary" @click="openCreateModal">
                Thêm sân con ngay
            </button>
        </div>

        <div v-else class="view-content-wrapper">
            <!-- Tabs Toggle Bar -->
            <div class="layout-toggle-tabs">
                <button
                    class="tab-btn"
                    :class="{ active: activeView === 'list' }"
                    @click="activeView = 'list'"
                >
                    <span>Danh sách sân con</span>
                </button>
                <button
                    class="tab-btn"
                    :class="{ active: activeView === 'layout' }"
                    @click="activeView = 'layout'"
                >
                    <span>Sắp xếp sơ đồ trực quan</span>
                </button>
            </div>

            <!-- Grid List of Courts (SaaS Grouped Compact Rows) -->
            <div v-if="activeView === 'list'" class="courts-list-wrapper">

                <!-- Grouped Content -->
                <div class="grouped-courts-list">
                    <div
                        v-for="group in groupedCourts"
                        :key="group.typeName"
                        class="court-type-group"
                    >
                        <div class="group-header">
                            <span class="group-title">{{ group.typeName.toUpperCase() }}</span>
                            <span class="group-divider"></span>
                            <span class="group-count">{{ group.courts.length }} sân</span>
                        </div>

                        <div class="group-items">
                            <div
                                v-for="court in group.courts"
                                :key="court.id"
                                class="court-row-item"
                                :class="{ 'status-inactive': court.status !== 'active' }"
                            >
                                <!-- Accent line indicator on hover (handled by CSS) -->
                                <div class="accent-line"></div>

                                <!-- Left side: Order & Name & Status badge -->
                                <div class="row-left">
                                    <span class="row-order">#{{ court.sort_order }}</span>
                                    <span class="row-name">{{ court.name }}</span>
                                    <span
                                        v-if="court.status !== 'active'"
                                        class="row-status-badge"
                                        :class="court.status"
                                    >
                                        {{ formatStatus(court.status) }}
                                    </span>
                                </div>

                                <!-- Middle side: Spatial position status -->
                                <div class="row-middle">
                                    <span v-if="court.layout_x !== null" class="spatial-status placed">
                                        <AppIcon name="circleCheck" size="13" />
                                        <span>Đã xếp ({{ formatToM(court.layout_x) }}m, {{ formatToM(court.layout_y) }}m)</span>
                                    </span>
                                    <button
                                        v-else
                                        type="button"
                                        class="btn-place-quick"
                                        @click="selectAndSwitchToLayout(court)"
                                    >
                                        <span>Chưa xếp &bull; Định vị ngay</span>
                                        <AppIcon name="chevronRight" size="12" />
                                    </button>
                                </div>

                                <!-- Right side: Action Buttons -->
                                <div class="row-right">
                                    <ActionIconButton
                                        icon="pencil"
                                        label="Sửa sân con"
                                        size="sm"
                                        @click="openEditModal(court)"
                                    />
                                    <ActionIconButton
                                        icon="trash"
                                        label="Xóa sân con"
                                        variant="danger"
                                        size="sm"
                                        @click="confirmDelete(court)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State for Search -->
                    <div v-if="groupedCourts.length === 0" class="empty-search-state">
                        <AppIcon name="alert" size="20" />
                        <span>Không tìm thấy sân con nào phù hợp với từ khóa.</span>
                    </div>
                </div>
            </div>

            <!-- Visual Layout Editor Workspace -->
            <div
                v-else-if="activeView === 'layout'"
                class="layout-editor-workspace"
            >
                <div class="editor-toolbar">
                    <div class="toolbar-left">

                        <button
                            class="btn btn-primary"
                            @click="saveLayout"
                            :disabled="savingLayout"
                        >
                            <span>{{
                                savingLayout ? "Đang lưu..." : "Lưu sơ đồ"
                            }}</span>
                        </button>
                        <button class="btn btn-outline" @click="autoArrange">
                            <span>Tự động sắp xếp</span>
                        </button>
                        <button
                            class="btn btn-outline btn-danger-outline"
                            @click="clearLayout"
                        >
                            <span>Xóa toàn bộ</span>
                        </button>
                    </div>
                    <div class="toolbar-right">
                        <span class="info-badge">
                            {{
                                editorTool === "select"
                                    ? "Chế độ Chọn — Click chọn, kéo để di chuyển"
                                    : "Chế độ Kéo — Kéo để di chuyển canvas"
                            }}
                        </span>
                    </div>
                </div>

                <div class="editor-body">
                    <!-- Canvas area -->
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
                        <!-- Zoom controls -->
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
                             Căn giữa
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
                            <!-- Grid Background inside canvas content -->
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

                            <!-- Placed Courts -->
                            <div
                                v-for="court in placedCourts"
                                :key="court.id"
                                class="canvas-court-element"
                                :class="{
                                    selected: selectedCourtId === court.id,
                                    dragging: draggingCourtId === court.id,
                                    resizing: resizingCourtId === court.id,
                                    'has-collision': collisions[court.id],
                                }"
                                :style="getCourtStyle(court)"
                                @mousedown.stop="startDrag($event, court)"
                                @click.stop="selectCourt(court)"
                                data-type="court"
                            >
                                <CourtVisual
                                    :name="court.name"
                                    :court-type-name="court.court_type?.name"
                                    status="active"
                                    :width="
                                        court.layout_w || getDefaultWidth(court)
                                    "
                                    :height="
                                        court.layout_h ||
                                        getDefaultHeight(court)
                                    "
                                    :rotation="court.layout_rotation || 0"
                                    :show-type="false"
                                />

                                <!-- Collision Warning Badge -->
                                <div
                                    v-if="collisions[court.id]"
                                    class="collision-badge"
                                    title="Sân đang bị chồng lấn!"
                                >
                                    Chồng lấp
                                </div>

                                <!-- Resize Handles -->
                                <template v-if="selectedCourtId === court.id">
                                    <div
                                        class="resize-handle tl"
                                        @mousedown.stop.prevent="
                                            startResize($event, court, 'tl')
                                        "
                                    ></div>
                                    <div
                                        class="resize-handle tr"
                                        @mousedown.stop.prevent="
                                            startResize($event, court, 'tr')
                                        "
                                    ></div>
                                    <div
                                        class="resize-handle bl"
                                        @mousedown.stop.prevent="
                                            startResize($event, court, 'bl')
                                        "
                                    ></div>
                                    <div
                                        class="resize-handle br"
                                        @mousedown.stop.prevent="
                                            startResize($event, court, 'br')
                                        "
                                    ></div>
                                </template>
                            </div>

                            <!-- Placed Decorations -->
                            <div
                                v-for="decor in decorations"
                                :key="decor.id"
                                class="canvas-decor-element"
                                :class="{
                                    selected: selectedDecorationId === decor.id,
                                    dragging: draggingDecorationId === decor.id,
                                    resizing: resizingDecorationId === decor.id,
                                }"
                                :style="getDecorStyle(decor)"
                                @mousedown.stop="startDragDecor($event, decor)"
                                @click.stop="selectDecor(decor)"
                                data-type="decor"
                            >
                                <DecorationVisual
                                    :type="decor.type"
                                    :name="decor.name"
                                    :width="decor.layout_w"
                                    :height="decor.layout_h"
                                    :rotation="decor.layout_rotation || 0"
                                />

                                <template
                                    v-if="selectedDecorationId === decor.id"
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

                    <!-- Sidebar (Unplaced + Inspector) -->
                    <div class="editor-sidebar">
                        <!-- Inspector Panel -->
                        <div
                            v-if="selectedCourt"
                            class="sidebar-section inspector-panel"
                        >
                            <h4 class="section-title">
                                Thông tin: {{ selectedCourt.name }}
                            </h4>

                            <!-- Inspector Warning Box for Collisions -->
                            <div
                                v-if="collisions[selectedCourt.id]"
                                class="inspector-warning-box"
                            >
                                Sân đang chồng lấn lên sân khác! Vui lòng
                                dịch chuyển hoặc thay đổi kích thước để tránh va
                                chạm.
                            </div>

                            <div class="inspector-fields">
                                <div class="field-row">
                                    <span class="label">BỘ MÔN:</span>
                                    <span class="value">{{
                                        selectedCourt.court_type?.name
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
                                                    $event.target.value,
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
                                                    $event.target.value,
                                                )
                                            "
                                            placeholder="Dọc"
                                        />
                                    </div>
                                </div>

                                <div class="field-group">
                                    <label
                                        >Vị trí cách lề Trái / Trên (m):</label
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
                                                    $event.target.value,
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
                                                    $event.target.value,
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
                                            selectedCourt.layout_rotation || 0
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
                                            @click="rotateSelected90"
                                        >
                                            <span>Xoay +90°</span>
                                        </button>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-outline btn-danger-outline btn-block"
                                    @click="unplaceCourt(selectedCourt)"
                                >
                                    <span>Gỡ khỏi sơ đồ</span>
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
                                    <span
                                        class="value font-bold uppercase"
                                        style="
                                            font-weight: 700;
                                            text-transform: uppercase;
                                        "
                                        >{{ selectedDecoration.type }}</span
                                    >
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
                                            v-model.number="
                                                selectedDecoration.layout_w
                                            "
                                            placeholder="Rộng"
                                            style="width: 70px"
                                        />
                                        <span class="x">x</span>
                                        <input
                                            type="number"
                                            v-model.number="
                                                selectedDecoration.layout_h
                                            "
                                            placeholder="Dài"
                                            style="width: 70px"
                                        />
                                    </div>
                                </div>
                                <div class="field-group">
                                    <label>Vị trí X / Y (px):</label>
                                    <div class="input-row">
                                        <input
                                            type="number"
                                            v-model.number="
                                                selectedDecoration.layout_x
                                            "
                                            placeholder="X"
                                            style="width: 70px"
                                        />
                                        <span class="comma">,</span>
                                        <input
                                            type="number"
                                            v-model.number="
                                                selectedDecoration.layout_y
                                            "
                                            placeholder="Y"
                                            style="width: 70px"
                                        />
                                    </div>
                                </div>
                                <div class="field-group">
                                    <label
                                        >Góc xoay:
                                        {{
                                            selectedDecoration.layout_rotation ||
                                            0
                                        }}°</label
                                    >
                                    <div class="rotation-control">
                                        <input
                                            type="range"
                                            min="0"
                                            max="359"
                                            v-model.number="
                                                selectedDecoration.layout_rotation
                                            "
                                            class="rotation-slider"
                                        />
                                        <button
                                            type="button"
                                            class="btn btn-outline btn-xs btn-rotate"
                                            @click="rotateSelectedDecor90"
                                        >
                                            <span>Xoay +90°</span>
                                        </button>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-outline btn-danger-outline btn-block"
                                    @click="
                                        deleteDecoration(selectedDecoration)
                                    "
                                >
                                    <span>Xóa khỏi sơ đồ</span>
                                </button>
                            </div>
                        </div>

                        <!-- Thư viện vật phẩm trang trí -->
                        <div class="sidebar-section decoration-library-section">
                            <h4 class="section-title">Thêm vật phẩm bổ trợ</h4>
                            <p class="section-desc">
                                Click để thêm các vật phẩm định vị không gian:
                            </p>
                            <div class="decor-library-grid">
                                <button
                                    type="button"
                                    class="btn-add-decor"
                                    @click="
                                        addDecoration('entrance', 'Cửa ra vào')
                                    "
                                >
                                    Cửa ra vào
                                </button>
                                <button
                                    type="button"
                                    class="btn-add-decor"
                                    @click="
                                        addDecoration('reception', 'Lễ tân')
                                    "
                                  >
                                    Quầy lễ tân
                                </button>
                                <button
                                    type="button"
                                    class="btn-add-decor"
                                    @click="addDecoration('restroom', 'WC')"
                                  >
                                    Nhà vệ sinh
                                </button>
                                <button
                                    type="button"
                                    class="btn-add-decor"
                                    @click="addDecoration('seating', 'Ghế chờ')"
                                  >
                                    Ghế ngồi chờ
                                </button>
                                <button
                                    type="button"
                                    class="btn-add-decor"
                                    @click="
                                        addDecoration('parking', 'Bãi đỗ xe')
                                    "
                                  >
                                    Bãi đỗ xe
                                </button>
                                <button
                                    type="button"
                                    class="btn-add-decor"
                                    @click="addDecoration('custom', 'Khác')"
                                  >
                                    Vật thể khác
                                </button>
                            </div>
                        </div>

                        <!-- Unplaced list -->
                        <div class="sidebar-section unplaced-list-section">
                            <h4 class="section-title">
                                Sân chưa xếp ({{ unplacedCourts.length }})
                            </h4>
                            <p class="section-desc">
                                Click vào sân để đưa vào bản đồ rồi kéo thả sắp
                                xếp:
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
                                    v-if="unplacedCourts.length === 0"
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

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
            <div class="modal card">
                <div class="modal-header">
                    <h3>
                        {{
                            editingId ? "Cập nhật sân con" : "Thêm sân con mới"
                        }}
                    </h3>
                    <button class="btn-close" @click="closeModal">
                        <AppIcon name="x" size="18" />
                    </button>
                </div>
                <form @submit.prevent="handleSubmit">
                    <div class="modal-body">
                        <div v-if="modalError" class="alert alert-danger">
                            {{ modalError }}
                        </div>

                        <div class="form-group">
                            <label for="court-name"
                                >Tên sân con
                                <span class="required">*</span></label
                            >
                            <input
                                id="court-name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                placeholder="Ví dụ: Sân số 1, Sân VIP 2..."
                                required
                            />
                        </div>

                        <div class="form-group">
                            <label
                                >Loại sân <span class="required">*</span></label
                            >
                            <div class="custom-select-wrapper">
                                <div
                                    class="custom-select-trigger"
                                    :class="{ active: showTypeDropdown }"
                                    @click.stop="
                                        showTypeDropdown = !showTypeDropdown
                                    "
                                >
                                    <span v-if="selectedCourtType">
                                        <span class="parent-name">{{
                                            getParentTypeName(selectedCourtType)
                                        }}</span>
                                        <span class="separator">/</span>
                                        <span class="child-name"
                                            >{{ selectedCourtType.name }} ({{
                                                selectedCourtType.player_count
                                            }}
                                            người)</span
                                        >
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
                                        <div class="custom-optgroup-label">
                                            {{ group.name }}
                                        </div>
                                        <div
                                            v-for="child in group.children"
                                            :key="child.id"
                                            class="custom-option"
                                            :class="{
                                                selected:
                                                    form.court_type_id ===
                                                    child.id,
                                            }"
                                            @click="selectCourtType(child)"
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
                                                    form.court_type_id ===
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

                        <div v-if="editingId" class="form-group">
                            <label for="court-status"
                                >Trạng thái sân
                                <span class="required">*</span></label
                            >
                            <select
                                id="court-status"
                                v-model="form.status"
                                class="form-control"
                                required
                            >
                                <option value="active">Đang hoạt động</option>
                                <option value="inactive">
                                    Tạm ngưng hoạt động
                                </option>
                                <option value="maintenance">Bảo trì</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="sort-order">Thứ tự hiển thị</label>
                            <input
                                id="sort-order"
                                v-model.number="form.sort_order"
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
                            @click="closeModal"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="submitting"
                        >
                            {{ submitting ? "Đang lưu..." : "Lưu lại" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import CourtVisual from "../../components/CourtVisual.vue";
import DecorationVisual from "../../components/DecorationVisual.vue";
import { venueClusterService } from "../../services/venueClusters";
import { courtTypeService } from "../../services/courtTypes";

export default {
    name: "OwnerVenueCourts",
    components: { ActionIconButton, AppIcon, CourtVisual, DecorationVisual },
    data() {
        return {
            clusterId:
                this.$route.query.venue_cluster_id ||
                localStorage.getItem("selected_cluster") ||
                "",
            cluster: null,
            courts: [],
            courtTypes: [],
            loading: true,
            error: null,
            showModal: false,
            editingId: null,
            submitting: false,
            modalError: null,
            form: {
                name: "",
                court_type_id: "",
                status: "active",
                sort_order: 1,
            },
            showTypeDropdown: false,
            activeView: "list",
            searchQuery: "",
            selectedCourtId: null,
            draggingCourtId: null,
            dragStartX: 0,
            dragStartY: 0,
            savingLayout: false,
            resizingCourtId: null,
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
            editorTool: "select",
            originalCourtState: null,
            decorations: [],
            selectedDecorationId: null,
            draggingDecorationId: null,
            resizingDecorationId: null,
            showScrollTop: false,
            activeGuidelines: { x: [], y: [] },
        };
    },
    computed: {
        groupedCourts() {
            const filtered = this.courts.filter((c) => {
                if (!this.searchQuery) return true;
                return c.name.toLowerCase().includes(this.searchQuery.toLowerCase());
            });

            const groups = {};
            filtered.forEach((court) => {
                const typeName = court.court_type?.name || "Khác";
                if (!groups[typeName]) {
                    groups[typeName] = [];
                }
                groups[typeName].push(court);
            });

            return Object.keys(groups).map((typeName) => {
                return {
                    typeName,
                    courts: groups[typeName].sort((a, b) => a.sort_order - b.sort_order),
                };
            });
        },
        selectedCourtType() {
            return this.courtTypes.find(
                (t) => t.id === this.form.court_type_id,
            );
        },
        groupedCourtTypes() {
            // Tìm các danh mục cha (parent_id là null)
            const parents = this.courtTypes.filter((t) => !t.parent_id);

            const groups = parents.map((parent) => {
                return {
                    id: parent.id,
                    name: parent.name,
                    // Lọc danh sách con thuộc cha này
                    children: this.courtTypes.filter(
                        (t) => t.parent_id === parent.id,
                    ),
                };
            });

            // Chỉ hiển thị các nhóm bộ môn có cấu hình sân con
            return groups.filter((g) => g.children.length > 0);
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
        selectedCourt() {
            return (
                this.courts.find((c) => c.id === this.selectedCourtId) || null
            );
        },
        selectedDecoration() {
            return (
                this.decorations.find(
                    (d) => d.id === this.selectedDecorationId,
                ) || null
            );
        },
        collisions() {
            const collisionMap = {};
            const placed = this.placedCourts;
            for (let i = 0; i < placed.length; i++) {
                const courtA = placed[i];
                const polyA = this.getVertices(courtA);
                for (let j = i + 1; j < placed.length; j++) {
                    const courtB = placed[j];
                    const polyB = this.getVertices(courtB);
                    if (this.polygonsIntersect(polyA, polyB)) {
                        collisionMap[courtA.id] = true;
                        collisionMap[courtB.id] = true;
                    }
                }
            }
            return collisionMap;
        },
    },
    methods: {
        async initData() {
            this.loading = true;
            this.error = null;
            try {
                if (!this.clusterId) {
                    const clustersRes = await venueClusterService.getClusters();
                    this.clusterId = clustersRes.data?.[0]?.id || "";
                }

                if (!this.clusterId) {
                    throw new Error("Thiếu mã cụm sân (venue_cluster_id).");
                }

                localStorage.setItem("selected_cluster", this.clusterId);

                // Tải chi tiết cụm sân
                const clusterRes = await venueClusterService.getClusterDetails(
                    this.clusterId,
                );
                this.cluster = clusterRes.data;

                // Tải vật phẩm bổ trợ
                this.decorations = Array.isArray(
                    this.cluster?.layout_decorations,
                )
                    ? JSON.parse(
                          JSON.stringify(this.cluster.layout_decorations),
                      )
                    : [];

                // Tải danh sách sân con
                const courtsRes = await venueClusterService.getCourts(
                    this.clusterId,
                );
                this.courts = courtsRes.data || [];

                // Tải danh mục loại sân
                const courtTypesRes = await courtTypeService.getAll();
                this.courtTypes = courtTypesRes.data || [];
            } catch (err) {
                this.error = err.message || "Lỗi khởi tạo dữ liệu.";
            } finally {
                this.loading = false;
            }
        },
        formatStatus(status) {
            const map = {
                active: "Đang hoạt động",
                inactive: "Tạm khóa",
                maintenance: "Bảo trì",
            };
            return map[status] || status;
        },
        selectAndSwitchToLayout(court) {
            this.activeView = "layout";
            if (court.layout_x === null || court.layout_y === null) {
                this.placeCourt(court);
            } else {
                this.selectedCourtId = court.id;
            }
            this.$nextTick(() => {
                this.fitView();
            });
        },
        openCreateModal() {
            this.editingId = null;
            this.modalError = null;
            this.showTypeDropdown = false;
            this.form = {
                name: "",
                court_type_id: "",
                status: "active",
                sort_order: this.courts.length + 1,
            };
            this.showModal = true;
        },
        openEditModal(court) {
            this.editingId = court.id;
            this.modalError = null;
            this.showTypeDropdown = false;
            this.form = {
                name: court.name,
                court_type_id: court.court_type_id,
                status: court.status,
                sort_order: court.sort_order,
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.editingId = null;
            this.modalError = null;
            this.showTypeDropdown = false;
        },
        async handleSubmit() {
            this.submitting = true;
            this.modalError = null;
            if (!this.form.court_type_id) {
                this.modalError = "Vui lòng chọn loại sân.";
                this.submitting = false;
                return;
            }
            try {
                if (this.editingId) {
                    await venueClusterService.updateCourt(this.editingId, {
                        name: this.form.name,
                        court_type_id: this.form.court_type_id,
                        status: this.form.status,
                        sort_order: this.form.sort_order,
                    });
                } else {
                    await venueClusterService.createCourt({
                        venue_cluster_id: this.clusterId,
                        court_type_id: this.form.court_type_id,
                        name: this.form.name,
                        sort_order: this.form.sort_order,
                    });
                }
                await this.initData();
                this.closeModal();
            } catch (err) {
                this.modalError = err.message || "Lỗi lưu dữ liệu sân con.";
            } finally {
                this.submitting = false;
            }
        },
        getParentTypeName(child) {
            if (!child.parent_id) return "";
            const parent = this.courtTypes.find(
                (t) => t.id === child.parent_id,
            );
            return parent ? parent.name : "";
        },
        selectCourtType(child) {
            this.form.court_type_id = child.id;
            this.showTypeDropdown = false;
        },
        handleOutsideClick(e) {
            const el = this.$el.querySelector(".custom-select-wrapper");
            if (el && !el.contains(e.target)) {
                this.showTypeDropdown = false;
            }
        },
        handleOwnerClusterChanged(event) {
            const clusterId = event.detail?.id;
            if (!clusterId || String(clusterId) === String(this.clusterId))
                return;
            this.clusterId = clusterId;
            this.initData();
        },
        async confirmDelete(court) {
            if (
                confirm(`Bạn có chắc chắn muốn xóa sân "${court.name}" không?`)
            ) {
                try {
                    await venueClusterService.deleteCourt(court.id);
                    await this.initData();
                } catch (err) {
                    alert(err.message || "Không thể xóa sân con.");
                }
            }
        },
        placeCourt(court) {
            court.layout_w = this.getDefaultWidth(court);
            court.layout_h = this.getDefaultHeight(court);
            court.layout_rotation = 0;

            const rect = this.$refs.canvasViewport?.getBoundingClientRect();
            const centerX = rect ? rect.width / 2 : 500;
            const centerY = rect ? rect.height / 2 : 300;
            let startX = (centerX - this.panX) / this.zoom - court.layout_w / 2;
            let startY = (centerY - this.panY) / this.zoom - court.layout_h / 2;

            court.layout_x = startX;
            court.layout_y = startY;

            let attempts = 0;
            while (this.checkCollisionWithOthers(court) && attempts < 50) {
                court.layout_x += 30;
                court.layout_y += 30;
                attempts++;
            }

            this.selectedCourtId = court.id;
        },
        unplaceCourt(court) {
            court.layout_x = null;
            court.layout_y = null;
            if (this.selectedCourtId === court.id) {
                this.selectedCourtId = null;
            }
        },
        getDefaultWidth(court) {
            if (court?.court_type?.default_layout_w) {
                return court.court_type.default_layout_w;
            }
            return 800; // Fallback an toàn nếu admin chưa nhập
        },
        getDefaultHeight(court) {
            if (court?.court_type?.default_layout_h) {
                return court.court_type.default_layout_h;
            }
            return 800; // Fallback an toàn nếu admin chưa nhập
        },
        formatToM(val) {
            if (val === null || val === undefined) return 0;
            return Math.round(val) / 100;
        },
        updateW(court, value) {
            const parsed = parseFloat(value);
            court.layout_w = isNaN(parsed) ? 0 : parsed * 100;
            this.validateSize(court);
        },
        updateH(court, value) {
            const parsed = parseFloat(value);
            court.layout_h = isNaN(parsed) ? 0 : parsed * 100;
            this.validateSize(court);
        },
        updateX(court, value) {
            const parsed = parseFloat(value);
            court.layout_x = isNaN(parsed) ? 0 : parsed * 100;
            this.validateCoords(court);
        },
        updateY(court, value) {
            const parsed = parseFloat(value);
            court.layout_y = isNaN(parsed) ? 0 : parsed * 100;
            this.validateCoords(court);
        },
        getVertices(court) {
            const w = court.layout_w || this.getDefaultWidth(court);
            const h = court.layout_h || this.getDefaultHeight(court);
            const cx = (court.layout_x || 0) + w / 2;
            const cy = (court.layout_y || 0) + h / 2;
            const angle = ((court.layout_rotation || 0) * Math.PI) / 180;

            const cos = Math.cos(angle);
            const sin = Math.sin(angle);

            const points = [
                { x: -w / 2, y: -h / 2 },
                { x: w / 2, y: -h / 2 },
                { x: w / 2, y: h / 2 },
                { x: -w / 2, y: h / 2 },
            ];

            return points.map((p) => ({
                x: cx + p.x * cos - p.y * sin,
                y: cy + p.x * sin + p.y * cos,
            }));
        },
        polygonsIntersect(polyA, polyB) {
            const getEdges = (poly) => {
                const edges = [];
                for (let i = 0; i < poly.length; i++) {
                    const p1 = poly[i];
                    const p2 = poly[(i + 1) % poly.length];
                    edges.push({ x: p2.x - p1.x, y: p2.y - p1.y });
                }
                return edges;
            };

            const getNormals = (edges) => {
                return edges.map((edge) => ({ x: -edge.y, y: edge.x }));
            };

            const project = (poly, axis) => {
                const length = Math.sqrt(axis.x * axis.x + axis.y * axis.y);
                const ax = axis.x / length;
                const ay = axis.y / length;
                let min = Infinity;
                let max = -Infinity;
                for (const p of poly) {
                    const dot = p.x * ax + p.y * ay;
                    if (dot < min) min = dot;
                    if (dot > max) max = dot;
                }
                return { min, max };
            };

            const edgesA = getEdges(polyA);
            const edgesB = getEdges(polyB);
            const axes = [...getNormals(edgesA), ...getNormals(edgesB)];

            for (const axis of axes) {
                const projA = project(polyA, axis);
                const projB = project(polyB, axis);
                if (
                    projA.max <= projB.min + 0.1 ||
                    projB.max <= projA.min + 0.1
                ) {
                    return false;
                }
            }
            return true;
        },
        checkCollisionWithOthers(targetCourt) {
            const polyA = this.getVertices(targetCourt);
            for (const court of this.placedCourts) {
                if (court.id === targetCourt.id) continue;
                const polyB = this.getVertices(court);
                if (this.polygonsIntersect(polyA, polyB)) {
                    return true;
                }
            }
            return false;
        },
        rotateSelected90() {
            const court = this.selectedCourt;
            if (court) {
                court.layout_rotation =
                    ((court.layout_rotation || 0) + 90) % 360;
            }
        },
        getCourtStyle(court) {
            return {
                left: `${court.layout_x}px`,
                top: `${court.layout_y}px`,
                width: `${court.layout_w || this.getDefaultWidth(court)}px`,
                height: `${court.layout_h || this.getDefaultHeight(court)}px`,
            };
        },
        getLogicalCoords(event) {
            const rect = this.$refs.canvasViewport?.getBoundingClientRect();
            if (!rect) return { x: 0, y: 0 };
            const mouseX = event.clientX - rect.left;
            const mouseY = event.clientY - rect.top;
            return {
                x: (mouseX - this.panX) / this.zoom,
                y: (mouseY - this.panY) / this.zoom,
            };
        },
        handleCanvasKeydown(e) {
            const tag = document.activeElement?.tagName;
            if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT")
                return;
            if (e.key === "v" || e.key === "V") {
                this.editorTool = "select";
                return;
            }
            if (e.key === "h" || e.key === "H") {
                this.editorTool = "pan";
                return;
            }
            if (e.key === "Escape") {
                this.selectedCourtId = null;
                this.selectedDecorationId = null;
                return;
            }
            if (
                (e.key === "Delete" || e.key === "Backspace") &&
                this.selectedDecorationId
            ) {
                const decor = this.selectedDecoration;
                if (decor) this.deleteDecoration(decor);
                return;
            }

            // Figma-style Arrow Keys Nudging
            if (
                e.key === "ArrowUp" ||
                e.key === "ArrowDown" ||
                e.key === "ArrowLeft" ||
                e.key === "ArrowRight"
            ) {
                e.preventDefault();
                const amount = e.shiftKey ? 10 : 1;
                let dx = 0;
                let dy = 0;
                if (e.key === "ArrowLeft") dx = -amount;
                if (e.key === "ArrowRight") dx = amount;
                if (e.key === "ArrowUp") dy = -amount;
                if (e.key === "ArrowDown") dy = amount;

                if (this.selectedCourtId) {
                    const court = this.courts.find(
                        (c) => c.id === this.selectedCourtId,
                    );
                    if (court) {
                        court.layout_x = (court.layout_x || 0) + dx;
                        court.layout_y = (court.layout_y || 0) + dy;
                    }
                } else if (this.selectedDecorationId) {
                    const decor = this.decorations.find(
                        (d) => d.id === this.selectedDecorationId,
                    );
                    if (decor) {
                        decor.layout_x = (decor.layout_x || 0) + dx;
                        decor.layout_y = (decor.layout_y || 0) + dy;
                    }
                }
            }
        },
        onCanvasClick(e) {
            if (this.editorTool === "select") {
                // Kiểm tra xem click có nằm trong court/decor không bằng data-type attribute
                // (dùng closest để traverse lên dù scoped CSS)
                const hitDecor = e.target.closest('[data-type="decor"]');
                const hitCourt = e.target.closest('[data-type="court"]');
                const hitZoom = e.target.closest(".zoom-controls");
                const hitResize = e.target.closest(".resize-handle");
                if (!hitDecor && !hitCourt && !hitZoom && !hitResize) {
                    this.selectedCourtId = null;
                    this.selectedDecorationId = null;
                }
            }
        },
        startPan(e) {
            if (e.target.closest(".zoom-controls")) return;
            if (this.editorTool === "pan") {
                this.isPanning = true;
                this.panStartX = e.clientX - this.panX;
                this.panStartY = e.clientY - this.panY;
                return;
            }
            // Mode Select: chỉ pan khi click vào nền trống
            if (
                e.target.closest('[data-type="court"]') ||
                e.target.closest('[data-type="decor"]') ||
                e.target.closest(".resize-handle")
            )
                return;
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
            if (this.draggingCourtId || this.resizingCourtId) {
                this.endDrag();
            }
            if (this.draggingDecorationId || this.resizingDecorationId) {
                this.endDragDecor();
            }
        },
        handleZoom(e) {
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            this.setZoom(this.zoom + delta, e.clientX, e.clientY);
        },
        setZoom(val, clientX = null, clientY = null) {
            const newZoom = Math.max(0.2, Math.min(3, val));
            if (newZoom === this.zoom) return;

            const rect = this.$refs.canvasViewport?.getBoundingClientRect();
            if (!rect) {
                this.zoom = newZoom;
                return;
            }

            const targetX =
                clientX !== null ? clientX - rect.left : rect.width / 2;
            const targetY =
                clientY !== null ? clientY - rect.top : rect.height / 2;

            const logicalX = (targetX - this.panX) / this.zoom;
            const logicalY = (targetY - this.panY) / this.zoom;

            this.zoom = newZoom;
            this.panX = targetX - logicalX * this.zoom;
            this.panY = targetY - logicalY * this.zoom;
        },
        resetView() {
            this.zoom = 1;
            this.panX = 0;
            this.panY = 0;
        },
        startResize(event, court, direction) {
            this.resizingCourtId = court.id;
            this.resizeDirection = direction;
            const logical = this.getLogicalCoords(event);
            this.dragStartX = logical.x;
            this.dragStartY = logical.y;
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
            this.selectedDecorationId = null;
            const logical = this.getLogicalCoords(event);
            this.dragStartX = logical.x - (court.layout_x || 0);
            this.dragStartY = logical.y - (court.layout_y || 0);
        },
        handleDrag(event) {
            if (this.resizingCourtId) {
                const court = this.courts.find(
                    (c) => c.id === this.resizingCourtId,
                );
                if (!court) return;

                const logical = this.getLogicalCoords(event);
                const dx = logical.x - this.dragStartX;
                const dy = logical.y - this.dragStartY;

                if (this.resizeDirection === "br") {
                    court.layout_w = Math.max(30, this.resizeStartW + dx);
                    court.layout_h = Math.max(30, this.resizeStartH + dy);
                } else if (this.resizeDirection === "bl") {
                    const newW = this.resizeStartW - dx;
                    if (newW >= 30) {
                        court.layout_x = this.resizeStartXCoord + dx;
                        court.layout_w = newW;
                    }
                    court.layout_h = Math.max(30, this.resizeStartH + dy);
                } else if (this.resizeDirection === "tr") {
                    court.layout_w = Math.max(30, this.resizeStartW + dx);
                    const newH = this.resizeStartH - dy;
                    if (newH >= 30) {
                        court.layout_y = this.resizeStartYCoord + dy;
                        court.layout_h = newH;
                    }
                } else if (this.resizeDirection === "tl") {
                    const newW = this.resizeStartW - dx;
                    const newH = this.resizeStartH - dy;
                    if (newW >= 30) {
                        court.layout_x = this.resizeStartXCoord + dx;
                        court.layout_w = newW;
                    }
                    if (newH >= 30) {
                        court.layout_y = this.resizeStartYCoord + dy;
                        court.layout_h = newH;
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

            const logical = this.getLogicalCoords(event);
            let newX = logical.x - this.dragStartX;
            let newY = logical.y - this.dragStartY;

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
        selectCourt(court) {
            this.selectedCourtId = court.id;
            this.selectedDecorationId = null;
        },
        validateSize(court) {
            if (!court) return;
            if (court.layout_w < 10) court.layout_w = 10;
            if (court.layout_h < 10) court.layout_h = 10;
        },
        async saveLayout() {
            const collisionCount = Object.keys(this.collisions).length;
            if (collisionCount > 0) {
                if (
                    !confirm(
                        `Phát hiện có một số sân đang bị chồng lấn nhau (hiển thị màu đỏ). Bạn có chắc chắn vẫn muốn lưu sơ đồ này không?`,
                    )
                ) {
                    return;
                }
            }
            this.savingLayout = true;
            try {
                const layoutData = {
                    venue_cluster_id: this.clusterId,
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
                };
                await venueClusterService.updateCourtsLayout(layoutData);
                alert(
                    "Sơ đồ sân con và vật phẩm bổ trợ đã được lưu thành công.",
                );
                if (this.cluster) {
                    this.cluster.layout_decorations = JSON.parse(
                        JSON.stringify(this.decorations),
                    );
                }
                await this.initData();
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
            if (type === "entrance") {
                defaultW = 120;
                defaultH = 60;
            } else if (type === "reception") {
                defaultW = 120;
                defaultH = 80;
            } else if (type === "restroom") {
                defaultW = 80;
                defaultH = 80;
            } else if (type === "seating") {
                defaultW = 120;
                defaultH = 50;
            }

            const newDecor = {
                id:
                    "decor_" +
                    Date.now() +
                    "_" +
                    Math.random().toString(36).substr(2, 9),
                type: type,
                name: defaultName,
                layout_x: Math.round(
                    (cx - this.panX) / this.zoom - defaultW / 2,
                ),
                layout_y: Math.round(
                    (cy - this.panY) / this.zoom - defaultH / 2,
                ),
                layout_w: defaultW,
                layout_h: defaultH,
                layout_rotation: 0,
            };

            this.decorations.push(newDecor);
            this.selectedDecorationId = newDecor.id;
            this.selectedCourtId = null;
        },
        rotateSelectedDecor90() {
            const decor = this.selectedDecoration;
            if (decor) {
                decor.layout_rotation =
                    ((decor.layout_rotation || 0) + 90) % 360;
            }
        },
        deleteDecoration(decor) {
            console.log("deleteDecoration called with:", decor);
            if (!decor) {
                console.warn("deleteDecoration: decor is null or undefined!");
                return;
            }
            this.decorations = this.decorations.filter(
                (d) => d.id !== decor.id,
            );
            if (this.selectedDecorationId === decor.id) {
                this.selectedDecorationId = null;
            }
            console.log("decorations after delete:", this.decorations);
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
                confirm(
                    "Bạn có chắc chắn muốn tự động sắp xếp tất cả các sân không? Thao tác này sẽ ghi đè các vị trí hiện tại.",
                )
            ) {
                let currentX = 50;
                let currentY = 50;
                let maxRowH = 0;

                this.courts.forEach((court, index) => {
                    const w = this.getDefaultWidth(court);
                    const h = this.getDefaultHeight(court);

                    if (currentX + w > 1500) {
                        currentX = 50;
                        currentY += maxRowH + 80;
                        maxRowH = 0;
                    }

                    court.layout_w = w;
                    court.layout_h = h;
                    court.layout_x = currentX;
                    court.layout_y = currentY;
                    court.layout_rotation = 0;

                    currentX += w + 80;
                    if (h > maxRowH) maxRowH = h;
                });

                this.$nextTick(() => {
                    this.fitView();
                });
            }
        },
        fitView() {
            const placed = this.placedCourts;
            if (placed.length === 0) {
                this.resetView();
                return;
            }

            let minX = Infinity;
            let minY = Infinity;
            let maxX = -Infinity;
            let maxY = -Infinity;

            placed.forEach((court) => {
                const vertices = this.getVertices(court);
                vertices.forEach((v) => {
                    if (v.x < minX) minX = v.x;
                    if (v.y < minY) minY = v.y;
                    if (v.x > maxX) maxX = v.x;
                    if (v.y > maxY) maxY = v.y;
                });
            });

            const padding = 60;
            const contentW = maxX - minX + padding * 2;
            const contentH = maxY - minY + padding * 2;

            const viewport = this.$refs.canvasViewport;
            if (!viewport) return;
            const viewW = viewport.clientWidth;
            const viewH = viewport.clientHeight;

            const zoomX = viewW / contentW;
            const zoomY = viewH / contentH;
            let newZoom = Math.min(zoomX, zoomY);

            newZoom = Math.max(0.35, Math.min(1.5, newZoom));
            this.zoom = newZoom;

            const centerLogicalX = minX + (maxX - minX) / 2;
            const centerLogicalY = minY + (maxY - minY) / 2;

            this.panX = viewW / 2 - centerLogicalX * this.zoom;
            this.panY = viewH / 2 - centerLogicalY * this.zoom;
        },
        clearLayout() {
            if (
                confirm(
                    "Bạn có muốn gỡ bỏ toàn bộ sân con khỏi sơ đồ hiện tại không?",
                )
            ) {
                this.courts.forEach((court) => {
                    court.layout_x = null;
                    court.layout_y = null;
                });
                this.selectedCourtId = null;
            }
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
        },
    },
    mounted() {
        document.addEventListener("click", this.handleOutsideClick);
        window.addEventListener(
            "owner-cluster-changed",
            this.handleOwnerClusterChanged,
        );
        window.addEventListener("keydown", this.handleCanvasKeydown);
        window.addEventListener("scroll", this.handleScroll);
    },
    beforeUnmount() {
        document.removeEventListener("click", this.handleOutsideClick);
        window.removeEventListener(
            "owner-cluster-changed",
            this.handleOwnerClusterChanged,
        );
        window.removeEventListener("keydown", this.handleCanvasKeydown);
        window.removeEventListener("scroll", this.handleScroll);
    },
    created() {
        this.initData();
    },
};
</script>

<style scoped>
.venue-courts-container {
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
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    padding: 24px;
}

.header-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.header-left {
    display: flex;
    flex-direction: column;
}

.btn-back {
    color: var(--admin-muted);
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 8px;
    transition: color 0.2s ease;
}

.btn-back:hover {
    color: var(--admin-text);
}

.header-left h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--admin-text);
    margin: 0;
}

.subtitle {
    margin-top: 4px;
    color: var(--admin-muted);
    font-size: 14px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-primary {
    background: #000000;
    border: 1px solid #000000;
    color: #fff;
}

.btn-primary:hover {
    background: #222222;
    border-color: var(--admin-text);
}

.btn-outline {
    border: 1px solid var(--admin-border);
    background: transparent;
    color: var(--admin-text);
}

.btn-outline:hover {
    background: var(--admin-surface-muted);
}

.btn-danger-outline {
    border: 1px solid rgba(0, 0, 0, 0.15);
    background: transparent;
    color: var(--admin-text);
}

.btn-danger-outline:hover {
    background: rgba(0, 0, 0, 0.05);
    border-color: var(--admin-faint);
    color: var(--admin-text);
}

/* SaaS Grouped Compact Rows */
.courts-list-wrapper {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: 100%;
}

.command-search-bar {
    max-width: 360px;
    width: 100%;
}

.grouped-courts-list {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.court-type-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.group-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 4px;
    user-select: none;
}

.group-title {
    font-size: 11px;
    font-weight: 750;
    color: var(--admin-muted);
    letter-spacing: 0.06em;
}

.group-divider {
    flex: 1;
    height: 1px;
    background: var(--admin-hover);
}

.group-count {
    font-size: 11px;
    font-weight: 700;
    color: var(--admin-faint);
}

.group-items {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.court-row-item {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 52px;
    padding: 0 16px;
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border-soft);
    border-radius: 8px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.court-row-item:hover {
    background: var(--admin-hover);
    border-color: var(--admin-faint);
    transform: translateX(2px);
}

.accent-line {
    position: absolute;
    left: 0;
    top: 15%;
    bottom: 15%;
    width: 2.5px;
    background: #000000;
    border-radius: 0 2px 2px 0;
    opacity: 0;
    transform: scaleY(0.7);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.court-row-item:hover .accent-line {
    opacity: 1;
    transform: scaleY(1);
}

.row-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.row-order {
    font-size: 12px;
    font-weight: 700;
    color: var(--admin-faint);
    font-family: monospace;
}

.row-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--admin-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: opacity 0.2s ease;
}

.court-row-item.status-inactive .row-name {
    opacity: 0.5;
}

.row-status-badge {
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

.row-status-badge.inactive {
    background: var(--admin-hover);
    color: var(--admin-muted);
}

.row-status-badge.maintenance {
    background: rgba(245, 158, 11, 0.08);
    color: #d97706;
}

.row-middle {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex: 1;
    padding: 0 24px;
}

.spatial-status.placed {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #16a34a;
    font-size: 12.5px;
    font-weight: 600;
}

.btn-place-quick {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    padding: 0;
    color: #d97706;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-place-quick:hover {
    color: #b45309;
}

.btn-place-quick:hover .app-icon {
    transform: translateX(2px);
}

.btn-place-quick .app-icon {
    transition: transform 0.2s ease;
    color: inherit;
}

.row-right {
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transform: translateX(6px);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.court-row-item:hover .row-right {
    opacity: 1;
    transform: translateX(0);
}

.empty-search-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 16px;
    color: var(--admin-muted);
    gap: 12px;
}

.empty-search-state span {
    font-size: 13.5px;
    font-weight: 600;
}

/* Responsive Styles for SaaS Rows */
@media (max-width: 768px) {
    .court-row-item {
        height: auto;
        padding: 12px 14px;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .accent-line {
        top: 0;
        bottom: 0;
        width: 3px;
        height: auto;
    }

    .row-middle {
        padding: 0;
        margin-left: 24px;
    }

    .row-right {
        opacity: 1;
        transform: none;
        justify-content: flex-end;
        border-top: 1px dashed var(--admin-border-soft);
        padding-top: 8px;
    }
}

/* Modal Styling */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 1000;
    padding: 20px;
}

.modal {
    width: 100%;
    max-width: 500px;
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
    padding: 0;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--sg-border);
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 800;
    margin: 0;
    color: var(--admin-text);
}

.btn-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--admin-muted);
}

.modal-body {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 13px;
    font-weight: 700;
    color: var(--admin-text);
}

.required {
    color: #ef4444;
}

.form-control {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--admin-border);
    font-size: 14px;
    color: var(--admin-text);
    outline: none;
    transition: border-color 0.2s ease;
}

.form-control:focus {
    border-color: var(--admin-text);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid var(--sg-border);
    background: var(--admin-surface-muted);
}

.alert-danger {
    background: var(--admin-surface-muted);
    color: #ef4444;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid #e5e7eb;
}

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
    color: var(--admin-muted);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(0, 0, 0, 0.1);
    border-top-color: var(--admin-text);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Custom Select Dropdown Styling */
.custom-select-wrapper {
    position: relative;
    width: 100%;
}

.custom-select-trigger {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: var(--admin-surface, #fff);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    font-size: 14px;
    color: var(--admin-text);
    cursor: pointer;
    user-select: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.custom-select-trigger:hover {
    border-color: var(--admin-text);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.custom-select-trigger.active {
    border-color: var(--admin-text);
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
}

.custom-select-trigger .parent-name {
    color: var(--admin-muted);
    font-weight: 500;
}

.custom-select-trigger .separator {
    margin: 0 6px;
    color: var(--admin-faint);
}

.custom-select-trigger .child-name {
    font-weight: 700;
    color: var(--admin-text);
}

.custom-select-trigger .placeholder {
    color: var(--admin-muted);
}

.custom-select-trigger .arrow {
    font-size: 10px;
    color: var(--admin-muted);
    transition: transform 0.2s ease;
}

.custom-select-trigger.active .arrow {
    transform: rotate(180deg);
}

/* Dropdown Container */
.custom-options-container {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    z-index: 100;
    max-height: 250px;
    overflow-y: auto;
    opacity: 0;
    transform: translateY(-8px);
    animation: slideDown 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes slideDown {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Optgroup Styling */
.custom-optgroup-label {
    padding: 10px 14px 6px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-muted);
    background: var(--admin-hover);
    border-bottom: 1px solid rgba(0, 0, 0, 0.02);
}

/* Option Styling */
.custom-option {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    cursor: pointer;
    font-size: 13.5px;
    color: var(--admin-text);
    transition:
        background 0.15s ease,
        color 0.15s ease;
}

.custom-option:hover {
    background: rgba(0, 0, 0, 0.03);
}

.custom-option.selected {
    background: rgba(0, 0, 0, 0.05);
    font-weight: 700;
}

.custom-option .option-text {
    font-weight: 600;
}

.custom-option .option-details {
    margin-left: 6px;
    font-size: 12px;
    color: var(--admin-muted);
}

.custom-option .check-mark {
    margin-left: auto;
    color: var(--admin-text);
    font-weight: 900;
}

/* Layout Editor CSS */
.layout-toggle-tabs {
    display: flex;
    gap: 12px;
    padding-bottom: 2px;
    margin-bottom: 24px;
}

.tab-btn {
    background: none;
    border: none;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 700;
    color: var(--admin-muted);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    outline: none;
}

.tab-btn:hover {
    color: var(--admin-text);
    border-bottom-color: var(--admin-faint);
}

.tab-btn.active {
    color: var(--admin-text);
    border-bottom-color: var(--admin-text);
}

.badge-placed {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
}

.badge-unplaced {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
}

.layout-editor-workspace {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.editor-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border);
    padding: 12px 20px;
    border-radius: 12px;
}

.toolbar-left {
    display: flex;
    gap: 10px;
}

.info-badge {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--admin-muted);
}
/* ── Tool Switcher ── */
.tool-switcher {
    display: flex;
    background: var(--admin-surface-muted);
    border: 1.5px solid var(--admin-border);
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
    color: var(--admin-muted);
    transition: all 0.15s;
}
.tool-btn:hover {
    background: var(--admin-border);
    color: var(--admin-text);
}
.tool-btn.active {
    background: var(--admin-surface, #fff);
    color: #3b82f6;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}
.toolbar-divider {
    width: 1px;
    height: 28px;
    background: var(--admin-border);
    align-self: center;
    margin: 0 2px;
}

.editor-body {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.canvas-viewport {
    position: relative;
    flex: 1;
    min-width: 0;
    height: 600px;
    background-color: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 16px;
    box-shadow:
        inset 0 2px 8px rgba(0, 0, 0, 0.04),
        0 4px 12px rgba(0, 0, 0, 0.02);
    overflow: hidden;
    cursor: default;
    user-select: none;
}
.canvas-viewport.tool-select {
    cursor: default;
}
.canvas-viewport.tool-pan {
    cursor: grab;
}
.canvas-viewport.tool-pan:active,
.canvas-viewport.tool-pan.panning {
    cursor: grabbing;
}
.canvas-viewport.tool-select.panning {
    cursor: grabbing;
}

.canvas-content {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.canvas-court-element {
    pointer-events: auto;
}

.zoom-controls {
    position: absolute;
    bottom: 20px;
    right: 20px;
    display: flex;
    align-items: center;
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 100;
    overflow: hidden;
}

.btn-zoom {
    background: none;
    border: none;
    padding: 8px 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    color: var(--admin-text);
    transition: background 0.2s;
}

.btn-zoom:hover {
    background: var(--admin-surface-muted);
}

.btn-zoom.reset {
    font-size: 13px;
    border-left: 1px solid var(--sg-border);
}

.zoom-level {
    font-size: 13px;
    font-weight: 700;
    padding: 0 10px;
    color: var(--admin-text);
    min-width: 48px;
    text-align: center;
}

.canvas-grid-bg {
    position: absolute;
    width: 10000px;
    height: 10000px;
    left: -5000px;
    top: -5000px;
    background-image:
        linear-gradient(to right, rgba(15, 23, 42, 0.04) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(15, 23, 42, 0.04) 1px, transparent 1px);
    background-size: 30px 30px;
    pointer-events: none;
    z-index: 1;
}

.canvas-guideline {
    position: absolute;
    pointer-events: none;
    z-index: 99;
}
.canvas-guideline.vertical {
    top: -5000px;
    bottom: -5000px;
    width: 1px;
    border-left: 1px dashed #ef4444;
    opacity: 0.8;
}
.canvas-guideline.horizontal {
    left: -5000px;
    right: -5000px;
    height: 1px;
    border-top: 1px dashed #ef4444;
    opacity: 0.8;
}

.canvas-court-element {
    position: absolute;
    z-index: 10;
    border-radius: 10px;
    box-sizing: border-box;
    transition:
        transform 0.1s ease-out,
        outline 0.2s,
        box-shadow 0.2s;
    cursor: pointer;
}

.canvas-court-element:hover {
    cursor: pointer;
}

.canvas-court-element.dragging {
    cursor: move;
    z-index: 50;
    opacity: 0.85;
    transition: none;
}

.canvas-court-element.resizing {
    transition: none;
}

.canvas-court-element.selected {
    outline: 2.5px solid #000000 !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12) !important;
}

.canvas-court-element.has-collision {
    outline: 2.5px solid #ef4444 !important;
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.5) !important;
}

.collision-badge {
    position: absolute;
    top: 8px;
    left: 50%;
    transform: translateX(-50%);
    background: #ef4444;
    color: white;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
    z-index: 30;
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
    pointer-events: none;
    animation: pulseWarning 1.5s infinite;
}

@keyframes pulseWarning {
    0% {
        transform: translateX(-50%) scale(1);
    }
    50% {
        transform: translateX(-50%) scale(1.06);
    }
    100% {
        transform: translateX(-50%) scale(1);
    }
}

.canvas-interaction-guide {
    position: absolute;
    top: 20px;
    left: 20px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    padding: 12px 14px;
    z-index: 99;
    display: flex;
    flex-direction: column;
    gap: 6px;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.guide-item {
    font-size: 11.5px;
    color: var(--admin-text);
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-zoom.fit {
    font-size: 12.5px;
    border-left: 1px solid var(--sg-border);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-zoom.fit .btn-icon {
    font-size: 13px;
}

.inspector-warning-box {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    color: #ef4444;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 14px;
    line-height: 1.4;
}

.resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background-color: var(--admin-surface, #ffffff);
    border: 2px solid #000000;
    border-radius: 50%;
    z-index: 25;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.resize-handle.tl {
    top: -5px;
    left: -5px;
    cursor: nwse-resize;
}
.resize-handle.tr {
    top: -5px;
    right: -5px;
    cursor: nesw-resize;
}
.resize-handle.bl {
    bottom: -5px;
    left: -5px;
    cursor: nesw-resize;
}
.resize-handle.br {
    bottom: -5px;
    right: -5px;
    cursor: nwse-resize;
}

.editor-sidebar {
    width: 300px;
    flex: 0 0 300px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-section {
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    padding: 16px;
}

.section-title {
    font-size: 14px;
    font-weight: 800;
    margin-top: 0;
    margin-bottom: 12px;
    color: var(--admin-text);
    border-bottom: 1px solid var(--sg-border);
    padding-bottom: 8px;
}

.section-desc {
    font-size: 12px;
    color: var(--admin-muted);
    margin-top: 0;
    margin-bottom: 12px;
}

/* Inspector styles */
.inspector-fields {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.field-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    padding-bottom: 8px;
    border-bottom: 1px dashed var(--sg-border);
}

.field-row .label {
    font-weight: 700;
    color: var(--admin-muted);
}

.field-row .value {
    font-weight: 700;
    color: var(--admin-text);
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field-group label {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--admin-text);
}

.input-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.input-row input {
    width: 100%;
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid var(--admin-border);
    font-size: 13px;
    outline: none;
    font-weight: 700;
}

.input-row input:focus {
    border-color: var(--admin-text);
}

.input-row .x,
.input-row .comma {
    font-size: 12px;
    font-weight: 700;
    color: var(--admin-faint);
}

.rotation-control {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rotation-slider {
    flex: 1;
    accent-color: var(--admin-text);
    height: 4px;
}

.btn-rotate {
    padding: 6px 10px;
    font-size: 11px;
}

.btn-block {
    width: 100%;
    display: flex;
    justify-content: center;
}

/* Unplaced list styles */
.unplaced-items {
    max-height: 300px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.unplaced-court-item {
    padding: 10px 12px;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.unplaced-court-item:hover {
    background: var(--admin-surface, #ffffff);
    border-color: var(--admin-text);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.item-name {
    font-weight: 700;
    font-size: 13.5px;
    color: var(--admin-text);
}

.item-add-hint {
    font-size: 11px;
    font-weight: 700;
    color: var(--admin-text);
    opacity: 0;
    transition: opacity 0.15s ease;
}

.unplaced-court-item:hover .item-add-hint {
    opacity: 1;
}

.item-type {
    font-size: 11.5px;
    color: var(--admin-muted);
    margin-top: 2px;
}

.empty-unplaced {
    font-size: 12.5px;
    color: var(--admin-muted);
    text-align: center;
    padding: 20px 0;
    font-style: italic;
}

@media (max-width: 1024px) {
    .editor-body {
        flex-direction: column;
        align-items: stretch;
    }

    .canvas-viewport {
        width: 100%;
        height: 500px;
    }

    .editor-sidebar {
        width: 100%;
        flex: none;
    }
}

/* ─── Layout Decorations ─── */
.canvas-decor-element {
    position: absolute;
    cursor: pointer;
    box-sizing: border-box;
    transition: box-shadow 0.1s;
    z-index: 20;
    pointer-events: auto;
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
    background: var(--admin-surface-muted);
    border: 1.5px solid var(--admin-border);
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    color: var(--admin-faint);
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
    background: var(--admin-surface-muted);
    border-color: var(--admin-border);
    color: var(--admin-text);
}
</style>
