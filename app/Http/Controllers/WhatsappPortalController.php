<?php

namespace App\Http\Controllers;

use App\Models\WhatsappInstance;
use App\Services\WhatsappGatewayService;
use Illuminate\Http\Request;

class WhatsappPortalController extends Controller
{
    protected WhatsappGatewayService $gatewayService;

    public function __construct(WhatsappGatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    /**
     * عرض الصفحة الرئيسية للواجهة
     */
    public function index()
    {
        return view('whatsapp.portal');
    }

    /**
     * تسجيل دخول العميل عبر الرقم السري (PIN)
     */
    public function login(Request $request)
    {
        $request->validate([
            'pin_code' => 'required|string',
        ]);

        $instance = WhatsappInstance::where('pin_code', $request->pin_code)
            ->where('is_active', true)
            ->first();

        if (!$instance) {
            return response()->json([
                'success' => false,
                'message' => 'الرمز السري غير صحيح أو الحساب معطل.',
            ], 401);
        }

        // حفظ المعرف في الجلسة (Session)
        session(['wa_instance_id' => $instance->instance_id]);

        return response()->json([
            'success' => true,
            'instance' => [
                'name'         => $instance->name,
                'instance_id'  => $instance->instance_id,
                'phone_number' => $instance->phone_number,
            ],
        ]);
    }

    /**
     * التحقق من الـ Instance الحالي في الجلسة
     */
    private function getCurrentInstanceId()
    {
        return session('wa_instance_id');
    }

    /**
     * جلب حالة الاتصال
     */
    public function status()
    {
        $instanceId = $this->getCurrentInstanceId();
        if (!$instanceId) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $res = $this->gatewayService->getStatus($instanceId);
        return response()->json($res);
    }

    /**
     * جلب كود الـ QR
     */
    public function qr()
    {
        $instanceId = $this->getCurrentInstanceId();
        if (!$instanceId) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $res = $this->gatewayService->getQrCode($instanceId);
        return response()->json($res);
    }

    /**
     * طلب كود الربط المباشر بالرقم (Pairing Code)
     */
    public function pair(Request $request)
    {
        $instanceId = $this->getCurrentInstanceId();
        if (!$instanceId) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $request->validate([
            'phone' => 'required|string',
        ]);

        // تنظيف الرقم من أي إشارات مثل + أو مسافات
        $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone);

        $res = $this->gatewayService->requestPairingCode($instanceId, $cleanPhone);
        return response()->json($res);
    }

    /**
     * إعادة محاولة الاتصال
     */
    public function reconnect()
    {
        $instanceId = $this->getCurrentInstanceId();
        if (!$instanceId) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $res = $this->gatewayService->reconnect($instanceId);
        return response()->json($res);
    }

    /**
     * تسجيل خروج من الواتساب
     */
    public function logout()
    {
        $instanceId = $this->getCurrentInstanceId();
        if (!$instanceId) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $res = $this->gatewayService->logout($instanceId);
        return response()->json($res);
    }

    /**
     * تسجيل الخروج من البوابة وإنهاء الجلسة
     */
    public function exitPortal()
    {
        session()->forget('wa_instance_id');
        return response()->json(['success' => true]);
    }
}