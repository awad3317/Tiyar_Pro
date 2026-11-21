<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficeHasFeature
{
    /**
     * التحقق من أن المكتب المسجّل دخوله يمتلك تفعيل الميزة المطلوبة.
     */
    public function handle(Request $request, Closure $next, string $featureCode): Response
    {
        $office = $request->user('office');

        if (! $office || ! $office->hasFeature($featureCode)) {
            $message = 'هذه الميزة غير مفعّلة لهذا المكتب.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
