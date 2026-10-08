<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConstructionStage;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $routePrefix = $this->routePrefix();
        $properties = Property::query()
            ->withCount([
                'blocks',
                'blocks as lots_count' => function ($query) {
                    $query->join('lots', 'blocks.id', '=', 'lots.block_id');
                },
            ])
            ->when($request->filled('property_id'), function ($query) use ($request) {
                $query->whereKey($request->integer('property_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->search.'%');
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $propertyOptions = Property::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.properties.index', compact(
            'properties',
            'propertyOptions',
            'routePrefix'
        ));
    }

    public function constructionStages(): View
    {
        $properties = Property::query()
            ->with(['constructionStages' => fn ($query) => $query->orderBy('order')])
            ->orderBy('name')
            ->get();

        return view('pembangunan.construction-stages.index', compact('properties'));
    }

    public function create(): View
    {
        return view('admin.properties.create', [
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        Property::create($validated);

        return redirect()
            ->route($this->routePrefix().'.index')
            ->with('success', 'Property berhasil dibuat.');
    }

    public function edit(Property $property): View
    {
        return view('admin.properties.edit', [
            'property' => $property,
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function update(
        Request $request,
        Property $property
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $property->update($validated);

        return redirect()
            ->route($this->routePrefix().'.index')
            ->with('success', 'Property berhasil diperbarui.');
    }

    public function storeConstructionStage(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('construction_stages', 'name')->where('property_id', $property->id),
            ],
            'order' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('construction_stages', 'order')->where('property_id', $property->id),
            ],
            'min_progress' => ['required', 'numeric', 'min:0', 'max:100', 'lte:max_progress'],
            'max_progress' => ['required', 'numeric', 'min:0', 'max:100', 'gte:min_progress'],
            'description' => ['nullable', 'string'],
        ]);

        $overlappingStageExists = $property->constructionStages()
            ->where('min_progress', '<=', $validated['max_progress'])
            ->where('max_progress', '>=', $validated['min_progress'])
            ->exists();

        if ($overlappingStageExists) {
            return back()
                ->withErrors([
                    'min_progress' => 'Range persentase bertabrakan dengan tahapan pembangunan lain di perumahan ini.',
                ])
                ->withInput();
        }

        $property->constructionStages()->create($validated);

        return back()->with('success', 'Tahapan pembangunan berhasil ditambahkan ke '.$property->name.'.');
    }

    public function destroyConstructionStage(
        Property $property,
        ConstructionStage $constructionStage,
    ): RedirectResponse {
        abort_unless($constructionStage->property_id === $property->id, 404);

        DB::transaction(function () use ($constructionStage): void {
            $constructionStage->progresses()->delete();
            $constructionStage->delete();
        });

        return back()->with('success', 'Tahapan pembangunan dan progress kavling terkait berhasil dihapus.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        if (
            $property->blocks()->exists() ||
            $property->commonFacilities()->exists() ||
            $property->pemberkasanDocuments()->exists() ||
            $property->incomeTransactions()->exists() ||
            $property->expenseTransactions()->exists() ||
            $property->budgets()->exists()
            || $property->constructionStages()->exists()
        ) {
            return redirect()
                ->route($this->routePrefix().'.index')
                ->with('error', 'Property tidak dapat dihapus karena masih memiliki data terkait.');
        }

        $property->delete();

        return redirect()
            ->route($this->routePrefix().'.index')
            ->with('success', 'Property berhasil dihapus.');
    }

    private function routePrefix(): string
    {
        return request()->routeIs('pembangunan.properties.*')
            ? 'pembangunan.properties'
            : 'admin.properties';
    }
}
