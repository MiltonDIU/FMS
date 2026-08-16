{{--
    The bar above a decision table: how many rows are ticked, and the single
    click that answers all of them.

    Shared by the three tabs that ask for a decision, because the tedium is the
    same in each — a run brings hundreds of rows and most of them get the same
    answer. Only the wording of the buttons differs, so that is what is passed
    in: $group is the key in `picked`, $pending is how many rows are still
    undecided, and $actions is [['decision' =>, 'label' =>, 'class' =>], ...].

    Laid out as a bar rather than a line of text. Everything in it was 11px and
    the same weight, so the count, the actions and the clear link ran together
    and read as a sentence.
--}}
@if($pending > 0)
<div class="fms-bulk-bar">
    <div class="fms-bulk-status">
        {{-- Nothing ticked yet: say what the checkboxes are for, since a row of
             disabled buttons explains nothing on its own. --}}
        <span x-show="picked.{{ $group }}.length === 0" class="fms-bulk-hint">
            Tick rows to decide several at once —
            <span class="fms-bulk-selected">{{ $pending }}</span> still to decide
        </span>

        <span x-show="picked.{{ $group }}.length > 0" x-cloak class="fms-bulk-count">
            <span class="fms-bulk-badge" x-text="picked.{{ $group }}.length">0</span>
            <span class="fms-bulk-selected">of {{ $pending }} selected</span>
        </span>
    </div>

    <div class="fms-bulk-actions">
        @foreach($actions as $action)
            <button type="button"
                @click="applyBulk('{{ $group }}', '{{ $action['decision'] }}')"
                :disabled="working || picked.{{ $group }}.length === 0"
                class="fms-bulk-btn {{ $action['class'] }}">
                {{ $action['label'] }}
            </button>
        @endforeach

        <button type="button"
            @click="picked.{{ $group }} = []"
            x-show="picked.{{ $group }}.length > 0"
            x-cloak
            class="fms-bulk-clear">
            Clear
        </button>

        <span x-show="working" x-cloak class="fms-bulk-hint" style="font-size: 0.7rem;">Saving…</span>
    </div>
</div>
@endif
{{-- Nothing left undecided means nothing to bulk-decide, and a bar reading
     "0 still to decide" over two dead buttons is worse than no bar. --}}
