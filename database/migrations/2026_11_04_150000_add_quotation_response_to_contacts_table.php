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
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'quotation_path')) {
                $table->string('quotation_path')->nullable()->after('attachment_name');
            }
            if (! Schema::hasColumn('contacts', 'quotation_name')) {
                $table->string('quotation_name')->nullable()->after('quotation_path');
            }
            if (! Schema::hasColumn('contacts', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('quotation_name');
            }
            if (! Schema::hasColumn('contacts', 'quoted_at')) {
                $table->timestamp('quoted_at')->nullable()->after('admin_note');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $columns = array_filter(
                ['quotation_path', 'quotation_name', 'admin_note', 'quoted_at'],
                fn (string $column): bool => Schema::hasColumn('contacts', $column),
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
