<?php

namespace App\Filament\Concerns;

/**
 * Loads heavy relationship repeaters a window at a time instead of all at once.
 *
 * A teacher with a few hundred publications used to render every single row on
 * page load, which is what made the profile slow to open. Repeaters built with
 * TeacherForm::window() only hydrate one page of rows and show a "Load more"
 * button for the rest; rows that were never loaded are left untouched on save
 * (see TeacherForm::removedIds()).
 *
 * Page size is decided per repeater in TeacherForm::WINDOWS and passed in here,
 * so each repeater can have its own number.
 */
trait HasWindowedRepeaters
{
    /** Page size for repeaters that don't declare their own. */
    public const REPEATER_WINDOW = 10;

    /**
     * How many rows of each repeater are currently loaded, keyed by repeater
     * name. 0 means "no limit, everything is loaded".
     *
     * @var array<string, int>
     */
    public array $repeaterWindows = [];

    public function getRepeaterWindow(string $name, ?int $size = null): int
    {
        return $this->repeaterWindows[$name] ?? $size ?? static::REPEATER_WINDOW;
    }

    /**
     * Widen one repeater's window by another page of rows, keeping whatever the
     * user has already typed into the rows that are on screen.
     */
    public function loadMoreRepeaterItems(string $name, ?int $size = null): void
    {
        $size ??= static::REPEATER_WINDOW;

        $this->repeaterWindows[$name] = $this->getRepeaterWindow($name, $size) + $size;

        $edited = data_get($this->data, $name) ?? [];

        // Re-hydrating just this path reloads the repeater from the (now wider)
        // relationship query and runs the child components' hydration hooks, so
        // the newly loaded rows get their derived fields (authors).
        $this->getSchema('form')->fillPartially([], [$name]);

        $reloaded = data_get($this->data, $name) ?? [];

        // Rows already on screen win, so unsaved edits survive; rows that are new
        // to this window keep the freshly hydrated state.
        data_set($this->data, $name, array_replace($reloaded, $edited));
    }

    /**
     * Pull every row a window left behind into one repeater's state, keeping
     * the loaded rows exactly as the user left them — edits, additions and
     * removals alike.
     *
     * For a save that hands the whole list to TeacherVersionService, which
     * reads a missing row as a deleted one: without this, approving a teacher
     * with thirty publications would delete the twenty that were never loaded.
     * Unlike loadMoreRepeaterItems() a row the user removed stays removed, so
     * only the keys the window never showed are added back.
     */
    public function loadRemainingRepeaterItems(string $name): void
    {
        $repeater = collect($this->getSchema('form')->getFlatComponents(withHidden: true))
            ->first(fn ($c) => $c instanceof \Filament\Forms\Components\Repeater && $c->getName() === $name);

        if (! $repeater) {
            return;
        }

        $shown = array_flip($repeater->getCachedExistingRecords()->keys()->all());
        $edited = data_get($this->data, $name) ?? [];

        $this->repeaterWindows[$name] = 0;
        $this->getSchema('form')->fillPartially([], [$name]);

        $neverShown = array_diff_key(data_get($this->data, $name) ?? [], $shown);

        data_set($this->data, $name, $edited + $neverShown);
    }
}
