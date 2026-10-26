<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class FeaturedProject extends Model
{
    use HasImages;

    protected $fillable = [
        'name',
        'description',
        'location',
        'category',
        'subcategory',
        'type',
    ];

    protected $hidden = ['images'];

    protected $appends = ['image', 'image_url', 'images_url'];

    /**
     * @return Attribute<string|null, never>
     */
    protected function image(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->firstImageUrl());
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->firstImageUrl());
    }

    /**
     * @return Attribute<list<string>, never>
     */
    protected function imagesUrl(): Attribute
    {
        return Attribute::get(fn (): array => $this->imageUrls());
    }
}
