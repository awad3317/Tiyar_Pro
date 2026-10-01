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
     * عميل HTTP جاهز مع الـ Headers المطلوبة
     */
    protected function client(string $instanceId)
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->withHeaders([
                'apikey'       => $this->secretToken,
                'instance'     => $instanceId,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * فحص حالة الاتصال
     * Endpoint: GET /instance/status
     */
    public function getStatus(string $instanceId): array
    {
        try {
            $response = $this->client($instanceId)->get('/instance/status');
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
     * جلب كود الـ QR
     * Endpoint: GET /instance/qr
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
     * طلب كود الربط المباشر Pairing Code (بدون كاميرا عبر رقم الهاتف)
     * Endpoint: POST /instance/pair
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
     * إعادة محاولة الاتصال
     * Endpoint: POST /instance/reconnect
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
     * تسجيل الخروج وفصل الجلسة
     * Endpoint: DELETE /instance/logout
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