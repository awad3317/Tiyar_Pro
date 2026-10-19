<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'whatsapp_base_url',
        'whatsapp_instance_id',
        'whatsapp_instance_name',
        'whatsapp_api_key',
        'whatsapp_webhook_url',
        'whatsapp_timeout',
        'whatsapp_template',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'httpsms_api_key',
        'whatsapp_api_key',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hasWhatsAppConfigured(): bool
    {
        return !empty($this->whatsapp_base_url) && !empty($this->whatsapp_api_key);
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'office_id');
    }
}