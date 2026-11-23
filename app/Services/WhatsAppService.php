<?php

namespace App\Services;

use App\Models\Office;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppService
{
    protected ?Office $currentOffice = null;
    protected string $baseUrl;
    protected ?string $instanceId = null;
    protected ?string $instanceName = null;
    protected ?string $token = null;
    protected ?string $webhookUrl = null;
    protected int $timeout = 30;

    /**
     * التهيئة الديناميكية مع دعم تعدد المكاتب (Multi-Office Instances)
     * كل مكتب لديه إعدادات واتساب مستقلة خاصة به
     */
    public function __construct(?Office $office = null)
    {
        if ($office) {
            $this->loadOfficeConfig($office);
        } elseif (Auth::guard('office')->check()) {
            $this->loadOfficeConfig(Auth::guard('office')->user());
        } elseif (Auth::check() && isset(Auth::user()->office)) {
            $this->loadOfficeConfig(Auth::user()->office);
        } else {
            $configuredOffice = Office::where('is_active', true)
                ->whereNotNull('whatsapp_api_key')
                ->where('whatsapp_api_key', '!=', '')
                ->first();

            if ($configuredOffice) {
                $this->loadOfficeConfig($configuredOffice);
            } else {
                $this->loadDefaultConfig();
            }
        }
    }

    /**
     * تحميل إعدادات مكتب محدد
     */
    public function loadOfficeConfig(Office $office): self
    {
        $this->currentOffice = $office;
        $this->baseUrl      = rtrim($office->whatsapp_base_url ?: config('whatsapp.base_url', 'http://195.35.24.73:4000'), '/');
        $this->instanceId   = $office->whatsapp_instance_id ?: config('whatsapp.instance_id');
        $this->instanceName = $office->whatsapp_instance_name;
        $this->token        = $office->whatsapp_api_key ?: config('whatsapp.global_api_key');
        $this->webhookUrl   = $office->whatsapp_webhook_url ?: config('whatsapp.webhook_url');
        $this->timeout      = $office->whatsapp_timeout ?: (int) config('whatsapp.timeout', 30);

        return $this;
    }

    /**
     * تحميل الإعدادات الافتراضية العامة للنظام من ملف config
     */
    public function loadDefaultConfig(): self
    {
        $this->currentOffice = null;
        $this->baseUrl      = rtrim(config('whatsapp.base_url', 'http://195.35.24.73:4000'), '/');
        $this->instanceId   = config('whatsapp.instance_id');
        $this->instanceName = null;
        $this->token        = config('whatsapp.global_api_key');
        $this->webhookUrl   = config('whatsapp.webhook_url');
        $this->timeout      = (int) config('whatsapp.timeout', 30);

        return $this;
    }

    /**
     * إرجاع نسخة معزولة خاصة بمكتب محدد لمنع تسرب الإعدادات بين الطلبات والمهام الخلفية (Queue/Jobs)
     */
    public function forOffice(Office|int|null $office): self
    {
        $clone = clone $this;

        if ($office instanceof Office) {
            $clone->loadOfficeConfig($office);
        } elseif (is_numeric($office)) {
            $officeModel = Office::find($office);
            if ($officeModel) {
                $clone->loadOfficeConfig($officeModel);
            }
        } elseif ($office === null) {
            $clone->loadDefaultConfig();
        }

        return $clone;
    }

    /**
     * إرجاع نسخة معزولة بإعدادات مخصصة
     */
    public function withConfig(array $config): self
    {
        $clone = clone $this;

        if (isset($config['base_url'])) {
            $clone->baseUrl = rtrim($config['base_url'], '/');
        }
        if (isset($config['instance_id'])) {
            $clone->instanceId = $config['instance_id'];
        }
        if (isset($config['instance_name'])) {
            $clone->instanceName = $config['instance_name'];
        }
        if (isset($config['instance_token'])) {
            $clone->token = $config['instance_token'];
        }
        if (isset($config['webhook_url'])) {
            $clone->webhookUrl = $config['webhook_url'];
        }
        if (isset($config['timeout'])) {
            $clone->timeout = (int) $config['timeout'];
        }

        return $clone;
    }

    /**
     * التحقق مما إذا كانت الخدمة مهيأة ببيانات الاتصال الأساسية
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->token);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }

    public function getInstanceName(): ?string
    {
        return $this->instanceName;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getCurrentOffice(): ?Office
    {
        return $this->currentOffice;
    }

    /**
     * إعداد عميل الـ HTTP بالـ Headers الأساسية
     */
    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders([
                'Apikey'       => $this->token ?? '',
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ]);
    }

    /**
     * معالجة استجابة الـ HTTP
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->failed()) {
            return [
                'success' => false,
                'error'   => true,
                'message' => data_get($response->json(), 'message', data_get($response->json(), 'error', 'HTTP Error ' . $response->status())),
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        }
        $json = $response->json() ?? [];
        return array_merge(['success' => true, 'error' => false], is_array($json) ? $json : ['data' => $json]);
    }

    /* =========================================================================
     * 1. دوال التحكم بالإنستانس وإدارة الجلسة (Instance & Session Management)
     * ========================================================================= */

    public function getStatus(): array
    {
        try {
            $response = $this->client()->get('/instance/status');

            if ($response->status() === 404 && !empty($this->instanceId)) {
                $fallback = $this->client()->get("/instance/connectionState/{$this->instanceId}");
                if ($fallback->successful()) {
                    return $this->handleResponse($fallback);
                }
            }

            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService getStatus Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function connect(?string $webhookUrl = null, array $events = ['MESSAGES_UPSERT', 'CONNECTION_UPDATE', 'QRCODE_UPDATED']): array
    {
        try {
            $payload = [
                'webhookUrl' => $webhookUrl ?? $this->webhookUrl,
                'subscribe'  => $events,
                'immediate'  => true,
            ];

            $response = $this->client()->post('/instance/connect', $payload);
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService connect Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function getQrCode(): array
    {
        try {
            $response = $this->client()->get('/instance/qr');
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService getQrCode Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function getPairingCode(string $phone): array
    {
        try {
            $response = $this->client()->post('/instance/pair', [
                'phone' => $this->formatNumber($phone),
            ]);
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService getPairingCode Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function disconnect(): array
    {
        try {
            $response = $this->client()->post('/instance/disconnect');
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService disconnect Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function reconnect(): array
    {
        try {
            $response = $this->client()->post('/instance/reconnect');
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService reconnect Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function logout(): array
    {
        try {
            $response = $this->client()->delete('/instance/logout');
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService logout Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function getInstanceInfo(): array
    {
        try {
            $url = $this->instanceId ? "/instance/info/{$this->instanceId}" : '/instance/status';
            $response = $this->client()->get($url);
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService getInstanceInfo Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function updateAdvancedSettings(array $settings): array
    {
        try {
            $url = $this->instanceId ? "/instance/{$this->instanceId}/advanced-settings" : '/instance/advanced-settings';
            $response = $this->client()->put($url, $settings);
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error('WhatsAppService updateAdvancedSettings Error: ' . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    /* =========================================================================
     * 2. دوال إرسال الرسائل والقوالب (Messaging & Templates)
     * ========================================================================= */

    public function sendText(string $phone, string $text, ?int $delay = null): array
    {
        try {
            $payload = [
                'number' => $this->formatNumber($phone),
                'text'   => $text,
            ];

            if ($delay) {
                $payload['delay'] = $delay;
            }

            $response = $this->client()->post('/send/text', $payload);
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error("WhatsAppService sendText to {$phone} Error: " . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * تجهيز وإرسال رسالة باستخدام قالب الواتساب الخاص بالمكتب الحالي (whatsapp_template)
     * 
     * @param string $phone رقم المستلم
     * @param array $variables المتغيرات المراد استبدالها في القالب مثل ['{name}' => 'أحمد', '{tracking}' => '12345']
     */
    public function sendOfficeTemplate(string $phone, array $variables = [], ?int $delay = null): array
    {
        $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
        $template = !empty($this->currentOffice?->whatsapp_template)
            ? $this->currentOffice->whatsapp_template
            : (!empty($this->currentOffice?->sms_template) ? $this->currentOffice->sms_template : $defaultTemplate);

        $message = $this->parseTemplate($template, $variables);

        return $this->sendText($phone, $message, $delay);
    }

    /**
     * استبدال المتغيرات داخل نص القالب
     */
    public function parseTemplate(string $template, array $variables = []): string
    {
        // إضافة متغيرات المكتب الافتراضية تلقائياً
        if ($this->currentOffice) {
            $variables['{office}']      = $variables['{office}'] ?? $this->currentOffice->name;
            $variables['{office_name}'] = $variables['{office_name}'] ?? $this->currentOffice->name;
            $variables['{branch}']      = $variables['{branch}'] ?? ($this->currentOffice->branch_name ?? '');
            $variables['{branch_name}'] = $variables['{branch_name}'] ?? ($this->currentOffice->branch_name ?? '');
        }

        foreach ($variables as $key => $value) {
            // يدعم الصيغتين {key} أو key مباشرة
            $placeholder = str_starts_with($key, '{') ? $key : '{' . $key . '}';
            $template = str_replace($placeholder, (string) $value, $template);
        }

        $template = str_replace(' ()', '', $template);

        return $template;
    }

    public function sendMedia(string $phone, string $fileUrl, string $type = 'document', ?string $caption = null, ?string $filename = null): array
    {
        try {
            $payload = [
                'number' => $this->formatNumber($phone),
                'type'   => $type,
                'url'    => $fileUrl,
            ];

            if ($caption) {
                $payload['caption'] = $caption;
            }

            if ($filename) {
                $payload['filename'] = $filename;
            }

            $response = $this->client()->post('/send/media', $payload);
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error("WhatsAppService sendMedia to {$phone} Error: " . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function sendButton(string $phone, string $title, string $description, string $footer, array $buttons): array
    {
        try {
            $payload = [
                'number'      => $this->formatNumber($phone),
                'title'       => $title,
                'description' => $description,
                'footer'      => $footer,
                'buttons'     => $buttons,
            ];

            $response = $this->client()->post('/send/button', $payload);
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error("WhatsAppService sendButton to {$phone} Error: " . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    public function checkNumber(string $phone): array
    {
        try {
            $response = $this->client()->post('/user/check', [
                'number' => [$this->formatNumber($phone)],
            ]);
            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::error("WhatsAppService checkNumber {$phone} Error: " . $e->getMessage());
            return ['success' => false, 'error' => true, 'message' => $e->getMessage()];
        }
    }

    /* =========================================================================
     * 3. دوال تنسيق ومعالجة أرقام الهواتف (Phone Formatting)
     * ========================================================================= */

    public function formatNumber(string $phone): string
    {
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);

        if (empty($cleanPhone)) {
            return '';
        }

        while (str_starts_with($cleanPhone, '00')) {
            $cleanPhone = substr($cleanPhone, 2);
        }

        while (str_starts_with($cleanPhone, '967967')) {
            $cleanPhone = substr($cleanPhone, 3);
        }

        while (str_starts_with($cleanPhone, '966966')) {
            $cleanPhone = substr($cleanPhone, 3);
        }

        if (str_starts_with($cleanPhone, '9670')) {
            $cleanPhone = '967' . substr($cleanPhone, 4);
        }

        if (str_starts_with($cleanPhone, '9660')) {
            $cleanPhone = '966' . substr($cleanPhone, 4);
        }

        if (str_starts_with($cleanPhone, '05')) {
            $cleanPhone = '966' . substr($cleanPhone, 1);
        } elseif (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '967' . substr($cleanPhone, 1);
        } elseif (str_starts_with($cleanPhone, '7') && !str_starts_with($cleanPhone, '967')) {
            $cleanPhone = '967' . $cleanPhone;
        } elseif (str_starts_with($cleanPhone, '5') && !str_starts_with($cleanPhone, '966') && strlen($cleanPhone) <= 10) {
            $cleanPhone = '966' . $cleanPhone;
        } elseif (!str_starts_with($cleanPhone, '967') && !str_starts_with($cleanPhone, '966') && strlen($cleanPhone) <= 9) {
            $cleanPhone = '967' . $cleanPhone;
        }

        return $cleanPhone;
    }

    public function formatPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        return $this->formatNumber($phone);
    }

    public function createWhatsAppLink(?string $phone, ?string $message): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $formattedPhone = $this->formatNumber($phone);
        $encodedMessage = urlencode($message ?? '');

        return "https://api.whatsapp.com/send?phone={$formattedPhone}&text={$encodedMessage}";
    }
}