<?php

namespace App\Jobs;

use App\Models\ScopusImport;
use App\Services\Scopus\ScopusAnalysis;
use App\Services\Scopus\ReviewWorkbook;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Turns an uploaded Scopus export into the workbook a person reviews.
 *
 * Queued because the file is megabytes and the work is thousands of name
 * comparisons — the July csv alone holds 1,572 papers and 12,349 author
 * entries. Nobody is going to hold a request open for that.
 *
 * It writes no publication, teacher or author. The only rows it touches are
 * this import's own, recording where the run got to.
 */
class BuildScopusReviewFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;

    public $tries = 1;

    public function __construct(protected ScopusImport $import) {}

    public function handle(ScopusAnalysis $analysis, ReviewWorkbook $workbook): void
    {
        $this->import->update(['status' => ScopusImport::STATUS_PROCESSING]);

        try {
            ini_set('memory_limit', '1024M');

            $sourcePath = Storage::disk('local')->path($this->import->source_path);

            // The rules this run was told to use. Defaults fill in for a record
            // saved before the options existed.
            $result = $analysis->run($sourcePath, $this->import->matchingOptions());

            $filename = 'scopus-review-' . $this->import->id . '-' . now()->format('Y-m-d_His') . '.xlsx';
            $relativePath = 'exports/' . $filename;

            Storage::disk('public')->makeDirectory('exports');

            $workbook->write(
                $result['papers'],
                $result['people'],
                $result['summary'],
                Storage::disk('public')->path($relativePath),
            );

            app(\App\Services\Scopus\ScopusAnalysisPayloadService::class)->savePayload($this->import->id, $result);

            $this->import->update([
                'status' => ScopusImport::STATUS_READY,
                'result_path' => $relativePath,
                'summary' => $result['summary'],
                'completed_at' => now(),
                'failure_reason' => null,
            ]);

            $this->notifyReady($result['summary']);
        } catch (Throwable $e) {
            Log::error('Scopus review build failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            $this->import->update([
                'status' => ScopusImport::STATUS_FAILED,
                // Kept on the record, not only in the log: whoever uploaded the
                // file needs to know it was the wrong file, and why.
                'failure_reason' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $this->notifyFailed($e->getMessage());
        }
    }

    protected function notifyReady(array $summary): void
    {
        $user = $this->import->uploader;

        if (! $user) {
            return;
        }

        $papers = $summary['papers'];
        $people = $summary['people'];

        Notification::make()
            ->title('Scopus review file ready')
            ->body(sprintf(
                '%d publications — %d already here, %d new. %d people connected to %s. Nothing has been changed.',
                $papers['total'],
                $papers['already_here'],
                $papers['new'],
                $people['total'],
                \App\Helpers\Institution::shortName(),
            ))
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->url(Storage::disk('public')->url($this->import->fresh()->result_path))
                    ->button()
                    ->openUrlInNewTab(),
            ])
            ->sendToDatabase($user);
    }

    protected function notifyFailed(string $reason): void
    {
        if (! $this->import->uploader) {
            return;
        }

        Notification::make()
            ->title('Scopus review file failed')
            ->body($reason)
            ->danger()
            ->sendToDatabase($this->import->uploader);
    }
}
