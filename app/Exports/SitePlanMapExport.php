<?php

namespace App\Exports;

use App\Models\Lot;
use App\Models\Property;
use App\Models\SitePlanArea;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SitePlanMapExport extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStyles, WithTitle
{
    private const GRID_COLUMN_COUNT = 50;

    private const GRID_ROW_COUNT = 30;

    private const GRID_START_COLUMN = 2;

    private const GRID_START_ROW = 7;

    private bool $prepared = false;

    /**
     * @var list<array{row: int, column: int, color: string, font_color: string}>
     */
    private array $placedCells = [];

    /**
     * @var list<array<int, string|null>>
     */
    private array $rows = [];

    /**
     * @param  Collection<int, SitePlanArea>  $sitePlanAreas
     * @param  Collection<int, Lot>  $legacyLots
     */
    public function __construct(
        private Property $property,
        private string $format,
        private Collection $sitePlanAreas,
        private Collection $legacyLots,
    ) {}

    public function array(): array
    {
        $this->prepare();

        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $this->prepare();

        $sheet->mergeCells('A1:AZ1');
        $sheet->mergeCells('A2:AZ2');
        $sheet->getStyle('A1:AZ1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 16,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '174A35'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
        ]);
        $sheet->getStyle('A2:AZ2')->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => ['rgb' => '5B6573'],
            ],
            'alignment' => [
                'horizontal' => 'center',
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(4)->setRowHeight(24);
        $sheet->getStyle('A4:AZ4')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A4:AZ4')->getAlignment()->setVertical('center');
        $sheet->getStyle('A4:AZ4')->getAlignment()->setWrapText(true);

        foreach ($this->legend() as $index => $item) {
            $startColumn = $index * 13 + 1;
            $endColumn = $startColumn + 12;
            $start = Coordinate::stringFromColumnIndex($startColumn);
            $end = Coordinate::stringFromColumnIndex($endColumn);
            $range = "{$start}4:{$end}4";
            $sheet->mergeCells($range);
            $sheet->setCellValue("{$start}4", $item['label']);
            $sheet->getStyle($range)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $item['color']],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => $item['font_color']],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => 'thin',
                        'color' => ['rgb' => $item['color'] === 'FFFFFF' ? '64748B' : 'FFFFFF'],
                    ],
                ],
            ]);
        }

        for ($column = 1; $column <= 52; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(4.5);
        }

        for ($row = self::GRID_START_ROW; $row < self::GRID_START_ROW + self::GRID_ROW_COUNT; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(33);
        }

        foreach ($this->placedCells as $cell) {
            $coordinate = Coordinate::stringFromColumnIndex($cell['column']).$cell['row'];
            $sheet->getStyle($coordinate)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 9,
                    'color' => ['rgb' => $cell['font_color']],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $cell['color']],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                    'vertical' => 'center',
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => 'thin',
                        'color' => ['rgb' => '64748B'],
                    ],
                ],
            ]);
        }

        return [];
    }

    public function title(): string
    {
        return $this->format === 'construction' ? 'Pembangunan' : 'Marketing';
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function prepare(): void
    {
        if ($this->prepared) {
            return;
        }

        $this->prepared = true;
        $this->rows = array_fill(0, self::GRID_START_ROW + self::GRID_ROW_COUNT - 1, array_fill(0, 52, null));
        $this->rows[0][0] = mb_strtoupper($this->property->name).' | SITE PLAN DIGITAL';
        $this->rows[1][0] = $this->format === 'construction'
            ? 'Pembangunan - nama kavling dan progres (%)'
            : 'Marketing - nama kavling dan status ketersediaan berdasarkan warna';
        $occupied = [];
        $mappedLotIds = [];

        foreach ($this->sitePlanAreas as $area) {
            $lot = $area->lot;
            if (! $lot instanceof Lot) {
                continue;
            }

            $mappedLotIds[] = (int) $lot->id;
            $position = $this->polygonCenter($area);
            if ($position !== null) {
                $this->placeLot($lot, $position['x'], $position['y'], $occupied);
            }
        }

        foreach ($this->legacyLots as $lot) {
            if (in_array((int) $lot->id, $mappedLotIds, true) || ! is_array($lot->map_coordinates)) {
                continue;
            }

            $coordinates = $lot->map_coordinates;
            if (! is_numeric($coordinates['x'] ?? null)
                || ! is_numeric($coordinates['y'] ?? null)
                || ! is_numeric($coordinates['width'] ?? null)
                || ! is_numeric($coordinates['height'] ?? null)) {
                continue;
            }

            $this->placeLot(
                $lot,
                (float) $coordinates['x'] + (float) $coordinates['width'] / 2,
                (float) $coordinates['y'] + (float) $coordinates['height'] / 2,
                $occupied,
            );
        }
    }

    /**
     * @return array{x: float, y: float}|null
     */
    private function polygonCenter(SitePlanArea $area): ?array
    {
        $points = array_values(array_filter(
            $area->polygon,
            fn (mixed $point): bool => is_array($point)
                && is_numeric($point['x'] ?? null)
                && is_numeric($point['y'] ?? null),
        ));

        if ($points === []) {
            return null;
        }

        return [
            'x' => array_sum(array_column($points, 'x')) / count($points),
            'y' => array_sum(array_column($points, 'y')) / count($points),
        ];
    }

    /**
     * @param  array<string, true>  $occupied
     */
    private function placeLot(Lot $lot, float $x, float $y, array &$occupied): void
    {
        $column = self::GRID_START_COLUMN + (int) round(min(100, max(0, $x)) / 100 * (self::GRID_COLUMN_COUNT - 1));
        $row = self::GRID_START_ROW + (int) round(min(100, max(0, $y)) / 100 * (self::GRID_ROW_COUNT - 1));
        [$row, $column] = $this->nearestAvailableCell($row, $column, $occupied);
        $occupied["{$row}:{$column}"] = true;

        $progress = $this->format === 'construction' ? $lot->constructionProgressPercentage() : 0;
        $color = $this->format === 'construction'
            ? $this->progressBand($progress)
            : $this->marketingColor($lot->status);
        $fontColor = in_array($color, ['FFD966', 'FFFFFF'], true) ? '1F2937' : 'FFFFFF';
        $label = $lot->block->name.'/'.$lot->lot_number;

        if ($this->format === 'construction') {
            $label .= PHP_EOL.rtrim(rtrim(number_format($progress, 1, ',', '.'), '0'), ',').'%';
        }

        $this->rows[$row - 1][$column - 1] = $label;
        $this->placedCells[] = [
            'row' => $row,
            'column' => $column,
            'color' => $color,
            'font_color' => $fontColor,
        ];
    }

    /**
     * @param  array<string, true>  $occupied
     * @return array{0: int, 1: int}
     */
    private function nearestAvailableCell(int $row, int $column, array $occupied): array
    {
        if (! isset($occupied["{$row}:{$column}"])) {
            return [$row, $column];
        }

        for ($distance = 1; $distance <= self::GRID_ROW_COUNT + self::GRID_COLUMN_COUNT; $distance++) {
            for ($rowOffset = -$distance; $rowOffset <= $distance; $rowOffset++) {
                $columnOffset = $distance - abs($rowOffset);

                foreach (array_unique([$column - $columnOffset, $column + $columnOffset]) as $candidateColumn) {
                    $candidateRow = $row + $rowOffset;

                    if ($candidateRow >= self::GRID_START_ROW
                        && $candidateRow < self::GRID_START_ROW + self::GRID_ROW_COUNT
                        && $candidateColumn >= self::GRID_START_COLUMN
                        && $candidateColumn < self::GRID_START_COLUMN + self::GRID_COLUMN_COUNT
                        && ! isset($occupied["{$candidateRow}:{$candidateColumn}"])) {
                        return [$candidateRow, $candidateColumn];
                    }
                }
            }
        }

        return [$row, $column];
    }

    /**
     * @return list<array{label: string, color: string, font_color: string}>
     */
    private function legend(): array
    {
        if ($this->format === 'construction') {
            return [
                ['label' => '0-24% | MERAH', 'color' => 'F8696B', 'font_color' => 'FFFFFF'],
                ['label' => '25-54% | KUNING', 'color' => 'FFD966', 'font_color' => '1F2937'],
                ['label' => '55-79% | BIRU', 'color' => '5B9BD5', 'font_color' => 'FFFFFF'],
                ['label' => '80-100% | HIJAU', 'color' => '70AD47', 'font_color' => 'FFFFFF'],
            ];
        }

        return [
            ['label' => 'TERSEDIA', 'color' => 'FFFFFF', 'font_color' => '1F2937'],
            ['label' => 'BOOKING', 'color' => '22C55E', 'font_color' => 'FFFFFF'],
            ['label' => 'TERJUAL', 'color' => 'EF4444', 'font_color' => 'FFFFFF'],
            ['label' => 'TIDAK TERSEDIA', 'color' => '94A3B8', 'font_color' => 'FFFFFF'],
        ];
    }

    private function marketingColor(string $status): string
    {
        return match ($status) {
            'available' => 'FFFFFF',
            'booked', 'process', 'akad' => '22C55E',
            'finish' => 'EF4444',
            default => '94A3B8',
        };
    }

    private function progressBand(float $progress): string
    {
        return match (true) {
            $progress <= 24 => 'F8696B',
            $progress <= 54 => 'FFD966',
            $progress <= 79 => '5B9BD5',
            default => '70AD47',
        };
    }
}
