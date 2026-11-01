export type CartExcelRow = {
    index: number;
    name: string;
    category: string;
    quantity: number;
    unitPrice: number;
    lineTotal: number;
};

const CRC32_TABLE = (() => {
    const table = new Uint32Array(256);
    for (let i = 0; i < 256; i++) {
        let crc = i;
        for (let bit = 0; bit < 8; bit++) {
            crc = (crc & 1) !== 0 ? 0xedb88320 ^ (crc >>> 1) : crc >>> 1;
        }
        table[i] = crc >>> 0;
    }

    return table;
})();

function crc32(bytes: Uint8Array): number {
    let crc = 0xffffffff;
    for (let i = 0; i < bytes.length; i++) {
        crc = CRC32_TABLE[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
    }

    return (crc ^ 0xffffffff) >>> 0;
}

function createZipArchive(files: Record<string, string>): Blob {
    const encoder = new TextEncoder();
    const entries = Object.entries(files).map(([name, content]) => {
        const nameBytes = encoder.encode(name);
        const dataBytes = encoder.encode(content);
        const checksum = crc32(dataBytes);

        return { nameBytes, dataBytes, checksum };
    });

    const localParts: Uint8Array[] = [];
    const centralParts: Uint8Array[] = [];
    let offset = 0;

    for (const entry of entries) {
        const localHeader = new Uint8Array(30 + entry.nameBytes.length);
        const localView = new DataView(localHeader.buffer);
        localView.setUint32(0, 0x04034b50, true);
        localView.setUint16(4, 20, true);
        localView.setUint16(6, 0x0800, true);
        localView.setUint16(8, 0, true);
        localView.setUint16(10, 0, true);
        localView.setUint16(12, 0x21, true);
        localView.setUint32(14, entry.checksum, true);
        localView.setUint32(18, entry.dataBytes.length, true);
        localView.setUint32(22, entry.dataBytes.length, true);
        localView.setUint16(26, entry.nameBytes.length, true);
        localView.setUint16(28, 0, true);
        localHeader.set(entry.nameBytes, 30);

        localParts.push(localHeader, entry.dataBytes);

        const centralHeader = new Uint8Array(46 + entry.nameBytes.length);
        const centralView = new DataView(centralHeader.buffer);
        centralView.setUint32(0, 0x02014b50, true);
        centralView.setUint16(4, 20, true);
        centralView.setUint16(6, 20, true);
        centralView.setUint16(8, 0x0800, true);
        centralView.setUint16(10, 0, true);
        centralView.setUint16(12, 0, true);
        centralView.setUint16(14, 0x21, true);
        centralView.setUint32(16, entry.checksum, true);
        centralView.setUint32(20, entry.dataBytes.length, true);
        centralView.setUint32(24, entry.dataBytes.length, true);
        centralView.setUint16(28, entry.nameBytes.length, true);
        centralView.setUint16(30, 0, true);
        centralView.setUint16(32, 0, true);
        centralView.setUint16(34, 0, true);
        centralView.setUint16(36, 0, true);
        centralView.setUint32(38, 0, true);
        centralView.setUint32(42, offset, true);
        centralHeader.set(entry.nameBytes, 46);

        centralParts.push(centralHeader);
        offset += localHeader.length + entry.dataBytes.length;
    }

    const centralSize = centralParts.reduce((sum, part) => sum + part.length, 0);
    const endRecord = new Uint8Array(22);
    const endView = new DataView(endRecord.buffer);
    endView.setUint32(0, 0x06054b50, true);
    endView.setUint16(4, 0, true);
    endView.setUint16(6, 0, true);
    endView.setUint16(8, entries.length, true);
    endView.setUint16(10, entries.length, true);
    endView.setUint32(12, centralSize, true);
    endView.setUint32(16, offset, true);
    endView.setUint16(20, 0, true);

    return new Blob([...localParts, ...centralParts, endRecord], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
}

function escapeXml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

function stylesXml(): string {
    return (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        + '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        + '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts>'
        + '<fonts count="2">'
        + '<font><sz val="14"/><name val="Times New Roman"/><family val="1"/></font>'
        + '<font><b/><sz val="14"/><name val="Times New Roman"/><family val="1"/></font>'
        + '</fonts>'
        + '<fills count="2">'
        + '<fill><patternFill patternType="none"/></fill>'
        + '<fill><patternFill patternType="gray125"/></fill>'
        + '</fills>'
        + '<borders count="2">'
        + '<border><left/><right/><top/><bottom/><diagonal/></border>'
        + '<border>'
        + '<left style="thin"><color rgb="FF000000"/></left>'
        + '<right style="thin"><color rgb="FF000000"/></right>'
        + '<top style="thin"><color rgb="FF000000"/></top>'
        + '<bottom style="thin"><color rgb="FF000000"/></bottom>'
        + '<diagonal/>'
        + '</border>'
        + '</borders>'
        + '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        + '<cellXfs count="5">'
        + '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        + '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        + '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        + '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        + '<xf numFmtId="164" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        + '</cellXfs>'
        + '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        + '</styleSheet>'
    );
}

function estimateRowHeight(texts: string[], widths: number[]): number {
    let maxLines = 1;
    texts.forEach((text, index) => {
        const colWidth = Math.max(8, (widths[index] ?? 16) - 4);
        const lines = Math.max(1, Math.ceil(text.length / colWidth));
        if (lines > maxLines) {
            maxLines = lines;
        }
    });

    return Math.min(180, Math.max(28, maxLines * 20 + 8));
}

function worksheetXml(rows: CartExcelRow[], totalQuantity: number, totalAmount: number): string {
    const headers = [
        'STT',
        'Tên sản phẩm',
        'Danh mục',
        'Số lượng',
        'Đơn giá tham khảo (VNĐ)',
        'Thành tiền (VNĐ)',
    ];

    const widths = [10, 38, 26, 14, 28, 28];
    rows.forEach((row) => {
        widths[1] = Math.min(56, Math.max(widths[1], row.name.length + 6));
        widths[2] = Math.min(40, Math.max(widths[2], row.category.length + 6));
    });

    const colsXml = widths
        .map((width, idx) => `<col min="${idx + 1}" max="${idx + 1}" width="${width}" customWidth="1"/>`)
        .join('');

    const colLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
    let sheetRowsXml = '';

    // Row 1: Header
    const headerCells = headers
        .map(
            (title, colIdx) =>
                `<c r="${colLetters[colIdx]}1" s="2" t="inlineStr"><is><t xml:space="preserve">${escapeXml(title)}</t></is></c>`,
        )
        .join('');
    sheetRowsXml += `<row r="1" ht="32" customHeight="1">${headerCells}</row>`;

    // Data rows
    rows.forEach((row, index) => {
        const rowNum = index + 2;
        const height = estimateRowHeight([String(row.index), row.name, row.category], widths);
        const cells = [
            `<c r="A${rowNum}" s="3" t="n"><v>${row.index}</v></c>`,
            `<c r="B${rowNum}" s="1" t="inlineStr"><is><t xml:space="preserve">${escapeXml(row.name)}</t></is></c>`,
            `<c r="C${rowNum}" s="1" t="inlineStr"><is><t xml:space="preserve">${escapeXml(row.category)}</t></is></c>`,
            `<c r="D${rowNum}" s="3" t="n"><v>${row.quantity}</v></c>`,
            `<c r="E${rowNum}" s="3" t="n"><v>${row.unitPrice}</v></c>`,
            `<c r="F${rowNum}" s="3" t="n"><v>${row.lineTotal}</v></c>`,
        ].join('');
        sheetRowsXml += `<row r="${rowNum}" ht="${height}" customHeight="1">${cells}</row>`;
    });

    // Total row
    const totalRowNum = rows.length + 2;
    const totalCells = [
        `<c r="A${totalRowNum}" s="2" t="inlineStr"><is><t xml:space="preserve">TỔNG CỘNG THAM KHẢO</t></is></c>`,
        `<c r="B${totalRowNum}" s="2"/>`,
        `<c r="C${totalRowNum}" s="2"/>`,
        `<c r="D${totalRowNum}" s="4" t="n"><v>${totalQuantity}</v></c>`,
        `<c r="E${totalRowNum}" s="2"/>`,
        `<c r="F${totalRowNum}" s="4" t="n"><v>${totalAmount}</v></c>`,
    ].join('');
    sheetRowsXml += `<row r="${totalRowNum}" ht="30" customHeight="1">${totalCells}</row>`;

    return (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        + '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        + `<cols>${colsXml}</cols>`
        + `<sheetData>${sheetRowsXml}</sheetData>`
        + `<mergeCells count="1"><mergeCell ref="A${totalRowNum}:C${totalRowNum}"/></mergeCells>`
        + '</worksheet>'
    );
}

export function downloadCartExcelWorkbook(
    rows: CartExcelRow[],
    totalQuantity: number,
    totalAmount: number,
    filename: string,
): void {
    const files: Record<string, string> = {
        '[Content_Types].xml':
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            + '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            + '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            + '<Default Extension="xml" ContentType="application/xml"/>'
            + '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            + '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            + '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            + '</Types>',
        '_rels/.rels':
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            + '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            + '</Relationships>',
        'xl/workbook.xml':
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            + '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            + '<sheets><sheet name="Bảng dự toán" sheetId="1" r:id="rId1"/></sheets>'
            + '</workbook>',
        'xl/_rels/workbook.xml.rels':
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            + '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            + '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            + '</Relationships>',
        'xl/worksheets/sheet1.xml': worksheetXml(rows, totalQuantity, totalAmount),
        'xl/styles.xml': stylesXml(),
    };

    const blob = createZipArchive(files);
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
