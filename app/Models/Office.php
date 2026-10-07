<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'branch_name',
        'whatsapp_sender_phone',
        'password',
        'httpsms_from_phone',
        'httpsms_api_key',
        'sms_template',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'httpsms_api_key',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // public function parcels(): HasMany
    // {
    //     return $this->hasMany(Parcel::class, 'office_id');
    // }
}