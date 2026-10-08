<x-layouts::app :title="'Edit User'">

    <div class="p-6">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                Edit User
            </h1>

            <p class="mt-1 text-sm text-gray-600">
                Perbarui data akun dan role user.
            </p>
        </div>

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <div class="font-semibold text-red-800">
                    Ada kesalahan:
                </div>

                <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <form
                method="POST"
                action="{{ route('admin.users.update', $user) }}"
                class="space-y-6"
            >
                @csrf
                @method('PUT')

                {{-- Nama --}}
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Nama
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $user->name) }}"
                        required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                {{-- Email --}}
                <div>
                    <label
                        for="email"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $user->email) }}"
                        required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                {{-- Role --}}
                <div>
                    <label
                        for="role"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Role
                    </label>

                    <select
                        id="role"
                        name="role"
                        required
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        @php
                            $roles = [
                                'admin' => 'Admin',
                                'tl_pembangunan' => 'TL Pembangunan',
                                'staff_pembangunan' => 'Staff Pembangunan',
                                'tl_marketing' => 'TL Marketing',
                                'staff_marketing' => 'Staff Marketing',
                                'tl_pemberkasan' => 'TL Pemberkasan',
                                'staff_pemberkasan' => 'Staff Pemberkasan',
                            ];
                        @endphp

                        @foreach ($roles as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(old('role', $user->role) === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Division Info --}}
                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm font-medium text-gray-700">
                        Divisi
                    </p>

                    <p
                        id="division-info"
                        class="mt-1 text-sm text-gray-600"
                    >
                        -
                    </p>

                    <p class="mt-2 text-xs text-gray-500">
                        Divisi ditentukan otomatis berdasarkan role.
                    </p>
                </div>

                {{-- Password --}}
                <div>
                    <label
                        for="password"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Password Baru
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >

                    <p class="mt-1 text-xs text-gray-500">
                        Kosongkan jika tidak ingin mengubah password.
                    </p>
                </div>

                {{-- Confirm Password --}}
                <div>
                    <label
                        for="password_confirmation"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Konfirmasi Password Baru
                    </label>

                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        minlength="8"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                {{-- Buttons --}}
                <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-5">

                    <a
                        href="{{ route('admin.users.index') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Simpan Perubahan
                    </button>

                </div>

            </form>

        </div>

    </div>

    <script>
        const roleSelect = document.getElementById('role');
        const divisionInfo = document.getElementById('division-info');

        function updateDivision() {
            const role = roleSelect.value;

            let division = 'Tidak memiliki divisi';

            if (
                role === 'tl_pembangunan' ||
                role === 'staff_pembangunan'
            ) {
                division = 'Pembangunan';
            }

            if (
                role === 'tl_marketing' ||
                role === 'staff_marketing'
            ) {
                division = 'Marketing';
            }

            if (
                role === 'tl_pemberkasan' ||
                role === 'staff_pemberkasan'
            ) {
                division = 'Pemberkasan';
            }

            divisionInfo.textContent = division;
        }

        roleSelect.addEventListener('change', updateDivision);

        updateDivision();
    </script>

</x-layouts::app>