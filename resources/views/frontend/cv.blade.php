<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $teacher->full_name }} — CV</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            /* Overridden below when the active theme's font can be embedded in a
               PDF. dompdf only accepts an uploaded .ttf/.otf, never the woff2
               that Google Fonts serves, so this stack is the normal case. */
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            font-size: 10.5px;
            line-height: 1.5;
        }
        .page { padding: 34px 38px; }

        /* ---------- Header ---------- */
        .header {
            display: flex;
            align-items: center;
            gap: 18px;
            border-bottom: 3px solid #034ea2;
            padding-bottom: 16px;
            margin-bottom: 18px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .photo {
            width: 78px; height: 78px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #e5e7eb;
            flex-shrink: 0;
        }
        .photo-fallback {
            width: 78px; height: 78px;
            border-radius: 10px;
            background: #034ea2;
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; font-weight: 700;
            flex-shrink: 0;
        }
        .name { font-size: 23px; font-weight: 700; color: #0b1120; letter-spacing: -0.01em; }
        .title { font-size: 13px; color: #034ea2; font-weight: 600; margin-top: 1px; }
        .org { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .contact { font-size: 9.5px; color: #374151; margin-top: 7px; }
        .contact span { margin-right: 12px; white-space: nowrap; }
        /* Word labels, not pictograms: the embedded theme font is a text face and
           has no envelope or telephone glyph, so dompdf printed "?" for both. */
        .contact .label { color: #6b7280; }

        /* ---------- Two-column body ---------- */
        .cols { display: flex; gap: 22px; align-items: flex-start; }
        .col-main { flex: 1 1 64%; min-width: 0; }
        .col-side { flex: 1 1 36%; min-width: 0; }

        /* Keep a whole section (heading + its content) together so a heading
           never gets orphaned at the bottom of a page with its body on the
           next page. */
        .section { page-break-inside: avoid; break-inside: avoid; }
        /* Never leave a heading dangling at the page bottom. */
        h2 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: #034ea2;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin: 16px 0 9px;
            page-break-after: avoid;
            break-after: avoid;
        }
        .col-side h2:first-child { margin-top: 0; }
        p.summary { color: #374151; text-align: justify; }

        .item { margin-bottom: 10px; page-break-inside: avoid; break-inside: avoid; }
        .item .row1 { font-weight: 600; color: #111827; }
        .item .row2 { color: #6b7280; font-style: italic; font-size: 9.5px; }
        /* Marks a post still held, so a reader does not have to infer it from a
           missing end date. Plain text, since dompdf has no icon font here. */
        .item .row2 .current {
            font-style: normal; font-weight: 700; font-size: 8px;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: #034ea2; margin-left: 6px;
        }
        .item .row3 { color: #374151; }

        ol.pubs { margin: 0; padding-left: 17px; }
        ol.pubs li { margin-bottom: 5px; color: #374151; page-break-inside: avoid; break-inside: avoid; }
        ol.pubs em { color: #111827; }

        .chips span {
            display: inline-block;
            background: #eef2ff; color: #034ea2;
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 2px 9px; margin: 0 5px 5px 0;
            font-size: 9.5px;
        }
        .chips a {
            display: inline-block;
            background: #eef2ff; color: #034ea2;
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 2px 9px; margin: 0 5px 5px 0;
            font-size: 9.5px;
            text-decoration: none;
        }
        .chips a:hover { background: #e0e7ff; }

        .side-block { page-break-inside: auto; break-inside: auto; }
        .side-block + .side-block { margin-top: 4px; }

        .footer {
            margin-top: 22px; padding-top: 9px;
            border-top: 1px solid #e5e7eb;
            font-size: 8.5px; color: #9ca3af;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Faint diagonal watermark printed on every page when enabled.
           position: fixed makes DomPDF repeat it on each page. */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            transform-origin: center center;
            font-size: 90px;
            font-weight: 800;
            letter-spacing: 6px;
            color: rgba(3, 78, 162, 0.07);
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
            z-index: 0;
        }
        .page > *:not(.watermark) { position: relative; z-index: 1; }
    </style>

    {{-- Applies the active theme's fonts when they are embeddable; emits nothing
         otherwise, leaving the stack above in place. Must follow the styles it
         overrides. --}}
    {!! \App\Helpers\FontManager::pdfCssBlock() !!}
</head>
<body>
<div class="page">

    @if(\App\Helpers\CvSections::watermarkEnabled())
        <div class="watermark">{{ \App\Helpers\CvSections::watermarkText() }}</div>
    @endif

    <div class="header">
        @if(\App\Helpers\CvSections::enabled('basic_info'))
            {{-- Was resolving a bare filename to public_path('storage/…'), but the
                 photographs live on the legacy host, so that path never existed.
                 photo_url gives the address they are actually served from, and
                 the controller enables remote images for dompdf. --}}
            @php
                // dompdf runs with isRemoteEnabled, so it is the server that
                // fetches this. serverFetchablePhotoUrl() refuses addresses that
                // resolve inside the network; see the accessor for why.
                $cvPhotoUrl = $teacher->serverFetchablePhotoUrl();
            @endphp
            @if($cvPhotoUrl)
                <img class="photo" src="{{ $cvPhotoUrl }}">
            @else
                <div class="photo-fallback">{{ strtoupper(substr($teacher->first_name ?? '?', 0, 1)) }}</div>
            @endif
            <div>
                <div class="name">{{ $teacher->full_name }}</div>
                @if($teacher->designation?->name)
                    <div class="title">{{ $teacher->designation->name }}</div>
                @endif
                @if($teacher->department?->faculty?->name || $teacher->department?->name)
                    <div class="org">
                        {{ $teacher->department?->faculty?->name ?? $brand['site_name'] }}
                        @if($teacher->department?->name) &middot; {{ $teacher->department->name }} @endif
                    </div>
                @endif
                <div class="contact">
                    @if($teacher->user?->email || $teacher->secondary_email)
                        <span><span class="label">Email:</span> {{ $teacher->user?->email ?? $teacher->secondary_email }}</span>
                    @endif
                    @if($teacher->phone || $teacher->personal_phone)
                        <span><span class="label">Phone:</span> {{ $teacher->phone ?? $teacher->personal_phone }}</span>
                    @endif
                    @if($teacher->office_room)
                        <span>Room: {{ $teacher->office_room }}</span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="cols">
        {{-- MAIN COLUMN --}}
        <div class="col-main">

            @if(\App\Helpers\CvSections::enabled('profile') && ($teacher->bio || $teacher->researchInterests->isNotEmpty()))
                <div class="section">
                    <h2>Profile</h2>
                    @if($teacher->bio)<p class="summary">{{ strip_tags($teacher->bio) }}</p>@endif
                    @if($teacher->researchInterests->isNotEmpty())
                        <p class="summary" style="margin-top:6px;"><strong>Research Interests:</strong> {{ implode(', ', $teacher->researchInterestNames()) }}</p>
                    @endif
                </div>
            @endif

            @if(\App\Helpers\CvSections::enabled('experience') && $teacher->jobExperiences->isNotEmpty())
                <div class="section">
                    <h2>Experience</h2>
                    @foreach($teacher->jobExperiences as $exp)
                        @php
                            /*
                             * The column is `position`; `designation` does not exist on
                             * this table, so every line printed the literal fallback
                             * "Role". positionRelation is the lookup-table copy, used
                             * when the free-text field is blank.
                             */
                            $role = $exp->position ?: optional($exp->positionRelation)->name;
                            $org = $exp->organization ?: optional($exp->organizationRelation)->name;

                            $from = $exp->start_date?->format('M Y');
                            $to = $exp->end_date?->format('M Y');

                            /*
                             * Say which posts are still held. Printing "Present" whenever
                             * end_date happened to be empty claimed a great many finished
                             * jobs were current — is_current is the field that actually
                             * knows, and a start date with no end date only tells us when
                             * it began.
                             */
                            $period = match (true) {
                                $exp->is_current && $from => $from . ' – Present',
                                $exp->is_current => 'Present',
                                (bool) ($from && $to) => $from . ' – ' . $to,
                                (bool) $from => $from,
                                (bool) $to => 'Until ' . $to,
                                default => null,
                            };
                        @endphp
                        <div class="item">
                            <div class="row1">{{ $role ?: 'Position not recorded' }}@if($org) — {{ $org }}@endif</div>
                            @if($period)
                                <div class="row2">
                                    {{ $period }}
                                    @if($exp->is_current)<span class="current">Current</span>@endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if(\App\Helpers\CvSections::enabled('publications') && $teacher->publications->isNotEmpty())
                <h2>Publications ({{ $teacher->publications->count() }})</h2>
                <ol class="pubs">
                    @foreach($teacher->publications as $pub)
                        <li>
                            {{ $pub->title ?? '' }}
                            @if($pub->journal_name) <em>({{ $pub->journal_name }})</em>@endif
                            {{-- The column is publication_year; `year` does not exist,
                                 so no publication ever carried a date here. --}}
                            @if($pub->publication_year) &middot; {{ $pub->publication_year }}@endif
                        </li>
                    @endforeach
                </ol>
            @endif

            @if(\App\Helpers\CvSections::enabled('teaching_areas') && $teacher->teachingAreas->isNotEmpty())
                <div class="section">
                    <h2>Teaching Areas</h2>
                    <div class="chips">
                        @foreach($teacher->teachingAreas as $area)
                            <span>{{ $area->area ?? 'Area' }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        {{-- SIDE COLUMN --}}
        <div class="col-side">

            @if(\App\Helpers\CvSections::enabled('education') && $teacher->educations->isNotEmpty())
                <div class="side-block">
                    <h2>Education</h2>
                    @foreach($teacher->educations as $edu)
                        @php
                            /*
                             * There is no `result` column and the relation is
                             * resultType(), not result_type — both resolved to null, so
                             * this line never printed for anyone. The grade actually
                             * lives in cgpa/scale, grade or marks.
                             */
                            $resultValue = match (true) {
                                filled($edu->cgpa) && filled($edu->scale) => $edu->cgpa . ' / ' . $edu->scale,
                                filled($edu->cgpa) => (string) $edu->cgpa,
                                filled($edu->grade) => (string) $edu->grade,
                                filled($edu->marks) => (string) $edu->marks,
                                default => null,
                            };
                            $resultLabel = optional($edu->resultType)->name ?: 'Result';
                            $institution = $edu->institution ?: optional($edu->educationalInstitution)->name;
                        @endphp
                        <div class="item">
                            <div class="row1">{{ $edu->degreeType?->name ?? 'Degree' }}@if($edu->degreeLevel?->name) ({{ $edu->degreeLevel->name }})@endif</div>
                            @if($institution || $edu->passing_year)
                                <div class="row2">{{ $institution }}@if($edu->passing_year) &middot; {{ $edu->passing_year }}@endif</div>
                            @endif
                            @if($resultValue)
                                <div class="row3" style="font-size:9px;color:#6b7280;">{{ $resultLabel }}: {{ $resultValue }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if(\App\Helpers\CvSections::enabled('skills') && $teacher->skills->isNotEmpty())
                <div class="side-block">
                    <h2>Skills</h2>
                    <div class="chips">
                        @foreach($teacher->skills as $skill)
                            <span>{{ $skill->name ?? 'Skill' }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(\App\Helpers\CvSections::enabled('memberships') && $teacher->memberships->isNotEmpty())
                <div class="side-block">
                    <h2>Memberships</h2>
                    @foreach($teacher->memberships as $mem)
                        <div class="item">
                            <div class="row1">
                                {{ $mem->membershipType?->name ?? 'Membership' }}
                                @if($mem->membershipOrganization?->name)
                                    &middot; {{ $mem->membershipOrganization->name }}
                                @endif
                            </div>
                            @if($mem->position || $mem->scope || $mem->start_date || $mem->end_date)
                                <div class="row2">
                                    @if($mem->position){{ $mem->position }}@endif
                                    @if($mem->scope)&middot; {{ ucfirst($mem->scope) }}@endif
                                    @php
                                        // Same rule as Experience: a missing end date is
                                        // not evidence the membership is still held.
                                        $from = $mem->start_date?->format('Y');
                                        $to = $mem->end_date?->format('Y');
                                        $span = match (true) {
                                            $mem->is_active && $from => $from . ' – Present',
                                            (bool) ($from && $to) => $from . ' – ' . $to,
                                            (bool) $from => $from,
                                            (bool) $to => 'Until ' . $to,
                                            default => null,
                                        };
                                    @endphp
                                    @if($span)&middot; {{ $span }}@endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if(\App\Helpers\CvSections::enabled('awards') && $teacher->awards->isNotEmpty())
                <div class="side-block">
                    <h2>Awards & Honors</h2>
                    @foreach($teacher->awards as $award)
                        {{-- The column is awarding_body; `organization` does not exist. --}}
                        @php $body = $award->awarding_body ?: optional($award->awardingBodyOrganizationRelation)->name; @endphp
                        <div class="item">
                            <div class="row1">{{ $award->title ?? 'Award' }}</div>
                            @if($body || $award->year)
                                <div class="row2">{{ $body }}@if($award->year) &middot; {{ $award->year }}@endif</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if(\App\Helpers\CvSections::enabled('certifications') && $teacher->certifications->isNotEmpty())
                <div class="side-block">
                    <h2>Certifications</h2>
                    @foreach($teacher->certifications as $cert)
                        {{-- The columns are title and issuing_authority; `name` and
                             `organization` do not exist on this table. --}}
                        @php $issuer = $cert->issuing_authority ?: optional($cert->issuingAuthorityOrganizationRelation)->name; @endphp
                        <div class="item">
                            <div class="row1">{{ $cert->title ?? 'Certification' }}</div>
                            @if($issuer)<div class="row2">{{ $issuer }}</div>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if(\App\Helpers\CvSections::enabled('links') && $teacher->socialLinks->isNotEmpty())
                <div class="side-block">
                    <h2>Links</h2>
                    <div class="chips">
                        @foreach($teacher->socialLinks as $link)
                            @if($link->url)
                                <a href="{{ $link->url }}" style="text-decoration:none; color:inherit;">{{ $link->platform?->name ?? 'Link' }}</a>
                            @else
                                <span>{{ $link->platform?->name ?? 'Link' }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

    <div class="footer">
        Generated from {{ $brand['site_name'] ?? 'Faculty Directory' }} &middot; {{ $brand['meta_title_suffix'] ?? '' }}
    </div>

</div>
</body>
</html>
