<?php

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
        DB::table('products')
            ->whereNull('tag')
            ->orWhereNotIn('tag', [0, 1])
            ->update(['tag' => 0]);

        if (DB::table('products')->count() > 0 && DB::table('products')->where('tag', 1)->count() === 0) {
            $featuredIds = DB::table('products')
                ->orderByDesc('id')
                ->limit(8)
                ->pluck('id');

            DB::table('products')
                ->whereIn('id', $featuredIds)
                ->update(['tag' => 1]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->integer('tag')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('tag')->nullable()->change();
        });
    }
};
