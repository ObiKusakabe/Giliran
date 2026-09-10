{{-- Sidebar Navigation Skeleton --}}
<flux:skeleton.group animate="shimmer" class="flex flex-col gap-1 px-4">
    @for ($i = 0; $i < 8; $i++)
        <div class="flex items-center gap-3 px-3 py-2.5">
            <flux:skeleton class="size-5 rounded-md" />
            <flux:skeleton.line class="flex-1" style="width: {{ rand(60, 90) }}%" />
        </div>
    @endfor
</flux:skeleton.group>
