<?php

namespace App\Http\Controllers;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Models\ExternalPartnerLink;
use App\Models\OfficeProfile;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExternalPartnerPortalController extends Controller
{
    private const PIN_MAX_ATTEMPTS = 5;
    private const PIN_DECAY_SECONDS = 900; // 15 دقيقة

    private const NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
    ];

    /**
     * GET /p/{token}
     * عرض البوابة العامة أو شاشة الإيقاف أو شاشة التحقق من الـ PIN.
     */
    public function show(Request $request, string $token): Response
    {
        $link = $this->resolveActiveOrPausedLink($token);
        $brand = $this->resolveReceivingOfficeBrand($link);

        if ($this->isLinkPaused($link)) {
            return response()
                ->view('partner_portal.paused', compact('link', 'brand'))
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        if ($this->requiresPinVerification($link, $request)) {
            $rateKey = $this->pinRateLimitKey($link, $request);
            $lockedSeconds = RateLimiter::tooManyAttempts($rateKey, self::PIN_MAX_ATTEMPTS)
                ? RateLimiter::availableIn($rateKey)
                : 0;

            return response()
                ->view('partner_portal.pin', compact('link', 'brand', 'lockedSeconds'))
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        return response()
            ->view('partner_portal.form', compact('link', 'brand'))
            ->withHeaders(self::NO_CACHE_HEADERS);
    }

    /**
     * POST /p/{token}/verify-pin
     * التحقق من رمز الـ PIN مع حظر 15 دقيقة بعد 5 محاولات خاطئة لكل token + IP.
     */
    public function verifyPin(Request $request, string $token): Response|RedirectResponse|JsonResponse
    {
        $link = $this->resolveActiveOrPausedLink($token);
        $brand = $this->resolveReceivingOfficeBrand($link);

        if ($this->isLinkPaused($link)) {
            return response()
                ->view('partner_portal.paused', compact('link', 'brand'), 403)
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        if ($link->access_pin === null || $link->access_pin === '') {
            return redirect()->route('partner.portal.show', ['token' => $link->token]);
        }

        $rateKey = $this->pinRateLimitKey($link, $request);

        if (RateLimiter::tooManyAttempts($rateKey, self::PIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $minutes = max(1, (int) ceil($seconds / 60));
            $lockMsg = "تم تجاوز الحد المسموح من المحاولات الخاطئة (5 محاولات). يرجى الانتظار {$minutes} دقيقة قبل المحاولة مجدداً.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'          => false,
                    'message'     => $lockMsg,
                    'retry_after' => $seconds,
                ], 429);
            }

            return response()
                ->view('partner_portal.pin', [
                    'link'          => $link,
                    'brand'         => $brand,
                    'lockedSeconds' => $seconds,
                    'pinError'      => $lockMsg,
                ], 429)
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        $normalizedPin = preg_replace('/\D+/', '', $this->normalizeDigits((string) $request->input('pin', ''))) ?? '';

        if (! hash_equals((string) $link->access_pin, $normalizedPin)) {
            RateLimiter::hit($rateKey, self::PIN_DECAY_SECONDS);

            if (RateLimiter::tooManyAttempts($rateKey, self::PIN_MAX_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($rateKey);
                $minutes = max(1, (int) ceil($seconds / 60));
                $lockMsg = "تم حظر المحاولات مؤقتاً لمدة {$minutes} دقيقة بسبب تكرار إدخال رمز PIN غير صحيح.";

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'ok'          => false,
                        'message'     => $lockMsg,
                        'retry_after' => $seconds,
                    ], 429);
                }

                return response()
                    ->view('partner_portal.pin', [
                        'link'          => $link,
                        'brand'         => $brand,
                        'lockedSeconds' => $seconds,
                        'pinError'      => $lockMsg,
                    ], 429)
                    ->withHeaders(self::NO_CACHE_HEADERS);
            }

            $remaining = max(0, self::PIN_MAX_ATTEMPTS - RateLimiter::attempts($rateKey));
            $errMsg = "رمز الدخول غير صحيح. المحاولات المتبقية: {$remaining}.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'                 => false,
                    'message'            => $errMsg,
                    'remaining_attempts' => $remaining,
                ], 422);
            }

            return redirect()
                ->route('partner.portal.show', ['token' => $link->token])
                ->withErrors(['pin' => $errMsg]);
        }

        RateLimiter::clear($rateKey);
        $request->session()->put($this->pinSessionKey($link), $this->pinSessionSignature($link));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'           => true,
                'redirect_url' => route('partner.portal.show', ['token' => $link->token]),
            ]);
        }

        return redirect()->route('partner.portal.show', ['token' => $link->token]);
    }

    /**
     * POST /p/{token}/shipments
     * إنشاء وإرسال كشف الشحنة والطرود التابعة له داخل معاملة واحدة مع دعم idempotency_key.
     */
    public function store(Request $request, string $token): JsonResponse|RedirectResponse|Response
    {
        $link = $this->resolveActiveOrPausedLink($token);
        $brand = $this->resolveReceivingOfficeBrand($link);

        if ($this->isLinkPaused($link)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'هذا الرابط متوقف مؤقتاً من قِبل إدارة المكتب المستلم.',
                ], 403);
            }

            return response()
                ->view('partner_portal.paused', compact('link', 'brand'), 403)
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        if ($this->requiresPinVerification($link, $request)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'يرجى إدخال رمز الدخول السري (PIN) أولاً.',
                ], 403);
            }

            return redirect()->route('partner.portal.show', ['token' => $link->token]);
        }

        $normalizedPayload = $this->normalizeShipmentPayload($request->all());

        $validator = Validator::make($normalizedPayload, [
            'idempotency_key'           => ['required', 'string', 'max:64'],
            'driver_name'               => ['required', 'string', 'max:120'],
            'driver_phone'              => ['required', 'regex:/^7[01378]\d{7}$/'],
            'notes'                     => ['nullable', 'string', 'max:1000'],
            'parcels'                   => ['required', 'array', 'min:1', 'max:100'],
            'parcels.*.recipient_name'  => ['required', 'string', 'max:255'],
            'parcels.*.recipient_phone' => ['required', 'regex:/^7[01378]\d{7}$/'],
            'parcels.*.sender_name'     => ['nullable', 'string', 'max:255'],
            'parcels.*.sender_phone'    => ['nullable', 'regex:/^7[01378]\d{7}$/'],
            'parcels.*.payment_type'    => ['required', Rule::in(['paid', 'deferred'])],
            'parcels.*.cod_amount'      => ['required', 'numeric', 'min:0'],
            'parcels.*.package_type'    => ['nullable', 'string', 'max:255'],
            'parcels.*.notes'           => ['nullable', 'string', 'max:1000'],
            'parcels.*.receipt_number'  => ['nullable', 'string', 'max:40'],
        ], $this->shipmentValidationMessages());

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            return redirect()
                ->route('partner.portal.show', ['token' => $link->token])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $idempotencyKey = $validated['idempotency_key'];

        // 1. فحص عدم التكرار (Idempotency) لنفس الرابط قبل فتح المعاملة
        $existingShipment = Shipment::query()
            ->where('external_partner_link_id', $link->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingShipment) {
            return $this->respondWithShipmentSuccess($request, $link, $existingShipment);
        }

        // منع تعارض مفتاح idempotency مستخدم في رابط آخر مختلف
        if (Shipment::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            $conflictMsg = 'مفتاح عدم التكرار مستخدم مسبقاً، يرجى تحديث الصفحة والمحاولة مجدداً.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $conflictMsg], 422);
            }

            return redirect()
                ->route('partner.portal.show', ['token' => $link->token])
                ->withErrors(['idempotency_key' => $conflictMsg]);
        }

        $shipment = DB::transaction(function () use ($link, $validated, $idempotencyKey): Shipment {
            /** @var ExternalPartnerLink $lockedLink */
            $lockedLink = ExternalPartnerLink::query()
                ->with('office')
                ->whereKey($link->id)
                ->lockForUpdate()
                ->firstOrFail();

            // فحص ثانٍ داخل القفل لمنع السباق المتزامن (Race Condition)
            $alreadyCreated = Shipment::query()
                ->where('external_partner_link_id', $lockedLink->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($alreadyCreated) {
                return $alreadyCreated;
            }

            $receivingOffice = $lockedLink->office;
            $destBranch = trim((string) ($receivingOffice?->branch_name ?? ''));
            $destinationLabel = $destBranch !== ''
                ? "{$receivingOffice->name} - {$destBranch}"
                : (string) ($receivingOffice?->name ?? '');

            $now = now();
            $parcelsInput = $validated['parcels'];

            $totalPaid = 0.0;
            $totalDeferred = 0.0;

            foreach ($parcelsInput as $item) {
                $amount = round((float) $item['cod_amount'], 2);
                if ($item['payment_type'] === OutgoingPaymentType::DEFERRED->value) {
                    $totalDeferred += $amount;
                } else {
                    $totalPaid += $amount;
                }
            }

            $shipmentNumber = $this->generateExternalShipmentNumber($lockedLink);

            $createdShipment = Shipment::create([
                'office_id'                => null,
                'external_partner_link_id' => $lockedLink->id,
                'idempotency_key'          => $idempotencyKey,
                'shipment_number'          => $shipmentNumber,
                'driver_name'              => $validated['driver_name'],
                'driver_phone'             => $validated['driver_phone'],
                'parcels_count'            => count($parcelsInput),
                'total_paid_amount'        => round($totalPaid, 2),
                'total_deferred_amount'    => round($totalDeferred, 2),
                'total_amount'             => round($totalPaid + $totalDeferred, 2),
                'destinations_summary'     => $destinationLabel !== '' ? [$destinationLabel] : [],
                'status'                   => ShipmentStatus::IN_TRANSIT,
                'notes'                    => ! empty($validated['notes']) ? $validated['notes'] : null,
                'dispatched_at'            => $now,
            ]);

            $nextAutoSequence = $this->determineNextReceiptSequence($lockedLink);
            $prefixLen = mb_strlen($lockedLink->receipt_prefix, 'UTF-8');
            $usedReceiptsInBatch = [];
            $resolvedReceipts = [];

            // المرور الأول: تثبيت أرقام السندات اليدوية وتحديث التسلسل التلقائي ليتجاوز أعلى رقم يدوي في الدفعة
            foreach ($parcelsInput as $idx => $item) {
                $rawManualReceipt = trim((string) ($item['receipt_number'] ?? ''));
                if ($rawManualReceipt !== '') {
                    $formatted = $this->formatPrefixedReceipt($lockedLink->receipt_prefix, $rawManualReceipt);
                    $resolvedReceipts[$idx] = $formatted;
                    $usedReceiptsInBatch[] = $formatted;

                    $suffix = mb_substr($formatted, $prefixLen, null, 'UTF-8');
                    if ($suffix !== '' && ctype_digit($suffix)) {
                        $nextAutoSequence = max($nextAutoSequence, ((int) $suffix) + 1);
                    }
                }
            }

            $fallbackSenderName = $lockedLink->partner_name;
            $fallbackSenderPhone = (string) ($lockedLink->partner_phone ?? '');

            foreach ($parcelsInput as $idx => $item) {
                if (isset($resolvedReceipts[$idx])) {
                    $finalReceipt = $resolvedReceipts[$idx];
                } else {
                    do {
                        $candidate = $lockedLink->receipt_prefix . $nextAutoSequence;
                        $nextAutoSequence++;
                    } while (in_array($candidate, $usedReceiptsInBatch, true));
                    $finalReceipt = $candidate;
                    $usedReceiptsInBatch[] = $finalReceipt;
                }

                $senderName = trim((string) ($item['sender_name'] ?? ''));
                $senderPhone = trim((string) ($item['sender_phone'] ?? ''));
                $packageType = trim((string) ($item['package_type'] ?? ''));
                $parcelNotes = trim((string) ($item['notes'] ?? ''));

                OutgoingParcel::create([
                    'office_id'                => null,
                    'external_partner_link_id' => $lockedLink->id,
                    'shipment_id'              => $createdShipment->id,
                    'destination_office_id'    => $lockedLink->office_id,
                    'external_office_name'     => null,
                    'sender_name'              => $senderName !== '' ? $senderName : $fallbackSenderName,
                    'sender_phone'             => $senderPhone !== '' ? $senderPhone : $fallbackSenderPhone,
                    'recipient_name'           => $item['recipient_name'],
                    'recipient_phone'          => $item['recipient_phone'],
                    'package_type'             => $packageType !== '' ? $packageType : 'طرد',
                    'receipt_number'           => $finalReceipt,
                    'cod_amount'               => round((float) $item['cod_amount'], 2),
                    'payment_type'             => $item['payment_type'],
                    'notes'                    => $parcelNotes !== '' ? $parcelNotes : null,
                    'status'                   => OutgoingParcelStatus::IN_TRANSIT,
                    'dispatched_at'            => $now,
                ]);
            }

            $lockedLink->update([
                'last_used_at'    => $now,
                'shipments_count' => (int) $lockedLink->shipments_count + 1,
            ]);

            return $createdShipment;
        });

        return $this->respondWithShipmentSuccess($request, $link, $shipment);
    }

    /**
     * GET /p/{token}/shipments/{shipment}
     * شاشة نجاح وعرض الكشف المُرسل (مقفلة للعرض فقط).
     */
    public function success(Request $request, string $token, Shipment $shipment): Response|RedirectResponse
    {
        $link = $this->resolveActiveOrPausedLink($token);
        $brand = $this->resolveReceivingOfficeBrand($link);

        abort_unless((int) $shipment->external_partner_link_id === (int) $link->id, 404);

        if ($this->isLinkPaused($link)) {
            return response()
                ->view('partner_portal.paused', compact('link', 'brand'))
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        if ($this->requiresPinVerification($link, $request)) {
            return redirect()->route('partner.portal.show', ['token' => $link->token]);
        }

        $parcels = $shipment->parcels()
            ->orderBy('created_at')
            ->orderBy('receipt_number')
            ->get();

        return response()
            ->view('partner_portal.success', compact('link', 'brand', 'shipment', 'parcels'))
            ->withHeaders(self::NO_CACHE_HEADERS);
    }

    protected function resolveActiveOrPausedLink(string $token): ExternalPartnerLink
    {
        $cleanToken = trim($token);
        abort_if($cleanToken === '', 404);

        $link = ExternalPartnerLink::with('office')
            ->where('token', $cleanToken)
            ->first();

        abort_if(! $link || ! $link->office, 404);

        return $link;
    }

    protected function isLinkPaused(ExternalPartnerLink $link): bool
    {
        return ! $link->is_active
            || ! $link->office
            || ! $link->office->is_active
            || ! $link->office->hasFeature('external_partner_links');
    }

    protected function requiresPinVerification(ExternalPartnerLink $link, Request $request): bool
    {
        if ($link->access_pin === null || $link->access_pin === '') {
            return false;
        }

        $storedSig = (string) $request->session()->get($this->pinSessionKey($link), '');

        return $storedSig === '' || ! hash_equals($this->pinSessionSignature($link), $storedSig);
    }

    protected function pinSessionKey(ExternalPartnerLink $link): string
    {
        return 'partner_portal_pin_verified_' . $link->id;
    }

    protected function pinSessionSignature(ExternalPartnerLink $link): string
    {
        return hash_hmac('sha256', (string) $link->access_pin . '|' . $link->token, (string) config('app.key'));
    }

    protected function pinRateLimitKey(ExternalPartnerLink $link, Request $request): string
    {
        return 'partner-portal-pin:' . $link->id . ':' . $request->ip();
    }

    protected function resolveReceivingOfficeBrand(ExternalPartnerLink $link): array
    {
        $office = $link->office;
        $profile = $office ? $office->getResolvedProfile() : [];

        return [
            'office_name'     => $office?->name ?? 'مكتب مُرسَل',
            'branch_name'     => $office?->branch_name ?? '',
            'primary_color'   => $profile['primary_color'] ?? OfficeProfile::DEFAULT_PRIMARY_COLOR,
            'secondary_color' => $profile['secondary_color'] ?? OfficeProfile::DEFAULT_SECONDARY_COLOR,
            'logo_url'        => $profile['logo_url'] ?? $profile['icon_url'] ?? null,
            'icon_url'        => $profile['icon_url'] ?? $profile['logo_url'] ?? asset('icons/icon-192.png'),
        ];
    }

    protected function respondWithShipmentSuccess(
        Request $request,
        ExternalPartnerLink $link,
        Shipment $shipment
    ): JsonResponse|RedirectResponse {
        $successUrl = route('partner.portal.shipments.success', [
            'token'    => $link->token,
            'shipment' => $shipment->id,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'              => true,
                'shipment_id'     => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'redirect_url'    => $successUrl,
            ]);
        }

        return redirect()->to($successUrl);
    }

    protected function formatPrefixedReceipt(string $prefix, string $rawReceipt): string
    {
        $cleanPrefix = mb_strtoupper(trim($prefix), 'UTF-8');
        $cleanReceipt = ltrim(trim($this->normalizeDigits($rawReceipt)), '#');

        if (mb_strtoupper(mb_substr($cleanReceipt, 0, mb_strlen($cleanPrefix, 'UTF-8'), 'UTF-8'), 'UTF-8') === $cleanPrefix) {
            $suffix = mb_substr($cleanReceipt, mb_strlen($cleanPrefix, 'UTF-8'), null, 'UTF-8');

            return $cleanPrefix . $suffix;
        }

        return $cleanPrefix . $cleanReceipt;
    }

    protected function determineNextReceiptSequence(ExternalPartnerLink $link): int
    {
        $prefixLen = mb_strlen($link->receipt_prefix, 'UTF-8');
        $existingReceipts = OutgoingParcel::query()
            ->where('external_partner_link_id', $link->id)
            ->pluck('receipt_number');

        $maxSeq = 0;
        foreach ($existingReceipts as $receipt) {
            $str = (string) $receipt;
            $afterPrefix = mb_substr($str, $prefixLen, null, 'UTF-8');
            if ($afterPrefix !== '' && ctype_digit($afterPrefix)) {
                $num = (int) $afterPrefix;
                if ($num > $maxSeq) {
                    $maxSeq = $num;
                }
            }
        }

        return max($maxSeq + 1, $existingReceipts->count() + 1, 1);
    }

    protected function generateExternalShipmentNumber(ExternalPartnerLink $link): string
    {
        $ymd = now()->format('ymd');
        $seq = (int) $link->shipments_count + 1;
        $prefixClean = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($link->receipt_prefix, 'UTF-8')) ?: 'EXT';
        $prefixShort = substr($prefixClean, 0, 6);

        do {
            $candidate = sprintf('%s-%s-%03d', $prefixShort, $ymd, $seq);
            $seq++;
        } while (Shipment::query()->where('shipment_number', $candidate)->exists());

        return $candidate;
    }

    protected function normalizeShipmentPayload(array $input): array
    {
        $parcels = is_array($input['parcels'] ?? null) ? $input['parcels'] : [];
        $normalizedParcels = [];

        foreach ($parcels as $p) {
            if (! is_array($p)) {
                continue;
            }

            $rawCod = isset($p['cod_amount']) ? $this->normalizeDigits((string) $p['cod_amount']) : '';
            $rawSenderPhone = $this->normalizeYemeniPhone((string) ($p['sender_phone'] ?? ''));

            // دعم إرسال الوصف باسم package_type أو description بمرونة
            $rawPackage = trim((string) ($p['package_type'] ?? ($p['description'] ?? '')));

            $normalizedParcels[] = [
                'recipient_name'  => trim((string) ($p['recipient_name'] ?? '')),
                'recipient_phone' => $this->normalizeYemeniPhone((string) ($p['recipient_phone'] ?? '')),
                'sender_name'     => trim((string) ($p['sender_name'] ?? '')),
                'sender_phone'    => $rawSenderPhone !== '' ? $rawSenderPhone : null,
                'payment_type'    => trim((string) ($p['payment_type'] ?? '')),
                'cod_amount'      => $rawCod !== '' ? $rawCod : null,
                'package_type'    => $rawPackage !== '' ? $rawPackage : null,
                'notes'           => isset($p['notes']) ? trim((string) $p['notes']) : null,
                'receipt_number'  => isset($p['receipt_number']) ? trim($this->normalizeDigits((string) $p['receipt_number'])) : null,
            ];
        }

        return [
            'idempotency_key' => trim((string) ($input['idempotency_key'] ?? '')),
            'driver_name'     => trim((string) ($input['driver_name'] ?? '')),
            'driver_phone'    => $this->normalizeYemeniPhone((string) ($input['driver_phone'] ?? '')),
            'notes'           => isset($input['notes']) ? trim((string) $input['notes']) : null,
            'parcels'         => $normalizedParcels,
        ];
    }

    protected function normalizeDigits(string $value): string
    {
        return str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );
    }

    protected function normalizeYemeniPhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $this->normalizeDigits(trim($raw))) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '9677')) {
            $digits = substr($digits, 3);
        } elseif (strlen($digits) === 14 && str_starts_with($digits, '009677')) {
            $digits = substr($digits, 5);
        }

        return $digits;
    }

    protected function shipmentValidationMessages(): array
    {
        return [
            'idempotency_key.required'           => 'مفتاح الإرسال غير موجود، يرجى تحديث الصفحة.',
            'driver_name.required'               => 'يرجى إدخال اسم السائق.',
            'driver_phone.required'              => 'يرجى إدخال رقم جوال السائق.',
            'driver_phone.regex'                 => 'رقم جوال السائق غير صالح. يجب أن يتكون من 9 أرقام ويبدأ بـ 70، 71، 73، 77، أو 78.',
            'parcels.required'                   => 'يرجى إضافة طرد واحد على الأقل إلى الكشف.',
            'parcels.min'                        => 'يرجى إضافة طرد واحد على الأقل إلى الكشف.',
            'parcels.max'                        => 'الحد الأقصى للكشف الواحد هو 100 طرد.',
            'parcels.*.recipient_name.required'  => 'يرجى إدخال اسم المستلم لكل طرد.',
            'parcels.*.recipient_phone.required' => 'يرجى إدخال رقم جوال المستلم لكل طرد.',
            'parcels.*.recipient_phone.regex'    => 'رقم جوال المستلم غير صالح. يجب أن يتكون من 9 أرقام ويبدأ بـ 70، 71، 73، 77، أو 78.',
            'parcels.*.sender_phone.regex'       => 'رقم جوال المرسل غير صالح. يجب أن يتكون من 9 أرقام ويبدأ بـ 70، 71، 73، 77، أو 78.',
            'parcels.*.payment_type.required'    => 'يرجى تحديد حالة الدفع (محاسب أو آجل) لكل طرد.',
            'parcels.*.payment_type.in'          => 'حالة الدفع المحددة غير صالحة.',
            'parcels.*.cod_amount.required'      => 'يرجى إدخال المبلغ لكل طرد (أدخل 0 إن لم يوجد تحصيل).',
            'parcels.*.cod_amount.numeric'       => 'المبلغ يجب أن يكون رقماً صحيحاً.',
            'parcels.*.cod_amount.min'           => 'المبلغ يجب ألا يقل عن صفر.',
        ];
    }
}
