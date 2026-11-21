<?php

namespace App\Models;

use App\Enums\OutgoingParcelStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingParcel extends Model
{
    use HasUuids;

    protected $fillable = [
        'office_id',
        'destination_office_id',
        'external_office_name',
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'package_type',
        'receipt_number',
        'cod_amount',
        'notes',
        'status',
        'linked_incoming_parcel_id',
        'dispatched_at',
        'received_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status'        => OutgoingParcelStatus::class,
            'cod_amount'    => 'decimal:2',
            'dispatched_at' => 'datetime',
            'received_at'   => 'datetime',
            'cancelled_at'  => 'datetime',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function destinationOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'destination_office_id');
    }

    public function linkedIncomingParcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class, 'linked_incoming_parcel_id');
    }

    public function isExternal(): bool
    {
        return $this->destination_office_id === null;
    }

    public function destinationLabel(): string
    {
        if ($this->destination_office_id !== null) {
            $destination = $this->destinationOffice;
            if ($destination) {
                $branch = trim((string) ($destination->branch_name ?? ''));
                return $branch !== '' ? "{$destination->name} - {$branch}" : $destination->name;
            }
        }

        return trim((string) ($this->external_office_name ?? ''));
    }
}
