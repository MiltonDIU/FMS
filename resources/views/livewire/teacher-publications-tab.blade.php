<div>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end pt-4">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Save Publications
            </x-filament::button>
        </div>
    </form>
</div>