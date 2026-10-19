<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public $table = 'products';

    protected $fillable = [
        'name',
        'category_id',
        'description',
        'display_order',
        'discount',
        'image',
        'tag',
        'status',
        'price',
        'quantity',
        'created_at',
        'updated_at',
    ];
}
