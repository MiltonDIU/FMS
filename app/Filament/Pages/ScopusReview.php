<?php

namespace App\Filament\Pages;

use App\Jobs\ApplyScopusDecisions;
use App\Jobs\BuildScopusReviewFile;
use App\Models\ScopusImport;
use App\Services\Scopus\MatchingOptions;
use App\Services\Scopus\ScopusFileReader;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Upload a Scopus export, get back a workbook to check.
 *
 * The first half of the two-part job the registrar's office asked for. This
 * half reads the file, works out which papers we already hold and who the
 * Daffodil-affiliated authors are, and writes it all into a spreadsheet.
 *
 * It changes nothing. Applying the decisions somebody makes in that spreadsheet
 * is the second half, and is deliberately not built yet — the shape of it
 * should follow from doing the review once.
 */
class ScopusReview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static UnitEnum|string|null $navigationGroup = 'Publication Management';

    protected static ?string $navigationLabel = 'Scopus Review';

    protected static ?string $title = 'Scopus Review';

    protected static ?string $slug = 'scopus-review';

    protected string $view = 'filament.pages.scopus-review';

    protected static ?int $navigationSort = 9;

    /**
     * Reading the file means reading every publication, teacher and author we
     * hold, so the page asks for the permission that covers the widest of those.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin')
            || ($user->can('ViewAny:Publication') && $user->can('Create:Publication'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload a Scopus export')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('primary')
                ->modalHeading('Upload a Scopus export')
                ->modalDescription('A .csv straight out of Scopus, or the .xlsx the Directorate of Research circulates. Nothing in the system is changed — you get a workbook to check.')
                ->modalSubmitActionLabel('Upload and analyse')
                ->form([
                    FileUpload::make('file')
                        ->label('Scopus file')
                        ->required()
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'text/x-csv',
                            'application/x-csv',
                            'text/comma-separated-values',
                            'text/x-comma-separated-values',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/zip',
                            'application/x-zip-compressed',
                            'application/octet-stream',
                        ])
                        /*
                         * The real ceiling, not a hopeful one.
                         *
                         * PHP refuses an upload over upload_max_filesize before
                         * Livewire or this page ever sees it, and the browser
                         * reports only "failed to upload" — which says nothing
                         * about why. Asking PHP what it will actually accept
                         * means the form can refuse it first, with a reason.
                         */
                        ->maxSize(static::uploadCeilingInKilobytes())
                        ->disk('local')
                        ->directory('scopus')
                        ->visibility('private')
                        ->helperText(sprintf(
                            'At least the columns Title and "Authors with affiliations". Author(s) ID, DOI and EID make the matching far better. This server accepts files up to %s.',
                            static::uploadCeilingLabel(),
                        )),

                    ...static::matchingToggles(),
                ])
                ->action(function (array $data): void {
                    $path = $data['file'];

                    $import = ScopusImport::create([
                        'original_filename' => basename($path),
                        'source_path' => $path,
                        'status' => ScopusImport::STATUS_UPLOADED,
                        'options' => static::optionsFrom($data),
                        'uploaded_by' => auth()->id(),
                    ]);

                    BuildScopusReviewFile::dispatch($import);

                    Notification::make()
                        ->title('Analysing the file')
                        ->body('You will get a notification with a download link when the workbook is ready.')
                        ->success()
                        ->send();
                }),

            Action::make('importWorkbook')
                ->label('Import checked workbook')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->modalHeading('Import decisions from checked workbook')
                ->modalDescription('Upload the .xlsx workbook after marking your decisions (e.g. Approve/Import in the Decision column). This will create approved publications and bind teacher Scopus IDs.')
                ->modalSubmitActionLabel('Import decisions')
                ->form([
                    FileUpload::make('file')
                        ->label('Reviewed Excel workbook (.xlsx)')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/octet-stream',
                            'application/zip',
                        ])
                        ->disk('local')
                        ->directory('scopus-reviewed')
                        ->visibility('private'),
                ])
                ->action(function (array $data, \App\Services\Scopus\ScopusWorkbookImporter $importer): void {
                    $path = Storage::disk('local')->path($data['file']);

                    $result = $importer->import($path);

                    $msg = sprintf(
                        '%d new publications imported, %d author IDs linked. (%d skipped)',
                        $result['publications_created'],
                        $result['people_linked'],
                        $result['publications_skipped'],
                    );

                    if (! empty($result['errors'])) {
                        $msg .= ' Warnings: ' . implode('; ', array_slice($result['errors'], 0, 3));
                    }

                    Notification::make()
                        ->title('Workbook imported successfully')
                        ->body($msg)
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ScopusImport::query()->with('uploader'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('original_filename')
                    ->label('File')
                    ->wrap()
                    ->description(fn (ScopusImport $record) => $record->uploader?->name),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ScopusImport::STATUS_READY => 'success',
                        ScopusImport::STATUS_PROCESSING => 'warning',
                        ScopusImport::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (ScopusImport $record) => $record->failure_reason),

                TextColumn::make('papers')
                    ->label('Publications')
                    ->state(fn (ScopusImport $record) => $record->isReady()
                        ? sprintf('%d — %d here, %d new',
                            $record->stat('papers.total'),
                            $record->stat('papers.already_here'),
                            $record->stat('papers.new'))
                        : '—'),

                TextColumn::make('people')
                    ->label(\App\Helpers\Institution::shortName() . ' authors')
                    ->state(fn (ScopusImport $record) => $record->isReady()
                        ? sprintf('%d — %d teachers, %d external, %d unknown',
                            $record->stat('people.total'),
                            $record->stat('people.teacher'),
                            $record->stat('people.external_author'),
                            $record->stat('people.not_found'))
                        : '—'),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                /*
                 * The same figures the workbook opens with, without downloading
                 * it. The summary is on the record already, so showing it costs
                 * nothing — and the question it answers most often ("why do
                 * 1,518 papers give only 447 teachers?") is one you want settled
                 * before opening a 438 KB spreadsheet.
                 */
                Action::make('reviewOnline')
                    ->label('Review Online')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('primary')
                    ->visible(fn (ScopusImport $record) => $record->isReady())
                    ->modalHeading(fn (ScopusImport $record) => 'Review Scopus Import — ' . $record->original_filename)
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('Import Decisions Online')
                    ->modalContent(function (ScopusImport $record, \App\Services\Scopus\ScopusAnalysisPayloadService $payloadService) {
                        $payload = $payloadService->getPayload($record->id);
                        return view('filament.pages.partials.scopus-online-review', [
                            'import' => $record,
                            'payload' => $payload ?: ['papers' => [], 'people' => [], 'summary' => $record->summary],
                        ]);
                    })
                    ->action(function (ScopusImport $record, array $data, \App\Services\Scopus\ScopusAnalysisPayloadService $payloadService): void {
                        if (! empty($data['decisions']) && is_array($data['decisions'])) {
                            foreach ($data['decisions'] as $pKey => $decision) {
                                if (filled($decision) && $decision !== 'pending') {
                                    $payloadService->setPaperDecision($record->id, (string) $pKey, (string) $decision);
                                }
                            }
                        }

                        if (! empty($data['people_decisions']) && is_array($data['people_decisions'])) {
                            foreach ($data['people_decisions'] as $pKey => $decision) {
                                if (filled($decision) && $decision !== 'pending') {
                                    $payloadService->setPersonDecision($record->id, (string) $pKey, (string) $decision);
                                }
                            }
                        }

                        static::applyDecisions($record);
                    }),

                Action::make('summary')
                    ->label('Summary')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->color('info')
                    ->visible(fn (ScopusImport $record) => filled($record->summary))
                    ->modalHeading(fn (ScopusImport $record) => 'Summary — ' . $record->original_filename)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('4xl')
                    ->modalContent(fn (ScopusImport $record) => view(
                        'filament.pages.partials.scopus-summary',
                        ['import' => $record],
                    )),

                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->visible(fn (ScopusImport $record) => $record->isReady())
                    ->url(fn (ScopusImport $record) => $record->downloadUrl())
                    ->openUrlInNewTab(),

                Action::make('rerun')
                    ->label('Run again')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->modalHeading('Run this file again')
                    ->modalDescription('Reads the same file against the system as it stands now — useful after teachers or authors have been corrected. Change the rules below to see what difference they make.')
                    ->modalSubmitActionLabel('Run')
                    ->visible(fn (ScopusImport $record) => $record->status !== ScopusImport::STATUS_PROCESSING
                        && Storage::disk('local')->exists($record->source_path))
                    // Pre-filled from the run before it, so re-running with one
                    // switch changed is a comparison rather than a fresh guess.
                    ->fillForm(fn (ScopusImport $record) => $record->matchingOptions()->toArray())
                    ->form(static::matchingToggles())
                    ->action(function (ScopusImport $record, array $data): void {
                        $record->update([
                            'options' => static::optionsFrom($data),
                            'status' => ScopusImport::STATUS_UPLOADED,
                            'failure_reason' => null,
                        ]);

                        BuildScopusReviewFile::dispatch($record);

                        Notification::make()->title('Running again')->success()->send();
                    }),

                /*
                 * Super admin only, and deliberately not tied to a permission.
                 *
                 * These runs are the record of what was reviewed and on what
                 * rules — a workbook somebody is halfway through checking has no
                 * other copy. Removing one should be a decision, not something
                 * anybody with access to the page can do by reflex.
                 */
                Action::make('delete')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (ScopusImport $record) => (auth()->user()?->hasRole('super_admin') ?? false)
                        // Not while a job is working on it: deleting the record
                        // out from under the run leaves the job writing to
                        // nothing and no record of why it stopped.
                        && $record->status !== ScopusImport::STATUS_PROCESSING)
                    ->requiresConfirmation()
                    ->modalHeading('Delete this run')
                    ->modalDescription(fn (ScopusImport $record) => 'Removes the uploaded file and the workbook it produced. '
                        . ($record->isReady()
                            ? 'Anyone still working through that workbook will lose it. '
                            : '')
                        . 'Nothing in the publications, teachers or authors tables is touched — this run never wrote to them.')
                    ->modalSubmitActionLabel('Delete')
                    ->action(function (ScopusImport $record): void {
                        foreach ([['local', $record->source_path], ['public', $record->result_path]] as [$disk, $path]) {
                            if (filled($path) && Storage::disk($disk)->exists($path)) {
                                Storage::disk($disk)->delete($path);
                            }
                        }

                        $record->delete();

                        Notification::make()
                            ->title('Run deleted')
                            ->body('The file and its workbook are gone. No publication, teacher or author was affected.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Scopus file has been analysed yet')
            ->emptyStateDescription('Upload an export to see which of its publications we already hold, and who its '
                . \App\Helpers\Institution::shortName() . ' authors are here.');
    }

    /**
     * The rules a run can be told to match by, as form toggles.
     *
     * The same set on upload and on Run again, so whoever starts a run can see
     * what it will be judged on rather than having to read the code — and so a
     * workbook can be explained months later by the switches stored beside it.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function matchingToggles(): array
    {
        $toggles = [];

        foreach (MatchingOptions::describe() as $key => $described) {
            $toggles[] = Toggle::make($key)
                ->label($described['label'])
                ->helperText($described['help'])
                ->default((new MatchingOptions)->toArray()[$key]);
        }

        return [
            Section::make('Matching rules')
                ->description('What this run is allowed to conclude, and from what. Stored with the result.')
                ->schema($toggles)
                ->collapsed(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected static function optionsFrom(array $data): array
    {
        return MatchingOptions::fromArray(
            collect(MatchingOptions::describe())
                ->keys()
                ->mapWithKeys(fn (string $key) => [$key => (bool) ($data[$key] ?? false)])
                ->all()
        )->toArray();
    }

    /**
     * The largest upload this server will actually take, in kilobytes.
     *
     * The smallest of three ceilings, because any one of them refuses first:
     * PHP's upload_max_filesize, PHP's post_max_size, and Livewire's own
     * temporary-file rule, which defaults to 12 MB.
     *
     * A stock php.ini allows 2 MB. The Scopus export for one term is 4.7 MB, so
     * this is not a hypothetical — the upload fails, and the browser says only
     * "failed to upload".
     */
    public static function uploadCeilingInKilobytes(): int
    {
        $livewireRules = config('livewire.temporary_file_upload.rules');

        $livewireMax = 102400;

        if ($livewireRules !== null) {
            $rules = is_array($livewireRules) ? $livewireRules : explode('|', (string) $livewireRules);

            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $livewireMax = (int) substr($rule, 4);
                }
            }
        }

        return (int) min(
            static::iniBytes('upload_max_filesize') / 1024,
            static::iniBytes('post_max_size') / 1024,
            $livewireMax,
        );
    }

    public static function uploadCeilingLabel(): string
    {
        $kilobytes = static::uploadCeilingInKilobytes();

        return $kilobytes >= 1024
            ? round($kilobytes / 1024, 1) . ' MB'
            : $kilobytes . ' KB';
    }

    /** php.ini writes sizes as 2M, 8M, 512K — not as numbers. */
    protected static function iniBytes(string $setting): float
    {
        $value = trim((string) ini_get($setting));

        if ($value === '') {
            return INF;
        }

        $number = (float) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** Shown on the page itself, so the columns are known before uploading. */
    public function requiredColumns(): array
    {
        return ScopusFileReader::REQUIRED;
    }

    public function updatePaperDecision(int $importId, string $paperKey, string $decision): void
    {
        app(\App\Services\Scopus\ScopusAnalysisPayloadService::class)
            ->setPaperDecision($importId, $paperKey, $decision);
    }

    public function updatePersonDecision(int $importId, string $personKey, string $decision, ?int $teacherId = null): void
    {
        app(\App\Services\Scopus\ScopusAnalysisPayloadService::class)
            ->setPersonDecision($importId, $personKey, $decision, $teacherId);
    }

    /**
     * Who actually corresponded, when the export got it wrong or said nothing.
     *
     * Names, as they appear in the author list beside the box — matched back to
     * a position by the same rules the correspondence address is read with, so a
     * reviewer types what they can see rather than counting along the row.
     */
    public function updateCorrespondingOverride(int $importId, string $paperKey, string $names): void
    {
        app(\App\Services\Scopus\ScopusAnalysisPayloadService::class)
            ->setCorrespondingOverride($importId, $paperKey, $names);
    }

    /**
     * The same decision across every row the reviewer ticked.
     *
     * A run brings hundreds of rows and most of them get the same answer, so
     * one at a time is not review, it is data entry. The keys arrive from the
     * page, so the service is left to decide which of them it recognises rather
     * than trusting the list.
     *
     * @param  array<int, string>  $paperKeys
     */
    public function bulkUpdatePaperDecisions(int $importId, array $paperKeys, string $decision): void
    {
        $applied = app(\App\Services\Scopus\ScopusAnalysisPayloadService::class)
            ->bulkSetPaperDecisions($importId, $paperKeys, $decision);

        $this->notifyBulkApplied($applied, count($paperKeys), 'publication');
    }

    /** @param  array<int, string>  $personKeys */
    public function bulkUpdatePersonDecisions(int $importId, array $personKeys, string $decision): void
    {
        $applied = app(\App\Services\Scopus\ScopusAnalysisPayloadService::class)
            ->bulkSetPersonDecisions($importId, $personKeys, $decision);

        $this->notifyBulkApplied($applied, count($personKeys), 'person');
    }

    /**
     * Says how many rows it reached, not just that it ran.
     *
     * Anything already imported is left alone, so the number applied can be
     * lower than the number ticked — and silently marking fewer rows than were
     * selected is exactly the kind of thing a reviewer should be told about.
     */
    protected function notifyBulkApplied(int $applied, int $requested, string $noun): void
    {
        if ($applied === 0) {
            Notification::make()
                ->title('Nothing changed')
                ->body('None of the selected rows could still be decided.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title($applied . ' ' . Str::plural($noun, $applied) . ' updated')
            ->body($applied < $requested
                ? sprintf('%d of the %d selected; the rest were already imported.', $applied, $requested)
                : 'Use “Import Decisions Online” to apply them.')
            ->success()
            ->send();
    }

    public function importTabDecisions(int $importId): void
    {
        $record = ScopusImport::find($importId);

        if ($record) {
            static::applyDecisions($record);
        }
    }

    /**
     * Hands the decisions to a queued job and says so.
     *
     * Applying them used to happen inside this click. That worked while people
     * approved a row at a time; deciding in bulk does not fit — 793 approved
     * papers cost about a minute of queries, and the request is cut off at 30
     * seconds, leaving the reviewer with a fatal error and no idea whether any
     * of it went in. (It had not: the whole batch is one transaction.)
     */
    protected static function applyDecisions(ScopusImport $record): void
    {
        $pending = static::pendingDecisionCount($record);

        if ($pending === 0) {
            Notification::make()
                ->title('Nothing to apply')
                ->body('No paper or person on this run is marked for import. Approve some first.')
                ->warning()
                ->send();

            return;
        }

        ApplyScopusDecisions::dispatch($record, auth()->id());

        Notification::make()
            ->title('Applying ' . $pending . ' ' . Str::plural('decision', $pending))
            ->body('This runs in the background because it can take minutes. You will get a notification when it is done — nothing is changed until then.')
            ->success()
            ->send();
    }

    /** How many rows the reviewer has actually marked for import. */
    protected static function pendingDecisionCount(ScopusImport $record): int
    {
        $payload = app(\App\Services\Scopus\ScopusAnalysisPayloadService::class)->getPayload($record->id);

        if (! $payload) {
            return 0;
        }

        $approved = fn (array $rows) => count(array_filter(
            $rows,
            fn (array $row) => in_array(strtolower(trim((string) ($row['decision'] ?? ''))), ['approve', 'import', 'yes'], true),
        ));

        return $approved($payload['papers'] ?? []) + $approved($payload['people'] ?? []);
    }
}
