@extends('frontend.themes.theme_portrait.layouts.app')

@section('title', ($department->name ?? 'Department') . ' — Contact')

@section('content')

    {{-- Breadcrumb as a line of text, same as every other page in this theme. --}}
    <nav class="text-[13px] mb-8 flex flex-wrap items-center gap-2" style="color: var(--text-muted);">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span style="color: var(--border-strong);">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span style="color: var(--border-strong);">/</span>
        <a href="{{ route('department.show', [
                'faculty_short_name' => strtolower($faculty->short_name),
                'department_code' => strtolower($department->code),
            ]) }}" wire:navigate class="hover:underline">{{ $department->name }}</a>
        <span style="color: var(--border-strong);">/</span>
        <span style="color: var(--text-strong);">Contact</span>
    </nav>

    {{-- Same component, same sidebar, same main stage — only what fills the
         stage changes. The department's contacts are one of two views of the
         department, not a separate destination, so nothing around them moves
         when you switch. --}}
    <livewire:department-search :department-id="$department->id" view="contact" />

@endsection
