<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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

type ProjectType = 'apartment' | 'hotel' | 'industrial';
type InsulationMaterial = 'mflex' | 'st_kprotect' | 'st' | 'kprotect';

const props = defineProps<{
    open: boolean;
    products?: CalculatorProduct[];
}>();

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'add-to-cart', product: CalculatorProduct): void;
}>();

const fetchedProducts = ref<CalculatorProduct[]>([]);
const isFetchingProducts = ref(false);

const availableProducts = computed<CalculatorProduct[]>(() =>
    props.products && props.products.length > 0 ? props.products : fetchedProducts.value,
);

const ensureProductsLoaded = async (): Promise<void> => {
    if ((props.products && props.products.length > 0) || fetchedProducts.value.length > 0 || isFetchingProducts.value) {
        return;
    }

    isFetchingProducts.value = true;
    try {
        const response = await fetch('/products/search?all=1', {
            headers: { Accept: 'application/json' },
        });
        if (response.ok) {
            const data = (await response.json()) as { products?: CalculatorProduct[] };
            fetchedProducts.value = data.products ?? [];
        }
    } catch {
        // Bỏ qua lỗi mạng tạm thời
    } finally {
        isFetchingProducts.value = false;
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            void ensureProductsLoaded();
        }
    },
    { immediate: true },
);

const activeTab = ref<'ariston' | 'kflex'>('ariston');

// Loại công trình (3 nhóm dự án)
const buildingType = ref<ProjectType>('apartment');

// Tab 1: Bình nước nóng & Heatpump
const peopleCount = ref<number>(3);
const usageStyle = ref<'shower' | 'bathtub' | 'public'>('shower');
const hotelSystemType = ref<'both' | 'central' | 'pool'>('both');

// Tab 2: Bảo ôn (Lựa chọn theo loại vật liệu, tự động tính độ dày)
const materialType = ref<InsulationMaterial>('mflex');
const pipeApplication = ref<'vrv' | 'chiller' | 'duct'>('vrv');
const installEnv = ref<'indoor' | 'humid' | 'outdoor'>('indoor');
const pipeTemp = ref<'standard' | 'deep'>('standard');

// Tự động đồng bộ vật liệu bảo ôn đề xuất khi đổi loại công trình
watch(buildingType, (newType) => {
    if (newType === 'hotel') {
        materialType.value = 'st_kprotect';
    } else {
        materialType.value = 'mflex';
    }
});

const isAristonProduct = (product: CalculatorProduct): boolean => {
    const haystack = `${product.name} ${product.product_title ?? ''} ${product.category?.name ?? ''} ${product.subcategory?.name ?? ''}`.toLowerCase();
    return (
        haystack.includes('ariston') ||
        haystack.includes('nước nóng') ||
        haystack.includes('bơm nhiệt') ||
        haystack.includes('slim') ||
        haystack.includes('pro r') ||
        haystack.includes('an3') ||
        haystack.includes('ari ')
    );
};

const getHeatPumpType = (product: CalculatorProduct): 'pool' | 'central' | null => {
    const text = `${product.name} ${product.product_title ?? ''} ${product.subcategory?.name ?? ''}`.toLowerCase();
    const isHeatPump =
        text.includes('bơm nhiệt') ||
        text.includes('heatpump') ||
        /\b(stp|ptp)\b/i.test(product.name) ||
        /\bar-(20|40|50|80)/i.test(product.name);

    if (!isHeatPump) {
        return null;
    }

    if (/\bstp\b/i.test(product.name) || text.includes('bể bơi') || text.includes('hồ bơi')) {
        return 'pool';
    }

    return 'central';
};

const extractWaterHeaterLiters = (product: CalculatorProduct): number | null => {
    if (getHeatPumpType(product) !== null) {
        return null;
    }

    const specText = `${product.product_title ?? ''}\n${product.description ?? ''}`;
    const explicitMatch = specText.match(/dung\s*tích\s*:?\s*(\d+)\s*l\b/i) ?? specText.match(/\b(15|20|30|40|50|80|100|150)\s*l\b/i);
    if (explicitMatch) {
        return Number(explicitMatch[1]);
    }

    const nameMatch = product.name.match(/\b(15|20|30|40|50|80|100|150)(?:b|\b)/i);
    return nameMatch ? Number(nameMatch[1]) : null;
};

const findAristonHeatPumps = (mode: 'pool' | 'central' | 'both'): CalculatorProduct[] => {
    const aristonPool = availableProducts.value.filter(isAristonProduct);

    const matched = aristonPool.filter((product) => {
        const hpType = getHeatPumpType(product);
        if (hpType === null) {
            return false;
        }
        if (mode === 'both') {
            return true;
        }
        return hpType === mode;
    });

    // Sắp xếp theo mã công suất tăng dần (AR-20 -> AR-40 -> AR-50 -> AR-80)
    matched.sort((a, b) => {
        const numA = Number(a.name.match(/\d+/)?.[0] ?? 0);
        const numB = Number(b.name.match(/\d+/)?.[0] ?? 0);
        return numA - numB;
    });

    return matched;
};

const findAristonByLiters = (minLiters: number, maxLiters: number, preferredLiters: number[] = []): CalculatorProduct[] => {
    const aristonPool = availableProducts.value
        .filter(isAristonProduct)
        .filter((product) => {
            const liters = extractWaterHeaterLiters(product);
            return liters !== null && liters >= minLiters && liters <= maxLiters;
        })
        .sort((a, b) => a.id - b.id);

    if (preferredLiters.length === 0) {
        return aristonPool.slice(0, 4);
    }

    // Phân bổ đều đại diện từng mức dung tích theo thứ tự ưu tiên
    const buckets = new Map<number, CalculatorProduct[]>();
    for (const cap of preferredLiters) {
        buckets.set(cap, []);
    }

    const others: CalculatorProduct[] = [];
    for (const product of aristonPool) {
        const liters = extractWaterHeaterLiters(product);
        if (liters !== null && buckets.has(liters)) {
            buckets.get(liters)!.push(product);
        } else {
            others.push(product);
        }
    }

    const result: CalculatorProduct[] = [];
    let added = true;
    while (added && result.length < 4) {
        added = false;
        for (const cap of preferredLiters) {
            const group = buckets.get(cap);
            if (group && group.length > 0 && result.length < 4) {
                result.push(group.shift()!);
                added = true;
            }
        }
    }

    for (const item of others) {
        if (result.length >= 4) {
            break;
        }
        result.push(item);
    }

    return result;
};

const findKflexProducts = (material: InsulationMaterial): CalculatorProduct[] => {
    const kflexPool = availableProducts.value.filter((product) => {
        const haystack = `${product.name} ${product.product_title ?? ''} ${product.category?.name ?? ''} ${product.subcategory?.name ?? ''}`.toLowerCase();
        return (
            haystack.includes('k-flex') ||
            haystack.includes('kflex') ||
            haystack.includes('m-flex') ||
            haystack.includes('mflex') ||
            haystack.includes('k-protect') ||
            haystack.includes('bảo ôn')
        );
    });

    const filtered = kflexPool.filter((product) => {
        const nameText = `${product.name} ${product.product_title ?? ''}`;
        const isMflex = /\bm-?flex\b/i.test(nameText);
        const isKprotect = /\bk-?protect\b/i.test(nameText);
        const isSt = /\bst\b/i.test(product.name);

        if (material === 'mflex') {
            return isMflex;
        }
        if (material === 'st') {
            return isSt;
        }
        if (material === 'kprotect') {
            return isKprotect;
        }
        // st_kprotect
        return isSt || isKprotect;
    });

    filtered.sort((a, b) => a.id - b.id);

    if (filtered.length > 0) {
        return filtered;
    }

    return kflexPool.slice(0, 3);
};

const aristonRecommendation = computed(() => {
    // 1. Khách sạn -> Heatpump - Heatpump hồ bơi, Bảo ôn đề xuất: ST, K-Protect
    if (buildingType.value === 'hotel') {
        const systemLabel =
            hotelSystemType.value === 'pool'
                ? 'Heatpump hồ bơi'
                : hotelSystemType.value === 'central'
                  ? 'Heatpump trung tâm'
                  : 'Heatpump – Heatpump hồ bơi';

        const lineSuggest =
            hotelSystemType.value === 'pool'
                ? 'Bơm nhiệt bể bơi Ariston AR-20STP HX (24kW) · AR-50STP HX (47kW)'
                : hotelSystemType.value === 'central'
                  ? 'Bơm nhiệt trung tâm Ariston AR-40 PTP HX (48kW) · AR-80PTP HX (80kW)'
                  : 'Heatpump trung tâm (AR-40 PTP HX / AR-80PTP HX) & Heatpump hồ bơi (AR-20STP HX / AR-50STP HX)';

        return {
            projectScope: 'DỰ ÁN KHÁCH SẠN',
            metricLabel: 'Thiết bị đề xuất',
            capacityLabel: systemLabel,
            lineSuggest,
            insulationSuggest: 'ST, K-Protect',
            advice: 'Khách sạn sử dụng hệ thống bơm nhiệt nước nóng trung tâm (Heatpump) và Heatpump gia nhiệt hồ bơi giúp tiết kiệm điện năng vận hành, kết hợp bảo ôn cao cấp K-FLEX ST, K-Protect.',
            matched: findAristonHeatPumps(hotelSystemType.value),
        };
    }

    // 2. Khu công nghiệp -> Heatpump, Bảo ôn đề xuất: M-Flex
    if (buildingType.value === 'industrial') {
        return {
            projectScope: 'DỰ ÁN KHU CÔNG NGHIỆP',
            metricLabel: 'Thiết bị đề xuất',
            capacityLabel: 'Heatpump',
            lineSuggest: 'Bơm nhiệt công nghiệp Ariston AR-40 PTP HX (48kW) / AR-80PTP HX (80kW)',
            insulationSuggest: 'M-Flex',
            advice: 'Khu công nghiệp sử dụng hệ thống bơm nhiệt công suất lớn (Heatpump) phục vụ nhu cầu nước nóng tập trung và dây chuyền sản xuất, kết hợp vật tư bảo ôn M-Flex.',
            matched: findAristonHeatPumps('central'),
        };
    }

    // 3. Chung cư, NOXH -> Tính theo dung tích bình, Bảo ôn đề xuất: M-Flex
    if (usageStyle.value === 'public') {
        return {
            projectScope: 'DỰ ÁN CHUNG CƯ, NOXH · KHU CÔNG CỘNG',
            metricLabel: 'Dung tích bình đề xuất',
            capacityLabel: 'Từ 100L trở lên (100L – 150L)',
            lineSuggest: 'Ariston PRO R 100 V/H (100L) · ARI 150 HORB (150L)',
            insulationSuggest: 'M-Flex',
            advice: 'Khu vực công cộng, phòng sinh hoạt cộng đồng hoặc tiện ích thương mại tại chung cư sử dụng bình nước nóng dung tích từ 100L trở lên, đi kèm bảo ôn M-Flex.',
            matched: findAristonByLiters(100, 200, [100, 150]),
        };
    }

    if (usageStyle.value === 'bathtub') {
        return {
            projectScope: 'DỰ ÁN CHUNG CƯ, NOXH · CÓ BỒN TẮM',
            metricLabel: 'Dung tích bình đề xuất',
            capacityLabel: '80L – 100L',
            lineSuggest: 'Ariston PRO R 80 V/H (80L) · PRO R 100 V/H (100L)',
            insulationSuggest: 'M-Flex',
            advice: 'Căn hộ có lắp đặt bồn tắm hoặc gia đình từ 5 người trở lên cần sử dụng bình dung tích 80L – 100L để đảm bảo đủ lưu lượng nước nóng, đi kèm bảo ôn M-Flex.',
            matched: findAristonByLiters(80, 100, [80, 100]),
        };
    }

    // Tắm vòi sen tiêu chuẩn theo số người:
    // <= 2 người: 15L - 20L
    if (peopleCount.value <= 2) {
        return {
            projectScope: 'DỰ ÁN CHUNG CƯ, NOXH (< 2 – 2 NGƯỜI)',
            metricLabel: 'Dung tích bình đề xuất',
            capacityLabel: '15L – 20L',
            lineSuggest: 'Ariston AN3 15 B VN (15L) · SLIM3 20B VN (20L)',
            insulationSuggest: 'M-Flex',
            advice: 'Căn hộ chung cư, NOXH có từ 1–2 người sử dụng phù hợp với dòng bình nước nóng gián tiếp 15L – 20L nhỏ gọn, kết hợp bảo ôn M-Flex.',
            matched: findAristonByLiters(15, 20, peopleCount.value === 1 ? [15, 20] : [20, 15]),
        };
    }

    // 3 - 4 người: 30L - 56L
    if (peopleCount.value <= 4) {
        return {
            projectScope: 'DỰ ÁN CHUNG CƯ, NOXH (3 – 4 NGƯỜI)',
            metricLabel: 'Dung tích bình đề xuất',
            capacityLabel: '30L – 56L',
            lineSuggest: 'Ariston SLIM3 30B / AN3 30B (30L) · PRO R S 40 SH (40L) · PRO R 50 V/SH (50L)',
            insulationSuggest: 'M-Flex',
            advice: 'Căn hộ chung cư, NOXH cho gia đình 3–4 người sử dụng dòng bình dung tích 30L – 56L (phổ biến 30L, 40L, 50L), kết hợp bảo ôn M-Flex.',
            matched: findAristonByLiters(30, 56, peopleCount.value === 3 ? [30, 40, 50] : [40, 50, 30]),
        };
    }

    // 5 người trở lên: 80L - 100L
    return {
        projectScope: 'DỰ ÁN CHUNG CƯ, NOXH (≥ 5 NGƯỜI)',
        metricLabel: 'Dung tích bình đề xuất',
        capacityLabel: '80L – 100L',
        lineSuggest: 'Ariston PRO R 80 V/H (80L) · PRO R 100 V/H (100L)',
        insulationSuggest: 'M-Flex',
        advice: 'Gia đình từ 5 người trở lên hoặc căn hộ có bồn tắm cần chọn bình nước nóng dung tích 80L – 100L để cấp đủ nước nóng liên tục, kết hợp bảo ôn M-Flex.',
        matched: findAristonByLiters(80, 100, peopleCount.value === 5 ? [80, 100] : [100, 80]),
    };
});

const kflexRecommendation = computed(() => {
    // Tự động tính độ dày theo ứng dụng, môi trường lắp đặt và nhiệt độ môi chất
    let thickness = '13mm – 19mm';

    if (pipeApplication.value === 'chiller') {
        if (pipeTemp.value === 'deep') {
            thickness = installEnv.value === 'indoor' ? '32mm – 40mm' : '40mm – 50mm';
        } else {
            thickness = installEnv.value === 'indoor' ? '25mm – 32mm' : '32mm – 50mm';
        }
    } else if (pipeApplication.value === 'duct') {
        if (installEnv.value === 'outdoor') {
            thickness = '25mm – 32mm (Tấm phủ bạc Alu)';
        } else if (installEnv.value === 'humid' || pipeTemp.value === 'deep') {
            thickness = '19mm – 25mm (Bảo ôn tấm)';
        } else {
            thickness = '13mm – 19mm (Bảo ôn tấm)';
        }
    } else {
        // Ống đồng điều hòa VRV/VRF & ống nước nóng
        if (installEnv.value === 'outdoor' || pipeTemp.value === 'deep') {
            thickness = '19mm – 25mm';
        } else if (installEnv.value === 'humid') {
            thickness = '19mm';
        } else {
            thickness = '13mm – 19mm';
        }
    }

    const materialInfoMap: Record<
        InsulationMaterial,
        { label: string; note: string }
    > = {
        mflex: {
            label: 'M-Flex (Cao su lưu hóa NBR)',
            note: 'Dòng bảo ôn M-Flex tối ưu hiệu quả cách nhiệt, chống đọng sương và chi phí đầu tư, là lựa chọn tiêu chuẩn cho dự án Chung cư, NOXH và Khu công nghiệp.',
        },
        st_kprotect: {
            label: 'ST, K-Protect (Cao su lưu hóa NBR & Polyolefin XLPE chống cháy)',
            note: 'Bộ đôi bảo ôn cao cấp K-FLEX ST và K-Protect đáp ứng tiêu chuẩn chống cháy lan Class 0, FM Approved, ít sinh khói, chuyên dùng cho dự án Khách sạn phối hợp cùng hệ thống Heatpump.',
        },
        st: {
            label: 'K-FLEX ST (Cao su lưu hóa NBR cao cấp Class 0)',
            note: 'Dòng bảo ôn K-FLEX ST có hệ số dẫn nhiệt thấp, kháng ẩm vượt trội và chứng chỉ chống cháy quốc tế, phù hợp cho hệ thống Chiller, VRV/VRF và đường ống Heatpump khách sạn.',
        },
        kprotect: {
            label: 'K-FLEX K-Protect (Polyolefin XLPE an toàn cháy nổ cao cấp)',
            note: 'Dòng bảo ôn K-Protect tăng cường khả năng chống cháy và kiểm soát khói nghiêm ngặt cho các trục kỹ thuật, phòng máy và công trình khách sạn cao tầng.',
        },
    };

    const projectInfoMap: Record<ProjectType, { scope: string; defaultInsulation: string; waterHeaterCompanion: string }> = {
        apartment: {
            scope: 'DỰ ÁN CHUNG CƯ, NOXH',
            defaultInsulation: 'M-Flex',
            waterHeaterCompanion: 'Bình nước nóng Ariston (15–20L / 30–56L / 80–100L / ≥100L)',
        },
        hotel: {
            scope: 'DỰ ÁN KHÁCH SẠN',
            defaultInsulation: 'ST, K-Protect',
            waterHeaterCompanion: 'Heatpump – Heatpump hồ bơi',
        },
        industrial: {
            scope: 'DỰ ÁN KHU CÔNG NGHIỆP',
            defaultInsulation: 'M-Flex',
            waterHeaterCompanion: 'Heatpump công nghiệp',
        },
    };

    const currentMaterial = materialInfoMap[materialType.value];
    const currentProject = projectInfoMap[buildingType.value];

    return {
        projectScope: `${currentProject.scope} · BẢO ÔN ĐỀ XUẤT: ${currentProject.defaultInsulation}`,
        thickness,
        productLine: currentMaterial.label,
        companionSuggest: `Thiết bị đồng bộ: ${currentProject.waterHeaterCompanion}`,
        technicalNote: currentMaterial.note,
        matched: findKflexProducts(materialType.value),
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
                        Bình nước nóng &amp; Heatpump
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="kb-auth-tab"
                        :class="{ active: activeTab === 'kflex' }"
                        @click="activeTab = 'kflex'"
                    >
                        Bảo ôn (M-Flex / ST / K-Protect)
                    </button>
                </div>

                <div v-if="activeTab === 'ariston'" class="kb-calc-body">
                    <div class="kb-calc-grid">
                        <div class="kb-calc-inputs">
                            <div class="field">
                                <label for="calcBuilding">LOẠI CÔNG TRÌNH</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcBuilding" v-model="buildingType">
                                        <option value="apartment">Chung cư, NOXH</option>
                                        <option value="hotel">Khách sạn</option>
                                        <option value="industrial">Khu công nghiệp</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>

                            <div v-if="buildingType === 'apartment'" class="field">
                                <label for="calcUsage">NHU CẦU SỬ DỤNG</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcUsage" v-model="usageStyle">
                                        <option value="shower">Tắm vòi sen tiêu chuẩn (Tính theo số người)</option>
                                        <option value="bathtub">Căn hộ có bồn tắm (80L – 100L)</option>
                                        <option value="public">Khu công cộng (100L trở lên)</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>

                            <div v-if="buildingType === 'apartment' && usageStyle === 'shower'" class="field">
                                <label for="calcPeople">SỐ NGƯỜI SỬ DỤNG ({{ peopleCount }} người)</label>
                                <input
                                    id="calcPeople"
                                    v-model.number="peopleCount"
                                    type="range"
                                    min="1"
                                    max="8"
                                    step="1"
                                >
                                <div class="kb-calc-scale">
                                    <span>&le; 2 người (15–20L)</span>
                                    <span>3–4 người (30–56L)</span>
                                    <span>&ge; 5 người (80–100L)</span>
                                </div>
                            </div>

                            <div v-if="buildingType === 'hotel'" class="field">
                                <label for="calcHotelSystem">HẠNG MỤC CẤP NHIỆT KHÁCH SẠN</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcHotelSystem" v-model="hotelSystemType">
                                        <option value="both">Heatpump – Heatpump hồ bơi</option>
                                        <option value="central">Heatpump nước nóng trung tâm</option>
                                        <option value="pool">Heatpump gia nhiệt hồ bơi</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="kb-calc-result">
                            <span class="kb-calc-badge">{{ aristonRecommendation.projectScope }}</span>
                            <div class="kb-calc-metric">
                                <span>{{ aristonRecommendation.metricLabel }}</span>
                                <strong>{{ aristonRecommendation.capacityLabel }}</strong>
                            </div>
                            <div class="kb-calc-line">
                                <span>Dòng thiết bị phù hợp:</span>
                                <b>{{ aristonRecommendation.lineSuggest }}</b>
                            </div>
                            <div class="kb-calc-line">
                                <span>Bảo ôn đề xuất:</span>
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
                                        <Link :href="`/products/${(product as { slug?: string }).slug || product.id}`" class="kb-calc-prod-link" @click="emit('close')">
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
                                <label for="calcKflexProject">LOẠI CÔNG TRÌNH</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcKflexProject" v-model="buildingType">
                                        <option value="apartment">Chung cư, NOXH</option>
                                        <option value="hotel">Khách sạn</option>
                                        <option value="industrial">Khu công nghiệp</option>
                                    </select>
                                    <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>

                            <div class="field">
                                <label for="calcMaterial">LOẠI VẬT LIỆU BẢO ÔN</label>
                                <div class="kb-adv-select-shell">
                                    <select id="calcMaterial" v-model="materialType">
                                        <option value="mflex">M-Flex (Đề xuất cho Chung cư, NOXH &amp; KCN)</option>
                                        <option value="st_kprotect">ST, K-Protect (Đề xuất cho Khách sạn)</option>
                                        <option value="st">K-FLEX ST (Cao su lưu hóa NBR Class 0)</option>
                                        <option value="kprotect">K-FLEX K-Protect (Chống cháy cao cấp)</option>
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
                                        <option value="vrv">Ống đồng điều hòa (Split / Multi / VRV) &amp; Ống nước nóng</option>
                                        <option value="chiller">Đường ống nước lạnh Chiller / AHU / FCU / Heatpump</option>
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
                                <span>Độ dày bảo ôn tự tính</span>
                                <strong>{{ kflexRecommendation.thickness }}</strong>
                            </div>
                            <div class="kb-calc-line">
                                <span>Vật liệu bảo ôn đề xuất:</span>
                                <b>{{ kflexRecommendation.productLine }}</b>
                            </div>
                            <div class="kb-calc-line">
                                <span>Giải pháp cấp nhiệt đi kèm:</span>
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
                                        <Link :href="`/products/${(product as { slug?: string }).slug || product.id}`" class="kb-calc-prod-link" @click="emit('close')">
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
