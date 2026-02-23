<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'model',
        'prompt_per_million',
        'completion_per_million',
    ];

    protected $casts = [
        'prompt_per_million' => 'float',
        'completion_per_million' => 'float',
    ];
}
