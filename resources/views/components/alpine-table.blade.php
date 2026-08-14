{{--
  Reusable Alpine pagination + instant search wrapper.
  Usage:
    <x-alpine-table
        :rows="$this->semuaTim"
        :per-page="10"
        search-placeholder="Cari nama tim…"
        search-fields="['nama_tim']"
    >
        ... table thead/tbody via slot
    </x-alpine-table>

  Provides Alpine variables: displayed, filtered, q, page, totalPages, prevPage, nextPage
--}}
@props([
    'rows'              => [],
    'perPage'           => 10,
    'searchPlaceholder' => 'Cari…',
    'searchFields'      => ['nama'],  // array of field names to search
    'filterKey'         => null,      // optional filter field name
    'filterValue'       => '',        // initial filter value
])

<div
    x-data="{
        rows: @js($rows),
        q: '',
        page: 1,
        perPage: {{ $perPage }},
        filterKey: @js($filterKey),
        filterVal: @js($filterValue),

        get filtered() {
            let data = [...this.rows];

            // Search across searchFields
            const fields = @js($searchFields);
            if (this.q.trim()) {
                const qLow = this.q.toLowerCase();
                data = data.filter(r =>
                    fields.some(f => (r[f] ?? '').toLowerCase().includes(qLow))
                );
            }

            // Optional filter
            if (this.filterKey && this.filterVal) {
                data = data.filter(r => r[this.filterKey] === this.filterVal);
            }

            return data;
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
        },

        get displayed() {
            const start = (this.page - 1) * this.perPage;
            return this.filtered.slice(start, start + this.perPage);
        },

        get pageNumbers() {
            // Show max 5 page numbers around current
            const total = this.totalPages;
            const cur = this.page;
            let pages = [];
            const range = (from, to) => Array.from({ length: to - from + 1 }, (_, i) => from + i);

            if (total <= 7) {
                pages = range(1, total);
            } else if (cur <= 4) {
                pages = [...range(1, 5), '...', total];
            } else if (cur >= total - 3) {
                pages = [1, '...', ...range(total - 4, total)];
            } else {
                pages = [1, '...', cur - 1, cur, cur + 1, '...', total];
            }
            return pages;
        },

        prevPage() { if (this.page > 1) this.page--; },
        nextPage() { if (this.page < this.totalPages) this.page++; },
        goPage(p)  { if (p !== '...' && p >= 1 && p <= this.totalPages) this.page = p; },

        // Reset ke halaman 1 saat search/filter berubah
        resetPage() { this.page = 1; }
    }"
    @keyup.window.debounce="/* noop */"
    class="flex flex-col gap-4"
>
    {{ $slot }}
</div>
