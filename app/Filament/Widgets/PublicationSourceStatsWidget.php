<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Publications\Pages\ListPublications;
use App\Models\Teacher;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Coverage report for PD-sourced publications: how many of them have an
 * internal teacher linked in `publication_authors`, and how many are still
 * external-authors-only. Reacts to the filters applied on the publications
 * table it is mounted on.
 */
class PublicationSourceStatsWidget extends BaseWidget
{
    use InteractsWithPageTable;

    // Mounted explicitly as a header widget on the publications list; keep it
    // out of the auto-discovered dashboard widgets.
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'PD Publications — Internal Teacher Coverage';

    protected ?string $description = 'Counts respect the filters currently applied to the table below.';

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListPublications::class;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Publication') ?? false;
    }

    protected function getStats(): array
    {
        $pdTotal = $this->pdQuery()->count();
        $matched = $this->pdQuery()->whereHas('teachers')->count();
        $unmatched = $pdTotal - $matched;

        return [
            Stat::make('PD Publications', number_format($pdTotal))
                ->description('Records flagged "Come From PD"')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('primary')
                ->url($this->filterUrl(['come_from_pd' => '1'])),

            Stat::make('Internal Teacher Attached', number_format($matched))
                ->description($this->percentage($matched, $pdTotal) . ' of PD publications')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url($this->filterUrl(['come_from_pd' => '1', 'internal_teacher_attached' => '1'])),

            Stat::make('No Internal Teacher', number_format($unmatched))
                ->description($this->percentage($unmatched, $pdTotal) . ' still unmatched')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($unmatched > 0 ? 'danger' : 'gray')
                ->url($this->filterUrl(['come_from_pd' => '1', 'internal_teacher_attached' => '0'])),

            Stat::make('Distinct Teachers Involved', number_format($this->distinctTeacherCount()))
                ->description('Unique internal teachers across PD publications')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }

    protected ?Builder $pdBaseQuery = null;

    /**
     * The page's filtered table query, narrowed to PD-sourced records. Always
     * returns a clone so callers can chain freely, and ordering is dropped so
     * the builder stays reusable as a subquery.
     */
    protected function pdQuery(): Builder
    {
        $this->pdBaseQuery ??= $this->getPageTableQuery()
            ->reorder()
            ->where('publications.come_from_pd', true);

        return $this->pdBaseQuery->clone();
    }

    protected function distinctTeacherCount(): int
    {
        return DB::table('publication_authors')
            ->where('authorable_type', Teacher::class)
            ->whereIn(
                'publication_id',
                $this->pdQuery()->select('publications.id')->toBase(),
            )
            ->distinct()
            ->count('authorable_id');
    }

    protected function percentage(int $part, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }

        return round(($part / $total) * 100, 1) . '%';
    }

    /**
     * Build a publications-list URL that pre-applies the given ternary filters.
     * ListRecords binds `$tableFilters` to the `filters` query string key.
     *
     * @param  array<string, string>  $filters
     */
    protected function filterUrl(array $filters): string
    {
        $parameters = [];

        foreach ($filters as $name => $value) {
            $parameters['filters'][$name]['value'] = $value;
        }

        return ListPublications::getUrl($parameters);
    }
}
