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
