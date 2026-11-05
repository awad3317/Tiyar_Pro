<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Office;
use App\Models\ParcelEditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParcelEditLogController extends Controller
{
    /**
     * شاشة سجل تتبع تعديلات المحاسبين على الطرود (Audit Logs) في لوحة تحكم الأدمن
     */
    public function index(Request $request): View
    {
        $officeId     = $request->input('office_id');
        $accountantId = $request->input('accountant_id');
        $action       = $request->input('action');
        $dateFrom     = $request->input('date_from');
        $dateTo       = $request->input('date_to');
        $parcelId     = trim((string) $request->input('parcel_id', ''));
        $search       = trim((string) $request->input('search', ''));

        $baseQuery = ParcelEditLog::query();

        if (!empty($officeId)) {
            $baseQuery->where('office_id', (int) $officeId);
        }

        if (!empty($accountantId)) {
            $baseQuery->where('accountant_id', (int) $accountantId);
        }

        if (in_array($action, ['update_details', 'assign_manifest', 'remove_from_manifest'], true)) {
            $baseQuery->where('action', $action);
        }

        if ($parcelId !== '') {
            $baseQuery->where('parcel_id', $parcelId);
        }

        if (!empty($dateFrom)) {
            $baseQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $baseQuery->whereDate('created_at', '<=', $dateTo);
        }

        if ($search !== '') {
            $normalizedSearch = $this->convertDigits($search);
            $cleanPhone       = preg_replace('/\D+/', '', $normalizedSearch) ?? '';

            $baseQuery->whereHas('parcel', function ($q) use ($search, $normalizedSearch, $cleanPhone) {
                $q->where('recipient_name', 'like', "%{$search}%")
                  ->orWhere('receipt_number', 'like', "%{$normalizedSearch}%")
                  ->orWhere('package_type', 'like', "%{$search}%");

                if ($cleanPhone !== '') {
                    $q->orWhere('recipient_phone', 'like', "%{$cleanPhone}%");
                }
            });
        }

        // استعلام تجميعي واحد لحساب إحصائيات حركات التعديل حسب الفلتر الحالي
        $agg = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as total_logs,
                COALESCE(SUM(CASE WHEN action = 'update_details' THEN 1 ELSE 0 END), 0) as update_details_count,
                COALESCE(SUM(CASE WHEN action = 'assign_manifest' THEN 1 ELSE 0 END), 0) as assign_manifest_count,
                COALESCE(SUM(CASE WHEN action = 'remove_from_manifest' THEN 1 ELSE 0 END), 0) as remove_manifest_count
            ")
            ->first();

        $stats = [
            'total_logs'            => (int) ($agg->total_logs ?? 0),
            'update_details_count'  => (int) ($agg->update_details_count ?? 0),
            'assign_manifest_count' => (int) ($agg->assign_manifest_count ?? 0),
            'remove_manifest_count' => (int) ($agg->remove_manifest_count ?? 0),
        ];

        $logs = (clone $baseQuery)
            ->with([
                'office:id,name,branch_name',
                'accountant:id,name,phone',
                'parcel:id,office_id,manifest_id,recipient_name,recipient_phone,package_type,receipt_number,payment_type,cod_amount',
            ])
            ->latest()
            ->paginate(30)
            ->withQueryString();

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

        return view('admin.parcel-edit-logs.index', [
            'logs'        => $logs,
            'offices'     => $offices,
            'accountants' => $accountants,
            'stats'       => $stats,
            'filters'     => [
                'office_id'     => $officeId,
                'accountant_id' => $accountantId,
                'action'        => $action,
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
                'parcel_id'     => $parcelId,
                'search'        => $search,
            ],
        ]);
    }

    private function convertDigits(string $text): string
    {
        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabicDigits, $englishDigits, $text);
    }
}

