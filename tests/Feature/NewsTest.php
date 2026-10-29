<?php

use App\Models\Article;
use App\Models\Catalogue;
use App\Models\User;
use App\Support\ArticleContentSanitizer;
use Database\Seeders\ArticleSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('news page lists only published articles and catalogues', function () {
    $published = Article::factory()->create();
    Article::factory()->draft()->create();
    $catalogue = Catalogue::factory()->create();
    Catalogue::factory()->create(['is_published' => false]);

    $this->get(route('kbuser.news'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news/Index')
            ->has('articles', 1)
            ->where('articles.0.id', $published->id)
            ->has('catalogues', 1)
            ->where('catalogues.0.id', $catalogue->id)
            ->where('catalogues.0.file_url', '/storage/'.$catalogue->file_path),
        );
});

test('article detail is shown by slug and drafts return not found', function () {
    $article = Article::factory()->create(['title' => 'Bảo ôn K-Flex']);
    $draft = Article::factory()->draft()->create();

    expect($article->slug)->toBe('bao-on-k-flex');

    $this->get(route('kbuser.news.show', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news/Show')
            ->where('article.id', $article->id),
        );

    $this->get(route('kbuser.news.show', $draft))->assertNotFound();
});

test('article slugs stay unique for duplicate titles', function () {
    $first = Article::factory()->create(['title' => 'Cùng tiêu đề']);
    $second = Article::factory()->create(['title' => 'Cùng tiêu đề']);

    expect($second->slug)->not->toBe($first->slug);
});

test('guests cannot manage articles or catalogues', function () {
    $this->post(route('admin.articles.store'), [])->assertRedirect(route('admin.login'));
    $this->post(route('admin.catalogues.store'), [])->assertRedirect(route('admin.login'));
});

test('admin can create, update and delete an article with a cover image', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.articles.store'), [
        'title' => 'Tin mới',
        'excerpt' => 'Tóm tắt',
        'content' => "Đoạn 1\n\nĐoạn 2",
        'is_published' => true,
        'cover' => UploadedFile::fake()->create('cover.jpg', 50, 'image/jpeg'),
    ])->assertRedirect(route('admin.articles.index'));

    $article = Article::query()->with('images')->firstOrFail();
    expect($article->published_at)->not->toBeNull()
        ->and($article->images)->toHaveCount(1);
    Storage::disk('public')->assertExists($article->images->first()->path);

    $this->put(route('admin.articles.update', $article), [
        'title' => 'Tin đã sửa',
        'excerpt' => 'Tóm tắt',
        'content' => 'Nội dung',
        'is_published' => false,
    ])->assertRedirect(route('admin.articles.index'));

    $article->refresh();
    expect($article->title)->toBe('Tin đã sửa')
        ->and($article->slug)->toBe('tin-moi')
        ->and($article->is_published)->toBeFalse()
        ->and($article->published_at)->toBeNull();

    $this->delete(route('admin.articles.destroy', $article))->assertRedirect(route('admin.articles.index'));
    expect(Article::query()->count())->toBe(0);
});

test('admin can upload, replace and delete a catalogue pdf', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.catalogues.store'), [
        'title' => 'Catalogue Ariston',
        'description' => 'Bản 2026',
        'is_published' => true,
        'file' => UploadedFile::fake()->create('ariston.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('admin.catalogues.index'));

    $catalogue = Catalogue::query()->firstOrFail();
    expect($catalogue->file_name)->toBe('ariston.pdf');
    Storage::disk('public')->assertExists($catalogue->file_path);
    $oldPath = $catalogue->file_path;

    $this->put(route('admin.catalogues.update', $catalogue), [
        'title' => 'Catalogue Ariston',
        'is_published' => true,
        'file' => UploadedFile::fake()->create('ariston-v2.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('admin.catalogues.index'));

    $catalogue->refresh();
    expect($catalogue->file_name)->toBe('ariston-v2.pdf');
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($catalogue->file_path);

    $path = $catalogue->file_path;
    $this->delete(route('admin.catalogues.destroy', $catalogue))->assertRedirect(route('admin.catalogues.index'));
    Storage::disk('public')->assertMissing($path);
});

test('catalogue upload rejects non pdf files and requires a file on create', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.catalogues.store'), [
        'title' => 'Sai định dạng',
        'is_published' => true,
        'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
    ])->assertSessionHasErrors('file');

    $this->post(route('admin.catalogues.store'), [
        'title' => 'Thiếu file',
        'is_published' => true,
    ])->assertSessionHasErrors('file');

    expect(Catalogue::query()->count())->toBe(0);
});

test('article content is sanitized but keeps formatting and images', function () {
    $dirty = '<p style="color: #ff0000; font-family: Arial; position: fixed; background: url(x)">Xin <strong>chào</strong></p>'
        .'<script>alert(1)</script><img src="/storage/articles/content/a.jpg" onerror="alert(1)">'
        .'<img src="javascript:alert(1)"><a href="javascript:alert(1)">bad</a><a href="https://example.com">ok</a>'
        .'<iframe src="https://evil.test"></iframe>';

    $clean = ArticleContentSanitizer::clean($dirty);

    expect($clean)
        ->toContain('<strong>chào</strong>')
        ->toContain('color: #ff0000')
        ->toContain('font-family: Arial')
        ->toContain('src="/storage/articles/content/a.jpg"')
        ->toContain('href="https://example.com"')
        ->not->toContain('script')
        ->not->toContain('onerror')
        ->not->toContain('position')
        ->not->toContain('url(')
        ->not->toContain('javascript:')
        ->not->toContain('iframe');
});

test('admin article save sanitizes content and rejects empty content', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.articles.store'), [
        'title' => 'Bài có định dạng',
        'excerpt' => 'Tóm tắt',
        'content' => '<p>Nội dung</p><script>alert(1)</script>',
        'is_published' => true,
    ])->assertRedirect(route('admin.articles.index'));

    expect(Article::query()->firstOrFail()->content)->toBe('<p>Nội dung</p>');

    $this->post(route('admin.articles.store'), [
        'title' => 'Bài rỗng',
        'excerpt' => 'Tóm tắt',
        'content' => '<p><br></p>',
        'is_published' => true,
    ])->assertSessionHasErrors('content');
});

test('admin can upload an image for article content and guests cannot', function () {
    Storage::fake('public');

    $this->postJson(route('admin.articles.images.store'), [
        'image' => UploadedFile::fake()->create('inline.png', 20, 'image/png'),
    ])->assertUnauthorized();

    $this->actingAs(User::factory()->admin()->create());

    $response = $this->postJson(route('admin.articles.images.store'), [
        'image' => UploadedFile::fake()->create('inline.png', 20, 'image/png'),
    ])->assertOk();

    $url = $response->json('url');
    expect($url)->toStartWith('/storage/articles/content/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));

    $this->postJson(route('admin.articles.images.store'), [
        'image' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
    ])->assertUnprocessable();
});

test('article and catalogue seeders are idempotent and publish content', function () {
    Storage::fake('public');

    $this->seed([ArticleSeeder::class, CatalogueSeeder::class]);
    $this->seed([ArticleSeeder::class, CatalogueSeeder::class]);

    expect(Article::query()->count())->toBe(4)
        ->and(Article::query()->published()->count())->toBe(3)
        ->and(Catalogue::query()->count())->toBe(4)
        ->and(Article::query()->with('images')->get()->every(fn (Article $article): bool => $article->image_url !== null))->toBeTrue()
        ->and(Catalogue::query()->with('images')->get()->every(fn (Catalogue $catalogue): bool => $catalogue->image_url !== null))->toBeTrue();

    Storage::disk('public')->assertExists(Catalogue::query()->firstOrFail()->file_path);
    $this->get(route('kbuser.news.show', Article::query()->published()->firstOrFail()))->assertOk();
});

test('sitemap includes published articles', function () {
    $article = Article::factory()->create();
    $draft = Article::factory()->draft()->create();

    $this->get(route('kbuser.sitemap'))
        ->assertOk()
        ->assertSee('/news/'.$article->slug, false)
        ->assertDontSee('/news/'.$draft->slug, false);
});
