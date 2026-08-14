<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Inovindo theme: apply sebelum render supaya tidak flash --}}
<script>
    (function() {
        if (localStorage.getItem('theme-inovindo') === 'true') {
            document.documentElement.classList.add('inovindo', 'dark');
        }
    })();
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<style>
    /* Modal backdrop blur — targets Flux modal overlay */
    [data-flux-modal-overlay],
    dialog::backdrop {
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
    }

    /* Fallback: target semua fixed overlay dengan background gelap */
    .fixed.inset-0.bg-black\/50,
    .fixed.inset-0.bg-black\/40,
    .fixed.inset-0[style*="background"] {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
</style>
