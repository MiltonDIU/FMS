<x-filament-panels::page>
    <div class="space-y-6">
        {{--
            Said plainly and up front, because the whole point of this page is
            that it is safe to run: it reads, it reports, it changes nothing.
        --}}
        <x-filament::section icon="heroicon-o-information-circle">
            <x-slot name="heading">Nothing here changes the system</x-slot>

            <div class="space-y-3 text-sm">
                <p>
                    Upload a Scopus export and you get back a workbook to check. It says which of the
                    publications we already hold, which are new, and who each Daffodil-affiliated author
                    is here &mdash; a teacher, an external author, or somebody we have never seen.
                </p>

                <p>
                    Every suggestion is a suggestion. A match on an email address is reliable; a match on a
                    name is not, and the workbook marks which is which so you know where to look hardest.
                    Applying the decisions you make in it is a separate step.
                </p>

                <p class="text-gray-500 dark:text-gray-400">
                    The file needs at least <strong>{{ implode('</strong> and <strong>', $this->requiredColumns()) }}</strong>.
                    An export that also carries <strong>Author(s) ID</strong>, <strong>DOI</strong> and
                    <strong>EID</strong> matches far better &mdash; ask the Directorate of Research for the
                    full-field export rather than the trimmed one.
                </p>

                {{--
                    Upload the raw export, not the workbook this page produces.
                    Doing that is an easy mistake — the workbook is the thing you
                    have just been looking at — and the error it causes explains
                    nothing on its own.
                --}}
                <p class="text-gray-500 dark:text-gray-400">
                    Upload the export <em>from Scopus</em>. The workbook this page produces is not a valid
                    input: its columns are named differently, and it will be refused.
                </p>

                @if (\App\Filament\Pages\ScopusReview::uploadCeilingInKilobytes() < 6144)
                    {{--
                        A stock php.ini stops at 2 MB and one term's export is
                        4.7 MB, so the upload fails with nothing but "failed to
                        upload" in the browser. Said here rather than discovered.
                    --}}
                    <p class="rounded-md bg-amber-50 p-3 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                        <strong>This server accepts uploads up to
                        {{ \App\Filament\Pages\ScopusReview::uploadCeilingLabel() }}.</strong>
                        A full Scopus export runs to several megabytes, so it will be refused before it
                        reaches this page. Raise <code>upload_max_filesize</code> and
                        <code>post_max_size</code> in php.ini, then restart PHP.
                    </p>
                @endif
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
