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
        if (! Schema::hasColumn('featured_projects', 'products')) {
            Schema::table('featured_projects', function (Blueprint $table) {
                $table->json('products')->nullable()->after('subcategory');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('featured_projects', 'products')) {
            Schema::table('featured_projects', function (Blueprint $table) {
                $table->dropColumn('products');
            });
        }
    }
};
