@extends('frontend.themes.theme_aurora.layouts.app')

@section('title', ($department->name ?? 'Department') . ' — Contact' . \App\Helpers\Branding::get('meta_title_suffix'))

@section('meta_description', 'Office contacts for ' . ($department->name ?? 'this department') . ' at ' . \App\Helpers\Branding::get('site_name') . '.')

@section('content')

    <nav class="text-[13px] mb-6 flex flex-wrap items-center gap-2" style="color: var(--ink-4);">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span style="color: var(--hairline-strong);">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span style="color: var(--hairline-strong);">/</span>
        <a href="{{ route('department.show', [
                'faculty_short_name' => strtolower($faculty->short_name),
                'department_code' => strtolower($department->code),
           ]) }}" wire:navigate class="hover:underline">{{ $department->name }}</a>
        <span style="color: var(--hairline-strong);">/</span>
        <span style="color: var(--ink-2);">Contact</span>
    </nav>

    {{-- Same component, same navigation, same stage — only what fills the stage
         changes. A department's contacts are one of its two faces, not a
         separate destination, so nothing around them moves when you switch. --}}
    <livewire:department-search :department-id="$department->id" view="contact" />

@endsection
