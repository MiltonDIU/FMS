<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A Scopus export somebody uploaded, and what came of it.
 *
 * Nothing here writes to publications, teachers or authors. The run reads the
 * file, works out what it thinks each row and each author corresponds to, and
 * writes a workbook for a person to check. Applying those decisions is a
 * separate step that does not exist yet, on purpose.
 */
class ScopusImport extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'original_filename',
        'source_path',
        'status',
        'failure_reason',
        'result_path',
        'summary',
        'options',
        'uploaded_by',
        'completed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'options' => 'array',
        'completed_at' => 'datetime',
    ];

    /** The rules this run was told to match by, defaults filled in. */
    public function matchingOptions(): \App\Services\Scopus\MatchingOptions
    {
        return \App\Services\Scopus\MatchingOptions::fromArray($this->options);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && filled($this->result_path);
    }

    /** Where the finished workbook can be downloaded from, or null. */
    public function downloadUrl(): ?string
    {
        if (! $this->isReady()) {
            return null;
        }

        return Storage::disk('public')->url($this->result_path);
    }

    /** A count from the summary, without the caller worrying whether it is set. */
    public function stat(string $key, mixed $default = 0): mixed
    {
        return data_get($this->summary, $key, $default);
    }
}
