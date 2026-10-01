<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'instance_id',
        'instance_token',
        'pin_code',
        'phone_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}