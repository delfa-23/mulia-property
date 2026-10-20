<x-layouts::app :title="__('Siteplan Perumahan')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#10233D]">Siteplan Perumahan</h1>
            <p class="mt-1 text-sm text-zinc-500">Pilih perumahan untuk melihat denah dan status kavling.</p>
        </div>

        @if($errors->any())
            <p role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </p>
        @endif

        <form method="GET" action="{{ route($indexRoute) }}" data-site-plan-property-form class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="w-full max-w-xl space-y-1.5">
                <label for="site-plan-property" class="block text-sm font-medium text-zinc-700">Perumahan</label>
                <select id="site-plan-property" name="property_id" data-site-plan-property-select class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="">Pilih perumahan</option>
                    @foreach($properties as $availableProperty)
                        <option value="{{ $availableProperty->id }}" @selected($property?->id === $availableProperty->id)>
                            {{ $availableProperty->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-[#10233D] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1e3a5f]">
                Tampilkan denah
            </button>
        </form>

        @if($property)
            <x-site-plan-map
                :property="$property"
                :lots="$lots"
                :areas="$sitePlanAreas"
                :manage-url="$manageUrl"
                :dashboard-url="route($indexRoute)"
                detail-mode="modal"
                :management-role="$routePrefix"
            />
        @else
            <p class="rounded-lg border border-dashed border-zinc-300 bg-white px-4 py-10 text-center text-sm text-zinc-500">
                Pilih perumahan untuk membuka siteplan.
            </p>
        @endif
    </div>
</x-layouts::app>