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
        if (Schema::hasTable('quotation_drafts')) {
            return;
        }

        Schema::create('quotation_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('title');
            $table->string('quotation_type', 32)->default('ariston');
            $table->string('project_name')->nullable();
            $table->string('recipient_company')->nullable();
            $table->string('recipient_person')->nullable();
            $table->unsignedInteger('items_count')->default(1);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->longText('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_drafts');
    }
};
