@props(['count' => 3, 'columns' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3'])

{{-- Cards Grid Skeleton --}}
<flux:skeleton.group animate="shimmer" class="grid {{ $columns }} gap-4">
    @for ($i = 0; $i < $count; $i++)
        <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <flux:skeleton class="h-6 w-32 rounded-lg mb-2" />
                    <flux:skeleton.line class="w-3/4" />
                </div>
                <flux:skeleton class="size-10 rounded-full" />
            </div>
            <div class="space-y-3 mt-4">
                <flux:skeleton.line />
                <flux:skeleton.line style="width: 80%" />
                <flux:skeleton.line style="width: 60%" />
            </div>
        </div>
    @endfor
</flux:skeleton.group>
