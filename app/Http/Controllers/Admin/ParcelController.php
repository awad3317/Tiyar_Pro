<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParcelController extends Controller
{
    /**
     * مراقبة الطرود الشاملة لجميع المكاتب مع نظام فلترة متقدم
     */
    public function index(Request $request): View
    {
        $query = Parcel::query()->with('office');

        // فلترة حسب المكتب
        if ($officeId = $request->input('office_id')) {
            $query->where('office_id', $officeId);
        }

        // فلترة حسب الحالة
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // فلترة بالتواريخ المحددة
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // فلترة بالفترات السريعة (Presets)
        if ($preset = $request->input('date_preset')) {
            match ($preset) {
                'today'      => $query->whereDate('created_at', today()),
                'yesterday'  => $query->whereDate('created_at', today()->subDay()),
                'this_week'  => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'this_month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                default      => null,
            };
        }

        // البحث بالنص: رقم الجوال، اسم المستلم، أو رقم السند
        if ($search = trim($request->input('search', ''))) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $search);
            $query->where(function ($q) use ($search, $cleanPhone) {
                $q->where('recipient_name', 'like', "%{$search}%")
                  ->orWhere('receipt_number', 'like', "%{$search}%")
                  ->orWhere('package_type', 'like', "%{$search}%");

                if (!empty($cleanPhone)) {
                    $q->orWhere('recipient_phone', 'like', "%{$cleanPhone}%");
                }
            });
        }

        // إحصائيات سريعة للنتائج المطابقة
        $statsQuery = clone $query;
        $filteredStats = [
            'total'      => (clone $statsQuery)->count(),
            'in_office'  => (clone $statsQuery)->where('status', ParcelStatus::InOffice)->count(),
            'delivered'  => (clone $statsQuery)->where('status', ParcelStatus::Delivered)->count(),
            'returned'   => (clone $statsQuery)->where('status', ParcelStatus::Returned)->count(),
        ];

        $parcels = $query->latest('created_at')->paginate(20)->withQueryString();

        $offices = Office::select('id', 'name', 'branch_name')->orderBy('name')->get();

        return view('admin.parcels.index', [
            'parcels'       => $parcels,
            'offices'       => $offices,
            'filteredStats' => $filteredStats,
            'statuses'      => ParcelStatus::cases(),
            'filters'       => $request->only(['office_id', 'status', 'date_from', 'date_to', 'date_preset', 'search']),
        ]);
    }

    /**
     * تحديث حالة طرد
     */
    public function updateStatus(Request $request, Parcel $parcel): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ParcelStatus::class)],
        ]);

        $newStatus = ParcelStatus::from($validated['status']);

        $parcel->status = $newStatus;
        if ($newStatus === ParcelStatus::Delivered) {
            $parcel->delivered_at = $parcel->delivered_at ?: now();
        } else {
            $parcel->delivered_at = null;
        }
        $parcel->save();

        $msg = "تم تغيير حالة الطرد ({$parcel->receipt_number}) إلى \"{$newStatus->label()}\" بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'status'  => $newStatus->value,
                'label'   => $newStatus->label(),
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * حذف طرد نهائياً
     */
    public function destroy(Request $request, Parcel $parcel): JsonResponse|RedirectResponse
    {
        $receipt = $parcel->receipt_number ?: $parcel->id;
        $parcel->delete();

        $msg = "تم حذف الطرد رقم ({$receipt}) بنجاح.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }
}
