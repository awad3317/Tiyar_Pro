<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountantAccess
{
    /**
     * التحقق من تسجيل دخول المحاسب، وتفقد نشاط حسابه وتفعيل ميزة المحاسبين لمكتبه في كل طلب.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('accountant')->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'انتهت الجلسة، يرجى تسجيل الدخول مجدداً.',
                ], 401);
            }

            return redirect()->route('accountant.login');
        }

        /** @var \App\Models\Accountant|null $accountant */
        $accountant = Auth::guard('accountant')->user();

        // تحميل علاقة المكتب مرة واحدة لمنع تكرار الاستعلامات (N+1) في الهيدر والمتحكمات
        $accountant?->loadMissing('office');

        if (!$accountant || !$accountant->canAccessApp()) {
            Auth::guard('accountant')->logout();

            $errorMessage = 'تم إيقاف حسابك أو تعطيل خدمة المحاسبين للمكتب.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 403);
            }

            return redirect()->route('accountant.login')
                ->withErrors(['phone' => $errorMessage])
                ->with('error', $errorMessage);
        }

        return $next($request);
    }
}

