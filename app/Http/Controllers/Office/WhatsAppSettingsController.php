<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppSettingsController extends Controller
{
    /**
     * عرض صفحة إعدادات الواتساب
     */
    public function index(WhatsAppService $whatsAppService)
    {
        $office = Auth::guard('office')->user();
        $wa = $whatsAppService->forOffice($office);

        return view('office.whatsapp-settings', [
            'office'       => $office,
            'isConfigured' => $wa->isConfigured(),
        ]);
    }

    /**
     * جلب حالة الاتصال الحالية (AJAX)
     * تدعم الكاش للتسريع وتتحقق بدقة من حالة LoggedIn
     */
    public function status(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $office = Auth::guard('office')->user();

        if (!$office) {
            return response()->json([
                'success'   => false,
                'connected' => false,
                'error'     => 'تعذر تحديد المكتب الحالي.',
            ], 403);
        }

        $cacheKey = 'whatsapp_status_office_' . $office->id;

        // 1. التحقق من اكتمال الإعدادات
        if (!$office->hasWhatsAppConfigured()) {
            Cache::put($cacheKey, 'offline', now()->addMinutes(10));

            return response()->json([
                'success'       => false,
                'connected'     => false,
                'is_configured' => false,
                'message'       => 'إعدادات WhatsApp غير مكتملة للمكتب.',
                'state'         => 'not_configured',
            ]);
        }

        // 2. استخدام الكاش لتسريع الاستجابة ما لم يُطلب التحديث المباشر
        $force = $request->boolean('refresh') || $request->boolean('force');
        if (!$force && Cache::has($cacheKey)) {
            $cachedStatus = Cache::get($cacheKey);
            $isConnected = ($cachedStatus === 'online');

            return response()->json([
                'success'      => true,
                'connected'    => $isConnected,
                'state'        => $isConnected ? 'connected' : 'disconnected',
                'is_logged_in' => $isConnected,
                'account_name' => $office->name,
                'from_cache'   => true,
                'message'      => $isConnected ? 'متصل بنجاح' : 'غير متصل (الجلسة مغلقة أو بانتظار مسح رمز QR)',
            ]);
        }

        // 3. طلب حالة الاتصال من السيرفيس مع timeout سريع (4 ثوان)
        $wa = $whatsAppService->forOffice($office)->withConfig(['timeout' => 4]);

        try {
            $result = $wa->getStatus();

            // 4. إذا كان الرد يحمل خطأ صريحاً بالسيرفر
            if (!empty($result['error']) && !isset($result['data']['LoggedIn'])) {
                $rawMsg = strtolower($result['message'] ?? data_get($result, 'data.error', ''));
                if (str_contains($rawMsg, 'already logged') || str_contains($rawMsg, 'already connected')) {
                    Cache::put($cacheKey, 'online', now()->addMinutes(15));

                    return response()->json([
                        'success'      => true,
                        'connected'    => true,
                        'state'        => 'connected',
                        'is_logged_in' => true,
                        'account_name' => $office->name,
                        'message'      => 'الجلسة متصلة ونشطة بالفعل',
                        'data'         => $result,
                    ]);
                }

                Cache::put($cacheKey, 'offline', now()->addMinutes(15));

                return response()->json([
                    'success'   => false,
                    'connected' => false,
                    'error'     => $result['message'] ?? 'فشل الاتصال بخدمة WhatsApp',
                    'state'     => 'error',
                    'raw'       => $result,
                    'message'   => 'غير متصل: ' . ($result['message'] ?? 'فشل الاتصال'),
                ], 200);
            }

            // 5. استخراج الحقول الدقيقة لحالة تسجيل الدخول
            $isLoggedInRaw  = data_get($result, 'data.LoggedIn') ?? data_get($result, 'LoggedIn');
            $isLoggedIn     = filter_var($isLoggedInRaw, FILTER_VALIDATE_BOOLEAN);

            $isConnectedRaw = data_get($result, 'data.Connected') ?? data_get($result, 'Connected');
            $isConnected    = filter_var($isConnectedRaw, FILTER_VALIDATE_BOOLEAN);
            $accountName    = data_get($result, 'data.Name') ?? data_get($result, 'Name') ?? $office->name;

            $instanceState  = strtolower(trim((string)(
                data_get($result, 'instance.state') 
                ?? data_get($result, 'data.instance.state') 
                ?? data_get($result, 'state') 
                ?? ''
            )));

            // 6. تحديد الحالة الفعلية:
            // تنبيه: لا نعتمد على 'message' => 'success' لأنها تعني نجاح طلب الـ HTTP فقط وليس تسجيل الدخول!
            if ($isLoggedInRaw !== null) {
                $isOnline = $isLoggedIn;
                $state = $isLoggedIn ? 'connected' : ($isConnected ? 'waiting_qr' : 'disconnected');
            } else {
                $isOnline = in_array($instanceState, ['open', 'connected'], true);
                $state = $instanceState ?: ($isOnline ? 'connected' : 'disconnected');
            }

            // حفظ النتيجة في الكاش
            if ($isOnline) {
                Cache::put($cacheKey, 'online', now()->addMinutes(15));
            } else {
                Cache::put($cacheKey, 'offline', now()->addMinutes(15));
            }

            return response()->json([
                'success'      => true,
                'connected'    => $isOnline,
                'state'        => $state,
                'is_logged_in' => (bool) $isLoggedIn,
                'account_name' => $accountName,
                'message'      => $isOnline ? 'متصل بنجاح' : ($state === 'waiting_qr' ? 'غير مسجل (بانتظار مسح رمز QR)' : 'غير متصل'),
                'data'         => $result,
            ]);
        } catch (\Exception $e) {
            Cache::put($cacheKey, 'offline', now()->addMinutes(15));
            Log::error("WhatsApp status check failed: " . $e->getMessage());

            return response()->json([
                'success'      => false,
                'connected'    => false,
                'state'        => 'error',
                'is_logged_in' => false,
                'message'      => 'تعذر الاتصال بالخادم: ' . $e->getMessage(),
            ], 200);
        }
    }

    /**
     * طلب اتصال جديد وإنشاء QR Code (AJAX)
     */
    public function connect(WhatsAppService $whatsAppService): JsonResponse
    {
        $office = Auth::guard('office')->user();
        $wa = $whatsAppService->forOffice($office);

        if (!$wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى إدخال إعدادات الاتصال أولاً (Base URL و API Key).',
            ], 422);
        }

        $cacheKey = 'whatsapp_status_office_' . $office->id;

        try {
            $result = $wa->connect();

            // إذا كانت الجلسة متصلة بالفعل (session already logged in)
            $msg = strtolower($result['message'] ?? data_get($result, 'data.error', ''));
            if (str_contains($msg, 'already logged') || str_contains($msg, 'already connected')) {
                Cache::put($cacheKey, 'online', now()->addMinutes(15));

                return response()->json([
                    'success'      => true,
                    'connected'    => true,
                    'is_connected' => true,
                    'message'      => 'الجلسة متصلة بالفعل، لا حاجة لمسح QR.',
                    'data'         => $result,
                ]);
            }

            return response()->json([
                'success' => !($result['error'] ?? false),
                'message' => $result['message'] ?? 'تم طلب الاتصال',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("WhatsApp connect failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء الاتصال: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب QR Code للمسح (AJAX)
     * يتعامل مع حالة الجلسة المتصلة بالفعل
     */
    public function qrCode(WhatsAppService $whatsAppService): JsonResponse
    {
        $office = Auth::guard('office')->user();
        $wa = $whatsAppService->forOffice($office);

        if (!$wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى إعداد الاتصال أولاً.',
            ], 422);
        }

        $cacheKey = 'whatsapp_status_office_' . $office->id;

        try {
            $result = $wa->getQrCode();

            // التحقق من حالة الاتصال: إذا كانت الجلسة مسجلة الدخول بالفعل
            $isLoggedIn = data_get($result, 'data.LoggedIn') ?? data_get($result, 'LoggedIn');
            $instanceState = strtolower(data_get($result, 'instance.state') ?? data_get($result, 'state') ?? '');
            $errorMsg = strtolower(data_get($result, 'message') ?? data_get($result, 'data.error') ?? '');

            if ($isLoggedIn === true
                || in_array($instanceState, ['open', 'connected'])
                || str_contains($errorMsg, 'already logged')
                || str_contains($errorMsg, 'already connected')
            ) {
                Cache::put($cacheKey, 'online', now()->addMinutes(15));

                return response()->json([
                    'success'      => true,
                    'connected'    => true,
                    'is_connected' => true,
                    'qr_base64'    => null,
                    'message'      => 'جلسة WhatsApp متصلة بالفعل، لا حاجة لمسح رمز QR.',
                    'data'         => $result,
                ]);
            }

            // استخراج QR Code بمختلف البنى المحتملة
            $qrBase64 = data_get($result, 'data.qrcode')
                ?? data_get($result, 'data.base64')
                ?? data_get($result, 'data.qrcode.base64')
                ?? data_get($result, 'qrcode.base64')
                ?? data_get($result, 'qrcode')
                ?? data_get($result, 'base64')
                ?? data_get($result, 'data.code')
                ?? data_get($result, 'code');

            $pairingCode = data_get($result, 'data.PairingCode')
                ?? data_get($result, 'data.pairingCode')
                ?? data_get($result, 'data.pairing_code')
                ?? data_get($result, 'PairingCode')
                ?? data_get($result, 'pairingCode');

            if (!$qrBase64) {
                return response()->json([
                    'success'    => false,
                    'qr_base64'  => null,
                    'message'    => 'لم يتم إرجاع رمز QR من الخادم. قد تكون الجلسة قيد المعالجة أو متصلة بالفعل.',
                    'data'       => $result,
                ]);
            }

            return response()->json([
                'success'      => true,
                'qr_base64'    => $qrBase64,
                'pairing_code' => $pairingCode,
                'message'      => $result['message'] ?? null,
                'data'         => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("WhatsApp QR code failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'تعذر جلب رمز QR: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * طلب كود الربط برقم الهاتف (Pairing Code) (AJAX)
     */
    public function pairingCode(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:20',
        ]);

        $office = Auth::guard('office')->user();

        if (!$office) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر تحديد المكتب الحالي.',
            ], 403);
        }

        $wa = $whatsAppService->forOffice($office);

        if (!$wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى إدخال إعدادات الاتصال أولاً (Base URL و API Key).',
            ], 422);
        }

        $cacheKey = 'whatsapp_status_office_' . $office->id;
        $phone = trim($request->input('phone'));

        try {
            // حفظ رقم الهاتف في إعدادات المكتب للاستخدام المستقبلي
            if ($office->whatsapp_sender_phone !== $phone) {
                $office->update(['whatsapp_sender_phone' => $phone]);
            }

            $result = $wa->getPairingCode($phone);

            // التحقق من حالة الاتصال: إذا كانت الجلسة مسجلة الدخول بالفعل
            $rawMsg = strtolower($result['message'] ?? data_get($result, 'data.error', ''));
            if (str_contains($rawMsg, 'already logged') || str_contains($rawMsg, 'already connected')) {
                Cache::put($cacheKey, 'online', now()->addMinutes(15));

                return response()->json([
                    'success'      => true,
                    'connected'    => true,
                    'is_connected' => true,
                    'message'      => 'جلسة WhatsApp متصلة بالفعل، لا حاجة لكود الربط.',
                    'data'         => $result,
                ]);
            }

            // استخراج كود الربط
            $pairingCode = data_get($result, 'data.PairingCode')
                ?? data_get($result, 'data.pairingCode')
                ?? data_get($result, 'data.pairing_code')
                ?? data_get($result, 'PairingCode')
                ?? data_get($result, 'pairingCode')
                ?? data_get($result, 'code');

            if (!$pairingCode) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'تعذر الحصول على كود الربط. يرجى التأكد من صحة رقم الهاتف وأن الجلسة نشطة.',
                    'data'    => $result,
                ], 400);
            }

            return response()->json([
                'success'      => true,
                'pairing_code' => $pairingCode,
                'phone'        => $phone,
                'message'      => 'تم استخراج كود الربط بنجاح.',
                'data'         => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("WhatsApp pairing code failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل في استخراج كود الربط: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * قطع الاتصال وتسجيل الخروج من الواتساب (AJAX)
     */
    public function disconnect(WhatsAppService $whatsAppService): JsonResponse
    {
        $office = Auth::guard('office')->user();
        $wa = $whatsAppService->forOffice($office);
        $cacheKey = 'whatsapp_status_office_' . $office->id;

        try {
            $logoutResult = $wa->logout();
            $disconnectResult = $wa->disconnect();

            Cache::put($cacheKey, 'offline', now()->addMinutes(15));

            return response()->json([
                'success' => true,
                'message' => 'تم قطع الاتصال وتسجيل الخروج بنجاح.',
            ]);
        } catch (\Exception $e) {
            Log::error("WhatsApp disconnect failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء قطع الاتصال: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حفظ إعدادات الواتساب الخاصة بالمكتب (AJAX)
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp_base_url'       => 'nullable|url',
            'whatsapp_api_key'        => 'nullable|string|max:500',
            'whatsapp_instance_id'    => 'nullable|string|max:255',
            'whatsapp_instance_name'  => 'nullable|string|max:255',
            'whatsapp_webhook_url'    => 'nullable|url',
            'whatsapp_template'       => 'nullable|string|max:2000',
        ]);

        $office = Auth::guard('office')->user();

        $office->update($request->only([
            'whatsapp_base_url',
            'whatsapp_api_key',
            'whatsapp_instance_id',
            'whatsapp_instance_name',
            'whatsapp_webhook_url',
            'whatsapp_template',
        ]));

        // مسح الكاش لفرض فحص الإعدادات الجديدة عند الطلب القادم
        Cache::forget('whatsapp_status_office_' . $office->id);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الإعدادات بنجاح.',
        ]);
    }
}
