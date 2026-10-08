<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->withCount('bookings')
            ->when($request->filled('customer_id'), function ($query) use ($request): void {
                $query->whereKey($request->integer('customer_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('nik', 'like', $search)->orWhere('phone', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $customerOptions = Customer::query()->orderBy('name')->get(['id', 'name']);

        return view('marketing.customers.index', compact('customers', 'customerOptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('marketing.customers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        Customer::create($this->validated($request));

        return redirect()->route('marketing.customers.index')->with('success', 'Customer berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): View
    {
        $customer->load(['bookings.lot.block.property', 'bookings.sales']);

        return view('marketing.customers.show', compact('customer'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer): View
    {
        return view('marketing.customers.edit', compact('customer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('marketing.customers.index')->with('success', 'Customer berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->bookings()->exists()) {
            return back()->with('error', 'Customer tidak dapat dihapus karena sudah memiliki booking.');
        }

        $customer->delete();

        return back()->with('success', 'Customer berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Customer $customer = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'nik' => ['nullable', 'digits:16', 'unique:customers,nik'.($customer ? ','.$customer->id : '')],
            'marital_status' => ['nullable', Rule::in(['Belum Menikah', 'Menikah'])],
            'occupation' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        return $validated;
    }
}
