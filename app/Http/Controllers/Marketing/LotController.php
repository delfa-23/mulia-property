<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LotController extends Controller
{
    public function index(Request $request): View
    {
        $lots = Lot::query()
            ->with(['block.property', 'bookings' => fn ($query) => $query->whereIn('status', ['booking', 'process', 'akad', 'finish'])->with('customer')])
            ->when($request->filled('property_id'), fn ($query) => $query->whereHas('block', fn ($block) => $block->where('property_id', $request->integer('property_id'))))
            ->when($request->filled('lot_id'), fn ($query) => $query->whereKey($request->integer('lot_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('min_price'), fn ($query) => $query->where('house_price', '>=', $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($query) => $query->where('house_price', '<=', $request->input('max_price')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery->where('lot_number', 'like', $search)
                        ->orWhereHas('block', fn ($block) => $block->where('name', 'like', $search))
                        ->orWhereHas('block.property', fn ($property) => $property->where('name', 'like', $search));
                });
            })
            ->orderBy('block_id')
            ->orderBy('lot_number')
            ->paginate(20)
            ->withQueryString();

        return view('marketing.lots.index', [
            'lots' => $lots,
            'properties' => Property::query()->orderBy('name')->get(),
            'blocks' => Block::query()->when($request->filled('property_id'), fn ($query) => $query->where('property_id', $request->integer('property_id')))->orderBy('name')->get(),
            'lotOptions' => Lot::query()->with('block')->when($request->filled('property_id'), fn ($query) => $query->whereHas('block', fn ($block) => $block->where('property_id', $request->integer('property_id'))))->orderBy('block_id')->orderBy('lot_number')->get(),
        ]);
    }
}
