<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $fillable = [
        'name',
        'branch_name',
        'whatsapp_sender_phone',
        'httpsms_from_phone',
        'httpsms_api_key',
        'sms_template',
        'is_active',
    ];
}