<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\EmploymentStatus;
use App\Models\PublicationQuartile;
use App\Models\PublicationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The fixed lists an app needs to build its own filter screens.
 *
 * These change a few times a year at most, so they are cached for a day and
 * served in one response rather than one request per list — a filter sheet that
 * has to make four calls before it can open is a filter sheet that feels slow.
 */
class LookupController extends Controller
{
    protected const CACHE_KEY = 'api.v1.lookups';

    protected const CACHE_FOR = 86400;

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Cache::remember(self::CACHE_KEY, self::CACHE_FOR, fn () => [
                'designations' => $this->list(Designation::class),
                'employment_statuses' => $this->list(EmploymentStatus::class),
                'publication_types' => $this->list(PublicationType::class),
                'publication_quartiles' => $this->list(PublicationQuartile::class),
            ]),
        ]);
    }

    /**
     * The id and name of every active row, in display order.
     *
     * The id is what the filter parameters take, so it has to travel with the
     * name — an app cannot filter by a label it was only shown.
     */
    protected function list(string $model): array
    {
        $query = $model::query();

        if (in_array('is_active', (new $model)->getFillable(), true)) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy(in_array('sort_order', (new $model)->getFillable(), true) ? 'sort_order' : 'name')
            ->get(['id', 'name'])
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
            ->all();
    }
}
