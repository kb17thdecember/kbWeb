<?php

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
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['id', 'parent_id'], 'categories_id_parent_id_unique');
            $table->foreign('parent_id', 'categories_parent_id_foreign')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id', 'products_category_id_foreign')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();
            $table->foreign(['subcategory_id', 'category_id'], 'products_subcategory_parent_foreign')
                ->references(['id', 'parent_id'])
                ->on('categories')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_subcategory_parent_foreign');
            $table->dropForeign('products_category_id_foreign');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign('categories_parent_id_foreign');
            $table->dropUnique('categories_id_parent_id_unique');
        });
    }
};
