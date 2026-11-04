<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Contact extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'message',
        'status',
        'category_id',
        'project_name',
        'product',
        'attachment_path',
        'attachment_name',
        'quotation_path',
        'quotation_name',
        'admin_note',
        'quoted_at',
    ];

    protected $appends = ['attachment_url', 'quotation_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quoted_at' => 'datetime',
        ];
    }

    public static function ensureQuotationColumns(): void
    {
        try {
            if (! Schema::hasTable('contacts') || Schema::hasColumn('contacts', 'quotation_path')) {
                return;
            }

            Schema::table('contacts', function (Blueprint $table): void {
                if (! Schema::hasColumn('contacts', 'quotation_path')) {
                    $table->string('quotation_path')->nullable();
                }
                if (! Schema::hasColumn('contacts', 'quotation_name')) {
                    $table->string('quotation_name')->nullable();
                }
                if (! Schema::hasColumn('contacts', 'admin_note')) {
                    $table->text('admin_note')->nullable();
                }
                if (! Schema::hasColumn('contacts', 'quoted_at')) {
                    $table->timestamp('quoted_at')->nullable();
                }
            });
        } catch (Throwable) {
            // Ignore schema check errors in read-only contexts
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function attachmentUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->attachment_path) {
                return null;
            }

            if (config('filesystems.disks.public.driver') === 'local') {
                return '/storage/'.ltrim($this->attachment_path, '/');
            }

            return Storage::disk('public')->url($this->attachment_path);
        });
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function quotationUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->quotation_path) {
                return null;
            }

            if (config('filesystems.disks.public.driver') === 'local') {
                return '/storage/'.ltrim($this->quotation_path, '/');
            }

            return Storage::disk('public')->url($this->quotation_path);
        });
    }
}
