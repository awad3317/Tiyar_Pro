<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Parcel;
use App\Models\WhatsappInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * عرض لوحة التحكم الرئيسية وإحصائيات النظام الشاملة
     */
    public function index(Request $request): View
    {
        // 1. إحصائيات المكاتب
        $totalOffices    = Office::count();
        $activeOffices   = Office::where('is_active', true)->count();
        $inactiveOffices = $totalOffices - $activeOffices;

        // 2. إحصائيات الطرود والرسائل
        $totalParcels     = Parcel::count();
        $inOfficeParcels  = Parcel::where('status', ParcelStatus::InOffice)->count();
        $deliveredParcels = Parcel::where('status', ParcelStatus::Delivered)->count();
        $returnedParcels  = Parcel::where('status', ParcelStatus::Returned)->count();
        $todayParcels     = Parcel::whereDate('created_at', today())->count();

        // 3. إحصائيات الـ Instances
        $totalInstances  = WhatsappInstance::count();
        $activeInstances = WhatsappInstance::where('is_active', true)->count();

        // 4. جلب المكاتب وربط حالة كاش الواتساب لكل مكتب
        $offices = Office::withCount([
            'parcels',
            'parcels as today_parcels_count' => fn ($q) => $q->whereDate('created_at', today()),
            'parcels as delivered_count'     => fn ($q) => $q->where('status', ParcelStatus::Delivered),
        ])
        ->latest('updated_at')
        ->get();

        $onlineOfficesCount  = 0;
        $offlineOfficesCount = 0;

        foreach ($offices as $office) {
            $cacheKey = 'whatsapp_status_office_' . $office->id;
            $cachedStatus = Cache::get($cacheKey);

            if ($cachedStatus === 'online') {
                $office->whatsapp_live_status = 'online';
                $onlineOfficesCount++;
            } else {
                $office->whatsapp_live_status = $office->hasWhatsAppConfigured() ? ($cachedStatus ?: 'offline') : 'not_configured';
                $offlineOfficesCount++;
            }
        }

        return view('admin.dashboard', [
            'stats' => [
                'total_offices'          => $totalOffices,
                'active_offices'         => $activeOffices,
                'inactive_offices'       => $inactiveOffices,
                'total_parcels'          => $totalParcels,
                'in_office_parcels'      => $inOfficeParcels,
                'delivered_parcels'      => $deliveredParcels,
                'returned_parcels'       => $returnedParcels,
                'today_parcels'          => $todayParcels,
                'whatsapp_online_count'  => $onlineOfficesCount,
                'whatsapp_offline_count' => $offlineOfficesCount,
                'total_instances'        => $totalInstances,
                'active_instances'       => $activeInstances,
            ],
            'offices' => $offices,
        ]);
    }
}
