<?php

namespace App\Filament\Resources\ResearchCollaborations;

use App\Filament\Resources\ResearchCollaborations\Pages\CreateResearchCollaboration;
use App\Filament\Resources\ResearchCollaborations\Pages\EditResearchCollaboration;
use App\Filament\Resources\ResearchCollaborations\Pages\ListResearchCollaborations;
use App\Filament\Resources\ResearchCollaborations\Schemas\ResearchCollaborationForm;
use App\Filament\Resources\ResearchCollaborations\Tables\ResearchCollaborationsTable;
use App\Models\ResearchCollaboration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResearchCollaborationResource extends Resource
{
    protected static ?string $model = ResearchCollaboration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ResearchCollaborationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResearchCollaborationsTable::configure($table);
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
            'index' => ListResearchCollaborations::route('/'),
            'create' => CreateResearchCollaboration::route('/create'),
            'edit' => EditResearchCollaboration::route('/{record}/edit'),
        ];
    }
}
