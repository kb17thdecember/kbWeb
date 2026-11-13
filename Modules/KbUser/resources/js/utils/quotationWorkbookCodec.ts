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
    return {
        ...normalized,
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
        const day = String(Number(match[1])).padStart(2, '0');
        const month = String(Number(match[2])).padStart(2, '0');
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

        const ranges = block[1].matchAll(
            /<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/g,
        );
        for (const r of ranges) {
            const start = parseInt(r[1], 16);
            const end = parseInt(r[2], 16);
            let target = parseInt(r[3], 16);
            const padLen = r[1].length;
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

    // Map font resource name (/F1, /F2, ...) -> CMap
    const fontNameToCMap = new Map<string, Map<string, string>>();
    for (const header of objectHeaders.values()) {
        const fontRefs = header.matchAll(/\/(F\d+)\s+(\d+)\s+\d+\s+R/g);
        for (const ref of fontRefs) {
            const fontName = ref[1];
            const fontObjId = Number(ref[2]);
            const cmap = fontObjToCMap.get(fontObjId);
            if (cmap) {
                fontNameToCMap.set(fontName, cmap);
            }
        }
    }

    // 4. Decode BT..ET blocks while tracking active font (/F1 Tf) and line breaks (Td/TD/Tm/T*)
    const decodedLines: string[] = [];
    for (const s of allDecompressedStreams) {
        if (!s.includes('BT') || !s.includes('ET')) {
            continue;
        }
        let activeCMap = fallbackCMap;
        const btBlocks = s.matchAll(/BT([\s\S]*?)ET/g);
        for (const blk of btBlocks) {
            const body = blk[1];
            let currentLine = '';
            const opRegex =
                /\/(F\d+)\s+[\d.-]+\s+Tf|([\d.-]+)\s+([\d.-]+)\s+Td|([\d.-]+)\s+([\d.-]+)\s+TD|[\d.-]+\s+[\d.-]+\s+[\d.-]+\s+[\d.-]+\s+([\d.-]+)\s+([\d.-]+)\s+Tm|\bT\*|<[0-9A-Fa-f\s]+>|\((?:\\.|[^\\()])*\)|\[(?:<[0-9A-Fa-f\s]+>|\((?:\\.|[^\\()])*\)|[\s\d.-])*\]\s*TJ/g;
            let opMatch: RegExpExecArray | null;
            let lastTmY: number | null = null;

            while ((opMatch = opRegex.exec(body)) !== null) {
                const full = opMatch[0];
                if (opMatch[1]) {
                    // /F1 ... Tf
                    activeCMap = fontNameToCMap.get(opMatch[1]) ?? fallbackCMap;
                } else if (opMatch[2] !== undefined && opMatch[3] !== undefined) {
                    // dx dy Td
                    const dy = parseFloat(opMatch[3]);
                    if (Math.abs(dy) > 1.5 && currentLine.trim()) {
                        decodedLines.push(currentLine.trim());
                        currentLine = '';
                    }
                } else if (opMatch[4] !== undefined && opMatch[5] !== undefined) {
                    // dx dy TD
                    const dy = parseFloat(opMatch[5]);
                    if (Math.abs(dy) > 1.5 && currentLine.trim()) {
                        decodedLines.push(currentLine.trim());
                        currentLine = '';
                    }
                } else if (opMatch[6] !== undefined && opMatch[7] !== undefined) {
                    // a b c d e f Tm
                    const y = parseFloat(opMatch[7]);
                    if (lastTmY !== null && Math.abs(y - lastTmY) > 1.5 && currentLine.trim()) {
                        decodedLines.push(currentLine.trim());
                        currentLine = '';
                    }
                    lastTmY = y;
                } else if (full === 'T*') {
                    if (currentLine.trim()) {
                        decodedLines.push(currentLine.trim());
                        currentLine = '';
                    }
                } else if (full.startsWith('[')) {
                    // [...] TJ array
                    const innerTokens = full.matchAll(/<[0-9A-Fa-f\s]+>|\((?:\\.|[^\\()])*\)|(-?\d+(?:\.\d+)?)/g);
                    for (const it of innerTokens) {
                        if (it[1] !== undefined) {
                            const kern = parseFloat(it[1]);
                            if (kern < -180 && !currentLine.endsWith(' ')) {
                                currentLine += ' ';
                            }
                        } else {
                            currentLine += decodePdfStringToken(it[0], activeCMap, fallbackCMap);
                        }
                    }
                } else {
                    currentLine += decodePdfStringToken(full, activeCMap, fallbackCMap);
                }
            }
            if (currentLine.trim()) {
                decodedLines.push(currentLine.trim());
            }
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

    // 5. Structured parsing of extracted PDF lines for Ariston & K-Flex
    const upperAll = combinedText.toUpperCase();
    const isKflex = upperAll.includes('K-FLEX') || upperAll.includes('BẢO ÔN');

    if (isKflex) {
        return parseKflexPdfLines(decodedLines, combinedText, kflexPrices, todayIso);
    }

    return parseAristonPdfLines(decodedLines, combinedText, aristonProducts, todayIso);
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
    combinedText: string,
    aristonProducts: CatalogAristonProduct[],
    todayIso: string,
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
            quoteForm.documentTitle = line;
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
            if (/^hp\s*:/i.test(line)) {
                quoteForm.signerPhone = line.replace(/^hp\s*:\s*/i, '').trim();
            } else if (/^email\s*:/i.test(line)) {
                quoteForm.signerEmail = line.replace(/^email\s*:\s*/i, '').trim();
            } else if (!signerFound && line.length > 1 && !line.includes(':')) {
                quoteForm.signerName = line;
                signerFound = true;
            }
        }
    }

    // Extract products appearing in the PDF table section
    const items: AristonQuotationItemState[] = [];
    const sortedProducts = [...aristonProducts].sort(
        (a, b) => (b.name?.length ?? 0) - (a.name?.length ?? 0),
    );
    const usedProductIds = new Set<number>();

    for (let i = 0; i < decodedLines.length; i++) {
        const line = decodedLines[i].trim();
        if (line.toUpperCase().startsWith('TỔNG CỘNG')) {
            break;
        }
        const matchedProd = sortedProducts.find(
            (p) =>
                p.name &&
                !usedProductIds.has(p.id) &&
                (line === p.name.trim() || line.includes(p.name.trim())),
        );
        if (!matchedProd) {
            continue;
        }
        usedProductIds.add(matchedProd.id);

        // Look around line i for unitPrice and quantity
        let unitPrice = matchedProd.price > 0 ? matchedProd.price : 2386000;
        let quantity = 1;
        const windowLines = decodedLines.slice(i, Math.min(decodedLines.length, i + 25));
        const priceCandidates: number[] = [];
        for (const wl of windowLines) {
            if (wl.toUpperCase().startsWith('TỔNG CỘNG')) {
                break;
            }
            const formattedMatches = wl.match(/\b\d{1,3}(?:[.,]\d{3})+\b/g);
            if (formattedMatches) {
                for (const fm of formattedMatches) {
                    const num = parseFormattedNumber(fm, 0);
                    if (num >= 10000) {
                        priceCandidates.push(num);
                    }
                }
            }
            if (/^\d{1,3}$/.test(wl.trim())) {
                const q = Number(wl.trim());
                if (q >= 1 && q <= 500) {
                    quantity = q;
                }
            }
        }
        if (priceCandidates.length >= 1) {
            unitPrice = priceCandidates[0];
            if (priceCandidates.length >= 2 && unitPrice > 0) {
                const ratio = Math.round(priceCandidates[1] / unitPrice);
                if (ratio >= 1 && ratio <= 500 && Math.abs(ratio * unitPrice - priceCandidates[1]) < 100) {
                    quantity = ratio;
                }
            }
        }

        items.push({
            uid: makeUid('ariston-pdf'),
            productId: matchedProd.id,
            productLabel: matchedProd.product_title || 'Máy nước nóng gián tiếp Ariston',
            modelCode: matchedProd.name,
            origin: matchedProd.origin ? `Ý-${matchedProd.origin}` : 'Ý-Việt Nam',
            imageUrl: resolveProductImageUrl(matchedProd.image?.[0]),
            unit: 'Máy',
            specifications: matchedProd.description || '',
            unitPrice,
            quantity,
        });
    }

    if (items.length === 0) {
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
    combinedText: string,
    kflexPrices: CatalogKflexPrice[],
    todayIso: string,
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

    for (let i = 0; i < decodedLines.length; i++) {
        const line = decodedLines[i].trim();
        const lower = line.toLowerCase();
        if (lower.startsWith('báo giá') && !lower.includes('có hiệu lực')) {
            kflexQuoteForm.documentTitle = line;
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
            const val = line.split(':').slice(1).join(':').trim();
            if (val) {
                kflexQuoteForm.deliveryTime = val;
            }
        }
    }

    const matchedItems: KflexQuotationItemState[] = [];
    const detectedLines = new Set<KflexProductLineKey>();
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

    return {
        version: 1,
        quotationType: 'second',
        activeQuotationType: 'second',
        kflexQuoteForm,
        kflexSelectedProductLines: detectedLines.size > 0 ? Array.from(detectedLines) : ['st'],
        kflexGroups:
            matchedItems.length > 0
                ? [
                      {
                          uid: makeUid('kf-grp-pdf'),
                          hasTitle: true,
                          title: 'BẢO ÔN CHO ĐƯỜNG THÉP',
                          items: matchedItems,
                      },
                  ]
                : [],
    };
}
