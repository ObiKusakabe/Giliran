import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import idLocale from '@fullcalendar/core/locales/id';

// Expose FullCalendar ke window supaya bisa diakses dari Alpine x-init di blade
window.FullCalendar = {
    Calendar,
    dayGridPlugin,
    timeGridPlugin,
    listPlugin,
    interactionPlugin,
    idLocale,
};
