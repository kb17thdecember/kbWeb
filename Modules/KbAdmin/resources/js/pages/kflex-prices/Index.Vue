<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
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

const getProductLineShortCode = (line: string): string => {
    if (line === 'st') {
        return 'ST';
    }
    if (line === 'k_protect') {
        return 'KP';
    }
    if (line === 'm_flex') {
        return 'MF';
    }
    return 'KF';
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

const formatCurrency = (val: number): string => {
    return new Intl.NumberFormat('vi-VN').format(val || 0) + ' ₫';
};

// ============================================================================
// LIST FILTERING & PAGINATION
// ============================================================================
const searchQuery = ref('');
const filterProductLine = ref('all');
const filterFormType = ref('all');
const filterVariantType = ref('all');
const filterThickness = ref('all');
const selectedIds = ref<number[]>([]);

const filterVariantOptions = computed<Record<string, string>>(() => {
    if (filterFormType.value === 'sheet') {
        return props.sheetVariants;
    }
    if (filterFormType.value === 'tube') {
        return props.tubeVariants;
    }
    return allVariantLabels;
});

watch(filterFormType, () => {
    if (
        filterVariantType.value !== 'all' &&
        !(filterVariantType.value in filterVariantOptions.value)
    ) {
        filterVariantType.value = 'all';
    }
});

const availableThicknesses = computed(() => {
    const set = new Set<string>();
    props.kflexPrices.forEach((item) => {
        const matchesLine =
            filterProductLine.value === 'all' || item.product_line === filterProductLine.value;
        const matchesForm =
            filterFormType.value === 'all' || item.form_type === filterFormType.value;
        const matchesVariant =
            filterVariantType.value === 'all' || item.variant_type === filterVariantType.value;

        if (matchesLine && matchesForm && matchesVariant && item.thickness) {
            set.add(item.thickness);
        }
    });

    return Array.from(set).sort(
        (a, b) => Number(a.replace(/\D/g, '')) - Number(b.replace(/\D/g, '')),
    );
});

watch([filterProductLine, filterFormType, filterVariantType], () => {
    if (
        filterThickness.value !== 'all' &&
        !availableThicknesses.value.includes(filterThickness.value)
    ) {
        filterThickness.value = 'all';
    }
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
            `${item.thickness}x${item.diameter ?? ''}`.toLowerCase().includes(q) ||
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
    resetPage,
} = useClientPagination(filteredPrices);

watch(
    [searchQuery, filterProductLine, filterFormType, filterVariantType, filterThickness],
    resetPage,
);

const hasActiveFilters = computed(() => {
    return (
        filterProductLine.value !== 'all' ||
        filterFormType.value !== 'all' ||
        filterVariantType.value !== 'all' ||
        filterThickness.value !== 'all' ||
        searchQuery.value.trim() !== ''
    );
});

const resetFilters = () => {
    filterProductLine.value = 'all';
    filterFormType.value = 'all';
    filterVariantType.value = 'all';
    filterThickness.value = 'all';
    searchQuery.value = '';
};

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

// ============================================================================
// SINGLE ROW EDIT & DELETE
// ============================================================================
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

// ============================================================================
// BULK ADD / UPDATE MODAL
// ============================================================================
const showBulkModal = ref(false);
const stepProductLine = ref<'st' | 'k_protect' | 'm_flex'>('st');
const stepFormType = ref<'sheet' | 'tube'>('sheet');
const stepVariantType = ref<string>('foam');
const stepThickness = ref<string>('13');
const stepBrand = ref<string>('Ý');
const stepOrigin = ref<string>('Malaysia');

const bulkVariantOptions = computed(() => {
    return stepFormType.value === 'sheet' ? props.sheetVariants : props.tubeVariants;
});

const commonThicknessPresets = ['6', '09', '10', '13', '16', '19', '20', '25', '32', '40', '50'];
const standardTubeDiameters = [
    '06', '10', '13', '16', '19', '22', '25', '28', '32', '35', '42', '48', '54', '60', '67', '76', '79', '89', '108', '114', '140', '168',
];

interface SheetThicknessRow {
    id?: number | null;
    thickness: string;
    product_name: string;
    unit: string;
    price: number;
    existingPrice?: number | null;
}

interface TubeDiameterRow {
    id?: number | null;
    diameter: string;
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

const tubeRows = ref<TubeDiameterRow[]>([
    { diameter: '22', product_name: '', unit: 'm', price: 0, existingPrice: null },
    { diameter: '28', product_name: '', unit: 'm', price: 0, existingPrice: null },
    { diameter: '35', product_name: '', unit: 'm', price: 0, existingPrice: null },
]);

const quickThicknessesInput = ref('');
const quickDefaultSheetPrice = ref<number>(0);
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

const openBulkModalFromFilters = () => {
    if (filterProductLine.value !== 'all') {
        stepProductLine.value = filterProductLine.value as 'st' | 'k_protect' | 'm_flex';
    }
    if (filterFormType.value !== 'all') {
        stepFormType.value = filterFormType.value as 'sheet' | 'tube';
    }
    if (filterVariantType.value !== 'all') {
        stepVariantType.value = filterVariantType.value;
    }
    if (filterThickness.value !== 'all' && stepFormType.value === 'tube') {
        stepThickness.value = filterThickness.value;
    }

    if (stepFormType.value === 'sheet') {
        loadExistingSheetThicknesses();
    } else {
        loadExistingDiametersForCurrentThickness();
    }

    showBulkModal.value = true;
};

const openBulkModalForRecord = (item: KflexPriceItem) => {
    stepProductLine.value = item.product_line;
    stepFormType.value = item.form_type;
    stepVariantType.value = item.variant_type;
    stepBrand.value = item.brand;
    stepOrigin.value = item.origin;

    if (item.form_type === 'sheet') {
        loadExistingSheetThicknesses();
    } else {
        stepThickness.value = item.thickness;
        loadExistingDiametersForCurrentThickness();
    }

    showBulkModal.value = true;
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
            showBulkModal.value = false;
            syncSheetExistingData();
            syncTubeExistingData();
        },
    });
};
</script>

<template>
    <AdminLayout
        :stats="props.stats"
        :open-requests-count="props.openRequestsCount"
        :chat-unread-count="props.chatUnreadCount"
        :support-projects-count="props.supportProjectsCount"
    >
        <!-- Page Header -->
        <div class="page-head">
            <div>
                <h2>Bảng giá chi tiết K-Flex</h2>
                <p>
                    Danh sách đơn giá bảo ôn K-Flex theo dòng sản phẩm (ST, K-Protect, M-Flex),
                    loại hình (Tấm, Ống) và phân loại bề mặt.
                </p>
            </div>
            <div class="actions">
                <button
                    v-if="selectedIds.length > 0"
                    class="btn danger"
                    type="button"
                    @click="bulkDeleteSelected"
                >
                    <FontAwesomeIcon icon="fa-solid fa-trash" /> Xóa đã chọn ({{
                        selectedIds.length
                    }})
                </button>
                <button class="btn primary" type="button" @click="openBulkModalFromFilters">
                    ＋ Thêm / Cập nhật giá hàng loạt
                </button>
                <input
                    v-model="searchQuery"
                    class="search"
                    type="search"
                    placeholder="Tìm tên, kích thước (VD: 13x22)..."
                    aria-label="Tìm kiếm kích thước K-Flex"
                />
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filters kflex-filters">
            <div class="filter-group">
                <span>DÒNG SẢN PHẨM</span>
                <select v-model="filterProductLine" class="kflex-filter-select">
                    <option value="all">Tất cả dòng (ST, K-Protect, M-Flex)</option>
                    <option v-for="(label, key) in productLines" :key="key" :value="key">
                        K-Flex {{ label }}
                    </option>
                </select>
            </div>

            <div class="filter-group">
                <span>LOẠI HÌNH</span>
                <select v-model="filterFormType" class="kflex-filter-select">
                    <option value="all">Tất cả (Tấm &amp; Ống)</option>
                    <option value="sheet">Tấm</option>
                    <option value="tube">Ống</option>
                </select>
            </div>

            <div class="filter-group">
                <span>PHÂN LOẠI</span>
                <select v-model="filterVariantType" class="kflex-filter-select">
                    <option value="all">Tất cả phân loại</option>
                    <option
                        v-for="(label, key) in filterVariantOptions"
                        :key="key"
                        :value="key"
                    >
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
                v-if="hasActiveFilters"
                class="btn"
                type="button"
                @click="resetFilters"
            >
                Xóa bộ lọc
            </button>

            <em>{{ filteredPrices.length }} / {{ kflexPrices.length }} kích thước</em>
        </div>

        <!-- Table List -->
        <div v-if="filteredPrices.length > 0" class="tablewrap kflex-tablewrap">
            <table class="table kflex-table">
                <thead>
                    <tr>
                        <th class="col-check">
                            <input
                                type="checkbox"
                                class="kflex-checkbox"
                                :checked="isAllSelected"
                                aria-label="Chọn tất cả"
                                @change="toggleSelectAll"
                            />
                        </th>
                        <th class="col-product">Sản phẩm bảo ôn</th>
                        <th class="col-line">Dòng sản phẩm</th>
                        <th class="col-form">Loại hình</th>
                        <th class="col-variant">Phân loại</th>
                        <th class="col-size">Kích thước</th>
                        <th class="col-price">Đơn giá</th>
                        <th class="col-actions">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in paginatedPrices" :key="item.id">
                        <td class="col-check">
                            <input
                                v-model="selectedIds"
                                type="checkbox"
                                class="kflex-checkbox"
                                :value="item.id"
                                :aria-label="`Chọn ${item.product_name}`"
                            />
                        </td>
                        <td class="col-product">
                            <div class="kflex-product-cell">
                                <b class="kflex-product-title">{{ item.product_name }}</b>
                                <small class="kflex-product-sub">
                                    Mã #{{ item.id }} · Đơn vị: {{ item.unit || 'm' }} ·
                                    {{ item.brand || getProductLineMeta(item.product_line).brand }} ({{
                                        item.origin || getProductLineMeta(item.product_line).origin
                                    }})
                                </small>
                            </div>
                        </td>
                        <td class="col-line">
                            <b class="kflex-nowrap">
                                K-Flex {{ productLines[item.product_line] || item.product_line }}
                            </b>
                        </td>
                        <td class="col-form">
                            <span
                                class="kflex-type-badge"
                                :class="item.form_type === 'sheet' ? 'sheet' : 'tube'"
                            >
                                {{ item.form_type === 'sheet' ? 'Tấm' : 'Ống' }}
                            </span>
                        </td>
                        <td class="col-variant">
                            <span class="badge kflex-variant-badge">
                                {{ getVariantLabel(item.variant_type, item.form_type) }}
                            </span>
                        </td>
                        <td class="col-size">
                            <div class="kflex-size-cell">
                                <b v-if="item.form_type === 'tube' || item.diameter">
                                    {{ item.thickness }}x{{ item.diameter }}
                                </b>
                                <b v-else>Dày {{ item.thickness }}mm</b>
                                <small class="muted">
                                    Độ dày: {{ item.thickness }}mm
                                    <template v-if="item.diameter">
                                        · Ø{{ item.diameter }}
                                    </template>
                                </small>
                            </div>
                        </td>
                        <td class="col-price">
                            <div class="kflex-price-cell">
                                <b class="kflex-price-text">
                                    {{ formatCurrency(item.price) }}
                                </b>
                                <span class="kflex-price-unit">/ {{ item.unit || 'm' }}</span>
                            </div>
                        </td>
                        <td class="col-actions">
                            <div class="row-actions kflex-row-actions">
                                <button
                                    class="mini"
                                    type="button"
                                    @click="openEditModal(item)"
                                >
                                    Sửa
                                </button>
                                <button
                                    class="mini"
                                    type="button"
                                    title="Sửa hàng loạt theo nhóm độ dày / đường kính này"
                                    @click="openBulkModalForRecord(item)"
                                >
                                    Sửa lô
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
                    ? 'Không tìm thấy kích thước bảo ôn nào phù hợp với điều kiện lọc.'
                    : 'Chưa có dữ liệu bảng giá K-Flex.'
            }}
        </div>

        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredPrices.length"
            @page-change="goToPage"
        />

        <!-- ======================================================================= -->
        <!-- MODAL SỬA NHANH 1 KÍCH THƯỚC -->
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

        <!-- ======================================================================= -->
        <!-- MODAL THÊM / CẬP NHẬT GIÁ HÀNG LOẠT -->
        <!-- ======================================================================= -->
        <div
            v-if="showBulkModal"
            class="modal open"
            role="presentation"
            @click.self="showBulkModal = false"
        >
            <section class="dialog kflex-bulk-dialog" role="dialog" aria-modal="true">
                <button
                    class="close"
                    type="button"
                    aria-label="Đóng"
                    @click="showBulkModal = false"
                >
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">K-FLEX PRICING / BULK UPDATE</div>
                    <h2>Thêm / Cập nhật bảng giá K-Flex hàng loạt</h2>
                    <p>
                        Chọn dòng sản phẩm, loại hình và phân loại để thêm hoặc cập nhật nhiều kích
                        thước cùng lúc.
                    </p>
                </div>

                <div class="kflex-bulk-body">
                    <!-- Chọn Dòng SP / Loại hình / Phân loại -->
                    <div class="kflex-bulk-selectors">
                        <label class="kb-quote-field">
                            <span>Dòng sản phẩm</span>
                            <select v-model="stepProductLine">
                                <option v-for="(label, key) in productLines" :key="key" :value="key">
                                    K-Flex {{ label }}
                                </option>
                            </select>
                        </label>

                        <label class="kb-quote-field">
                            <span>Loại hình</span>
                            <select v-model="stepFormType">
                                <option value="sheet">Tấm (Sheet)</option>
                                <option value="tube">Ống (Tube)</option>
                            </select>
                        </label>

                        <label class="kb-quote-field">
                            <span>Phân loại</span>
                            <select v-model="stepVariantType">
                                <option
                                    v-for="(label, key) in bulkVariantOptions"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </label>

                        <label v-if="stepFormType === 'tube'" class="kb-quote-field">
                            <span>Độ dày ống (mm) *</span>
                            <input
                                v-model="stepThickness"
                                type="text"
                                placeholder="VD: 09, 13, 19, 25..."
                            />
                        </label>

                        <label class="kb-quote-field">
                            <span>Thương hiệu</span>
                            <input v-model="stepBrand" type="text" placeholder="Ý" />
                        </label>

                        <label class="kb-quote-field">
                            <span>Xuất xứ</span>
                            <input v-model="stepOrigin" type="text" placeholder="Malaysia / VN" />
                        </label>
                    </div>

                    <!-- Preset độ dày cho Ống -->
                    <div v-if="stepFormType === 'tube'" class="kflex-preset-list" style="margin-bottom: 14px">
                        <span class="kflex-preset-label">Chọn nhanh độ dày ống:</span>
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

                    <!-- TẤM: Danh sách độ dày -->
                    <template v-if="stepFormType === 'sheet'">
                        <div class="kb-quote-items-head">
                            <div>
                                <div class="kb-quote-section-title" style="margin-bottom: 2px">
                                    Danh sách độ dày Tấm K-Flex {{ getProductLineMeta(stepProductLine).label }}
                                    ({{ getVariantLabel(stepVariantType, 'sheet') }})
                                </div>
                            </div>
                            <div class="kb-quote-items-actions">
                                <button
                                    type="button"
                                    class="btn"
                                    @click="loadExistingSheetThicknesses"
                                >
                                    ↻ Nạp độ dày đã lưu
                                </button>
                                <button type="button" class="btn primary" @click="addSheetThicknessRow()">
                                    ＋ Thêm dòng độ dày
                                </button>
                            </div>
                        </div>

                        <div class="kflex-quick-generator">
                            <label class="kb-quote-field" style="flex: 1">
                                <span>Nhập nhanh nhiều độ dày (cách nhau bởi dấu phẩy)</span>
                                <input
                                    v-model="quickThicknessesInput"
                                    type="text"
                                    placeholder="VD: 6, 10, 13, 16, 19, 25, 32, 40, 50"
                                    @keydown.enter.prevent="appendQuickSheetThicknesses"
                                />
                            </label>
                            <button
                                type="button"
                                class="btn"
                                style="height: 39px; align-self: flex-end"
                                @click="appendQuickSheetThicknesses"
                            >
                                ＋ Tạo nhanh
                            </button>
                        </div>

                        <div class="kb-quote-items-list kflex-bulk-scroll">
                            <div
                                v-for="(row, index) in sheetRows"
                                :key="index"
                                class="kb-quote-item-box"
                            >
                                <div class="kflex-tube-row">
                                    <span class="kb-quote-item-index">#{{ index + 1 }}</span>

                                    <label class="kb-quote-field">
                                        <span>Độ dày (mm) *</span>
                                        <input
                                            v-model="row.thickness"
                                            type="text"
                                            placeholder="VD: 13"
                                            @input="onSheetThicknessInput(row)"
                                        />
                                    </label>

                                    <label class="kb-quote-field">
                                        <span>
                                            Tên sản phẩm
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
                                        />
                                    </label>

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

                    <!-- ỐNG: Danh sách đường kính -->
                    <template v-else>
                        <div class="kb-quote-items-head">
                            <div>
                                <div class="kb-quote-section-title" style="margin-bottom: 2px">
                                    Danh sách đường kính Ống K-Flex
                                    {{ getProductLineMeta(stepProductLine).label }} dày
                                    {{ stepThickness || '...' }}mm ({{
                                        getVariantLabel(stepVariantType, 'tube')
                                    }})
                                </div>
                            </div>
                            <div class="kb-quote-items-actions">
                                <button
                                    type="button"
                                    class="btn"
                                    @click="loadExistingDiametersForCurrentThickness"
                                >
                                    ↻ Nạp đường kính đã lưu
                                </button>
                                <button type="button" class="btn" @click="loadStandardTubeDiameters">
                                    ＋ Bộ đường kính chuẩn
                                </button>
                                <button type="button" class="btn primary" @click="addTubeDiameterRow()">
                                    ＋ Thêm dòng đường kính
                                </button>
                            </div>
                        </div>

                        <div class="kflex-quick-generator">
                            <label class="kb-quote-field" style="flex: 1">
                                <span>Nhập nhanh nhiều đường kính (cách nhau bởi dấu phẩy)</span>
                                <input
                                    v-model="quickDiametersInput"
                                    type="text"
                                    placeholder="VD: 06, 10, 13, 16, 19, 22, 25, 28, 32, 35, 42, 48..."
                                    @keydown.enter.prevent="appendQuickTubeDiameters"
                                />
                            </label>
                            <button
                                type="button"
                                class="btn"
                                style="height: 39px; align-self: flex-end"
                                @click="appendQuickTubeDiameters"
                            >
                                ＋ Tạo nhanh
                            </button>
                        </div>

                        <div class="kb-quote-items-list kflex-bulk-scroll">
                            <div
                                v-for="(row, index) in tubeRows"
                                :key="index"
                                class="kb-quote-item-box"
                            >
                                <div class="kflex-tube-row">
                                    <span class="kb-quote-item-index">#{{ index + 1 }}</span>

                                    <label class="kb-quote-field">
                                        <span>Đường kính (Ø mm) *</span>
                                        <input
                                            v-model="row.diameter"
                                            type="text"
                                            placeholder="VD: 22"
                                            @input="onTubeDiameterInput(row)"
                                        />
                                    </label>

                                    <label class="kb-quote-field">
                                        <span>
                                            Tên sản phẩm
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
                                        />
                                    </label>

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

                    <div
                        v-if="builderError || Object.keys(builderForm.errors).length > 0"
                        class="kflex-error-box"
                    >
                        <div v-if="builderError">{{ builderError }}</div>
                        <div v-for="(err, key) in builderForm.errors" :key="key">{{ err }}</div>
                    </div>

                    <div class="request-detail-actions" style="margin-top: 18px">
                        <button class="btn" type="button" @click="showBulkModal = false">
                            Đóng
                        </button>
                        <button
                            class="btn primary"
                            type="button"
                            :disabled="builderForm.processing"
                            @click="submitBuilderPricing"
                        >
                            {{
                                builderForm.processing
                                    ? 'Đang lưu...'
                                    : stepFormType === 'sheet'
                                      ? `Lưu ${validSheetRowCount} độ dày Tấm`
                                      : `Lưu ${validTubeRowCount} đường kính Ống`
                            }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
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

.kflex-tablewrap {
    overflow-x: auto;
    width: 100%;
}

.kflex-table {
    width: 100%;
    min-width: 1020px;
    border-collapse: collapse;
    table-layout: auto;
}

.kflex-table th,
.kflex-table td {
    padding: 13px 14px;
    vertical-align: middle;
    box-sizing: border-box;
}

.kflex-table .col-check {
    width: 46px;
    min-width: 46px;
    max-width: 46px;
    text-align: center;
    padding-left: 10px;
    padding-right: 10px;
}

.kflex-checkbox {
    width: 16px !important;
    height: 16px !important;
    margin: 0;
    cursor: pointer;
    vertical-align: middle;
}

.kflex-table .col-product {
    min-width: 260px;
    width: 30%;
}

.kflex-product-cell {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.kflex-product-title {
    display: block;
    font-size: 13.5px;
    line-height: 1.4;
    color: var(--ink);
    word-break: break-word;
}

.kflex-product-sub {
    display: block;
    color: var(--muted);
    font-size: 11.5px;
    line-height: 1.35;
}

.kflex-table .col-line {
    min-width: 125px;
    white-space: nowrap;
}

.kflex-table .col-form {
    min-width: 90px;
    white-space: nowrap;
}

.kflex-table .col-variant {
    width: 115px;
    min-width: 105px;
    white-space: nowrap;
}

.kflex-table .col-size {
    min-width: 130px;
    white-space: nowrap;
}

.kflex-size-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.kflex-price-cell {
    display: inline-flex;
    align-items: baseline;
    gap: 4px;
    white-space: nowrap;
}

.kflex-price-unit {
    color: var(--muted);
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.kflex-table .col-price {
    min-width: 130px;
    white-space: nowrap;
}

.kflex-table .col-actions {
    width: 185px;
    min-width: 185px;
    white-space: nowrap;
}

.kflex-row-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: nowrap;
}

.kflex-nowrap {
    white-space: nowrap;
}

.kflex-variant-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    font-size: 11px;
    white-space: nowrap;
}

.kflex-type-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 11.5px;
    font-weight: 700;
    white-space: nowrap;
}

.kflex-type-badge.sheet {
    background: #efe6d5;
    color: #5b4b2a;
    border: 1px solid #d8c8a8;
}

.kflex-type-badge.tube {
    background: #e1ede8;
    color: #18352e;
    border: 1px solid #b8d0c6;
}

.kflex-price-text {
    color: #8e7443;
    font-size: 14px;
    white-space: nowrap;
}

.kflex-modal-form,
.kflex-bulk-body {
    padding: 20px 25px 25px;
}

.kflex-modal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.kflex-span-2 {
    grid-column: span 2;
}

.kflex-bulk-dialog {
    width: min(1040px, 95vw);
    max-height: 90vh;
    overflow-y: auto;
}

.kflex-bulk-selectors {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
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

.kflex-existing-tag {
    color: #2c6e49;
    font-weight: 700;
    font-style: normal;
}

.kflex-tube-row {
    display: grid;
    grid-template-columns: 38px 140px 1.8fr 90px 1.2fr auto;
    gap: 10px;
    align-items: end;
}

.kflex-quick-generator {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px dashed #c8ba9e;
    background: #f8f4eb;
    margin-bottom: 12px;
}

.kflex-bulk-scroll {
    max-height: 360px;
    overflow-y: auto;
    padding-right: 4px;
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

@media (max-width: 900px) {
    .kflex-bulk-selectors,
    .kflex-modal-grid {
        grid-template-columns: 1fr;
    }

    .kflex-span-2 {
        grid-column: span 1;
    }

    .kflex-tube-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
