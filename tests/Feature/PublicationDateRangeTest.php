<?php

namespace Tests\Feature;

use App\Models\Publication;
use Tests\TestCase;

/**
 * Date-range filters have to read the year as well as the date.
 *
 * The source export carries a Publication Date for 1,465 of its 12,423 rows and
 * every one of them was imported, so the empty column is a limit of the data,
 * not a bug in the import. What was a bug is that every filter read
 * publication_date alone: asking for 2023 returned 12 papers out of roughly
 * fifteen hundred, and asking for 2015-2019 returned none at all.
 */
class PublicationDateRangeTest extends TestCase
{
    public function test_a_range_includes_publications_that_only_know_their_year(): void
    {
        $dateOnly = Publication::whereNotNull('publication_date')
            ->whereBetween('publication_date', ['2020-01-01', '2024-12-31'])
            ->count();

        $withFallback = Publication::publishedBetween('2020-01-01', '2024-12-31')->count();

        $this->assertGreaterThan(
            $dateOnly,
            $withFallback,
            'the year fallback found nothing the date column had not already',
        );
    }

    public function test_a_year_only_publication_is_found_by_a_range_covering_its_year(): void
    {
        $publication = Publication::whereNull('publication_date')
            ->whereNotNull('publication_year')
            ->first();

        if (! $publication) {
            $this->markTestSkipped('no year-only publication in the database');
        }

        $year = (int) $publication->publication_year;

        $this->assertTrue(
            Publication::publishedBetween("{$year}-01-01", "{$year}-12-31")
                ->whereKey($publication->id)
                ->exists(),
        );

        // And is left out of a range that does not cover it.
        $this->assertFalse(
            Publication::publishedBetween(($year + 1) . '-01-01', ($year + 2) . '-12-31')
                ->whereKey($publication->id)
                ->exists(),
        );
    }

    public function test_a_dated_publication_is_matched_on_its_exact_day(): void
    {
        $publication = Publication::whereNotNull('publication_date')->first();

        if (! $publication) {
            $this->markTestSkipped('no dated publication in the database');
        }

        $day = $publication->publication_date->format('Y-m-d');

        $this->assertTrue(
            Publication::publishedBetween($day, $day)->whereKey($publication->id)->exists(),
        );
    }

    public function test_the_bounds_are_optional(): void
    {
        $total = Publication::count();

        $this->assertSame($total, Publication::publishedBetween()->count());
        $this->assertSame($total, Publication::publishedBetween(null, null)->count());

        // Open-ended in either direction still filters.
        $this->assertLessThan($total, Publication::publishedBetween('2024-01-01')->count());
        $this->assertLessThan($total, Publication::publishedBetween(null, '2010-12-31')->count());
    }
}
