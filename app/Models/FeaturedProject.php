<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeaturedProject extends Model
{
    protected $fillable = [
        'name',
        'description',
        'location',
        'category_id',
        'subcategory_id',
        'category',
        'subcategory',
        'type',
        'image',
    ];

    /**
     * Get the parent category selected for this project.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the child category selected for this project.
     *
     * @return BelongsTo<Category, $this>
     */
    public function childCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }
}
