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
        Schema::create('project_reports', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->string('project_code');
            $table->string('project_area');
            $table->string('project_location');
            $table->string('developer')->nullable();
            $table->string('me_consultant')->nullable();
            $table->string('me_contractor')->nullable();
            $table->string('progress')->nullable();
            $table->string('estimate_value')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('project_update');
            $table->string('project_status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_reports');
    }
};
