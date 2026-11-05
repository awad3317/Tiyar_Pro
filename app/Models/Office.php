<?php

namespace App\Models;

use App\Traits\HasOfficeFeatures;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Authenticatable
{
    use Notifiable, HasOfficeFeatures;

    protected $fillable = [
        'name',
        'branch_name',
        'whatsapp_sender_phone',
        'manager_phone',
        'send_daily_report',
        'password',
        'httpsms_from_phone',
        'httpsms_api_key',
        'sms_template',
        'delivery_sms_template',
        'whatsapp_base_url',
        'whatsapp_instance_id',
        'whatsapp_instance_name',
        'whatsapp_api_key',
        'whatsapp_webhook_url',
        'whatsapp_timeout',
        'whatsapp_template',
        'delivery_whatsapp_template',
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
            'send_daily_report' => 'boolean',
        ];
    }

    public const DEFAULT_DELIVERY_SMS_TEMPLATE = "تم تسليم طردك: {package_type}\nمكتب {office_name}\nشكراً لتعاملكم معنا.";

    public const DEFAULT_DELIVERY_WHATSAPP_TEMPLATE = "✅ *إشعار تسليم طرد*\n\nعميلنا العزيز، تم بحمد الله تسليم طردك: *{package_type}*\nمن مكتب: *{office_name}*\n\nنسعد بخدمتكم دائماً.";

    public function getDeliverySmsTemplate(): string
    {
        return !empty($this->delivery_sms_template)
            ? $this->delivery_sms_template
            : self::DEFAULT_DELIVERY_SMS_TEMPLATE;
    }

    public function getDeliveryWhatsappTemplate(): string
    {
        return !empty($this->delivery_whatsapp_template)
            ? $this->delivery_whatsapp_template
            : self::DEFAULT_DELIVERY_WHATSAPP_TEMPLATE;
    }

    public function hasWhatsAppConfigured(): bool
    {
        return !empty($this->whatsapp_base_url) && !empty($this->whatsapp_api_key);
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'office_id');
    }

    public function favoriteCustomers(): HasMany
    {
        return $this->hasMany(FavoriteCustomer::class, 'office_id');
    }

    public function accountants(): HasMany
    {
        return $this->hasMany(Accountant::class, 'office_id');
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class, 'office_id');
    }

    public function manifests(): HasMany
    {
        return $this->hasMany(Manifest::class, 'office_id');
    }

    public function parcelEditLogs(): HasMany
    {
        return $this->hasMany(ParcelEditLog::class, 'office_id');
    }
}