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
                        Quản lý sân con
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
    </div>
</template>

<script>
import { venueClusterService } from "../../services/venueClusters";

export default {
    name: "OwnerVenueClusters",
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
            availableAmenities: [
                "Wifi",
                "Gửi xe",
                "Căng tin",
                "Tắm nóng lạnh",
                "Cho thuê vợt",
                "Nước uống free",
            ],
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
            this.updateSuccess = false;
            this.updateError = null;
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
    },
    created() {
        this.fetchClusters();
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
</style>
