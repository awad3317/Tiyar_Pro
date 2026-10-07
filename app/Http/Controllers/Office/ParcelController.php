<?php

namespace App\Http\Controllers\Office;

use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Services\HttpSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ParcelController extends Controller
{
    public function index()
    {
        $office = Auth::guard('office')->user();
        return view('office.parcels.index', compact('office'));
    }

    public function getParcelsData()
    {
        $officeId = Auth::guard('office')->id();
        
        $parcels = Parcel::where('office_id', $officeId)
            ->latest('created_at')
            ->take(150)
            ->get();

        return response()->json($parcels);
    }

    /**
     * استقبال طابور التعديلات من الواجهة (أونلاين/أوفلاين) وتطبيقها بالترتيب.
     * أي انتقال غير مسموح (مثل: تم التسليم → مرتجع) يُرفض ويُعاد معرّفه في rejected.
     */
    public function syncUpdates(Request $request)
    {
        $updates = collect($request->input('updates', []))
            ->filter(fn ($item) => is_array($item) && ! empty($item['id']) && ! empty($item['status']));

        $parcels = Parcel::where('office_id', Auth::guard('office')->id())
            ->whereIn('id', $updates->pluck('id')->unique())
            ->get()
            ->keyBy('id');

        $synced = 0;
        $rejected = [];

        foreach ($updates as $item) {
            $parcel = $parcels->get($item['id']);
            $status = ParcelStatus::tryFrom($item['status']);

            if ($parcel && $status && $parcel->transitionTo($status, $this->parseDate($item['delivered_at'] ?? null))) {
                $synced++;
            } else {
                $rejected[] = $item['id'];
            }
        }

        return response()->json([
            'status'   => 'synced',
            'count'    => $synced,
            'rejected' => array_values(array_unique($rejected)),
        ]);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * إعادة إرسال إشعار SMS للمستلم (مخصص للطرود المتواجدة في المكتب فقط عبر httpSMS).
     */
    public function resendSms(Parcel $parcel, HttpSmsService $smsService)
    {
        $office = Auth::guard('office')->user();

        // التحقق من تبعية الطرد لنفس المكتب
        if ($parcel->office_id !== $office->id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإدارة هذا الطرد.'
            ], 403);
        }

        // التحقق من أن حالة الطرد هي في المكتب فقط
        if ($parcel->status !== ParcelStatus::InOffice) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن إعادة إرسال رسائل SMS للطرود المتواجدة بالمكتب فقط.'
            ], 422);
        }

        // التحقق من وجود إعدادات httpSMS للمكتب
        if (empty($office->httpsms_api_key) || empty($office->httpsms_from_phone)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات بوابة الرسائل (httpSMS) غير مكتملة في إعدادات المكتب.'
            ], 422);
        }

        // تجهيز نص الرسالة بالاعتماد على قالب المكتب أو القالب الافتراضي
        $defaultTemplate = "عميلنا العزيز،\nمكتب: {office} ({branch})\nتم استلام طردكم: {package}\nيرجى التوجه للفرع للاستلام. شكراً لتعاملكم معنا.";
        $template = !empty($office->sms_template) ? $office->sms_template : $defaultTemplate;

        $smsBody = str_replace(
            ['{office}', '{branch}', '{package}', '{receipt}'],
            [
                $office->name,
                $office->branch_name ?? '',
                $parcel->package_type,
                $parcel->receipt_number ?? ''
            ],
            $template
        );

        $smsBody = str_replace(' ()', '', $smsBody);

        // إرسال الرسالة عبر خدمة SMS المستقلة
        $result = $smsService->send(
            $parcel->recipient_phone,
            $smsBody,
            $office->httpsms_api_key,
            $office->httpsms_from_phone
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة إرسال رسالة الـ SMS للمستلم بنجاح.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'تعذر إرسال رسالة SMS: ' . ($result['error'] ?? 'خطأ في بوابة الرسائل')
        ], 500);
    }
}