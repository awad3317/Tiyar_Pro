<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\WhatsappInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WhatsappInstanceController extends Controller
{
    /**
     * استعراض قائمة الـ WhatsApp Instances
     */
    public function index(Request $request): View
    {
        $query = WhatsappInstance::query();

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('instance_id', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $instances = $query->latest('id')->paginate(12)->withQueryString();

        // إرفاق المكاتب المرتبطة بكل instance
        $allOffices = Office::select('id', 'name', 'branch_name', 'whatsapp_instance_id')->get();

        $instances->getCollection()->transform(function ($instance) use ($allOffices) {
            $instance->linked_offices = $allOffices->filter(function ($office) use ($instance) {
                return $office->whatsapp_instance_id === $instance->instance_id;
            });
            return $instance;
        });

        return view('admin.whatsapp_instances.index', [
            'instances' => $instances,
            'offices'   => $allOffices,
            'filters'   => $request->only(['search', 'status']),
        ]);
    }

    /**
     * صفحة إنشاء instance جديدة
     */
    public function create(): View
    {
        $offices = Office::select('id', 'name', 'branch_name')->orderBy('name')->get();

        return view('admin.whatsapp_instances.create', [
            'offices' => $offices,
        ]);
    }

    /**
     * حفظ الـ instance الجديدة
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'instance_id'           => ['required', 'string', 'max:255', 'unique:whatsapp_instances,instance_id'],
            'pin_code'              => ['required', 'string', 'max:50'],
            'phone_number'          => ['nullable', 'string', 'max:30'],
            'is_active'             => ['nullable', 'boolean'],
            'assign_to_office_id'   => ['nullable', 'exists:offices,id'],
        ], [
            'name.required'         => 'اسم المعرف/صاحب الـ Instance مطلوب.',
            'instance_id.required'  => 'معرف الـ Instance (ID) في السيرفر مطلوب.',
            'instance_id.unique'    => 'معرف الـ Instance هذا مسجل مسبقاً.',
            'pin_code.required'     => 'الرمز السري المخصص للـ Instance مطلوب.',
        ]);

        $instance = WhatsappInstance::create([
            'name'         => $validated['name'],
            'instance_id'  => $validated['instance_id'],
            'pin_code'     => $validated['pin_code'],
            'phone_number' => $validated['phone_number'] ?? null,
            'is_active'    => $request->boolean('is_active', true),
        ]);

        // إذا طُلب ربطه بمكتب مباشرة
        if (!empty($validated['assign_to_office_id'])) {
            $office = Office::find($validated['assign_to_office_id']);
            if ($office) {
                $office->update([
                    'whatsapp_instance_id'   => $instance->instance_id,
                    'whatsapp_instance_name' => $instance->name,
                ]);
            }
        }

        return redirect()->route('admin.whatsapp_instances.index')
            ->with('success', "تم إنشاء الـ Instance \"{$instance->name}\" بنجاح.");
    }

    /**
     * صفحة تعديل الـ instance
     */
    public function edit(WhatsappInstance $whatsappInstance): View
    {
        $offices = Office::select('id', 'name', 'branch_name', 'whatsapp_instance_id')->orderBy('name')->get();
        $linkedOffice = $offices->firstWhere('whatsapp_instance_id', $whatsappInstance->instance_id);

        return view('admin.whatsapp_instances.edit', [
            'instance'     => $whatsappInstance,
            'offices'      => $offices,
            'linkedOffice' => $linkedOffice,
        ]);
    }

    /**
     * تحديث بيانات الـ instance
     */
    public function update(Request $request, WhatsappInstance $whatsappInstance): RedirectResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'instance_id'         => ['required', 'string', 'max:255', Rule::unique('whatsapp_instances', 'instance_id')->ignore($whatsappInstance->id)],
            'pin_code'            => ['required', 'string', 'max:50'],
            'phone_number'        => ['nullable', 'string', 'max:30'],
            'is_active'           => ['nullable', 'boolean'],
            'assign_to_office_id' => ['nullable', 'exists:offices,id'],
        ]);

        $oldInstanceId = $whatsappInstance->instance_id;

        $whatsappInstance->update([
            'name'         => $validated['name'],
            'instance_id'  => $validated['instance_id'],
            'pin_code'     => $validated['pin_code'],
            'phone_number' => $validated['phone_number'] ?? null,
            'is_active'    => $request->boolean('is_active'),
        ]);

        // تحديث المكاتب إذا تغير المعرف
        if ($oldInstanceId !== $validated['instance_id']) {
            Office::where('whatsapp_instance_id', $oldInstanceId)->update([
                'whatsapp_instance_id' => $validated['instance_id'],
            ]);
        }

        // إذا طُلب تعيين مكتب
        if ($request->has('assign_to_office_id')) {
            $officeId = $validated['assign_to_office_id'];
            if ($officeId) {
                Office::where('id', $officeId)->update([
                    'whatsapp_instance_id'   => $whatsappInstance->instance_id,
                    'whatsapp_instance_name' => $whatsappInstance->name,
                ]);
            }
        }

        return redirect()->route('admin.whatsapp_instances.index')
            ->with('success', "تم تحديث بيانات الـ Instance \"{$whatsappInstance->name}\" بنجاح.");
    }

    /**
     * تفعيل أو تعطيل الـ instance
     */
    public function toggleStatus(Request $request, WhatsappInstance $whatsappInstance): JsonResponse|RedirectResponse
    {
        $whatsappInstance->update([
            'is_active' => !$whatsappInstance->is_active,
        ]);

        $msg = $whatsappInstance->is_active ? 'تم تفعيل الـ Instance بنجاح.' : 'تم تعطيل الـ Instance.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'   => true,
                'is_active' => $whatsappInstance->is_active,
                'message'   => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * ربط الـ Instance بمكتب محدد
     */
    public function assignToOffice(Request $request, WhatsappInstance $whatsappInstance): JsonResponse|RedirectResponse
    {
        $request->validate([
            'office_id' => ['required', 'exists:offices,id'],
        ]);

        $office = Office::findOrFail($request->office_id);
        $office->update([
            'whatsapp_instance_id'   => $whatsappInstance->instance_id,
            'whatsapp_instance_name' => $whatsappInstance->name,
        ]);

        $msg = "تم ربط الـ Instance \"{$whatsappInstance->name}\" بالمكتب \"{$office->name}\" بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * حذف الـ instance
     */
    public function destroy(WhatsappInstance $whatsappInstance): RedirectResponse
    {
        $name = $whatsappInstance->name;
        $whatsappInstance->delete();

        return redirect()->route('admin.whatsapp_instances.index')
            ->with('success', "تم حذف الـ Instance \"{$name}\" بنجاح.");
    }
}
