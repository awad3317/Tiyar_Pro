<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\FavoriteCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteCustomerController extends Controller
{
    /**
     * جلب قائمة العملاء المميزين الخاصة بالمكتب الحالي فقط.
     */
    public function index(): JsonResponse
    {
        $office = auth('office')->user();

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

        $phone = preg_replace('/\D/', '', (string) $request->input('phone', ''));
        $request->merge([
            'phone' => $phone,
            'name'  => trim((string) $request->input('name', '')),
        ]);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'regex:/^7[01378]\d{7}$/'],
        ], [
            'name.required' => 'اسم العميل مطلوب لإضافته كعميل مميز.',
            'name.min'      => 'اسم العميل قصير جداً.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.regex'   => 'رقم الهاتف غير صالح.',
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
}

