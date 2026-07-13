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
                A classic registry of our university's professors, lecturers, and academic departments.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar: Filter by Faculty -->
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-200 p-6 shadow-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-royal-900 border-b border-gray-100 pb-2 mb-4">
                        Faculty List
                    </h3>
                    <div class="space-y-1">
                        @foreach($faculties as $faculty)
                            <a 
                                href="{{ url('/?faculty=' . strtolower($faculty->short_name)) }}" 
                                class="block text-left px-3 py-2 rounded text-sm font-semibold transition {{ ($selectedFaculty && $selectedFaculty->id === $faculty->id) ? 'bg-royal-50 text-royal-900 border-l-4 border-royal-700 pl-2' : 'text-gray-600 hover:bg-gray-50' }}"
                            >
                                {{ $faculty->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Main Area: Departments Grid -->
            <div class="lg:col-span-3">
                @if($departments->isEmpty())
                    <div class="bg-white border border-gray-200 p-12 text-center shadow-sm">
                        <p class="text-gray-500 italic">No departments listed for this faculty.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($departments as $dept)
                            <div class="bg-white border border-gray-200 p-6 shadow-sm hover:shadow transition">
                                <h3 class="font-display font-bold text-lg text-royal-900">{{ $dept->name }}</h3>
                                <div class="border-b border-gray-100 my-4"></div>
                                <a href="{{ url('/departments/' . $dept->code) }}" class="inline-block text-xs font-bold text-royal-800 hover:text-royal-900 hover:underline">
                                    Browse Faculty Members &rarr;
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-royal-900 text-royal-100 py-8 border-t-4 border-royal-700 mt-12">
        <div class="max-w-6xl mx-auto px-4 text-center text-xs">
            &copy; {{ date('Y') }} Daffodil International University. Legacy Academic Directory.
        </div>
    </footer>
</body>
</html>
