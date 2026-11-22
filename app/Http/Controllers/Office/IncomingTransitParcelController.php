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
    private const SELECT_COLUMNS = [
        'id',
        'office_id',
        'destination_office_id',
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'package_type',
        'receipt_number',
        'cod_amount',
        'payment_type',
        'notes',
        'status',
        'dispatched_at',
        'created_at',
    ];

    private const NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
        'Vary'          => 'Accept, X-Requested-With',
    ];

    public function index(Request $request)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $transitParcels = OutgoingParcel::query()
            ->select(self::SELECT_COLUMNS)
            ->with('office:id,name,branch_name')
            ->where('destination_office_id', $office->id)
            ->where('status', OutgoingParcelStatus::IN_TRANSIT)
            ->latest('dispatched_at')
            ->get();

        if ($request->expectsJson() || $request->ajax()) {
            return response()
                ->json([
                    'ok'              => true,
                    'remaining_count' => $transitParcels->count(),
                    'parcels'         => $transitParcels,
                ])
                ->withHeaders(self::NO_CACHE_HEADERS);
        }

        return response()
            ->view('office.transit_parcels.index', compact('office', 'transitParcels'))
            ->withHeaders(self::NO_CACHE_HEADERS);
    }

    public function receive(Request $request, OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->destination_office_id !== (int) $office->id) {
            abort(403, 'هذا الطرد غير موجّه إلى مكتبكم.');
        }

        try {
            $incomingParcel = $service->receiveAtDestination($outgoingParcel, $office, afterResponse: true);
            $successMessage = 'تم استلام الطرد بنجاح، تم تحويله إلى قائمة الطرود وإرسال الإشعار للعميل.';

            if ($request->expectsJson() || $request->ajax()) {
                $remainingCount = OutgoingParcel::where('destination_office_id', $office->id)
                    ->where('status', OutgoingParcelStatus::IN_TRANSIT)
                    ->count();

                return response()->json([
                    'ok'              => true,
                    'message'         => $successMessage,
                    'parcel_id'       => $outgoingParcel->id,
                    'incoming_id'     => $incomingParcel->id,
                    'remaining_count' => $remainingCount,
                ]);
            }

            return redirect()
                ->route('office.transit-parcels.index')
                ->with('success', $successMessage);
        } catch (DomainException|AuthorizationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->route('office.transit-parcels.index')
                ->with('error', $e->getMessage());
        }
    }
}
