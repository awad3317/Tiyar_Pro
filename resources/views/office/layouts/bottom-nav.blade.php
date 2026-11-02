<footer class="fixed bottom-0 left-0 right-0 max-w-md mx-auto bg-white/95 backdrop-blur-md border-t border-slate-200 px-6 py-2 flex justify-around items-center z-30 shadow-[0_-4px_10px_rgba(0,0,0,0.03)]">
    
    <!-- زر الرئيسية -->
    @php
        $isDashboard = request()->routeIs('office.dashboard');
    @endphp
    <a href="{{ route('office.dashboard') }}" class="flex flex-col items-center font-bold text-[10px] transition-colors {{ $isDashboard ? 'text-primary' : 'text-slate-400 hover:text-primary' }}">
        <span class="material-symbols-outlined text-2xl {{ $isDashboard ? 'text-secondary' : '' }}">home</span>
        <span class="mt-0.5">الرئيسية</span>
    </a>

    <!-- زر الطرود -->
    @php
        $isParcels = request()->routeIs('office.parcels.*');
    @endphp
    <a href="{{ route('office.parcels.index') }}" class="flex flex-col items-center font-bold text-[10px] transition-colors {{ $isParcels ? 'text-primary' : 'text-slate-400 hover:text-primary' }}">
        <span class="material-symbols-outlined text-2xl {{ $isParcels ? 'text-secondary' : '' }}">inventory_2</span>
        <span class="mt-0.5">الطرود</span>
    </a>

    @if(auth('office')->check() && auth('office')->user()->hasFeature('vip_customers_tab'))
        <!-- زر العملاء المميزين -->
        @php
            $isFavoriteCustomers = request()->routeIs('office.favorite_customers.*');
        @endphp
        <a href="{{ route('office.favorite_customers.index') }}" class="flex flex-col items-center font-bold text-[10px] transition-colors {{ $isFavoriteCustomers ? 'text-primary' : 'text-slate-400 hover:text-primary' }}">
            <span class="material-symbols-outlined text-2xl {{ $isFavoriteCustomers ? 'text-secondary' : '' }}">stars</span>
            <span class="mt-0.5">العملاء المميزين</span>
        </a>
    @endif
    
</footer>
