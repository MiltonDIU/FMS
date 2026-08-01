<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesDirectoryPath;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PublicationResource;
use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * The publication library — 17,510 records — and the single-paper view.
 */
class PublicationController extends Controller
{
    use ResolvesDirectoryPath;

    public const PER_PAGE = 20;

    /**
     * Search across the whole library.
     *
     * ?q= title, journal, keywords or research area · ?year= · ?from=&to=
     * ?type= · ?faculty= · ?department=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Publication::query()->with(['type', 'quartile']);

        if (filled($search = $request->query('q'))) {
            $like = '%' . $search . '%';

            $query->where(fn ($q) => $q
                ->where('title', 'like', $like)
                ->orWhere('journal_name', 'like', $like)
                ->orWhere('keywords', 'like', $like)
                ->orWhere('research_area', 'like', $like));
        }

        if (filled($year = $request->query('year'))) {
            $query->where('publication_year', $year);
        }

        // publishedBetween reads the year where there is no exact date; see the
        // scope for why a range that read publication_date alone would answer
        // with a twelfth of the library and give no sign that it had.
        if (filled($request->query('from')) || filled($request->query('to'))) {
            $query->publishedBetween($request->query('from'), $request->query('to'));
        }

        if (filled($type = $request->query('type'))) {
            $query->where('publication_type_id', $type);
        }

        if (filled($faculty = $request->query('faculty'))) {
            $query->where('faculty_id', $faculty);
        }

        if (filled($department = $request->query('department'))) {
            $query->where('department_id', $department);
        }

        $publications = $query
            ->orderByDesc('publication_year')
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', self::PER_PAGE), 1), 100))
            ->withQueryString();

        return PublicationResource::collection($publications);
    }

    /**
     * One paper, with its abstract, its co-authors and ready-made citations.
     *
     * Addressed through the teacher whose page it was reached from, exactly as
     * the website addresses it. A paper with several authors is therefore
     * reachable at one URL per author, all serving the same record.
     */
    public function show(string $faculty, string $department, string $webpage, string $slug): PublicationResource
    {
        $teacher = $this->resolveTeacher($faculty, $department, $webpage);

        // Matched against the slug the model stores, with the title as a
        // fallback for rows imported before slugs existed, then the id.
        $publication = $teacher->publications
            ->first(fn ($p) => ($p->slug ?: Str::slug($p->title)) === $slug)
            ?? $teacher->publications->firstWhere('id', $slug);

        abort_if($publication === null, 404);

        $publication->load(['type', 'quartile', 'teachers.designation', 'externalAuthors']);

        /*
         * The citation is built from the teacher whose page this is, matching
         * what the website prints on the same paper.
         *
         * Held under citation_formats, not citations: the model already has a
         * citations() method, and an attribute of that name would collide with
         * it the moment anything read it back as a property.
         */
        $authors = trim($teacher->first_name . ' ' . $teacher->last_name);

        $publication->setAttribute('authors_line', $authors);
        $publication->setAttribute('citation_formats', $publication->citations($authors));

        return new PublicationResource($publication);
    }
}
