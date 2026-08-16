{{--
    The bar above a decision table: how many rows are ticked, and the single
    click that answers all of them.

    Shared by the three tabs that ask for a decision, because the tedium is the
    same in each — a run brings hundreds of rows and most of them get the same
    answer. Only the wording of the buttons differs, so that is what is passed
    in: $group is the key in `picked`, $pending is how many rows are still
    undecided, and $actions is [['decision' =>, 'label' =>, 'class' =>], ...].
--}}
<div class="flex flex-wrap items-center gap-2 pb-1">
    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400">
        <span x-text="picked.{{ $group }}.length">0</span> of {{ $pending }} selected
    </span>

    @foreach($actions as $action)
        <button type="button"
            @click="applyBulk('{{ $group }}', '{{ $action['decision'] }}')"
            :disabled="working || picked.{{ $group }}.length === 0"
            class="text-[11px] font-semibold px-2.5 py-1 rounded-md transition disabled:opacity-40 disabled:cursor-not-allowed {{ $action['class'] }}">
            {{ $action['label'] }}
        </button>
    @endforeach

    <button type="button"
        @click="picked.{{ $group }} = []"
        x-show="picked.{{ $group }}.length > 0"
        x-cloak
        class="text-[11px] text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline">
        Clear selection
    </button>

    <span x-show="working" x-cloak class="text-[11px] text-gray-500 dark:text-gray-400">Saving…</span>
</div>
