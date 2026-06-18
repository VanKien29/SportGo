<template>
    <div class="court-types-container">


        <div class="header-actions-bar">
            <!-- View Switcher -->
            <div class="view-switcher">
                <button 
                    type="button"
                    class="btn-switch" 
                    :class="{ active: currentView === 'table' }" 
                    @click="currentView = 'table'"
                    title="Xem dạng Bảng"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="switch-icon"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    <span>Bảng</span>
                </button>
                <button 
                    type="button"
                    class="btn-switch" 
                    :class="{ active: currentView === 'split' }" 
                    @click="currentView = 'split'"
                    title="Xem dạng Phân Cột"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="switch-icon"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                    <span>Phân cột</span>
                </button>
                <button 
                    type="button"
                    class="btn-switch" 
                    :class="{ active: currentView === 'cards' }" 
                    @click="currentView = 'cards'"
                    title="Xem dạng Lưới Thẻ"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="switch-icon"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                    <span>Lưới thẻ</span>
                </button>
                <button 
                    type="button"
                    class="btn-switch" 
                    :class="{ active: currentView === 'kanban' }" 
                    @click="currentView = 'kanban'"
                    title="Xem dạng Kanban"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="switch-icon"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="3" x2="12" y2="21"></line></svg>
                    <span>Kanban</span>
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state card">
            <div class="spinner"></div>
            <p>Đang tải danh sách...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state card">
            <p class="error-message">{{ error }}</p>
            <button class="btn btn-outline icon-text" type="button" @click="fetchCourtTypes">
                <AppIcon name="refresh" size="17" />
                <span>Thử lại</span>
            </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="courtTypes.length === 0" class="empty-state card">
            <p>Chưa có môn thể thao hay loại sân nào được cấu hình trên hệ thống.</p>
            <button class="btn btn-primary icon-text" type="button" @click="openCreateModal">
                <AppIcon name="plus" size="17" />
                <span>Thêm ngay</span>
            </button>
        </div>

        <!-- Views Content Wrapper -->
        <div v-else class="views-content-wrapper">
            <!-- 1. Table View -->
            <div v-if="currentView === 'table'" class="card table-card animate-fade-in">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 28%;">Tên bộ môn / Loại sân</th>
                                <th class="text-center player-count-header" style="width: 18%;">Số người chơi</th>
                                <th class="description-header" style="width: 28%;">Mô tả</th>
                                <th class="status-header" style="width: 11%;">Trạng thái</th>
                                <th class="text-center actions-header" style="width: 15%;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="parent in mainParentTypes" :key="parent.id">
                                <tr class="parent-row" :class="{ 'has-children': getChildren(parent.id).length > 0 }" @click="toggleExpand(parent.id)">
                                    <td class="font-bold parent-name-cell">
                                        <div class="name-wrapper">
                                            <span v-if="getChildren(parent.id).length > 0" class="toggle-icon-container">
                                                <svg 
                                                    xmlns="http://www.w3.org/2000/svg" 
                                                    viewBox="0 0 24 24" 
                                                    fill="none" 
                                                    stroke="currentColor" 
                                                    stroke-width="2.5" 
                                                    stroke-linecap="round" 
                                                    stroke-linejoin="round" 
                                                    class="chevron-icon"
                                                    :class="{ 'rotated': isExpanded(parent.id) }"
                                                >
                                                    <polyline points="9 18 15 12 9 6"></polyline>
                                                </svg>
                                            </span>
                                            <span v-else class="toggle-placeholder"></span>
                                            
                                            <span class="name-text">{{ parent.name }}</span>
                                            
                                            <span v-if="getChildren(parent.id).length > 0" class="child-count-badge">
                                                {{ getChildren(parent.id).length }} loại sân
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center text-muted player-count-cell">-</td>
                                    <td class="description-cell">
                                        <div class="description-text text-muted text-truncate" :title="parent.description">
                                            {{ parent.description || "Chưa có mô tả" }}
                                        </div>
                                    </td>
                                    <td class="status-cell">
                                        <div class="status-dot-wrapper" :class="parent.is_active ? 'active' : 'inactive'">
                                            <span class="status-dot-indicator"></span>
                                            <span class="status-tooltip-text">{{ parent.is_active ? 'Đang hoạt động' : 'Tạm khóa' }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center actions-cell" @click.stop>
                                        <div class="actions-wrapper">
                                            <button
                                                class="btn-action btn-add-child"
                                                type="button"
                                                title="Thêm loại sân con trực thuộc"
                                                aria-label="Thêm loại sân con trực thuộc"
                                                @click="openCreateChildModal(parent.id)"
                                            >
                                                <AppIcon name="plus" size="16" />
                                            </button>
                                            <button
                                                class="btn-action btn-edit"
                                                type="button"
                                                title="Sửa bộ môn"
                                                aria-label="Sửa bộ môn"
                                                @click="openEditModal(parent)"
                                            >
                                                <AppIcon name="pencil" size="16" />
                                            </button>
                                            <button
                                                class="btn-action btn-delete"
                                                type="button"
                                                title="Xóa bộ môn"
                                                aria-label="Xóa bộ môn"
                                                @click="confirmDelete(parent)"
                                            >
                                                <AppIcon name="trash" size="16" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-if="isExpanded(parent.id)"
                                    v-for="child in getChildren(parent.id)"
                                    :key="child.id"
                                    class="child-row"
                                >
                                    <td class="font-bold child-name child-name-cell">
                                        <div class="name-wrapper child-wrapper">
                                            <span class="name-text">{{ child.name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center player-count-cell">{{ child.player_count }} người</td>
                                    <td class="description-cell">
                                        <div class="description-text text-muted text-truncate" :title="child.description">
                                            {{ child.description || "Chưa có mô tả" }}
                                        </div>
                                    </td>
                                    <td class="status-cell">
                                        <div class="status-dot-wrapper" :class="child.is_active ? 'active' : 'inactive'">
                                            <span class="status-dot-indicator"></span>
                                            <span class="status-tooltip-text">{{ child.is_active ? 'Đang hoạt động' : 'Tạm khóa' }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center actions-cell">
                                        <div class="actions-wrapper">
                                            <button
                                                class="btn-action btn-edit"
                                                type="button"
                                                title="Sửa loại sân"
                                                aria-label="Sửa loại sân"
                                                @click="openEditModal(child)"
                                            >
                                                <AppIcon name="pencil" size="16" />
                                            </button>
                                            <button
                                                class="btn-action btn-delete"
                                                type="button"
                                                title="Xóa loại sân"
                                                aria-label="Xóa loại sân"
                                                @click="confirmDelete(child)"
                                            >
                                                <AppIcon name="trash" size="16" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. Split View -->
            <div v-else-if="currentView === 'split'" class="split-view-container animate-fade-in">
                <!-- Cột trái: Danh sách Cha -->
                <div class="split-left-sidebar card">
                    <div class="sidebar-list">
                        <div 
                            v-for="parent in mainParentTypes" 
                            :key="parent.id"
                            class="parent-sidebar-item"
                            :class="{ active: selectedParentId === parent.id }"
                            @click="selectParent(parent.id)"
                        >
                            <div class="parent-sidebar-info">
                                <span class="parent-sidebar-name font-bold">{{ parent.name }}</span>
                                <span class="parent-sidebar-badge">{{ getChildren(parent.id).length }} sân</span>
                            </div>
                            <span 
                                class="status-dot" 
                                :class="parent.is_active ? 'active' : 'inactive'"
                            ></span>
                        </div>
                    </div>
                </div>
                
                <!-- Cột phải: Chi tiết Cha & Con -->
                <div class="split-right-content card">
                    <div v-if="selectedParent" class="parent-detail-section">
                        <div class="detail-header">
                            <h3>{{ selectedParent.name }}</h3>
                            <div class="detail-actions">
                                <button class="btn-action btn-edit" type="button" title="Sửa bộ môn" aria-label="Sửa bộ môn" @click="openEditModal(selectedParent)">
                                    <AppIcon name="pencil" size="16" />
                                </button>
                                <button class="btn-action btn-delete" type="button" title="Xóa bộ môn" aria-label="Xóa bộ môn" @click="confirmDelete(selectedParent)">
                                    <AppIcon name="trash" size="16" />
                                </button>
                            </div>
                        </div>
                        <p class="parent-description text-muted">
                            {{ selectedParent.description || "Chưa có mô tả về môn thể thao này." }}
                        </p>
                        
                        <hr class="divider" />
                        
                        <div class="children-section">
                            <div class="children-header">
                                <h4>Loại sân cụ thể ({{ getChildren(selectedParent.id).length }})</h4>
                                <button 
                                    class="btn btn-primary btn-sm icon-text"
                                    type="button"
                                    @click="openCreateChildModal(selectedParent.id)"
                                >
                                    <AppIcon name="plus" size="16" />
                                    <span>Thêm loại sân</span>
                                </button>
                            </div>
                            
                            <div v-if="getChildren(selectedParent.id).length === 0" class="empty-children">
                                Chưa có loại sân con nào thuộc bộ môn này.
                            </div>
                            
                            <div v-else class="children-cards-grid">
                                <div 
                                    v-for="child in getChildren(selectedParent.id)" 
                                    :key="child.id" 
                                    class="child-detail-card"
                                >
                                    <div class="child-card-header">
                                         <span class="child-card-name font-bold">{{ child.name }}</span>
                                         <div class="status-dot-wrapper" :class="child.is_active ? 'active' : 'inactive'">
                                             <span class="status-dot-indicator"></span>
                                             <span class="status-tooltip-text">{{ child.is_active ? 'Đang hoạt động' : 'Tạm khóa' }}</span>
                                         </div>
                                    </div>
                                    <p class="child-card-desc text-muted">{{ child.description || "Chưa có mô tả" }}</p>
                                    <div class="child-card-footer">
                                         <div class="player-count-badge">
                                             <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="user-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                             <span>{{ child.player_count }}</span>
                                         </div>
                                         <div class="child-card-actions">
                                             <button class="btn-action btn-edit" type="button" title="Sửa loại sân" aria-label="Sửa loại sân" @click="openEditModal(child)">
                                                 <AppIcon name="pencil" size="16" />
                                             </button>
                                             <button class="btn-action btn-delete" type="button" title="Xóa loại sân" aria-label="Xóa loại sân" @click="confirmDelete(child)">
                                                 <AppIcon name="trash" size="16" />
                                             </button>
                                         </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="select-parent-prompt">
                        <p>Vui lòng chọn một môn thể thao ở cột bên trái để quản lý.</p>
                    </div>
                </div>
            </div>

            <!-- 3. Nested Cards Grid View -->
            <div v-else-if="currentView === 'cards'" class="cards-view-container animate-fade-in">
                <div v-for="parent in mainParentTypes" :key="parent.id" class="parent-grid-card card">
                    <div class="parent-card-header">
                        <div class="parent-card-title-area">
                            <div class="parent-title-group">
                                <h3 class="font-bold">{{ parent.name }}</h3>
                                <div class="status-dot-wrapper" :class="parent.is_active ? 'active' : 'inactive'">
                                    <span class="status-dot-indicator"></span>
                                    <span class="status-tooltip-text">{{ parent.is_active ? 'Đang hoạt động' : 'Tạm khóa' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="parent-card-actions">
                            <button class="btn-action btn-add-child" type="button" title="Thêm loại sân con" aria-label="Thêm loại sân con" @click="openCreateChildModal(parent.id)">
                                <AppIcon name="plus" size="16" />
                            </button>
                            <button class="btn-action btn-edit" type="button" title="Sửa bộ môn" aria-label="Sửa bộ môn" @click="openEditModal(parent)">
                                <AppIcon name="pencil" size="16" />
                            </button>
                            <button class="btn-action btn-delete" type="button" title="Xóa bộ môn" aria-label="Xóa bộ môn" @click="confirmDelete(parent)">
                                <AppIcon name="trash" size="16" />
                            </button>
                        </div>
                    </div>
                    <div class="parent-card-body">
                        <p class="parent-card-desc text-muted">{{ parent.description || "Chưa có mô tả môn thể thao." }}</p>
                    </div>
                    
                    <!-- Collapsible Children Section -->
                    <div v-if="getChildren(parent.id).length > 0" class="parent-card-children-section">
                        <div class="children-title font-bold">Danh sách loại sân con:</div>
                        <div class="children-inline-list">
                            <div 
                                v-for="child in getChildren(parent.id)" 
                                :key="child.id" 
                                class="child-inline-item"
                            >
                                <div class="child-inline-left">
                                    <span class="child-inline-name font-bold">{{ child.name }}</span>
                                    <div class="status-dot-wrapper small-dot" :class="child.is_active ? 'active' : 'inactive'">
                                        <span class="status-dot-indicator"></span>
                                        <span class="status-tooltip-text">{{ child.is_active ? 'Đang hoạt động' : 'Tạm khóa' }}</span>
                                    </div>
                                </div>
                                
                                <div class="child-inline-right">
                                    <div class="player-count-badge mini-badge">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="user-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                        <span>{{ child.player_count }}</span>
                                    </div>
                                    <div class="child-inline-actions">
                                        <button class="btn-action-mini" type="button" title="Sửa loại sân" aria-label="Sửa loại sân" @click="openEditModal(child)">
                                            <AppIcon name="pencil" size="15" />
                                        </button>
                                        <button class="btn-action-mini btn-delete-mini" type="button" title="Xóa loại sân" aria-label="Xóa loại sân" @click="confirmDelete(child)">
                                            <AppIcon name="trash" size="15" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Kanban Board View -->
            <div v-else-if="currentView === 'kanban'" class="kanban-view-container animate-fade-in">
                <div 
                    v-for="parent in mainParentTypes" 
                    :key="parent.id" 
                    class="kanban-column card"
                >
                    <div class="kanban-column-header">
                        <div class="kanban-column-title">
                            <h4 class="font-bold">{{ parent.name }}</h4>
                            <span class="kanban-count-badge">{{ getChildren(parent.id).length }}</span>
                        </div>
                        <div class="kanban-column-actions">
                            <button class="btn-action-icon" type="button" title="Thêm loại sân con" aria-label="Thêm loại sân con" @click="openCreateChildModal(parent.id)">
                                <AppIcon name="plus" size="15" />
                            </button>
                            <button class="btn-action-icon" type="button" title="Sửa môn thể thao" aria-label="Sửa môn thể thao" @click="openEditModal(parent)">
                                <AppIcon name="pencil" size="15" />
                            </button>
                        </div>
                    </div>
                    
                    <div 
                        class="kanban-cards-list"
                        @dragover.prevent
                        @drop="onDrop($event, parent.id)"
                    >
                        <div v-if="getChildren(parent.id).length === 0" class="kanban-empty-state">
                            <p>Chưa có loại sân</p>
                            <p class="text-muted text-xs">Kéo thả loại sân khác vào đây</p>
                        </div>
                        
                        <div 
                            v-for="child in getChildren(parent.id)" 
                            :key="child.id" 
                            class="kanban-card"
                            draggable="true"
                            @dragstart="onDragStart($event, child.id)"
                        >
                            <div class="kanban-card-header">
                                <span class="kanban-card-name font-bold">{{ child.name }}</span>
                                <div class="status-dot-wrapper small-dot" :class="child.is_active ? 'active' : 'inactive'">
                                    <span class="status-dot-indicator"></span>
                                    <span class="status-tooltip-text">{{ child.is_active ? 'Đang hoạt động' : 'Tạm khóa' }}</span>
                                </div>
                            </div>
                            <p class="kanban-card-desc text-muted text-truncate" :title="child.description">
                                {{ child.description || "Chưa có mô tả" }}
                            </p>
                            <div class="kanban-card-footer">
                                <div class="player-count-badge mini-badge">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="user-icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    <span>{{ child.player_count }}</span>
                                </div>
                                <div class="kanban-card-actions">
                                    <button class="btn-action-mini" type="button" title="Sửa loại sân" aria-label="Sửa loại sân" @click="openEditModal(child)">
                                        <AppIcon name="pencil" size="15" />
                                    </button>
                                    <button class="btn-action-mini btn-delete-mini" type="button" title="Xóa loại sân" aria-label="Xóa loại sân" @click="confirmDelete(child)">
                                        <AppIcon name="trash" size="15" />
                                    </button>
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
                            editingId
                                ? (form.parent_id === null ? "Cập nhật môn thể thao" : "Cập nhật loại sân")
                                : (form.parent_id === null ? "Thêm môn thể thao mới" : "Thêm loại sân mới")
                        }}
                    </h3>
                    <button class="btn-close" @click="closeModal">
                        &times;
                    </button>
                </div>
                <form @submit.prevent="handleSubmit">
                    <div class="modal-body">
                        <div v-if="modalError" class="alert alert-danger">
                            {{ modalError }}
                        </div>

                        <div class="form-group">
                            <label for="name">
                                {{ form.parent_id === null ? "Tên môn thể thao" : "Tên loại sân" }}
                                <span class="required">*</span>
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :placeholder="form.parent_id === null ? 'Ví dụ: Bóng đá, Cầu lông, Pickleball...' : 'Ví dụ: Sân 5 người, Sân đơn...'"
                                required
                            />
                        </div>

                        <div class="form-group">
                            <label for="parent_id">Thuộc môn thể thao</label>
                            <div class="custom-select-container" ref="customSelect">
                                <div class="custom-select-trigger" @click="toggleDropdown">
                                    <span class="selected-value">{{ selectedParentName }}</span>
                                    <svg 
                                        xmlns="http://www.w3.org/2000/svg" 
                                        viewBox="0 0 24 24" 
                                        fill="none" 
                                        stroke="currentColor" 
                                        stroke-width="2" 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        class="select-arrow-icon"
                                        :class="{ 'rotated': dropdownOpen }"
                                    >
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </div>
                                <div v-if="dropdownOpen" class="custom-select-options-wrapper">
                                    <div 
                                        class="custom-select-option" 
                                        :class="{ active: form.parent_id === null }"
                                        @click="selectOption(null)"
                                    >
                                        <span class="option-badge-root">Bộ môn</span>
                                        <span class="option-text">-- Không chọn (Là môn thể thao độc lập) --</span>
                                    </div>
                                    <div 
                                        v-for="pt in parentTypes" 
                                        :key="pt.id" 
                                        class="custom-select-option" 
                                        :class="{ active: form.parent_id === pt.id }"
                                        @click="selectOption(pt.id)"
                                    >
                                        <span class="option-badge-parent">Môn thể thao</span>
                                        <span class="option-text">{{ pt.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="form.parent_id !== null" class="form-group">
                            <label for="player_count"
                                >Số người chơi tiêu chuẩn
                                <span class="required">*</span></label
                            >
                            <input
                                id="player_count"
                                v-model.number="form.player_count"
                                type="number"
                                min="1"
                                class="form-control"
                                :required="form.parent_id !== null"
                            />
                        </div>

                        <!-- Cấu hình quy chuẩn kích thước sơ đồ trực quan -->
                        <div v-if="form.parent_id !== null" class="form-group">
                            <label>Kích thước sơ đồ quy chuẩn (m)</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input
                                    v-model.number="form.default_layout_w"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="form-control"
                                    placeholder="Ngang (ví dụ: 6.1)"
                                    style="flex: 1; min-width: 0;"
                                />
                                <span class="text-muted">x</span>
                                <input
                                    v-model.number="form.default_layout_h"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="form-control"
                                    placeholder="Dọc (ví dụ: 13.4)"
                                    style="flex: 1; min-width: 0;"
                                />
                            </div>
                            <small class="text-muted" style="margin-top: 4px; display: block;">
                                Cấu hình kích thước này giúp chủ sân có sẵn thông số chuẩn khi kéo thả sân con vào bản đồ ảo.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="description">Mô tả</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="form-control"
                                rows="3"
                                :placeholder="form.parent_id === null ? 'Nhập mô tả ngắn về môn thể thao...' : 'Nhập mô tả ngắn về loại sân...'"
                            ></textarea>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                />
                                <span>Kích hoạt hoạt động</span>
                            </label>
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
        <!-- Floating Add Button -->
        <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
            <button class="btn-float-add" @click="openCreateModal">
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Thêm bộ môn</span>
            </button>
        </div>
    </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import { courtTypeService } from "../../services/courtTypes";

export default {
    name: "AdminCourtTypes",
    components: { AppIcon },
    data() {
        return {
            courtTypes: [],
            expandedParentIds: [],
            loading: true,
            error: null,
            showModal: false,
            editingId: null,
            submitting: false,
            modalError: null,
            currentView: "table", // table, split, cards, kanban
            selectedParentId: null, // phục vụ cho split view
            dropdownOpen: false, // điều khiển custom select dropdown
            form: {
                name: "",
                parent_id: null,
                player_count: 4,
                description: "",
                is_active: true,
                default_layout_w: null,
                default_layout_h: null,
            },
            showScrollTop: false,
        };
    },
    computed: {
        parentTypes() {
            // Lọc danh sách loại sân là gốc (chưa có cha) và không phải chính nó để tránh vòng lặp vô hạn
            return this.courtTypes.filter(
                (type) => !type.parent_id && type.id !== this.editingId
            );
        },
        mainParentTypes() {
            // Chỉ hiển thị các loại sân gốc (Cha) trên bảng chính
            return this.courtTypes.filter(type => !type.parent_id);
        },
        selectedParent() {
            if (!this.selectedParentId && this.mainParentTypes.length > 0) {
                this.selectedParentId = this.mainParentTypes[0].id;
            }
            return this.courtTypes.find(type => type.id === this.selectedParentId) || null;
        },
        selectedParentName() {
            if (this.form.parent_id === null) {
                return "-- Không chọn (Là môn thể thao độc lập) --";
            }
            const parent = this.courtTypes.find(type => type.id === this.form.parent_id);
            return parent ? parent.name : "-- Không chọn (Là môn thể thao độc lập) --";
        }
    },
    methods: {
        isExpanded(parentId) {
            return this.expandedParentIds.includes(parentId);
        },
        toggleExpand(parentId) {
            if (this.getChildren(parentId).length === 0) return;
            const index = this.expandedParentIds.indexOf(parentId);
            if (index > -1) {
                this.expandedParentIds.splice(index, 1);
            } else {
                this.expandedParentIds.push(parentId);
            }
        },
        getChildren(parentId) {
            return this.courtTypes.filter(type => type.parent_id === parentId);
        },
        selectParent(parentId) {
            this.selectedParentId = parentId;
        },
        toggleDropdown() {
            this.dropdownOpen = !this.dropdownOpen;
        },
        selectOption(val) {
            this.form.parent_id = val;
            this.dropdownOpen = false;
        },
        handleClickOutside(event) {
            if (this.$refs.customSelect && !this.$refs.customSelect.contains(event.target)) {
                this.dropdownOpen = false;
            }
        },
        async fetchCourtTypes(silent = false) {
            if (!silent) {
                this.loading = true;
            }
            this.error = null;
            try {
                const res = await courtTypeService.getAll();
                this.courtTypes = res.data || [];
                
                // Chọn mặc định cha đầu tiên cho Split View nếu chưa chọn
                if (this.mainParentTypes.length > 0) {
                    if (!this.selectedParentId || !this.mainParentTypes.some(p => p.id === this.selectedParentId)) {
                        this.selectedParentId = this.mainParentTypes[0].id;
                    }
                }
            } catch (err) {
                if (!silent) {
                    this.error = err.message || "Lỗi khi tải danh sách môn thể thao và loại sân.";
                }
            } finally {
                if (!silent) {
                    this.loading = false;
                }
            }
        },
        openCreateModal() {
            this.editingId = null;
            this.modalError = null;
            this.form = {
                name: "",
                parent_id: null,
                player_count: 0,
                description: "",
                is_active: true,
                default_layout_w: null,
                default_layout_h: null,
            };
            this.showModal = true;
        },
        openCreateChildModal(parentId) {
            this.editingId = null;
            this.modalError = null;
            this.form = {
                name: "",
                parent_id: parentId,
                player_count: 4,
                description: "",
                is_active: true,
                default_layout_w: null,
                default_layout_h: null,
            };
            this.showModal = true;
        },
        openEditModal(type) {
            this.editingId = type.id;
            this.modalError = null;
            this.form = {
                name: type.name,
                parent_id: type.parent_id || null,
                player_count: type.player_count,
                description: type.description || "",
                is_active: !!type.is_active,
                default_layout_w: type.default_layout_w ? type.default_layout_w / 100 : null,
                default_layout_h: type.default_layout_h ? type.default_layout_h / 100 : null,
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.editingId = null;
            this.modalError = null;
        },
        async handleSubmit() {
            this.submitting = true;
            this.modalError = null;
            
            // Nếu là bộ môn thể thao độc lập (cha), gán player_count mặc định là 0 để qua validation backend
            if (this.form.parent_id === null) {
                this.form.player_count = 0;
            }
            
            const payload = {
                ...this.form,
                default_layout_w: (this.form.parent_id !== null && this.form.default_layout_w) ? parseFloat(this.form.default_layout_w) * 100 : null,
                default_layout_h: (this.form.parent_id !== null && this.form.default_layout_h) ? parseFloat(this.form.default_layout_h) * 100 : null,
            };
            
            try {
                if (this.editingId) {
                    await courtTypeService.update(this.editingId, payload);
                } else {
                    await courtTypeService.create(payload);
                }
                await this.fetchCourtTypes();
                this.closeModal();
            } catch (err) {
                this.modalError = err.message || "Lỗi lưu thông tin.";
            } finally {
                this.submitting = false;
            }
        },
        async confirmDelete(type) {
            const isParent = !type.parent_id;
            const msg = isParent 
                ? `Bạn có chắc chắn muốn xóa môn thể thao "${type.name}" không? Toàn bộ các loại sân con trực thuộc môn thể thao này cũng sẽ bị ảnh hưởng.`
                : `Bạn có chắc chắn muốn xóa loại sân "${type.name}" không?`;
            if (confirm(msg)) {
                try {
                    await courtTypeService.delete(type.id);
                    await this.fetchCourtTypes();
                } catch (err) {
                    alert(err.message || "Không thể xóa.");
                }
            }
        },
        onDragStart(event, childId) {
            event.dataTransfer.setData("childId", childId.toString());
            event.dataTransfer.effectAllowed = "move";
        },
        async onDrop(event, newParentId) {
            const childIdStr = event.dataTransfer.getData("childId");
            if (!childIdStr) return;
            
            const childId = parseInt(childIdStr, 10);
            if (isNaN(childId)) return;
            
            const child = this.courtTypes.find(type => type.id === childId);
            if (!child || child.parent_id === newParentId || child.id === newParentId) return;
            
            const oldParentId = child.parent_id;
            
            // Optimistic Update: Cập nhật giao diện local ngay lập tức
            child.parent_id = newParentId;
            
            try {
                const updatedForm = {
                    name: child.name,
                    parent_id: newParentId,
                    player_count: child.player_count,
                    description: child.description || "",
                    is_active: !!child.is_active,
                    default_layout_w: child.default_layout_w,
                    default_layout_h: child.default_layout_h,
                };
                await courtTypeService.update(child.id, updatedForm);
                // Đồng bộ dữ liệu mới nhất ngầm từ server mà không bật spinner loading
                await this.fetchCourtTypes(true);
            } catch (err) {
                // Rollback lại giá trị cũ nếu API xảy ra lỗi
                child.parent_id = oldParentId;
                alert("Lỗi khi chuyển danh mục: " + (err.message || err));
            }
        },
        checkMobileView() {
            if (window.innerWidth <= 768) {
                this.currentView = 'split';
            }
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
        }
    },
    created() {
        this.fetchCourtTypes();
    },
    mounted() {
        document.addEventListener("click", this.handleClickOutside);
        this.checkMobileView();
        window.addEventListener("resize", this.checkMobileView);
        window.addEventListener('scroll', this.handleScroll);
    },
    beforeUnmount() {
        document.removeEventListener("click", this.handleClickOutside);
        window.removeEventListener("resize", this.checkMobileView);
        window.removeEventListener('scroll', this.handleScroll);
    }
};
</script>

<style scoped>
.court-types-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
}

.card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--sg-border);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    padding: 24px;
}

.header-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 16px;
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

.btn-primary {
    background: #000000;
    border: 1px solid #000000;
    color: #fff;
}

.btn-primary:hover {
    background: #222222;
    border-color: #222222;
}

.btn-outline {
    border: 1px solid var(--sg-border);
    background: transparent;
    color: var(--sg-text);
}

.btn-outline:hover {
    background: var(--sg-surface);
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
    color: rgba(15, 23, 42, 0.6);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(0, 0, 0, 0.1);
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.table-card {
    padding: 0;
    overflow: hidden;
    width: 100%;
    max-width: 100%;
}

.table-responsive {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    table-layout: fixed;
}

.data-table th,
.data-table td {
    padding: 16px 16px;
    border-bottom: 1px solid var(--sg-border);
    font-size: 14px;
}

.data-table th {
    background: var(--sg-surface);
    font-weight: 700;
    color: var(--sg-text);
}

.font-bold {
    font-weight: 700;
    color: var(--sg-text);
}

.text-muted {
    color: rgba(15, 23, 42, 0.5);
}

.text-truncate {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.status-badge {
    display: inline-flex;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid transparent;
    white-space: nowrap;
}

.status-badge.active {
    background: rgba(0, 0, 0, 0.04);
    color: #000000;
    border-color: rgba(0, 0, 0, 0.15);
}

.status-badge.inactive {
    background: #f3f4f6;
    color: rgba(0, 0, 0, 0.4);
    border-color: rgba(0, 0, 0, 0.08);
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center !important;
}

.actions-cell {
    text-align: right;
}

.btn-action {
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.btn-edit {
    background: rgba(0, 0, 0, 0.02);
    border-color: rgba(0, 0, 0, 0.1);
    color: rgba(0, 0, 0, 0.8);
}

.btn-edit:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #000000;
}

.btn-delete {
    background: rgba(0, 0, 0, 0.02);
    border-color: rgba(0, 0, 0, 0.08);
    color: rgba(0, 0, 0, 0.6);
}

.btn-delete:hover {
    background: rgba(239, 68, 68, 0.05);
    border-color: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

/* Modal Styling */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
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
    color: var(--sg-text);
}

.btn-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: rgba(15, 23, 42, 0.4);
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
    color: var(--sg-text);
}

.required {
    color: #ef4444;
}

.form-control {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--sg-border);
    font-size: 14px;
    color: var(--sg-text);
    outline: none;
    transition: border-color 0.2s ease;
}

.form-control:focus {
    border-color: #000000;
}

.checkbox-group {
    margin-top: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
    color: var(--sg-text);
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    accent-color: #000000;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid var(--sg-border);
    background: var(--sg-surface);
}

.alert-danger {
    background: #f3f4f6;
    color: #ef4444;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid #e5e7eb;
}

.parent-row.has-children {
    cursor: pointer;
}

.parent-row:hover {
    background: rgba(0, 0, 0, 0.015) !important;
}

.name-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toggle-icon-container {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    color: rgba(15, 23, 42, 0.4);
    border-radius: 4px;
    transition: all 0.2s ease;
}

.parent-row:hover .toggle-icon-container {
    background: rgba(0, 0, 0, 0.04);
    color: #000000;
}

.chevron-icon {
    width: 14px;
    height: 14px;
    transition: transform 0.2s ease;
}

.chevron-icon.rotated {
    transform: rotate(90deg);
}

.toggle-placeholder {
    display: inline-block;
    width: 24px;
}

.child-count-badge {
    display: inline-flex;
    align-items: center;
    background: rgba(0, 0, 0, 0.04);
    color: rgba(0, 0, 0, 0.6);
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 8px;
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.child-wrapper {
    padding-left: 32px;
}

.child-badge {
    display: inline-flex;
    align-items: center;
    background: #f3f4f6;
    color: rgba(0, 0, 0, 0.4);
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    margin-left: 8px;
    border: 1px solid rgba(0, 0, 0, 0.05);
    text-transform: uppercase;
}

.child-row {
    background: rgba(0, 0, 0, 0.01) !important;
}

.child-row:hover {
    background: rgba(0, 0, 0, 0.02) !important;
}

.btn-add-child {
    background: rgba(0, 0, 0, 0.02);
    border-color: rgba(0, 0, 0, 0.08);
    color: #10b981;
}

.btn-add-child:hover {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.2);
    color: #059669;
}

/* Định nghĩa độ rộng cột Thao tác và căn chỉnh các nút bấm */
.actions-header,
.actions-cell {
    width: 15% !important;
    min-width: 180px !important;
    max-width: 200px !important;
    text-align: center;
}

.status-header,
.status-cell {
    width: 11% !important;
    min-width: 115px !important;
    max-width: 130px !important;
    text-align: center;
}

.parent-name-cell,
.child-name-cell {
    min-width: 200px;
}

.player-count-header,
.player-count-cell {
    min-width: 140px;
}

.description-header,
.description-cell {
    min-width: 180px;
}

.status-dot-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 6px;
}

.status-dot-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    transition: transform 0.2s ease;
}

.status-dot-wrapper.active .status-dot-indicator {
    background-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.status-dot-wrapper.inactive .status-dot-indicator {
    background-color: #9ca3af;
    box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.2);
}

.status-dot-wrapper:hover .status-dot-indicator {
    transform: scale(1.2);
}

.status-tooltip-text {
    visibility: hidden;
    width: 110px;
    background-color: #1e293b;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 6px 0;
    position: absolute;
    z-index: 999;
    bottom: 150%;
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    opacity: 0;
    transition: opacity 0.2s ease, transform 0.2s ease;
    font-size: 11px;
    font-weight: 700;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    white-space: nowrap;
}

.status-tooltip-text::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px;
    border-style: solid;
    border-color: #1e293b transparent transparent transparent;
}

.status-dot-wrapper:hover .status-tooltip-text {
    visibility: visible;
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

.actions-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
}

.description-text {
    width: 100%;
    max-width: 100%;
    display: block;
}

/* Header Actions Bar */
.header-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 8px;
}

/* View Switcher */
.view-switcher {
    display: inline-flex;
    background: rgba(0, 0, 0, 0.04);
    padding: 4px;
    border-radius: 10px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.btn-switch {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    color: rgba(15, 23, 42, 0.6);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-switch:hover {
    color: #000;
}

.btn-switch.active {
    background: #fff;
    color: #000;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
}

.switch-icon {
    width: 14px;
    height: 14px;
}

/* Views Wrapper */
.views-content-wrapper {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

/* Animations */
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Split View Layout */
.split-view-container {
    display: flex;
    gap: 20px;
    min-height: 550px;
    align-items: stretch;
}

.split-left-sidebar {
    width: 300px;
    min-width: 300px;
    max-width: 300px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sidebar-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: var(--sg-text);
}

.sidebar-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    overflow-y: auto;
}

.parent-sidebar-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-radius: 8px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    background: rgba(0, 0, 0, 0.01);
}

.parent-sidebar-item:hover {
    background: rgba(0, 0, 0, 0.03);
}

.parent-sidebar-item.active {
    background: #000;
    color: #fff;
}

.parent-sidebar-item.active .parent-sidebar-name {
    color: #fff;
}

.parent-sidebar-item.active .parent-sidebar-badge {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.3);
}

.parent-sidebar-info {
    display: flex;
    align-items: center;
}

.parent-sidebar-name {
    font-size: 14px;
}

.parent-sidebar-badge {
    font-size: 11px;
    padding: 2px 6px;
    background: rgba(0, 0, 0, 0.05);
    color: rgba(0, 0, 0, 0.6);
    border-radius: 9999px;
    font-weight: 700;
    margin-left: 8px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-dot.active {
    background: #10b981;
}

.status-dot.inactive {
    background: #9ca3af;
}

.split-right-content {
    flex-grow: 1;
    padding: 24px;
}

.parent-detail-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.detail-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
}

.detail-actions {
    display: flex;
    gap: 8px;
}

.parent-description {
    font-size: 14.5px;
    line-height: 1.6;
    color: rgba(15, 23, 42, 0.55);
    margin: 4px 0 12px 0;
}

.divider {
    border: 0;
    border-top: 1px solid var(--sg-border);
    margin: 8px 0;
}

.children-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.children-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
}

.empty-children {
    padding: 40px 20px;
    text-align: center;
    color: rgba(15, 23, 42, 0.5);
    background: rgba(0, 0, 0, 0.015);
    border-radius: 8px;
    border: 1px dashed var(--sg-border);
}

.children-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}

.child-detail-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.06);
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.015);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.child-detail-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
    border-color: rgba(0, 0, 0, 0.12);
}

.child-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.child-card-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--sg-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: calc(100% - 28px);
}

.child-detail-card .status-dot-wrapper {
    padding: 2px;
}

.child-card-desc {
    font-size: 12.5px;
    line-height: 1.5;
    color: rgba(15, 23, 42, 0.5);
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    min-height: 38px;
}

.child-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 8px;
    border-top: 1px dashed rgba(0, 0, 0, 0.04);
}

.player-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(0, 0, 0, 0.03);
    color: rgba(15, 23, 42, 0.6);
    height: 32px;
    padding: 0 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid rgba(0, 0, 0, 0.02);
    box-sizing: border-box;
}

.user-icon {
    opacity: 0.65;
    width: 13px;
    height: 13px;
}

.child-card-actions {
    display: flex;
    gap: 8px;
}

.child-detail-card .btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    padding: 0 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    box-sizing: border-box;
}

.select-parent-prompt {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: rgba(15, 23, 42, 0.5);
    text-align: center;
}

/* Nested Cards View nâng cấp */
.cards-view-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.parent-grid-card {
    display: flex;
    flex-direction: column;
    gap: 16px;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.06);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.015);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    background: #fff;
    padding: 20px;
}

.parent-grid-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
    border-color: rgba(0, 0, 0, 0.12);
}

.parent-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.parent-card-title-area {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-width: calc(100% - 110px);
}

.parent-title-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.parent-title-group h3 {
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: var(--sg-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.parent-card-title-area .child-count-tag {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    background: rgba(0, 0, 0, 0.04);
    color: rgba(0, 0, 0, 0.6);
    border-radius: 9999px;
    align-self: flex-start;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.parent-card-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.parent-card-actions .btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 28px;
    padding: 0 10px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 700;
    box-sizing: border-box;
}

.parent-card-actions .btn-add-child {
    font-size: 14px;
    padding: 0 10px;
    color: #10b981;
}

.parent-card-body {
    display: flex;
    flex-direction: column;
}

.parent-card-desc {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: rgba(15, 23, 42, 0.55);
}

.parent-card-children-section {
    border-top: 1px dashed rgba(0, 0, 0, 0.06);
    padding-top: 14px;
    margin-top: auto;
}

.children-title {
    font-size: 12px;
    font-weight: 700;
    color: rgba(15, 23, 42, 0.45);
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.children-inline-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.child-inline-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: rgba(0, 0, 0, 0.012);
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, 0.02);
    transition: background 0.2s ease, border-color 0.2s ease;
}

.child-inline-item:hover {
    background: rgba(0, 0, 0, 0.025);
    border-color: rgba(0, 0, 0, 0.06);
}

.child-inline-left {
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 50%;
}

.child-inline-name {
    font-size: 13.5px;
    color: var(--sg-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.status-dot-wrapper.small-dot {
    padding: 2px;
}

.status-dot-wrapper.small-dot .status-dot-indicator {
    width: 8px;
    height: 8px;
}

.child-inline-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.player-count-badge.mini-badge {
    height: 24px;
    padding: 0 8px;
    font-size: 11.5px;
    gap: 4px;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.025);
}

.child-inline-actions {
    display: flex;
    gap: 4px;
}

.child-inline-actions .btn-action-mini {
    padding: 4px 8px;
    background: rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    color: rgba(0, 0, 0, 0.6);
    transition: all 0.2s ease;
}

.child-inline-actions .btn-action-mini:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #000;
    border-color: rgba(0, 0, 0, 0.12);
}

.child-inline-actions .btn-delete-mini:hover {
    background: rgba(239, 68, 68, 0.05);
    border-color: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

/* Kanban Board View - Masonry Layout */
.kanban-view-container {
    column-count: 3;
    column-gap: 20px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    padding-bottom: 16px;
    min-height: 550px;
}

.kanban-column {
    display: inline-flex;
    flex-direction: column;
    width: 100%;
    margin-bottom: 20px;
    break-inside: avoid-column;
    padding: 16px;
    gap: 16px;
    background: #f8fafc;
    border: 1px solid var(--sg-border);
    max-height: 520px;
    box-sizing: border-box;
    border-radius: 12px;
}

@media (max-width: 992px) {
    .kanban-view-container {
        column-count: 2;
    }
}

@media (max-width: 768px) {
    .kanban-view-container {
        column-count: 1;
    }
}

.kanban-column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding-bottom: 10px;
}

.kanban-column-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.kanban-column-title h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
}

.kanban-count-badge {
    background: rgba(0, 0, 0, 0.05);
    color: rgba(0, 0, 0, 0.6);
    padding: 2px 6px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
}

.kanban-column-actions {
    display: flex;
    gap: 4px;
}

.btn-action-icon {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 13px;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: background 0.2s ease;
}

.btn-action-icon:hover {
    background: rgba(0, 0, 0, 0.05);
}

.kanban-cards-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
    overflow-x: hidden; /* Ngăn chặn hoàn toàn thanh cuộn ngang xuất hiện ở cột */
    flex-grow: 1;
    min-height: 150px;
    padding: 4px;
    border-radius: 6px;
}

.kanban-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 120px;
    border: 2px dashed rgba(0, 0, 0, 0.04);
    border-radius: 8px;
    color: rgba(15, 23, 42, 0.4);
    font-size: 12px;
    text-align: center;
}

.kanban-card {
    background: #fff;
    border-radius: 10px;
    border: 1px solid rgba(0, 0, 0, 0.05);
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.015);
    cursor: grab;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    width: 100%;
    box-sizing: border-box;
}

.kanban-card .text-truncate {
    max-width: 100%;
}

.kanban-card:active {
    cursor: grabbing;
    transform: scale(0.97);
}

.kanban-card:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.04);
    border-color: rgba(0, 0, 0, 0.12);
}

.kanban-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.kanban-card-name {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sg-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: calc(100% - 24px);
}

.kanban-card-desc {
    font-size: 11.5px;
    line-height: 1.5;
    color: rgba(15, 23, 42, 0.5);
    margin: 0;
    max-width: 100%;
}

.kanban-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
    padding-top: 8px;
    border-top: 1px dashed rgba(0, 0, 0, 0.04);
}

.kanban-card-actions {
    display: flex;
    gap: 6px;
}

.kanban-card .btn-action-mini {
    padding: 4px 8px;
    background: rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    color: rgba(0, 0, 0, 0.6);
    transition: all 0.2s ease;
}

.kanban-card .btn-action-mini:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #000;
    border-color: rgba(0, 0, 0, 0.12);
}

.kanban-card .btn-delete-mini:hover {
    background: rgba(239, 68, 68, 0.05);
    border-color: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

/* Custom Select Dropdown */
.custom-select-container {
    position: relative;
    width: 100%;
    user-select: none;
}

.custom-select-trigger {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--sg-border);
    background: #fff;
    font-size: 14px;
    color: var(--sg-text);
    cursor: pointer;
    transition: all 0.2s ease;
}

.custom-select-trigger:hover {
    border-color: #000;
}

.select-arrow-icon {
    width: 16px;
    height: 16px;
    color: rgba(15, 23, 42, 0.4);
    transition: transform 0.2s ease;
}

.select-arrow-icon.rotated {
    transform: rotate(180deg);
}

.custom-select-options-wrapper {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid var(--sg-border);
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    z-index: 1010;
    max-height: 220px;
    overflow-y: auto;
    padding: 4px;
    animation: slideDown 0.15s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.custom-select-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    color: var(--sg-text);
    cursor: pointer;
    transition: background 0.15s ease;
}

.custom-select-option:hover {
    background: rgba(0, 0, 0, 0.03);
}

.custom-select-option.active {
    background: rgba(0, 0, 0, 0.05);
    font-weight: 700;
}

.option-badge-root,
.option-badge-parent {
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
}

.option-badge-root {
    background: rgba(0, 0, 0, 0.06);
    color: rgba(0, 0, 0, 0.6);
}

.option-badge-parent {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.option-text {
    flex-grow: 1;
}

.court-types-container .btn-switch:hover {
    color: var(--admin-primary-dark);
}

.court-types-container .btn-switch.active {
    border-color: rgba(47, 158, 68, 0.22);
    background: var(--admin-primary-soft);
    color: var(--admin-primary-dark);
    box-shadow: 0 8px 18px rgba(47, 158, 68, 0.08);
}

.court-types-container .parent-sidebar-item:hover,
.court-types-container .custom-select-option:hover {
    background: rgba(232, 247, 236, 0.68);
}

.court-types-container .parent-sidebar-item.active {
    border-color: rgba(47, 158, 68, 0.26);
    background: var(--admin-primary-soft);
    color: var(--admin-primary-dark);
}

.court-types-container .parent-sidebar-item.active .parent-sidebar-name {
    color: var(--admin-primary-dark);
}

.court-types-container .parent-sidebar-item.active .parent-sidebar-badge,
.court-types-container .custom-select-option.active {
    border-color: rgba(47, 158, 68, 0.18);
    background: rgba(47, 158, 68, 0.12);
    color: var(--admin-primary-dark);
}

.court-types-container .custom-select-trigger:hover,
.court-types-container .custom-select-trigger:focus-within {
    border-color: rgba(47, 158, 68, 0.62);
    box-shadow: 0 0 0 3px rgba(47, 158, 68, 0.14);
}

.court-types-container .status-dot.active {
    background: var(--admin-primary);
}

.court-types-container .status-dot.inactive {
    background: #9aa89c;
}

.court-types-container .btn-action,
.court-types-container .btn-action-mini,
.court-types-container .btn-action-icon {
    color: #344238;
}

.court-types-container .btn-add-child,
.court-types-container .option-badge-parent {
    color: var(--admin-primary-dark);
}

/* ==========================================
   RESPONSIVE DESIGN (MOBILE & TABLET)
   ========================================== */

@media (max-width: 1024px) {
    /* Container chính */
    .court-types-container {
        gap: 16px;
        padding: 0 4px;
        max-width: 100%;
        overflow: hidden;
    }

    /* Header Actions Bar & View Switcher */
    .header-actions-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .view-switcher {
        width: 100%;
        display: flex;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .view-switcher::-webkit-scrollbar {
        display: none;
    }

    .btn-switch {
        flex: 1;
        justify-content: center;
        white-space: nowrap;
        padding: 8px 10px;
        font-size: 12.5px;
    }

    .header-actions-bar .btn-primary {
        width: 100%;
        justify-content: center;
        padding: 12px 18px;
    }

    /* 1. Table View */
    .data-table {
        min-width: 820px; /* Ngăn chặn co bóp cột, cho phép cuộn ngang mượt */
    }

    /* 2. Split View (Chuyển sang dạng dọc trên Mobile) */
    .split-view-container {
        flex-direction: column;
        gap: 16px;
        min-height: auto;
    }

    .split-left-sidebar {
        width: 100%;
        min-width: 100%;
        max-width: 100%;
        padding: 12px;
    }

    .sidebar-list {
        max-height: 160px; /* Giới hạn chiều cao để không chiếm quá nhiều chỗ */
        overflow-y: auto;
    }

    .split-right-content {
        padding: 16px;
    }

    .children-cards-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 12px;
    }

    /* 3. Nested Cards View */
    .cards-view-container {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }
}

@media (max-width: 576px) {
    /* Tối ưu hóa modal trên mobile để tránh bị tràn chiều cao */
    .modal-backdrop {
        padding: 10px;
    }

    .modal {
        max-height: 95vh;
        display: flex;
        flex-direction: column;
    }

    .modal-header,
    .modal-footer {
        padding: 14px 16px;
    }

    .modal-header h3 {
        font-size: 16px;
    }

    .modal-body {
        padding: 16px;
        overflow-y: auto;
        max-height: calc(95vh - 120px);
        gap: 12px;
    }

    /* Card view con trong Cards View */
    .child-inline-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .child-inline-left {
        max-width: 100%;
        width: 100%;
        justify-content: space-between;
    }

    .child-inline-right {
        width: 100%;
        justify-content: space-between;
    }
}

@media (max-width: 768px) {
    /* Ẩn hoàn toàn thanh chuyển đổi chế độ xem trên điện thoại */
    .view-switcher {
        display: none !important;
    }
}

/* Floating Add Button */
.floating-add-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9998;
    transition: right 0.25s ease;
}
.floating-add-container.has-scroll {
    right: 86px;
}
.btn-float-add {
    width: 44px;
    height: 44px;
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #10b981;
    color: #fff;
    border: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    white-space: nowrap;
    padding: 0 12px;
}
.btn-float-add .btn-float-text {
    max-width: 0;
    opacity: 0;
    margin-left: 0;
    font-weight: 700;
    font-size: 13px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
}
.btn-float-add:hover {
    width: 145px;
    justify-content: flex-start;
    padding-left: 14px;
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    background-color: #059669;
}
.btn-float-add:hover .btn-float-text {
    max-width: 100px;
    opacity: 1;
    margin-left: 6px;
}
@media (max-width: 768px) {
    .floating-add-container {
        bottom: 20px;
        right: 20px;
    }
    .floating-add-container.has-scroll {
        right: 72px;
    }
    .btn-float-add {
        width: 40px;
        height: 40px;
        border-radius: 20px;
        padding: 0 10px;
    }
    .btn-float-add:hover {
        width: 130px;
        padding-left: 12px;
    }
    .btn-float-add:hover .btn-float-text {
        max-width: 80px;
    }
}
</style>
