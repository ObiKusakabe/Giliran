@props([
    'steps' => [],
    'idleText' => 'Submit',
    'variant' => 'primary',
    'icon' => null,
    'size' => 'base',
    'wireTarget' => null,
    'minWidth' => '180px',
])

<div
    x-data="{
        isProcessing: false,
        currentLabel: {{ Js::from($idleText) }},
        idleText: {{ Js::from($idleText) }},
        steps: {{ Js::from($steps) }},
        timers: [],

        init() {
            @if ($wireTarget)
            const observer = new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    if (mutation.attributeName === 'class') {
                        const isLoading = this.$refs.btn.classList.contains('is-loading');
                        if (isLoading && !this.isProcessing) {
                            this.start();
                        } else if (!isLoading && this.isProcessing) {
                            this.reset();
                        }
                    }
                }
            });

            observer.observe(this.$refs.btn, {
                attributes: true,
                attributeFilter: ['class']
            });
            @endif

            if (window.Livewire) {
                Livewire.hook('commit', ({ succeed, fail }) => {
                    succeed(() => {
                        if (this.isProcessing) this.reset();
                    });
                    fail(() => {
                        if (this.isProcessing) this.reset();
                    });
                });
            }
        },

        start() {
            if (!this.steps || this.steps.length === 0) return;

            this.isProcessing = true;
            this.currentLabel = this.steps[0].label;

            let elapsed = 0;
            this.steps.forEach((step) => {
                const timer = setTimeout(() => {
                    this.currentLabel = step.label;
                }, elapsed);
                this.timers.push(timer);
                elapsed += step.duration;
            });
        },

        reset() {
            this.timers.forEach(t => clearTimeout(t));
            this.timers = [];
            this.isProcessing = false;
            this.currentLabel = this.idleText;
        }
    }"
    class="inline-block"
>
    @php
        $buttonAttrs = ['type' => 'button'];
        if ($wireTarget) {
            $buttonAttrs['wire:target'] = $wireTarget;
            $buttonAttrs['wire:loading.class'] = 'is-loading';
        }
    @endphp
    <flux:button
        {{ $attributes->merge($buttonAttrs) }}
        :variant="$variant"
        :size="$size"
        :loading="false"
        wire:loading.attr="disabled"
        x-bind:aria-busy="isProcessing ? 'true' : 'false'"
        x-bind:disabled="isProcessing"
        aria-live="polite"
        style="min-width: {{ $minWidth }};"
        class="transition-all"
        x-ref="btn"
    >
        {{-- State 1: Idle (icon di kiri, teks idle) --}}
        <span 
            x-show="!isProcessing" 
            class="inline-flex items-center justify-center gap-2"
        >
            @if ($icon)
                <flux:icon :icon="$icon" variant="micro" class="size-4 shrink-0" />
            @endif
            <span>{{ $slot->isEmpty() ? $idleText : $slot }}</span>
        </span>
        
        {{-- State 2: Processing (Format: keterangan -> loading animation di kanan) --}}
        <span 
            x-show="isProcessing" 
            x-cloak
            class="inline-flex items-center justify-center gap-2"
        >
            <span 
                x-text="currentLabel"
                style="animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;"
            ></span>
            <svg class="animate-spin size-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </span>
    </flux:button>
</div>
