import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import idLocale from '@fullcalendar/core/locales/id';
import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect';

// Flatpickr default locale Indonesia
flatpickr.localize(Indonesian);

// Expose ke window SEBELUM Alpine boot
window.flatpickr = flatpickr;
window.monthSelectPlugin = monthSelectPlugin;

// Expose FullCalendar ke window
window.FullCalendar = {
    Calendar,
    dayGridPlugin,
    timeGridPlugin,
    listPlugin,
    interactionPlugin,
    idLocale,
};

// ── Livewire Navigate - FOUC Prevention ────────────────────────────────────
// Prevent Flash of Unstyled Content during wire:navigate page transitions
document.addEventListener('livewire:navigating', (e) => {
    // Apply critical styles BEFORE swap to prevent flashing
    e.detail.onSwap(() => {
        // Preserve dark mode preference
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) {
            requestAnimationFrame(() => {
                document.documentElement.classList.add('dark');
            });
        }
        
        // Preserve mobile/desktop UI state
        const currentLayout = document.body.dataset.uiLayout;
        if (currentLayout) {
            requestAnimationFrame(() => {
                document.body.dataset.uiLayout = currentLayout;
            });
        }
    });
});

// Pastikan flatpickr tersedia setelah setiap wire:navigate
document.addEventListener('livewire:navigated', () => {
    window.flatpickr = flatpickr;
    
    // Re-init Alpine if needed (some components may need refresh)
    if (window.Alpine) {
        Alpine.initTree(document.body);
    }
});
