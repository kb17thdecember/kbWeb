<script setup lang="ts">
import UserLayout from '../layout/UserLayout.Vue';

const solutions = [
    {
        number: '01',
        title: 'Thiết bị nước nóng',
        description:
            'Giải pháp nước nóng tiện nghi, phù hợp cho căn hộ, khách sạn và công trình dịch vụ.',
        tag: 'ARISTON',
        symbol: '◒',
    },
    {
        number: '02',
        title: 'Bảo ôn và cách nhiệt',
        description:
            'Vật liệu bảo ôn giúp tối ưu hiệu suất hệ thống và nâng cao độ bền cho công trình.',
        tag: 'KFLEX',
        symbol: '◌',
    },
    {
        number: '03',
        title: 'Giải pháp cơ điện',
        description:
            'Tư vấn lựa chọn thiết bị và giải pháp kỹ thuật theo yêu cầu sử dụng thực tế.',
        tag: 'M&E',
        symbol: '⌁',
    },
];

const projectTypes = [
    'Chung cư thương mại & NOXH',
    'Văn phòng, khách sạn & bệnh viện',
    'Khu công nghiệp',
];
</script>

<template>
    <UserLayout>
        <main>
            <section id="home" class="hero">
                <div class="hero-inner">
                    <div class="hero-copy">
                        <span class="eyebrow"><span></span> GIẢI PHÁP KỸ THUẬT CÔNG TRÌNH</span>
                        <h1>
                            Nền tảng vững chắc
                            <span>cho mọi công trình.</span>
                        </h1>
                        <p>
                            KB TECH cung cấp thiết bị và giải pháp kỹ thuật đáng tin cậy,
                            đồng hành cùng chủ đầu tư và nhà thầu từ lựa chọn sản phẩm đến
                            triển khai thực tế.
                        </p>
                        <div class="hero-actions">
                            <a class="button button-primary" href="#solutions">
                                Khám phá giải pháp <span aria-hidden="true">↗</span>
                            </a>
                            <a class="button button-secondary" href="#projects">
                                Xem dự án tiêu biểu
                            </a>
                        </div>
                        <div class="hero-proof">
                            <span class="proof-check" aria-hidden="true">✓</span>
                            <span>Đối tác đồng hành tin cậy cho các công trình</span>
                        </div>
                    </div>

                    <div class="hero-visual" aria-label="Minh họa giải pháp kỹ thuật cho công trình">
                        <div class="visual-orbit orbit-one"></div>
                        <div class="visual-orbit orbit-two"></div>
                        <div class="visual-building building-back"></div>
                        <div class="visual-building building-front">
                            <div class="building-roof"></div>
                            <div class="building-windows">
                                <i v-for="window in 12" :key="window"></i>
                            </div>
                            <div class="building-base"></div>
                        </div>
                        <div class="visual-pipe pipe-one"></div>
                        <div class="visual-pipe pipe-two"></div>
                        <div class="visual-label label-top">
                            <span class="label-dot"></span>
                            <span><strong>Hiệu quả</strong><small>Giải pháp tối ưu</small></span>
                        </div>
                        <div class="visual-label label-bottom">
                            <span class="label-icon">⌂</span>
                            <span><strong>Đồng hành</strong><small>Từ thiết kế đến vận hành</small></span>
                        </div>
                        <span class="visual-caption">ENGINEERED FOR PERFORMANCE</span>
                    </div>
                </div>
                <div class="hero-bottom">
                    <span>GIẢI PHÁP CHO NHỮNG CÔNG TRÌNH BỀN VỮNG</span>
                    <span class="bottom-line"></span>
                </div>
            </section>

            <section class="trust-strip" aria-label="Lĩnh vực phục vụ">
                <span>GIẢI PHÁP CHO NHIỀU LĨNH VỰC</span>
                <strong>NHÀ Ở</strong>
                <i></i>
                <strong>THƯƠNG MẠI</strong>
                <i></i>
                <strong>CÔNG NGHIỆP</strong>
                <i></i>
                <strong>Y TẾ & DỊCH VỤ</strong>
            </section>

            <section id="solutions" class="section solutions-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">GIẢI PHÁP CỦA CHÚNG TÔI</span>
                        <h2>Đúng giải pháp.<br /><span>Vững công trình.</span></h2>
                    </div>
                    <p>
                        Từ thiết bị đến vật liệu kỹ thuật, chúng tôi tập trung vào những
                        lựa chọn phù hợp với yêu cầu vận hành và giá trị lâu dài của dự án.
                    </p>
                </div>

                <div class="solution-grid">
                    <article
                        v-for="solution in solutions"
                        :key="solution.number"
                        class="solution-card"
                    >
                        <div class="solution-top">
                            <span class="solution-symbol">{{ solution.symbol }}</span>
                            <span class="solution-number">{{ solution.number }}</span>
                        </div>
                        <span class="solution-tag">{{ solution.tag }}</span>
                        <h3>{{ solution.title }}</h3>
                        <p>{{ solution.description }}</p>
                        <a href="#contact" :aria-label="`Tìm hiểu ${solution.title}`">
                            Tìm hiểu thêm <span aria-hidden="true">↗</span>
                        </a>
                    </article>
                </div>
            </section>

            <section class="expertise">
                <div class="expertise-inner">
                    <div class="expertise-art" aria-hidden="true">
                        <div class="art-circle circle-large"></div>
                        <div class="art-circle circle-small"></div>
                        <div class="art-grid"></div>
                        <div class="art-card">
                            <span>KB</span>
                            <strong>ENGINEERING</strong>
                            <small>DESIGNED FOR REAL LIFE</small>
                        </div>
                        <div class="art-number">01 <span>— 03</span></div>
                    </div>
                    <div class="expertise-copy">
                        <span class="eyebrow">NĂNG LỰC ĐỒNG HÀNH</span>
                        <h2>Không chỉ cung cấp sản phẩm, chúng tôi mang đến sự an tâm.</h2>
                        <p>
                            Mỗi công trình có một bài toán riêng. KB TECH lắng nghe nhu cầu,
                            đề xuất phương án phù hợp và phối hợp cùng đối tác trong suốt quá
                            trình triển khai.
                        </p>
                        <ul>
                            <li><span>✓</span> Tư vấn lựa chọn theo nhu cầu thực tế</li>
                            <li><span>✓</span> Sản phẩm và giải pháp từ thương hiệu uy tín</li>
                            <li><span>✓</span> Hỗ trợ xuyên suốt quá trình triển khai</li>
                        </ul>
                        <a class="text-link" href="#contact">Trao đổi với chúng tôi <span>↗</span></a>
                    </div>
                </div>
            </section>

            <section id="projects" class="section projects-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">DẤU ẤN CÔNG TRÌNH</span>
                        <h2>Giải pháp thực tế,<br /><span>giá trị bền lâu.</span></h2>
                    </div>
                    <a class="text-link" href="#contact">
                        Khám phá năng lực dự án <span>↗</span>
                    </a>
                </div>

                <div class="project-panel">
                    <div class="project-illustration" aria-hidden="true">
                        <div class="project-sun"></div>
                        <div class="project-tower tower-one">
                            <i v-for="window in 8" :key="`one-${window}`"></i>
                        </div>
                        <div class="project-tower tower-two">
                            <i v-for="window in 10" :key="`two-${window}`"></i>
                        </div>
                        <div class="project-tower tower-three">
                            <i v-for="window in 6" :key="`three-${window}`"></i>
                        </div>
                        <div class="project-ground"></div>
                        <span class="project-stamp">KB TECH<br />PROJECTS</span>
                    </div>
                    <div class="project-copy">
                        <span class="project-label">LĨNH VỰC DỰ ÁN</span>
                        <h3>Giải pháp cho đa dạng quy mô công trình</h3>
                        <p>
                            Năng lực sản phẩm và tư vấn phù hợp với nhiều loại hình dự án
                            dân dụng, thương mại và công nghiệp.
                        </p>
                        <ul>
                            <li v-for="(projectType, index) in projectTypes" :key="projectType">
                                <span>0{{ index + 1 }}</span>{{ projectType }}<b>↗</b>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="contact" class="contact-section">
                <div class="contact-inner">
                    <div>
                        <span class="eyebrow">BẮT ĐẦU CÙNG KB TECH</span>
                        <h2>Bạn đang chuẩn bị cho công trình tiếp theo?</h2>
                        <p>Hãy chia sẻ nhu cầu, chúng tôi sẵn sàng cùng bạn tìm giải pháp phù hợp.</p>
                    </div>
                    <a class="button button-light" href="#solutions">
                        Khám phá giải pháp <span aria-hidden="true">↗</span>
                    </a>
                </div>
            </section>
        </main>
    </UserLayout>
</template>

<style scoped>
.hero {
    position: relative;
    min-height: 585px;
    padding: 50px 24px 0;
    background:
        radial-gradient(ellipse at 77% 39%, rgb(220 241 239 / 75%), transparent 37%),
        linear-gradient(115deg, #f4f8f8 0%, #f6f9f9 53%, #eef6f4 100%);
}

.hero-inner,
.section,
.expertise-inner,
.contact-inner {
    width: min(1120px, 100%);
    margin: 0 auto;
}

.hero-inner {
    display: grid;
    min-height: 470px;
    grid-template-columns: 1fr 0.9fr;
    align-items: center;
    gap: 35px;
}

.hero-copy {
    position: relative;
    z-index: 1;
    padding: 10px 0 27px;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: #087e83;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.hero-copy .eyebrow > span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #16a1a0;
    box-shadow: 0 0 0 4px rgb(22 161 160 / 13%);
}

.hero h1 {
    max-width: 590px;
    margin: 22px 0 18px;
    color: #183347;
    font-size: clamp(38px, 5vw, 61px);
    font-weight: 750;
    letter-spacing: -2.8px;
    line-height: 1.1;
}

.hero h1 span,
.section-heading h2 span {
    color: #07858a;
}

.hero-copy > p,
.section-heading > p,
.expertise-copy > p {
    max-width: 505px;
    color: #627580;
    font-size: 14px;
    line-height: 1.9;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 27px;
}

.button {
    display: inline-flex;
    min-height: 48px;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 0 18px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    transition:
        transform 160ms ease,
        background 160ms ease;
}

.button:hover {
    transform: translateY(-2px);
}

.button-primary {
    background: #087e83;
    color: #fff;
}

.button-primary:hover {
    background: #066c71;
}

.button-secondary {
    border: 1px solid #d6e1e2;
    background: rgb(255 255 255 / 65%);
    color: #244252;
}

.hero-proof {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-top: 26px;
    color: #71828c;
    font-size: 11px;
}

.proof-check {
    display: grid;
    width: 18px;
    height: 18px;
    place-items: center;
    border-radius: 50%;
    background: #dcefeb;
    color: #07857e;
    font-size: 11px;
    font-weight: 800;
}

.hero-visual {
    position: relative;
    height: 410px;
    overflow: hidden;
    border-radius: 44% 44% 20px 20px;
    background:
        linear-gradient(165deg, rgb(210 233 229 / 25%), transparent 55%),
        linear-gradient(135deg, #d9eae7, #eef4ef 68%, #d4e6e1);
}

.visual-orbit {
    position: absolute;
    border: 1px solid rgb(8 126 131 / 13%);
    border-radius: 50%;
}

.orbit-one {
    top: 34px;
    left: 54px;
    width: 380px;
    height: 380px;
}

.orbit-two {
    top: 78px;
    left: 98px;
    width: 292px;
    height: 292px;
}

.visual-building {
    position: absolute;
    bottom: 46px;
}

.building-back {
    right: 48px;
    width: 116px;
    height: 224px;
    border: 1px solid #7d9b9b;
    background: repeating-linear-gradient(
        0deg,
        #b2cbca 0,
        #b2cbca 3px,
        #d8e5e0 4px,
        #d8e5e0 23px
    );
    box-shadow: 12px 15px 0 rgb(52 94 99 / 7%);
    transform: skewY(-7deg);
}

.building-front {
    right: 125px;
    bottom: 46px;
    width: 174px;
    height: 268px;
    background: linear-gradient(100deg, #e8f0e9, #bdcfca);
    box-shadow: 19px 18px 30px rgb(55 96 97 / 15%);
    clip-path: polygon(0 10%, 77% 0, 100% 8%, 100% 100%, 0 100%);
}

.building-roof {
    position: absolute;
    top: 0;
    right: 0;
    width: 23%;
    height: 100%;
    background: rgb(66 113 115 / 15%);
    clip-path: polygon(0 0, 100% 8%, 100% 100%, 0 100%);
}

.building-windows {
    position: absolute;
    top: 42px;
    left: 20px;
    display: grid;
    width: 125px;
    grid-template-columns: repeat(3, 1fr);
    gap: 9px;
}

.building-windows i {
    height: 27px;
    border: 1px solid rgb(92 141 143 / 35%);
    background: linear-gradient(135deg, #80b3b3, #b1d4ce);
}

.building-base {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 34px;
    background: #a9c0ba;
}

.visual-pipe {
    position: absolute;
    z-index: 1;
    width: 48px;
    height: 5px;
    border: 2px solid #fff;
    border-radius: 6px;
    background: #078c8c;
    box-shadow: 0 3px 8px rgb(8 126 131 / 20%);
}

.pipe-one {
    right: 231px;
    bottom: 160px;
    transform: rotate(90deg);
}

.pipe-two {
    right: 203px;
    bottom: 135px;
    width: 32px;
    transform: rotate(0deg);
}

.visual-label {
    position: absolute;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    border: 1px solid rgb(255 255 255 / 80%);
    border-radius: 10px;
    background: rgb(255 255 255 / 90%);
    box-shadow: 0 12px 30px rgb(37 72 76 / 10%);
}

.label-top {
    top: 80px;
    left: 0;
}

.label-bottom {
    right: 8px;
    bottom: 82px;
}

.label-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #20a489;
    box-shadow: 0 0 0 5px #e3f4ee;
}

.label-icon {
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    border-radius: 8px;
    background: #e3f3f1;
    color: #087e83;
    font-size: 17px;
}

.visual-label strong,
.visual-label small {
    display: block;
}

.visual-label strong {
    color: #244252;
    font-size: 10px;
}

.visual-label small {
    margin-top: 4px;
    color: #819098;
    font-size: 8px;
}

.visual-caption {
    position: absolute;
    right: 16px;
    bottom: 15px;
    color: #6f8b88;
    font-size: 7px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.hero-bottom {
    display: flex;
    width: min(1120px, 100%);
    height: 62px;
    align-items: center;
    gap: 20px;
    margin: 0 auto;
    border-top: 1px solid rgb(86 122 125 / 15%);
    color: #7f9298;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.bottom-line {
    width: 54px;
    height: 1px;
    background: #0c8989;
}

.trust-strip {
    display: flex;
    min-height: 89px;
    align-items: center;
    justify-content: center;
    gap: clamp(14px, 4vw, 48px);
    padding: 20px 24px;
    border-bottom: 1px solid #edf0f0;
    color: #789098;
}

.trust-strip > span {
    color: #a0adb1;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 1.3px;
}

.trust-strip strong {
    color: #617780;
    font-size: 10px;
    letter-spacing: 1px;
}

.trust-strip i {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #17a09a;
}

.section {
    padding: 98px 0;
}

.section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 40px;
    margin-bottom: 38px;
}

.section-heading h2,
.expertise-copy h2,
.contact-inner h2 {
    margin: 14px 0 0;
    color: #183347;
    font-size: clamp(30px, 4vw, 43px);
    font-weight: 730;
    letter-spacing: -1.7px;
    line-height: 1.18;
}

.section-heading > p {
    max-width: 425px;
    margin: 0 0 4px;
    font-size: 12px;
}

.solution-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 17px;
}

.solution-card {
    min-height: 266px;
    padding: 25px 25px 22px;
    border: 1px solid #e6eded;
    border-radius: 11px;
    background: #fff;
    transition:
        border-color 160ms ease,
        box-shadow 160ms ease,
        transform 160ms ease;
}

.solution-card:hover {
    transform: translateY(-4px);
    border-color: #b8d8d5;
    box-shadow: 0 16px 34px rgb(28 75 80 / 8%);
}

.solution-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.solution-symbol {
    display: grid;
    width: 43px;
    height: 43px;
    place-items: center;
    border-radius: 12px;
    background: #e8f4f1;
    color: #078e88;
    font-size: 25px;
}

.solution-number {
    color: #b7c4c7;
    font-size: 11px;
    font-weight: 700;
}

.solution-tag {
    display: inline-block;
    margin-top: 24px;
    color: #078e88;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.solution-card h3 {
    margin: 8px 0;
    color: #213d4c;
    font-size: 17px;
}

.solution-card p {
    min-height: 48px;
    margin: 0;
    color: #71828b;
    font-size: 11px;
    line-height: 1.8;
}

.solution-card > a,
.text-link {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: #087e83;
    font-size: 11px;
    font-weight: 750;
}

.solution-card > a {
    margin-top: 17px;
}

.expertise {
    padding: 67px 24px;
    background: #f3f7f6;
}

.expertise-inner {
    display: grid;
    grid-template-columns: 0.95fr 1fr;
    align-items: center;
    gap: 80px;
}

.expertise-art {
    position: relative;
    height: 330px;
    overflow: hidden;
    border-radius: 14px;
    background: linear-gradient(145deg, #d5e7e2, #edf3ee);
}

.art-circle {
    position: absolute;
    border: 1px solid rgb(8 126 131 / 18%);
    border-radius: 50%;
}

.circle-large {
    top: -65px;
    left: -30px;
    width: 360px;
    height: 360px;
}

.circle-small {
    top: -17px;
    left: 19px;
    width: 264px;
    height: 264px;
}

.art-grid {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 74%;
    height: 66%;
    background:
        linear-gradient(90deg, transparent 49%, rgb(91 128 128 / 10%) 50%),
        linear-gradient(0deg, transparent 49%, rgb(91 128 128 / 10%) 50%);
    background-size: 32px 32px;
    mask-image: linear-gradient(135deg, transparent, #000);
}

.art-card {
    position: absolute;
    top: 77px;
    left: 50%;
    display: grid;
    width: 200px;
    height: 153px;
    align-content: center;
    justify-items: center;
    gap: 11px;
    border: 1px solid rgb(255 255 255 / 85%);
    border-radius: 12px;
    background: rgb(255 255 255 / 75%);
    box-shadow: 0 18px 38px rgb(39 83 84 / 11%);
    transform: translateX(-50%) rotate(-5deg);
}

.art-card > span {
    display: grid;
    width: 44px;
    height: 44px;
    place-items: center;
    border-radius: 13px;
    background: #087e83;
    color: #fff;
    font-size: 21px;
    font-weight: 800;
}

.art-card strong {
    color: #244252;
    font-size: 10px;
    letter-spacing: 1.5px;
}

.art-card small {
    color: #829497;
    font-size: 6px;
    font-weight: 700;
    letter-spacing: 1px;
}

.art-number {
    position: absolute;
    right: 22px;
    bottom: 17px;
    color: #477674;
    font-size: 11px;
    font-weight: 800;
}

.art-number span {
    color: #8ca8a3;
    font-weight: 500;
}

.expertise-copy h2 {
    max-width: 470px;
    font-size: clamp(28px, 3.3vw, 38px);
}

.expertise-copy > p {
    margin: 17px 0;
    font-size: 12px;
}

.expertise-copy ul {
    display: grid;
    gap: 12px;
    margin: 23px 0;
    padding: 0;
    color: #4c626e;
    font-size: 11px;
    list-style: none;
}

.expertise-copy li {
    display: flex;
    align-items: center;
    gap: 10px;
}

.expertise-copy li span {
    display: grid;
    width: 18px;
    height: 18px;
    place-items: center;
    border-radius: 50%;
    background: #dcefeb;
    color: #07857e;
    font-size: 10px;
    font-weight: 800;
}

.projects-section {
    padding-top: 93px;
    padding-bottom: 100px;
}

.projects-section .section-heading {
    margin-bottom: 34px;
}

.projects-section .section-heading h2 {
    font-size: clamp(30px, 3.7vw, 40px);
}

.project-panel {
    display: grid;
    min-height: 360px;
    grid-template-columns: 1.02fr 0.98fr;
    overflow: hidden;
    border: 1px solid #e4eceb;
    border-radius: 13px;
    background: #fff;
}

.project-illustration {
    position: relative;
    min-height: 360px;
    overflow: hidden;
    background: linear-gradient(150deg, #dcece7 0%, #edf3ee 75%);
}

.project-sun {
    position: absolute;
    top: 47px;
    right: 67px;
    width: 93px;
    height: 93px;
    border-radius: 50%;
    background: #c6e2d6;
}

.project-tower {
    position: absolute;
    bottom: 40px;
    display: grid;
    align-content: start;
    justify-content: center;
    gap: 9px;
    padding: 18px 12px;
    border: 1px solid rgb(255 255 255 / 75%);
    background: linear-gradient(90deg, #aac6c0, #d1e0d9);
    box-shadow: 8px 9px 22px rgb(60 98 96 / 10%);
}

.tower-one {
    left: 17%;
    width: 92px;
    height: 167px;
    grid-template-columns: repeat(2, 1fr);
    transform: skewY(3deg);
}

.tower-two {
    z-index: 1;
    left: 38%;
    width: 112px;
    height: 235px;
    grid-template-columns: repeat(2, 1fr);
    padding-top: 23px;
    background: linear-gradient(90deg, #90b5af, #bed3cb);
}

.tower-three {
    left: 64%;
    width: 76px;
    height: 139px;
    grid-template-columns: repeat(2, 1fr);
    transform: skewY(-4deg);
}

.project-tower i {
    min-height: 14px;
    border: 1px solid rgb(255 255 255 / 44%);
    background: linear-gradient(135deg, #76a9a4, #a4c9bd);
}

.project-ground {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 45px;
    background: #9fbdb2;
}

.project-stamp {
    position: absolute;
    z-index: 2;
    right: 17px;
    bottom: 15px;
    color: #557e78;
    font-size: 7px;
    font-weight: 800;
    letter-spacing: 1px;
    line-height: 1.5;
    text-align: right;
}

.project-copy {
    padding: 42px 42px 33px;
}

.project-label {
    color: #078e88;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.project-copy h3 {
    max-width: 360px;
    margin: 12px 0 10px;
    color: #1c394a;
    font-size: 24px;
    letter-spacing: -0.7px;
    line-height: 1.3;
}

.project-copy > p {
    color: #71828b;
    font-size: 11px;
    line-height: 1.8;
}

.project-copy ul {
    display: grid;
    margin: 22px 0 0;
    padding: 0;
    list-style: none;
}

.project-copy li {
    display: flex;
    min-height: 42px;
    align-items: center;
    gap: 11px;
    border-bottom: 1px solid #edf0f0;
    color: #425a67;
    font-size: 10px;
    font-weight: 650;
}

.project-copy li span {
    color: #07908a;
    font-size: 8px;
    font-weight: 800;
}

.project-copy li b {
    margin-left: auto;
    color: #8fa4a7;
}

.contact-section {
    padding: 58px 24px;
    background:
        radial-gradient(circle at 82% 20%, rgb(36 161 151 / 18%), transparent 28%),
        #123b4b;
}

.contact-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
}

.contact-inner .eyebrow {
    color: #73d0c2;
}

.contact-inner h2 {
    max-width: 610px;
    margin-top: 12px;
    color: #fff;
    font-size: clamp(27px, 3.5vw, 38px);
}

.contact-inner p {
    margin: 12px 0 0;
    color: #b7cbd0;
    font-size: 11px;
    line-height: 1.8;
}

.button-light {
    flex-shrink: 0;
    background: #fff;
    color: #087e83;
}

@media (max-width: 900px) {
    .hero-inner,
    .section,
    .expertise-inner,
    .contact-inner {
        width: min(100%, 700px);
    }

    .hero {
        padding-right: 28px;
        padding-left: 28px;
    }

    .hero-inner {
        grid-template-columns: 1fr 0.8fr;
        gap: 12px;
    }

    .hero-visual {
        height: 355px;
        transform: scale(0.94);
        transform-origin: center right;
    }

    .section {
        padding: 78px 28px;
    }

    .expertise-inner {
        gap: 40px;
    }

    .project-copy {
        padding: 33px 27px 26px;
    }
}

@media (max-width: 650px) {
    .hero {
        padding: 25px 18px 0;
    }

    .hero-inner {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .hero-copy {
        padding: 14px 0 0;
    }

    .hero h1 {
        max-width: 480px;
        font-size: clamp(39px, 11vw, 54px);
        letter-spacing: -2.2px;
    }

    .hero-copy > p {
        font-size: 13px;
    }

    .hero-visual {
        width: min(100%, 390px);
        height: 330px;
        justify-self: center;
        transform: none;
    }

    .building-front {
        right: 102px;
    }

    .building-back {
        right: 28px;
    }

    .label-bottom {
        right: 0;
    }

    .hero-bottom {
        height: 55px;
        font-size: 7px;
    }

    .trust-strip {
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 13px 17px;
        padding: 22px 20px;
    }

    .trust-strip > span {
        width: 100%;
    }

    .trust-strip strong {
        font-size: 8px;
    }

    .section {
        padding: 68px 20px;
    }

    .section-heading {
        display: grid;
        gap: 14px;
        margin-bottom: 27px;
    }

    .section-heading > p {
        font-size: 11px;
    }

    .solution-grid {
        grid-template-columns: 1fr;
    }

    .solution-card {
        min-height: auto;
    }

    .solution-card p {
        min-height: auto;
    }

    .expertise {
        padding: 45px 20px;
    }

    .expertise-inner {
        grid-template-columns: 1fr;
        gap: 32px;
    }

    .expertise-art {
        height: 270px;
    }

    .circle-large {
        width: 310px;
        height: 310px;
    }

    .circle-small {
        width: 230px;
        height: 230px;
    }

    .art-card {
        top: 58px;
        height: 145px;
    }

    .project-panel {
        grid-template-columns: 1fr;
    }

    .project-illustration {
        min-height: 280px;
    }

    .project-copy {
        padding: 28px 22px;
    }

    .contact-section {
        padding: 42px 20px;
    }

    .contact-inner {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
