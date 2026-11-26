<?php

namespace App\Http\Controllers\Office\Auth;

use App\Http\Controllers\Controller;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        // توحيد الأرقام العربية/الفارسية وتنظيف رقم الهاتف المدخل من أي مسافات أو رموز
        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalizedPhone = str_replace($arabicDigits, $englishDigits, $credentials['phone']);
        $cleanPhone = preg_replace('/[^0-9]/', '', $normalizedPhone);

        // البحث عن المكتب النشط بمطابقة رقم هاتف الواتساب أولاً ثم رقم شريحة الإرسال
        // دون إعادة تجزئة كلمة المرور تلقائياً (rehash) حتى لا تبطل كوكيز التذكر للأجهزة الأخرى المفتوحة في نفس الوقت
        $office = Office::where('is_active', true)
            ->where('whatsapp_sender_phone', $cleanPhone)
            ->first();

        if (! $office || ! Hash::check($credentials['password'], $office->password)) {
            $fallbackOffice = Office::where('is_active', true)
                ->where('httpsms_from_phone', $cleanPhone)
                ->first();

            if ($fallbackOffice && Hash::check($credentials['password'], $fallbackOffice->password)) {
                $office = $fallbackOffice;
            } else {
                $office = null;
            }
        }

        if ($office) {
            // تسجيل الدخول على هذا الجهاز مع تفعيل التذكر الدائم دون المساس بجلسات أو رمز تذكر الأجهزة الأخرى
            Auth::guard('office')->login($office, true);

            return redirect()->intended(route('office.dashboard'));
        }

        throw ValidationException::withMessages([
            'phone' => 'بيانات الدخول غير صحيحة أو أن حساب المكتب معطل.',
        ]);
    }

    public function logout(Request $request)
    {
        // تسجيل الخروج من الجهاز الحالي فقط (logoutCurrentDevice) دون تدوير remember_token في قاعدة البيانات
        // لضمان عدم طرد الجوال أو الأجهزة الأخرى المسجلة بنفس حساب المكتب
        Auth::guard('office')->logoutCurrentDevice();

        if ($request->hasSession()) {
            $request->session()->regenerate(true);
            $request->session()->regenerateToken();
        }

        return redirect()->route('office.login');
    }
}
