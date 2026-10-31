<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type CalculatorProduct = {
    id: number;
    name: string;
    product_title: string | null;
    description: string;
    price: number;
    image: string[] | null;
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
};

type ProjectType = 'apartment' | 'hotel_office' | 'industrial_hospital';

const props = defineProps<{
    open: boolean;
    products: CalculatorProduct[];
}>();

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'add-to-cart', product: CalculatorProduct): void;
}>();

const activeTab = ref<'ariston' | 'kflex'>('ariston');

// Shared / Ariston calculator state (3 nhóm dự án chính)
const buildingType = ref<ProjectType>('apartment');
const peopleCount = ref<number>(3);
const usageStyle = ref<'shower' | 'bathtub' | 'public_business'>('shower');

// K-FLEX calculator state
const pipeApplication = ref<'vrv' | 'chiller' | 'duct'>('vrv');
const installEnv = ref<'indoor' | 'humid' | 'outdoor'>('indoor');
const pipeTemp = ref<'standard' | 'deep'>('standard');

const findAristonProducts = (keywords: string[], preferHeatPump = false): CalculatorProduct[] => {
    const aristonPool = props.products.filter((product) => {
        const haystack = `${product.name} ${product.product_title ?? ''} ${product.category?.name ?? ''} ${product.subcategory?.name ?? ''}`.toLowerCase();
        return haystack.includes('ariston') || haystack.includes('nước nóng') || haystack.includes('bơm nhiệt') || haystack.includes('slim') || haystack.includes('pro r') || haystack.includes('an3') || haystack.includes('ari ');
    });

    const filtered = aristonPool.filter((product) => {
        const text = `${product.name} ${product.product_title ?? ''} ${product.subcategory?.name ?? ''}`.toLowerCase();
        const isHeatPump = text.includes('bơm nhiệt') || text.includes('ptp') || text.includes('ar-40') || text.includes('ar-80');
        if (preferHeatPump) {
            return isHeatPump || keywords.some((kw) => text.includes(kw));
        }
        return !isHeatPump && keywords.some((kw) => text.includes(kw));
    });

    if (filtered.length > 0) {
        return filtered.slice(0, 3);
    }

    return (aristonPool.length > 0 ? aristonPool : props.products).slice(0, 3);
};

const findKflexProducts = (keywords: string[]): CalculatorProduct[] => {
    const kflexPool = props.products.filter((product) => {
        const haystack = `${product.name} ${product.product_title ?? ''} ${product.category?.name ?? ''} ${product.subcategory?.name ?? ''}`.toLowerCase();
        return haystack.includes('k-flex') || haystack.includes('kflex') || haystack.includes('m-flex') || haystack.includes('mflex') || haystack.includes('k-protect') || haystack.includes('bảo ôn');
    });

    const filtered = kflexPool.filter((product) => {
        const text = `${product.name} ${product.product_title ?? ''} ${product.description ?? ''}`.toLowerCase();
        return keywords.some((kw) => text.includes(kw));
    });

    if (filtered.length > 0) {
        return filtered.slice(0, 3);
    }

    return (kflexPool.length > 0 ? kflexPool : props.products).slice(0, 3);
};

const aristonRecommendation = computed(() => {
    // Nhóm dự án 2: Văn phòng, Khách sạn -> Heat Pump + Bảo ôn ST / K-Protect
    if (buildingType.value === 'hotel_office') {
        if (usageStyle.value === 'public_business') {
            return {
                projectScope: 'Văn phòng & Khách sạn (Hạng mục: Heat Pump & Bảo ôn)',
                capacityLabel: 'Heat Pump / Bình ≥ 100L',
                lineSuggest: 'Bơm nhiệt Ariston AR-40 PTP HX / AR-80PTP HX hoặc Bình PRO R 100L – ARI 150L',
                insulationSuggest: 'Bảo ôn cao cấp K-FLEX ST / K-FLEX K-Protect',
                advice: 'Khách sạn & tòa nhà văn phòng thường lắp hệ thống bơm nhiệt Heat Pump trung tâm. Với khu vệ sinh công cộng hoặc cơ sở kinh doanh dịch vụ cục bộ dùng bình từ 100L trở lên, đi kèm bảo ôn K-FLEX ST hoặc K-Protect.',
                matched: findAristonProducts(['ar-40', 'ar-80', '100', '150'], true),
            };
        }

        return {
            projectScope: 'Văn phòng & Khách sạn (Hạng mục: Heat Pump & Bảo ôn)',
            capacityLabel: 'Heat Pump 48kW – 80kW',
            lineSuggest: 'Bơm nhiệt Ariston AR-40 PTP HX (48kW) / AR-80PTP HX (80kW)',
            insulationSuggest: 'Bảo ôn cao cấp K-FLEX ST / K-FLEX K-Protect',
            advice: 'Đối với dự án khách sạn và tổ hợp văn phòng, giải pháp tiêu chuẩn là lắp đặt bơm nhiệt nước nóng trung tâm (Heat Pump Ariston) kết hợp bảo ôn cao cấp K-FLEX ST hoặc K-Protect chống cháy lan.',
            matched: findAristonProducts(['ar-40', 'ar-80', 'ptp'], true),
        };
    }

    // Nhóm dự án 3: Khu công nghiệp, Bệnh viện -> Hạng mục chính là Bảo ôn M-Flex; khu công cộng dùng bình >= 100L
    if (buildingType.value === 'industrial_hospital') {
        return {
            projectScope: 'Khu công nghiệp & Bệnh viện (Hạng mục chính: Bảo ôn M-Flex)',
            capacityLabel: 'Từ 100L – 150L+ (Khu công cộng)',
            lineSuggest: 'Bình Ariston PRO R 100L / ARI 150L & Bảo ôn K-FLEX M-Flex',
            insulationSuggest: 'Bảo ôn K-FLEX M-Flex chuyên dụng cho bệnh viện & KCN',
            advice: 'Dự án khu công nghiệp & bệnh viện tập trung chủ yếu vào hạng mục bảo ôn đường ống (sử dụng dòng M-Flex). Nếu có khu vực tắm công cộng, nhà ăn hoặc cơ sở dịch vụ cần nước nóng thì dùng bình từ 100L trở lên.',
            matched: [
                ...findKflexProducts(['m-flex', 'mflex', 'kflex', 'k-flex']).slice(0, 1),
                ...findAristonProducts(['100', '150'], false).slice(0, 2),
            ],
        };
    }

    // Nhóm dự án 1: Chung cư thương mại & NOXH (Bình nước nóng & Bảo ôn)
    if (usageStyle.value === 'public_business') {
        return {
            projectScope: 'Chung cư thương mại & NOXH · Khu công cộng / Cơ sở kinh doanh',
            capacityLabel: 'Từ 100L trở lên (100L – 150L)',
            lineSuggest: 'Ariston PRO R 100 V/H (100L) hoặc ARI 150 HORB (150L)',
            insulationSuggest: 'Bảo ôn K-FLEX ST / M-Flex cho trục & nhánh ống nước nóng',
            advice: 'Bình dung tích từ 100L trở lên thường dùng trong khu công cộng, shophouse, phòng gym, spa hoặc cơ sở kinh doanh dịch vụ cần lưu lượng nước nóng lớn và liên tục.',
            matched: findAristonProducts(['100', '150']),
        };
    }

    if (usageStyle.value === 'bathtub') {
        return {
            projectScope: 'Chung cư thương mại & NOXH (Bình nước nóng & Bảo ôn)',
            capacityLabel: 'Từ 80L trở lên (80L – 100L)',
            lineSuggest: 'Ariston PRO R 80 V/H (80L) hoặc PRO R 100 V/H (100L)',
            insulationSuggest: 'Bảo ôn K-FLEX ST cho đường ống nước nóng & điều hòa căn hộ',
            advice: 'Đối với căn hộ chung cư có bồn tắm nằm hoặc sen cây lưu lượng lớn, bắt buộc chọn bình từ 80L trở lên (80L – 100L) để đảm bảo đủ lượng nước ấm cho một lần xả bồn.',
            matched: findAristonProducts(['80', '100']),
        };
    }

    // Chung cư dùng vòi sen tiêu chuẩn: <=2 người (15-20L / 30L), 3 người (30L), >=4 người (50L trở lên)
    if (peopleCount.value <= 2) {
        return {
            projectScope: 'Chung cư thương mại & NOXH (Bình nước nóng & Bảo ôn)',
            capacityLabel: '20L – 30L (Khuyên dùng 30L)',
            lineSuggest: 'Ariston SLIM3 20B / AN3 15B hoặc SLIM3 30B / AN3 30B (30L)',
            insulationSuggest: 'Bảo ôn K-FLEX cho đường ống điều hòa & nước nóng căn hộ',
            advice: 'Căn hộ 1–2 người có thể dùng bình 15L–20L; tuy nhiên tại các dự án chung cư thương mại & NOXH, dòng 30L (chuẩn gia đình 3 người) thường được ưu tiên lắp đồng bộ để đảm bảo công suất dự phòng.',
            matched: findAristonProducts(['30', '20', '15']),
        };
    }

    if (peopleCount.value === 3) {
        return {
            projectScope: 'Chung cư thương mại & NOXH (Bình nước nóng & Bảo ôn)',
            capacityLabel: '30L',
            lineSuggest: 'Ariston SLIM3 30B VN / AN3 30B VN / PRO R S 30 SH (30L)',
            insulationSuggest: 'Bảo ôn K-FLEX cho đường ống điều hòa & nước nóng căn hộ',
            advice: 'Đối với chung cư thương mại & NOXH, hộ gia đình 3 người sử dụng vòi sen tiêu chuẩn thường dùng bình nước nóng gián tiếp dòng 30L, vừa vặn trần giả phòng tắm và tiết kiệm điện năng.',
            matched: findAristonProducts(['30']),
        };
    }

    if (peopleCount.value >= 7) {
        return {
            projectScope: 'Chung cư thương mại & NOXH (Bình nước nóng & Bảo ôn)',
            capacityLabel: '80L – 100L trở lên',
            lineSuggest: 'Ariston PRO R 80 V/H (80L) hoặc PRO R 100 V/H (100L)',
            insulationSuggest: 'Bảo ôn K-FLEX cho đường ống điều hòa & nước nóng căn hộ',
            advice: 'Gia đình đông người (từ 4 người trở lên dùng bình ≥ 50L; với 7+ người hoặc nhiều phòng tắm dùng chung) nên nâng lên bình 80L – 100L để cấp nước nóng liên tục.',
            matched: findAristonProducts(['80', '100', '50']),
        };
    }

    return {
        projectScope: 'Chung cư thương mại & NOXH (Bình nước nóng & Bảo ôn)',
        capacityLabel: 'Từ 50L trở lên (50L – 80L)',
        lineSuggest: 'Ariston PRO R 50 V / PRO R 50 SH (50L) hoặc PRO R 80 (80L)',
        insulationSuggest: 'Bảo ôn K-FLEX cho đường ống điều hòa & nước nóng căn hộ',
        advice: 'Đối với căn hộ chung cư có từ 4 người trở lên, cần chọn bình nước nóng dung tích từ 50L trở lên để đảm bảo đủ nước nóng sinh hoạt liên tiếp cho cả gia đình.',
        matched: findAristonProducts(['50', '80', '40']),
    };
});

const kflexRecommendation = computed(() => {
    let thickness = '13mm – 19mm';
    if (pipeApplication.value === 'chiller') {
        thickness = installEnv.value === 'indoor' ? '25mm – 32mm' : '32mm – 50mm';
    } else if (pipeApplication.value === 'duct') {
        thickness = installEnv.value === 'outdoor' ? '25mm (Có phủ bạc Alu)' : '13mm – 19mm (Bảo ôn tấm)';
    } else if (installEnv.value === 'humid' || installEnv.value === 'outdoor' || pipeTemp.value === 'deep') {
        thickness = '19mm – 25mm';
    }

    // Nhóm 2: Văn phòng, Khách sạn -> Bảo ôn loại xịn K-FLEX ST, K-Protect (+ Heatpump)
    if (buildingType.value === 'hotel_office') {
        const appLabel =
            pipeApplication.value === 'chiller'
                ? 'Đường ống Chiller & trục nước nóng Heat Pump'
                : pipeApplication.value === 'duct'
                  ? 'Hệ thống ống gió cấp & hồi khách sạn / văn phòng'
                  : 'Hệ thống ống đồng VRV/VRF & đường ống Heat Pump';

        return {
            projectScope: 'Văn phòng & Khách sạn (Hạng mục: Heat Pump & Bảo ôn cao cấp)',
            thickness,
            productLine: 'K-FLEX ST / K-FLEX K-Protect (Dòng bảo ôn cao cấp)',
            companionSuggest: `Ứng dụng: ${appLabel} · Kết hợp hệ Heat Pump Ariston`,
            technicalNote:
                'Khách sạn và tòa nhà văn phòng yêu cầu tiêu chuẩn chống cháy lan, ít khói và thẩm mỹ kỹ thuật khắt khe nên dùng dòng bảo ôn cao cấp K-FLEX ST hoặc K-Protect (Class 0, FM Approved, UL94) kết hợp hệ thống bơm nhiệt Heat Pump.',
            matched: findKflexProducts(['st', 'k-protect', 'kprotect', 'kflex']),
        };
    }

    // Nhóm 3: Khu công nghiệp & Bệnh viện -> Dùng M-Flex
    if (buildingType.value === 'industrial_hospital') {
        const appLabel =
            pipeApplication.value === 'chiller'
                ? 'Đường ống nước lạnh Chiller / AHU / FCU nhà máy & bệnh viện'
                : pipeApplication.value === 'duct'
                  ? 'Hệ thống ống gió HVAC khu công nghiệp & bệnh viện'
                  : 'Hệ thống ống đồng & đường ống kỹ thuật công nghiệp';

        return {
            projectScope: 'Khu công nghiệp & Bệnh viện (Hạng mục chuyên dụng: Bảo ôn)',
            thickness,
            productLine: 'K-FLEX M-Flex (Bảo ôn chuyên dụng Bệnh viện & KCN)',
            companionSuggest: `Ứng dụng: ${appLabel}`,
            technicalNote:
                'Đối với dự án bệnh viện, nhà máy và khu công nghiệp, khuyến nghị sử dụng dòng bảo ôn M-Flex giúp kiểm soát ngưng tụ ổn định và tối ưu chi phí đầu tư trên khối lượng tuyến ống lớn.',
            matched: findKflexProducts(['m-flex', 'mflex', 'kflex', 'k-flex']),
        };
    }

    // Nhóm 1: Chung cư thương mại & NOXH -> Bình nước nóng + Bảo ôn K-FLEX (ST / M-Flex)
    return {
        projectScope: 'Chung cư thương mại & NOXH (Hạng mục: Bình nước nóng & Bảo ôn)',
        thickness,
        productLine: 'Bảo ôn K-FLEX ST / M-Flex (Ống & tấm cách nhiệt căn hộ)',
        companionSuggest: 'Đồng bộ cùng bình nước nóng gián tiếp Ariston (30L / 50L+ / 80L+)',
        technicalNote:
            'Dự án chung cư thương mại & NOXH sử dụng đồng bộ bảo ôn K-FLEX cho tuyến ống đồng điều hòa Multi/Split và ống cấp nước nóng căn hộ, ngăn đọng sương trần thạch cao và giảm thất thoát nhiệt.',
        matched: findKflexProducts(['k-flex', 'kflex', 'st', 'm-flex']),
    };
});

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ';
</script>

<template>
    <Transition name="kb-modal">
        <div v-if="open" class="modal-wrap kb-calc-modal-wrap open" role="presentation" @click.self="emit('close')">
            <section class="modal kb-calc-modal" role="dialog" aria-modal="true" aria-label="Công cụ tính chọn thiết bị HVAC">
                <div class="kb-auth-modal-head">
                    <div>
                        <span class="eyebrow"><span class="dot"></span> SMART SIZING TOOL</span>
                        <h3>Công cụ tư vấn chọn thiết bị theo loại dự án</h3>
                    </div>
                    <button class="close-btn" type="button" aria-label="Đóng công cụ tính chọn" @click="emit('close')">×</button>
                </div>

                <div class="kb-auth-tabs" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        class="kb-auth-tab"
                        :class="{ active: activeTab === 'ariston' }"
                        @click="activeTab = 'ariston'"
                    >
                        Bình nước nóng &amp; Heat Pump Ariston
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="kb-auth-tab"
                        :class="{ active: activeTab === 'kflex' }"
                        @click="activeTab = 'kflex'"
                    >
                        Bảo ôn K-FLEX (ST / K-Protect / M-Flex)
                    </button>
                </div>

                <div v-if="activeTab === 'ariston'" class="kb-calc-body">
                    <div class="kb-calc-grid">
                        <div class="kb-calc-inputs">
                            <div class="field">
                                <label for="calcBuilding">LOẠI DỰ ÁN / CÔNG TRÌNH</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcBuilding" v-model="buildingType">
                                        <option value="apartment">Chung cư thương mại &amp; NOXH</option>
                                        <option value="hotel_office">Văn phòng &amp; Khách sạn</option>
                                        <option value="industrial_hospital">Khu công nghiệp &amp; Bệnh viện</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>

                            <div class="field">
                                <label for="calcUsage">NHU CẦU SỬ DỤNG NƯỚC NÓNG</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcUsage" v-model="usageStyle">
                                        <option value="shower">Hộ gia đình tắm vòi sen tiêu chuẩn (3 người: 30L · ≥4 người: ≥50L)</option>
                                        <option value="bathtub">Căn hộ có bồn tắm nằm / Sen lưu lượng lớn (Từ 80L trở lên)</option>
                                        <option value="public_business">Khu công cộng / Cơ sở kinh doanh dịch vụ (Từ 100L trở lên)</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>

                            <div v-if="buildingType === 'apartment' && usageStyle === 'shower'" class="field">
                                <label for="calcPeople">QUY MÔ HỘ GIA ĐÌNH ({{ peopleCount }} người)</label>
                                <input
                                    id="calcPeople"
                                    v-model.number="peopleCount"
                                    type="range"
                                    min="1"
                                    max="8"
                                    step="1"
                                >
                                <div class="kb-calc-scale">
                                    <span>1–2 người</span>
                                    <span>3 người (30L)</span>
                                    <span>≥ 4 người (≥ 50L)</span>
                                </div>
                            </div>
                        </div>

                        <div class="kb-calc-result">
                            <span class="kb-calc-badge">{{ aristonRecommendation.projectScope }}</span>
                            <div class="kb-calc-metric">
                                <span>Công suất / Dung tích đề xuất</span>
                                <strong>{{ aristonRecommendation.capacityLabel }}</strong>
                            </div>
                            <div class="kb-calc-line">
                                <span>Thiết bị nước nóng khuyến nghị:</span>
                                <b>{{ aristonRecommendation.lineSuggest }}</b>
                            </div>
                            <div class="kb-calc-line">
                                <span>Hạng mục bảo ôn đi kèm:</span>
                                <b>{{ aristonRecommendation.insulationSuggest }}</b>
                            </div>
                            <p class="kb-calc-note">{{ aristonRecommendation.advice }}</p>

                            <div v-if="aristonRecommendation.matched.length" class="kb-calc-products">
                                <span class="kb-calc-subhead">Sản phẩm gợi ý tại HVAC Mart:</span>
                                <div
                                    v-for="product in aristonRecommendation.matched"
                                    :key="product.id"
                                    class="kb-calc-product-row"
                                >
                                    <div>
                                        <Link :href="`/products/${product.id}`" class="kb-calc-prod-link" @click="emit('close')">
                                            {{ product.name }}
                                        </Link>
                                        <small>{{ formatPrice(product.price) }}</small>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-primary kb-calc-add-btn"
                                        @click="emit('add-to-cart', product)"
                                    >
                                        + Giỏ báo giá
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="kb-calc-body">
                    <div class="kb-calc-grid">
                        <div class="kb-calc-inputs">
                            <div class="field">
                                <label for="calcKflexProject">LOẠI DỰ ÁN / CÔNG TRÌNH</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcKflexProject" v-model="buildingType">
                                        <option value="apartment">Chung cư thương mại &amp; NOXH</option>
                                        <option value="hotel_office">Văn phòng &amp; Khách sạn</option>
                                        <option value="industrial_hospital">Khu công nghiệp &amp; Bệnh viện</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>

                            <div class="field">
                                <label for="calcPipeApp">HỆ THỐNG ĐƯỜNG ỐNG / ỨNG DỤNG</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcPipeApp" v-model="pipeApplication">
                                        <option value="vrv">Ống đồng điều hòa (Split / Multi / VRV / VRF) &amp; Ống nước nóng</option>
                                        <option value="chiller">Đường ống nước lạnh Chiller / AHU / FCU / Heat Pump</option>
                                        <option value="duct">Hệ thống ống gió cấp &amp; hồi HVAC</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>

                            <div class="field">
                                <label for="calcEnv">MÔI TRƯỜNG LẮP ĐẶT</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcEnv" v-model="installEnv">
                                        <option value="indoor">Trong nhà có trần giả thông thoáng</option>
                                        <option value="humid">Trục kỹ thuật kín / Độ ẩm môi trường cao (&gt;80%)</option>
                                        <option value="outdoor">Ngoài trời / Phòng máy / Tầng mái</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>

                            <div class="field">
                                <label for="calcTemp">DẢI NHIỆT ĐỘ MÔI CHẤT</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcTemp" v-model="pipeTemp">
                                        <option value="standard">Điều hòa &amp; cấp nhiệt tiêu chuẩn (5°C đến 60°C)</option>
                                        <option value="deep">Làm lạnh sâu / Công nghiệp (&lt; 5°C)</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="kb-calc-result">
                            <span class="kb-calc-badge">{{ kflexRecommendation.projectScope }}</span>
                            <div class="kb-calc-metric">
                                <span>Độ dày bảo ôn đề xuất</span>
                                <strong>{{ kflexRecommendation.thickness }}</strong>
                            </div>
                            <div class="kb-calc-line">
                                <span>Dòng bảo ôn khuyến nghị:</span>
                                <b>{{ kflexRecommendation.productLine }}</b>
                            </div>
                            <div class="kb-calc-line">
                                <span>Phạm vi &amp; giải pháp đồng bộ:</span>
                                <b>{{ kflexRecommendation.companionSuggest }}</b>
                            </div>
                            <p class="kb-calc-note">{{ kflexRecommendation.technicalNote }}</p>

                            <div v-if="kflexRecommendation.matched.length" class="kb-calc-products">
                                <span class="kb-calc-subhead">Vật tư bảo ôn gợi ý:</span>
                                <div
                                    v-for="product in kflexRecommendation.matched"
                                    :key="product.id"
                                    class="kb-calc-product-row"
                                >
                                    <div>
                                        <Link :href="`/products/${product.id}`" class="kb-calc-prod-link" @click="emit('close')">
                                            {{ product.name }}
                                        </Link>
                                        <small>{{ formatPrice(product.price) }}</small>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-primary kb-calc-add-btn"
                                        @click="emit('add-to-cart', product)"
                                    >
                                        + Giỏ báo giá
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </Transition>
</template>
