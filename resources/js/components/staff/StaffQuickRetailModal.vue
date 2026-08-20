<template>
  <Teleport to="body">
    <div v-if="isOpen" class="retail-modal-backdrop" @click.self="onClose">
      <div class="retail-dialog">
        <!-- HEADER -->
        <header class="retail-head">
          <div class="retail-head-title">
            <AppIcon name="shoppingBag" :size="18" class="text-green-main" />
            <h3>Bán nhanh Nước uống &amp; Dịch vụ quầy</h3>
          </div>
          <button type="button" class="retail-close-btn" aria-label="Đóng" @click="onClose">✕</button>
        </header>

        <!-- BODY: 2 COLUMNS (LEFT: PRODUCTS, RIGHT: CART & CHECKOUT) -->
        <div class="retail-body">
          <!-- LEFT: PRODUCTS CATALOG -->
          <div class="retail-catalog-col">
            <!-- Search & Category Filters -->
            <div class="catalog-toolbar">
              <div class="catalog-search">
                <AppIcon name="search" :size="14" class="search-icon" />
                <input
                  v-model.trim="searchKeyword"
                  type="text"
                  placeholder="Tìm nước, bóng, vợt..."
                  class="catalog-search-input"
                />
                <button
                  v-if="searchKeyword"
                  type="button"
                  class="search-clear"
                  @click="searchKeyword = ''"
                >
                  ✕
                </button>
              </div>

              <!-- Category filter tabs -->
              <div v-if="categories.length > 1" class="category-tabs">
                <button
                  type="button"
                  class="cat-tab"
                  :class="{ active: selectedCategory === 'all' }"
                  @click="selectedCategory = 'all'"
                >
                  Tất cả ({{ services.length }})
                </button>
                <button
                  v-for="cat in categories"
                  :key="cat.id"
                  type="button"
                  class="cat-tab"
                  :class="{ active: selectedCategory === cat.id }"
                  @click="selectedCategory = cat.id"
                >
                  {{ cat.name }}
                </button>
              </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="catalog-loading">
              <div class="loading-spinner"></div>
              <span>Đang tải danh mục hàng hóa...</span>
            </div>

            <!-- Products Grid -->
            <div v-else-if="filteredServices.length" class="products-grid">
              <button
                v-for="item in filteredServices"
                :key="item.id"
                type="button"
                class="product-card"
                @click="addToCart(item)"
              >
                <div class="prod-info">
                  <span class="prod-name">{{ item.name }}</span>
                  <span class="prod-unit">{{ item.unit || 'Đơn vị' }}</span>
                </div>
                <div class="prod-bottom">
                  <span class="prod-price">{{ formatCurrency(item.price) }}</span>
                  <span class="prod-add-tag">+ Thêm</span>
                </div>
              </button>
            </div>

            <div v-else class="catalog-empty">
              <p>Không tìm thấy mặt hàng nào phù hợp.</p>
            </div>
          </div>

          <!-- RIGHT: ORDER CART & PAYMENT -->
          <div class="retail-cart-col">
            <div class="cart-header">
              <span class="cart-title">Giỏ hàng thanh toán</span>
              <button
                v-if="cartItems.length"
                type="button"
                class="cart-clear-btn"
                @click="cartItems = []"
              >
                Xóa tất cả
              </button>
            </div>

            <!-- Cart Items List -->
            <div class="cart-items-list">
              <div v-if="!cartItems.length" class="cart-empty-msg">
                <span>Chưa chọn mặt hàng nào.</span>
                <small>Nhấp vào sản phẩm bên trái để thêm vào giỏ.</small>
              </div>

              <div
                v-for="item in cartItems"
                :key="item.id"
                class="cart-item-row"
              >
                <div class="item-meta">
                  <span class="item-name">{{ item.name }}</span>
                  <span class="item-price">{{ formatCurrency(item.price) }} / {{ item.unit || 'món' }}</span>
                </div>

                <div class="item-actions">
                  <div class="qty-control">
                    <button type="button" class="qty-btn" @click="changeQty(item, -1)">-</button>
                    <span class="qty-num">{{ item.quantity }}</span>
                    <button type="button" class="qty-btn" @click="changeQty(item, 1)">+</button>
                  </div>
                  <span class="item-total">{{ formatCurrency(item.price * item.quantity) }}</span>
                  <button
                    type="button"
                    class="item-del-btn"
                    title="Xóa"
                    @click="removeItem(item.id)"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>

            <!-- Cart Summary & Checkout Controls -->
            <div class="cart-checkout-box">
              <!-- Walk-in Customer Name (Optional) -->
              <div class="cart-field">
                <input
                  v-model.trim="customerNote"
                  type="text"
                  placeholder="Ghi chú khách / Tên sân (Tùy chọn)..."
                  class="cart-input"
                  maxlength="60"
                />
              </div>

              <!-- Payment Mode Selector -->
              <div class="pay-method-grid">
                <button
                  type="button"
                  class="pay-method-btn"
                  :class="{ active: paymentMethod === 'cash' }"
                  @click="paymentMethod = 'cash'"
                >
                  <AppIcon name="banknote" :size="15" />
                  <span>Tiền mặt</span>
                </button>
                <button
                  type="button"
                  class="pay-method-btn"
                  :class="{ active: paymentMethod === 'sepay' }"
                  @click="paymentMethod = 'sepay'"
                >
                  <AppIcon name="creditCard" :size="15" />
                  <span>VietQR</span>
                </button>
              </div>

              <!-- Total Amount -->
              <div class="cart-total-row">
                <span>Tổng thanh toán:</span>
                <strong class="total-money">{{ formatCurrency(totalAmount) }}</strong>
              </div>

              <!-- Submit Action Button -->
              <button
                type="button"
                class="cart-submit-btn"
                :disabled="!cartItems.length || submitting"
                @click="handleCheckout"
              >
                <span>{{ submitting ? 'Đang xử lý...' : (paymentMethod === 'cash' ? 'Thu tiền mặt & In phiếu' : 'Tạo mã VietQR') }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { api } from '../../services/api.js';
import { ownerVenueService } from '../../services/ownerVenueService.js';
import { playSuccessChime } from '../../utils/audioChime.js';

export default {
  name: 'StaffQuickRetailModal',
  components: { AppIcon },
  props: {
    isOpen: {
      type: Boolean,
      default: false,
    },
    clusterId: {
      type: [String, Number],
      default: null,
    },
  },
  emits: ['close', 'order-completed'],
  data() {
    return {
      loading: false,
      submitting: false,
      services: [],
      categories: [],
      selectedCategory: 'all',
      searchKeyword: '',
      cartItems: [],
      customerNote: '',
      paymentMethod: 'cash',
    };
  },
  computed: {
    filteredServices() {
      return this.services.filter((item) => {
        const matchesCat = this.selectedCategory === 'all' || String(item.category_id) === String(this.selectedCategory);
        const matchesKw = !this.searchKeyword || item.name.toLowerCase().includes(this.searchKeyword.toLowerCase());
        return matchesCat && matchesKw;
      });
    },
    totalAmount() {
      return this.cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    },
  },
  watch: {
    isOpen(val) {
      if (val) {
        this.loadServices();
      } else {
        this.cartItems = [];
        this.customerNote = '';
        this.searchKeyword = '';
      }
    },
  },
  methods: {
    async loadServices() {
      const cid = this.clusterId || localStorage.getItem('selected_cluster');
      if (!cid) return;
      this.loading = true;
      try {
        const response = await ownerVenueService.listForOwner(cid);
        const items = response.data || [];
        this.services = items.filter((s) => s.status === 'active' || s.status === 1 || !s.status);
        
        // Extract unique categories
        const catMap = new Map();
        items.forEach((item) => {
          if (item.category && item.category.id) {
            catMap.set(item.category.id, item.category);
          }
        });
        this.categories = Array.from(catMap.values());
      } catch (e) {
        this.services = [];
      } finally {
        this.loading = false;
      }
    },
    addToCart(product) {
      const existing = this.cartItems.find((item) => item.id === product.id);
      if (existing) {
        existing.quantity += 1;
      } else {
        this.cartItems.push({
          id: product.id,
          name: product.name,
          price: Number(product.price) || 0,
          unit: product.unit || '',
          quantity: 1,
        });
      }
    },
    changeQty(item, delta) {
      item.quantity += delta;
      if (item.quantity <= 0) {
        this.removeItem(item.id);
      }
    },
    removeItem(id) {
      this.cartItems = this.cartItems.filter((i) => i.id !== id);
    },
    async handleCheckout() {
      if (!this.cartItems.length) return;
      const cid = this.clusterId || localStorage.getItem('selected_cluster');
      if (!cid) return;

      this.submitting = true;
      try {
        const payload = {
          venue_cluster_id: cid,
          services: this.cartItems.map((item) => ({
            service_id: item.id,
            quantity: item.quantity,
          })),
          payment_method: this.paymentMethod,
          customer_note: this.customerNote || undefined,
        };

        const res = await api('/api/owner/retail-orders', {
          method: 'POST',
          body: JSON.stringify(payload),
        });

        playSuccessChime();
        this.$emit('order-completed', {
          items: this.cartItems,
          totalAmount: res.data?.total_amount || this.totalAmount,
          paymentMethod: this.paymentMethod,
          customerNote: this.customerNote,
          payment: res.data?.payment,
        });
        this.onClose();
      } catch (err) {
        alert(err.message || 'Không thể ghi nhận đơn bán lẻ.');
      } finally {
        this.submitting = false;
      }
    },
    onClose() {
      this.$emit('close');
    },
    formatCurrency(val) {
      if (val === undefined || val === null) return '0 đ';
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
      }).format(val);
    },
  },
};
</script>

<style scoped>
.retail-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(17, 24, 39, 0.6);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.retail-dialog {
  background: #ffffff;
  border-radius: 8px;
  max-width: 880px;
  width: 100%;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  color: #111827;
  font-weight: 400;
}

/* HEADER */
.retail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.retail-head-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.retail-head-title h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  color: #111827;
}

.text-green-main {
  color: #087642;
}

.retail-close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  color: #6b7280;
  cursor: pointer;
  padding: 4px;
}

/* BODY */
.retail-body {
  display: grid;
  grid-template-columns: 1fr 340px;
  flex: 1;
  min-height: 460px;
  max-height: calc(88vh - 55px);
  overflow: hidden;
}

/* LEFT CATALOG */
.retail-catalog-col {
  padding: 16px;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-y: auto;
}

.catalog-toolbar {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.catalog-search {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 10px;
  color: #9ca3af;
}

.catalog-search-input {
  width: 100%;
  padding: 8px 30px 8px 32px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13px;
  outline: none;
}

.catalog-search-input:focus {
  border-color: #087642;
}

.search-clear {
  position: absolute;
  right: 8px;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  font-size: 12px;
}

.category-tabs {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.cat-tab {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  color: #374151;
  cursor: pointer;
  white-space: nowrap;
}

.cat-tab.active {
  background: #087642;
  color: #ffffff;
  border-color: #087642;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.product-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 10px;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
}

.product-card:hover {
  border-color: #087642;
  background: #f0fdf4;
}

.prod-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.prod-name {
  font-size: 13px;
  font-weight: 500;
  color: #111827;
}

.prod-unit {
  font-size: 11px;
  color: #6b7280;
}

.prod-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.prod-price {
  font-size: 12.5px;
  font-weight: 500;
  color: #087642;
}

.prod-add-tag {
  font-size: 11px;
  color: #087642;
}

.catalog-loading,
.catalog-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
  color: #6b7280;
  font-size: 13px;
}

.loading-spinner {
  width: 20px;
  height: 20px;
  border: 2px solid #e5e7eb;
  border-top-color: #087642;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 8px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* RIGHT CART */
.retail-cart-col {
  padding: 16px;
  display: flex;
  flex-direction: column;
  background: #fafafa;
}

.cart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 10px;
  border-bottom: 1px solid #e5e7eb;
}

.cart-title {
  font-size: 13.5px;
  font-weight: 500;
  color: #111827;
}

.cart-clear-btn {
  background: transparent;
  border: none;
  color: #ef4444;
  font-size: 11.5px;
  cursor: pointer;
}

.cart-items-list {
  flex: 1;
  overflow-y: auto;
  padding: 10px 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cart-empty-msg {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 10px;
  color: #9ca3af;
  font-size: 12.5px;
  text-align: center;
  gap: 4px;
}

.cart-item-row {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 8px 10px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.item-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.item-name {
  font-size: 12.5px;
  font-weight: 500;
  color: #111827;
}

.item-price {
  font-size: 11px;
  color: #6b7280;
}

.item-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.qty-control {
  display: inline-flex;
  align-items: center;
  border: 1px solid #d1d5db;
  border-radius: 4px;
}

.qty-btn {
  width: 22px;
  height: 22px;
  background: #ffffff;
  border: none;
  font-size: 12px;
  cursor: pointer;
}

.qty-num {
  padding: 0 8px;
  font-size: 12px;
  font-weight: 500;
}

.item-total {
  font-size: 12.5px;
  font-weight: 500;
  color: #111827;
}

.item-del-btn {
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  font-size: 12px;
}

.item-del-btn:hover {
  color: #ef4444;
}

/* CHECKOUT BOX */
.cart-checkout-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 10px;
  border-top: 1px solid #e5e7eb;
}

.cart-input {
  width: 100%;
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 12px;
  outline: none;
}

.pay-method-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.pay-method-btn {
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  padding: 6px;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  color: #374151;
}

.pay-method-btn.active {
  background: #087642;
  color: #ffffff;
  border-color: #087642;
}

.cart-total-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
}

.total-money {
  font-size: 16px;
  color: #087642;
}

.cart-submit-btn {
  background: #087642;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 10px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  width: 100%;
}

.cart-submit-btn:disabled {
  background: #d1d5db;
  color: #9ca3af;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .retail-body {
    grid-template-columns: 1fr;
  }
  .products-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
