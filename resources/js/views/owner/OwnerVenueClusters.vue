<template>
    <div class="venue-clusters-container">
        <section class="page-head">
            <div>
                <h2>Quản lý cụm sân</h2>
                <p>Cập nhật thông tin vận hành, bản đồ, tiện ích và hình ảnh của cụm sân.</p>
            </div>
        </section>

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
            <!-- Cluster List -->
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
                        <p class="cluster-address">{{ cluster.address }}</p>
                    </div>
                </div>
            </div>

            <!-- Cluster Edit Form -->
            <div v-if="selectedCluster" class="cluster-edit card">
                <div class="edit-header">
                    <h3>Cấu hình chi tiết: {{ selectedCluster.name }}</h3>
                    <router-link
                        :to="{
                            name: 'owner-venue-courts',
                            query: { venue_cluster_id: selectedCluster.id },
                        }"
                        class="btn btn-outline btn-sm"
                    >
                        <AppIcon name="layers" size="15" />
                        <span>Quản lý sân con</span>
                    </router-link>
                </div>

                <form @submit.prevent="handleUpdate">
                    <div v-if="updateSuccess" class="alert alert-success">
                        Cập nhật thông tin cụm sân thành công!
                    </div>
                    <div v-if="updateError" class="alert alert-danger">
                        {{ updateError }}
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name"
                                >Tên cụm sân
                                <span class="required">*</span></label
                            >
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                required
                            />
                        </div>
                        <div class="form-group">
                            <label for="phone"
                                >Số điện thoại liên hệ
                                <span class="required">*</span></label
                            >
                            <input
                                id="phone"
                                v-model="form.phone_contact"
                                type="text"
                                class="form-control"
                                required
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address"
                            >Địa chỉ cụm sân
                            <span class="required">*</span></label
                        >
                        <input
                            id="address"
                            v-model="form.address"
                            type="text"
                            class="form-control"
                            required
                        />
                    </div>

                    <div class="form-group">
                        <label for="map_url">Google Map Link URL</label>
                        <div class="map-input-group">
                            <input
                                id="map_url"
                                v-model="form.map_url"
                                type="url"
                                class="form-control"
                                placeholder="https://maps.google.com/..."
                            />
                            <button
                                type="button"
                                class="btn btn-outline btn-extract"
                                :disabled="resolvingMap"
                                @click="handleExtractCoordinates"
                            >
                                <AppIcon name="search" size="15" />
                                {{
                                    resolvingMap
                                        ? "Đang trích xuất..."
                                        : "Trích xuất tọa độ"
                                }}
                            </button>
                        </div>
                        <p
                            v-if="mapExtractMsg"
                            :class="['map-extract-msg', mapExtractMsg.type]"
                        >
                            {{ mapExtractMsg.text }}
                        </p>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="latitude"
                                >Vĩ độ (Latitude)
                                <span class="required">*</span></label
                            >
                            <input
                                id="latitude"
                                v-model="form.latitude"
                                type="number"
                                step="0.0000001"
                                class="form-control"
                                required
                            />
                        </div>
                        <div class="form-group">
                            <label for="longitude"
                                >Kinh độ (Longitude)
                                <span class="required">*</span></label
                            >
                            <input
                                id="longitude"
                                v-model="form.longitude"
                                type="number"
                                step="0.0000001"
                                class="form-control"
                                required
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tiện ích cụm sân (Amenities)</label>
                        <div class="amenities-grid">
                            <label
                                v-for="item in availableAmenities"
                                :key="item"
                                class="amenity-checkbox"
                            >
                                <input
                                    v-model="form.amenities"
                                    type="checkbox"
                                    :value="item"
                                />
                                <span>{{ item }}</span>
                            </label>
                        </div>
                        <div class="amenity-request-tip">
                            Bạn không tìm thấy tiện ích mong muốn?
                            <a href="#" class="link-request-amenity" @click.prevent="openRequestModal">
                                Gửi yêu cầu thêm tiện ích mới
                            </a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Hình ảnh cụm sân (Album/Gallery)</label>
                        
                        <!-- Hiển thị lưới ảnh hiện tại -->
                        <div class="owner-gallery-grid" v-if="imagesList.length > 0">
                            <div v-for="img in imagesList" :key="img.id" class="owner-gallery-item">
                                <img :src="imageUrl(img.file_path)" alt="Hình ảnh cụm sân" class="owner-gallery-img" />
                                <button type="button" class="btn-delete-img" @click="handleDeleteImage(img.id)" title="Xóa hình ảnh này">×</button>
                            </div>
                        </div>
                        <div v-else class="owner-gallery-empty">
                            Chưa có hình ảnh nào được tải lên cho cụm sân này.
                        </div>

                        <!-- Khung upload ảnh mới -->
                        <div class="owner-upload-zone">
                            <input
                                type="file"
                                id="owner-image-upload"
                                accept="image/*"
                                multiple
                                class="hidden-file-input"
                                @change="handleImageUpload"
                                :disabled="uploadingImage"
                            />
                            <label for="owner-image-upload" class="upload-label-zone">
                                <span v-if="uploadingImage" class="upload-status-text">
                                    <div class="spinner-sm"></div> Đang tải lên ảnh...
                                </span>
                                <span v-else class="upload-status-text">
                                    Nhấp vào đây để tải lên ảnh mới (jpeg, png, webp, tối đa 5MB)
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Mô tả cụm sân</label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="form-control"
                            rows="4"
                        ></textarea>
                    </div>

                    <div class="form-actions">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="updating"
                        >
                            <AppIcon name="check" size="16" />
                            {{
                                updating
                                    ? "Đang cập nhật..."
                                    : "Cập nhật cụm sân"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Request Amenity Modal -->
        <div v-if="showRequestModal" class="modal-backdrop" @click.self="closeRequestModal">
            <div class="modal card">
                <div class="modal-header">
                    <h3>Gửi yêu cầu thêm tiện ích</h3>
                    <button class="btn-close" @click="closeRequestModal">
                        &times;
                    </button>
                </div>
                <form @submit.prevent="handleRequestSubmit">
                    <div class="modal-body">
                        <div v-if="requestError" class="alert alert-danger">
                            {{ requestError }}
                        </div>
                        <div v-if="requestSuccessMsg" class="alert alert-success">
                            {{ requestSuccessMsg }}
                        </div>

                        <div class="form-group">
                            <label for="req-name">
                                Tên tiện ích <span class="required">*</span>
                            </label>
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
                            <label for="req-description">
                                Mô tả tiện ích
                            </label>
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
                            {{ requestSubmitting ? "Đang gửi..." : "Gửi yêu cầu" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import { venueClusterService } from "../../services/venueClusters";
import { amenityService } from "../../services/amenityService";

export default {
    name: "OwnerVenueClusters",
    components: { AppIcon },
    data() {
        return {
            clusters: [],
            selectedCluster: null,
            loading: true,
            error: null,
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
                address: "",
                map_url: "",
                latitude: 21.0285,
                longitude: 105.8542,
                amenities: [],
                description: "",
            },
            showRequestModal: false,
            requestSubmitting: false,
            requestError: null,
            requestSuccessMsg: null,
            requestForm: {
                name: "",
                description: "",
            },
        };
    },

    methods: {
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
            // Nếu là link rút gọn maps.app.goo.gl hoặc goo.gl/maps thì gọi API Server-side để giải mã
            if (
                url.includes("maps.app.goo.gl") ||
                url.includes("goo.gl/maps")
            ) {
                try {
                    const res = await venueClusterService.resolveMapUrl(url);
                    if (res.latitude && res.longitude) {
                        this.form.latitude = res.latitude;
                        this.form.longitude = res.longitude;
                        this.mapExtractMsg = {
                            type: "success",
                            text: `Trích xuất thành công: Vĩ độ ${res.latitude}, Kinh độ ${res.longitude}`,
                        };
                        return;
                    }
                } catch (e) {
                    console.warn(
                        "Không thể giải mã link map từ Server-side:",
                        e.message,
                    );
                    this.mapExtractMsg = {
                        type: "error",
                        text: `Lỗi: ${e.message || "Không thể giải mã link rút gọn từ server."}`,
                    };
                    return;
                }
            }

            // Ví dụ URL: https://www.google.com/maps/place/21.028511,105.854167 hoặc @21.028511,105.854167,17z
            // Regex 1: Tìm mẫu @latitude,longitude
            let match = url.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
            if (match) {
                this.form.latitude = parseFloat(match[1]);
                this.form.longitude = parseFloat(match[2]);
                this.mapExtractMsg = {
                    type: "success",
                    text: `Trích xuất thành công: Vĩ độ ${match[1]}, Kinh độ ${match[2]}`,
                };
                return;
            }

            // Regex 2: Tìm mẫu !3dlatitude!4dlongitude (phổ biến trong link share của Google Maps)
            let match3d4d = url.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
            if (match3d4d) {
                this.form.latitude = parseFloat(match3d4d[1]);
                this.form.longitude = parseFloat(match3d4d[2]);
                this.mapExtractMsg = {
                    type: "success",
                    text: `Trích xuất thành công: Vĩ độ ${match3d4d[1]}, Kinh độ ${match3d4d[2]}`,
                };
                return;
            }

            // Regex 3: Tìm query q=latitude,longitude
            let matchQuery = url.match(/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/);
            if (matchQuery) {
                this.form.latitude = parseFloat(matchQuery[1]);
                this.form.longitude = parseFloat(matchQuery[2]);
                this.mapExtractMsg = {
                    type: "success",
                    text: `Trích xuất thành công: Vĩ độ ${matchQuery[1]}, Kinh độ ${matchQuery[2]}`,
                };
                return;
            }

            // Regex 4: Tìm cặp tọa độ trực tiếp trong URL dạng /place/latitude,longitude
            let matchCoords = url.match(/\/place\/(-?\d+\.\d+),(-?\d+\.\d+)/);
            if (matchCoords) {
                this.form.latitude = parseFloat(matchCoords[1]);
                this.form.longitude = parseFloat(matchCoords[2]);
                this.mapExtractMsg = {
                    type: "success",
                    text: `Trích xuất thành công: Vĩ độ ${matchCoords[1]}, Kinh độ ${matchCoords[2]}`,
                };
                return;
            }

            this.mapExtractMsg = {
                type: "error",
                text: "Không tìm thấy tọa độ trong link này. Hãy thử link đầy đủ từ Google Maps desktop.",
            };
        },
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
        selectCluster(cluster) {
            this.selectedCluster = cluster;
            localStorage.setItem("selected_cluster", cluster.id);
            window.dispatchEvent(new CustomEvent("owner-cluster-changed", { detail: cluster }));
            this.updateSuccess = false;
            this.updateError = null;
            this.imagesList = (cluster.media || []).filter(
                (img) => img.file_path && !img.file_path.includes("default-home.jpg")
            );
            this.form = {
                name: cluster.name,
                phone_contact: cluster.phone_contact || "",
                address: cluster.address,
                map_url: cluster.map_url || "",
                latitude: parseFloat(cluster.latitude || 21.0285),
                longitude: parseFloat(cluster.longitude || 105.8542),
                amenities: Array.isArray(cluster.amenities)
                    ? cluster.amenities
                    : [],
                description: cluster.description || "",
            };
        },
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
                // Cập nhật lại list ở cột bên trái
                const index = this.clusters.findIndex(
                    (c) => c.id === this.selectedCluster.id,
                );
                if (index !== -1) {
                    this.clusters[index] = {
                        ...this.clusters[index],
                        ...res.data,
                    };
                }
            } catch (err) {
                this.updateError = err.message || "Lỗi khi cập nhật cụm sân.";
            } finally {
                this.updating = false;
            }
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
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    // Validate kích thước 5MB
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`File ${file.name} vượt quá 5MB. Vui lòng chọn ảnh nhỏ hơn.`);
                        continue;
                    }
                    
                    const formData = new FormData();
                    formData.append("image", file);
                    
                    const res = await venueClusterService.uploadMedia(this.selectedCluster.id, formData);
                    this.imagesList.push(res.data);
                }
                // Đồng bộ lại media vào selectedCluster
                this.selectedCluster.media = [...this.imagesList];
            } catch (err) {
                this.updateError = err.message || "Tải lên hình ảnh thất bại.";
            } finally {
                this.uploadingImage = false;
                e.target.value = ""; // Clear input file
            }
        },
        async handleDeleteImage(mediaId) {
            if (!confirm("Bạn có chắc chắn muốn xóa hình ảnh này khỏi album?")) return;

            this.updateError = null;
            try {
                await venueClusterService.deleteMedia(this.selectedCluster.id, mediaId);
                this.imagesList = this.imagesList.filter((img) => img.id !== mediaId);
                this.selectedCluster.media = [...this.imagesList];
            } catch (err) {
                this.updateError = err.message || "Xóa hình ảnh thất bại.";
            }
        },
        async fetchAvailableAmenities() {
            try {
                const res = await amenityService.getAll(true); // Chỉ lấy active
                this.availableAmenities = (res.data || []).map(a => a.name);
            } catch (err) {
                console.error("Lỗi khi tải danh sách tiện ích:", err.message);
            }
        },
        openRequestModal() {
            this.showRequestModal = true;
            this.requestError = null;
            this.requestSuccessMsg = null;
            this.requestForm = {
                name: "",
                description: "",
            };
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
                this.requestSuccessMsg = "Gửi yêu cầu thành công. Vui lòng chờ admin duyệt.";
                setTimeout(() => {
                    this.closeRequestModal();
                }, 2000);
            } catch (err) {
                this.requestError = err.message || "Lỗi gửi yêu cầu.";
            } finally {
                this.requestSubmitting = false;
            }
        },
    },
    created() {
        this.fetchClusters();
        this.fetchAvailableAmenities();
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
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--sg-border);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    padding: 24px;
}



.clusters-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 20px;
    align-items: start;
}

@media (max-width: 768px) {
    .clusters-grid {
        grid-template-columns: 1fr;
    }
}

.clusters-list {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.cluster-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-radius: 8px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.cluster-item:hover {
    background: var(--sg-surface);
}

.cluster-item.active {
    background: rgba(0, 0, 0, 0.05);
    border-color: rgba(0, 0, 0, 0.2);
}

.cluster-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--sg-text);
    margin: 0;
}

.cluster-address {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.5);
    margin-top: 4px;
    text-overflow: ellipsis;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
}


.cluster-edit {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.edit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--sg-border);
    padding-bottom: 16px;
}

.edit-header h3 {
    font-size: 18px;
    font-weight: 800;
    color: var(--sg-text);
    margin: 0;
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

.amenities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    background: var(--sg-surface);
    padding: 16px;
    border-radius: 8px;
    border: 1px solid var(--sg-border);
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
    accent-color: #000000;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid var(--sg-border);
    padding-top: 20px;
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
    background: #f3f4f6;
    color: #ef4444;
    border: 1px solid #e5e7eb;
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
    color: #000000;
    border-left: 3px solid #000000;
}

.map-extract-msg.error {
    background: #f3f4f6;
    color: #ef4444;
    border-left: 3px solid #ef4444;
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

/* Owner Image Gallery & Upload Zone */
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
    transition: background 0.18s;
    z-index: 10;
}
.btn-delete-img:hover {
    background: rgb(220, 38, 38);
}
.owner-gallery-empty {
    padding: 18px;
    background: #f8fafc;
    border: 1px dashed var(--sg-border);
    border-radius: 8px;
    text-align: center;
    color: rgba(15, 23, 42, 0.45);
    font-size: 13px;
    margin-bottom: 12px;
}
.owner-upload-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    background: #fff;
    transition: border-color 0.2s, background-color 0.2s;
}
.owner-upload-zone:hover {
    border-color: #000000;
    background-color: #f8fafc;
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
    width: 100%;
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
.spinner-sm {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

/* Modal styles for requesting amenity */
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
    width: min(450px, 95vw);
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
    border-bottom: 1px solid var(--sg-border, #e2e8f0);
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
    border-top: 1px solid var(--sg-border, #e2e8f0);
    background: #f8fafc;
}

.amenity-request-tip {
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
}

.link-request-amenity {
    color: #000000;
    font-weight: 700;
    text-decoration: underline;
    cursor: pointer;
}
.link-request-amenity:hover {
    color: #333333;
}
</style>
