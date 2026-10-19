<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedProject extends Model
{
    protected $fillable = [
        'name',
        'description',
        'location',
        'category',
        'subcategory',
        'type',
        'image',
    ];
}
