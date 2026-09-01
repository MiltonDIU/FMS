<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Support\TeacherMediaPathGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brings the teacher photographs onto our own storage before the old site goes.
 *
 * Every picture on the site today is still served from
 * faculty.daffodilvarsity.edu.bd: the import wrote a bare filename into
 * `teachers.photo` and the accessor prefixes it with that host. It works only
 * for as long as somebody else keeps that host running, and the whole point of
 * going live is that they will not.
 *
 * So each one is fetched once and put where an uploaded photograph goes — the
 * `avatar` media collection on the public disk, the same place the form writes
 * to — and the legacy filename is cleared so the accessor stops reaching
 * outside for it. The original address is kept on the media record rather than
 * thrown away, so a picture can always be traced back to where it came from.
 *
 * Safe to run again. A teacher who already has an avatar is skipped unless
 * --force is given, and because the collection is registered singleFile,
 * storing a new one removes the old: a teacher cannot end up with two.
 */
class DownloadTeacherPhotosCommand extends Command
{
    protected $signature = 'teachers:download-photos
                            {--limit=0 : Stop after this many teachers}
                            {--dry-run : Report what would be fetched, download nothing}
                            {--force : Fetch again for teachers who already have an avatar}
                            {--timeout=20 : Seconds to wait for one image}
                            {--teacher=* : Only these teacher ids}
                            {--repair : Put back any avatar whose stored file has gone missing}
                            {--reorganise : Move photographs already stored under the old flat layout into the joining-year folders, then stop}';

    protected $description = 'Download teacher photographs from the legacy faculty site into local storage';

    /** What an avatar is allowed to be. */
    protected const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * A picture larger than this is not a portrait, it is a mistake or an
     * error page dressed as one.
     */
    protected const MAX_BYTES = 8 * 1024 * 1024;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($this->option('repair')) {
            $this->repairMissingFiles($dryRun);
        }

        /*
         * Returns rather than falling through, unlike --repair. Repair exists to
         * feed the fetch pass — it puts the legacy filename back so the pass
         * below picks the teacher up again. Reorganising is housekeeping on
         * files already held, and nobody asking for it wants 1,800 downloads to
         * start behind it.
         */
        if ($this->option('reorganise')) {
            $this->reorganiseStoredFiles($dryRun);

            return self::SUCCESS;
        }

        $query = Teacher::query()
            // The column, not the accessor: the accessor falls back to the media
            // library, so reading it here would hand back the local copy of a
            // photograph already fetched and call it work still to do.
            ->whereNotNull('photo')
            ->where('photo', '!=', '');

        if ($ids = $this->option('teacher')) {
            $query->whereIn('id', $ids);
        }

        $total = (clone $query)->count();
        $limit = (int) $this->option('limit');

        if ($total === 0) {
            $this->info('No teacher is still carrying a legacy photograph filename.');

            return self::SUCCESS;
        }

        $this->info($dryRun
            ? "🔍 Dry run — {$total} teacher(s) carry a legacy photograph; nothing will be downloaded"
            : "🚀 Fetching photographs for {$total} teacher(s)...");

        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $bar->start();

        $stats = ['fetched' => 0, 'skipped' => 0, 'already_local' => 0, 'refused' => 0, 'failed' => 0];
        $failures = [];
        $seen = 0;

        foreach ($query->orderBy('id')->cursor() as $teacher) {
            if ($limit > 0 && $seen >= $limit) {
                break;
            }

            $seen++;
            $bar->advance();

            if (! $force && $teacher->getFirstMedia('avatar') !== null) {
                $stats['skipped']++;

                continue;
            }

            /*
             * The guarded address, not the raw one. This runs inside the
             * network, where 169.254.169.254 and the database host are
             * reachable and a public visitor's browser is not — and the column
             * was written by an import from another system's data rather than
             * typed into a form here.
             */
            $legacy = $teacher->legacyPhotoUrl();

            if ($legacy === null) {
                /*
                 * Nothing on the old site to fetch. The column holds an address
                 * rather than a legacy filename, which is what an upload made
                 * through the form leaves behind — that photograph is already
                 * ours. Not a failure, and reporting it as one buried the real
                 * ones in a list of people who were fine.
                 */
                $stats['already_local']++;

                continue;
            }

            $url = $teacher->serverFetchableLegacyPhotoUrl();

            if ($url === null) {
                $stats['refused']++;
                $failures[] = [$teacher->id, $teacher->webpage, 'address refused by the outbound guard'];

                continue;
            }

            if ($dryRun) {
                $stats['fetched']++;

                continue;
            }

            $problem = $this->fetchInto($teacher, $url);

            if ($problem !== null) {
                $stats['failed']++;
                $failures[] = [$teacher->id, $teacher->webpage, $problem];

                continue;
            }

            $stats['fetched']++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'Count'], [
            [$dryRun ? 'Would fetch' : 'Fetched', $stats['fetched']],
            ['Already had one (skipped)', $stats['skipped']],
            ['Already stored locally', $stats['already_local']],
            ['Address refused', $stats['refused']],
            ['Download failed', $stats['failed']],
        ]);

        if ($failures !== []) {
            $this->warn(count($failures) . ' photograph(s) did not arrive:');
            $this->table(
                ['Teacher', 'Webpage', 'Why'],
                array_slice($failures, 0, 30),
            );

            if (count($failures) > 30) {
                $this->line('  … and ' . (count($failures) - 30) . ' more');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Moves photographs stored under the old flat layout into the year folders.
     *
     * Everything fetched or uploaded before TeacherMediaPathGenerator was
     * introduced sits in a directory named after its own media id, at the root
     * of the disk. New arrivals go to teachers/{year}/{id}/ — so without this
     * pass the storage directory holds both shapes at once, and the older half
     * would break the moment anything recomputed their paths.
     *
     * The year is stamped onto the record first and the file follows it, in
     * that order: the stamp is what the path is built from, so a run that
     * stopped halfway leaves records pointing at files that are still there
     * rather than at a folder nothing was moved into.
     *
     * Safe to run again — anything already in place is counted and skipped.
     */
    protected function reorganiseStoredFiles(bool $dryRun): void
    {
        $generator = new TeacherMediaPathGenerator();

        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
            ->where('model_type', Teacher::class)
            ->orderBy('id')
            ->get();

        if ($media->isEmpty()) {
            $this->info('No teacher media on record, so there is nothing to reorganise.');

            return;
        }

        $this->info($dryRun
            ? "🔍 Dry run — {$media->count()} stored file(s) examined; nothing will be moved"
            : "📁 Reorganising {$media->count()} stored file(s) into joining-year folders...");

        $stats = ['moved' => 0, 'already' => 0, 'missing' => 0, 'failed' => 0];
        $problems = [];

        $bar = $this->output->createProgressBar($media->count());
        $bar->start();

        foreach ($media as $item) {
            $bar->advance();

            if (blank($item->getCustomProperty('storage_year'))) {
                $item->setCustomProperty(
                    'storage_year',
                    TeacherMediaPathGenerator::yearForTeacherId($item->model_id),
                );

                if (! $dryRun) {
                    $item->save();
                }
            }

            // The shape the default generator left behind: the media id alone.
            $from = (string) $item->getKey();
            $to = rtrim($generator->getPath($item), '/');

            $disk = Storage::disk($item->disk);

            /*
             * Checked before the source is, or a file an earlier run already
             * carried across would be reported as one that had gone missing.
             */
            if ($disk->exists($to . '/' . $item->file_name)) {
                $stats['already']++;

                // A half-finished earlier run can leave the old directory behind
                // with the photograph already copied out of it.
                if (! $dryRun && $from !== $to && $disk->directoryExists($from)) {
                    $disk->deleteDirectory($from);
                }

                continue;
            }

            if (! $disk->exists($from . '/' . $item->file_name)) {
                /*
                 * Not at the target and not at the source: the file is genuinely
                 * gone, which is what --repair is for. Nothing to carry across.
                 */
                $stats['missing']++;
                $problems[] = [$item->model_id, $item->file_name, 'nothing stored at ' . $from . '/ or ' . $to . '/'];

                continue;
            }

            if ($dryRun) {
                $stats['moved']++;

                continue;
            }

            try {
                // allFiles rather than the one photograph: the directory also
                // holds the generated conversions, and they belong together.
                foreach ($disk->allFiles($from) as $file) {
                    $disk->move($file, $to . '/' . Str::after($file, $from . '/'));
                }

                $disk->deleteDirectory($from);

                $stats['moved']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $problems[] = [$item->model_id, $item->file_name, Str::limit($e->getMessage(), 60)];
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'Count'], [
            [$dryRun ? 'Would move' : 'Moved', $stats['moved']],
            ['Already in a year folder', $stats['already']],
            ['File missing entirely (try --repair)', $stats['missing']],
            ['Move failed', $stats['failed']],
        ]);

        if ($problems !== []) {
            $this->warn(count($problems) . ' file(s) were left where they were:');
            $this->table(['Teacher', 'File', 'Why'], array_slice($problems, 0, 30));

            if (count($problems) > 30) {
                $this->line('  … and ' . (count($problems) - 30) . ' more');
            }
        }
    }

    /**
     * Puts back the teachers whose avatar record survived but whose file did not.
     *
     * A media row and the file it names can come apart — a storage directory
     * cleared by hand, a disk restored from a backup taken before the images
     * were fetched, a deployment that did not carry storage/app/public across.
     * The row still says a photograph exists, so nothing re-fetches it and the
     * page shows a broken image with no obvious cause.
     *
     * Recoverable because the fetch wrote down where each picture came from:
     * `legacy_filename` in the media record's custom properties is the value
     * this command cleared out of `teachers.photo`. Putting it back and
     * dropping the dead row leaves the teacher looking exactly as they did
     * before the first run, and the ordinary pass below fetches them again.
     */
    protected function repairMissingFiles(bool $dryRun): void
    {
        $missing = 0;
        $unrecoverable = [];

        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
            ->where('model_type', Teacher::class)
            ->where('collection_name', 'avatar')
            ->get();

        foreach ($media as $item) {
            try {
                if (is_file($item->getPath())) {
                    continue;
                }
            } catch (\Throwable $e) {
                // A path that cannot even be worked out is as missing as one
                // whose file is not there.
            }

            $legacy = $item->getCustomProperty('legacy_filename');

            if (blank($legacy)) {
                $unrecoverable[] = [$item->model_id, $item->file_name, 'no legacy filename recorded'];

                continue;
            }

            $missing++;

            if ($dryRun) {
                continue;
            }

            Teacher::whereKey($item->model_id)->update(['photo' => $legacy]);
            $item->forceDelete();
        }

        if ($missing === 0 && $unrecoverable === []) {
            $this->info('Every stored avatar still has its file.');

            return;
        }

        $this->warn(($dryRun ? 'Would put back ' : 'Put back ') . $missing . ' avatar(s) whose file had gone missing.');

        if ($unrecoverable !== []) {
            $this->error(count($unrecoverable) . ' could not be traced back to a source and were left alone:');
            $this->table(['Teacher', 'File', 'Why'], array_slice($unrecoverable, 0, 20));
        }

        $this->newLine();
    }

    /**
     * Fetches one photograph and stores it, or says what went wrong.
     *
     * Returns null on success. Everything that can fail here is somebody else's
     * server on the other end of a network, so it fails often enough that the
     * reason has to travel back rather than becoming a thrown exception that
     * stops the other 1,860.
     */
    protected function fetchInto(Teacher $teacher, string $url): ?string
    {
        try {
            $response = Http::timeout((int) $this->option('timeout'))
                ->withOptions(['stream' => false])
                ->get($url);
        } catch (\Throwable $e) {
            return Str::limit($e->getMessage(), 60);
        }

        if (! $response->successful()) {
            return 'HTTP ' . $response->status();
        }

        $body = $response->body();

        if ($body === '') {
            return 'empty response';
        }

        if (strlen($body) > self::MAX_BYTES) {
            return 'larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB';
        }

        /*
         * What the bytes are, not what the server claims and not what the URL
         * ends in. A missing photograph on that host answers 200 with an HTML
         * error page, and stored unchecked it would sit in the avatar
         * collection as a broken image nobody could explain.
         */
        $extension = $this->extensionOf($body);

        if ($extension === null) {
            return 'not an image';
        }

        $temporary = tempnam(sys_get_temp_dir(), 'avatar') . '.' . $extension;
        file_put_contents($temporary, $body);

        try {
            $teacher->addMedia($temporary)
                ->usingFileName($this->fileNameFor($teacher, $extension))
                ->usingName($teacher->full_name ?: ($teacher->webpage ?: 'teacher-' . $teacher->id))
                // Where it came from, kept so a picture can be traced back and
                // so a second run can tell a fetched photograph from an upload.
                ->withCustomProperties([
                    'source_url' => $url,
                    'legacy_filename' => $teacher->getRawOriginal('photo'),
                    'fetched_at' => now()->toIso8601String(),
                ])
                // singleFile on the collection: this removes whatever was there.
                ->toMediaCollection('avatar', 'public');
        } catch (\Throwable $e) {
            @unlink($temporary);

            return Str::limit($e->getMessage(), 60);
        }

        // addMedia moves the file, but a failed conversion can leave it behind.
        @unlink($temporary);

        /*
         * The column has to go, or none of this shows.
         *
         * getPhotoAttribute returns the column when it is filled and only falls
         * through to the media library when it is not — so a teacher whose
         * photograph we now hold locally would still have every page pointing
         * at the old host. Cleared last, once the file is definitely stored,
         * and the value itself is on the media record above.
         */
        $teacher->forceFill(['photo' => null])->saveQuietly();

        return null;
    }

    /**
     * The name the file is stored under: the teacher's own webpage handle.
     *
     * It is what identifies them everywhere else — it is in their profile URL
     * and it is what the researcher import matched on — so a stored avatar can
     * be recognised without looking up an id. The few teachers with no handle
     * fall back to their id, which is the only other thing guaranteed unique.
     */
    protected function fileNameFor(Teacher $teacher, string $extension): string
    {
        $handle = trim((string) $teacher->webpage);

        // Never trust it straight into a path: it is imported data, and a
        // handle carrying a slash or dots would write outside the directory.
        $handle = $handle === '' ? '' : Str::slug($handle, '-', null);

        if ($handle === '') {
            $handle = 'teacher-' . $teacher->id;
        }

        return $handle . '.' . $extension;
    }

    /** The extension the bytes themselves call for, or null if they are not an image. */
    protected function extensionOf(string $body): ?string
    {
        $info = @getimagesizefromstring($body);

        if ($info === false) {
            return null;
        }

        return self::IMAGE_TYPES[$info['mime'] ?? ''] ?? null;
    }
}
