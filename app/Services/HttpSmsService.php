<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpSmsService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $sim;

    public function __construct()
    {
        $this->baseUrl = config('services.httpsms.base_url');
        $this->apiKey  = config('services.httpsms.api_key');
        $this->sim     = config('services.httpsms.sim');
    }

    /**
     * إرسال رسالة نصية عبر بوابة httpSMS
     */
    public function send(string $recipient, string $message): array
    {
        $formattedRecipient = $this->formatYemenNumber($recipient);

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->baseUrl}/messages/send", [
                'recipient' => $formattedRecipient,
                'content'   => $message,
                'sim'       => $this->sim,
            ]);

            if ($response->successful()) {
                Log::info("SMS sent to {$formattedRecipient} via {$this->sim}");
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
     * توحيد صيغة أرقام الهواتف اليمنية لتناسب البوابة (+967XXXXXXXXX)
     */
    protected function formatYemenNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        if (!str_starts_with($cleaned, '967')) {
            $cleaned = '967' . $cleaned;
        }

        return '+' . $cleaned;
    }
}