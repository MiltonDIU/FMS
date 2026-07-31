@extends('frontend.themes.theme_diu.layouts.app')

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

    <!-- Breadcrumb -->
    <div class="text-xs text-slate-500 font-semibold mb-6 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" wire:navigate class="hover:text-diu-primary transition">Home</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:text-diu-primary transition">{{ $faculty->short_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-diu-primary">{{ $department->name }}</span>
    </div>

    <!-- department-search renders its own header, sidebar + main-stage grid -->
    <livewire:department-search :department-id="$department->id" />

@endsection
