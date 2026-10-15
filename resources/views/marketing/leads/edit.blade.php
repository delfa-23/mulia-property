<x-layouts::app :title="__('Edit Prospek')">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('marketing.leads.show', $lead) }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke detail prospek</a>
            <h1 class="mt-3 text-2xl font-semibold">Edit Prospek</h1>
            @if($lead->bookings->isNotEmpty())
                <p class="mt-1 text-sm text-zinc-500">Lead ini sudah terhubung ke booking dan tetap dihitung sebagai pencapaian akad berdasarkan status booking.</p>
            @endif
        </div>
        <form method="POST" action="{{ route('marketing.leads.update', $lead) }}" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            @method('PUT')
            @include('marketing.leads._form')
            <button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Simpan Perubahan</button>
        </form>
    </div>
</x-layouts::app>
