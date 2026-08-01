<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug ?: Str::slug($this->title),
            'title' => $this->title,
            'journal_name' => $this->journal_name,
            'journal_link' => $this->journal_link,

            /*
             * The year is the field to rely on. An exact date exists for 1,465
             * of 17,510 records — that is all the source export ever carried —
             * so an app that sorts or groups by date has to fall back to this.
             */
            'publication_year' => $this->publication_year,
            'publication_date' => optional($this->publication_date)->toDateString(),

            'type' => optional($this->whenLoaded('type'))->name,
            'research_area' => $this->research_area,
            'keywords' => $this->keywords,
            'impact_factor' => $this->impact_factor,
            'citescore' => $this->citescore,
            'h_index' => $this->h_index,
            'quartile' => optional($this->whenLoaded('quartile'))->name,

            // Only on the single view: an abstract is long, and a list of a
            // hundred of them is a payload nobody asked for.
            'abstract' => $this->when(
                $request->routeIs('api.publications.show'),
                fn () => $this->abstract,
            ),

            /*
             * Both are set by the controller on the single view, not columns.
             * Read through getAttributes() rather than as properties: Eloquent
             * resolves an unknown property by looking for a method of that name,
             * and Publication has a citations() that takes an argument — so
             * `isset($this->citations)` does not answer false, it throws.
             */
            'authors' => $this->when(
                array_key_exists('authors_line', $this->getAttributes()),
                fn () => $this->getAttributes()['authors_line'],
            ),
            'citations' => $this->when(
                array_key_exists('citation_formats', $this->getAttributes()),
                fn () => $this->getAttributes()['citation_formats'],
            ),

            /*
             * Everyone credited on the paper, in the order the record keeps them
             * in. Teachers carry the address of their own profile so an app can
             * move between co-authors; external authors are a name only.
             *
             * incentive_amount lives on the same pivot and is deliberately not
             * here — what a teacher was paid is internal.
             */
            'contributors' => $this->when(
                $this->relationLoaded('teachers') || $this->relationLoaded('externalAuthors'),
                fn () => collect()
                    ->concat($this->whenLoaded('teachers', fn () => $this->teachers->map(fn ($t) => [
                        'name' => $t->full_name,
                        'role' => $t->pivot->author_role,
                        'sort_order' => $t->pivot->sort_order,
                        'is_faculty' => true,
                        'slug' => $t->webpage,
                    ]), collect()))
                    ->concat($this->whenLoaded('externalAuthors', fn () => $this->externalAuthors->map(fn ($a) => [
                        'name' => $a->name,
                        'role' => $a->pivot->author_role,
                        'sort_order' => $a->pivot->sort_order,
                        'is_faculty' => false,
                        'slug' => null,
                    ]), collect()))
                    ->sortBy('sort_order')
                    ->values(),
            ),
        ];
    }
}
