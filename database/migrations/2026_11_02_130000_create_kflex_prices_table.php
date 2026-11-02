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
        Schema::create('kflex_prices', function (Blueprint $table) {
            $table->id();
            $table->string('product_line', 50)->index();
            $table->string('form_type', 30)->index();
            $table->string('variant_type', 50)->index();
            $table->string('thickness', 50);
            $table->string('diameter', 50)->nullable();
            $table->string('brand', 100)->default('K-FLEX');
            $table->string('origin', 100)->default('Malaysia');
            $table->string('product_name', 255);
            $table->string('unit', 30)->default('m');
            $table->unsignedBigInteger('price')->default(0);
            $table->timestamps();

            $table->index(['product_line', 'form_type', 'variant_type'], 'kflex_prices_hierarchy_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kflex_prices');
    }
};
