<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FavoriteCustomer;
use App\Models\Feature;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FavoriteCustomerController extends Controller
{
    /**
     * عرض قائمة العملاء المميزين للمكاتب مع الفلترة والبحث المتقدم.
     */
    public function index(Request $request): View
    {
        $query = FavoriteCustomer::query()->with('office');

        // فلترة حسب المكتب
        $officeId = $request->input('office_id');
        if ($officeId) {
            $query->where('office_id', $officeId);
        }

        // البحث بالاسم أو رقم الهاتف
        $search = trim($request->input('search', ''));
        if ($search !== '') {
            $cleanPhone = preg_replace('/[^0-9]/', '', $search);
            $query->where(function ($q) use ($search, $cleanPhone) {
                $q->where('name', 'like', "%{$search}%");
                if (!empty($cleanPhone)) {
                    $q->orWhere('phone', 'like', "%{$cleanPhone}%");
                }
            });
        }

        // إحصائيات عامة
        $totalAll = FavoriteCustomer::count();
        $officesWithCustomers = Office::has('favoriteCustomers')->count();

        $vipFeature = Feature::where('code', 'vip_customers_tab')->first();
        $officesWithVipFeature = 0;
        if ($vipFeature) {
            $officesWithVipFeature = DB::table('office_features')
                ->where('feature_id', $vipFeature->id)
                ->where('is_enabled', true)
                ->count();
        }

        // تفاصيل المكتب المختار (إن وُجد)
        $selectedOffice = null;
        $selectedOfficeCount = 0;
        $selectedOfficeHasVipFeature = false;

        if ($officeId) {
            $selectedOffice = Office::find($officeId);
            if ($selectedOffice) {
                $selectedOfficeCount = $selectedOffice->favoriteCustomers()->count();
                $selectedOfficeHasVipFeature = $selectedOffice->hasFeature('vip_customers_tab');
            }
        }

        // جلب البيانات مع الترقيم
        $customers = $query->latest('updated_at')->paginate(25)->withQueryString();

        // قائمة المكاتب للفلتر المنسدل مع عدد العملاء لكل مكتب
        $offices = Office::withCount('favoriteCustomers')->orderBy('name')->get();

        return view('admin.favorite_customers.index', [
            'customers' => $customers,
            'offices' => $offices,
            'selectedOffice' => $selectedOffice,
            'selectedOfficeCount' => $selectedOfficeCount,
            'selectedOfficeHasVipFeature' => $selectedOfficeHasVipFeature,
            'stats' => [
                'total_all' => $totalAll,
                'offices_with_customers' => $officesWithCustomers,
                'offices_with_vip_feature' => $officesWithVipFeature,
                'filtered_total' => $customers->total(),
            ],
            'filters' => [
                'office_id' => $officeId,
                'search' => $search,
            ],
        ]);
    }
}

