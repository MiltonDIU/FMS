<div class="space-y-4 p-4 max-h-96 overflow-y-auto">
    @if(is_array($data))
        @foreach($data as $section => $content)
            <div class="border rounded-lg p-4 dark:border-gray-700">
                <h4 class="font-semibold text-primary-600 dark:text-primary-400 mb-2 capitalize">
                    {{ str_replace('_', ' ', $section) }}
                </h4>
                
                @if(is_array($content))
                    @if(isset($content[0]) && is_array($content[0]))
                        {{-- Array of objects (like educations, publications) --}}
                        <div class="space-y-2">
                            @foreach($content as $index => $item)
                                <div class="bg-gray-50 dark:bg-gray-800 p-2 rounded text-sm">
                                    @foreach($item as $key => $value)
                                        @if($value)
                                            <span class="text-gray-500">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                            <span class="ml-1">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                            @if(!$loop->last), @endif
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- Simple key-value object --}}
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            @foreach($content as $key => $value)
                                @if($value && !in_array($key, ['id', 'teacher_id', 'created_at', 'updated_at', 'deleted_at']))
                                    <dt class="text-gray-500">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                    <dd>{{ is_array($value) ? json_encode($value) : $value }}</dd>
                                @endif
                            @endforeach
                        </dl>
                    @endif
                @else
                    <p class="text-sm">{{ $content }}</p>
                @endif
            </div>
        @endforeach
    @else
        <p class="text-gray-500">No data available</p>
    @endif
</div>
