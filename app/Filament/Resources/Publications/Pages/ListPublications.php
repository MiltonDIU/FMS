<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListPublications extends ListRecords
{
    protected static string $resource = PublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action(function (): StreamedResponse {
                    $query = $this->getFilteredTableQuery();
                    $records = $query->with([
                        'teachers.user',
                        'incentive',
                        'type',
                        'faculty',
                        'department',
                        'linkage',
                        'quartile',
                        'grant',
                        'collaboration',
                    ])->get();

                    $spreadsheet = new Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle('Publications');

                    // All Publication Fields + Author Fields
                    $headers = [
                        'A' => 'ID',
                        'B' => 'Title',
                        'C' => 'Type',
                        'D' => 'Faculty',
                        'E' => 'Department',
                        'F' => 'Linkage',
                        'G' => 'Quartile',
                        'H' => 'Grant Type',
                        'I' => 'Collaboration',
                        'J' => 'Journal Name',
                        'K' => 'Journal Link',
                        'L' => 'Pub Date',
                        'M' => 'Pub Year',
                        'N' => 'Research Area',
                        'O' => 'H-Index',
                        'P' => 'Citescore',
                        'Q' => 'Impact Factor',
                        'R' => 'Student Involvement',
                        'S' => 'Keywords',
                        'T' => 'Abstract',
                        'U' => 'Status',
                        'V' => 'Featured',
                        'W' => 'Incentive Total',
                        'X' => 'Incentive Status',
                        'Y' => 'Author Name',
                        'Z' => 'Author Email',
                        'AA' => 'Employee ID',
                        'AB' => 'Role',
                        'AC' => 'Amount',
                    ];

                    foreach ($headers as $col => $header) {
                        $sheet->setCellValue($col . '1', $header);
                    }

                    // Style headers
                    $headerStyle = [
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ];
                    $sheet->getStyle('A1:AC1')->applyFromArray($headerStyle);

                    $row = 2;
                    foreach ($records as $publication) {
                        $authors = $publication->teachers->sortBy('pivot.sort_order')->values();
                        $authorCount = max($authors->count(), 1);
                        $startRow = $row;

                        // All publication data
                        $pubData = [
                            'A' => $publication->id,
                            'B' => $publication->title,
                            'C' => $publication->type?->name,
                            'D' => $publication->faculty?->name,
                            'E' => $publication->department?->name,
                            'F' => $publication->linkage?->name,
                            'G' => $publication->quartile?->name,
                            'H' => $publication->grant?->name,
                            'I' => $publication->collaboration?->name,
                            'J' => $publication->journal_name,
                            'K' => $publication->journal_link,
                            'L' => $publication->publication_date?->format('Y-m-d'),
                            'M' => $publication->publication_year,
                            'N' => $publication->research_area,
                            'O' => $publication->h_index,
                            'P' => $publication->citescore,
                            'Q' => $publication->impact_factor,
                            'R' => $publication->student_involvement ? 'Yes' : 'No',
                            'S' => $publication->keywords,
                            'T' => $publication->abstract,
                            'U' => $publication->status,
                            'V' => $publication->is_featured ? 'Yes' : 'No',
                            'W' => $publication->incentive?->total_amount,
                            'X' => $publication->incentive?->status,
                        ];

                        foreach ($pubData as $col => $value) {
                            $sheet->setCellValue($col . $startRow, $value);
                        }

                        // Write authors
                        if ($authors->isEmpty()) {
                            $row++;
                        } else {
                            foreach ($authors as $author) {
                                $roleLabel = match ($author->pivot->author_role) {
                                    'first' => '1st Author',
                                    'corresponding' => 'Corresponding',
                                    'co_author' => 'Co-Author',
                                    default => $author->pivot->author_role,
                                };

                                $sheet->setCellValue('Y' . $row, $author->full_name);
                                $sheet->setCellValue('Z' . $row, $author->user?->email ?? '');
                                $sheet->setCellValue('AA' . $row, $author->employee_id);
                                $sheet->setCellValue('AB' . $row, $roleLabel);
                                $sheet->setCellValue('AC' . $row, $author->pivot->incentive_amount ?? 0);
                                $row++;
                            }
                        }

                        // Merge publication cells if multiple authors
                        if ($authorCount > 1) {
                            $endRow = $startRow + $authorCount - 1;
                            foreach (array_keys($pubData) as $col) {
                                $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                                $sheet->getStyle("{$col}{$startRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                            }
                        }
                    }

                    // Apply borders to all data
                    $lastRow = $row - 1;
                    if ($lastRow >= 2) {
                        $sheet->getStyle("A2:AC{$lastRow}")->applyFromArray([
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        ]);
                    }

                    // Auto-size columns (except abstract which can be very long)
                    $columnsToAutoSize = range('A', 'S');
                    $columnsToAutoSize = array_merge($columnsToAutoSize, ['U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC']);
                    foreach ($columnsToAutoSize as $col) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }
                    // Set fixed width for Abstract column
                    $sheet->getColumnDimension('T')->setWidth(50);

                    // Freeze header row
                    $sheet->freezePane('A2');

                    $writer = new Xlsx($spreadsheet);

                    return response()->streamDownload(function () use ($writer) {
                        $writer->save('php://output');
                    }, 'publications_export_' . now()->format('Y-m-d_His') . '.xlsx', [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),
            CreateAction::make(),
        ];
    }
}





