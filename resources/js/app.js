import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import idLocale from '@fullcalendar/core/locales/id';
import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';

// Flatpickr default locale Indonesia
flatpickr.localize(Indonesian);

// Expose ke window SEBELUM Alpine boot
window.flatpickr = flatpickr;

// Expose FullCalendar ke window
window.FullCalendar = {
    Calendar,
    dayGridPlugin,
    timeGridPlugin,
    listPlugin,
    interactionPlugin,
    idLocale,
};

// Pastikan flatpickr tersedia setelah setiap wire:navigate
document.addEventListener('livewire:navigated', () => {
    window.flatpickr = flatpickr;
});
