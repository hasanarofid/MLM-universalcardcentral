<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'price',
        'quantity',
        'points',
        'image',
        'description',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'quantity' => 'integer',
        'points' => 'integer',
        'is_active' => 'boolean',
    ];
}
