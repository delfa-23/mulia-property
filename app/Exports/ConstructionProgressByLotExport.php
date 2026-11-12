<?php

namespace App\Exports;

use App\Models\Lot;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConstructionProgressByLotExport extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private Collection $lots) {}

    public function collection(): Collection
    {
        return $this->lots->map(function (Lot $lot): array {
            $progress = $lot->constructionProgressPercentage();
            $latestProgress = $lot->constructionProgresses
                ->whereNotNull('stage_id')
                ->sortByDesc('updated_at')
                ->first();
            $band = $this->progressBand($progress);

            return [
                $lot->block->property->name,
                $lot->block->name,
                $lot->lot_number,
                $lot->bookings->first()?->customer?->name ?? '-',
                $progress,
                $band['label'],
                $latestProgress?->updated_at?->format('d/m/Y') ?? '',
                $latestProgress?->notes ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Perumahan',
            'Blok',
            'No. Kavling',
            'Konsumen',
            'Progres (%)',
            'Status Otomatis',
            'Tanggal Update',
            'Catatan Lapangan',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '174A35'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getAutoFilter()->setRange('A1:H'.$sheet->getHighestDataRow());
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('0.00"%"');
        $sheet->getStyle('H:H')->getAlignment()->setWrapText(true);

        foreach (['A' => 28, 'B' => 12, 'C' => 14, 'D' => 28, 'E' => 14, 'F' => 22, 'G' => 16, 'H' => 40] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $progress = $sheet->getCell("E{$row}")->getValue();

            if (! is_numeric($progress)) {
                continue;
            }

            $sheet->getStyle("E{$row}:F{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->progressBand((float) $progress)['color']],
                ],
            ]);
        }

        return [];
    }

    public function title(): string
    {
        return 'Progress Kavling';
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * @return array{label: string, color: string}
     */
    private function progressBand(float $progress): array
    {
        return match (true) {
            $progress <= 0 => ['label' => 'Belum dibangun', 'color' => 'F8696B'],
            $progress <= 24 => ['label' => 'Progres 1-24%', 'color' => 'F8696B'],
            $progress <= 54 => ['label' => 'Progres 25-54%', 'color' => 'FFD966'],
            $progress <= 79 => ['label' => 'Progres 55-79%', 'color' => '5B9BD5'],
            default => ['label' => 'Progres 80-100%', 'color' => '70AD47'],
        };
    }
}
