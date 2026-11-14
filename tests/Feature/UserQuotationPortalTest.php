<?php

use App\Models\Product;
use App\Models\QuotationDraft;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Database\Seeders\PublicQuoteUserSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\KbUser\Http\Controllers\QuotationController;

test('visitors and regular authenticated users are redirected to quotation login when visiting the quotation builder', function () {
    $this->get(route('kbuser.quotations.index'))
        ->assertRedirect(route('kbuser.quotations.login'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('kbuser.quotations.index'))
        ->assertRedirect(route('kbuser.quotations.login'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get(route('kbuser.quotations.login'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Login')
            ->where('auth.hasQuoteSession', false)
            ->where('recaptchaSiteKey', '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'),
        );

    $this->get(route('kbuser.sitemap'))
        ->assertOk()
        ->assertDontSee('/quotations', false);

    $this->get(route('kbuser.robots'))
        ->assertOk()
        ->assertDontSee('Disallow: /quotations', false);
});

test('the seeded public quote account can log in, view the quotation builder, and log out', function () {
    $this->seed([
        PublicQuoteUserSeeder::class,
        ProductSeeder::class,
    ]);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => 'publicquote@gmail.com',
        'password' => 'stlquote',
    ])
        ->assertSessionHasErrors('recaptcha_token')
        ->assertSessionMissing(QuotationController::SESSION_KEY);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => 'publicquote@gmail.com',
        'password' => 'stlquote',
        'recaptcha_token' => 'test-recaptcha-token',
    ])
        ->assertRedirect(route('kbuser.quotations.index'))
        ->assertSessionHas(QuotationController::SESSION_KEY);

    $slim30Id = Product::query()->where('name', 'SLIM3 30B VN')->value('id');
    $proR40Id = Product::query()->where('name', 'PRO R S 40 SH 2.5 FE')->value('id');

    $this->get(route('kbuser.quotations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Index')
            ->where('auth.hasQuoteSession', true)
            ->where('aristonProducts', fn ($products) => collect($products)->firstWhere('id', $slim30Id)['origin'] === 'Việt Nam'
                && collect($products)->firstWhere('id', $proR40Id)['origin'] === 'Trung Quốc'),
        );

    $this->post(route('kbuser.quotations.logout'))
        ->assertRedirect(route('kbuser.quotations.login'))
        ->assertSessionMissing(QuotationController::SESSION_KEY);
});

test('invalid credentials or non-authorized accounts cannot log in to the quotation builder', function () {
    $this->seed(PublicQuoteUserSeeder::class);
    $otherUser = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'stlqoute',
    ]);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => 'publicquote@gmail.com',
        'password' => 'wrong-password',
        'recaptcha_token' => 'test-recaptcha-token',
    ])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing(QuotationController::SESSION_KEY);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => $otherUser->email,
        'password' => 'stlqoute',
        'recaptcha_token' => 'test-recaptcha-token',
    ])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing(QuotationController::SESSION_KEY);
});

test('authorized quote user can save, update, list, and delete quotation drafts', function () {
    $this->seed(PublicQuoteUserSeeder::class);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => 'publicquote@gmail.com',
        'password' => 'stlquote',
        'recaptcha_token' => 'test-recaptcha-token',
    ])->assertRedirect(route('kbuser.quotations.index'));

    $draftPayload = [
        'version' => 1,
        'quotationType' => 'ariston',
        'quoteForm' => [
            'documentTitle' => 'BÁO GIÁ BƠM NHIỆT ARISTON',
            'recipientCompany' => 'CÔNG TY CP ĐẦU TƯ SAO MAI',
            'recipientPerson' => 'Mr. Minh',
            'contactPhone' => '0912345678',
            'quoteDate' => '2026-11-13',
            'validUntilDate' => '2027-01-13',
            'projectName' => 'Tòa nhà Sao Mai',
            'vatPercent' => 8,
            'signerName' => 'Nguyễn Tuấn Kiệt',
            'signerPhone' => '0979 807 234',
            'signerEmail' => 'tuankiet@starlightgroup.vn',
        ],
        'items' => [
            [
                'uid' => 'row-1',
                'productId' => null,
                'productLabel' => 'Máy nước nóng gián tiếp dung tích 30L',
                'modelCode' => 'SLIM3 30B VN',
                'origin' => 'Ý-Việt Nam',
                'imageUrl' => '',
                'unit' => 'Máy',
                'specifications' => '- Dung tích: 30L',
                'unitPrice' => 3200000,
                'quantity' => 50,
            ],
        ],
    ];

    $this->post(route('kbuser.quotations.drafts.store'), [
        'title' => 'Nháp Ariston - Tòa nhà Sao Mai',
        'quotation_type' => 'ariston',
        'project_name' => 'Tòa nhà Sao Mai',
        'recipient_company' => 'CÔNG TY CP ĐẦU TƯ SAO MAI',
        'recipient_person' => 'Mr. Minh',
        'items_count' => 1,
        'grand_total' => 172800000,
        'payload' => $draftPayload,
    ])->assertRedirect();

    $draft = QuotationDraft::query()->first();
    expect($draft)->not->toBeNull()
        ->and($draft->title)->toBe('Nháp Ariston - Tòa nhà Sao Mai')
        ->and($draft->project_name)->toBe('Tòa nhà Sao Mai')
        ->and($draft->grand_total)->toBe(172800000);

    // Update existing draft
    $this->post(route('kbuser.quotations.drafts.store'), [
        'draft_id' => $draft->id,
        'title' => 'Nháp Ariston - Tòa nhà Sao Mai (Cập nhật)',
        'quotation_type' => 'ariston',
        'project_name' => 'Tòa nhà Sao Mai GĐ2',
        'recipient_company' => 'CÔNG TY CP ĐẦU TƯ SAO MAI',
        'recipient_person' => 'Mr. Minh',
        'items_count' => 2,
        'grand_total' => 250000000,
        'payload' => $draftPayload,
    ])->assertRedirect();

    expect(QuotationDraft::query()->count())->toBe(1);
    $draft->refresh();
    expect($draft->title)->toBe('Nháp Ariston - Tòa nhà Sao Mai (Cập nhật)')
        ->and($draft->project_name)->toBe('Tòa nhà Sao Mai GĐ2');

    $this->get(route('kbuser.quotations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Index')
            ->has('quotationDrafts', 1)
            ->where('quotationDrafts.0.title', 'Nháp Ariston - Tòa nhà Sao Mai (Cập nhật)'),
        );

    $this->delete(route('kbuser.quotations.drafts.destroy', $draft))
        ->assertRedirect();

    expect(QuotationDraft::query()->count())->toBe(0);
});

test('authorized quote user can import an exported xlsx quotation file for editing', function () {
    $this->seed(PublicQuoteUserSeeder::class);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => 'publicquote@gmail.com',
        'password' => 'stlquote',
        'recaptcha_token' => 'test-recaptcha-token',
    ])->assertRedirect(route('kbuser.quotations.index'));

    $tmpPath = tempnam(sys_get_temp_dir(), 'quote_xlsx_');
    @unlink($tmpPath);
    $zip = new ZipArchive;
    $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $sheetXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="5"><c r="A5" t="inlineStr"><is><t>BÁO GIÁ BƠM NHIỆT ARISTON</t></is></c></row>
        <row r="6"><c r="A6" t="inlineStr"><is><t>Kính gửi:</t></is></c><c r="B6" t="inlineStr"><is><t>CÔNG TY CP XÂY DỰNG HÒA BÌNH</t></is></c></row>
        <row r="7"><c r="A7" t="inlineStr"><is><t>Người nhận :</t></is></c><c r="B7" t="inlineStr"><is><t>Ms. Lan</t></is></c></row>
        <row r="8"><c r="A8" t="inlineStr"><is><t>Thông tin liên lạc:</t></is></c><c r="B8" t="inlineStr"><is><t>0988777666</t></is></c></row>
        <row r="9"><c r="A9" t="inlineStr"><is><t>Thời gian :</t></is></c><c r="B9" t="inlineStr"><is><t>13/11/2026</t></is></c></row>
        <row r="10"><c r="A10" t="inlineStr"><is><t>Project:</t></is></c><c r="B10" t="inlineStr"><is><t>Khu đô thị Hòa Bình</t></is></c></row>
        <row r="12">
            <c r="A12" t="inlineStr"><is><t>Sản Phẩm
Product</t></is></c>
            <c r="B12" t="inlineStr"><is><t>Mã sản phẩm
Model</t></is></c>
        </row>
        <row r="13">
            <c r="A13" t="inlineStr"><is><t>Máy nước nóng gián tiếp dung tích 30L</t></is></c>
            <c r="B13" t="inlineStr"><is><t>SLIM3 30B VN</t></is></c>
            <c r="C13" t="inlineStr"><is><t>Ý-Việt Nam</t></is></c>
            <c r="E13" t="inlineStr"><is><t>Máy</t></is></c>
            <c r="F13" t="inlineStr"><is><t>- Dung tích: 30L</t></is></c>
            <c r="G13" t="inlineStr"><is><t>2.850.000</t></is></c>
            <c r="H13" t="inlineStr"><is><t>120</t></is></c>
            <c r="I13" t="inlineStr"><is><t>342.000.000</t></is></c>
        </row>
        <row r="14">
            <c r="A14" t="inlineStr"><is><t>TỔNG CỘNG (chưa bao gồm thuế VAT)</t></is></c>
            <c r="I14" t="inlineStr"><is><t>342.000.000</t></is></c>
        </row>
        <row r="15">
            <c r="A15" t="inlineStr"><is><t>Thuế VAT (08%)</t></is></c>
            <c r="I15" t="inlineStr"><is><t>27.360.000</t></is></c>
        </row>
        <row r="18">
            <c r="A18" t="inlineStr"><is><t>1. Báo giá có hiệu lực đến ngày: 13/01/2027</t></is></c>
        </row>
        <row r="26"><c r="A26" t="inlineStr"><is><t>Many thanks &amp; Best regards</t></is></c></row>
        <row r="27"><c r="A27" t="inlineStr"><is><t>Nguyễn Tuấn Kiệt</t></is></c></row>
        <row r="28"><c r="A28" t="inlineStr"><is><t>HP: 0979 807 234</t></is></c></row>
        <row r="29"><c r="A29" t="inlineStr"><is><t>Email : tuankiet@starlightgroup.vn</t></is></c></row>
    </sheetData>
</worksheet>
XML;
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $uploadedFile = new UploadedFile(
        $tmpPath,
        'Bao-gia-Ariston-Khu-do-thi-Hoa-Binh.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $response = $this->postJson(route('kbuser.quotations.import'), [
        'quotation_file' => $uploadedFile,
    ]);

    $response->assertOk()
        ->assertJsonPath('payload.quotationType', 'ariston')
        ->assertJsonPath('payload.quoteForm.recipientCompany', 'CÔNG TY CP XÂY DỰNG HÒA BÌNH')
        ->assertJsonPath('payload.quoteForm.recipientPerson', 'Ms. Lan')
        ->assertJsonPath('payload.quoteForm.projectName', 'Khu đô thị Hòa Bình')
        ->assertJsonPath('payload.items.0.modelCode', 'SLIM3 30B VN')
        ->assertJsonPath('payload.items.0.unitPrice', 2850000)
        ->assertJsonPath('payload.items.0.quantity', 120);

    @unlink($tmpPath);
});
