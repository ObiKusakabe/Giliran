@props([
    'nameFrom'    => 'tanggal_mulai',
    'nameTo'      => 'tanggal_selesai',
    'labelFrom'   => 'Tanggal Mulai',
    'labelTo'     => 'Tanggal Selesai',
    'wireFrom'    => null,
    'wireTo'      => null,
    'valueFrom'   => '',
    'valueTo'     => '',
    'minDate'     => null,
    'maxDate'     => null,
    'required'    => false,
])

<div
    wire:ignore
    x-data="{
        fp: null,
        from: @js($valueFrom ?? ''),
        to: @js($valueTo ?? ''),

        syncDates() {
            if (!this.fp) return;
            const dates = [this.from, this.to].filter(Boolean);
            if (dates.length > 0) {
                this.fp.setDate(dates, false);
            } else {
                this.fp.clear();
            }
        },

        initFp() {
            if (this.fp) {
                this.fp.destroy();
                this.fp = null;
            }
            if (!this.$refs.fpInput) return;
            if (typeof window.flatpickr === 'undefined') {
                setTimeout(() => this.initFp(), 100);
                return;
            }

            const wireFrom = @js($wireFrom);
            const wireTo = @js($wireTo);

            this.fp = window.flatpickr(this.$refs.fpInput, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: [this.from, this.to].filter(Boolean),
                minDate: @js($minDate),
                maxDate: @js($maxDate),
                allowInput: false,
                disableMobile: true,
                static: true,
                monthSelectorType: 'static',
                onOpen: (selectedDates, dateStr, instance) => {
                    if (instance.calendarContainer) {
                        instance.calendarContainer.style.zIndex = '999999';
                        instance.calendarContainer.style.visibility = 'visible';
                        instance.calendarContainer.style.opacity = '1';
                    }
                },
                onChange: (dates) => {
                    if (dates.length >= 1) {
                        this.from = this.fp.formatDate(dates[0], 'Y-m-d');
                        if (wireFrom && typeof $wire !== 'undefined') {
                            $wire.set(wireFrom, this.from);
                        }
                    }
                    if (dates.length >= 2) {
                        this.to = this.fp.formatDate(dates[1], 'Y-m-d');
                        if (wireTo && typeof $wire !== 'undefined') {
                            $wire.set(wireTo, this.to);
                        }
                    } else if (dates.length < 2) {
                        this.to = '';
                        if (wireTo && typeof $wire !== 'undefined') {
                            $wire.set(wireTo, '');
                        }
                    }
                },
            });

            this.syncDates();
        }
    }"
    x-init="
        $nextTick(() => initFp());

        @if($wireFrom)
            $watch('$wire.{{ $wireFrom }}', (val) => {
                from = val || '';
                syncDates();
            });
        @endif

        @if($wireTo)
            $watch('$wire.{{ $wireTo }}', (val) => {
                to = val || '';
                syncDates();
            });
        @endif
    "
    x-on:livewire:navigating.window="
        if (fp) { fp.destroy(); fp = null; }
    "
    class="w-full"
>
    @if($labelFrom)
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
            {{ $labelFrom }}{{ $labelTo ? ' → '.$labelTo : '' }}
            @if($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <div class="relative w-full">
        <input
            x-ref="fpInput"
            type="text"
            placeholder="Pilih rentang tanggal…"
            readonly
            @click="if (fp) { fp.open(); } else { initFp(); fp && fp.open(); }"
            class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600
                   bg-white dark:bg-zinc-800 px-3 py-2 pl-9 text-sm
                   text-zinc-900 dark:text-zinc-100 placeholder-zinc-400
                   focus:outline-none focus:ring-2 focus:ring-brand cursor-pointer"
        />
        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 pointer-events-none"
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <button
            x-show="from"
            type="button"
            @click.stop="fp && fp.clear(); from = ''; to = '';"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 z-10"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Hidden inputs untuk form submit biasa (non-Livewire) --}}
    <input type="hidden" name="{{ $nameFrom }}" :value="from" />
    <input type="hidden" name="{{ $nameTo }}" :value="to" />

    @if($wireFrom)
        @error($wireFrom) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @endif
    @if($wireTo)
        @error($wireTo) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @endif
</div>
