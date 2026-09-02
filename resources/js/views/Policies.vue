<template>
  <div class="sg-policy-page">
    <PublicNavbar />

    <!-- ───── HERO HEADER ───── -->
    <section class="sg-policy-hero">
      <div class="sg-policy-container text-center">
        <h1 class="sg-policy-hero__title">Trung Tâm Chính Sách & Điều Khoản</h1>
        <p class="sg-policy-hero__subtitle">
          Quy định minh bạch bảo vệ quyền lợi người chơi, đối tác chủ sân và đảm bảo chất lượng dịch vụ trên SportGo.
        </p>

        <p v-if="isLoading" class="sg-policy-sync-state">
          Đang tải dữ liệu chính sách mới nhất từ hệ thống...
        </p>
        <p v-else-if="loadError" class="sg-policy-sync-state sg-policy-sync-state--warning">
          {{ loadError }}
        </p>

        <!-- Search Bar -->
        <div class="sg-policy-search">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tìm kiếm nội dung, điều khoản hoặc từ khóa..."
            class="sg-policy-search__input"
          />
          <button v-if="searchQuery" type="button" class="sg-policy-search__clear" title="Xóa tìm kiếm" @click="searchQuery = ''">
            ✕
          </button>
        </div>
      </div>
    </section>

    <!-- ───── MAIN POLICY LAYOUT ───── -->
    <section class="sg-policy-body">
      <div class="sg-policy-container sg-policy-layout">
        
        <!-- Sidebar Navigation (DỮ LIỆU ĐỘNG 100% TỪ DATABASE) -->
        <aside class="sg-policy-sidebar">
          <div class="sg-sidebar-title">Danh Mục Chính Sách</div>

          <!-- Loading Skeleton -->
          <div v-if="isLoading" class="sg-sidebar-skeleton">
            <div v-for="i in 5" :key="i" class="sg-skeleton-item"></div>
          </div>

          <!-- Navigation Items (Vertical on Desktop, Horizontal Scroll on Mobile) -->
          <nav v-else class="sg-sidebar-nav">
            <button
              v-for="policy in dynamicPolicies"
              :key="policy.key"
              type="button"
              class="sg-sidebar-item"
              :class="{ 'is-active': activeKey === policy.key }"
              @click="selectPolicy(policy.key)"
            >
              <span class="sg-sidebar-item__title">{{ policy.title }}</span>
              <span v-if="policy.version" class="sg-sidebar-item__ver">v{{ policy.version }}</span>
            </button>
          </nav>
        </aside>

        <!-- Content Area -->
        <main class="sg-policy-content">
          <!-- Loading State -->
          <div v-if="isLoading" class="sg-content-loading">
            <div class="spinner-sm"></div>
            <span>Đang tải nội dung văn bản chính sách...</span>
          </div>

          <!-- Empty State -->
          <div v-else-if="!currentPolicy" class="sg-content-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <h3>Chưa có chính sách nào được kích hoạt</h3>
            <p>Vui lòng quay lại sau hoặc liên hệ bộ phận hỗ trợ của SportGo để được giải đáp.</p>
          </div>

          <!-- Content Document -->
          <template v-else>
            <!-- TOP ACTION BAR -->
            <div class="sg-policy-action-bar">
              <div class="sg-policy-meta-badges">
                <span v-if="currentPolicy.version" class="sg-badge-version">
                  Phiên bản {{ currentPolicy.version }}.0
                </span>
                <span v-if="currentPolicy.effective_from" class="sg-badge-date">
                  Hiệu lực từ: {{ formatDate(currentPolicy.effective_from) }}
                </span>
                <span class="sg-badge-published">
                  Ban hành: {{ formatDate(currentPolicy.published_at || currentPolicy.effective_from) }}
                </span>
              </div>

              <div class="sg-policy-action-btns">
                <button type="button" class="sg-btn-action" title="Sao chép liên kết chính sách" @click="copyPolicyLink">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                  </svg>
                  <span>{{ copySuccess ? "Đã sao chép" : "Chia sẻ" }}</span>
                </button>

                <button type="button" class="sg-btn-action sg-btn-print" title="In tài liệu" @click="printPolicy">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                  </svg>
                  <span>In / PDF</span>
                </button>
              </div>
            </div>

            <!-- POLICY DOCUMENT BODY -->
            <article class="sg-policy-document">
              <!-- Title & Summary Banner -->
              <h2 class="sg-doc-title">{{ currentPolicy.title }}</h2>

              <div v-if="currentPolicy.change_summary" class="sg-change-summary-box">
                <div class="sg-change-summary-title">Tóm tắt cập nhật:</div>
                <p>{{ currentPolicy.change_summary }}</p>
              </div>

              <!-- Extra: Platform Fee Tiers Table (Nếu là chính sách phí hoặc đối tác) -->
              <section v-if="['platform_fee', 'partner_contract'].includes(currentPolicy.key) && partnerTerms?.platform_fee" class="sg-partner-fee-panel">
                <div class="sg-partner-fee-panel__head">
                  <div>
                    <h3>{{ partnerTerms.platform_fee.title || "Biểu phí nền tảng dành cho Chủ sân" }}</h3>
                    <p>{{ partnerTerms.platform_fee.summary || "Bảng giá dịch vụ phần mềm quản lý sân theo quy mô" }}</p>
                  </div>
                  <span class="sg-fee-cycle-pill">{{ partnerTerms.platform_fee.billing_cycle_label || "Theo tháng" }}</span>
                </div>
                <div class="sg-partner-fee-table-wrap">
                  <table class="sg-partner-fee-table">
                    <thead>
                      <tr>
                        <th>QUY MÔ CỤM SÂN</th>
                        <th>ĐƠN GIÁ / SÂN / THÁNG</th>
                        <th>ƯU ĐÃI TRẢ THEO NĂM</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="tier in partnerTerms.platform_fee.tiers" :key="tier.id">
                        <td><strong>{{ tier.min_courts }}{{ tier.max_courts ? ` - ${tier.max_courts}` : '+' }} sân</strong></td>
                        <td class="sg-fee-val">{{ formatCurrency(tier.price_per_court_month) }}</td>
                        <td class="sg-fee-discount">{{ Number(tier.annual_discount_percent || 0) }}% giảm giá</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="sg-partner-fee-note">
                  Thời hạn thanh toán: {{ partnerTerms.platform_fee.settings?.default_due_days || 7 }} ngày kể từ ngày xuất kỳ phí.
                </p>
              </section>

              <!-- Sections List -->
              <div v-if="filteredSections.length" class="sg-doc-sections-wrap">
                <div
                  v-for="(section, idx) in filteredSections"
                  :key="idx"
                  class="sg-doc-section"
                >
                  <h3 v-if="section.heading" class="sg-doc-section__heading">
                    {{ section.heading }}
                  </h3>
                  <div class="sg-doc-section__body">
                    <p
                      v-for="(para, pIdx) in section.paragraphs"
                      :key="pIdx"
                      class="sg-doc-paragraph"
                      v-html="highlightKeyword(para)"
                    ></p>
                  </div>
                </div>
              </div>

              <!-- No Search Results Found -->
              <div v-else-if="searchQuery" class="sg-search-empty-state">
                <p>Không tìm thấy nội dung phù hợp với từ khóa "<strong>{{ searchQuery }}</strong>" trong chính sách này.</p>
                <button type="button" class="btn btn-outline btn-sm" @click="searchQuery = ''">
                  Xóa từ khóa tìm kiếm
                </button>
              </div>
            </article>
          </template>
        </main>
      </div>
    </section>
  </div>
</template>

<script>
import PublicNavbar from "../components/PublicNavbar.vue";
import { policyService } from "../services/policies.js";

export default {
  name: "PoliciesView",
  components: { PublicNavbar },
  data() {
    return {
      activeKey: "",
      searchQuery: "",
      isLoading: true,
      loadError: "",
      remotePolicies: [],
      partnerTerms: null,
      copySuccess: false,
    };
  },

  computed: {
    dynamicPolicies() {
      return this.remotePolicies.map((p) => ({
        ...p,
        title: p.title || "Chính sách",
        key: p.key || `policy-${p.id}`,
      }));
    },

    currentPolicy() {
      if (!this.dynamicPolicies.length) return null;
      const found = this.dynamicPolicies.find((p) => p.key === this.activeKey);
      return found || this.dynamicPolicies[0];
    },

    parsedSections() {
      if (!this.currentPolicy || !this.currentPolicy.content) return [];
      const raw = String(this.currentPolicy.content || "").trim();

      const lines = raw.split("\n");
      const sections = [];
      let currentSection = { heading: "", paragraphs: [] };

      lines.forEach((line) => {
        const trimmed = line.trim();
        if (!trimmed) {
          return;
        }

        const isHeading =
          /^(#{1,4}\s+|Điều\s+\d+|Phần\s+\d+|\d+\.\s+[A-ZÀ-Ỵ])/i.test(trimmed);

        if (isHeading) {
          if (currentSection.heading || currentSection.paragraphs.length) {
            sections.push(currentSection);
          }
          currentSection = {
            heading: trimmed.replace(/^#{1,4}\s*/, ""),
            paragraphs: [],
          };
        } else {
          currentSection.paragraphs.push(trimmed);
        }
      });

      if (currentSection.heading || currentSection.paragraphs.length) {
        sections.push(currentSection);
      }

      if (!sections.length || (sections.length === 1 && !sections[0].heading)) {
        const paragraphs = raw
          .split(/\n\s*\n/)
          .map((p) => p.trim())
          .filter(Boolean);
        return [{ heading: "Nội dung điều khoản", paragraphs }];
      }

      return sections;
    },

    filteredSections() {
      if (!this.searchQuery.trim()) {
        return this.parsedSections;
      }
      const q = this.searchQuery.toLowerCase().trim();
      return this.parsedSections
        .map((sec) => {
          const headingMatch = sec.heading.toLowerCase().includes(q);
          const matchingParas = sec.paragraphs.filter((p) =>
            p.toLowerCase().includes(q)
          );
          if (headingMatch || matchingParas.length) {
            return {
              heading: sec.heading,
              paragraphs: headingMatch ? sec.paragraphs : matchingParas,
            };
          }
          return null;
        })
        .filter(Boolean);
    },
  },

  watch: {
    "$route.query.tab"(newTab) {
      if (newTab && newTab !== this.activeKey) {
        this.activeKey = newTab;
      }
    },
  },

  mounted() {
    this.loadPolicies();
  },

  methods: {
    async loadPolicies() {
      this.isLoading = true;
      this.loadError = "";
      try {
        const response = await policyService.list();
        const data = response?.data || response || {};
        this.remotePolicies = Array.isArray(data.policies) ? data.policies : [];
        this.partnerTerms = data.partner_onboarding || null;

        const queryTab = this.$route.query.tab;
        if (queryTab && this.remotePolicies.some((p) => p.key === queryTab)) {
          this.activeKey = queryTab;
        } else if (this.remotePolicies.length) {
          this.activeKey = this.remotePolicies[0].key;
        }
      } catch (error) {
        this.loadError = "Không thể tải danh sách chính sách từ máy chủ. Vui lòng thử lại sau.";
      } finally {
        this.isLoading = false;
      }
    },

    selectPolicy(key) {
      this.activeKey = key;
      this.searchQuery = "";
      this.$router.replace({
        query: { ...this.$route.query, tab: key },
      }).catch(() => {});
    },

    copyPolicyLink() {
      const url = `${window.location.origin}/policies?tab=${this.activeKey}`;
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(url).then(() => {
          this.copySuccess = true;
          setTimeout(() => {
            this.copySuccess = false;
          }, 2000);
        });
      }
    },

    printPolicy() {
      window.print();
    },

    highlightKeyword(text) {
      if (!this.searchQuery.trim()) return text;
      const q = this.searchQuery.trim().replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      const regex = new RegExp(`(${q})`, "gi");
      return text.replace(regex, '<mark class="sg-search-highlight">$1</mark>');
    },

    formatDate(dateStr) {
      if (!dateStr) return "Đang cập nhật";
      try {
        const d = new Date(dateStr);
        if (Number.isNaN(d.getTime())) return dateStr;
        return new Intl.DateTimeFormat("vi-VN", {
          day: "2-digit",
          month: "2-digit",
          year: "numeric",
        }).format(d);
      } catch {
        return dateStr;
      }
    },

    formatCurrency(value) {
      const num = Number(value || 0);
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(num);
    },
  },
};
</script>

<style scoped>
.sg-policy-page {
  min-height: 100vh;
  background-color: #f8fafc;
  color: #1e293b;
  font-family: inherit;
  box-sizing: border-box;
}

/* Độc lập và cách ly khỏi ghi đè của client.css */
.sg-policy-container {
  max-width: 1280px;
  width: 100%;
  margin: 0 auto;
  padding: 0 24px;
  box-sizing: border-box;
}

.text-center {
  text-align: center;
}

/* ───── HERO HEADER (CÓ PADDING TOP TRÁNH BỊ NAVBAR ĐÈ) ───── */
.sg-policy-hero {
  background: linear-gradient(135deg, #15803d 0%, #166534 100%);
  color: #ffffff;
  padding: 48px 0 44px;
  position: relative;
  box-sizing: border-box;
}

.sg-policy-hero__title {
  font-size: 30px;
  font-weight: 800;
  margin: 0 0 10px;
  letter-spacing: -0.4px;
  color: #ffffff;
  line-height: 1.3;
}

.sg-policy-hero__subtitle {
  font-size: 15px;
  color: #dcfce7;
  max-width: 680px;
  margin: 0 auto 22px;
  line-height: 1.55;
}

.sg-policy-sync-state {
  font-size: 13px;
  color: #bbf7d0;
  margin-bottom: 14px;
}

.sg-policy-sync-state--warning {
  color: #fef08a;
}

/* Search Bar */
.sg-policy-search {
  max-width: 560px;
  width: 100%;
  margin: 0 auto;
  position: relative;
  display: flex;
  align-items: center;
  box-sizing: border-box;
}

.sg-policy-search svg {
  position: absolute;
  left: 16px;
  color: #64748b;
  pointer-events: none;
}

.sg-policy-search__input {
  width: 100%;
  padding: 12px 42px 12px 46px;
  border-radius: 999px;
  border: 1.5px solid transparent;
  background: #ffffff;
  color: #0f172a;
  font-size: 14px;
  outline: none;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.14);
  transition: all 0.15s ease;
  box-sizing: border-box;
}

.sg-policy-search__input:focus {
  border-color: #86efac;
  box-shadow: 0 8px 26px rgba(0, 0, 0, 0.2);
}

.sg-policy-search__clear {
  position: absolute;
  right: 14px;
  background: #e2e8f0;
  color: #475569;
  border: none;
  border-radius: 50%;
  width: 22px;
  height: 22px;
  font-size: 11px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ───── MAIN LAYOUT ───── */
.sg-policy-body {
  padding: 32px 0 64px;
  box-sizing: border-box;
}

.sg-policy-layout {
  display: grid;
  grid-template-columns: 310px 1fr;
  gap: 28px;
  align-items: start;
}

/* ───── SIDEBAR ───── */
.sg-policy-sidebar {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  position: sticky;
  top: 84px;
  min-width: 0;
  box-sizing: border-box;
}

.sg-sidebar-title {
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 12px;
  padding: 0 8px;
}

.sg-sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.sg-sidebar-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  color: #334155;
  font-size: 13.5px;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
  transition: all 0.15s ease;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.sg-sidebar-item:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.sg-sidebar-item.is-active {
  background: #f0fdf4;
  border-color: #86efac;
  color: #15803d;
  font-weight: 700;
}

.sg-sidebar-item__title {
  flex: 1;
  min-width: 0;
  white-space: normal;
  word-break: break-word;
  line-height: 1.4;
}

.sg-sidebar-item__ver {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  background: #e2e8f0;
  color: #475569;
  flex-shrink: 0;
}

.sg-sidebar-item.is-active .sg-sidebar-item__ver {
  background: #15803d;
  color: #ffffff;
}

.sg-sidebar-skeleton {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sg-skeleton-item {
  height: 38px;
  background: #f1f5f9;
  border-radius: 6px;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}

/* ───── CONTENT AREA ───── */
.sg-policy-content {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 30px 32px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  min-height: 480px;
  min-width: 0;
  box-sizing: border-box;
}

.sg-content-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 0;
  color: #64748b;
  gap: 12px;
}

.sg-content-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
}

.sg-content-empty h3 {
  margin: 16px 0 8px;
  color: #0f172a;
  font-size: 18px;
}

/* Top Action Bar */
.sg-policy-action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 18px;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 24px;
}

.sg-policy-meta-badges {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 12px;
}

.sg-badge-version {
  background: #15803d;
  color: #ffffff;
  padding: 3px 8px;
  border-radius: 999px;
  font-weight: 700;
}

.sg-badge-date,
.sg-badge-published {
  background: #f1f5f9;
  color: #475569;
  padding: 3px 8px;
  border-radius: 999px;
  font-weight: 500;
}

.sg-policy-action-btns {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sg-btn-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sg-btn-action:hover {
  background: #f8fafc;
  border-color: #15803d;
  color: #15803d;
}

/* Document Body */
.sg-doc-title {
  font-size: 23px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 16px;
  letter-spacing: -0.3px;
  line-height: 1.35;
}

.sg-change-summary-box {
  background: #eff6ff;
  border-left: 3.5px solid #3b82f6;
  border-radius: 0 8px 8px 0;
  padding: 12px 16px;
  margin-bottom: 24px;
}

.sg-change-summary-title {
  font-size: 12.5px;
  font-weight: 700;
  color: #1d4ed8;
  margin-bottom: 4px;
}

.sg-change-summary-box p {
  margin: 0;
  font-size: 13.5px;
  color: #1e3a8a;
  line-height: 1.5;
}

/* Sections */
.sg-doc-sections-wrap {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sg-doc-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sg-doc-section__heading {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  line-height: 1.4;
  padding-bottom: 4px;
  border-bottom: 1px solid #f1f5f9;
}

.sg-doc-paragraph {
  font-size: 14.5px;
  color: #334155;
  line-height: 1.7;
  margin: 0;
  white-space: pre-line;
}

/* Platform Fee Panel */
.sg-partner-fee-panel {
  background: #f0fdf4;
  border: 1.5px solid #86efac;
  border-radius: 10px;
  padding: 18px 20px;
  margin-bottom: 26px;
  box-sizing: border-box;
}

.sg-partner-fee-panel__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}

.sg-partner-fee-panel__head h3 {
  margin: 0 0 4px;
  font-size: 15.5px;
  font-weight: 700;
  color: #15803d;
}

.sg-partner-fee-panel__head p {
  margin: 0;
  font-size: 13px;
  color: #166534;
}

.sg-fee-cycle-pill {
  background: #15803d;
  color: #ffffff;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  white-space: nowrap;
}

.sg-partner-fee-table-wrap {
  overflow-x: auto;
  background: #ffffff;
  border-radius: 6px;
  border: 1px solid #bbf7d0;
  width: 100%;
}

.sg-partner-fee-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
  min-width: 480px;
}

.sg-partner-fee-table th,
.sg-partner-fee-table td {
  padding: 9px 12px;
  border-bottom: 1px solid #dcfce7;
  text-align: left;
}

.sg-partner-fee-table th {
  background: #f8fafc;
  color: #475569;
  font-weight: 700;
  font-size: 12px;
  letter-spacing: 0.3px;
}

.sg-fee-val {
  color: #15803d;
  font-weight: 700;
}

.sg-fee-discount {
  color: #b45309;
  font-weight: 600;
}

.sg-partner-fee-note {
  margin: 10px 0 0;
  font-size: 12px;
  color: #15803d;
}

/* Search Highlight */
:deep(.sg-search-highlight) {
  background: #fef08a;
  color: #854d0e;
  padding: 1px 3px;
  border-radius: 2px;
  font-weight: 600;
}

.sg-search-empty-state {
  padding: 30px;
  text-align: center;
  background: #f8fafc;
  border-radius: 8px;
  color: #64748b;
  font-size: 14px;
}

.sg-search-empty-state p {
  margin: 0 0 12px;
}

/* Print Styles */
@media print {
  .sg-policy-hero,
  .sg-policy-sidebar,
  .sg-policy-action-bar,
  header {
    display: none !important;
  }
  .sg-policy-layout {
    display: block !important;
  }
  .sg-policy-content {
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
  }
}

/* ───── RESPONSIVE DESIGN ───── */
@media (max-width: 992px) {
  .sg-policy-layout {
    grid-template-columns: 1fr;
    gap: 20px;
  }

  .sg-policy-sidebar {
    position: static;
    padding: 12px;
  }

  .sg-sidebar-title {
    display: none;
  }

  .sg-sidebar-nav {
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 6px;
    gap: 8px;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }

  .sg-sidebar-nav::-webkit-scrollbar {
    display: none;
  }

  .sg-sidebar-item {
    width: auto;
    flex-shrink: 0;
    white-space: nowrap;
    border-radius: 999px;
    padding: 7px 14px;
    background: #f1f5f9;
  }

  .sg-sidebar-item__title {
    white-space: nowrap;
  }

  .sg-sidebar-item.is-active {
    background: #15803d;
    border-color: #15803d;
    color: #ffffff;
  }

  .sg-sidebar-item.is-active .sg-sidebar-item__ver {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
  }

  .sg-policy-content {
    padding: 22px 18px;
  }
}

@media (max-width: 640px) {
  .sg-policy-hero {
    padding: 36px 0 32px;
  }

  .sg-policy-hero__title {
    font-size: 24px;
  }

  .sg-policy-hero__subtitle {
    font-size: 13.5px;
    margin-bottom: 18px;
  }

  .sg-policy-container {
    padding: 0 16px;
  }

  .sg-policy-action-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .sg-policy-action-btns {
    width: 100%;
    justify-content: flex-end;
  }

  .sg-doc-title {
    font-size: 20px;
  }
}
</style>
