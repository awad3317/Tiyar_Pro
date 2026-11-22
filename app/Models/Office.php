<?php

namespace App\Models;

use App\Traits\HasOfficeFeatures;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class Office extends Authenticatable
{
    use Notifiable, HasOfficeFeatures;

    /** @var array<string, mixed>|null */
    protected ?array $runtimeProfileCache = null;

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

    public function profile(): HasOne
    {
        return $this->hasOne(OfficeProfile::class, 'office_id');
    }

    /**
     * جلب هوية المكتب الكاملة (مكيشة في الذاكرة + الكاش الدائم بدون استعلامات مكررة).
     *
     * @return array<string, mixed>
     */
    public function getResolvedProfile(): array
    {
        if ($this->runtimeProfileCache !== null) {
            return $this->runtimeProfileCache;
        }

        return $this->runtimeProfileCache = OfficeProfile::resolvedForOffice($this);
    }

    public function clearProfileCache(): void
    {
        $this->runtimeProfileCache = null;
        OfficeProfile::forgetCache((int) $this->id);
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

    public function outgoingParcels(): HasMany
    {
        return $this->hasMany(OutgoingParcel::class, 'office_id');
    }

    public function incomingTransitParcels(): HasMany
    {
        return $this->hasMany(OutgoingParcel::class, 'destination_office_id');
    }

    public function partnerOffices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class, 'office_partner_links', 'office_id', 'partner_office_id')
            ->withTimestamps();
    }

    /**
     * مزامنة الفروع والمكاتب المرتبطة بشكل ثنائي الاتجاه (Symmetric / Bidirectional).
     *
     * @param  array<int|string>  $partnerIds
     */
    public function syncPartnerOffices(array $partnerIds): void
    {
        $selfId = (int)$this->id;
        $normalizedIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id,$partnerIds),
            static fn (int $id) =>$id > 0 && $id !==$selfId
        )));

        DB::transaction(function () use ($selfId, $normalizedIds): void {$currentIds = array_map(
                'intval',
                $this->partnerOffices()->pluck('offices.id')->all()
            );

            $toAttach = array_diff($normalizedIds, $currentIds);$toDetach = array_diff($currentIds,$normalizedIds);

            if (! empty($toAttach)) {
                $now = now();$rows = [];
                foreach ($toAttach as $partnerId) {$rows[] = [
                        'office_id'         => $selfId,
                        'partner_office_id' => $partnerId,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                    $rows[] = [
                        'office_id'         => $partnerId,
                        'partner_office_id' => $selfId,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
                DB::table('office_partner_links')->insertOrIgnore($rows);
            }

            if (! empty($toDetach)) {
                $detachIds = array_values($toDetach);

                DB::table('office_partner_links')
                    ->where('office_id', $selfId)
                    ->whereIn('partner_office_id', $detachIds)
                    ->delete();

                DB::table('office_partner_links')
                    ->whereIn('office_id', $detachIds)
                    ->where('partner_office_id', $selfId)
                    ->delete();
            }
        });

        $this->unsetRelation('partnerOffices');
    }
}