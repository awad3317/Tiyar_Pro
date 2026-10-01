<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappGatewayService
{
    protected string $baseUrl;
    protected ?string $secretToken;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.whatsapp.url', 'http://195.35.24.73:4000'), '/');
        $this->secretToken = config('services.whatsapp.token');
    }

    /**
     * عميل HTTP جاهز مع مفتاح الأمان ومعرف الجلسة
     */
    protected function client(string $instanceId = null)
    {
        $headers = [
            'Apikey'       => $this->secretToken,
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];

        if ($instanceId) {
            $headers['instance']   = $instanceId;
            $headers['instanceId'] = $instanceId;
        }

        return Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->withHeaders($headers);
    }

    /**
     * فحص الحالة
     */
    public function getStatus(string $instanceId): array
    {
        try {
            $response = $this->client($instanceId)->get("/instance/info/{$instanceId}");

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway getStatus Error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * كود الـ QR
     */
    public function getQrCode(string $instanceId): array
    {
        try {
            $response = $this->client($instanceId)->get('/instance/qr');

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway getQrCode Error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * طلب كود الربط المباشر Pairing Code
     */
    public function requestPairingCode(string $instanceId, string $phoneNumber): array
    {
        try {
            $response = $this->client($instanceId)->post('/instance/pair', [
                'phone' => $phoneNumber,
            ]);

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway requestPairingCode Error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * إعادة الاتصال
     */
    public function reconnect(string $instanceId): array
    {
        try {
            $response = $this->client($instanceId)->post('/instance/reconnect');

            return [
                'success' => $response->successful(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway reconnect Error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * فصل الجلسة وتسجيل الخروج
     */
    public function logout(string $instanceId): array
    {
        try {
            $response = $this->client($instanceId)->delete('/instance/logout');

            return [
                'success' => $response->successful(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway logout Error: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}