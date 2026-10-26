{{--
    System Settings → Maintenance Mode → Internal Notice, across the top of
    every admin panel page. See App\Helpers\AdminNotice.
--}}
<div
    role="status"
    style="display:flex;align-items:flex-start;gap:0.6rem;padding:0.65rem 1rem;background:{{ $style['bg'] }};border-bottom:1px solid {{ $style['border'] }};color:{{ $style['text'] }};font-size:0.875rem;line-height:1.5;"
>
    <x-filament::icon icon="heroicon-o-megaphone" style="width:1.25rem;height:1.25rem;flex-shrink:0;margin-top:0.1rem;" />
    <div style="flex:1;min-width:0;font-weight:500;overflow-wrap:anywhere;">{!! nl2br(e($message)) !!}</div>
</div>
