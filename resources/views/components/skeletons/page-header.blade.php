{{-- Page Header Skeleton (with actions) --}}
<flux:skeleton.group animate="shimmer">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex-1">
            <flux:skeleton class="h-8 w-48 rounded-lg mb-2" />
            <flux:skeleton.line class="w-64" />
        </div>
        <div class="flex items-center gap-2">
            <flux:skeleton class="h-10 w-32 rounded-lg" />
            <flux:skeleton class="h-10 w-28 rounded-lg" />
        </div>
    </div>
</flux:skeleton.group>
