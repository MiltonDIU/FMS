<?php

namespace App\Filament\Resources\IntegrationMappings\Pages;

use App\Filament\Resources\IntegrationMappings\IntegrationMappingResource;
use App\Support\MappingGroups;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegrationMapping extends CreateRecord
{
    protected static string $resource = IntegrationMappingResource::class;

    /**
     * Fold the per-section editing shape back into the flat rule list that is
     * stored and that IntegrationService::transform() reads.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['mapping_config'] = MappingGroups::flatten((array) ($data['mapping_groups'] ?? []));

        // sample_json is kept: it is the response this mapping was built from,
        // and picking up where you left off needs it.
        unset($data['mapping_groups']);

        return $data;
    }
}
