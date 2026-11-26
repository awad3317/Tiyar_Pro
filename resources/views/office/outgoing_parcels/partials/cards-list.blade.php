<div class="space-y-2.5 lg:space-y-0 lg:grid lg:grid-cols-2 2xl:grid-cols-3 lg:gap-4">
    @forelse ($parcels as $parcel)
        @include('office.outgoing_parcels.partials.parcel-card', ['parcel' => $parcel])
    @empty
        <div class="lg:col-span-full bg-white rounded-2xl border border-slate-200/80 p-8 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-1 text-slate-300">outbox</span>
            <p class="text-xs font-bold text-slate-500">لا توجد طرود صادرة مطابقة</p>
            <p class="text-[11px] text-slate-400 mt-0.5">اضغط على (+ إضافة طرد صادر) لتسجيل إرسالية جديدة</p>
        </div>
    @endforelse
</div>

@if ($parcels->hasPages())
    <div class="pt-2">
        {{ $parcels->links() }}
    </div>
@endif
