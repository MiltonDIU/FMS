<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records a model's creates, edits and deletes in the activity log.
 *
 * One set of defaults for every audited model, so they all read the same way:
 * the log name is the model's name, an edit keeps only the fields that moved,
 * and the entry's description names the record ("updated · Computer Science")
 * so the list can be read without opening each entry.
 *
 * A model lists in $activityLogIgnore the columns that change on their own —
 * counters, scores, view tallies — and the ones too large or too sensitive to
 * copy. They are left out of every entry, and a save that touched nothing else
 * writes no entry at all.
 *
 * Only what people do is recorded. Artisan commands and queue workers — the
 * ERP sync, the Scopus and researcher imports, email sending — write thousands
 * of rows at a time with nobody signed in, and would bury the entries that say
 * who changed what. They are skipped unless activitylog.log_console_model_events
 * is on.
 */
trait RecordsActivity
{
    use LogsActivity {
        shouldLogEvent as protected spatieShouldLogEvent;
    }

    /** Never worth an entry on any model. */
    protected static array $activityLogAlwaysIgnore = ['created_at', 'updated_at', 'sort_order'];

    public function getActivitylogOptions(): LogOptions
    {
        $ignore = array_merge(static::$activityLogAlwaysIgnore, $this->activityLogIgnored());

        return LogOptions::defaults()
            ->useLogName(class_basename(static::class))
            ->logAll()
            ->logExcept($ignore)
            ->dontLogIfAttributesChangedOnly($ignore)
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => ($label = $this->activityLogLabel())
                ? "{$event} · {$label}"
                : $event);
    }

    /** @return array<int, string> */
    protected function activityLogIgnored(): array
    {
        return property_exists($this, 'activityLogIgnore') ? $this->activityLogIgnore : [];
    }

    /** What the record is called, for the entry's description. */
    public function activityLogLabel(): ?string
    {
        foreach (['name', 'title', 'subject', 'key', 'email'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim($value), 0, 120);
            }
        }

        return null;
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        if (! \App\Models\Activity::recordsChangesHere()) {
            return false;
        }

        return $this->spatieShouldLogEvent($eventName);
    }
}
