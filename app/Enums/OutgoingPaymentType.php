<?php

namespace App\Enums;

enum OutgoingPaymentType: string
{
    case PAID     = 'paid';
    case DEFERRED = 'deferred';

    public function label(): string
    {
        return match ($this) {
            self::PAID     => 'محاسب',
            self::DEFERRED => 'آجل',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PAID     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::DEFERRED => 'bg-amber-50 text-amber-800 border-amber-200',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
