<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\FavoriteCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FavoriteCustomerController extends Controller
{
    /**
     * عرض صفحة إدارة العملاء المميزين للفرع (PWA)
     */
    public function page(): View|RedirectResponse
    {
        $office = auth('office')->user();

        if (! $office || ! $office->hasFeature('vip_customers_tab')) {
            return redirect()->route('office.dashboard')->with('error', 'ميزة العملاء المميزين غير مفعلة لهذا الفرع.');
        }

        return view('office.favorite-customers.index', [
            'office' => $office,
        ]);
    }

    /**
     * التحقق من تفعيل ميزة العملاء المميزين للمكتب الحالي عبر الكاش
     */
    protected function checkFeatureAccess($office): ?JsonResponse
    {
        if (! $office || ! $office->hasFeature('vip_customers_tab')) {
            return response()->json([
                'success' => false,
                'message' => 'ميزة العملاء المميزين غير مفعلة لهذا المكتب.',
            ], 403);
        }

        return null;
    }

    /**
     * جلب قائمة العملاء المميزين الخاصة بالمكتب الحالي فقط.
     */
    public function index(): JsonResponse
    {
        $office = auth('office')->user();

        if ($response = $this->checkFeatureAccess($office)) {
            return $response;
        }

        $customers = FavoriteCustomer::query()
            ->where('office_id', $office->id)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'updated_at']);

        return response()->json([
            'success'   => true,
            'customers' => $customers,
        ]);
    }

    /**
     * إضافة عميل مميز (أو تحديث اسمه إن كان رقمه موجوداً مسبقاً لنفس المكتب).
     * الاسم والرقم إجباريان.
     */
    public function store(Request $request): JsonResponse
    {
        $office = auth('office')->user();

        if ($response = $this->checkFeatureAccess($office)) {
            return $response;
        }

        $phone = preg_replace('/\D/', '', (string) $request->input('phone', ''));
        $request->merge([
            'phone' => $phone,
            'name'  => trim((string) $request->input('name', '')),
        ]);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'regex:/^7[01378]\d{7}$/'],
        ], [
            'name.required'  => 'اسم العميل مطلوب لإضافته كعميل مميز.',
            'name.min'       => 'اسم العميل قصير جداً.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.regex'    => 'رقم الهاتف غير صالح.',
        ]);

        $customer = FavoriteCustomer::updateOrCreate(
            ['office_id' => $office->id, 'phone' => $validated['phone']],
            ['name' => $validated['name']]
        );

        return response()->json([
            'success'  => true,
            'message'  => 'تمت إضافة العميل إلى العملاء المميزين.',
            'customer' => $customer->only(['id', 'name', 'phone', 'updated_at']),
        ]);
    }

    /**
     * تحديث بيانات عميل مميز محدد تابع للمكتب الحالي.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $office = auth('office')->user();

        if ($response = $this->checkFeatureAccess($office)) {
            return $response;
        }

        $customer = FavoriteCustomer::query()
            ->where('office_id', $office->id)
            ->find($id);

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'العميل المميز غير موجود.',
            ], 404);
        }

        $phone = preg_replace('/\D/', '', (string) $request->input('phone', ''));
        $request->merge([
            'phone' => $phone,
            'name'  => trim((string) $request->input('name', '')),
        ]);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'min:2', 'max:100'],
            'phone' => [
                'required',
                'regex:/^7[01378]\d{7}$/',
                Rule::unique('favorite_customers', 'phone')->where('office_id', $office->id)->ignore($customer->id),
            ],
        ], [
            'name.required'  => 'اسم العميل مطلوب.',
            'name.min'       => 'اسم العميل قصير جداً.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.regex'    => 'رقم الهاتف غير صالح.',
            'phone.unique'   => 'رقم الهاتف هذا مسجل مسبقاً لعميل مميز آخر في مكتبكم.',
        ]);

        $customer->update([
            'name'  => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'تم تحديث بيانات العميل المميز بنجاح.',
            'customer' => $customer->only(['id', 'name', 'phone', 'updated_at']),
        ]);
    }

    /**
     * حذف عميل مميز محدد تابع للمكتب الحالي.
     */
    public function destroy(int $id): JsonResponse
    {
        $office = auth('office')->user();

        if ($response = $this->checkFeatureAccess($office)) {
            return $response;
        }

        $customer = FavoriteCustomer::query()
            ->where('office_id', $office->id)
            ->find($id);

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'العميل المميز غير موجود.',
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العميل من قائمة العملاء المميزين.',
        ]);
    }
}
