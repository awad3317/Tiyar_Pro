<?php

namespace App\Services;

use App\Models\Office;
use App\Models\Parcel;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * جدولة إشعارات الواتساب لكل مكتب بشكل مستقل مع فواصل عشوائية واستراحات بشرية
 * لمنع إطلاق عشرات الرسائل في دقائق معدودة (السبب الرئيسي لحظر أرقام المكاتب).
 */
class WhatsAppQueueScheduler
{
    public const STATUS_CACHE_PREFIX = 'whatsapp_status_office_';

    // الفاصل العادي بين رسالتين لنفس المكتب (ثوانٍ)
    private const GAP_MIN = 65;
    private const GAP_MAX = 140;

    // أول رسالة عندما يكون طابور المكتب فارغاً (ثوانٍ)
    private const FIRST_MIN = 20;
    private const FIRST_MAX = 45;

    // استراحة بشرية إجبارية كل 15 رسالة متتالية (ثوانٍ: 8 - 15 دقيقة)
    private const BREAK_EVERY = 15;
    private const BREAK_MIN = 480;
    private const BREAK_MAX = 900;

    public function __construct(protected WhatsAppService $whatsAppService) {}

    public static function statusCacheKey(int $officeId): string
    {
        return self::STATUS_CACHE_PREFIX . $officeId;
    }

    /**
     * نفس منطق المشروع: فقط القيمة الصريحة 'offline' تعني انقطاعاً، والقيمة الفارغة (null) تعامل كمتصل.
     */
    public function isOfficeOffline(int $officeId): bool
    {
        return Cache::get(self::statusCacheKey($officeId)) === 'offline';
    }

    public function markOfficeOffline(int $officeId): void
    {
        Cache::put(self::statusCacheKey($officeId), 'offline', now()->addMinutes(15));
    }

    public function markOfficeOnline(int $officeId): void
    {
        Cache::put(self::statusCacheKey($officeId), 'online', now()->addMinutes(15));
    }

    /**
     * تحديد مصير إشعار الواتساب لطرد جديد: جدولته أو إغلاقه فوراً.
     *
     * @return array{queued: bool, scheduled_at: ?Carbon, reason: ?string}
     */
    public function scheduleForParcel(Parcel $parcel, Office $office, bool $whatsappRequested = true): array
    {
        $reason = null;

        if (! $whatsappRequested) {
            $reason = 'not_requested';
        } elseif (! $this->whatsAppService->forOffice($office)->isConfigured()) {
            $reason = 'not_configured';
        } elseif ($this->isOfficeOffline((int) $office->id)) {
            // لا نكدّس الرسائل أثناء انقطاع الواتساب حتى لا تصل للعميل بعد أيام من استلامه الطرد
            $reason = 'offline';
        }

        if ($reason !== null) {
            $parcel->forceFill([
                'whatsapp_status'       => 'sent',
                'whatsapp_scheduled_at' => null,
            ])->save();

            return ['queued' => false, 'scheduled_at' => null, 'reason' => $reason];
        }

        $officeId = (int) $office->id;

        $assign = function () use ($parcel, $officeId): Carbon {
            $scheduledAt = $this->nextSlotForOffice($officeId, (string) $parcel->id);

            $parcel->forceFill([
                'whatsapp_status'       => 'pending',
                'whatsapp_scheduled_at' => $scheduledAt,
            ])->save();

            return $scheduledAt;
        };

        try {
            // قفل لكل مكتب: الإدخال التدريجي يرسل عدة طلبات متزامنة، وبدون القفل قد يأخذ طردان نفس الموعد
            $scheduledAt = Cache::lock("wa_schedule_office_{$officeId}", 10)->block(5, $assign);
        } catch (LockTimeoutException $e) {
            Log::warning("WhatsAppQueueScheduler: lock timeout for office {$officeId}, scheduling without lock.");
            $scheduledAt = $assign();
        }

        return ['queued' => true, 'scheduled_at' => $scheduledAt, 'reason' => null];
    }

    /**
     * حساب الموعد التالي في طابور المكتب.
     */
    private function nextSlotForOffice(int $officeId, string $excludeParcelId): Carbon
    {
        $now = now();

        $upcoming = Parcel::query()
            ->where('office_id', $officeId)
            ->where('whatsapp_status', 'pending')
            ->where('whatsapp_scheduled_at', '>', $now)
            ->where('id', '!=', $excludeParcelId);

        $lastScheduled = (clone $upcoming)->max('whatsapp_scheduled_at');

        if (! $lastScheduled) {
            return $now->copy()->addSeconds(random_int(self::FIRST_MIN, self::FIRST_MAX));
        }

        $queuedCount = (clone $upcoming)->count();

        $slot = Carbon::parse($lastScheduled)->addSeconds(random_int(self::GAP_MIN, self::GAP_MAX));

        // استراحة بشرية بعد كل 15 رسالة متتالية في طابور المكتب لكسر النمط الآلي
        if ($queuedCount > 0 && $queuedCount % self::BREAK_EVERY === 0) {
            $slot->addSeconds(random_int(self::BREAK_MIN, self::BREAK_MAX));
        }

        return $slot;
    }
}

