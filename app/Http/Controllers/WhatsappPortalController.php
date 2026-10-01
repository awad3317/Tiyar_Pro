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

   public function qr()
    {
        $token = session('wa_instance_token');
        if (!$token) {
            return response()->json(['error' => 'غير مصرح - لا يوجد توكن'], 401);
        }

        $res = $this->gatewayService->getQrCode($token);
        return response()->json($res);
    }

    public function logout()
    {
        $token = session('wa_instance_token');
        if (!$token) {
            return response()->json(['error' => 'غير مصرح - لا يوجد توكن'], 401);
        }

        $res = $this->gatewayService->logout($token);
        return response()->json($res);
    }

    public function pair(Request $request)
    {
        $token = session('wa_instance_token');
        if (!$token) {
            return response()->json(['error' => 'غير مصرح'], 401);
        }

        $request->validate(['phone' => 'required|string']);
        $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone);

        $res = $this->gatewayService->requestPairingCode($token, $cleanPhone);
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