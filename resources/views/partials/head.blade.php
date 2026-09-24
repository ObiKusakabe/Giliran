<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
<meta name="theme-color" content="#ffffff" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="apple-mobile-web-app-title" content="Giliran" />
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>
    {{ filled($title ?? null) ? $title.' - Giliran' : 'Giliran - Sistem Penjadwalan' }}
</title>

{{-- Favicon - SVG only (modern browsers) --}}
<link rel="icon" href="/favicon.svg?v={{ time() }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ config('app.asset_version', '1') }}">
<link rel="manifest" href="/site.webmanifest">

@fonts

{{-- Dark Mode Init - BEFORE ANY STYLES --}}
<script>
    // Prevent FOUC by applying dark mode class immediately (before any CSS loads)
    (function() {
        const appearance = localStorage.getItem('flux-appearance');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Apply dark class if user preference is dark OR system prefers dark (and no explicit light preference)
        if (appearance === 'dark' || (!appearance && prefersDark)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>

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

    /* Only swap logo with collapse toggle when hovering directly over the header in collapsed desktop sidebar */
    [data-flux-sidebar-collapsed-desktop] [data-flux-sidebar-brand] {
        opacity: 1 !important;
        position: relative !important;
        pointer-events: auto !important;
    }
    [data-flux-sidebar-collapsed-desktop] [data-flux-sidebar-collapse] {
        opacity: 0 !important;
        position: absolute !important;
        pointer-events: none !important;
    }
    [data-flux-sidebar-collapsed-desktop] [data-flux-sidebar-header]:hover [data-flux-sidebar-brand] {
        opacity: 0 !important;
        position: absolute !important;
        pointer-events: none !important;
    }
    [data-flux-sidebar-collapsed-desktop] [data-flux-sidebar-header]:hover [data-flux-sidebar-collapse] {
        opacity: 1 !important;
        position: relative !important;
        pointer-events: auto !important;
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
    
    /* Avatar container: rounded kotak khas Flux & overflow hidden */
    [data-flux-avatar],
    [data-flux-sidebar-profile] [data-flux-avatar] {
        border-radius: 0.5rem !important; /* rounded-lg bawaan Flux */
        overflow: hidden !important;
    }
    
    /* Avatar image: rounded kotak khas Flux & ukuran pas */
    [data-flux-avatar] img,
    [data-flux-sidebar-profile] [data-flux-avatar] img {
        border-radius: 0.5rem !important;
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        display: block !important;
    }

    /* Cegah tombol disabled Flux menampilkan loading spinner otomatis */
    button[data-flux-button].no-disabled-spinner[disabled] [data-flux-loading-indicator],
    button[data-flux-button][data-no-disabled-spinner][disabled] [data-flux-loading-indicator] {
        display: none !important;
        opacity: 0 !important;
    }
    button[data-flux-button].no-disabled-spinner[disabled] > :not([data-flux-loading-indicator]),
    button[data-flux-button][data-no-disabled-spinner][disabled] > :not([data-flux-loading-indicator]) {
        opacity: 1 !important;
    }
    button[data-flux-button].no-disabled-spinner[data-flux-loading] [data-flux-loading-indicator],
    button[data-flux-button][data-no-disabled-spinner][data-flux-loading] [data-flux-loading-indicator] {
        display: flex !important;
        opacity: 1 !important;
    }
    button[data-flux-button].no-disabled-spinner[data-flux-loading] > :not([data-flux-loading-indicator]),
    button[data-flux-button][data-no-disabled-spinner][data-flux-loading] > :not([data-flux-loading-indicator]) {
        opacity: 0 !important;
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
    
    /* Flux modal backdrop blur */
    [data-flux-modal-overlay] {
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
    }
</style>

{{-- Script Inovindo: Force Light Mode Always --}}
<script>
    (function () {
        function forceLightMode() {
            // Always force light mode
            document.documentElement.classList.remove('dark');
            localStorage.setItem('flux-appearance', 'light');
            
            // Apply Inovindo theme if set
            if (localStorage.getItem('theme-inovindo') === 'true') {
                document.documentElement.classList.add('inovindo');
            } else {
                document.documentElement.classList.remove('inovindo');
            }
        }
        
        // Run immediately
        forceLightMode();
        
        // Run on Livewire navigation
        document.addEventListener('livewire:navigated', forceLightMode);
        
        // Run on DOM ready
        document.addEventListener('DOMContentLoaded', forceLightMode);
    }());
</script>
