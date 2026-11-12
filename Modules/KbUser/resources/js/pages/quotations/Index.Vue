<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import aristonLogoAsset from '../../../assets/images/ariston_quote_logo.png';
import kflexLogoAsset from '../../../assets/images/kflex_quote_logo.png';
import starLightLogoAsset from '../../../assets/images/starlight_logo.png';
import dayMemAsset from '../../../../../KbAdmin/resources/assets/images/products/DAYMEM.jpg';
import UserLayout from '../../layout/UserLayout.Vue';

type AristonProduct = {
    id: number;
    name: string;
    product_title: string | null;
    origin?: string | null;
    description: string;
    price: number;
    image: string[];
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
};

type KflexPriceRecord = {
    id: number;
    product_line: 'st' | 'k_protect' | 'm_flex';
    form_type: 'sheet' | 'tube';
    variant_type: string;
    thickness: string;
    diameter: string | null;
    brand: string | null;
    origin: string | null;
    product_name: string;
    unit: string;
    price: number;
};

type QuotationItem = {
    uid: string;
    productId: number | null;
    productLabel: string;
    modelCode: string;
    origin: string;
    imageUrl: string;
    unit: string;
    specifications: string;
    unitPrice: number;
    quantity: number;
};

type KflexQuotationItem = {
    uid: string;
    kflexPriceId: number | null;
    itemName: string;
    brandLabel: string;
    originLabel: string;
    productTypeLabel: string;
    productLine: 'st' | 'k_protect' | 'm_flex';
    formType: 'tube' | 'sheet';
    variantType: string;
    thickness: string;
    innerDiameter: string;
    widthM: string;
    lengthM: string;
    qtyM: number | null;
    qtyM2: number | null;
    unitPrice: number;
    note: string;
};

type KflexQuotationGroup = {
    uid: string;
    hasTitle: boolean;
    title: string;
    items: KflexQuotationItem[];
};

const props = withDefaults(
    defineProps<{
        aristonProducts: AristonProduct[];
        kflexPrices?: KflexPriceRecord[];
        kflexProductLines?: Record<string, string>;
        kflexProductLineMeta?: Record<string, { label: string; brand: string; origin: string }>;
        kflexTubeVariants?: Record<string, string>;
        kflexSheetVariants?: Record<string, string>;
        quoteUser?: {
            name: string;
            email: string;
        };
    }>(),
    {
        kflexPrices: () => [],
        kflexProductLines: () => ({
            st: 'ST',
            k_protect: 'K-Protect',
            m_flex: 'M-Flex',
        }),
        kflexProductLineMeta: () => ({
            st: { label: 'ST', brand: 'Ý', origin: 'Malaysia' },
            k_protect: { label: 'K-Protect', brand: 'Ý', origin: 'Malaysia' },
            m_flex: { label: 'M-Flex', brand: 'Ý', origin: 'VN' },
        }),
        kflexTubeVariants: () => ({
            foam: 'Chỉ Foam',
            alu: 'Phủ nhôm',
        }),
        kflexSheetVariants: () => ({
            foam: 'Chỉ Foam',
            alu_or_glue: 'Có keo/nhôm',
            alu_and_glue: 'Có cả keo và nhôm',
        }),
        quoteUser: () => ({
            name: 'Tài khoản Báo Giá',
            email: 'publicquote@gmail.com',
        }),
    },
);

const cartCount = ref(0);

const refreshCartCount = (): void => {
    try {
        const raw = sessionStorage.getItem('kbuser-cart');
        if (!raw) {
            cartCount.value = 0;
            return;
        }
        const parsed = JSON.parse(raw) as Record<string, number>;
        cartCount.value = Object.values(parsed).reduce((sum, qty) => sum + (Number(qty) || 0), 0);
    } catch {
        cartCount.value = 0;
    }
};

onMounted(() => {
    refreshCartCount();
});

const logoutQuoteSession = (): void => {
    router.post('/quotations/logout');
};

const activeQuotationType = ref<'ariston' | 'second'>('ariston');

const pad2 = (n: number): string => String(n).padStart(2, '0');

const toInputDate = (date: Date): string =>
    `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;

const addTwoMonths = (isoDate: string): string => {
    const parts = isoDate.split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        const fallback = new Date();
        fallback.setMonth(fallback.getMonth() + 2);
        return toInputDate(fallback);
    }
    const d = new Date(parts[0], parts[1] - 1 + 2, parts[2]);
    return toInputDate(d);
};

const formatDisplayDateSlash = (isoDate: string, padDayMonth = false): string => {
    const parts = isoDate.split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        return isoDate;
    }
    const [year, month, day] = parts;
    if (padDayMonth) {
        return `${pad2(day)}/${pad2(month)}/${year}`;
    }
    return `${day}/${month}/${year}`;
};

const todayIso = toInputDate(new Date());

const quoteForm = ref({
    documentTitle: 'BÁO GIÁ BƠM NHIỆT ARISTON',
    recipientCompany: 'QUÝ ĐỐI TÁC CÔNG TY CỔ PHẦN TẬP ĐOÀN BĐS VẠN XUÂN',
    recipientPerson: 'Ms.Vân Chi',
    contactPhone: '0963.015.966',
    quoteDate: todayIso,
    validUntilDate: addTwoMonths(todayIso),
    projectName: 'Chung cư cao cấp Vạn Xuân',
    vatPercent: 8,
    signerName: 'Nguyễn Tuấn Kiệt',
    signerPhone: '0979 807 234',
    signerEmail: 'tuankiet@starlightgroup.vn',
});

watch(
    () => quoteForm.value.quoteDate,
    (newDate) => {
        if (newDate) {
            quoteForm.value.validUntilDate = addTwoMonths(newDate);
        }
    },
);

const deriveProductLabel = (product: AristonProduct): string => {
    const desc = product.description || '';
    const capacityMatch =
        desc.match(/Dung tích\s*:\s*([0-9]+\s*L)/i) ||
        product.name.match(/\b(15|20|30|40|50|80|100|150)\s*B?\b/i);
    const subcatName = (product.subcategory?.name || '').toLowerCase();

    if (subcatName.includes('bơm nhiệt')) {
        return 'Máy bơm nhiệt nước nóng Ariston';
    }

    if (capacityMatch) {
        const cap = capacityMatch[1].toUpperCase().endsWith('L')
            ? capacityMatch[1].replace(/\s+/g, '')
            : `${capacityMatch[1]}L`;
        return `Máy nước nóng gián tiếp dung tích ${cap}`;
    }

    return product.product_title || 'Máy nước nóng gián tiếp Ariston';
};

const resolveImageUrl = (raw?: string | null): string => {
    if (!raw) {
        return '';
    }
    if (raw.startsWith('data:') || raw.startsWith('http')) {
        return raw;
    }
    const path = raw.startsWith('/') ? raw : `/storage/${raw}`;
    return typeof window !== 'undefined' ? `${window.location.origin}${path}` : path;
};

const deriveAristonShortOrigin = (product?: AristonProduct): string => {
    if (!product) {
        return 'Việt Nam';
    }
    const rawOrigin = (product.origin ?? '').trim();
    if (rawOrigin) {
        const upper = rawOrigin.toUpperCase();
        if (upper === 'VN' || upper === 'VIỆT NAM' || upper === 'VIET NAM' || upper === 'VIETNAM') {
            return 'Việt Nam';
        }
        if (upper === 'TQ' || upper === 'TRUNG QUỐC' || upper === 'TRUNG QUOC' || upper === 'CHINA') {
            return 'Trung Quốc';
        }
        return rawOrigin;
    }

    const subcatName = (product.subcategory?.name || '').toLowerCase();
    const text = `${product.name} ${product.product_title ?? ''} ${product.description ?? ''}`;
    if (subcatName.includes('bơm nhiệt') || /\b(bơm\s*nhiệt|heat\s*pump|stp|ptp|ar-\d+)\b/i.test(text)) {
        return 'Trung Quốc';
    }

    const capMatch =
        text.match(/dung\s*tích\s*:?\s*(\d+)\s*l\b/i) ??
        product.name.match(/\b(10|15|20|30|40|50|56|80|100|150|200|300)\s*(?:l\b|b\b|sh\b|v\b|h\b|horb\b|hord\b|vert\b)/i);
    if (capMatch) {
        const liters = Number(capMatch[1]);
        return liters > 0 && liters <= 30 ? 'Việt Nam' : 'Trung Quốc';
    }

    return 'Trung Quốc';
};

const deriveAristonOrigin = (product?: AristonProduct): string => {
    const rawOrigin = (product?.origin ?? '').trim();
    if (rawOrigin && /^ý\s*-/i.test(rawOrigin)) {
        return rawOrigin;
    }
    const shortOrigin = deriveAristonShortOrigin(product);
    return `Ý-${shortOrigin}`;
};

const formatSpecLines = (raw?: string | null): string[] => {
    const text = (raw ?? '').trim();
    if (!text) {
        return [];
    }

    return text
        .replace(/\r\n/g, '\n')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            if (/^[-•+]/.test(line)) {
                return line.replace(/^[-•+]\s*/, '- ');
            }
            return `- ${line}`;
        });
};

const normalizeSpecificationsText = (raw?: string | null): string =>
    formatSpecLines(raw).join('\n');

let uidCounter = 1;
const nextUid = (): string => `row-${Date.now()}-${uidCounter++}`;

const createItemFromProduct = (product?: AristonProduct, quantity = 1): QuotationItem => {
    if (!product) {
        return {
            uid: nextUid(),
            productId: null,
            productLabel: 'Máy nước nóng gián tiếp dung tích 30L',
            modelCode: '',
            origin: 'Ý-Việt Nam',
            imageUrl: '',
            unit: 'Máy',
            specifications: '',
            unitPrice: 0,
            quantity,
        };
    }

    return {
        uid: nextUid(),
        productId: product.id,
        productLabel: deriveProductLabel(product),
        modelCode: product.name,
        origin: deriveAristonOrigin(product),
        imageUrl: resolveImageUrl(product.image?.[0]),
        unit: 'Máy',
        specifications: normalizeSpecificationsText(product.description),
        unitPrice: product.price > 0 ? product.price : 2386000,
        quantity,
    };
};

const defaultFirstProduct =
    props.aristonProducts.find((p) => p.name.includes('SLIM3 20')) ||
    props.aristonProducts[0];

const items = ref<QuotationItem[]>([
    createItemFromProduct(defaultFirstProduct, 798),
]);

const productSearchQuery = ref('');
const expandedUid = ref<string | null>(null);

const filteredAristonCatalog = computed(() => {
    const q = productSearchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.aristonProducts;
    }
    return props.aristonProducts.filter(
        (prod) =>
            prod.name.toLowerCase().includes(q) ||
            (prod.product_title || '').toLowerCase().includes(q) ||
            (prod.subcategory?.name || '').toLowerCase().includes(q),
    );
});

const isProductSelected = (productId: number): boolean =>
    items.value.some((item) => item.productId === productId);

const toggleProductSelection = (product: AristonProduct): void => {
    const existingIndex = items.value.findIndex((item) => item.productId === product.id);
    if (existingIndex !== -1) {
        if (items.value.length > 1) {
            items.value.splice(existingIndex, 1);
        }
        return;
    }

    items.value.push(createItemFromProduct(product, 1));
};

const selectAllFilteredProducts = (): void => {
    filteredAristonCatalog.value.forEach((product) => {
        if (!isProductSelected(product.id)) {
            items.value.push(createItemFromProduct(product, 1));
        }
    });
};

const clearToFirstProduct = (): void => {
    if (items.value.length > 1) {
        items.value = [items.value[0]];
    }
};

const toggleExpandRow = (uid: string): void => {
    expandedUid.value = expandedUid.value === uid ? null : uid;
};

const onSelectProduct = (item: QuotationItem, productIdValue: string): void => {
    const id = Number(productIdValue);
    if (!id) {
        item.productId = null;
        return;
    }
    const found = props.aristonProducts.find((p) => p.id === id);
    if (!found) {
        return;
    }
    item.productId = found.id;
    item.productLabel = deriveProductLabel(found);
    item.modelCode = found.name;
    item.origin = deriveAristonOrigin(found);
    item.imageUrl = resolveImageUrl(found.image?.[0]);
    item.unit = 'Máy';
    item.specifications = normalizeSpecificationsText(found.description);
    if (found.price > 0) {
        item.unitPrice = found.price;
    }
};

const addAristonRow = (): void => {
    const fallback = props.aristonProducts[0];
    items.value.push(createItemFromProduct(fallback, 1));
};

const addAccessoryRow = (): void => {
    items.value.push({
        uid: nextUid(),
        productId: null,
        productLabel: 'Dây mềm cấp nước 40cm',
        modelCode: '',
        origin: 'Thủy Sanh-\nVN',
        imageUrl: resolveImageUrl(dayMemAsset),
        unit: 'Dây',
        specifications:
            '-Dây cấp 4T lạnh\n-Dây cấp inox 40cm chịu nhiệt, ốc nối đồng, lớp trong nhựa PVC, lớp ngoài inox\n-Chiều dài dây 40cm',
        unitPrice: 50000,
        quantity: 1,
    });
};

const removeRow = (index: number): void => {
    if (items.value.length <= 1) {
        return;
    }
    items.value.splice(index, 1);
};

const onUploadCustomImage = (item: QuotationItem, event: Event): void => {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];
    if (!file) {
        return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        if (typeof reader.result === 'string') {
            item.imageUrl = reader.result;
        }
    };
    reader.readAsDataURL(file);
};

const formatVnd = (amount: number): string => {
    const safe = Number.isFinite(amount) ? Math.round(amount) : 0;
    return new Intl.NumberFormat('vi-VN').format(safe);
};

const rowAmount = (item: QuotationItem): number =>
    Math.round((Number(item.unitPrice) || 0) * (Number(item.quantity) || 0));

const subTotal = computed(() =>
    items.value.reduce((sum, item) => sum + rowAmount(item), 0),
);

const vatAmount = computed(() =>
    Math.round((subTotal.value * (Number(quoteForm.value.vatPercent) || 0)) / 100),
);

const grandTotal = computed(() => subTotal.value + vatAmount.value);

const formattedQuoteDate = computed(() =>
    formatDisplayDateSlash(quoteForm.value.quoteDate, false),
);

const formattedValidUntil = computed(() =>
    formatDisplayDateSlash(quoteForm.value.validUntilDate, true),
);

const formattedVatLabel = computed(() => {
    const v = Number(quoteForm.value.vatPercent) || 0;
    return v < 10 ? `0${v}%` : `${v}%`;
});

const escapeHtml = (value: string): string =>
    (value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const nl2br = (value: string): string =>
    escapeHtml(value).replace(/\r?\n/g, '<br>');

const starLightLogoUrl = computed(() => resolveImageUrl(starLightLogoAsset));
const aristonLogoUrl = computed(() => resolveImageUrl(aristonLogoAsset));
const kflexLogoUrl = computed(() => resolveImageUrl(kflexLogoAsset));
const isPortraitMode = computed(() => items.value.length >= 3);

const notifyToast = (message: string): void => {
    window.dispatchEvent(new CustomEvent('kbuser-toast', { detail: { message } }));
};

// =========================================================================
// K-FLEX QUOTATION BUILDER & PDF EXPORT
// =========================================================================
const kflexQuoteForm = ref({
    documentTitle: 'BÁO GIÁ BẢO ÔN K-FLEX',
    recipientCompany: 'Quý đối tác Công ty Cổ phần Kỹ thuật Thăng Tiến',
    recipientPerson: 'Mr.Phương',
    projectName: 'Detech Tower',
    quoteDate: todayIso,
    validUntilDate: addTwoMonths(todayIso),
    vatPercent: 8,
    introText: 'Công ty Star Light là đơn vị phân phối mảng dự án sản phẩm bảo ôn K-FLEX',
    paymentTerms: 'Thỏa thuận',
    deliveryMethod: 'Giao hàng tại chân công trình',
    deliveryTime:
        'tối thiểu 40 ngày (đối với hàng nhập khẩu) kể từ ngày đơn đặt hàng được xác nhận, Hiện đơn hàng đang có sẵn',
    contactName: 'Mr.Kiệt',
    contactHp: '0979.807.234',
    contactEmail: 'tuankiet@starlightgroup.vn',
});

watch(
    () => kflexQuoteForm.value.quoteDate,
    (newDate) => {
        if (newDate) {
            kflexQuoteForm.value.validUntilDate = addTwoMonths(newDate);
        }
    },
);

const kflexSectionPresets = [
    'BẢO ÔN CHO ĐƯỜNG THÉP',
    'BẢO ÔN CHO ĐƯỜNG ỐNG PVC',
    'BẢO ÔN ĐƯỜNG ỐNG ĐỒNG',
    'BẢO ÔN ỐNG GIÓ',
];

type KflexProductLineKey = 'st' | 'k_protect' | 'm_flex';

const kflexAllProductLineOptions: Array<{
    key: KflexProductLineKey;
    brandLabel: string;
    originLabel: string;
}> = [
    { key: 'st', brandLabel: 'K-Flex ST', originLabel: 'Ý - Malaysia' },
    { key: 'k_protect', brandLabel: 'K-Flex K-Protect', originLabel: 'Ý - Malaysia' },
    { key: 'm_flex', brandLabel: 'K-Flex M-Flex', originLabel: 'Ý - VN' },
];

const kflexSelectedProductLines = ref<KflexProductLineKey[]>(['st']);

const getDefaultSheetDimensions = (
    productLine: 'st' | 'k_protect' | 'm_flex',
    variantType: string,
    thickness: string,
): { widthM: string; lengthM: string } => {
    const tNum = Number(String(thickness).replace(/[^0-9.]/g, '')) || 25;

    if (productLine === 'k_protect') {
        return {
            widthM: '1.2',
            lengthM: tNum >= 30 ? '7.5' : '15',
        };
    }

    const widthM = productLine === 'st' && variantType !== 'foam' ? '1.14' : '1.22';
    const lengthMap: Record<number, string> = {
        6: '30',
        9: '20',
        10: '20',
        13: '14',
        16: '12',
        19: '10',
        25: '8',
        32: '6',
        40: '4',
        50: '4',
    };

    return {
        widthM,
        lengthM: lengthMap[tNum] || '4',
    };
};

const getDefaultBrandLabel = (productLine: 'st' | 'k_protect' | 'm_flex'): string => {
    if (productLine === 'k_protect') {
        return 'K-Flex K-Protect';
    }
    if (productLine === 'm_flex') {
        return 'K-Flex M-Flex';
    }
    return 'K-Flex ST';
};

const getDefaultOriginLabel = (
    productLine: 'st' | 'k_protect' | 'm_flex',
    brand?: string | null,
    origin?: string | null,
): string => {
    const meta = props.kflexProductLineMeta[productLine] || { brand: 'Ý', origin: 'Malaysia' };
    const b = (brand || meta.brand || 'Ý').trim();
    const o = (origin || meta.origin || 'Malaysia').trim();
    return `${b} - ${o}`;
};

const getDefaultProductTypeLabel = (
    formType: 'tube' | 'sheet',
    variantType: string,
): string => {
    if (formType === 'tube') {
        return variantType === 'alu' ? 'Có nhôm' : 'Chỉ Foam';
    }
    if (variantType === 'alu_or_glue') {
        return 'Có nhôm, không keo';
    }
    if (variantType === 'alu_and_glue') {
        return 'Có cả keo và nhôm';
    }
    return 'Chỉ Foam';
};

const normalizeNumberStr = (val?: string | null): string => {
    if (!val) {
        return '';
    }
    const trimmed = String(val).trim();
    if (/^\d+$/.test(trimmed)) {
        return String(Number(trimmed));
    }
    return trimmed;
};

const findMatchingKflexPrice = (
    productLine: 'st' | 'k_protect' | 'm_flex',
    formType: 'tube' | 'sheet',
    variantType: string,
    thickness: string,
    innerDiameter: string,
): KflexPriceRecord | undefined => {
    const tNorm = normalizeNumberStr(thickness);
    const dNorm = normalizeNumberStr(innerDiameter);

    return props.kflexPrices.find((p) => {
        if (p.product_line !== productLine || p.form_type !== formType || p.variant_type !== variantType) {
            return false;
        }
        if (normalizeNumberStr(p.thickness) !== tNorm) {
            return false;
        }
        if (formType === 'tube') {
            return normalizeNumberStr(p.diameter) === dNorm;
        }
        return true;
    });
};

const getAvailableThicknessesForRow = (item: KflexQuotationItem): string[] => {
    const set = new Set<string>();
    props.kflexPrices.forEach((p) => {
        if (
            p.product_line === item.productLine &&
            p.form_type === item.formType &&
            p.variant_type === item.variantType &&
            p.thickness
        ) {
            set.add(normalizeNumberStr(p.thickness));
        }
    });
    return Array.from(set).sort((a, b) => Number(a) - Number(b));
};

const getAvailableDiametersForRow = (item: KflexQuotationItem): string[] => {
    if (item.formType !== 'tube') {
        return [];
    }
    const tNorm = normalizeNumberStr(item.thickness);
    const set = new Set<string>();
    props.kflexPrices.forEach((p) => {
        if (
            p.product_line === item.productLine &&
            p.form_type === 'tube' &&
            p.variant_type === item.variantType &&
            normalizeNumberStr(p.thickness) === tNorm &&
            p.diameter
        ) {
            set.add(normalizeNumberStr(p.diameter));
        }
    });
    return Array.from(set).sort((a, b) => Number(a) - Number(b));
};

const getStandardKflexProductName = (
    productLine: 'st' | 'k_protect' | 'm_flex',
    formType: 'tube' | 'sheet',
    thickness: string,
    innerDiameter: string,
    matched?: KflexPriceRecord,
): string => {
    if (matched?.product_name) {
        return matched.product_name;
    }

    const lineLabel =
        props.kflexProductLines[productLine] ||
        (productLine === 'k_protect' ? 'K-Protect' : productLine === 'm_flex' ? 'M-Flex' : 'ST');
    const cleanThickness = String(thickness || '')
        .trim()
        .replace(/\s*mm$/i, '');
    const cleanDiameter = String(innerDiameter || '')
        .trim()
        .replace(/\s*mm$/i, '');

    if (formType === 'sheet') {
        return `Tấm bảo ôn K-Flex ${lineLabel} dày ${cleanThickness}mm`;
    }

    return `Ống bảo ôn K-Flex ${lineLabel} kích thước ${cleanThickness}x${cleanDiameter}`;
};

const getDefaultTubeLength = (productLine: 'st' | 'k_protect' | 'm_flex'): string => {
    return productLine === 'm_flex' ? '1.83' : '2';
};

const createDefaultKflexRow = (
    productLine: 'st' | 'k_protect' | 'm_flex' = 'st',
    formType: 'tube' | 'sheet' = 'tube',
): KflexQuotationItem => {
    const variantType = formType === 'tube' ? 'alu' : 'alu_or_glue';
    const thickness = formType === 'tube' ? '32' : '25';
    const innerDiameter = formType === 'tube' ? '22' : '';
    const found = findMatchingKflexPrice(productLine, formType, variantType, thickness, innerDiameter);
    const dims =
        formType === 'sheet'
            ? getDefaultSheetDimensions(productLine, variantType, thickness)
            : { widthM: '', lengthM: getDefaultTubeLength(productLine) };

    return {
        uid: nextUid(),
        kflexPriceId: found?.id ?? null,
        itemName: getStandardKflexProductName(
            productLine,
            formType,
            thickness,
            innerDiameter,
            found,
        ),
        brandLabel: getDefaultBrandLabel(productLine),
        originLabel: getDefaultOriginLabel(productLine, found?.brand, found?.origin),
        productTypeLabel: getDefaultProductTypeLabel(formType, variantType),
        productLine,
        formType,
        variantType,
        thickness,
        innerDiameter,
        widthM: dims.widthM,
        lengthM: dims.lengthM,
        qtyM: formType === 'tube' ? 9 : null,
        qtyM2: formType === 'sheet' ? 10 : null,
        unitPrice: found?.price ?? 165127,
        note: '',
    };
};

const kflexGroups = ref<KflexQuotationGroup[]>([
    {
        uid: nextUid(),
        hasTitle: true,
        title: 'BẢO ÔN CHO ĐƯỜNG THÉP',
        items: [createDefaultKflexRow('st', 'tube')],
    },
]);

const kflexTotalItemsCount = computed(() =>
    kflexGroups.value.reduce((acc, g) => acc + g.items.length, 0),
);

const resolveDefaultProductLineForNewRow = (isDuct = false): KflexProductLineKey => {
    const allowed = kflexSelectedProductLines.value;
    if (isDuct && allowed.includes('k_protect')) {
        return 'k_protect';
    }
    return allowed[0] || 'st';
};

const selectSingleKflexProductLine = (line: KflexProductLineKey): void => {
    kflexSelectedProductLines.value = [line];
    kflexGroups.value.forEach((group) => {
        group.items.forEach((item) => {
            item.productLine = line;
            onKflexLineOrFormChange(item);
        });
    });
};

const toggleKflexProductLine = (line: KflexProductLineKey): void => {
    const current = [...kflexSelectedProductLines.value];
    const idx = current.indexOf(line);
    if (idx >= 0) {
        if (current.length <= 1) {
            return;
        }
        current.splice(idx, 1);
    } else {
        current.push(line);
    }

    kflexSelectedProductLines.value = current;
    const fallbackLine = current[0] || 'st';

    kflexGroups.value.forEach((group) => {
        group.items.forEach((item) => {
            if (!current.includes(item.productLine) || current.length === 1) {
                item.productLine = fallbackLine;
                onKflexLineOrFormChange(item);
            }
        });
    });
};

const addKflexGroup = (presetTitle?: string): void => {
    const hasTitle = presetTitle !== '';
    const title = presetTitle !== undefined ? presetTitle : '';
    const isDuct = title.toUpperCase().includes('ỐNG GIÓ');
    const defaultLine = resolveDefaultProductLineForNewRow(isDuct);

    kflexGroups.value.push({
        uid: nextUid(),
        hasTitle,
        title,
        items: [
            createDefaultKflexRow(
                defaultLine,
                isDuct ? 'sheet' : 'tube',
            ),
        ],
    });
};

const removeKflexGroup = (groupIdx: number): void => {
    if (kflexGroups.value.length <= 1) {
        return;
    }
    kflexGroups.value.splice(groupIdx, 1);
};

const addKflexItemToGroup = (group: KflexQuotationGroup): void => {
    const lastItem = group.items[group.items.length - 1];
    const allowed = kflexSelectedProductLines.value;
    const targetLine =
        lastItem && allowed.includes(lastItem.productLine)
            ? lastItem.productLine
            : resolveDefaultProductLineForNewRow(
                  (group.title || '').toUpperCase().includes('ỐNG GIÓ'),
              );
    const targetForm = lastItem?.formType ?? 'tube';
    const newRow = createDefaultKflexRow(targetLine, targetForm);

    if (lastItem) {
        newRow.variantType = lastItem.variantType;
        newRow.thickness = lastItem.thickness;
        if (targetForm === 'tube') {
            const availD = getAvailableDiametersForRow(newRow);
            const curIdx = availD.indexOf(normalizeNumberStr(lastItem.innerDiameter));
            if (curIdx >= 0 && curIdx + 1 < availD.length) {
                newRow.innerDiameter = availD[curIdx + 1];
            }
        }
        syncKflexRowFromDatabase(newRow);
    }

    group.items.push(newRow);
};

const duplicateKflexItemInGroup = (group: KflexQuotationGroup, itemIdx: number): void => {
    const src = group.items[itemIdx];
    if (!src) {
        return;
    }
    group.items.splice(itemIdx + 1, 0, {
        ...src,
        uid: nextUid(),
    });
};

const removeKflexItemFromGroup = (group: KflexQuotationGroup, itemIdx: number): void => {
    if (group.items.length <= 1 && kflexGroups.value.length <= 1) {
        return;
    }
    if (group.items.length <= 1) {
        const gIdx = kflexGroups.value.findIndex((g) => g.uid === group.uid);
        if (gIdx >= 0 && kflexGroups.value.length > 1) {
            kflexGroups.value.splice(gIdx, 1);
        }
        return;
    }
    group.items.splice(itemIdx, 1);
};

const onKflexLineOrFormChange = (item: KflexQuotationItem): void => {
    if (item.formType === 'tube' && !['foam', 'alu'].includes(item.variantType)) {
        item.variantType = 'alu';
    } else if (item.formType === 'sheet' && !['foam', 'alu_or_glue', 'alu_and_glue'].includes(item.variantType)) {
        item.variantType = 'alu_or_glue';
    }

    const availT = getAvailableThicknessesForRow(item);
    if (availT.length > 0 && !availT.includes(normalizeNumberStr(item.thickness))) {
        item.thickness = availT[0];
    }

    if (item.formType === 'tube') {
        const availD = getAvailableDiametersForRow(item);
        if (availD.length > 0 && !availD.includes(normalizeNumberStr(item.innerDiameter))) {
            item.innerDiameter = availD[0];
        }
    } else {
        item.innerDiameter = '';
    }

    syncKflexRowFromDatabase(item);
};

const syncKflexRowFromDatabase = (item: KflexQuotationItem): void => {
    if (item.formType === 'tube') {
        const availD = getAvailableDiametersForRow(item);
        if (availD.length > 0 && !availD.includes(normalizeNumberStr(item.innerDiameter))) {
            item.innerDiameter = availD[0];
        }
    }

    const found = findMatchingKflexPrice(
        item.productLine,
        item.formType,
        item.variantType,
        item.thickness,
        item.innerDiameter,
    );

    item.itemName = getStandardKflexProductName(
        item.productLine,
        item.formType,
        item.thickness,
        item.innerDiameter,
        found,
    );
    item.brandLabel = getDefaultBrandLabel(item.productLine);
    item.originLabel = getDefaultOriginLabel(item.productLine, found?.brand, found?.origin);
    item.productTypeLabel = getDefaultProductTypeLabel(item.formType, item.variantType);

    if (item.formType === 'sheet') {
        const dims = getDefaultSheetDimensions(item.productLine, item.variantType, item.thickness);
        item.innerDiameter = '';
        item.widthM = dims.widthM;
        item.lengthM = dims.lengthM;
        if (item.qtyM2 === null && item.qtyM !== null) {
            item.qtyM2 = item.qtyM;
            item.qtyM = null;
        }
    } else {
        item.widthM = '';
        item.lengthM = getDefaultTubeLength(item.productLine);
        if (item.qtyM === null && item.qtyM2 !== null) {
            item.qtyM = item.qtyM2;
            item.qtyM2 = null;
        }
    }

    if (found) {
        item.kflexPriceId = found.id;
        if (found.price > 0) {
            item.unitPrice = found.price;
        }
    }
};

const formatKflexNum = (amount: number | null | undefined): string => {
    if (amount === null || amount === undefined || Number.isNaN(Number(amount))) {
        return '';
    }
    const safe = Math.round(Number(amount));
    if (safe === 0) {
        return '';
    }
    return new Intl.NumberFormat('en-US').format(safe);
};

const formatKflexTotalNum = (amount: number): string => {
    const safe = Number.isFinite(amount) ? Math.round(amount) : 0;
    return new Intl.NumberFormat('en-US').format(safe);
};

const kflexRowQuantity = (item: KflexQuotationItem): number => {
    if (item.formType === 'sheet') {
        return Number(item.qtyM2) || Number(item.qtyM) || 0;
    }
    return Number(item.qtyM) || Number(item.qtyM2) || 0;
};

const kflexRowAmount = (item: KflexQuotationItem): number =>
    Math.round(kflexRowQuantity(item) * (Number(item.unitPrice) || 0));

const kflexSubTotal = computed(() =>
    kflexGroups.value.reduce(
        (groupSum, group) =>
            groupSum + group.items.reduce((itemSum, item) => itemSum + kflexRowAmount(item), 0),
        0,
    ),
);

const kflexVatAmount = computed(() =>
    Math.round((kflexSubTotal.value * (Number(kflexQuoteForm.value.vatPercent) || 0)) / 100),
);

const kflexGrandTotal = computed(() => kflexSubTotal.value + kflexVatAmount.value);

const kflexFormattedQuoteDate = computed(() =>
    formatDisplayDateSlash(kflexQuoteForm.value.quoteDate, false),
);

const kflexFormattedValidUntil = computed(() =>
    formatDisplayDateSlash(kflexQuoteForm.value.validUntilDate, true),
);

const kflexFormattedVatLabel = computed(() => {
    const v = Number(kflexQuoteForm.value.vatPercent) || 0;
    return v < 10 ? `0${v}%` : `${v}%`;
});

type KflexRenderRow =
    | {
          rowType: 'section';
          key: string;
          sectionTitle: string;
      }
    | {
          rowType: 'item';
          key: string;
          tt: number;
          item: KflexQuotationItem;
          noteRowspan: number;
      };

const kflexRenderRows = computed<KflexRenderRow[]>(() => {
    const rows: KflexRenderRow[] = [];
    let globalTt = 0;

    kflexGroups.value.forEach((group) => {
        const cleanTitle = (group.title || '').trim();
        if (group.hasTitle && cleanTitle) {
            rows.push({
                rowType: 'section',
                key: `sec-${group.uid}`,
                sectionTitle: cleanTitle,
            });
        }

        const list = group.items;
        const noteSpans = new Array<number>(list.length).fill(1);
        let i = 0;
        while (i < list.length) {
            const noteText = (list[i].note || '').trim();
            if (!noteText) {
                noteSpans[i] = 1;
                i++;
                continue;
            }
            let j = i + 1;
            while (j < list.length && (list[j].note || '').trim() === noteText) {
                noteSpans[j] = 0;
                j++;
            }
            noteSpans[i] = j - i;
            i = j;
        }

        list.forEach((item, idx) => {
            globalTt += 1;
            rows.push({
                rowType: 'item',
                key: item.uid,
                tt: globalTt,
                item,
                noteRowspan: noteSpans[idx],
            });
        });
    });

    return rows;
});

const printKflexQuotationPdf = (): void => {
    const printWindow = window.open('', '_blank', 'width=980,height=1180');
    if (!printWindow) {
        notifyToast('Vui lòng cho phép mở cửa sổ bật lên (Pop-up) để xuất báo giá PDF.');
        return;
    }

    const bodyRowsHtml = kflexRenderRows.value
        .map((row) => {
            if (row.rowType === 'section') {
                return `
                    <tr class="kf-sec-row">
                        <td></td>
                        <td colspan="13" class="c-left c-bold">${escapeHtml(row.sectionTitle)}</td>
                    </tr>
                `;
            }

            const it = row.item;
            const displayName =
                it.itemName.trim() ||
                `${it.brandLabel} ${it.thickness}${it.innerDiameter ? 'x' + it.innerDiameter : 'mm'}`;
            const noteCellHtml =
                row.noteRowspan > 0
                    ? `<td class="c-center c-note"${row.noteRowspan > 1 ? ` rowspan="${row.noteRowspan}"` : ''}>${nl2br(it.note)}</td>`
                    : '';

            return `
                <tr>
                    <td class="c-center c-tt">${row.tt}</td>
                    <td class="c-left c-name">${nl2br(displayName)}</td>
                    <td class="c-center">${escapeHtml(it.brandLabel)}</td>
                    <td class="c-center">${escapeHtml(it.originLabel)}</td>
                    <td class="c-center">${nl2br(it.productTypeLabel)}</td>
                    <td class="c-center">${escapeHtml(it.thickness)}</td>
                    <td class="c-center">${escapeHtml(it.innerDiameter)}</td>
                    <td class="c-center">${escapeHtml(it.widthM)}</td>
                    <td class="c-center">${escapeHtml(it.lengthM)}</td>
                    <td class="c-center c-nowrap">${formatKflexNum(it.qtyM)}</td>
                    <td class="c-center c-nowrap">${formatKflexNum(it.qtyM2)}</td>
                    <td class="c-center c-uprice c-nowrap">${formatKflexTotalNum(it.unitPrice)}</td>
                    <td class="c-right c-bold c-nowrap">${formatKflexTotalNum(kflexRowAmount(it))}</td>
                    ${noteCellHtml}
                </tr>
            `;
        })
        .join('');

    const html = `<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>${escapeHtml(kflexQuoteForm.value.documentTitle)} - ${escapeHtml(kflexQuoteForm.value.projectName)}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            width: 210mm;
            margin: 0 auto;
            padding: 0;
            font-family: "Times New Roman", Times, serif;
            color: #000000;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .q-page-frame {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 16mm 18mm;
            page-break-after: avoid;
            break-after: avoid;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .q-scale-box {
            width: 100%;
        }
        .q-sheet {
            --s: 1;
            width: 100%;
            font-size: calc(8.5pt * var(--s));
            line-height: 1.22;
        }
        .kf-header {
            display: grid;
            grid-template-columns: 38mm 1fr 38mm;
            align-items: center;
            gap: 6px;
            margin-bottom: calc(3px * var(--s));
        }
        .kf-logo-left {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding-left: 6px;
        }
        .kf-logo-left img {
            width: calc(86px * var(--s));
            height: auto;
            object-fit: contain;
            display: block;
        }
        .kf-company-center {
            text-align: center;
            line-height: 1.22;
        }
        .kf-company-center h1 {
            margin: 0 0 1px;
            font-size: calc(11pt * var(--s));
            font-weight: 700;
            text-transform: uppercase;
        }
        .kf-company-center p {
            margin: 0;
            font-size: calc(9.5pt * var(--s));
        }
        .kf-logo-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 14px;
        }
        .kf-logo-right img {
            width: calc(112px * var(--s));
            height: auto;
            object-fit: contain;
            display: block;
        }
        .kf-title {
            text-align: center;
            font-size: calc(12pt * var(--s));
            font-weight: 700;
            text-transform: uppercase;
            margin: calc(4px * var(--s)) 0 calc(5px * var(--s));
        }
        .kf-meta {
            width: 100%;
            margin-bottom: calc(4px * var(--s));
            font-size: calc(8.8pt * var(--s));
        }
        .kf-meta-row {
            display: grid;
            grid-template-columns: 8.2% 1fr;
            column-gap: 4px;
            margin-bottom: calc(2px * var(--s));
            align-items: baseline;
        }
        .kf-meta-row > span {
            min-width: 0;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .kf-meta-row > span:first-child {
            white-space: nowrap;
        }
        .kf-meta-kinhgui-label {
            font-weight: 700;
            font-style: italic;
            text-decoration: underline;
            font-size: calc(8.2pt * var(--s));
        }
        .kf-meta-kinhgui-val {
            font-weight: 700;
            font-style: italic;
        }
        .kf-meta-label-sub {
            font-style: italic;
            font-size: calc(7.2pt * var(--s));
            line-height: 1.15;
        }
        .kf-meta-italic {
            font-style: italic;
        }
        .kf-intro {
            text-align: center;
            font-weight: 700;
            font-style: italic;
            color: #0070c0;
            font-size: calc(9.2pt * var(--s));
            margin: calc(3px * var(--s)) 0 calc(4px * var(--s));
        }
        table.kf-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: calc(7.4pt * var(--s));
        }
        table.kf-table th,
        table.kf-table td {
            border: 1px solid #000000;
            padding: calc(2.4px * var(--s)) calc(3px * var(--s));
            vertical-align: middle;
            line-height: 1.18;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        table.kf-table thead th {
            background: #ffff00;
            text-align: center;
            font-weight: 700;
            font-size: calc(7pt * var(--s));
            line-height: 1.14;
            padding: calc(3px * var(--s)) calc(2px * var(--s));
        }
        .kf-sec-row td {
            background: #c5d9f1;
            font-weight: 700;
            text-transform: uppercase;
            padding: calc(2.6px * var(--s)) calc(4px * var(--s));
        }
        .c-center { text-align: center; }
        .c-left { text-align: left; }
        .c-right { text-align: right; }
        .c-bold { font-weight: 700; }
        .c-nowrap {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }
        .c-tt { font-size: calc(6.8pt * var(--s)); }
        .c-name { line-height: 1.18; }
        .c-uprice {
            font-size: calc(7pt * var(--s));
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }
        .c-note {
            font-size: calc(6.8pt * var(--s));
            line-height: 1.16;
        }
        .kf-summary-row td {
            font-weight: 700;
            padding: calc(3px * var(--s)) calc(4px * var(--s));
            font-size: calc(7.6pt * var(--s));
        }
        .kf-summary-label {
            text-align: center;
        }
        .kf-summary-val {
            text-align: right;
            color: #c00000;
            font-weight: 700;
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }
        table.kf-table td.no-border {
            border: 0 !important;
            background: transparent !important;
        }
        .kf-terms {
            margin-top: calc(4px * var(--s));
            font-family: Arial, Helvetica, sans-serif;
            font-size: calc(7.2pt * var(--s));
            line-height: 1.25;
        }
        .kf-terms-head {
            font-family: "Times New Roman", Times, serif;
            font-size: calc(8pt * var(--s));
            font-weight: 700;
            text-decoration: underline;
            font-style: italic;
            margin-bottom: 1px;
        }
        .kf-terms p {
            margin: 1px 0;
        }
        .kf-sign {
            margin-top: calc(3px * var(--s));
            font-family: "Times New Roman", Times, serif;
            font-size: calc(8.2pt * var(--s));
            line-height: 1.28;
            font-style: italic;
        }
        .kf-sign-grid {
            display: grid;
            grid-template-columns: 6.0% 1fr;
            column-gap: 3px;
            align-items: baseline;
        }
        .kf-sign-grid > span {
            min-width: 0;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .kf-sign-label {
            text-align: center;
        }
        .kf-sign-name {
            grid-column: 1 / -1;
            padding-left: 6px;
            color: #800000;
        }
        .kf-sign-hp {
            text-decoration: underline;
        }
        .kf-sign-email {
            color: #0070c0;
        }
    </style>
</head>
<body>
    <div class="q-page-frame" id="qPageFrame">
        <div class="q-scale-box" id="qScaleBox">
            <div class="q-sheet" id="qSheet">
                <header class="kf-header">
                    <div class="kf-logo-left">
                        <img src="${escapeHtml(starLightLogoUrl.value)}" alt="STAR LIGHT" />
                    </div>
                    <div class="kf-company-center">
                        <h1>CÔNG TY CỔ PHẦN QUỐC TẾ STAR LIGHT</h1>
                        <p>Địa chỉ VP: Số 15 Nguyễn Cảnh Dị, Định Công, Hà Nội</p>
                        <p>Điện thoại: 0243 201 8322 - Hotline: 0979 541 345</p>
                        <p>www: starlightgroup.vn</p>
                    </div>
                    <div class="kf-logo-right">
                        <img src="${escapeHtml(kflexLogoUrl.value)}" alt="K-FLEX" />
                    </div>
                </header>

                <div class="kf-title">${escapeHtml(kflexQuoteForm.value.documentTitle)}</div>

                <div class="kf-meta">
                    <div class="kf-meta-row">
                        <span class="kf-meta-kinhgui-label">Kính gửi:</span>
                        <span class="kf-meta-kinhgui-val">${escapeHtml(kflexQuoteForm.value.recipientCompany)}</span>
                    </div>
                    <div class="kf-meta-row">
                        <span class="kf-meta-label-sub">Người nhận:</span>
                        <span class="kf-meta-italic">${escapeHtml(kflexQuoteForm.value.recipientPerson)}</span>
                    </div>
                    <div class="kf-meta-row">
                        <span class="kf-meta-label-sub">Project:</span>
                        <span class="kf-meta-italic">${escapeHtml(kflexQuoteForm.value.projectName)}</span>
                    </div>
                    <div class="kf-meta-row">
                        <span class="kf-meta-label-sub">Thời gian:</span>
                        <span class="kf-meta-italic">${escapeHtml(kflexFormattedQuoteDate.value)}</span>
                    </div>
                </div>

                <div class="kf-intro">${escapeHtml(kflexQuoteForm.value.introText)}</div>

                <table class="kf-table">
                    <colgroup>
                        <col style="width: 3.8%" />
                        <col style="width: 16.0%" />
                        <col style="width: 6.8%" />
                        <col style="width: 8.4%" />
                        <col style="width: 7.0%" />
                        <col style="width: 4.2%" />
                        <col style="width: 4.0%" />
                        <col style="width: 4.2%" />
                        <col style="width: 4.2%" />
                        <col style="width: 4.8%" />
                        <col style="width: 4.8%" />
                        <col style="width: 9.2%" />
                        <col style="width: 11.2%" />
                        <col style="width: 11.4%" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowspan="2">TT</th>
                            <th rowspan="2">Tên vật tư</th>
                            <th rowspan="2">Nhãn hiệu</th>
                            <th rowspan="2">Thương hiệu - Xuất xứ</th>
                            <th rowspan="2">Loại sản phẩm</th>
                            <th colspan="4">Kích thước</th>
                            <th colspan="2">Khối lượng</th>
                            <th rowspan="2">Đơn giá<br>Unit Price</th>
                            <th rowspan="2">Tổng<br>Total</th>
                            <th rowspan="2">Ghi chú</th>
                        </tr>
                        <tr>
                            <th>Chiều dày<br>(mm)</th>
                            <th>ID (mm)</th>
                            <th>Chiều rộng<br>(m)</th>
                            <th>Chiều dài<br>(m)</th>
                            <th>m</th>
                            <th>m2</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${bodyRowsHtml}
                        <tr class="kf-summary-row">
                            <td colspan="12" class="kf-summary-label">
                                TỔNG CỘNG (chưa bao gồm thuế VAT) - Sub Total (Not included VAT)
                            </td>
                            <td class="kf-summary-val">${formatKflexTotalNum(kflexSubTotal.value)}</td>
                            <td class="no-border"></td>
                        </tr>
                        <tr class="kf-summary-row">
                            <td colspan="12" class="kf-summary-label">
                                Thuế VAT (${kflexFormattedVatLabel.value})
                            </td>
                            <td class="kf-summary-val">${formatKflexTotalNum(kflexVatAmount.value)}</td>
                            <td class="no-border"></td>
                        </tr>
                        <tr class="kf-summary-row">
                            <td colspan="12" class="kf-summary-label">
                                TỔNG CỘNG (bao gồm thuế VAT) - Sub Total (included VAT)
                            </td>
                            <td class="kf-summary-val">${formatKflexTotalNum(kflexGrandTotal.value)}</td>
                            <td class="no-border"></td>
                        </tr>
                    </tbody>
                </table>

                <div class="kf-terms">
                    <div class="kf-terms-head">Điều kiện thương mại:</div>
                    <p>1. Báo giá có hiệu lực đến ngày: ${escapeHtml(kflexFormattedValidUntil.value)}</p>
                    <p>2. Phương thức thanh toán: ${escapeHtml(kflexQuoteForm.value.paymentTerms)}</p>
                    <p>3. Phương thức giao hàng: ${escapeHtml(kflexQuoteForm.value.deliveryMethod)}</p>
                    <p>4. Thời gian giao hàng: ${escapeHtml(kflexQuoteForm.value.deliveryTime)}</p>
                    <p>5. Chúng tôi hy vọng báo giá này sẽ làm hài lòng Quý Công ty. Mọi thắc mắc xin vui lòng liên lạc với chúng tôi</p>
                    <p>Rất mong nhận được sự ủng hộ từ Quý Công ty.</p>
                </div>

                <div class="kf-sign">
                    <div class="kf-sign-grid">
                        <span></span>
                        <span class="kf-sign-thanks">Many thanks &amp; Best regards</span>
                        <span class="kf-sign-name">${escapeHtml(kflexQuoteForm.value.contactName)}</span>
                        <span class="kf-sign-label">HP</span>
                        <span class="kf-sign-hp">${escapeHtml(kflexQuoteForm.value.contactHp)}</span>
                        <span class="kf-sign-label">Email :</span>
                        <span class="kf-sign-email">${escapeHtml(kflexQuoteForm.value.contactEmail)}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var imgs = Array.prototype.slice.call(document.images);
            var pending = imgs.filter(function (img) { return !img.complete; });

            function fitSinglePageAndPrint() {
                var frame = document.getElementById('qPageFrame');
                var sheet = document.getElementById('qSheet');
                if (frame && sheet) {
                    var cs = window.getComputedStyle(frame);
                    var padY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
                    var targetPagePx = (297 / 25.4) * 96;
                    var availH = targetPagePx - padY - 4;
                    var rawH = sheet.scrollHeight;

                    if (rawH > availH && availH > 0) {
                        var low = 0.48;
                        var high = 1.0;
                        var best = 0.48;
                        for (var step = 0; step < 12; step++) {
                            var mid = (low + high) / 2;
                            sheet.style.setProperty('--s', String(mid));
                            if (sheet.scrollHeight <= availH) {
                                best = mid;
                                low = mid;
                            } else {
                                high = mid;
                            }
                        }
                        sheet.style.setProperty('--s', String(best));
                    }
                }

                var closePrintWindow = function () {
                    try {
                        if (window.opener && !window.opener.closed) {
                            window.opener.dispatchEvent(
                                new CustomEvent('kbuser-toast', {
                                    detail: { message: 'Đã xuất báo giá bảo ôn K-Flex PDF thành công.' }
                                })
                            );
                        }
                    } catch (e) {}
                    setTimeout(function () {
                        window.close();
                    }, 100);
                };

                window.addEventListener('afterprint', closePrintWindow);
                window.onafterprint = closePrintWindow;
                if (window.matchMedia) {
                    var mediaQueryList = window.matchMedia('print');
                    if (mediaQueryList.addEventListener) {
                        mediaQueryList.addEventListener('change', function (mql) {
                            if (!mql.matches) {
                                closePrintWindow();
                            }
                        });
                    }
                }

                setTimeout(function () {
                    window.focus();
                    window.print();
                }, 150);
            }

            if (pending.length === 0) {
                fitSinglePageAndPrint();
            } else {
                var loaded = 0;
                pending.forEach(function (img) {
                    var done = function () {
                        loaded++;
                        if (loaded >= pending.length) {
                            fitSinglePageAndPrint();
                        }
                    };
                    img.addEventListener('load', done);
                    img.addEventListener('error', done);
                });
            }
        })();
    <\/script>
</body>
</html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    let notified = false;
    const notifyCompleted = (): void => {
        if (notified) {
            return;
        }
        notified = true;
        notifyToast('Đã xuất báo giá bảo ôn K-Flex PDF thành công.');
    };
    const watchClosedTimer = window.setInterval(() => {
        if (printWindow.closed) {
            window.clearInterval(watchClosedTimer);
            notifyCompleted();
        }
    }, 200);
};

const printQuotationPdf = (): void => {
    const portrait = isPortraitMode.value;
    const pageOrientation = portrait ? 'portrait' : 'landscape';
    const pageW = portrait ? '210mm' : '297mm';
    const pageH = portrait ? '297mm' : '210mm';
    const framePad = portrait ? '15mm 16mm' : '12mm 16mm';
    const targetPageHeightMm = portrait ? 297 : 210;
    const winFeatures = portrait ? 'width=920,height=1160' : 'width=1240,height=860';

    const printWindow = window.open('', '_blank', winFeatures);
    if (!printWindow) {
        notifyToast('Vui lòng cho phép mở cửa sổ bật lên (Pop-up) để xuất báo giá PDF.');
        return;
    }

    const rowsHtml = items.value
        .map((item) => {
            const imgCell = item.imageUrl
                ? `<img src="${escapeHtml(item.imageUrl)}" alt="${escapeHtml(item.modelCode || item.productLabel)}" class="q-prod-img" />`
                : '';

            const specHtml = formatSpecLines(item.specifications)
                .map((line) => `<div class="c-spec-line">${escapeHtml(line)}</div>`)
                .join('');

            return `
                <tr>
                    <td class="c-center c-bold">${nl2br(item.productLabel)}</td>
                    <td class="c-center c-bold">${nl2br(item.modelCode)}</td>
                    <td class="c-center c-bold">${nl2br(item.origin)}</td>
                    <td class="c-center c-img">${imgCell}</td>
                    <td class="c-center c-bold">${escapeHtml(item.unit)}</td>
                    <td class="c-spec">${specHtml}</td>
                    <td class="c-center c-bold c-nowrap">${formatVnd(item.unitPrice)}</td>
                    <td class="c-center c-bold c-nowrap">${formatVnd(item.quantity)}</td>
                    <td class="c-center c-bold c-nowrap">${formatVnd(rowAmount(item))}</td>
                </tr>
            `;
        })
        .join('');

    const html = `<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>${escapeHtml(quoteForm.value.documentTitle)} - ${escapeHtml(quoteForm.value.projectName)}</title>
    <style>
        @page {
            size: A4 ${pageOrientation};
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            width: ${pageW};
            margin: 0 auto;
            padding: 0;
            font-family: "Times New Roman", Times, serif;
            color: #000000;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .q-page-frame {
            width: ${pageW};
            min-height: ${pageH};
            margin: 0 auto;
            padding: ${framePad};
            page-break-after: avoid;
            break-after: avoid;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .q-scale-box {
            width: 100%;
        }
        .q-sheet {
            --s: 1;
            width: 100%;
            font-size: calc(10.5pt * var(--s));
            line-height: 1.26;
        }
        .q-header {
            display: grid;
            grid-template-columns: 44mm 1fr 44mm;
            align-items: center;
            gap: 10px;
            margin-bottom: calc(4px * var(--s));
        }
        .q-logo-left {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding-left: 6px;
        }
        .q-logo-left img {
            width: calc(108px * var(--s));
            height: auto;
            object-fit: contain;
            display: block;
        }
        .q-company-center {
            text-align: center;
            line-height: 1.24;
        }
        .q-company-center h1 {
            margin: 0 0 2px;
            font-size: calc(13.5pt * var(--s));
            font-weight: 700;
            text-transform: uppercase;
        }
        .q-company-center p {
            margin: 0;
            font-size: calc(11.5pt * var(--s));
        }
        .q-logo-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 6px;
        }
        .q-logo-right img {
            width: calc(168px * var(--s));
            height: auto;
            object-fit: contain;
            display: block;
        }
        .q-title {
            text-align: center;
            font-size: calc(14.5pt * var(--s));
            font-weight: 700;
            text-decoration: underline;
            text-transform: uppercase;
            margin: calc(5px * var(--s)) 0 calc(6px * var(--s));
        }
        .q-meta {
            width: 100%;
            margin-bottom: calc(3px * var(--s));
            font-size: calc(10.5pt * var(--s));
        }
        .q-meta-row {
            display: grid;
            grid-template-columns: 9.5% 1fr;
            column-gap: 4px;
            margin-bottom: calc(2px * var(--s));
            align-items: baseline;
        }
        .q-meta-row > span {
            min-width: 0;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .q-meta-label-kinhgui {
            font-weight: 700;
            font-style: italic;
            text-decoration: underline;
        }
        .q-meta-val-kinhgui {
            text-transform: uppercase;
        }
        .q-meta-label-italic {
            font-style: italic;
            font-size: calc(9.2pt * var(--s));
            line-height: 1.18;
        }
        .q-meta-label-project {
            font-weight: 700;
            font-style: italic;
        }
        .q-meta-val-italic {
            font-style: italic;
        }
        .q-intro {
            text-align: center;
            font-style: italic;
            font-size: calc(10.2pt * var(--s));
            margin: calc(3px * var(--s)) 0 calc(4px * var(--s));
        }
        table.q-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: calc(10pt * var(--s));
        }
        table.q-table th,
        table.q-table td {
            border: 1px solid #000000;
            padding: calc(4px * var(--s)) calc(5px * var(--s));
            vertical-align: middle;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        table.q-table thead th {
            text-align: center;
            font-weight: 700;
            font-size: calc(9.5pt * var(--s));
            line-height: 1.2;
            padding: calc(5px * var(--s)) calc(3px * var(--s));
        }
        table.q-table thead th em {
            display: block;
            font-style: italic;
            font-weight: 400;
        }
        .col-prod { width: 9.0%; }
        .col-model { width: 8.5%; }
        .col-origin { width: 5.5%; }
        .col-img { width: 17.5%; }
        .col-unit { width: 4.5%; }
        .col-spec { width: 26.5%; }
        .col-price { width: 10.5%; }
        .col-qty { width: 6.0%; }
        .col-amount { width: 12.0%; }
        .c-center {
            text-align: center;
        }
        .c-bold {
            font-weight: 700;
        }
        .c-nowrap {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }
        .c-spec {
            text-align: left;
            vertical-align: middle;
            line-height: 1.26;
            font-size: calc(10.2pt * var(--s));
            padding: calc(5px * var(--s)) calc(7px * var(--s));
        }
        .c-img {
            padding: calc(4px * var(--s));
        }
        .q-prod-img {
            width: 98%;
            max-width: 100%;
            height: calc(170px * var(--s));
            max-height: calc(180px * var(--s));
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .q-summary-row td {
            font-weight: 700;
            padding: calc(3.5px * var(--s)) calc(5px * var(--s));
            font-size: calc(9.8pt * var(--s));
        }
        .q-summary-label {
            text-align: center;
        }
        .q-summary-label em {
            font-style: italic;
            font-weight: 700;
        }
        .q-terms {
            margin-top: calc(3px * var(--s));
            font-size: calc(9pt * var(--s));
            line-height: 1.25;
            font-weight: 700;
        }
        .q-terms-head {
            text-decoration: underline;
            margin-bottom: 1px;
        }
        .q-terms p {
            margin: 1px 0;
        }
        .q-sign {
            margin-top: calc(6px * var(--s));
            font-size: calc(9.2pt * var(--s));
            line-height: 1.25;
            font-weight: 700;
            font-style: italic;
        }
        .q-sign p {
            margin: 1px 0;
        }
    </style>
</head>
<body>
    <div class="q-page-frame" id="qPageFrame">
        <div class="q-scale-box" id="qScaleBox">
            <div class="q-sheet" id="qSheet">
        <header class="q-header">
            <div class="q-logo-left"><img src="${escapeHtml(starLightLogoUrl.value)}" alt="STAR LIGHT" /></div>
            <div class="q-company-center">
                <h1>CÔNG TY CỔ PHẦN QUỐC TẾ STAR LIGHT</h1>
                <p>Địa chỉ VP: Số 15 Nguyễn Cảnh Dị, Định Công, TP Hà Nội</p>
                <p>Điện thoại: 0243 201 8322 - Hotline: 0979 541 345</p>
                <p>www: starlightgroup.vn</p>
            </div>
            <div class="q-logo-right"><img src="${escapeHtml(aristonLogoUrl.value)}" alt="ARISTON" /></div>
        </header>

        <div class="q-title">${escapeHtml(quoteForm.value.documentTitle)}</div>

        <div class="q-meta">
            <div class="q-meta-row">
                <span class="q-meta-label-kinhgui">Kính gửi:</span>
                <span class="q-meta-val-kinhgui">${escapeHtml(quoteForm.value.recipientCompany)}</span>
            </div>
            <div class="q-meta-row">
                <span class="q-meta-label-italic">Người nhận :</span>
                <span class="q-meta-val-italic">${escapeHtml(quoteForm.value.recipientPerson)}</span>
            </div>
            ${
                quoteForm.value.contactPhone.trim()
                    ? `<div class="q-meta-row">
                        <span class="q-meta-label-italic">Thông tin liên lạc:</span>
                        <span class="q-meta-val-italic">${escapeHtml(quoteForm.value.contactPhone)}</span>
                    </div>`
                    : ''
            }
            <div class="q-meta-row">
                <span class="q-meta-label-italic">Thời gian :</span>
                <span class="q-meta-val-italic">${escapeHtml(formattedQuoteDate.value)}</span>
            </div>
            <div class="q-meta-row">
                <span class="q-meta-label-project">Project:</span>
                <span class="q-meta-val-italic">${escapeHtml(quoteForm.value.projectName)}</span>
            </div>
        </div>

        <div class="q-intro">
            Công ty Star Light là đơn vị phát triển mảng dự án sản phẩm Ariston, rất hân hạnh gửi bảng báo giá cho Quý Khách hàng. Cụ thể như sau
        </div>

        <table class="q-table">
            <thead>
                <tr>
                    <th class="col-prod">Sản Phẩm<em>Product</em></th>
                    <th class="col-model">Mã sản phẩm<em>Model</em></th>
                    <th class="col-origin">Xuất xứ<em>Origin</em></th>
                    <th class="col-img">Hình ảnh sản phẩm<em>Image</em></th>
                    <th class="col-unit">Đơn vị</th>
                    <th class="col-spec">Đặc tính kỹ thuật<em>Specifications</em></th>
                    <th class="col-price">Giá bán dự án<em>Selling Price</em><em>(VNĐ)</em></th>
                    <th class="col-qty">Số lượng Quantity<br>(chiếc)</th>
                    <th class="col-amount">Thành tiền<em>Amount</em><em>(VNĐ)</em></th>
                </tr>
            </thead>
            <tbody>
                ${rowsHtml}
                <tr class="q-summary-row">
                    <td colspan="7" class="q-summary-label">
                        TỔNG CỘNG (chưa bao gồm thuế VAT) - <em>Sub Total (not included VAT)</em>
                    </td>
                    <td></td>
                    <td class="c-center c-nowrap">${formatVnd(subTotal.value)}</td>
                </tr>
                <tr class="q-summary-row">
                    <td colspan="7" class="q-summary-label">
                        Thuế VAT (${formattedVatLabel.value})
                    </td>
                    <td></td>
                    <td class="c-center c-nowrap">${formatVnd(vatAmount.value)}</td>
                </tr>
                <tr class="q-summary-row">
                    <td colspan="7" class="q-summary-label">
                        TỔNG CỘNG (bao gồm thuế VAT) - <em>Sub Total (included VAT)</em>
                    </td>
                    <td></td>
                    <td class="c-center c-nowrap">${formatVnd(grandTotal.value)}</td>
                </tr>
            </tbody>
        </table>

        <div class="q-terms">
            <div class="q-terms-head">Điều kiện thương mại:</div>
            <p>1. Báo giá có hiệu lực đến ngày: ${escapeHtml(formattedValidUntil.value)}</p>
            <p>2. Phương thức thanh toán: Thỏa Thuận</p>
            <p>3. Phương thức giao hàng: đến chân công trình</p>
            <p>4. Thời gian giao hàng: 40 ngày đối với hàng trong nước, 90 ngày đối với hàng nhập khẩu kể từ ngày đơn đặt hàng được xác nhận</p>
            <p>5. Bảo hành: 2 năm linh kiện điện tử, 3 năm với máy nén Theo chính sách bảo hành của Ariston Việt Nam</p>
            <p>6. Chúng tôi hy vọng báo giá này sẽ làm hài lòng Quý Công ty. Mọi thắc mắc xin vui lòng liên lạc với chúng tôi</p>
            <p>Rất mong nhận được sự ủng hộ từ Quý Công ty.</p>
        </div>

        <div class="q-sign">
            <p>Many thanks &amp; Best regards</p>
            ${quoteForm.value.signerName.trim() ? `<p>${escapeHtml(quoteForm.value.signerName)}</p>` : ''}
            ${quoteForm.value.signerPhone.trim() ? `<p>HP: ${escapeHtml(quoteForm.value.signerPhone)}</p>` : ''}
            ${quoteForm.value.signerEmail.trim() ? `<p>Email : ${escapeHtml(quoteForm.value.signerEmail)}</p>` : ''}
        </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var imgs = Array.prototype.slice.call(document.images);
            var pending = imgs.filter(function (img) { return !img.complete; });

            function fitSinglePageAndPrint() {
                var frame = document.getElementById('qPageFrame');
                var sheet = document.getElementById('qSheet');
                if (frame && sheet) {
                    var cs = window.getComputedStyle(frame);
                    var padY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
                    var targetPagePx = (${targetPageHeightMm} / 25.4) * 96;
                    var availH = targetPagePx - padY - 4;
                    var rawH = sheet.scrollHeight;

                    if (rawH > availH && availH > 0) {
                        var low = 0.48;
                        var high = 1.0;
                        var best = 0.48;
                        for (var step = 0; step < 12; step++) {
                            var mid = (low + high) / 2;
                            sheet.style.setProperty('--s', String(mid));
                            if (sheet.scrollHeight <= availH) {
                                best = mid;
                                low = mid;
                            } else {
                                high = mid;
                            }
                        }
                        sheet.style.setProperty('--s', String(best));
                    }
                }

                var closePrintWindow = function () {
                    try {
                        if (window.opener && !window.opener.closed) {
                            window.opener.dispatchEvent(
                                new CustomEvent('kbuser-toast', {
                                    detail: { message: 'Đã xuất báo giá Ariston PDF thành công.' }
                                })
                            );
                        }
                    } catch (e) {}
                    setTimeout(function () {
                        window.close();
                    }, 100);
                };

                window.addEventListener('afterprint', closePrintWindow);
                window.onafterprint = closePrintWindow;
                if (window.matchMedia) {
                    var mediaQueryList = window.matchMedia('print');
                    if (mediaQueryList.addEventListener) {
                        mediaQueryList.addEventListener('change', function (mql) {
                            if (!mql.matches) {
                                closePrintWindow();
                            }
                        });
                    }
                }

                setTimeout(function () {
                    window.focus();
                    window.print();
                }, 150);
            }

            if (pending.length === 0) {
                fitSinglePageAndPrint();
            } else {
                var loaded = 0;
                pending.forEach(function (img) {
                    var done = function () {
                        loaded++;
                        if (loaded >= pending.length) {
                            fitSinglePageAndPrint();
                        }
                    };
                    img.addEventListener('load', done);
                    img.addEventListener('error', done);
                });
            }
        })();
    <\/script>
</body>
</html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    let notified = false;
    const notifyCompleted = (): void => {
        if (notified) {
            return;
        }
        notified = true;
        notifyToast('Đã xuất báo giá Ariston PDF thành công.');
    };
    const watchClosedTimer = window.setInterval(() => {
        if (printWindow.closed) {
            window.clearInterval(watchClosedTimer);
            notifyCompleted();
        }
    }, 200);
};

const sanitizeExcelFilename = (raw: string, fallback: string): string => {
    const cleaned = (raw || '')
        .trim()
        .replace(/[\\/:*?"<>|]+/g, '-')
        .replace(/\s+/g, '_');
    return cleaned || fallback;
};

const escapeXml = (val: unknown): string =>
    String(val ?? '')
        .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');

const excelColName = (col1Based: number): string => {
    let n = col1Based;
    let name = '';
    while (n > 0) {
        const rem = (n - 1) % 26;
        name = String.fromCharCode(65 + rem) + name;
        n = Math.floor((n - 1) / 26);
    }
    return name;
};

const crc32Table = (() => {
    const table = new Uint32Array(256);
    for (let i = 0; i < 256; i++) {
        let c = i;
        for (let k = 0; k < 8; k++) {
            c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        }
        table[i] = c >>> 0;
    }
    return table;
})();

const computeCrc32 = (bytes: Uint8Array): number => {
    let crc = 0xffffffff;
    for (let i = 0; i < bytes.length; i++) {
        crc = crc32Table[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
    }
    return (crc ^ 0xffffffff) >>> 0;
};

type ZipFileEntryInput = {
    path: string;
    content?: string;
    data?: Uint8Array;
};

const createZipBlob = (files: ZipFileEntryInput[]): Blob => {
    const encoder = new TextEncoder();
    const entries = files.map((f) => {
        const nameBytes = encoder.encode(f.path);
        const dataBytes = f.data ? f.data : encoder.encode(f.content ?? '');
        const crc = computeCrc32(dataBytes);
        return { nameBytes, dataBytes, crc, offset: 0 };
    });

    let totalLocalSize = 0;
    let totalCentralSize = 0;
    for (const entry of entries) {
        entry.offset = totalLocalSize;
        totalLocalSize += 30 + entry.nameBytes.length + entry.dataBytes.length;
        totalCentralSize += 46 + entry.nameBytes.length;
    }

    const totalSize = totalLocalSize + totalCentralSize + 22;
    const out = new Uint8Array(totalSize);
    const view = new DataView(out.buffer);
    let pos = 0;

    for (const entry of entries) {
        view.setUint32(pos, 0x04034b50, true);
        view.setUint16(pos + 4, 20, true);
        view.setUint16(pos + 6, 0x0800, true);
        view.setUint16(pos + 8, 0, true);
        view.setUint16(pos + 10, 0, true);
        view.setUint16(pos + 12, 0x21, true);
        view.setUint32(pos + 14, entry.crc, true);
        view.setUint32(pos + 18, entry.dataBytes.length, true);
        view.setUint32(pos + 22, entry.dataBytes.length, true);
        view.setUint16(pos + 26, entry.nameBytes.length, true);
        view.setUint16(pos + 28, 0, true);
        pos += 30;
        out.set(entry.nameBytes, pos);
        pos += entry.nameBytes.length;
        out.set(entry.dataBytes, pos);
        pos += entry.dataBytes.length;
    }

    const centralDirOffset = pos;
    for (const entry of entries) {
        view.setUint32(pos, 0x02014b50, true);
        view.setUint16(pos + 4, 20, true);
        view.setUint16(pos + 6, 20, true);
        view.setUint16(pos + 8, 0x0800, true);
        view.setUint16(pos + 10, 0, true);
        view.setUint16(pos + 12, 0, true);
        view.setUint16(pos + 14, 0x21, true);
        view.setUint32(pos + 16, entry.crc, true);
        view.setUint32(pos + 20, entry.dataBytes.length, true);
        view.setUint32(pos + 24, entry.dataBytes.length, true);
        view.setUint16(pos + 28, entry.nameBytes.length, true);
        view.setUint16(pos + 30, 0, true);
        view.setUint16(pos + 32, 0, true);
        view.setUint16(pos + 34, 0, true);
        view.setUint16(pos + 36, 0, true);
        view.setUint32(pos + 38, 0, true);
        view.setUint32(pos + 42, entry.offset, true);
        pos += 46;
        out.set(entry.nameBytes, pos);
        pos += entry.nameBytes.length;
    }

    view.setUint32(pos, 0x06054b50, true);
    view.setUint16(pos + 4, 0, true);
    view.setUint16(pos + 6, 0, true);
    view.setUint16(pos + 8, entries.length, true);
    view.setUint16(pos + 10, entries.length, true);
    view.setUint32(pos + 12, totalCentralSize, true);
    view.setUint32(pos + 16, centralDirOffset, true);
    view.setUint16(pos + 20, 0, true);

    return new Blob([out], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
};

type LoadedPngAsset = {
    bytes: Uint8Array;
    width: number;
    height: number;
};

const loadImageAsPngBytes = (url: string): Promise<LoadedPngAsset | null> =>
    new Promise((resolve) => {
        if (!url || !url.trim()) {
            resolve(null);
            return;
        }
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            try {
                const natW = img.naturalWidth || img.width || 200;
                const natH = img.naturalHeight || img.height || 100;
                const canvas = document.createElement('canvas');
                canvas.width = natW;
                canvas.height = natH;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    resolve(null);
                    return;
                }
                ctx.drawImage(img, 0, 0, natW, natH);
                canvas.toBlob((blob) => {
                    if (!blob) {
                        resolve(null);
                        return;
                    }
                    blob.arrayBuffer()
                        .then((buf) => {
                            resolve({
                                bytes: new Uint8Array(buf),
                                width: natW,
                                height: natH,
                            });
                        })
                        .catch(() => resolve(null));
                }, 'image/png');
            } catch {
                resolve(null);
            }
        };
        img.onerror = () => resolve(null);
        img.src = url;
    });

type XlsxDrawingImage = {
    bytes: Uint8Array;
    col0: number;
    row0: number;
    widthPx: number;
    heightPx: number;
    offsetXEmus?: number;
    offsetYEmus?: number;
};

type XlsxCell = {
    type: 'str' | 'empty';
    value?: string;
    style: number;
};

const estimateWrappedLineCount = (text: string, colWidthChars: number): number => {
    const raw = String(text ?? '');
    if (!raw.trim()) {
        return 1;
    }
    const effectiveChars = Math.max(5, Math.floor(colWidthChars - 2));
    return raw.split(/\r?\n/).reduce((sum, line) => {
        const len = line.trim().length;
        return sum + Math.max(1, Math.ceil(len / effectiveChars));
    }, 0);
};

const createXlsxSheetBuilder = (colWidths: number[]) => {
    const rows = new Map<number, Map<number, XlsxCell>>();
    const rowHeights = new Map<number, number>();
    const merges: string[] = [];
    const images: XlsxDrawingImage[] = [];

    const ensureRow = (r: number): Map<number, XlsxCell> => {
        let row = rows.get(r);
        if (!row) {
            row = new Map<number, XlsxCell>();
            rows.set(r, row);
        }
        return row;
    };

    const setRowHeight = (r: number, heightPt: number): void => {
        const prev = rowHeights.get(r) ?? 0;
        if (heightPt > prev) {
            rowHeights.set(r, Math.round(heightPt));
        }
    };

    const setText = (r: number, c: number, text: string, style = 0, heightPt?: number): void => {
        ensureRow(r).set(c, { type: 'str', value: String(text ?? ''), style });
        if (heightPt) {
            setRowHeight(r, heightPt);
        }
    };

    const setEmpty = (r: number, c: number, style = 0): void => {
        const row = ensureRow(r);
        if (!row.has(c)) {
            row.set(c, { type: 'empty', style });
        }
    };

    const merge = (r1: number, c1: number, r2: number, c2: number, fillStyle?: number): void => {
        if (r1 === r2 && c1 === c2) {
            return;
        }
        merges.push(`${excelColName(c1)}${r1}:${excelColName(c2)}${r2}`);
        if (fillStyle !== undefined) {
            for (let rr = r1; rr <= r2; rr++) {
                for (let cc = c1; cc <= c2; cc++) {
                    setEmpty(rr, cc, fillStyle);
                }
            }
        }
    };

    const addFittedImage = (
        asset: LoadedPngAsset | null,
        col1Based: number,
        row1Based: number,
        maxWidthPx: number,
        maxHeightPx: number,
        offsetXPx = 6,
        offsetYPx = 4,
    ): void => {
        if (!asset) {
            return;
        }
        const scale = Math.min(maxWidthPx / Math.max(1, asset.width), maxHeightPx / Math.max(1, asset.height));
        const widthPx = Math.max(16, Math.round(asset.width * scale));
        const heightPx = Math.max(16, Math.round(asset.height * scale));
        images.push({
            bytes: asset.bytes,
            col0: col1Based - 1,
            row0: row1Based - 1,
            widthPx,
            heightPx,
            offsetXEmus: Math.round(offsetXPx * 9525),
            offsetYEmus: Math.round(offsetYPx * 9525),
        });
    };

    const toSheetXml = (orientation: 'portrait' | 'landscape' = 'portrait'): string => {
        const colsXml = colWidths
            .map(
                (w, idx) =>
                    `<col min="${idx + 1}" max="${idx + 1}" width="${w}" customWidth="1"/>`,
            )
            .join('');

        const sortedRowNums = Array.from(rows.keys()).sort((a, b) => a - b);
        const rowsXml = sortedRowNums
            .map((r) => {
                const rowMap = rows.get(r)!;
                const sortedCols = Array.from(rowMap.keys()).sort((a, b) => a - b);
                const ht = rowHeights.get(r) ?? 20;
                const htAttr = ` ht="${Math.round(ht)}" customHeight="1"`;
                const cellsXml = sortedCols
                    .map((c) => {
                        const cell = rowMap.get(c)!;
                        const ref = `${excelColName(c)}${r}`;
                        if (cell.type === 'str') {
                            return `<c r="${ref}" s="${cell.style}" t="inlineStr"><is><t xml:space="preserve">${escapeXml(cell.value)}</t></is></c>`;
                        }
                        return `<c r="${ref}" s="${cell.style}"/>`;
                    })
                    .join('');
                return `<row r="${r}"${htAttr}>${cellsXml}</row>`;
            })
            .join('');

        const mergeXml =
            merges.length > 0
                ? `<mergeCells count="${merges.length}">${merges.map((m) => `<mergeCell ref="${m}"/>`).join('')}</mergeCells>`
                : '';

        const drawingXmlTag = images.length > 0 ? `<drawing r:id="rId1"/>` : '';

        return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetViews><sheetView workbookViewId="0" showGridLines="1"/></sheetViews>
    <sheetFormatPr defaultRowHeight="20"/>
    <cols>${colsXml}</cols>
    <sheetData>${rowsXml}</sheetData>
    ${mergeXml}
    <pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>
    <pageSetup paperSize="9" orientation="${orientation}" fitToWidth="1" fitToHeight="0"/>
    ${drawingXmlTag}
</worksheet>`;
    };

    return { setRowHeight, setText, setEmpty, merge, addFittedImage, images, toSheetXml };
};

const buildXlsxStylesXml = (): string => `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="14">
        <font><sz val="11"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><b/><sz val="13"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><sz val="10.5"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><b/><u/><sz val="14"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><b/><i/><u/><sz val="11"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><b/><i/><sz val="11"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><i/><sz val="10.5"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><b/><i/><sz val="11"/><color rgb="FF0070C0"/><name val="Times New Roman"/></font>
        <font><b/><sz val="10"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><sz val="10"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><b/><sz val="10.5"/><color rgb="FF000000"/><name val="Times New Roman"/></font>
        <font><b/><sz val="10.5"/><color rgb="FFC00000"/><name val="Times New Roman"/></font>
        <font><b/><i/><sz val="10.5"/><color rgb="FF800000"/><name val="Times New Roman"/></font>
        <font><i/><sz val="10.5"/><color rgb="FF0070C0"/><name val="Times New Roman"/></font>
    </fonts>
    <fills count="5">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFFFFF00"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFC5D9F1"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border>
            <left style="thin"><color rgb="FF000000"/></left>
            <right style="thin"><color rgb="FF000000"/></right>
            <top style="thin"><color rgb="FF000000"/></top>
            <bottom style="thin"><color rgb="FF000000"/></bottom>
            <diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="25">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="8" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="8" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="9" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="9" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="49" fontId="9" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
        <xf numFmtId="49" fontId="8" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
        <xf numFmtId="0" fontId="10" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="49" fontId="11" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
        <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="9" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="12" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="13" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="49" fontId="9" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="8" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="8" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    </cellXfs>
</styleSheet>`;

const buildXlsxDrawingXml = (images: XlsxDrawingImage[]): string => {
    const anchorsXml = images
        .map((img, idx) => {
            const cx = Math.round(img.widthPx * 9525);
            const cy = Math.round(img.heightPx * 9525);
            const colOff = img.offsetXEmus ?? 57150;
            const rowOff = img.offsetYEmus ?? 38100;
            const picId = idx + 1;
            return `<xdr:oneCellAnchor>
        <xdr:from>
            <xdr:col>${img.col0}</xdr:col>
            <xdr:colOff>${colOff}</xdr:colOff>
            <xdr:row>${img.row0}</xdr:row>
            <xdr:rowOff>${rowOff}</xdr:rowOff>
        </xdr:from>
        <xdr:ext cx="${cx}" cy="${cy}"/>
        <xdr:pic>
            <xdr:nvPicPr>
                <xdr:cNvPr id="${picId}" name="Picture ${picId}"/>
                <xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr>
            </xdr:nvPicPr>
            <xdr:blipFill>
                <a:blip r:embed="rId${picId}"/>
                <a:stretch><a:fillRect/></a:stretch>
            </xdr:blipFill>
            <xdr:spPr>
                <a:xfrm><a:off x="0" y="0"/><a:ext cx="${cx}" cy="${cy}"/></a:xfrm>
                <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
            </xdr:spPr>
        </xdr:pic>
        <xdr:clientData/>
    </xdr:oneCellAnchor>`;
        })
        .join('\n    ');

    return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    ${anchorsXml}
</xdr:wsDr>`;
};

const downloadXlsxWorkbook = (
    sheetName: string,
    sheetXml: string,
    images: XlsxDrawingImage[],
    filename: string,
): void => {
    const safeSheetName = escapeXml(sheetName.slice(0, 31) || 'Bao Gia');
    const hasImages = images.length > 0;
    const files: ZipFileEntryInput[] = [
        {
            path: '[Content_Types].xml',
            content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Default Extension="png" ContentType="image/png"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    ${hasImages ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : ''}
</Types>`,
        },
        {
            path: '_rels/.rels',
            content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>`,
        },
        {
            path: 'xl/workbook.xml',
            content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="${safeSheetName}" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>`,
        },
        {
            path: 'xl/_rels/workbook.xml.rels',
            content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>`,
        },
        {
            path: 'xl/styles.xml',
            content: buildXlsxStylesXml(),
        },
        {
            path: 'xl/worksheets/sheet1.xml',
            content: sheetXml,
        },
    ];

    if (hasImages) {
        files.push({
            path: 'xl/worksheets/_rels/sheet1.xml.rels',
            content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>`,
        });

        const drawingRelsXml = images
            .map(
                (_, idx) =>
                    `<Relationship Id="rId${idx + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image${idx + 1}.png"/>`,
            )
            .join('\n    ');

        files.push({
            path: 'xl/drawings/_rels/drawing1.xml.rels',
            content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    ${drawingRelsXml}
</Relationships>`,
        });

        files.push({
            path: 'xl/drawings/drawing1.xml',
            content: buildXlsxDrawingXml(images),
        });

        images.forEach((img, idx) => {
            files.push({
                path: `xl/media/image${idx + 1}.png`,
                data: img.bytes,
            });
        });
    }

    const blob = createZipBlob(files);
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename.endsWith('.xlsx') ? filename : `${filename}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => URL.revokeObjectURL(url), 1500);
};

const exportQuotationExcel = async (): Promise<void> => {
    const colWidths = [16, 15, 11, 24, 9, 44, 16, 12, 18];
    const sb = createXlsxSheetBuilder(colWidths);

    const [starLightAsset, aristonAsset, ...productAssets] = await Promise.all([
        loadImageAsPngBytes(starLightLogoUrl.value),
        loadImageAsPngBytes(aristonLogoUrl.value),
        ...items.value.map((it) => loadImageAsPngBytes(it.imageUrl)),
    ]);

    sb.setRowHeight(1, 22);
    sb.setRowHeight(2, 18);
    sb.setRowHeight(3, 18);
    sb.setRowHeight(4, 18);

    sb.merge(1, 1, 4, 2);
    sb.addFittedImage(starLightAsset, 1, 1, 108, 76, 8, 6);

    sb.setText(1, 3, 'CÔNG TY CỔ PHẦN QUỐC TẾ STAR LIGHT', 1, 22);
    sb.merge(1, 3, 1, 7);
    sb.setText(2, 3, 'Địa chỉ VP: Số 15 Nguyễn Cảnh Dị, Định Công, TP Hà Nội', 2, 18);
    sb.merge(2, 3, 2, 7);
    sb.setText(3, 3, 'Điện thoại: 0243 201 8322 - Hotline: 0979 541 345', 2, 18);
    sb.merge(3, 3, 3, 7);
    sb.setText(4, 3, 'www: starlightgroup.vn', 2, 18);
    sb.merge(4, 3, 4, 7);

    sb.merge(1, 8, 4, 9);
    sb.addFittedImage(aristonAsset, 8, 1, 155, 62, 10, 12);

    let r = 5;
    sb.setText(r, 1, quoteForm.value.documentTitle, 3, 30);
    sb.merge(r, 1, r, 9);
    r++;

    sb.setText(r, 1, 'Kính gửi:', 4, 21);
    sb.setText(r, 2, quoteForm.value.recipientCompany, 5, 21);
    sb.merge(r, 2, r, 9);
    r++;

    sb.setText(r, 1, 'Người nhận :', 6, 20);
    sb.setText(r, 2, quoteForm.value.recipientPerson, 7, 20);
    sb.merge(r, 2, r, 9);
    r++;

    if (quoteForm.value.contactPhone.trim()) {
        sb.setText(r, 1, 'Thông tin liên lạc:', 6, 20);
        sb.setText(r, 2, quoteForm.value.contactPhone, 7, 20);
        sb.merge(r, 2, r, 9);
        r++;
    }

    sb.setText(r, 1, 'Thời gian :', 6, 20);
    sb.setText(r, 2, formattedQuoteDate.value, 7, 20);
    sb.merge(r, 2, r, 9);
    r++;

    sb.setText(r, 1, 'Project:', 5, 20);
    sb.setText(r, 2, quoteForm.value.projectName, 7, 20);
    sb.merge(r, 2, r, 9);
    r++;

    sb.setText(
        r,
        1,
        'Công ty Star Light là đơn vị phát triển mảng dự án sản phẩm Ariston, rất hân hạnh gửi bảng báo giá cho Quý Khách hàng. Cụ thể như sau',
        6,
        24,
    );
    sb.merge(r, 1, r, 9);
    r++;

    sb.setText(r, 1, 'Sản Phẩm\nProduct', 23, 38);
    sb.setText(r, 2, 'Mã sản phẩm\nModel', 23, 38);
    sb.setText(r, 3, 'Xuất xứ\nOrigin', 23, 38);
    sb.setText(r, 4, 'Hình ảnh sản phẩm\nImage', 23, 38);
    sb.setText(r, 5, 'Đơn vị', 23, 38);
    sb.setText(r, 6, 'Đặc tính kỹ thuật\nSpecifications', 23, 38);
    sb.setText(r, 7, 'Giá bán dự án\nSelling Price\n(VNĐ)', 23, 38);
    sb.setText(r, 8, 'Số lượng\nQuantity\n(chiếc)', 23, 38);
    sb.setText(r, 9, 'Thành tiền\nAmount\n(VNĐ)', 23, 38);
    r++;

    items.value.forEach((item, idx) => {
        const specLines = formatSpecLines(item.specifications);
        const specText = specLines.join('\n');
        const wrappedSpecLines = estimateWrappedLineCount(specText, colWidths[5]);
        const wrappedLabelLines = estimateWrappedLineCount(item.productLabel, colWidths[0]);
        const wrappedModelLines = estimateWrappedLineCount(item.modelCode, colWidths[1]);
        const maxLines = Math.max(1, wrappedSpecLines, wrappedLabelLines, wrappedModelLines);
        const prodAsset = productAssets[idx] ?? null;
        const rowH = Math.max(prodAsset ? 118 : 34, maxLines * 16 + 14);

        const uPrice = Math.max(0, Math.round(Number(item.unitPrice) || 0));
        const qty = Math.max(0, Math.round(Number(item.quantity) || 0));
        const amount = rowAmount(item);

        sb.setText(r, 1, item.productLabel, 24, rowH);
        sb.setText(r, 2, item.modelCode, 24, rowH);
        sb.setText(r, 3, item.origin, 24, rowH);
        sb.setEmpty(r, 4, 11);
        if (prodAsset) {
            sb.addFittedImage(prodAsset, 4, r, 145, 138, 10, 8);
        }
        sb.setText(r, 5, item.unit, 24, rowH);
        sb.setText(r, 6, specText, 12, rowH);
        sb.setText(r, 7, formatVnd(uPrice), 24, rowH);
        sb.setText(r, 8, formatVnd(qty), 24, rowH);
        sb.setText(r, 9, formatVnd(amount), 24, rowH);
        r++;
    });

    sb.setText(r, 1, 'TỔNG CỘNG (chưa bao gồm thuế VAT) - Sub Total (not included VAT)', 15, 24);
    sb.merge(r, 1, r, 7, 15);
    sb.setEmpty(r, 8, 15);
    sb.setText(r, 9, formatVnd(subTotal.value), 24, 24);
    r++;

    sb.setText(r, 1, `Thuế VAT (${formattedVatLabel.value})`, 15, 24);
    sb.merge(r, 1, r, 7, 15);
    sb.setEmpty(r, 8, 15);
    sb.setText(r, 9, formatVnd(vatAmount.value), 24, 24);
    r++;

    sb.setText(r, 1, 'TỔNG CỘNG (bao gồm thuế VAT) - Sub Total (included VAT)', 15, 24);
    sb.merge(r, 1, r, 7, 15);
    sb.setEmpty(r, 8, 15);
    sb.setText(r, 9, formatVnd(grandTotal.value), 16, 24);
    r += 2;

    sb.setText(r, 1, 'Điều kiện thương mại:', 17, 21);
    sb.merge(r, 1, r, 9);
    r++;

    const terms = [
        `1. Báo giá có hiệu lực đến ngày: ${formattedValidUntil.value}`,
        '2. Phương thức thanh toán: Thỏa Thuận',
        '3. Phương thức giao hàng: đến chân công trình',
        '4. Thời gian giao hàng: 40 ngày đối với hàng trong nước, 90 ngày đối với hàng nhập khẩu kể từ ngày đơn đặt hàng được xác nhận',
        '5. Bảo hành: 2 năm linh kiện điện tử, 3 năm với máy nén Theo chính sách bảo hành của Ariston Việt Nam',
        '6. Chúng tôi hy vọng báo giá này sẽ làm hài lòng Quý Công ty. Mọi thắc mắc xin vui lòng liên lạc với chúng tôi',
        'Rất mong nhận được sự ủng hộ từ Quý Công ty.',
    ];
    for (const line of terms) {
        const lineH = line.length > 105 ? 32 : 20;
        sb.setText(r, 1, line, 18, lineH);
        sb.merge(r, 1, r, 9);
        r++;
    }

    r++;
    sb.setText(r, 1, 'Many thanks & Best regards', 5, 20);
    sb.merge(r, 1, r, 9);
    r++;
    if (quoteForm.value.signerName.trim()) {
        sb.setText(r, 1, quoteForm.value.signerName, 20, 20);
        sb.merge(r, 1, r, 9);
        r++;
    }
    if (quoteForm.value.signerPhone.trim()) {
        sb.setText(r, 1, `HP: ${quoteForm.value.signerPhone}`, 5, 20);
        sb.merge(r, 1, r, 9);
        r++;
    }
    if (quoteForm.value.signerEmail.trim()) {
        sb.setText(r, 1, `Email : ${quoteForm.value.signerEmail}`, 21, 20);
        sb.merge(r, 1, r, 9);
    }

    const fileSlug = sanitizeExcelFilename(quoteForm.value.projectName, 'Ariston');
    const orientation = isPortraitMode.value ? 'portrait' : 'landscape';
    downloadXlsxWorkbook(
        'Bao Gia Ariston',
        sb.toSheetXml(orientation),
        sb.images,
        `Bao-gia-Ariston-${fileSlug}.xlsx`,
    );
    notifyToast('Đã xuất báo giá Ariston ra file Excel (.xlsx) thành công.');
};

const exportKflexQuotationExcel = async (): Promise<void> => {
    const colWidths = [5.5, 30, 12, 15, 14, 8.5, 8.5, 8.5, 8.5, 9.5, 9.5, 14, 16, 19];
    const sb = createXlsxSheetBuilder(colWidths);

    const [starLightAsset, kflexAsset] = await Promise.all([
        loadImageAsPngBytes(starLightLogoUrl.value),
        loadImageAsPngBytes(kflexLogoUrl.value),
    ]);

    sb.setRowHeight(1, 22);
    sb.setRowHeight(2, 18);
    sb.setRowHeight(3, 18);
    sb.setRowHeight(4, 18);

    sb.merge(1, 1, 4, 2);
    sb.addFittedImage(starLightAsset, 1, 1, 102, 74, 8, 6);

    sb.setText(1, 3, 'CÔNG TY CỔ PHẦN QUỐC TẾ STAR LIGHT', 1, 22);
    sb.merge(1, 3, 1, 12);
    sb.setText(2, 3, 'Địa chỉ VP: Số 15 Nguyễn Cảnh Dị, Định Công, Hà Nội', 2, 18);
    sb.merge(2, 3, 2, 12);
    sb.setText(3, 3, 'Điện thoại: 0243 201 8322 - Hotline: 0979 541 345', 2, 18);
    sb.merge(3, 3, 3, 12);
    sb.setText(4, 3, 'www: starlightgroup.vn', 2, 18);
    sb.merge(4, 3, 4, 12);

    sb.merge(1, 13, 4, 14);
    sb.addFittedImage(kflexAsset, 13, 1, 132, 58, 8, 12);

    let r = 5;
    sb.setText(r, 1, kflexQuoteForm.value.documentTitle, 3, 28);
    sb.merge(r, 1, r, 14);
    r++;

    sb.setText(r, 1, 'Kính gửi:', 4, 21);
    sb.merge(r, 1, r, 2);
    sb.setText(r, 3, kflexQuoteForm.value.recipientCompany, 5, 21);
    sb.merge(r, 3, r, 14);
    r++;

    sb.setText(r, 1, 'Người nhận:', 6, 20);
    sb.merge(r, 1, r, 2);
    sb.setText(r, 3, kflexQuoteForm.value.recipientPerson, 7, 20);
    sb.merge(r, 3, r, 14);
    r++;

    sb.setText(r, 1, 'Project:', 6, 20);
    sb.merge(r, 1, r, 2);
    sb.setText(r, 3, kflexQuoteForm.value.projectName, 7, 20);
    sb.merge(r, 3, r, 14);
    r++;

    sb.setText(r, 1, 'Thời gian:', 6, 20);
    sb.merge(r, 1, r, 2);
    sb.setText(r, 3, kflexFormattedQuoteDate.value, 7, 20);
    sb.merge(r, 3, r, 14);
    r++;

    sb.setText(r, 1, kflexQuoteForm.value.introText, 8, 24);
    sb.merge(r, 1, r, 14);
    r++;

    const hRow1 = r;
    const hRow2 = r + 1;
    sb.setRowHeight(hRow1, 24);
    sb.setRowHeight(hRow2, 30);

    sb.setText(hRow1, 1, 'TT', 9);
    sb.merge(hRow1, 1, hRow2, 1, 9);

    sb.setText(hRow1, 2, 'Tên vật tư', 9);
    sb.merge(hRow1, 2, hRow2, 2, 9);

    sb.setText(hRow1, 3, 'Nhãn hiệu', 9);
    sb.merge(hRow1, 3, hRow2, 3, 9);

    sb.setText(hRow1, 4, 'Thương hiệu - Xuất xứ', 9);
    sb.merge(hRow1, 4, hRow2, 4, 9);

    sb.setText(hRow1, 5, 'Loại sản phẩm', 9);
    sb.merge(hRow1, 5, hRow2, 5, 9);

    sb.setText(hRow1, 6, 'Kích thước', 9);
    sb.merge(hRow1, 6, hRow1, 9, 9);
    sb.setText(hRow2, 6, 'Chiều dày\n(mm)', 9);
    sb.setText(hRow2, 7, 'ID (mm)', 9);
    sb.setText(hRow2, 8, 'Chiều rộng\n(m)', 9);
    sb.setText(hRow2, 9, 'Chiều dài\n(m)', 9);

    sb.setText(hRow1, 10, 'Khối lượng', 9);
    sb.merge(hRow1, 10, hRow1, 11, 9);
    sb.setText(hRow2, 10, 'm', 9);
    sb.setText(hRow2, 11, 'm2', 9);

    sb.setText(hRow1, 12, 'Đơn giá\nUnit Price', 9);
    sb.merge(hRow1, 12, hRow2, 12, 9);

    sb.setText(hRow1, 13, 'Tổng\nTotal', 9);
    sb.merge(hRow1, 13, hRow2, 13, 9);

    sb.setText(hRow1, 14, 'Ghi chú', 9);
    sb.merge(hRow1, 14, hRow2, 14, 9);

    r = hRow2 + 1;

    for (const row of kflexRenderRows.value) {
        if (row.rowType === 'section') {
            sb.setEmpty(r, 1, 10);
            sb.setText(r, 2, (row.sectionTitle || '').toUpperCase(), 10, 25);
            sb.merge(r, 2, r, 14, 10);
            r++;
            continue;
        }

        const it = row.item;
        const displayName =
            it.itemName.trim() ||
            `${it.brandLabel} ${it.thickness}${it.innerDiameter ? 'x' + it.innerDiameter : 'mm'}`;

        const linesName = estimateWrappedLineCount(displayName, colWidths[1]);
        const linesBrand = estimateWrappedLineCount(it.brandLabel, colWidths[2]);
        const linesOrigin = estimateWrappedLineCount(it.originLabel, colWidths[3]);
        const linesType = estimateWrappedLineCount(it.productTypeLabel, colWidths[4]);
        const linesNote =
            row.noteRowspan === 1 ? estimateWrappedLineCount(it.note || '', colWidths[13]) : 1;
        const maxLines = Math.max(1, linesName, linesBrand, linesOrigin, linesType, linesNote);
        const rowH = Math.max(26, maxLines * 16 + 10);

        sb.setText(r, 1, String(row.tt), 11, rowH);
        sb.setText(r, 2, displayName, 12, rowH);
        sb.setText(r, 3, it.brandLabel, 11, rowH);
        sb.setText(r, 4, it.originLabel, 11, rowH);
        sb.setText(r, 5, it.productTypeLabel, 11, rowH);
        sb.setText(r, 6, it.thickness, 11, rowH);
        sb.setText(r, 7, it.innerDiameter, 11, rowH);
        sb.setText(r, 8, it.widthM, 11, rowH);
        sb.setText(r, 9, it.lengthM, 11, rowH);

        const formattedQtyM = formatKflexNum(it.qtyM);
        const formattedQtyM2 = formatKflexNum(it.qtyM2);

        if (formattedQtyM) {
            sb.setText(r, 10, formattedQtyM, 22, rowH);
        } else {
            sb.setEmpty(r, 10, 11);
        }

        if (formattedQtyM2) {
            sb.setText(r, 11, formattedQtyM2, 22, rowH);
        } else {
            sb.setEmpty(r, 11, 11);
        }

        sb.setText(r, 12, formatKflexTotalNum(it.unitPrice), 13, rowH);
        sb.setText(r, 13, formatKflexTotalNum(kflexRowAmount(it)), 14, rowH);

        if (row.noteRowspan > 0) {
            sb.setText(r, 14, it.note || '', 11, rowH);
            if (row.noteRowspan > 1) {
                sb.merge(r, 14, r + row.noteRowspan - 1, 14, 11);
            }
        } else {
            sb.setEmpty(r, 14, 11);
        }

        r++;
    }

    sb.setText(r, 1, 'TỔNG CỘNG (chưa bao gồm thuế VAT) - Sub Total (Not included VAT)', 15, 24);
    sb.merge(r, 1, r, 12, 15);
    sb.setText(r, 13, formatKflexTotalNum(kflexSubTotal.value), 16, 24);
    r++;

    sb.setText(r, 1, `Thuế VAT (${kflexFormattedVatLabel.value})`, 15, 24);
    sb.merge(r, 1, r, 12, 15);
    sb.setText(r, 13, formatKflexTotalNum(kflexVatAmount.value), 16, 24);
    r++;

    sb.setText(r, 1, 'TỔNG CỘNG (bao gồm thuế VAT) - Sub Total (included VAT)', 15, 24);
    sb.merge(r, 1, r, 12, 15);
    sb.setText(r, 13, formatKflexTotalNum(kflexGrandTotal.value), 16, 24);
    r += 2;

    sb.setText(r, 1, 'Điều kiện thương mại:', 17, 21);
    sb.merge(r, 1, r, 14);
    r++;

    const kfTerms = [
        `1. Báo giá có hiệu lực đến ngày: ${kflexFormattedValidUntil.value}`,
        `2. Phương thức thanh toán: ${kflexQuoteForm.value.paymentTerms}`,
        `3. Phương thức giao hàng: ${kflexQuoteForm.value.deliveryMethod}`,
        `4. Thời gian giao hàng: ${kflexQuoteForm.value.deliveryTime}`,
        '5. Chúng tôi hy vọng báo giá này sẽ làm hài lòng Quý Công ty. Mọi thắc mắc xin vui lòng liên lạc với chúng tôi',
        'Rất mong nhận được sự ủng hộ từ Quý Công ty.',
    ];
    for (const line of kfTerms) {
        const lineH = line.length > 115 ? 32 : 20;
        sb.setText(r, 1, line, 18, lineH);
        sb.merge(r, 1, r, 14);
        r++;
    }

    r++;
    sb.setText(r, 2, 'Many thanks & Best regards', 19, 20);
    sb.merge(r, 2, r, 14);
    r++;

    sb.setText(r, 1, kflexQuoteForm.value.contactName, 20, 20);
    sb.merge(r, 1, r, 14);
    r++;

    sb.setText(r, 1, 'HP', 19, 20);
    sb.setText(r, 2, kflexQuoteForm.value.contactHp, 4, 20);
    sb.merge(r, 2, r, 14);
    r++;

    sb.setText(r, 1, 'Email :', 19, 20);
    sb.setText(r, 2, kflexQuoteForm.value.contactEmail, 21, 20);
    sb.merge(r, 2, r, 14);

    const fileSlug = sanitizeExcelFilename(kflexQuoteForm.value.projectName, 'K-Flex');
    downloadXlsxWorkbook(
        'Bao Gia K-Flex',
        sb.toSheetXml('portrait'),
        sb.images,
        `Bao-gia-K-Flex-${fileSlug}.xlsx`,
    );
    notifyToast('Đã xuất báo giá bảo ôn K-Flex ra file Excel (.xlsx) thành công.');
};
</script>
<template>
    <UserLayout :cart-count="cartCount">
        <Head title="Tạo Báo Giá & Xuất PDF" />

        <section class="kb-user-quote-section">
            <div class="kb-user-quote-container">
                <div class="page-head">
                    <div>
                        <h2>Tạo Báo Giá &amp; Xuất PDF / Excel</h2>
                        <p>Lập bảng báo giá dự án chuẩn mẫu Star Light và xuất file PDF khổ A4 hoặc file Excel.</p>
                    </div>
                    <div class="actions">
                        <div v-if="props.quoteUser" class="kb-quote-user-pill">
                            <span>{{ props.quoteUser.email }}</span>
                            <button type="button" class="q-btn danger" @click="logoutQuoteSession">
                                Đăng xuất báo giá
                            </button>
                        </div>
                        <template v-if="activeQuotationType === 'ariston'">
                            <button
                                class="q-btn kflex-excel-btn"
                                type="button"
                                @click="exportQuotationExcel"
                            >
                                📊 Xuất Excel Báo Giá Ariston
                            </button>
                            <button
                                class="q-btn primary"
                                type="button"
                                @click="printQuotationPdf"
                            >
                                🖨️ In / Xuất PDF Báo Giá Ariston
                            </button>
                        </template>
                        <template v-else>
                            <button
                                class="q-btn kflex-excel-btn"
                                type="button"
                                @click="exportKflexQuotationExcel"
                            >
                                📊 Xuất Excel Báo Giá K-Flex
                            </button>
                            <button
                                class="q-btn primary"
                                type="button"
                                @click="printKflexQuotationPdf"
                            >
                                🖨️ In / Xuất PDF Báo Giá K-Flex
                            </button>
                        </template>
                    </div>
                </div>

                <div class="kb-quote-type-tabs">
                    <button
                        type="button"
                        class="kb-quote-tab-btn"
                        :class="{ active: activeQuotationType === 'ariston' }"
                        @click="activeQuotationType = 'ariston'"
                    >
                        <span class="kb-quote-tab-badge">01</span>
                        <div>
                            <strong>Báo giá Bình nước nóng / Bơm nhiệt Ariston</strong>
                            <small>Mẫu chuẩn Công ty CP Quốc tế Star Light · Ariston</small>
                        </div>
                    </button>
                    <button
                        type="button"
                        class="kb-quote-tab-btn"
                        :class="{ active: activeQuotationType === 'second' }"
                        @click="activeQuotationType = 'second'"
                    >
                        <span class="kb-quote-tab-badge">02</span>
                        <div>
                            <strong>Báo giá Bảo ôn K-Flex (Ống &amp; Tấm)</strong>
                            <small>Mẫu chuẩn Công ty CP Quốc tế Star Light · K-Flex</small>
                        </div>
                    </button>
                </div>

                <template v-if="activeQuotationType === 'ariston'">
                    <div class="kb-quote-builder-card">
                        <div class="kb-quote-section-title">1. Thông tin chung của Báo giá</div>
                        <div class="kb-quote-form-grid">
                            <label class="kb-quote-field">
                                <span>Tiêu đề báo giá</span>
                                <input v-model="quoteForm.documentTitle" type="text" placeholder="BÁO GIÁ BƠM NHIỆT ARISTON" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Kính gửi</span>
                                <input
                                    v-model="quoteForm.recipientCompany"
                                    type="text"
                                    placeholder="QUÝ ĐỐI TÁC CÔNG TY CỔ PHẦN..."
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Người nhận</span>
                                <input v-model="quoteForm.recipientPerson" type="text" placeholder="VD: Ms.Vân Chi" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Thông tin liên lạc (SĐT)</span>
                                <input v-model="quoteForm.contactPhone" type="text" placeholder="VD: 0963.015.966" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Project (Dự án)</span>
                                <input v-model="quoteForm.projectName" type="text" placeholder="VD: Chung cư cao cấp Vạn Xuân" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Thời gian (Mặc định hôm nay)</span>
                                <input v-model="quoteForm.quoteDate" type="date" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Hiệu lực đến ngày (Hiện tại + 2 tháng)</span>
                                <input v-model="quoteForm.validUntilDate" type="date" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Thuế VAT (%)</span>
                                <input v-model.number="quoteForm.vatPercent" type="number" min="0" max="100" step="1" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Người báo giá (Ký tên)</span>
                                <input
                                    v-model="quoteForm.signerName"
                                    type="text"
                                    placeholder="VD: Nguyễn Tuấn Kiệt"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Số điện thoại người báo giá (HP)</span>
                                <input
                                    v-model="quoteForm.signerPhone"
                                    type="text"
                                    placeholder="VD: 0979 807 234"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Email người báo giá</span>
                                <input
                                    v-model="quoteForm.signerEmail"
                                    type="text"
                                    placeholder="VD: tuankiet@starlightgroup.vn"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="kb-quote-builder-card">
                        <div class="kb-quote-items-head">
                            <div>
                                <div class="kb-quote-section-title">
                                    2. Chọn sản phẩm Ariston ({{ items.length }} sản phẩm trong báo giá)
                                </div>
                                <small class="kb-quote-section-sub">
                                    Tick chọn một hoặc nhiều sản phẩm Ariston bên dưới để đưa vào cùng 1 bảng báo giá, sau đó nhập Giá bán và Số lượng cho từng mặt hàng.
                                </small>
                            </div>
                            <div class="kb-quote-items-actions">
                                <input
                                    v-model="productSearchQuery"
                                    type="search"
                                    class="kb-quote-catalog-search"
                                    placeholder="Tìm nhanh model Ariston..."
                                />
                                <button type="button" class="q-btn" @click="selectAllFilteredProducts">
                                    ✓ Chọn tất cả
                                </button>
                                <button v-if="items.length > 1" type="button" class="q-btn" @click="clearToFirstProduct">
                                    Giữ lại 1 dòng
                                </button>
                                <button type="button" class="q-btn primary" @click="addAristonRow">
                                    ＋ Thêm dòng sản phẩm
                                </button>
                                <button type="button" class="q-btn" @click="addAccessoryRow">
                                    ＋ Thêm phụ kiện (Dây cấp...)
                                </button>
                            </div>
                        </div>

                        <div class="kb-quote-catalog-grid">
                            <button
                                v-for="prod in filteredAristonCatalog"
                                :key="prod.id"
                                type="button"
                                class="kb-quote-catalog-card"
                                :class="{ selected: isProductSelected(prod.id) }"
                                @click="toggleProductSelection(prod)"
                            >
                                <span class="kb-quote-catalog-check">
                                    {{ isProductSelected(prod.id) ? '✓' : '+' }}
                                </span>
                                <img
                                    v-if="prod.image?.[0]"
                                    :src="resolveImageUrl(prod.image[0])"
                                    :alt="prod.name"
                                    class="kb-quote-catalog-thumb"
                                />
                                <div class="kb-quote-catalog-meta">
                                    <strong>{{ prod.name }}</strong>
                                    <small>{{ prod.subcategory?.name || 'Ariston' }} · Xuất xứ: {{ deriveAristonShortOrigin(prod) }}</small>
                                </div>
                            </button>
                        </div>

                        <div class="kb-quote-items-list">
                            <div v-for="(item, idx) in items" :key="item.uid" class="kb-quote-item-box">
                                <div class="kb-quote-item-compact-row">
                                    <span class="kb-quote-item-index">#{{ idx + 1 }}</span>

                                    <img
                                        v-if="item.imageUrl"
                                        :src="item.imageUrl"
                                        alt=""
                                        class="kb-quote-thumb-preview"
                                    />
                                    <span v-else class="kb-quote-no-img">SP</span>

                                    <label class="kb-quote-field kb-quote-compact-select">
                                        <span>Sản phẩm Ariston / Model</span>
                                        <select
                                            :value="item.productId ?? ''"
                                            @change="onSelectProduct(item, ($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="">-- Tự nhập / Phụ kiện ngoài --</option>
                                            <option
                                                v-for="prod in props.aristonProducts"
                                                :key="prod.id"
                                                :value="prod.id"
                                            >
                                                {{ prod.name }} — {{ prod.subcategory?.name || 'Ariston' }} (Xuất xứ: {{ deriveAristonShortOrigin(prod) }})
                                            </option>
                                        </select>
                                    </label>

                                    <label class="kb-quote-field">
                                        <span>Tên hiển thị (Cột 1)</span>
                                        <input v-model="item.productLabel" type="text" placeholder="Máy nước nóng gián tiếp..." />
                                    </label>

                                    <label class="kb-quote-field">
                                        <span>Giá bán (VNĐ)</span>
                                        <input
                                            v-model.number="item.unitPrice"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            class="kb-quote-no-spin"
                                            @wheel.prevent="($event.target as HTMLInputElement).blur()"
                                        />
                                    </label>

                                    <label class="kb-quote-field">
                                        <span>Số lượng (chiếc)</span>
                                        <input
                                            v-model.number="item.quantity"
                                            type="number"
                                            min="1"
                                            step="1"
                                            class="kb-quote-no-spin"
                                            @wheel.prevent="($event.target as HTMLInputElement).blur()"
                                        />
                                    </label>

                                    <div class="kb-quote-field">
                                        <span>Thành tiền</span>
                                        <div class="kb-quote-computed-amount">{{ formatVnd(rowAmount(item)) }} ₫</div>
                                    </div>

                                    <div class="kb-quote-compact-btns">
                                        <button
                                            type="button"
                                            class="mini"
                                            @click="toggleExpandRow(item.uid)"
                                        >
                                            {{ expandedUid === item.uid ? 'Thu gọn' : 'Sửa chi tiết' }}
                                        </button>
                                        <button
                                            v-if="items.length > 1"
                                            type="button"
                                            class="mini danger"
                                            @click="removeRow(idx)"
                                        >
                                            Xóa
                                        </button>
                                    </div>
                                </div>

                                <div v-if="expandedUid === item.uid" class="kb-quote-item-grid kb-quote-item-expanded">
                                    <label class="kb-quote-field">
                                        <span>Mã sản phẩm (Cột 2 - Model)</span>
                                        <input v-model="item.modelCode" type="text" placeholder="SLIM3 20 B VN" />
                                    </label>

                                    <label class="kb-quote-field">
                                        <span>Xuất xứ (Cột 3 - Origin)</span>
                                        <input v-model="item.origin" type="text" placeholder="Ý-Việt Nam" />
                                    </label>

                                    <label class="kb-quote-field">
                                        <span>Đơn vị (Cột 5)</span>
                                        <input v-model="item.unit" type="text" placeholder="Máy / Dây / Bộ" />
                                    </label>

                                    <div class="kb-quote-field">
                                        <span>Hình ảnh sản phẩm</span>
                                        <div class="kb-quote-img-picker">
                                            <label class="mini kb-quote-upload-btn">
                                                Đổi ảnh
                                                <input type="file" accept="image/*" hidden @change="onUploadCustomImage(item, $event)" />
                                            </label>
                                            <button
                                                v-if="item.imageUrl"
                                                type="button"
                                                class="mini"
                                                @click="item.imageUrl = ''"
                                            >
                                                Bỏ ảnh
                                            </button>
                                        </div>
                                    </div>

                                    <label class="kb-quote-field kb-quote-span-full">
                                        <span>Đặc tính kỹ thuật (Specifications)</span>
                                        <textarea v-model="item.specifications" rows="5"></textarea>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kb-quote-preview-wrap">
                        <div class="kb-quote-preview-toolbar">
                            <strong>
                                Xem trước bản in ({{
                                    isPortraitMode
                                        ? `Khổ A4 Đứng — ${items.length} sản phẩm`
                                        : `Khổ A4 Ngang — ${items.length} sản phẩm`
                                }})
                            </strong>
                            <div class="kb-quote-preview-actions">
                                <button class="q-btn kflex-excel-btn" type="button" @click="exportQuotationExcel">
                                    📊 Xuất Excel
                                </button>
                                <button class="q-btn primary" type="button" @click="printQuotationPdf">
                                    🖨️ In / Xuất PDF ({{ isPortraitMode ? 'A4 Đứng' : 'A4 Ngang' }})
                                </button>
                            </div>
                        </div>

                        <div class="kb-quote-paper" :class="{ 'is-portrait': isPortraitMode }">
                            <header class="qp-header">
                                <div class="qp-logo-left">
                                    <img :src="starLightLogoUrl" alt="STAR LIGHT" class="qp-starlight-logo" />
                                </div>
                                <div class="qp-company-center">
                                    <h1>CÔNG TY CỔ PHẦN QUỐC TẾ STAR LIGHT</h1>
                                    <p>Địa chỉ VP: Số 15 Nguyễn Cảnh Dị, Định Công, TP Hà Nội</p>
                                    <p>Điện thoại: 0243 201 8322 - Hotline: 0979 541 345</p>
                                    <p>www: starlightgroup.vn</p>
                                </div>
                                <div class="qp-logo-right">
                                    <img :src="aristonLogoUrl" alt="ARISTON" class="qp-ariston-logo" />
                                </div>
                            </header>

                            <div class="qp-title">{{ quoteForm.documentTitle }}</div>

                            <div class="qp-meta">
                                <div class="qp-meta-row">
                                    <span class="qp-meta-label-kinhgui">Kính gửi:</span>
                                    <span class="qp-meta-val-kinhgui">{{ quoteForm.recipientCompany }}</span>
                                </div>
                                <div class="qp-meta-row">
                                    <span class="qp-meta-label-italic">Người nhận :</span>
                                    <span class="qp-meta-val-italic">{{ quoteForm.recipientPerson }}</span>
                                </div>
                                <div v-if="quoteForm.contactPhone.trim()" class="qp-meta-row">
                                    <span class="qp-meta-label-italic">Thông tin liên lạc:</span>
                                    <span class="qp-meta-val-italic">{{ quoteForm.contactPhone }}</span>
                                </div>
                                <div class="qp-meta-row">
                                    <span class="qp-meta-label-italic">Thời gian :</span>
                                    <span class="qp-meta-val-italic">{{ formattedQuoteDate }}</span>
                                </div>
                                <div class="qp-meta-row">
                                    <span class="qp-meta-label-project">Project:</span>
                                    <span class="qp-meta-val-italic">{{ quoteForm.projectName }}</span>
                                </div>
                            </div>

                            <div class="qp-intro">
                                Công ty Star Light là đơn vị phát triển mảng dự án sản phẩm Ariston, rất hân hạnh gửi bảng báo giá cho Quý Khách hàng. Cụ thể như sau
                            </div>

                            <table class="qp-table">
                                <thead>
                                    <tr>
                                        <th style="width: 9.0%">Sản Phẩm<em>Product</em></th>
                                        <th style="width: 8.5%">Mã sản phẩm<em>Model</em></th>
                                        <th style="width: 5.5%">Xuất xứ<em>Origin</em></th>
                                        <th style="width: 17.5%">Hình ảnh sản phẩm<em>Image</em></th>
                                        <th style="width: 4.5%">Đơn vị</th>
                                        <th style="width: 26.5%">Đặc tính kỹ thuật<em>Specifications</em></th>
                                        <th style="width: 10.5%">Giá bán dự án<em>Selling Price</em><em>(VNĐ)</em></th>
                                        <th style="width: 6.0%">Số lượng Quantity<br>(chiếc)</th>
                                        <th style="width: 12.0%">Thành tiền<em>Amount</em><em>(VNĐ)</em></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in items" :key="item.uid">
                                        <td class="qp-center qp-bold qp-preline">{{ item.productLabel }}</td>
                                        <td class="qp-center qp-bold qp-preline">{{ item.modelCode }}</td>
                                        <td class="qp-center qp-bold qp-preline">{{ item.origin }}</td>
                                        <td class="qp-center">
                                            <img
                                                v-if="item.imageUrl"
                                                :src="item.imageUrl"
                                                :alt="item.modelCode || item.productLabel"
                                                class="qp-prod-img"
                                            />
                                        </td>
                                        <td class="qp-center qp-bold">{{ item.unit }}</td>
                                        <td class="qp-spec qp-preline">
                                            <div
                                                v-for="(specLine, sIdx) in formatSpecLines(item.specifications)"
                                                :key="sIdx"
                                                class="qp-spec-line"
                                            >
                                                {{ specLine }}
                                            </div>
                                        </td>
                                        <td class="qp-center qp-bold qp-nowrap">{{ formatVnd(item.unitPrice) }}</td>
                                        <td class="qp-center qp-bold qp-nowrap">{{ formatVnd(item.quantity) }}</td>
                                        <td class="qp-center qp-bold qp-nowrap">{{ formatVnd(rowAmount(item)) }}</td>
                                    </tr>
                                    <tr class="qp-summary-row">
                                        <td colspan="7" class="qp-summary-label">
                                            TỔNG CỘNG (chưa bao gồm thuế VAT) - <em>Sub Total (not included VAT)</em>
                                        </td>
                                        <td></td>
                                        <td class="qp-center qp-nowrap">{{ formatVnd(subTotal) }}</td>
                                    </tr>
                                    <tr class="qp-summary-row">
                                        <td colspan="7" class="qp-summary-label">
                                            Thuế VAT ({{ formattedVatLabel }})
                                        </td>
                                        <td></td>
                                        <td class="qp-center qp-nowrap">{{ formatVnd(vatAmount) }}</td>
                                    </tr>
                                    <tr class="qp-summary-row">
                                        <td colspan="7" class="qp-summary-label">
                                            TỔNG CỘNG (bao gồm thuế VAT) - <em>Sub Total (included VAT)</em>
                                        </td>
                                        <td></td>
                                        <td class="qp-center qp-nowrap">{{ formatVnd(grandTotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="qp-terms">
                                <div class="qp-terms-head">Điều kiện thương mại:</div>
                                <p>1. Báo giá có hiệu lực đến ngày: {{ formattedValidUntil }}</p>
                                <p>2. Phương thức thanh toán: Thỏa Thuận</p>
                                <p>3. Phương thức giao hàng: đến chân công trình</p>
                                <p>4. Thời gian giao hàng: 40 ngày đối với hàng trong nước, 90 ngày đối với hàng nhập khẩu kể từ ngày đơn đặt hàng được xác nhận</p>
                                <p>5. Bảo hành: 2 năm linh kiện điện tử, 3 năm với máy nén Theo chính sách bảo hành của Ariston Việt Nam</p>
                                <p>6. Chúng tôi hy vọng báo giá này sẽ làm hài lòng Quý Công ty. Mọi thắc mắc xin vui lòng liên lạc với chúng tôi</p>
                                <p>Rất mong nhận được sự ủng hộ từ Quý Công ty.</p>
                            </div>

                            <div class="qp-sign">
                                <p>Many thanks &amp; Best regards</p>
                                <p v-if="quoteForm.signerName.trim()">{{ quoteForm.signerName }}</p>
                                <p v-if="quoteForm.signerPhone.trim()">HP: {{ quoteForm.signerPhone }}</p>
                                <p v-if="quoteForm.signerEmail.trim()">Email : {{ quoteForm.signerEmail }}</p>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="kb-quote-builder-card">
                        <div class="kb-quote-section-title">
                            1. Thông tin chung &amp; Điều kiện thương mại (Báo giá Bảo ôn K-Flex)
                        </div>
                        <div class="kb-quote-form-grid">
                            <label class="kb-quote-field">
                                <span>Tiêu đề báo giá</span>
                                <input
                                    v-model="kflexQuoteForm.documentTitle"
                                    type="text"
                                    placeholder="BÁO GIÁ BẢO ÔN K-FLEX"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Kính gửi</span>
                                <input
                                    v-model="kflexQuoteForm.recipientCompany"
                                    type="text"
                                    placeholder="VD: Quý đối tác Công ty Cổ phần Kỹ thuật Thăng Tiến"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Người nhận</span>
                                <input
                                    v-model="kflexQuoteForm.recipientPerson"
                                    type="text"
                                    placeholder="VD: Mr.Phương"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Project (Dự án)</span>
                                <input
                                    v-model="kflexQuoteForm.projectName"
                                    type="text"
                                    placeholder="VD: Detech Tower"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Thời gian báo giá</span>
                                <input v-model="kflexQuoteForm.quoteDate" type="date" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Hiệu lực đến ngày</span>
                                <input v-model="kflexQuoteForm.validUntilDate" type="date" />
                            </label>
                            <label class="kb-quote-field">
                                <span>Thuế VAT (%)</span>
                                <input
                                    v-model.number="kflexQuoteForm.vatPercent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="1"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Người liên hệ (Ký tên)</span>
                                <input
                                    v-model="kflexQuoteForm.contactName"
                                    type="text"
                                    placeholder="Mr.Kiệt"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Số điện thoại (HP)</span>
                                <input
                                    v-model="kflexQuoteForm.contactHp"
                                    type="text"
                                    placeholder="0979.807.234"
                                />
                            </label>
                            <label class="kb-quote-field">
                                <span>Email liên hệ</span>
                                <input
                                    v-model="kflexQuoteForm.contactEmail"
                                    type="text"
                                    placeholder="tuankiet@starlightgroup.vn"
                                />
                            </label>
                            <label class="kb-quote-field kb-quote-span-2">
                                <span>Dòng giới thiệu (In nghiêng xanh dưới thông tin dự án)</span>
                                <input
                                    v-model="kflexQuoteForm.introText"
                                    type="text"
                                />
                            </label>
                            <label class="kb-quote-field kb-quote-span-2">
                                <span>2. Phương thức thanh toán</span>
                                <input
                                    v-model="kflexQuoteForm.paymentTerms"
                                    type="text"
                                />
                            </label>
                            <label class="kb-quote-field kb-quote-span-2">
                                <span>3. Phương thức giao hàng</span>
                                <input
                                    v-model="kflexQuoteForm.deliveryMethod"
                                    type="text"
                                />
                            </label>
                            <label class="kb-quote-field kb-quote-span-full">
                                <span>4. Thời gian giao hàng</span>
                                <input
                                    v-model="kflexQuoteForm.deliveryTime"
                                    type="text"
                                />
                            </label>

                            <div class="kb-quote-field kb-quote-span-full kflex-global-brand-box">
                                <div class="kflex-global-brand-head">
                                    <div>
                                        <strong>Nhãn hiệu (Dòng sản phẩm) &amp; Xuất xứ áp dụng cho báo giá</strong>
                                        <p>
                                            Thường chỉ chọn 1 loại cho toàn bộ file báo giá (Xuất xứ tự động đi theo Nhãn hiệu). Nếu khách yêu cầu báo giá kết hợp 2 hoặc 3 loại, hãy tích chọn thêm bên dưới.
                                        </p>
                                    </div>
                                    <div class="kflex-global-brand-quick">
                                        <button
                                            v-for="opt in kflexAllProductLineOptions"
                                            :key="`single-${opt.key}`"
                                            type="button"
                                            class="kflex-quick-line-btn"
                                            :class="{
                                                active:
                                                    kflexSelectedProductLines.length === 1 &&
                                                    kflexSelectedProductLines[0] === opt.key,
                                            }"
                                            @click="selectSingleKflexProductLine(opt.key)"
                                        >
                                            Chỉ dùng {{ opt.brandLabel }} ({{ opt.originLabel }})
                                        </button>
                                    </div>
                                </div>

                                <div class="kflex-global-brand-pills">
                                    <label
                                        v-for="opt in kflexAllProductLineOptions"
                                        :key="opt.key"
                                        class="kflex-brand-pill"
                                        :class="{ checked: kflexSelectedProductLines.includes(opt.key) }"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="kflexSelectedProductLines.includes(opt.key)"
                                            @change="toggleKflexProductLine(opt.key)"
                                        />
                                        <div class="kflex-brand-pill-info">
                                            <strong>{{ opt.brandLabel }}</strong>
                                            <small>Thương hiệu - Xuất xứ: {{ opt.originLabel }}</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kb-quote-builder-card">
                        <div class="kb-quote-items-head">
                            <div>
                                <div class="kb-quote-section-title">
                                    2. Nhóm hạng mục &amp; Vật tư Bảo ôn K-Flex ({{ kflexGroups.length }} nhóm — {{ kflexTotalItemsCount }} dòng vật tư)
                                </div>
                                <small class="kb-quote-section-sub">
                                    Mỗi <strong>Nhóm hạng mục</strong> có thể bật/tắt tiêu đề nhóm (hoặc để trống nếu không chia nhóm) và chứa nhiều loại bảo ôn khác nhau. <strong>Tên vật tư</strong> và <strong>Đơn giá</strong> tự động lấy chuẩn theo bảng giá đã lưu.
                                </small>
                            </div>
                            <div class="kb-quote-items-actions">
                                <button
                                    v-for="preset in kflexSectionPresets"
                                    :key="preset"
                                    type="button"
                                    class="q-btn"
                                    @click="addKflexGroup(preset)"
                                >
                                    ＋ {{ preset }}
                                </button>
                                <button
                                    type="button"
                                    class="q-btn"
                                    @click="addKflexGroup('')"
                                >
                                    ＋ Nhóm không tiêu đề
                                </button>
                                <button
                                    type="button"
                                    class="q-btn primary"
                                    @click="addKflexGroup('BẢO ÔN CHO ĐƯỜNG THÉP')"
                                >
                                    ＋ Thêm Nhóm hạng mục
                                </button>
                            </div>
                        </div>

                        <datalist id="kflex-section-presets">
                            <option v-for="sec in kflexSectionPresets" :key="sec" :value="sec" />
                        </datalist>

                        <div class="kflex-groups-container">
                            <div
                                v-for="(group, gIdx) in kflexGroups"
                                :key="group.uid"
                                class="kflex-group-card"
                            >
                                <div class="kflex-group-bar">
                                    <div class="kflex-group-bar-left">
                                        <span class="kflex-group-badge">Nhóm #{{ gIdx + 1 }}</span>

                                        <label class="kflex-group-toggle">
                                            <input v-model="group.hasTitle" type="checkbox" />
                                            <span>Có tiêu đề nhóm hạng mục</span>
                                        </label>

                                        <input
                                            v-if="group.hasTitle"
                                            v-model="group.title"
                                            type="text"
                                            list="kflex-section-presets"
                                            class="kflex-group-title-input"
                                            placeholder="Nhập tên nhóm (VD: BẢO ÔN CHO ĐƯỜNG THÉP) — để trống nếu không hiện dòng tiêu đề..."
                                        />
                                        <span v-else class="kflex-group-no-title-hint">
                                            (Không hiển thị dòng tiêu đề nhóm trên báo giá)
                                        </span>
                                    </div>

                                    <div class="kflex-group-bar-right">
                                        <button
                                            type="button"
                                            class="q-btn primary kflex-btn-sm"
                                            @click="addKflexItemToGroup(group)"
                                        >
                                            ＋ Thêm loại bảo ôn vào nhóm
                                        </button>
                                        <button
                                            v-if="kflexGroups.length > 1"
                                            type="button"
                                            class="q-btn danger kflex-btn-sm"
                                            @click="removeKflexGroup(gIdx)"
                                        >
                                            Xóa nhóm
                                        </button>
                                    </div>
                                </div>

                                <div class="kflex-entry-table-wrap">
                                    <table class="kflex-entry-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 38px">#</th>
                                                <th style="min-width: 245px">Tên vật tư (Chuẩn theo bảng giá đã lưu)</th>
                                                <th style="width: 138px">Nhãn hiệu</th>
                                                <th style="width: 96px">Thương hiệu<br />- Xuất xứ</th>
                                                <th style="width: 82px">Hình thức</th>
                                                <th style="width: 148px">Loại sản phẩm</th>
                                                <th style="width: 78px">Chiều dày<br />(mm)</th>
                                                <th style="width: 78px">ID<br />(mm)</th>
                                                <th style="width: 68px">Chiều rộng<br />(m)</th>
                                                <th style="width: 68px">Chiều dài<br />(m)</th>
                                                <th style="width: 82px">Khối lượng<br />(m / m2)</th>
                                                <th style="width: 105px">Đơn giá<br />Unit Price</th>
                                                <th style="width: 112px">Tổng<br />Total</th>
                                                <th style="width: 165px">Ghi chú</th>
                                                <th style="width: 68px">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in group.items" :key="item.uid">
                                                <td class="kflex-entry-tt">{{ idx + 1 }}</td>

                                                <td>
                                                    <input
                                                        v-model="item.itemName"
                                                        type="text"
                                                        class="kflex-cell-input kflex-cell-name"
                                                        placeholder="Tên vật tư theo bảng giá..."
                                                    />
                                                </td>

                                                <td class="kflex-cell-center">
                                                    <select
                                                        v-if="kflexSelectedProductLines.length > 1"
                                                        v-model="item.productLine"
                                                        class="kflex-cell-select"
                                                        @change="onKflexLineOrFormChange(item)"
                                                    >
                                                        <option
                                                            v-for="opt in kflexAllProductLineOptions.filter((o) =>
                                                                kflexSelectedProductLines.includes(o.key),
                                                            )"
                                                            :key="opt.key"
                                                            :value="opt.key"
                                                        >
                                                            {{ opt.brandLabel }}
                                                        </option>
                                                    </select>
                                                    <span v-else class="kflex-readonly-chip">
                                                        {{ item.brandLabel }}
                                                    </span>
                                                </td>

                                                <td class="kflex-cell-center">
                                                    <span class="kflex-readonly-origin">
                                                        {{ item.originLabel }}
                                                    </span>
                                                </td>

                                                <td>
                                                    <select
                                                        v-model="item.formType"
                                                        class="kflex-cell-select"
                                                        @change="onKflexLineOrFormChange(item)"
                                                    >
                                                        <option value="tube">Ống</option>
                                                        <option value="sheet">Tấm</option>
                                                    </select>
                                                </td>

                                                <td>
                                                    <select
                                                        v-model="item.variantType"
                                                        class="kflex-cell-select"
                                                        @change="syncKflexRowFromDatabase(item)"
                                                    >
                                                        <template v-if="item.formType === 'tube'">
                                                            <option value="alu">Có nhôm</option>
                                                            <option value="foam">Chỉ Foam</option>
                                                        </template>
                                                        <template v-else>
                                                            <option value="alu_or_glue">Có nhôm, không keo</option>
                                                            <option value="alu_and_glue">Có cả keo và nhôm</option>
                                                            <option value="foam">Chỉ Foam</option>
                                                        </template>
                                                    </select>
                                                </td>

                                                <td>
                                                    <select
                                                        v-if="getAvailableThicknessesForRow(item).length > 0"
                                                        v-model="item.thickness"
                                                        class="kflex-cell-select kflex-cell-center"
                                                        @change="syncKflexRowFromDatabase(item)"
                                                    >
                                                        <option
                                                            v-for="t in getAvailableThicknessesForRow(item)"
                                                            :key="t"
                                                            :value="t"
                                                        >
                                                            {{ t }}
                                                        </option>
                                                    </select>
                                                    <input
                                                        v-else
                                                        v-model="item.thickness"
                                                        type="text"
                                                        class="kflex-cell-input kflex-cell-center"
                                                        placeholder="25"
                                                        @change="syncKflexRowFromDatabase(item)"
                                                    />
                                                </td>

                                                <td>
                                                    <template v-if="item.formType === 'tube'">
                                                        <select
                                                            v-if="getAvailableDiametersForRow(item).length > 0"
                                                            v-model="item.innerDiameter"
                                                            class="kflex-cell-select kflex-cell-center"
                                                            @change="syncKflexRowFromDatabase(item)"
                                                        >
                                                            <option
                                                                v-for="d in getAvailableDiametersForRow(item)"
                                                                :key="d"
                                                                :value="d"
                                                            >
                                                                {{ d }}
                                                            </option>
                                                        </select>
                                                        <input
                                                            v-else
                                                            v-model="item.innerDiameter"
                                                            type="text"
                                                            class="kflex-cell-input kflex-cell-center"
                                                            placeholder="22"
                                                            @change="syncKflexRowFromDatabase(item)"
                                                        />
                                                    </template>
                                                    <input
                                                        v-else
                                                        v-model="item.innerDiameter"
                                                        type="text"
                                                        class="kflex-cell-input kflex-cell-center"
                                                        placeholder="-"
                                                    />
                                                </td>

                                                <td>
                                                    <input
                                                        v-model="item.widthM"
                                                        type="text"
                                                        class="kflex-cell-input kflex-cell-center"
                                                        placeholder="1.2"
                                                    />
                                                </td>

                                                <td>
                                                    <input
                                                        v-model="item.lengthM"
                                                        type="text"
                                                        class="kflex-cell-input kflex-cell-center"
                                                        :placeholder="item.formType === 'tube' && item.productLine === 'm_flex' ? '1.83' : '2'"
                                                    />
                                                </td>

                                                <td>
                                                    <input
                                                        v-if="item.formType === 'tube'"
                                                        v-model.number="item.qtyM"
                                                        type="number"
                                                        min="0"
                                                        step="any"
                                                        inputmode="decimal"
                                                        class="kflex-cell-input kflex-cell-center kb-quote-no-spin"
                                                        placeholder="m"
                                                        title="Khối lượng (m)"
                                                        @wheel.prevent="($event.target as HTMLInputElement).blur()"
                                                    />
                                                    <input
                                                        v-else
                                                        v-model.number="item.qtyM2"
                                                        type="number"
                                                        min="0"
                                                        step="any"
                                                        inputmode="decimal"
                                                        class="kflex-cell-input kflex-cell-center kb-quote-no-spin"
                                                        placeholder="m2"
                                                        title="Khối lượng (m2)"
                                                        @wheel.prevent="($event.target as HTMLInputElement).blur()"
                                                    />
                                                </td>

                                                <td>
                                                    <input
                                                        v-model.number="item.unitPrice"
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        inputmode="numeric"
                                                        class="kflex-cell-input kflex-cell-right kb-quote-no-spin"
                                                        @wheel.prevent="($event.target as HTMLInputElement).blur()"
                                                    />
                                                </td>

                                                <td class="kflex-entry-total">
                                                    {{ formatKflexTotalNum(kflexRowAmount(item)) }}
                                                </td>

                                                <td>
                                                    <input
                                                        v-model="item.note"
                                                        type="text"
                                                        class="kflex-cell-input"
                                                        placeholder="Ghi chú (tự gộp ô nếu trùng)..."
                                                    />
                                                </td>

                                                <td class="kflex-entry-actions">
                                                    <button
                                                        type="button"
                                                        class="kflex-icon-btn"
                                                        title="Nhân bản dòng này"
                                                        @click="duplicateKflexItemInGroup(group, idx)"
                                                    >
                                                        +
                                                    </button>
                                                    <button
                                                        v-if="group.items.length > 1 || kflexGroups.length > 1"
                                                        type="button"
                                                        class="kflex-icon-btn danger"
                                                        title="Xóa dòng này"
                                                        @click="removeKflexItemFromGroup(group, idx)"
                                                    >
                                                        ×
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kb-quote-preview-wrap">
                        <div class="kb-quote-preview-toolbar">
                            <strong>
                                Xem trước bản in Báo giá Bảo ôn K-Flex (Khổ A4 Đứng — {{ kflexTotalItemsCount }} hạng mục)
                            </strong>
                            <div class="kb-quote-preview-actions">
                                <button class="q-btn kflex-excel-btn" type="button" @click="exportKflexQuotationExcel">
                                    📊 Xuất Excel Báo Giá K-Flex
                                </button>
                                <button class="q-btn primary" type="button" @click="printKflexQuotationPdf">
                                    🖨️ In / Xuất PDF Báo Giá K-Flex (A4)
                                </button>
                            </div>
                        </div>

                        <div class="kb-quote-paper kflex-quote-paper">
                            <header class="qp-header kflex-qp-header">
                                <div class="qp-logo-left">
                                    <img :src="starLightLogoUrl" alt="STAR LIGHT" class="qp-starlight-logo" />
                                </div>
                                <div class="qp-company-center">
                                    <h1>CÔNG TY CỔ PHẦN QUỐC TẾ STAR LIGHT</h1>
                                    <p>Địa chỉ VP: Số 15 Nguyễn Cảnh Dị, Định Công, Hà Nội</p>
                                    <p>Điện thoại: 0243 201 8322 - Hotline: 0979 541 345</p>
                                    <p>www: starlightgroup.vn</p>
                                </div>
                                <div class="qp-logo-right">
                                    <img :src="kflexLogoUrl" alt="K-FLEX" class="qp-kflex-logo" />
                                </div>
                            </header>

                            <div class="kflex-qp-title">{{ kflexQuoteForm.documentTitle }}</div>

                            <div class="kflex-qp-meta">
                                <div class="kflex-qp-meta-row">
                                    <span class="kflex-qp-kinhgui-label">Kính gửi:</span>
                                    <span class="kflex-qp-kinhgui-val">{{ kflexQuoteForm.recipientCompany }}</span>
                                </div>
                                <div class="kflex-qp-meta-row">
                                    <span class="kflex-qp-meta-label-sub">Người nhận:</span>
                                    <span class="kflex-qp-italic">{{ kflexQuoteForm.recipientPerson }}</span>
                                </div>
                                <div class="kflex-qp-meta-row">
                                    <span class="kflex-qp-meta-label-sub">Project:</span>
                                    <span class="kflex-qp-italic">{{ kflexQuoteForm.projectName }}</span>
                                </div>
                                <div class="kflex-qp-meta-row">
                                    <span class="kflex-qp-meta-label-sub">Thời gian:</span>
                                    <span class="kflex-qp-italic">{{ kflexFormattedQuoteDate }}</span>
                                </div>
                            </div>

                            <div class="kflex-qp-intro">
                                {{ kflexQuoteForm.introText }}
                            </div>

                            <table class="kflex-qp-table">
                                <colgroup>
                                    <col style="width: 3.8%" />
                                    <col style="width: 16.0%" />
                                    <col style="width: 6.8%" />
                                    <col style="width: 8.4%" />
                                    <col style="width: 7.0%" />
                                    <col style="width: 4.2%" />
                                    <col style="width: 4.0%" />
                                    <col style="width: 4.2%" />
                                    <col style="width: 4.2%" />
                                    <col style="width: 4.8%" />
                                    <col style="width: 4.8%" />
                                    <col style="width: 9.2%" />
                                    <col style="width: 11.2%" />
                                    <col style="width: 11.4%" />
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th rowspan="2">TT</th>
                                        <th rowspan="2">Tên vật tư</th>
                                        <th rowspan="2">Nhãn hiệu</th>
                                        <th rowspan="2">Thương hiệu - Xuất xứ</th>
                                        <th rowspan="2">Loại sản phẩm</th>
                                        <th colspan="4">Kích thước</th>
                                        <th colspan="2">Khối lượng</th>
                                        <th rowspan="2">Đơn giá<br />Unit Price</th>
                                        <th rowspan="2">Tổng<br />Total</th>
                                        <th rowspan="2">Ghi chú</th>
                                    </tr>
                                    <tr>
                                        <th>Chiều dày<br />(mm)</th>
                                        <th>ID (mm)</th>
                                        <th>Chiều rộng<br />(m)</th>
                                        <th>Chiều dài<br />(m)</th>
                                        <th>m</th>
                                        <th>m2</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="row in kflexRenderRows" :key="row.key">
                                        <tr v-if="row.rowType === 'section'" class="kflex-qp-sec-row">
                                            <td></td>
                                            <td colspan="13" class="kflex-qp-sec-name">{{ row.sectionTitle }}</td>
                                        </tr>
                                        <tr v-else>
                                            <td class="qp-center">{{ row.tt }}</td>
                                            <td class="kflex-qp-item-name">{{ row.item.itemName }}</td>
                                            <td class="qp-center">{{ row.item.brandLabel }}</td>
                                            <td class="qp-center">{{ row.item.originLabel }}</td>
                                            <td class="qp-center qp-preline">{{ row.item.productTypeLabel }}</td>
                                            <td class="qp-center">{{ row.item.thickness }}</td>
                                            <td class="qp-center">{{ row.item.innerDiameter }}</td>
                                            <td class="qp-center">{{ row.item.widthM }}</td>
                                            <td class="qp-center">{{ row.item.lengthM }}</td>
                                            <td class="qp-center qp-nowrap">{{ formatKflexNum(row.item.qtyM) }}</td>
                                            <td class="qp-center qp-nowrap">{{ formatKflexNum(row.item.qtyM2) }}</td>
                                            <td class="qp-center qp-nowrap">{{ formatKflexTotalNum(row.item.unitPrice) }}</td>
                                            <td class="kflex-qp-right qp-bold qp-nowrap">
                                                {{ formatKflexTotalNum(kflexRowAmount(row.item)) }}
                                            </td>
                                            <td
                                                v-if="row.noteRowspan > 0"
                                                :rowspan="row.noteRowspan > 1 ? row.noteRowspan : undefined"
                                                class="qp-center qp-preline kflex-qp-note"
                                            >
                                                {{ row.item.note }}
                                            </td>
                                        </tr>
                                    </template>

                                    <tr class="kflex-qp-summary-row">
                                        <td colspan="12" class="qp-center qp-bold">
                                            TỔNG CỘNG (chưa bao gồm thuế VAT) - Sub Total (Not included VAT)
                                        </td>
                                        <td class="kflex-qp-summary-val">
                                            {{ formatKflexTotalNum(kflexSubTotal) }}
                                        </td>
                                        <td class="kflex-qp-no-border"></td>
                                    </tr>
                                    <tr class="kflex-qp-summary-row">
                                        <td colspan="12" class="qp-center qp-bold">
                                            Thuế VAT ({{ kflexFormattedVatLabel }})
                                        </td>
                                        <td class="kflex-qp-summary-val">
                                            {{ formatKflexTotalNum(kflexVatAmount) }}
                                        </td>
                                        <td class="kflex-qp-no-border"></td>
                                    </tr>
                                    <tr class="kflex-qp-summary-row">
                                        <td colspan="12" class="qp-center qp-bold">
                                            TỔNG CỘNG (bao gồm thuế VAT) - Sub Total (included VAT)
                                        </td>
                                        <td class="kflex-qp-summary-val">
                                            {{ formatKflexTotalNum(kflexGrandTotal) }}
                                        </td>
                                        <td class="kflex-qp-no-border"></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="kflex-qp-terms">
                                <div class="kflex-qp-terms-head">Điều kiện thương mại:</div>
                                <p>1. Báo giá có hiệu lực đến ngày: {{ kflexFormattedValidUntil }}</p>
                                <p>2. Phương thức thanh toán: {{ kflexQuoteForm.paymentTerms }}</p>
                                <p>3. Phương thức giao hàng: {{ kflexQuoteForm.deliveryMethod }}</p>
                                <p>4. Thời gian giao hàng: {{ kflexQuoteForm.deliveryTime }}</p>
                                <p>
                                    5. Chúng tôi hy vọng báo giá này sẽ làm hài lòng Quý Công ty. Mọi thắc mắc xin vui lòng
                                    liên lạc với chúng tôi
                                </p>
                                <p>Rất mong nhận được sự ủng hộ từ Quý Công ty.</p>
                            </div>

                            <div class="kflex-qp-sign">
                                <div class="kflex-qp-sign-grid">
                                    <span></span>
                                    <span class="kflex-qp-sign-thanks">Many thanks &amp; Best regards</span>
                                    <span class="kflex-qp-sign-name">{{ kflexQuoteForm.contactName }}</span>
                                    <span class="kflex-qp-sign-label">HP</span>
                                    <span class="kflex-qp-sign-hp">{{ kflexQuoteForm.contactHp }}</span>
                                    <span class="kflex-qp-sign-label">Email :</span>
                                    <span class="kflex-qp-sign-email">{{ kflexQuoteForm.contactEmail }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </UserLayout>
</template>
<style scoped>
.kb-user-quote-section {
    --q-blue: #b59a65;
    --q-blue2: #f1e8d6;
    --q-ink: #29271f;
    --q-muted: #888272;
    --q-line: #e6dfd1;
    --q-bg: #f5f2eb;
    --q-surface: #fffdf8;
    --q-gold: #b59a65;
    --q-serif: 'Playfair Display', Cambria, Georgia, serif;
    padding: 28px 0 64px;
    background: var(--paper);
}

.kb-user-quote-container {
    width: min(1640px, calc(100% - 36px));
    margin: 0 auto;
}

.page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.page-head h2 {
    color: var(--ink);
    font-family: var(--q-serif);
    font-size: 29px;
    font-weight: 500;
    letter-spacing: -0.025em;
    line-height: 1.2;
    margin: 0 0 4px;
}

.page-head p {
    font-size: 13.5px;
    color: var(--muted);
    margin: 0;
}

.actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.kb-quote-user-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 6px 5px 12px;
    border: 1px solid var(--q-line);
    border-radius: 8px;
    background: var(--q-surface);
    color: var(--q-ink);
    font-size: 12.5px;
    font-weight: 600;
}

.q-btn {
    border: 1px solid #d8cfbd;
    border-radius: 6px;
    background: var(--q-surface);
    color: #5f5641;
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-decoration: none;
    transition: 0.2s;
    cursor: pointer;
}

.q-btn:hover {
    border-color: #b59a65;
    color: #8e7443;
    background: #f6f1e7;
}

.q-btn.primary {
    border-color: var(--q-blue);
    background: var(--q-blue);
    color: #ffffff;
}

.q-btn.primary:hover {
    background: #9d8250;
    color: #ffffff;
}

.q-btn.danger:hover {
    border-color: #efc3c3;
    background: #fff5f5;
    color: #b94242;
}

.mini {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    border: 1px solid #d8cfbd;
    background: var(--q-surface);
    border-radius: 5px;
    padding: 0 11px;
    font-size: 12px;
    color: #5f5641;
    font-weight: 700;
    transition: border-color 0.2s, background 0.2s, color 0.2s;
    cursor: pointer;
}

.mini:hover {
    border-color: #b59a65;
    color: #8e7443;
    background: #f6f1e7;
}

.mini.danger:hover {
    border-color: #efc3c3;
    background: #fff5f5;
    color: #b94242;
}

/* Quotation Builder & Live Print Preview */
.kb-quote-type-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.kb-quote-tab-btn {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 14px 18px;
    border-radius: 12px;
    border: 1px solid var(--q-line);
    background: var(--q-surface);
    color: var(--q-ink);
    text-align: left;
    transition: all 0.2s ease;
}

.kb-quote-tab-btn:hover {
    border-color: var(--q-gold);
}

.kb-quote-tab-btn.active {
    background: linear-gradient(135deg, #18352e 0%, #23473b 100%);
    border-color: #18352e;
    color: #f5f1e8;
    box-shadow: 0 10px 24px rgba(24, 53, 46, 0.16);
}

.kb-quote-tab-badge {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 12px;
    background: var(--q-blue2);
    color: #5b4b2a;
    flex-shrink: 0;
}

.kb-quote-tab-btn.active .kb-quote-tab-badge {
    background: var(--q-gold);
    color: #171714;
}

.kb-quote-tab-btn strong {
    display: block;
    font-size: 14px;
    margin-bottom: 2px;
}

.kb-quote-tab-btn small {
    display: block;
    font-size: 11.5px;
    color: var(--q-muted);
}

.kb-quote-tab-btn.active small {
    color: #c8d8ce;
}

.kb-quote-builder-card {
    background: var(--q-surface);
    color: var(--q-ink);
    border: 1px solid var(--q-line);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 18px;
}

.kb-quote-section-title {
    font-weight: 800;
    font-size: 15px;
    color: var(--q-ink);
    margin-bottom: 14px;
}

.kb-quote-form-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.kb-quote-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.kb-quote-field > span {
    font-size: 11.5px;
    font-weight: 700;
    color: var(--q-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.kb-quote-field input,
.kb-quote-field select,
.kb-quote-field textarea {
    width: 100%;
    border: 1px solid var(--q-line);
    border-radius: 8px;
    background: #ffffff;
    color: var(--q-ink);
    padding: 9px 12px;
    font-size: 13px;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.kb-quote-field input:focus,
.kb-quote-field select:focus,
.kb-quote-field textarea:focus {
    border-color: var(--q-gold);
    box-shadow: 0 0 0 3px rgba(181, 154, 101, 0.16);
}

.kb-quote-items-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.kb-quote-items-head .kb-quote-section-title {
    margin-bottom: 2px;
}

.kb-quote-section-sub {
    display: block;
    font-size: 12px;
    color: var(--q-muted);
}

.kb-quote-catalog-search {
    border: 1px solid var(--q-line);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 12.5px;
    background: #ffffff;
    color: var(--q-ink);
    min-width: 200px;
}

.kb-quote-catalog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 10px;
    margin-bottom: 18px;
    padding: 12px;
    border-radius: 12px;
    background: var(--q-bg);
    border: 1px solid var(--q-line);
    max-height: 250px;
    overflow-y: auto;
}

.kb-quote-catalog-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid var(--q-line);
    background: #ffffff;
    text-align: left;
    transition: all 0.18s ease;
}

.kb-quote-catalog-card:hover {
    border-color: var(--q-gold);
}

.kb-quote-catalog-card.selected {
    border-color: #18352e;
    background: #eef4f1;
    box-shadow: 0 4px 12px rgba(24, 53, 46, 0.08);
}

.kb-quote-catalog-check {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    border: 1px solid var(--q-line);
    background: var(--q-bg);
    color: var(--q-muted);
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 800;
    flex-shrink: 0;
}

.kb-quote-catalog-card.selected .kb-quote-catalog-check {
    background: #18352e;
    border-color: #18352e;
    color: #ffffff;
}

.kb-quote-catalog-thumb {
    width: 38px;
    height: 32px;
    object-fit: contain;
    flex-shrink: 0;
}

.kb-quote-catalog-meta {
    min-width: 0;
    flex: 1;
}

.kb-quote-catalog-meta strong {
    display: block;
    font-size: 12.5px;
    color: var(--q-ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kb-quote-catalog-meta small {
    display: block;
    font-size: 11px;
    color: var(--q-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kb-quote-item-compact-row {
    display: grid;
    grid-template-columns: 34px 46px 1.3fr 1.2fr 0.9fr 0.7fr 1fr auto;
    gap: 10px;
    align-items: end;
}

.kb-quote-item-index {
    height: 39px;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 12px;
    color: var(--q-muted);
    background: #ffffff;
    border: 1px solid var(--q-line);
    border-radius: 8px;
}

.kb-quote-compact-btns {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 39px;
}

.kb-quote-item-expanded {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--q-line);
}

.kb-quote-items-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.kb-quote-items-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.kb-quote-item-box {
    border: 1px solid var(--q-line);
    border-radius: 12px;
    background: var(--q-bg);
    padding: 16px;
}

.kb-quote-item-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.kb-quote-span-2 {
    grid-column: span 2;
}

.kb-quote-span-full {
    grid-column: 1 / -1;
}

.kb-quote-computed-amount {
    height: 39px;
    display: flex;
    align-items: center;
    padding: 0 12px;
    border-radius: 8px;
    border: 1px solid #d5c7a9;
    background: #f8f2e4;
    font-weight: 800;
    color: #413418;
    font-size: 13.5px;
}

.kb-quote-img-picker {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 39px;
}

.kb-quote-thumb-preview {
    width: 46px;
    height: 38px;
    object-fit: contain;
    border: 1px solid var(--q-line);
    border-radius: 6px;
    background: #ffffff;
    padding: 2px;
}

.kb-quote-no-img {
    font-size: 12px;
    color: var(--q-muted);
}

.kb-quote-upload-btn {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
}

.kb-quote-preview-wrap {
    background: #e6e2d8;
    color: #29271f;
    border: 1px solid var(--q-line);
    border-radius: 14px;
    padding: 18px;
    overflow-x: auto;
}

.kb-quote-preview-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.kb-quote-paper {
    width: 1080px;
    min-width: 1080px;
    margin: 0 auto;
    background: #ffffff;
    color: #000000;
    padding: 42px 58px;
    box-shadow: 0 14px 36px rgba(0, 0, 0, 0.12);
    font-family: 'Times New Roman', Times, serif;
    font-size: 13px;
    line-height: 1.3;
    transition: width 0.22s ease;
}

.kb-quote-paper.is-portrait {
    width: 820px;
    min-width: 820px;
    padding: 44px 52px;
}

.qp-header {
    display: grid;
    grid-template-columns: 190px 1fr 190px;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.kb-quote-paper.is-portrait .qp-header {
    grid-template-columns: 145px 1fr 145px;
}

.qp-logo-left {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding-left: 8px;
}

.qp-starlight-logo {
    width: 112px;
    height: auto;
    object-fit: contain;
    display: block;
}

.qp-company-center {
    text-align: center;
    line-height: 1.28;
}

.qp-company-center h1 {
    margin: 0 0 2px;
    font-size: 17px;
    font-weight: 700;
    text-transform: uppercase;
    font-family: 'Times New Roman', Times, serif;
}

.qp-company-center p {
    margin: 0;
    font-size: 14.5px;
}

.qp-logo-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 8px;
}

.qp-ariston-logo {
    width: 180px;
    height: auto;
    object-fit: contain;
    display: block;
}

.kb-quote-paper.is-portrait .qp-ariston-logo {
    width: 140px;
}

.qp-title {
    text-align: center;
    font-size: 18px;
    font-weight: 700;
    text-decoration: underline;
    text-transform: uppercase;
    margin: 10px 0 10px;
}

.qp-meta {
    width: 100%;
    margin-bottom: 5px;
    font-size: 13px;
}

.qp-meta-row {
    display: grid;
    grid-template-columns: 9.5% 1fr;
    column-gap: 4px;
    margin-bottom: 3px;
    align-items: baseline;
}

.qp-meta-row > span {
    min-width: 0;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.qp-meta-label-kinhgui {
    font-weight: 700;
    font-style: italic;
    text-decoration: underline;
}

.qp-meta-val-kinhgui {
    text-transform: uppercase;
}

.qp-meta-label-italic {
    font-style: italic;
    font-size: 11.5px;
    line-height: 1.2;
}

.qp-meta-val-italic {
    font-style: italic;
}

.qp-meta-label-project {
    font-weight: 700;
    font-style: italic;
}

.qp-intro {
    text-align: center;
    font-style: italic;
    font-size: 12.8px;
    margin: 5px 0 5px;
}

.qp-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 12px;
}

.qp-table th,
.qp-table td {
    border: 1px solid #000000;
    padding: 5px 6px;
    vertical-align: middle;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    word-break: break-word;
}

.qp-table thead th {
    text-align: center;
    font-weight: 700;
    font-size: 12px;
    line-height: 1.22;
    padding: 6px 4px;
}

.qp-table thead th em {
    display: block;
    font-style: italic;
    font-weight: 400;
}

.qp-center {
    text-align: center;
}

.qp-bold {
    font-weight: 700;
}

.qp-table td.qp-preline,
.kflex-qp-table td.qp-preline,
.qp-preline {
    white-space: pre-line;
}

.qp-spec {
    text-align: left;
    vertical-align: middle;
    white-space: pre-line;
    font-size: 13.5px;
    line-height: 1.3;
    padding: 6px 8px;
}

.qp-spec-line {
    display: block;
    margin: 0;
}

.qp-prod-img {
    width: 98%;
    max-width: 100%;
    height: 178px;
    max-height: 188px;
    object-fit: contain;
    display: block;
    margin: 0 auto;
}

.qp-summary-row td {
    font-weight: 700;
    padding: 4.5px 6px;
    font-size: 12px;
}

.qp-summary-label {
    text-align: center;
}

.qp-summary-label em {
    font-style: italic;
    font-weight: 700;
}

.qp-terms {
    margin-top: 5px;
    font-size: 11.5px;
    line-height: 1.3;
    font-weight: 700;
}

.qp-terms-head {
    text-decoration: underline;
    margin-bottom: 1px;
}

.qp-terms p {
    margin: 1px 0;
}

.qp-sign {
    margin-top: 10px;
    font-size: 11.8px;
    line-height: 1.3;
    font-weight: 700;
    font-style: italic;
}

.qp-sign p {
    margin: 1px 0;
}

/* K-Flex Styles */
.kflex-global-brand-box {
    margin-top: 4px;
    padding: 14px 16px;
    border-radius: 10px;
    border: 1px solid #d5c7ad;
    background: #fcfaf5;
}

.kflex-global-brand-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}

.kflex-global-brand-head strong {
    display: block;
    font-size: 13.5px;
    color: #0f172a;
    margin-bottom: 2px;
}

.kflex-global-brand-head p {
    margin: 0;
    font-size: 12px;
    color: #475569;
}

.kflex-global-brand-quick {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.kflex-quick-line-btn {
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    padding: 6px 11px;
    font-size: 12px;
    font-weight: 600;
    background: #ffffff;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.15s ease;
}

.kflex-quick-line-btn:hover {
    border-color: #2563eb;
    color: #2563eb;
}

.kflex-quick-line-btn.active {
    background: #1e293b;
    border-color: #1e293b;
    color: #ffffff;
}

.kflex-global-brand-pills {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.kflex-brand-pill {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease;
}

.kflex-brand-pill.checked {
    border-color: #2563eb;
    background: #eff6ff;
    box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.2);
}

.kflex-brand-pill-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.kflex-brand-pill-info strong {
    font-size: 13px;
    color: #0f172a;
}

.kflex-brand-pill-info small {
    font-size: 11.5px;
    color: #475569;
}

.kflex-readonly-chip {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 5px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    font-weight: 600;
    font-size: 12px;
    color: #0f172a;
}

.kflex-readonly-origin {
    display: inline-block;
    font-size: 12px;
    font-weight: 500;
    color: #334155;
}

.kflex-groups-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.kflex-group-card {
    border: 1px solid #d5c7ad;
    border-radius: 12px;
    background: #fcfaf5;
    padding: 12px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.kflex-group-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px dashed #d8cbb3;
}

.kflex-group-bar-left {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    flex: 1;
    min-width: 280px;
}

.kflex-group-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 6px;
    background: #1e293b;
    color: #ffffff;
    font-weight: 700;
    font-size: 12px;
}

.kflex-group-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    user-select: none;
}

.kflex-group-title-input {
    flex: 1;
    min-width: 260px;
    max-width: 520px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    background: #ffffff;
    color: #0f172a;
}

.kflex-group-title-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
}

.kflex-group-no-title-hint {
    font-size: 12px;
    font-style: italic;
    color: #64748b;
}

.kflex-group-bar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.kflex-btn-sm {
    padding: 6px 12px;
    font-size: 12.5px;
}

.kflex-cell-name {
    font-weight: 500;
}

.kflex-entry-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #d9cfb8;
    border-radius: 8px;
    background: #ffffff;
}

.kflex-entry-table {
    width: 100%;
    min-width: 1320px;
    border-collapse: collapse;
    font-size: 12.5px;
}

.kflex-entry-table th,
.kflex-entry-table td {
    border: 1px solid #e2d9c5;
    padding: 6px 6px;
    vertical-align: middle;
}

.kflex-entry-table thead th {
    background: #fef08a;
    color: #1f2937;
    font-weight: 700;
    text-align: center;
    font-size: 11.5px;
    line-height: 1.25;
}

.kflex-entry-tt {
    text-align: center;
    font-weight: 700;
    color: #4b5563;
    background: #faf7f0;
}

.kflex-cell-input,
.kflex-cell-select {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    padding: 5px 6px;
    font-size: 12.5px;
    background: #ffffff;
    color: #111827;
}

.kflex-cell-input:focus,
.kflex-cell-select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
}

.kflex-cell-center {
    text-align: center;
}

.kflex-cell-right {
    text-align: right;
}

.kflex-entry-total {
    text-align: right;
    font-weight: 700;
    color: #111827;
    background: #fafaf9;
    white-space: nowrap;
}

.kflex-entry-actions {
    text-align: center;
    white-space: nowrap;
}

.kflex-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #f8fafc;
    color: #1e293b;
    font-weight: 700;
    cursor: pointer;
    margin: 0 2px;
}

.kflex-icon-btn:hover {
    background: #e2e8f0;
}

.kflex-icon-btn.danger {
    color: #dc2626;
    border-color: #fecaca;
    background: #fef2f2;
}

.kflex-icon-btn.danger:hover {
    background: #fee2e2;
}

.kflex-quote-paper {
    width: 960px;
    min-width: 960px;
    padding: 46px 68px;
    font-family: 'Times New Roman', Times, serif;
    color: #000000;
}

.kflex-qp-header {
    grid-template-columns: 165px 1fr 165px !important;
}

.kflex-qp-header .qp-logo-left {
    padding-left: 8px;
}

.kflex-qp-header .qp-logo-right {
    padding-right: 18px;
}

.qp-kflex-logo {
    width: 132px;
    height: auto;
    object-fit: contain;
    display: block;
}

.kflex-qp-title {
    text-align: center;
    font-size: 16.5px;
    font-weight: 700;
    text-transform: uppercase;
    margin: 4px 0 6px;
}

.kflex-qp-meta {
    width: 100%;
    margin-bottom: 5px;
    font-size: 12px;
}

.kflex-qp-meta-row {
    display: grid;
    grid-template-columns: 8.2% 1fr;
    column-gap: 4px;
    margin-bottom: 2px;
    align-items: baseline;
}

.kflex-qp-meta-row > span {
    min-width: 0;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.kflex-qp-meta-row > span:first-child {
    white-space: nowrap;
}

.kflex-qp-kinhgui-label {
    font-weight: 700;
    font-style: italic;
    text-decoration: underline;
    font-size: 11px;
}

.kflex-qp-kinhgui-val {
    font-weight: 700;
    font-style: italic;
}

.kflex-qp-meta-label-sub {
    font-style: italic;
    font-size: 9.8px;
    line-height: 1.15;
}

.kflex-qp-italic {
    font-style: italic;
}

.kflex-qp-intro {
    text-align: center;
    font-weight: 700;
    font-style: italic;
    color: #0070c0;
    font-size: 12.5px;
    margin: 4px 0 5px;
}

.kflex-qp-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 10px;
}

.kflex-qp-table th,
.kflex-qp-table td {
    border: 1px solid #000000;
    padding: 3px 4px;
    vertical-align: middle;
    line-height: 1.2;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    word-break: break-word;
}

.kflex-qp-table thead th {
    background: #ffff00;
    text-align: center;
    font-weight: 700;
    font-size: 9.5px;
    line-height: 1.16;
    padding: 4px 2px;
}

.kflex-qp-sec-row td {
    background: #c5d9f1;
    padding: 3.5px 5px;
}

.kflex-qp-sec-name {
    font-weight: 700;
    text-transform: uppercase;
    text-align: left;
}

.kflex-qp-item-name {
    text-align: left;
    white-space: pre-line;
}

.kflex-qp-right {
    text-align: right;
}

.qp-nowrap {
    white-space: nowrap !important;
    word-break: normal !important;
    overflow-wrap: normal !important;
}

.kflex-qp-note {
    font-size: 9.5px;
    line-height: 1.2;
}

.kflex-qp-summary-row td {
    font-weight: 700;
    padding: 4px 5px;
    font-size: 10.5px;
}

.kflex-qp-summary-val {
    text-align: right;
    color: #c00000;
    font-weight: 700;
    white-space: nowrap !important;
    word-break: normal !important;
    overflow-wrap: normal !important;
}

.kb-quote-preview-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.kflex-excel-btn {
    border-color: #16a34a !important;
    background: #16a34a !important;
    color: #ffffff !important;
    font-weight: 600;
}

.kflex-excel-btn:hover {
    background: #15803d !important;
    border-color: #15803d !important;
}

.kb-quote-no-spin::-webkit-outer-spin-button,
.kb-quote-no-spin::-webkit-inner-spin-button,
.kflex-cell-input[type='number']::-webkit-outer-spin-button,
.kflex-cell-input[type='number']::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.kb-quote-no-spin,
.kflex-cell-input[type='number'] {
    -moz-appearance: textfield;
    appearance: textfield;
}

.kflex-qp-no-border {
    border: 0 !important;
    background: transparent !important;
}

.kflex-qp-terms {
    margin-top: 5px;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10.2px;
    line-height: 1.3;
}

.kflex-qp-terms-head {
    font-family: 'Times New Roman', Times, serif;
    font-size: 11px;
    font-weight: 700;
    font-style: italic;
    text-decoration: underline;
    margin-bottom: 2px;
}

.kflex-qp-terms p {
    margin: 1px 0;
    font-weight: 400;
}

.kflex-qp-sign {
    margin-top: 5px;
    padding-left: 0;
    font-family: 'Times New Roman', Times, serif;
    font-size: 11px;
    line-height: 1.32;
    font-style: italic;
}

.kflex-qp-sign-grid {
    display: grid;
    grid-template-columns: 6.0% 1fr;
    column-gap: 4px;
    align-items: baseline;
}

.kflex-qp-sign-grid > span {
    min-width: 0;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.kflex-qp-sign-label {
    text-align: center;
}

.kflex-qp-sign-thanks {
    font-weight: 400;
}

.kflex-qp-sign-name {
    grid-column: 1 / -1;
    padding-left: 8px;
    color: #800000;
}

.kflex-qp-sign-hp {
    text-decoration: underline;
}

.kflex-qp-sign-email {
    color: #0070c0;
}

@media (max-width: 1100px) {
    .kb-quote-form-grid,
    .kb-quote-item-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .kb-quote-item-compact-row {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 680px) {
    .kb-quote-type-tabs,
    .kb-quote-form-grid,
    .kb-quote-item-grid,
    .kb-quote-item-compact-row,
    .kflex-global-brand-pills {
        grid-template-columns: 1fr;
    }

    .kb-quote-span-2 {
        grid-column: span 1;
    }
}
</style>
