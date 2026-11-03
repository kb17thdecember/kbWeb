<?php

use App\Services\SeoAutoIndexService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'seo_title')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('seo_title', 180)->nullable()->after('description');
                $table->string('seo_description', 350)->nullable()->after('seo_title');
                $table->string('seo_keywords', 500)->nullable()->after('seo_description');
            });
        }

        if (Schema::hasTable('categories') && ! Schema::hasColumn('categories', 'seo_title')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->string('seo_title', 180)->nullable()->after('description_2');
                $table->string('seo_description', 350)->nullable()->after('seo_title');
                $table->string('seo_keywords', 500)->nullable()->after('seo_description');
            });
        }

        if (Schema::hasTable('articles') && ! Schema::hasColumn('articles', 'seo_title')) {
            Schema::table('articles', function (Blueprint $table): void {
                $table->string('seo_title', 180)->nullable()->after('content');
                $table->string('seo_description', 350)->nullable()->after('seo_title');
                $table->string('seo_keywords', 500)->nullable()->after('seo_description');
            });
        }

        SeoAutoIndexService::syncAllEntitiesSeo(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'seo_title')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn(['seo_title', 'seo_description', 'seo_keywords']);
            });
        }

        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'seo_title')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->dropColumn(['seo_title', 'seo_description', 'seo_keywords']);
            });
        }

        if (Schema::hasTable('articles') && Schema::hasColumn('articles', 'seo_title')) {
            Schema::table('articles', function (Blueprint $table): void {
                $table->dropColumn(['seo_title', 'seo_description', 'seo_keywords']);
            });
        }
    }
};
