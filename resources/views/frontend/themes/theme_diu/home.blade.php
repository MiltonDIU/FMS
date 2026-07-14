@extends('frontend.themes.theme_diu.layouts.app')

@section('title', 'Daffodil International University Faculty Directory')

@section('content')

    <!-- Breadcrumb Navigation Strip -->
    <div class="text-xs text-slate-500 font-semibold mb-8 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" class="hover:text-diu-primary font-semibold transition-colors">Home</a>
    </div>

    <livewire:teacher-search :selected-faculty-id="$selectedFaculty ? $selectedFaculty->id : null" />

@endsection
