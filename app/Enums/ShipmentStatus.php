<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case DRAFT = 'draft';
    case IN_TRANSIT = 'in_transit';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT              => 'قيد التجهيز',
            self::IN_TRANSIT         => 'في الطريق',
            self::PARTIALLY_RECEIVED => 'استلام جزئي',
            self::RECEIVED           => 'مكتملة الاستلام',
            self::CANCELLED          => 'ملغاة',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT              => 'bg-amber-50 text-amber-800 border-amber-200',
            self::IN_TRANSIT         => 'bg-sky-50 text-sky-800 border-sky-200',
            self::PARTIALLY_RECEIVED => 'bg-indigo-50 text-indigo-800 border-indigo-200',
            self::RECEIVED           => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::CANCELLED          => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }
}