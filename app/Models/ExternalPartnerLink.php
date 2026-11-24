<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalPartnerLink extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'office_id',
        'partner_name',
        'partner_city',
        'partner_phone',
        'token',
        'access_pin',
        'receipt_prefix',
        'is_active',
        'last_used_at',
        'shipments_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'last_used_at'    => 'datetime',
            'shipments_count' => 'integer',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'external_partner_link_id');
    }

    public function outgoingParcels(): HasMany
    {
        return $this->hasMany(OutgoingParcel::class, 'external_partner_link_id');
    }
}
