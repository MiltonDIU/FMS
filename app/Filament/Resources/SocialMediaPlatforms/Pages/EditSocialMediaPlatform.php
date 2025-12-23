<?php

namespace App\Filament\Resources\SocialMediaPlatforms\Pages;

use App\Filament\Resources\SocialMediaPlatforms\SocialMediaPlatformResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialMediaPlatform extends EditRecord
{
    protected static string $resource = SocialMediaPlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
