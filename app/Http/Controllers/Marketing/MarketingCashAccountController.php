<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingCashAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketingCashAccountController extends Controller
{
    public function update(Request $request, MarketingCashAccount $cashAccount): RedirectResponse
    {
        $validated = $request->validate([
            'balance' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        $cashAccount->update([
            'balance' => $validated['balance'],
            'updated_by' => $request->user()->id,
        ]);

        return to_route('marketing.dashboard')->with('status', 'Saldo berhasil diperbarui.');
    }
}
