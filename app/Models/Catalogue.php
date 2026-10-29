<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use Database\Factories\CatalogueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Catalogue extends Model
{
    /** @use HasFactory<CatalogueFactory> */
    use HasFactory;

    use HasImages;

    protected $fillable = [
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'is_published',
    ];

    protected $hidden = ['images', 'file_path'];

    protected $appends = ['image_url', 'file_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Catalogue $catalogue): void {
            Storage::disk('public')->delete($catalogue->file_path);
        });
    }

    /**
     * @param  Builder<Catalogue>  $query
     * @return Builder<Catalogue>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->firstImageUrl());
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::get(function (): string {
            if (config('filesystems.disks.public.driver') === 'local') {
                return '/storage/'.ltrim($this->file_path, '/');
            }

            return Storage::disk('public')->url($this->file_path);
        });
    }
}
