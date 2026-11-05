<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Manifest;
use App\Models\ManifestDeduction;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManifestController extends Controller
{
    /**
     * صفحة الرقابة المالية والإدارية الشاملة لجميع كشوفات السائقين والتصفيات
     */
    public function index(Request $request): View
    {
        $officeId     = $request->input('office_id');
        $accountantId = $request->input('accountant_id');
        $status       = $request->input('status');
        $dateFrom     = $request->input('date_from');
        $dateTo       = $request->input('date_to');
        $search       = trim((string) $request->input('search', ''));

        $baseQuery = Manifest::query();

        if (!empty($officeId)) {
            $baseQuery->where('office_id', (int) $officeId);
        }

        if (!empty($accountantId)) {
            $baseQuery->where('accountant_id', (int) $accountantId);
        }

        if (in_array($status, ['open', 'settled'], true)) {
            $baseQuery->where('status', $status);
        }

        if (!empty($dateFrom)) {
            $baseQuery->whereDate('manifest_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $baseQuery->whereDate('manifest_date', '<=', $dateTo);
        }

        if ($search !== '') {
            $normalizedSearch = $this->convertDigits($search);
            $cleanPhone       = preg_replace('/\D+/', '', $normalizedSearch) ?? '';

            $baseQuery->where(function ($q) use ($search, $normalizedSearch, $cleanPhone) {
                $q->where('driver_name', 'like', "%{$search}%")
                  ->orWhere('source_office_name', 'like', "%{$search}%")
                  ->orWhere('source_branch_name', 'like', "%{$search}%")
                  ->orWhere('manifest_number', 'like', "%{$normalizedSearch}%");

                if ($cleanPhone !== '') {
                    $q->orWhere('driver_phone', 'like', "%{$cleanPhone}%");
                }
            });
        }

        // 1. استعلام تجميعي واحد لحساب إحصائيات الكشوفات والمبالغ المالية حسب الفلتر الحالي
        $manifestAgg = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as total_count,
                COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) as open_count,
                COALESCE(SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END), 0) as settled_count,
                COALESCE(SUM(total_postpaid_amount), 0) as total_postpaid_amount,
                COALESCE(SUM(total_deductions), 0) as total_deductions,
                COALESCE(SUM(net_driver_amount), 0) as net_driver_amount
            ")
            ->first();

        // 2. استعلام تجميعي واحد لتفصيل أنواع الخصومات (عمولة المكاتب / تنزيل العمال / أخرى) للكشوفات المفلترة
        $deductionAgg = ManifestDeduction::query()
            ->whereIn('manifest_id', (clone $baseQuery)->select('manifests.id'))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN deduction_type = 'office_commission' THEN amount ELSE 0 END), 0) as office_commission,
                COALESCE(SUM(CASE WHEN deduction_type = 'workers_unloading' THEN amount ELSE 0 END), 0) as workers_unloading,
                COALESCE(SUM(CASE WHEN deduction_type = 'other' THEN amount ELSE 0 END), 0) as other_deductions
            ")
            ->first();

        $stats = [
            'total_count'             => (int) ($manifestAgg->total_count ?? 0),
            'open_count'              => (int) ($manifestAgg->open_count ?? 0),
            'settled_count'           => (int) ($manifestAgg->settled_count ?? 0),
            'total_postpaid_amount'   => round((float) ($manifestAgg->total_postpaid_amount ?? 0), 2),
            'total_deductions'        => round((float) ($manifestAgg->total_deductions ?? 0), 2),
            'net_driver_amount'       => round((float) ($manifestAgg->net_driver_amount ?? 0), 2),
            'office_commission_total' => round((float) ($deductionAgg->office_commission ?? 0), 2),
            'workers_unloading_total' => round((float) ($deductionAgg->workers_unloading ?? 0), 2),
            'other_deductions_total'  => round((float) ($deductionAgg->other_deductions ?? 0), 2),
        ];

        // 3. جلب الكشوفات مع المكتب والمحاسب وأعداد الطرود (الكل، الآجل، المحاسب)
        $manifests = (clone $baseQuery)
            ->with([
                'office:id,name,branch_name',
                'accountant:id,name,phone',
            ])
            ->withCount([
                'parcels',
                'parcels as postpaid_parcels_count' => fn ($q) => $q->where('payment_type', 'postpaid'),
                'parcels as prepaid_parcels_count'  => fn ($q) => $q->where('payment_type', 'prepaid'),
            ])
            ->latest('manifest_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        // قوائم الفلترة السريعة للمكاتب والمحاسبين
        $offices = Office::query()
            ->select(['id', 'name', 'branch_name'])
            ->orderBy('name')
            ->get();

        $accountantsQuery = Accountant::query()
            ->select(['id', 'office_id', 'name', 'phone'])
            ->with('office:id,name')
            ->orderBy('name');

        if (!empty($officeId)) {
            $accountantsQuery->where('office_id', (int) $officeId);
        }
        $accountants = $accountantsQuery->get();

        return view('admin.manifests.index', [
            'manifests'   => $manifests,
            'offices'     => $offices,
            'accountants' => $accountants,
            'stats'       => $stats,
            'filters'     => [
                'office_id'     => $officeId,
                'accountant_id' => $accountantId,
                'status'        => $status,
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
                'search'        => $search,
            ],
        ]);
    }

    /**
     * عرض تفاصيل كشف سائق محدد للأدمن (البيانات، المعادلة المالية، بنود الخصومات، الطرود، والطباعة)
     */
    public function show(int $id): View
    {
        $manifest = Manifest::query()
            ->with([
                'office:id,name,branch_name,whatsapp_sender_phone',
                'accountant:id,name,phone',
                'driver:id,name,phone',
                'deductions' => fn ($q) => $q->orderBy('id'),
            ])
            ->findOrFail($id);

        $parcels = $manifest->parcels()
            ->withCount('editLogs')
            ->latest()
            ->get();

        $totalParcelsCount    = $parcels->count();
        $postpaidParcelsCount = $parcels->where('payment_type', 'postpaid')->count();
        $prepaidParcelsCount  = $totalParcelsCount - $postpaidParcelsCount;

        $deductionBreakdown = [
            'office_commission' => round((float) $manifest->deductions->where('deduction_type', 'office_commission')->sum('amount'), 2),
            'workers_unloading' => round((float) $manifest->deductions->where('deduction_type', 'workers_unloading')->sum('amount'), 2),
            'other'             => round((float) $manifest->deductions->where('deduction_type', 'other')->sum('amount'), 2),
        ];

        return view('admin.manifests.show', [
            'manifest'              => $manifest,
            'parcels'               => $parcels,
            'totalParcelsCount'     => $totalParcelsCount,
            'postpaidParcelsCount'  => $postpaidParcelsCount,
            'prepaidParcelsCount'   => $prepaidParcelsCount,
            'deductionBreakdown'    => $deductionBreakdown,
        ]);
    }

    private function convertDigits(string $text): string
    {
        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabicDigits, $englishDigits, $text);
    }
}

