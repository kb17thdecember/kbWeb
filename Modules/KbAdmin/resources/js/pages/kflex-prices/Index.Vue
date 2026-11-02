<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminPagination from '../../components/AdminPagination.Vue';
import { useClientPagination } from '../../composables/useClientPagination';
import AdminLayout from '../../layout/AdminLayout.Vue';

type AdminStat = {
    label: string;
    count: number;
    description: string;
};

interface KflexPriceItem {
    id: number;
    product_line: 'st' | 'k_protect' | 'm_flex';
    form_type: 'sheet' | 'tube';
    variant_type: string;
    thickness: string;
    diameter: string | null;
    brand: string;
    origin: string;
    product_name: string;
    unit: string;
    price: number;
    updated_at?: string;
}

interface ProductLineMetaItem {
    label: string;
    brand: string;
    origin: string;
}

const props = defineProps<{
    kflexPrices: KflexPriceItem[];
    productLines: Record<string, string>;
    productLineMeta: Record<string, ProductLineMetaItem>;
    formTypes: Record<string, string>;
    tubeVariants: Record<string, string>;
    sheetVariants: Record<string, string>;
    stats: AdminStat[];
    openRequestsCount: number;
    chatUnreadCount?: number;
    supportProjectsCount?: number;
}>();

const allVariantLabels: Record<string, string> = {
    foam: 'Chỉ Foam',
    alu: 'Phủ nhôm',
    alu_or_glue: 'Có keo/nhôm',
    alu_and_glue: 'Có cả keo và nhôm',
};

const getVariantLabel = (variantType: string, formType?: 'sheet' | 'tube'): string => {
    if (formType === 'tube') {
        return props.tubeVariants[variantType] || allVariantLabels[variantType] || variantType;
    }
    if (formType === 'sheet') {
        return props.sheetVariants[variantType] || allVariantLabels[variantType] || variantType;
    }

    return (
        allVariantLabels[variantType] ||
        props.sheetVariants[variantType] ||
        props.tubeVariants[variantType] ||
        variantType
    );
};

const getProductLineMeta = (line: string): ProductLineMetaItem => {
    return (
        props.productLineMeta?.[line] || {
            label: props.productLines[line] || line,
            brand: 'Ý',
            origin: line === 'm_flex' ? 'VN' : 'Malaysia',
        }
    );
};

const buildDefaultName = (
    productLine: string,
    formType: 'sheet' | 'tube',
    thickness: string,
    diameter?: string | null,
): string => {
    const lineLabel = getProductLineMeta(productLine).label;
    const cleanThickness = thickness.trim().replace(/\s*mm$/i, '') || '...';
    const cleanDiameter = (diameter ?? '').trim().replace(/\s*mm$/i, '') || '...';

    if (formType === 'sheet') {
        return `Tấm bảo ôn K-Flex ${lineLabel} dày ${cleanThickness}mm`;
    }

    return `Ống bảo ôn K-Flex ${lineLabel} kích thước ${cleanThickness}x${cleanDiameter}`;
};

// ============================================================================
// STEP-BY-STEP BÁO GIÁ BẢO ÔN BUILDER
// ============================================================================
const stepProductLine = ref<'st' | 'k_protect' | 'm_flex'>('st');
const stepFormType = ref<'sheet' | 'tube'>('sheet');
const stepVariantType = ref<string>('foam');
const stepThickness = ref<string>('13');
const stepBrand = ref<string>('Ý');
const stepOrigin = ref<string>('Malaysia');

// Tấm có 3 phân loại: Chỉ Foam, Có keo/nhôm, Có cả keo và nhôm
// Ống chỉ có 2 phân loại: Chỉ Foam và Phủ nhôm
const primaryVariantOptions = computed(() => {
    if (stepFormType.value === 'sheet') {
        return [
            {
                key: 'foam',
                badge: '01',
                label: 'Chỉ Foam',
                desc: 'Tấm bảo ôn cao su lưu hóa tiêu chuẩn chỉ có lớp Foam',
            },
            {
                key: 'alu_or_glue',
                badge: '02',
                label: 'Có keo/nhôm',
                desc: 'Tấm bảo ôn tích hợp sẵn lớp keo dán hoặc phủ nhôm',
            },
            {
                key: 'alu_and_glue',
                badge: '03',
                label: 'Có cả keo và nhôm',
                desc: 'Tấm bảo ôn cao cấp tích hợp cả keo dán và phủ nhôm',
            },
        ];
    }

    return [
        {
            key: 'foam',
            badge: '01',
            label: 'Chỉ Foam',
            desc: 'Ống bảo ôn tiêu chuẩn chỉ có lớp Foam cách nhiệt',
        },
        {
            key: 'alu',
            badge: '02',
            label: 'Phủ nhôm',
            desc: 'Ống bảo ôn bề mặt ngoài phủ nhôm bảo vệ',
        },
    ];
});

const commonThicknessPresets = ['6', '9', '13', '16', '19', '25', '32', '40', '50'];
const standardTubeDiameters = [
    '6', '10', '12', '16', '19', '22', '25', '28', '32', '35', '42', '48', '54', '60', '76', '89', '108', '114', '133', '140', '165',
];

// --- Sheet multi-thickness rows state (when stepFormType === 'sheet') ---
interface SheetThicknessRow {
    id?: number | null;
    thickness: string;
    product_name: string;
    unit: string;
    price: number;
    existingPrice?: number | null;
}

const sheetRows = ref<SheetThicknessRow[]>([
    { thickness: '13', product_name: '', unit: 'm²', price: 0, existingPrice: null },
    { thickness: '19', product_name: '', unit: 'm²', price: 0, existingPrice: null },
    { thickness: '25', product_name: '', unit: 'm²', price: 0, existingPrice: null },
]);

const quickThicknessesInput = ref('');
const quickDefaultSheetPrice = ref<number>(0);

// --- Tube unlimited diameters state (when stepFormType === 'tube') ---
interface TubeDiameterRow {
    id?: number | null;
    diameter: string;
    product_name: string;
    unit: string;
    price: number;
    existingPrice?: number | null;
}

const tubeRows = ref<TubeDiameterRow[]>([
    { diameter: '22', product_name: '', unit: 'm', price: 0, existingPrice: null },
    { diameter: '28', product_name: '', unit: 'm', price: 0, existingPrice: null },
    { diameter: '35', product_name: '', unit: 'm', price: 0, existingPrice: null },
]);

const quickDiametersInput = ref('');
const quickDefaultTubePrice = ref<number>(0);

const findExistingRecord = (
    productLine: string,
    formType: 'sheet' | 'tube',
    variantType: string,
    thickness: string,
    diameter: string | null,
): KflexPriceItem | undefined => {
    const cleanT = thickness.trim().replace(/\s*mm$/i, '');
    const cleanD = diameter ? diameter.trim().replace(/\s*mm$/i, '') : null;

    return props.kflexPrices.find(
        (item) =>
            item.product_line === productLine &&
            item.form_type === formType &&
            item.variant_type === variantType &&
            item.thickness === cleanT &&
            (cleanD === null ? !item.diameter : item.diameter === cleanD),
    );
};

const syncSheetExistingData = () => {
    if (stepFormType.value !== 'sheet') {
        return;
    }

    sheetRows.value.forEach((row) => {
        const cleanT = row.thickness.trim().replace(/\s*mm$/i, '');
        const match = cleanT
            ? findExistingRecord(
                  stepProductLine.value,
                  'sheet',
                  stepVariantType.value,
                  cleanT,
                  null,
              )
            : undefined;

        if (match) {
            row.id = match.id;
            row.existingPrice = match.price;
            if (row.price === 0) {
                row.price = match.price;
            }
        } else {
            row.id = null;
            row.existingPrice = null;
        }
    });
};

const syncTubeExistingData = () => {
    if (stepFormType.value !== 'tube') {
        return;
    }

    tubeRows.value.forEach((row) => {
        const match = findExistingRecord(
            stepProductLine.value,
            'tube',
            stepVariantType.value,
            stepThickness.value,
            row.diameter,
        );

        if (match) {
            row.id = match.id;
            row.existingPrice = match.price;
            if (row.price === 0) {
                row.price = match.price;
            }
        } else {
            row.id = null;
            row.existingPrice = null;
        }
    });
};

watch(
    stepProductLine,
    (newLine) => {
        const meta = getProductLineMeta(newLine);
        stepBrand.value = meta.brand;
        stepOrigin.value = meta.origin;
        syncSheetExistingData();
        syncTubeExistingData();
    },
    { immediate: true },
);

watch(stepFormType, (newFormType) => {
    const allowedKeys =
        newFormType === 'sheet'
            ? ['foam', 'alu_or_glue', 'alu_and_glue']
            : ['foam', 'alu'];

    if (!allowedKeys.includes(stepVariantType.value)) {
        stepVariantType.value = 'foam';
    }

    syncSheetExistingData();
    syncTubeExistingData();
});

watch([stepVariantType, stepThickness], () => {
    syncSheetExistingData();
    syncTubeExistingData();
});

// --- Sheet multi-thickness helpers ---
const addSheetThicknessRow = (thickness = '', price = 0) => {
    const cleanT = thickness.trim().replace(/\s*mm$/i, '');
    const match = cleanT
        ? findExistingRecord(
              stepProductLine.value,
              'sheet',
              stepVariantType.value,
              cleanT,
              null,
          )
        : undefined;

    sheetRows.value.push({
        id: match?.id ?? null,
        thickness: cleanT,
        product_name: '',
        unit: 'm²',
        price: match ? match.price : price,
        existingPrice: match ? match.price : null,
    });
};

const removeSheetThicknessRow = (index: number) => {
    if (sheetRows.value.length <= 1) {
        sheetRows.value[0].thickness = '';
        sheetRows.value[0].price = 0;
        sheetRows.value[0].id = null;
        sheetRows.value[0].existingPrice = null;
        return;
    }
    sheetRows.value.splice(index, 1);
};

const onSheetThicknessInput = (row: SheetThicknessRow) => {
    const cleanT = row.thickness.trim().replace(/\s*mm$/i, '');
    if (!cleanT) {
        row.id = null;
        row.existingPrice = null;
        return;
    }

    const match = findExistingRecord(
        stepProductLine.value,
        'sheet',
        stepVariantType.value,
        cleanT,
        null,
    );

    if (match) {
        row.id = match.id;
        row.existingPrice = match.price;
        if (row.price === 0) {
            row.price = match.price;
        }
    } else {
        row.id = null;
        row.existingPrice = null;
    }
};

const appendQuickSheetThicknesses = () => {
    const thicknesses = quickThicknessesInput.value
        .split(/[,;\s\n]+/)
        .map((t) => t.trim().replace(/\s*mm$/i, ''))
        .filter(Boolean);

    if (thicknesses.length === 0) {
        return;
    }

    if (
        sheetRows.value.length === 1 &&
        !sheetRows.value[0].thickness.trim() &&
        sheetRows.value[0].price === 0
    ) {
        sheetRows.value = [];
    }

    const existingSet = new Set(sheetRows.value.map((r) => r.thickness.trim()));

    thicknesses.forEach((t) => {
        if (!existingSet.has(t)) {
            addSheetThicknessRow(t, Number(quickDefaultSheetPrice.value) || 0);
            existingSet.add(t);
        }
    });

    quickThicknessesInput.value = '';
};

const loadStandardSheetThicknesses = () => {
    if (
        sheetRows.value.length === 1 &&
        !sheetRows.value[0].thickness.trim() &&
        sheetRows.value[0].price === 0
    ) {
        sheetRows.value = [];
    }

    const existingSet = new Set(sheetRows.value.map((r) => r.thickness.trim()));

    commonThicknessPresets.forEach((t) => {
        if (!existingSet.has(t)) {
            addSheetThicknessRow(t, Number(quickDefaultSheetPrice.value) || 0);
            existingSet.add(t);
        }
    });
};

const loadExistingSheetThicknesses = () => {
    const matches = props.kflexPrices.filter(
        (item) =>
            item.product_line === stepProductLine.value &&
            item.form_type === 'sheet' &&
            item.variant_type === stepVariantType.value,
    );

    if (matches.length === 0) {
        return;
    }

    sheetRows.value = matches.map((m) => ({
        id: m.id,
        thickness: m.thickness,
        product_name: '',
        unit: m.unit || 'm²',
        price: m.price,
        existingPrice: m.price,
    }));
};

// --- Tube multi-diameter helpers ---
const addTubeDiameterRow = (diameter = '', price = 0) => {
    const cleanD = diameter.trim();
    const match = cleanD
        ? findExistingRecord(
              stepProductLine.value,
              'tube',
              stepVariantType.value,
              stepThickness.value,
              cleanD,
          )
        : undefined;

    tubeRows.value.push({
        id: match?.id ?? null,
        diameter: cleanD,
        product_name: '',
        unit: 'm',
        price: match ? match.price : price,
        existingPrice: match ? match.price : null,
    });
};

const removeTubeDiameterRow = (index: number) => {
    if (tubeRows.value.length <= 1) {
        tubeRows.value[0].diameter = '';
        tubeRows.value[0].price = 0;
        tubeRows.value[0].id = null;
        tubeRows.value[0].existingPrice = null;
        return;
    }
    tubeRows.value.splice(index, 1);
};

const onTubeDiameterInput = (row: TubeDiameterRow) => {
    const cleanD = row.diameter.trim();
    if (!cleanD) {
        row.id = null;
        row.existingPrice = null;
        return;
    }

    const match = findExistingRecord(
        stepProductLine.value,
        'tube',
        stepVariantType.value,
        stepThickness.value,
        cleanD,
    );

    if (match) {
        row.id = match.id;
        row.existingPrice = match.price;
        if (row.price === 0) {
            row.price = match.price;
        }
    } else {
        row.id = null;
        row.existingPrice = null;
    }
};

const appendQuickTubeDiameters = () => {
    const diameters = quickDiametersInput.value
        .split(/[,;\s\n]+/)
        .map((d) => d.trim())
        .filter(Boolean);

    if (diameters.length === 0) {
        return;
    }

    if (
        tubeRows.value.length === 1 &&
        !tubeRows.value[0].diameter.trim() &&
        tubeRows.value[0].price === 0
    ) {
        tubeRows.value = [];
    }

    const existingSet = new Set(tubeRows.value.map((r) => r.diameter.trim()));

    diameters.forEach((d) => {
        if (!existingSet.has(d)) {
            addTubeDiameterRow(d, Number(quickDefaultTubePrice.value) || 0);
            existingSet.add(d);
        }
    });

    quickDiametersInput.value = '';
};

const loadStandardTubeDiameters = () => {
    if (
        tubeRows.value.length === 1 &&
        !tubeRows.value[0].diameter.trim() &&
        tubeRows.value[0].price === 0
    ) {
        tubeRows.value = [];
    }

    const existingSet = new Set(tubeRows.value.map((r) => r.diameter.trim()));

    standardTubeDiameters.forEach((d) => {
        if (!existingSet.has(d)) {
            addTubeDiameterRow(d, Number(quickDefaultTubePrice.value) || 0);
            existingSet.add(d);
        }
    });
};

const loadExistingDiametersForCurrentThickness = () => {
    const matches = props.kflexPrices.filter(
        (item) =>
            item.product_line === stepProductLine.value &&
            item.form_type === 'tube' &&
            item.variant_type === stepVariantType.value &&
            item.thickness === stepThickness.value.trim(),
    );

    if (matches.length === 0) {
        return;
    }

    tubeRows.value = matches.map((m) => ({
        id: m.id,
        diameter: m.diameter || '',
        product_name: '',
        unit: m.unit || 'm',
        price: m.price,
        existingPrice: m.price,
    }));
};

const builderForm = useForm<{
    items: Array<{
        id?: number | null;
        product_line: string;
        form_type: 'sheet' | 'tube';
        variant_type: string;
        thickness: string;
        diameter: string | null;
        brand: string;
        origin: string;
        product_name: string;
        unit: string;
        price: number;
    }>;
}>({
    items: [],
});

const builderError = ref('');

const validSheetRowCount = computed(
    () => sheetRows.value.filter((r) => r.thickness.trim() !== '').length,
);

const validTubeRowCount = computed(
    () => tubeRows.value.filter((r) => r.diameter.trim() !== '').length,
);

const submitBuilderPricing = () => {
    builderError.value = '';

    if (stepFormType.value === 'sheet') {
        const validSheetRows = sheetRows.value.filter((r) => r.thickness.trim() !== '');

        if (validSheetRows.length === 0) {
            builderError.value = 'Vui lòng nhập ít nhất 1 Độ dày (mm) cho Tấm bảo ôn.';
            return;
        }

        builderForm.items = validSheetRows.map((r) => {
            const cleanT = r.thickness.trim().replace(/\s*mm$/i, '');
            return {
                id: r.id ?? null,
                product_line: stepProductLine.value,
                form_type: 'sheet' as const,
                variant_type: stepVariantType.value,
                thickness: cleanT,
                diameter: null,
                brand: stepBrand.value.trim() || getProductLineMeta(stepProductLine.value).brand,
                origin: stepOrigin.value.trim() || getProductLineMeta(stepProductLine.value).origin,
                product_name:
                    r.product_name.trim() ||
                    buildDefaultName(stepProductLine.value, 'sheet', cleanT),
                unit: r.unit.trim() || 'm²',
                price: Number(r.price) || 0,
            };
        });
    } else {
        const cleanThickness = stepThickness.value.trim().replace(/\s*mm$/i, '');

        if (!cleanThickness) {
            builderError.value = 'Vui lòng nhập Độ dày (mm) trước khi lưu báo giá Ống.';
            return;
        }

        const validTubeRows = tubeRows.value.filter((r) => r.diameter.trim() !== '');

        if (validTubeRows.length === 0) {
            builderError.value = 'Vui lòng nhập ít nhất 1 Đường kính cho Ống bảo ôn.';
            return;
        }

        builderForm.items = validTubeRows.map((r) => {
            const cleanD = r.diameter.trim().replace(/\s*mm$/i, '');
            return {
                id: r.id ?? null,
                product_line: stepProductLine.value,
                form_type: 'tube' as const,
                variant_type: stepVariantType.value,
                thickness: cleanThickness,
                diameter: cleanD,
                brand: stepBrand.value.trim() || getProductLineMeta(stepProductLine.value).brand,
                origin: stepOrigin.value.trim() || getProductLineMeta(stepProductLine.value).origin,
                product_name:
                    r.product_name.trim() ||
                    buildDefaultName(stepProductLine.value, 'tube', cleanThickness, cleanD),
                unit: r.unit.trim() || 'm',
                price: Number(r.price) || 0,
            };
        });
    }

    builderForm.post(route('admin.kflex-prices.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            syncSheetExistingData();
            syncTubeExistingData();
        },
    });
};

const loadRecordIntoBuilder = (item: KflexPriceItem) => {
    stepProductLine.value = item.product_line;
    stepFormType.value = item.form_type;
    stepVariantType.value = item.variant_type;
    stepBrand.value = item.brand;
    stepOrigin.value = item.origin;

    if (item.form_type === 'sheet') {
        const siblings = props.kflexPrices.filter(
            (p) =>
                p.product_line === item.product_line &&
                p.form_type === 'sheet' &&
                p.variant_type === item.variant_type,
        );

        sheetRows.value =
            siblings.length > 0
                ? siblings.map((s) => ({
                      id: s.id,
                      thickness: s.thickness,
                      product_name: '',
                      unit: s.unit || 'm²',
                      price: s.price,
                      existingPrice: s.price,
                  }))
                : [
                      {
                          id: item.id,
                          thickness: item.thickness,
                          product_name: '',
                          unit: item.unit || 'm²',
                          price: item.price,
                          existingPrice: item.price,
                      },
                  ];
    } else {
        stepThickness.value = item.thickness;

        const siblings = props.kflexPrices.filter(
            (p) =>
                p.product_line === item.product_line &&
                p.form_type === 'tube' &&
                p.variant_type === item.variant_type &&
                p.thickness === item.thickness,
        );

        tubeRows.value =
            siblings.length > 0
                ? siblings.map((s) => ({
                      id: s.id,
                      diameter: s.diameter || '',
                      product_name: '',
                      unit: s.unit || 'm',
                      price: s.price,
                      existingPrice: s.price,
                  }))
                : [
                      {
                          id: item.id,
                          diameter: item.diameter || '',
                          product_name: '',
                          unit: item.unit || 'm',
                          price: item.price,
                          existingPrice: item.price,
                      },
                  ];
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// ============================================================================
// TABLE FILTERING & PAGINATION
// ============================================================================
const searchQuery = ref('');
const filterProductLine = ref('all');
const filterFormType = ref('all');
const filterVariantType = ref('all');
const filterThickness = ref('all');
const selectedIds = ref<number[]>([]);

const availableThicknesses = computed(() => {
    const set = new Set<string>();
    props.kflexPrices.forEach((item) => {
        if (item.thickness) {
            set.add(item.thickness);
        }
    });
    return Array.from(set).sort(
        (a, b) => Number(a.replace(/\D/g, '')) - Number(b.replace(/\D/g, '')),
    );
});

const filteredPrices = computed(() => {
    return props.kflexPrices.filter((item) => {
        const matchesLine =
            filterProductLine.value === 'all' || item.product_line === filterProductLine.value;
        const matchesForm =
            filterFormType.value === 'all' || item.form_type === filterFormType.value;
        const matchesVariant =
            filterVariantType.value === 'all' || item.variant_type === filterVariantType.value;
        const matchesThickness =
            filterThickness.value === 'all' || item.thickness === filterThickness.value;

        const q = searchQuery.value.trim().toLowerCase();
        const matchesSearch =
            !q ||
            item.product_name.toLowerCase().includes(q) ||
            item.thickness.toLowerCase().includes(q) ||
            (item.diameter ?? '').toLowerCase().includes(q) ||
            item.brand.toLowerCase().includes(q) ||
            item.origin.toLowerCase().includes(q);

        return (
            matchesLine && matchesForm && matchesVariant && matchesThickness && matchesSearch
        );
    });
});

const {
    currentPage,
    paginatedItems: paginatedPrices,
    goToPage,
} = useClientPagination(filteredPrices);

const isAllSelected = computed(() => {
    return (
        filteredPrices.value.length > 0 &&
        filteredPrices.value.every((item) => selectedIds.value.includes(item.id))
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        const filteredIdSet = new Set(filteredPrices.value.map((i) => i.id));
        selectedIds.value = selectedIds.value.filter((id) => !filteredIdSet.has(id));
    } else {
        const next = new Set(selectedIds.value);
        filteredPrices.value.forEach((i) => next.add(i.id));
        selectedIds.value = Array.from(next);
    }
};

// Single Row Edit Modal
const showEditModal = ref(false);
const editingItem = ref<KflexPriceItem | null>(null);
const editForm = useForm({
    product_line: 'st' as 'st' | 'k_protect' | 'm_flex',
    form_type: 'tube' as 'sheet' | 'tube',
    variant_type: 'foam',
    thickness: '',
    diameter: '',
    brand: 'Ý',
    origin: 'Malaysia',
    product_name: '',
    unit: 'm',
    price: 0,
});

const editModalVariants = computed(() => {
    return editForm.form_type === 'sheet' ? props.sheetVariants : props.tubeVariants;
});

const openEditModal = (item: KflexPriceItem) => {
    editingItem.value = item;
    editForm.product_line = item.product_line;
    editForm.form_type = item.form_type;
    editForm.variant_type = item.variant_type;
    editForm.thickness = item.thickness;
    editForm.diameter = item.diameter ?? '';
    editForm.brand = item.brand;
    editForm.origin = item.origin;
    editForm.product_name = item.product_name;
    editForm.unit = item.unit;
    editForm.price = item.price;
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingItem.value) {
        return;
    }

    editForm.put(route('admin.kflex-prices.update', editingItem.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingItem.value = null;
        },
    });
};

const deleteItem = (item: KflexPriceItem) => {
    if (!confirm(`Bạn có chắc muốn xóa "${item.product_name}"?`)) {
        return;
    }

    router.delete(route('admin.kflex-prices.destroy', item.id), {
        preserveScroll: true,
    });
};

const bulkDeleteSelected = () => {
    if (selectedIds.value.length === 0) {
        return;
    }

    if (
        !confirm(
            `Bạn có chắc muốn xóa ${selectedIds.value.length} kích thước báo giá bảo ôn đã chọn?`,
        )
    ) {
        return;
    }

    router.delete(route('admin.kflex-prices.bulk-destroy'), {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN').format(val || 0) + ' ₫';
};
</script>

<template>
    <Head title="Báo Giá Bảo Ôn K-Flex (ST / K-Protect / M-Flex)" />

    <AdminLayout
        :stats="props.stats"
        :open-requests-count="props.openRequestsCount"
        :chat-unread-count="props.chatUnreadCount"
        :support-projects-count="props.supportProjectsCount"
    >
        <!-- Page Header -->
        <div class="page-head">
            <div>
                <h2>Báo Giá Bảo Ôn K-Flex (ST · K-Protect · M-Flex)</h2>
                <p>
                    Chọn dòng sản phẩm → chọn loại hình (Tấm / Ống) → chọn phân loại bề mặt → cập nhật
                    cùng lúc nhiều độ dày Tấm hoặc nhiều đường kính Ống không giới hạn số lượng.
                </p>
            </div>
            <div class="actions">
                <button
                    v-if="selectedIds.length > 0"
                    class="btn danger"
                    type="button"
                    @click="bulkDeleteSelected"
                >
                    <FontAwesomeIcon icon="fa-solid fa-trash" /> Xóa đã chọn ({{ selectedIds.length }})
                </button>
                <button
                    class="btn primary"
                    type="button"
                    :disabled="builderForm.processing"
                    @click="submitBuilderPricing"
                >
                    <FontAwesomeIcon icon="fa-solid fa-floppy-disk" />
                    {{
                        builderForm.processing
                            ? 'Đang lưu...'
                            : stepFormType === 'sheet'
                              ? `Lưu Báo Giá (${validSheetRowCount} độ dày Tấm)`
                              : `Lưu Báo Giá (${validTubeRowCount} đường kính Ống)`
                    }}
                </button>
            </div>
        </div>

        <!-- ======================================================================= -->
        <!-- BƯỚC 1: CHỌN DÒNG SẢN PHẨM (ST / K-PROTECT / M-FLEX) -->
        <!-- ======================================================================= -->
        <div class="kb-quote-builder-card kflex-step-card">
            <div class="kflex-step-header">
                <div class="kb-quote-section-title" style="margin-bottom: 0">
                    1. Chọn Dòng Sản Phẩm Bảo Ôn K-Flex
                </div>
                <div class="kflex-summary-pills">
                    <span class="kflex-pill gold">
                        Dòng: <b>{{ getProductLineMeta(stepProductLine).label }}</b>
                    </span>
                    <span class="kflex-pill">
                        Thương hiệu: <b>{{ stepBrand }}</b> · Xuất xứ: <b>{{ stepOrigin }}</b>
                    </span>
                    <span class="kflex-pill dark">
                        {{ stepFormType === 'sheet' ? 'Loại hình: Tấm' : 'Loại hình: Ống' }} ·
                        {{ getVariantLabel(stepVariantType, stepFormType) }}
                    </span>
                </div>
            </div>

            <div class="kflex-3col-tabs">
                <button
                    type="button"
                    class="kb-quote-tab-btn"
                    :class="{ active: stepProductLine === 'st' }"
                    @click="stepProductLine = 'st'"
                >
                    <span class="kb-quote-tab-badge">ST</span>
                    <div>
                        <strong>Dòng sản phẩm ST</strong>
                        <small>Thương hiệu: Ý · Xuất xứ: Malaysia</small>
                    </div>
                </button>

                <button
                    type="button"
                    class="kb-quote-tab-btn"
                    :class="{ active: stepProductLine === 'k_protect' }"
                    @click="stepProductLine = 'k_protect'"
                >
                    <span class="kb-quote-tab-badge">KP</span>
                    <div>
                        <strong>Dòng sản phẩm K-Protect</strong>
                        <small>Thương hiệu: Ý · Xuất xứ: Malaysia</small>
                    </div>
                </button>

                <button
                    type="button"
                    class="kb-quote-tab-btn"
                    :class="{ active: stepProductLine === 'm_flex' }"
                    @click="stepProductLine = 'm_flex'"
                >
                    <span class="kb-quote-tab-badge">MF</span>
                    <div>
                        <strong>Dòng sản phẩm M-Flex</strong>
                        <small>Thương hiệu: Ý · Xuất xứ: VN</small>
                    </div>
                </button>
            </div>
        </div>

        <!-- ======================================================================= -->
        <!-- BƯỚC 2 & 3: CHỌN LOẠI HÌNH (TẤM / ỐNG) & LOẠI NHÔM KEO -->
        <!-- ======================================================================= -->
        <div class="kflex-dual-grid">
            <!-- BƯỚC 2: CHỌN TẤM HAY ỐNG -->
            <div class="kb-quote-builder-card" style="margin-bottom: 0">
                <div class="kb-quote-section-title">2. Chọn Loại Hình (Tấm hay Ống)</div>
                <div class="kflex-2col-tabs">
                    <button
                        type="button"
                        class="kb-quote-tab-btn"
                        :class="{ active: stepFormType === 'sheet' }"
                        @click="stepFormType = 'sheet'"
                    >
                        <span class="kb-quote-tab-badge">
                            <FontAwesomeIcon icon="fa-solid fa-layer-group" />
                        </span>
                        <div>
                            <strong>Bảo ôn dạng Tấm (3 phân loại)</strong>
                            <small>
                                Chỉ Foam / Có keo/nhôm / Có cả keo và nhôm · Tên: Tấm bảo ôn K-Flex
                                {{ getProductLineMeta(stepProductLine).label }} dày ...mm
                            </small>
                        </div>
                    </button>

                    <button
                        type="button"
                        class="kb-quote-tab-btn"
                        :class="{ active: stepFormType === 'tube' }"
                        @click="stepFormType = 'tube'"
                    >
                        <span class="kb-quote-tab-badge">
                            <FontAwesomeIcon icon="fa-solid fa-circle-dot" />
                        </span>
                        <div>
                            <strong>Bảo ôn dạng Ống (2 phân loại)</strong>
                            <small>
                                Chỉ Foam / Phủ nhôm · Tên: Ống bảo ôn K-Flex
                                {{ getProductLineMeta(stepProductLine).label }} kích thước độ dàyxđường kính
                            </small>
                        </div>
                    </button>
                </div>
            </div>

            <!-- BƯỚC 3: CHỌN PHÂN LOẠI NHÔM / KEO / FOAM -->
            <div class="kb-quote-builder-card" style="margin-bottom: 0">
                <div class="kb-quote-section-title">
                    3. Chọn Phân Loại ({{
                        stepFormType === 'sheet'
                            ? 'Tấm: 3 phân loại Chỉ Foam, Có keo/nhôm, Có cả keo và nhôm'
                            : 'Ống: 2 phân loại Chỉ Foam và Phủ nhôm'
                    }})
                </div>
                <div class="kflex-variant-grid">
                    <button
                        v-for="option in primaryVariantOptions"
                        :key="option.key"
                        type="button"
                        class="kflex-variant-card"
                        :class="{ active: stepVariantType === option.key }"
                        @click="stepVariantType = option.key"
                    >
                        <span class="kflex-variant-check">
                            {{ stepVariantType === option.key ? '✓' : option.badge }}
                        </span>
                        <div class="kflex-variant-meta">
                            <strong>{{ option.label }}</strong>
                            <small>{{ option.desc }}</small>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- ======================================================================= -->
        <!-- BƯỚC 4 & 5: CẬP NHẬT GIÁ NHIỀU ĐỘ DÀY (TẤM) HOẶC NHIỀU ĐƯỜNG KÍNH (ỐNG) -->
        <!-- ======================================================================= -->
        <div class="kb-quote-builder-card">
            <!-- =================================================================== -->
            <!-- TRƯỜNG HỢP 1: NẾU LÀ TẤM (SHEET) - UPDATE NHIỀU ĐỘ DÀY 1 LÚC -->
            <!-- =================================================================== -->
            <template v-if="stepFormType === 'sheet'">
                <div class="kflex-meta-inputs-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr))">
                    <label class="kb-quote-field">
                        <span>
                            Thương hiệu (Mặc định dòng {{ getProductLineMeta(stepProductLine).label }})
                        </span>
                        <input v-model="stepBrand" type="text" placeholder="Ý" />
                    </label>

                    <label class="kb-quote-field">
                        <span>
                            Xuất xứ (Mặc định dòng {{ getProductLineMeta(stepProductLine).label }})
                        </span>
                        <input v-model="stepOrigin" type="text" placeholder="Malaysia / VN" />
                    </label>
                </div>

                <hr class="kflex-divider" />

                <div class="kb-quote-items-head">
                    <div>
                        <div class="kb-quote-section-title">
                            4. Cập Nhật Nhiều Độ Dày &amp; Giá Cùng Lúc Cho Tấm Bảo Ôn K-Flex
                            {{ getProductLineMeta(stepProductLine).label }} ({{
                                getVariantLabel(stepVariantType, 'sheet')
                            }})
                        </div>
                        <small class="kb-quote-section-sub">
                            Không giới hạn số lượng độ dày · Tên tự động:
                            <b style="color: var(--ink)">
                                Tấm bảo ôn K-Flex {{ getProductLineMeta(stepProductLine).label }} dày
                                {độ dày}mm
                            </b>
                        </small>
                    </div>
                    <div class="kb-quote-items-actions">
                        <button
                            type="button"
                            class="btn"
                            @click="loadExistingSheetThicknesses"
                        >
                            ↻ Nạp các độ dày đã lưu
                        </button>
                        <button type="button" class="btn" @click="loadStandardSheetThicknesses">
                            ＋ Nạp bộ độ dày chuẩn (6mm → 50mm)
                        </button>
                        <button type="button" class="btn primary" @click="addSheetThicknessRow()">
                            ＋ Thêm dòng độ dày
                        </button>
                    </div>
                </div>

                <!-- Thanh tạo nhanh hàng loạt độ dày Tấm -->
                <div class="kflex-quick-generator">
                    <label class="kb-quote-field" style="flex: 1">
                        <span>
                            Nhập nhanh nhiều độ dày Tấm cùng lúc (cách nhau bởi dấu phẩy hoặc khoảng trắng)
                        </span>
                        <input
                            v-model="quickThicknessesInput"
                            type="text"
                            placeholder="VD: 6, 9, 13, 16, 19, 25, 32, 40, 50..."
                            @keydown.enter.prevent="appendQuickSheetThicknesses"
                        />
                    </label>
                    <label class="kb-quote-field" style="width: 210px">
                        <span>Giá mặc định (VNĐ/m² - Tùy chọn)</span>
                        <input
                            v-model.number="quickDefaultSheetPrice"
                            type="number"
                            min="0"
                            step="500"
                            placeholder="0"
                        />
                    </label>
                    <button
                        type="button"
                        class="btn primary"
                        style="height: 39px; align-self: flex-end"
                        @click="appendQuickSheetThicknesses"
                    >
                        ＋ Tạo danh sách độ dày
                    </button>
                </div>

                <!-- Danh sách các dòng độ dày Tấm -->
                <div class="kb-quote-items-list">
                    <div v-for="(row, index) in sheetRows" :key="index" class="kb-quote-item-box">
                        <div class="kflex-tube-row">
                            <span class="kb-quote-item-index">#{{ index + 1 }}</span>

                            <label class="kb-quote-field">
                                <span>Độ dày (mm) *</span>
                                <input
                                    v-model="row.thickness"
                                    type="text"
                                    placeholder="VD: 13, 19, 25..."
                                    @input="onSheetThicknessInput(row)"
                                />
                            </label>

                            <label class="kb-quote-field">
                                <span>
                                    Tên tấm tự động
                                    <em
                                        v-if="
                                            row.existingPrice !== null &&
                                            row.existingPrice !== undefined
                                        "
                                        class="kflex-existing-tag"
                                    >
                                        (Giá cũ: {{ formatCurrency(row.existingPrice) }})
                                    </em>
                                </span>
                                <input
                                    v-model="row.product_name"
                                    type="text"
                                    :placeholder="
                                        buildDefaultName(
                                            stepProductLine,
                                            'sheet',
                                            row.thickness,
                                        )
                                    "
                                />
                            </label>

                            <label class="kb-quote-field">
                                <span>Đơn vị</span>
                                <input v-model="row.unit" type="text" placeholder="m²" />
                            </label>

                            <label class="kb-quote-field">
                                <span>Đơn giá (VNĐ) *</span>
                                <input
                                    v-model.number="row.price"
                                    type="number"
                                    min="0"
                                    step="500"
                                    placeholder="Nhập giá tấm..."
                                />
                            </label>

                            <div class="kb-quote-field">
                                <span>Hiển thị giá</span>
                                <div class="kb-quote-computed-amount">
                                    {{ formatCurrency(row.price) }} / {{ row.unit || 'm²' }}
                                </div>
                            </div>

                            <div class="kb-quote-compact-btns">
                                <button
                                    type="button"
                                    class="mini danger"
                                    @click="removeSheetThicknessRow(index)"
                                >
                                    Xóa
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- =================================================================== -->
            <!-- TRƯỜNG HỢP 2: NẾU LÀ ỐNG (TUBE) - GIỮ NGUYÊN NHƯ ĐANG ĐÚNG -->
            <!-- =================================================================== -->
            <template v-else>
                <div class="kflex-thickness-bar">
                    <div>
                        <div class="kb-quote-section-title" style="margin-bottom: 4px">
                            4. Nhập Độ Dày Ống (mm) &amp; Thông Tin Xuất Xứ
                        </div>
                        <small class="kb-quote-section-sub">
                            Chọn nhanh độ dày tiêu chuẩn bên phải hoặc tự nhập độ dày bất kỳ vào ô bên dưới.
                        </small>
                    </div>

                    <div class="kflex-preset-list">
                        <span class="kflex-preset-label">Chọn nhanh độ dày:</span>
                        <button
                            v-for="preset in commonThicknessPresets"
                            :key="preset"
                            type="button"
                            class="mini"
                            :class="{ 'kflex-preset-active': stepThickness.trim() === preset }"
                            @click="stepThickness = preset"
                        >
                            {{ preset }} mm
                        </button>
                    </div>
                </div>

                <div class="kflex-meta-inputs-grid">
                    <label class="kb-quote-field">
                        <span>Độ dày bảo ôn (mm) *</span>
                        <input
                            v-model="stepThickness"
                            type="text"
                            placeholder="VD: 13, 19, 25, 32..."
                        />
                    </label>

                    <label class="kb-quote-field">
                        <span>
                            Thương hiệu (Mặc định dòng {{ getProductLineMeta(stepProductLine).label }})
                        </span>
                        <input v-model="stepBrand" type="text" placeholder="Ý" />
                    </label>

                    <label class="kb-quote-field">
                        <span>
                            Xuất xứ (Mặc định dòng {{ getProductLineMeta(stepProductLine).label }})
                        </span>
                        <input v-model="stepOrigin" type="text" placeholder="Malaysia / VN" />
                    </label>
                </div>

                <hr class="kflex-divider" />

                <div class="kb-quote-items-head">
                    <div>
                        <div class="kb-quote-section-title">
                            5. Cập Nhật 1 Loạt Đường Kính &amp; Giá Cho Ống Bảo Ôn K-Flex
                            {{ getProductLineMeta(stepProductLine).label }} (Dày
                            {{ stepThickness || '...' }} mm · {{ getVariantLabel(stepVariantType, 'tube') }})
                        </div>
                        <small class="kb-quote-section-sub">
                            Không giới hạn số lượng đường kính · Tên tự động:
                            <b style="color: var(--ink)">
                                Ống bảo ôn K-Flex {{ getProductLineMeta(stepProductLine).label }} kích thước
                                {{ stepThickness || '...' }}x{đường kính}
                            </b>
                        </small>
                    </div>
                    <div class="kb-quote-items-actions">
                        <button
                            type="button"
                            class="btn"
                            @click="loadExistingDiametersForCurrentThickness"
                        >
                            ↻ Nạp đường kính đã lưu của độ dày {{ stepThickness }}mm
                        </button>
                        <button type="button" class="btn" @click="loadStandardTubeDiameters">
                            ＋ Nạp bộ đường kính chuẩn (Ø6 → Ø165)
                        </button>
                        <button type="button" class="btn primary" @click="addTubeDiameterRow()">
                            ＋ Thêm dòng đường kính
                        </button>
                    </div>
                </div>

                <!-- Thanh tạo nhanh hàng loạt đường kính không giới hạn -->
                <div class="kflex-quick-generator">
                    <label class="kb-quote-field" style="flex: 1">
                        <span>
                            Nhập nhanh 1 loạt đường kính (cách nhau bởi dấu phẩy hoặc khoảng trắng — không giới hạn số lượng)
                        </span>
                        <input
                            v-model="quickDiametersInput"
                            type="text"
                            placeholder="VD: 6, 10, 12, 16, 19, 22, 25, 28, 32, 35, 42, 48, 54, 60, 76, 89, 108, 114, 133, 140, 165..."
                            @keydown.enter.prevent="appendQuickTubeDiameters"
                        />
                    </label>
                    <label class="kb-quote-field" style="width: 210px">
                        <span>Giá mặc định (VNĐ/m - Tùy chọn)</span>
                        <input
                            v-model.number="quickDefaultTubePrice"
                            type="number"
                            min="0"
                            step="500"
                            placeholder="0"
                        />
                    </label>
                    <button
                        type="button"
                        class="btn primary"
                        style="height: 39px; align-self: flex-end"
                        @click="appendQuickTubeDiameters"
                    >
                        ＋ Tạo danh sách đường kính
                    </button>
                </div>

                <!-- Danh sách các dòng đường kính ống -->
                <div class="kb-quote-items-list">
                    <div v-for="(row, index) in tubeRows" :key="index" class="kb-quote-item-box">
                        <div class="kflex-tube-row">
                            <span class="kb-quote-item-index">#{{ index + 1 }}</span>

                            <label class="kb-quote-field">
                                <span>Đường kính (Ø mm) *</span>
                                <input
                                    v-model="row.diameter"
                                    type="text"
                                    placeholder="VD: 22, 28, 35..."
                                    @input="onTubeDiameterInput(row)"
                                />
                            </label>

                            <label class="kb-quote-field">
                                <span>
                                    Tên ống tự động
                                    <em
                                        v-if="
                                            row.existingPrice !== null &&
                                            row.existingPrice !== undefined
                                        "
                                        class="kflex-existing-tag"
                                    >
                                        (Giá cũ: {{ formatCurrency(row.existingPrice) }})
                                    </em>
                                </span>
                                <input
                                    v-model="row.product_name"
                                    type="text"
                                    :placeholder="
                                        buildDefaultName(
                                            stepProductLine,
                                            'tube',
                                            stepThickness,
                                            row.diameter,
                                        )
                                    "
                                />
                            </label>

                            <label class="kb-quote-field">
                                <span>Đơn vị</span>
                                <input v-model="row.unit" type="text" placeholder="m" />
                            </label>

                            <label class="kb-quote-field">
                                <span>Đơn giá (VNĐ/m) *</span>
                                <input
                                    v-model.number="row.price"
                                    type="number"
                                    min="0"
                                    step="500"
                                    placeholder="Nhập giá..."
                                />
                            </label>

                            <div class="kb-quote-field">
                                <span>Thành tiền / m</span>
                                <div class="kb-quote-computed-amount">
                                    {{ formatCurrency(row.price) }}
                                </div>
                            </div>

                            <div class="kb-quote-compact-btns">
                                <button
                                    type="button"
                                    class="mini danger"
                                    @click="removeTubeDiameterRow(index)"
                                >
                                    Xóa
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Thông báo lỗi (nếu có) -->
            <div
                v-if="builderError || Object.keys(builderForm.errors).length > 0"
                class="kflex-error-box"
            >
                <div v-if="builderError">{{ builderError }}</div>
                <div v-for="(err, key) in builderForm.errors" :key="key">{{ err }}</div>
            </div>

            <!-- Thanh nút Lưu dưới chân card -->
            <div class="kflex-builder-footer">
                <div class="kflex-builder-summary">
                    <template v-if="stepFormType === 'sheet'">
                        Đang thiết lập:
                        <b>{{ validSheetRowCount }} độ dày</b>
                        cho
                        <b>
                            Tấm bảo ôn K-Flex {{ getProductLineMeta(stepProductLine).label }}
                        </b>
                        · Phân loại: <b>{{ getVariantLabel(stepVariantType, 'sheet') }}</b> · Thương hiệu:
                        <b>{{ stepBrand }}</b> · Xuất xứ: <b>{{ stepOrigin }}</b>
                    </template>
                    <template v-else>
                        Đang thiết lập:
                        <b>{{ validTubeRowCount }} đường kính</b>
                        cho
                        <b>
                            Ống bảo ôn K-Flex {{ getProductLineMeta(stepProductLine).label }} dày
                            {{ stepThickness || '...' }} mm
                        </b>
                        · Phân loại: <b>{{ getVariantLabel(stepVariantType, 'tube') }}</b> · Thương hiệu:
                        <b>{{ stepBrand }}</b> · Xuất xứ: <b>{{ stepOrigin }}</b>
                    </template>
                </div>

                <div class="actions">
                    <button
                        v-if="stepFormType === 'sheet'"
                        type="button"
                        class="btn"
                        @click="addSheetThicknessRow()"
                    >
                        ＋ Thêm độ dày
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn"
                        @click="addTubeDiameterRow()"
                    >
                        ＋ Thêm đường kính
                    </button>
                    <button
                        type="button"
                        class="btn primary"
                        :disabled="builderForm.processing"
                        @click="submitBuilderPricing"
                    >
                        <FontAwesomeIcon icon="fa-solid fa-floppy-disk" />
                        {{
                            builderForm.processing
                                ? 'Đang lưu báo giá...'
                                : stepFormType === 'sheet'
                                  ? `Lưu Báo Giá ${validSheetRowCount} Độ Dày Tấm`
                                  : `Lưu Báo Giá ${validTubeRowCount} Đường Kính Ống`
                        }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ======================================================================= -->
        <!-- BỘ LỌC & BẢNG DANH SÁCH GIÁ BẢO ÔN ĐÃ LƯU -->
        <!-- ======================================================================= -->
        <div class="page-head" style="margin-top: 26px">
            <div>
                <h2>Danh Sách Bảng Giá Bảo Ôn Đã Lưu</h2>
                <p>
                    Tra cứu, lọc theo dòng sản phẩm, loại hình, độ dày hoặc bấm
                    <b>Nạp vào khung</b> để sửa hàng loạt.
                </p>
            </div>
            <div class="actions">
                <input
                    v-model="searchQuery"
                    class="search"
                    type="search"
                    placeholder="Tìm tên, độ dày, đường kính..."
                />
            </div>
        </div>

        <div class="filters kflex-filters">
            <div class="filter-group">
                <span>DÒNG SP</span>
                <select v-model="filterProductLine" class="kflex-filter-select">
                    <option value="all">Tất cả (ST, K-Protect, M-Flex)</option>
                    <option v-for="(label, key) in productLines" :key="key" :value="key">
                        Dòng {{ label }}
                    </option>
                </select>
            </div>

            <div class="filter-group">
                <span>LOẠI HÌNH</span>
                <select v-model="filterFormType" class="kflex-filter-select">
                    <option value="all">Tất cả (Tấm & Ống)</option>
                    <option value="sheet">Tấm</option>
                    <option value="tube">Ống</option>
                </select>
            </div>

            <div class="filter-group">
                <span>NHÔM / KEO</span>
                <select v-model="filterVariantType" class="kflex-filter-select">
                    <option value="all">Tất cả phân loại</option>
                    <option v-for="(label, key) in allVariantLabels" :key="key" :value="key">
                        {{ label }}
                    </option>
                </select>
            </div>

            <div class="filter-group">
                <span>ĐỘ DÀY</span>
                <select v-model="filterThickness" class="kflex-filter-select">
                    <option value="all">Tất cả độ dày</option>
                    <option v-for="t in availableThicknesses" :key="t" :value="t">
                        Dày {{ t }} mm
                    </option>
                </select>
            </div>

            <button
                v-if="
                    filterProductLine !== 'all' ||
                    filterFormType !== 'all' ||
                    filterVariantType !== 'all' ||
                    filterThickness !== 'all' ||
                    searchQuery
                "
                class="btn"
                type="button"
                @click="
                    filterProductLine = 'all';
                    filterFormType = 'all';
                    filterVariantType = 'all';
                    filterThickness = 'all';
                    searchQuery = '';
                "
            >
                Xóa bộ lọc
            </button>

            <em>{{ filteredPrices.length }} / {{ kflexPrices.length }} kích thước</em>
        </div>

        <div v-if="filteredPrices.length > 0" class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 42px; text-align: center">
                            <input
                                type="checkbox"
                                :checked="isAllSelected"
                                @change="toggleSelectAll"
                            />
                        </th>
                        <th>Sản phẩm bảo ôn</th>
                        <th>Dòng / Loại hình</th>
                        <th>Phân loại</th>
                        <th>Kích thước</th>
                        <th>Thương hiệu / Xuất xứ</th>
                        <th>Đơn giá</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in paginatedPrices" :key="item.id">
                        <td style="text-align: center">
                            <input
                                v-model="selectedIds"
                                type="checkbox"
                                :value="item.id"
                            />
                        </td>
                        <td>
                            <div class="item">
                                <div class="thumb">
                                    <span>{{ productLines[item.product_line] || 'KF' }}</span>
                                </div>
                                <div>
                                    <b>{{ item.product_name }}</b>
                                    <small>Đơn vị tính: {{ item.unit || 'm' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <b>Dòng {{ productLines[item.product_line] || item.product_line }}</b>
                            <small class="muted">
                                Loại hình: {{ item.form_type === 'sheet' ? 'Tấm' : 'Ống' }}
                            </small>
                        </td>
                        <td>
                            <span class="badge">
                                {{ getVariantLabel(item.variant_type, item.form_type) }}
                            </span>
                        </td>
                        <td>
                            <b v-if="item.form_type === 'tube' || item.diameter">
                                {{ item.thickness }}x{{ item.diameter }}
                            </b>
                            <b v-else>Dày {{ item.thickness }}mm</b>
                            <small class="muted">
                                Độ dày: {{ item.thickness }}mm
                                <template v-if="item.diameter">
                                    · Đường kính: Ø{{ item.diameter }}
                                </template>
                            </small>
                        </td>
                        <td>
                            <b>{{ item.brand }}</b>
                            <small class="muted">Xuất xứ: {{ item.origin }}</small>
                        </td>
                        <td>
                            <b style="color: #8e7443; font-size: 14px">
                                {{ formatCurrency(item.price) }}
                            </b>
                            <small class="muted">/ {{ item.unit || 'm' }}</small>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button
                                    class="mini"
                                    type="button"
                                    title="Nạp lên khung trên để sửa hàng loạt"
                                    @click="loadRecordIntoBuilder(item)"
                                >
                                    Nạp vào khung
                                </button>
                                <button
                                    class="mini"
                                    type="button"
                                    @click="openEditModal(item)"
                                >
                                    Sửa
                                </button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    @click="deleteItem(item)"
                                >
                                    Xóa
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty">
            {{
                kflexPrices.length
                    ? 'Không tìm thấy kích thước bảo ôn nào khớp với bộ lọc.'
                    : 'Chưa có dữ liệu báo giá bảo ôn K-Flex. Hãy chọn dòng sản phẩm và nhập giá ở khung phía trên.'
            }}
        </div>

        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredPrices.length"
            @page-change="goToPage"
        />

        <!-- ======================================================================= -->
        <!-- MODAL SỬA NHANH 1 DÒNG -->
        <!-- ======================================================================= -->
        <div
            v-if="showEditModal && editingItem"
            class="modal open"
            role="presentation"
            @click.self="showEditModal = false"
        >
            <section class="dialog" role="dialog" aria-modal="true">
                <button
                    class="close"
                    type="button"
                    aria-label="Đóng"
                    @click="showEditModal = false"
                >
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">K-FLEX PRICING / EDIT</div>
                    <h2>Chỉnh sửa báo giá #{{ editingItem.id }}</h2>
                    <p>{{ editingItem.product_name }}</p>
                </div>

                <form class="kflex-modal-form" @submit.prevent="submitEdit">
                    <div class="kflex-modal-grid">
                        <label class="kb-quote-field">
                            <span>Dòng sản phẩm</span>
                            <select
                                v-model="editForm.product_line"
                                @change="
                                    editForm.brand = getProductLineMeta(editForm.product_line).brand;
                                    editForm.origin = getProductLineMeta(editForm.product_line).origin;
                                "
                            >
                                <option v-for="(label, key) in productLines" :key="key" :value="key">
                                    Dòng {{ label }}
                                </option>
                            </select>
                        </label>

                        <label class="kb-quote-field">
                            <span>Loại hình</span>
                            <select
                                v-model="editForm.form_type"
                                @change="editForm.variant_type = 'foam'"
                            >
                                <option value="sheet">Tấm</option>
                                <option value="tube">Ống</option>
                            </select>
                        </label>

                        <label class="kb-quote-field kflex-span-2">
                            <span>Phân loại</span>
                            <select v-model="editForm.variant_type">
                                <option
                                    v-for="(label, key) in editModalVariants"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </label>

                        <label class="kb-quote-field">
                            <span>Độ dày (mm) *</span>
                            <input v-model="editForm.thickness" type="text" required />
                        </label>

                        <label class="kb-quote-field">
                            <span>
                                Đường kính (mm)
                                <template v-if="editForm.form_type === 'tube'">*</template>
                            </span>
                            <input
                                v-model="editForm.diameter"
                                type="text"
                                :required="editForm.form_type === 'tube'"
                            />
                        </label>

                        <label class="kb-quote-field">
                            <span>Thương hiệu</span>
                            <input v-model="editForm.brand" type="text" />
                        </label>

                        <label class="kb-quote-field">
                            <span>Xuất xứ</span>
                            <input v-model="editForm.origin" type="text" />
                        </label>

                        <label class="kb-quote-field kflex-span-2">
                            <span>Tên sản phẩm (Để trống sẽ tự động tạo theo chuẩn)</span>
                            <input
                                v-model="editForm.product_name"
                                type="text"
                                :placeholder="
                                    buildDefaultName(
                                        editForm.product_line,
                                        editForm.form_type,
                                        editForm.thickness,
                                        editForm.diameter,
                                    )
                                "
                            />
                        </label>

                        <label class="kb-quote-field">
                            <span>Đơn vị tính</span>
                            <input v-model="editForm.unit" type="text" />
                        </label>

                        <label class="kb-quote-field">
                            <span>Đơn giá (VNĐ) *</span>
                            <input
                                v-model.number="editForm.price"
                                type="number"
                                min="0"
                                step="500"
                                required
                            />
                        </label>
                    </div>

                    <div class="request-detail-actions" style="margin-top: 18px">
                        <button class="btn" type="button" @click="showEditModal = false">
                            Hủy
                        </button>
                        <button
                            class="btn primary"
                            type="submit"
                            :disabled="editForm.processing"
                        >
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
.kflex-step-card {
    margin-bottom: 18px;
}

.kflex-step-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.kflex-summary-pills {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.kflex-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 11px;
    border-radius: 99px;
    font-size: 12px;
    border: 1px solid var(--line);
    background: var(--bg);
    color: var(--ink);
}

.kflex-pill.gold {
    background: var(--blue2);
    border-color: #d8c8a8;
    color: #5b4b2a;
}

.kflex-pill.dark {
    background: #18352e;
    border-color: #18352e;
    color: #f5f1e8;
    font-weight: 700;
}

.kflex-3col-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.kflex-3col-tabs .kb-quote-tab-btn,
.kflex-2col-tabs .kb-quote-tab-btn {
    display: flex;
    align-items: center;
}

.kflex-dual-grid {
    display: grid;
    grid-template-columns: 5fr 7fr;
    gap: 18px;
    margin-bottom: 18px;
}

.kflex-2col-tabs {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.kflex-variant-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 10px;
}

.kflex-variant-card {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: #ffffff;
    color: var(--ink);
    text-align: left;
    transition: all 0.18s ease;
}

.kflex-variant-card:hover {
    border-color: var(--gold);
}

.kflex-variant-card.active {
    background: linear-gradient(135deg, #18352e 0%, #23473b 100%);
    border-color: #18352e;
    color: #f5f1e8;
    box-shadow: 0 8px 20px rgba(24, 53, 46, 0.15);
}

.kflex-variant-check {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    background: var(--blue2);
    color: #5b4b2a;
    display: grid;
    place-items: center;
    font-size: 11px;
    font-weight: 800;
    flex-shrink: 0;
}

.kflex-variant-card.active .kflex-variant-check {
    background: var(--gold);
    color: #171714;
}

.kflex-variant-meta strong {
    display: block;
    font-size: 13px;
    margin-bottom: 2px;
}

.kflex-variant-meta small {
    display: block;
    font-size: 11.5px;
    color: var(--muted);
    line-height: 1.4;
}

.kflex-variant-card.active .kflex-variant-meta small {
    color: #c8d8ce;
}

.kflex-thickness-bar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.kflex-preset-list {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.kflex-preset-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--muted);
    margin-right: 4px;
}

.kflex-preset-active {
    background: #18352e !important;
    border-color: #18352e !important;
    color: #ffffff !important;
}

.kflex-meta-inputs-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.kflex-divider {
    border: 0;
    border-top: 1px solid var(--line);
    margin: 20px 0;
}

.kflex-existing-tag {
    color: #2c6e49;
    font-weight: 700;
    font-style: normal;
}

.kflex-tube-row {
    display: grid;
    grid-template-columns: 38px 150px 1.8fr 90px 1fr 1fr auto;
    gap: 10px;
    align-items: end;
}

.kflex-quick-generator {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px dashed #c8ba9e;
    background: #f8f4eb;
    margin-bottom: 14px;
}

.kflex-error-box {
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid #e6b8b0;
    background: #fdf2f0;
    color: #a75b4d;
    font-weight: 700;
    font-size: 13px;
}

.kflex-builder-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid var(--line);
}

.kflex-builder-summary {
    font-size: 13px;
    color: var(--muted);
}

.kflex-builder-summary b {
    color: var(--ink);
}

.kflex-filters {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.kflex-filter-select {
    border: 1px solid #d8cfbd;
    border-radius: 6px;
    background: var(--surface);
    color: var(--ink);
    padding: 7px 11px;
    font-size: 12.5px;
    font-weight: 600;
    outline: none;
}

.kflex-modal-form {
    margin-top: 12px;
}

.kflex-modal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.kflex-span-2 {
    grid-column: span 2;
}

@media (max-width: 1100px) {
    .kflex-3col-tabs,
    .kflex-dual-grid,
    .kflex-meta-inputs-grid {
        grid-template-columns: 1fr;
    }

    .kflex-tube-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
