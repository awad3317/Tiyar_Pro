<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('whatsapp:check-status')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// معالجة طابور إشعارات الواتساب: طرد واحد لكل مكتب في الدقيقة لحماية أرقام المكاتب من الحظر
Schedule::command('whatsapp:process-queue')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();

// إرسال التقرير اليومي لحركة الطرود لمدراء المكاتب يومياً الساعة 10:00 مساءً
Schedule::command('office:send-daily-reports')
    ->dailyAt('22:00')
    ->timezone('Asia/Riyadh')
    ->withoutOverlapping()
    ->runInBackground();


