<x-layouts::app :title="'User Management'">
    <div class="p-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">
                    User Management
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    Kelola akun dan role pengguna Mulia Property.
                </p>
            </div>

            <a
                href="{{ route('admin.users.create') }}"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
                + Tambah User
            </a>
        </div>

        @if (session('success'))
            <div class="mt-6 rounded-lg bg-green-50 p-4 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="mt-6 overflow-hidden rounded-xl border bg-white shadow-sm">

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-gray-50">
                        <tr>
                            <th class="px-5 py-4 font-semibold">Nama</th>
                            <th class="px-5 py-4 font-semibold">Email</th>
                            <th class="px-5 py-4 font-semibold">Role</th>
                            <th class="px-5 py-4 font-semibold">Divisi</th>
                            <th class="px-5 py-4 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-5 py-4 font-medium">
                                    {{ $user->name }}
                                </td>

                                <td class="px-5 py-4 text-gray-600">
                                    {{ $user->email }}
                                </td>

                                <td class="px-5 py-4">
                                    {{ ucwords(str_replace('_', ' ', $user->role)) }}
                                </td>

                                <td class="px-5 py-4">
                                    {{ $user->division?->name ?? 'Semua Divisi' }}
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">

                                        <a
                                            href="{{ route('admin.users.edit', $user) }}"
                                            class="rounded-lg border px-3 py-2 text-xs font-medium hover:bg-gray-50"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="{{ route('admin.users.destroy', $user) }}"
                                            onsubmit="return confirm('Hapus user ini?')"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-100"
                                            >
                                                Hapus
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="5"
                                    class="px-5 py-10 text-center text-gray-500"
                                >
                                    Belum ada user.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t p-4">
                {{ $users->links() }}
            </div>

        </div>

    </div>
</x-layouts::app>