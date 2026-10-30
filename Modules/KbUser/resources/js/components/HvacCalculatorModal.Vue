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

const props = defineProps<{
    open: boolean;
    products: CalculatorProduct[];
}>();

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'add-to-cart', product: CalculatorProduct): void;
}>();

const activeTab = ref<'ariston' | 'kflex'>('ariston');

// Ariston calculator state
const peopleCount = ref<number>(4);
const buildingType = ref<'apartment' | 'villa' | 'project'>('apartment');
const usageStyle = ref<'shower' | 'bathtub'>('shower');

// K-FLEX calculator state
const pipeApplication = ref<'vrv' | 'chiller' | 'duct'>('vrv');
const installEnv = ref<'indoor' | 'humid' | 'outdoor'>('indoor');
const pipeTemp = ref<'standard' | 'deep'>('standard');

const aristonRecommendation = computed(() => {
    let baseLiters = peopleCount.value * (usageStyle.value === 'bathtub' ? 12 : 7);

    if (buildingType.value === 'villa') {
        baseLiters = Math.max(baseLiters, 30);
    } else if (buildingType.value === 'project') {
        baseLiters = Math.max(baseLiters, 50);
    }

    let capacityLabel = '15L – 20L';
    let lineSuggest = 'Ariston AN2 / SL2 (Gián tiếp 15L - 20L)';
    let advice = 'Phù hợp cho căn hộ nhỏ hoặc phòng tắm đơn từ 1–3 người sử dụng vòi sen tiêu chuẩn.';

    if (baseLiters > 22 && baseLiters <= 35) {
        capacityLabel = '30L';
        lineSuggest = 'Ariston Slim2 / Andris2 30L';
        advice = 'Dung tích tối ưu cho gia đình 3–5 người, đảm bảo giữ nhiệt lâu và tiết kiệm điện năng.';
    } else if (baseLiters > 35) {
        capacityLabel = '50L – 100L / Bơm nhiệt';
        lineSuggest = 'Ariston PRO R 50L–100L hoặc Heat Pump Ariston';
        advice = 'Khuyến nghị dùng bình dung tích lớn hoặc hệ nước nóng trung tâm cho biệt thự, khách sạn, nhiều phòng tắm.';
    }

    const matched = props.products
        .filter((product) => {
            const haystack = `${product.name} ${product.product_title ?? ''} ${product.category?.name ?? ''} ${product.subcategory?.name ?? ''}`.toLowerCase();
            return haystack.includes('ariston') || haystack.includes('nước nóng') || haystack.includes('bình');
        })
        .slice(0, 3);

    return {
        capacityLabel,
        lineSuggest,
        advice,
        matched: matched.length > 0 ? matched : props.products.slice(0, 3),
    };
});

const kflexRecommendation = computed(() => {
    let thickness = '13mm – 19mm';
    let productLine = 'K-FLEX ST (Ống bảo ôn tiêu chuẩn)';
    let technicalNote = 'Ngăn đọng sương hiệu quả cho đường ống đồng điều hòa cục bộ & VRV/VRF trong nhà.';

    if (pipeApplication.value === 'chiller') {
        thickness = installEnv.value === 'indoor' ? '25mm – 32mm' : '32mm – 50mm';
        productLine = 'K-FLEX ST ống / tấm bọc đường ống Chiller';
        technicalNote = 'Đường ống nước lạnh Chiller chênh lệch nhiệt độ lớn, cần độ dày từ 25mm trở lên để chống thất thoát nhiệt.';
    } else if (pipeApplication.value === 'duct') {
        thickness = installEnv.value === 'outdoor' ? '25mm (Có phủ bạc Alu)' : '13mm – 19mm (Tấm K-FLEX)';
        productLine = 'Bảo ôn tấm K-FLEX ST / K-FLEX Alu';
        technicalNote = 'Dùng tấm bảo ôn K-FLEX dán ống gió tôn tráng kẽm, giảm ồn và cách nhiệt đường gió cấp.';
    } else if (installEnv.value === 'humid' || installEnv.value === 'outdoor' || pipeTemp.value === 'deep') {
        thickness = '19mm – 25mm';
        productLine = 'K-FLEX ST / Class 0 chống cháy lan';
        technicalNote = 'Khu vực độ ẩm cao hoặc trục kỹ thuật không thông gió nên tăng thêm 1 cấp độ dày (19mm–25mm).';
    }

    const matched = props.products
        .filter((product) => {
            const haystack = `${product.name} ${product.product_title ?? ''} ${product.category?.name ?? ''} ${product.subcategory?.name ?? ''}`.toLowerCase();
            return haystack.includes('k-flex') || haystack.includes('kflex') || haystack.includes('bảo ôn');
        })
        .slice(0, 3);

    return {
        thickness,
        productLine,
        technicalNote,
        matched: matched.length > 0 ? matched : props.products.slice(0, 3),
    };
});

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ';
</script>

<template>
    <Transition name="kb-modal">
        <div v-if="open" class="modal-wrap open" role="presentation" @click.self="emit('close')">
            <section class="modal kb-calc-modal" role="dialog" aria-modal="true" aria-label="Công cụ tính chọn thiết bị HVAC">
            <div class="kb-auth-modal-head">
                <div>
                    <span class="eyebrow"><span class="dot"></span> SMART SIZING TOOL</span>
                    <h3>Công cụ tính chọn nhanh thiết bị &amp; vật tư</h3>
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
                    Bình nước nóng Ariston
                </button>
                <button
                    type="button"
                    role="tab"
                    class="kb-auth-tab"
                    :class="{ active: activeTab === 'kflex' }"
                    @click="activeTab = 'kflex'"
                >
                    Độ dày bảo ôn K-FLEX
                </button>
            </div>

            <div v-if="activeTab === 'ariston'" class="kb-calc-body">
                <div class="kb-calc-grid">
                    <div class="kb-calc-inputs">
                        <div class="field">
                            <label for="calcPeople">SỐ NGƯỜI SỬ DỤNG ({{ peopleCount }} người)</label>
                            <input
                                id="calcPeople"
                                v-model.number="peopleCount"
                                type="range"
                                min="1"
                                max="10"
                                step="1"
                            >
                            <div class="kb-calc-scale">
                                <span>1–2 người</span>
                                <span>3–5 người</span>
                                <span>6–10 người</span>
                            </div>
                        </div>

                        <div class="field">
                            <label for="calcBuilding">LOẠI CÔNG TRÌNH</label>
                            <select id="calcBuilding" v-model="buildingType">
                                <option value="apartment">Căn hộ gia đình / Nhà phố</option>
                                <option value="villa">Biệt thự / Nhiều phòng tắm</option>
                                <option value="project">Khách sạn / Căn hộ dịch vụ / Dự án</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="calcUsage">NHU CẦU SỬ DỤNG NƯỚC NÓNG</label>
                            <select id="calcUsage" v-model="usageStyle">
                                <option value="shower">Tắm vòi sen tiêu chuẩn &amp; lavabo</option>
                                <option value="bathtub">Có bồn tắm nằm / Sen cây lưu lượng lớn</option>
                            </select>
                        </div>
                    </div>

                    <div class="kb-calc-result">
                        <span class="kb-calc-badge">KẾT QUẢ KHUYẾN NGHỊ KỸ THUẬT</span>
                        <div class="kb-calc-metric">
                            <span>Dung tích đề xuất</span>
                            <strong>{{ aristonRecommendation.capacityLabel }}</strong>
                        </div>
                        <div class="kb-calc-line">
                            <span>Dòng sản phẩm phù hợp:</span>
                            <b>{{ aristonRecommendation.lineSuggest }}</b>
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
                            <label for="calcPipeApp">HỆ THỐNG ĐƯỜNG ỐNG / ỨNG DỤNG</label>
                            <select id="calcPipeApp" v-model="pipeApplication">
                                <option value="vrv">Ống đồng điều hòa (Split / Multi / VRV / VRF)</option>
                                <option value="chiller">Đường ống nước lạnh Chiller / AHU / FCU</option>
                                <option value="duct">Hệ thống ống gió cấp &amp; hồi HVAC</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="calcEnv">MÔI TRƯỜNG LẮP ĐẶT</label>
                            <select id="calcEnv" v-model="installEnv">
                                <option value="indoor">Trong nhà có trần giả thông thoáng</option>
                                <option value="humid">Trục kỹ thuật kín / Độ ẩm môi trường cao (&gt;80%)</option>
                                <option value="outdoor">Ngoài trời / Phòng máy / Tầng mái</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="calcTemp">DẢI NHIỆT ĐỘ MÔI CHẤT</label>
                            <select id="calcTemp" v-model="pipeTemp">
                                <option value="standard">Điều hòa tiêu chuẩn (5°C đến 12°C)</option>
                                <option value="deep">Làm lạnh sâu / Công nghiệp (&lt; 5°C)</option>
                            </select>
                        </div>
                    </div>

                    <div class="kb-calc-result">
                        <span class="kb-calc-badge">KẾT QUẢ KHUYẾN NGHỊ KỸ THUẬT</span>
                        <div class="kb-calc-metric">
                            <span>Độ dày bảo ôn đề xuất</span>
                            <strong>{{ kflexRecommendation.thickness }}</strong>
                        </div>
                        <div class="kb-calc-line">
                            <span>Chủng loại vật tư:</span>
                            <b>{{ kflexRecommendation.productLine }}</b>
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
