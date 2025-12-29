<?php

namespace App\Filament\Resources\NotificationRoutings\Schemas;

use App\Models\ApprovalSetting;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class NotificationRoutingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('trigger_type')
                    ->label('Trigger Event')
                    ->options([
                        'teacher_profile_update' => 'Teacher Profile Update',
                    ])
                    ->default('teacher_profile_update')
                    ->required(),
                    
                Select::make('trigger_sections')
                    ->label('Trigger Sections')
                    ->helperText('Select sections that trigger this notification. Leave empty for all approval-required sections.')
                    ->options(function () {
                        // Only show sections that require approval
                        return ApprovalSetting::where('is_active', true)
                            ->where('requires_approval', true)
                            ->orderBy('sort_order')
                            ->pluck('section_label', 'section_key');
                    })
                    ->multiple()
                    ->searchable()
                    ->placeholder('All approval sections'),
                    
                Select::make('recipient_type')
                    ->label('Recipient Type')
                    ->options([
                        'role' => 'Role',
                        'user' => 'Specific User',
                        'department_head' => 'Department Head (Auto)',
                    ])
                    ->reactive()
                    ->required()
                    ->helperText('Who should receive notifications'),
                    
                Select::make('recipient_identifiers')
                    ->label('Recipients')
                    ->helperText('Select one or more recipients')
                    ->options(function (callable $get) {
                        $type = $get('recipient_type');
                        
                        return match ($type) {
                            'role' => Role::all()->pluck('name', 'name'),
                            'user' => User::where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')']),
                            default => [],
                        };
                    })
                    ->multiple()
                    ->searchable()
                    ->visible(fn (callable $get) => in_array($get('recipient_type'), ['role', 'user']))
                    ->required(fn (callable $get) => in_array($get('recipient_type'), ['role', 'user'])),
                    
                Textarea::make('description')
                    ->label('Description')
                    ->helperText('Brief description of this routing rule')
                    ->rows(2)
                    ->columnSpanFull(),
                    
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
