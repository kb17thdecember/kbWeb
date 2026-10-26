<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '../../layout/UserLayout.Vue';
</script>

<template>
    <Head title="Về chúng tôi | KB TECH" />

    <UserLayout :cart-count="0" active-page="about">
        <main>
            <section class="page-intro" aria-live="polite">
                <div class="wrap page-intro-inner">
                    <div>
                        <div class="eyebrow"><span class="dot"></span> KB TECH / ABOUT</div>
                        <h1>Về chúng tôi</h1>
                        <p>
                            Định hướng cung ứng rõ ràng, tư vấn kỹ thuật phù hợp và đồng hành cùng
                            tiến độ dự án.
                        </p>
                    </div>
                    <Link class="page-home-link" href="/"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Về trang chủ</Link>
                </div>
            </section>

            <section class="wrap trustbar reveal visible">
                <div class="trustitem">
                    <div class="trust-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="m12 3 8 4v5c0 5-3.5 7.8-8 9-4.5-1.2-8-4-8-9V7z" />
                            <path d="m8.5 12 2.2 2.2 4.8-5" />
                        </svg>
                    </div>
                    <div><strong>Chọn lọc chất lượng</strong><span>Sản phẩm &amp; nguồn gốc rõ ràng</span></div>
                </div>
                <div class="trustitem">
                    <div class="trust-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M3 6h11v11H3zM14 10h4l3 4v3h-7z" />
                            <circle cx="7.5" cy="19" r="2" />
                            <circle cx="18" cy="19" r="2" />
                        </svg>
                    </div>
                    <div><strong>Cung ứng linh hoạt</strong><span>Hỗ trợ theo tiến độ công trình</span></div>
                </div>
                <div class="trustitem">
                    <div class="trust-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1" />
                            <circle cx="12" cy="12" r="5" />
                        </svg>
                    </div>
                    <div><strong>Tư vấn kỹ thuật</strong><span>Đúng thông số, đúng nhu cầu</span></div>
                </div>
                <div class="trustitem">
                    <div class="trust-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M12 8v4l3 2" />
                            <circle cx="12" cy="12" r="9" />
                            <path d="m5 3 2 2m12-2-2 2" />
                        </svg>
                    </div>
                    <div><strong>Đồng hành dài lâu</strong><span>Phản hồi rõ ràng, tận tâm</span></div>
                </div>
            </section>

            <section class="section">
                <div class="wrap split-feature">
                    <div class="feature-visual reveal visible" role="img" aria-label="Minh họa giải pháp vật liệu bảo ôn K-FLEX">
                        <div class="feature-lines"></div>
                        <div class="feature-big">Less loss.<br>More life.<small>Insulation that works.</small></div>
                        <div class="insulation-roll">
                            <div class="roll-core"></div>
                        </div>
                        <div class="feature-label">
                            <strong>K-FLEX · THERMAL INSULATION</strong>
                            <span>Giải pháp bảo ôn chuyên dụng</span>
                        </div>
                    </div>

                    <div class="feature-copy reveal visible">
                        <div class="eyebrow"><span class="dot"></span> Chọn giải pháp, không chỉ chọn hàng</div>
                        <h2 class="section-title">Từ bản vẽ đến<br><em>công trình thực tế.</em></h2>
                        <p>
                            Chúng tôi hướng đến việc trở thành đầu mối cung ứng tin cậy cho nhà
                            thầu, đơn vị cơ điện và chủ đầu tư — bằng danh mục rõ ràng, tư vấn phù
                            hợp và quy trình làm việc chuyên nghiệp.
                        </p>
                        <ul class="check-list">
                            <li><span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span> Hỗ trợ tra cứu thông số và lựa chọn sản phẩm</li>
                            <li><span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span> Tổng hợp nhu cầu đa danh mục trong một đầu mối</li>
                            <li><span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span> Đồng hành báo giá, tiến độ và hồ sơ vật tư</li>
                            <li><span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span> Ưu tiên giải pháp bền vững, tối ưu vận hành</li>
                        </ul>
                        <Link href="/products" class="btn btn-primary">Khám phá sản phẩm <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></Link>
                    </div>
                </div>
            </section>

            <section class="section">
                <div class="wrap">
                    <div class="section-head">
                        <div>
                            <div class="eyebrow"><span class="dot"></span> CÁCH CHÚNG TÔI LÀM VIỆC</div>
                            <h2 class="section-title">Rõ ràng từ<br><em>bước đầu tiên.</em></h2>
                        </div>
                        <p class="section-desc">
                            Quy trình tinh gọn giúp làm rõ yêu cầu, thống nhất phương án và phối
                            hợp cung ứng theo tiến độ công trình.
                        </p>
                    </div>
                    <div class="process">
                        <div class="process-step">
                            <span class="process-num">01 — DISCOVER</span>
                            <h3>Tiếp nhận nhu cầu</h3>
                            <p>Làm rõ hạng mục, số lượng, thông số và tiến độ dự kiến.</p>
                        </div>
                        <div class="process-step">
                            <span class="process-num">02 — CONSULT</span>
                            <h3>Tư vấn giải pháp</h3>
                            <p>Đề xuất danh mục phù hợp với điều kiện vận hành thực tế.</p>
                        </div>
                        <div class="process-step">
                            <span class="process-num">03 — PROPOSE</span>
                            <h3>Báo giá minh bạch</h3>
                            <p>Thống nhất quy cách, giá và các điều kiện cung ứng.</p>
                        </div>
                        <div class="process-step">
                            <span class="process-num">04 — DELIVER</span>
                            <h3>Phối hợp giao hàng</h3>
                            <p>Đồng hành xử lý các yêu cầu trong quá trình triển khai.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section">
                <div class="wrap">
                    <div class="testimonial">
                        <div class="quote-mark" aria-hidden="true">“</div>
                        <div class="quote-copy">
                            Một đối tác cung ứng tốt không chỉ giao đúng hàng — mà còn giúp đội dự
                            án chọn đúng giải pháp ngay từ đầu.
                        </div>
                        <div class="quote-meta">
                            <div class="stars" aria-label="5 trên 5 sao">★★★★★</div>
                            <strong>Cam kết dịch vụ</strong>
                            <span>Định hướng trải nghiệm khách hàng và đồng hành cùng công trình.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section">
                <div class="wrap">
                    <div class="project-section">
                        <div class="wrap">
                            <div class="section-head">
                                <div>
                                    <div class="eyebrow"><span class="dot"></span> ĐỒNG HÀNH CÙNG CÔNG TRÌNH</div>
                                    <h2 class="section-title">Bắt đầu từ<br><em>nhu cầu của bạn.</em></h2>
                                </div>
                                <p class="section-desc">
                                    Chia sẻ hạng mục, quy cách hoặc tiến độ dự kiến để đội ngũ tư vấn
                                    có cơ sở hỗ trợ phù hợp.
                                </p>
                            </div>
                            <Link href="/contacts" class="btn btn-primary">Trao đổi với chúng tôi <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></Link>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </UserLayout>
</template>