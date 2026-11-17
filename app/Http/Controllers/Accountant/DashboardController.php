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
                SUM(CASE WHEN payment_type = 'postpaid' THEN 1 ELSE 0 END) as postpaid_unclassified
            ")
            ->first();

        $manifestStats = DB::table('manifests')
            ->where('office_id', $officeId)
            ->selectRaw("
                COUNT(*) as total_manifests,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_manifests,
                SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) as settled_manifests
            ")
            ->first();

        $totalUnclassified    = (int) ($parcelStats->total_unclassified ?? 0);
        $postpaidUnclassified = (int) ($parcelStats->postpaid_unclassified ?? 0);

        $stats = [
            'unclassified_total'    => $totalUnclassified,
            'unclassified_postpaid' => $postpaidUnclassified,
            'unclassified_prepaid'  => max(0, $totalUnclassified - $postpaidUnclassified),
            'manifests_total'       => (int) ($manifestStats->total_manifests ?? 0),
            'manifests_open'        => (int) ($manifestStats->open_manifests ?? 0),
            'manifests_settled'     => (int) ($manifestStats->settled_manifests ?? 0),
        ];

        return view('accountant.dashboard', compact('accountant', 'office', 'stats'));
    }
}

