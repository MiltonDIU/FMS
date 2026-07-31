@extends('frontend.themes.theme_portrait.layouts.app')

@section('title', ($department->name ?? 'Department') . ' - Faculty Directory')

{{-- Sharing and structured data; see frontend.partials.seo-tags. --}}
@php
    $seo = \App\Helpers\SeoPayload::forDepartment($faculty, $department, $totalMembers ?? 0);
@endphp

@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.partials.seo-tags', ['seo' => $seo])
@endsection

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
