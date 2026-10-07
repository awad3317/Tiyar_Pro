<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->latest()
            ->take(150)
            ->get();

        return response()->json($parcels);
    }

    public function syncUpdates(Request $request)
    {
        $updates = $request->input('updates', []);
        $officeId = Auth::guard('office')->id();

        foreach ($updates as $item) {
            $parcel = Parcel::where('id', $item['id'])
                ->where('office_id', $officeId)
                ->first();

            if ($parcel) {
                $status = $item['status'];
                $deliveredAt = ($status === 'delivered') 
                    ? ($item['delivered_at'] ?? now()) 
                    : null;

                $parcel->update([
                    'status' => $status,
                    'delivered_at' => $deliveredAt,
                ]);
            }
        }

        return response()->json(['status' => 'synced', 'count' => count($updates)]);
    }
}