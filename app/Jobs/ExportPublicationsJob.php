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

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, array $filterData = [], ?string $searchQuery = null)
    {
        $this->user = $user;
        $this->filterData = $filterData;
        $this->searchQuery = $searchQuery;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Increase memory limit for large excel generation
            ini_set('memory_limit', '1024M');

            $fileName = 'publications_export_' . now()->format('Y-m-d_His') . '.xlsx';
            $filePath = 'exports/' . $fileName;

            // Ensure directory exists
            Storage::disk('public')->makeDirectory('exports');
            $absPath = Storage::disk('public')->path($filePath);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Publications');

            // Headers
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
                'W' => 'Sort Order',
                'X' => 'Incentive Total',
                'Y' => 'Incentive Status',
                'Z' => 'Author Name',
                'AA' => 'Author Email',
                'AB' => 'Employee ID',
                'AC' => 'Role',
                'AD' => 'Amount',
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

            // Reconstruct Query
            $query = Publication::query()
                ->with([
                    'teachers.user',
                    'incentive',
                    'type',
                    'faculty',
                    'department',
                    'linkage',
                    'quartile',
                    'grant',
                    'collaboration',
                ]);

            $this->applyFilters($query);

            $row = 2;

            // Process in Chunks
            $query->chunkById(500, function ($publications) use ($sheet, &$row) {
                foreach ($publications as $key=>$publication) {
                    $authors = $publication->teachers->sortBy('pivot.sort_order')->values();
                    $authorCount = max($authors->count(), 1);
                    $startRow = $row;

                    // Prepare common publication data (Mapped to Columns A-Y)
                    $pubData = [
                        'A' => $key+1,//$publication->id,
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

                    // Write Publication Data
                    foreach ($pubData as $col => $value) {
                        $sheet->setCellValue($col . $startRow, $value);
                    }

                    // Write Authors (Columns Z-AD)
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

                    // Merge Publication Cells if multiple authors
                    if ($authorCount > 1) {
                        $endRow = $startRow + $authorCount - 1;
                        foreach (array_keys($pubData) as $col) {
                            $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                        }
                    }
                }
            });

            // OPTIMIZATION: Removed AutoSize (extremely slow). Using fixed widths.
            $columnWidths = [
                'A' => 10,  // ID
                'B' => 50,  // Title
                'C' => 15,  // Type
                'D' => 20,  // Faculty
                'E' => 20,  // Department
                'F' => 15,  // Linkage
                'G' => 10,  // Quartile
                'H' => 15,  // Grant
                'I' => 15,  // Collaboration
                'J' => 30,  // Journal
                'K' => 20,  // Journal Link
                'L' => 12,  // Date
                'M' => 10,  // Year
                'N' => 20,  // Area
                'O' => 10,  // H-Index
                'P' => 10,  // Citescore
                'Q' => 10,  // Impact
                'R' => 10,  // Student
                'S' => 20,  // Keywords
                'T' => 50,  // Abstract
                'U' => 15,  // Status
                'V' => 10,  // Featured
                'W' => 10,  // Sort
                'X' => 15,  // Incentive
                'Y' => 15,  // Inc. Status
                'Z' => 25,  // Author
                'AA' => 30, // Email
                'AB' => 15, // ID
                'AC' => 15, // Role
                'AD' => 15, // Amount
            ];

            foreach ($columnWidths as $col => $width) {
                $sheet->getColumnDimension($col)->setWidth($width);
            }

            // Bulk Style Application (Centering)
            // Apply Vertical Center to ALL publication columns (A-Y) for the entire data range
            $lastRow = $row - 1;
            if ($lastRow >= 2) {
                $styleArray = [
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_CENTER, // Center text key logic
                    ],
                ];
                 // Apply to Columns A-Y (Publication Data)
                $sheet->getStyle("A2:Y{$lastRow}")->applyFromArray($styleArray);
            }

            // Save File
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false); // Optimization

            $writer->save($absPath);

            // Notify User
            Notification::make()
                ->title('Export Completed')
                ->body('Your publication Excel export is ready.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Download')
                        ->url(Storage::disk('public')->url($filePath))
                        ->button()
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($this->user);

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
