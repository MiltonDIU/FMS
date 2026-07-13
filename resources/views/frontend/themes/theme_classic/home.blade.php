<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Directory - DIU FMS (Classic)</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        serif: ['"Merriweather"', 'serif'],
                        display: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        royal: {
                            50: '#f0f4fe',
                            100: '#e1e9fd',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Merriweather', serif;
        }
    </style>
</head>
<body class="bg-[#F8F9FA] text-[#212529] min-h-screen flex flex-col antialiased">

    <!-- Header -->
    <header class="bg-royal-900 text-white py-6 shadow-md border-b-4 border-royal-700">
        <div class="max-w-6xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="text-center md:text-left">
                <span class="text-2xl font-serif font-bold tracking-tight font-display">Daffodil International University</span>
                <p class="text-xs uppercase tracking-wider text-royal-100 font-semibold mt-1">Faculty & Scholar Directory</p>
            </a>
            
            <a href="{{ url('/admin/login') }}" class="px-6 py-2 bg-royal-700 hover:bg-royal-800 text-white rounded font-bold text-xs uppercase tracking-wider transition duration-300">
                Portal Login
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-6xl mx-auto w-full px-4 py-12">
        <div class="text-center mb-12">
            <span class="px-3 py-1 bg-royal-100 text-royal-900 border border-royal-700/20 text-xs font-bold uppercase tracking-wider">
                Theme: Classic Royal Blue
            </span>
            <h1 class="text-3xl sm:text-4xl font-display font-bold text-royal-900 mt-4">
                Academic Roster & Biographies
            </h1>
            <p class="text-sm text-gray-600 max-w-xl mx-auto mt-2 italic">
                A classic registry of our university's professors, lectures, and academic leaders.
            </p>
        </div>

        <!-- Directory Grid -->
        @if($teachers->isEmpty())
            <div class="text-center py-12 bg-white border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800">No records found</h3>
                <p class="text-sm text-gray-500 mt-1">Please try again with a different search.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($teachers as $teacher)
                    <div class="bg-white border-t-4 border-royal-800 shadow-sm hover:shadow-md p-6 transition-all duration-300">
                        <div class="flex items-start space-x-4">
                            <!-- Photo -->
                            <div class="w-20 h-20 shrink-0 bg-gray-100 border border-gray-200">
                                @if($teacher->photo)
                                    <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-royal-50 text-royal-800 font-bold text-xl">
                                        {{ substr($teacher->first_name, 0, 1) }}
                                    </div>
                                @endif
                            </div>

                            <!-- Identity -->
                            <div>
                                <h3 class="font-display font-bold text-lg text-gray-900">
                                    {{ $teacher->first_name }} {{ $teacher->last_name }}
                                </h3>
                                <p class="text-xs text-royal-700 font-bold uppercase tracking-wider mt-1">
                                    {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                                </p>
                                <p class="text-xs text-gray-500 font-medium mt-1">
                                    Dept. of {{ optional($teacher->department)->name ?? 'General' }}
                                </p>
                            </div>
                        </div>

                        <div class="border-b border-gray-100 my-4"></div>

                        <!-- Mini Bio -->
                        @if($teacher->research_interest)
                            <p class="text-xs text-gray-600 line-clamp-2 italic mb-4">
                                "{{ $teacher->research_interest }}"
                            </p>
                        @endif

                        <a href="{{ url('/teachers/' . ($teacher->employee_id ?? $teacher->id)) }}" class="inline-block text-xs font-bold text-royal-800 hover:text-royal-900 hover:underline">
                            View Academic Profile &rarr;
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="bg-royal-900 text-royal-100 py-8 border-t-4 border-royal-700 mt-12">
        <div class="max-w-6xl mx-auto px-4 text-center text-xs">
            &copy; {{ date('Y') }} Daffodil International University. Legacy Academic Directory.
        </div>
    </footer>
</body>
</html>
