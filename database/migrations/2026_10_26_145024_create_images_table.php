<?php

use App\Models\Category;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Support\ImageStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->morphs('imageable');
            $table->string('path', 1000);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->moveProductImages();
        $this->moveCategoryImages();
        $this->moveFeaturedProjectImages();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image');
        });
        Schema::table('featured_projects', function (Blueprint $table) {
            $table->dropColumn(['image', 'images']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('image')->nullable();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable();
        });
        Schema::table('featured_projects', function (Blueprint $table) {
            $table->string('image')->default('');
            $table->json('images')->nullable();
        });

        foreach ($this->groupedPaths(Product::class) as $id => $paths) {
            DB::table('products')->where('id', $id)->update(['image' => json_encode($paths)]);
        }

        foreach ($this->groupedPaths(Category::class) as $id => $paths) {
            DB::table('categories')->where('id', $id)->update(['image' => $paths[0]]);
        }

        foreach ($this->groupedPaths(FeaturedProject::class) as $id => $paths) {
            DB::table('featured_projects')->where('id', $id)->update([
                'image' => $paths[0],
                'images' => json_encode($paths),
            ]);
        }

        Schema::dropIfExists('images');
    }

    private function moveProductImages(): void
    {
        DB::table('products')->select('id', 'image')->orderBy('id')->each(function (object $product): void {
            $images = json_decode((string) $product->image, true);

            $this->insertImages(Product::class, $product->id, is_array($images) ? $images : [], 'products');
        });
    }

    private function moveCategoryImages(): void
    {
        DB::table('categories')->select('id', 'image')->whereNotNull('image')->orderBy('id')->each(
            fn (object $category) => $this->insertImages(Category::class, $category->id, [$category->image], 'categories'),
        );
    }

    private function moveFeaturedProjectImages(): void
    {
        DB::table('featured_projects')->select('id', 'image', 'images')->orderBy('id')->each(function (object $project): void {
            $images = json_decode((string) $project->images, true);
            $images = is_array($images) && $images !== [] ? $images : [$project->image];

            $this->insertImages(FeaturedProject::class, $project->id, $images, 'featured-projects');
        });
    }

    /**
     * @param  array<int, mixed>  $images
     */
    private function insertImages(string $type, int $id, array $images, string $directory): void
    {
        $order = 0;

        foreach ($images as $image) {
            if (! is_string($image) || $image === '') {
                continue;
            }

            $path = ImageStorage::isDataUri($image) ? ImageStorage::storeDataUri($image, $directory) : $image;

            DB::table('images')->insert([
                'imageable_type' => $type,
                'imageable_id' => $id,
                'path' => $path,
                'sort_order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<int, list<string>>
     */
    private function groupedPaths(string $type): array
    {
        return DB::table('images')
            ->where('imageable_type', $type)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('imageable_id')
            ->map(fn ($rows): array => $rows->pluck('path')->all())
            ->all();
    }
};
