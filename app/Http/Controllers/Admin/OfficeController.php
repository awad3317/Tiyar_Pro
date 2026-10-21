<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\WhatsappInstance;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficeController extends Controller
{
    /**
     * قائمة المكاتب مع إمكانيات البحث والفلترة وحالة الواتساب
     */
    public function index(Request $request): View
    {
        $query = Office::query()
            ->withCount([
                'parcels',
                'parcels as today_parcels_count' => fn ($q) => $q->whereDate('created_at', today()),
                'parcels as delivered_count'     => fn ($q) => $q->where('status', ParcelStatus::Delivered),
                'parcels as in_office_count'     => fn ($q) => $q->where('status', ParcelStatus::InOffice),
            ]);

        // البحث بالنص
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('branch_name', 'like', "%{$search}%")
                  ->orWhere('whatsapp_sender_phone', 'like', "%{$search}%")
                  ->orWhere('httpsms_from_phone', 'like', "%{$search}%");
            });
        }

        // فلترة الحالة (نشط / محظور)
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $offices = $query->latest('id')->paginate(12)->withQueryString();

        $onlineCount  = 0;
        $offlineCount = 0;

        // إرفاق حالة الكاش الحالية لكل مكتب
        $offices->getCollection()->transform(function ($office) use (&$onlineCount, &$offlineCount) {
            $cacheKey = 'whatsapp_status_office_' . $office->id;
            $hasCache = Cache::has($cacheKey);
            $cachedStatus = $hasCache ? Cache::get($cacheKey) : null;

            if ($cachedStatus === 'online') {
                $office->whatsapp_live_status = 'online';
                $office->needs_status_check   = false;
                $onlineCount++;
            } elseif (!$office->hasWhatsAppConfigured()) {
                $office->whatsapp_live_status = 'not_configured';
                $office->needs_status_check   = false;
                $offlineCount++;
            } else {
                $office->whatsapp_live_status = $hasCache ? ($cachedStatus ?: 'offline') : 'offline';
                $office->needs_status_check   = !$hasCache || ($cachedStatus !== 'online');
                $offlineCount++;
            }

            return $office;
        });

        // فلترة حالة الواتساب بعد تحميل الكاش (إن طُلبت)
        $waFilter = $request->input('whatsapp_status');
        if ($waFilter) {
            $filtered = $offices->getCollection()->filter(function ($office) use ($waFilter) {
                return $office->whatsapp_live_status === $waFilter;
            });
            $offices->setCollection($filtered);
        }

        return view('admin.offices.index', [
            'offices'       => $offices,
            'filters'       => $request->only(['search', 'status', 'whatsapp_status']),
            'instances'     => WhatsappInstance::where('is_active', true)->get(),
            'whatsappStats' => [
                'online'  => $onlineCount,
                'offline' => $offlineCount,
                'total'   => $offices->total(),
            ],
        ]);
    }

    /**
     * صفحة إضافة مكتب جديد
     */
    public function create(): View
    {
        return view('admin.offices.create', [
            'instances' => WhatsappInstance::where('is_active', true)->get(),
        ]);
    }

    /**
     * تخزين مكتب جديد
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'branch_name'            => ['nullable', 'string', 'max:255'],
            'whatsapp_sender_phone'  => ['required', 'string', 'max:30'],
            'password'               => ['required', 'string', 'min:6'],
            'httpsms_from_phone'     => ['required', 'string', 'max:30'],
            'httpsms_api_key'        => ['nullable', 'string', 'max:255'],
            'sms_template'           => ['nullable', 'string'],
            'whatsapp_base_url'      => ['nullable', 'url'],
            'whatsapp_instance_id'   => ['nullable', 'string', 'max:255'],
            'whatsapp_instance_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_api_key'       => ['nullable', 'string'],
            'whatsapp_webhook_url'   => ['nullable', 'url'],
            'whatsapp_timeout'       => ['nullable', 'integer', 'min:5', 'max:120'],
            'whatsapp_template'      => ['nullable', 'string'],
            'is_active'              => ['nullable', 'boolean'],
        ], [
            'name.required'                  => 'اسم المكتب مطلوب.',
            'whatsapp_sender_phone.required' => 'رقم هاتف الواتساب مطلوب لدخول المكتب.',
            'password.required'              => 'كلمة المرور مطلوبة.',
            'httpsms_from_phone.required'    => 'رقم شريحة الـ SMS مطلوب.',
        ]);

        $cleanWaPhone = preg_replace('/[^0-9]/', '', $validated['whatsapp_sender_phone']);
        $cleanSmsPhone = preg_replace('/[^0-9+]/', '', $validated['httpsms_from_phone']);

        // القوالب الافتراضية إذا تُرِكت فارغة
        $defaultSmsTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nرقم السند: {receipt}\nيرجى التوجه للاستلام. شكراً لكم.";
        $defaultWaTemplate  = "السلام عليكم ورحمة الله وبركاته 🌸\nعميلنا العزيز {recipient}،\nيسرنا إشعاركم بوصول طردكم الجديد لدى *{office}* ({branch}).\n📦 نوع الطرد: {package}\n🔖 رقم السند: {receipt}\n📍 يرجى الحضور للاستلام خلال أوقات الدوام الرسمي.\nشكراً لتعاملكم معنا ✨";

        $office = Office::create([
            'name'                   => $validated['name'],
            'branch_name'            => $validated['branch_name'] ?? null,
            'whatsapp_sender_phone'  => $cleanWaPhone,
            'password'               => Hash::make($validated['password']),
            'httpsms_from_phone'     => $cleanSmsPhone,
            'httpsms_api_key'        => $validated['httpsms_api_key'] ?? '',
            'sms_template'           => ($validated['sms_template'] ?? null) ?: $defaultSmsTemplate,
            'whatsapp_base_url'      => ($validated['whatsapp_base_url'] ?? null) ?: config('whatsapp.base_url', 'http://195.35.24.73:4000'),
            'whatsapp_instance_id'   => $validated['whatsapp_instance_id'] ?? null,
            'whatsapp_instance_name' => $validated['whatsapp_instance_name'] ?? null,
            'whatsapp_api_key'       => $validated['whatsapp_api_key'] ?? null,
            'whatsapp_webhook_url'   => $validated['whatsapp_webhook_url'] ?? null,
            'whatsapp_timeout'       => $validated['whatsapp_timeout'] ?? 30,
            'whatsapp_template'      => ($validated['whatsapp_template'] ?? null) ?: $defaultWaTemplate,
            'is_active'              => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.offices.edit', $office)
            ->with('success', 'تم إنشاء المكتب بنجاح! يمكنك مراجعة وإدارة إعدادات الواتساب والـ SMS.');
    }

    /**
     * صفحة تعديل وإدارة المكتب بنظام التبويبات المتعددة (Tabs)
     */
    public function edit(Office $office): View
    {
        // ملخص طرود المكتب
        $parcelsSummary = [
            'total'     => $office->parcels()->count(),
            'in_office' => $office->parcels()->where('status', ParcelStatus::InOffice)->count(),
            'delivered' => $office->parcels()->where('status', ParcelStatus::Delivered)->count(),
            'returned'  => $office->parcels()->where('status', ParcelStatus::Returned)->count(),
            'today'     => $office->parcels()->whereDate('created_at', today())->count(),
        ];

        // آخر 6 طرود للمكتب
        $recentParcels = $office->parcels()->latest('created_at')->take(6)->get();

        // حالة كاش الواتساب الحالية
        $cacheKey = 'whatsapp_status_office_' . $office->id;
        $cachedStatus = Cache::get($cacheKey);

        $whatsappStatus = [
            'is_configured' => $office->hasWhatsAppConfigured(),
            'cached_status' => $cachedStatus ?: 'offline',
            'is_online'     => ($cachedStatus === 'online'),
        ];

        $availableInstances = WhatsappInstance::where('is_active', true)->get();

        return view('admin.offices.edit', [
            'office'             => $office,
            'parcelsSummary'     => $parcelsSummary,
            'recentParcels'      => $recentParcels,
            'whatsappStatus'     => $whatsappStatus,
            'availableInstances' => $availableInstances,
        ]);
    }

    /**
     * تحديث بيانات المكتب (المعلومات العامة، إعدادات SMS، إعدادات الواتساب)
     */
    public function update(Request $request, Office $office): RedirectResponse
    {
        $validated = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'branch_name'            => ['nullable', 'string', 'max:255'],
            'whatsapp_sender_phone'  => ['required', 'string', 'max:30'],
            'httpsms_from_phone'     => ['required', 'string', 'max:30'],
            'httpsms_api_key'        => ['nullable', 'string', 'max:255'],
            'sms_template'           => ['nullable', 'string'],
            'whatsapp_base_url'      => ['nullable', 'url'],
            'whatsapp_instance_id'   => ['nullable', 'string', 'max:255'],
            'whatsapp_instance_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_api_key'       => ['nullable', 'string'],
            'whatsapp_webhook_url'   => ['nullable', 'url'],
            'whatsapp_timeout'       => ['nullable', 'integer', 'min:5', 'max:120'],
            'whatsapp_template'      => ['nullable', 'string'],
            'is_active'              => ['nullable', 'boolean'],
        ]);

        $cleanWaPhone = preg_replace('/[^0-9]/', '', $validated['whatsapp_sender_phone']);
        $cleanSmsPhone = preg_replace('/[^0-9+]/', '', $validated['httpsms_from_phone']);

        $updateData = [
            'name'                   => $validated['name'],
            'branch_name'            => $validated['branch_name'] ?? null,
            'whatsapp_sender_phone'  => $cleanWaPhone,
            'httpsms_from_phone'     => $cleanSmsPhone,
            'httpsms_api_key'        => $validated['httpsms_api_key'] ?? $office->httpsms_api_key,
            'sms_template'           => $validated['sms_template'] ?? $office->sms_template,
            'whatsapp_base_url'      => $validated['whatsapp_base_url'] ?? $office->whatsapp_base_url,
            'whatsapp_instance_id'   => $validated['whatsapp_instance_id'] ?? $office->whatsapp_instance_id,
            'whatsapp_instance_name' => $validated['whatsapp_instance_name'] ?? $office->whatsapp_instance_name,
            'whatsapp_api_key'       => $validated['whatsapp_api_key'] ?? $office->whatsapp_api_key,
            'whatsapp_webhook_url'   => $validated['whatsapp_webhook_url'] ?? $office->whatsapp_webhook_url,
            'whatsapp_timeout'       => $validated['whatsapp_timeout'] ?? $office->whatsapp_timeout ?? 30,
            'whatsapp_template'      => $validated['whatsapp_template'] ?? $office->whatsapp_template,
            'is_active'              => $request->boolean('is_active'),
        ];

        $office->update($updateData);

        // مسح كاش الواتساب لإعادة الفحص بالإعدادات الجديدة
        Cache::forget('whatsapp_status_office_' . $office->id);

        $activeTab = $request->input('active_tab', 'general');

        return redirect()->route('admin.offices.edit', ['office' => $office, 'tab' => $activeTab])
            ->with('success', 'تم حفظ وتحديث بيانات المكتب وإعداداته بنجاح.');
    }

    /**
     * تفعيل أو حظر المكتب فورياً (AJAX أو Form)
     */
    public function toggleStatus(Request $request, Office $office): JsonResponse|RedirectResponse
    {
        $office->update([
            'is_active' => !$office->is_active,
        ]);

        $statusMsg = $office->is_active ? 'تم تفعيل المكتب بنجاح ويمكنه الدخول الآن.' : 'تم حظر / تعطيل حساب المكتب.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'   => true,
                'is_active' => $office->is_active,
                'message'   => $statusMsg,
            ]);
        }

        return back()->with('success', $statusMsg);
    }

    /**
     * تحديث كلمة مرور المكتب من الـ Modal
     */
    public function updatePassword(Request $request, Office $office): JsonResponse|RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ], [
            'password.required' => 'يرجى كتابة كلمة المرور الجديدة.',
            'password.min'      => 'كلمة المرور يجب ألا تقل عن 6 أحرف.',
        ]);

        $office->update([
            'password' => Hash::make($request->password),
        ]);

        $message = 'تم تغيير وتحديث كلمة المرور للمكتب بنجاح.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * حذف المكتب نهائياً
     */
    public function destroy(Office $office): RedirectResponse
    {
        $name = $office->name;
        $office->delete();

        return redirect()->route('admin.offices.index')
            ->with('success', "تم حذف المكتب \"{$name}\" بنجاح.");
    }

    /* =========================================================================
     | أدوات التحكم المباشر بجلسة واتساب المكتب (AJAX)
     | ========================================================================= */

    /**
     * فحص حالة الواتساب للمكتب (قراءة الكاش أو فحص حي)
     */
    public function whatsappStatus(Request $request, Office $office, WhatsAppService $whatsAppService): JsonResponse
    {
        $cacheKey = 'whatsapp_status_office_' . $office->id;

        if (!$office->hasWhatsAppConfigured()) {
            Cache::put($cacheKey, 'offline', now()->addMinutes(10));

            return response()->json([
                'success'       => false,
                'connected'     => false,
                'is_configured' => false,
                'state'         => 'not_configured',
                'message'       => 'إعدادات WhatsApp غير مكتملة لهذا المكتب (تحتاج Base URL و API Key).',
            ]);
        }

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
                'message'      => $isConnected ? 'متصل بنجاح (الكاش)' : 'غير متصل (الكاش)',
            ]);
        }

        $wa = $whatsAppService->forOffice($office)->withConfig(['timeout' => 5]);

        try {
            $result = $wa->getStatus();

            // فحص رد الخطأ
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

                Cache::put($cacheKey, 'offline', now()->addMinutes(10));

                return response()->json([
                    'success'   => false,
                    'connected' => false,
                    'state'     => 'error',
                    'message'   => 'غير متصل: ' . ($result['message'] ?? 'فشل الاتصال'),
                    'raw'       => $result,
                ]);
            }

            $isLoggedInRaw = data_get($result, 'data.LoggedIn') ?? data_get($result, 'LoggedIn');
            $isLoggedIn    = filter_var($isLoggedInRaw, FILTER_VALIDATE_BOOLEAN);

            $isConnectedRaw = data_get($result, 'data.Connected') ?? data_get($result, 'Connected');
            $accountName    = data_get($result, 'data.Name') ?? data_get($result, 'Name') ?? $office->name;

            $instanceState  = strtolower(trim((string)(
                data_get($result, 'instance.state')
                ?? data_get($result, 'data.instance.state')
                ?? data_get($result, 'state')
                ?? ''
            )));

            if ($isLoggedInRaw !== null) {
                $isOnline = $isLoggedIn;
                $state = $isLoggedIn ? 'connected' : 'disconnected';
            } else {
                $isOnline = in_array($instanceState, ['open', 'connected'], true);
                $state = $instanceState ?: ($isOnline ? 'connected' : 'disconnected');
            }

            Cache::put($cacheKey, $isOnline ? 'online' : 'offline', now()->addMinutes(15));

            return response()->json([
                'success'      => true,
                'connected'    => $isOnline,
                'state'        => $state,
                'is_logged_in' => (bool) $isLoggedIn,
                'account_name' => $accountName,
                'message'      => $isOnline ? 'متصل ونشط بنجاح ✅' : 'الجلسة غير متصلة ❌',
                'data'         => $result,
            ]);
        } catch (\Exception $e) {
            Cache::put($cacheKey, 'offline', now()->addMinutes(10));
            Log::error("Admin WhatsApp status check error: " . $e->getMessage());

            return response()->json([
                'success'      => false,
                'connected'    => false,
                'state'        => 'error',
                'is_logged_in' => false,
                'message'      => 'تعذر الاتصال بسيرفر الواتساب: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * طلب اتصال جديد بالسيرفر (Connect)
     */
    public function whatsappConnect(Office $office, WhatsAppService $whatsAppService): JsonResponse
    {
        $wa = $whatsAppService->forOffice($office);

        if (!$wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى إكمال إعدادات الواتساب للمكتب أولاً.',
            ], 422);
        }

        try {
            $result = $wa->connect();
            $cacheKey = 'whatsapp_status_office_' . $office->id;

            $msg = strtolower($result['message'] ?? data_get($result, 'data.error', ''));
            if (str_contains($msg, 'already logged') || str_contains($msg, 'already connected')) {
                Cache::put($cacheKey, 'online', now()->addMinutes(15));

                return response()->json([
                    'success'   => true,
                    'connected' => true,
                    'message'   => 'الجلسة متصلة بالفعل، لا داعي لمسح رمز QR.',
                    'data'      => $result,
                ]);
            }

            return response()->json([
                'success' => !($result['error'] ?? false),
                'message' => $result['message'] ?? 'تم إرسال طلب الاتصال.',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في الاتصال: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب QR Code للجلسة
     */
    public function whatsappQr(Office $office, WhatsAppService $whatsAppService): JsonResponse
    {
        $wa = $whatsAppService->forOffice($office);

        if (!$wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'إعدادات الواتساب غير مكتملة.',
            ], 422);
        }

        try {
            $result = $wa->getQrCode();
            $cacheKey = 'whatsapp_status_office_' . $office->id;

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
                    'success'   => true,
                    'connected' => true,
                    'qr_base64' => null,
                    'message'   => 'الجلسة متصلة ونشطة بالفعل، لا حاجة لرمز QR.',
                ]);
            }

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
                    'success'   => false,
                    'qr_base64' => null,
                    'message'   => 'لم يتم إرجاع رمز QR. قد تكون الجلسة متصلة بالفعل أو قيد التهيئة.',
                    'data'      => $result,
                ]);
            }

            return response()->json([
                'success'      => true,
                'qr_base64'    => $qrBase64,
                'pairing_code' => $pairingCode,
                'message'      => 'تم جلب رمز QR بنجاح.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر جلب رمز QR: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * طلب كود الربط (Pairing Code) برقم هاتف
     */
    public function whatsappPairingCode(Request $request, Office $office, WhatsAppService $whatsAppService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:7', 'max:25'],
        ]);

        $wa = $whatsAppService->forOffice($office);

        if (!$wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'إعدادات الواتساب غير مكتملة.',
            ], 422);
        }

        $phone = trim($request->input('phone'));
        $cacheKey = 'whatsapp_status_office_' . $office->id;

        try {
            $result = $wa->getPairingCode($phone);

            $rawMsg = strtolower($result['message'] ?? data_get($result, 'data.error', ''));
            if (str_contains($rawMsg, 'already logged') || str_contains($rawMsg, 'already connected')) {
                Cache::put($cacheKey, 'online', now()->addMinutes(15));

                return response()->json([
                    'success'   => true,
                    'connected' => true,
                    'message'   => 'الجلسة متصلة بالفعل.',
                ]);
            }

            $pairingCode = data_get($result, 'data.PairingCode')
                ?? data_get($result, 'data.pairingCode')
                ?? data_get($result, 'data.pairing_code')
                ?? data_get($result, 'PairingCode')
                ?? data_get($result, 'pairingCode')
                ?? data_get($result, 'code');

            if (!$pairingCode) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'تعذر الحصول على كود الربط. تأكد من صحة رقم الهاتف وأن السيرفر يعمل.',
                    'data'    => $result,
                ], 400);
            }

            return response()->json([
                'success'      => true,
                'pairing_code' => $pairingCode,
                'phone'        => $phone,
                'message'      => 'تم إنشاء كود الربط بنجاح.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في استخراج كود الربط: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * قطع الاتصال وتسجيل الخروج من جلسة الواتساب
     */
    public function whatsappDisconnect(Office $office, WhatsAppService $whatsAppService): JsonResponse
    {
        $wa = $whatsAppService->forOffice($office);
        $cacheKey = 'whatsapp_status_office_' . $office->id;

        try {
            $wa->logout();
            $wa->disconnect();

            Cache::put($cacheKey, 'offline', now()->addMinutes(15));

            return response()->json([
                'success' => true,
                'message' => 'تم قطع الاتصال وتسجيل الخروج من الجلسة بنجاح.',
            ]);
        } catch (\Exception $e) {
            Cache::put($cacheKey, 'offline', now()->addMinutes(15));

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء قطع الاتصال: ' . $e->getMessage(),
            ], 500);
        }
    }
}
