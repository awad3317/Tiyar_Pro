<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Office;
use App\Services\WhatsAppService;

class CheckWhatsAppStatus extends Command
{
    protected $signature = 'whatsapp:check-status';
    protected $description = 'فحص حالة اتصال واتساب لكل مكتب وحفظها في الكاش عبر WhatsAppService';

    public function handle(WhatsAppService $whatsAppService): int
    {
        // جلب جميع المكاتب النشطة التي تمتلك مفاتيح واتساب
        $offices = Office::where('is_active', true)
            ->whereNotNull('whatsapp_api_key')
            ->where('whatsapp_api_key', '!=', '')
            ->get();

        if ($offices->isEmpty()) {
            $this->warn('لا توجد مكاتب مهيأة بإعدادات واتساب.');
            return Command::SUCCESS;
        }

        foreach ($offices as $office) {
            $cacheKey = 'whatsapp_status_office_' . $office->id;

            try {
                // استخدام السيرفيس المعزولة الخاصة بالمكتب مع timeout سريع للفحص
                $service = $whatsAppService->forOffice($office)->withConfig(['timeout' => 4]);

                $result = $service->getStatus();

                // 1. إذا كان الرد يحمل خطأ صريحاً بالسيرفر
                if (isset($result['error']) && $result['error'] === true && !isset($result['data']['LoggedIn'])) {
                    $rawMsg = strtolower($result['message'] ?? data_get($result, 'data.error', ''));
                    // استثناء حالة إذا كان الرد يفيد بأن الجلسة مسجلة مسبقاً
                    if (str_contains($rawMsg, 'already logged') || str_contains($rawMsg, 'already connected')) {
                        Cache::put($cacheKey, 'online', now()->addMinutes(15));
                        $this->info("Office [{$office->name} - ID: {$office->id}]: WhatsApp is ONLINE ✅ (Session already logged in)");
                        continue;
                    }

                    Cache::put($cacheKey, 'offline', now()->addMinutes(15));
                    $errorMsg = $result['message'] ?? 'Connection error';
                    $this->warn("Office [{$office->name} - ID: {$office->id}]: WhatsApp is OFFLINE ❌ ({$errorMsg})");
                    continue;
                }

                // 2. استخراج الحقول الدقيقة لحالة تسجيل الدخول من Evolution API
                $isLoggedInRaw  = data_get($result, 'data.LoggedIn') ?? data_get($result, 'LoggedIn');
                $isLoggedIn     = filter_var($isLoggedInRaw, FILTER_VALIDATE_BOOLEAN);

                $isConnectedRaw = data_get($result, 'data.Connected') ?? data_get($result, 'Connected');
                $isConnected    = filter_var($isConnectedRaw, FILTER_VALIDATE_BOOLEAN);

                $instanceState  = strtolower(trim((string)(
                    data_get($result, 'instance.state') 
                    ?? data_get($result, 'data.instance.state') 
                    ?? data_get($result, 'state') 
                    ?? ''
                )));

                // 3. تحديد ما إذا كانت الجلسة مسجلة الدخول فعلياً
                // تنبيه هام: لا نعتمد على 'message' => 'success' أو status 200 لأنها تعني نجاح طلب الـ HTTP فقط وليس تسجيل الدخول!
                if ($isLoggedInRaw !== null) {
                    // إذا كان حقل LoggedIn موجوداً، فهو المعيار الحاسم (إذا كان false والجلسة متصلة بالسوكيت فهي غير مسجلة وتنتظر QR)
                    $isOnline = $isLoggedIn;
                    $state = $isLoggedIn ? 'connected' : ($isConnected ? 'waiting_qr' : 'disconnected');
                } else {
                    // في حال عدم وجود حقل LoggedIn صراحة
                    $isOnline = in_array($instanceState, ['open', 'connected'], true);
                    $state = $instanceState ?: ($isOnline ? 'connected' : 'disconnected');
                }

                // 4. حفظ النتيجة في الكاش وطباعة السجل
                if ($isOnline) {
                    Cache::put($cacheKey, 'online', now()->addMinutes(15));
                    $this->info("Office [{$office->name} - ID: {$office->id}]: WhatsApp is ONLINE ✅ (State: {$state})");
                } else {
                    Cache::put($cacheKey, 'offline', now()->addMinutes(15));
                    $reason = ($state === 'waiting_qr') ? 'الجلسة غير مسجلة وبانتظار مسح QR' : "State: {$state}";
                    $this->warn("Office [{$office->name} - ID: {$office->id}]: WhatsApp is OFFLINE ❌ ({$reason})");
                }

            } catch (\Exception $e) {
                Cache::put($cacheKey, 'offline', now()->addMinutes(15));
                $this->error("Office [{$office->name} - ID: {$office->id}]: WhatsApp is OFFLINE ❌ ({$e->getMessage()})");
            }
        }

        return Command::SUCCESS;
    }
}
