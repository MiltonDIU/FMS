<?php

namespace App\Jobs;

use App\Models\Author;
use App\Models\PublicationIncentive;
use App\Models\Teacher;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The two exports on the Publication Incentives page.
 *
 * Same shape as ExportPublicationsJob — one sheet per incentive with the
 * authors merged beneath it, and one grouped by author — but the publication
 * only contributes its title here. This page is read by whoever is paying, and
 * the journal, quartile and abstract are not their business.
 *
 * One difference from the publications export, and it matters: external authors
 * are included. Of the 23,488,522.95 recorded, 9,285,030.47 — two fifths, over
 * 1,074 rows — sits against people who are not our teachers. An "author"
 * breakdown that listed only teachers would show per-author amounts that do not
 * add up to the total it is printed beside.
 */
class ExportIncentivesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected User $user;

    protected array $filterData;

    protected ?string $searchQuery;

    /** 'incentive' or 'author'. */
    protected string $exportMode;

    public function __construct(User $user, array $filterData = [], ?string $searchQuery = null, string $exportMode = 'incentive')
    {
        $this->user = $user;
        $this->filterData = $filterData;
        $this->searchQuery = $searchQuery;
        $this->exportMode = $exportMode;
    }

    public function handle(): void
    {
        try {
            ini_set('memory_limit', '1024M');

            if ($this->exportMode === 'author') {
                $this->exportByAuthor();
            } else {
                $this->exportByIncentive();
            }
        } catch (\Exception $e) {
            Log::error('Incentive export failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            Notification::make()
                ->title('Export Failed')
                ->body('There was an error generating your export. Please try again.')
                ->danger()
                ->sendToDatabase($this->user);
        }
    }

    /**
     * One block per incentive: the record, then a row for each author paid.
     */
    protected function exportByIncentive(): void
    {
        $fileName = 'incentives_export_' . now()->format('Y-m-d_His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        Storage::disk('public')->makeDirectory('exports');
        $absPath = Storage::disk('public')->path($filePath);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Incentives');

        $headers = [
            'A' => 'ID', 'B' => 'Publication Title', 'C' => 'Incentive Total', 'D' => 'Incentive Status',
            'E' => 'Approved By', 'F' => 'Approved At', 'G' => 'Paid By', 'H' => 'Paid At',
            'I' => 'Created By', 'J' => 'Created At', 'K' => 'Remarks',
            'L' => 'Author Name', 'M' => 'Author Type', 'N' => 'Employee ID', 'O' => 'Role', 'P' => 'Amount',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . '1', $header);
        }

        $sheet->getStyle('A1:P1')->applyFromArray($this->headerStyle('4472C4'));

        $query = PublicationIncentive::query()
            ->with(['publication', 'creator', 'approver', 'payer']);

        $this->applyFilters($query);

        $row = 2;
        $index = 1;
        $grandTotal = 0.0;

        $query->chunkById(200, function ($incentives) use ($sheet, &$row, &$index, &$grandTotal) {
            foreach ($incentives as $incentive) {
                $grandTotal += (float) $incentive->total_amount;

                $authors = $this->authorsOf($incentive->publication_id);
                $lineCount = max($authors->count(), 1);
                $startRow = $row;

                $incentiveData = [
                    'A' => $index++,
                    'B' => $incentive->publication?->title,
                    'C' => (float) $incentive->total_amount,
                    'D' => $incentive->status,
                    'E' => $incentive->approver?->name,
                    'F' => $incentive->approved_at?->format('Y-m-d H:i'),
                    'G' => $incentive->payer?->name,
                    'H' => $incentive->paid_at?->format('Y-m-d H:i'),
                    'I' => $incentive->creator?->name ?? 'System Generated',
                    'J' => $incentive->created_at?->format('Y-m-d H:i'),
                    'K' => $incentive->remarks,
                ];

                foreach ($incentiveData as $col => $value) {
                    $sheet->setCellValue($col . $startRow, $value);
                }

                if ($authors->isEmpty()) {
                    $row++;
                } else {
                    foreach ($authors as $author) {
                        $sheet->setCellValue('L' . $row, $author['name']);
                        $sheet->setCellValue('M' . $row, $author['type']);
                        $sheet->setCellValue('N' . $row, $author['employee_id']);
                        $sheet->setCellValue('O' . $row, $author['role']);
                        $sheet->setCellValue('P' . $row, $author['amount']);
                        $row++;
                    }
                }

                // The incentive is one record however many people it paid, so its
                // cells span their rows rather than repeating.
                if ($lineCount > 1) {
                    $endRow = $startRow + $lineCount - 1;

                    foreach (array_keys($incentiveData) as $col) {
                        $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                    }
                }
            }
        });

        $sheet->setCellValue('B' . $row, 'Grand Total:');
        $sheet->setCellValue('C' . $row, $grandTotal);
        $sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray($this->totalStyle());
        $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        $this->setFixedWidths($sheet, [
            'A' => 8, 'B' => 55, 'C' => 16, 'D' => 14, 'E' => 22, 'F' => 18,
            'G' => 22, 'H' => 18, 'I' => 22, 'J' => 18, 'K' => 30,
            'L' => 28, 'M' => 12, 'N' => 14, 'O' => 16, 'P' => 14,
        ]);

        $sheet->getStyle('C2:C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('P2:P' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        $this->finalizeExport($spreadsheet, $absPath, $filePath, $row - 1, 'P');
    }

    /**
     * One block per author: what they were paid, and on which papers.
     */
    protected function exportByAuthor(): void
    {
        $fileName = 'incentive_authors_export_' . now()->format('Y-m-d_His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        Storage::disk('public')->makeDirectory('exports');
        $absPath = Storage::disk('public')->path($filePath);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Authors');

        $headers = [
            'A' => 'Author Name', 'B' => 'Author Type', 'C' => 'Employee ID', 'D' => 'Department',
            'E' => 'Total Incentive Received', 'F' => 'Publication Title', 'G' => 'Publication Date',
            'H' => 'Incentive Status', 'I' => 'Role', 'J' => 'Incentive Amount',
            'K' => 'Publication Total', 'L' => 'Share Percentage (%)',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . '1', $header);
        }

        $sheet->getStyle('A1:L1')->applyFromArray($this->headerStyle('ED7D31'));

        $query = PublicationIncentive::query()->with('publication');
        $this->applyFilters($query);

        /** @var array<string, array<string, mixed>> keyed by "Type:id" */
        $authorMap = [];

        $query->chunkById(200, function ($incentives) use (&$authorMap) {
            foreach ($incentives as $incentive) {
                $publicationTotal = (float) $incentive->total_amount;

                foreach ($this->authorsOf($incentive->publication_id) as $author) {
                    $key = $author['key'];

                    if (! isset($authorMap[$key])) {
                        $authorMap[$key] = [
                            'name' => $author['name'],
                            'type' => $author['type'],
                            'employee_id' => $author['employee_id'],
                            'department' => $author['department'],
                            'total' => 0.0,
                            'lines' => [],
                        ];
                    }

                    $authorMap[$key]['total'] += $author['amount'];

                    $authorMap[$key]['lines'][] = [
                        'title' => $incentive->publication?->title,
                        'date' => $incentive->publication?->publication_date?->format('Y-m-d')
                            ?? $incentive->publication?->publication_year,
                        'status' => $incentive->status,
                        'role' => $author['role'],
                        'amount' => $author['amount'],
                        'publication_total' => $publicationTotal,
                        'share' => $publicationTotal > 0 ? ($author['amount'] / $publicationTotal) * 100 : 0,
                    ];
                }
            }
        });

        // Largest earner first: this sheet is read to answer "who received what",
        // and that question is asked from the top.
        uasort($authorMap, fn ($a, $b) => $b['total'] <=> $a['total']);

        $row = 2;
        $grandTotal = 0.0;

        foreach ($authorMap as $author) {
            $grandTotal += $author['total'];
            $startRow = $row;
            $lineCount = count($author['lines']);

            $sheet->setCellValue('A' . $startRow, $author['name']);
            $sheet->setCellValue('B' . $startRow, $author['type']);
            $sheet->setCellValue('C' . $startRow, $author['employee_id']);
            $sheet->setCellValue('D' . $startRow, $author['department']);
            $sheet->setCellValue('E' . $startRow, $author['total']);

            foreach ($author['lines'] as $line) {
                $sheet->setCellValue('F' . $row, $line['title']);
                $sheet->setCellValue('G' . $row, $line['date']);
                $sheet->setCellValue('H' . $row, $line['status']);
                $sheet->setCellValue('I' . $row, $line['role']);
                $sheet->setCellValue('J' . $row, $line['amount']);
                $sheet->setCellValue('K' . $row, $line['publication_total']);
                $sheet->setCellValue('L' . $row, number_format($line['share'], 2) . '%');
                $row++;
            }

            $endRow = $startRow + max($lineCount, 1) - 1;

            foreach (range('A', 'E') as $col) {
                if ($lineCount > 1) {
                    $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                }

                $sheet->getStyle("{$col}{$startRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("{$col}{$startRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        $sheet->setCellValue('D' . $row, 'Grand Total:');
        $sheet->setCellValue('E' . $row, $grandTotal);
        $sheet->getStyle('D' . $row . ':E' . $row)->applyFromArray($this->totalStyle());
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getColumnDimension('F')->setAutoSize(false);
        $sheet->getColumnDimension('F')->setWidth(50);

        $sheet->getStyle('E2:E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('J2:K' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        $this->finalizeExport($spreadsheet, $absPath, $filePath, $row - 1, 'L');
    }

    /**
     * Everyone paid on one publication, teachers and external authors alike.
     *
     * Read straight from the pivot rather than through the two relations,
     * because the money is on the pivot and the ordering that makes the sheet
     * readable — first author, then corresponding, then the rest — is too.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function authorsOf(?int $publicationId): \Illuminate\Support\Collection
    {
        if (! $publicationId) {
            return collect();
        }

        $pivots = DB::table('publication_authors')
            ->where('publication_id', $publicationId)
            ->get();

        if ($pivots->isEmpty()) {
            return collect();
        }

        $teachers = Teacher::whereIn(
            'id',
            $pivots->where('authorable_type', Teacher::class)->pluck('authorable_id'),
        )->with('department')->get()->keyBy('id');

        $externals = Author::whereIn(
            'id',
            $pivots->where('authorable_type', Author::class)->pluck('authorable_id'),
        )->get()->keyBy('id');

        return $pivots->map(function ($pivot) use ($teachers, $externals) {
            $isTeacher = $pivot->authorable_type === Teacher::class;
            $model = $isTeacher
                ? $teachers->get($pivot->authorable_id)
                : $externals->get($pivot->authorable_id);

            return [
                'key' => ($isTeacher ? 'T' : 'A') . ':' . $pivot->authorable_id,
                'name' => $isTeacher
                    ? ($model?->full_name ?? 'Unknown')
                    : ($model?->name ?? 'Unknown'),
                'type' => $isTeacher ? 'Teacher' : 'External',
                'employee_id' => $isTeacher ? $model?->employee_id : null,
                'department' => $isTeacher ? $model?->department?->name : null,
                'role' => match ($pivot->author_role) {
                    'first' => '1st Author',
                    'corresponding' => 'Corresponding',
                    'co_author' => 'Co-Author',
                    default => (string) $pivot->author_role,
                },
                'amount' => (float) ($pivot->incentive_amount ?? 0),
                'priority' => sprintf('%d-%04d', match ($pivot->author_role) {
                    'first' => 1,
                    'corresponding' => 2,
                    default => 3,
                }, $pivot->sort_order),
            ];
        })->sortBy('priority')->values();
    }

    /**
     * The table's filters, as this page defines them.
     *
     * They are not the publications page's filters: the status here is the
     * incentive's own, and faculty and department reach through the publication.
     */
    protected function applyFilters(Builder $query): void
    {
        if (filled($this->searchQuery)) {
            $search = $this->searchQuery;

            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('publication', function ($pq) use ($search) {
                    $pq->where('title', 'like', "%{$search}%")
                        ->orWhereHas('teachers', function ($sq) use ($search) {
                            $sq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('employee_id', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('externalAuthors', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });
        }

        if (! empty($this->filterData['status'])) {
            $status = $this->filterData['status'];
            $values = is_array($status) ? ($status['values'] ?? $status) : [$status];
            $values = array_filter((array) $values);

            if ($values) {
                $query->whereIn('status', $values);
            }
        }

        $dates = $this->filterData['publication_date'] ?? [];

        if (filled($dates['date_from'] ?? null) || filled($dates['date_until'] ?? null)) {
            // Year-only publications are in range too; see
            // Publication::scopePublishedBetween().
            $query->whereHas('publication', fn ($q) => $q
                ->publishedBetween($dates['date_from'] ?? null, $dates['date_until'] ?? null));
        }

        if (! empty($this->filterData['faculty_department']['faculty_id'])) {
            $facultyId = $this->filterData['faculty_department']['faculty_id'];

            $query->whereHas('publication.department', fn ($q) => $q->where('faculty_id', $facultyId));
        }

        if (! empty($this->filterData['faculty_department']['department_id'])) {
            $departmentId = $this->filterData['faculty_department']['department_id'];

            $query->whereHas('publication', fn ($q) => $q->where('department_id', $departmentId));
        }
    }

    /** @return array<string, mixed> */
    protected function headerStyle(string $rgb): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
    }

    /** @return array<string, mixed> */
    protected function totalStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
        ];
    }

    /** @param  array<string, int>  $widths */
    protected function setFixedWidths($sheet, array $widths): void
    {
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    protected function finalizeExport($spreadsheet, string $absPath, string $filePath, int $lastRow, string $maxCol): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        if ($lastRow >= 2) {
            $sheet->getStyle("A2:{$maxCol}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            $sheet->getStyle("A2:{$maxCol}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($absPath);

        Notification::make()
            ->title('Export Completed')
            ->body("Your incentive {$this->exportMode} export is ready.")
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
}
