<x-filament-widgets::widget>
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @endpush

    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Publications by Year
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Annual publication trends
                </p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="w-full sm:w-auto">
                    <select
                        wire:model.live="faculty_id"
                        class="block w-full rounded-lg border-none bg-gray-50 px-3 py-2 text-sm text-gray-950 ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:focus:ring-primary-500"
                    >
                        <option value="">All Faculties</option>
                        @foreach(\App\Models\Faculty::orderBy('name')->get() as $faculty)
                            <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-auto">
                    <select
                        wire:model.live="department_id"
                        class="block w-full rounded-lg border-none bg-gray-50 px-3 py-2 text-sm text-gray-950 ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:focus:ring-primary-500"
                    >
                        <option value="">All Departments</option>
                        @foreach($this->departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div
            x-data="{
                chart: null,
                init() {
                    this.renderChart(@js($this->getChartData()));
                    
                    $wire.on('update-chart', (data) => {
                        this.updateChart(data.data);
                    });

                    // Handle dark mode changes
                    const observer = new MutationObserver(() => {
                        if (this.chart) {
                            this.chart.updateOptions({
                                theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
                                chart: { background: 'transparent' },
                                xaxis: { 
                                    labels: { style: { colors: document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280' } } 
                                },
                                yaxis: { 
                                    labels: { style: { colors: document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280' } } 
                                }
                            });
                        }
                    });
                    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                },
                renderChart(data) {
                    if (this.chart) {
                        this.chart.destroy();
                    }

                    const isDark = document.documentElement.classList.contains('dark');
                    const options = {
                        series: [{
                            name: 'Publications',
                            data: data.counts
                        }],
                        chart: {
                            type: 'bar',
                            height: 350,
                            background: 'transparent',
                            toolbar: { show: false },
                            animations: { enabled: true }
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                columnWidth: '60%',
                            }
                        },
                        dataLabels: {
                            enabled: false
                        },
                        labels: data.years,
                        xaxis: {
                            labels: {
                                style: {
                                    colors: isDark ? '#9ca3af' : '#6b7280',
                                    fontFamily: 'inherit'
                                }
                            },
                            axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: {
                            labels: {
                                style: {
                                    colors: isDark ? '#9ca3af' : '#6b7280',
                                    fontFamily: 'inherit'
                                }
                            }
                        },
                        grid: {
                            borderColor: isDark ? '#374151' : '#f3f4f6',
                            strokeDashArray: 4,
                        },
                        theme: {
                            mode: isDark ? 'dark' : 'light'
                        },
                        colors: ['#3b82f6'],
                    };

                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();
                },
                updateChart(data) {
                    if (!this.chart) return;
                    
                    this.chart.updateOptions({
                        labels: data.years
                    });
                    
                    this.chart.updateSeries([{
                        data: data.counts
                    }]);
                }
            }"
            wire:ignore
        >
            <div x-ref="chart"></div>
            
            <div class="mt-4 grid grid-cols-3 gap-4 border-t border-gray-100 pt-4 dark:border-white/10">
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $this->getChartData()['total'] }}
                    </div>
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                        Total
                    </div>
                </div>
                <!-- You can add more summary stats here if needed -->
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
