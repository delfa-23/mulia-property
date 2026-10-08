<x-layouts::app :title="'Tambah User'">
    <div class="p-6">

        <div class="mb-6">
            <a
                href="{{ route('admin.users.index') }}"
                class="text-sm text-gray-500 hover:text-gray-700"
            >
                ← Kembali ke User Management
            </a>

            <h1 class="mt-3 text-2xl font-bold">
                Tambah User
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Buat akun baru untuk pengguna Mulia Property.
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">

            <form
                method="POST"
                action="{{ route('admin.users.store') }}"
                class="space-y-6"
            >
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Nama
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        class="w-full rounded-lg border-gray-300 px-4 py-2.5"
                        placeholder="Contoh: Budi Santoso"
                    >
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="w-full rounded-lg border-gray-300 px-4 py-2.5"
                        placeholder="contoh@mulia-property.test"
                    >
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Role
                    </label>

                    <select
                        name="role"
                        id="role"
                        required
                        class="w-full rounded-lg border-gray-300 px-4 py-2.5"
                    >
                        <option value="">Pilih Role</option>

                        <option value="admin" @selected(old('role') === 'admin')>
                            Admin
                        </option>

                        <option value="tl_pembangunan" @selected(old('role') === 'tl_pembangunan')>
                            TL Pembangunan
                        </option>

                        <option value="staff_pembangunan" @selected(old('role') === 'staff_pembangunan')>
                            Staff Pembangunan
                        </option>

                        <option value="tl_marketing" @selected(old('role') === 'tl_marketing')>
                            TL Marketing
                        </option>

                        <option value="staff_marketing" @selected(old('role') === 'staff_marketing')>
                            Staff Marketing
                        </option>

                        <option value="tl_pemberkasan" @selected(old('role') === 'tl_pemberkasan')>
                            TL Pemberkasan
                        </option>

                        <option value="staff_pemberkasan" @selected(old('role') === 'staff_pemberkasan')>
                            Staff Pemberkasan
                        </option>

                    </select>
                </div>

                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm font-medium">
                        Divisi
                    </p>

                    <p
                        id="division-info"
                        class="mt-1 text-sm text-gray-500"
                    >
                        Divisi akan ditentukan otomatis berdasarkan role.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">

                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            required
                            class="w-full rounded-lg border-gray-300 px-4 py-2.5"
                            placeholder="Minimal 8 karakter"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Konfirmasi Password
                        </label>

                        <input
                            type="password"
                            name="password_confirmation"
                            required
                            class="w-full rounded-lg border-gray-300 px-4 py-2.5"
                            placeholder="Ulangi password"
                        >
                    </div>

                </div>

                <div class="flex justify-end gap-3 border-t pt-6">

                    <a
                        href="{{ route('admin.users.index') }}"
                        class="rounded-lg border px-5 py-2.5 text-sm font-medium hover:bg-gray-50"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Buat User
                    </button>

                </div>

            </form>

        </div>

    </div>

    <script>
        const roleSelect = document.getElementById('role');
        const divisionInfo = document.getElementById('division-info');

        function updateDivisionInfo() {
            const divisions = {
                admin: 'Tidak memiliki divisi',
                tl_pembangunan: 'Pembangunan',
                staff_pembangunan: 'Pembangunan',
                tl_marketing: 'Marketing',
                staff_marketing: 'Marketing',
                tl_pemberkasan: 'Pemberkasan',
                staff_pemberkasan: 'Pemberkasan',
            };

            divisionInfo.textContent =
                divisions[roleSelect.value]
                ?? 'Divisi akan ditentukan otomatis berdasarkan role.';
        }

        roleSelect.addEventListener('change', updateDivisionInfo);

        updateDivisionInfo();
    </script>
</x-layouts::app>