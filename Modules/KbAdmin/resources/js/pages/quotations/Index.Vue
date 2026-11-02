<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import aristonLogoAsset from '../../../assets/images/ariston_quote_logo.png';
import starLightLogoAsset from '../../../assets/images/starlight_logo.png';
import AdminLayout from '../../layout/AdminLayout.Vue';

type AdminStat = {
    label: string;
    count: number;
    description: string;
};

type AristonProduct = {
    id: number;
    name: string;
    product_title: string | null;
    description: string;
    price: number;
    image: string[];
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
};

type QuotationItem = {
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

const props = defineProps<{
    aristonProducts: AristonProduct[];
    stats: AdminStat[];
    openRequestsCount: number;
    chatUnreadCount?: number;
    supportProjectsCount?: number;
}>();

const activeQuotationType = ref<'ariston' | 'second'>('ariston');

const pad2 = (n: number): string => String(n).padStart(2, '0');

const toInputDate = (date: Date): string =>
    `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;

const addTwoMonths = (isoDate: string): string => {
    const parts = isoDate.split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        const fallback = new Date();
        fallback.setMonth(fallback.getMonth() + 2);
        return toInputDate(fallback);
    }
    const d = new Date(parts[0], parts[1] - 1 + 2, parts[2]);
    return toInputDate(d);
};

const formatDisplayDateSlash = (isoDate: string, padDayMonth = false): string => {
    const parts = isoDate.split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        return isoDate;
    }
    const [year, month, day] = parts;
    if (padDayMonth) {
        return `${pad2(day)}/${pad2(month)}/${year}`;
    }
    return `${day}/${month}/${year}`;
};

const todayIso = toInputDate(new Date());

const quoteForm = ref({
    documentTitle: 'BÁO GIÁ BƠM NHIỆT ARISTON',
    recipientCompany: 'QUÝ ĐỐI TÁC CÔNG TY CỔ PHẦN TẬP ĐOÀN BĐS VẠN XUÂN',
    recipientPerson: 'Ms.Vân Chi',
    contactPhone: '0963.015.966',
    quoteDate: todayIso,
    validUntilDate: addTwoMonths(todayIso),
    projectName: 'Chung cư cao cấp Vạn Xuân',
    vatPercent: 8,
});

watch(
    () => quoteForm.value.quoteDate,
    (newDate) => {
        if (newDate) {
            quoteForm.value.validUntilDate = addTwoMonths(newDate);
        }
    },
);

const deriveProductLabel = (product: AristonProduct): string => {
    const desc = product.description || '';
    const capacityMatch = desc.match(/Dung tích\s*:\s*([0-9]+\s*L)/i) || product.name.match(/\b(15|20|30|40|50|80|100|150)\s*B?\b/i);
    const subcatName = (product.subcategory?.name || '').toLowerCase();

    if (subcatName.includes('bơm nhiệt')) {
        return `Máy bơm nhiệt nước nóng Ariston`;
    }

    if (capacityMatch) {
        const cap = capacityMatch[1].toUpperCase().endsWith('L')
            ? capacityMatch[1].replace(/\s+/g, '')
            : `${capacityMatch[1]}L`;
        return `Máy nước nóng gián tiếp dung tích ${cap}`;
    }

    return product.product_title || 'Máy nước nóng gián tiếp Ariston';
};

const resolveImageUrl = (raw?: string | null): string => {
    if (!raw) {
        return '';
    }
    if (raw.startsWith('data:') || raw.startsWith('http')) {
        return raw;
    }
    const path = raw.startsWith('/') ? raw : `/storage/${raw}`;
    return typeof window !== 'undefined' ? `${window.location.origin}${path}` : path;
};

let uidCounter = 1;
const nextUid = (): string => `row-${Date.now()}-${uidCounter++}`;

const createItemFromProduct = (product?: AristonProduct, quantity = 1): QuotationItem => {
    if (!product) {
        return {
            uid: nextUid(),
            productId: null,
            productLabel: 'Máy nước nóng gián tiếp dung tích 30L',
            modelCode: '',
            origin: 'Ariston-Ý\n(Sản xuất VN)',
            imageUrl: '',
            unit: 'Máy',
            specifications: '',
            unitPrice: 0,
            quantity,
        };
    }

    return {
        uid: nextUid(),
        productId: product.id,
        productLabel: deriveProductLabel(product),
        modelCode: product.name,
        origin: 'Ariston-Ý\n(Sản xuất VN)',
        imageUrl: resolveImageUrl(product.image?.[0]),
        unit: 'Máy',
        specifications: product.description || '',
        unitPrice: product.price > 0 ? product.price : 2386000,
        quantity,
    };
};

const defaultFirstProduct =
    props.aristonProducts.find((p) => p.name.includes('SLIM3 20')) ||
    props.aristonProducts[0];

const items = ref<QuotationItem[]>([
    createItemFromProduct(defaultFirstProduct, 798),
]);

const productSearchQuery = ref('');
const expandedUid = ref<string | null>(null);

const filteredAristonCatalog = computed(() => {
    const q = productSearchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.aristonProducts;
    }
    return props.aristonProducts.filter(
        (prod) =>
            prod.name.toLowerCase().includes(q) ||
            (prod.product_title || '').toLowerCase().includes(q) ||
            (prod.subcategory?.name || '').toLowerCase().includes(q),
    );
});

const isProductSelected = (productId: number): boolean =>
    items.value.some((item) => item.productId === productId);

const toggleProductSelection = (product: AristonProduct): void => {
    const existingIndex = items.value.findIndex((item) => item.productId === product.id);
    if (existingIndex !== -1) {
        if (items.value.length > 1) {
            items.value.splice(existingIndex, 1);
        }
        return;
    }

    items.value.push(createItemFromProduct(product, 1));
};

const selectAllFilteredProducts = (): void => {
    filteredAristonCatalog.value.forEach((product) => {
        if (!isProductSelected(product.id)) {
            items.value.push(createItemFromProduct(product, 1));
        }
    });
};

const clearToFirstProduct = (): void => {
    if (items.value.length > 1) {
        items.value = [items.value[0]];
    }
};

const toggleExpandRow = (uid: string): void => {
    expandedUid.value = expandedUid.value === uid ? null : uid;
};

const onSelectProduct = (item: QuotationItem, productIdValue: string): void => {
    const id = Number(productIdValue);
    if (!id) {
        item.productId = null;
        return;
    }
    const found = props.aristonProducts.find((p) => p.id === id);
    if (!found) {
        return;
    }
    item.productId = found.id;
    item.productLabel = deriveProductLabel(found);
    item.modelCode = found.name;
    item.origin = 'Ariston-Ý\n(Sản xuất VN)';
    item.imageUrl = resolveImageUrl(found.image?.[0]);
    item.unit = 'Máy';
    item.specifications = found.description || '';
    if (found.price > 0) {
        item.unitPrice = found.price;
    }
};

const addAristonRow = (): void => {
    const fallback = props.aristonProducts[0];
    items.value.push(createItemFromProduct(fallback, 1));
};

const addAccessoryRow = (): void => {
    items.value.push({
        uid: nextUid(),
        productId: null,
        productLabel: 'Dây mềm cấp nước 40cm',
        modelCode: '',
        origin: 'Thủy Sanh-\nVN',
        imageUrl: '',
        unit: 'Dây',
        specifications:
            '-Dây cấp 4T lạnh\n-Dây cấp inox 40cm chịu nhiệt, ốc nối đồng, lớp trong nhựa PVC, lớp ngoài inox\n-Chiều dài dây 40cm',
        unitPrice: 50000,
        quantity: 1,
    });
};

const removeRow = (index: number): void => {
    if (items.value.length <= 1) {
        return;
    }
    items.value.splice(index, 1);
};

const onUploadCustomImage = (item: QuotationItem, event: Event): void => {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];
    if (!file) {
        return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        if (typeof reader.result === 'string') {
            item.imageUrl = reader.result;
        }
    };
    reader.readAsDataURL(file);
};

const formatVnd = (amount: number): string => {
    const safe = Number.isFinite(amount) ? Math.round(amount) : 0;
    return new Intl.NumberFormat('vi-VN').format(safe);
};

const rowAmount = (item: QuotationItem): number =>
    Math.round((Number(item.unitPrice) || 0) * (Number(item.quantity) || 0));

const subTotal = computed(() =>
    items.value.reduce((sum, item) => sum + rowAmount(item), 0),
);

const vatAmount = computed(() =>
    Math.round((subTotal.value * (Number(quoteForm.value.vatPercent) || 0)) / 100),
);

const grandTotal = computed(() => subTotal.value + vatAmount.value);

const formattedQuoteDate = computed(() =>
    formatDisplayDateSlash(quoteForm.value.quoteDate, false),
);

const formattedValidUntil = computed(() =>
    formatDisplayDateSlash(quoteForm.value.validUntilDate, true),
);

const formattedVatLabel = computed(() => {
    const v = Number(quoteForm.value.vatPercent) || 0;
    return v < 10 ? `0${v}%` : `${v}%`;
});

const escapeHtml = (value: string): string =>
    (value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const nl2br = (value: string): string =>
    escapeHtml(value).replace(/\r?\n/g, '<br>');

const starLightLogoUrl = computed(() => resolveImageUrl(starLightLogoAsset));
const aristonLogoUrl = computed(() => resolveImageUrl(aristonLogoAsset));
const isPortraitMode = computed(() => items.value.length >= 3);

const printQuotationPdf = (): void => {
    const portrait = isPortraitMode.value;
    const pageOrientation = portrait ? 'portrait' : 'landscape';
    const pageW = portrait ? '210mm' : '297mm';
    const pageH = portrait ? '297mm' : '210mm';
    const winFeatures = portrait ? 'width=920,height=1160' : 'width=1240,height=860';

    const printWindow = window.open('', '_blank', winFeatures);
    if (!printWindow) {
        return;
    }

    const rowsHtml = items.value
        .map((item) => {
            const imgCell = item.imageUrl
                ? `<img src="${escapeHtml(item.imageUrl)}" alt="${escapeHtml(item.modelCode || item.productLabel)}" class="q-prod-img" />`
                : '';

            return `
                <tr>
                    <td class="c-center c-bold">${nl2br(item.productLabel)}</td>
                    <td class="c-center c-bold">${nl2br(item.modelCode)}</td>
                    <td class="c-center c-bold">${nl2br(item.origin)}</td>
                    <td class="c-center c-img">${imgCell}</td>
                    <td class="c-center c-bold">${escapeHtml(item.unit)}</td>
                    <td class="c-spec">${nl2br(item.specifications)}</td>
                    <td class="c-center c-bold">${formatVnd(item.unitPrice)}</td>
                    <td class="c-center c-bold">${formatVnd(item.quantity)}</td>
                    <td class="c-center c-bold">${formatVnd(rowAmount(item))}</td>
                </tr>
            `;
        })
        .join('');

    const html = `<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>${escapeHtml(quoteForm.value.documentTitle)} - ${escapeHtml(quoteForm.value.projectName)}</title>
    <style>
        @page {
            size: A4 ${pageOrientation};
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            width: ${pageW};
            height: ${pageH};
            max-height: ${pageH};
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: "Times New Roman", Times, serif;
            color: #000000;
            background: #ffffff;
            font-size: 10.5pt;
            line-height: 1.26;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .q-page-frame {
            width: ${pageW};
            height: ${pageH};
            max-height: ${pageH};
            padding: 5mm 8mm;
            overflow: hidden;
            page-break-after: avoid;
            break-after: avoid;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .q-scale-box {
            width: 100%;
            overflow: hidden;
        }
        .q-sheet {
            width: 100%;
            transform-origin: top left;
        }
        .q-header {
            display: grid;
            grid-template-columns: 200px 1fr 210px;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }
        .q-logo-left {
            padding-left: 14px;
        }
        .q-logo-left img {
            width: 112px;
            height: auto;
            object-fit: contain;
            display: block;
        }
        .q-company-center {
            text-align: center;
            line-height: 1.24;
        }
        .q-company-center h1 {
            margin: 0 0 2px;
            font-size: 13.5pt;
            font-weight: 700;
            text-transform: uppercase;
        }
        .q-company-center p {
            margin: 0;
            font-size: 11.5pt;
        }
        .q-logo-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
        }
        .q-logo-right img {
            width: 188px;
            height: auto;
            object-fit: contain;
            display: block;
        }
        .q-title {
            text-align: center;
            font-size: 14.5pt;
            font-weight: 700;
            text-decoration: underline;
            text-transform: uppercase;
            margin: 5px 0 6px;
        }
        .q-meta {
            width: 100%;
            margin-bottom: 3px;
            font-size: 10.5pt;
        }
        .q-meta-row {
            display: grid;
            grid-template-columns: 9.5% 1fr;
            margin-bottom: 2px;
            align-items: baseline;
        }
        .q-meta-label-kinhgui {
            font-weight: 700;
            font-style: italic;
            text-decoration: underline;
        }
        .q-meta-val-kinhgui {
            text-transform: uppercase;
        }
        .q-meta-label-italic {
            font-style: italic;
            padding-left: 3px;
        }
        .q-meta-label-project {
            font-weight: 700;
            font-style: italic;
        }
        .q-meta-val-italic {
            font-style: italic;
        }
        .q-intro {
            text-align: center;
            font-style: italic;
            font-size: 10.2pt;
            margin: 3px 0 4px;
        }
        table.q-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10pt;
        }
        table.q-table th,
        table.q-table td {
            border: 1px solid #000000;
            padding: 4px 5px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.q-table thead th {
            text-align: center;
            font-weight: 700;
            font-size: 9.5pt;
            line-height: 1.2;
            padding: 5px 3px;
        }
        table.q-table thead th em {
            display: block;
            font-style: italic;
            font-weight: 400;
        }
        .col-prod { width: 9.5%; }
        .col-model { width: 8.5%; }
        .col-origin { width: 5.5%; }
        .col-img { width: 18.5%; }
        .col-unit { width: 4.5%; }
        .col-spec { width: 29.0%; }
        .col-price { width: 8.5%; }
        .col-qty { width: 6.5%; }
        .col-amount { width: 9.5%; }
        .c-center {
            text-align: center;
        }
        .c-bold {
            font-weight: 700;
        }
        .c-spec {
            text-align: left;
            vertical-align: middle;
            line-height: 1.28;
            font-size: 10.5pt;
            padding: 5px 7px;
        }
        .c-img {
            padding: 4px;
        }
        .q-prod-img {
            width: 98%;
            max-width: 100%;
            height: 178px;
            max-height: 188px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .q-summary-row td {
            font-weight: 700;
            padding: 3.5px 5px;
            font-size: 9.8pt;
        }
        .q-summary-label {
            text-align: center;
        }
        .q-summary-label em {
            font-style: italic;
            font-weight: 700;
        }
        .q-terms {
            margin-top: 3px;
            font-size: 9pt;
            line-height: 1.26;
            font-weight: 700;
        }
        .q-terms-head {
            text-decoration: underline;
            margin-bottom: 1px;
        }
        .q-terms p {
            margin: 1px 0;
        }
        .q-sign {
            margin-top: 6px;
            font-size: 9.2pt;
            line-height: 1.26;
            font-weight: 700;
            font-style: italic;
        }
        .q-sign p {
            margin: 1px 0;
        }
    </style>
</head>
<body>
    <div class="q-page-frame" id="qPageFrame">
        <div class="q-scale-box" id="qScaleBox">
            <div class="q-sheet" id="qSheet">
        <header class="q-header">
            <div class="q-logo-left"><img src="${escapeHtml(starLightLogoUrl.value)}" alt="STAR LIGHT" /></div>
            <div class="q-company-center">
                <h1>CÔNG TY CỔ PHẦN QUỐC TẾ STAR LIGHT</h1>
                <p>Địa chỉ VP: Số 15 Nguyễn Cảnh Dị, Định Công, TP Hà Nội</p>
                <p>Điện thoại: 0243 201 8322 - Hotline: 0979 541 345</p>
                <p>www: starlightgroup.vn</p>
            </div>
            <div class="q-logo-right"><img src="${escapeHtml(aristonLogoUrl.value)}" alt="ARISTON" /></div>
        </header>

        <div class="q-title">${escapeHtml(quoteForm.value.documentTitle)}</div>

        <div class="q-meta">
            <div class="q-meta-row">
                <span class="q-meta-label-kinhgui">Kính gửi:</span>
                <span class="q-meta-val-kinhgui">${escapeHtml(quoteForm.value.recipientCompany)}</span>
            </div>
            <div class="q-meta-row">
                <span class="q-meta-label-italic">Người nhận :</span>
                <span class="q-meta-val-italic">${escapeHtml(quoteForm.value.recipientPerson)}</span>
            </div>
            ${
                quoteForm.value.contactPhone.trim()
                    ? `<div class="q-meta-row">
                        <span class="q-meta-label-italic">Thông tin liên lạc:</span>
                        <span class="q-meta-val-italic">${escapeHtml(quoteForm.value.contactPhone)}</span>
                    </div>`
                    : ''
            }
            <div class="q-meta-row">
                <span class="q-meta-label-italic">Thời gian :</span>
                <span class="q-meta-val-italic">${escapeHtml(formattedQuoteDate.value)}</span>
            </div>
            <div class="q-meta-row">
                <span class="q-meta-label-project">Project:</span>
                <span class="q-meta-val-italic">${escapeHtml(quoteForm.value.projectName)}</span>
            </div>
        </div>

        <div class="q-intro">
            Công ty Star Light là đơn vị phát triển mảng dự án sản phẩm Ariston, rất hân hạnh gửi bảng báo giá cho Quý Khách hàng. Cụ thể như sau
        </div>

        <table class="q-table">
            <thead>
                <tr>
                    <th class="col-prod">Sản Phẩm<em>Product</em></th>
                    <th class="col-model">Mã sản phẩm<em>Model</em></th>
                    <th class="col-origin">Xuất xứ<em>Origin</em></th>
                    <th class="col-img">Hình ảnh sản phẩm<em>Image</em></th>
                    <th class="col-unit">Đơn vị</th>
                    <th class="col-spec">Đặc tính kỹ thuật<em>Specifications</em></th>
                    <th class="col-price">Giá bán dự án<em>Selling Price</em><em>(VNĐ)</em></th>
                    <th class="col-qty">Số lượng Quantity<br>(chiếc)</th>
                    <th class="col-amount">Thành tiền<em>Amount</em><em>(VNĐ)</em></th>
                </tr>
            </thead>
            <tbody>
                ${rowsHtml}
                <tr class="q-summary-row">
                    <td colspan="7" class="q-summary-label">
                        TỔNG CỘNG (chưa bao gồm thuế VAT) - <em>Sub Total (not included VAT)</em>
                    </td>
                    <td></td>
                    <td class="c-center">${formatVnd(subTotal.value)}</td>
                </tr>
                <tr class="q-summary-row">
                    <td colspan="7" class="q-summary-label">
                        Thuế VAT (${formattedVatLabel.value})
                    </td>
                    <td></td>
                    <td class="c-center">${formatVnd(vatAmount.value)}</td>
                </tr>
                <tr class="q-summary-row">
                    <td colspan="7" class="q-summary-label">
                        TỔNG CỘNG (bao gồm thuế VAT) - <em>Sub Total (included VAT)</em>
                    </td>
                    <td></td>
                    <td class="c-center">${formatVnd(grandTotal.value)}</td>
                </tr>
            </tbody>
        </table>

        <div class="q-terms">
            <div class="q-terms-head">Điều kiện thương mại:</div>
            <p>1. Báo giá có hiệu lực đến ngày: ${escapeHtml(formattedValidUntil.value)}</p>
            <p>2. Phương thức thanh toán: Thỏa Thuận</p>
            <p>3. Phương thức giao hàng: đến chân công trình</p>
            <p>4. Thời gian giao hàng: 40 ngày đối với hàng trong nước, 90 ngày đối với hàng nhập khẩu kể từ ngày đơn đặt hàng được xác nhận</p>
            <p>5. Bảo hành: 2 năm linh kiện điện tử, 3 năm với máy nén Theo chính sách bảo hành của Ariston Việt Nam</p>
            <p>6. Chúng tôi hy vọng báo giá này sẽ làm hài lòng Quý Công ty. Mọi thắc mắc xin vui lòng liên lạc với chúng tôi</p>
            <p>Rất mong nhận được sự ủng hộ từ Quý Công ty.</p>
        </div>

        <div class="q-sign">
            <p>Many thanks &amp; Best regards</p>
            <p>Nguyễn Tuấn Kiệt</p>
            <p>HP: 0979 807 234</p>
            <p>Email : tuankiet@starlightgroup.vn</p>
        </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var imgs = Array.prototype.slice.call(document.images);
            var pending = imgs.filter(function (img) { return !img.complete; });

            function fitSinglePageAndPrint() {
                var frame = document.getElementById('qPageFrame');
                var scaleBox = document.getElementById('qScaleBox');
                var sheet = document.getElementById('qSheet');
                if (frame && scaleBox && sheet) {
                    var cs = window.getComputedStyle(frame);
                    var padY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
                    var availH = frame.clientHeight - padY - 2;
                    var rawH = sheet.scrollHeight;

                    if (rawH > availH && availH > 0) {
                        var low = 0.28;
                        var high = 1.0;
                        var best = 0.28;
                        for (var i = 0; i < 12; i++) {
                            var mid = (low + high) / 2;
                            sheet.style.width = (100 / mid) + '%';
                            sheet.style.transform = 'scale(' + mid + ')';
                            var scaledH = sheet.scrollHeight * mid;
                            if (scaledH <= availH) {
                                best = mid;
                                low = mid;
                            } else {
                                high = mid;
                            }
                        }
                        sheet.style.width = (100 / best) + '%';
                        sheet.style.transform = 'scale(' + best + ')';
                        scaleBox.style.height = Math.floor(availH) + 'px';
                    } else {
                        scaleBox.style.height = Math.ceil(rawH) + 'px';
                    }
                }

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

                setTimeout(function () {
                    window.focus();
                    window.print();
                }, 150);
            }

            if (pending.length === 0) {
                fitSinglePageAndPrint();
            } else {
                var loaded = 0;
                pending.forEach(function (img) {
                    var done = function () {
                        loaded++;
                        if (loaded >= pending.length) {
                            fitSinglePageAndPrint();
                        }
                    };
                    img.addEventListener('load', done);
                    img.addEventListener('error', done);
                });
            }
        })();
    <\/script>
</body>
</html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
};
</script>

<template>
    <AdminLayout
        :stats="props.stats"
        :open-requests-count="props.openRequestsCount"
        :chat-unread-count="props.chatUnreadCount"
        :support-projects-count="props.supportProjectsCount"
    >
        <div class="page-head">
            <div>
                <h2>Tạo Báo Giá &amp; Xuất PDF</h2>
                <p>Lập bảng báo giá dự án chuẩn mẫu Star Light và xuất file PDF khổ A4 ngang.</p>
            </div>
            <div class="actions">
                <button
                    v-if="activeQuotationType === 'ariston'"
                    class="btn primary"
                    type="button"
                    @click="printQuotationPdf"
                >
                    🖨️ In / Xuất PDF Báo Giá
                </button>
            </div>
        </div>

        <div class="kb-quote-type-tabs">
            <button
                type="button"
                class="kb-quote-tab-btn"
                :class="{ active: activeQuotationType === 'ariston' }"
                @click="activeQuotationType = 'ariston'"
            >
                <span class="kb-quote-tab-badge">01</span>
                <div>
                    <strong>Báo giá Bình nước nóng / Bơm nhiệt Ariston</strong>
                    <small>Mẫu chuẩn Công ty CP Quốc tế Star Light · Ariston</small>
                </div>
            </button>
            <button
                type="button"
                class="kb-quote-tab-btn"
                :class="{ active: activeQuotationType === 'second' }"
                @click="activeQuotationType = 'second'"
            >
                <span class="kb-quote-tab-badge">02</span>
                <div>
                    <strong>Loại báo giá thứ 2 (Bảo ôn / Vật tư dự án)</strong>
                    <small>Sẵn sàng cập nhật khi bạn gửi file mẫu thứ 2</small>
                </div>
            </button>
        </div>

        <template v-if="activeQuotationType === 'ariston'">
            <div class="kb-quote-builder-card">
                <div class="kb-quote-section-title">1. Thông tin chung của Báo giá</div>
                <div class="kb-quote-form-grid">
                    <label class="kb-quote-field">
                        <span>Tiêu đề báo giá</span>
                        <input v-model="quoteForm.documentTitle" type="text" placeholder="BÁO GIÁ BƠM NHIỆT ARISTON" />
                    </label>
                    <label class="kb-quote-field">
                        <span>Kính gửi</span>
                        <input
                            v-model="quoteForm.recipientCompany"
                            type="text"
                            placeholder="QUÝ ĐỐI TÁC CÔNG TY CỔ PHẦN..."
                        />
                    </label>
                    <label class="kb-quote-field">
                        <span>Người nhận</span>
                        <input v-model="quoteForm.recipientPerson" type="text" placeholder="VD: Ms.Vân Chi" />
                    </label>
                    <label class="kb-quote-field">
                        <span>Thông tin liên lạc (SĐT)</span>
                        <input v-model="quoteForm.contactPhone" type="text" placeholder="VD: 0963.015.966" />
                    </label>
                    <label class="kb-quote-field">
                        <span>Project (Dự án)</span>
                        <input v-model="quoteForm.projectName" type="text" placeholder="VD: Chung cư cao cấp Vạn Xuân" />
                    </label>
                    <label class="kb-quote-field">
                        <span>Thời gian (Mặc định hôm nay)</span>
                        <input v-model="quoteForm.quoteDate" type="date" />
                    </label>
                    <label class="kb-quote-field">
                        <span>Hiệu lực đến ngày (Hiện tại + 2 tháng)</span>
                        <input v-model="quoteForm.validUntilDate" type="date" />
                    </label>
                    <label class="kb-quote-field">
                        <span>Thuế VAT (%)</span>
                        <input v-model.number="quoteForm.vatPercent" type="number" min="0" max="100" step="1" />
                    </label>
                </div>
            </div>

            <div class="kb-quote-builder-card">
                <div class="kb-quote-items-head">
                    <div>
                        <div class="kb-quote-section-title">
                            2. Chọn sản phẩm Ariston ({{ items.length }} sản phẩm trong báo giá)
                        </div>
                        <small class="kb-quote-section-sub">
                            Tick chọn một hoặc nhiều sản phẩm Ariston bên dưới để đưa vào cùng 1 bảng báo giá, sau đó nhập Giá bán và Số lượng cho từng mặt hàng.
                        </small>
                    </div>
                    <div class="kb-quote-items-actions">
                        <input
                            v-model="productSearchQuery"
                            type="search"
                            class="kb-quote-catalog-search"
                            placeholder="Tìm nhanh model Ariston..."
                        />
                        <button type="button" class="btn" @click="selectAllFilteredProducts">
                            ✓ Chọn tất cả
                        </button>
                        <button v-if="items.length > 1" type="button" class="btn" @click="clearToFirstProduct">
                            Giữ lại 1 dòng
                        </button>
                        <button type="button" class="btn primary" @click="addAristonRow">
                            ＋ Thêm dòng sản phẩm
                        </button>
                        <button type="button" class="btn" @click="addAccessoryRow">
                            ＋ Thêm phụ kiện (Dây cấp...)
                        </button>
                    </div>
                </div>

                <!-- Multi-select Ariston Product Picker Grid -->
                <div class="kb-quote-catalog-grid">
                    <button
                        v-for="prod in filteredAristonCatalog"
                        :key="prod.id"
                        type="button"
                        class="kb-quote-catalog-card"
                        :class="{ selected: isProductSelected(prod.id) }"
                        @click="toggleProductSelection(prod)"
                    >
                        <span class="kb-quote-catalog-check">
                            {{ isProductSelected(prod.id) ? '✓' : '+' }}
                        </span>
                        <img
                            v-if="prod.image?.[0]"
                            :src="resolveImageUrl(prod.image[0])"
                            :alt="prod.name"
                            class="kb-quote-catalog-thumb"
                        />
                        <div class="kb-quote-catalog-meta">
                            <strong>{{ prod.name }}</strong>
                            <small>{{ prod.subcategory?.name || 'Ariston' }}</small>
                        </div>
                    </button>
                </div>

                <div class="kb-quote-items-list">
                    <div v-for="(item, idx) in items" :key="item.uid" class="kb-quote-item-box">
                        <div class="kb-quote-item-compact-row">
                            <span class="kb-quote-item-index">#{{ idx + 1 }}</span>

                            <img
                                v-if="item.imageUrl"
                                :src="item.imageUrl"
                                alt=""
                                class="kb-quote-thumb-preview"
                            />
                            <span v-else class="kb-quote-no-img">SP</span>

                            <label class="kb-quote-field kb-quote-compact-select">
                                <span>Sản phẩm Ariston / Model</span>
                                <select
                                    :value="item.productId ?? ''"
                                    @change="onSelectProduct(item, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="">-- Tự nhập / Phụ kiện ngoài --</option>
                                    <option
                                        v-for="prod in props.aristonProducts"
                                        :key="prod.id"
                                        :value="prod.id"
                                    >
                                        {{ prod.name }} — {{ prod.subcategory?.name || 'Ariston' }}
                                    </option>
                                </select>
                            </label>

                            <label class="kb-quote-field">
                                <span>Tên hiển thị (Cột 1)</span>
                                <input v-model="item.productLabel" type="text" placeholder="Máy nước nóng gián tiếp..." />
                            </label>

                            <label class="kb-quote-field">
                                <span>Giá bán (VNĐ)</span>
                                <input v-model.number="item.unitPrice" type="number" min="0" step="1000" />
                            </label>

                            <label class="kb-quote-field">
                                <span>Số lượng (chiếc)</span>
                                <input v-model.number="item.quantity" type="number" min="1" step="1" />
                            </label>

                            <div class="kb-quote-field">
                                <span>Thành tiền</span>
                                <div class="kb-quote-computed-amount">{{ formatVnd(rowAmount(item)) }} ₫</div>
                            </div>

                            <div class="kb-quote-compact-btns">
                                <button
                                    type="button"
                                    class="mini"
                                    @click="toggleExpandRow(item.uid)"
                                >
                                    {{ expandedUid === item.uid ? 'Thu gọn' : 'Sửa chi tiết' }}
                                </button>
                                <button
                                    v-if="items.length > 1"
                                    type="button"
                                    class="mini danger"
                                    @click="removeRow(idx)"
                                >
                                    Xóa
                                </button>
                            </div>
                        </div>

                        <div v-if="expandedUid === item.uid" class="kb-quote-item-grid kb-quote-item-expanded">
                            <label class="kb-quote-field">
                                <span>Mã sản phẩm (Cột 2 - Model)</span>
                                <input v-model="item.modelCode" type="text" placeholder="SLIM3 20 B VN" />
                            </label>

                            <label class="kb-quote-field">
                                <span>Xuất xứ (Cột 3 - Origin)</span>
                                <input v-model="item.origin" type="text" placeholder="Ariston-Ý (Sản xuất VN)" />
                            </label>

                            <label class="kb-quote-field">
                                <span>Đơn vị (Cột 5)</span>
                                <input v-model="item.unit" type="text" placeholder="Máy / Dây / Bộ" />
                            </label>

                            <div class="kb-quote-field">
                                <span>Hình ảnh sản phẩm</span>
                                <div class="kb-quote-img-picker">
                                    <label class="mini kb-quote-upload-btn">
                                        Đổi ảnh
                                        <input type="file" accept="image/*" hidden @change="onUploadCustomImage(item, $event)" />
                                    </label>
                                    <button
                                        v-if="item.imageUrl"
                                        type="button"
                                        class="mini"
                                        @click="item.imageUrl = ''"
                                    >
                                        Bỏ ảnh
                                    </button>
                                </div>
                            </div>

                            <label class="kb-quote-field kb-quote-span-full">
                                <span>Đặc tính kỹ thuật (Specifications)</span>
                                <textarea v-model="item.specifications" rows="5"></textarea>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Preview Matching Exact PDF Sample -->
            <div class="kb-quote-preview-wrap">
                <div class="kb-quote-preview-toolbar">
                    <strong>
                        Xem trước bản in ({{
                            isPortraitMode
                                ? `Khổ A4 Đứng — ${items.length} sản phẩm`
                                : `Khổ A4 Ngang — ${items.length} sản phẩm`
                        }})
                    </strong>
                    <button class="btn primary" type="button" @click="printQuotationPdf">
                        🖨️ In / Xuất PDF ({{ isPortraitMode ? 'A4 Đứng' : 'A4 Ngang' }})
                    </button>
                </div>

                <div class="kb-quote-paper" :class="{ 'is-portrait': isPortraitMode }">
                    <header class="qp-header">
                        <div class="qp-logo-left">
                            <img :src="starLightLogoUrl" alt="STAR LIGHT" class="qp-starlight-logo" />
                        </div>
                        <div class="qp-company-center">
                            <h1>CÔNG TY CỔ PHẦN QUỐC TẾ STAR LIGHT</h1>
                            <p>Địa chỉ VP: Số 15 Nguyễn Cảnh Dị, Định Công, TP Hà Nội</p>
                            <p>Điện thoại: 0243 201 8322 - Hotline: 0979 541 345</p>
                            <p>www: starlightgroup.vn</p>
                        </div>
                        <div class="qp-logo-right">
                            <img :src="aristonLogoUrl" alt="ARISTON" class="qp-ariston-logo" />
                        </div>
                    </header>

                    <div class="qp-title">{{ quoteForm.documentTitle }}</div>

                    <div class="qp-meta">
                        <div class="qp-meta-row">
                            <span class="qp-meta-label-kinhgui">Kính gửi:</span>
                            <span class="qp-meta-val-kinhgui">{{ quoteForm.recipientCompany }}</span>
                        </div>
                        <div class="qp-meta-row">
                            <span class="qp-meta-label-italic">Người nhận :</span>
                            <span class="qp-meta-val-italic">{{ quoteForm.recipientPerson }}</span>
                        </div>
                        <div v-if="quoteForm.contactPhone.trim()" class="qp-meta-row">
                            <span class="qp-meta-label-italic">Thông tin liên lạc:</span>
                            <span class="qp-meta-val-italic">{{ quoteForm.contactPhone }}</span>
                        </div>
                        <div class="qp-meta-row">
                            <span class="qp-meta-label-italic">Thời gian :</span>
                            <span class="qp-meta-val-italic">{{ formattedQuoteDate }}</span>
                        </div>
                        <div class="qp-meta-row">
                            <span class="qp-meta-label-project">Project:</span>
                            <span class="qp-meta-val-italic">{{ quoteForm.projectName }}</span>
                        </div>
                    </div>

                    <div class="qp-intro">
                        Công ty Star Light là đơn vị phát triển mảng dự án sản phẩm Ariston, rất hân hạnh gửi bảng báo giá cho Quý Khách hàng. Cụ thể như sau
                    </div>

                    <table class="qp-table">
                        <thead>
                            <tr>
                                <th style="width: 9.5%">Sản Phẩm<em>Product</em></th>
                                <th style="width: 8.5%">Mã sản phẩm<em>Model</em></th>
                                <th style="width: 5.5%">Xuất xứ<em>Origin</em></th>
                                <th style="width: 18.5%">Hình ảnh sản phẩm<em>Image</em></th>
                                <th style="width: 4.5%">Đơn vị</th>
                                <th style="width: 29.0%">Đặc tính kỹ thuật<em>Specifications</em></th>
                                <th style="width: 8.5%">Giá bán dự án<em>Selling Price</em><em>(VNĐ)</em></th>
                                <th style="width: 6.5%">Số lượng Quantity<br>(chiếc)</th>
                                <th style="width: 9.5%">Thành tiền<em>Amount</em><em>(VNĐ)</em></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.uid">
                                <td class="qp-center qp-bold qp-preline">{{ item.productLabel }}</td>
                                <td class="qp-center qp-bold qp-preline">{{ item.modelCode }}</td>
                                <td class="qp-center qp-bold qp-preline">{{ item.origin }}</td>
                                <td class="qp-center">
                                    <img
                                        v-if="item.imageUrl"
                                        :src="item.imageUrl"
                                        :alt="item.modelCode || item.productLabel"
                                        class="qp-prod-img"
                                    />
                                </td>
                                <td class="qp-center qp-bold">{{ item.unit }}</td>
                                <td class="qp-spec qp-preline">{{ item.specifications }}</td>
                                <td class="qp-center qp-bold">{{ formatVnd(item.unitPrice) }}</td>
                                <td class="qp-center qp-bold">{{ formatVnd(item.quantity) }}</td>
                                <td class="qp-center qp-bold">{{ formatVnd(rowAmount(item)) }}</td>
                            </tr>
                            <tr class="qp-summary-row">
                                <td colspan="7" class="qp-summary-label">
                                    TỔNG CỘNG (chưa bao gồm thuế VAT) - <em>Sub Total (not included VAT)</em>
                                </td>
                                <td></td>
                                <td class="qp-center">{{ formatVnd(subTotal) }}</td>
                            </tr>
                            <tr class="qp-summary-row">
                                <td colspan="7" class="qp-summary-label">
                                    Thuế VAT ({{ formattedVatLabel }})
                                </td>
                                <td></td>
                                <td class="qp-center">{{ formatVnd(vatAmount) }}</td>
                            </tr>
                            <tr class="qp-summary-row">
                                <td colspan="7" class="qp-summary-label">
                                    TỔNG CỘNG (bao gồm thuế VAT) - <em>Sub Total (included VAT)</em>
                                </td>
                                <td></td>
                                <td class="qp-center">{{ formatVnd(grandTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="qp-terms">
                        <div class="qp-terms-head">Điều kiện thương mại:</div>
                        <p>1. Báo giá có hiệu lực đến ngày: {{ formattedValidUntil }}</p>
                        <p>2. Phương thức thanh toán: Thỏa Thuận</p>
                        <p>3. Phương thức giao hàng: đến chân công trình</p>
                        <p>4. Thời gian giao hàng: 40 ngày đối với hàng trong nước, 90 ngày đối với hàng nhập khẩu kể từ ngày đơn đặt hàng được xác nhận</p>
                        <p>5. Bảo hành: 2 năm linh kiện điện tử, 3 năm với máy nén Theo chính sách bảo hành của Ariston Việt Nam</p>
                        <p>6. Chúng tôi hy vọng báo giá này sẽ làm hài lòng Quý Công ty. Mọi thắc mắc xin vui lòng liên lạc với chúng tôi</p>
                        <p>Rất mong nhận được sự ủng hộ từ Quý Công ty.</p>
                    </div>

                    <div class="qp-sign">
                        <p>Many thanks &amp; Best regards</p>
                        <p>Nguyễn Tuấn Kiệt</p>
                        <p>HP: 0979 807 234</p>
                        <p>Email : tuankiet@starlightgroup.vn</p>
                    </div>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="kb-quote-builder-card">
                <div class="kb-quote-section-title">Mẫu báo giá số 02</div>
                <p style="margin: 0; color: var(--muted);">
                    Bạn hãy gửi tiếp file mẫu PDF của loại báo giá thứ 2 để hệ thống thiết lập form nhập liệu và biểu mẫu in giống hệt 100% như mẫu Bình nước nóng Ariston.
                </p>
            </div>
        </template>
    </AdminLayout>
</template>
