{{-- Calendar Skeleton (FullCalendar style) --}}
<flux:skeleton.group animate="shimmer" class="space-y-4">
    {{-- Toolbar --}}
    <div class="flex justify-between items-center gap-4">
        <div class="flex gap-2">
            <flux:skeleton class="h-8 w-16 rounded-md" />
            <flux:skeleton class="h-8 w-16 rounded-md" />
            <flux:skeleton class="h-8 w-20 rounded-md" />
        </div>
        <flux:skeleton class="h-8 w-32 rounded-md" />
        <div class="flex gap-2">
            <flux:skeleton class="h-8 w-24 rounded-md" />
            <flux:skeleton class="h-8 w-24 rounded-md" />
        </div>
    </div>
    
    {{-- Calendar Grid --}}
    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
        {{-- Week header --}}
        <div class="grid grid-cols-7 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                <div class="h-10 flex items-center justify-center">
                    <flux:skeleton.line class="w-12" />
                </div>
            @endforeach
        </div>
        
        {{-- Calendar days --}}
        <div class="grid grid-cols-7">
            @for ($i = 0; $i < 35; $i++)
                <div class="min-h-[120px] border-r border-b border-zinc-200 dark:border-zinc-700 p-3 space-y-3">
                    <flux:skeleton class="h-4 w-8 rounded" />
                    <div class="space-y-2">
                        <flux:skeleton.line />
                        @if ($i % 3 === 0)
                            <flux:skeleton.line style="width: 80%" />
                        @endif
                        @if ($i % 5 === 0)
                            <flux:skeleton.line style="width: 60%" />
                        @endif
                    </div>
                </div>
            @endfor
        </div>
    </div>
</flux:skeleton.group>
