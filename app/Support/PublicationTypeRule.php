<?php

namespace App\Support;

/**
 * What kind of output a publication is, read from whatever the source wrote.
 *
 * The PD export speaks Scopus. Its "Remarks" column carries Scopus document
 * types — Article, Conference paper, Review, Letter, Erratum — while
 * publication_types was seeded with the university's own wording: Journal
 * Article, Conference Proceeding, Review Article. The importer slugged the
 * text and looked it up, so "Article" became "article", matched nothing, and
 * 7,203 of 17,762 publications ended up with no type at all. Every one of them
 * came from PD; the old-site export uses our own names and always matched.
 *
 * Two different problems were tangled in that, and they need different answers:
 *
 *  - Names for a thing we already have. Scopus's "Article" is our Journal
 *    Article, its "Conference paper" our Conference Proceeding, its "Review"
 *    our Review Article. Those are synonyms, so they map. Adding them as rows
 *    would split one kind of output across two categories, which is the mess
 *    this is meant to end.
 *  - Kinds we genuinely did not have. A Letter, an Erratum, a Data Paper and an
 *    Editorial are not journal articles and there was nowhere to put them.
 *    Those became rows of their own.
 *
 * Anything still unrecognised is Not Assigned rather than null, for the same
 * reason the grant type and the quartile are: a null is invisible to the
 * charts, which filter it out and then do not add up to the number of
 * publications beside them.
 */
class PublicationTypeRule
{
    /** Nothing on record says what kind of output this is. */
    public const NOT_ASSIGNED = 'not-assigned';

    /**
     * Source wording => the slug it means.
     *
     * Keyed by the text reduced to letters only, so spacing, punctuation and
     * case cannot separate "Conference paper" from "Conference Paper" or
     * "Data paper" from "Data Paper". The export holds 22 spellings of these
     * fifteen ideas.
     *
     * @return array<string, string>
     */
    public static function map(): array
    {
        return [
            // Scopus' name on the left, ours on the right.
            'article' => 'journal-article',
            'journalarticle' => 'journal-article',
            'conferencepaper' => 'conference-proceeding',
            'conferenceproceeding' => 'conference-proceeding',
            'proceedingspaper' => 'conference-proceeding',
            'review' => 'review-article',
            'reviewarticle' => 'review-article',
            'reviewunpaid' => 'review-article',
            'shortsurvey' => 'short-survey',

            // A chapter in a conference volume is still a book chapter; three
            // rows do not warrant a category of their own.
            'book' => 'book',
            'bookchapter' => 'book-chapter',
            'conferencebookchapter' => 'book-chapter',

            'report' => 'report',
            'thesis' => 'thesis',
            'patent' => 'patent',

            // The ones with no equivalent until now.
            'letter' => 'letter',
            'datapaper' => 'data-paper',
            'erratum' => 'erratum',
            'editorial' => 'editorial',
            'note' => 'note',
            'retracted' => 'retracted',
        ];
    }

    /**
     * The rows publication_types needs beyond what it was first seeded with.
     *
     * Kept here rather than only in the seeder because a deploy runs migrations
     * and never seeders, so the migration that adds them reads this list too
     * and the two cannot disagree about what exists.
     *
     * @return array<int, array{name: string, slug: string, sort_order: int}>
     */
    public static function additionalTypes(): array
    {
        return [
            ['name' => 'Letter', 'slug' => 'letter', 'sort_order' => 9],
            ['name' => 'Data Paper', 'slug' => 'data-paper', 'sort_order' => 10],
            ['name' => 'Editorial', 'slug' => 'editorial', 'sort_order' => 11],
            ['name' => 'Short Survey', 'slug' => 'short-survey', 'sort_order' => 12],
            ['name' => 'Note', 'slug' => 'note', 'sort_order' => 13],
            ['name' => 'Erratum', 'slug' => 'erratum', 'sort_order' => 14],
            ['name' => 'Retracted', 'slug' => 'retracted', 'sort_order' => 15],
            // Last in every dropdown: where a publication lands, not something
            // anybody should be picking.
            ['name' => 'Not Assigned', 'slug' => self::NOT_ASSIGNED, 'sort_order' => 99],
        ];
    }

    /**
     * The type slug for a raw source value. Always returns a slug.
     */
    public static function slugFor(?string $value): string
    {
        $key = preg_replace('/[^a-z]/', '', mb_strtolower(trim((string) $value)));

        if ($key === '') {
            return self::NOT_ASSIGNED;
        }

        return static::map()[$key] ?? self::NOT_ASSIGNED;
    }
}
