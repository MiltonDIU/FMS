<div class="mb-4" x-ref="searchWrapper">
    <div
        x-data="{
            query: '',
            loading: false,
            results: [],
            showResults: false,
            dropdownPosition: { top: 0, left: 0, width: 0 },
            async search() {
                if (this.query.length < 2) {
                    this.results = [];
                    this.showResults = false;
                    return;
                }

                this.loading = true;
                this.updatePosition();
                try {
                    const response = await fetch(`/api/teacher/search?q=${encodeURIComponent(this.query)}`);
                    const data = await response.json();

                    if (data.success) {
                        this.results = data.data || [];
                        this.showResults = true;
                    } else {
                        this.results = [];
                        this.showResults = true;
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    this.results = [];
                } finally {
                    this.loading = false;
                    this.updatePosition();
                }
            },
            updatePosition() {
                const input = this.$refs.searchInput;
                if (input) {
                    const rect = input.getBoundingClientRect();
                    this.dropdownPosition = {
                        top: rect.bottom + window.scrollY + 6,
                        left: rect.left + window.scrollX,
                        width: rect.width
                    };
                }
            },
            autoFill(teacher) {
                if (window.Livewire) {
                    Livewire.dispatch('fillTeacherData', { teacher });
                }
                this.showResults = false;
                this.query = '';
                this.results = [];
            }
        }"
        @click.away="showResults = false"
        @scroll.window="if(showResults) updatePosition()"
        @resize.window="if(showResults) updatePosition()"
    >
        <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white ring-gray-950/10 focus-within:ring-2 focus-within:ring-primary-600">
            <div class="min-w-0 flex-1">
                <input
                    type="text"
                    x-ref="searchInput"
                    x-model="query"
                    @input.debounce.500ms="search()"
                    @focus="if(query.length >= 2) { showResults = true; updatePosition(); }"
                    placeholder="Search legacy database (name, employee ID, email)..."
                    class="fi-input block w-full border-none py-2.5 text-base text-gray-950 transition duration-75 placeholder:text-gray-400 focus:ring-0 sm:text-sm bg-white ps-4 pe-4 rounded-lg"
                />
            </div>
        </div>

        <template x-teleport="body">
            <div
                x-show="showResults"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                :style="`position: absolute; top: ${dropdownPosition.top}px; left: ${dropdownPosition.left}px; width: ${dropdownPosition.width}px; z-index: 99999; background-color: #ffffff !important; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);`"
                class="overflow-hidden"
            >
                <template x-if="results.length > 0">
                    <div class="w-full bg-white" style="padding: 20px 10px">
                        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <span class="text-xs font-bold uppercase tracking-widest text-gray-500">Search Results</span>
                            <span class="text-[11px] font-semibold text-primary-700 bg-primary-50 px-2.5 py-1 rounded-md" x-text="results.length + ' match(es)'"></span>
                        </div>

                        <div class="max-h-[400px] overflow-y-auto w-full">
                            <table class="w-full border-collapse" style="table-layout: fixed; width: 100%;">
                                <thead class="sticky top-0 bg-white z-10">
                                <tr class="text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider" style="border-bottom: 3px solid darkgray;">
                                    <th class="px-6 py-4 border-b w-[30%]">Name</th>
                                    <th class="px-6 py-4 border-b w-[20%]">Emp ID</th>
                                    <th class="px-6 py-4 border-b w-[35%]">Email Address</th>
                                    <th class="px-6 py-4 border-b w-[15%] text-center">Action</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 bg-white">
                                <template x-for="teacher in results" :key="teacher.employeeID || teacher.id">
                                    <tr
                                        class="group hover:bg-primary-50/30 transition-colors duration-150 cursor-pointer"
                                        @click="if(!teacher.exists_locally) autoFill(teacher)"
                                        style="text-align: center;
  line-height: 30px;
  border-bottom: 1px solid darkgray;"
                                    >
                                        <td class="px-6 py-5">
                                            <div class="flex flex-col gap-1">
                                                <span class="text-sm font-bold text-gray-900 group-hover:text-primary-600 transition-colors" x-text="teacher.name"></span>
                                                <template x-if="teacher.exists_locally">
                                                    <span class="inline-flex items-center w-fit px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-green-100 text-green-700 ring-1 ring-inset ring-green-600/20">Added</span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5 text-sm font-medium text-gray-600 font-mono tracking-tight" x-text="teacher.employeeID || 'N/A'"></td>
                                        <td class="px-6 py-5 text-sm text-gray-500 truncate" x-text="teacher.email || '—'" :title="teacher.email"></td>
                                        <td class="px-6 py-5 text-center">
                                            <button
                                                type="button"
                                                x-show="!teacher.exists_locally"
                                                @click.stop="autoFill(teacher)"
                                                class="inline-flex items-center px-4 py-1.5 bg-primary-600 text-white text-[11px] font-bold rounded-lg hover:bg-primary-700 shadow-sm transition-all active:scale-95"
                                            >
                                                Fill Data
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <template x-if="results.length === 0 && !loading">
                    <div class="px-6 py-12 text-center bg-white" style="padding: 50px 20px">
                        <h3 class="text-base font-bold text-gray-900">No Teacher Found</h3>
                        <p class="text-sm text-gray-500 mt-1 max-w-[250px] mx-auto">We couldn't find any record matching your search in the legacy database.</p>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
