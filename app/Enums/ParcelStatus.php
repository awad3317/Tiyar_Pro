<?php

namespace App\Enums;

enum ParcelStatus: string
{
    case InOffice  = 'in_office';
    case Delivered = 'delivered';
    case Returned  = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::InOffice  => 'بالمكتب',
            self::Delivered => 'تم التسليم',
            self::Returned  => 'مُرتجع',
        };
    }

    /**
     * الانتقالات المسموحة من الحالة الحالية.
     *
     * بالمكتب   → تسليم للعميل | إرجاع للمكتب المرسل
     * تم التسليم → إرجاع للمكتب (لا يمكن الإرجاع للمرسل مباشرة، يجب المرور بالمكتب)
     * مُرتجع     → حالة نهائية
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::InOffice  => [self::Delivered, self::Returned],
            self::Delivered => [self::InOffice],
            self::Returned  => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** خريطة الانتقالات بصيغة قابلة للإرسال للواجهة: ['in_office' => ['delivered', 'returned'], ...] */
    public static function transitionMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [
                $s->value => array_map(fn (self $t) => $t->value, $s->allowedTransitions()),
            ])
            ->all();
    }

    /** ['in_office' => 'بالمكتب', ...] */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
