<template>
    <div class="affiliate-shop-container">
        <!-- Khi chưa chọn cụm sân -->
        <div v-if="!selectedCluster" class="empty-state card">
            <div class="empty-icon-wrapper">
                <AppIcon name="building" size="32" />
            </div>
            <div>
                <h5 class="empty-title">Chưa chọn cụm sân</h5>
                <p class="empty-desc">Vui lòng chọn một cụm sân ở thanh tiêu đề phía trên để quản lý sản phẩm tiếp thị liên kết.</p>
            </div>
        </div>

        <!-- Khi đã chọn cụm sân -->
        <div v-else>
            <!-- Alert message if any -->
            <div v-if="productsError" class="alert alert-danger" style="margin-bottom: 16px;">
                {{ productsError }}
            </div>

            <!-- Products List Table/Grid -->
            <div v-if="loadingProducts" class="loading-state card" style="padding: 40px 0; text-align: center;">
                <div class="spinner"></div>
                <p style="margin-top: 10px; color: #64748b;">Đang tải danh sách sản phẩm...</p>
            </div>
            
            <div v-else-if="affiliateProducts.length === 0" class="empty-state card">
                <div class="empty-icon-wrapper">
                    <AppIcon name="shopping-bag" size="32" />
                </div>
                <div>
                    <h5 class="empty-title">Cửa hàng trống</h5>
                    <p class="empty-desc">Bạn chưa đăng sản phẩm tiếp thị liên kết nào cho cụm sân này. Hãy thêm sản phẩm để bắt đầu tiếp thị.</p>
                </div>
                <button type="button" class="btn btn-outline" @click="openCreateProductModal">Thêm sản phẩm đầu tiên</button>
            </div>

            <div v-else class="card affiliate-list-card">
                <div class="table-scroll">
                    <table class="affiliate-table">
                        <thead>
                            <tr>
                                <th class="col-img">Ảnh</th>
                                <th class="col-name">Tên sản phẩm</th>
                                <th class="col-platform">Nền tảng</th>
                                <th class="col-price">Giá bán</th>
                                <th class="col-clicks">Lượt click</th>
                                <th class="col-status">Trạng thái</th>
                                <th class="col-actions">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in affiliateProducts" :key="product.id" class="product-row">
                                <td class="cell-img">
                                    <div class="product-img-box">
                                        <img v-if="product.image_path" :src="imageUrl(product.image_path)" class="product-thumb" />
                                        <AppIcon v-else name="image" size="20" class="placeholder-icon" />
                                    </div>
                                </td>
                                <td class="cell-name">
                                    <div class="product-title">{{ product.name }}</div>
                                    <div class="product-desc">{{ product.description || 'Không có mô tả.' }}</div>
                                </td>
                                <td class="cell-platform">
                                    <span class="platform-badge" :class="product.platform_name.toLowerCase().replace(' ', '-')">
                                        {{ product.platform_name }}
                                    </span>
                                </td>
                                <td class="cell-price">
                                    <div v-if="product.price" class="price-discount">
                                        {{ formatCurrency(product.price) }}
                                    </div>
                                    <div v-if="product.original_price" class="price-original">
                                        {{ formatCurrency(product.original_price) }}
                                    </div>
                                    <div v-if="!product.price" class="price-empty">
                                        Liên hệ nơi bán
                                    </div>
                                </td>
                                <td class="cell-clicks">{{ product.click_count || 0 }}</td>
                                <td class="cell-status">
                                    <label class="switch-toggle">
                                        <input type="checkbox" :checked="product.is_active" @change="handleToggleProductStatus(product.id)" />
                                        <span class="slider-round"></span>
                                    </label>
                                </td>
                                <td class="cell-actions">
                                    <div class="action-buttons-group">
                                        <button type="button" class="btn-action-icon edit" @click="openEditProductModal(product)">
                                            <AppIcon name="pencil" size="14" />
                                        </button>
                                        <button type="button" class="btn-action-icon delete" @click="handleDeleteProduct(product.id)">
                                            <AppIcon name="trash" size="14" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Nút Thêm sản phẩm dạng nổi ở góc dưới -->
            <div class="floating-add-container">
                <FloatAddButton 
                    label="Thêm sản phẩm tiếp thị"
                    title="Thêm sản phẩm tiếp thị"
                    @click="openCreateProductModal" 
                />
            </div>
        </div>

        <!-- Modal: Affiliate Product (Thêm/Sửa sản phẩm tiếp thị liên kết) -->
        <div v-if="showProductModal" class="modal-backdrop" @click.self="closeProductModal">
            <div class="modal card product-edit-modal">
                <div class="modal-header">
                    <h3>
                        <span>{{ editingProduct ? 'Chỉnh sửa sản phẩm tiếp thị' : 'Thêm sản phẩm tiếp thị liên kết' }}</span>
                    </h3>
                    <button class="btn-close" @click="closeProductModal">&times;</button>
                </div>
                <form @submit.prevent="submitProductForm">
                    <div class="modal-body">
                        <div v-if="productFormError" class="alert alert-danger" style="margin-bottom: 16px;">
                            {{ productFormError }}
                        </div>
                        <div class="modal-layout-cols">
                            <!-- Left Column: Form Fields (Nhập liệu) -->
                            <div class="form-fields-col">
                                <!-- Product Name -->
                                <div class="form-group">
                                    <label class="form-label-bold">
                                        Tên sản phẩm <span class="required">*</span>
                                    </label>
                                    <input
                                        v-model.trim="productForm.name"
                                        type="text"
                                        class="form-control"
                                        placeholder="Ví dụ: Vợt cầu lông Yonex Astrox 88D Play"
                                        required
                                    />
                                </div>

                                <!-- Platform & Link Affiliate -->
                                <div class="form-row-grid">
                                    <div class="form-group">
                                        <label class="form-label-bold">
                                            Nền tảng <span class="required">*</span>
                                        </label>
                                        <div class="custom-select-wrapper">
                                            <div class="custom-select-trigger" :class="{ active: showPlatformDropdown }" @click.stop="showPlatformDropdown = !showPlatformDropdown">
                                                <span>{{ platformLabel(productForm.platform_name) }}</span>
                                                <span class="arrow">&#9662;</span>
                                            </div>
                                            <div v-if="showPlatformDropdown" class="custom-options-container">
                                                <div v-for="opt in platformOptions" :key="opt.value" class="custom-option" :class="{ selected: productForm.platform_name === opt.value }" @click="selectPlatform(opt.value)">
                                                    {{ opt.label }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label-bold">
                                            Link tiếp thị liên kết <span class="required">*</span>
                                        </label>
                                        <input
                                            v-model.trim="productForm.affiliate_url"
                                            type="url"
                                            class="form-control"
                                            placeholder="https://shopee.vn/..."
                                            required
                                        />
                                    </div>
                                </div>

                                <!-- Prices -->
                                <div class="form-row-grid price-fields">
                                    <div class="form-group">
                                        <label class="form-label-bold">Giá bán hiện tại (đ)</label>
                                        <input
                                            v-model.number="productForm.price"
                                            type="number"
                                            class="form-control"
                                            placeholder="Giá sau khi giảm..."
                                            min="0"
                                        />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label-bold">Giá gốc (đ)</label>
                                        <input
                                            v-model.number="productForm.original_price"
                                            type="number"
                                            class="form-control"
                                            placeholder="Giá trước khi giảm..."
                                            min="0"
                                        />
                                    </div>
                                </div>

                                <!-- Description -->
                                <div class="form-group">
                                    <label class="form-label-bold">Mô tả sản phẩm</label>
                                    <textarea
                                        v-model.trim="productForm.description"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Mô tả ngắn về sản phẩm để khách hàng tham khảo..."
                                        style="resize: none;"
                                    ></textarea>
                                </div>

                                <!-- Toggle status & Image select trigger on mobile -->
                                <div class="form-group mobile-image-upload-trigger">
                                    <label class="form-label-bold">Ảnh sản phẩm</label>
                                    <div class="image-upload-box" @click="$refs.productImageInput.click()">
                                        <template v-if="productImagePreview">
                                            <img :src="productImagePreview" class="uploaded-img" />
                                            <div class="change-img-overlay">Đổi ảnh</div>
                                        </template>
                                        <div v-else class="upload-placeholder">
                                            <AppIcon name="image" size="24" />
                                            <span>Tải ảnh lên</span>
                                        </div>
                                    </div>
                                    <input
                                        ref="productImageInput"
                                        type="file"
                                        accept="image/*"
                                        style="display:none;"
                                        @change="handleProductImageChange"
                                    />
                                </div>

                                <!-- Toggle Status -->
                                <div class="form-group" style="margin-top: 10px;">
                                    <label class="form-label-bold" style="margin-bottom: 8px;">Trạng thái hiển thị</label>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="switch-toggle-custom" :style="productForm.is_active ? 'background-color: #10b981;' : ''" @click="productForm.is_active = !productForm.is_active">
                                            <input type="checkbox" v-model="productForm.is_active" style="display: none;" />
                                            <div class="toggle-dot" :style="productForm.is_active ? 'transform: translateX(20px);' : ''"></div>
                                        </div>
                                        <span style="color: #1e293b; font-weight: 600; font-size: 13px;">
                                            {{ productForm.is_active ? 'Bật hiển thị cho khách hàng' : 'Ẩn đối với khách hàng' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column: Image Upload & Preview (Chỉ hiển thị trên Desktop) -->
                            <div class="image-preview-col">
                                <label class="form-label-bold" style="text-align: center; display: block; margin-bottom: 12px;">Hình ảnh minh họa</label>
                                <div class="desktop-image-zone" @click="$refs.productImageInput.click()">
                                    <img v-if="productImagePreview" :src="productImagePreview" class="preview-img-full" />
                                    <div v-else class="empty-image-placeholder">
                                        <AppIcon name="image" size="48" style="color: #94a3b8; margin-bottom: 8px;" />
                                        <p style="margin: 0; font-size: 13px; color: #64748b; font-weight: 600;">Chọn hình ảnh sản phẩm</p>
                                        <p style="margin: 4px 0 0 0; font-size: 11px; color: #94a3b8;">Hỗ trợ JPG, PNG, WebP (Tối đa 5MB)</p>
                                    </div>
                                    <div v-if="productImagePreview" class="hover-change-text">Nhấp để thay đổi ảnh mới</div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" @click="closeProductModal">Hủy</button>
                        <button type="submit" class="btn btn-primary" :disabled="submittingProduct">
                            {{ submittingProduct ? "Đang lưu..." : "Lưu sản phẩm" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import FloatAddButton from "../../components/FloatAddButton.vue";
import { affiliateProductService } from "../../services/affiliateProducts";

export default {
    name: "OwnerAffiliate",
    components: { AppIcon, FloatAddButton },
    data() {
        return {
            selectedCluster: null,
            affiliateProducts: [],
            loadingProducts: false,
            productsError: null,

            // Modal
            showProductModal: false,
            editingProduct: null,
            submittingProduct: false,
            productFormError: null,
            showPlatformDropdown: false,
            productImagePreview: null,

            productForm: {
                name: "",
                description: "",
                price: "",
                original_price: "",
                affiliate_url: "",
                platform_name: "Shopee",
                is_active: true,
                image: null,
            },

            platformOptions: [
                { value: "Shopee", label: "Shopee" },
                { value: "Lazada", label: "Lazada" },
                { value: "Tiki", label: "Tiki" },
                { value: "Tiktok Shop", label: "Tiktok Shop" },
                { value: "Khac", label: "Khác" },
            ],
        };
    },
    mounted() {
        window.addEventListener("owner-cluster-changed", this.handleClusterChange);
        this.initSelectedCluster();
        document.addEventListener("click", this.handleOutsideClick);
    },
    beforeUnmount() {
        window.removeEventListener("owner-cluster-changed", this.handleClusterChange);
        document.removeEventListener("click", this.handleOutsideClick);
    },
    methods: {
        initSelectedCluster() {
            // Đọc cụm sân hiện tại được lưu trong localStorage
            const savedClusterId = localStorage.getItem("selected_cluster");
            if (savedClusterId) {
                this.loadClusterFromLocalStorage(savedClusterId);
            }
        },
        async loadClusterFromLocalStorage(clusterId) {
            this.loadingProducts = true;
            try {
                // Tải danh sách cụm sân để tìm đối tượng cụm sân đầy đủ
                const { venueClusterService } = await import("../../services/venueClusters");
                const res = await venueClusterService.getClusters();
                const list = res.data || [];
                const cluster = list.find(c => String(c.id) === String(clusterId));
                if (cluster) {
                    this.selectedCluster = cluster;
                    this.fetchAffiliateProducts(cluster.id);
                } else {
                    this.loadingProducts = false;
                }
            } catch (err) {
                console.error("Lỗi khi đồng bộ cụm sân:", err);
                this.loadingProducts = false;
            }
        },
        handleClusterChange(event) {
            const cluster = event.detail;
            if (cluster) {
                this.selectedCluster = cluster;
                this.fetchAffiliateProducts(cluster.id);
            } else {
                this.selectedCluster = null;
                this.affiliateProducts = [];
            }
        },
        async fetchAffiliateProducts(clusterId) {
            this.loadingProducts = true;
            this.productsError = null;
            try {
                const res = await affiliateProductService.listForOwner(clusterId);
                this.affiliateProducts = res.data || [];
            } catch (err) {
                this.productsError = err.message || "Không thể tải danh sách sản phẩm tiếp thị.";
            } finally {
                this.loadingProducts = false;
            }
        },
        openCreateProductModal() {
            this.editingProduct = null;
            this.productFormError = null;
            this.productForm = {
                name: "",
                description: "",
                price: "",
                original_price: "",
                affiliate_url: "",
                platform_name: "Shopee",
                is_active: true,
                image: null,
            };
            this.productImagePreview = null;
            this.showProductModal = true;
        },
        openEditProductModal(product) {
            this.editingProduct = product;
            this.productFormError = null;
            this.productForm = {
                name: product.name,
                description: product.description || "",
                price: product.price ? parseFloat(product.price) : "",
                original_price: product.original_price ? parseFloat(product.original_price) : "",
                affiliate_url: product.affiliate_url,
                platform_name: product.platform_name || "Shopee",
                is_active: product.is_active !== false,
                image: null,
            };
            this.productImagePreview = product.image_path ? this.imageUrl(product.image_path) : null;
            this.showProductModal = true;
        },
        closeProductModal() {
            this.showProductModal = false;
            this.editingProduct = null;
            this.productFormError = null;
        },
        handleProductImageChange(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.productForm.image = file;
            this.productImagePreview = URL.createObjectURL(file);
        },
        async submitProductForm() {
            if (!this.productForm.name || !this.productForm.affiliate_url) {
                this.productFormError = "Vui lòng nhập đầy đủ các trường bắt buộc.";
                return;
            }
            this.submittingProduct = true;
            this.productFormError = null;
            
            const formData = new FormData();
            formData.append("name", this.productForm.name);
            formData.append("description", this.productForm.description || "");
            if (this.productForm.price !== "" && this.productForm.price !== null && this.productForm.price !== undefined) {
                formData.append("price", this.productForm.price);
            }
            if (this.productForm.original_price !== "" && this.productForm.original_price !== null && this.productForm.original_price !== undefined) {
                formData.append("original_price", this.productForm.original_price);
            }
            formData.append("affiliate_url", this.productForm.affiliate_url);
            formData.append("platform_name", this.productForm.platform_name);
            formData.append("is_active", this.productForm.is_active ? "1" : "0");
            
            if (this.productForm.image) {
                formData.append("image", this.productForm.image);
            }

            try {
                if (this.editingProduct) {
                    await affiliateProductService.update(this.editingProduct.id, formData);
                } else {
                    await affiliateProductService.create(this.selectedCluster.id, formData);
                }
                this.closeProductModal();
                this.fetchAffiliateProducts(this.selectedCluster.id);
            } catch (err) {
                this.productFormError = err.message || "Có lỗi xảy ra khi lưu sản phẩm.";
            } finally {
                this.submittingProduct = false;
            }
        },
        async handleDeleteProduct(productId) {
            if (!confirm("Bạn có chắc chắn muốn xóa sản phẩm tiếp thị này?")) return;
            try {
                await affiliateProductService.delete(productId);
                this.fetchAffiliateProducts(this.selectedCluster.id);
            } catch (err) {
                alert(err.message || "Không thể xóa sản phẩm.");
            }
        },
        async handleToggleProductStatus(productId) {
            try {
                await affiliateProductService.toggleStatus(productId);
                this.fetchAffiliateProducts(this.selectedCluster.id);
            } catch (err) {
                alert(err.message || "Không thể cập nhật trạng thái hiển thị.");
            }
        },
        selectPlatform(val) {
            this.productForm.platform_name = val;
            this.showPlatformDropdown = false;
        },
        platformLabel(val) {
            const opt = this.platformOptions.find(o => o.value === val);
            return opt ? opt.label : val;
        },
        imageUrl(path) {
            if (!path) return "";
            return path.startsWith("http") ? path : `/storage/${path}`;
        },
        formatCurrency(value) {
            if (!value) return "0đ";
            return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(value);
        },
        handleOutsideClick(e) {
            const trigger = this.$el.querySelector(".custom-select-trigger");
            const dropdown = this.$el.querySelector(".custom-options-container");
            if (dropdown && !dropdown.contains(e.target) && trigger && !trigger.contains(e.target)) {
                this.showPlatformDropdown = false;
            }
        },
    },
};
</script>

<style scoped>
.affiliate-shop-container {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

/* Empty State Card */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    justify-content: center;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
.empty-icon-wrapper {
    width: 64px;
    height: 64px;
    background-color: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
}
.empty-title {
    margin: 0 0 6px 0;
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
}
.empty-desc {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    max-width: 340px;
    line-height: 1.5;
}

/* Table View */
.affiliate-list-card {
    padding: 0;
    overflow: hidden;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
.table-scroll {
    overflow-x: auto;
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
    padding: 14px 16px;
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
.affiliate-table th.col-platform { width: 130px; }
.affiliate-table th.col-price { width: 160px; text-align: right; }
.affiliate-table td.cell-price { text-align: right; }
.affiliate-table th.col-clicks { width: 100px; text-align: center; }
.affiliate-table td.cell-clicks { text-align: center; font-weight: 700; color: #475569; }
.affiliate-table th.col-status { width: 120px; text-align: center; }
.affiliate-table td.cell-status { text-align: center; }
.affiliate-table th.col-actions { width: 120px; text-align: center; }
.affiliate-table td.cell-actions { text-align: center; }

.product-img-box {
    width: 52px;
    height: 52px;
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
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.product-desc {
    font-size: 12.5px;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}
.platform-badge {
    padding: 4px 10px;
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
    font-size: 14.5px;
}
.price-original {
    font-size: 11.5px;
    color: #94a3b8;
    text-decoration: line-through;
    margin-top: 2px;
}
.price-empty {
    font-style: italic;
    color: #94a3b8;
    font-size: 12px;
}

/* Switch Toggle Custom */
.switch-toggle {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}
.switch-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.slider-round {
    position: relative;
    width: 38px;
    height: 22px;
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
    width: 18px;
    height: 18px;
    background-color: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
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
    padding: 8px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-action-icon:hover {
    background-color: #f8fafc;
    border-color: #cbd5e1;
}
.btn-action-icon.edit {
    color: #475569;
}
.btn-action-icon.delete {
    border-color: #fee2e2;
    color: #dc2626;
}
.btn-action-icon.delete:hover {
    background-color: #fee2e2;
    border-color: #fca5a5;
}

/* Spinner */
.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #f3f4f6;
    border-top: 3px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Modal Backdrop & Container Styles */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.46);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 10000;
    padding: 20px;
}

.modal {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 800;
    margin: 0;
    color: #0f172a;
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
    overflow-y: auto;
    max-height: calc(90vh - 140px);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

/* Modal Design styles (Premium Glassmorphism-style) */
.product-edit-modal {
    max-width: 900px;
    width: min(900px, calc(100vw - 32px)) !important;
    border-radius: 16px;
    border: none;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}
.modal-layout-cols {
    display: flex;
    gap: 28px;
    align-items: stretch;
}
.form-fields-col {
    flex: 1.3;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.image-preview-col {
    flex: 0.9;
    border-left: 1px solid #e2e8f0;
    padding-left: 28px;
    display: flex;
    flex-direction: column;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
}

.form-control {
    width: 100% !important;
    max-width: none !important;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    color: #1e293b;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-sizing: border-box;
}

.form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.form-label-bold {
    font-weight: 700;
    font-size: 13.5px;
    color: #334155;
    display: block;
    margin-bottom: 6px;
}
.form-row-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.price-fields {
    grid-template-columns: 1fr 1fr;
}

/* Custom Select Dropdown styles */
.custom-select-trigger {
    background-color: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
    transition: all 0.2s;
}
.custom-select-trigger:hover, .custom-select-trigger.active {
    border-color: #94a3b8;
}
.custom-select-wrapper {
    position: relative;
    width: 100%;
}
.custom-options-container {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    z-index: 100;
    max-height: 200px;
    overflow-y: auto;
}
.custom-option {
    padding: 10px 14px;
    cursor: pointer;
    font-size: 14px;
    color: #1e293b;
    transition: background-color 0.15s;
}
.custom-option:hover {
    background-color: #f1f5f9;
}
.custom-option.selected {
    background-color: #e2e8f0;
    font-weight: 600;
}

/* Image Upload UI components */
.desktop-image-zone {
    flex: 1;
    border: 2px dashed #cbd5e1;
    background-color: #f8fafc;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    min-height: 260px;
    transition: all 0.2s;
}
.desktop-image-zone:hover {
    border-color: #3b82f6;
    background-color: #eff6ff;
}
.empty-image-placeholder {
    text-align: center;
    padding: 16px;
}
.preview-img-full {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
}
.hover-change-text {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(15, 23, 42, 0.75);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    text-align: center;
    padding: 8px 4px;
    backdrop-filter: blur(4px);
    opacity: 0;
    transition: opacity 0.2s;
}
.desktop-image-zone:hover .hover-change-text {
    opacity: 1;
}

/* Switch Toggle Custom */
.switch-toggle-custom {
    position: relative;
    width: 44px;
    height: 24px;
    background-color: #cbd5e1;
    border-radius: 9999px;
    transition: background-color 0.2s;
    cursor: pointer;
}
.toggle-dot {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background-color: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

.mobile-image-upload-trigger {
    display: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-layout-cols {
        flex-direction: column;
        gap: 16px;
    }
    .image-preview-col {
        display: none;
    }
    .mobile-image-upload-trigger {
        display: block;
    }
    .image-upload-box {
        border: 1px solid #cbd5e1;
        background-color: #f8fafc;
        border-radius: 8px;
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    .upload-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
    }
    .uploaded-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .change-img-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(15, 23, 42, 0.7);
        color: #fff;
        font-size: 11px;
        text-align: center;
        padding: 4px;
    }
}
</style>
