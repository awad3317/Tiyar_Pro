<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * عرض لوحة التحكم الرئيسية للمحاسب مع الإحصائيات الفورية
     */
    public function index(): View
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user()->loadMissing('office');
        $office     = $accountant->office;
        $officeId   = (int) $accountant->office_id;

        $parcelStats = DB::table('parcels')
            ->where('office_id', $officeId)
            ->whereNull('manifest_id')
            ->selectRaw("
                COUNT(*) as total_unclassified,
                SUM(CASE WHEN payment_type = 'postpaid' THEN 1 ELSE 0 END) as postpaid_unclassified,
                SUM(CASE WHEN payment_type = 'postpaid' THEN COALESCE(cod_amount, 0) ELSE 0 END) as postpaid_unclassified_amount
            ")
            ->first();

        $manifestStats = DB::table('manifests')
            ->where('office_id', $officeId)
            ->selectRaw("
                COUNT(*) as total_manifests,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_manifests,
                SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) as settled_manifests,
                SUM(CASE WHEN status = 'open' THEN COALESCE(net_driver_amount, 0) ELSE 0 END) as open_net_amount,
                SUM(CASE WHEN status = 'settled' THEN COALESCE(net_driver_amount, 0) ELSE 0 END) as settled_net_amount,
                SUM(COALESCE(total_deductions, 0)) as total_deductions
            ")
            ->first();

        $totalUnclassified    = (int) ($parcelStats->total_unclassified ?? 0);
        $postpaidUnclassified = (int) ($parcelStats->postpaid_unclassified ?? 0);

        $stats = [
            'unclassified_total'           => $totalUnclassified,
            'unclassified_postpaid'        => $postpaidUnclassified,
            'unclassified_prepaid'         => max(0, $totalUnclassified - $postpaidUnclassified),
            'unclassified_postpaid_amount' => (float) ($parcelStats->postpaid_unclassified_amount ?? 0),
            'manifests_total'              => (int) ($manifestStats->total_manifests ?? 0),
            'manifests_open'               => (int) ($manifestStats->open_manifests ?? 0),
            'manifests_settled'            => (int) ($manifestStats->settled_manifests ?? 0),
            'open_net_amount'              => (float) ($manifestStats->open_net_amount ?? 0),
            'settled_net_amount'           => (float) ($manifestStats->settled_net_amount ?? 0),
            'total_deductions'             => (float) ($manifestStats->total_deductions ?? 0),
        ];

        return view('accountant.dashboard', compact('accountant', 'office', 'stats'));
    }
}

