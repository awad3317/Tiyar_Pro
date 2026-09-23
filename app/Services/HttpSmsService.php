<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpSmsService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $fromPhone;

    public function __construct()
    {
        $this->baseUrl   = config('services.httpsms.base_url', 'https://abdaa.tiyar.cc/v1');
        $this->apiKey    = config('services.httpsms.api_key', env('HTTPSMS_API_KEY'));
        $this->fromPhone = env('HTTPSMS_FROM_PHONE', '+967781152674');
    }

    /**
     * إرسال رسالة نصية عبر بوابة httpSMS
     */
    public function send(string $recipient, string $message): array
    {
        // حقل to يتطلب أرقاماً فقط بدون + وأقل من 14 خانة
        $toCleaned = $this->formatToNumber($recipient);

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->baseUrl}/messages/send", [
                'from'    => $this->fromPhone,
                'to'      => $toCleaned,
                'content' => $message,
            ]);

            if ($response->successful()) {
                Log::info("SMS sent to {$toCleaned} successfully.");
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error("SMS Gateway Error: " . $response->body());
            return ['success' => false, 'error' => $response->body()];

        } catch (\Throwable $e) {
            Log::critical("SMS Service Failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * تنظيف رقم المستلم: أرقام فقط مع إضافة رمز الدولة إذا لم يتوفر
     */
    protected function formatToNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        if (!str_starts_with($cleaned, '967')) {
            $cleaned = '967' . $cleaned;
        }

        return $cleaned;
    }
}