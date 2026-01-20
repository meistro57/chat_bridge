<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'key',
        'label',
        'is_active',
        'is_validated',
        'last_validated_at',
        'validation_error',
    ];

    protected $casts = [
        'key' => 'encrypted',
        'is_active' => 'boolean',
        'is_validated' => 'boolean',
        'last_validated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
