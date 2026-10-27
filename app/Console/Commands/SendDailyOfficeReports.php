<?php

namespace App\Console\Commands;

use App\Models\Office;
use App\Services\DailyReportService;
use Illuminate\Console\Command;

class SendDailyOfficeReports extends Command
{
    /**
     * اسم ووصف الأمر البرمجي
     */
    protected $signature = 'office:send-daily-reports 
                            {--office= : تحديد معرّف ID مكتب معين لإرسال التقرير له فقط}
                            {--force : إرسال التقرير حتى لو كان خيار التقرير اليومي معطلاً (مفيد للاختبار)}';

    protected $description = 'إرسال التقرير اليومي لحركة الطرود (المستلمة، المسلمة، المرجعة) لمدراء الفروع عبر الواتساب الساعة 10:00 مساءً';

    public function handle(DailyReportService $reportService): int
    {
        $officeId = $this->option('office');
        $force    = $this->option('force');

        $query = Office::query()->where('is_active', true);

        if ($officeId) {
            $query->where('id', $officeId);
        } else {
            // جلب المكاتب المفعل فيها التقرير اليومي والتي تملك رقم هاتف مدير
            $query->where('send_daily_report', true)
                  ->whereNotNull('manager_phone')
                  ->where('manager_phone', '!=', '');
        }

        $offices = $query->get();

        if ($offices->isEmpty()) {
            $this->warn('لا توجد مكاتب مؤهلة لإرسال التقرير اليومي حالياً.');
            return Command::SUCCESS;
        }

        $this->info("بدء معالجة التقرير اليومي لعدد ({$offices->count()}) مكتب/فروع...");

        $successCount = 0;
        $failedCount  = 0;

        foreach ($offices as $office) {
            if (!$force && !$office->send_daily_report && $officeId) {
                $this->warn("المكتب [{$office->name}] خيار التقرير اليومي لديه معطل. استخدم --force للتجاوز.");
                continue;
            }

            if (empty($office->manager_phone)) {
                $this->error("المكتب [{$office->name}] ليس لديه رقم هاتف مدير مسجل.");
                $failedCount++;
                continue;
            }

            $this->line("جاري إرسال التقرير لمكتب: {$office->name} (هاتف المدير: {$office->manager_phone})...");

            $result = $reportService->sendReport($office, isTest: (bool) $officeId);

            if ($result['success']) {
                $this->info("✔ تم إرسال التقرير لمكتب [{$office->name}] بنجاح.");
                $successCount++;
            } else {
                $this->error("✖ فشل إرسال التقرير لمكتب [{$office->name}]: {$result['message']}");
                $failedCount++;
            }
        }

        $this->newLine();
        $this->info("انتهت عملية الإرسال: نجح ({$successCount}) | تعذر ({$failedCount})");

        return Command::SUCCESS;
    }
}
