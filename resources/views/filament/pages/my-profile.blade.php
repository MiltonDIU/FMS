<x-filament-panels::page>
    <style>
        @media (max-width: 1024px) {
            .responsive-vertical-tabs {
                display: flex;
                flex-direction: column !important;
            }
            
            .responsive-vertical-tabs > .fi-tabs-nav {
                width: 100% !important;
                border-right: none !important;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 1rem;
                
                /* Make tabs horizontal and scrollable */
                display: flex !important;
                flex-direction: row !important;
                overflow-x: auto !important;
                white-space: nowrap !important;
                gap: 0.5rem;
                padding-bottom: 0.5rem;
                
                /* Hide scrollbar for cleaner look */
                -ms-overflow-style: none;  /* IE and Edge */
                scrollbar-width: none;  /* Firefox */
            }
            
            .responsive-vertical-tabs > .fi-tabs-nav::-webkit-scrollbar {
                display: none;
            }

            /* Ensure tab items are not full width */
            .responsive-vertical-tabs > .fi-tabs-nav > .fi-tabs-item {
                flex: 0 0 auto !important;
            }

            .responsive-vertical-tabs > .fi-tabs-content {
                width: 100% !important;
            }
        }
    </style>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
        />
    </form>
</x-filament-panels::page>
