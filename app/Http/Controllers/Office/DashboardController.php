<?php

namespace App\Http\Controllers\Office;

use App\Enums\OutgoingParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $office = Auth::guard('office')->user();

        // إحصائيات سريعة للرئيسية
        $stats = [
            'total'     => Parcel::where('office_id', $office->id)->count(),
            'in_office' => Parcel::where('office_id', $office->id)->where('status', 'in_office')->count(),
            'delivered' => Parcel::where('office_id', $office->id)->where('status', 'delivered')->count(),
            'returned'  => Parcel::where('office_id', $office->id)->where('status', 'returned')->count(),
        ];

        $inTransitCount = OutgoingParcel::query()
            ->leftJoin('shipments', 'shipments.id', '=', 'outgoing_parcels.shipment_id')
            ->where('outgoing_parcels.destination_office_id', $office->id)
            ->where('outgoing_parcels.status', OutgoingParcelStatus::IN_TRANSIT)
            ->whereNull('shipments.rejected_at')
            ->count();

        return view('office.dashboard', compact('office', 'stats', 'inTransitCount'));
    }
}