<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('project_reports')
            ->orderBy('id')
            ->select('id')
            ->get()
            ->each(function (object $projectReport): void {
                DB::table('project_reports')
                    ->where('id', $projectReport->id)
                    ->update(['project_code' => 'NTK'.$projectReport->id]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing manually assigned project codes cannot be restored.
    }
};
