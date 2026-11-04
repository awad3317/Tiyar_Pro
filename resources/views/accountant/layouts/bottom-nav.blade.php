<footer class="fixed bottom-0 left-0 right-0 max-w-md mx-auto bg-white/95 backdrop-blur-md border-t border-slate-200 px-6 py-2 flex justify-around items-center z-30 shadow-[0_-4px_10px_rgba(0,0,0,0.03)]">

    <!-- زر الرئيسية -->
    @php
        $isDashboard = request()->routeIs('accountant.dashboard');
    @endphp
    <a href="{{ route('accountant.dashboard') }}"
       class="flex flex-col items-center font-bold text-[10px] transition-colors {{ $isDashboard ? 'text-primary' : 'text-slate-400 hover:text-primary' }}">
        <span class="material-symbols-outlined text-2xl {{ $isDashboard ? 'text-secondary' : '' }}">home</span>
        <span class="mt-0.5">الرئيسية</span>
    </a>

    {{-- سيتم إضافة تبويبات المحاسب الإضافية هنا في المهام القادمة --}}

</footer>

