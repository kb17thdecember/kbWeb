<template>
    <div ref="rootRef" class="admin-datepicker" :class="{ 'is-open': isOpen, 'has-value': Boolean(modelValue) }">
        <button
            type="button"
            class="admin-datepicker-trigger"
            :aria-label="ariaLabel || placeholder"
            :aria-expanded="isOpen"
            @click="togglePopover"
        >
            <span class="admin-datepicker-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
            </span>
            <span class="admin-datepicker-value" :class="{ 'is-placeholder': !modelValue }">
                {{ formattedDisplay }}
            </span>
            <span
                v-if="modelValue"
                class="admin-datepicker-clear"
                role="button"
                tabindex="0"
                title="Xóa ngày"
                aria-label="Xóa ngày đã chọn"
                @click.stop="clearDate"
                @keydown.enter.stop.prevent="clearDate"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </span>
            <span v-else class="admin-datepicker-chevron" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9" />
                </svg>
            </span>
        </button>

        <div v-if="isOpen" class="admin-datepicker-popover" role="dialog" aria-label="Chọn ngày">
            <div class="admin-datepicker-head">
                <button type="button" class="admin-datepicker-nav" aria-label="Tháng trước" @click="changeMonth(-1)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <div class="admin-datepicker-title">
                    Tháng {{ viewMonth + 1 }}, {{ viewYear }}
                </div>
                <button type="button" class="admin-datepicker-nav" aria-label="Tháng sau" @click="changeMonth(1)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>

            <div class="admin-datepicker-weekdays">
                <span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span>
            </div>

            <div class="admin-datepicker-days">
                <button
                    v-for="cell in calendarCells"
                    :key="cell.dateKey"
                    type="button"
                    class="admin-datepicker-day"
                    :class="{
                        'is-outside': !cell.isCurrentMonth,
                        'is-today': cell.isToday,
                        'is-selected': cell.dateKey === modelValue,
                    }"
                    @click="selectDate(cell.dateKey)"
                >
                    {{ cell.day }}
                </button>
            </div>

            <div class="admin-datepicker-foot">
                <button type="button" class="admin-datepicker-foot-btn" @click="selectToday">
                    Hôm nay
                </button>
                <button v-if="modelValue" type="button" class="admin-datepicker-foot-btn is-muted" @click="clearDate">
                    Xóa chọn
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        ariaLabel?: string;
    }>(),
    {
        placeholder: 'Chọn ngày...',
        ariaLabel: 'Chọn ngày',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const weekdays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
const isOpen = ref(false);
const rootRef = ref<HTMLElement | null>(null);

const now = new Date();
const viewYear = ref(now.getFullYear());
const viewMonth = ref(now.getMonth());

function parseDateKey(value: string): { year: number; month: number; day: number } | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) {
        return null;
    }

    return {
        year: Number(match[1]),
        month: Number(match[2]) - 1,
        day: Number(match[3]),
    };
}

function toDateKey(year: number, month: number, day: number): string {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

watch(
    () => props.modelValue,
    (val) => {
        const parsed = parseDateKey(val);
        if (parsed) {
            viewYear.value = parsed.year;
            viewMonth.value = parsed.month;
        }
    },
    { immediate: true },
);

const formattedDisplay = computed(() => {
    const parsed = parseDateKey(props.modelValue);
    if (!parsed) {
        return props.placeholder;
    }

    return `${String(parsed.day).padStart(2, '0')}/${String(parsed.month + 1).padStart(2, '0')}/${parsed.year}`;
});

const calendarCells = computed(() => {
    const year = viewYear.value;
    const month = viewMonth.value;
    const firstDayOfMonth = new Date(year, month, 1);
    const startOffset = (firstDayOfMonth.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const today = new Date();
    const todayKey = toDateKey(today.getFullYear(), today.getMonth(), today.getDate());

    const cells: Array<{
        day: number;
        dateKey: string;
        isCurrentMonth: boolean;
        isToday: boolean;
    }> = [];

    for (let i = startOffset - 1; i >= 0; i--) {
        const d = daysInPrevMonth - i;
        const prevDate = new Date(year, month - 1, d);
        const key = toDateKey(prevDate.getFullYear(), prevDate.getMonth(), prevDate.getDate());
        cells.push({
            day: d,
            dateKey: key,
            isCurrentMonth: false,
            isToday: key === todayKey,
        });
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const key = toDateKey(year, month, d);
        cells.push({
            day: d,
            dateKey: key,
            isCurrentMonth: true,
            isToday: key === todayKey,
        });
    }

    const remaining = 42 - cells.length;
    for (let d = 1; d <= remaining; d++) {
        const nextDate = new Date(year, month + 1, d);
        const key = toDateKey(nextDate.getFullYear(), nextDate.getMonth(), nextDate.getDate());
        cells.push({
            day: d,
            dateKey: key,
            isCurrentMonth: false,
            isToday: key === todayKey,
        });
    }

    return cells;
});

function togglePopover(): void {
    isOpen.value = !isOpen.value;
}

function changeMonth(delta: number): void {
    const next = new Date(viewYear.value, viewMonth.value + delta, 1);
    viewYear.value = next.getFullYear();
    viewMonth.value = next.getMonth();
}

function selectDate(dateKey: string): void {
    emit('update:modelValue', dateKey);
    isOpen.value = false;
}

function selectToday(): void {
    const today = new Date();
    viewYear.value = today.getFullYear();
    viewMonth.value = today.getMonth();
    emit('update:modelValue', toDateKey(today.getFullYear(), today.getMonth(), today.getDate()));
    isOpen.value = false;
}

function clearDate(): void {
    emit('update:modelValue', '');
    isOpen.value = false;
}

function handleDocumentMouseDown(event: MouseEvent): void {
    if (!isOpen.value) {
        return;
    }

    const target = event.target as Node | null;
    if (rootRef.value && target && !rootRef.value.contains(target)) {
        isOpen.value = false;
    }
}

function handleKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && isOpen.value) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleDocumentMouseDown);
    window.addEventListener('keydown', handleKeyDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleDocumentMouseDown);
    window.removeEventListener('keydown', handleKeyDown);
});
</script>
