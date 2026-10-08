<?php

namespace App\Exports;

use App\Models\Division;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProgressReportsByDivisionExport extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    public function collection(): Collection
    {
        $statusLabels = [
            'draft' => 'Draft',
            'submitted' => 'Menunggu Review',
            'revision' => 'Perlu Revisi',
            'approved' => 'Disetujui',
        ];
        $reviewLabels = [
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
        ];

        return Division::query()
            ->with(['progressReports' => fn ($query) => $query
                ->where('status', 'approved')
                ->with(['creator', 'reviews.reviewer'])
                ->orderByDesc('period_start')
                ->orderByDesc('created_at')])
            ->orderBy('name')
            ->get()
            ->flatMap(function (Division $division) use ($statusLabels, $reviewLabels): Collection {
                $reports = $division->progressReports;
                $progressValues = $reports->pluck('progress')->filter(fn ($progress) => $progress !== null);
                $averageProgress = $progressValues->isNotEmpty()
                    ? round((float) $progressValues->avg(), 2)
                    : null;
                $divisionData = [
                    $division->name,
                    $division->is_active ? 'Aktif' : 'Nonaktif',
                    $division->description ?: '-',
                    $reports->count(),
                    $averageProgress,
                ];

                if ($reports->isEmpty()) {
                    return collect([array_merge($divisionData, array_fill(0, 11, null))]);
                }

                return $reports->map(function ($report) use ($divisionData, $statusLabels, $reviewLabels): array {
                    $reviews = $report->reviews->map(function ($review) use ($reviewLabels): string {
                        $reviewLabel = $reviewLabels[$review->action] ?? ucfirst($review->action);

                        return $reviewLabel.' oleh '.($review->reviewer?->name ?? '-').': '.($review->notes ?: '-');
                    })->implode(PHP_EOL);

                    return array_merge($divisionData, [
                        $report->title,
                        $report->period_start->format('d/m/Y'),
                        $report->period_end->format('d/m/Y'),
                        $report->content ?: '(Belum ada rincian)',
                        $report->creator?->name ?? '-',
                        $report->progress !== null ? (float) $report->progress : null,
                        $statusLabels[$report->status] ?? ucfirst($report->status),
                        $report->created_at?->format('d/m/Y H:i') ?? '-',
                        $report->submitted_at?->format('d/m/Y H:i') ?? '-',
                        $report->approved_at?->format('d/m/Y H:i') ?? '-',
                        $reviews ?: 'Belum ada review',
                    ]);
                });
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'Divisi',
            'Status Divisi',
            'Keterangan Divisi',
            'Jumlah Laporan',
            'Rata-rata Progress (%)',
            'Judul Laporan',
            'Periode Mulai',
            'Periode Selesai',
            'Rincian Progress',
            'Pembuat',
            'Progress (%)',
            'Status Laporan',
            'Dibuat Pada',
            'Dikirim Pada',
            'Disetujui Pada',
            'Riwayat Review',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('C:C')->getAlignment()->setWrapText(true);
        $sheet->getStyle('I:I')->getAlignment()->setWrapText(true);
        $sheet->getStyle('P:P')->getAlignment()->setWrapText(true);

        return [];
    }

    public function title(): string
    {
        return 'Progress per Divisi';
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
