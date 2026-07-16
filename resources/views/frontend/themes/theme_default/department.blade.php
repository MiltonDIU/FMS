@extends('frontend.themes.theme_default.layouts.app')

@section('title', ($department->name ?? 'Department') . ' - Faculty Directory')

@section('content')

    <!-- Breadcrumb -->
    <div class="text-xs text-slate-500 font-semibold mb-6 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" class="hover:text-diu-primary transition">Home</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $faculty->url }}" class="hover:text-diu-primary transition">{{ $faculty->short_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-diu-primary">{{ $department->name }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        @include('frontend.themes.theme_default.partials.sidebar', [
            'faculties' => $faculties,
            'currentFaculty' => $faculty,
            'departments' => $faculty->departments()->where('is_active', true)->orderBy('sort_order')->get(),
            'adminRoles' => $adminRoles,
            'designations' => $designations,
            'currentDepartment' => $department,
            'isHome' => false,
            'sticky' => true,
        ])

        <!-- RIGHT MAIN STAGE -->
        <div class="lg:col-span-3 space-y-6">
            <div>
                <span class="text-[10px] bg-diu-primary/10 text-diu-primary font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Department Active</span>
                <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ $department->name }}</h2>
                <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                    <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    {{ $totalMembers }} Faculty Members
                </p>
            </div>

            <livewire:department-search :department-id="$department->id" />
        </div>

    </div>

@endsection
