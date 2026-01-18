<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'key',
        'label',
        'is_active',
    ];

    protected $casts = [
        'key' => 'encrypted',
        'is_active' => 'boolean',
    ];
}
