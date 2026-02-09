<?php

namespace App\Filament\Resources\Teachers\Components;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Concerns\HasState;

class LegacyTeacherSearch extends Field
{
    protected string $view = 'filament.resources.teachers.components.legacy-teacher-search';
    
    public function getSearchResults(): array
    {
        return $this->evaluate($this->searchResults) ?? [];
    }
    
    public function searchResults(array | \Closure $results): static
    {
        $this->searchResults = $results;
        return $this;
    }
}
