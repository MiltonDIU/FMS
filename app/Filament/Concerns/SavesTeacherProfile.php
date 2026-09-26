<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\Teachers\Schemas\TeacherForm;
use App\Services\TeacherVersionService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Support\Exceptions\Halt;

/**
 * Reading the teacher form for TeacherVersionService, which decides what is
 * saved now and what waits for approval.
 *
 * Shared by the admin edit page and the teacher's own profile. Both used to
 * read the form their own way — the edit page through a getState() that
 * saved everything itself, the profile straight from the raw Livewire state
 * with no validation, where a disabled field or another teacher's row id
 * could be sent as easily as anything else.
 *
 * Expects HasWindowedRepeaters on the same page.
 */
trait SavesTeacherProfile
{
    /**
     * The form's validated, dehydrated state — written nowhere.
     *
     * A bare getState() also calls saveRelationships() on the whole form
     * (vendor/filament/schemas/src/Concerns/HasState.php). That wrote every
     * repeater straight to the database before the approval service was
     * asked, and associated each select's new id onto the record so the
     * service saw nothing to compare.
     *
     * afterValidate runs after validation, the upload hooks that move award
     * attachments into storage, and dehydration, but before relationships are
     * saved; halting there keeps exactly the part wanted. Disabled fields are
     * not dehydrated, so a teacher cannot change what the form locks.
     *
     * @return array<string, mixed>|null null when a hook genuinely halted
     */
    protected function validatedStateWithoutSaving(): ?array
    {
        $state = null;

        try {
            $this->form->getState(afterValidate: function (array $validated) use (&$state): void {
                $state = $validated;

                throw new Halt;
            });
        } catch (Halt) {
            // Ours when $state is set; otherwise a hook stopped the save.
        }

        return $state;
    }

    /**
     * Repeaters and the qualifications pivot, in the shape the service takes.
     *
     * Each row is its raw form state — which carries the row's id and the
     * derived author fields — overlaid with what the row's own fields
     * dehydrate to, so an award attachment arrives as its stored path rather
     * than the upload widget's keyed array. Every row a window held back is
     * loaded first, because the service reads a missing row as a deleted one.
     *
     * @return array<string, mixed>
     */
    protected function relationStateForService(): array
    {
        foreach (TeacherForm::windowedRelations() as $name) {
            $this->loadRemainingRepeaterItems($name);
        }

        $components = collect($this->form->getFlatComponents(withHidden: true))
            ->filter(fn ($c) => method_exists($c, 'getName'))
            ->keyBy(fn ($c) => $c->getName());

        $relations = [];

        foreach (TeacherVersionService::RELATION_NAMES as $name) {
            $repeater = $components->get($name);
            $raw = data_get($this->data, $name);

            // Raw state is whatever the browser sent, so a section the form
            // does not show, or shows locked, is not taken from it.
            if (! $repeater instanceof Repeater || $repeater->isDisabled() || ! is_array($raw)) {
                continue;
            }

            $items = $repeater->getItems();
            $rows = [];

            foreach ($raw as $key => $row) {
                $rows[] = array_merge(is_array($row) ? $row : [], $this->dehydratedRow($items[$key] ?? null));
            }

            $relations[$name] = $rows;
        }

        foreach (TeacherVersionService::PIVOT_RELATIONS as $name) {
            $select = $components->get($name);

            if ($select instanceof Select && ! $select->isDisabled() && is_array($ids = data_get($this->data, $name))) {
                $relations[$name] = array_values($ids);
            }
        }

        return $relations;
    }

    /**
     * One row as its fields dehydrate, or nothing to overlay.
     *
     * getState() validates as well, and the rows a window never showed are
     * loaded just before this — nobody has looked at them, and old imported
     * ones fail rules added since. The rows on screen were validated with the
     * form already, so a failure here is one of those, and it is saved as it
     * stands rather than blocking the save of something else.
     *
     * @return array<string, mixed>
     */
    private function dehydratedRow(?\Filament\Schemas\Schema $item): array
    {
        if (! $item) {
            return [];
        }

        try {
            return $item->getState(shouldCallHooksBefore: false);
        } catch (\Illuminate\Validation\ValidationException) {
            $this->resetErrorBag();

            return [];
        }
    }

    /**
     * Photo and documents: media, which the service does not version, saved as
     * they always were — and the teacher's photo column kept pointing at the
     * current avatar.
     */
    protected function saveTeacherMedia(\App\Models\Teacher $teacher): void
    {
        collect($this->form->getFlatComponents(withHidden: true))
            ->filter(fn ($c) => $c instanceof SpatieMediaLibraryFileUpload && ! $c->isDisabled())
            ->each(fn ($c) => $c->saveRelationships());

        $teacher->refresh();

        if ($teacher->hasMedia('avatar') && ($avatarUrl = $teacher->getFirstMediaUrl('avatar'))) {
            // Quietly: no observer, so nothing to switch off first. The flag
            // this used to set was never cleared, and silenced the observer for
            // every later write in the same request.
            $teacher->updateQuietly(['photo' => $avatarUrl]);
        }
    }
}
