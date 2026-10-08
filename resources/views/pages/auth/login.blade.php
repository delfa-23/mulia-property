<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-7">
        <div class="space-y-2">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#EB5120]">Portal internal</p>
            <h2 class="text-3xl font-black tracking-tight text-[#10233D]">Masuk ke akun</h2>
            <p class="text-sm leading-relaxed text-slate-500">Gunakan akun kerja Mulia Property untuk melanjutkan.</p>
        </div>

        <x-auth-session-status class="text-sm" :status="session('status')" />

        @if ($teamInvitation)
            <x-team-invitation-alert :invitation="$teamInvitation" :action="__('Log in')" />
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                label="Email kerja"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="nama@mulia-property.com"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    label="Kata sandi"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Masukkan kata sandi"
                    viewable
                />
            </div>

            <div class="flex justify-end -mt-2">
                <flux:modal.trigger name="password-help">
                    <button type="button" class="text-sm font-semibold text-[#EB5120] transition hover:text-[#D44215] hover:underline">Lupa password?</button>
                </flux:modal.trigger>
            </div>

            <flux:button variant="primary" type="submit" class="mt-1 w-full bg-[#EB5120]! text-white! hover:bg-[#D44215]!" data-test="login-button">
                Masuk
            </flux:button>
        </form>

        <flux:modal name="password-help" scroll="body" class="md:w-[28rem]">
            <div class="space-y-5">
                <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#EB5120]">Bantuan akun</p>
                    <h2 class="text-xl font-bold text-[#10233D]">Hubungi administrator</h2>
                    <p class="text-sm leading-relaxed text-slate-600">Untuk menjaga keamanan akun, permintaan reset kata sandi diproses oleh administrator Mulia Property.</p>
                </div>
                <div class="rounded-lg bg-orange-50 px-4 py-3 text-sm leading-relaxed text-slate-700">
                    Siapkan nama dan email kerja yang terdaftar agar administrator dapat membantu memulihkan akses.
                </div>
                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="primary" class="bg-[#EB5120]! text-white! hover:bg-[#D44215]!">Mengerti</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts::auth>
