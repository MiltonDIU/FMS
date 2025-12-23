<?php

namespace App\Filament\Resources\SocialMediaPlatforms\Pages;

use App\Filament\Resources\SocialMediaPlatforms\SocialMediaPlatformResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSocialMediaPlatforms extends ListRecords
{
    protected static string $resource = SocialMediaPlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
