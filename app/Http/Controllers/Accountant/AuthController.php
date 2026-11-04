<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * هاش وهمي ثابت لمنع هجمات التوقيت (Timing Attacks) عند عدم وجود رقم الجوال
     */
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /**
     * عرض صفحة تسجيل دخول المحاسب
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('accountant')->check()) {
            /** @var Accountant|null $accountant */
            $accountant = Auth::guard('accountant')->user();
            $accountant?->loadMissing('office');

            if ($accountant && $accountant->canAccessApp()) {
                return redirect()->route('accountant.dashboard');
            }

            Auth::guard('accountant')->logout();
        }

        return view('accountant.auth.login');
    }

    /**
     * معالجة تسجيل دخول المحاسب برقم الجوال والرمز السري مع حماية Rate Limiting وعزل الجلسات
     */
    public function login(Request $request): RedirectResponse
    {
        $cleanPhone = $this->normalizePhone($request->input('phone'));
        $request->merge(['phone' => $cleanPhone]);

        $validated = $request->validate([
            'phone'    => ['required', 'regex:/^7[01378]\d{7}$/'],
            'password' => ['required', 'string', 'max:100'],
        ], [
            'phone.required'    => 'يرجى إدخال رقم الجوال.',
            'phone.regex'       => 'صيغة رقم الجوال غير صحيحة (يجب أن يتكون من 9 أرقام ويبدأ بـ 77، 78، 73، 71، أو 70).',
            'password.required' => 'يرجى إدخال الرمز السري / كلمة المرور.',
        ]);

        // 1. حماية Brute-Force: بحد أقصى 5 محاولات خاطئة خلال دقيقة لكل رقم جوال + IP
        $throttleKey = 'accountant-login:' . $validated['phone'] . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors(['phone' => "تم تجاوز الحد المسموح من محاولات الدخول. يرجى الانتظار {$seconds} ثانية قبل المحاولة مجدداً."])
                ->withInput($request->only('phone', 'remember'));
        }

        $accountant = Accountant::with('office')
            ->where('phone', $validated['phone'])
            ->first();

        // 2. التحقق من كلمة المرور بزمن ثابت (Timing-Safe) لمنع كشف الأرقام المسجلة
        $passwordValid = $accountant
            ? Hash::check($validated['password'], $accountant->password)
            : Hash::check($validated['password'], self::DUMMY_HASH) && false;

        if (!$accountant || !$passwordValid) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['phone' => 'رقم الجوال أو الرمز السري غير صحيح.'])
                ->withInput($request->only('phone', 'remember'));
        }

        // 3. التحقق من نشاط حساب المحاسب
        if (!$accountant->is_active) {
            return back()
                ->withErrors(['phone' => 'حسابك موقوف حالياً. يرجى مراجعة الإدارة.'])
                ->withInput($request->only('phone', 'remember'));
        }

        // 4. التحقق من نشاط المكتب وتفعيل ميزة المحاسبين للمكتب
        if (!$accountant->office || !$accountant->office->is_active) {
            return back()
                ->withErrors(['phone' => 'حساب المكتب التابع له موقوف حالياً. يرجى مراجعة الإدارة.'])
                ->withInput($request->only('phone', 'remember'));
        }

        if (!$accountant->office->hasFeature('office_accountants')) {
            return back()
                ->withErrors(['phone' => 'خدمة تطبيق المحاسبين غير مفعلة لهذا المكتب حالياً.'])
                ->withInput($request->only('phone', 'remember'));
        }

        // نجاح المصادقة: مسح عداد المحاولات الخاطئة
        RateLimiter::clear($throttleKey);

        Auth::guard('accountant')->login($accountant, $request->boolean('remember', true));

        // تدوير معرف الجلسة للحماية من Session Fixation مع الحفاظ على جلسات الحراس الآخرين
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $accountant->update([
            'last_login_at' => now(),
        ]);

        // ضمان التوجيه داخل نطاق /accountant فقط وعدم التداخل مع url.intended الخاص بالمكتب أو الأدمن
        $intended = $request->hasSession() ? $request->session()->pull('url.intended') : null;
        if ($intended && str_starts_with($intended, url('/accountant'))) {
            return redirect()->to($intended);
        }

        return redirect()->route('accountant.dashboard');
    }

    /**
     * تسجيل خروج المحاسب بشكل معزول دون إبطال جلسات الحراس الآخرين (office / web)
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('accountant')->logout();

        if ($request->hasSession()) {
            $request->session()->regenerateToken();
        }

        return redirect()->route('accountant.login');
    }

    /**
     * توحيد الأرقام العربية/الفارسية إلى إنجليزية وإزالة أي رموز أو مسافات (مع معالجة بادئة 967 إن وجدت)
     */
    private function normalizePhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $converted = str_replace($arabicDigits, $englishDigits, $phone);
        $digits = preg_replace('/\D+/', '', $converted) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '9677')) {
            $digits = substr($digits, 3);
        } elseif (strlen($digits) === 14 && str_starts_with($digits, '009677')) {
            $digits = substr($digits, 5);
        }

        return $digits;
    }
}
