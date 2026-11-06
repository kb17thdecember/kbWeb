export type CompareExportProduct = {
    id: number;
    name: string;
    product_title: string | null;
    description: string;
    price: number;
    image: string[] | null;
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
};

export type CompareExportCompanyInfo = {
    companyName?: string;
    tagline?: string;
    hotline?: string;
    email?: string;
    address?: string;
    onCompleted?: () => void;
};

const escapeHtml = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const resolveImageUrl = (raw?: string | null): string | null => {
    if (!raw) {
        return null;
    }
    if (raw.startsWith('data:') || raw.startsWith('http')) {
        return raw;
    }
    const path = raw.startsWith('/') ? raw : `/storage/${raw}`;
    return `${window.location.origin}${path}`;
};

const formatCurrency = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ báo giá';

const formatDescriptionLines = (description: string): string => {
    const lines = (description || '')
        .split(/\r?\n/)
        .map((line) => line.trim().replace(/^[-•*]\s*/, ''))
        .filter(Boolean);

    if (lines.length === 0) {
        return '<p class="spec-empty">Đang cập nhật thông số kỹ thuật.</p>';
    }

    return `<ul class="spec-list">${lines.map((line) => `<li>${escapeHtml(line)}</li>`).join('')}</ul>`;
};

export function exportProductComparisonPdf(
    products: CompareExportProduct[],
    company: CompareExportCompanyInfo = {},
): boolean {
    if (products.length === 0 || typeof window === 'undefined') {
        return false;
    }

    const printWindow = window.open('', '_blank', 'width=1024,height=780');
    if (!printWindow) {
        return false;
    }

    const companyName = escapeHtml(company.companyName || 'HVAC Mart');
    const tagline = escapeHtml(company.tagline || 'HVAC & ENGINEERING SUPPLY');
    const hotline = escapeHtml(company.hotline || '0979.807.234');
    const email = escapeHtml(company.email || 'kbnguyen0311@gmail.com');
    const address = escapeHtml(company.address || 'Hà Nội, Việt Nam');

    const now = new Date();
    const dateStr = now.toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
    const docCode = `CMP-${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}-${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}`;

    const headerCols = products
        .map((item, idx) => {
            const imgUrl = resolveImageUrl(item.image?.[0]);
            const imgHtml = imgUrl
                ? `<div class="prod-thumb"><img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(item.name)}" /></div>`
                : `<div class="prod-thumb prod-thumb-fallback">${escapeHtml(item.category?.name ?? 'HVAC')}</div>`;

            return `
                <th>
                    <div class="col-head">
                        <span class="col-badge">THIẾT BỊ 0${idx + 1}</span>
                        ${imgHtml}
                        <div class="col-cat">${escapeHtml(item.category?.name ?? 'Thiết bị kỹ thuật')}</div>
                        <div class="col-name">${escapeHtml(item.name)}</div>
                    </div>
                </th>
            `;
        })
        .join('');

    const codeCols = products
        .map((item) => `<td><strong>${escapeHtml(item.name)}</strong></td>`)
        .join('');

    const categoryCols = products
        .map((item) => `<td>${escapeHtml(item.category?.name ?? '—')}</td>`)
        .join('');

    const subcategoryCols = products
        .map((item) => `<td>${escapeHtml(item.subcategory?.name ?? '—')}</td>`)
        .join('');

    const priceCols = products
        .map((item) => `<td class="price-cell">${escapeHtml(formatCurrency(item.price))}</td>`)
        .join('');

    const specCols = products
        .map((item) => `<td>${formatDescriptionLines(item.description)}</td>`)
        .join('');

    const html = `<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>BẢNG SO SÁNH THÔNG SỐ KỸ THUẬT</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 16px;
            font-family: "Be Vietnam Pro", "Segoe UI", Arial, sans-serif;
            color: #1c1c18;
            background: #ffffff;
            line-height: 1.5;
            font-size: 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .sheet {
            max-width: 960px;
            margin: 0 auto;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #171714;
            margin-bottom: 16px;
        }
        .brand-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 4px;
            background: #b79a63;
            color: #171714;
            font-weight: 900;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .brand-title strong {
            display: block;
            font-size: 16px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .brand-title small {
            display: block;
            font-size: 9px;
            letter-spacing: 0.16em;
            color: #6b685e;
            margin-top: 2px;
        }
        .company-meta {
            margin-top: 8px;
            font-size: 11px;
            color: #55534b;
        }
        .doc-meta {
            text-align: right;
            font-size: 11px;
            color: #55534b;
        }
        .doc-meta strong {
            display: block;
            font-size: 12px;
            color: #171714;
            margin-bottom: 3px;
        }
        .doc-banner {
            background: #f6f4ef;
            border: 1px solid #dedad0;
            border-left: 4px solid #b79a63;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        .doc-banner h1 {
            margin: 0 0 4px;
            font-size: 17px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #171714;
        }
        .doc-banner p {
            margin: 0;
            font-size: 11.5px;
            color: #5d5b53;
        }
        table.cmp-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .cmp-table th,
        .cmp-table td {
            border: 1px solid #d8d4c8;
            padding: 10px 12px;
            vertical-align: top;
            text-align: left;
            word-break: break-word;
        }
        .cmp-table thead th:first-child,
        .cmp-table tbody td:first-child {
            width: 155px;
            background: #faf8f3;
            font-weight: 700;
            color: #3a3830;
            font-size: 11.5px;
        }
        .cmp-table thead th {
            background: #171714;
            color: #f2efe5;
        }
        .cmp-table thead th:first-child {
            background: #24241f;
            color: #dfcda9;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 10.5px;
            vertical-align: middle;
        }
        .col-head {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .col-badge {
            display: inline-block;
            align-self: flex-start;
            padding: 2px 7px;
            background: rgba(183, 154, 99, 0.22);
            border: 1px solid rgba(223, 205, 169, 0.35);
            color: #dfcda9;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 0.1em;
            border-radius: 2px;
        }
        .prod-thumb {
            width: 100%;
            height: 110px;
            background: #ffffff;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            overflow: hidden;
        }
        .prod-thumb img {
            max-width: 100%;
            max-height: 98px;
            object-fit: contain;
        }
        .prod-thumb-fallback {
            color: #6b685e;
            font-weight: 800;
            font-size: 14px;
            letter-spacing: 0.1em;
        }
        .col-cat {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #dfcda9;
        }
        .col-name {
            font-size: 14px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.3;
        }
        .price-cell {
            font-weight: 800;
            font-size: 13px;
            color: #8a6d38;
        }
        .spec-list {
            margin: 0;
            padding-left: 16px;
        }
        .spec-list li {
            margin-bottom: 4px;
            font-size: 11.5px;
            line-height: 1.45;
        }
        .spec-empty {
            margin: 0;
            color: #77766e;
            font-style: italic;
        }
        .doc-footer {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #dedad0;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 10.5px;
            color: #6b685e;
        }
        .toolbar-no-print {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 12px;
        }
        .toolbar-btn {
            padding: 8px 14px;
            border: 1px solid #171714;
            border-radius: 3px;
            background: #171714;
            color: #f2efe5;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
        }
        .toolbar-btn.secondary {
            background: #ffffff;
            color: #171714;
        }
        @media print {
            body {
                padding: 12mm;
            }
            .toolbar-no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar-no-print">
            <button type="button" class="toolbar-btn secondary" onclick="window.close()">Đóng cửa sổ</button>
            <button type="button" class="toolbar-btn" onclick="window.print()">In / Lưu file PDF</button>
        </div>

        <header class="doc-header">
            <div>
                <div class="brand-row">
                    <span class="brand-mark">HM</span>
                    <div class="brand-title">
                        <strong>${companyName}</strong>
                        <small>${tagline}</small>
                    </div>
                </div>
                <div class="company-meta">
                    Địa chỉ: ${address} · Hotline: ${hotline} · Email: ${email}
                </div>
            </div>
            <div class="doc-meta">
                <strong>PHIẾU SO SÁNH KỸ THUẬT</strong>
                <div>Mã tài liệu: ${docCode}</div>
                <div>Ngày xuất: ${dateStr}</div>
            </div>
        </header>

        <section class="doc-banner">
            <h1>Bảng đối chiếu thông số kỹ thuật thiết bị (${products.length} sản phẩm)</h1>
            <p>Tài liệu phục vụ công tác bóc tách khối lượng BOQ, trình duyệt vật tư kỹ thuật M&amp;E và lựa chọn cấu hình thiết bị cho dự án.</p>
        </section>

        <table class="cmp-table">
            <thead>
                <tr>
                    <th>Tiêu chí kỹ thuật</th>
                    ${headerCols}
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Tên sản phẩm</td>
                    ${codeCols}
                </tr>
                <tr>
                    <td>Thương hiệu / Danh mục</td>
                    ${categoryCols}
                </tr>
                <tr>
                    <td>Dòng sản phẩm</td>
                    ${subcategoryCols}
                </tr>
                <tr>
                    <td>Đơn giá tham khảo</td>
                    ${priceCols}
                </tr>
                <tr>
                    <td>Thông số kỹ thuật chi tiết</td>
                    ${specCols}
                </tr>
            </tbody>
        </table>

        <footer class="doc-footer">
            <span>* Lưu ý: Đơn giá và thông số kỹ thuật có thể được điều chỉnh theo chính sách chiết khấu dự án. Liên hệ ${companyName} (${hotline}) để nhận CO/CQ và báo giá chính thức.</span>
            <span>${companyName} · Submittal Sheet</span>
        </footer>
    </div>
    <script>
        (function () {
            var closePrintWindow = function () {
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

            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.focus();
                    window.print();
                }, 250);
            });
        })();
    </script>
</body>
</html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.document.title = 'BẢNG SO SÁNH THÔNG SỐ KỸ THUẬT';

    if (company.onCompleted) {
        let notified = false;
        const notifyOnce = (): void => {
            if (notified) {
                return;
            }
            notified = true;
            company.onCompleted?.();
        };
        printWindow.addEventListener('afterprint', notifyOnce);
        printWindow.addEventListener('beforeunload', notifyOnce);
    }

    return true;
}
