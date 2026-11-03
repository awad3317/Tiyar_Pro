<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeatureController extends Controller
{
    /**
     * عرض قائمة المميزات العامة وكتالوج النظام
     */
    public function index(): View
    {
        $features = Feature::query()
            ->withCount(['officeFeatures as enabled_offices_count' => function ($query) {
                $query->where('is_enabled', true);
            }])
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        $totalOfficesCount = Office::count();

        $categoryStats = [
            'total'        => $features->count(),
            'ui_tab'       => $features->where('category', 'ui_tab')->count(),
            'notification' => $features->where('category', 'notification')->count(),
            'general'      => $features->where('category', 'general')->count(),
        ];

        return view('admin.features.index', [
            'features'          => $features,
            'totalOfficesCount' => $totalOfficesCount,
            'categoryStats'     => $categoryStats,
        ]);
    }

    /**
     * إضافة ميزة جديدة ومزامنتها تلقائياً مع جميع المكاتب (بحالة غير مفعلة)
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->has('code')) {
            $code = strtolower(trim((string) $request->input('code')));
            $code = str_replace([' ', '-'], '_', $code);
            $request->merge([
                'code' => $code,
            ]);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'min:3', 'max:100'],
            'code'        => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:features,code'],
            'category'    => ['required', 'in:ui_tab,notification,general'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required'     => 'اسم الميزة مطلوب.',
            'name.string'       => 'اسم الميزة يجب أن يكون نصاً صالحاً.',
            'name.min'          => 'اسم الميزة يجب ألا يقل عن 3 أحرف.',
            'name.max'          => 'اسم الميزة يجب ألا يتجاوز 100 حرف.',
            'code.required'     => 'الكود البرمجي للميزة مطلوب.',
            'code.max'          => 'الكود البرمجي يجب ألا يتجاوز 50 حرفاً.',
            'code.regex'        => 'الكود البرمجي يجب أن يكون بالإنجليزية ويبدأ بحرف صغير وبدون مسافات، ويحتوي فقط على حروف صغيرة وأرقام وشرطة سفلية (مثل: new_feature_code).',
            'code.unique'       => 'هذا الكود البرمجي مستخدم بالفعل لميزة أخرى، يرجى اختيار كود فريد.',
            'category.required' => 'يرجى اختيار تصنيف الميزة.',
            'category.in'       => 'التصنيف المختار غير صالح.',
            'description.max'   => 'وصف الميزة يجب ألا يتجاوز 500 حرف.',
        ]);

        DB::transaction(function () use ($validated) {
            $feature = Feature::create($validated);

            $officeIds = Office::query()->pluck('id');
            if ($officeIds->isNotEmpty()) {
                $now = now();
                $records = $officeIds->map(fn ($officeId) => [
                    'office_id'   => $officeId,
                    'feature_id'  => $feature->id,
                    'is_enabled'  => false,
                    'config'      => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ])->all();

                DB::table('office_features')->insertOrIgnore($records);
            }

            Office::clearAllOfficesFeaturesCache();
        });

        return redirect()->route('admin.features.index')
            ->with('success', 'تمت إضافة الميزة ومزامنتها مع جميع المكاتب (بحالة غير مفعلة) بنجاح.');
    }

    /**
     * تعديل بيانات الميزة (الاسم، التصنيف، الوصف) ومسح الكاش
     */
    public function update(Request $request, Feature $feature): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'min:3', 'max:100'],
            'category'    => ['required', 'in:ui_tab,notification,general'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required'     => 'اسم الميزة مطلوب.',
            'name.string'       => 'اسم الميزة يجب أن يكون نصاً صالحاً.',
            'name.min'          => 'اسم الميزة يجب ألا يقل عن 3 أحرف.',
            'name.max'          => 'اسم الميزة يجب ألا يتجاوز 100 حرف.',
            'category.required' => 'يرجى اختيار تصنيف الميزة.',
            'category.in'       => 'التصنيف المختار غير صالح.',
            'description.max'   => 'وصف الميزة يجب ألا يتجاوز 500 حرف.',
        ]);

        $feature->update($validated);

        Office::clearAllOfficesFeaturesCache();

        return redirect()->route('admin.features.index')
            ->with('success', 'تم تحديث بيانات الميزة بنجاح.');
    }

    /**
     * مزامنة شاملة لجميع المميزات مع جميع المكاتب (ميزة أمان إضافية)
     */
    public function syncAll(): RedirectResponse
    {
        $features = Feature::all();
        $officeIds = Office::query()->pluck('id');

        if ($features->isNotEmpty() && $officeIds->isNotEmpty()) {
            $now = now();
            $records = [];

            foreach ($features as $feature) {
                foreach ($officeIds as $officeId) {
                    $records[] = [
                        'office_id'  => $officeId,
                        'feature_id' => $feature->id,
                        'is_enabled' => false,
                        'config'     => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            collect($records)->chunk(200)->each(function ($chunk) {
                DB::table('office_features')->insertOrIgnore($chunk->all());
            });
        }

        Office::clearAllOfficesFeaturesCache();

        return redirect()->route('admin.features.index')
            ->with('success', 'تمت مزامنة جميع المميزات مع كافة المكاتب بنجاح وتحديث الكاش.');
    }
}

