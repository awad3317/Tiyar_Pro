<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Driver;
use App\Models\Manifest;
use App\Models\Parcel;
use App\Models\ParcelEditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ManifestController extends Controller
{
    /**
     * عرض شاشة كشوفات السائقين في تطبيق المحاسب
     */
    public function index(Request $request): View
    {
        return view('accountant.manifests.index');
    }

    /**
     * API لجلب كشوفات المكتب، قائمة السائقين، وإحصائيات الكشوفات بأقل عدد استعلامات
     */
    public function listData(Request $request): JsonResponse
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user();
        $officeId   = (int) $accountant->office_id;

        $manifests = Manifest::query()
            ->where('office_id', $officeId)
            ->withCount([
                'parcels',
                'parcels as postpaid_parcels_count' => fn ($q) => $q->where('payment_type', 'postpaid'),
                'parcels as prepaid_parcels_count'  => fn ($q) => $q->where('payment_type', 'prepaid'),
            ])
            ->latest('manifest_date')
            ->latest('id')
            ->get();

        $drivers = Driver::query()
            ->where('office_id', $officeId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $totalCount            = $manifests->count();
        $openCount             = $manifests->where('status', 'open')->count();
        $settledCount          = $manifests->where('status', 'settled')->count();
        $totalPostpaidAmount   = (float) $manifests->sum('total_postpaid_amount');
        $totalDeductionsAmount = (float) $manifests->sum('total_deductions');
        $totalNetDriverAmount  = (float) $manifests->sum('net_driver_amount');

        return response()->json([
            'success'   => true,
            'stats'     => [
                'total_count'             => $totalCount,
                'open_count'              => $openCount,
                'settled_count'           => $settledCount,
                'total_postpaid_amount'   => round($totalPostpaidAmount, 2),
                'total_deductions_amount' => round($totalDeductionsAmount, 2),
                'total_net_driver_amount' => round($totalNetDriverAmount, 2),
            ],
            'drivers'   => $drivers,
            'manifests' => $manifests->map(fn (Manifest $m) => $this->serializeManifest($m))->values(),
        ]);
    }

    /**
     * API خفيف لجلب سائقي المكتب والكشوفات المفتوحة (يُستخدم في شاشة الطرود غير المصنفة لضم الطرود)
     */
    public function driversList(Request $request): JsonResponse
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user();
        $officeId   = (int) $accountant->office_id;

        $drivers = Driver::query()
            ->where('office_id', $officeId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $openManifests = Manifest::query()
            ->where('office_id', $officeId)
            ->where('status', 'open')
            ->withCount('parcels')
            ->latest('manifest_date')
            ->latest('id')
            ->get([
                'id',
                'driver_id',
                'driver_name',
                'driver_phone',
                'source_office_name',
                'source_branch_name',
                'manifest_number',
                'manifest_date',
                'total_postpaid_amount',
            ])
            ->map(fn (Manifest $m) => [
                'id'                    => $m->id,
                'driver_id'             => $m->driver_id,
                'driver_name'           => $m->driver_name,
                'driver_phone'          => $m->driver_phone,
                'source_office_name'    => $m->source_office_name,
                'source_branch_name'    => $m->source_branch_name,
                'manifest_number'       => $m->manifest_number,
                'manifest_date'         => $m->manifest_date?->format('Y-m-d'),
                'parcels_count'         => (int) ($m->parcels_count ?? 0),
                'total_postpaid_amount' => (float) ($m->total_postpaid_amount ?? 0),
            ]);

        return response()->json([
            'success'        => true,
            'drivers'        => $drivers,
            'open_manifests' => $openManifests,
        ]);
    }

    /**
     * إنشاء كشف سائق جديد + ضم طرود اختيارياً في نفس الخطوة
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user();
        $officeId   = (int) $accountant->office_id;

        $this->sanitizeManifestInputs($request);

        $validated = $request->validate([
            'driver_id'          => ['nullable', 'integer'],
            'driver_name'        => ['required', 'string', 'min:2', 'max:100'],
            'driver_phone'       => ['nullable', 'regex:/^7[01378]\d{7}$/'],
            'source_office_name' => ['required', 'string', 'max:100'],
            'source_branch_name' => ['nullable', 'string', 'max:100'],
            'manifest_number'    => ['nullable', 'string', 'max:50'],
            'manifest_date'      => ['nullable', 'date'],
            'notes'              => ['nullable', 'string', 'max:500'],
            'parcel_ids'         => ['nullable', 'array', 'max:200'],
            'parcel_ids.*'       => ['string', 'uuid'],
        ], [
            'driver_name.required'        => 'يرجى إدخال اسم السائق أو اختياره من القائمة.',
            'driver_name.min'             => 'اسم السائق يجب ألا يقل عن حرفين.',
            'driver_phone.regex'          => 'صيغة رقم جوال السائق غير صحيحة (يجب أن يتكون من 9 أرقام ويبدأ بـ 77، 78، 73، 71، أو 70).',
            'source_office_name.required' => 'يرجى إدخال اسم المكتب المرسل.',
            'manifest_date.date'          => 'تاريخ الكشف غير صالح.',
            'parcel_ids.max'              => 'لا يمكن ضم أكثر من 200 طرد في عملية واحدة.',
        ]);

        $assignedCount = 0;

        $manifest = DB::transaction(function () use ($validated, $accountant, $officeId, $request, &$assignedCount) {
            $manifest = Manifest::create([
                'office_id'          => $officeId,
                'accountant_id'      => $accountant->id,
                'driver_id'          => $validated['driver_id'] ?? null,
                'driver_name'        => $validated['driver_name'],
                'driver_phone'       => $validated['driver_phone'] ?? null,
                'source_office_name' => $validated['source_office_name'],
                'source_branch_name' => $validated['source_branch_name'] ?? null,
                'manifest_number'    => $validated['manifest_number'] ?? null,
                'manifest_date'      => !empty($validated['manifest_date']) ? $validated['manifest_date'] : now()->toDateString(),
                'status'             => 'open',
                'notes'              => $validated['notes'] ?? null,
            ]);

            $parcelIds = array_values(array_unique($validated['parcel_ids'] ?? []));
            if (!empty($parcelIds)) {
                $assignedCount = $this->attachParcelsToManifest(
                    $manifest,
                    $parcelIds,
                    $accountant,
                    $request->ip()
                );
            }

            $manifest->recalculateTotals();

            return $manifest;
        });

        $manifest->load('driver:id,office_id,name,phone')->loadCount([
            'parcels',
            'parcels as postpaid_parcels_count' => fn ($q) => $q->where('payment_type', 'postpaid'),
            'parcels as prepaid_parcels_count'  => fn ($q) => $q->where('payment_type', 'prepaid'),
        ]);

        $msg = $assignedCount > 0
            ? "تم إنشاء كشف السائق ({$manifest->driver_name}) وضم ({$assignedCount}) طرد إليه بنجاح."
            : "تم إنشاء كشف السائق ({$manifest->driver_name}) بنجاح.";

        return response()->json([
            'success'        => true,
            'message'        => $msg,
            'assigned_count' => $assignedCount,
            'driver'         => $manifest->driver,
            'manifest'       => $this->serializeManifest($manifest),
        ]);
    }

    /**
     * تعديل بيانات الكشف (مسموح فقط للكشوفات المفتوحة قيد المراجعة)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user();
        $officeId   = (int) $accountant->office_id;

        $manifest = Manifest::query()
            ->where('office_id', $officeId)
            ->findOrFail($id);

        if ($manifest->status === 'settled') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل بيانات كشف تمت تصفيته وإغلاقه.',
            ], 422);
        }

        $this->sanitizeManifestInputs($request);

        $validated = $request->validate([
            'driver_id'          => ['nullable', 'integer'],
            'driver_name'        => ['required', 'string', 'min:2', 'max:100'],
            'driver_phone'       => ['nullable', 'regex:/^7[01378]\d{7}$/'],
            'source_office_name' => ['required', 'string', 'max:100'],
            'source_branch_name' => ['nullable', 'string', 'max:100'],
            'manifest_number'    => ['nullable', 'string', 'max:50'],
            'manifest_date'      => ['nullable', 'date'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ], [
            'driver_name.required'        => 'يرجى إدخال اسم السائق أو اختياره من القائمة.',
            'driver_name.min'             => 'اسم السائق يجب ألا يقل عن حرفين.',
            'driver_phone.regex'          => 'صيغة رقم جوال السائق غير صحيحة (يجب أن يتكون من 9 أرقام ويبدأ بـ 77، 78، 73، 71، أو 70).',
            'source_office_name.required' => 'يرجى إدخال اسم المكتب المرسل.',
            'manifest_date.date'          => 'تاريخ الكشف غير صالح.',
        ]);

        // إذا تغير اسم أو رقم السائق ولم يحدد driver_id مختلف يدوياً، نمرر null لـ driver_id ليتم البحث أو الإنشاء الذكي عبر Driver::resolveForOffice
        $driverIdToResolve = $validated['driver_id'] ?? null;
        if (
            $driverIdToResolve &&
            (int) $driverIdToResolve === (int) $manifest->driver_id &&
            (
                (!empty($validated['driver_phone']) && $validated['driver_phone'] !== $manifest->driver_phone) ||
                ($validated['driver_name'] !== $manifest->driver_name && empty($validated['driver_phone']))
            )
        ) {
            $driverIdToResolve = null;
        }

        $manifest->update([
            'driver_id'          => $driverIdToResolve,
            'driver_name'        => $validated['driver_name'],
            'driver_phone'       => $validated['driver_phone'] ?? null,
            'source_office_name' => $validated['source_office_name'],
            'source_branch_name' => $validated['source_branch_name'] ?? null,
            'manifest_number'    => $validated['manifest_number'] ?? null,
            'manifest_date'      => !empty($validated['manifest_date']) ? $validated['manifest_date'] : $manifest->manifest_date,
            'notes'              => $validated['notes'] ?? null,
        ]);

        $manifest->load('driver:id,office_id,name,phone')->loadCount([
            'parcels',
            'parcels as postpaid_parcels_count' => fn ($q) => $q->where('payment_type', 'postpaid'),
            'parcels as prepaid_parcels_count'  => fn ($q) => $q->where('payment_type', 'prepaid'),
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'تم تحديث بيانات الكشف بنجاح.',
            'driver'   => $manifest->driver,
            'manifest' => $this->serializeManifest($manifest),
        ]);
    }

    /**
     * ضم طرود محددة إلى كشف مفتوح موجود
     */
    public function assignParcels(Request $request, int $id): JsonResponse
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user();
        $officeId   = (int) $accountant->office_id;

        $manifest = Manifest::query()
            ->where('office_id', $officeId)
            ->findOrFail($id);

        if ($manifest->status === 'settled') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن ضم طرود جديدة إلى كشف تمت تصفيته وإغلاقه.',
            ], 422);
        }

        $validated = $request->validate([
            'parcel_ids'   => ['required', 'array', 'min:1', 'max:200'],
            'parcel_ids.*' => ['string', 'uuid'],
        ], [
            'parcel_ids.required' => 'يرجى تحديد طرد واحد على الأقل لضمّه إلى الكشف.',
            'parcel_ids.min'      => 'يرجى تحديد طرد واحد على الأقل لضمّه إلى الكشف.',
            'parcel_ids.max'      => 'لا يمكن ضم أكثر من 200 طرد في عملية واحدة.',
        ]);

        $parcelIds     = array_values(array_unique($validated['parcel_ids']));
        $assignedCount = 0;

        DB::transaction(function () use ($manifest, $parcelIds, $accountant, $request, &$assignedCount) {
            $assignedCount = $this->attachParcelsToManifest(
                $manifest,
                $parcelIds,
                $accountant,
                $request->ip()
            );

            $manifest->recalculateTotals();
        });

        if ($assignedCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الطرود تم تصنيفها وضمها لكشف آخر مسبقاً، يرجى تحديث القائمة.',
            ], 422);
        }

        $manifest->loadCount([
            'parcels',
            'parcels as postpaid_parcels_count' => fn ($q) => $q->where('payment_type', 'postpaid'),
            'parcels as prepaid_parcels_count'  => fn ($q) => $q->where('payment_type', 'prepaid'),
        ]);

        return response()->json([
            'success'        => true,
            'message'        => "تم ضم ({$assignedCount}) طرد إلى كشف السائق ({$manifest->driver_name}) بنجاح.",
            'assigned_count' => $assignedCount,
            'manifest'       => $this->serializeManifest($manifest),
        ]);
    }

    /**
     * ربط مجموعة طرود غير مصنفة بكشف محدد باستخدام التحديث الجماعي السريع (Bulk Update & Bulk Audit Log Insert)
     * مع قفل الصفوف (lockForUpdate) لمنع التعارض المتزامن (Race Conditions)
     */
    private function attachParcelsToManifest(
        Manifest $manifest,
        array $parcelIds,
        Accountant $accountant,
        ?string $ipAddress
    ): int {
        $officeId = (int) $accountant->office_id;

        // جلب الطرود غير المصنفة فقط التابعة لنفس المكتب مع قفلها أثناء الـ Transaction
        $parcels = Parcel::query()
            ->select(['id', 'office_id', 'manifest_id', 'source_office_name'])
            ->where('office_id', $officeId)
            ->whereNull('manifest_id')
            ->whereIn('id', $parcelIds)
            ->lockForUpdate()
            ->get();

        if ($parcels->isEmpty()) {
            return 0;
        }

        $manifestSourceLabel = trim((string) $manifest->source_office_name);
        if (!empty($manifest->source_branch_name)) {
            $manifestSourceLabel .= ' - ' . trim((string) $manifest->source_branch_name);
        }
        $manifestSourceLabel = mb_substr($manifestSourceLabel, 0, 100);

        $now                 = now();
        $idsWithSource       = [];
        $idsWithoutSource    = [];
        $auditLogsToInsert   = [];

        foreach ($parcels as $parcel) {
            $changedFields = ['manifest_id'];
            $oldValues     = ['manifest_id' => $parcel->manifest_id];
            $newValues     = ['manifest_id' => $manifest->id];

            if (empty($parcel->source_office_name) && $manifestSourceLabel !== '') {
                $changedFields[]                 = 'source_office_name';
                $oldValues['source_office_name'] = $parcel->source_office_name;
                $newValues['source_office_name'] = $manifestSourceLabel;
                $idsWithoutSource[]              = $parcel->id;
            } else {
                $idsWithSource[] = $parcel->id;
            }

            $auditLogsToInsert[] = [
                'parcel_id'      => $parcel->id,
                'office_id'      => $officeId,
                'accountant_id'  => $accountant->id,
                'action'         => 'assign_manifest',
                'changed_fields' => json_encode($changedFields, JSON_UNESCAPED_UNICODE),
                'old_values'     => json_encode($oldValues, JSON_UNESCAPED_UNICODE),
                'new_values'     => json_encode($newValues, JSON_UNESCAPED_UNICODE),
                'ip_address'     => $ipAddress,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // تحديث جماعي (Bulk Update) بدلاً من N استعلام تحديث منفرد
        if (!empty($idsWithSource)) {
            Parcel::query()
                ->where('office_id', $officeId)
                ->whereIn('id', $idsWithSource)
                ->update([
                    'manifest_id' => $manifest->id,
                    'updated_at'  => $now,
                ]);
        }

        if (!empty($idsWithoutSource)) {
            Parcel::query()
                ->where('office_id', $officeId)
                ->whereIn('id', $idsWithoutSource)
                ->update([
                    'manifest_id'        => $manifest->id,
                    'source_office_name' => $manifestSourceLabel,
                    'updated_at'         => $now,
                ]);
        }

        // إدخال جماعي واحد (Bulk Insert) لجميع سجلات التعديل
        if (!empty($auditLogsToInsert)) {
            ParcelEditLog::query()->insert($auditLogsToInsert);
        }

        return count($auditLogsToInsert);
    }

    /**
     * تنظيف مدخلات الكشف من وسوم HTML وتحويل الأرقام العربية في الهاتف ورقم الكشف
     */
    private function sanitizeManifestInputs(Request $request): void
    {
        $cleanDriverName   = trim(strip_tags((string) $request->input('driver_name', '')));
        $cleanDriverPhone  = Driver::normalizePhone($request->input('driver_phone'));
        $cleanSourceOffice = trim(strip_tags((string) $request->input('source_office_name', '')));
        $cleanSourceBranch = $this->cleanNullableString($request->input('source_branch_name'));
        $cleanManifestNum  = $this->cleanNullableString($this->convertDigits((string) $request->input('manifest_number', '')));
        $cleanNotes        = $this->cleanNullableString($request->input('notes'));

        $request->merge([
            'driver_name'        => $cleanDriverName,
            'driver_phone'       => $cleanDriverPhone,
            'source_office_name' => $cleanSourceOffice,
            'source_branch_name' => $cleanSourceBranch,
            'manifest_number'    => $cleanManifestNum,
            'notes'              => $cleanNotes,
        ]);
    }

    private function cleanNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim(strip_tags((string) $value));

        return $trimmed === '' ? null : $trimmed;
    }

    private function convertDigits(string $text): string
    {
        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabicDigits, $englishDigits, $text);
    }

    private function serializeManifest(Manifest $manifest): array
    {
        return [
            'id'                     => $manifest->id,
            'office_id'              => $manifest->office_id,
            'accountant_id'          => $manifest->accountant_id,
            'driver_id'              => $manifest->driver_id,
            'driver_name'            => $manifest->driver_name,
            'driver_phone'           => $manifest->driver_phone,
            'source_office_name'     => $manifest->source_office_name,
            'source_branch_name'     => $manifest->source_branch_name,
            'manifest_number'        => $manifest->manifest_number,
            'manifest_date'          => $manifest->manifest_date?->format('Y-m-d'),
            'total_postpaid_amount'  => (float) ($manifest->total_postpaid_amount ?? 0),
            'total_deductions'       => (float) ($manifest->total_deductions ?? 0),
            'net_driver_amount'      => (float) ($manifest->net_driver_amount ?? 0),
            'status'                 => $manifest->status ?: 'open',
            'settled_at'             => $manifest->settled_at?->format('Y-m-d h:i A'),
            'notes'                  => $manifest->notes,
            'parcels_count'          => (int) ($manifest->parcels_count ?? 0),
            'postpaid_parcels_count' => (int) ($manifest->postpaid_parcels_count ?? 0),
            'prepaid_parcels_count'  => (int) ($manifest->prepaid_parcels_count ?? 0),
            'created_at'             => $manifest->created_at?->toIso8601String(),
        ];
    }
}
