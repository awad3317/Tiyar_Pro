<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * عرض صفحة تسجيل دخول مسؤولي النظام
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    /**
     * معالجة تسجيل الدخول للمسؤول
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'يرجى إدخال البريد الإلكتروني للمسؤول.',
            'email.email'       => 'صيغة البريد الإلكتروني غير صالحة.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::guard('web')->attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'مرحباً بك مجدداً في لوحة تحكم الإدارة المركزية.');
        }

        throw ValidationException::withMessages([
            'email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
        ]);
    }

    /**
     * تسجيل خروج المسؤول
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('info', 'تم تسجيل الخروج بنجاح.');
    }
}
