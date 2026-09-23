<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpSmsService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.httpsms.base_url', 'https://abdaa.tiyar.cc/v1');
    }

    /**
     * إرسال رسالة SMS باستخدام بيانات المكتب المحددة ديناميكياً
     */
    public function send(string $recipient, string $message, string $apiKey, string $fromPhone): array
    {
        $toCleaned = $this->formatToNumber($recipient);

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->baseUrl}/messages/send", [
                'from'    => $fromPhone,
                'to'      => $toCleaned,
                'content' => $message,
            ]);

            if ($response->successful()) {
                Log::info("SMS sent to {$toCleaned} via phone {$fromPhone}");
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error("SMS Gateway Error: " . $response->body());
            return ['success' => false, 'error' => $response->body()];

        } catch (\Throwable $e) {
            Log::critical("SMS Service Failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

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