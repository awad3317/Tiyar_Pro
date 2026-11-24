<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\ExternalPartnerLink;
use App\Models\Office;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExternalPartnerLinkController extends Controller
{
    private const NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
    ];

    /**
     * عرض قائمة روابط المكاتب الخارجية الخاصة بالمكتب الحالي.
     */
    public function index(): Response
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $links = $office->externalPartnerLinks()
            ->latest()
            ->get();

        return response()
            ->view('office.external_partner_links.index', compact('office', 'links'))
            ->withHeaders(self::NO_CACHE_HEADERS);
    }

    /**
     * إنشاء رابط جديد لمكتب خارجي.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $this->normalizeRequestInputs($request);

        $validated = $request->validateWithBag('partnerLink', [
            'partner_name'   => ['required', 'string', 'max:150'],
            'partner_city'   => ['nullable', 'string', 'max:100'],
            'partner_phone'  => ['nullable', 'string', 'max:20'],
            'access_pin'     => ['nullable', 'digits:4'],
            'receipt_prefix' => [
                'required',
                'string',
                'max:12',
                Rule::unique('external_partner_links', 'receipt_prefix')
                    ->where('office_id', $office->id)
                    ->whereNull('deleted_at'),
            ],
        ], $this->validationMessages());

        $link = ExternalPartnerLink::create([
            'office_id'       => $office->id,
            'partner_name'    => $validated['partner_name'],
            'partner_city'    => $validated['partner_city'] ?? null,
            'partner_phone'   => $validated['partner_phone'] ?? null,
            'token'           => $this->generateUniqueToken(),
            'access_pin'      => $validated['access_pin'] ?? null,
            'receipt_prefix'  => $validated['receipt_prefix'],
            'is_active'       => true,
            'shipments_count' => 0,
        ]);

        $msg = "تم إنشاء رابط المكتب الخارجي ({$link->partner_name}) بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => $msg,
                'link'    => $link,
            ], 201);
        }

        return redirect()
            ->route('office.external-partner-links.index')
            ->with('success', $msg);
    }

    /**
     * تعديل بيانات المكتب الخارجي أو البادئة أو رمز الـ PIN.
     */
    public function update(Request $request, ExternalPartnerLink $externalPartnerLink): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        $this->ensureOwnership($externalPartnerLink, $office);

        $this->normalizeRequestInputs($request);

        $validated = $request->validateWithBag('partnerLink', [
            'partner_name'   => ['required', 'string', 'max:150'],
            'partner_city'   => ['nullable', 'string', 'max:100'],
            'partner_phone'  => ['nullable', 'string', 'max:20'],
            'access_pin'     => ['nullable', 'digits:4'],
            'receipt_prefix' => [
                'required',
                'string',
                'max:12',
                Rule::unique('external_partner_links', 'receipt_prefix')
                    ->where('office_id', $office->id)
                    ->whereNull('deleted_at')
                    ->ignore($externalPartnerLink->id),
            ],
        ], $this->validationMessages());

        $externalPartnerLink->update([
            'partner_name'   => $validated['partner_name'],
            'partner_city'   => $validated['partner_city'] ?? null,
            'partner_phone'  => $validated['partner_phone'] ?? null,
            'access_pin'     => $validated['access_pin'] ?? null,
            'receipt_prefix' => $validated['receipt_prefix'],
        ]);

        $msg = "تم تحديث بيانات رابط ({$externalPartnerLink->partner_name}) بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => $msg,
                'link'    => $externalPartnerLink->fresh(),
            ]);
        }

        return redirect()
            ->route('office.external-partner-links.index')
            ->with('success', $msg);
    }

    /**
     * تفعيل أو إيقاف الرابط فوراً بضغطة زر.
     */
    public function toggleActive(Request $request, ExternalPartnerLink $externalPartnerLink): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        $this->ensureOwnership($externalPartnerLink, $office);

        $nextState = ! $externalPartnerLink->is_active;
        $externalPartnerLink->update(['is_active' => $nextState]);

        $msg = $nextState
            ? "تم تفعيل رابط ({$externalPartnerLink->partner_name}) بنجاح."
            : "تم إيقاف رابط ({$externalPartnerLink->partner_name}) مؤقتاً.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'        => true,
                'message'   => $msg,
                'is_active' => $nextState,
            ]);
        }

        return redirect()
            ->route('office.external-partner-links.index')
            ->with('success', $msg);
    }

    /**
     * إلغاء التوكن القديم وتوليد توكن جديد فوراً بطول 64 حرفاً.
     */
    public function regenerateToken(Request $request, ExternalPartnerLink $externalPartnerLink): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        $this->ensureOwnership($externalPartnerLink, $office);

        $newToken = $this->generateUniqueToken();
        $externalPartnerLink->update(['token' => $newToken]);

        $msg = "تم تغيير رابط ({$externalPartnerLink->partner_name}) وإلغاء الرابط القديم فوراً.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'         => true,
                'message'    => $msg,
                'token'      => $newToken,
                'portal_url' => url('/p/' . $newToken),
            ]);
        }

        return redirect()
            ->route('office.external-partner-links.index')
            ->with('success', $msg);
    }

    /**
     * حذف ناعم (SoftDelete) للرابط مع بقاء سجل الشحنات التاريخية سليماً.
     */
    public function destroy(Request $request, ExternalPartnerLink $externalPartnerLink): JsonResponse|RedirectResponse
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        $this->ensureOwnership($externalPartnerLink, $office);

        $partnerName = $externalPartnerLink->partner_name;
        $externalPartnerLink->delete();

        $msg = "تم حذف رابط ({$partnerName}) بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => $msg,
            ]);
        }

        return redirect()
            ->route('office.external-partner-links.index')
            ->with('success', $msg);
    }

    protected function ensureOwnership(ExternalPartnerLink $link, Office $office): void
    {
        abort_unless((int) $link->office_id === (int) $office->id, 403, 'غير مصرح لك بالوصول إلى هذا الرابط.');
    }

    protected function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (ExternalPartnerLink::withTrashed()->where('token', $token)->exists());

        return $token;
    }

    protected function normalizeRequestInputs(Request $request): void
    {
        $prefixRaw = (string) $request->input('receipt_prefix', '');
        $prefixClean = mb_strtoupper(preg_replace('/\s+/u', '', trim($prefixRaw)) ?? '', 'UTF-8');

        $pinRaw = $this->normalizeDigits(trim((string) $request->input('access_pin', '')));
        $phoneRaw = $this->normalizeDigits(trim((string) $request->input('partner_phone', '')));
        $cityRaw = trim((string) $request->input('partner_city', ''));

        $request->merge([
            'partner_name'   => trim((string) $request->input('partner_name', '')),
            'partner_city'   => $cityRaw !== '' ? $cityRaw : null,
            'partner_phone'  => $phoneRaw !== '' ? $phoneRaw : null,
            'access_pin'     => $pinRaw !== '' ? $pinRaw : null,
            'receipt_prefix' => $prefixClean,
        ]);
    }

    protected function normalizeDigits(string $value): string
    {
        return str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );
    }

    protected function validationMessages(): array
    {
        return [
            'partner_name.required'   => 'يرجى إدخال اسم المكتب الخارجي.',
            'partner_name.max'        => 'اسم المكتب الخارجي يجب ألا يتجاوز 150 حرفاً.',
            'partner_city.max'        => 'اسم المدينة يجب ألا يتجاوز 100 حرف.',
            'partner_phone.max'       => 'رقم الهاتف يجب ألا يتجاوز 20 رقماً.',
            'access_pin.digits'       => 'رمز الدخول السري (PIN) يجب أن يتكون من 4 أرقام بالضبط.',
            'receipt_prefix.required' => 'يرجى تحديد بادئة أرقام السندات (مثل: MRB-).',
            'receipt_prefix.max'      => 'بادئة السند يجب ألا تتجاوز 12 حرفاً.',
            'receipt_prefix.unique'   => 'هذه البادئة مستخدمة بالفعل لرابط نشط آخر في مكتبكم.',
        ];
    }
}
