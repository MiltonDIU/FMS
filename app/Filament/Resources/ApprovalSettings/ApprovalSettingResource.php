<?php

namespace App\Filament\Resources\ApprovalSettings;

use App\Filament\Resources\ApprovalSettings\Pages\CreateApprovalSetting;
use App\Filament\Resources\ApprovalSettings\Pages\EditApprovalSetting;
use App\Filament\Resources\ApprovalSettings\Pages\ListApprovalSettings;
use App\Filament\Resources\ApprovalSettings\Schemas\ApprovalSettingForm;
use App\Filament\Resources\ApprovalSettings\Tables\ApprovalSettingsTable;
use App\Models\ApprovalSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class ApprovalSettingResource extends Resource
{
    protected static ?string $model = ApprovalSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static UnitEnum|string|null $navigationGroup = 'Approvals';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Approval Settings';

    protected static ?string $pluralLabel = 'Approval Settings';

    protected static ?string $modelLabel = 'Approval Setting';

    protected static ?string $recordTitleAttribute = 'section_label';

    public static function form(Schema $schema): Schema
    {
        return ApprovalSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApprovalSettingsTable::configure($table);
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
            'index' => ListApprovalSettings::route('/'),
            'create' => CreateApprovalSetting::route('/create'),
            'edit' => EditApprovalSetting::route('/{record}/edit'),
        ];
    }
}
