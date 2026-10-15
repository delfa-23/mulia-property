<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingCashFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingCashFlowController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'title' => ['required', 'string', 'max:150'],
            'direction' => ['required', Rule::in(['income', 'outcome'])],
            'status' => ['required', Rule::in(['confirmed', 'potential', 'forecast'])],
            'due_date' => [
                'required',
                'date',
                'after_or_equal:'.today()->toDateString(),
                'before_or_equal:'.today()->addDays(30)->toDateString(),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
        ]);

        MarketingCashFlow::query()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return to_route('marketing.dashboard')->with('status', 'Rencana arus kas berhasil ditambahkan.');
    }

    public function destroy(MarketingCashFlow $cashFlow): RedirectResponse
    {
        $cashFlow->delete();

        return to_route('marketing.dashboard')->with('status', 'Rencana arus kas berhasil dihapus.');
    }
}
