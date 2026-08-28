<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

{{-- Custom sidebar styles --}}
<style>
    /* Prevent text selection on logo */
    [data-flux-sidebar-brand],
    [data-flux-sidebar-brand] *,
    [data-flux-brand],
    [data-flux-brand] * {
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }

    /* Custom scrollbar untuk sidebar */
    .scrollbar-thin::-webkit-scrollbar {
        width: 6px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background-color: rgb(212 212 216); /* zinc-300 */
        border-radius: 3px;
    }
    
    .dark .scrollbar-thin::-webkit-scrollbar-thumb {
        background-color: rgb(63 63 70); /* zinc-700 */
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background-color: rgb(161 161 170); /* zinc-400 */
    }
    
    .dark .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background-color: rgb(82 82 91); /* zinc-600 */
    }

    /* Sidebar smooth animation - ease-out gentle (300ms, no bounce) */
    [data-flux-sidebar] {
        transition: width 300ms cubic-bezier(0.4, 0.0, 0.2, 1) !important;
    }
    
    [data-flux-sidebar] * {
        transition: opacity 300ms cubic-bezier(0.4, 0.0, 0.2, 1),
                    transform 300ms cubic-bezier(0.4, 0.0, 0.2, 1);
    }

    /* Fix collapsed button sizing - force square dimensions */
    [data-flux-sidebar][data-flux-collapsed="true"] button[data-flux-sidebar-item],
    [data-flux-sidebar][data-flux-collapsed="true"] a[data-flux-sidebar-item] {
        width: 2.5rem !important; /* 40px = w-10 */
        height: 2.5rem !important;
        min-width: 2.5rem !important;
        min-height: 2.5rem !important;
        border-radius: 0.5rem !important; /* rounded-lg */
        justify-content: center !important;
        align-items: center !important;
        padding: 0 !important;
    }
    
    /* Ensure icon stays centered in collapsed state */
    [data-flux-sidebar][data-flux-collapsed="true"] button[data-flux-sidebar-item] svg,
    [data-flux-sidebar][data-flux-collapsed="true"] a[data-flux-sidebar-item] svg {
        margin: 0 !important;
    }
    
    /* Hide text labels in collapsed state */
    [data-flux-sidebar][data-flux-collapsed="true"] button[data-flux-sidebar-item] span:not(.sr-only),
    [data-flux-sidebar][data-flux-collapsed="true"] a[data-flux-sidebar-item] span:not(.sr-only) {
        display: none !important;
    }

    /* Sidebar active state: blue background + white text (sama kayak collapsed icons) */
    /* Target dengan specificity tinggi */
    [data-flux-sidebar] a[aria-current="page"],
    [data-flux-sidebar] button[aria-current="page"],
    [data-flux-sidebar] [aria-current="page"],
    nav[data-flux-sidebar-nav] a[aria-current="page"],
    nav[data-flux-sidebar-nav] button[aria-current="page"],
    nav[data-flux-sidebar-nav] [aria-current="page"] {
        background-color: rgb(37 99 235) !important; /* bg-blue-600 */
        color: white !important;
    }
    
    [data-flux-sidebar] a[aria-current="page"]:hover,
    [data-flux-sidebar] button[aria-current="page"]:hover,
    [data-flux-sidebar] [aria-current="page"]:hover,
    nav[data-flux-sidebar-nav] a[aria-current="page"]:hover,
    nav[data-flux-sidebar-nav] button[aria-current="page"]:hover,
    nav[data-flux-sidebar-nav] [aria-current="page"]:hover {
        background-color: rgb(29 78 216) !important; /* bg-blue-700 */
    }
    
    /* Icon color untuk active state */
    [data-flux-sidebar] [aria-current="page"] svg,
    nav[data-flux-sidebar-nav] [aria-current="page"] svg {
        color: white !important;
        stroke: currentColor !important;
    }
    
    /* Text color untuk active state */
    [data-flux-sidebar] [aria-current="page"] span,
    nav[data-flux-sidebar-nav] [aria-current="page"] span {
        color: white !important;
    }
    
    /* Remove Flux default active indicator (border/underline) */
    [data-flux-sidebar] [aria-current="page"]::before,
    [data-flux-sidebar] [aria-current="page"]::after,
    nav[data-flux-sidebar-nav] [aria-current="page"]::before,
    nav[data-flux-sidebar-nav] [aria-current="page"]::after {
        display: none !important;
        background: none !important;
    }
    
    /* Avatar profile button jadi rounded-md (bukan full circle) */
    [data-flux-sidebar-profile] {
        border-radius: 0.5rem !important; /* rounded-lg */
        overflow: visible !important;
        background: transparent !important;
    }
    
    /* Semua layer di dalam profile button: transparent + no border */
    [data-flux-sidebar-profile] > * {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
    
    /* Avatar container: bulat penuh */
    [data-flux-sidebar-profile] [data-flux-avatar] {
        border-radius: 9999px !important;
        background: transparent !important;
        overflow: hidden !important;
    }
    
    /* Avatar image: bulat penuh */
    [data-flux-sidebar-profile] [data-flux-avatar] img {
        border-radius: 9999px !important;
    }

    /* Fix Flux button hover - prevent white background on colored buttons */
    /* Strengthened selectors to catch all Flux button variants */
    button[data-flux-button][variant="primary"],
    button[data-flux-button][class*="bg-blue"],
    button[data-flux-button][class*="bg-brand"],
    button[data-flux-button][class*="primary"] {
        background-color: rgb(37 99 235) !important; /* blue-600 */
        color: white !important;
        border-color: rgb(37 99 235) !important;
    }
    
    button[data-flux-button][variant="primary"]:hover,
    button[data-flux-button][class*="bg-blue"]:hover,
    button[data-flux-button][class*="bg-brand"]:hover,
    button[data-flux-button][class*="primary"]:hover {
        background-color: rgb(29 78 216) !important; /* blue-700 */
        color: white !important;
        border-color: rgb(29 78 216) !important;
    }
    
    button[data-flux-button][variant="danger"],
    button[data-flux-button][class*="bg-red"],
    button[data-flux-button][class*="danger"] {
        background-color: rgb(220 38 38) !important; /* red-600 */
        color: white !important;
        border-color: rgb(220 38 38) !important;
    }
    
    button[data-flux-button][variant="danger"]:hover,
    button[data-flux-button][class*="bg-red"]:hover,
    button[data-flux-button][class*="danger"]:hover {
        background-color: rgb(185 28 28) !important; /* red-700 */
        color: white !important;
        border-color: rgb(185 28 28) !important;
    }
    
    /* Disabled state - ensure no hover effect */
    button[data-flux-button][disabled],
    button[data-flux-button]:disabled {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
    }
    
    button[data-flux-button][disabled]:hover,
    button[data-flux-button]:disabled:hover {
        background-color: inherit !important;
    }
</style>

{{-- Script Inovindo: jalankan SETELAH @fluxAppearance --}}
<script>
    (function () {
        function applyInovindoTheme() {
            if (localStorage.getItem('theme-inovindo') === 'true') {
                document.documentElement.classList.add('inovindo');
                document.documentElement.classList.remove('dark');
                localStorage.setItem('flux-appearance', 'light');
            } else {
                document.documentElement.classList.remove('inovindo');
            }
        }
        applyInovindoTheme();
        document.addEventListener('livewire:navigated', applyInovindoTheme);
    }());
</script>
