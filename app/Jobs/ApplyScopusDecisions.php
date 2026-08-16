<?php

namespace App\Jobs;

use App\Models\ScopusImport;
use App\Models\User;
use App\Services\Scopus\ScopusAnalysisPayloadService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies the decisions a reviewer made, away from the request.
 *
 * This used to run inside the click that started it, which was survivable while
 * a reviewer approved a handful of rows at a time. Deciding in bulk changed
 * that: one import carried 793 papers marked approve, and applying them costs
 * roughly 37 queries and a tenth of a second each — about a minute, against a
 * 30-second limit. The request died mid-way through a select.
 *
 * Queued for the same reason [[BuildScopusReviewFile]] is, and it is the same
 * shape of work: thousands of lookups against every publication, teacher and
 * author we hold. The reviewer gets a notification when it lands, as they
 * already do when the workbook is built.
 */
class ApplyScopusDecisions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;

    /*
     * One attempt.
     *
     * A failure rolls the whole import back — the payload is only rewritten
     * inside the transaction — so a retry would be safe. It is still off:
     * whatever stopped it once will stop it again, and doing so quietly three
     * times just delays somebody being told.
     */
    public $tries = 1;

    /**
     * @param  ?array<int, string>  $paperKeys  only these papers, or null for every approved one
     */
    public function __construct(
        protected ScopusImport $import,
        protected ?int $userId = null,
        protected ?array $paperKeys = null,
    ) {}

    public function handle(ScopusAnalysisPayloadService $payloads): void
    {
        try {
            ini_set('memory_limit', '1024M');

            $result = $payloads->importOnlineDecisions($this->import->id, $this->paperKeys);

            $this->notify(
                sprintf(
                    '%d new publications created, %d existing updated, %d author IDs bound.',
                    $result['created'],
                    $result['updated'] ?? 0,
                    $result['people_linked'],
                ),
                $result['errors'],
            );
        } catch (Throwable $e) {
            Log::error('Applying Scopus decisions failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            $this->notifyFailed($e->getMessage());

            throw $e;
        }
    }

    /** @param  array<int, string>  $errors */
    protected function notify(string $body, array $errors): void
    {
        $user = $this->recipient();

        if (! $user) {
            return;
        }

        // Warnings are the ones worth reading — a paper that went in but whose
        // authors could not all be filed is not a success worth a green tick.
        if ($errors !== []) {
            Notification::make()
                ->title('Scopus decisions applied, with warnings')
                ->body($body . ' ' . count($errors) . ' warning(s): ' . implode('; ', array_slice($errors, 0, 3)))
                ->warning()
                ->sendToDatabase($user);

            return;
        }

        Notification::make()
            ->title('Scopus decisions applied')
            ->body($body)
            ->success()
            ->sendToDatabase($user);
    }

    protected function notifyFailed(string $reason): void
    {
        $user = $this->recipient();

        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Applying Scopus decisions failed')
            ->body($reason . ' Nothing was changed — the whole batch is rolled back, so the decisions are still waiting.')
            ->danger()
            ->sendToDatabase($user);
    }

    /** Whoever pressed the button, falling back to whoever uploaded the file. */
    protected function recipient(): ?User
    {
        return ($this->userId ? User::find($this->userId) : null) ?? $this->import->uploader;
    }
}
