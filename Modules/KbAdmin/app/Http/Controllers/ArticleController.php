<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\ArticleContentSanitizer;
use App\Support\ImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class ArticleController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        return Inertia::render('articles/Index', [
            'articles' => Article::query()->with('images')->latest()->get(),
            ...$dashboardStats->summary(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedArticle($request);
        $cover = $validated['cover'] ?? null;
        unset($validated['cover']);

        $article = Article::create($this->withPublishedAt($validated));

        if ($cover !== null) {
            $article->syncImagePaths([ImageStorage::storeUpload($cover, 'articles')]);
        }

        return to_route('admin.articles.index');
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $validated = $this->validatedArticle($request);
        $cover = $validated['cover'] ?? null;
        unset($validated['cover']);

        $article->update($this->withPublishedAt($validated, $article));

        if ($cover !== null) {
            $article->syncImagePaths([ImageStorage::storeUpload($cover, 'articles')]);
        }

        return to_route('admin.articles.index');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return to_route('admin.articles.index');
    }

    public function storeContentImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = ImageStorage::storeUpload($validated['image'], 'articles/content');

        return response()->json([
            'url' => config('filesystems.disks.public.driver') === 'local'
                ? '/storage/'.ltrim($path, '/')
                : Storage::disk('public')->url($path),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedArticle(Request $request): array
    {
        $validated = $request->validate($this->rules());
        $validated['content'] = ArticleContentSanitizer::clean($validated['content']);

        if (! ArticleContentSanitizer::hasContent($validated['content'])) {
            throw ValidationException::withMessages(['content' => 'Vui lòng nhập nội dung bài viết.']);
        }

        return $validated;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:100000'],
            'is_published' => ['required', 'boolean'],
            'cover' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withPublishedAt(array $validated, ?Article $article = null): array
    {
        $validated['published_at'] = $validated['is_published']
            ? ($article?->published_at ?? now())
            : null;

        return $validated;
    }
}
