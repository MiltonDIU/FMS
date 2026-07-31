@extends('frontend.themes.theme_portrait.layouts.app')

@section('title', ($department->name ?? 'Department') . ' - Faculty Directory')

@section('content')

    {{-- Breadcrumb as a line of text, same as every other page in this theme. --}}
    <nav class="text-[13px] mb-8 flex flex-wrap items-center gap-2" style="color: var(--text-muted);">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span style="color: var(--border-strong);">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span style="color: var(--border-strong);">/</span>
        <span style="color: var(--text-strong);">{{ $department->name }}</span>
    </nav>

    <!-- department-search renders its own header, sidebar + main-stage grid -->
    <livewire:department-search :department-id="$department->id" />

@endsection
