<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SitePlanWorkbookExport;
use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\Property;
use App\Models\SitePlanArea;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SitePlanExportController extends Controller
{
    public function download(Property $property): BinaryFileResponse
    {
        abort_unless(filled($property->site_plan_path), 404);

        $sitePlanAreas = $property->sitePlanAreas()
            ->where('site_plan_path', $property->site_plan_path)
            ->whereNotNull('lot_id')
            ->with(['lot.block', 'lot.constructionProgresses.stage'])
            ->orderBy('id')
            ->get()
            ->filter(fn (SitePlanArea $area): bool => $area->lot !== null
                && (int) $area->lot->block->property_id === (int) $property->id)
            ->values();

        $mappedLotIds = $sitePlanAreas->pluck('lot_id');
        $legacyLots = Lot::query()
            ->with(['block', 'constructionProgresses.stage'])
            ->whereHas('block', fn ($query) => $query->where('property_id', $property->id))
            ->whereNotNull('map_coordinates')
            ->whereNotIn('id', $mappedLotIds)
            ->orderBy('block_id')
            ->orderBy('lot_number')
            ->get();

        $export = new SitePlanWorkbookExport($property, $sitePlanAreas, $legacyLots);
        $filename = str($property->name)->slug()
            ->prepend('export-siteplan-')
            ->append('-', now()->format('Ymd-His'), '.xlsx');

        return Excel::download($export, $filename->toString());
    }
}
