<?php

namespace App\Livewire;

use App\Models\Teacher;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class TeacherPublicationsTab extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?Teacher $record = null;
    public ?array $data = [];

    public function mount(?Teacher $record = null): void
    {
        $this->record = $record;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model($this->record)
            ->components([
Repeater::make('publications')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->schema([
                                        \Filament\Schemas\Components\Group::make()
                                            ->schema([
                                                \Filament\Schemas\Components\Section::make('Publication Details')
                                                    ->schema([
                                                        Select::make('faculty_id')
                                                            ->label('Faculty')
                                                            ->relationship('faculty', 'name')
                                                            ->searchable()
                                                            ->preload()
                                                            ->live()
                                                            ->afterStateUpdated(fn (callable $set) => $set('department_id', null)),
                                                        Select::make('department_id')
                                                            ->label('Department')
                                                            ->relationship('department', 'name', modifyQueryUsing: function ($query, callable $get) {
                                                                $facultyId = $get('faculty_id');
                                                                if (!$facultyId) {
                                                                    return $query;
                                                                }
                                                                return $query->where('faculty_id', $facultyId);
                                                            })
                                                            ->searchable()
                                                            ->preload(),
                                                        Select::make('publication_type_id')
                                                            ->relationship('type', 'name')
                                                            ->required(),
                                                        Select::make('publication_linkage_id')
                                                            ->relationship('linkage', 'name')
                                                            ->required(),
                                                        Select::make('publication_quartile_id')
                                                            ->relationship('quartile', 'name'),
                                                        Select::make('grant_type_id')
                                                            ->relationship('grant', 'name'),
                                                        Select::make('research_collaboration_id')
                                                            ->relationship('collaboration', 'name'),
                                                    ])->columns(3)->collapsible(),
                                                \Filament\Schemas\Components\Section::make('Core Information')
                                                    ->schema([
                                                        TextInput::make('title')
                                                            ->required()
                                                            ->columnSpanFull(),
                                                        Textarea::make('abstract')
                                                            ->columnSpanFull(),
                                                        TextInput::make('research_area')
                                                            ->columnSpanFull(),
                                                        Textarea::make('keywords')
                                                            ->columnSpanFull(),
                                                    ])->collapsible(),
                                            ])
                                            ->columnSpan(1),
                                        \Filament\Schemas\Components\Group::make()
                                            ->schema([
                                                \Filament\Schemas\Components\Section::make('Journal / Conference')
                                                    ->schema([
                                                        TextInput::make('journal_name'),
                                                        TextInput::make('journal_link')->url(),
                                                        DatePicker::make('publication_date'),
                                                        TextInput::make('publication_year')->numeric(),
                                                    ])->columns(2)->collapsible(),

                                                \Filament\Schemas\Components\Section::make('Authorship')
                                                    ->schema([
                                                        Select::make('first_author_id')
                                                            ->label('First Author')
                                                            ->searchable()
                                                            ->options(fn () => \App\Models\Teacher::query()->orderBy('sort_order')->limit(10)->get()->pluck('full_name', 'id'))
                                                            ->getSearchResultsUsing(fn (string $search) => \App\Models\Teacher::query()
                                                                ->where('first_name', 'like', "%{$search}%")
                                                                ->orWhere('middle_name', 'like', "%{$search}%")
                                                                ->orWhere('last_name', 'like', "%{$search}%")
                                                                ->orWhere('employee_id', 'like', "%{$search}%")
                                                                ->limit(20)
                                                                ->get()
                                                                ->pluck('full_name', 'id')
                                                            )
                                                            ->getOptionLabelUsing(fn ($value) => \App\Models\Teacher::find($value)?->full_name)
                                                            ->afterStateHydrated(fn ($component, $record) => $record && $component->state($record->teachers()->wherePivot('author_role', 'first')->first()?->id)),

                                                        Select::make('corresponding_author_id')
                                                            ->label('Corresponding Author')
                                                            ->searchable()
                                                            ->options(fn () => \App\Models\Teacher::query()->orderBy('sort_order')->limit(10)->get()->pluck('full_name', 'id'))
                                                            ->getSearchResultsUsing(fn (string $search) => \App\Models\Teacher::query()
                                                                ->where('first_name', 'like', "%{$search}%")
                                                                ->orWhere('middle_name', 'like', "%{$search}%")
                                                                ->orWhere('last_name', 'like', "%{$search}%")
                                                                ->orWhere('employee_id', 'like', "%{$search}%")
                                                                ->limit(20)
                                                                ->get()
                                                                ->pluck('full_name', 'id')
                                                            )
                                                            ->getOptionLabelUsing(fn ($value) => \App\Models\Teacher::find($value)?->full_name)
                                                            ->afterStateHydrated(fn ($component, $record) => $record && $component->state($record->teachers()->wherePivot('author_role', 'corresponding')->first()?->id)),

                                                        Select::make('co_author_ids')
                                                            ->label('Co-Authors')
                                                            ->multiple()
                                                            ->searchable()
                                                            ->options(fn () => \App\Models\Teacher::query()->orderBy('sort_order')->limit(10)->get()->pluck('full_name', 'id'))
                                                            ->getSearchResultsUsing(fn (string $search) => \App\Models\Teacher::query()
                                                                ->where('first_name', 'like', "%{$search}%")
                                                                ->orWhere('middle_name', 'like', "%{$search}%")
                                                                ->orWhere('last_name', 'like', "%{$search}%")
                                                                ->orWhere('employee_id', 'like', "%{$search}%")
                                                                ->limit(20)
                                                                ->get()
                                                                ->pluck('full_name', 'id')
                                                            )
                                                            ->getOptionLabelsUsing(fn (array $values) => \App\Models\Teacher::whereIn('id', $values)->get()->pluck('full_name', 'id')->toArray())
                                                            ->afterStateHydrated(fn ($component, $record) => $record && $component->state($record->teachers()->wherePivot('author_role', 'co_author')->orderByPivot('sort_order')->pluck('teachers.id')->toArray())),
                                                    ])->columns(3),

                                                \Filament\Schemas\Components\Section::make('Metrics')
                                                    ->schema([
                                                        TextInput::make('h_index'),
                                                        TextInput::make('citescore')->numeric(),
                                                        TextInput::make('impact_factor')->numeric(),
                                                    ])->columns(3)->collapsible(),
                                                 \Filament\Schemas\Components\Section::make('Status & Flags')
                                                    ->schema([
                                                        Toggle::make('student_involvement'),
                                                        Toggle::make('is_featured')
                                                            ->disabled(function () {
                                                                $user = auth()->user();
                                                                if (!$user) return true;
                                                                if ($user->hasRole(['super_admin', 'admin', 'registrar', 'dean', 'head', 'research_team'])) return false;
                                                                if ($user->administrativeRoles()->where('administrative_role_user.is_active', true)->exists()) return false;
                                                                return $user->hasRole('teacher') || $user->isTeacher();
                                                            })
                                                            ->helperText(function () {
                                                                $user = auth()->user();
                                                                if (!$user) return null;
                                                                $isTeacherOnly = ($user->hasRole('teacher') || $user->isTeacher())
                                                                    && !$user->hasRole(['super_admin', 'admin', 'registrar', 'dean', 'head', 'research_team'])
                                                                    && !$user->administrativeRoles()->where('administrative_role_user.is_active', true)->exists();
                                                                return $isTeacherOnly ? 'Featured status can only be set by administrators or role managers.' : null;
                                                            }),
                                                        // Status is set automatically based on approval settings
                                                    ])->columns(2)->collapsible(),
                                            ])
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->reorderable()
                                    ->orderColumn('publications.sort_order')
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        // Delete removed items - use table qualified ID for MorphToMany
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->publications()->whereNotIn('publications.id', $existingIds)->delete();

                                        $sortOrder = 0;
                                        foreach ($state ?? [] as $item) {
                                            // Determine status based on approval settings
                                            $requiresApproval = \App\Models\ApprovalSetting::requiresApproval('publication');
                                            $status = $requiresApproval ? 'pending' : 'approved';

                                            $deptId = $item['department_id'] ?? $record->department_id;
                                            $facultyId = $item['faculty_id'] ?? ($deptId ? \App\Models\Department::find($deptId)?->faculty_id : $record->department?->faculty_id);

                                            $user = auth()->user();
                                            $canManageFeatured = $user && (
                                                $user->hasRole(['super_admin', 'admin', 'registrar', 'dean', 'head', 'research_team']) ||
                                                $user->administrativeRoles()->where('administrative_role_user.is_active', true)->exists()
                                            );

                                            $publication = isset($item['id']) ? \App\Models\Publication::find($item['id']) : null;
                                            $isFeatured = $canManageFeatured
                                                ? ($item['is_featured'] ?? false)
                                                : ($publication ? $publication->is_featured : false);

                                            $data = [
                                                'faculty_id' => $facultyId,
                                                'department_id' => $deptId,
                                                'publication_type_id' => $item['publication_type_id'],
                                                'publication_linkage_id' => $item['publication_linkage_id'],
                                                'publication_quartile_id' => $item['publication_quartile_id'] ?? null,
                                                'grant_type_id' => $item['grant_type_id'] ?? null,
                                                'research_collaboration_id' => $item['research_collaboration_id'] ?? null,
                                                'title' => $item['title'],
                                                'abstract' => $item['abstract'] ?? null,
                                                'research_area' => $item['research_area'] ?? null,
                                                'keywords' => $item['keywords'] ?? null,
                                                'journal_name' => $item['journal_name'] ?? null,
                                                'journal_link' => $item['journal_link'] ?? null,
                                                'publication_date' => $item['publication_date'] ?? null,
                                                'publication_year' => $item['publication_year'] ?? null,
                                                'h_index' => $item['h_index'] ?? null,
                                                'citescore' => $item['citescore'] ?? null,
                                                'impact_factor' => $item['impact_factor'] ?? null,
                                                'student_involvement' => $item['student_involvement'] ?? false,
                                                'is_featured' => $isFeatured,
                                                'sort_order' => $sortOrder++,
                                            ];

                                            $publication = null;

                                            if (isset($item['id'])) {
                                                // Update
                                                $publication = \App\Models\Publication::find($item['id']);
                                                if ($publication) {
                                                    $publication->update($data);
                                                }
                                            } else {
                                                // Create
                                                $data['status'] = $requiresApproval ? 'pending' : 'approved';
                                                $publication = $record->publications()->create($data);
                                            }

                                            // Handle Authorship Sync
                                            if ($publication) {
                                                $syncData = [];

                                                // First Author
                                                if (!empty($item['first_author_id'])) {
                                                    $syncData[$item['first_author_id']] = ['author_role' => 'first', 'sort_order' => 1];
                                                }

                                                // Corresponding Author
                                                if (!empty($item['corresponding_author_id'])) {
                                                    $existing = $syncData[$item['corresponding_author_id']] ?? [];
                                                    $syncData[$item['corresponding_author_id']] = array_merge($existing, ['author_role' => 'corresponding', 'sort_order' => 2]);
                                                }

                                                // Co-Authors
                                                if (!empty($item['co_author_ids']) && is_array($item['co_author_ids'])) {
                                                    foreach ($item['co_author_ids'] as $index => $coAuthorId) {
                                                        if (!isset($syncData[$coAuthorId])) {
                                                            $syncData[$coAuthorId] = ['author_role' => 'co_author', 'sort_order' => 3 + $index];
                                                        }
                                                    }
                                                }

                                                // Sync teachers
                                                if (!empty($syncData)) {
                                                    $publication->teachers()->sync($syncData);
                                                }
                                            }
                                        }
                                    }),

            ]);
    }

    public function save(): void
    {
        $this->form->getState();
        $this->form->saveRelationships();

        Notification::make()
            ->title('Publications saved successfully')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.teacher-publications-tab');
    }
}