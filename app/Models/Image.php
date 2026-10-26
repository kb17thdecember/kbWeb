<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'path',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Image $image): void {
            $image->deleteStoredFile();
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isStoredFile(): bool
    {
        return ! str_starts_with($this->path, 'http://')
            && ! str_starts_with($this->path, 'https://')
            && ! str_starts_with($this->path, 'data:')
            && ! str_starts_with($this->path, '/');
    }

    /**
     * Đường dẫn tương đối để không phụ thuộc vào APP_URL (tránh lỗi localhost/127.0.0.1).
     */
    public function getUrlAttribute(): string
    {
        return $this->isStoredFile() ? '/storage/'.ltrim($this->path, '/') : $this->path;
    }

    /**
     * Xóa file khi không còn dòng ảnh nào khác trỏ tới cùng đường dẫn.
     */
    private function deleteStoredFile(): void
    {
        if (! $this->isStoredFile() || static::query()->where('path', $this->path)->exists()) {
            return;
        }

        Storage::disk('public')->delete($this->path);
    }
}
