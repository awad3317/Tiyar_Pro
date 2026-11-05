<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $fillable = [
        'office_id',
        'name',
        'phone',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function manifests(): HasMany
    {
        return $this->hasMany(Manifest::class, 'driver_id');
    }

    /**
     * البحث عن السائق داخل نفس المكتب أو إنشاؤه تلقائياً وربطه بالكشف
     * مع تنظيف المدخلات وحمايتها من تجاوز أطوال الأعمدة أو تداخل المكاتب
     */
    public static function resolveForOffice(
        int $officeId,
        string $name,
        ?string $phone = null,
        ?int $driverId = null
    ): self {
        $cleanName  = mb_substr(trim(strip_tags($name)), 0, 100);
        $cleanPhone = self::normalizePhone($phone);

        // 1. إذا تم تمرير معرف السائق ($driverId) وكان تابعاً لنفس المكتب حصراً
        if ($driverId) {
            $driver = self::query()
                ->where('office_id', $officeId)
                ->find($driverId);

            if ($driver) {
                $updates = [];
                if ($cleanName !== '' && $driver->name !== $cleanName) {
                    $updates['name'] = $cleanName;
                }
                if ($cleanPhone !== null && $driver->phone !== $cleanPhone) {
                    $updates['phone'] = $cleanPhone;
                }
                if (!empty($updates)) {
                    $driver->update($updates);
                }

                return $driver;
            }
        }

        // 2. إذا لم يُمرر $driverId وكان رقم الجوال موجوداً: نبحث برقم الجوال داخل نفس المكتب
        if ($cleanPhone !== null) {
            $driver = self::query()
                ->where('office_id', $officeId)
                ->where('phone', $cleanPhone)
                ->first();

            if ($driver) {
                if ($cleanName !== '' && $driver->name !== $cleanName) {
                    $driver->update(['name' => $cleanName]);
                }

                return $driver;
            }

            return self::create([
                'office_id' => $officeId,
                'name'      => $cleanName !== '' ? $cleanName : $cleanPhone,
                'phone'     => $cleanPhone,
            ]);
        }

        // ضمان عدم حفظ اسم فارغ في حال عدم توفر رقم هاتف أو معرف سائق صالح
        if ($cleanName === '') {
            $cleanName = 'سائق غير محدد';
        }

        // 3. إذا كان رقم الجوال فارغاً: نبحث بالاسم المطابق داخل نفس المكتب أو ننشئ سائقاً جديداً
        $driver = self::query()
            ->where('office_id', $officeId)
            ->where('name', $cleanName)
            ->first();

        if ($driver) {
            return $driver;
        }

        return self::create([
            'office_id' => $officeId,
            'name'      => $cleanName,
            'phone'     => null,
        ]);
    }

    /**
     * تنظيف رقم الجوال وتحويل الأرقام العربية/الفارسية إلى إنجليزية وتقييد الطول بـ 20 خانة
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $converted = str_replace($arabicDigits, $englishDigits, $phone);
        $digits    = preg_replace('/\D+/', '', $converted) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '9677')) {
            $digits = substr($digits, 3);
        } elseif (strlen($digits) === 14 && str_starts_with($digits, '009677')) {
            $digits = substr($digits, 5);
        }

        $digits = substr($digits, 0, 20);

        return $digits !== '' ? $digits : null;
    }
}
