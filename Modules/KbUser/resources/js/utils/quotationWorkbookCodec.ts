export type QuotationType = 'ariston' | 'second';

export type KflexProductLineKey = 'st' | 'k_protect' | 'm_flex';

export type AristonQuoteFormState = {
    documentTitle: string;
    recipientCompany: string;
    recipientPerson: string;
    contactPhone: string;
    quoteDate: string;
    validUntilDate: string;
    projectName: string;
    vatPercent: number;
    signerName: string;
    signerPhone: string;
    signerEmail: string;
};

export type AristonQuotationItemState = {
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

export type KflexQuoteFormState = {
    documentTitle: string;
    recipientCompany: string;
    recipientPerson: string;
    projectName: string;
    quoteDate: string;
    validUntilDate: string;
    vatPercent: number;
    introText: string;
    paymentTerms: string;
    deliveryMethod: string;
    deliveryTime: string;
    contactName: string;
    contactHp: string;
    contactEmail: string;
};

export type KflexQuotationItemState = {
    uid: string;
    kflexPriceId: number | null;
    itemName: string;
    brandLabel: string;
    originLabel: string;
    productTypeLabel: string;
    productLine: KflexProductLineKey;
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

export type KflexQuotationGroupState = {
    uid: string;
    hasTitle: boolean;
    title: string;
    items: KflexQuotationItemState[];
};

export type QuotationPayloadState = {
    version?: number;
    quotationType?: QuotationType;
    activeQuotationType?: QuotationType;
    savedAt?: string;
    quoteForm?: Partial<AristonQuoteFormState>;
    items?: AristonQuotationItemState[];
    kflexQuoteForm?: Partial<KflexQuoteFormState>;
    kflexSelectedProductLines?: KflexProductLineKey[];
    kflexGroups?: KflexQuotationGroupState[];
};

export type QuotationDraftRecord = {
    id: number;
    title: string;
    quotation_type: QuotationType;
    project_name: string | null;
    recipient_company: string | null;
    recipient_person: string | null;
    items_count: number;
    grand_total: number;
    payload: QuotationPayloadState;
    updated_at: string;
};

export type CatalogAristonProduct = {
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

export type CatalogKflexPrice = {
    id: number;
    product_line: KflexProductLineKey;
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

let codecUidSeed = 1;
const makeUid = (prefix = 'imp'): string => `${prefix}-${Date.now()}-${codecUidSeed++}`;

export function getCsrfHeaders(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }
    const headers: Record<string, string> = {
        'X-Requested-With': 'XMLHttpRequest',
    };
    const metaToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content')
        ?.trim();
    if (metaToken) {
        headers['X-CSRF-TOKEN'] = metaToken;
    }
    const xsrfCookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');
    if (xsrfCookie) {
        try {
            headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfCookie);
        } catch {
            headers['X-XSRF-TOKEN'] = xsrfCookie;
        }
    }
    return headers;
}

export function normalizeQuotationPayload(
    raw: Partial<QuotationPayloadState> | null | undefined,
): QuotationPayloadState | null {
    if (!raw || typeof raw !== 'object') {
        return null;
    }
    const resolvedType: QuotationType =
        raw.quotationType === 'second' || raw.activeQuotationType === 'second'
            ? 'second'
            : raw.quotationType === 'ariston' || raw.activeQuotationType === 'ariston'
              ? 'ariston'
              : Array.isArray(raw.kflexGroups) && raw.kflexGroups.length > 0 && (!raw.items || raw.items.length === 0)
                ? 'second'
                : 'ariston';

    return {
        ...raw,
        version: raw.version ?? 1,
        quotationType: resolvedType,
        activeQuotationType: resolvedType,
    };
}

export function compactQuotationPayload(payload: QuotationPayloadState): QuotationPayloadState {
    const normalized = normalizeQuotationPayload(payload) ?? {
        version: 1,
        quotationType: 'ariston',
        activeQuotationType: 'ariston',
    };
    const isKflex = (normalized.quotationType || normalized.activeQuotationType) === 'second';
    if (isKflex) {
        return {
            version: 1,
            quotationType: 'second',
            activeQuotationType: 'second',
            kflexQuoteForm: normalized.kflexQuoteForm,
            kflexSelectedProductLines: normalized.kflexSelectedProductLines,
            kflexGroups: normalized.kflexGroups,
        };
    }
    return {
        version: 1,
        quotationType: 'ariston',
        activeQuotationType: 'ariston',
        quoteForm: normalized.quoteForm,
        items: Array.isArray(normalized.items)
            ? normalized.items.map((item) => ({
                  ...item,
                  imageUrl:
                      typeof item.imageUrl === 'string' && item.imageUrl.startsWith('data:')
                          ? ''
                          : item.imageUrl,
              }))
            : undefined,
    };
}

export function parseFormattedNumber(raw: unknown, fallback = 0): number {
    if (typeof raw === 'number' && Number.isFinite(raw)) {
        return raw;
    }
    const str = String(raw ?? '').trim();
    if (!str) {
        return fallback;
    }
    const cleaned = str.replace(/[^\d.,-]/g, '');
    if (!cleaned) {
        return fallback;
    }
    if (/^-?\d{1,3}(\.\d{3})+$/.test(cleaned)) {
        return Number(cleaned.replace(/\./g, '')) || fallback;
    }
    if (/^-?\d{1,3}(,\d{3})+$/.test(cleaned)) {
        return Number(cleaned.replace(/,/g, '')) || fallback;
    }
    const dotCount = (cleaned.match(/\./g) || []).length;
    const commaCount = (cleaned.match(/,/g) || []).length;
    if (dotCount > 1 && commaCount === 0) {
        return Number(cleaned.replace(/\./g, '')) || fallback;
    }
    if (commaCount > 1 && dotCount === 0) {
        return Number(cleaned.replace(/,/g, '')) || fallback;
    }
    if (dotCount === 1 && commaCount === 1) {
        const lastDot = cleaned.lastIndexOf('.');
        const lastComma = cleaned.lastIndexOf(',');
        if (lastDot > lastComma) {
            return Number(cleaned.replace(/,/g, '')) || fallback;
        }
        return Number(cleaned.replace(/\./g, '').replace(',', '.')) || fallback;
    }
    if (commaCount === 1 && dotCount === 0) {
        const afterComma = cleaned.split(',')[1] ?? '';
        if (afterComma.length === 3) {
            return Number(cleaned.replace(/,/g, '')) || fallback;
        }
        return Number(cleaned.replace(',', '.')) || fallback;
    }
    if (dotCount === 1 && commaCount === 0) {
        const afterDot = cleaned.split('.')[1] ?? '';
        if (afterDot.length === 3 && Number(cleaned.split('.')[0]) > 0) {
            return Number(cleaned.replace(/\./g, '')) || fallback;
        }
    }
    const num = Number(cleaned.replace(/,/g, ''));
    return Number.isFinite(num) ? num : fallback;
}

export function parseSlashDateToIso(raw: string, fallbackIso: string): string {
    const trimmed = (raw || '').trim();
    if (!trimmed) {
        return fallbackIso;
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
        return trimmed;
    }
    const match = trimmed.match(/(\d{1,2})[/-](\d{1,2})[/-](\d{4})/);
    if (match) {
        let p1 = Number(match[1]);
        let p2 = Number(match[2]);
        if (p2 > 12 && p1 <= 12) {
            const tmp = p1;
            p1 = p2;
            p2 = tmp;
        }
        const day = String(p1).padStart(2, '0');
        const month = String(p2).padStart(2, '0');
        const year = match[3];
        return `${year}-${month}-${day}`;
    }
    return fallbackIso;
}

function uint8ToBase64(bytes: Uint8Array): string {
    let binary = '';
    const chunkSize = 0x8000;
    for (let i = 0; i < bytes.length; i += chunkSize) {
        const chunk = bytes.subarray(i, i + chunkSize);
        binary += String.fromCharCode(...chunk);
    }
    return btoa(binary);
}

export function encodeQuotationPayloadBase64(payload: QuotationPayloadState): string {
    const normalized = normalizeQuotationPayload(payload) ?? payload;
    const encoder = new TextEncoder();
    const bytes = encoder.encode(JSON.stringify(normalized));
    return uint8ToBase64(bytes);
}

export function decodeQuotationPayloadBase64(base64Str: string): QuotationPayloadState | null {
    try {
        const clean = base64Str.replace(/[^A-Za-z0-9+/=]/g, '');
        if (!clean) {
            return null;
        }
        const binary = atob(clean);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        const decoder = new TextDecoder('utf-8');
        const parsed = JSON.parse(decoder.decode(bytes)) as Partial<QuotationPayloadState>;
        return normalizeQuotationPayload(parsed);
    } catch {
        return null;
    }
}

// ============================================================================
// Pure-JS RFC 1951 INFLATE (Synchronous, zero stream backpressure deadlock)
// ============================================================================
const FIXED_LIT_LEN_TABLE = (() => {
    const lengths = new Uint8Array(288);
    for (let i = 0; i <= 143; i++) lengths[i] = 8;
    for (let i = 144; i <= 255; i++) lengths[i] = 9;
    for (let i = 256; i <= 279; i++) lengths[i] = 7;
    for (let i = 280; i <= 287; i++) lengths[i] = 8;
    return buildHuffmanTable(lengths);
})();

const FIXED_DIST_TABLE = (() => {
    const lengths = new Uint8Array(32);
    lengths.fill(5);
    return buildHuffmanTable(lengths);
})();

const LEN_BASE = [
    3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 15, 17, 19, 23, 27, 31,
    35, 43, 51, 59, 67, 83, 99, 115, 131, 163, 195, 227, 258,
];
const LEN_EXTRA = [
    0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2,
    3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 0,
];
const DIST_BASE = [
    1, 2, 3, 4, 5, 7, 9, 13, 17, 25, 33, 49, 65, 97, 129, 193,
    257, 385, 513, 769, 1025, 1537, 2049, 3073, 4097, 6145, 8193, 12289, 16385, 24577,
];
const DIST_EXTRA = [
    0, 0, 0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6,
    7, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 12, 13, 13,
];
const CL_ORDER = [16, 17, 18, 0, 8, 7, 9, 6, 10, 5, 11, 4, 12, 3, 13, 2, 14, 1, 15];

type HuffmanTable = {
    counts: Uint16Array;
    symbols: Uint16Array;
    maxBits: number;
};

function buildHuffmanTable(lengths: Uint8Array): HuffmanTable {
    let maxBits = 0;
    for (let i = 0; i < lengths.length; i++) {
        if (lengths[i] > maxBits) {
            maxBits = lengths[i];
        }
    }
    if (maxBits === 0) {
        return { counts: new Uint16Array(16), symbols: new Uint16Array(0), maxBits: 0 };
    }
    const counts = new Uint16Array(maxBits + 1);
    for (let i = 0; i < lengths.length; i++) {
        if (lengths[i] > 0) {
            counts[lengths[i]]++;
        }
    }
    const offsets = new Uint16Array(maxBits + 1);
    let total = 0;
    for (let bits = 1; bits <= maxBits; bits++) {
        offsets[bits] = total;
        total += counts[bits];
    }
    const symbols = new Uint16Array(total);
    for (let i = 0; i < lengths.length; i++) {
        const len = lengths[i];
        if (len > 0) {
            symbols[offsets[len]++] = i;
        }
    }
    return { counts, symbols, maxBits };
}

function inflateRawSync(src: Uint8Array): Uint8Array {
    let bitPos = 0;
    const totalBits = src.length * 8;

    const readBits = (n: number): number => {
        let val = 0;
        for (let i = 0; i < n; i++) {
            if (bitPos >= totalBits) {
                throw new Error('Unexpected EOF in deflate stream');
            }
            const byteIdx = bitPos >>> 3;
            const bitIdx = bitPos & 7;
            val |= ((src[byteIdx] >>> bitIdx) & 1) << i;
            bitPos++;
        }
        return val;
    };

    const decodeSymbol = (table: HuffmanTable): number => {
        let code = 0;
        let first = 0;
        let index = 0;
        for (let len = 1; len <= table.maxBits; len++) {
            code |= readBits(1);
            const count = table.counts[len];
            if (code - count < first) {
                return table.symbols[index + (code - first)];
            }
            index += count;
            first += count;
            first <<= 1;
            code <<= 1;
        }
        throw new Error('Invalid Huffman code');
    };

    let out = new Uint8Array(Math.max(4096, src.length * 3));
    let outLen = 0;
    const ensureCap = (extra: number) => {
        if (outLen + extra <= out.length) {
            return;
        }
        let nextCap = out.length * 2;
        while (nextCap < outLen + extra) {
            nextCap *= 2;
        }
        const grown = new Uint8Array(nextCap);
        grown.set(out.subarray(0, outLen));
        out = grown;
    };

    let bfinal = 0;
    while (bfinal === 0) {
        bfinal = readBits(1);
        const btype = readBits(2);

        if (btype === 0) {
            // Uncompressed block: align to byte boundary
            bitPos = (bitPos + 7) & ~7;
            const bytePos = bitPos >>> 3;
            if (bytePos + 4 > src.length) {
                throw new Error('Truncated stored block');
            }
            const len = src[bytePos] | (src[bytePos + 1] << 8);
            const start = bytePos + 4;
            if (start + len > src.length) {
                throw new Error('Truncated stored block data');
            }
            ensureCap(len);
            out.set(src.subarray(start, start + len), outLen);
            outLen += len;
            bitPos = (start + len) * 8;
        } else if (btype === 1 || btype === 2) {
            let litLenTable = FIXED_LIT_LEN_TABLE;
            let distTable = FIXED_DIST_TABLE;

            if (btype === 2) {
                const hlit = readBits(5) + 257;
                const hdist = readBits(5) + 1;
                const hclen = readBits(4) + 4;
                const clLengths = new Uint8Array(19);
                for (let i = 0; i < hclen; i++) {
                    clLengths[CL_ORDER[i]] = readBits(3);
                }
                const clTable = buildHuffmanTable(clLengths);
                const allLengths = new Uint8Array(hlit + hdist);
                let idx = 0;
                while (idx < hlit + hdist) {
                    const sym = decodeSymbol(clTable);
                    if (sym < 16) {
                        allLengths[idx++] = sym;
                    } else if (sym === 16) {
                        const prev = idx > 0 ? allLengths[idx - 1] : 0;
                        let repeat = readBits(2) + 3;
                        while (repeat-- > 0 && idx < allLengths.length) {
                            allLengths[idx++] = prev;
                        }
                    } else if (sym === 17) {
                        let repeat = readBits(3) + 3;
                        while (repeat-- > 0 && idx < allLengths.length) {
                            allLengths[idx++] = 0;
                        }
                    } else if (sym === 18) {
                        let repeat = readBits(7) + 11;
                        while (repeat-- > 0 && idx < allLengths.length) {
                            allLengths[idx++] = 0;
                        }
                    }
                }
                litLenTable = buildHuffmanTable(allLengths.subarray(0, hlit));
                distTable = buildHuffmanTable(allLengths.subarray(hlit));
            }

            while (true) {
                const sym = decodeSymbol(litLenTable);
                if (sym < 256) {
                    ensureCap(1);
                    out[outLen++] = sym;
                } else if (sym === 256) {
                    break;
                } else {
                    const lenIdx = sym - 257;
                    const length = LEN_BASE[lenIdx] + readBits(LEN_EXTRA[lenIdx]);
                    const distSym = decodeSymbol(distTable);
                    const dist = DIST_BASE[distSym] + readBits(DIST_EXTRA[distSym]);
                    ensureCap(length);
                    let srcIdx = outLen - dist;
                    for (let i = 0; i < length; i++) {
                        out[outLen++] = out[srcIdx++];
                    }
                }
            }
        } else {
            throw new Error('Invalid deflate block type');
        }
    }

    return out.subarray(0, outLen);
}

function inflateZlibSync(src: Uint8Array): Uint8Array {
    if (src.length < 6) {
        throw new Error('Zlib stream too short');
    }
    const cmf = src[0];
    const flg = src[1];
    if ((cmf & 0x0f) !== 8 || ((cmf << 8) + flg) % 31 !== 0) {
        throw new Error('Invalid zlib header');
    }
    const fdict = (flg & 0x20) !== 0;
    const startOffset = fdict ? 6 : 2;
    return inflateRawSync(src.subarray(startOffset, src.length - 4));
}

async function inflateStreamBytes(
    compressed: Uint8Array,
    format: 'deflate' | 'deflate-raw',
): Promise<Uint8Array> {
    try {
        return format === 'deflate' ? inflateZlibSync(compressed) : inflateRawSync(compressed);
    } catch (syncErr) {
        if (typeof DecompressionStream !== 'undefined' && typeof Response !== 'undefined') {
            try {
                const stream = new Blob([compressed as unknown as BlobPart])
                    .stream()
                    .pipeThrough(new DecompressionStream(format));
                const ab = await new Response(stream).arrayBuffer();
                return new Uint8Array(ab);
            } catch {
                // fall through
            }
        }
        throw syncErr;
    }
}

async function inflateRawBytes(compressed: Uint8Array): Promise<Uint8Array> {
    return inflateStreamBytes(compressed, 'deflate-raw');
}

export async function readZipEntries(buffer: ArrayBuffer): Promise<Map<string, Uint8Array>> {
    const bytes = new Uint8Array(buffer);
    const view = new DataView(buffer);
    const decoder = new TextDecoder('utf-8');
    const entries = new Map<string, Uint8Array>();

    let eocdPos = -1;
    const minEocdSearch = Math.max(0, bytes.length - 65557);
    for (let i = bytes.length - 22; i >= minEocdSearch; i--) {
        if (view.getUint32(i, true) === 0x06054b50) {
            eocdPos = i;
            break;
        }
    }

    if (eocdPos === -1) {
        throw new Error('File không phải định dạng Excel (.xlsx) hợp lệ.');
    }

    const totalEntries = view.getUint16(eocdPos + 10, true);
    const centralDirOffset = view.getUint32(eocdPos + 16, true);
    let cdPos = centralDirOffset;

    for (let idx = 0; idx < totalEntries; idx++) {
        if (cdPos + 46 > bytes.length || view.getUint32(cdPos, true) !== 0x02014b50) {
            break;
        }
        const compressionMethod = view.getUint16(cdPos + 10, true);
        const compressedSize = view.getUint32(cdPos + 20, true);
        const fileNameLen = view.getUint16(cdPos + 28, true);
        const extraLen = view.getUint16(cdPos + 30, true);
        const commentLen = view.getUint16(cdPos + 32, true);
        const localHeaderOffset = view.getUint32(cdPos + 42, true);

        const nameBytes = bytes.subarray(cdPos + 46, cdPos + 46 + fileNameLen);
        const fileName = decoder
            .decode(nameBytes)
            .replace(/\\/g, '/')
            .replace(/^\/+/, '');

        cdPos += 46 + fileNameLen + extraLen + commentLen;

        if (localHeaderOffset + 30 > bytes.length || view.getUint32(localHeaderOffset, true) !== 0x04034b50) {
            continue;
        }
        const localNameLen = view.getUint16(localHeaderOffset + 26, true);
        const localExtraLen = view.getUint16(localHeaderOffset + 28, true);
        const dataStart = localHeaderOffset + 30 + localNameLen + localExtraLen;
        const rawData = bytes.subarray(dataStart, dataStart + compressedSize);

        if (compressionMethod === 0) {
            entries.set(fileName, rawData);
        } else if (compressionMethod === 8) {
            const decompressed = await inflateRawBytes(rawData);
            entries.set(fileName, decompressed);
        }
    }

    return entries;
}

function getZipEntry(entries: Map<string, Uint8Array>, targetPath: string): Uint8Array | undefined {
    const exact = entries.get(targetPath);
    if (exact) {
        return exact;
    }
    const lowerTarget = targetPath.toLowerCase().replace(/^\/+/, '');
    for (const [k, v] of entries.entries()) {
        if (k.toLowerCase().replace(/^\/+/, '') === lowerTarget) {
            return v;
        }
    }
    return undefined;
}

function colLettersToIndex(colRef: string): number {
    const letters = colRef.replace(/[^A-Za-z]/g, '').toUpperCase();
    let col = 0;
    for (let i = 0; i < letters.length; i++) {
        col = col * 26 + (letters.charCodeAt(i) - 64);
    }
    return col;
}

function parseCellRef(ref: string): { row: number; col: number } {
    const match = ref.match(/^([A-Za-z]+)(\d+)$/);
    if (!match) {
        return { row: 0, col: 0 };
    }
    return {
        col: colLettersToIndex(match[1]),
        row: Number(match[2]),
    };
}

function getElementsByLocalName(root: Document | Element, localName: string): Element[] {
    const direct = Array.from(root.getElementsByTagName(localName));
    if (direct.length > 0) {
        return direct;
    }
    const all = Array.from(root.getElementsByTagName('*'));
    return all.filter((el) => el.localName === localName || el.tagName.endsWith(`:${localName}`));
}

function parseSharedStringsXml(xmlText: string): string[] {
    const parser = new DOMParser();
    const doc = parser.parseFromString(xmlText, 'application/xml');
    const siNodes = getElementsByLocalName(doc, 'si');
    return siNodes.map((si) => {
        const tNodes = getElementsByLocalName(si, 't');
        return tNodes.map((t) => t.textContent ?? '').join('');
    });
}

type ParsedWorksheet = {
    grid: Map<number, Map<number, string>>;
    merges: Array<{ r1: number; c1: number; r2: number; c2: number }>;
    maxRow: number;
};

function parseWorksheetXml(xmlText: string, sharedStrings: string[]): ParsedWorksheet {
    const parser = new DOMParser();
    const doc = parser.parseFromString(xmlText, 'application/xml');
    const grid = new Map<number, Map<number, string>>();
    let maxRow = 0;

    const rowNodes = getElementsByLocalName(doc, 'row');
    let implicitRow = 0;
    for (const rowEl of rowNodes) {
        implicitRow++;
        const rAttr = Number(rowEl.getAttribute('r') || String(implicitRow));
        if (!rAttr) {
            continue;
        }
        implicitRow = rAttr;
        if (rAttr > maxRow) {
            maxRow = rAttr;
        }
        let rowMap = grid.get(rAttr);
        if (!rowMap) {
            rowMap = new Map<number, string>();
            grid.set(rAttr, rowMap);
        }

        const cellNodes = getElementsByLocalName(rowEl, 'c');
        let implicitCol = 0;
        for (const cellEl of cellNodes) {
            implicitCol++;
            const ref = cellEl.getAttribute('r') || '';
            const parsedRef = parseCellRef(ref);
            const col = parsedRef.col || implicitCol;
            implicitCol = col;

            const t = cellEl.getAttribute('t') || '';
            let val = '';
            if (t === 'inlineStr') {
                const tEls = getElementsByLocalName(cellEl, 't');
                val = tEls.map((el) => el.textContent ?? '').join('');
            } else {
                const vEl = getElementsByLocalName(cellEl, 'v')[0];
                const rawV = vEl?.textContent ?? '';
                if (t === 's') {
                    val = sharedStrings[Number(rawV)] ?? '';
                } else {
                    val = rawV;
                }
            }
            rowMap.set(col, val);
        }
    }

    const merges: Array<{ r1: number; c1: number; r2: number; c2: number }> = [];
    const mergeNodes = getElementsByLocalName(doc, 'mergeCell');
    for (const mEl of mergeNodes) {
        const ref = mEl.getAttribute('ref') || '';
        const parts = ref.split(':');
        if (parts.length === 2) {
            const start = parseCellRef(parts[0]);
            const end = parseCellRef(parts[1]);
            if (start.row && start.col && end.row && end.col) {
                merges.push({
                    r1: Math.min(start.row, end.row),
                    c1: Math.min(start.col, end.col),
                    r2: Math.max(start.row, end.row),
                    c2: Math.max(start.col, end.col),
                });
            }
        }
    }

    return { grid, merges, maxRow };
}

function extractRowImagesFromZip(entries: Map<string, Uint8Array>): Map<string, string> {
    const decoder = new TextDecoder('utf-8');
    const result = new Map<string, string>();

    const drawingXmlBytes = getZipEntry(entries, 'xl/drawings/drawing1.xml');
    const drawingRelsBytes = getZipEntry(entries, 'xl/drawings/_rels/drawing1.xml.rels');
    if (!drawingXmlBytes || !drawingRelsBytes) {
        return result;
    }

    const parser = new DOMParser();
    const relsDoc = parser.parseFromString(decoder.decode(drawingRelsBytes), 'application/xml');
    const relMap = new Map<string, string>();
    for (const relEl of getElementsByLocalName(relsDoc, 'Relationship')) {
        const id = relEl.getAttribute('Id') || '';
        const target = relEl.getAttribute('Target') || '';
        if (id && target) {
            const cleanPath = 'xl/' + target.replace(/^(\.\.\/)+/, '').replace(/^\/+/, '');
            relMap.set(id, cleanPath);
        }
    }

    const drawingDoc = parser.parseFromString(decoder.decode(drawingXmlBytes), 'application/xml');
    const anchors = [
        ...getElementsByLocalName(drawingDoc, 'oneCellAnchor'),
        ...getElementsByLocalName(drawingDoc, 'twoCellAnchor'),
    ];

    for (const anchor of anchors) {
        const fromEl = getElementsByLocalName(anchor, 'from')[0];
        if (!fromEl) {
            continue;
        }
        const colEl = getElementsByLocalName(fromEl, 'col')[0];
        const rowEl = getElementsByLocalName(fromEl, 'row')[0];
        const blipEl = getElementsByLocalName(anchor, 'blip')[0];
        if (!colEl || !rowEl || !blipEl) {
            continue;
        }

        const col1 = Number(colEl.textContent || '0') + 1;
        const row1 = Number(rowEl.textContent || '0') + 1;
        const embedId = blipEl.getAttribute('r:embed') || blipEl.getAttribute('embed') || '';
        const mediaPath = relMap.get(embedId);
        if (!mediaPath) {
            continue;
        }
        const imgBytes = getZipEntry(entries, mediaPath);
        if (!imgBytes) {
            continue;
        }
        const ext =
            mediaPath.toLowerCase().endsWith('.jpg') || mediaPath.toLowerCase().endsWith('.jpeg')
                ? 'image/jpeg'
                : 'image/png';
        result.set(`${row1}:${col1}`, `data:${ext};base64,${uint8ToBase64(imgBytes)}`);
    }

    return result;
}

function getCellText(grid: Map<number, Map<number, string>>, r: number, c: number): string {
    return (grid.get(r)?.get(c) ?? '').trim();
}

function resolveProductImageUrl(raw?: string | null): string {
    if (!raw) {
        return '';
    }
    if (raw.startsWith('data:') || raw.startsWith('http')) {
        return raw;
    }
    const path = raw.startsWith('/') ? raw : `/storage/${raw}`;
    return typeof window !== 'undefined' ? `${window.location.origin}${path}` : path;
}

export async function parseExportedQuotationFile(
    file: File,
    aristonProducts: CatalogAristonProduct[],
    kflexPrices: CatalogKflexPrice[],
    todayIso: string,
): Promise<QuotationPayloadState> {
    const fileName = file.name.toLowerCase();

    if (fileName.endsWith('.json')) {
        const text = await file.text();
        const parsed = normalizeQuotationPayload(JSON.parse(text) as Partial<QuotationPayloadState>);
        if (parsed) {
            return parsed;
        }
        throw new Error('File JSON không đúng định dạng báo giá.');
    }

    if (fileName.endsWith('.html') || fileName.endsWith('.htm')) {
        const htmlText = await file.text();
        const match = htmlText.match(/KBQDATA:([A-Za-z0-9+/=\s]+):ENDKBQDATA/);
        if (match) {
            const decoded = decodeQuotationPayloadBase64(match[1]);
            if (decoded) {
                return decoded;
            }
        }
        throw new Error('File HTML không chứa dữ liệu báo giá hợp lệ.');
    }

    if (fileName.endsWith('.pdf')) {
        return parseExportedQuotationPdf(file, aristonProducts, kflexPrices, todayIso);
    }

    const buffer = await file.arrayBuffer();
    const entries = await readZipEntries(buffer);
    const decoder = new TextDecoder('utf-8');

    let embeddedState: QuotationPayloadState | null = null;
    const embeddedBytes = getZipEntry(entries, 'xl/customData/quotation.json');
    if (embeddedBytes) {
        try {
            embeddedState = normalizeQuotationPayload(
                JSON.parse(decoder.decode(embeddedBytes)) as Partial<QuotationPayloadState>,
            );
        } catch {
            embeddedState = null;
        }
    }

    const sheetBytes =
        getZipEntry(entries, 'xl/worksheets/sheet1.xml') ||
        Array.from(entries.entries()).find(([k]) =>
            k.toLowerCase().includes('xl/worksheets/sheet'),
        )?.[1];

    if (!sheetBytes) {
        if (embeddedState) {
            return embeddedState;
        }
        throw new Error('Không tìm thấy trang tính dữ liệu trong file Excel.');
    }

    const sharedStringsBytes = getZipEntry(entries, 'xl/sharedStrings.xml');
    const sharedStrings = sharedStringsBytes
        ? parseSharedStringsXml(decoder.decode(sharedStringsBytes))
        : [];

    const { grid, merges, maxRow } = parseWorksheetXml(decoder.decode(sheetBytes), sharedStrings);
    const rowImages = extractRowImagesFromZip(entries);

    let isKflex = false;
    for (let r = 1; r <= Math.min(maxRow, 25); r++) {
        const c1 = getCellText(grid, r, 1).toUpperCase();
        const c2 = getCellText(grid, r, 2).toUpperCase();
        if (
            c1.includes('K-FLEX') ||
            c1.includes('BẢO ÔN') ||
            (c1 === 'TT' && c2.includes('TÊN VẬT TƯ'))
        ) {
            isKflex = true;
            break;
        }
    }

    if (isKflex) {
        return parseKflexWorksheet(grid, merges, maxRow, kflexPrices, embeddedState, todayIso);
    }

    return parseAristonWorksheet(grid, maxRow, rowImages, aristonProducts, embeddedState, todayIso);
}

function parseAristonWorksheet(
    grid: Map<number, Map<number, string>>,
    maxRow: number,
    rowImages: Map<string, string>,
    aristonProducts: CatalogAristonProduct[],
    embeddedState: QuotationPayloadState | null,
    todayIso: string,
): QuotationPayloadState {
    const baseForm = embeddedState?.quoteForm ?? {};
    const quoteForm: AristonQuoteFormState = {
        documentTitle:
            baseForm.documentTitle || getCellText(grid, 5, 1) || 'BÁO GIÁ BƠM NHIỆT ARISTON',
        recipientCompany: baseForm.recipientCompany || '',
        recipientPerson: baseForm.recipientPerson || '',
        contactPhone: baseForm.contactPhone || '',
        quoteDate: baseForm.quoteDate || todayIso,
        validUntilDate: baseForm.validUntilDate || todayIso,
        projectName: baseForm.projectName || '',
        vatPercent: baseForm.vatPercent ?? 8,
        signerName: baseForm.signerName ?? '',
        signerPhone: baseForm.signerPhone ?? '',
        signerEmail: baseForm.signerEmail ?? '',
    };

    const titleCell = getCellText(grid, 5, 1);
    if (titleCell) {
        quoteForm.documentTitle = titleCell;
    }

    let tableHeaderRow = 0;
    for (let r = 5; r <= Math.min(maxRow, 25); r++) {
        const c1 = getCellText(grid, r, 1);
        const c2 = getCellText(grid, r, 2) || getCellText(grid, r, 3);
        const c1Lower = c1.toLowerCase();

        if (c1Lower.startsWith('kính gửi')) {
            quoteForm.recipientCompany = c2;
        } else if (c1Lower.startsWith('người nhận')) {
            quoteForm.recipientPerson = c2;
        } else if (c1Lower.startsWith('thông tin liên lạc')) {
            quoteForm.contactPhone = c2;
        } else if (c1Lower.startsWith('thời gian')) {
            quoteForm.quoteDate = parseSlashDateToIso(c2, todayIso);
        } else if (c1Lower.startsWith('project')) {
            quoteForm.projectName = c2;
        } else if (
            c1Lower.includes('sản phẩm') &&
            getCellText(grid, r, 2).toLowerCase().includes('mã sản phẩm')
        ) {
            tableHeaderRow = r;
            break;
        }
    }

    const items: AristonQuotationItemState[] = [];
    let summaryStartRow = maxRow + 1;

    if (tableHeaderRow > 0) {
        let embeddedIdx = 0;
        for (let r = tableHeaderRow + 1; r <= maxRow; r++) {
            const c1 = getCellText(grid, r, 1);
            if (c1.toUpperCase().startsWith('TỔNG CỘNG')) {
                summaryStartRow = r;
                break;
            }
            const c2 = getCellText(grid, r, 2);
            const c3 = getCellText(grid, r, 3);
            const c5 = getCellText(grid, r, 5);
            const c6 = getCellText(grid, r, 6);
            const c7 = getCellText(grid, r, 7);
            const c8 = getCellText(grid, r, 8);

            if (!c1 && !c2 && !c6 && !c7) {
                continue;
            }

            const matchedProd = c2
                ? aristonProducts.find(
                      (p) => p.name.trim().toLowerCase() === c2.trim().toLowerCase(),
                  )
                : undefined;

            const embeddedItem = embeddedState?.items?.[embeddedIdx];
            embeddedIdx++;

            const extractedImg = rowImages.get(`${r}:4`) || '';
            let imageUrl = '';
            if (matchedProd && matchedProd.image?.[0]) {
                imageUrl = resolveProductImageUrl(matchedProd.image[0]);
            } else if (embeddedItem?.imageUrl) {
                imageUrl = embeddedItem.imageUrl;
            } else if (extractedImg) {
                imageUrl = extractedImg;
            }

            items.push({
                uid: makeUid('ariston-row'),
                productId: matchedProd?.id ?? embeddedItem?.productId ?? null,
                productLabel:
                    c1 || embeddedItem?.productLabel || 'Máy nước nóng gián tiếp Ariston',
                modelCode: c2,
                origin: c3 || embeddedItem?.origin || 'Ý-Việt Nam',
                imageUrl,
                unit: c5 || embeddedItem?.unit || 'Máy',
                specifications: c6,
                unitPrice: Math.max(
                    0,
                    Math.round(parseFormattedNumber(c7, embeddedItem?.unitPrice ?? 0)),
                ),
                quantity: Math.max(
                    1,
                    Math.round(parseFormattedNumber(c8, embeddedItem?.quantity ?? 1)),
                ),
            });
        }
    }

    let afterThanks = false;
    let signerNameFound = false;

    for (let r = summaryStartRow; r <= maxRow; r++) {
        const c1 = getCellText(grid, r, 1);
        if (!c1) {
            continue;
        }
        const c1Lower = c1.toLowerCase();

        if (c1Lower.startsWith('thuế vat')) {
            const vatMatch = c1.match(/(\d+(?:\.\d+)?)\s*%/);
            if (vatMatch) {
                quoteForm.vatPercent = Number(vatMatch[1]);
            }
        } else if (c1Lower.includes('báo giá có hiệu lực đến ngày')) {
            const parts = c1.split(':');
            const datePart = parts.slice(1).join(':').trim();
            if (datePart) {
                quoteForm.validUntilDate = parseSlashDateToIso(datePart, quoteForm.validUntilDate);
            }
        } else if (c1Lower.includes('many thanks')) {
            afterThanks = true;
            quoteForm.signerName = '';
            quoteForm.signerPhone = '';
            quoteForm.signerEmail = '';
        } else if (afterThanks) {
            if (/^hp\s*:/i.test(c1)) {
                quoteForm.signerPhone = c1.replace(/^hp\s*:\s*/i, '').trim();
            } else if (/^email\s*:/i.test(c1)) {
                quoteForm.signerEmail = c1.replace(/^email\s*:\s*/i, '').trim();
            } else if (!signerNameFound) {
                quoteForm.signerName = c1;
                signerNameFound = true;
            }
        }
    }

    return {
        version: 1,
        quotationType: 'ariston',
        activeQuotationType: 'ariston',
        quoteForm,
        items: items.length > 0 ? items : embeddedState?.items ?? [],
    };
}

function parseKflexWorksheet(
    grid: Map<number, Map<number, string>>,
    merges: Array<{ r1: number; c1: number; r2: number; c2: number }>,
    maxRow: number,
    kflexPrices: CatalogKflexPrice[],
    embeddedState: QuotationPayloadState | null,
    todayIso: string,
): QuotationPayloadState {
    for (const m of merges) {
        if (m.c1 === 14 && m.c2 === 14 && m.r2 > m.r1) {
            const topNote = getCellText(grid, m.r1, 14);
            if (topNote) {
                for (let rr = m.r1 + 1; rr <= m.r2; rr++) {
                    let rowMap = grid.get(rr);
                    if (!rowMap) {
                        rowMap = new Map<number, string>();
                        grid.set(rr, rowMap);
                    }
                    if (!rowMap.get(14)) {
                        rowMap.set(14, topNote);
                    }
                }
            }
        }
    }

    const baseKf = embeddedState?.kflexQuoteForm ?? {};
    const kflexQuoteForm: KflexQuoteFormState = {
        documentTitle: baseKf.documentTitle || getCellText(grid, 5, 1) || 'BÁO GIÁ BẢO ÔN K-FLEX',
        recipientCompany: baseKf.recipientCompany || '',
        recipientPerson: baseKf.recipientPerson || '',
        projectName: baseKf.projectName || '',
        quoteDate: baseKf.quoteDate || todayIso,
        validUntilDate: baseKf.validUntilDate || todayIso,
        vatPercent: baseKf.vatPercent ?? 8,
        introText:
            baseKf.introText ||
            'Công ty Star Light là đơn vị phân phối mảng dự án sản phẩm bảo ôn K-FLEX',
        paymentTerms: baseKf.paymentTerms || 'Thỏa thuận',
        deliveryMethod: baseKf.deliveryMethod || 'Giao hàng tại chân công trình',
        deliveryTime:
            baseKf.deliveryTime ||
            'tối thiểu 40 ngày (đối với hàng nhập khẩu) kể từ ngày đơn đặt hàng được xác nhận, Hiện đơn hàng đang có sẵn',
        contactName: baseKf.contactName || 'Mr.Kiệt',
        contactHp: baseKf.contactHp || '0979.807.234',
        contactEmail: baseKf.contactEmail || 'tuankiet@starlightgroup.vn',
    };

    const titleCell = getCellText(grid, 5, 1);
    if (titleCell) {
        kflexQuoteForm.documentTitle = titleCell;
    }

    let tableHeaderStartRow = 0;
    for (let r = 5; r <= Math.min(maxRow, 25); r++) {
        const c1 = getCellText(grid, r, 1);
        const cVal = getCellText(grid, r, 3) || getCellText(grid, r, 2);
        const c1Lower = c1.toLowerCase();

        if (c1Lower.startsWith('kính gửi')) {
            kflexQuoteForm.recipientCompany = cVal;
        } else if (c1Lower.startsWith('người nhận')) {
            kflexQuoteForm.recipientPerson = cVal;
        } else if (c1Lower.startsWith('project')) {
            kflexQuoteForm.projectName = cVal;
        } else if (c1Lower.startsWith('thời gian')) {
            kflexQuoteForm.quoteDate = parseSlashDateToIso(cVal, todayIso);
        } else if (c1 === 'TT' && getCellText(grid, r, 2).toLowerCase().includes('tên vật tư')) {
            tableHeaderStartRow = r;
            const prevLine = getCellText(grid, r - 1, 1);
            if (prevLine && !prevLine.toLowerCase().startsWith('thời gian')) {
                kflexQuoteForm.introText = prevLine;
            }
            break;
        }
    }

    const kflexGroups: KflexQuotationGroupState[] = [];
    const detectedLines = new Set<KflexProductLineKey>();
    let summaryStartRow = maxRow + 1;

    if (tableHeaderStartRow > 0) {
        const dataStartRow = tableHeaderStartRow + 2;
        let currentGroup: KflexQuotationGroupState | null = null;

        for (let r = dataStartRow; r <= maxRow; r++) {
            const c1 = getCellText(grid, r, 1);
            const c2 = getCellText(grid, r, 2);
            const c3 = getCellText(grid, r, 3);
            const c4 = getCellText(grid, r, 4);
            const c5 = getCellText(grid, r, 5);
            const c6 = getCellText(grid, r, 6);
            const c7 = getCellText(grid, r, 7);
            const c8 = getCellText(grid, r, 8);
            const c9 = getCellText(grid, r, 9);
            const c10 = getCellText(grid, r, 10);
            const c11 = getCellText(grid, r, 11);
            const c12 = getCellText(grid, r, 12);
            const c14 = getCellText(grid, r, 14);

            if (c1.toUpperCase().startsWith('TỔNG CỘNG')) {
                summaryStartRow = r;
                break;
            }

            const isMergedSection = merges.some(
                (m) => m.r1 === r && m.r2 === r && m.c1 === 2 && m.c2 >= 13,
            );
            const isSectionRow =
                isMergedSection || (!c1 && Boolean(c2) && !c3 && !c6 && !c10 && !c11 && !c12);

            if (isSectionRow) {
                currentGroup = {
                    uid: makeUid('kflex-grp'),
                    hasTitle: true,
                    title: c2,
                    items: [],
                };
                kflexGroups.push(currentGroup);
                continue;
            }

            if (!c1 && !c2 && !c3 && !c6 && !c12) {
                continue;
            }

            if (!currentGroup) {
                currentGroup = {
                    uid: makeUid('kflex-grp'),
                    hasTitle: false,
                    title: '',
                    items: [],
                };
                kflexGroups.push(currentGroup);
            }

            const brandAndName = `${c3} ${c2}`.toLowerCase();
            let productLine: KflexProductLineKey = 'st';
            if (brandAndName.includes('protect')) {
                productLine = 'k_protect';
            } else if (brandAndName.includes('m-flex') || brandAndName.includes('mflex')) {
                productLine = 'm_flex';
            }
            detectedLines.add(productLine);

            const qtyM = c10 ? parseFormattedNumber(c10, 0) : null;
            const qtyM2 = c11 ? parseFormattedNumber(c11, 0) : null;

            const isSheet =
                (qtyM2 !== null && qtyM === null) ||
                Boolean(c8) ||
                c2.toLowerCase().startsWith('tấm');
            const formType: 'tube' | 'sheet' = isSheet ? 'sheet' : 'tube';

            const typeLower = c5.toLowerCase();
            let variantType = formType === 'tube' ? 'alu' : 'alu_or_glue';
            if (formType === 'tube') {
                variantType = typeLower.includes('nhôm') ? 'alu' : 'foam';
            } else {
                if (typeLower.includes('cả keo và nhôm')) {
                    variantType = 'alu_and_glue';
                } else if (typeLower.includes('nhôm') || typeLower.includes('keo')) {
                    variantType = 'alu_or_glue';
                } else {
                    variantType = 'foam';
                }
            }

            const matchedPrice = kflexPrices.find(
                (p) =>
                    p.product_line === productLine &&
                    p.form_type === formType &&
                    p.variant_type === variantType &&
                    String(Number(p.thickness) || p.thickness).trim() ===
                        String(Number(c6) || c6).trim() &&
                    (formType === 'sheet' ||
                        String(Number(p.diameter) || p.diameter || '').trim() ===
                            String(Number(c7) || c7).trim()),
            );

            currentGroup.items.push({
                uid: makeUid('kflex-item'),
                kflexPriceId: matchedPrice?.id ?? null,
                itemName: c2,
                brandLabel:
                    c3 ||
                    (productLine === 'k_protect'
                        ? 'K-Flex K-Protect'
                        : productLine === 'm_flex'
                          ? 'K-Flex M-Flex'
                          : 'K-Flex ST'),
                originLabel: c4 || (productLine === 'm_flex' ? 'Ý - VN' : 'Ý - Malaysia'),
                productTypeLabel: c5,
                productLine,
                formType,
                variantType,
                thickness: c6,
                innerDiameter: c7,
                widthM: c8,
                lengthM: c9,
                qtyM,
                qtyM2,
                unitPrice: Math.max(
                    0,
                    Math.round(parseFormattedNumber(c12, matchedPrice?.price ?? 0)),
                ),
                note: c14,
            });
        }
    }

    let afterThanks = false;
    for (let r = summaryStartRow; r <= maxRow; r++) {
        const c1 = getCellText(grid, r, 1);
        const c2 = getCellText(grid, r, 2);
        const c1Lower = c1.toLowerCase();

        if (c1Lower.startsWith('thuế vat')) {
            const vatMatch = c1.match(/(\d+(?:\.\d+)?)\s*%/);
            if (vatMatch) {
                kflexQuoteForm.vatPercent = Number(vatMatch[1]);
            }
        } else if (c1Lower.includes('1. báo giá có hiệu lực đến ngày')) {
            const datePart = c1.split(':').slice(1).join(':').trim();
            if (datePart) {
                kflexQuoteForm.validUntilDate = parseSlashDateToIso(
                    datePart,
                    kflexQuoteForm.validUntilDate,
                );
            }
        } else if (c1Lower.includes('2. phương thức thanh toán')) {
            kflexQuoteForm.paymentTerms = c1.split(':').slice(1).join(':').trim();
        } else if (c1Lower.includes('3. phương thức giao hàng')) {
            kflexQuoteForm.deliveryMethod = c1.split(':').slice(1).join(':').trim();
        } else if (c1Lower.includes('4. thời gian giao hàng')) {
            kflexQuoteForm.deliveryTime = c1.split(':').slice(1).join(':').trim();
        } else if (
            c1Lower.includes('many thanks') ||
            c2.toLowerCase().includes('many thanks')
        ) {
            afterThanks = true;
        } else if (afterThanks) {
            if (c1Lower === 'hp' || c1Lower.startsWith('hp:')) {
                kflexQuoteForm.contactHp = c2 || c1.replace(/^hp\s*:\s*/i, '').trim();
            } else if (c1Lower.startsWith('email')) {
                kflexQuoteForm.contactEmail = c2 || c1.replace(/^email\s*:\s*/i, '').trim();
            } else if (c1) {
                kflexQuoteForm.contactName = c1;
            }
        }
    }

    const selectedLines =
        detectedLines.size > 0
            ? Array.from(detectedLines)
            : (embeddedState?.kflexSelectedProductLines ?? ['st']);

    return {
        version: 1,
        quotationType: 'second',
        activeQuotationType: 'second',
        kflexQuoteForm,
        kflexSelectedProductLines: selectedLines,
        kflexGroups:
            kflexGroups.filter((g) => g.items.length > 0).length > 0
                ? kflexGroups.filter((g) => g.items.length > 0)
                : (embeddedState?.kflexGroups ?? []),
    };
}

function decodeHexToUnicode(hex: string): string {
    const clean = hex.replace(/[^0-9A-Fa-f]/g, '');
    if (!clean) {
        return '';
    }
    let out = '';
    for (let i = 0; i < clean.length; i += 4) {
        const code = parseInt(clean.slice(i, i + 4), 16);
        if (!Number.isNaN(code) && code > 0) {
            out += String.fromCharCode(code);
        }
    }
    return out;
}

function parseToUnicodeCMap(cmapText: string, map: Map<string, string>): void {
    const bfCharBlocks = cmapText.matchAll(/beginbfchar([\s\S]*?)endbfchar/g);
    for (const block of bfCharBlocks) {
        const pairs = block[1].matchAll(/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/g);
        for (const p of pairs) {
            const src = p[1].toUpperCase();
            const dst = decodeHexToUnicode(p[2]);
            if (dst) {
                map.set(src, dst);
            }
        }
    }

    const bfRangeBlocks = cmapText.matchAll(/beginbfrange([\s\S]*?)endbfrange/g);
    for (const block of bfRangeBlocks) {
        const arrayRanges = block[1].matchAll(/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[([\s\S]*?)\]/g);
        for (const ar of arrayRanges) {
            const start = parseInt(ar[1], 16);
            const end = parseInt(ar[2], 16);
            const padLen = ar[1].length;
            const targets = Array.from(ar[3].matchAll(/<([0-9A-Fa-f]+)>/g)).map((m) => m[1]);
            for (let code = start, idx = 0; code <= end && idx < targets.length; code++, idx++) {
                const key = code.toString(16).toUpperCase().padStart(padLen, '0');
                const dst = decodeHexToUnicode(targets[idx]);
                if (dst) {
                    map.set(key, dst);
                }
            }
        }

        const blockWithoutArrays = block[1].replace(
            /<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[[\s\S]*?\]/g,
            '',
        );
        const ranges = blockWithoutArrays.matchAll(
            /<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/g,
        );
        for (const r of ranges) {
            const start = parseInt(r[1], 16);
            const end = parseInt(r[2], 16);
            const padLen = r[1].length;
            if (start === end && r[3].length > 4) {
                const key = start.toString(16).toUpperCase().padStart(padLen, '0');
                const dst = decodeHexToUnicode(r[3]);
                if (dst) {
                    map.set(key, dst);
                }
                continue;
            }
            let target = parseInt(r[3], 16);
            if (!Number.isNaN(start) && !Number.isNaN(end) && !Number.isNaN(target)) {
                for (let code = start; code <= end && code - start < 1024; code++) {
                    const key = code.toString(16).toUpperCase().padStart(padLen, '0');
                    map.set(key, String.fromCharCode(target));
                    target++;
                }
            }
        }
    }
}

function shouldInsertPdfSpace(prevText: string, nextText: string, gap: number): boolean {
    if (gap <= 2.4 || prevText.endsWith(' ') || nextText.startsWith(' ')) {
        return false;
    }
    if (/^[.,:;)\]]/.test(nextText) || prevText.endsWith('(') || prevText.endsWith('-')) {
        return false;
    }
    if (
        gap < 6.5 &&
        /\p{L}$/u.test(prevText) &&
        /^[àáạảãăắằẳẵặâấầẩẫậèéẹẻẽêếềểễệìíịỉĩòóọỏõôốồổỗộơớờởỡợùúụủũưứừửữựỳýỵỷỹ]/i.test(nextText)
    ) {
        return false;
    }
    return true;
}

type PdfCellRun = {
    x: number;
    endX: number;
    centerX: number;
    text: string;
};

type PdfStructuredRow = {
    page: number;
    y: number;
    cellRuns: PdfCellRun[];
    cells: string[];
    line: string;
};

function buildPdfColumnDividers(
    headerRows: PdfStructuredRow[],
    tableMinX: number,
    tableMaxX: number,
    proportions: number[],
    colMatchers: Array<(txtLower: string) => boolean>,
    vLines: number[] = [],
): number[] {
    const colCount = proportions.length;
    const tableVLines = vLines.filter((vl) => vl >= tableMinX - 25 && vl <= tableMaxX + 35);
    if (tableVLines.length === colCount + 1) {
        return tableVLines.slice(1, colCount);
    }

    const span = Math.max(200, tableMaxX - tableMinX);
    const defaultMin: number[] = [];
    const defaultMax: number[] = [];
    let acc = 0;
    for (let c = 0; c < colCount; c++) {
        defaultMin[c] = tableMinX + acc * span;
        acc += proportions[c];
        defaultMax[c] = tableMinX + acc * span;
    }

    const detectedMin: Array<number | null> = new Array(colCount).fill(null);
    const detectedMax: Array<number | null> = new Array(colCount).fill(null);

    for (const hr of headerRows) {
        for (const cr of hr.cellRuns) {
            const txtLower = cr.text.toLowerCase().trim();
            if (!txtLower || txtLower.includes('kích thước') || txtLower.includes('khối lượng')) {
                continue;
            }
            for (let c = 0; c < colCount; c++) {
                if (colMatchers[c](txtLower)) {
                    detectedMin[c] = detectedMin[c] === null ? cr.x : Math.min(detectedMin[c]!, cr.x);
                    detectedMax[c] = detectedMax[c] === null ? cr.endX : Math.max(detectedMax[c]!, cr.endX);
                    break;
                }
            }
        }
    }

    const dividers: number[] = [];
    for (let c = 0; c < colCount - 1; c++) {
        if (
            detectedMax[c] !== null &&
            detectedMin[c + 1] !== null &&
            detectedMin[c + 1]! > detectedMax[c]!
        ) {
            dividers[c] = (detectedMax[c]! + detectedMin[c + 1]!) / 2;
        } else {
            dividers[c] = defaultMax[c];
        }
    }
    return dividers;
}

function resolvePdfColumnIndex(
    x: number,
    centerX: number,
    dividers: number[],
    leftAlignedCols: number[] = [],
): number {
    const colCount = dividers.length + 1;
    for (const lc of leftAlignedCols) {
        const leftBound = lc === 0 ? -1e9 : dividers[lc - 1] - 4;
        const rightBound = lc < dividers.length ? dividers[lc] : 1e9;
        if (x >= leftBound && x < rightBound && centerX < rightBound + 8) {
            return lc;
        }
    }
    for (let c = 0; c < dividers.length; c++) {
        if (centerX < dividers[c]) {
            return c;
        }
    }
    return colCount - 1;
}

function decodePdfStringToken(token: string, activeCMap: Map<string, string>, fallbackCMap: Map<string, string>): string {
    if (token.startsWith('<') && token.endsWith('>')) {
        const hex = token.slice(1, -1).replace(/\s+/g, '').toUpperCase();
        if (!hex) {
            return '';
        }
        const map = activeCMap.size > 0 ? activeCMap : fallbackCMap;
        const step = map.has(hex.slice(0, 4)) || hex.length % 4 === 0 ? 4 : 2;
        let out = '';
        for (let i = 0; i < hex.length; i += step) {
            const code = hex.slice(i, i + step);
            if (activeCMap.has(code)) {
                out += activeCMap.get(code)!;
            } else if (fallbackCMap.has(code)) {
                out += fallbackCMap.get(code)!;
            } else {
                const charCode = parseInt(code, 16);
                if (charCode >= 32 && charCode <= 126) {
                    out += String.fromCharCode(charCode);
                }
            }
        }
        return out;
    }

    if (token.startsWith('(') && token.endsWith(')')) {
        return token
            .slice(1, -1)
            .replace(/\\n/g, '\n')
            .replace(/\\r/g, '\r')
            .replace(/\\t/g, '\t')
            .replace(/\\\(/g, '(')
            .replace(/\\\)/g, ')')
            .replace(/\\\\/g, '\\');
    }

    return '';
}

async function parseExportedQuotationPdf(
    file: File,
    aristonProducts: CatalogAristonProduct[],
    kflexPrices: CatalogKflexPrice[],
    todayIso: string,
): Promise<QuotationPayloadState> {
    const buffer = await file.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    const latin1Decoder = new TextDecoder('latin1');
    const utf8Decoder = new TextDecoder('utf-8', { fatal: false });
    const rawPdfText = latin1Decoder.decode(bytes);

    // 1. Direct check in raw PDF text
    const directMatch = rawPdfText.match(/KBQDATA:([A-Za-z0-9+/=\s]+):ENDKBQDATA/);
    if (directMatch) {
        const decoded = decodeQuotationPayloadBase64(directMatch[1]);
        if (decoded) {
            return decoded;
        }
    }

    // 2. Parse PDF indirect objects and decompress all streams
    const objectStreams = new Map<number, string>();
    const objectHeaders = new Map<number, string>();
    const allDecompressedStreams: string[] = [];

    const objRegex = /(\d+)\s+\d+\s+obj\b([\s\S]*?)endobj/g;
    let objMatch: RegExpExecArray | null;
    while ((objMatch = objRegex.exec(rawPdfText)) !== null) {
        const objId = Number(objMatch[1]);
        const objBody = objMatch[2];
        const streamPos = objBody.search(/stream\r?\n/);
        if (streamPos === -1) {
            objectHeaders.set(objId, objBody);
            continue;
        }
        objectHeaders.set(objId, objBody.slice(0, streamPos));

        const absObjStart = objMatch.index + objMatch[0].indexOf(objBody);
        const streamHeaderMatch = objBody.slice(streamPos).match(/^stream\r?\n/);
        if (!streamHeaderMatch) {
            continue;
        }
        const startOffset = absObjStart + streamPos + streamHeaderMatch[0].length;
        const endStreamRel = objBody.indexOf('endstream', streamPos + streamHeaderMatch[0].length);
        if (endStreamRel === -1) {
            continue;
        }
        let streamEnd = absObjStart + endStreamRel;
        if (rawPdfText[streamEnd - 1] === '\n') {
            streamEnd--;
        }
        if (rawPdfText[streamEnd - 1] === '\r') {
            streamEnd--;
        }

        const slice = bytes.subarray(startOffset, streamEnd);
        let decodedStream = '';
        try {
            const inflated = await inflateStreamBytes(slice, 'deflate');
            decodedStream = utf8Decoder.decode(inflated);
        } catch {
            try {
                const inflatedRaw = await inflateStreamBytes(slice, 'deflate-raw');
                decodedStream = utf8Decoder.decode(inflatedRaw);
            } catch {
                decodedStream = latin1Decoder.decode(slice);
            }
        }
        objectStreams.set(objId, decodedStream);
        allDecompressedStreams.push(decodedStream);
    }

    for (const s of allDecompressedStreams) {
        const m = s.match(/KBQDATA:([A-Za-z0-9+/=\s]+):ENDKBQDATA/);
        if (m) {
            const decoded = decodeQuotationPayloadBase64(m[1]);
            if (decoded) {
                return decoded;
            }
        }
    }

    // 3. Build per-font ToUnicode CMaps so /F1, /F2, /F3 never collide on glyph <0001>
    const cmapsByObjId = new Map<number, Map<string, string>>();
    const fallbackCMap = new Map<string, string>();
    for (const [objId, streamText] of objectStreams.entries()) {
        if (streamText.includes('beginbfchar') || streamText.includes('beginbfrange')) {
            const cmap = new Map<string, string>();
            parseToUnicodeCMap(streamText, cmap);
            cmapsByObjId.set(objId, cmap);
            for (const [k, v] of cmap.entries()) {
                if (!fallbackCMap.has(k)) {
                    fallbackCMap.set(k, v);
                }
            }
        }
    }

    // Map font object ID -> ToUnicode object ID
    const fontObjToCMap = new Map<number, Map<string, string>>();
    for (const [objId, header] of objectHeaders.entries()) {
        const toUniMatch = header.match(/\/ToUnicode\s+(\d+)\s+\d+\s+R/);
        if (toUniMatch) {
            const cmapObjId = Number(toUniMatch[1]);
            const cmap = cmapsByObjId.get(cmapObjId);
            if (cmap) {
                fontObjToCMap.set(objId, cmap);
            }
        }
    }

    // Map font resource name (/F1, /F2, /TT0, ...) -> CMap
    const fontNameToCMap = new Map<string, Map<string, string>>();
    for (const header of objectHeaders.values()) {
        const fontRefs = header.matchAll(/\/([A-Za-z0-9_.-]+)\s+(\d+)\s+\d+\s+R/g);
        for (const ref of fontRefs) {
            const fontName = ref[1];
            const fontObjId = Number(ref[2]);
            const cmap = fontObjToCMap.get(fontObjId);
            if (cmap) {
                fontNameToCMap.set(fontName, cmap);
            }
        }
    }

    // 4. Decode PDF content streams sequentially
    type PdfTextRun = {
        page: number;
        x: number;
        endX: number;
        y: number;
        fontSize: number;
        text: string;
    };

    const estimateRunWidth = (txt: string, fontSize: number): number => {
        let w = 0;
        for (let i = 0; i < txt.length; i++) {
            const ch = txt[i];
            if (ch === ' ' || ch === 'i' || ch === 'l' || ch === 'I' || ch === '.' || ch === ',' || ch === ':') {
                w += fontSize * 0.28;
            } else if (ch.toUpperCase() === ch && ch.toLowerCase() !== ch) {
                w += fontSize * 0.65;
            } else {
                w += fontSize * 0.50;
            }
        }
        return w;
    };

    const textRuns: PdfTextRun[] = [];
    const rawVLines: number[] = [];
    const streamOrderChunks: string[] = [];
    let pageIdx = 0;

    for (const s of allDecompressedStreams) {
        if (!s.includes('BT') || !s.includes('ET')) {
            continue;
        }
        pageIdx++;
        const reMatches = s.matchAll(/([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+re\b/g);
        for (const rm of reMatches) {
            const rx = parseFloat(rm[1]);
            const rw = parseFloat(rm[3]);
            const rh = parseFloat(rm[4]);
            if (Math.abs(rw) < 2.0 && Math.abs(rh) > 15.0 && !Number.isNaN(rx)) {
                rawVLines.push(Math.round(rx * 100) / 100);
            }
        }

        let activeCMap = fallbackCMap;
        let fontSize = 6.0;
        let tmA = 1.0;
        let tmD = 1.0;
        let curX = 0;
        let curY = 0;

        const opRegex =
            /\/([A-Za-z0-9_.-]+)\s+([\d.-]+)\s+Tf|([\d.-]+)\s+([\d.-]+)\s+Td|([\d.-]+)\s+([\d.-]+)\s+TD|([\d.-]+)\s+[\d.-]+\s+[\d.-]+\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+Tm|\[(?:<[0-9A-Fa-f\s]+>|\((?:\\.|[^\\()])*\)|[\s\d.-])*\]\s*TJ|<[0-9A-Fa-f\s]+>\s*Tj|\((?:\\.|[^\\()])*\)\s*Tj/g;
        let opMatch: RegExpExecArray | null;

        while ((opMatch = opRegex.exec(s)) !== null) {
            const full = opMatch[0];
            if (opMatch[1] !== undefined && opMatch[2] !== undefined) {
                activeCMap = fontNameToCMap.get(opMatch[1]) ?? fallbackCMap;
                const rawSize = parseFloat(opMatch[2]) || 6.0;
                fontSize = rawSize > 0 ? rawSize : 6.0;
            } else if (opMatch[3] !== undefined && opMatch[4] !== undefined) {
                curX += (parseFloat(opMatch[3]) || 0) * Math.abs(tmA);
                curY += (parseFloat(opMatch[4]) || 0) * tmD;
            } else if (opMatch[5] !== undefined && opMatch[6] !== undefined) {
                curX += (parseFloat(opMatch[5]) || 0) * Math.abs(tmA);
                curY += (parseFloat(opMatch[6]) || 0) * tmD;
            } else if (
                opMatch[7] !== undefined &&
                opMatch[8] !== undefined &&
                opMatch[9] !== undefined &&
                opMatch[10] !== undefined
            ) {
                tmA = parseFloat(opMatch[7]) || 1.0;
                tmD = parseFloat(opMatch[8]) || 1.0;
                curX = parseFloat(opMatch[9]) || 0;
                curY = parseFloat(opMatch[10]) || 0;
            } else {
                const effFontSize = Math.max(
                    3.5,
                    fontSize * (Math.abs(tmD) > 1.5 && fontSize <= 1.5 ? Math.abs(tmD) : 1.0),
                );
                let txt = '';
                const innerTokens = full.matchAll(/<[0-9A-Fa-f\s]+>|\((?:\\.|[^\\()])*\)|(-?\d+(?:\.\d+)?)/g);
                for (const it of innerTokens) {
                    if (it[1] !== undefined) {
                        const kern = parseFloat(it[1]);
                        if (kern < -180 && !txt.endsWith(' ')) {
                            txt += ' ';
                        }
                    } else {
                        txt += decodePdfStringToken(it[0], activeCMap, fallbackCMap);
                    }
                }
                if (txt) {
                    const normTxt = typeof txt.normalize === 'function' ? txt.normalize('NFC') : txt;
                    streamOrderChunks.push(normTxt);
                    const estW = estimateRunWidth(normTxt, effFontSize);
                    textRuns.push({
                        page: pageIdx,
                        x: curX,
                        endX: curX + estW,
                        y: curY,
                        fontSize: effFontSize,
                        text: normTxt,
                    });
                }
            }
        }
    }

    // Check stream-order text first so wrapped KBQDATA lines are never scrambled by (x, y) sorting
    const streamOrderCompact = streamOrderChunks.join('').replace(/\s+/g, '');
    const streamKbqMatch = streamOrderCompact.match(/KBQDATA:([A-Za-z0-9+/=]+):ENDKBQDATA/);
    if (streamKbqMatch) {
        const decoded = decodeQuotationPayloadBase64(streamKbqMatch[1]);
        if (decoded) {
            return decoded;
        }
    }

    // Auto-detect flipped Y coordinate space (top-down generators where header Y < summary Y)
    let topMarkerY: number | null = null;
    let bottomMarkerY: number | null = null;
    for (const r of textRuns) {
        if (r.page !== 1) {
            continue;
        }
        const u = r.text.toUpperCase();
        if (
            topMarkerY === null &&
            (u.includes('BÁO GIÁ') || u.includes('STAR LIGHT') || u.includes('KÍNH GỬI'))
        ) {
            topMarkerY = r.y;
        }
        if (u.includes('TỔNG CỘNG') || u.includes('MANY THANKS') || u.includes('ĐIỀU KIỆN')) {
            bottomMarkerY = r.y;
        }
    }
    if (topMarkerY !== null && bottomMarkerY !== null && topMarkerY < bottomMarkerY) {
        for (const r of textRuns) {
            r.y = -r.y;
        }
    }

    rawVLines.sort((a, b) => a - b);
    const vLines: number[] = [];
    for (const vx of rawVLines) {
        if (vLines.length === 0 || Math.abs(vx - vLines[vLines.length - 1]) > 2.0) {
            vLines.push(vx);
        }
    }

    // Group runs into visual rows by page and Y coordinate (tolerance 2.5pt)
    type PdfVisualRow = {
        page: number;
        y: number;
        runs: PdfTextRun[];
    };
    const visualRows: PdfVisualRow[] = [];
    for (const run of textRuns) {
        const existing = visualRows.find((r) => r.page === run.page && Math.abs(r.y - run.y) <= 2.5);
        if (existing) {
            existing.runs.push(run);
        } else {
            visualRows.push({ page: run.page, y: run.y, runs: [run] });
        }
    }

    visualRows.sort((a, b) => (a.page !== b.page ? a.page - b.page : b.y - a.y));

    const structuredRows: PdfStructuredRow[] = [];
    const decodedLines: string[] = [];

    for (const row of visualRows) {
        row.runs.sort((a, b) => a.x - b.x);
        const mergedCells: PdfTextRun[] = [];
        for (const run of row.runs) {
            if (mergedCells.length === 0) {
                mergedCells.push({ ...run });
                continue;
            }
            const prev = mergedCells[mergedCells.length - 1];
            const gap = run.x - prev.endX;
            const crossesVLine = vLines.some(
                (vl) => prev.x + 1.5 < vl && run.x >= vl - 1.5,
            );
            if (!crossesVLine && gap < 5.5) {
                const needSpace = shouldInsertPdfSpace(prev.text, run.text, gap);
                prev.text += (needSpace ? ' ' : '') + run.text;
                prev.endX = Math.max(prev.endX, run.endX);
            } else {
                mergedCells.push({ ...run });
            }
        }
        const cellRuns: PdfCellRun[] = [];
        for (const c of mergedCells) {
            const cleanText = c.text.replace(/\s+/g, ' ').trim();
            if (cleanText.length > 0) {
                cellRuns.push({
                    x: c.x,
                    endX: c.endX,
                    centerX: (c.x + c.endX) / 2,
                    text: cleanText,
                });
            }
        }
        const cells = cellRuns.map((c) => c.text);
        if (cells.length > 0) {
            const line = cells.join(' ');
            structuredRows.push({
                page: row.page,
                y: row.y,
                cellRuns,
                cells,
                line,
            });
            decodedLines.push(line);
        }
    }

    const combinedText = decodedLines.join('\n');
    const compactText = decodedLines.join('').replace(/\s+/g, '');
    const embeddedInText =
        combinedText.match(/KBQDATA:([A-Za-z0-9+/=\s]+):ENDKBQDATA/) ||
        compactText.match(/KBQDATA:([A-Za-z0-9+/=]+):ENDKBQDATA/);
    if (embeddedInText) {
        const decoded = decodeQuotationPayloadBase64(embeddedInText[1]);
        if (decoded) {
            return decoded;
        }
    }

    if (!combinedText.trim()) {
        throw new Error(
            'Không thể đọc nội dung từ file PDF này. Vui lòng nhập file Excel (.xlsx) hoặc file PDF xuất từ hệ thống.',
        );
    }

    // 5. Structured parsing of extracted PDF lines and cells for Ariston & K-Flex
    const upperAll = combinedText.toUpperCase();
    const isKflex = upperAll.includes('K-FLEX') || upperAll.includes('BẢO ÔN');

    if (isKflex) {
        return parseKflexPdfLines(decodedLines, structuredRows, combinedText, kflexPrices, todayIso, vLines);
    }

    return parseAristonPdfLines(decodedLines, structuredRows, combinedText, aristonProducts, todayIso, vLines);
}

function readNextFieldValue(lines: string[], index: number, prefixRegex: RegExp): string {
    const inline = lines[index].replace(prefixRegex, '').trim();
    if (inline) {
        return inline;
    }
    const next = (lines[index + 1] ?? '').trim();
    if (
        !next ||
        /^(kính gửi|người nhận|thông tin liên lạc|thời gian|project|sản phẩm|tt\b|công ty)/i.test(
            next,
        )
    ) {
        return '';
    }
    return next;
}

function parseAristonPdfLines(
    decodedLines: string[],
    structuredRows: PdfStructuredRow[],
    combinedText: string,
    aristonProducts: CatalogAristonProduct[],
    todayIso: string,
    vLines: number[] = [],
): QuotationPayloadState {
    const quoteForm: AristonQuoteFormState = {
        documentTitle: 'BÁO GIÁ BƠM NHIỆT ARISTON',
        recipientCompany: '',
        recipientPerson: '',
        contactPhone: '',
        quoteDate: todayIso,
        validUntilDate: todayIso,
        projectName: '',
        vatPercent: 8,
        signerName: 'Nguyễn Tuấn Kiệt',
        signerPhone: '0979 807 234',
        signerEmail: 'tuankiet@starlightgroup.vn',
    };

    let afterThanks = false;
    let signerFound = false;

    for (let i = 0; i < decodedLines.length; i++) {
        const line = decodedLines[i].trim();
        const lower = line.toLowerCase();

        if (lower.startsWith('báo giá') && !lower.includes('có hiệu lực')) {
            quoteForm.documentTitle = line.replace(/b\s+ảo/i, 'BẢO');
        } else if (lower.startsWith('kính gửi')) {
            quoteForm.recipientCompany = readNextFieldValue(
                decodedLines,
                i,
                /^kính gửi\s*:?\s*/i,
            );
        } else if (lower.startsWith('người nhận')) {
            quoteForm.recipientPerson = readNextFieldValue(
                decodedLines,
                i,
                /^người nhận\s*:?\s*/i,
            );
        } else if (lower.startsWith('thông tin liên lạc')) {
            quoteForm.contactPhone = readNextFieldValue(
                decodedLines,
                i,
                /^thông tin liên lạc\s*:?\s*/i,
            );
        } else if (lower.startsWith('thời gian') && !lower.includes('giao hàng')) {
            const dStr = readNextFieldValue(decodedLines, i, /^thời gian\s*:?\s*/i);
            if (dStr) {
                quoteForm.quoteDate = parseSlashDateToIso(dStr, todayIso);
            }
        } else if (lower.startsWith('project')) {
            quoteForm.projectName = readNextFieldValue(decodedLines, i, /^project\s*:?\s*/i);
        } else if (lower.includes('thuế vat')) {
            const vm = line.match(/(\d+(?:\.\d+)?)\s*%/);
            if (vm) {
                quoteForm.vatPercent = Number(vm[1]);
            }
        } else if (lower.includes('báo giá có hiệu lực đến ngày')) {
            const dateMatch = line.match(/(\d{1,2}[/-]\d{1,2}[/-]\d{4}|\d{4}-\d{2}-\d{2})/);
            if (dateMatch) {
                quoteForm.validUntilDate = parseSlashDateToIso(
                    dateMatch[1],
                    quoteForm.validUntilDate,
                );
            }
        } else if (lower.includes('many thanks')) {
            afterThanks = true;
        } else if (afterThanks) {
            if (/^hp\b/i.test(line)) {
                quoteForm.signerPhone = line.replace(/^hp\s*:?\s*/i, '').trim();
            } else if (/^email\b/i.test(line)) {
                quoteForm.signerEmail = line.replace(/^email\s*:?\s*/i, '').trim();
            } else if (!signerFound && line.length > 1 && !line.includes(':')) {
                quoteForm.signerName = line;
                signerFound = true;
            }
        }
    }

    const items: AristonQuotationItemState[] = [];
    const headerRows: PdfStructuredRow[] = [];
    const bodyRows: PdfStructuredRow[] = [];
    let inTable = false;
    let tableMinX = 1e9;
    let tableMaxX = 0;

    for (const row of structuredRows) {
        const upperLine = row.line.toUpperCase();
        if (upperLine.startsWith('TỔNG CỘNG')) {
            break;
        }
        const isHeader =
            upperLine.includes('MÃ SẢN PHẨM') ||
            upperLine.includes('GIÁ BÁN DỰ ÁN') ||
            upperLine.includes('ĐẶC TÍNH KỸ THUẬT') ||
            upperLine.includes('SELLING PRICE') ||
            upperLine.includes('SPECIFICATIONS') ||
            (upperLine.includes('PRODUCT') && upperLine.includes('MODEL')) ||
            upperLine.trim() === '(VNĐ)' ||
            upperLine.trim() === '(CHIẾC)';
        if (isHeader) {
            inTable = true;
            headerRows.push(row);
            for (const cr of row.cellRuns) {
                tableMinX = Math.min(tableMinX, cr.x);
                tableMaxX = Math.max(tableMaxX, cr.endX);
            }
            continue;
        }
        if (!inTable) {
            continue;
        }
        bodyRows.push(row);
        for (const cr of row.cellRuns) {
            tableMinX = Math.min(tableMinX, cr.x);
            tableMaxX = Math.max(tableMaxX, cr.endX);
        }
    }

    if (tableMinX > tableMaxX) {
        tableMinX = 40;
        tableMaxX = 780;
    }

    const proportions = [0.09, 0.085, 0.055, 0.175, 0.045, 0.265, 0.105, 0.06, 0.12];
    const matchers = [
        (s: string) => s === 'sản phẩm' || s === 'product' || s.startsWith('sản phẩm'),
        (s: string) => s.includes('mã sản phẩm') || s === 'model',
        (s: string) => s.includes('xuất xứ') || s === 'origin',
        (s: string) => s.includes('hình ảnh') || s === 'image',
        (s: string) => s.includes('đơn vị'),
        (s: string) => s.includes('đặc tính') || s.includes('specifications'),
        (s: string) => s.includes('giá bán') || s.includes('selling price'),
        (s: string) => s.includes('số lượng') || s.includes('quantity') || s.includes('(chiếc)'),
        (s: string) => s.includes('thành tiền') || s.includes('amount'),
    ];
    const dividers = buildPdfColumnDividers(headerRows, tableMinX, tableMaxX, proportions, matchers, vLines);

    const anchorIndices: number[] = [];
    for (let idx = 0; idx < bodyRows.length; idx++) {
        const pm = bodyRows[idx].line.match(/\b\d{1,3}(?:[.,]\d{3})+\b/g);
        if (pm && pm.length >= 1) {
            const maxPriceOnRow = Math.max(...pm.map((pStr) => parseFormattedNumber(pStr, 0)));
            if (maxPriceOnRow >= 10000) {
                anchorIndices.push(idx);
            }
        }
    }

    if (anchorIndices.length > 0) {
        type BucketRun = { y: number; x: number; text: string };
        const itemBuckets: BucketRun[][][] = Array.from({ length: anchorIndices.length }, () =>
            Array.from({ length: 9 }, () => []),
        );

        for (let rIdx = 0; rIdx < bodyRows.length; rIdx++) {
            const row = bodyRows[rIdx];
            let targetBucket = 0;
            let bestDist = 1e9;
            for (let bIdx = 0; bIdx < anchorIndices.length; bIdx++) {
                const aRow = bodyRows[anchorIndices[bIdx]];
                const dist =
                    (aRow.page === row.page ? 0 : 10000) + Math.abs(row.y - aRow.y);
                if (dist < bestDist) {
                    bestDist = dist;
                    targetBucket = bIdx;
                }
            }

            if (row.cellRuns.length >= 2 && tableMaxX - tableMinX > 120) {
                for (const cr of row.cellRuns) {
                    const cIdx = resolvePdfColumnIndex(cr.x, cr.centerX, dividers, [5]);
                    itemBuckets[targetBucket][cIdx].push({
                        y: row.y,
                        x: cr.x,
                        text: cr.text,
                    });
                }
            } else if (rIdx === anchorIndices[targetBucket] && row.cells.length >= 4) {
                itemBuckets[targetBucket][0].push({ y: row.y, x: 0, text: row.cells[0] ?? '' });
                itemBuckets[targetBucket][1].push({ y: row.y, x: 1, text: row.cells[1] ?? '' });
                itemBuckets[targetBucket][2].push({ y: row.y, x: 2, text: row.cells[2] ?? '' });
            }
        }

        for (let bIdx = 0; bIdx < anchorIndices.length; bIdx++) {
            const bucket = itemBuckets[bIdx];
            const colJoinedSpace: string[] = [];
            const colJoinedNewline: string[] = [];
            for (let c = 0; c < 9; c++) {
                const cRuns = bucket[c];
                cRuns.sort((a, b) => (Math.abs(a.y - b.y) > 2 ? b.y - a.y : a.x - b.x));
                colJoinedSpace[c] = cRuns
                    .map((r) => r.text)
                    .join(' ')
                    .replace(/\s+/g, ' ')
                    .trim();

                const specLines: string[] = [];
                let curSpecY: number | null = null;
                for (const sr of cRuns) {
                    if (curSpecY === null || Math.abs(sr.y - curSpecY) > 2.5) {
                        specLines.push(sr.text);
                        curSpecY = sr.y;
                    } else {
                        specLines[specLines.length - 1] += ' ' + sr.text;
                    }
                }
                colJoinedNewline[c] = specLines.join('\n').trim();
            }

            const anchorRow = bodyRows[anchorIndices[bIdx]];
            let uPrice = Math.round(parseFormattedNumber(colJoinedSpace[6], 0));
            let totalAmt = Math.round(parseFormattedNumber(colJoinedSpace[8], 0));
            let qty = Math.round(parseFormattedNumber(colJoinedSpace[7], 0));

            if (uPrice < 1000) {
                const pm = anchorRow.line.match(/\b\d{1,3}(?:[.,]\d{3})+\b/g);
                if (pm && pm.length >= 1) {
                    uPrice = Math.round(parseFormattedNumber(pm[0], 0));
                    if (pm.length >= 2) {
                        totalAmt = Math.round(parseFormattedNumber(pm[pm.length - 1], 0));
                    }
                }
            }
            if (qty <= 0) {
                qty =
                    uPrice > 0 && totalAmt >= uPrice
                        ? Math.max(1, Math.round(totalAmt / uPrice))
                        : 1;
            }

            const modelCode = colJoinedSpace[1];
            const productLabel = colJoinedSpace[0];
            const normModel = modelCode.replace(/[\s-]+/g, '').toLowerCase();

            const matchedProd = aristonProducts.find((p) => {
                if (!p.name) {
                    return false;
                }
                const pNorm = p.name.replace(/[\s-]+/g, '').toLowerCase();
                if (
                    normModel &&
                    (pNorm === normModel ||
                        normModel.includes(pNorm) ||
                        pNorm.includes(normModel))
                ) {
                    return true;
                }
                return (
                    !modelCode &&
                    Boolean(productLabel) &&
                    productLabel.toLowerCase().includes(p.name.toLowerCase())
                );
            });

            const origin = colJoinedSpace[2]
                ? colJoinedSpace[2].replace(/\s*-\s*/g, '-')
                : matchedProd?.origin
                  ? `Ý-${matchedProd.origin}`
                  : 'Ý-Việt Nam';

            items.push({
                uid: makeUid('ariston-pdf-row'),
                productId: matchedProd?.id ?? null,
                productLabel:
                    productLabel ||
                    matchedProd?.product_title ||
                    'Máy nước nóng gián tiếp Ariston',
                modelCode: modelCode || matchedProd?.name || '',
                origin,
                imageUrl: resolveProductImageUrl(matchedProd?.image?.[0]),
                unit: colJoinedSpace[4] || 'Máy',
                specifications: colJoinedNewline[5] || matchedProd?.description || '',
                unitPrice: uPrice > 0 ? uPrice : (matchedProd?.price ?? 2386000),
                quantity: qty,
            });
        }
    }

    if (items.length === 0) {
        const sortedProducts = [...aristonProducts].sort(
            (a, b) => (b.name?.length ?? 0) - (a.name?.length ?? 0),
        );
        for (const prod of sortedProducts) {
            if (prod.name && combinedText.includes(prod.name)) {
                items.push({
                    uid: makeUid('ariston-pdf'),
                    productId: prod.id,
                    productLabel: prod.product_title || 'Máy nước nóng gián tiếp Ariston',
                    modelCode: prod.name,
                    origin: prod.origin ? `Ý-${prod.origin}` : 'Ý-Việt Nam',
                    imageUrl: resolveProductImageUrl(prod.image?.[0]),
                    unit: 'Máy',
                    specifications: prod.description || '',
                    unitPrice: prod.price > 0 ? prod.price : 2386000,
                    quantity: 1,
                });
            }
        }
    }

    return {
        version: 1,
        quotationType: 'ariston',
        activeQuotationType: 'ariston',
        quoteForm,
        items,
    };
}

function parseKflexPdfLines(
    decodedLines: string[],
    structuredRows: PdfStructuredRow[],
    combinedText: string,
    kflexPrices: CatalogKflexPrice[],
    todayIso: string,
    vLines: number[] = [],
): QuotationPayloadState {
    const kflexQuoteForm: KflexQuoteFormState = {
        documentTitle: 'BÁO GIÁ BẢO ÔN K-FLEX',
        recipientCompany: '',
        recipientPerson: '',
        projectName: '',
        quoteDate: todayIso,
        validUntilDate: todayIso,
        vatPercent: 8,
        introText: 'Công ty Star Light là đơn vị phân phối mảng dự án sản phẩm bảo ôn K-FLEX',
        paymentTerms: 'Thỏa thuận',
        deliveryMethod: 'Giao hàng tại chân công trình',
        deliveryTime:
            'tối thiểu 40 ngày (đối với hàng nhập khẩu) kể từ ngày đơn đặt hàng được xác nhận, Hiện đơn hàng đang có sẵn',
        contactName: 'Mr.Kiệt',
        contactHp: '0979.807.234',
        contactEmail: 'tuankiet@starlightgroup.vn',
    };

    let afterThanks = false;
    let contactNameFound = false;

    for (let i = 0; i < decodedLines.length; i++) {
        const line = decodedLines[i].trim();
        const lower = line.toLowerCase();
        if (lower.startsWith('báo giá') && !lower.includes('có hiệu lực')) {
            kflexQuoteForm.documentTitle = line.replace(/b\s+ảo/i, 'BẢO');
        } else if (lower.startsWith('kính gửi')) {
            kflexQuoteForm.recipientCompany = readNextFieldValue(
                decodedLines,
                i,
                /^kính gửi\s*:?\s*/i,
            );
        } else if (lower.startsWith('người nhận')) {
            kflexQuoteForm.recipientPerson = readNextFieldValue(
                decodedLines,
                i,
                /^người nhận\s*:?\s*/i,
            );
        } else if (lower.startsWith('project')) {
            kflexQuoteForm.projectName = readNextFieldValue(decodedLines, i, /^project\s*:?\s*/i);
        } else if (lower.startsWith('thời gian') && !lower.includes('giao hàng')) {
            const dStr = readNextFieldValue(decodedLines, i, /^thời gian\s*:?\s*/i);
            if (dStr) {
                kflexQuoteForm.quoteDate = parseSlashDateToIso(dStr, todayIso);
            }
        } else if (lower.includes('đơn vị phân phối')) {
            kflexQuoteForm.introText = line;
        } else if (lower.includes('thuế vat')) {
            const vm = line.match(/(\d+(?:\.\d+)?)\s*%/);
            if (vm) {
                kflexQuoteForm.vatPercent = Number(vm[1]);
            }
        } else if (lower.includes('1. báo giá có hiệu lực đến ngày')) {
            const dateMatch = line.match(/(\d{1,2}[/-]\d{1,2}[/-]\d{4}|\d{4}-\d{2}-\d{2})/);
            if (dateMatch) {
                kflexQuoteForm.validUntilDate = parseSlashDateToIso(
                    dateMatch[1],
                    kflexQuoteForm.validUntilDate,
                );
            }
        } else if (lower.includes('2. phương thức thanh toán')) {
            const val = line.split(':').slice(1).join(':').trim();
            if (val) {
                kflexQuoteForm.paymentTerms = val;
            }
        } else if (lower.includes('3. phương thức giao hàng')) {
            const val = line.split(':').slice(1).join(':').trim();
            if (val) {
                kflexQuoteForm.deliveryMethod = val;
            }
        } else if (lower.includes('4. thời gian giao hàng')) {
            let val = line.split(':').slice(1).join(':').trim();
            if (val) {
                const nextLine = (decodedLines[i + 1] ?? '').trim();
                if (nextLine && !/^([0-9]+\.|rất mong|many thanks)/i.test(nextLine)) {
                    val += ' ' + nextLine;
                }
                kflexQuoteForm.deliveryTime = val;
            }
        } else if (lower.includes('many thanks')) {
            afterThanks = true;
        } else if (afterThanks) {
            if (/^hp\b/i.test(line)) {
                kflexQuoteForm.contactHp = line.replace(/^hp\s*:?\s*/i, '').trim();
            } else if (/^email\b/i.test(line)) {
                kflexQuoteForm.contactEmail = line.replace(/^email\s*:?\s*/i, '').trim();
            } else if (!contactNameFound && line.length > 1 && !line.includes(':')) {
                kflexQuoteForm.contactName = line;
                contactNameFound = true;
            }
        }
    }

    const kflexGroups: KflexQuotationGroupState[] = [];
    const detectedLines = new Set<KflexProductLineKey>();
    let currentGroup: KflexQuotationGroupState | null = null;

    const headerRows: PdfStructuredRow[] = [];
    const bodyRows: PdfStructuredRow[] = [];
    let inTable = false;
    let tableMinX = 1e9;
    let tableMaxX = 0;

    for (const row of structuredRows) {
        const lineUpper = row.line.toUpperCase();
        if (lineUpper.startsWith('TỔNG CỘNG')) {
            break;
        }
        const isHeaderLine =
            (row.cells[0] === 'TT' && lineUpper.includes('TÊN VẬT TƯ')) ||
            lineUpper.includes('UNIT PRICE') ||
            lineUpper.includes('CHIỀU DÀY') ||
            lineUpper.includes('ID (MM)') ||
            /^\(MM\)/i.test(row.line.trim());
        if (isHeaderLine) {
            inTable = true;
            headerRows.push(row);
            for (const cr of row.cellRuns) {
                tableMinX = Math.min(tableMinX, cr.x);
                tableMaxX = Math.max(tableMaxX, cr.endX);
            }
            continue;
        }
        if (!inTable) {
            continue;
        }
        bodyRows.push(row);
        for (const cr of row.cellRuns) {
            tableMinX = Math.min(tableMinX, cr.x);
            tableMaxX = Math.max(tableMaxX, cr.endX);
        }
    }

    if (tableMinX > tableMaxX) {
        tableMinX = 50;
        tableMaxX = 720;
    }

    const proportions = [
        0.038, 0.16, 0.068, 0.084, 0.07, 0.042, 0.04, 0.042, 0.042, 0.048, 0.048, 0.092, 0.112,
        0.114,
    ];
    const matchers = [
        (s: string) => s === 'tt',
        (s: string) => s.includes('tên vật tư'),
        (s: string) => s.includes('nhãn hiệu'),
        (s: string) => s.includes('thương hiệu') || s.includes('xuất xứ'),
        (s: string) => s.includes('loại sản') || s === 'phẩm',
        (s: string) => s.includes('chiều dày'),
        (s: string) => s.includes('id (mm)') || s === 'id',
        (s: string) => s.includes('chiều rộng'),
        (s: string) => s.includes('chiều dài'),
        (s: string) => s === 'm',
        (s: string) => s === 'm2',
        (s: string) => s.includes('đơn giá') || s.includes('unit price'),
        (s: string) => s.includes('tổng') || s.includes('total'),
        (s: string) => s.includes('ghi chú'),
    ];
    const dividers = buildPdfColumnDividers(headerRows, tableMinX, tableMaxX, proportions, matchers, vLines);
    if (vLines.length < 14) {
        for (const hr of headerRows) {
            for (const cr of hr.cellRuns) {
                const t = cr.text.toLowerCase();
                if (t === 'tt') {
                    dividers[0] = Math.min(dividers[0], cr.endX + 12);
                } else if (t.includes('nhãn hiệu')) {
                    dividers[1] = Math.max(dividers[0] + 40, cr.x - 8);
                }
            }
        }
    }

    type MappedBodyRow = {
        type: 'section' | 'item' | 'wrap';
        page: number;
        y: number;
        colRuns: Array<Array<{ y: number; x: number; text: string }>>;
        cells: string[];
        line: string;
    };

    const mappedBody: MappedBodyRow[] = [];
    for (let idx = 0; idx < bodyRows.length; idx++) {
        const row = bodyRows[idx];
        const colRuns: Array<Array<{ y: number; x: number; text: string }>> = Array.from(
            { length: 14 },
            () => [],
        );
        for (const cr of row.cellRuns) {
            const cIdx = resolvePdfColumnIndex(cr.x, cr.centerX, dividers, [1]);
            colRuns[cIdx].push({
                y: row.y,
                x: cr.x,
                text: cr.text,
            });
        }

        const colTexts = colRuns.map((arr) =>
            arr
                .map((r) => r.text)
                .join(' ')
                .trim(),
        );
        const hasFormattedPrice = /\d{1,3}[.,]\d{3}/.test(row.line);
        const hasTt = /^\d{1,3}$/.test(colTexts[0]) || /^\d{1,3}$/.test(row.cells[0] ?? '');
        const hasNumericDims =
            (colTexts[5] !== '' && !Number.isNaN(Number(colTexts[5].replace(',', '.')))) ||
            (colTexts[9] !== '' && !Number.isNaN(Number(colTexts[9].replace(',', '.')))) ||
            (colTexts[10] !== '' && !Number.isNaN(Number(colTexts[10].replace(',', '.'))));

        const lineTrim = row.line.trim();
        const lineUpper = lineTrim.toUpperCase();
        const isSectionRow =
            row.cells.length === 1 &&
            !hasFormattedPrice &&
            !hasTt &&
            (lineUpper.includes('BẢO ÔN') ||
                lineUpper.includes('HẠNG MỤC') ||
                lineUpper.includes('PHẦN ') ||
                (lineUpper === lineTrim &&
                    lineTrim.length >= 6 &&
                    /[A-ZÀÁẠẢÃĂẮẰẲẴẶÂẤẦẨẪẬÈÉẸẺẼÊẾỀỂỄỆÌÍỊỈĨÒÓỌỎÕÔỐỒỔỖỘƠỚỜỞỠỢÙÚỤỦŨƯỨỪỬỮỰỲÝỴỶỸ]/.test(
                        lineTrim,
                    ) &&
                    !/^(DN\d+|K-FLEX|ST\b|M-FLEX|TUBE|SHEET)/i.test(lineTrim)));

        const isItemAnchor =
            !isSectionRow &&
            (hasFormattedPrice || (hasTt && (hasNumericDims || row.cells.length >= 5)));

        mappedBody.push({
            type: isSectionRow ? 'section' : isItemAnchor ? 'item' : 'wrap',
            page: row.page,
            y: row.y,
            colRuns,
            cells: row.cells,
            line: row.line,
        });
    }

    const itemIndices = mappedBody
        .map((mb, idx) => (mb.type === 'item' ? idx : -1))
        .filter((idx) => idx >= 0);

    for (let idx = 0; idx < mappedBody.length; idx++) {
        const mb = mappedBody[idx];
        if (mb.type !== 'wrap' || itemIndices.length === 0) {
            continue;
        }
        let bestItemIdx: number | null = null;
        let bestDist = 1e9;
        for (const iIdx of itemIndices) {
            if (mappedBody[iIdx].page !== mb.page) {
                continue;
            }
            const dist = Math.abs(mappedBody[iIdx].y - mb.y);
            if (dist < bestDist && dist <= 36) {
                bestDist = dist;
                bestItemIdx = iIdx;
            }
        }
        if (bestItemIdx !== null) {
            for (let c = 0; c < 14; c++) {
                for (const cr of mb.colRuns[c]) {
                    mappedBody[bestItemIdx].colRuns[c].push(cr);
                }
            }
        }
    }

    for (const mb of mappedBody) {
        if (mb.type === 'section') {
            currentGroup = {
                uid: makeUid('kf-grp-pdf'),
                hasTitle: true,
                title: mb.cells[0] ?? mb.line.trim(),
                items: [],
            };
            kflexGroups.push(currentGroup);
            continue;
        }

        if (mb.type !== 'item') {
            continue;
        }

        const cols: string[] = [];
        for (let c = 0; c < 14; c++) {
            const cRuns = mb.colRuns[c];
            cRuns.sort((a, b) => (Math.abs(a.y - b.y) > 2 ? b.y - a.y : a.x - b.x));
            cols[c] = cRuns
                .map((r) => r.text)
                .join(' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        const nonEmptyColCount = cols.filter((v) => v !== '').length;
        let itemName = '';
        let brandLabel = 'K-Flex ST';
        let originLabel = 'Ý - Malaysia';
        let productTypeLabel = 'Chỉ Foam';
        let productLine: KflexProductLineKey = 'st';
        let formType: 'tube' | 'sheet' = 'tube';
        let variantType = 'foam';
        let thickness = '13';
        let innerDiameter = '';
        let widthM = '';
        let lengthM = '';
        let qtyVal = 1;
        let unitPriceVal = 0;
        let note = '';

        if (nonEmptyColCount >= 6 && (cols[1] !== '' || cols[2] !== '')) {
            itemName = cols[1];
            brandLabel = cols[2] || 'K-Flex ST';
            originLabel = cols[3] || 'Ý - Malaysia';
            productTypeLabel = cols[4] || 'Chỉ Foam';
            thickness = cols[5] || '13';
            innerDiameter = cols[6];
            widthM = cols[7];
            lengthM = cols[8];
            const qtyMStr = cols[9];
            const qtyM2Str = cols[10];
            const unitPriceStr = cols[11];
            const totalStr = cols[12];
            note = cols[13];

            const brandAndName = `${brandLabel} ${itemName}`.toLowerCase();
            if (brandAndName.includes('protect')) {
                productLine = 'k_protect';
            } else if (brandAndName.includes('m-flex') || brandAndName.includes('mflex')) {
                productLine = 'm_flex';
            }
            detectedLines.add(productLine);

            const isSheet =
                (qtyM2Str !== '' && qtyMStr === '') ||
                (widthM !== '' && innerDiameter === '') ||
                brandAndName.includes('sheet') ||
                brandAndName.includes('tấm');
            formType = isSheet ? 'sheet' : 'tube';

            const typeLower = productTypeLabel.toLowerCase();
            variantType =
                formType === 'tube'
                    ? typeLower.includes('không nhôm') || typeLower.includes('chỉ foam')
                        ? 'foam'
                        : typeLower.includes('nhôm')
                          ? 'alu'
                          : 'foam'
                    : typeLower.includes('cả keo và nhôm')
                      ? 'alu_and_glue'
                      : typeLower.includes('nhôm') || typeLower.includes('keo')
                        ? 'alu_or_glue'
                        : 'foam';

            if (!lengthM) {
                lengthM = formType === 'tube' ? (productLine === 'm_flex' ? '1.83' : '2') : '8';
            }
            if (formType === 'sheet' && !widthM) {
                widthM = '1.2';
            }

            qtyVal =
                formType === 'sheet'
                    ? Math.max(0, parseFormattedNumber(qtyM2Str || qtyMStr, 1))
                    : Math.max(0, parseFormattedNumber(qtyMStr || qtyM2Str, 1));
            unitPriceVal = Math.max(
                0,
                Math.round(parseFormattedNumber(unitPriceStr || totalStr, 0)),
            );
        } else {
            const cells = [...mb.cells];
            const hasLeadingTt = /^\d{1,3}$/.test(cells[0] ?? '');
            const offset = hasLeadingTt ? 1 : 0;
            itemName = cells[offset] ?? '';
            brandLabel = cells[offset + 1] ?? 'K-Flex ST';
            originLabel = cells[offset + 2] ?? 'Ý - Malaysia';
            productTypeLabel = cells[offset + 3] ?? 'Chỉ Foam';
            const restCells = cells.slice(offset + 4);
            if (!itemName || restCells.length < 3) {
                continue;
            }

            const brandAndName = `${brandLabel} ${itemName}`.toLowerCase();
            if (brandAndName.includes('protect')) {
                productLine = 'k_protect';
            } else if (brandAndName.includes('m-flex') || brandAndName.includes('mflex')) {
                productLine = 'm_flex';
            }
            detectedLines.add(productLine);

            const isSheet = brandAndName.includes('sheet') || brandAndName.includes('tấm');
            formType = isSheet ? 'sheet' : 'tube';
            const typeLower = productTypeLabel.toLowerCase();
            variantType =
                formType === 'tube'
                    ? typeLower.includes('không nhôm') || typeLower.includes('chỉ foam')
                        ? 'foam'
                        : typeLower.includes('nhôm')
                          ? 'alu'
                          : 'foam'
                    : typeLower.includes('cả keo và nhôm')
                      ? 'alu_and_glue'
                      : typeLower.includes('nhôm') || typeLower.includes('keo')
                        ? 'alu_or_glue'
                        : 'foam';

            if (restCells.length > 0 && !/^[\d.,\s-]+$/.test(restCells[restCells.length - 1])) {
                note = restCells.pop() ?? '';
            }

            thickness = restCells[0] ?? '13';
            const secondDim = restCells[1] ?? '';
            lengthM =
                restCells.length >= 5
                    ? (restCells[2] ?? '')
                    : productLine === 'm_flex'
                      ? '1.83'
                      : '2';
            innerDiameter = formType === 'tube' ? secondDim : '';
            widthM = formType === 'sheet' ? secondDim || '1.2' : '';
            const qtyStr = restCells.length >= 3 ? (restCells[restCells.length - 3] ?? '1') : '1';
            const unitPriceStr =
                restCells.length >= 2 ? (restCells[restCells.length - 2] ?? '0') : '0';

            qtyVal = Math.max(0, parseFormattedNumber(qtyStr, 1));
            unitPriceVal = Math.max(0, Math.round(parseFormattedNumber(unitPriceStr, 0)));
        }

        const matchedPrice = kflexPrices.find(
            (p) =>
                p.product_line === productLine &&
                p.form_type === formType &&
                p.variant_type === variantType &&
                String(Number(p.thickness) || p.thickness).trim() ===
                    String(Number(thickness) || thickness).trim() &&
                (formType === 'sheet' ||
                    String(Number(p.diameter) || p.diameter || '').trim() ===
                        String(Number(innerDiameter) || innerDiameter).trim()),
        );

        if (!currentGroup) {
            currentGroup = {
                uid: makeUid('kf-grp-pdf'),
                hasTitle: false,
                title: '',
                items: [],
            };
            kflexGroups.push(currentGroup);
        }

        currentGroup.items.push({
            uid: makeUid('kf-pdf'),
            kflexPriceId: matchedPrice?.id ?? null,
            itemName,
            brandLabel,
            originLabel,
            productTypeLabel,
            productLine,
            formType,
            variantType,
            thickness,
            innerDiameter,
            widthM,
            lengthM,
            qtyM: formType === 'tube' ? qtyVal : null,
            qtyM2: formType === 'sheet' ? qtyVal : null,
            unitPrice: unitPriceVal > 0 ? unitPriceVal : (matchedPrice?.price ?? 0),
            note,
        });
    }

    // Fallback: if table rows weren't matched by structure, match catalog product names
    const nonEmptyGroups = kflexGroups.filter((g) => g.items.length > 0);
    if (nonEmptyGroups.length === 0) {
        const matchedItems: KflexQuotationItemState[] = [];
        const sortedPrices = [...kflexPrices].sort(
            (a, b) => (b.product_name?.length ?? 0) - (a.product_name?.length ?? 0),
        );
        for (const p of sortedPrices) {
            if (p.product_name && combinedText.includes(p.product_name)) {
                detectedLines.add(p.product_line);
                matchedItems.push({
                    uid: makeUid('kf-pdf'),
                    kflexPriceId: p.id,
                    itemName: p.product_name,
                    brandLabel:
                        p.product_line === 'k_protect'
                            ? 'K-Flex K-Protect'
                            : p.product_line === 'm_flex'
                              ? 'K-Flex M-Flex'
                              : 'K-Flex ST',
                    originLabel: `${p.brand || 'Ý'} - ${p.origin || 'Malaysia'}`,
                    productTypeLabel: p.variant_type === 'alu' ? 'Có nhôm' : 'Chỉ Foam',
                    productLine: p.product_line,
                    formType: p.form_type,
                    variantType: p.variant_type,
                    thickness: p.thickness,
                    innerDiameter: p.diameter || '',
                    widthM: p.form_type === 'sheet' ? '1.2' : '',
                    lengthM: p.form_type === 'tube' ? '2' : '8',
                    qtyM: p.form_type === 'tube' ? 1 : null,
                    qtyM2: p.form_type === 'sheet' ? 1 : null,
                    unitPrice: p.price,
                    note: '',
                });
            }
        }
        if (matchedItems.length > 0) {
            const hasSecInText = combinedText.toUpperCase().includes('BẢO ÔN CHO');
            nonEmptyGroups.push({
                uid: makeUid('kf-grp-pdf'),
                hasTitle: hasSecInText,
                title: hasSecInText ? 'BẢO ÔN CHO ĐƯỜNG THÉP' : '',
                items: matchedItems,
            });
        }
    }

    return {
        version: 1,
        quotationType: 'second',
        activeQuotationType: 'second',
        kflexQuoteForm,
        kflexSelectedProductLines: detectedLines.size > 0 ? Array.from(detectedLines) : ['st'],
        kflexGroups: nonEmptyGroups,
    };
}
