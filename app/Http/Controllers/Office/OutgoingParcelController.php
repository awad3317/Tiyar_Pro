<?php

namespace App\Http\Controllers\Office;

use App\Enums\OutgoingParcelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreOutgoingParcelRequest;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Services\OutgoingParcelService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OutgoingParcelController extends Controller
{
    public function index(Request $request)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        $statusFilter = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        $query = $office->outgoingParcels()
            ->with([
                'destinationOffice:id,name,branch_name',
                'linkedIncomingParcel:id,status,delivered_at',
            ]);

        $allowedStatuses = array_map(
            static fn (OutgoingParcelStatus $s) => $s->value,
            OutgoingParcelStatus::cases()
        );

        if (in_array($statusFilter, $allowedStatuses, true)) {
            $query->where('status', $statusFilter);
        } else {
            $statusFilter = 'all';
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('sender_name', 'like', $like)
                    ->orWhere('sender_phone', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhere('recipient_phone', 'like', $like)
                    ->orWhere('receipt_number', 'like', $like)
                    ->orWhere('external_office_name', 'like', $like);
            });
        }

        $rawCounts = $office->outgoingParcels()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'all'        => (int) $rawCounts->sum(),
            'pending'    => (int) ($rawCounts[OutgoingParcelStatus::PENDING->value] ?? 0),
            'in_transit' => (int) ($rawCounts[OutgoingParcelStatus::IN_TRANSIT->value] ?? 0),
            'received'   => (int) ($rawCounts[OutgoingParcelStatus::RECEIVED->value] ?? 0),
            'delivered'  => (int) ($rawCounts[OutgoingParcelStatus::DELIVERED->value] ?? 0),
            'cancelled'  => (int) ($rawCounts[OutgoingParcelStatus::CANCELLED->value] ?? 0),
        ];

        $destinationOffices = Office::where('is_active', true)
            ->where('id', '!=', $office->id)
            ->orderBy('name')
            ->get(['id', 'name', 'branch_name']);

        $parcels = $query->latest('created_at')->paginate(20)->withQueryString();

        return view('office.outgoing_parcels.index', compact(
            'office',
            'parcels',
            'counts',
            'destinationOffices',
            'statusFilter',
            'search'
        ));
    }

    public function store(StoreOutgoingParcelRequest $request, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();
        $validated = $request->validated();

        if (($validated['destination_type'] ?? 'office') === 'office') {
            $validated['external_office_name'] = null;
        } else {
            $validated['destination_office_id'] = null;
        }

        try {
            $service->create($office, $validated);

            return redirect()
                ->route('office.outgoing-parcels.index')
                ->with('success', 'تم تسجيل الطرد الصادر بنجاح.');
        } catch (DomainException|AuthorizationException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function dispatch(OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->office_id !== (int) $office->id) {
            abort(403, 'غير مصرح لك بإدارة هذا الطرد.');
        }

        try {
            $service->markInTransit($outgoingParcel, $office);

            return redirect()
                ->back()
                ->with('success', 'تم تحديث حالة الطرد إلى (في الطريق) بنجاح.');
        } catch (DomainException|AuthorizationException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function deliverExternal(OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->office_id !== (int) $office->id) {
            abort(403, 'غير مصرح لك بإدارة هذا الطرد.');
        }

        try {
            $service->markExternalDelivered($outgoingParcel, $office);

            return redirect()
                ->back()
                ->with('success', 'تم تأكيد تسليم الطرد للمكتب الخارجي بنجاح.');
        } catch (DomainException|AuthorizationException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function cancel(OutgoingParcel $outgoingParcel, OutgoingParcelService $service)
    {
        /** @var Office $office */
        $office = Auth::guard('office')->user();

        if ((int) $outgoingParcel->office_id !== (int) $office->id) {
            abort(403, 'غير مصرح لك بإدارة هذا الطرد.');
        }

        try {
            $service->cancel($outgoingParcel, $office);

            return redirect()
                ->back()
                ->with('success', 'تم إلغاء الطرد الصادر بنجاح.');
        } catch (DomainException|AuthorizationException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}
