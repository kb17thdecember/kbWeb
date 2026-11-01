<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserLayout from '../../layout/UserLayout.Vue';

type AboutStats = {
    products?: number;
    categories?: number;
    projects?: number;
    catalogues?: number;
};

type SectionKey = 'story' | 'capabilities' | 'brands' | 'standards' | 'milestones' | 'faq';

const props = defineProps<{
    stats?: AboutStats;
}>();

const activeSection = ref<SectionKey>('story');
const activePhilosophy = ref<'vision' | 'mission' | 'values'>('vision');
const activeCapability = ref<'kflex' | 'ariston' | 'hvac' | 'project'>('kflex');
const openFaqIndex = ref<number | null>(0);

const quickNavItems: Array<{ id: SectionKey; index: string; label: string }> = [
    { id: 'story', index: '01', label: 'Hồ sơ & Định hướng' },
    { id: 'capabilities', index: '02', label: 'Năng lực cung ứng' },
    { id: 'brands', index: '03', label: 'Thương hiệu chiến lược' },
    { id: 'standards', index: '04', label: 'Tiêu chuẩn & Hồ sơ CO/CQ' },
    { id: 'milestones', index: '05', label: 'Lộ trình & Mô hình hợp tác' },
    { id: 'faq', index: '06', label: 'Hỏi đáp & Liên hệ' },
];

const currentTabIndex = computed(() =>
    quickNavItems.findIndex((item) => item.id === activeSection.value),
);

const prevTab = computed(() =>
    currentTabIndex.value > 0 ? quickNavItems[currentTabIndex.value - 1] : null,
);

const nextTab = computed(() =>
    currentTabIndex.value < quickNavItems.length - 1
        ? quickNavItems[currentTabIndex.value + 1]
        : null,
);

const selectSection = (id: SectionKey): void => {
    activeSection.value = id;
};

const metrics = computed(() => [
    {
        value: `${Math.max(props.stats?.products ?? 0, 28)}+`,
        label: 'Dòng sản phẩm & quy cách',
        detail: 'Bảo ôn K-FLEX, bình nước nóng Ariston & phụ kiện cơ điện MEP đồng bộ.',
    },
    {
        value: `${Math.max(props.stats?.projects ?? 0, 35)}+`,
        label: 'Dự án & công trình đồng hành',
        detail: 'Chung cư cao tầng, tổ hợp văn phòng – khách sạn và nhà máy công nghiệp.',
    },
    {
        value: '100%',
        label: 'Minh bạch hồ sơ kỹ thuật',
        detail: 'Đầy đủ Catalogue, TDS, SDS, chứng nhận xuất xứ (CO) và chất lượng (CQ).',
    },
    {
        value: '24/7',
        label: 'Phối hợp theo tiến độ dự án',
        detail: 'Hỗ trợ bóc tách khối lượng BOQ, trình mẫu vật tư và giao hàng theo giai đoạn.',
    },
]);

const philosophyTabs = {
    vision: {
        badge: '01 · STRATEGIC VISION',
        title: 'Trở thành chuẩn mực cung ứng vật tư kỹ thuật HVAC & MEP minh bạch tại Việt Nam',
        summary:
            'HVAC Mart định vị là cầu nối kỹ thuật đáng tin cậy giữa các thương hiệu vật tư hàng đầu thế giới và đội ngũ chủ đầu tư, tư vấn thiết kế, tổng thầu cơ điện tại Việt Nam.',
        points: [
            'Chuẩn hóa danh mục vật tư cách nhiệt, cấp nước nóng và phụ kiện HVAC theo tiêu chuẩn quốc tế.',
            'Xây dựng hệ sinh thái tra cứu thông số kỹ thuật, catalogue và hồ sơ trình duyệt nhanh chóng.',
            'Đồng hành dài hạn cùng sự phát triển bền vững và tiết kiệm năng lượng của công trình hiện đại.',
        ],
    },
    mission: {
        badge: '02 · CORE MISSION',
        title: 'Cung cấp đúng giải pháp kỹ thuật, đủ hồ sơ pháp lý và kịp tiến độ thi công',
        summary:
            'Chúng tôi không chỉ phân phối sản phẩm đơn thuần mà giải quyết trọn vẹn bài toán cung ứng vật tư từ giai đoạn thiết kế cơ sở, lập dự toán BOQ đến nghiệm thu bàn giao.',
        points: [
            'Tư vấn lựa chọn đúng chủng loại, độ dày bảo ôn và công suất thiết bị sát với điều kiện vận hành.',
            'Đảm bảo tính đồng bộ giữa hàng hóa thực tế giao tại công trường và hồ sơ đệ trình (Material Submittal).',
            'Tối ưu chi phí đầu tư và giảm thiểu rủi ro phát sinh nhờ kế hoạch giao hàng linh hoạt theo từng đợt.',
        ],
    },
    values: {
        badge: '03 · CORE VALUES',
        title: 'Chính xác kỹ thuật — Minh bạch nguồn gốc — Trách nhiệm đến cùng',
        summary:
            'Mỗi lô hàng xuất kho đều gắn liền với cam kết về xuất xứ chính hãng, thông số kiểm định rõ ràng và sự đồng hành xuyên suốt của đội ngũ chuyên viên.',
        points: [
            'Chính trực trong báo giá, quy cách sản phẩm và chứng từ xuất xứ CO/CQ.',
            'Chủ động phối hợp xử lý các yêu cầu phát sinh tại công trường của nhà thầu MEP.',
            'Lấy hiệu quả vận hành lâu dài và độ an toàn của công trình làm thước đo chất lượng dịch vụ.',
        ],
    },
};

const currentPhilosophy = computed(() => philosophyTabs[activePhilosophy.value]);

const capabilityItems = [
    {
        key: 'kflex' as const,
        code: '01 / THERMAL INSULATION',
        name: 'Giải pháp Bảo ôn & Cách nhiệt K-FLEX',
        tagline: 'Kiểm soát ngưng tụ, giảm tổn thất nhiệt và đáp ứng tiêu chuẩn an toàn cháy khắt khe.',
        description:
            'Cung ứng trọn bộ vật liệu bảo ôn cao su lưu hóa NBR và Polyolefin XLPE dạng ống, dạng tấm cùng hệ phụ kiện keo dán, băng cuốn đồng bộ cho hệ thống Chiller, VRV/VRF và đường ống gió.',
        specs: [
            { label: 'Dòng chủ lực', value: 'K-Flex ST, K-Flex M-Flex, K-Flex K-Protect' },
            { label: 'Dải nhiệt độ làm việc', value: '-200°C đến +116°C (tùy dòng sản phẩm)' },
            { label: 'Hệ số kháng ẩm (μ)', value: '> 10.000 – 20.000 (ASTM C209 / EN 12086)' },
            { label: 'Chứng chỉ chống cháy', value: 'FM Approved, UL94 V-0, BS 476 Class 0, ASTM E84' },
        ],
        applications: [
            'Bọc cách nhiệt chống đọng sương cho hệ thống đường ống nước lạnh Chiller và điều hòa trung tâm.',
            'Cách nhiệt – tiêu âm hệ thống ống gió cấp/hồi trong tòa nhà thương mại, khách sạn 5 sao.',
            'Đường ống công nghệ, phòng sạch, nhà máy điện tử và khu công nghiệp.',
        ],
        ctaText: 'Xem sản phẩm Bảo ôn K-FLEX',
        ctaHref: '/products',
    },
    {
        key: 'ariston' as const,
        code: '02 / WATER HEATING SYSTEMS',
        name: 'Giải pháp Nước nóng Dân dụng & Thương mại Ariston',
        tagline: 'An toàn điện đa cấp, tiết kiệm điện năng và bền bỉ cho căn hộ, khách sạn, dự án.',
        description:
            'Phân phối đa dạng các dòng bình nước nóng gián tiếp, trực tiếp và máy nước nóng bơm nhiệt (Heat Pump) thương hiệu Ariston — đáp ứng từ nhu cầu hộ gia đình đến các dự án chung cư cao tầng, biệt thự, resort.',
        specs: [
            { label: 'Dòng chủ lực', value: 'Ariston Slim3, AN3, Pro R, Heat Pump' },
            { label: 'Dải dung tích', value: '15L – 30L (căn hộ) & 50L – 300L+ (biệt thự, thương mại)' },
            { label: 'Công nghệ an toàn', value: 'Chống giật ELCB, vi mạch kiểm soát an toàn 3D, rơ-le nhiệt kép' },
            { label: 'Độ bền bình chứa', value: 'Tráng men Titan / Thanh đốt đồng & hợp kim Incoloy' },
        ],
        applications: [
            'Căn hộ chung cư cao tầng, nhà phố, biệt thự cao cấp cần thiết kế gọn đẹp.',
            'Khách sạn, khu nghỉ dưỡng, bệnh viện yêu cầu lưu lượng nước nóng ổn định và tiết kiệm điện.',
            'Các gói thầu cung cấp thiết bị vệ sinh – phòng tắm đồng bộ theo tiêu chuẩn chủ đầu tư.',
        ],
        ctaText: 'Xem thiết bị Nước nóng Ariston',
        ctaHref: '/products',
    },
    {
        key: 'hvac' as const,
        code: '03 / HEAT PUMP, SOLAR & INSULATION ACCESSORIES',
        name: 'Bơm nhiệt Heat Pump, Thái dương năng & Phụ kiện Bảo ôn',
        tagline: 'Giải pháp nước nóng trung tâm công suất lớn và phụ kiện thi công bảo ôn đồng bộ.',
        description:
            'Cung ứng các dòng máy nước nóng bơm nhiệt (Heat Pump) thương mại – công nghiệp, hệ thống nước nóng năng lượng mặt trời Thái dương năng cùng trọn bộ phụ kiện bảo ôn K-FLEX chính hãng phục vụ thi công lắp đặt.',
        specs: [
            { label: 'Phụ kiện bảo ôn', value: 'Keo chuyên dụng K-Flex, băng dính bảo ôn, băng cuốn PVC, gối đỡ PU' },
            { label: 'Hệ thống nước nóng lớn', value: 'Máy bơm nhiệt Heat Pump Ariston AR-STP / AR-PTP HX & Thái dương năng' },
            { label: 'Kiểm soát chất lượng', value: 'Đóng gói nguyên đai kiện, đầy đủ tem nhãn và mã lô sản xuất' },
            { label: 'Hình thức cung ứng', value: 'Cung cấp trọn gói theo BOQ hoặc bổ sung nhanh cho công trường' },
        ],
        applications: [
            'Hệ thống cấp nước nóng trung tâm lưu lượng lớn cho khách sạn, resort, bệnh viện và tòa nhà thương mại.',
            'Xử lý kín khít các mối nối bảo ôn, vị trí van, mặt bích và điểm treo đỡ chống cầu nhiệt.',
            'Đáp ứng nhanh nhu cầu vật tư và phụ kiện bảo ôn phát sinh trong giai đoạn hoàn thiện công trình.',
        ],
        ctaText: 'Khám phá danh mục vật tư',
        ctaHref: '/categories',
    },
    {
        key: 'project' as const,
        code: '04 / PROJECT ENGINEERING SUPPORT',
        name: 'Tư vấn Kỹ thuật, Hồ sơ Đệ trình & Cung ứng Dự án',
        tagline: 'Đồng hành cùng phòng Vật tư – Dự toán và Ban chỉ huy công trường từ lúc chào thầu đến nghiệm thu.',
        description:
            'Đội ngũ chuyên viên của HVAC Mart am hiểu quy trình phê duyệt vật tư (Material Approval) của các dự án lớn, hỗ trợ nhà thầu chuẩn bị bộ hồ sơ kỹ thuật hoàn chỉnh ngay từ bước đầu tiên.',
        specs: [
            { label: 'Hỗ trợ giai đoạn thầu', value: 'Bóc tách BOQ, đối chiếu spec thiết kế & đề xuất mã hàng tương đương' },
            { label: 'Hồ sơ trình duyệt', value: 'Catalogue, Technical Data Sheet (TDS), Test Report, mẫu vật tư thực tế' },
            { label: 'Hồ sơ nghiệm thu', value: 'Chứng nhận xuất xứ (CO), chất lượng (CQ), biên bản giao nhận & bảo hành' },
            { label: 'Điều phối giao hàng', value: 'Chia đợt giao theo tiến độ thi công từng tầng / từng phân khu' },
        ],
        applications: [
            'Hỗ trợ nhà thầu MEP lập phương án chào giá cạnh tranh với phương án vật tư tối ưu.',
            'Làm việc cùng tư vấn giám sát và chủ đầu tư để làm rõ thông số kỹ thuật, tiêu chuẩn chống cháy.',
            'Quản lý tiến độ giao hàng chuẩn xác, giảm tải diện tích lưu kho tại công trường.',
        ],
        ctaText: 'Gửi BOQ nhận báo giá dự án',
        ctaHref: '/contacts',
    },
];

const currentCapability = computed(
    () => capabilityItems.find((item) => item.key === activeCapability.value) ?? capabilityItems[0],
);

const brandPartners = [
    {
        badge: 'ITALIAN THERMAL INSULATION LEADER',
        name: 'K-FLEX',
        subtitle: 'Tập đoàn vật liệu bảo ôn & cách âm hàng đầu thế giới',
        description:
            'Thương hiệu bảo ôn quốc tế được tin dùng tại hàng nghìn dự án cao tầng, nhà máy công nghiệp và tổ hợp thương mại nhờ hệ số dẫn nhiệt thấp, kháng ẩm vượt trội và hệ chứng chỉ chống cháy toàn diện.',
        highlights: [
            'Đầy đủ các dòng NBR & XLPE: K-Flex ST, K-Flex M-Flex, K-Flex K-Protect.',
            'Đạt chuẩn FM Approved, UL94 V-0, BS 476 Part 6 & 7 Class 0, ASTM E84.',
            'Kiểm soát tối ưu hiện tượng đọng sương và thất thoát năng lượng cho hệ Chiller / HVAC.',
        ],
        series: ['K-Flex ST', 'K-Flex M-Flex', 'K-Flex K-Protect', 'Phụ kiện K-Flex'],
        primaryLink: '/products',
        primaryText: 'Dòng sản phẩm K-FLEX',
        secondaryLink: '/news?tab=catalogue',
        secondaryText: 'Tải Catalogue K-FLEX',
    },
    {
        badge: 'GLOBAL WATER HEATING SPECIALIST',
        name: 'ARISTON',
        subtitle: 'Thương hiệu chuyên gia nhiệt & nước nóng hàng đầu từ Ý',
        description:
            'Giải pháp làm nóng nước tiên phong kết hợp thiết kế Ý sang trọng, công nghệ tiết kiệm điện thông minh và hệ thống bảo vệ an toàn đa tầng cho cả hộ gia đình lẫn công trình quy mô lớn.',
        highlights: [
            'Đa dạng phân khúc: Bình nước nóng gián tiếp căn hộ đến bơm nhiệt Heat Pump công trình.',
            'Tích hợp cầu dao chống giật ELCB, rơ-le nhiệt TBSE và lòng bình tráng men Titan bền bỉ.',
            'Chính sách bảo hành chính hãng minh bạch, thuận tiện cho bàn giao căn hộ và vận hành khách sạn.',
        ],
        series: ['Ariston Slim3', 'Ariston AN3', 'Ariston Pro R', 'Ariston Heat Pump'],
        primaryLink: '/products',
        primaryText: 'Thiết bị Ariston',
        secondaryLink: '/news?tab=catalogue',
        secondaryText: 'Tải Catalogue Ariston',
    },
];

const complianceCards = [
    {
        code: 'DOC — 01',
        title: 'Hồ sơ Đệ trình Vật tư (Material Submittal)',
        desc: 'Cung cấp trọn bộ Catalogue chính hãng, bảng thông số kỹ thuật (TDS), bảng dữ liệu an toàn vật liệu (SDS) và mẫu vật tư thực tế (Sample Board) phục vụ trình duyệt với Chủ đầu tư & Tư vấn giám sát.',
        tags: ['Catalogue gốc', 'TDS / SDS', 'Trình mẫu vật tư'],
    },
    {
        code: 'DOC — 02',
        title: 'Chứng chỉ Chống cháy & Kiểm định Kỹ thuật',
        desc: 'Đầy đủ báo cáo thử nghiệm (Test Report) theo đúng dòng sản phẩm về chỉ số lan truyền ngọn lửa (FSI ≤ 25), chỉ số sinh khói (SDI ≤ 30) và hệ số dẫn nhiệt theo tiêu chuẩn quốc tế.',
        tags: ['FM Approved', 'UL94 V-0', 'BS 476 Class 0', 'ASTM E84'],
    },
    {
        code: 'DOC — 03',
        title: 'Chứng từ Xuất xứ & Chất lượng (CO / CQ)',
        desc: 'Mỗi lô hàng giao tới dự án đều kèm theo chứng nhận xuất xứ (CO), chứng nhận chất lượng xuất xưởng (CQ), Packing List và biên bản bàn giao đối chiếu đúng số lô (Batch/Lot) trên bao bì.',
        tags: ['CO / CQ đầy đủ', 'Khớp số Lot/Batch', 'Hồ sơ nghiệm thu'],
    },
    {
        code: 'DOC — 04',
        title: 'Cam kết Bảo hành & Đồng hành Hậu mãi',
        desc: 'Hỗ trợ kích hoạt bảo hành chính hãng theo danh sách căn hộ hoặc hạng mục lắp đặt; tiếp nhận và phối hợp xử lý nhanh mọi yêu cầu kỹ thuật trong suốt quá trình vận hành công trình.',
        tags: ['Bảo hành chính hãng', 'Hỗ trợ kỹ thuật', 'Đồng hành dài hạn'],
    },
];

const milestones = [
    {
        step: 'GIAI ĐOẠN 01',
        title: 'Định hình chuyên môn Vật tư Nhiệt lạnh & Cơ điện',
        desc: 'Tập trung sâu vào hai mảng cốt lõi: vật liệu bảo ôn cách nhiệt K-FLEX và thiết bị nước nóng Ariston, xây dựng nền tảng hiểu biết kỹ thuật vững chắc cho từng dòng sản phẩm.',
    },
    {
        step: 'GIAI ĐOẠN 02',
        title: 'Chuẩn hóa quy trình Cung ứng cho Nhà thầu MEP',
        desc: 'Phát triển quy trình hỗ trợ dự án chuyên nghiệp: từ bóc tách BOQ, đối chiếu thông số thiết kế, chuẩn bị hồ sơ trình duyệt đến điều phối giao hàng theo tiến độ công trường.',
    },
    {
        step: 'GIAI ĐOẠN 03',
        title: 'Mở rộng mạng lưới Dự án Thương mại & Công nghiệp',
        desc: 'Đồng hành cung ứng vật tư cho hàng loạt công trình chung cư cao tầng, tòa nhà văn phòng – khách sạn, bệnh viện và nhà máy sản xuất tại nhiều tỉnh thành trên khắp cả nước.',
    },
    {
        step: 'GIAI ĐOẠN 04',
        title: 'Số hóa Trải nghiệm Tra cứu Kỹ thuật & Báo giá B2B',
        desc: 'Ra mắt nền tảng HVAC Mart trực tuyến tích hợp công cụ tính chọn kỹ thuật HVAC, thư viện Catalogue PDF, so sánh thông số sản phẩm và quản lý giỏ báo giá dự án nhanh chóng.',
    },
];

const clientTracks = [
    {
        type: 'DÀNH CHO CHỦ ĐẦU TƯ & NHÀ THẦU MEP',
        title: 'Giải pháp Cung ứng Dự án Trọn gói',
        subtitle: 'Tối ưu cho công trình chung cư, khách sạn, bệnh viện, nhà máy công nghiệp.',
        items: [
            'Báo giá dự án theo bảng tiên lượng (BOQ) với chính sách chiết khấu theo sản lượng.',
            'Hỗ trợ trọn bộ hồ sơ trình duyệt vật tư (Submittal), mẫu trình và CO/CQ nghiệm thu.',
            'Linh hoạt giữ hàng và chia nhiều đợt giao theo tiến độ thi công thực tế tại công trường.',
            'Chuyên viên phụ trách riêng theo sát dự án từ lúc chào thầu đến khi quyết toán.',
        ],
        actionText: 'Liên hệ Phòng Dự án',
        actionHref: '/contacts',
    },
    {
        type: 'DÀNH CHO ĐẠI LÝ, KỸ SƯ & CÔNG TRÌNH DÂN DỤNG',
        title: 'Phân phối Linh hoạt & Tư vấn Tận tâm',
        subtitle: 'Phù hợp cho căn hộ, biệt thự, tòa nhà dịch vụ và đội ngũ thi công điện lạnh.',
        items: [
            'Tư vấn chọn đúng dung tích bình nước nóng Ariston và quy cách ống/tấm bảo ôn K-FLEX.',
            'Cung cấp lẻ và sỉ đa dạng kích thước, độ dày với nguồn hàng sẵn kho ổn định.',
            'Công cụ tính chọn công suất trực tuyến giúp ước tính nhanh nhu cầu vật tư trong 1 phút.',
            'Giao hàng nhanh, chính sách bảo hành chính hãng rõ ràng và hỗ trợ kỹ thuật trực tiếp.',
        ],
        actionText: 'Xem Danh mục Sản phẩm',
        actionHref: '/products',
    },
];

const faqs = [
    {
        question: 'HVAC Mart có cung cấp đầy đủ hồ sơ CO/CQ và chứng chỉ chống cháy để nghiệm thu dự án không?',
        answer:
            'Có. Đối với mọi đơn hàng dự án, HVAC Mart cung cấp đầy đủ bộ hồ sơ gồm: Catalogue kỹ thuật (TDS), tài liệu an toàn vật liệu (SDS), các báo cáo thử nghiệm chống cháy (FM Approved, UL94 V-0, BS 476 Part 6 & 7 Class 0, ASTM E84 tùy dòng K-FLEX), cùng chứng nhận xuất xứ (CO) và chứng nhận chất lượng (CQ) khớp với lô hàng thực tế bàn giao.',
    },
    {
        question: 'Nhà thầu gửi bảng khối lượng (BOQ) hoặc bản vẽ thiết kế thì bao lâu nhận được báo giá?',
        answer:
            'Ngay khi tiếp nhận BOQ hoặc yêu cầu kỹ thuật qua trang Liên hệ (hỗ trợ đính kèm file Excel, PDF, Word), đội ngũ kỹ thuật của HVAC Mart sẽ rà soát quy cách, đối chiếu tiêu chuẩn thiết kế và phản hồi bảng chào giá chi tiết trong vòng 4 – 24 giờ làm việc.',
    },
    {
        question: 'HVAC Mart có hỗ trợ giao hàng thành nhiều đợt theo tiến độ thi công công trường không?',
        answer:
            'Hoàn toàn hỗ trợ. Chúng tôi hiểu đặc thù mặt bằng kho bãi tại công trường thường hạn chế, do đó HVAC Mart phối hợp cùng Ban chỉ huy công trường lên lịch giao hàng theo từng giai đoạn (theo tầng hoặc theo phân khu), đảm bảo vật tư luôn sẵn sàng đúng thời điểm lắp đặt.',
    },
    {
        question: 'Làm sao để lựa chọn đúng dòng bảo ôn K-FLEX (ST, M-Flex hay K-Protect) cho từng hạng mục?',
        answer:
            'Việc lựa chọn phụ thuộc vào nhiệt độ môi chất, độ ẩm môi trường, vị trí lắp đặt (trong nhà, ngoài trời hay trục kỹ thuật) và yêu cầu an toàn cháy của chủ đầu tư. Đội ngũ HVAC Mart sẽ hỗ trợ đối chiếu thông số kỹ thuật từng dòng và cung cấp bảng tính độ dày bảo ôn chống đọng sương phù hợp.',
    },
    {
        question: 'Chính sách bảo hành đối với bình nước nóng Ariston và vật tư K-FLEX được áp dụng như thế nào?',
        answer:
            'Tất cả sản phẩm do HVAC Mart phân phối đều là hàng chính hãng 100% và được hưởng đầy đủ chế độ bảo hành theo tiêu chuẩn của nhà sản xuất Ariston và K-FLEX. Đối với dự án, chúng tôi hỗ trợ tổng hợp danh mục serial/model và hướng dẫn quy trình kích hoạt, tra cứu bảo hành minh bạch khi bàn giao cho cư dân hoặc ban quản lý tòa nhà.',
    },
];

const toggleFaq = (index: number): void => {
    openFaqIndex.value = openFaqIndex.value === index ? null : index;
};
</script>

<template>
    <div>
        <Head title="HVAC Mart | Về chúng tôi — Hồ sơ năng lực & Giải pháp vật tư MEP" />

        <UserLayout :cart-count="0" active-page="about">
            <main class="kb-about-page">
                <!-- Page Intro -->
                <section class="page-intro" aria-live="polite">
                    <div class="wrap page-intro-inner">
                        <div>
                            <div class="eyebrow"><span class="dot"></span> HVAC MART / HỒ SƠ NĂNG LỰC &amp; ĐỊNH HƯỚNG</div>
                            <h1>Về chúng tôi</h1>
                            <p>
                                Đối tác cung ứng chuyên sâu vật liệu bảo ôn K-FLEX, giải pháp nước nóng Ariston
                                và vật tư cơ điện MEP — chuẩn xác thông số, minh bạch hồ sơ CO/CQ và đồng hành
                                sát sao cùng tiến độ từng công trình.
                            </p>
                        </div>
                        <Link class="page-home-link" href="/">
                            <FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Về trang chủ
                        </Link>
                    </div>
                </section>

                <!-- Interactive Section Switcher Tabs -->
                <nav class="kb-about-subnav-wrap" aria-label="Chuyên mục trang Về chúng tôi">
                    <div class="wrap kb-about-subnav" role="tablist">
                        <button
                            v-for="item in quickNavItems"
                            :key="item.id"
                            type="button"
                            role="tab"
                            class="kb-about-subnav-btn"
                            :class="{ active: activeSection === item.id }"
                            :aria-selected="activeSection === item.id"
                            @click="selectSection(item.id)"
                        >
                            <small>{{ item.index }}</small>
                            <span>{{ item.label }}</span>
                        </button>
                    </div>
                </nav>

                <!-- Tab 01: Hồ sơ & Định hướng -->
                <section
                    v-if="activeSection === 'story'"
                    id="story"
                    class="section kb-about-section kb-about-tab-stage"
                >
                    <div class="wrap">
                        <div class="kb-about-overview-grid">
                            <!-- Left: Story & Vision/Mission/Values Tabs -->
                            <div class="kb-about-story-card">
                                <div class="kb-about-story-head">
                                    <span class="kb-about-kicker">01 · CORPORATE PROFILE · HVAC &amp; MEP SUPPLY</span>
                                    <h2>Giải pháp vật tư chuẩn kỹ thuật cho công trình hiện đại.</h2>
                                    <p>
                                        Trong các dự án xây dựng từ dân dụng đến công nghiệp, chất lượng vật tư bảo ôn
                                        và thiết bị nhiệt lạnh quyết định trực tiếp đến hiệu suất tiêu thụ năng lượng,
                                        độ bền hệ thống và sự an toàn trong suốt vòng đời vận hành của tòa nhà.
                                    </p>
                                    <p>
                                        <strong>HVAC Mart</strong> được xây dựng nhằm mang đến cho chủ đầu tư, đơn vị tư vấn
                                        thiết kế và nhà thầu cơ điện (MEP) một đầu mối cung ứng chuyên nghiệp: nơi mỗi sản phẩm
                                        đều đi kèm thông số kỹ thuật rõ ràng, hồ sơ chứng chỉ minh bạch và dịch vụ điều phối
                                        bám sát thực tế công trường.
                                    </p>
                                </div>

                                <!-- Interactive Philosophy Tabs -->
                                <div class="kb-about-philosophy-box">
                                    <div class="kb-about-philosophy-tabs" role="tablist" aria-label="Định hướng phát triển">
                                        <button
                                            type="button"
                                            role="tab"
                                            class="kb-about-phil-tab"
                                            :class="{ active: activePhilosophy === 'vision' }"
                                            :aria-selected="activePhilosophy === 'vision'"
                                            @click="activePhilosophy = 'vision'"
                                        >
                                            Tầm nhìn
                                        </button>
                                        <button
                                            type="button"
                                            role="tab"
                                            class="kb-about-phil-tab"
                                            :class="{ active: activePhilosophy === 'mission' }"
                                            :aria-selected="activePhilosophy === 'mission'"
                                            @click="activePhilosophy = 'mission'"
                                        >
                                            Sứ mệnh
                                        </button>
                                        <button
                                            type="button"
                                            role="tab"
                                            class="kb-about-phil-tab"
                                            :class="{ active: activePhilosophy === 'values' }"
                                            :aria-selected="activePhilosophy === 'values'"
                                            @click="activePhilosophy = 'values'"
                                        >
                                            Giá trị cốt lõi
                                        </button>
                                    </div>

                                    <div class="kb-about-philosophy-panel" role="tabpanel">
                                        <span class="kb-about-phil-badge">{{ currentPhilosophy.badge }}</span>
                                        <h3>{{ currentPhilosophy.title }}</h3>
                                        <p>{{ currentPhilosophy.summary }}</p>
                                        <ul class="kb-about-phil-list">
                                            <li v-for="(point, idx) in currentPhilosophy.points" :key="idx">
                                                <span class="kb-about-check"><FontAwesomeIcon :icon="['fas', 'check']" /></span>
                                                <span>{{ point }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Architectural Dossier & Key Figures -->
                            <aside class="kb-about-dossier-card" aria-label="Chỉ số năng lực HVAC Mart">
                                <div class="kb-about-dossier-top">
                                    <span class="eyebrow"><span class="dot"></span> NĂNG LỰC THỰC THI</span>
                                    <h3>Con số &amp; Cam kết vận hành</h3>
                                    <p>
                                        Hệ thống danh mục được chuẩn hóa giúp rút ngắn thời gian tra cứu thông số,
                                        trình duyệt vật tư và lập dự toán cho từng gói thầu.
                                    </p>
                                </div>

                                <div class="kb-about-metrics-grid">
                                    <div v-for="(metric, index) in metrics" :key="index" class="kb-about-metric-item">
                                        <strong>{{ metric.value }}</strong>
                                        <span>{{ metric.label }}</span>
                                        <small>{{ metric.detail }}</small>
                                    </div>
                                </div>

                                <div class="kb-about-dossier-Highlight">
                                    <div class="kb-about-dossier-badge">HỒ SƠ NĂNG LỰC &amp; TÀI LIỆU</div>
                                    <p>
                                        Truy cập trực tiếp kho Catalogue PDF chính hãng hoặc tham khảo danh mục các
                                        dự án thực tế mà HVAC Mart đã tham gia cung ứng.
                                    </p>
                                    <div class="kb-about-dossier-actions">
                                        <Link href="/featured-projects" class="btn btn-primary">
                                            Dự án tiêu biểu <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                        </Link>
                                        <Link href="/news?tab=catalogue" class="btn btn-outline">
                                            Thư viện Catalogue PDF
                                        </Link>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>
                </section>

                <!-- Tab 02: Năng lực cung ứng -->
                <section
                    v-else-if="activeSection === 'capabilities'"
                    id="capabilities"
                    class="section kb-about-section kb-about-tab-stage"
                >
                    <div class="wrap">
                        <div class="section-head">
                            <div>
                                <div class="eyebrow"><span class="dot"></span> 02 · HỆ SINH THÁI GIẢI PHÁP</div>
                                <h2 class="section-title">4 trụ cột năng lực<br><em>cung ứng chuyên sâu.</em></h2>
                            </div>
                            <p class="section-desc">
                                Chọn từng lĩnh vực bên dưới để xem chi tiết phạm vi cung ứng, thông số tiêu chuẩn
                                và hạng mục ứng dụng thực tế tại công trình.
                            </p>
                        </div>

                        <div class="kb-about-cap-layout">
                            <!-- Selector List -->
                            <div class="kb-about-cap-nav" role="tablist" aria-label="Trụ cột năng lực cung ứng">
                                <button
                                    v-for="item in capabilityItems"
                                    :key="item.key"
                                    type="button"
                                    role="tab"
                                    class="kb-about-cap-tab"
                                    :class="{ active: activeCapability === item.key }"
                                    :aria-selected="activeCapability === item.key"
                                    @click="activeCapability = item.key"
                                >
                                    <small>{{ item.code }}</small>
                                    <strong>{{ item.name }}</strong>
                                    <span>{{ item.tagline }}</span>
                                </button>
                            </div>

                            <!-- Active Competency Detail Panel -->
                            <div class="kb-about-cap-detail" role="tabpanel">
                                <div class="kb-about-cap-detail-head">
                                    <span class="kb-about-cap-code">{{ currentCapability.code }}</span>
                                    <h3>{{ currentCapability.name }}</h3>
                                    <p>{{ currentCapability.description }}</p>
                                </div>

                                <div class="kb-about-cap-specs">
                                    <div
                                        v-for="(spec, sIdx) in currentCapability.specs"
                                        :key="sIdx"
                                        class="kb-about-cap-spec-box"
                                    >
                                        <span>{{ spec.label }}</span>
                                        <strong>{{ spec.value }}</strong>
                                    </div>
                                </div>

                                <div class="kb-about-cap-apps">
                                    <h4>Phạm vi ứng dụng trọng điểm tại dự án</h4>
                                    <ul>
                                        <li v-for="(app, aIdx) in currentCapability.applications" :key="aIdx">
                                            <span class="kb-about-check"><FontAwesomeIcon :icon="['fas', 'check']" /></span>
                                            <span>{{ app }}</span>
                                        </li>
                                    </ul>
                                </div>

                                <div class="kb-about-cap-foot">
                                    <Link :href="currentCapability.ctaHref" class="btn btn-primary">
                                        {{ currentCapability.ctaText }}
                                        <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                    </Link>
                                    <Link href="/contacts" class="btn btn-outline">
                                        Yêu cầu tư vấn kỹ thuật
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tab 03: Thương hiệu chiến lược -->
                <section
                    v-else-if="activeSection === 'brands'"
                    id="brands"
                    class="section kb-about-section kb-about-tab-stage"
                >
                    <div class="wrap">
                        <div class="section-head">
                            <div>
                                <div class="eyebrow"><span class="dot"></span> 03 · ĐỐI TÁC THƯƠNG HIỆU</div>
                                <h2 class="section-title">Thương hiệu quốc tế,<br><em>nguồn gốc chính hãng.</em></h2>
                            </div>
                            <p class="section-desc">
                                Tập trung phân phối các dòng sản phẩm có tiêu chuẩn kỹ thuật rõ ràng, được công nhận
                                rộng rãi trong hồ sơ chỉ dẫn kỹ thuật (Specification) của các dự án lớn.
                            </p>
                        </div>

                        <div class="kb-about-brands-grid">
                            <article
                                v-for="brand in brandPartners"
                                :key="brand.name"
                                class="kb-about-brand-card"
                            >
                                <div class="kb-about-brand-top">
                                    <span class="kb-about-brand-badge">{{ brand.badge }}</span>
                                    <h3>{{ brand.name }}</h3>
                                    <p class="kb-about-brand-sub">{{ brand.subtitle }}</p>
                                </div>

                                <p class="kb-about-brand-desc">{{ brand.description }}</p>

                                <div class="kb-about-brand-series">
                                    <span class="kb-about-series-label">DÒNG SẢN PHẨM TIÊU BIỂU</span>
                                    <div class="kb-about-series-chips">
                                        <span v-for="chip in brand.series" :key="chip" class="kb-about-series-chip">
                                            {{ chip }}
                                        </span>
                                    </div>
                                </div>

                                <ul class="kb-about-brand-points">
                                    <li v-for="(pt, pIdx) in brand.highlights" :key="pIdx">
                                        <span class="kb-about-check"><FontAwesomeIcon :icon="['fas', 'check']" /></span>
                                        <span>{{ pt }}</span>
                                    </li>
                                </ul>

                                <div class="kb-about-brand-actions">
                                    <Link :href="brand.primaryLink" class="btn btn-primary">
                                        {{ brand.primaryText }}
                                    </Link>
                                    <Link :href="brand.secondaryLink" class="btn btn-outline">
                                        {{ brand.secondaryText }}
                                    </Link>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <!-- Tab 04: Tiêu chuẩn & Hồ sơ CO/CQ -->
                <section
                    v-else-if="activeSection === 'standards'"
                    id="standards"
                    class="section kb-about-section kb-about-dark-band kb-about-tab-stage"
                >
                    <div class="wrap">
                        <div class="section-head kb-about-dark-head">
                            <div>
                                <div class="eyebrow"><span class="dot"></span> 04 · TIÊU CHUẨN &amp; PHÁP LÝ VẬT TƯ</div>
                                <h2 class="section-title">Chuẩn chỉnh hồ sơ từ<br><em>đệ trình đến nghiệm thu.</em></h2>
                            </div>
                            <p class="section-desc">
                                Đối với các dự án cơ điện MEP, hồ sơ đi kèm quan trọng không kém bản thân vật tư.
                                HVAC Mart chuẩn bị đồng bộ tài liệu cho từng giai đoạn kiểm tra của dự án.
                            </p>
                        </div>

                        <div class="kb-about-compliance-grid">
                            <article
                                v-for="card in complianceCards"
                                :key="card.code"
                                class="kb-about-compliance-card"
                            >
                                <span class="kb-about-compliance-code">{{ card.code }}</span>
                                <h3>{{ card.title }}</h3>
                                <p>{{ card.desc }}</p>
                                <div class="kb-about-compliance-tags">
                                    <span v-for="tag in card.tags" :key="tag">{{ tag }}</span>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <!-- Tab 05: Lộ trình & Mô hình hợp tác -->
                <section
                    v-else-if="activeSection === 'milestones'"
                    id="milestones"
                    class="section kb-about-section kb-about-tab-stage"
                >
                    <div class="wrap">
                        <div class="section-head">
                            <div>
                                <div class="eyebrow"><span class="dot"></span> 05 · LỘ TRÌNH &amp; MÔ HÌNH HỢP TÁC</div>
                                <h2 class="section-title">Phát triển bền vững,<br><em>phục vụ chuyên biệt.</em></h2>
                            </div>
                            <p class="section-desc">
                                Kết hợp kinh nghiệm thực chiến tại các dự án lớn với nền tảng tra cứu số hóa
                                nhằm phục vụ tối ưu cho cả nhà thầu dự án lẫn khách hàng dân dụng.
                            </p>
                        </div>

                        <!-- Timeline -->
                        <div class="kb-about-timeline">
                            <div
                                v-for="(m, mIdx) in milestones"
                                :key="mIdx"
                                class="kb-about-timeline-item"
                            >
                                <div class="kb-about-timeline-marker">
                                    <span>{{ m.step }}</span>
                                </div>
                                <div class="kb-about-timeline-body">
                                    <h3>{{ m.title }}</h3>
                                    <p>{{ m.desc }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Dual-Track Partnership Cards -->
                        <div class="kb-about-tracks-grid">
                            <article
                                v-for="(track, tIdx) in clientTracks"
                                :key="tIdx"
                                class="kb-about-track-card"
                            >
                                <span class="kb-about-track-type">{{ track.type }}</span>
                                <h3>{{ track.title }}</h3>
                                <p class="kb-about-track-sub">{{ track.subtitle }}</p>
                                <ul>
                                    <li v-for="(item, iIdx) in track.items" :key="iIdx">
                                        <span class="kb-about-check"><FontAwesomeIcon :icon="['fas', 'check']" /></span>
                                        <span>{{ item }}</span>
                                    </li>
                                </ul>
                                <Link :href="track.actionHref" class="btn btn-primary">
                                    {{ track.actionText }}
                                    <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                </Link>
                            </article>
                        </div>
                    </div>
                </section>

                <!-- Tab 06: Câu hỏi thường gặp & Liên hệ -->
                <section
                    v-else
                    id="faq"
                    class="section kb-about-section kb-about-tab-stage"
                >
                    <div class="wrap">
                        <div class="kb-about-faq-layout">
                            <div class="kb-about-faq-intro">
                                <div class="eyebrow"><span class="dot"></span> 06 · GIẢI ĐÁP CHUYÊN MÔN</div>
                                <h2 class="section-title">Câu hỏi thường gặp<br><em>khi hợp tác cung ứng.</em></h2>
                                <p>
                                    Tổng hợp các thông tin về hồ sơ nghiệm thu CO/CQ, quy trình báo giá BOQ,
                                    điều phối giao hàng công trình và chính sách hậu mãi tại HVAC Mart.
                                </p>
                                <div class="kb-about-faq-contact-box">
                                    <strong>Cần trao đổi trực tiếp về gói thầu?</strong>
                                    <span>Đội ngũ kỹ thuật luôn sẵn sàng hỗ trợ bóc tách khối lượng và gửi hồ sơ mẫu.</span>
                                    <Link href="/contacts" class="btn btn-primary">
                                        Gửi yêu cầu ngay <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                    </Link>
                                </div>
                            </div>

                            <div class="kb-about-faq-list">
                                <article
                                    v-for="(faq, fIdx) in faqs"
                                    :key="fIdx"
                                    class="kb-about-faq-item"
                                    :class="{ open: openFaqIndex === fIdx }"
                                >
                                    <button
                                        type="button"
                                        class="kb-about-faq-question"
                                        :aria-expanded="openFaqIndex === fIdx"
                                        @click="toggleFaq(fIdx)"
                                    >
                                        <span>{{ faq.question }}</span>
                                        <b aria-hidden="true">{{ openFaqIndex === fIdx ? '−' : '+' }}</b>
                                    </button>
                                    <div v-show="openFaqIndex === fIdx" class="kb-about-faq-answer">
                                        <p>{{ faq.answer }}</p>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <div class="kb-about-cta-banner kb-about-cta-in-tab">
                            <div class="kb-about-cta-copy">
                                <div class="eyebrow"><span class="dot"></span> KẾT NỐI CÙNG HVAC MART</div>
                                <h2>Sẵn sàng đồng hành cùng<br><em>dự án tiếp theo của bạn.</em></h2>
                                <p>
                                    Gửi danh mục vật tư (BOQ) hoặc liên hệ trực tiếp để nhận tư vấn kỹ thuật,
                                    bộ hồ sơ trình duyệt mẫu và báo giá tối ưu theo tiến độ công trình.
                                </p>
                                <div class="kb-about-cta-buttons">
                                    <Link href="/contacts" class="btn btn-primary">
                                        Gửi yêu cầu báo giá / BOQ <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                    </Link>
                                    <Link href="/products" class="btn btn-outline">
                                        Khám phá danh mục sản phẩm
                                    </Link>
                                </div>
                            </div>

                            <div class="kb-about-cta-info">
                                <div class="kb-about-info-row">
                                    <span>ĐỊA CHỈ VĂN PHÒNG</span>
                                    <strong>Số 15 đường Nguyễn Cản Dị, phường Định Công, TP. Hà Nội</strong>
                                </div>
                                <div class="kb-about-info-row">
                                    <span>HOTLINE TƯ VẤN DỰ ÁN &amp; BÁO GIÁ</span>
                                    <strong><a href="tel:0979807234">0979 807 234</a></strong>
                                </div>
                                <div class="kb-about-info-row">
                                    <span>EMAIL TIẾP NHẬN HỒ SƠ &amp; BOQ</span>
                                    <strong><a href="mailto:kbnguyen0311@gmail.com">kbnguyen0311@gmail.com</a></strong>
                                </div>
                                <div class="kb-about-info-row">
                                    <span>THỜI GIAN LÀM VIỆC</span>
                                    <strong>Thứ 2 – Thứ 7 · 08:00 – 17:00 (Hỗ trợ điều phối giao hàng theo lịch công trường)</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Bottom Tab Step Switcher Bar -->
                <div class="wrap kb-about-tab-pager">
                    <button
                        v-if="prevTab"
                        type="button"
                        class="kb-about-pager-btn is-prev"
                        @click="selectSection(prevTab.id)"
                    >
                        <small>← MỤC TRƯỚC ({{ prevTab.index }})</small>
                        <strong>{{ prevTab.label }}</strong>
                    </button>
                    <div v-else class="kb-about-pager-spacer"></div>

                    <div class="kb-about-pager-dots" aria-hidden="true">
                        <span
                            v-for="item in quickNavItems"
                            :key="item.id"
                            class="kb-about-pager-dot"
                            :class="{ active: activeSection === item.id }"
                        ></span>
                    </div>

                    <button
                        v-if="nextTab"
                        type="button"
                        class="kb-about-pager-btn is-next"
                        @click="selectSection(nextTab.id)"
                    >
                        <small>MỤC TIẾP THEO ({{ nextTab.index }}) →</small>
                        <strong>{{ nextTab.label }}</strong>
                    </button>
                    <Link
                        v-else
                        href="/contacts"
                        class="kb-about-pager-btn is-next is-cta"
                    >
                        <small>BƯỚC TIẾP THEO →</small>
                        <strong>Liên hệ nhận báo giá dự án</strong>
                    </Link>
                </div>
            </main>
        </UserLayout>
    </div>
</template>
