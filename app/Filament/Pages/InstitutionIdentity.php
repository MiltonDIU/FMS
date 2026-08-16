<?php

namespace App\Filament\Pages;

use App\Helpers\Institution;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * What the Scopus matcher believes about the world.
 *
 * These are the words a run is judged against: whether an affiliation line is
 * ours, which institutions carry the name without being us, and how a student's
 * address is told from a member of staff's. Getting them wrong does not throw
 * an error — the run simply recognises nobody, which is far worse.
 *
 * It lives here rather than in System Settings on purpose. Everything on this
 * page affects exactly one thing, the Scopus review, and the people who should
 * be setting it are the people who run those reviews — not everybody who
 * happens to need access to mail configuration or theme colours. So it sits
 * beside [[ScopusReview]] in the same navigation group, behind the same test.
 *
 * Nothing here is stored as a seeded row. Every value falls back to the
 * Daffodil defaults in [[Institution]], so an install that never opens this
 * page matches exactly as it did before the page existed.
 */
class InstitutionIdentity extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static UnitEnum|string|null $navigationGroup = 'Publication Management';

    protected static ?string $navigationLabel = 'Institution Identity';

    protected static ?string $title = 'Institution Identity';

    protected static ?string $slug = 'institution-identity';

    protected string $view = 'filament.pages.institution-identity';

    // Immediately after Scopus Review, which is the only thing it affects.
    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    /**
     * The same test the Scopus review itself applies.
     *
     * Deliberately not the System Settings permission. Somebody who can upload
     * an export already decides, run by run, what the matcher is allowed to
     * conclude — the switches on the upload form do exactly that. Setting the
     * words those switches operate on is the same job, so it asks for the same
     * thing rather than inventing a permission nobody has been granted yet.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin')
            || ($user->can('ViewAny:Publication') && $user->can('Create:Publication'));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        /*
         * Filled with what is actually in force, not with what has been saved.
         *
         * Institution::all supplies the built-in default for anything never
         * written, which matters more here than on a normal settings form: an
         * admin who cannot see the pattern a run is being matched by has no way
         * to tell a working configuration from an empty one.
         */
        $values = [];

        foreach (Institution::all() as $bare => $value) {
            $values[Institution::PREFIX . $bare] = $value;
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Recognising our own affiliation')
                    ->description('How the Scopus review decides that an affiliation line is this institution\'s. Leave a field empty to fall back to its built-in default.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('institution_name')
                            ->label('Institution name')
                            ->placeholder(Institution::DEFAULTS['institution_name'])
                            ->helperText('Used to build a matching pattern when none is given below. Defaults to the branding site name.'),

                        TextInput::make('institution_short_name')
                            ->label('Short name for headings')
                            ->placeholder(Institution::DEFAULTS['institution_short_name'])
                            ->helperText('Appears as "287 <short name> authors" on the review page and in the workbook.'),

                        TagsInput::make('institution_match_patterns')
                            ->label('Affiliation patterns')
                            ->placeholder('Add a pattern')
                            ->columnSpanFull()
                            ->helperText(fn (): HtmlString => new HtmlString(
                                'Regular expressions, matched case-insensitively against one affiliation segment. '
                                . 'Empty means one is derived from the name above, which tolerates misspelt words but not a different word order '
                                . '("Dhaka University" for "University of Dhaka") — add those here. '
                                . 'Derived from the name currently saved: <code>'
                                . e(implode(' , ', Institution::patternsFor(Institution::name())))
                                . '</code>'
                            )),

                        TagsInput::make('institution_not_us')
                            ->label('Carries our name but is not us')
                            ->placeholder('Add an institution')
                            ->columnSpanFull()
                            ->helperText('Matched as plain text, case-insensitively. Whether these count towards our output is a per-run switch on the upload form; this is the list that switch controls.'),
                    ]),

                Section::make('Addresses')
                    ->description('Used to tell a student\'s address from a member of staff\'s, which the review reports as a suggestion only.')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TagsInput::make('institution_email_domains')
                            ->label('Our email domains')
                            ->placeholder('Add a domain')
                            ->helperText('e.g. diu.edu.bd. Subdomains count. The student rule is only applied to these; empty means apply it to every address.'),

                        TextInput::make('institution_student_email_pattern')
                            ->label('Student address rule')
                            ->placeholder(Institution::DEFAULTS['institution_student_email_pattern'])
                            ->helperText(new HtmlString(
                                'A regular expression tested against the part before the @. DIU\'s <code>\d</code> catches admission numbers such as '
                                . '<code>murshid15-6122@diu.edu.bd</code> while leaving <code>kabir.cse@diu.edu.bd</code> alone. '
                                . 'Empty restores the default; to stop a run using the rule at all, turn the switch off on the upload form.'
                            )),
                    ]),

                Section::make('Faculty and department wording')
                    ->description('Scopus wording that resembles nothing in our own tables, mapped by hand. New spellings arrive with every export; adding a row here is the intended way to deal with them.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Repeater::make('institution_unit_aliases')
                            ->label('Unit aliases')
                            ->addActionLabel('Add wording')
                            ->columns(3)
                            ->schema([
                                TextInput::make('unit')
                                    ->label('As Scopus writes it')
                                    ->helperText('Lower case, matched whole.')
                                    ->required(),
                                TextInput::make('faculty')
                                    ->label('Faculty short name')
                                    ->helperText('e.g. FBE')
                                    ->required(),
                                TextInput::make('department')
                                    ->label('Department code')
                                    ->helperText('Leave empty to let the reviewer decide.'),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        // Public Livewire methods are reachable from the browser without going
        // through the form, so the check lives here rather than only on mount.
        abort_unless(static::canAccess(), 403);

        foreach ($this->form->getState() as $key => $value) {
            // Nothing outside this page's own prefix, whatever arrives in the
            // request: this form has no business writing another tab's keys.
            if (! str_starts_with($key, Institution::PREFIX)) {
                continue;
            }

            Setting::set($key, $value);
        }

        /*
         * Setting::get caches for an hour, and Setting::set only forgets the
         * key it wrote. That is enough on its own, but the derived pattern in
         * the helper text above is computed from the name, so the form has to
         * be refilled or the page keeps showing the old one back at you.
         */
        foreach (array_keys(Institution::DEFAULTS) as $key) {
            Cache::forget("setting.{$key}");
        }

        $this->mount();

        Notification::make()
            ->success()
            ->title('Institution identity saved')
            ->body('The next Scopus run will be matched on these. Runs already analysed are unchanged — use "Run again" on one to see the difference.')
            ->send();
    }

    /** @return array<int, Action> */
    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }
}
