@extends('frontend.themes.theme_ledger.layouts.app')

{{-- Sharing and structured data; see frontend.themes.theme_ledger.partials.seo-tags. --}}
@php
    $seo = \App\Helpers\SeoPayload::forDepartment($faculty, $department, $totalMembers ?? 0);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_ledger.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    <nav class="figure mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span aria-hidden="true">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span aria-hidden="true">/</span>
        <span style="color: var(--ink-2);">{{ $department->name }}</span>
    </nav>

    {{-- The component renders the department heading, the finder and the rows;
         everything on this page below the breadcrumb is one Livewire root, so
         typing in the search box costs no round trip through here. --}}
    <livewire:department-search :department-id="$department->id" />

@endsection
