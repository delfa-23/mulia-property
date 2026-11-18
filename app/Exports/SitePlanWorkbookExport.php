<?php

namespace App\Exports;

use App\Models\Lot;
use App\Models\Property;
use App\Models\SitePlanArea;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SitePlanWorkbookExport implements Export, WithMultipleSheets
{
    /**
     * @param  Collection<int, SitePlanArea>  $sitePlanAreas
     * @param  Collection<int, Lot>  $legacyLots
     */
    public function __construct(
        private Property $property,
        private Collection $sitePlanAreas,
        private Collection $legacyLots,
    ) {}

    public function sheets(): array
    {
        return [
            new SitePlanMapExport($this->property, 'marketing', $this->sitePlanAreas, $this->legacyLots),
            new SitePlanMapExport($this->property, 'construction', $this->sitePlanAreas, $this->legacyLots),
        ];
    }
}
