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
        Schema::table('featured_projects', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->foreignId('subcategory_id')
                ->nullable()
                ->after('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
        });

        DB::table('featured_projects')
            ->select(['id', 'category', 'subcategory'])
            ->orderBy('id')
            ->each(function (object $project): void {
                $parent = DB::table('categories')
                    ->whereNull('parent_id')
                    ->where('name', $project->category)
                    ->first(['id']);

                if ($parent === null) {
                    return;
                }

                $child = DB::table('categories')
                    ->where('parent_id', $parent->id)
                    ->where('name', $project->subcategory)
                    ->first(['id']);

                if ($child !== null) {
                    DB::table('featured_projects')
                        ->where('id', $project->id)
                        ->update([
                            'category_id' => $parent->id,
                            'subcategory_id' => $child->id,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('featured_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcategory_id');
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
