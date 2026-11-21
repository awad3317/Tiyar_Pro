<?php

namespace App\Http\Controllers\Office;

use App\Enums\OutgoingParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Services\OutgoingParcelService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncomingTransitParcelController extends Controller
{
    public function index(Request $request)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $transitParcels = OutgoingParcel::with('office:id,name,branch_name')
            ->where('destination_office_id', $office->id)
            ->where('status', OutgoingParcelStatus::IN_TRANSIT)
            ->latest('dispatched_at')
            ->get();

        return view('office.transit_parcels.index', compact('office', 'transitParcels'));
    }

    public function receive(OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->destination_office_id !== (int) $office->id) {
            abort(403, 'هذا الطرد غير موجّه إلى مكتبكم.');
        }

        try {
            $service->receiveAtDestination($outgoingParcel, $office);

            return redirect()
                ->route('office.transit-parcels.index')
                ->with('success', 'تم استلام الطرد بنجاح، تم تحويله إلى قائمة الطرود وإرسال الإشعار للعميل.');
        } catch (DomainException|AuthorizationException $e) {
            return redirect()
                ->route('office.transit-parcels.index')
                ->with('error', $e->getMessage());
        }
    }
}
