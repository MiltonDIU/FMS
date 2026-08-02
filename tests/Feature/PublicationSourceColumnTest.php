<?php

namespace Tests\Feature;

use App\Filament\Resources\Publications\Pages\ListPublications;
use App\Models\Publication;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Source column: where a publication came from.
 *
 * Two boolean flags carry it, and they are not alternatives. Of the 17,510
 * records, 10,148 came from the old website alone, 5,464 from the PD export
 * alone, and 1,898 from both — the paper was in each and the import matched
 * them to one record. Neither flag set means nobody imported it: it was
 * entered here, which is 0 today.
 */
class PublicationSourceColumnTest extends TestCase
{
    public function test_a_record_from_the_old_site_says_so(): void
    {
        $this->assertSame(['Old Site'], $this->sourceOf(old: true, pd: false));
    }

    public function test_a_record_from_the_pd_export_says_so(): void
    {
        $this->assertSame(['PD'], $this->sourceOf(old: false, pd: true));
    }

    public function test_a_record_from_both_names_both(): void
    {
        // 1,898 records. A single label here would drop the fact that two
        // separate sources agreed on the same paper.
        $this->assertSame(['Old Site', 'PD'], $this->sourceOf(old: true, pd: true));
    }

    public function test_a_record_created_here_says_new(): void
    {
        $this->assertSame(['New'], $this->sourceOf(old: false, pd: false));
    }

    public function test_the_list_renders_a_row_that_carries_two_badges(): void
    {
        $publication = Publication::first();

        if (! $publication) {
            $this->markTestSkipped('No publications in the development database.');
        }

        // The colour callback is handed one state at a time when the state is a
        // list. Rendering a both-flags row is what proves that — a callback
        // given the whole array instead would fall through to the default, or
        // throw on the match.
        $publication->forceFill([
            'come_from_old_site' => true,
            'come_from_pd' => true,
        ])->save();

        $this->actingAs($this->anAdmin());

        Livewire::test(ListPublications::class)
            ->assertOk()
            ->assertCanRenderTableColumn('source')
            ->assertSee('Old Site')
            ->assertSee('PD');
    }

    /**
     * What the column would show for a record with these two flags.
     *
     * @return array<int, string>
     */
    protected function sourceOf(bool $old, bool $pd): array
    {
        $publication = Publication::first();

        if (! $publication) {
            $this->markTestSkipped('No publications in the development database.');
        }

        // Held inside the transaction the base TestCase rolls back.
        $publication->forceFill([
            'come_from_old_site' => $old,
            'come_from_pd' => $pd,
        ])->save();

        $this->actingAs($this->anAdmin());

        $column = Livewire::test(ListPublications::class)
            ->instance()
            ->getTable()
            ->getColumn('source')
            ->record($publication->fresh());

        return $column->getState();
    }

    protected function anAdmin(): User
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        if (! $user) {
            $this->markTestSkipped('No super_admin in the development database.');
        }

        return $user;
    }
}
