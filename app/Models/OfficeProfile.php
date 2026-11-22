<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OfficeProfile extends Model
{
    public const DEFAULT_PRIMARY_COLOR   = '#041627';
    public const DEFAULT_SECONDARY_COLOR = '#fe9d20';
    public const DEFAULT_TAGLINE_1       = 'لإرسال واستقبال الطرود والرسائل';
    public const DEFAULT_TAGLINE_2       = 'من وإلى مختلف المحافظات';
    public const DEFAULT_RECEIPT_TITLE   = 'سند إرسال';
    public const DEFAULT_SLOGAN          = 'رسالتك في أيدٍ أمينة';
    public const DEFAULT_FOOTER_TEXT     = 'نظام مُرسَل للطرود';

    public const DEFAULT_TERMS = [
        '1- المكتب غير مسؤول عن الرسالة ما لم يتم المراجعة خلال خمسة أيام',
        '2- المكتب غير مسؤول عن الرسائل القابلة للكسر والتلف',
        '3- المكتب غير مسؤول عن أي تأخير بسبب حجز جمركي أو نقاط أمنية',
        '4- عزيزنا العميل أجور الإرسال لا يشمل حق الأمانات',
    ];

    protected $fillable = [
        'office_id',
        'logo_path',
        'icon_path',
        'primary_color',
        'secondary_color',
        'tagline_primary',
        'tagline_secondary',
        'branch_address',
        'complaints_phone',
        'dispatch_phone_1',
        'dispatch_phone_2',
        'delivery_phone',
        'receipt_title',
        'slogan',
        'receipt_terms',
        'footer_text',
    ];

    protected function casts(): array
    {
        return [
            'receipt_terms' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $profile): void {
            self::forgetCache((int) $profile->office_id);
        });

        static::deleted(function (self $profile): void {
            self::forgetCache((int) $profile->office_id);
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public static function cacheKey(int $officeId): string
    {
        return "office_profile:{$officeId}";
    }

    public static function forgetCache(int $officeId): void
    {
        Cache::forget(self::cacheKey($officeId));
    }

    public static function sanitizeHex(?string $color, string $fallback): string
    {
        $clean = trim((string) $color);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $clean) ? strtolower($clean) : $fallback;
    }

    public static function assetUrl(?string $path): ?string
    {
        $clean = trim((string) $path);
        if ($clean === '') {
            return null;
        }

        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://') || str_starts_with($clean, '/')) {
            return $clean;
        }

        return Storage::disk('public')->url($clean);
    }

    /**
     * إرجاع مصفوفة الهوية الجاهزة والمكيشة لأي مكتب مع القيم الافتراضية الآمنة.
     *
     * @return array<string, mixed>
     */
    public static function resolvedForOffice(Office $office): array
    {
        $cached = Cache::rememberForever(self::cacheKey((int) $office->id), function () use ($office): array {
            $profile = self::query()->where('office_id', $office->id)->first();

            $terms = is_array($profile?->receipt_terms)
                ? array_values(array_filter(array_map(fn ($t) => trim((string) $t), $profile->receipt_terms)))
                : [];

            return [
                'logo_path'         => $profile?->logo_path,
                'icon_path'         => $profile?->icon_path,
                'primary_color'     => self::sanitizeHex($profile?->primary_color, self::DEFAULT_PRIMARY_COLOR),
                'secondary_color'   => self::sanitizeHex($profile?->secondary_color, self::DEFAULT_SECONDARY_COLOR),
                'tagline_primary'   => trim((string) ($profile?->tagline_primary ?? '')) ?: self::DEFAULT_TAGLINE_1,
                'tagline_secondary' => trim((string) ($profile?->tagline_secondary ?? '')) ?: self::DEFAULT_TAGLINE_2,
                'branch_address'    => trim((string) ($profile?->branch_address ?? '')),
                'complaints_phone'  => trim((string) ($profile?->complaints_phone ?? '')),
                'dispatch_phone_1'  => trim((string) ($profile?->dispatch_phone_1 ?? '')),
                'dispatch_phone_2'  => trim((string) ($profile?->dispatch_phone_2 ?? '')),
                'delivery_phone'    => trim((string) ($profile?->delivery_phone ?? '')),
                'receipt_title'     => trim((string) ($profile?->receipt_title ?? '')) ?: self::DEFAULT_RECEIPT_TITLE,
                'slogan'            => trim((string) ($profile?->slogan ?? '')) ?: self::DEFAULT_SLOGAN,
                'receipt_terms'     => ! empty($terms) ? $terms : self::DEFAULT_TERMS,
                'footer_text'       => trim((string) ($profile?->footer_text ?? '')) ?: self::DEFAULT_FOOTER_TEXT,
            ];
        });

        $fallbackAddress = trim((string) ($office->branch_name ?? '')) !== ''
            ? "{$office->name} - {$office->branch_name}"
            : (string) $office->name;

        return array_merge($cached, [
            'logo_url'         => self::assetUrl($cached['logo_path']),
            'icon_url'         => self::assetUrl($cached['icon_path']),
            'branch_address'   => $cached['branch_address'] !== '' ? $cached['branch_address'] : $fallbackAddress,
            'complaints_phone' => $cached['complaints_phone'] !== '' ? $cached['complaints_phone'] : (string) ($office->manager_phone ?: $office->whatsapp_sender_phone ?? ''),
            'dispatch_phone_1' => $cached['dispatch_phone_1'] !== '' ? $cached['dispatch_phone_1'] : (string) ($office->whatsapp_sender_phone ?: $office->httpsms_from_phone ?? ''),
            'delivery_phone'   => $cached['delivery_phone'] !== '' ? $cached['delivery_phone'] : (string) ($office->whatsapp_sender_phone ?: $office->manager_phone ?? ''),
        ]);
    }
}