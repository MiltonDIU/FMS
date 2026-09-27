@extends('frontend.themes.theme_modern.layouts.app')

@section('title', ($department->name ?? 'Department') . ' — Contact' . \App\Helpers\Branding::get('meta_title_suffix'))

@section('meta_description', 'Office contacts for ' . ($department->name ?? 'this department') . ' at ' . \App\Helpers\Branding::get('site_name') . '.')

@section('content')

    @php
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;
        $deptUrl = ($facSlug && $department->code)
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code)])
            : route('home');
    @endphp

    <!-- Breadcrumb -->
    <div class="text-xs text-slate-500 font-semibold mb-6 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" wire:navigate class="hover:text-diu-primary transition">Home</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:text-diu-primary transition">{{ $faculty->short_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $deptUrl }}" wire:navigate class="hover:text-diu-primary transition">{{ $department->code }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-diu-primary truncate max-w-xs">Contact</span>
    </div>

    {{-- Same component, same navigation, same stage — only what fills the stage
         changes. A department's contacts are one of its two faces, not a
         separate destination, so nothing around them moves when you switch. --}}
    <livewire:department-search :department-id="$department->id" view="contact" />

@endsection
