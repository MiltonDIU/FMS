@php
    $matchFooter = \App\Models\Setting::get('theme_theme_default_footer_match_theme', false);
@endphp
<footer class="{{ $matchFooter ? 'text-slate-200' : 'bg-slate-950 border-slate-800 text-slate-400' }} border-t text-xs mt-12 font-sans"
    {!! $matchFooter ? 'style="background-color: var(--color-diu-primary-dark); border-color: var(--color-diu-primary-light);"' : '' !!}>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 border-b border-slate-800 pb-6 mb-6 pt-8">
            <div class="text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-2">
                    <span class="font-display font-black text-sm text-white tracking-wide">DAFFODIL INTERNATIONAL UNIVERSITY</span>
                </div>
                <p class="text-[10px] text-slate-500 mt-1 uppercase tracking-widest font-semibold">Official Scholar Profile &amp; Citation Directory</p>
            </div>

            <div class="flex gap-4 text-[11px] font-semibold text-slate-300">
                <span class="text-diu-accent">Smart City Campus, Dhaka</span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-[10px] text-slate-500 pb-8">
            <p>&copy; {{ date('Y') }} Daffodil International University. All rights reserved.</p>
            <p>BAETE &amp; IEB Accredited Smart Campus, Savar, Dhaka, Bangladesh.</p>
        </div>
    </div>
</footer>
