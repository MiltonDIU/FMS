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
            $fileName = 'publications_export_' . now()->format('Y-m-d_His') . '.csv';
            $filePath = 'exports/' . $fileName;

            // Ensure directory exists
            Storage::disk('public')->makeDirectory('exports');
            $path = Storage::disk('public')->path($filePath);

            $handle = fopen($path, 'w');

            // CSV Headers (Matching Excel Export)
            fputcsv($handle, [
                'ID',
                'Title',
                'Type',
                'Faculty',
                'Department',
                'Linkage',
                'Quartile',
                'Grant Type',
                'Collaboration',
                'Journal Name',
                'Journal Link',
                'Pub Date',
                'Pub Year',
                'Research Area',
                'H-Index',
                'Citescore',
                'Impact Factor',
                'Student Involvement',
                'Keywords',
                'Abstract',
                'Status',
                'Featured',
                'Sort Order',
                'Incentive Total',
                'Incentive Status',
                'Author Name',
                'Author Email',
                'Employee ID',
                'Role',
                'Amount',
            ]);

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

            // Process in Chunks
            $query->chunkById(500, function ($publications) use ($handle) {
                foreach ($publications as $publication) {
                    $authors = $publication->teachers->sortBy('pivot.sort_order');

                    // Prepare common publication data
                    $pubData = [
                        $publication->id,
                        $publication->title,
                        $publication->type?->name,
                        $publication->faculty?->name,
                        $publication->department?->name,
                        $publication->linkage?->name,
                        $publication->quartile?->name,
                        $publication->grant?->name,
                        $publication->collaboration?->name,
                        $publication->journal_name,
                        $publication->journal_link,
                        $publication->publication_date?->format('Y-m-d'),
                        $publication->publication_year,
                        $publication->research_area,
                        $publication->h_index,
                        $publication->citescore,
                        $publication->impact_factor,
                        $publication->student_involvement ? 'Yes' : 'No',
                        $publication->keywords,
                        $publication->abstract,
                        $publication->status,
                        $publication->is_featured ? 'Yes' : 'No',
                        $publication->sort_order,
                        $publication->incentive?->total_amount,
                        $publication->incentive?->status,
                    ];

                    if ($authors->isEmpty()) {
                        // Publication without authors: Write pubData + empty author fields
                        fputcsv($handle, array_merge($pubData, ['', '', '', '', '']));
                    } else {
                        // Sparse CSV Logic: Only first row has publication data, others are empty
                        $emptyPubData = array_fill(0, count($pubData), '');

                        foreach ($authors->values() as $index => $author) {
                            $roleLabel = match ($author->pivot->author_role) {
                                'first' => '1st Author',
                                'corresponding' => 'Corresponding',
                                'co_author' => 'Co-Author',
                                default => $author->pivot->author_role,
                            };

                            // Use full pubData for first author, empty for others
                            $currentPubData = ($index === 0) ? $pubData : $emptyPubData;

                            // Write Row: (Pub Data or Empty) + Author Data
                            fputcsv($handle, array_merge($currentPubData, [
                                $author->full_name,
                                $author->user?->email ?? '',
                                $author->employee_id,
                                $roleLabel,
                                $author->pivot->incentive_amount ?? 0,
                            ]));
                        }
                    }
                }
            });

            fclose($handle);

            // Notify User
            Notification::make()
                ->title('Export Completed')
                ->body('Your publication export is ready for download.')
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
             // SelectFilter logic: if it returns an array of values
             $statuses = $this->filterData['status'];
             // SelectFilter handles 'values' key if it's from the table payload,
             // but usually $filterData['status'] from the frontend component state might be just the value or array.
             // Filament Table filters structure is key => value.
             // SelectFilter multiple: value is array. Custom Query usually wraps it but here we are manual.
             // Standard SelectFilter applies "whereIn" if multiple.

             // Wait, in `PublicationsTable` it's a simple SelectFilter::make('status')->multiple()...
             // So it automatically adds whereIn('status', $values).
             // But we need to check if $statuses is an array or having 'values'.

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
            // TrashedFilter::make()
            // Values: '' (default), 'with_trashed', 'only_trashed'
            $trashed = $this->filterData['trashed'];
            if ($trashed === 'with_trashed') {
                 $query->withTrashed();
            } elseif ($trashed === 'only_trashed') {
                 $query->onlyTrashed();
            }
        }
    }
}
