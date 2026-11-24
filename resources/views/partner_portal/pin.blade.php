@extends('partner_portal.layout')

@section('title', 'التحقق من رمز الدخول')

@section('content')
    @php
        $isLocked = ($lockedSeconds ?? 0) > 0;
        $displayError = $pinError ?? $errors->first('pin');
    @endphp

    <div class="flex-1 flex items-center justify-center py-6"
         x-data="{
             pin: '',
             submitting: false,
             normalize(val) {
                 const ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
                 const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                 return String(val || '')
                     .replace(/[٠-٩]/g, d => ar.indexOf(d))
                     .replace(/[۰-۹]/g, d => fa.indexOf(d))
                     .replace(/\D/g, '')
                     .slice(0, 4);
             }
         }">
        <div class="w-full bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-4">
            <div class="text-center space-y-1.5">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-primary text-secondary flex items-center justify-center shadow-xs">
                    <span class="material-symbols-outlined text-3xl">lock</span>
                </div>
                <h2 class="text-base font-extrabold text-slate-900">أدخل رمز الدخول السري (PIN)</h2>
                <p class="text-xs text-slate-500">
                    هذه البوابة محمية برمز مكون من 4 أرقام مخصص لمكتب <strong class="text-slate-800">{{ $link->partner_name }}</strong>.
                </p>
            </div>

            @if ($displayError)
                <div class="bg-rose-50 border border-rose-200 rounded-2xl p-3 text-xs font-extrabold text-rose-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-base text-rose-600 shrink-0">error</span>
                    <span>{{ $displayError }}</span>
                </div>
            @endif

            <form method="POST"
                  action="{{ route('partner.portal.verify-pin', ['token' => $link->token]) }}"
                  @submit="submitting = true"
                  class="space-y-4">
                @csrf

                <div>
                    <input type="password"
                           name="pin"
                           x-model="pin"
                           @input="pin = normalize(pin)"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           dir="ltr"
                           maxlength="4"
                           required
                           {{ $isLocked ? 'disabled' : 'autofocus' }}
                           placeholder="• • • •"
                           class="w-full text-center text-2xl font-mono font-black tracking-[0.6em] py-3.5 px-4 rounded-2xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-secondary outline-none disabled:opacity-50">
                </div>

                <button type="submit"
                        :disabled="submitting || pin.length !== 4 || {{ $isLocked ? 'true' : 'false' }}"
                        class="w-full py-3 rounded-2xl bg-primary hover:bg-slate-800 text-secondary text-xs font-extrabold flex items-center justify-center gap-1.5 disabled:opacity-50 transition-colors">
                    <span class="material-symbols-outlined text-base">login</span>
                    <span x-text="submitting ? 'جاري التحقق...' : 'دخول إلى البوابة'"></span>
                </button>
            </form>
        </div>
    </div>
@endsection
