@props([
    'name' => 'month',
    'id' => null,
    'label' => null,
    'placeholder' => 'Pilih Bulan',
    'value' => null,
])

@php
    $id = $id ?? 'month-picker-' . uniqid();
    // Extract wire:model from attributes if present
    $wireModel = $attributes->wire('model')->value();
    $wireModelLive = $attributes->whereStartsWith('wire:model.live')->first();
@endphp

<div>
    @if ($label)
        <flux:label for="{{ $id }}">{{ $label }}</flux:label>
    @endif
    
    <flux:input 
        type="text" 
        id="{{ $id }}" 
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->whereStartsWith('wire:model') }}
        {!! $value ? 'value="' . $value . '"' : '' !!}
        data-month-picker
        readonly
        class="cursor-pointer"
    />
</div>

@pushOnce('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if flatpickr is loaded
    if (typeof flatpickr === 'undefined') {
        console.error('Flatpickr not loaded! Month picker will not work.');
        return;
    }

    // Init Flatpickr for all month pickers
    document.querySelectorAll('[data-month-picker]').forEach(el => {
        const instance = flatpickr(el, {
            plugins: [
                new monthSelectPlugin({
                    shorthand: false,
                    dateFormat: "F Y",  // Display: April 2026
                    altFormat: "Y-m",   // Wire model: 2026-04
                })
            ],
            // Match Flux UI styling
            onReady: function(selectedDates, dateStr, fp) {
                fp.calendarContainer.classList.add('flatpickr-flux-month');
            },
            // Trigger Livewire update on change
            onChange: function(selectedDates, dateStr, fp) {
                // Get wire:model attribute
                const wireModel = el.getAttribute('wire:model') || el.getAttribute('wire:model.live');
                if (wireModel) {
                    // Trigger Livewire update
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });
        
        // Store instance for cleanup
        el._flatpickr = instance;
    });
});

// Cleanup on Livewire navigate
document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('[data-month-picker]').forEach(el => {
        if (el._flatpickr) {
            el._flatpickr.destroy();
        }
    });
});
</script>
@endPushOnce

@pushOnce('styles')
<style>
/* Flatpickr Month Picker - Match Flux UI */
.flatpickr-flux-month .flatpickr-months {
    background: var(--color-zinc-50);
    border-bottom: 1px solid var(--color-zinc-200);
}

.dark .flatpickr-flux-month .flatpickr-months {
    background: var(--color-zinc-800);
    border-bottom-color: var(--color-zinc-700);
}

.flatpickr-flux-month .flatpickr-month {
    color: var(--color-zinc-900);
}

.dark .flatpickr-flux-month .flatpickr-month {
    color: var(--color-zinc-100);
}

/* Month select grid */
.flatpickr-monthSelect-months {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 0.5rem !important;
    padding: 1rem !important;
}

.flatpickr-monthSelect-month {
    padding: 0.75rem 1rem !important;
    border-radius: 0.5rem !important;
    background: var(--color-zinc-100) !important;
    color: var(--color-zinc-700) !important;
    border: 1px solid var(--color-zinc-200) !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}

.dark .flatpickr-monthSelect-month {
    background: var(--color-zinc-800) !important;
    color: var(--color-zinc-300) !important;
    border-color: var(--color-zinc-700) !important;
}

.flatpickr-monthSelect-month:hover {
    background: var(--color-zinc-200) !important;
    border-color: var(--color-zinc-300) !important;
}

.dark .flatpickr-monthSelect-month:hover {
    background: var(--color-zinc-700) !important;
    border-color: var(--color-zinc-600) !important;
}

.flatpickr-monthSelect-month.selected {
    background: #3B71CA !important;
    color: white !important;
    border-color: #3B71CA !important;
}

.dark .flatpickr-monthSelect-month.selected {
    background: #3B71CA !important;
    border-color: #3B71CA !important;
}

/* Calendar container */
.flatpickr-flux-month.flatpickr-calendar {
    border-radius: 0.75rem;
    border: 1px solid var(--color-zinc-200);
    box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.15), 0 4px 10px -5px rgb(0 0 0 / 0.1);
    background: white;
}

.dark .flatpickr-flux-month.flatpickr-calendar {
    background: var(--color-zinc-900);
    border-color: var(--color-zinc-700);
}
</style>
@endPushOnce
