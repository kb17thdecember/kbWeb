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
    activeQuotationType: QuotationType;
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

export function parseFormattedNumber(raw: unknown, fallback = 0): number {
    if (typeof raw === 'number' && Number.isFinite(raw)) {
        return raw;
    }
    const str = String(raw ?? '').trim();
    if (!str) {
        return fallback;
    }
    // Remove currency symbols and spaces
    const cleaned = str.replace(/[^\d.,-]/g, '');
    if (!cleaned) {
        return fallback;
    }
    // Check if dots or commas are thousand separators (e.g. "2.386.000" or "165,127")
    if (/^-?\d{1,3}(\.\d{3})+$/.test(cleaned)) {
        return Number(cleaned.replace(/\./g, '')) || fallback;
    }
    if (/^-?\d{1,3}(,\d{3})+$/.test(cleaned)) {
        return Number(cleaned.replace(/,/g, '')) || fallback;
    }
    // Multiple dots or commas -> thousand separators
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
    const encoder = new TextEncoder();
    const bytes = encoder.encode(JSON.stringify(payload));
    return uint8ToBase64(bytes);
}

export function decodeQuotationPayloadBase64(base64Str: string): QuotationPayloadState | null {
    try {
        const clean = base64Str.replace(/\s+/g, '');
        const binary = atob(clean);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        const decoder = new TextDecoder('utf-8');
        const parsed = JSON.parse(decoder.decode(bytes)) as QuotationPayloadState;
        if (
            parsed &&
            (parsed.activeQuotationType === 'ariston' || parsed.activeQuotationType === 'second')
        ) {
            return parsed;
        }
        return null;
    } catch {
        return null;
    }
}

async function inflateStreamBytes(
    compressed: Uint8Array,
    format: 'deflate' | 'deflate-raw',
): Promise<Uint8Array> {
    if (typeof DecompressionStream === 'undefined') {
        throw new Error('Trình duyệt không hỗ trợ giải nén file.');
    }
    const ds = new DecompressionStream(format);
    const writer = ds.writable.getWriter();
    await writer.write(compressed as unknown as BufferSource);
    await writer.close();

    const reader = ds.readable.getReader();
    const chunks: Uint8Array[] = [];
    let totalLen = 0;
    while (true) {
        const { done, value } = await reader.read();
        if (done) {
            break;
        }
        if (value) {
            chunks.push(value);
            totalLen += value.length;
        }
    }
    const out = new Uint8Array(totalLen);
    let offset = 0;
    for (const c of chunks) {
        out.set(c, offset);
        offset += c.length;
    }
    return out;
}

async function inflateRawBytes(compressed: Uint8Array): Promise<Uint8Array> {
    return inflateStreamBytes(compressed, 'deflate-raw');
}

export async function readZipEntries(buffer: ArrayBuffer): Promise<Map<string, Uint8Array>> {
    const bytes = new Uint8Array(buffer);
    const view = new DataView(buffer);
    const decoder = new TextDecoder('utf-8');
    const entries = new Map<string, Uint8Array>();

    // Find End of Central Directory (0x06054b50)
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
        const fileName = decoder.decode(nameBytes).replace(/^\/+/, '');

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

function parseSharedStringsXml(xmlText: string): string[] {
    const parser = new DOMParser();
    const doc = parser.parseFromString(xmlText, 'application/xml');
    const siNodes = Array.from(doc.getElementsByTagName('si'));
    return siNodes.map((si) => {
        const tNodes = Array.from(si.getElementsByTagName('t'));
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

    const rowNodes = Array.from(doc.getElementsByTagName('row'));
    for (const rowEl of rowNodes) {
        const rAttr = Number(rowEl.getAttribute('r') || '0');
        if (!rAttr) {
            continue;
        }
        if (rAttr > maxRow) {
            maxRow = rAttr;
        }
        let rowMap = grid.get(rAttr);
        if (!rowMap) {
            rowMap = new Map<number, string>();
            grid.set(rAttr, rowMap);
        }

        const cellNodes = Array.from(rowEl.getElementsByTagName('c'));
        for (const cellEl of cellNodes) {
            const ref = cellEl.getAttribute('r') || '';
            const { col } = parseCellRef(ref);
            if (!col) {
                continue;
            }
            const t = cellEl.getAttribute('t') || '';
            let val = '';
            if (t === 'inlineStr') {
                const tEls = Array.from(cellEl.getElementsByTagName('t'));
                val = tEls.map((el) => el.textContent ?? '').join('');
            } else {
                const vEl = cellEl.getElementsByTagName('v')[0];
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
    const mergeNodes = Array.from(doc.getElementsByTagName('mergeCell'));
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

    const drawingXmlBytes = entries.get('xl/drawings/drawing1.xml');
    const drawingRelsBytes = entries.get('xl/drawings/_rels/drawing1.xml.rels');
    if (!drawingXmlBytes || !drawingRelsBytes) {
        return result;
    }

    const parser = new DOMParser();
    const relsDoc = parser.parseFromString(decoder.decode(drawingRelsBytes), 'application/xml');
    const relMap = new Map<string, string>();
    for (const relEl of Array.from(relsDoc.getElementsByTagName('Relationship'))) {
        const id = relEl.getAttribute('Id') || '';
        const target = relEl.getAttribute('Target') || '';
        if (id && target) {
            const cleanPath = 'xl/' + target.replace(/^(\.\.\/)+/, '').replace(/^\/+/, '');
            relMap.set(id, cleanPath);
        }
    }

    const drawingDoc = parser.parseFromString(decoder.decode(drawingXmlBytes), 'application/xml');
    const anchors = [
        ...Array.from(drawingDoc.getElementsByTagName('xdr:oneCellAnchor')),
        ...Array.from(drawingDoc.getElementsByTagName('oneCellAnchor')),
        ...Array.from(drawingDoc.getElementsByTagName('xdr:twoCellAnchor')),
        ...Array.from(drawingDoc.getElementsByTagName('twoCellAnchor')),
    ];

    for (const anchor of anchors) {
        const fromEl =
            anchor.getElementsByTagName('xdr:from')[0] || anchor.getElementsByTagName('from')[0];
        if (!fromEl) {
            continue;
        }
        const colEl =
            fromEl.getElementsByTagName('xdr:col')[0] || fromEl.getElementsByTagName('col')[0];
        const rowEl =
            fromEl.getElementsByTagName('xdr:row')[0] || fromEl.getElementsByTagName('row')[0];
        const blipEl =
            anchor.getElementsByTagName('a:blip')[0] || anchor.getElementsByTagName('blip')[0];
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
        const imgBytes = entries.get(mediaPath);
        if (!imgBytes) {
            continue;
        }
        const ext = mediaPath.toLowerCase().endsWith('.jpg') || mediaPath.toLowerCase().endsWith('.jpeg')
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
        const parsed = JSON.parse(text) as QuotationPayloadState;
        if (parsed && (parsed.activeQuotationType === 'ariston' || parsed.activeQuotationType === 'second')) {
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

    // Check embedded customData state if available (used for fallback IDs/images)
    let embeddedState: QuotationPayloadState | null = null;
    const embeddedBytes = entries.get('xl/customData/quotation.json');
    if (embeddedBytes) {
        try {
            embeddedState = JSON.parse(decoder.decode(embeddedBytes)) as QuotationPayloadState;
        } catch {
            embeddedState = null;
        }
    }

    const sheetBytes =
        entries.get('xl/worksheets/sheet1.xml') ||
        Array.from(entries.entries()).find(([k]) => k.startsWith('xl/worksheets/sheet'))?.[1];

    if (!sheetBytes) {
        if (embeddedState) {
            return embeddedState;
        }
        throw new Error('Không tìm thấy trang tính dữ liệu trong file Excel.');
    }

    const sharedStringsBytes = entries.get('xl/sharedStrings.xml');
    const sharedStrings = sharedStringsBytes
        ? parseSharedStringsXml(decoder.decode(sharedStringsBytes))
        : [];

    const { grid, merges, maxRow } = parseWorksheetXml(decoder.decode(sheetBytes), sharedStrings);
    const rowImages = extractRowImagesFromZip(entries);

    // Detect whether this sheet is K-Flex or Ariston
    let isKflex = false;
    for (let r = 1; r <= Math.min(maxRow, 20); r++) {
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
        documentTitle: baseForm.documentTitle || getCellText(grid, 5, 1) || 'BÁO GIÁ BƠM NHIỆT ARISTON',
        recipientCompany: baseForm.recipientCompany || '',
        recipientPerson: baseForm.recipientPerson || '',
        contactPhone: '',
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
    for (let r = 5; r <= Math.min(maxRow, 20); r++) {
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
                productLabel: c1 || embeddedItem?.productLabel || 'Máy nước nóng gián tiếp Ariston',
                modelCode: c2,
                origin: c3 || embeddedItem?.origin || 'Ý-Việt Nam',
                imageUrl,
                unit: c5 || embeddedItem?.unit || 'Máy',
                specifications: c6,
                unitPrice: Math.max(0, Math.round(parseFormattedNumber(c7, embeddedItem?.unitPrice ?? 0))),
                quantity: Math.max(1, Math.round(parseFormattedNumber(c8, embeddedItem?.quantity ?? 1))),
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
    // Expand merged note cells in column 14 (N)
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
    for (let r = 5; r <= Math.min(maxRow, 20); r++) {
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
                unitPrice: Math.max(0, Math.round(parseFormattedNumber(c12, matchedPrice?.price ?? 0))),
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

    // 2. Decompress all PDF streams
    const decompressedStreams: string[] = [];
    const streamRegex = /stream\r?\n/g;
    let match: RegExpExecArray | null;
    while ((match = streamRegex.exec(rawPdfText)) !== null) {
        const startOffset = match.index + match[0].length;
        const endIdx = rawPdfText.indexOf('endstream', startOffset);
        if (endIdx === -1) {
            break;
        }
        let streamEnd = endIdx;
        if (rawPdfText[streamEnd - 1] === '\n') {
            streamEnd--;
        }
        if (rawPdfText[streamEnd - 1] === '\r') {
            streamEnd--;
        }
        const slice = bytes.subarray(startOffset, streamEnd);
        try {
            const inflated = await inflateStreamBytes(slice, 'deflate');
            decompressedStreams.push(utf8Decoder.decode(inflated));
        } catch {
            try {
                const inflatedRaw = await inflateStreamBytes(slice, 'deflate-raw');
                decompressedStreams.push(utf8Decoder.decode(inflatedRaw));
            } catch {
                decompressedStreams.push(latin1Decoder.decode(slice));
            }
        }
    }

    for (const s of decompressedStreams) {
        const m = s.match(/KBQDATA:([A-Za-z0-9+/=\s]+):ENDKBQDATA/);
        if (m) {
            const decoded = decodeQuotationPayloadBase64(m[1]);
            if (decoded) {
                return decoded;
            }
        }
    }

    // 3. Build CMap and decode BT..ET text streams
    const cmap = new Map<string, string>();
    for (const s of decompressedStreams) {
        if (s.includes('beginbfchar') || s.includes('beginbfrange')) {
            parseToUnicodeCMap(s, cmap);
        }
    }

    const decodedLines: string[] = [];
    for (const s of decompressedStreams) {
        if (!s.includes('BT') || !s.includes('ET')) {
            continue;
        }
        const btBlocks = s.matchAll(/BT([\s\S]*?)ET/g);
        for (const blk of btBlocks) {
            let lineText = '';
            const tokens = blk[1].matchAll(/<([0-9A-Fa-f]+)>|\((?:\\.|[^\\()])*\)/g);
            for (const tok of tokens) {
                if (tok[1]) {
                    const hex = tok[1].toUpperCase();
                    const step = cmap.has(hex.slice(0, 4)) ? 4 : 2;
                    for (let i = 0; i < hex.length; i += step) {
                        const code = hex.slice(i, i + step);
                        if (cmap.has(code)) {
                            lineText += cmap.get(code)!;
                        } else {
                            const charCode = parseInt(code, 16);
                            if (charCode >= 32 && charCode <= 126) {
                                lineText += String.fromCharCode(charCode);
                            }
                        }
                    }
                } else {
                    const lit = tok[0]
                        .slice(1, -1)
                        .replace(/\\n/g, '\n')
                        .replace(/\\r/g, '\r')
                        .replace(/\\\(/g, '(')
                        .replace(/\\\)/g, ')')
                        .replace(/\\\\/g, '\\');
                    lineText += lit;
                }
            }
            if (lineText.trim()) {
                decodedLines.push(lineText.trim());
            }
        }
    }

    const combinedText = decodedLines.join('\n');
    const compactText = decodedLines.join('');
    const embeddedInText =
        combinedText.match(/KBQDATA:([A-Za-z0-9+/=\s]+):ENDKBQDATA/) ||
        compactText.match(/KBQDATA:([A-Za-z0-9+/=\s]+):ENDKBQDATA/);
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

    // 4. Fallback heuristic parsing from extracted PDF text lines
    const upperAll = combinedText.toUpperCase();
    const isKflex = upperAll.includes('K-FLEX') || upperAll.includes('BẢO ÔN');

    if (isKflex) {
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
            const line = decodedLines[i];
            const lower = line.toLowerCase();
            if (lower.startsWith('báo giá')) {
                kflexQuoteForm.documentTitle = line;
            } else if (lower.startsWith('kính gửi')) {
                kflexQuoteForm.recipientCompany =
                    line.replace(/^kính gửi\s*:?\s*/i, '').trim() || decodedLines[i + 1] || '';
            } else if (lower.startsWith('người nhận')) {
                kflexQuoteForm.recipientPerson =
                    line.replace(/^người nhận\s*:?\s*/i, '').trim() || decodedLines[i + 1] || '';
            } else if (lower.startsWith('project')) {
                kflexQuoteForm.projectName =
                    line.replace(/^project\s*:?\s*/i, '').trim() || decodedLines[i + 1] || '';
            } else if (lower.startsWith('thời gian') && !lower.includes('giao hàng')) {
                const dStr =
                    line.replace(/^thời gian\s*:?\s*/i, '').trim() || decodedLines[i + 1] || '';
                kflexQuoteForm.quoteDate = parseSlashDateToIso(dStr, todayIso);
            } else if (lower.includes('thuế vat')) {
                const vm = line.match(/(\d+(?:\.\d+)?)\s*%/);
                if (vm) {
                    kflexQuoteForm.vatPercent = Number(vm[1]);
                }
            }
        }

        const matchedItems: KflexQuotationItemState[] = [];
        for (const p of kflexPrices) {
            if (p.product_name && combinedText.includes(p.product_name)) {
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
            activeQuotationType: 'second',
            kflexQuoteForm,
            kflexSelectedProductLines: ['st'],
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

    for (let i = 0; i < decodedLines.length; i++) {
        const line = decodedLines[i];
        const lower = line.toLowerCase();
        if (lower.startsWith('báo giá') && !lower.includes('có hiệu lực')) {
            quoteForm.documentTitle = line;
        } else if (lower.startsWith('kính gửi')) {
            quoteForm.recipientCompany =
                line.replace(/^kính gửi\s*:?\s*/i, '').trim() || decodedLines[i + 1] || '';
        } else if (lower.startsWith('người nhận')) {
            quoteForm.recipientPerson =
                line.replace(/^người nhận\s*:?\s*/i, '').trim() || decodedLines[i + 1] || '';
        } else if (lower.startsWith('thông tin liên lạc')) {
            quoteForm.contactPhone =
                line.replace(/^thông tin liên lạc\s*:?\s*/i, '').trim() ||
                decodedLines[i + 1] ||
                '';
        } else if (lower.startsWith('thời gian') && !lower.includes('giao hàng')) {
            const dStr =
                line.replace(/^thời gian\s*:?\s*/i, '').trim() || decodedLines[i + 1] || '';
            quoteForm.quoteDate = parseSlashDateToIso(dStr, todayIso);
        } else if (lower.startsWith('project')) {
            quoteForm.projectName =
                line.replace(/^project\s*:?\s*/i, '').trim() || decodedLines[i + 1] || '';
        } else if (lower.includes('thuế vat')) {
            const vm = line.match(/(\d+(?:\.\d+)?)\s*%/);
            if (vm) {
                quoteForm.vatPercent = Number(vm[1]);
            }
        }
    }

    const items: AristonQuotationItemState[] = [];
    for (const prod of aristonProducts) {
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

    return {
        version: 1,
        activeQuotationType: 'ariston',
        quoteForm,
        items,
    };
}

