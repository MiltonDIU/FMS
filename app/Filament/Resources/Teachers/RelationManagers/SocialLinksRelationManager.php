<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SocialLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'socialLinks';

    protected static ?string $recordTitleAttribute = 'platform';

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                \Filament\Forms\Components\Select::make('social_media_platform_id')
                    ->label('Platform')
                    ->relationship('platform', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order')) // Use explicit Builder class
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $username = $get('username');
                        if ($state && $username) {
                            $platform = \App\Models\SocialMediaPlatform::find($state);
                            if ($platform && $platform->base_url) {
                                $set('url', rtrim($platform->base_url, '/') . '/' . ltrim($username, '/'));
                            }
                        }
                    })
                    ->rules([
                        function ($livewire, $record) {
                            return function (string $attribute, $value, \Closure $fail) use ($livewire, $record) {
                                $platform = \App\Models\SocialMediaPlatform::find($value);
                                if (!$platform || $platform->allow_multiple) return;

                                $exists = $livewire->getOwnerRecord()->socialLinks()
                                    ->where('social_media_platform_id', $value)
                                    ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                    ->exists();

                                if ($exists) {
                                    $fail("The {$platform->name} platform allows only one link.");
                                }
                            };
                        }
                    ]),

                TextInput::make('username')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $platformId = $get('social_media_platform_id');
                        if ($platformId && $state) {
                            $platform = \App\Models\SocialMediaPlatform::find($platformId);
                            if ($platform && $platform->base_url) {
                                $set('url', rtrim($platform->base_url, '/') . '/' . ltrim($state, '/'));
                            }
                        }
                    }),

                TextInput::make('url')
                    ->url()
                    ->required()
                    ->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title') // platform.name usually
            ->columns([
                Tables\Columns\TextColumn::make('platform.name')->label('Platform')->sortable(),
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('url')->limit(30),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
