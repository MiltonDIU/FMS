<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TeacherPublicationTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Publication Trends (All Time)';

    // protected int | string | array $columnSpan = 'full'; // Removed to avoid potential conflict

    protected static ?int $sort = 4;

    public ?Teacher $record = null;

    protected function getTeacher(): ?Teacher
    {
        if ($this->record) {
            return $this->record;
        }
        return Auth::user()?->teacher;
    }

    public static function canView(): bool
    {
        return Auth::user()?->can('View:TeacherPublicationTrendWidget');
    }

    protected function getData(): array
    {
        $teacher = $this->getTeacher();

        if (!$teacher) {
            return [];
        }

        $minDate = $teacher->publications()->min('publication_date');
        $startYear = $minDate ? \Carbon\Carbon::parse($minDate)->year : now()->year - 4;
        $endYear = now()->year;

        // Ensure at least 1 year range
        if ($startYear > $endYear) $startYear = $endYear;

        $years = collect(range($startYear, $endYear));

        $data = $years->map(function ($year) use ($teacher) {
            return $teacher->publications()
                ->whereYear('publication_date', $year)
                ->count();
        });

        return [
            'datasets' => [
                [
                    'label' => 'Publications',
                    'data' => $data->toArray(),
                    'borderColor' => '#3b82f6', // blue-500
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $years->map(fn($y) => (string)$y)->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
