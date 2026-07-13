<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $department->name }} - Faculty Directory</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        diu: {
                            50: '#f2f7fd',
                            100: '#e4effb',
                            600: '#034ea2',
                            700: '#023c80',
                            900: '#011d3c',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
        }
    </style>
</head>
<body class="bg-slate-50 text-neutral-800 min-h-screen flex flex-col font-sans antialiased">

    <!-- Header Navigation -->
    <header class="sticky top-0 z-50 glass-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-diu-600 flex items-center justify-center text-white font-extrabold text-xl shadow-lg shadow-diu-600/20">
                    DIU
                </div>
                <div>
                    <span class="text-lg font-bold tracking-tight text-gray-900">Faculty <span class="text-diu-600">Directory</span></span>
                    <p class="text-[9px] text-gray-500 font-semibold tracking-wide uppercase">Daffodil International University</p>
                </div>
            </a>
            
            <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-gray-600">
                <a href="{{ url('/') }}" class="hover:text-diu-600 transition">Home</a>
                <a href="#" class="hover:text-diu-600 transition">Forum</a>
                <a href="#" class="hover:text-diu-600 transition">Contact Us</a>
            </nav>

            <a href="{{ url('/') }}" class="text-sm font-bold text-gray-600 hover:text-diu-600 transition">
                &larr; Back to Directory
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
        
        <!-- Breadcrumbs -->
        <div class="text-xs text-gray-500 font-semibold mb-6 flex items-center space-x-2">
            <a href="{{ url('/') }}" class="hover:text-diu-600 transition">Home</a>
            <span>/</span>
            <span>Faculty</span>
            <span>/</span>
            <span class="text-diu-600">{{ $department->name }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Left Sidebar: Filter by Role/Designation -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm sticky top-28">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4">
                        Filter by Role ({{ $designations->count() }})
                    </h3>
                    <div class="space-y-1">
                        <a 
                            href="{{ url('/departments/' . $department->id) }}" 
                            class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center justify-between transition {{ !request('designation') ? 'bg-diu-50 text-diu-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                        >
                            <span>All Roles</span>
                        </a>
                        @foreach($designations as $desig)
                            <a 
                                href="{{ url('/departments/' . $department->id . '?designation=' . $desig->id) }}" 
                                class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center justify-between transition {{ (request('designation') == $desig->id) ? 'bg-diu-50 text-diu-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                            >
                                <span>{{ $desig->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right Content: Teachers Lists -->
            <div class="lg:col-span-3 space-y-12">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">
                        {{ $department->name }}
                    </h1>
                    <p class="text-sm text-gray-500 mt-2">
                        Directory of professors, lecturers, and academic management of the department.
                    </p>
                </div>

                @php
                    $management = $teachers->filter(function ($t) {
                        $designation = strtolower(optional($t->designation)->name ?? '');
                        return str_contains($designation, 'dean') 
                            || str_contains($designation, 'head') 
                            || str_contains($designation, 'chairman') 
                            || str_contains($designation, 'director') 
                            || str_contains($designation, 'coordinator');
                    });
                    $faculty = $teachers->diff($management);
                @endphp

                <!-- Departmental Management -->
                @if($management->isNotEmpty() && !request('designation'))
                    <div class="space-y-6">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-gray-400 flex items-center">
                            <span class="w-1.5 h-4 bg-diu-600 rounded-full mr-2"></span>
                            Departmental Management
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($management as $teacher)
                                @include('frontend.themes.theme_default.partials.teacher_card', ['teacher' => $teacher])
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Departmental Faculty Members -->
                <div class="space-y-6">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-gray-400 flex items-center">
                        <span class="w-1.5 h-4 bg-diu-600 rounded-full mr-2"></span>
                        Departmental Faculty Members
                    </h2>
                    
                    @php
                        $listToDisplay = request('designation') ? $teachers : $faculty;
                    @endphp

                    @if($listToDisplay->isEmpty())
                        <div class="bg-white border border-gray-100 rounded-3xl p-12 text-center shadow-sm">
                            <p class="text-gray-500 font-semibold">No faculty members found for the selected filter.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($listToDisplay as $teacher)
                                @include('frontend.themes.theme_default.partials.teacher_card', ['teacher' => $teacher])
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-100 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} Daffodil International University. Faculty Directory. All rights reserved.
        </div>
    </footer>
</body>
</html>
