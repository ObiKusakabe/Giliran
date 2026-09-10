{{-- 4 Stat Cards Skeleton (Dashboard style) --}}
<flux:skeleton.group animate="shimmer" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    @for ($i = 0; $i < 4; $i++)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-start justify-between mb-4">
                <flux:skeleton.line class="w-1/2" />
                <flux:skeleton class="size-10 rounded-lg" />
            </div>
            <flux:skeleton class="h-10 w-16 rounded-md mb-2" />
            <flux:skeleton.line class="w-3/4" />
        </div>
    @endfor
</flux:skeleton.group>
