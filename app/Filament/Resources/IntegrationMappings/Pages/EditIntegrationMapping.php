<?php

namespace App\Filament\Resources\IntegrationMappings\Pages;

use App\Filament\Resources\IntegrationMappings\IntegrationMappingResource;
use App\Support\MappingGroups;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIntegrationMapping extends EditRecord
{
    protected static string $resource = IntegrationMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Split the stored flat rule list into one section per part of the payload.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['mapping_groups'] = MappingGroups::group((array) ($data['mapping_config'] ?? []));

        return $data;
    }

    /**
     * Fold it back before saving. The stored shape never changes.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['mapping_config'] = MappingGroups::flatten((array) ($data['mapping_groups'] ?? []));

        // sample_json is kept: it is the response this mapping was built from,
        // and picking up where you left off needs it.
        unset($data['mapping_groups']);

        return $data;
    }
}
