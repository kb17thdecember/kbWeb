<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

class QuotationDraft extends Model
{
    protected $table = 'quotation_drafts';

    protected $fillable = [
        'user_id',
        'title',
        'quotation_type',
        'project_name',
        'recipient_company',
        'recipient_person',
        'items_count',
        'grand_total',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'items_count' => 'integer',
            'grand_total' => 'integer',
            'payload' => 'array',
        ];
    }

    public static function ensureTableExists(): void
    {
        try {
            if (Schema::hasTable('quotation_drafts')) {
                return;
            }

            Schema::create('quotation_drafts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->string('quotation_type', 32)->default('ariston');
                $table->string('project_name')->nullable();
                $table->string('recipient_company')->nullable();
                $table->string('recipient_person')->nullable();
                $table->unsignedInteger('items_count')->default(1);
                $table->unsignedBigInteger('grand_total')->default(0);
                $table->json('payload');
                $table->timestamps();
            });
        } catch (Throwable) {
            // Ignore schema creation errors in read-only contexts
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
