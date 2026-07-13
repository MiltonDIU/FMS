<div class="bg-white border border-gray-100 hover:border-diu-100 p-6 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-diu-600/5 transition-all duration-300 group flex flex-col justify-between items-center text-center">
    <div class="flex flex-col items-center">
        <!-- Photo -->
        <div class="relative w-24 h-24 mb-4">
            @if($teacher->photo)
                <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover rounded-2xl shadow-sm border border-gray-50" />
            @else
                <div class="w-full h-full rounded-2xl bg-diu-50 flex items-center justify-center text-diu-600 font-extrabold text-xl border border-diu-100/50">
                    {{ substr($teacher->first_name, 0, 1) }}{{ substr($teacher->last_name, 0, 1) }}
                </div>
            @endif
            <span class="absolute bottom-1 right-1 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
        </div>

        <!-- Identity -->
        <h3 class="font-extrabold text-gray-900 group-hover:text-diu-600 transition duration-200">
            {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
        </h3>
        <p class="text-xs text-gray-500 font-semibold mt-1">
            {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
        </p>
        
        @if($teacher->research_interest)
            <p class="text-[11px] text-gray-400 mt-3 line-clamp-2 italic px-2">
                "{{ $teacher->research_interest }}"
            </p>
        @endif
    </div>

    <!-- View Profile Link -->
    <a 
        href="{{ url('/' . strtolower($faculty->short_name) . '/' . strtolower($department->code) . '/' . $teacher->webpage) }}" 
        class="mt-6 w-full py-2 bg-gray-50 group-hover:bg-diu-600 group-hover:text-white rounded-xl text-xs font-bold text-gray-700 transition duration-300"
    >
        View Profile
    </a>
</div>
