<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Parcel;
use App\Models\ParcelEditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ParcelController extends Controller
{
    /**
     * الحقول القابلة للتعديل والتتبع في سجل تعديلات المحاسب
     */
    private const TRACKED_FIELDS = [
        'recipient_name',
        'recipient_phone',
        'package_type',
        'receipt_number',
        'source_office_name',
        'payment_type',
        'cod_amount',
    ];

    /**
     * عرض شاشة الطرود غير المصنفة في تطبيق المحاسب
     */
    public function index(Request $request): View
    {
        return view('accountant.parcels.index');
    }

    /**
     * API لجلب الطرود غير المصنفة التابعة لمكتب المحاسب مع الإحصائيات السريعة
     */
    public function listData(Request $request): JsonResponse
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user();

        $parcels = Parcel::query()
            ->select([
                'id',
                'office_id',
                'manifest_id',
                'recipient_name',
                'recipient_phone',
                'package_type',
                'receipt_number',
                'source_office_name',
                'payment_type',
                'cod_amount',
                'status',
                'created_at',
                'delivered_at',
            ])
            ->where('office_id', $accountant->office_id)
            ->whereNull('manifest_id')
            ->withCount('editLogs')
            ->latest()
            ->get();

        $totalCount     = $parcels->count();
        $postpaidItems  = $parcels->where('payment_type', 'postpaid');
        $postpaidCount  = $postpaidItems->count();
        $postpaidAmount = (float) $postpaidItems->sum(fn (Parcel $p) => (float) ($p->cod_amount ?? 0));
        $prepaidCount   = $totalCount - $postpaidCount;

        return response()->json([
            'success' => true,
            'stats'   => [
                'total_count'     => $totalCount,
                'postpaid_count'  => $postpaidCount,
                'postpaid_amount' => round($postpaidAmount, 2),
                'prepaid_count'   => $prepaidCount,
            ],
            'parcels' => $parcels->map(fn (Parcel $p) => $this->serializeParcel($p))->values(),
        ]);
    }

    /**
     * تعديل بيانات الطرد بواسطة المحاسب مع توثيق التغييرات تلقائياً في جدول parcel_edit_logs
     */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user();

        $parcel = Parcel::query()
            ->where('office_id', $accountant->office_id)
            ->findOrFail($id);

        // تنظيف المدخلات قبل التحقق (مع دعم تحويل الأرقام العربية في الهاتف والمبلغ والسند وحماية XSS)
        $cleanPhone         = $this->normalizePhone($request->input('recipient_phone'));
        $cleanRecipientName = $this->cleanNullableString($request->input('recipient_name'));
        $cleanPackageType   = trim(strip_tags((string) $request->input('package_type', '')));
        $cleanReceiptNumber = $this->cleanNullableString($this->convertDigits((string) $request->input('receipt_number', '')));
        $cleanSourceOffice  = $this->cleanNullableString($request->input('source_office_name'));
        $cleanCodAmount     = $this->cleanNullableString($this->convertDigits((string) $request->input('cod_amount', '')));

        $request->merge([
            'recipient_phone'    => $cleanPhone,
            'recipient_name'     => $cleanRecipientName,
            'package_type'       => $cleanPackageType,
            'receipt_number'     => $cleanReceiptNumber,
            'source_office_name' => $cleanSourceOffice,
            'cod_amount'         => $cleanCodAmount,
        ]);

        $validated = $request->validate([
            'recipient_name'     => ['nullable', 'string', 'max:100'],
            'recipient_phone'    => ['required', 'regex:/^7[01378]\d{7}$/'],
            'package_type'       => ['required', 'string', 'max:100'],
            'receipt_number'     => ['nullable', 'string', 'max:50'],
            'source_office_name' => ['nullable', 'string', 'max:100'],
            'payment_type'       => ['required', 'in:postpaid,prepaid'],
            'cod_amount'         => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'recipient_phone.required' => 'رقم جوال المستلم مطلوب.',
            'recipient_phone.regex'    => 'صيغة رقم الجوال غير صحيحة (يجب أن يتكون من 9 أرقام ويبدأ بـ 77، 78، 73، 71، أو 70).',
            'package_type.required'    => 'يرجى تحديد نوع الطرد.',
            'payment_type.required'    => 'يرجى تحديد نوع الحساب (آجل أو محاسب).',
            'payment_type.in'          => 'نوع الحساب المختار غير صالح.',
            'cod_amount.numeric'       => 'المبلغ المطلوب يجب أن يكون رقماً صحيحاً.',
            'cod_amount.min'           => 'المبلغ المطلوب لا يمكن أن يكون بالسالب.',
        ]);

        // ضبط منطق نوع الدفع والمبلغ: إذا كان محاسب (prepaid) يصبح cod_amount = null تلقائياً
        $paymentType = $validated['payment_type'];
        $codAmount   = null;

        if ($paymentType === 'postpaid') {
            $rawAmount = $validated['cod_amount'] ?? 0;
            $codAmount = ($rawAmount !== null && $rawAmount !== '') ? round((float) $rawAmount, 2) : 0.0;
        }

        $newData = [
            'recipient_name'     => $validated['recipient_name'],
            'recipient_phone'    => $validated['recipient_phone'],
            'package_type'       => $validated['package_type'],
            'receipt_number'     => $validated['receipt_number'],
            'source_office_name' => $validated['source_office_name'],
            'payment_type'       => $paymentType,
            'cod_amount'         => $codAmount,
        ];

        // استخراج القيم القديمة ومقارنتها بالقيم الجديدة لتحديد الحقول المتغيرة فقط
        $changedFields = [];
        $oldValues     = [];
        $newValues     = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $oldVal = $this->normalizeFieldForComparison($field, $parcel->{$field});
            $newVal = $this->normalizeFieldForComparison($field, $newData[$field]);

            if ($oldVal !== $newVal) {
                $changedFields[]    = $field;
                $oldValues[$field]  = $oldVal;
                $newValues[$field]  = $newVal;
            }
        }

        if (!empty($changedFields)) {
            // حماية نزاهة الكشوفات المصفّاة: منع تعديل المبالغ المالية لطرد مدرج في كشف تمت تصفيته
            if (
                $parcel->manifest_id &&
                (in_array('payment_type', $changedFields, true) || in_array('cod_amount', $changedFields, true)) &&
                $parcel->manifest?->status === 'settled'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تعديل البيانات المالية لطرد مدرج في كشف سائق تمت تصفيته بالفعل.',
                ], 422);
            }

            DB::transaction(function () use ($parcel, $newData, $accountant, $changedFields, $oldValues, $newValues, $request) {
                $parcel->update($newData);

                ParcelEditLog::create([
                    'parcel_id'      => $parcel->id,
                    'office_id'      => $accountant->office_id,
                    'accountant_id'  => $accountant->id,
                    'action'         => 'update_details',
                    'changed_fields' => $changedFields,
                    'old_values'     => $oldValues,
                    'new_values'     => $newValues,
                    'ip_address'     => $request->ip(),
                ]);

                if ($parcel->manifest_id) {
                    $parcel->manifest?->recalculateTotals();
                }
            });
        }

        $parcel->loadCount('editLogs');
        $manifestData = null;
        if ($parcel->manifest_id) {
            $freshManifest = $parcel->manifest()->first();
            if ($freshManifest) {
                $manifestData = [
                    'id'                    => $freshManifest->id,
                    'total_postpaid_amount' => (float) $freshManifest->total_postpaid_amount,
                    'total_deductions'      => (float) $freshManifest->total_deductions,
                    'net_driver_amount'     => (float) $freshManifest->net_driver_amount,
                    'status'                => $freshManifest->status,
                ];
            }
        }

        return response()->json([
            'success'        => true,
            'message'        => !empty($changedFields)
                ? 'تم حفظ تعديلات الطرد وتوثيقها في السجل بنجاح.'
                : 'لم يتم إجراء أي تغيير جديد على بيانات الطرد.',
            'changed_fields' => $changedFields,
            'parcel'         => $this->serializeParcel($parcel),
            'manifest'       => $manifestData,
        ]);
    }

    /**
     * عرض سجل التعديلات (Audit Log) لطرد معين
     */
    public function logs(string $id): JsonResponse
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user();

        $parcel = Parcel::query()
            ->where('office_id', $accountant->office_id)
            ->findOrFail($id);

        $logs = ParcelEditLog::query()
            ->with('accountant:id,name,phone')
            ->where('office_id', $accountant->office_id)
            ->where('parcel_id', $parcel->id)
            ->latest()
            ->get();

        $formattedLogs = $logs->map(function (ParcelEditLog $log) {
            $changedFields = is_array($log->changed_fields) ? $log->changed_fields : [];
            $oldValues     = is_array($log->old_values) ? $log->old_values : [];
            $newValues     = is_array($log->new_values) ? $log->new_values : [];

            $changes = [];
            foreach ($changedFields as $field) {
                $changes[] = [
                    'field'     => $field,
                    'label'     => ParcelEditLog::fieldLabel($field),
                    'old_value' => $oldValues[$field] ?? null,
                    'new_value' => $newValues[$field] ?? null,
                    'old_text'  => ParcelEditLog::formatValue($field, $oldValues[$field] ?? null),
                    'new_text'  => ParcelEditLog::formatValue($field, $newValues[$field] ?? null),
                ];
            }

            return [
                'id'                   => $log->id,
                'action'               => $log->action,
                'accountant_id'        => $log->accountant_id,
                'accountant_name'      => $log->accountant?->name ?? 'محاسب غير محدد',
                'changed_fields'       => $changedFields,
                'old_values'           => $oldValues,
                'new_values'           => $newValues,
                'changes'              => $changes,
                'created_at'           => $log->created_at?->toIso8601String(),
                'created_at_formatted' => $log->created_at?->format('Y-m-d h:i A'),
                'created_at_human'     => $log->created_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'success' => true,
            'parcel'  => [
                'id'              => $parcel->id,
                'recipient_name'  => $parcel->recipient_name,
                'recipient_phone' => $parcel->recipient_phone,
                'package_type'    => $parcel->package_type,
                'receipt_number'  => $parcel->receipt_number,
            ],
            'logs'    => $formattedLogs,
        ]);
    }

    /**
     * تجهيز بيانات الطرد للواجهة بصيغة موحدة
     */
    private function serializeParcel(Parcel $parcel): array
    {
        return [
            'id'                 => $parcel->id,
            'office_id'          => $parcel->office_id,
            'manifest_id'        => $parcel->manifest_id,
            'recipient_name'     => $parcel->recipient_name,
            'recipient_phone'    => $parcel->recipient_phone,
            'package_type'       => $parcel->package_type,
            'receipt_number'     => $parcel->receipt_number,
            'source_office_name' => $parcel->source_office_name,
            'payment_type'       => $parcel->payment_type ?: ((float) ($parcel->cod_amount ?? 0) > 0 ? 'postpaid' : 'prepaid'),
            'cod_amount'         => $parcel->cod_amount !== null ? (float) $parcel->cod_amount : null,
            'status'             => $parcel->status instanceof \BackedEnum ? $parcel->status->value : (string) $parcel->status,
            'edit_logs_count'    => (int) ($parcel->edit_logs_count ?? 0),
            'created_at'         => $parcel->created_at?->toIso8601String(),
            'delivered_at'       => $parcel->delivered_at?->toIso8601String(),
        ];
    }

    /**
     * توحيد القيم عند المقارنة بين القديم والجديد لمنع تسجيل تغييرات وهمية
     */
    private function normalizeFieldForComparison(string $field, mixed $value): mixed
    {
        if ($field === 'cod_amount') {
            if ($value === null || $value === '') {
                return null;
            }
            return round((float) $value, 2);
        }

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
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

    private function normalizePhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        $converted = $this->convertDigits($phone);
        $digits    = preg_replace('/\D+/', '', $converted) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '9677')) {
            $digits = substr($digits, 3);
        } elseif (strlen($digits) === 14 && str_starts_with($digits, '009677')) {
            $digits = substr($digits, 5);
        }

        return $digits;
    }
}

