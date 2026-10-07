<?php

namespace App\Http\Controllers\Office\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('office')->check()) {
            return redirect()->route('office.parcels.index');
        }

        return view('office.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'phone.required'    => 'يرجى إدخال رقم الهاتف المعتمد للمكتب.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
        ]);

        // تنظيف رقم الهاتف المدخل من أي مسافات أو رموز
        $cleanPhone = preg_replace('/[^0-9]/', '', $credentials['phone']);

        // محاولة الدخول بمطابقة رقم هاتف الواتساب
        $attemptWhatsApp = Auth::guard('office')->attempt([
            'whatsapp_sender_phone' => $cleanPhone,
            'password'              => $credentials['password'],
            'is_active'             => true,
        ], $request->boolean('remember'));

        // إذا لم تنجح، نحاول مطابقة رقم شريحة الإرسال
        $attemptHttpSms = false;
        if (!$attemptWhatsApp) {
            $attemptHttpSms = Auth::guard('office')->attempt([
                'httpsms_from_phone' => $cleanPhone,
                'password'           => $credentials['password'],
                'is_active'          => true,
            ], $request->boolean('remember'));
        }

        if ($attemptWhatsApp || $attemptHttpSms) {
            $request->session()->regenerate();
            return redirect()->intended(route('office.dashboard'));
        }

        throw ValidationException::withMessages([
            'phone' => 'بيانات الدخول غير صحيحة أو أن حساب المكتب معطل.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('office')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('office.login');
    }
}