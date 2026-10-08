<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlockController extends Controller
{
    public function index(
        Request $request,
        Property $property
    ): View {
        $routePrefix = $this->routePrefix();
        $blocks = $property->blocks()
            ->withCount('lots')
            ->when($request->filled('block_id'), function ($query) use ($request) {
                $query->whereKey($request->integer('block_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(
                    'name',
                    'like',
                    '%'.$request->search.'%'
                );
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $blockOptions = $property->blocks()->orderBy('name')->get(['id', 'name']);

        return view('admin.blocks.index', compact(
            'property',
            'blocks',
            'blockOptions',
            'routePrefix'
        ));
    }

    public function create(Property $property): View
    {
        return view('admin.blocks.create', [
            'property' => $property,
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function store(
        Request $request,
        Property $property
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:blocks,name,NULL,id,property_id,'.$property->id,
            ],
            'description' => ['nullable', 'string'],
        ]);

        $property->blocks()->create($validated);

        return redirect()
            ->route($this->routePrefix().'.blocks.index', $property)
            ->with('success', 'Block berhasil dibuat.');
    }

    public function edit(
        Property $property,
        Block $block
    ): View {
        abort_unless(
            $block->property_id === $property->id,
            404
        );

        return view('admin.blocks.edit', [
            'property' => $property,
            'block' => $block,
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function update(
        Request $request,
        Property $property,
        Block $block
    ): RedirectResponse {
        abort_unless(
            $block->property_id === $property->id,
            404
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:blocks,name,'.$block->id.',id,property_id,'.$property->id,
            ],
            'description' => ['nullable', 'string'],
        ]);

        $block->update($validated);

        return redirect()
            ->route($this->routePrefix().'.blocks.index', $property)
            ->with('success', 'Block berhasil diperbarui.');
    }

    public function destroy(
        Property $property,
        Block $block
    ): RedirectResponse {
        abort_unless(
            $block->property_id === $property->id,
            404
        );

        if ($block->lots()->exists()) {
            return redirect()
                ->route($this->routePrefix().'.blocks.index', $property)
                ->with('error', 'Block tidak dapat dihapus karena masih memiliki kavling.');
        }

        $block->delete();

        return redirect()
            ->route($this->routePrefix().'.blocks.index', $property)
            ->with('success', 'Block berhasil dihapus.');
    }

    private function routePrefix(): string
    {
        return request()->routeIs('pembangunan.properties.*')
            ? 'pembangunan.properties'
            : 'admin.properties';
    }
}
