<?php

namespace App\Enums;

enum OutgoingParcelStatus: string
{
    case PENDING    = 'pending';
    case IN_TRANSIT = 'in_transit';
    case RECEIVED   = 'received';
    case DELIVERED  = 'delivered';
    case CANCELLED  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING    => 'في الانتظار',
            self::IN_TRANSIT => 'في الطريق',
            self::RECEIVED   => 'تم الاستلام بالفرع',
            self::DELIVERED  => 'تم التسليم',
            self::CANCELLED  => 'ملغي',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING    => 'bg-amber-100 text-amber-800 border-amber-200',
            self::IN_TRANSIT => 'bg-sky-100 text-sky-800 border-sky-200',
            self::RECEIVED   => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            self::DELIVERED  => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::CANCELLED  => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
