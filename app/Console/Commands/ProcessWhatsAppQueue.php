<?php

namespace App\Console\Commands;

use App\Models\Office;
use App\Models\Parcel;
use App\Services\WhatsAppMessageComposer;
use App\Services\WhatsAppQueueScheduler;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppQueue extends Command
{
    protected $signature = 'whatsapp:process-queue';
    protected $description = 'معالجة طابور إشعارات الواتساب: طرد واحد فقط لكل مكتب في الدقيقة مع إلغاء الرسائل عند انقطاع الواتساب';

    /** عبارات تدل على انقطاع جلسة الواتساب في ردود Evolution Go */
    private const DISCONNECT_HINTS = [
        'not logged', 'not connected', 'disconnected', 'no session', 'logged out',
        'logout', 'not authenticated', 'session not found', 'instance not found',
        'connection refused', 'could not resolve', 'failed to connect', 'timed out',
    ];

    public function handle(
        WhatsAppService $whatsAppService,
        WhatsAppMessageComposer $composer,
        WhatsAppQueueScheduler $scheduler
    ): int {
        $now = now();

        // 1. إغلاق الرسائل التي لم يعد لها معنى (طرد مُسلّم/مُرتجع، أو مضى عليه أكثر من 12 ساعة، أو بلا موعد)
        $expired = Parcel::query()
            ->where('whatsapp_status', 'pending')
            ->where(function ($q) use ($now) {
                $q->whereIn('status', ['delivered', 'returned'])
                  ->orWhere('created_at', '<', $now->copy()->subHours(12))
                  ->orWhereNull('whatsapp_scheduled_at');
            })
            ->update(['whatsapp_status' => 'sent', 'whatsapp_sent_at' => $now]);

        if ($expired > 0) {
            $this->line("Closed {$expired} stale pending WhatsApp notification(s).");
        }

        // 2. المكاتب التي لديها رسالة مستحقة الآن
        $officeIds = Parcel::query()
            ->where('whatsapp_status', 'pending')
            ->where('whatsapp_scheduled_at', '<=', $now)
            ->distinct()
            ->pluck('office_id');

        if ($officeIds->isEmpty()) {
            return Command::SUCCESS;
        }

        $offices = Office::whereIn('id', $officeIds)->get()->keyBy('id');

        foreach ($officeIds as $officeId) {
            $officeId = (int) $officeId;
            $office = $offices->get($officeId);

            try {
                $this->processOffice($officeId, $office, $whatsAppService, $composer, $scheduler);
            } catch (\Throwable $e) {
                Log::error("whatsapp:process-queue office {$officeId} error: " . $e->getMessage());
                $this->error("Office {$officeId}: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    private function processOffice(
        int $officeId,
        ?Office $office,
        WhatsAppService $whatsAppService,
        WhatsAppMessageComposer $composer,
        WhatsAppQueueScheduler $scheduler
    ): void {
        $wa = $office ? $whatsAppService->forOffice($office) : null;

        // مكتب محذوف/معطل/غير مهيأ أو واتسابه مفصول حسب الكاش: إلغاء جميع رسائله المستحقة
        if (! $office || ! $office->is_active || ! $wa->isConfigured() || $scheduler->isOfficeOffline($officeId)) {
            $cancelled = $this->cancelDueForOffice($officeId);
            $this->warn("Office {$officeId}: WhatsApp unavailable, cancelled {$cancelled} due notification(s).");
            return;
        }

        // قاعدة صارمة: طرد واحد فقط لكل مكتب في هذه الدقيقة
        $parcel = Parcel::query()
            ->where('office_id', $officeId)
            ->where('whatsapp_status', 'pending')
            ->where('whatsapp_scheduled_at', '<=', now())
            ->orderBy('whatsapp_scheduled_at')
            ->first();

        if (! $parcel) {
            return;
        }

        // حجز ذري للطرد قبل الإرسال لمنع إرساله مرتين تحت أي ظرف
        $claimed = Parcel::query()
            ->whereKey($parcel->id)
            ->where('whatsapp_status', 'pending')
            ->whereNotIn('status', ['delivered', 'returned'])
            ->where('created_at', '>=', now()->subHours(12))
            ->update(['whatsapp_status' => 'sent', 'whatsapp_sent_at' => now()]);

        if ($claimed === 0) {
            // تغيرت حالته (تم تسليمه أو انتهت صلاحيته) بين الجلب والحجز
            Parcel::query()->whereKey($parcel->id)->where('whatsapp_status', 'pending')
                ->update(['whatsapp_status' => 'sent', 'whatsapp_sent_at' => now()]);
            return;
        }

        $message = $composer->composeArrival($office, $parcel, $wa);

        // delay يجعل Evolution Go يُظهر "يكتب الآن..." للمستلم قبل وصول الرسالة
        $result = $wa->sendText($parcel->recipient_phone, $message, random_int(4000, 9000));

        if (! empty($result['success'])) {
            $scheduler->markOfficeOnline($officeId);
            Parcel::query()->whereKey($parcel->id)->update(['whatsapp_sent_at' => now()]);
            $this->info("Office {$officeId}: sent WhatsApp for parcel {$parcel->id}.");
            return;
        }

        if ($this->isDisconnectError($result)) {
            // الطرد الحالي يبقى sent (ملغى) ونلغي باقي رسائل المكتب المستحقة
            $scheduler->markOfficeOffline($officeId);
            $cancelled = $this->cancelDueForOffice($officeId);
            Log::warning("whatsapp:process-queue office {$officeId} disconnected: " . ($result['message'] ?? 'unknown') . " — cancelled {$cancelled} more.");
            $this->warn("Office {$officeId}: WhatsApp disconnected, cancelled current + {$cancelled} due notification(s).");
            return;
        }

        // فشل لسبب آخر (رقم غير مسجل في واتساب مثلاً)
        Parcel::query()->whereKey($parcel->id)->update(['whatsapp_status' => 'failed']);
        Log::warning("whatsapp:process-queue parcel {$parcel->id} failed: " . ($result['message'] ?? 'unknown'));
        $this->warn("Office {$officeId}: failed parcel {$parcel->id}.");
    }

    private function cancelDueForOffice(int $officeId): int
    {
        return Parcel::query()
            ->where('office_id', $officeId)
            ->where('whatsapp_status', 'pending')
            ->where('whatsapp_scheduled_at', '<=', now())
            ->update(['whatsapp_status' => 'sent', 'whatsapp_sent_at' => now()]);
    }

    private function isDisconnectError(array $result): bool
    {
        // استثناء في الاتصال بالسيرفر نفسه (لا يوجد كود HTTP)
        if (! isset($result['status'])) {
            return true;
        }

        if (in_array((int) $result['status'], [401, 403], true)) {
            return true;
        }

        $message = strtolower((string) ($result['message'] ?? '') . ' ' . json_encode($result['data'] ?? ''));
        foreach (self::DISCONNECT_HINTS as $hint) {
            if (str_contains($message, $hint)) {
                return true;
            }
        }

        return false;
    }
}

