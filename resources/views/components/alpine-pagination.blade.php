{{-- Alpine pagination bar — pakai di dalam x-data yang punya page/totalPages/pageNumbers --}}
<div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-4">
    {{-- Info --}}
    <span class="text-xs text-zinc-400">
        <span x-text="filtered.length === 0 ? 'Tidak ada hasil' :
            'Menampilkan ' + (Math.min((page-1)*perPage+1, filtered.length)) + '–' +
            Math.min(page*perPage, filtered.length) + ' dari ' + filtered.length + ' data'
        "></span>
    </span>

    {{-- Pagination --}}
    <div x-show="totalPages > 1" class="flex items-center gap-1">
        {{-- Prev --}}
        <button
            @click="prevPage()"
            :disabled="page === 1"
            class="h-7 w-7 flex items-center justify-center rounded-md text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        {{-- Page numbers --}}
        <template x-for="(p, idx) in pageNumbers" :key="idx">
            <button
                @click="goPage(p)"
                :disabled="p === '...'"
                :class="p === page
                    ? 'bg-brand text-white font-semibold'
                    : p === '...'
                        ? 'text-zinc-500 cursor-default'
                        : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700'"
                class="h-7 min-w-[28px] px-1.5 flex items-center justify-center rounded-md text-xs transition-colors"
                x-text="p"
            ></button>
        </template>

        {{-- Next --}}
        <button
            @click="nextPage()"
            :disabled="page === totalPages"
            class="h-7 w-7 flex items-center justify-center rounded-md text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
    </div>
</div>
