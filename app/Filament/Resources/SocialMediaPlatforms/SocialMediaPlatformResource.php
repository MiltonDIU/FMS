<?php

namespace App\Filament\Resources\SocialMediaPlatforms;

use App\Filament\Resources\SocialMediaPlatforms\Pages\CreateSocialMediaPlatform;
use App\Filament\Resources\SocialMediaPlatforms\Pages\EditSocialMediaPlatform;
use App\Filament\Resources\SocialMediaPlatforms\Pages\ListSocialMediaPlatforms;
use App\Filament\Resources\SocialMediaPlatforms\Schemas\SocialMediaPlatformForm;
use App\Filament\Resources\SocialMediaPlatforms\Tables\SocialMediaPlatformsTable;
use App\Models\SocialMediaPlatform;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SocialMediaPlatformResource extends Resource
{
    protected static ?string $model = SocialMediaPlatform::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SocialMediaPlatformForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialMediaPlatformsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialMediaPlatforms::route('/'),
            'create' => CreateSocialMediaPlatform::route('/create'),
            'edit' => EditSocialMediaPlatform::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
