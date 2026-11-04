import { computed, onBeforeUnmount, onMounted, ref, watch, type ComputedRef, type Ref } from 'vue';

export const ADMIN_PAGE_SIZE = 5;

export function useAdminSearchParam(): Ref<string> {
    const readFromLocation = (): string => {
        if (typeof window === 'undefined') {
            return '';
        }
        const params = new URLSearchParams(window.location.search);

        return params.get('search') ?? params.get('q') ?? '';
    };

    const search = ref(readFromLocation());

    const handleSearchEvent = (event: Event): void => {
        const keyword = (event as CustomEvent<{ search?: string }>).detail?.search;
        if (typeof keyword === 'string') {
            search.value = keyword;
        }
    };

    onMounted(() => {
        const initial = readFromLocation();
        if (initial && search.value !== initial) {
            search.value = initial;
        }
        window.addEventListener('kbadmin-search', handleSearchEvent);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('kbadmin-search', handleSearchEvent);
    });

    return search;
}

export function useClientPagination<T>(items: ComputedRef<T[]>) {
    const currentPage = ref(1);
    const pageCount = computed(() =>
        Math.max(1, Math.ceil(items.value.length / ADMIN_PAGE_SIZE)),
    );
    const paginatedItems = computed(() => {
        const start = (currentPage.value - 1) * ADMIN_PAGE_SIZE;

        return items.value.slice(start, start + ADMIN_PAGE_SIZE);
    });

    watch(pageCount, (count) => {
        if (currentPage.value > count) {
            currentPage.value = count;
        }
    });

    function goToPage(page: number): void {
        currentPage.value = Math.min(Math.max(1, page), pageCount.value);
    }

    function resetPage(): void {
        currentPage.value = 1;
    }

    return {
        currentPage,
        goToPage,
        pageCount,
        paginatedItems,
        resetPage,
    };
}
