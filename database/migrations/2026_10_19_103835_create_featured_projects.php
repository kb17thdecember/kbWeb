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
        Schema::create('featured_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('seo_title', 180)->nullable();
            $table->string('seo_description', 350)->nullable();
            $table->string('seo_keywords', 500)->nullable();
            $table->string('location');
            $table->string('category');
            $table->string('subcategory');
            $table->json('products')->nullable();
            $table->string('type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('featured_projects');
    }
};
