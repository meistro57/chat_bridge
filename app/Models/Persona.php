<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Persona extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'provider',
        'model',
        'system_prompt',
        'guidelines',
        'temperature',
        'notes',
    ];

    protected $casts = [
        'guidelines' => 'json',
        'temperature' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
