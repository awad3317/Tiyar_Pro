<?php

namespace App\Services;

use App\Enums\ParcelStatus;
use App\Models\Office;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DailyReportService
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * حساب إحصائيات طرود اليوم لمكتب محدد
     */
    public function getTodayStats(Office $office): array
    {
        $today = Carbon::today();

        // 1. عدد الطرود المستلمة / المضافة للمكتب اليوم
        $receivedToday = $office->parcels()
            ->whereDate('created_at', $today)
            ->count();

        // 2. عدد الطرود المسلمة للعملاء اليوم
        $deliveredToday = $office->parcels()
            ->where('status', ParcelStatus::Delivered)
            ->where(function ($q) use ($today) {
                $q->whereDate('delivered_at', $today)
                  ->orWhere(fn ($sq) => $sq->whereNull('delivered_at')->whereDate('updated_at', $today));
            })
            ->count();

        // 3. عدد الطرود المرجعة اليوم
        $returnedToday = $office->parcels()
            ->where('status', ParcelStatus::Returned)
            ->whereDate('updated_at', $today)
            ->count();

        // 4. الطرود المتواجدة بالمكتب حالياً
        $inOfficeTotal = $office->parcels()
            ->where('status', ParcelStatus::InOffice)
            ->count();

        return [
            'received_today'  => $receivedToday,
            'delivered_today' => $deliveredToday,
            'returned_today'  => $returnedToday,
            'in_office_total' => $inOfficeTotal,
        ];
    }

    /**
     * بناء نص الرسالة الموجهة لمدير الفرع
     */
    public function buildMessage(Office $office, array $stats): string
    {
        $todayArabic = Carbon::now()->locale('ar')->translatedFormat('l، d F Y');
        $branchInfo  = $office->branch_name ? "({$office->branch_name})" : '';

        return implode("\n", [
            "📊 *التقرير اليومي لحركة الطرود*",
            "🏢 *المكتب:* {$office->name} {$branchInfo}",
            "📅 *التاريخ:* {$todayArabic}",
            "⏰ *الوقت:* 10:00 مساءً",
            "━━━━━━━━━━━━━━━━━━━━",
            "📥 *الطرود المستلمة اليوم:* {$stats['received_today']}",
            "✅ *الطرود المسلمة للعملاء:* {$stats['delivered_today']}",
            "↩️ *الطرود المرجعة اليوم:* {$stats['returned_today']}",
            "📦 *الطرود المتواجدة بالمكتب حالياً:* {$stats['in_office_total']}",
            "━━━━━━━━━━━━━━━━━━━━",
            "✨ *نظام طيار برو الذكي لإدارة الشحنات*",
        ]);
    }

    /**
     * إرسال التقرير اليومي لمدير الفرع عبر الواتساب
     */
    public function sendReport(Office $office, bool $isTest = false): array
    {
        if (empty($office->manager_phone)) {
            return [
                'success' => false,
                'message' => "لم يتم تحديد رقم هاتف مدير الفرع للمكتب [{$office->name}].",
            ];
        }

        $stats   = $this->getTodayStats($office);
        $message = $this->buildMessage($office, $stats);

        if ($isTest) {
            $message = "⚠️ *(إرسال تجريبي من لوحة التحكم)*\n\n" . $message;
        }

        // اختيار نسخة الواتساب: إما التابعة للمكتب إن كانت مهيأة، أو العامة
        $wa = $this->whatsAppService->forOffice($office);
        if (!$wa->isConfigured()) {
            $wa = (clone $this->whatsAppService)->loadDefaultConfig();
        }

        if (!$wa->isConfigured()) {
            return [
                'success' => false,
                'message' => 'بيانات خادم الواتساب غير مكتملة (سواء للمكتب أو الإعدادات العامة).',
                'stats'   => $stats,
            ];
        }

        $formattedPhone = $this->whatsAppService->formatNumber($office->manager_phone);

        try {
            $response = $wa->sendText($formattedPhone, $message);

            $isSuccess = !empty($response['success']) || (empty($response['error']) && empty($response['message']));

            if ($isSuccess) {
                Log::info("Daily report sent to manager of office [{$office->name}] at {$formattedPhone}");
                return [
                    'success' => true,
                    'message' => "تم إرسال التقرير بنجاح لمدير الفرع ({$formattedPhone}).",
                    'stats'   => $stats,
                    'text'    => $message,
                ];
            }

            $errMsg = $response['message'] ?? 'فشل خادم الواتساب في تسليم الرسالة';
            Log::warning("Failed to send daily report to office [{$office->name}]: {$errMsg}");

            return [
                'success' => false,
                'message' => "تعذر تسليم التقرير: {$errMsg}",
                'stats'   => $stats,
                'raw'     => $response,
            ];
        } catch (\Throwable $e) {
            Log::error("Exception sending daily report to office [{$office->name}]: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء الإرسال: ' . $e->getMessage(),
                'stats'   => $stats,
            ];
        }
    }
}
