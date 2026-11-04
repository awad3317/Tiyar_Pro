<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountantController extends Controller
{
    /**
     * عرض قائمة المحاسبين مع الفلترة حسب المكتب والبحث بالاسم أو الهاتف
     */
    public function index(Request $request): View
    {
        $query = Accountant::query()->with('office:id,name,branch_name');

        // فلترة حسب المكتب
        $officeId = $request->input('office_id');
        if ($officeId) {
            $query->where('office_id', $officeId);
        }

        // البحث بالاسم أو رقم الجوال
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $cleanPhone = $this->cleanPhone($search);
            $query->where(function ($q) use ($search, $cleanPhone) {
                $q->where('name', 'like', "%{$search}%");
                if (!empty($cleanPhone)) {
                    $q->orWhere('phone', 'like', "%{$cleanPhone}%");
                }
            });
        }

        $accountants = $query->latest()->paginate(25)->withQueryString();

        $offices = Office::query()
            ->select(['id', 'name', 'branch_name'])
            ->withCount('accountants')
            ->orderBy('name')
            ->get();

        // استعلام تجميعي واحد سريع بدلاً من 4 استعلامات منفصلة
        $agg = Accountant::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
                COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as inactive,
                COUNT(DISTINCT office_id) as offices_with_accountant
            ')
            ->first();

        $stats = [
            'total'                   => (int) ($agg->total ?? 0),
            'active'                  => (int) ($agg->active ?? 0),
            'inactive'                => (int) ($agg->inactive ?? 0),
            'offices_with_accountant' => (int) ($agg->offices_with_accountant ?? 0),
        ];

        return view('admin.accountants.index', [
            'accountants' => $accountants,
            'offices'     => $offices,
            'stats'       => $stats,
            'filters'     => [
                'office_id' => $officeId,
                'search'    => $search,
            ],
        ]);
    }

    /**
     * إنشاء حساب محاسب جديد وربطه بمكتب
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'name'  => trim((string) $request->input('name', '')),
            'phone' => $this->cleanPhone((string) $request->input('phone', '')),
        ]);

        $validated = $request->validate([
            'office_id' => ['required', 'exists:offices,id'],
            'name'      => ['required', 'string', 'min:2', 'max:100'],
            'phone'     => ['required', 'regex:/^7[01378]\d{7}$/', 'unique:accountants,phone'],
            'password'  => ['required', 'string', 'min:4', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'office_id.required' => 'يرجى اختيار المكتب التابع له المحاسب.',
            'office_id.exists'   => 'المكتب المختار غير موجود.',
            'name.required'      => 'اسم المحاسب مطلوب.',
            'name.min'           => 'اسم المحاسب يجب ألا يقل عن حرفين.',
            'name.max'           => 'اسم المحاسب يجب ألا يتجاوز 100 حرف.',
            'phone.required'     => 'رقم جوال المحاسب مطلوب.',
            'phone.regex'        => 'رقم الجوال غير صالح. يجب أن يبدأ بـ 7 ويتكون من 9 أرقام (مثال: 77XXXXXXX).',
            'phone.unique'       => 'رقم الجوال هذا مسجل بالفعل لمحاسب آخر في النظام.',
            'password.required'  => 'الرمز السري / كلمة المرور مطلوبة.',
            'password.min'       => 'الرمز السري يجب ألا يقل عن 4 خانات.',
            'password.max'       => 'الرمز السري يجب ألا يتجاوز 50 خانة.',
        ]);

        Accountant::create([
            'office_id' => $validated['office_id'],
            'name'      => $validated['name'],
            'phone'     => $validated['phone'],
            'password'  => $validated['password'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return redirect()->route('admin.accountants.index', $request->only('office_id'))
            ->with('success', 'تم إنشاء حساب المحاسب وربطه بالمكتب بنجاح.');
    }

    /**
     * تعديل بيانات المحاسب أو تغيير الرمز السري
     */
    public function update(Request $request, Accountant $accountant): RedirectResponse
    {
        $request->merge([
            'name'  => trim((string) $request->input('name', '')),
            'phone' => $this->cleanPhone((string) $request->input('phone', '')),
        ]);

        $validated = $request->validate([
            'office_id' => ['required', 'exists:offices,id'],
            'name'      => ['required', 'string', 'min:2', 'max:100'],
            'phone'     => ['required', 'regex:/^7[01378]\d{7}$/', Rule::unique('accountants', 'phone')->ignore($accountant->id)],
            'password'  => ['nullable', 'string', 'min:4', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'office_id.required' => 'يرجى اختيار المكتب التابع له المحاسب.',
            'office_id.exists'   => 'المكتب المختار غير موجود.',
            'name.required'      => 'اسم المحاسب مطلوب.',
            'name.min'           => 'اسم المحاسب يجب ألا يقل عن حرفين.',
            'name.max'           => 'اسم المحاسب يجب ألا يتجاوز 100 حرف.',
            'phone.required'     => 'رقم جوال المحاسب مطلوب.',
            'phone.regex'        => 'رقم الجوال غير صالح. يجب أن يبدأ بـ 7 ويتكون من 9 أرقام (مثال: 77XXXXXXX).',
            'phone.unique'       => 'رقم الجوال هذا مسجل بالفعل لمحاسب آخر في النظام.',
            'password.min'       => 'الرمز السري الجديد يجب ألا يقل عن 4 خانات.',
            'password.max'       => 'الرمز السري الجديد يجب ألا يتجاوز 50 خانة.',
        ]);

        $data = [
            'office_id' => $validated['office_id'],
            'name'      => $validated['name'],
            'phone'     => $validated['phone'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $accountant->is_active,
        ];

        // تحديث كلمة المرور فقط إذا أدخل الأدمن قيمة غير فارغة
        if (!empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $accountant->update($data);

        return back()->with('success', 'تم تحديث بيانات المحاسب بنجاح.');
    }

    /**
     * تبديل حالة الحساب سريعاً بين نشط وموقوف
     */
    public function toggleStatus(Accountant $accountant): RedirectResponse
    {
        $accountant->update([
            'is_active' => !$accountant->is_active,
        ]);

        $statusLabel = $accountant->is_active ? 'تفعيل' : 'إيقاف';

        return back()->with('success', "تم {$statusLabel} حساب المحاسب ({$accountant->name}) بنجاح.");
    }

    /**
     * حذف حساب المحاسب نهائياً
     */
    public function destroy(Accountant $accountant): RedirectResponse
    {
        $name = $accountant->name;
        $accountant->delete();

        return back()->with('success', "تم حذف حساب المحاسب ({$name}) بنجاح.");
    }

    /**
     * تنظيف رقم الجوال من الرموز وتحويل الأرقام العربية/الفارسية إلى إنجليزية
     */
    private function cleanPhone(string $raw): string
    {
        $eastern = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $western = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
        $normalized = str_replace($eastern, $western, $raw);

        return preg_replace('/\D/', '', $normalized);
    }
}

