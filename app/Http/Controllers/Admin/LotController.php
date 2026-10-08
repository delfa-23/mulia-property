<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LotController extends Controller
{
    public function index(
        Request $request,
        Property $property,
        Block $block
    ): View {
        $routePrefix = $this->routePrefix();
        abort_unless(
            $block->property_id === $property->id,
            404
        );

        $lots = $block->lots()
            ->when($request->filled('lot_number'), function ($query) use ($request) {
                $query->where('lot_number', $request->string('lot_number'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(
                    'lot_number',
                    'like',
                    '%'.$request->search.'%'
                );
            })
            ->orderBy('lot_number')
            ->paginate(15)
            ->withQueryString();

        $lotOptions = $block->lots()->orderBy('lot_number')->get(['lot_number']);

        return view('admin.lots.index', compact(
            'property',
            'block',
            'lots',
            'lotOptions',
            'routePrefix'
        ));
    }

    public function create(
        Property $property,
        Block $block
    ): View {
        abort_unless(
            $block->property_id === $property->id,
            404
        );

        return view('admin.lots.create', [
            'property' => $property,
            'block' => $block,
            'lotNumberSuffix' => $this->lotNumberSuffix($block, old('lot_number')),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function store(
        Request $request,
        Property $property,
        Block $block
    ): RedirectResponse {
        abort_unless(
            $block->property_id === $property->id,
            404
        );

        $this->normalizeLotNumberInput($request, $block);
        $this->normalizeHousePriceInput($request);

        $validated = $request->validate([
            'lot_number' => [
                'required',
                'string',
                'max:100',
                'regex:/^'.preg_quote($block->name, '/').'-\d+$/D',
                'unique:lots,lot_number,NULL,id,block_id,'.$block->id,
            ],
            'house_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'status' => [
                'required',
                'in:available,blocked,booked,process,akad,finish,cancelled',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $block->lots()->create($validated);

        return redirect()
            ->route($this->routePrefix().'.blocks.lots.index', [$property, $block])
            ->with('success', 'Kavling berhasil dibuat.');
    }

    public function edit(
        Property $property,
        Block $block,
        Lot $lot
    ): View {
        abort_unless(
            $block->property_id === $property->id &&
            $lot->block_id === $block->id,
            404
        );

        return view('admin.lots.edit', [
            'property' => $property,
            'block' => $block,
            'lot' => $lot,
            'lotNumberSuffix' => $this->lotNumberSuffix(
                $block,
                old('lot_number', $lot->lot_number)
            ),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function update(
        Request $request,
        Property $property,
        Block $block,
        Lot $lot
    ): RedirectResponse {
        abort_unless(
            $block->property_id === $property->id &&
            $lot->block_id === $block->id,
            404
        );

        $this->normalizeLotNumberInput($request, $block);
        $this->normalizeHousePriceInput($request);

        $validated = $request->validate([
            'lot_number' => [
                'required',
                'string',
                'max:100',
                'regex:/^'.preg_quote($block->name, '/').'-\d+$/D',
                'unique:lots,lot_number,'.$lot->id.',id,block_id,'.$block->id,
            ],
            'house_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'status' => [
                'required',
                'in:available,blocked,booked,process,akad,finish,cancelled',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $lot->update($validated);

        return redirect()
            ->route($this->routePrefix().'.blocks.lots.index', [$property, $block])
            ->with('success', 'Kavling berhasil diperbarui.');
    }

    public function destroy(
        Property $property,
        Block $block,
        Lot $lot
    ): RedirectResponse {
        abort_unless(
            $block->property_id === $property->id &&
            $lot->block_id === $block->id,
            404
        );

        if (
            $lot->bookings()->exists() ||
            $lot->constructionProgresses()->exists() ||
            $lot->pemberkasanDocuments()->exists() ||
            $lot->incomeTransactions()->exists() ||
            $lot->expenseTransactions()->exists() ||
            $lot->budgets()->exists() ||
            $lot->alerts()->exists()
        ) {
            return redirect()
                ->route($this->routePrefix().'.blocks.lots.index', [$property, $block])
                ->with('error', 'Kavling tidak dapat dihapus karena masih memiliki data terkait.');
        }

        $lot->delete();

        return redirect()
            ->route($this->routePrefix().'.blocks.lots.index', [$property, $block])
            ->with('success', 'Kavling berhasil dihapus.');
    }

    private function routePrefix(): string
    {
        return request()->routeIs('pembangunan.properties.*')
            ? 'pembangunan.properties'
            : 'admin.properties';
    }

    private function normalizeHousePriceInput(Request $request): void
    {
        $housePrice = $request->input('house_price');

        if (! is_string($housePrice)) {
            return;
        }

        $housePrice = preg_replace('/^Rp\s*/i', '', trim($housePrice));
        $housePrice = str_replace([' ', "\u{00A0}"], '', $housePrice ?? '');

        if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/D', $housePrice) === 1) {
            $housePrice = str_replace('.', '', $housePrice);
            $housePrice = str_replace(',', '.', $housePrice);
        } elseif (preg_match('/^\d+,\d+$/D', $housePrice) === 1) {
            $housePrice = str_replace(',', '.', $housePrice);
        }

        if (is_numeric($housePrice)) {
            $request->merge(['house_price' => $housePrice]);
        }
    }

    private function normalizeLotNumberInput(Request $request, Block $block): void
    {
        $lotNumber = $request->input('lot_number');

        if (! is_string($lotNumber)) {
            return;
        }

        $lotNumber = trim($lotNumber);
        $prefix = $block->name.'-';

        if (str_starts_with($lotNumber, $prefix)) {
            $lotNumber = substr($lotNumber, strlen($prefix));
        }

        $request->merge(['lot_number' => $prefix.$lotNumber]);
    }

    private function lotNumberSuffix(Block $block, mixed $lotNumber): string
    {
        if (! is_string($lotNumber)) {
            return '';
        }

        $prefix = $block->name.'-';

        if (str_starts_with($lotNumber, $prefix)) {
            return substr($lotNumber, strlen($prefix));
        }

        if (preg_match('/-(\d+)$/D', $lotNumber, $matches) === 1) {
            return $matches[1];
        }

        return $lotNumber;
    }
}
