<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
    ];

    protected $appends = ['attachment_url'];

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
}
