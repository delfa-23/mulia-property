<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Sales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManagement($request);

        $sales = Sales::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('marketing.sales.index', compact('sales'));
    }

    public function create(Request $request): View
    {
        $this->authorizeManagement($request);

        return view('marketing.sales.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        Sales::create($this->validated($request));

        return redirect()->route('marketing.sales.index')->with('success', 'Sales berhasil ditambahkan.');
    }

    public function show(Request $request, Sales $sale): View
    {
        $this->authorizeManagement($request);
        $sale->loadCount('bookings');

        return view('marketing.sales.show', compact('sale'));
    }

    public function edit(Request $request, Sales $sale): View
    {
        $this->authorizeManagement($request);

        return view('marketing.sales.edit', compact('sale'));
    }

    public function update(Request $request, Sales $sale): RedirectResponse
    {
        $this->authorizeManagement($request);
        $sale->update($this->validated($request));

        return redirect()->route('marketing.sales.index')->with('success', 'Data Sales berhasil diperbarui.');
    }

    public function destroy(Request $request, Sales $sale): RedirectResponse
    {
        $this->authorizeManagement($request);

        if ($sale->bookings()->exists()) {
            return back()->with('error', 'Sales tidak dapat dihapus karena sudah digunakan pada booking. Nonaktifkan datanya.');
        }

        $sale->delete();

        return redirect()->route('marketing.sales.index')->with('success', 'Sales berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin() || $request->user()?->role === 'tl_marketing',
            403
        );
    }
}
