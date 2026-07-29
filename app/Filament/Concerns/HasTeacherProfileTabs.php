<?php

namespace App\Filament\Concerns;

/**
 * Backs the `livewireProperty()` on the Teacher profile Tabs.
 *
 * With a Livewire property driving the tabs, Filament only renders the schema
 * of the active tab (see Tabs\Tab::toEmbeddedHtml()), so the heavy repeaters on
 * the other tabs cost nothing on first paint. Every page that renders
 * TeacherForm must expose this property, or the Tabs component cannot resolve
 * the active tab.
 */
trait HasTeacherProfileTabs
{
    public ?string $activeProfileTab = 'basic-info';
}
