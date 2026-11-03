export type RecentlyViewedProductItem = {
    id: number;
    slug?: string;
    name: string;
    product_title: string | null;
    price: number;
    image: string[] | null;
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
    viewed_at: number;
};

export function toProductSlug(name: string, fallbackId?: number | string): string {
    const slug = (name || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[đĐ]/g, 'd')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return slug || String(fallbackId ?? '');
}

export function buildProductDetailUrl(product: {
    id?: number;
    slug?: string | null;
    name?: string | null;
}): string {
    const slug = product.slug || (product.name ? toProductSlug(product.name, product.id) : '');

    if (slug) {
        return `/products/${slug}`;
    }

    return product.id ? `/products/${product.id}` : '/products';
}

const STORAGE_KEY = 'kbuser-recently-viewed';
const MAX_ITEMS = 8;

export function getRecentlyViewedProducts(excludeId?: number): RecentlyViewedProductItem[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return [];
        }

        const parsed: unknown = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return [];
        }

        const valid = parsed.filter(
            (item): item is RecentlyViewedProductItem =>
                typeof item === 'object' &&
                item !== null &&
                typeof (item as RecentlyViewedProductItem).id === 'number' &&
                typeof (item as RecentlyViewedProductItem).name === 'string',
        );

        return excludeId ? valid.filter((item) => item.id !== excludeId) : valid;
    } catch {
        return [];
    }
}

export function recordRecentlyViewedProduct(product: {
    id: number;
    slug?: string;
    name: string;
    product_title?: string | null;
    price?: number;
    image?: string[] | null;
    category?: { id: number; name: string } | null;
    subcategory?: { id: number; name: string } | null;
}): void {
    if (typeof window === 'undefined' || !product.id) {
        return;
    }

    try {
        const current = getRecentlyViewedProducts();
        const nextItem: RecentlyViewedProductItem = {
            id: product.id,
            slug: product.slug || toProductSlug(product.name, product.id),
            name: product.name,
            product_title: product.product_title ?? null,
            price: Number(product.price) || 0,
            image: Array.isArray(product.image) ? product.image : null,
            category: product.category ?? null,
            subcategory: product.subcategory ?? null,
            viewed_at: Date.now(),
        };

        const filtered = current.filter((item) => item.id !== product.id);
        const updated = [nextItem, ...filtered].slice(0, MAX_ITEMS);

        localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
        window.dispatchEvent(new CustomEvent('kbuser-recently-viewed-updated'));
    } catch {
        // Bỏ qua lỗi bộ nhớ trình duyệt
    }
}

export function clearRecentlyViewedProducts(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        localStorage.removeItem(STORAGE_KEY);
        window.dispatchEvent(new CustomEvent('kbuser-recently-viewed-updated'));
    } catch {
        // Bỏ qua lỗi bộ nhớ trình duyệt
    }
}
