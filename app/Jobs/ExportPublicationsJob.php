<?php

namespace App\Jobs;

use App\Models\Publication;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExportPublicationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes

    protected User $user;
    protected array $filterData;
    protected ?string $searchQuery;
    protected string $exportMode;

    /**
     * Create a new job instance.
     * @param string $exportMode 'publication' or 'author'
     */
    public function __construct(User $user, array $filterData = [], ?string $searchQuery = null, string $exportMode = 'publication')
    {
        $this->user = $user;
        $this->filterData = $filterData;
        $this->searchQuery = $searchQuery;
        $this->exportMode = $exportMode;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            ini_set('memory_limit', '1024M');

            if ($this->exportMode === 'author') {
                $this->exportByAuthor();
            } else {
                $this->exportByPublication();
            }

        } catch (\Exception $e) {
            Log::error('Export failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            Notification::make()
                ->title('Export Failed')
                ->body('There was an error generating your export. Please try again.')
                ->danger()
                ->sendToDatabase($this->user);
        }
    }

    protected function exportByPublication(): void
    {
        $fileName = 'publications_export_' . now()->format('Y-m-d_His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        Storage::disk('public')->makeDirectory('exports');
        $absPath = Storage::disk('public')->path($filePath);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Publications');

        // Headers
        $headers = [
            'A' => 'ID', 'B' => 'Title', 'C' => 'Type', 'D' => 'Faculty', 'E' => 'Department',
            'F' => 'Linkage', 'G' => 'Quartile', 'H' => 'Grant Type', 'I' => 'Collaboration',
            'J' => 'Journal Name', 'K' => 'Journal Link', 'L' => 'Pub Date', 'M' => 'Pub Year',
            'N' => 'Research Area', 'O' => 'H-Index', 'P' => 'Citescore', 'Q' => 'Impact Factor',
            'R' => 'Student Involvement', 'S' => 'Keywords', 'T' => 'Abstract', 'U' => 'Status',
            'V' => 'Featured', 'W' => 'Sort Order', 'X' => 'Incentive Total', 'Y' => 'Incentive Status',
            'Z' => 'Author Name', 'AA' => 'Author Email', 'AB' => 'Employee ID', 'AC' => 'Role', 'AD' => 'Amount',
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
        $sheet->getStyle('A1:AD1')->applyFromArray($headerStyle);

        // Query
        $query = Publication::query()->with([
            'teachers.user', 'incentive', 'type', 'faculty', 'department',
            'linkage', 'quartile', 'grant', 'collaboration',
        ]);
        $this->applyFilters($query);

        $row = 2;
        $globalIndex = 1;
        $grandTotal = 0;

        $query->chunkById(500, function ($publications) use ($sheet, &$row, &$globalIndex, &$grandTotal) {
            foreach ($publications as $publication) {
                $grandTotal += (float) ($publication->incentive?->total_amount ?? 0);
                $authors = $publication->teachers->sortBy('pivot.sort_order')->values();
                    $authorCount = max($authors->count(), 1);
                    $startRow = $row;

                    $pubData = [
                        'A' => $globalIndex++,
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
                    'W' => $publication->sort_order,
                    'X' => $publication->incentive?->total_amount,
                    'Y' => $publication->incentive?->status,
                ];

                foreach ($pubData as $col => $value) {
                    $sheet->setCellValue($col . $startRow, $value);
                }

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

                        $sheet->setCellValue('Z' . $row, $author->full_name);
                        $sheet->setCellValue('AA' . $row, $author->user?->email ?? '');
                        $sheet->setCellValue('AB' . $row, $author->employee_id);
                        $sheet->setCellValue('AC' . $row, $roleLabel);
                        $sheet->setCellValue('AD' . $row, $author->pivot->incentive_amount ?? 0);
                        $row++;
                    }
                }

                if ($authorCount > 1) {
                    $endRow = $startRow + $authorCount - 1;
                    foreach (array_keys($pubData) as $col) {
                        $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                    }
                }
            }
        });

        // Add Grand Total Row
        $sheet->setCellValue('W' . $row, 'Grand Total:');
        $sheet->setCellValue('X' . $row, $grandTotal);
        $sheet->getStyle('W' . $row . ':X' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
        ]);
        $sheet->getStyle('X' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        // Fixed Widths

        $this->setFixedWidths($sheet);

        $this->finalizeExport($spreadsheet, $absPath, $filePath, $row - 1);
    }

    protected function exportByAuthor(): void
    {
        $fileName = 'authors_export_' . now()->format('Y-m-d_His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        Storage::disk('public')->makeDirectory('exports');
        $absPath = Storage::disk('public')->path($filePath);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Authors');

        // Headers for Author Mode
        $headers = [
            'A' => 'Author Name',
            'B' => 'Employee ID',
            'C' => 'Department',
            'D' => 'Total Incentive Received',
            'E' => 'Publication Title',
            'F' => 'Publication Date',
            'G' => 'Role',
            'H' => 'Incentive Amount',
            'I' => 'Publication Total',
            'J' => 'Share Percentage (%)',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . '1', $header);
        }

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ED7D31']], // Orange for Author Mode
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // Query: Get filtered publications first
        $pubQuery = Publication::query()->with(['teachers', 'teachers.department']);
        $this->applyFilters($pubQuery);
        
        // We need to group by authors.
        // 1. Get all filtered publications
        // 2. Extract unique teachers from them
        // 3. Group publications by teacher
        
        // Note: For very large datasets, this in-memory collection might be heavy.
        // A more optimized SQL approach would be better, but Eloquent relations are complex here.
        // We'll trust chunking on publications and manual grouping for now.
        
        // Optimization: Process publications and build an Author -> Publications map
        $authorMap = [];
        
        $pubQuery->chunk(500, function ($publications) use (&$authorMap) {
            foreach ($publications as $pub) {
                foreach ($pub->teachers as $teacher) {
                    if (!isset($authorMap[$teacher->id])) {
                        $authorMap[$teacher->id] = [
                            'details' => $teacher,
                            'total_incentive' => 0,
                            'publications' => [],
                        ];
                    }
                    
                    $incentive = (float) ($teacher->pivot->incentive_amount ?? 0);
                    $authorMap[$teacher->id]['total_incentive'] += $incentive;
                    
                    $pubTotal = (float) ($pub->incentive?->total_amount ?? 0);
                    $sharePercent = $pubTotal > 0 ? ($incentive / $pubTotal) * 100 : 0;

                    $authorMap[$teacher->id]['publications'][] = [
                        'title' => $pub->title,
                        'date' => $pub->publication_date?->format('Y-m-d'),
                        'role' => $teacher->pivot->author_role,
                        'amount' => $incentive,
                        'pub_total' => $pubTotal,
                        'share_percent' => $sharePercent,
                    ];
                }
            }
        });

        $row = 2;
        $grandTotal = 0;
        foreach ($authorMap as $authorId => $data) {
            $teacher = $data['details'];
            $grandTotal += $data['total_incentive'];
            $publications = $data['publications'];
            $pubCount = count($publications);
            $startRow = $row;
            
            // Common Author Data (A-D)
            $sheet->setCellValue('A' . $startRow, $teacher->full_name);
            $sheet->setCellValue('B' . $startRow, $teacher->employee_id);
            $sheet->setCellValue('C' . $startRow, $teacher->department?->name);
            $sheet->setCellValue('D' . $startRow, $data['total_incentive']); // Total Sum

            foreach ($publications as $pub) {
                $sheet->setCellValue('E' . $row, $pub['title']);
                $sheet->setCellValue('F' . $row, $pub['date']);
                
                $roleLabel = match ($pub['role']) {
                    'first' => '1st Author',
                    'corresponding' => 'Corresponding',
                    'co_author' => 'Co-Author',
                    default => $pub['role'],
                };
                $sheet->setCellValue('G' . $row, $roleLabel);
                
                $sheet->setCellValue('H' . $row, $pub['amount']);
                $sheet->setCellValue('I' . $row, $pub['pub_total']);
                $sheet->setCellValue('J' . $row, number_format($pub['share_percent'], 2) . '%');
                
                $row++;
            }

            // Merge Author Cells
            if ($pubCount > 1) {
                $endRow = $startRow + $pubCount - 1;
                foreach (range('A', 'D') as $col) {
                    $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                    $sheet->getStyle("{$col}{$startRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("{$col}{$startRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            } else {
                 // Even for single row, center alignment looks better
                 foreach (range('A', 'D') as $col) {
                    $sheet->getStyle("{$col}{$startRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("{$col}{$startRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                 }
            }
        }

        // Add Grand Total Row
        $sheet->setCellValue('C' . $row, 'Grand Total:');
        $sheet->setCellValue('D' . $row, $grandTotal);
        $sheet->getStyle('C' . $row . ':D' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
        ]);
        $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        // Auto-Size Columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('E')->setAutoSize(false); // Title
        $sheet->getColumnDimension('E')->setWidth(50);

        $this->finalizeExport($spreadsheet, $absPath, $filePath, $row - 1);
    }

    protected function setFixedWidths($sheet): void
    {
        $columnWidths = [
            'A' => 10, 'B' => 50, 'C' => 15, 'D' => 20, 'E' => 20, 'F' => 15,
            'G' => 10, 'H' => 15, 'I' => 15, 'J' => 30, 'K' => 20, 'L' => 12,
            'M' => 10, 'N' => 20, 'O' => 10, 'P' => 10, 'Q' => 10, 'R' => 10,
            'S' => 20, 'T' => 50, 'U' => 15, 'V' => 10, 'W' => 10, 'X' => 15,
            'Y' => 15, 'Z' => 25, 'AA' => 30, 'AB' => 15, 'AC' => 15, 'AD' => 15,
        ];

        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    protected function finalizeExport($spreadsheet, $absPath, $filePath, $lastRow): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        // Global styling for data rows
        if ($lastRow >= 2) {
             // For Publication mode it's A-AD, for Author it's A-J. 
             // Simplest is to check export mode or just style used range.
             $maxCol = $this->exportMode === 'author' ? 'J' : 'AD';
             
             // Borders
             $sheet->getStyle("A2:{$maxCol}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
             ]);
             
             if ($this->exportMode !== 'author') {
                 // Centering for publication mode (already handled in exportByPublication but good to ensure)
                  $sheet->getStyle("A2:Y{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                  $sheet->getStyle("A2:Y{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
             }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($absPath);

        Notification::make()
            ->title('Export Completed')
            ->body("Your {$this->exportMode} export is ready.")
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->url(Storage::disk('public')->url($filePath))
                    ->button()
                    ->openUrlInNewTab(),
            ])
            ->sendToDatabase($this->user);
    }

    protected function applyFilters(Builder $query): void
    {
        // 1. Search
        if ($this->searchQuery) {
            $search = $this->searchQuery;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('journal_name', 'like', "%{$search}%")
                  ->orWhereHas('teachers', function ($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // 2. Publication Date Range
        if (!empty($this->filterData['publication_date_range'])) {
            $dateData = $this->filterData['publication_date_range'];
            if (!empty($dateData['from'])) {
                $query->whereDate('publication_date', '>=', $dateData['from']);
            }
            if (!empty($dateData['until'])) {
                $query->whereDate('publication_date', '<=', $dateData['until']);
            }
        }

        // 3. Status
        if (!empty($this->filterData['status'])) {
             $statuses = $this->filterData['status'];
             $values = is_array($statuses) ? ($statuses['values'] ?? $statuses) : $statuses;
             if (!empty($values)) {
                 $query->whereIn('status', $values);
             }
        }

        // 4. Incentive Status
        if (!empty($this->filterData['incentive_status'])) {
            $incentiveData = $this->filterData['incentive_status'];
            $values = is_array($incentiveData) ? ($incentiveData['values'] ?? $incentiveData) : $incentiveData;

            if (!empty($values)) {
                $query->where(function (Builder $query) use ($values) {
                    if (in_array('none', $values)) {
                        $query->orWhereDoesntHave('incentive');
                    }

                    $statusValues = array_filter($values, fn($v) => $v !== 'none');
                    if (!empty($statusValues)) {
                        $query->orWhereHas('incentive', function (Builder $q) use ($statusValues) {
                            $q->whereIn('status', $statusValues);
                        });
                    }
                });
            }
        }

        // 5. Trashed
        if (!empty($this->filterData['trashed'])) {
            $trashed = $this->filterData['trashed'];
            if ($trashed === 'with_trashed') {
                 $query->withTrashed();
            } elseif ($trashed === 'only_trashed') {
                 $query->onlyTrashed();
            }
        }
    }
}

