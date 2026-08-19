@extends('frontend.themes.theme_aurora.layouts.app')

{{-- Sharing and structured data; see frontend.themes.theme_aurora.partials.seo-tags. --}}
@php
    $seo = \App\Helpers\SeoPayload::forDepartment($faculty, $department, $totalMembers ?? 0);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_aurora.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    <nav class="text-[13px] mb-6 flex flex-wrap items-center gap-2" style="color: var(--ink-4);">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span style="color: var(--hairline-strong);">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span style="color: var(--hairline-strong);">/</span>
        <span style="color: var(--ink-2);">{{ $department->name }}</span>
    </nav>

    {{-- The component renders the department heading, the command bar and the
         tiles; everything on this page below the breadcrumb is one Livewire
         root so typing in the search box costs no round trip through here. --}}
    <livewire:department-search :department-id="$department->id" />

@endsection
