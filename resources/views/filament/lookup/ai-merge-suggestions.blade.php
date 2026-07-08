<div style="font-family: inherit; color: #1f2937;">
    @if(empty($suggestions))
        <div style="text-align: center; padding: 40px; background-color: #f9fafb; border: 1px dashed #d1d5db; border-radius: 12px;">
            <div style="display: inline-block; padding: 12px; background-color: #ecfdf5; border-radius: 9999px; margin-bottom: 12px; color: #059669;">
                <svg width="32" height="32" style="width: 32px; height: 32px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 style="font-size: 16px; font-weight: 600; color: #111827; margin: 0 0 8px 0;">All Clear!</h3>
            <p style="font-size: 14px; color: #6b7280; margin: 0;">
                AI did not find any potential duplicate groups. Your database lookup values look clean and well-structured!
            </p>
        </div>
    @else
        <div style="background-color: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 16px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px;">
            <div style="color: #d97706; display: flex; align-items: center; justify-content: center; padding-top: 2px;">
                <svg width="20" height="20" style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h4 style="font-size: 14px; font-weight: 600; color: #92400e; margin: 0 0 4px 0;">Review AI Merge Suggestions</h4>
                <p style="font-size: 12px; color: #b45309; margin: 0; line-height: 1.5;">
                    Below are the duplicate groups identified. For each group, choose which name to keep as the primary target. Merging will update all associated teachers and delete the duplicates.
                </p>
            </div>
        </div>

        <div style="overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <table style="width: 100%; border-collapse: collapse; text-align: left; background-color: #ffffff;">
                <thead>
                    <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #4b5563; text-transform: uppercase; width: 100px;">Group</th>
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #4b5563; text-transform: uppercase;">Duplicate Candidates</th>
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #4b5563; text-transform: uppercase; width: 240px;">Keep Name</th>
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #4b5563; text-transform: uppercase; width: 140px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suggestions as $index => $group)
                        <tr class="group-card" style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 16px; font-size: 13px; font-weight: 600; color: #111827; vertical-align: middle;">
                                #{{ $index + 1 }}
                            </td>
                            <td style="padding: 16px; vertical-align: middle;">
                                <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                                    <span style="display: inline-block; padding: 4px 10px; background-color: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; font-size: 12px; font-weight: 500; border-radius: 9999px;">
                                        {{ $group['primary']['name'] }} (AI Target)
                                    </span>
                                    @foreach($group['duplicates'] as $dup)
                                        <span style="display: inline-block; padding: 4px 10px; background-color: #f3f4f6; border: 1px solid #e5e7eb; color: #4b5563; font-size: 12px; font-weight: 500; border-radius: 9999px;">
                                            {{ $dup['name'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td style="padding: 16px; vertical-align: middle;">
                                <select class="merge-target-select" style="width: 100%; font-size: 13px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; background-color: #ffffff; color: #1f2937; box-shadow: 0 1px 2px rgba(0,0,0,0.05); cursor: pointer; outline: none;">
                                    <option value="{{ $group['primary']['id'] }}">{{ $group['primary']['name'] }}</option>
                                    @foreach($group['duplicates'] as $dup)
                                        <option value="{{ $dup['id'] }}">{{ $dup['name'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding: 16px; text-align: right; vertical-align: middle;">
                                <button 
                                    type="button"
                                    wire:click="mergeGroup($event.target.closest('.group-card').querySelector('.merge-target-select').value, {{ json_encode(array_merge([$group['primary']['id']], array_column($group['duplicates'], 'id'))) }}, '{{ $type }}')"
                                    wire:loading.attr="disabled"
                                    style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border: 1px solid transparent; font-size: 12px; font-weight: 600; border-radius: 6px; color: #ffffff; background-color: #d97706; cursor: pointer; transition: background-color 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                                    onmouseover="this.style.backgroundColor='#b45309'"
                                    onmouseout="this.style.backgroundColor='#d97706'"
                                >
                                    Merge Group
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
