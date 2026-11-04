<template>
    <AdminLayout
        :stats="props.stats"
        :open-requests-count="props.openRequestsCount"
        :chat-unread-count="props.chatUnreadCount"
        :support-projects-count="props.supportProjectsCount"
    >
        <div class="page-head">
            <div>
                <h2>Tối ưu SEO &amp; Hiển thị Google Search</h2>
                <p>Quản lý thẻ Meta, OpenGraph, cấu trúc dữ liệu Schema.org JSON-LD, Sitemap XML và SEO cho tất cả Sản phẩm, Danh mục, Bài viết.</p>
            </div>
            <div class="actions">
                <a class="btn" href="/sitemap.xml" target="_blank" rel="noopener">
                    <FontAwesomeIcon icon="fa-solid fa-sitemap" /> Mở Sitemap XML
                </a>
                <button class="btn" type="button" :disabled="isGeneratingAll" @click="triggerGenerateAllSeo">
                    <FontAwesomeIcon :icon="isGeneratingAll ? 'fa-solid fa-spinner' : 'fa-solid fa-wand-magic-sparkles'" />
                    {{ isGeneratingAll ? 'Đang tạo SEO...' : 'Tự động tạo SEO tất cả' }}
                </button>
                <button class="btn" type="button" :disabled="isPinging" @click="triggerManualPing">
                    <FontAwesomeIcon :icon="isPinging ? 'fa-solid fa-spinner' : 'fa-solid fa-bolt'" />
                    {{ isPinging ? 'Đang gửi tín hiệu...' : 'Đẩy tín hiệu lên Google ngay' }}
                </button>
                <button class="btn" type="button" :disabled="form.processing" @click="restoreDefaults">
                    <FontAwesomeIcon icon="fa-solid fa-rotate-left" /> Khôi phục mặc định
                </button>
                <button class="btn primary" type="button" :disabled="form.processing" @click="submitSeoSettings">
                    <FontAwesomeIcon :icon="form.processing ? 'fa-solid fa-spinner' : 'fa-solid fa-floppy-disk'" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu cấu hình SEO' }}
                </button>
            </div>
        </div>

        <section class="seo-overview-strip">
            <div class="seo-score-card">
                <div class="seo-score-ring" :class="seoScoreClass">
                    <strong>{{ seoScore }}</strong>
                    <span>/ 100</span>
                </div>
                <div class="seo-score-copy">
                    <span class="seo-badge" :class="seoScoreClass">{{ seoScoreLabel }}</span>
                    <h3>Điểm tối ưu SEO kỹ thuật</h3>
                    <p>{{ passedChecksCount }}/{{ seoChecklist.length }} hạng mục đạt chuẩn hiển thị trên Google Search.</p>
                </div>
            </div>

            <div class="seo-metric-card">
                <span class="seo-metric-label">TỔNG URL TRONG SITEMAP.XML</span>
                <strong class="seo-metric-value">{{ props.seoAudit.totalIndexedUrls }}</strong>
                <small>
                    {{ props.seoAudit.staticPagesCount }} trang tĩnh · {{ props.seoAudit.categoriesCount }} danh mục · {{ props.seoAudit.productsCount }} sản phẩm · {{ props.seoAudit.projectsCount }} dự án tiêu biểu · {{ props.seoAudit.articlesCount }} bài viết
                </small>
            </div>

            <div class="seo-metric-card">
                <span class="seo-metric-label">TRẠNG THÁI CHỈ MỤC GOOGLEBOT</span>
                <strong class="seo-metric-value" :class="{ 'is-warn': isNoIndex }">
                    {{ isNoIndex ? 'Đang chặn (NoIndex)' : 'Cho phép Index' }}
                </strong>
                <small>{{ form.seo_robots }}</small>
            </div>

            <div class="seo-metric-card">
                <span class="seo-metric-label">TỰ ĐỘNG ĐỀ XUẤT LÊN GOOGLE</span>
                <strong class="seo-metric-value">
                    {{ detectedServiceAccountEmail ? 'Search Console API' : 'Auto-Ping Bật' }}
                </strong>
                <small v-if="props.settings.seo_last_ping_at">
                    Gần nhất: {{ props.settings.seo_last_ping_at }} · {{ props.settings.seo_last_ping_status || props.settings.seo_last_ping_url }}
                </small>
                <small v-else>
                    Tự động bắn tín hiệu Sitemap API, WebSub &amp; IndexNow khi thêm Sản phẩm / Danh mục / Bài viết
                </small>
            </div>
        </section>

        <div class="seo-tabs-bar" role="tablist" aria-label="Chuyên mục cấu hình SEO">
            <button
                type="button"
                class="seo-tab-btn"
                :class="{ active: activeTab === 'entities' }"
                @click="activeTab = 'entities'"
            >
                <FontAwesomeIcon icon="fa-solid fa-boxes-stacked" />
                SEO Sản phẩm, Danh mục, Dự án &amp; Bài viết ({{ totalEntitiesCount }})
            </button>
            <button
                type="button"
                class="seo-tab-btn"
                :class="{ active: activeTab === 'general' }"
                @click="activeTab = 'general'"
            >
                <FontAwesomeIcon icon="fa-solid fa-globe" /> Cấu hình Meta &amp; Google Tools
            </button>
            <button
                type="button"
                class="seo-tab-btn"
                :class="{ active: activeTab === 'pages' }"
                @click="activeTab = 'pages'"
            >
                <FontAwesomeIcon icon="fa-solid fa-layer-group" /> Tối ưu SEO trang tĩnh ({{ pageConfigs.length }})
            </button>
            <button
                type="button"
                class="seo-tab-btn"
                :class="{ active: activeTab === 'audit' }"
                @click="activeTab = 'audit'"
            >
                <FontAwesomeIcon icon="fa-solid fa-clipboard-check" /> Kiểm toán Nội dung &amp; Sitemap XML
            </button>
        </div>

        <div class="seo-workspace-grid">
            <div class="seo-main-column">
                <template v-if="activeTab === 'entities'">
                    <section class="site-settings-card">
                        <div class="site-settings-card-head">
                            <span class="site-settings-card-icon">
                                <FontAwesomeIcon icon="fa-solid fa-wand-magic-sparkles" />
                            </span>
                            <div class="seo-page-card-title-wrap">
                                <div>
                                    <h3>Quản lý &amp; Tự động hóa SEO cho từng Sản phẩm, Danh mục, Dự án tiêu biểu, Bài viết</h3>
                                    <p>Mỗi mục đều có Meta Title, Meta Description, Meta Keywords, Schema.org và Canonical URL riêng biệt trên Google.</p>
                                </div>
                                <button
                                    type="button"
                                    class="btn primary"
                                    :disabled="isGeneratingAll"
                                    @click="triggerGenerateAllSeo"
                                >
                                    <FontAwesomeIcon :icon="isGeneratingAll ? 'fa-solid fa-spinner' : 'fa-solid fa-wand-magic-sparkles'" />
                                    {{ isGeneratingAll ? 'Đang tạo SEO...' : 'Tự động chuẩn hóa SEO toàn bộ' }}
                                </button>
                            </div>
                        </div>

                        <div class="seo-entity-toolbar">
                            <div class="seo-tabs-bar" style="margin-bottom: 0">
                                <button
                                    type="button"
                                    class="seo-tab-btn"
                                    :class="{ active: entitySubTab === 'product' }"
                                    @click="entitySubTab = 'product'"
                                >
                                    <FontAwesomeIcon icon="fa-solid fa-box" />
                                    Tất cả Sản phẩm ({{ props.seoEntities.products.length }})
                                </button>
                                <button
                                    type="button"
                                    class="seo-tab-btn"
                                    :class="{ active: entitySubTab === 'category' }"
                                    @click="entitySubTab = 'category'"
                                >
                                    <FontAwesomeIcon icon="fa-solid fa-sitemap" />
                                    Tất cả Danh mục ({{ props.seoEntities.categories.length }})
                                </button>
                                <button
                                    type="button"
                                    class="seo-tab-btn"
                                    :class="{ active: entitySubTab === 'project' }"
                                    @click="entitySubTab = 'project'"
                                >
                                    <FontAwesomeIcon icon="fa-solid fa-building" />
                                    Tất cả Dự án tiêu biểu ({{ props.seoEntities.projects.length }})
                                </button>
                                <button
                                    type="button"
                                    class="seo-tab-btn"
                                    :class="{ active: entitySubTab === 'article' }"
                                    @click="entitySubTab = 'article'"
                                >
                                    <FontAwesomeIcon icon="fa-solid fa-newspaper" />
                                    Tất cả Bài viết ({{ props.seoEntities.articles.length }})
                                </button>
                            </div>

                            <input
                                v-model="entitySearch"
                                class="search"
                                type="search"
                                placeholder="Tìm theo tên sản phẩm, danh mục, dự án, bài viết, từ khóa..."
                                aria-label="Tìm mục cần chỉnh sửa SEO"
                            >
                        </div>
                    </section>

                    <div v-if="filteredEntities.length === 0" class="empty">
                        Không tìm thấy mục nào phù hợp với từ khóa "{{ entitySearch }}".
                    </div>

                    <section
                        v-for="item in filteredEntities"
                        :key="`${item.type}-${item.id}`"
                        class="site-settings-card seo-page-card"
                        :class="{ 'is-previewing': activeEntityPreview?.type === item.type && activeEntityPreview?.id === item.id }"
                    >
                        <div class="site-settings-card-head">
                            <span class="site-settings-card-icon">
                                <FontAwesomeIcon
                                    :icon="item.type === 'product' ? 'fa-solid fa-box' : (item.type === 'category' ? 'fa-solid fa-folder-tree' : (item.type === 'project' ? 'fa-solid fa-building' : 'fa-solid fa-newspaper'))"
                                />
                            </span>
                            <div class="seo-page-card-title-wrap">
                                <div>
                                    <h3>
                                        {{ item.displayName }}
                                        <span class="seo-char-badge is-good" style="margin-left: 6px">{{ item.badgeLabel }}</span>
                                    </h3>
                                    <p>
                                        URL chuẩn SEO:
                                        <a :href="item.path" target="_blank" rel="noopener">
                                            <code>{{ displayBaseUrl }}{{ item.path }}</code>
                                        </a>
                                    </p>
                                </div>
                                <div class="row-actions">
                                    <button
                                        type="button"
                                        class="btn seo-preview-trigger"
                                        @click="selectEntityForPreview(item)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-eye" /> Xem thử Google
                                    </button>
                                    <button
                                        type="button"
                                        class="btn seo-preview-trigger"
                                        :disabled="savingEntityKey === `${item.type}-${item.id}`"
                                        @click="regenerateSingleEntitySeo(item)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-wand-magic-sparkles" /> Tạo tự động
                                    </button>
                                    <button
                                        type="button"
                                        class="btn primary seo-preview-trigger"
                                        :disabled="savingEntityKey === `${item.type}-${item.id}`"
                                        @click="saveSingleEntitySeo(item)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-floppy-disk" />
                                        {{ savingEntityKey === `${item.type}-${item.id}` ? 'Đang lưu...' : 'Lưu SEO' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="formgrid">
                            <div class="field full">
                                <div class="seo-label-row">
                                    <label :for="`entity-title-${item.type}-${item.id}`">TIÊU ĐỀ SEO (META TITLE) *</label>
                                    <span class="seo-char-badge" :class="titleLengthClass(getEntityDraft(item).seo_title.length)">
                                        {{ getEntityDraft(item).seo_title.length }} / 65 ký tự khuyến nghị
                                    </span>
                                </div>
                                <input
                                    :id="`entity-title-${item.type}-${item.id}`"
                                    v-model="getEntityDraft(item).seo_title"
                                    type="text"
                                    maxlength="180"
                                    placeholder="Tiêu đề hiển thị trên Google Search..."
                                    @focus="selectEntityForPreview(item)"
                                >
                            </div>

                            <div class="field full">
                                <div class="seo-label-row">
                                    <label :for="`entity-desc-${item.type}-${item.id}`">MÔ TẢ SEO (META DESCRIPTION) *</label>
                                    <span class="seo-char-badge" :class="descLengthClass(getEntityDraft(item).seo_description.length)">
                                        {{ getEntityDraft(item).seo_description.length }} / 160 ký tự khuyến nghị
                                    </span>
                                </div>
                                <textarea
                                    :id="`entity-desc-${item.type}-${item.id}`"
                                    v-model="getEntityDraft(item).seo_description"
                                    rows="2"
                                    maxlength="350"
                                    placeholder="Mô tả tóm tắt thu hút khách hàng nhấp vào trên Google..."
                                    @focus="selectEntityForPreview(item)"
                                ></textarea>
                            </div>

                            <div class="field full">
                                <label :for="`entity-kw-${item.type}-${item.id}`">TỪ KHÓA MỤC TIÊU (META KEYWORDS - CÁCH NHAU BỞI DẤU PHẨY)</label>
                                <input
                                    :id="`entity-kw-${item.type}-${item.id}`"
                                    v-model="getEntityDraft(item).seo_keywords"
                                    type="text"
                                    maxlength="500"
                                    placeholder="Từ khóa tìm kiếm liên quan..."
                                    @focus="selectEntityForPreview(item)"
                                >
                            </div>
                        </div>
                    </section>
                </template>

                <form v-else @submit.prevent="submitSeoSettings">
                    <template v-if="activeTab === 'general'">
                        <section class="site-settings-card">
                            <div class="site-settings-card-head">
                                <span class="site-settings-card-icon">
                                    <FontAwesomeIcon icon="fa-solid fa-magnifying-glass" />
                                </span>
                                <div>
                                    <h3>Thẻ Meta Mặc Định &amp; Từ Khóa Tìm Kiếm</h3>
                                    <p>Sử dụng làm tiêu đề và mô tả chuẩn cho Google khi trang con không có cấu hình riêng.</p>
                                </div>
                            </div>

                            <div class="formgrid">
                                <div class="field full">
                                    <div class="seo-label-row">
                                        <label for="seoMetaTitle">TIÊU ĐỀ META MẶC ĐỊNH (META TITLE) *</label>
                                        <span class="seo-char-badge" :class="titleLengthClass(form.seo_meta_title.length)">
                                            {{ form.seo_meta_title.length }} / 65 ký tự khuyến nghị
                                        </span>
                                    </div>
                                    <input
                                        id="seoMetaTitle"
                                        v-model="form.seo_meta_title"
                                        type="text"
                                        maxlength="160"
                                        required
                                        placeholder="HVAC Mart - Bình Nước Nóng Ariston, Bảo Ôn K-Flex & Vật Tư HVAC"
                                    >
                                    <div class="seo-progress-track">
                                        <div
                                            class="seo-progress-bar"
                                            :class="titleLengthClass(form.seo_meta_title.length)"
                                            :style="{ width: `${Math.min(100, Math.round((form.seo_meta_title.length / 65) * 100))}%` }"
                                        ></div>
                                    </div>
                                    <small v-if="form.errors.seo_meta_title" class="field-error">{{ form.errors.seo_meta_title }}</small>
                                </div>

                                <div class="field full">
                                    <div class="seo-label-row">
                                        <label for="seoMetaDesc">MÔ TẢ META MẶC ĐỊNH (META DESCRIPTION) *</label>
                                        <span class="seo-char-badge" :class="descLengthClass(form.seo_meta_description.length)">
                                            {{ form.seo_meta_description.length }} / 160 ký tự khuyến nghị
                                        </span>
                                    </div>
                                    <textarea
                                        id="seoMetaDesc"
                                        v-model="form.seo_meta_description"
                                        rows="3"
                                        maxlength="320"
                                        required
                                        placeholder="Mô tả tóm tắt năng lực cung ứng vật tư HVAC, bình nước nóng Ariston và bảo ôn K-Flex..."
                                    ></textarea>
                                    <div class="seo-progress-track">
                                        <div
                                            class="seo-progress-bar"
                                            :class="descLengthClass(form.seo_meta_description.length)"
                                            :style="{ width: `${Math.min(100, Math.round((form.seo_meta_description.length / 160) * 100))}%` }"
                                        ></div>
                                    </div>
                                    <small v-if="form.errors.seo_meta_description" class="field-error">{{ form.errors.seo_meta_description }}</small>
                                </div>

                                <div class="field full">
                                    <label for="seoMetaKeywords">BỘ TỪ KHÓA TRỌNG TÂM (META KEYWORDS - CÁCH NHAU BỞI DẤU PHẨY)</label>
                                    <textarea
                                        id="seoMetaKeywords"
                                        v-model="form.seo_meta_keywords"
                                        rows="2"
                                        maxlength="500"
                                        placeholder="HVAC Mart, bình nước nóng Ariston, bảo ôn K-Flex, vật tư HVAC..."
                                    ></textarea>
                                    <div v-if="keywordChips.length" class="seo-keyword-chips">
                                        <span v-for="kw in keywordChips" :key="kw" class="seo-keyword-chip">
                                            # {{ kw }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="site-settings-card">
                            <div class="site-settings-card-head">
                                <span class="site-settings-card-icon">
                                    <FontAwesomeIcon icon="fa-solid fa-robot" />
                                </span>
                                <div>
                                    <h3>Cấu hình Googlebot, Schema.org &amp; OpenGraph</h3>
                                    <p>Điều khiển cách bot tìm kiếm thu thập dữ liệu, định danh doanh nghiệp địa phương và hiển thị ảnh khi chia sẻ.</p>
                                </div>
                            </div>

                            <div class="formgrid">
                                <div class="field">
                                    <label for="seoRobots">CHỈ THỊ THU THẬP DỮ LIỆU (ROBOTS META) *</label>
                                    <select id="seoRobots" v-model="form.seo_robots">
                                        <option value="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
                                            Chuẩn Google (index, follow, max-image-preview:large)
                                        </option>
                                        <option value="index, follow">
                                            Cơ bản (index, follow)
                                        </option>
                                        <option value="noindex, follow">
                                            Tạm ẩn trang khỏi kết quả tìm kiếm (noindex, follow)
                                        </option>
                                        <option value="noindex, nofollow">
                                            Chặn hoàn toàn (noindex, nofollow)
                                        </option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="seoSchemaType">LOẠI HÌNH DOANH NGHIỆP (SCHEMA.ORG TYPE) *</label>
                                    <select id="seoSchemaType" v-model="form.seo_schema_type">
                                        <option value="HardwareStore">HardwareStore (Cửa hàng Thiết bị &amp; Vật tư kỹ thuật)</option>
                                        <option value="LocalBusiness">LocalBusiness (Doanh nghiệp địa phương)</option>
                                        <option value="Store">Store (Nhà phân phối thương mại)</option>
                                        <option value="Organization">Organization (Tổ chức / Công ty)</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="seoCanonicalDomain">TÊN MIỀN CHÍNH THỨC (CANONICAL DOMAIN)</label>
                                    <input
                                        id="seoCanonicalDomain"
                                        v-model="form.seo_canonical_domain"
                                        type="text"
                                        maxlength="255"
                                        :placeholder="props.seoAudit.baseUrl"
                                    >
                                    <small class="seo-field-hint">Để trống sẽ tự động dùng tên miền hiện tại ({{ props.seoAudit.baseUrl }}).</small>
                                </div>

                                <div class="field">
                                    <label for="seoOgImage">ĐƯỜNG DẪN ẢNH CHIA SẺ MẶC ĐỊNH (OG:IMAGE)</label>
                                    <input
                                        id="seoOgImage"
                                        v-model="form.seo_og_image"
                                        type="text"
                                        maxlength="255"
                                        placeholder="/favicon.svg"
                                    >
                                    <small class="seo-field-hint">Hiển thị khi chia sẻ link qua Zalo, Facebook, LinkedIn.</small>
                                </div>

                                <div class="field">
                                    <label for="seoGeoRegion">MÃ VÙNG ĐỊA LÝ (GEO.REGION) *</label>
                                    <input
                                        id="seoGeoRegion"
                                        v-model="form.seo_geo_region"
                                        type="text"
                                        maxlength="40"
                                        required
                                        placeholder="VN-HN"
                                    >
                                </div>

                                <div class="field">
                                    <label for="seoGeoPlacename">ĐỊA DANH LOCAL SEO (GEO.PLACENAME) *</label>
                                    <input
                                        id="seoGeoPlacename"
                                        v-model="form.seo_geo_placename"
                                        type="text"
                                        maxlength="120"
                                        required
                                        placeholder="Hà Nội, Việt Nam"
                                    >
                                </div>
                            </div>
                        </section>

                        <section class="site-settings-card">
                            <div class="site-settings-card-head">
                                <span class="site-settings-card-icon">
                                    <FontAwesomeIcon icon="fa-solid fa-chart-simple" />
                                </span>
                                <div>
                                    <h3>Tích hợp Google Search Console &amp; Google Analytics 4</h3>
                                    <p>Tự động gắn thẻ xác minh quyền sở hữu tên miền và mã theo dõi lưu lượng truy cập vào phần <code>&lt;head&gt;</code>.</p>
                                </div>
                            </div>

                            <div class="formgrid">
                                <div class="field">
                                    <label for="seoGoogleVerification">MÃ XÁC MINH GOOGLE SEARCH CONSOLE</label>
                                    <input
                                        id="seoGoogleVerification"
                                        v-model="form.seo_google_verification"
                                        type="text"
                                        maxlength="255"
                                        placeholder="Nhập chuỗi content của google-site-verification..."
                                    >
                                    <small class="seo-field-hint">Sinh thẻ <code>&lt;meta name="google-site-verification" content="..."&gt;</code>.</small>
                                </div>

                                <div class="field">
                                    <label for="seoGoogleAnalytics">MÃ ĐO LƯỜNG GOOGLE ANALYTICS 4 (MEASUREMENT ID)</label>
                                    <input
                                        id="seoGoogleAnalytics"
                                        v-model="form.seo_google_analytics_id"
                                        type="text"
                                        maxlength="80"
                                        placeholder="G-XXXXXXXXXX"
                                    >
                                    <small class="seo-field-hint">Tự động nhúng <code>gtag.js</code> khi nhập mã bắt đầu bằng <code>G-</code>.</small>
                                </div>

                                <div class="field full">
                                    <div class="seo-label-row">
                                        <label for="seoGoogleServiceAccountJson">
                                            GOOGLE SEARCH CONSOLE API — SERVICE ACCOUNT JSON (CẬP NHẬT SITEMAP TỨC THÌ)
                                        </label>
                                        <div class="row-actions" style="gap: 8px">
                                            <span
                                                class="seo-char-badge"
                                                :class="detectedServiceAccountEmail ? 'is-good' : 'is-warn'"
                                            >
                                                {{
                                                    detectedServiceAccountEmail
                                                        ? `Đã cấu hình: ${detectedServiceAccountEmail}`
                                                        : 'Chưa gắn Service Account JSON'
                                                }}
                                            </span>
                                            <label class="btn" style="cursor: pointer; margin: 0">
                                                <FontAwesomeIcon icon="fa-solid fa-file-arrow-up" /> Tải file .json
                                                <input
                                                    type="file"
                                                    accept=".json,application/json"
                                                    style="display: none"
                                                    @change="handleServiceAccountFileUpload"
                                                >
                                            </label>
                                        </div>
                                    </div>
                                    <textarea
                                        id="seoGoogleServiceAccountJson"
                                        v-model="form.seo_google_service_account_json"
                                        rows="5"
                                        placeholder='Dán nội dung file JSON của Google Cloud Service Account vào đây ({"type": "service_account", "client_email": "...", "private_key": "..."})'
                                    ></textarea>
                                    <small v-if="form.errors.seo_google_service_account_json" class="field-error">
                                        {{ form.errors.seo_google_service_account_json }}
                                    </small>
                                    <div v-if="detectedServiceAccountEmail" class="seo-gsc-guide-box">
                                        <div>
                                            <strong>Email Service Account cần cấp quyền trên Google Search Console:</strong>
                                            <code>{{ detectedServiceAccountEmail }}</code>
                                        </div>
                                        <button
                                            type="button"
                                            class="btn"
                                            @click="copyToClipboard(detectedServiceAccountEmail)"
                                        >
                                            <FontAwesomeIcon icon="fa-solid fa-copy" />
                                            {{ copiedUrl === detectedServiceAccountEmail ? 'Đã chép Email' : 'Sao chép Email' }}
                                        </button>
                                    </div>
                                    <small class="seo-field-hint">
                                        Khi có Service Account JSON và đã thêm email trên vào <strong>Google Search Console → Cài đặt → Người dùng và quyền (Quyền: Đầy đủ / Chủ sở hữu)</strong>, mỗi lần bấm <strong>Đẩy tín hiệu lên Google ngay</strong> hệ thống sẽ gọi thẳng API <code>sitemaps.submit</code> để ép Google đọc lại <code>sitemap.xml</code> và <code>feed.xml</code> ngay lập tức.
                                    </small>
                                </div>
                            </div>
                        </section>
                    </template>

                    <template v-else-if="activeTab === 'pages'">
                        <section
                            v-for="pageItem in pageConfigs"
                            :key="pageItem.key"
                            class="site-settings-card seo-page-card"
                            :class="{ 'is-previewing': !activeEntityPreview && previewPageKey === pageItem.key }"
                        >
                            <div class="site-settings-card-head">
                                <span class="site-settings-card-icon">
                                    <FontAwesomeIcon :icon="pageItem.icon" />
                                </span>
                                <div class="seo-page-card-title-wrap">
                                    <div>
                                        <h3>{{ pageItem.label }}</h3>
                                        <p>Đường dẫn: <code>{{ pageItem.path }}</code></p>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn seo-preview-trigger"
                                        @click="selectStaticPagePreview(pageItem.key)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-eye" /> Xem thử trên Google
                                    </button>
                                </div>
                            </div>

                            <div class="formgrid">
                                <div class="field full">
                                    <div class="seo-label-row">
                                        <label :for="`title-${pageItem.key}`">TIÊU ĐỀ SEO ({{ pageItem.label.toUpperCase() }}) *</label>
                                        <span class="seo-char-badge" :class="titleLengthClass(form[pageItem.titleField].length)">
                                            {{ form[pageItem.titleField].length }} / 65 ký tự
                                        </span>
                                    </div>
                                    <input
                                        :id="`title-${pageItem.key}`"
                                        v-model="form[pageItem.titleField]"
                                        type="text"
                                        maxlength="160"
                                        required
                                        @focus="selectStaticPagePreview(pageItem.key)"
                                    >
                                </div>

                                <div class="field full">
                                    <div class="seo-label-row">
                                        <label :for="`desc-${pageItem.key}`">MÔ TẢ META DESCRIPTION *</label>
                                        <span class="seo-char-badge" :class="descLengthClass(form[pageItem.descField].length)">
                                            {{ form[pageItem.descField].length }} / 160 ký tự
                                        </span>
                                    </div>
                                    <textarea
                                        :id="`desc-${pageItem.key}`"
                                        v-model="form[pageItem.descField]"
                                        rows="2"
                                        maxlength="320"
                                        required
                                        @focus="selectStaticPagePreview(pageItem.key)"
                                    ></textarea>
                                </div>
                            </div>
                        </section>
                    </template>

                    <template v-else>
                        <section class="site-settings-card">
                            <div class="site-settings-card-head">
                                <span class="site-settings-card-icon">
                                    <FontAwesomeIcon icon="fa-solid fa-sitemap" />
                                </span>
                                <div>
                                    <h3>Sơ đồ trang web (Sitemap XML) &amp; Robots.txt</h3>
                                    <p>Cung cấp đường dẫn tự động cập nhật để khai báo trong Google Search Console.</p>
                                </div>
                            </div>

                            <div class="seo-url-tools-grid">
                                <div class="seo-url-box">
                                    <span>ĐƯỜNG DẪN SITEMAP XML ĐỘNG</span>
                                    <div class="seo-url-input-row">
                                        <input type="text" readonly :value="`${displayBaseUrl}/sitemap.xml`">
                                        <button type="button" class="btn" @click="copyToClipboard(`${displayBaseUrl}/sitemap.xml`)">
                                            <FontAwesomeIcon icon="fa-solid fa-copy" /> {{ copiedUrl === `${displayBaseUrl}/sitemap.xml` ? 'Đã chép' : 'Sao chép' }}
                                        </button>
                                        <a class="btn primary" href="/sitemap.xml" target="_blank" rel="noopener">
                                            Mở <FontAwesomeIcon icon="fa-solid fa-arrow-up-right-from-square" />
                                        </a>
                                    </div>
                                </div>

                                <div class="seo-url-box">
                                    <span>ĐƯỜNG DẪN FILE ROBOTS.TXT</span>
                                    <div class="seo-url-input-row">
                                        <input type="text" readonly :value="`${displayBaseUrl}/robots.txt`">
                                        <button type="button" class="btn" @click="copyToClipboard(`${displayBaseUrl}/robots.txt`)">
                                            <FontAwesomeIcon icon="fa-solid fa-copy" /> {{ copiedUrl === `${displayBaseUrl}/robots.txt` ? 'Đã chép' : 'Sao chép' }}
                                        </button>
                                        <a class="btn" href="/robots.txt" target="_blank" rel="noopener">
                                            Mở <FontAwesomeIcon icon="fa-solid fa-arrow-up-right-from-square" />
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="seo-audit-table-wrap">
                                <table class="seo-audit-table">
                                    <thead>
                                        <tr>
                                            <th>Nhóm nội dung lập chỉ mục</th>
                                            <th>Số URL trong Sitemap</th>
                                            <th>Độ hoàn thiện nội dung SEO</th>
                                            <th>Cấu trúc URL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Trang đích cố định (Landing Pages)</strong></td>
                                            <td>{{ props.seoAudit.staticPagesCount }} URL</td>
                                            <td><span class="seo-pill is-good">100% có Title &amp; Description riêng</span></td>
                                            <td><code>/, /categories, /products, /news...</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Danh mục &amp; Dòng sản phẩm</strong></td>
                                            <td>{{ props.seoAudit.categoriesCount }} URL</td>
                                            <td>
                                                <span class="seo-pill" :class="props.seoAudit.categoriesWithSeoCount === props.seoAudit.categoriesCount ? 'is-good' : 'is-warn'">
                                                    {{ props.seoAudit.categoriesWithSeoCount }}/{{ props.seoAudit.categoriesCount }} có SEO Title &amp; Description
                                                </span>
                                            </td>
                                            <td><code>/categories/{danh-muc}/{dong-sp}</code> (Schema CollectionPage)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Sản phẩm &amp; Thiết bị</strong></td>
                                            <td>{{ props.seoAudit.productsCount }} URL</td>
                                            <td>
                                                <span class="seo-pill" :class="props.seoAudit.productsWithSeoCount === props.seoAudit.productsCount ? 'is-good' : 'is-warn'">
                                                    {{ props.seoAudit.productsWithSeoCount }}/{{ props.seoAudit.productsCount }} có SEO chuẩn · {{ props.seoAudit.productsWithImageCount }}/{{ props.seoAudit.productsCount }} có ảnh
                                                </span>
                                            </td>
                                            <td><code>/products/{ten-san-pham}</code> (Slug + Schema Product)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Dự án tiêu biểu (Case Studies)</strong></td>
                                            <td>{{ props.seoAudit.projectsCount }} URL</td>
                                            <td>
                                                <span class="seo-pill" :class="props.seoAudit.projectsWithSeoCount === props.seoAudit.projectsCount ? 'is-good' : 'is-warn'">
                                                    {{ props.seoAudit.projectsWithSeoCount }}/{{ props.seoAudit.projectsCount }} có SEO chuẩn · {{ props.seoAudit.projectsWithDescCount }}/{{ props.seoAudit.projectsCount }} có mô tả chi tiết
                                                </span>
                                            </td>
                                            <td><code>/featured-projects/{ten-du-an}</code> (URL chuẩn Slug + Schema CaseStudy)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Bài viết &amp; Tin tức kỹ thuật</strong></td>
                                            <td>{{ props.seoAudit.articlesCount }} URL</td>
                                            <td>
                                                <span class="seo-pill" :class="props.seoAudit.articlesWithSeoCount >= props.seoAudit.articlesCount ? 'is-good' : 'is-warn'">
                                                    {{ props.seoAudit.articlesWithSeoCount }}/{{ props.seoAudit.articlesTotalCount }} có SEO chuẩn
                                                </span>
                                            </td>
                                            <td><code>/news/{slug}</code> (kèm Schema NewsArticle)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </template>
                </form>
            </div>

            <aside class="seo-side-column">
                <section class="site-settings-card seo-serp-card">
                    <div class="site-settings-card-head">
                        <span class="site-settings-card-icon">
                            <FontAwesomeIcon icon="fa-brands fa-google" />
                        </span>
                        <div>
                            <h3>Mô phỏng kết quả Google (SERP Preview)</h3>
                            <p>Xem trước cách trang web xuất hiện trên trang tìm kiếm của Google.</p>
                        </div>
                    </div>

                    <div class="seo-serp-controls">
                        <select v-model="previewPageKey" aria-label="Chọn trang để xem trước" @change="activeEntityPreview = null">
                            <option value="default">Mặc định toàn hệ thống</option>
                            <option v-for="item in pageConfigs" :key="item.key" :value="item.key">
                                {{ item.label }} ({{ item.path }})
                            </option>
                        </select>
                        <div class="seo-device-toggle">
                            <button
                                type="button"
                                :class="{ active: serpDevice === 'desktop' }"
                                @click="serpDevice = 'desktop'"
                            >
                                Desktop
                            </button>
                            <button
                                type="button"
                                :class="{ active: serpDevice === 'mobile' }"
                                @click="serpDevice = 'mobile'"
                            >
                                Mobile
                            </button>
                        </div>
                    </div>

                    <div class="seo-serp-box" :class="{ 'is-mobile': serpDevice === 'mobile' }">
                        <div class="seo-serp-site-row">
                            <span class="seo-serp-favicon">HM</span>
                            <div>
                                <strong>{{ props.settings.company_name || 'HVAC Mart' }}</strong>
                                <cite>{{ activePreviewUrl }}</cite>
                            </div>
                        </div>
                        <h4 class="seo-serp-title">{{ activePreviewTitle }}</h4>
                        <p class="seo-serp-desc">{{ activePreviewDescription }}</p>
                        <div class="seo-serp-sitelinks">
                            <span>Bình nước nóng Ariston</span>
                            <span>·</span>
                            <span>Bảo ôn K-Flex</span>
                            <span>·</span>
                            <span>Dự án tiêu biểu</span>
                            <span>·</span>
                            <span>Catalogue PDF</span>
                        </div>
                    </div>

                    <div class="seo-social-preview">
                        <span class="seo-social-label">XEM TRƯỚC KHI CHIA SẺ ZALO / FACEBOOK (OPENGRAPH)</span>
                        <div class="seo-social-card">
                            <div class="seo-social-thumb">
                                <span>HVAC MART · MEP SUPPLY</span>
                                <small>{{ form.seo_og_image || '/favicon.svg' }}</small>
                            </div>
                            <div class="seo-social-body">
                                <small>{{ displayHost }}</small>
                                <strong>{{ activePreviewTitle }}</strong>
                                <p>{{ activePreviewDescription }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="site-settings-card">
                    <div class="site-settings-card-head">
                        <span class="site-settings-card-icon">
                            <FontAwesomeIcon icon="fa-solid fa-list-check" />
                        </span>
                        <div>
                            <h3>Danh mục kiểm tra chuẩn Google</h3>
                            <p>Các tiêu chí kỹ thuật giúp tăng thứ hạng từ khóa tự nhiên.</p>
                        </div>
                    </div>

                    <ul class="seo-checklist">
                        <li
                            v-for="item in seoChecklist"
                            :key="item.label"
                            :class="item.passed ? 'is-pass' : 'is-warn'"
                        >
                            <span class="seo-check-icon">
                                <FontAwesomeIcon :icon="item.passed ? 'fa-solid fa-check' : 'fa-solid fa-triangle-exclamation'" />
                            </span>
                            <div>
                                <strong>{{ item.label }}</strong>
                                <span>{{ item.detail }}</span>
                            </div>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../layout/AdminLayout.Vue';

type SeoSettingsMap = {
    company_name?: string;
    seo_last_ping_at?: string;
    seo_last_ping_url?: string;
    seo_last_ping_status?: string;
    seo_meta_title: string;
    seo_meta_description: string;
    seo_meta_keywords: string;
    seo_og_image: string;
    seo_robots: string;
    seo_canonical_domain: string;
    seo_google_verification: string;
    seo_google_analytics_id: string;
    seo_google_service_account_json?: string;
    seo_schema_type: string;
    seo_geo_region: string;
    seo_geo_placename: string;
    seo_home_title: string;
    seo_home_description: string;
    seo_categories_title: string;
    seo_categories_description: string;
    seo_products_title: string;
    seo_products_description: string;
    seo_projects_title: string;
    seo_projects_description: string;
    seo_news_title: string;
    seo_news_description: string;
    seo_catalogues_title: string;
    seo_catalogues_description: string;
    seo_contacts_title: string;
    seo_contacts_description: string;
};

type SeoProductEntity = {
    id: number;
    name: string;
    slug: string;
    subtitle: string | null;
    category_name?: string | null;
    subcategory_name?: string | null;
    seo_title: string;
    seo_description: string;
    seo_keywords: string;
    url: string;
    path: string;
};

type SeoCategoryEntity = {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
    parent_name?: string | null;
    description?: string | null;
    seo_title: string;
    seo_description: string;
    seo_keywords: string;
    url: string;
    path: string;
};

type SeoArticleEntity = {
    id: number;
    title: string;
    slug: string;
    excerpt?: string | null;
    is_published: boolean;
    seo_title: string;
    seo_description: string;
    seo_keywords: string;
    url: string;
    path: string;
};

type SeoProjectEntity = {
    id: number;
    name: string;
    slug: string;
    location?: string | null;
    category?: string | null;
    subcategory?: string | null;
    seo_title: string;
    seo_description: string;
    seo_keywords: string;
    url: string;
    path: string;
};

type UnifiedSeoEntity = {
    type: 'product' | 'category' | 'project' | 'article';
    id: number;
    displayName: string;
    badgeLabel: string;
    path: string;
    url: string;
    seo_title: string;
    seo_description: string;
    seo_keywords: string;
};

type SeoAuditPayload = {
    staticPagesCount: number;
    categoriesCount: number;
    categoriesWithSeoCount: number;
    productsCount: number;
    productsWithDescCount: number;
    productsWithImageCount: number;
    productsWithSeoCount: number;
    projectsCount: number;
    projectsWithDescCount: number;
    projectsWithSeoCount: number;
    articlesCount: number;
    articlesTotalCount: number;
    articlesWithExcerptCount: number;
    articlesWithSeoCount: number;
    cataloguesCount: number;
    totalIndexedUrls: number;
    sitemapUrl: string;
    feedUrl?: string;
    robotsUrl: string;
    baseUrl: string;
    googleServiceAccount?: {
        configured: boolean;
        client_email: string | null;
        project_id: string | null;
    };
};

type AdminStat = {
    label: string;
    count: number;
    description: string;
};

const props = defineProps<{
    settings: SeoSettingsMap;
    defaults: SeoSettingsMap;
    seoEntities: {
        products: SeoProductEntity[];
        categories: SeoCategoryEntity[];
        projects: SeoProjectEntity[];
        articles: SeoArticleEntity[];
    };
    seoAudit: SeoAuditPayload;
    stats: AdminStat[];
    openRequestsCount: number;
    chatUnreadCount?: number;
    supportProjectsCount?: number;
}>();

const activeTab = ref<'entities' | 'general' | 'pages' | 'audit'>('entities');
const entitySubTab = ref<'product' | 'category' | 'project' | 'article'>('product');
const entitySearch = ref('');
const previewPageKey = ref<'default' | 'home' | 'categories' | 'products' | 'projects' | 'news' | 'catalogues' | 'contacts'>('home');
const activeEntityPreview = ref<UnifiedSeoEntity | null>(null);
const serpDevice = ref<'desktop' | 'mobile'>('desktop');
const copiedUrl = ref<string | null>(null);
const isPinging = ref(false);
const isGeneratingAll = ref(false);
const savingEntityKey = ref<string | null>(null);

const entityDrafts = reactive<Record<string, { seo_title: string; seo_description: string; seo_keywords: string }>>({});

const syncDraftsFromProps = (): void => {
    for (const prod of props.seoEntities.products) {
        entityDrafts[`product-${prod.id}`] = {
            seo_title: prod.seo_title || '',
            seo_description: prod.seo_description || '',
            seo_keywords: prod.seo_keywords || '',
        };
    }
    for (const cat of props.seoEntities.categories) {
        entityDrafts[`category-${cat.id}`] = {
            seo_title: cat.seo_title || '',
            seo_description: cat.seo_description || '',
            seo_keywords: cat.seo_keywords || '',
        };
    }
    for (const proj of props.seoEntities.projects ?? []) {
        entityDrafts[`project-${proj.id}`] = {
            seo_title: proj.seo_title || '',
            seo_description: proj.seo_description || '',
            seo_keywords: proj.seo_keywords || '',
        };
    }
    for (const art of props.seoEntities.articles) {
        entityDrafts[`article-${art.id}`] = {
            seo_title: art.seo_title || '',
            seo_description: art.seo_description || '',
            seo_keywords: art.seo_keywords || '',
        };
    }
};

syncDraftsFromProps();

watch(
    () => props.seoEntities,
    () => {
        syncDraftsFromProps();
    },
    { deep: true },
);

function getEntityDraft(item: UnifiedSeoEntity): { seo_title: string; seo_description: string; seo_keywords: string } {
    const key = `${item.type}-${item.id}`;
    if (!entityDrafts[key]) {
        entityDrafts[key] = {
            seo_title: item.seo_title || '',
            seo_description: item.seo_description || '',
            seo_keywords: item.seo_keywords || '',
        };
    }

    return entityDrafts[key];
}

const totalEntitiesCount = computed<number>(
    () => props.seoEntities.products.length
        + props.seoEntities.categories.length
        + (props.seoEntities.projects?.length ?? 0)
        + props.seoEntities.articles.length,
);

const currentSubTabEntities = computed<UnifiedSeoEntity[]>(() => {
    if (entitySubTab.value === 'product') {
        return props.seoEntities.products.map((prod) => ({
            type: 'product' as const,
            id: prod.id,
            displayName: prod.name,
            badgeLabel: [prod.category_name, prod.subcategory_name].filter(Boolean).join(' · ') || 'Sản phẩm',
            path: prod.path,
            url: prod.url,
            seo_title: prod.seo_title,
            seo_description: prod.seo_description,
            seo_keywords: prod.seo_keywords,
        }));
    }

    if (entitySubTab.value === 'category') {
        return props.seoEntities.categories.map((cat) => ({
            type: 'category' as const,
            id: cat.id,
            displayName: cat.name,
            badgeLabel: cat.parent_name ? `Dòng sản phẩm (${cat.parent_name})` : 'Danh mục chính',
            path: cat.path,
            url: cat.url,
            seo_title: cat.seo_title,
            seo_description: cat.seo_description,
            seo_keywords: cat.seo_keywords,
        }));
    }

    if (entitySubTab.value === 'project') {
        return (props.seoEntities.projects ?? []).map((proj) => ({
            type: 'project' as const,
            id: proj.id,
            displayName: proj.name,
            badgeLabel: [proj.category, proj.location].filter(Boolean).join(' · ') || 'Dự án tiêu biểu',
            path: proj.path,
            url: proj.url,
            seo_title: proj.seo_title,
            seo_description: proj.seo_description,
            seo_keywords: proj.seo_keywords,
        }));
    }

    return props.seoEntities.articles.map((art) => ({
        type: 'article' as const,
        id: art.id,
        displayName: art.title,
        badgeLabel: art.is_published ? 'Bài viết công khai' : 'Bản nháp',
        path: art.path,
        url: art.url,
        seo_title: art.seo_title,
        seo_description: art.seo_description,
        seo_keywords: art.seo_keywords,
    }));
});

const filteredEntities = computed<UnifiedSeoEntity[]>(() => {
    const q = entitySearch.value.trim().toLowerCase();
    if (!q) {
        return currentSubTabEntities.value;
    }

    return currentSubTabEntities.value.filter((item) => {
        const draft = getEntityDraft(item);

        return [
            item.displayName,
            item.badgeLabel,
            item.path,
            draft.seo_title,
            draft.seo_description,
            draft.seo_keywords,
        ]
            .join(' ')
            .toLowerCase()
            .includes(q);
    });
});

function selectEntityForPreview(item: UnifiedSeoEntity): void {
    activeEntityPreview.value = item;
}

function selectStaticPagePreview(key: typeof previewPageKey.value): void {
    activeEntityPreview.value = null;
    previewPageKey.value = key;
}

function triggerGenerateAllSeo(): void {
    isGeneratingAll.value = true;
    router.post('/admin/seo/generate-all', {}, {
        preserveScroll: true,
        onFinish: () => {
            isGeneratingAll.value = false;
        },
    });
}

function saveSingleEntitySeo(item: UnifiedSeoEntity): void {
    const key = `${item.type}-${item.id}`;
    const draft = getEntityDraft(item);
    savingEntityKey.value = key;

    router.put(`/admin/seo/entities/${item.type}/${item.id}`, {
        seo_title: draft.seo_title,
        seo_description: draft.seo_description,
        seo_keywords: draft.seo_keywords,
        auto_generate: false,
    }, {
        preserveScroll: true,
        onFinish: () => {
            savingEntityKey.value = null;
        },
    });
}

function regenerateSingleEntitySeo(item: UnifiedSeoEntity): void {
    const key = `${item.type}-${item.id}`;
    savingEntityKey.value = key;

    router.put(`/admin/seo/entities/${item.type}/${item.id}`, {
        auto_generate: true,
    }, {
        preserveScroll: true,
        onFinish: () => {
            savingEntityKey.value = null;
        },
    });
}

function triggerManualPing(): void {
    isPinging.value = true;
    router.post('/admin/seo/ping', {}, {
        preserveScroll: true,
        onFinish: () => {
            isPinging.value = false;
        },
    });
}

const form = useForm<SeoSettingsMap>({
    seo_meta_title: props.settings.seo_meta_title ?? props.defaults.seo_meta_title,
    seo_meta_description: props.settings.seo_meta_description ?? props.defaults.seo_meta_description,
    seo_meta_keywords: props.settings.seo_meta_keywords ?? props.defaults.seo_meta_keywords,
    seo_og_image: props.settings.seo_og_image ?? props.defaults.seo_og_image,
    seo_robots: props.settings.seo_robots ?? props.defaults.seo_robots,
    seo_canonical_domain: props.settings.seo_canonical_domain || props.defaults.seo_canonical_domain || 'https://hvacmart.io.vn',
    seo_google_verification: props.settings.seo_google_verification ?? '',
    seo_google_analytics_id: props.settings.seo_google_analytics_id ?? '',
    seo_google_service_account_json: props.settings.seo_google_service_account_json ?? '',
    seo_schema_type: props.settings.seo_schema_type ?? props.defaults.seo_schema_type,
    seo_geo_region: props.settings.seo_geo_region ?? props.defaults.seo_geo_region,
    seo_geo_placename: props.settings.seo_geo_placename ?? props.defaults.seo_geo_placename,
    seo_home_title: props.settings.seo_home_title ?? props.defaults.seo_home_title,
    seo_home_description: props.settings.seo_home_description ?? props.defaults.seo_home_description,
    seo_categories_title: props.settings.seo_categories_title ?? props.defaults.seo_categories_title,
    seo_categories_description: props.settings.seo_categories_description ?? props.defaults.seo_categories_description,
    seo_products_title: props.settings.seo_products_title ?? props.defaults.seo_products_title,
    seo_products_description: props.settings.seo_products_description ?? props.defaults.seo_products_description,
    seo_projects_title: props.settings.seo_projects_title ?? props.defaults.seo_projects_title,
    seo_projects_description: props.settings.seo_projects_description ?? props.defaults.seo_projects_description,
    seo_news_title: props.settings.seo_news_title ?? props.defaults.seo_news_title,
    seo_news_description: props.settings.seo_news_description ?? props.defaults.seo_news_description,
    seo_catalogues_title: props.settings.seo_catalogues_title ?? props.defaults.seo_catalogues_title,
    seo_catalogues_description: props.settings.seo_catalogues_description ?? props.defaults.seo_catalogues_description,
    seo_contacts_title: props.settings.seo_contacts_title ?? props.defaults.seo_contacts_title,
    seo_contacts_description: props.settings.seo_contacts_description ?? props.defaults.seo_contacts_description,
});

type PageConfigItem = {
    key: 'home' | 'categories' | 'products' | 'projects' | 'news' | 'catalogues' | 'contacts';
    label: string;
    path: string;
    icon: string;
    titleField: keyof SeoSettingsMap;
    descField: keyof SeoSettingsMap;
};

const pageConfigs: PageConfigItem[] = [
    {
        key: 'home',
        label: 'Trang chủ',
        path: '/',
        icon: 'fa-solid fa-house',
        titleField: 'seo_home_title',
        descField: 'seo_home_description',
    },
    {
        key: 'categories',
        label: 'Trang Danh mục chính & Dòng sản phẩm',
        path: '/categories',
        icon: 'fa-solid fa-sitemap',
        titleField: 'seo_categories_title',
        descField: 'seo_categories_description',
    },
    {
        key: 'products',
        label: 'Danh sách Sản phẩm & Thiết bị',
        path: '/products',
        icon: 'fa-solid fa-box',
        titleField: 'seo_products_title',
        descField: 'seo_products_description',
    },
    {
        key: 'projects',
        label: 'Dự án tiêu biểu (Case Studies)',
        path: '/featured-projects',
        icon: 'fa-solid fa-building',
        titleField: 'seo_projects_title',
        descField: 'seo_projects_description',
    },
    {
        key: 'news',
        label: 'Tin tức & Bài viết kỹ thuật',
        path: '/news',
        icon: 'fa-solid fa-newspaper',
        titleField: 'seo_news_title',
        descField: 'seo_news_description',
    },
    {
        key: 'catalogues',
        label: 'Thư viện Catalogue PDF',
        path: '/catalogues',
        icon: 'fa-solid fa-file-pdf',
        titleField: 'seo_catalogues_title',
        descField: 'seo_catalogues_description',
    },
    {
        key: 'contacts',
        label: 'Trang Liên hệ & Báo giá',
        path: '/contacts',
        icon: 'fa-solid fa-headset',
        titleField: 'seo_contacts_title',
        descField: 'seo_contacts_description',
    },
];

const keywordChips = computed<string[]>(() =>
    (form.seo_meta_keywords || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean),
);

const isNoIndex = computed<boolean>(() =>
    form.seo_robots.toLowerCase().includes('noindex'),
);

const displayBaseUrl = computed<string>(() => {
    const custom = form.seo_canonical_domain.trim().replace(/\/+$/, '');

    return custom || props.seoAudit.baseUrl.replace(/\/+$/, '');
});

const displayHost = computed<string>(() => {
    try {
        return new URL(displayBaseUrl.value).host.toUpperCase();
    } catch {
        return displayBaseUrl.value.toUpperCase();
    }
});

const activePreviewUrl = computed<string>(() => {
    if (activeEntityPreview.value) {
        return `${displayBaseUrl.value}${activeEntityPreview.value.path}`;
    }

    const matched = pageConfigs.find((item) => item.key === previewPageKey.value);
    const path = matched ? matched.path : '/';

    return path === '/' ? displayBaseUrl.value : `${displayBaseUrl.value}${path}`;
});

const activePreviewTitle = computed<string>(() => {
    if (activeEntityPreview.value) {
        const draft = getEntityDraft(activeEntityPreview.value);

        return draft.seo_title || activeEntityPreview.value.displayName || 'HVAC Mart';
    }

    const matched = pageConfigs.find((item) => item.key === previewPageKey.value);
    const raw = matched ? (form[matched.titleField] || form.seo_meta_title) : form.seo_meta_title;

    return raw || 'HVAC Mart';
});

const activePreviewDescription = computed<string>(() => {
    if (activeEntityPreview.value) {
        const draft = getEntityDraft(activeEntityPreview.value);

        return draft.seo_description || form.seo_meta_description;
    }

    const matched = pageConfigs.find((item) => item.key === previewPageKey.value);
    const raw = matched ? (form[matched.descField] || form.seo_meta_description) : form.seo_meta_description;

    return raw || '';
});

const titleLengthClass = (length: number): string => {
    if (length >= 30 && length <= 70) {
        return 'is-good';
    }

    if (length > 0 && length <= 90) {
        return 'is-warn';
    }

    return 'is-bad';
};

const descLengthClass = (length: number): string => {
    if (length >= 90 && length <= 175) {
        return 'is-good';
    }

    if (length >= 50 && length <= 220) {
        return 'is-warn';
    }

    return 'is-bad';
};

const seoChecklist = computed(() => {
    const titleLen = form.seo_meta_title.trim().length;
    const descLen = form.seo_meta_description.trim().length;

    return [
        {
            label: 'SEO cho 100% Sản phẩm, Danh mục, Dự án & Bài viết',
            detail: `${props.seoAudit.productsWithSeoCount}/${props.seoAudit.productsCount} sản phẩm · ${props.seoAudit.categoriesWithSeoCount}/${props.seoAudit.categoriesCount} danh mục · ${props.seoAudit.projectsWithSeoCount}/${props.seoAudit.projectsCount} dự án · ${props.seoAudit.articlesWithSeoCount}/${props.seoAudit.articlesTotalCount} bài viết.`,
            passed:
                props.seoAudit.productsWithSeoCount === props.seoAudit.productsCount
                && props.seoAudit.categoriesWithSeoCount === props.seoAudit.categoriesCount
                && props.seoAudit.projectsWithSeoCount === props.seoAudit.projectsCount
                && props.seoAudit.articlesWithSeoCount === props.seoAudit.articlesTotalCount,
        },
        {
            label: 'Độ dài Meta Title chuẩn Google (30–70 ký tự)',
            detail: `Hiện tại: ${titleLen} ký tự.`,
            passed: titleLen >= 30 && titleLen <= 70,
        },
        {
            label: 'Độ dài Meta Description chuẩn (90–175 ký tự)',
            detail: `Hiện tại: ${descLen} ký tự.`,
            passed: descLen >= 90 && descLen <= 175,
        },
        {
            label: 'Cho phép Googlebot lập chỉ mục (Index & Follow)',
            detail: isNoIndex.value ? 'Đang bật noindex — Google sẽ không hiển thị web!' : 'Đã bật index, follow.',
            passed: !isNoIndex.value,
        },
        {
            label: 'Bộ từ khóa mục tiêu (Meta Keywords)',
            detail: `${keywordChips.value.length} cụm từ khóa ngành HVAC / MEP.`,
            passed: keywordChips.value.length >= 4,
        },
        {
            label: 'Sơ đồ trang web Sitemap XML & Robots.txt tự động',
            detail: `Đang xuất ${props.seoAudit.totalIndexedUrls} URL kèm thẻ <lastmod> và <priority>.`,
            passed: props.seoAudit.totalIndexedUrls > 0,
        },
        {
            label: 'Dữ liệu có cấu trúc Schema.org JSON-LD',
            detail: `Đã kích hoạt ${form.seo_schema_type}, BreadcrumbList, CollectionPage, Product & NewsArticle.`,
            passed: Boolean(form.seo_schema_type),
        },
    ];
});

const passedChecksCount = computed<number>(
    () => seoChecklist.value.filter((item) => item.passed).length,
);

const seoScore = computed<number>(() =>
    Math.round((passedChecksCount.value / seoChecklist.value.length) * 100),
);

const seoScoreClass = computed<string>(() => {
    if (seoScore.value >= 85) {
        return 'is-good';
    }

    if (seoScore.value >= 65) {
        return 'is-warn';
    }

    return 'is-bad';
});

const seoScoreLabel = computed<string>(() => {
    if (seoScore.value >= 85) {
        return 'TỐI ƯU TỐT';
    }

    if (seoScore.value >= 65) {
        return 'KHÁ TỐT';
    }

    return 'CẦN CẢI THIỆN';
});

const detectedServiceAccountEmail = computed<string | null>(() => {
    const raw = (form.seo_google_service_account_json || '').trim();
    if (raw) {
        try {
            const parsed = JSON.parse(raw) as { client_email?: string };
            if (parsed && typeof parsed.client_email === 'string' && parsed.client_email.trim() !== '') {
                return parsed.client_email.trim();
            }
        } catch {
            // Ignore partial JSON while typing
        }
    }

    return props.seoAudit.googleServiceAccount?.client_email ?? null;
});

function handleServiceAccountFileUpload(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        if (typeof reader.result === 'string') {
            form.seo_google_service_account_json = reader.result.trim();
        }
    };
    reader.readAsText(file);
    input.value = '';
}

function copyToClipboard(url: string): void {
    if (navigator.clipboard) {
        void navigator.clipboard.writeText(url);
        copiedUrl.value = url;
        setTimeout(() => {
            if (copiedUrl.value === url) {
                copiedUrl.value = null;
            }
        }, 2000);
    }
}

function restoreDefaults(): void {
    (Object.keys(props.defaults) as Array<keyof SeoSettingsMap>).forEach((key) => {
        if (key in form && typeof props.defaults[key] === 'string') {
            (form as Record<string, string>)[key] = props.defaults[key] as string;
        }
    });
}

function submitSeoSettings(): void {
    form.put('/admin/seo', {
        preserveScroll: true,
    });
}
</script>

<style scoped>
.seo-entity-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.seo-entity-toolbar .search {
    min-width: 280px;
    flex: 1;
    max-width: 380px;
}

.seo-gsc-guide-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding: 10px 14px;
    border-radius: 10px;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.28);
    font-size: 13px;
}

.seo-gsc-guide-box code {
    margin-left: 6px;
    font-weight: 600;
}
</style>
