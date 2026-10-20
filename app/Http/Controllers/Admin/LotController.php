<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class LotController extends Controller
{
    public function index(
        Request $request,
        Property $property,
        Block $block
    ): View {
        $routePrefix = 'admin.properties';
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
            'routePrefix' => 'admin.properties',
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
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $images = array_values(array_filter(
            $validated['images'] ?? [],
            fn (mixed $image): bool => $image instanceof UploadedFile,
        ));
        unset($validated['images']);
        $imagePaths = array_map(
            fn (UploadedFile $image): string => $this->storeLotImage($image),
            $images,
        );

        $block->lots()->create([
            ...$validated,
            'image_path' => $imagePaths[0] ?? null,
            'image_paths' => array_slice($imagePaths, 1),
        ]);

        return redirect()
            ->route('admin.properties.blocks.lots.index', [$property, $block])
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

        $isConstructionRoute = request()->routeIs('pembangunan.*');

        return view('admin.lots.edit', [
            'property' => $property,
            'block' => $block,
            'lot' => $lot,
            'lotNumberSuffix' => $this->lotNumberSuffix(
                $block,
                old('lot_number', $lot->lot_number)
            ),
            'routePrefix' => 'admin.properties',
            'backUrl' => $isConstructionRoute
                ? route('pembangunan.dashboard', ['property_id' => $property->id])
                : route('admin.properties.blocks.lots.index', [$property, $block]),
            'updateUrl' => $isConstructionRoute
                ? route('pembangunan.properties.blocks.lots.update', [$property, $block, $lot])
                : route('admin.properties.blocks.lots.update', [$property, $block, $lot]),
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
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string', 'distinct', Rule::in($lot->imagePaths())],
        ]);

        $images = array_values(array_filter(
            $validated['images'] ?? [],
            fn (mixed $image): bool => $image instanceof UploadedFile,
        ));
        $removedImagePaths = $validated['remove_images'] ?? [];
        unset($validated['images'], $validated['remove_images']);

        $newImagePaths = array_map(
            fn (UploadedFile $image): string => $this->storeLotImage($image),
            $images,
        );
        $imagePaths = array_values(array_diff($lot->imagePaths(), $removedImagePaths));
        $imagePaths = [...$imagePaths, ...$newImagePaths];

        $validated['image_path'] = $imagePaths[0] ?? null;
        $validated['image_paths'] = array_slice($imagePaths, 1);
        $lot->update($validated);

        foreach ($removedImagePaths as $imagePath) {
            Storage::disk('site-plans')->delete($imagePath);
            Storage::disk('public')->delete($imagePath);
        }

        $redirect = request()->routeIs('pembangunan.*')
            ? redirect()->route('pembangunan.dashboard', ['property_id' => $property->id])
            : redirect()->route('admin.properties.blocks.lots.index', [$property, $block]);

        return $redirect->with('success', 'Kavling berhasil diperbarui.');
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
                ->route('admin.properties.blocks.lots.index', [$property, $block])
                ->with('error', 'Kavling tidak dapat dihapus karena masih memiliki data terkait.');
        }

        $imagePaths = $lot->imagePaths();
        $lot->delete();

        foreach ($imagePaths as $imagePath) {
            Storage::disk('site-plans')->delete($imagePath);
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()
            ->route('admin.properties.blocks.lots.index', [$property, $block])
            ->with('success', 'Kavling berhasil dihapus.');
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

    private function storeLotImage(UploadedFile $image): string
    {
        $path = $image->store('lots', 'site-plans');

        if (! is_string($path)) {
            throw new RuntimeException('Foto kavling gagal disimpan.');
        }

        return $path;
    }
}
