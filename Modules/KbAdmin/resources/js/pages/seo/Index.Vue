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
                <p>Quản lý thẻ Meta, OpenGraph, cấu trúc dữ liệu Schema.org JSON-LD, Sitemap XML và công cụ lập chỉ mục Google cho toàn bộ hệ thống.</p>
            </div>
            <div class="actions">
                <a class="btn" href="/sitemap.xml" target="_blank" rel="noopener">
                    <FontAwesomeIcon icon="fa-solid fa-sitemap" /> Mở Sitemap XML
                </a>
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
                    {{ props.seoAudit.staticPagesCount }} trang tĩnh · {{ props.seoAudit.productsCount }} sản phẩm · {{ props.seoAudit.projectsCount }} dự án · {{ props.seoAudit.articlesCount }} bài viết
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
                <strong class="seo-metric-value">Auto-Ping Bật</strong>
                <small v-if="props.settings.seo_last_ping_at">
                    Gần nhất: {{ props.settings.seo_last_ping_at }} ({{ props.settings.seo_last_ping_url }})
                </small>
                <small v-else>
                    Tự động bắn tín hiệu WebSub &amp; IndexNow khi thêm Sản phẩm / Dự án / Bài viết
                </small>
            </div>
        </section>

        <div class="seo-tabs-bar" role="tablist" aria-label="Chuyên mục cấu hình SEO">
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
                <FontAwesomeIcon icon="fa-solid fa-layer-group" /> Tối ưu SEO từng trang ({{ pageConfigs.length }})
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
            <form class="seo-main-column" @submit.prevent="submitSeoSettings">
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
                        </div>
                    </section>
                </template>

                <template v-else-if="activeTab === 'pages'">
                    <section
                        v-for="pageItem in pageConfigs"
                        :key="pageItem.key"
                        class="site-settings-card seo-page-card"
                        :class="{ 'is-previewing': previewPageKey === pageItem.key }"
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
                                    @click="previewPageKey = pageItem.key"
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
                                    @focus="previewPageKey = pageItem.key"
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
                                    @focus="previewPageKey = pageItem.key"
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
                                        <td><code>/, /products, /featured-projects, /news...</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sản phẩm &amp; Thiết bị</strong></td>
                                        <td>{{ props.seoAudit.productsCount }} URL</td>
                                        <td>
                                            <span class="seo-pill" :class="props.seoAudit.productsWithDescCount === props.seoAudit.productsCount ? 'is-good' : 'is-warn'">
                                                {{ props.seoAudit.productsWithDescCount }}/{{ props.seoAudit.productsCount }} có mô tả · {{ props.seoAudit.productsWithImageCount }}/{{ props.seoAudit.productsCount }} có ảnh
                                            </span>
                                        </td>
                                        <td><code>/products/{ten-san-pham}</code> (Slug + Schema Product)</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Dự án tiêu biểu (Case Studies)</strong></td>
                                        <td>{{ props.seoAudit.projectsCount }} URL</td>
                                        <td>
                                            <span class="seo-pill" :class="props.seoAudit.projectsWithDescCount === props.seoAudit.projectsCount ? 'is-good' : 'is-warn'">
                                                {{ props.seoAudit.projectsWithDescCount }}/{{ props.seoAudit.projectsCount }} có mô tả chi tiết
                                            </span>
                                        </td>
                                        <td><code>/featured-projects/{ten-du-an}</code> (URL chuẩn Slug)</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Bài viết &amp; Tin tức kỹ thuật</strong></td>
                                        <td>{{ props.seoAudit.articlesCount }} URL</td>
                                        <td>
                                            <span class="seo-pill" :class="props.seoAudit.articlesWithExcerptCount === props.seoAudit.articlesCount ? 'is-good' : 'is-warn'">
                                                {{ props.seoAudit.articlesWithExcerptCount }}/{{ props.seoAudit.articlesCount }} có tóm tắt (Excerpt)
                                            </span>
                                        </td>
                                        <td><code>/news/{slug}</code> (kèm Schema NewsArticle)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="site-settings-card">
                        <div class="site-settings-card-head">
                            <span class="site-settings-card-icon">
                                <FontAwesomeIcon icon="fa-solid fa-link" />
                            </span>
                            <div>
                                <h3>Kiểm tra URL thân thiện SEO (Friendly Slug URLs)</h3>
                                <p>Danh sách các đường dẫn thực tế đang được phục vụ cho Googlebot và người dùng.</p>
                            </div>
                        </div>

                        <div class="seo-sample-links-grid">
                            <div class="seo-sample-group">
                                <h4>Dự án tiêu biểu (Slug tiếng Việt không dấu)</h4>
                                <ul>
                                    <li v-for="proj in props.seoAudit.sampleProjects" :key="proj.id">
                                        <div>
                                            <strong>{{ proj.name }}</strong>
                                            <code>{{ displayBaseUrl }}/featured-projects/{{ proj.slug }}</code>
                                        </div>
                                        <a :href="proj.path || `/featured-projects/${proj.slug}`" target="_blank" rel="noopener" class="btn">Xem</a>
                                    </li>
                                </ul>
                            </div>

                            <div class="seo-sample-group">
                                <h4>Bài viết kỹ thuật &amp; Sản phẩm mới nhất</h4>
                                <ul>
                                    <li v-for="art in props.seoAudit.sampleArticles" :key="`art-${art.id}`">
                                        <div>
                                            <strong>{{ art.title }}</strong>
                                            <code>{{ displayBaseUrl }}/news/{{ art.slug }}</code>
                                        </div>
                                        <a :href="art.path || `/news/${art.slug}`" target="_blank" rel="noopener" class="btn">Xem</a>
                                    </li>
                                    <li v-for="prod in props.seoAudit.sampleProducts.slice(0, 3)" :key="`prod-${prod.id}`">
                                        <div>
                                            <strong>{{ prod.name }}</strong>
                                            <code>{{ displayBaseUrl }}/products/{{ prod.slug || prod.id }}</code>
                                        </div>
                                        <a :href="prod.path || `/products/${prod.slug || prod.id}`" target="_blank" rel="noopener" class="btn">Xem</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>
                </template>
            </form>

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
                        <select v-model="previewPageKey" aria-label="Chọn trang để xem trước">
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
import { computed, ref } from 'vue';
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
    seo_schema_type: string;
    seo_geo_region: string;
    seo_geo_placename: string;
    seo_home_title: string;
    seo_home_description: string;
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

type SeoAuditPayload = {
    staticPagesCount: number;
    productsCount: number;
    productsWithDescCount: number;
    productsWithImageCount: number;
    projectsCount: number;
    projectsWithDescCount: number;
    articlesCount: number;
    articlesWithExcerptCount: number;
    cataloguesCount: number;
    totalIndexedUrls: number;
    sitemapUrl: string;
    robotsUrl: string;
    baseUrl: string;
    sampleProjects: Array<{ id: number; name: string; slug: string; url: string; path?: string; location: string }>;
    sampleProducts: Array<{ id: number; name: string; slug?: string; subtitle: string | null; url: string; path?: string }>;
    sampleArticles: Array<{ id: number; title: string; slug: string; url: string; path?: string }>;
};

type AdminStat = {
    label: string;
    count: number;
    description: string;
};

const props = defineProps<{
    settings: SeoSettingsMap;
    defaults: SeoSettingsMap;
    seoAudit: SeoAuditPayload;
    stats: AdminStat[];
    openRequestsCount: number;
    chatUnreadCount?: number;
    supportProjectsCount?: number;
}>();

const activeTab = ref<'general' | 'pages' | 'audit'>('general');
const previewPageKey = ref<'default' | 'home' | 'products' | 'projects' | 'news' | 'catalogues' | 'contacts'>('home');
const serpDevice = ref<'desktop' | 'mobile'>('desktop');
const copiedUrl = ref<string | null>(null);
const isPinging = ref(false);

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
    seo_schema_type: props.settings.seo_schema_type ?? props.defaults.seo_schema_type,
    seo_geo_region: props.settings.seo_geo_region ?? props.defaults.seo_geo_region,
    seo_geo_placename: props.settings.seo_geo_placename ?? props.defaults.seo_geo_placename,
    seo_home_title: props.settings.seo_home_title ?? props.defaults.seo_home_title,
    seo_home_description: props.settings.seo_home_description ?? props.defaults.seo_home_description,
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
    key: 'home' | 'products' | 'projects' | 'news' | 'catalogues' | 'contacts';
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
        key: 'products',
        label: 'Danh mục Sản phẩm & Thiết bị',
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
    const matched = pageConfigs.find((item) => item.key === previewPageKey.value);
    const path = matched ? matched.path : '/';

    return path === '/' ? displayBaseUrl.value : `${displayBaseUrl.value}${path}`;
});

const activePreviewTitle = computed<string>(() => {
    const matched = pageConfigs.find((item) => item.key === previewPageKey.value);
    const raw = matched ? (form[matched.titleField] || form.seo_meta_title) : form.seo_meta_title;

    return raw || 'HVAC Mart';
});

const activePreviewDescription = computed<string>(() => {
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
            detail: `Đã kích hoạt ${form.seo_schema_type}, WebSite SearchAction, Product & NewsArticle.`,
            passed: Boolean(form.seo_schema_type),
        },
        {
            label: 'URL thân thiện SEO (Slug tiếng Việt không dấu)',
            detail: 'Áp dụng cho toàn bộ trang Dự án tiêu biểu và Bài viết tin tức.',
            passed: true,
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
