@extends('frontend.themes.theme_ledger.layouts.app')

@section('title', ($department->name ?? 'Department') . ' — Contact' . \App\Helpers\Branding::get('meta_title_suffix'))

@section('meta_description', 'Office contacts for ' . ($department->name ?? 'this department') . ' at ' . \App\Helpers\Branding::get('site_name') . '.')

@section('content')

    <nav class="figure mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span aria-hidden="true">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('department.show', [
                'faculty_short_name' => strtolower($faculty->short_name),
                'department_code' => strtolower($department->code),
           ]) }}" wire:navigate class="hover:underline">{{ $department->name }}</a>
        <span aria-hidden="true">/</span>
        <span style="color: var(--ink-2);">Contact</span>
    </nav>

    {{-- Same component, same navigation, same page — only what fills it
         changes. A department's contacts are one of its two faces, not a
         separate destination, so nothing around them moves when you switch. --}}
    <livewire:department-search :department-id="$department->id" view="contact" />

@endsection
