<x-layouts::app :title="__('Tahapan Pembangunan')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Tahapan Pembangunan</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Atur tahapan dan rentang progress untuk setiap perumahan.
            </p>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            @forelse($properties as $property)
                <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <header class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $property->name }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $property->constructionStages->count() }} tahapan
                        </p>
                    </header>

                    <div class="space-y-3 p-5">
                        @forelse($property->constructionStages as $stage)
                            <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="min-w-0">
                                    <p class="font-medium text-zinc-900 dark:text-white">
                                        {{ $stage->order }}. {{ $stage->name }}
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $stage->min_progress }}%-{{ $stage->max_progress }}%
                                        @if($stage->description)
                                            · {{ $stage->description }}
                                        @endif
                                    </p>
                                </div>
                                <form
                                    method="POST"
                                    action="{{ route('pembangunan.properties.construction-stages.destroy', [$property, $stage]) }}"
                                    onsubmit="return confirm('Hapus tahapan ini beserta progress kavling terkait?')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="shrink-0 rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-900/20">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Belum ada tahapan untuk perumahan ini.</p>
                        @endforelse

                        <form
                            method="POST"
                            action="{{ route('pembangunan.properties.construction-stages.store', $property) }}"
                            class="grid gap-3 rounded-lg bg-zinc-50 p-4 sm:grid-cols-2 dark:bg-zinc-800"
                        >
                            @csrf
                            <h3 class="text-sm font-semibold text-zinc-900 sm:col-span-2 dark:text-white">
                                Tambah Tahapan
                            </h3>
                            <div>
                                <label for="stage-name-{{ $property->id }}" class="mb-1.5 block text-xs font-medium text-zinc-700 dark:text-zinc-300">Nama tahapan</label>
                                <input id="stage-name-{{ $property->id }}" name="name" required maxlength="255" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white">
                            </div>
                            <div>
                                <label for="stage-order-{{ $property->id }}" class="mb-1.5 block text-xs font-medium text-zinc-700 dark:text-zinc-300">Urutan</label>
                                <input id="stage-order-{{ $property->id }}" type="number" name="order" required min="1" value="{{ $property->constructionStages->max('order') + 1 }}" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white">
                            </div>
                            <div>
                                <label for="stage-min-{{ $property->id }}" class="mb-1.5 block text-xs font-medium text-zinc-700 dark:text-zinc-300">Progress minimum (%)</label>
                                <input id="stage-min-{{ $property->id }}" type="number" name="min_progress" required min="0" max="100" step="0.01" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white">
                            </div>
                            <div>
                                <label for="stage-max-{{ $property->id }}" class="mb-1.5 block text-xs font-medium text-zinc-700 dark:text-zinc-300">Progress maksimum (%)</label>
                                <input id="stage-max-{{ $property->id }}" type="number" name="max_progress" required min="0" max="100" step="0.01" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="stage-description-{{ $property->id }}" class="mb-1.5 block text-xs font-medium text-zinc-700 dark:text-zinc-300">Deskripsi (opsional)</label>
                                <textarea id="stage-description-{{ $property->id }}" name="description" rows="2" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"></textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                    Tambah Tahapan
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Belum ada perumahan</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Tambahkan perumahan terlebih dahulu sebelum mengatur tahapannya.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts::app>
