<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappGatewayService
{
    protected string $baseUrl;
    protected ?string $globalApiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.whatsapp.url', 'http://195.35.24.73:4000'), '/');
        $this->globalApiKey = config('services.whatsapp.token');
    }

    /**
     * عميل HTTP:
     * إذا تم تمرير توكن الجلسة، يتم اعتماده كـ Apikey للطلب (للعمليات الخاصة بالجلسة)
     * وإلا يتم استخدام المفتاح الإداري العام
     */
    protected function client(string $instanceToken = null)
    {
        $keyToUse = !empty($instanceToken) ? $instanceToken : $this->globalApiKey;

        return Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->withHeaders([
                'Apikey'       => $keyToUse,
                'Accept'       => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * فحص الحالة: يعتمد على المفتاح العام + معرف الـ id
     */
    public function getStatus(string $instanceId): array
    {
        try {
            $response = $this->client()->get("/instance/info/{$instanceId}");

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway getStatus Error: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * جلب كود الـ QR: يعتمد على توكن الجلسة كـ Apikey
     */
    public function getQrCode(string $instanceToken): array
    {
        try {
            $response = $this->client($instanceToken)->get('/instance/qr');

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway getQrCode Error: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * فصل الجلسة وتسجيل الخروج: يعتمد على توكن الجلسة كـ Apikey
     */
    public function logout(string $instanceToken): array
    {
        try {
            $response = $this->client($instanceToken)->delete('/instance/logout');

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway logout Error: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * طلب كود الربط Pairing Code: يعتمد على توكن الجلسة كـ Apikey
     */
    public function requestPairingCode(string $instanceToken, string $phoneNumber): array
    {
        try {
            $response = $this->client($instanceToken)->post('/instance/pair', [
                'phone' => $phoneNumber,
            ]);

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Whatsapp Gateway requestPairingCode Error: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}