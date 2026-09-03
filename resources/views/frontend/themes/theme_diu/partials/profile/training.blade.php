<!-- Training Tab -->
<div x-show="tab === 'training'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        Special Training &amp; Pedagogy Programs
    </h3>
    @if($teacher->trainingExperiences->isEmpty())
        <p class="text-xs text-slate-400">No training experiences recorded.</p>
    @else
        {{-- By year, newest first — see publications for why one sortByDesc
             before the groupBy is all it takes. --}}
        @php
            $years = $teacher->trainingExperiences
                ->sortByDesc(fn ($trn) => (int) $trn->year)
                ->groupBy(fn ($trn) => $trn->year ?: '—');
        @endphp

        <div class="space-y-6">
          @foreach($years as $year => $rows)
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <span class="text-[11px] font-sans font-black text-diu-primary tracking-wider tabular-nums">{{ $year }}</span>
                    <span class="h-px flex-1 bg-slate-200"></span>
                    <span class="text-[10px] font-sans font-bold text-slate-400 tabular-nums">{{ $rows->count() }}</span>
                </div>
            @foreach($rows as $trn)
                <div class="p-4 rounded-xl border border-slate-200  flex gap-3 ring-1 ring-slate-900/5">
                    <div class="bg-diu-accent/15 text-diu-accent p-2 rounded-lg shrink-0 h-9 w-9 flex items-center justify-center">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 leading-snug font-display">{{ $trn->title }}</h4>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ $trn->organization ?? optional($trn->organizationRelation)->name ?? '' }}</p>
                        @php
                            $facts = array_filter([
                                $trn->category,
                                $trn->duration_days ? $trn->duration_days . ' days' : null,
                                $trn->is_online ? 'Online' : null,
                                $trn->country,
                            ], 'filled');
                        @endphp
                        @if($facts)
                            <div class="flex items-center gap-4 mt-2 text-[10px] text-slate-400 font-bold uppercase">
                                <span>{{ implode(' · ', $facts) }}</span>
                            </div>
                        @endif
                        @if($trn->description)
                            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">{{ $trn->description }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>
          @endforeach
        </div>
    @endif

    {{-- The credentials that came out of that training. Shown here and, until
         Aurora, nowhere on the site at all — the profile controller has been
         eager-loading them the whole time. --}}
    @if($teacher->certifications->isNotEmpty())
        <div class="pt-2">
            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/><circle cx="12" cy="8" r="6"/></svg>
                Certifications
            </h3>

            @php
                $certYear = fn ($cert) => $cert->issue_date
                    ? \Illuminate\Support\Carbon::parse($cert->issue_date)->format('Y')
                    : null;

                $certYears = $teacher->certifications
                    ->sortByDesc(fn ($cert) => (int) $certYear($cert))
                    ->groupBy(fn ($cert) => $certYear($cert) ?: '—');
            @endphp

            <div class="space-y-6">
              @foreach($certYears as $year => $rows)
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="text-[11px] font-sans font-black text-diu-primary tracking-wider tabular-nums">{{ $year }}</span>
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span class="text-[10px] font-sans font-bold text-slate-400 tabular-nums">{{ $rows->count() }}</span>
                    </div>
                @foreach($rows as $cert)
                    <div class="p-4 rounded-xl border border-slate-200 flex gap-3 ring-1 ring-slate-900/5">
                        <div class="bg-diu-accent/15 text-diu-accent p-2 rounded-lg shrink-0 h-9 w-9 flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/><circle cx="12" cy="8" r="6"/></svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-slate-800 leading-snug font-display">{{ $cert->title }}</h4>

                            {{-- The column, not the organisation relation the CV builder
                                 reaches for: issuing_authority is not nullable, and the
                                 profile controller does not eager-load that relation, so
                                 asking for it here would be a query per certification for
                                 a fallback that can never fire. --}}
                            @if($cert->issuing_authority)
                                <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ $cert->issuing_authority }}</p>
                            @endif

                            @php
                                $certFacts = array_filter([
                                    $cert->type,
                                    $cert->credential_id ? 'ID ' . $cert->credential_id : null,
                                    $cert->expiry_date
                                        ? 'Expires ' . \Illuminate\Support\Carbon::parse($cert->expiry_date)->format('M Y')
                                        : null,
                                ], 'filled');
                            @endphp

                            @if($certFacts)
                                <p class="mt-2 text-[10px] text-slate-400 font-bold uppercase">{{ implode(' • ', $certFacts) }}</p>
                            @endif

                            @if($cert->credential_url)
                                <a href="{{ $cert->credential_url }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center text-xs font-bold text-diu-primary hover:underline mt-2">
                                    <span>Verify credential</span><span class="ml-1.5">&rarr;</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
                </div>
              @endforeach
            </div>
        </div>
    @endif
</div>
