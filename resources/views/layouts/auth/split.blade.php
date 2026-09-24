@props([
    'title' => null,
    'skipSplash' => request()->routeIs('two-factor.login*'),
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        
        <style>
            /* Logo spinning animation */
            @keyframes spin-arrows {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .logo-splash .arrows-group {
                animation: spin-arrows 2s ease-in-out infinite;
            }
            
            /* Stop animation on tap */
            .logo-splash.stopping .arrows-group {
                animation: spin-arrows 0.6s ease-out 1 forwards;
            }
            
            /* Logo slide from center to top-left corner - subtle bounce */
            @keyframes logo-slide-to-corner {
                0% {
                    /* Start from true center - no manual offset needed with proper SVG */
                    transform: translate(calc(50vw - 50% - 1.5rem), calc(50vh - 50%)) scale(3.43);
                    /* scale(3.43) = size-24 (96px) / size-7 (28px) */
                }
                100% {
                    transform: translate(0, 0) scale(1);
                }
            }
            
            /* Fade in text nama web */
            @keyframes fade-in-text {
                0% { opacity: 0; transform: translateX(-8px); }
                100% { opacity: 1; transform: translateX(0); }
            }
            
            /* Fade in sapaan kiri */
            @keyframes fade-in-sapaan {
                0% { opacity: 0; transform: translateY(10px); }
                100% { opacity: 1; transform: translateY(0); }
            }
        </style>
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div 
            class="flex min-h-screen" 
            x-data="{ 
                mobileLayerActive: {{ $skipSplash ? 'false' : 'true' }},
                sheetHeight: 0,
                updateSheetHeight() {
                    if (this.$refs.whiteSheet) {
                        this.sheetHeight = this.$refs.whiteSheet.offsetHeight;
                    }
                }
            }" 
            x-init="
                if (window.innerWidth >= 640) mobileLayerActive = false;
                $nextTick(() => {
                    updateSheetHeight();
                    if (window.ResizeObserver && $refs.whiteSheet) {
                        new ResizeObserver(() => updateSheetHeight()).observe($refs.whiteSheet);
                    }
                });
            "
        >
            @php
                use App\Support\GreetingHelper;
                $salam = GreetingHelper::sapaanHari(); // "Selamat Pagi", "Selamat Sore", etc.
                $pesan = GreetingHelper::sapaanWaktu(); // Random message based on time
            @endphp

            @if(! $skipSplash)
            <!-- Mobile Layer 1: Splash Screen dengan Logo Center (< 640px only) -->
            <div 
                x-show="mobileLayerActive"
                x-transition:leave="transition-opacity ease-in duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="
                    $refs.centerLogo.querySelector('.logo-splash').classList.add('stopping');
                    setTimeout(() => mobileLayerActive = false, 600);
                "
                class="sm:hidden fixed inset-0 z-50 bg-gradient-to-br from-[#12314F] via-[#1a4268] to-[#1591D8] flex items-center justify-center px-8 text-white cursor-pointer"
            >
                <div class="flex flex-col items-center gap-6">
                    <!-- Logo besar di center dengan animasi -->
                    <div 
                        x-ref="centerLogo"
                        class="transition-all duration-700 ease-in-out"
                        :class="!mobileLayerActive ? 'opacity-0 scale-50' : 'opacity-100 scale-100'"
                    >
                        <x-app-logo-icon 
                            class="size-24 logo-splash"
                            style="filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.3));"
                        />
                    </div>
                    
                    <div class="text-center max-w-md">
                        <h1 class="text-4xl font-bold tracking-tight leading-tight">
                            {{ $salam }},
                        </h1>
                        <p class="mt-3 text-base text-blue-100/90 leading-relaxed">
                            {{ $pesan }}
                        </p>
                    </div>
                    
                    <p class="text-sm text-blue-200/70 animate-pulse pt-4">Ketuk untuk melanjutkan</p>
                </div>
            </div>
            @endif

            <!-- Mobile Layer Background Biru - Always Visible (< 640px only) -->
            <div class="sm:hidden fixed inset-0 z-30 bg-gradient-to-br from-[#12314F] via-[#1a4268] to-[#1591D8]">
                <!-- Logo & Brand di pojok kiri atas - GESER dari center jika ada splash -->
                <div 
                    x-show="!mobileLayerActive"
                    class="absolute top-6 left-6 origin-center"
                    @if(! $skipSplash)
                    style="
                        animation: logo-slide-to-corner 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
                    "
                    @endif
                >
                    <a href="{{ route('home') }}" class="group flex items-center gap-2.5" wire:navigate>
                        <x-app-logo-icon class="size-7 text-white transition-transform group-hover:scale-105" />
                        <span 
                            class="text-base font-bold tracking-tight text-white {{ $skipSplash ? 'opacity-100' : 'opacity-0' }}"
                            @if(! $skipSplash)
                            style="animation: fade-in-text 0.3s ease-out 0.7s forwards;"
                            @endif
                        >{{ config('app.name', 'Giliran') }}</span>
                    </a>
                </div>

                <!-- Sapaan Text - Tepat di tengah antara logo atas dan sheet putih -->
                <div 
                    x-show="!mobileLayerActive"
                    class="absolute px-6 left-0 right-0 flex items-center text-white pointer-events-none"
                    :style="sheetHeight > 0 ? ('top: 4.5rem; bottom: ' + sheetHeight + 'px;') : 'top: 4.5rem; bottom: 58vh;'"
                >
                    <h1 
                        class="text-2xl min-[400px]:text-3xl font-bold tracking-tight text-left leading-tight {{ $skipSplash ? 'opacity-100' : 'opacity-0' }}"
                        @if(! $skipSplash)
                        style="animation: fade-in-sapaan 0.5s ease-out 0.9s forwards;"
                        @endif
                    >
                        {{ $salam }},<br>{{ $pesan }}
                    </h1>
                </div>
            </div>

            <!-- Mobile Layer 2: White Sheet Slides Up (< 640px only) -->
            <div 
                x-ref="whiteSheet"
                x-show="!mobileLayerActive"
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                class="sm:hidden fixed inset-x-0 bottom-0 z-50 bg-white dark:bg-zinc-900 rounded-t-[32px] shadow-2xl pb-safe"
                style="max-height: 75vh; height: auto;"
            >
                <div class="h-full flex flex-col">
                    <!-- Content Area with Scroll -->
                    <div class="flex-1 overflow-y-auto px-6 pt-8 pb-4">
                        {{ $slot }}
                    </div>

                    <!-- Footer -->
                    <div class="border-t border-zinc-200 dark:border-zinc-700 px-6 py-3 text-center bg-zinc-50/50 dark:bg-zinc-800/50">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            © {{ date('Y') }} PT Inovindo Digital Media
                        </p>
                    </div>
                </div>
            </div>

            <!-- Desktop/Tablet: Original Split Layout (>= 640px) -->
            <div class="max-sm:hidden flex-1 flex justify-center items-center p-6 sm:p-8">
                <div class="w-80 max-w-80 space-y-6">
                    <!-- Brand Icon & Web Name (Desktop) -->
                    <div class="flex justify-center opacity-90 hover:opacity-100 transition">
                        <a href="{{ route('home') }}" class="group flex items-center gap-3" wire:navigate>
                            <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white transition-transform group-hover:scale-105" />
                            <span class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ config('app.name', 'Giliran') }}</span>
                        </a>
                    </div>

                    {{ $slot }}
                </div>
            </div>

            <!-- Right: Image / Decorative Card Slideshow with Lazy Background Loading -->
            <div 
                class="flex-1 p-4 max-lg:hidden"
                x-data="{
                    active: 0,
                    timer: null,
                    slides: [
                        {
                            src: '/images/inovindooffice.webp',
                            quote: 'Sistem otomasi rotasi kerja dan fasilitas kantor yang adil, efisien, dan transparan.',
                            subtitle: 'Sistem Manajemen Jadwal Internal',
                            loaded: true
                        },
                        {
                            src: '/images/office_slide_2.jpg',
                            quote: 'Pengelolaan alokasi ruangan & jadwal WFO fleksibel berbasis kapasitas real-time.',
                            subtitle: 'Alokasi Ruangan & Workstation',
                            loaded: false
                        },
                        {
                            src: '/images/office_slide_3.jpg',
                            quote: 'Distribusi tugas briefing pagi dan rotasi harian yang tertib dan merata.',
                            subtitle: 'Rotasi Aktivitas Harian',
                            loaded: false
                        }
                    ],
                    init() {
                        // Background preload images for slide 2 & 3 without blocking main UI
                        this.slides.forEach((s, idx) => {
                            if (idx === 0) return;
                            const img = new Image();
                            img.src = s.src;
                            img.onload = () => { s.loaded = true; };
                        });

                        // Start auto slide interval (6 seconds), only advances if next slide is fully loaded
                        this.timer = setInterval(() => {
                            const next = (this.active + 1) % this.slides.length;
                            if (this.slides[next].loaded) {
                                this.active = next;
                            }
                        }, 6000);
                    },
                    goToSlide(index) {
                        if (this.slides[index].loaded || index === 0) {
                            this.active = index;
                        }
                    }
                }"
            >
                <div class="text-white relative rounded-2xl h-full w-full bg-gradient-to-br from-[#12314F] via-[#1a4268] to-[#0c2033] flex flex-col items-start justify-end p-12 xl:p-16 overflow-hidden shadow-2xl border border-white/10">
                    <!-- Background Images with Smooth Cross-Fade -->
                    <template x-for="(slide, index) in slides" :key="index">
                        <div 
                            class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000 ease-in-out pointer-events-none"
                            :style="'background-image: url(' + slide.src + ');'"
                            :class="active === index ? 'opacity-100' : 'opacity-0'"
                            :fetchpriority="index === 0 ? 'high' : 'auto'"
                        ></div>
                    </template>

                    <!-- Dark Gradient Overlay for optimal readability -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0c2033]/95 via-[#12314F]/50 to-transparent/20 pointer-events-none"></div>

                    <!-- Slide Content -->
                    <div class="relative z-10 space-y-4 max-w-lg">
                        @php
                            $isRegister = request()->routeIs('register*');
                        @endphp

                        @if ($isRegister)
                            <!-- Sign Up / Register Heading & Description -->
                            <h1 class="text-4xl xl:text-5xl font-bold tracking-tight text-white leading-tight">
                                {{ $salam }},<br>{{ $pesan }}
                            </h1>
                            <p class="text-sm xl:text-base text-blue-100/85 leading-relaxed max-w-md">
                                Daftarkan akun baru Anda untuk mulai mengelola jadwal giliran kerja, briefing tim, dan fasilitas kantor bersama Giliran.
                            </p>
                        @else
                            <!-- Sign In / Login Heading & Description -->
                            <h1 class="text-4xl xl:text-5xl font-bold tracking-tight text-white leading-tight">
                                {{ $salam }},<br>{{ $pesan }}
                            </h1>
                            <p class="text-sm xl:text-base text-blue-100/85 leading-relaxed max-w-md">
                                Sistem otomasi rotasi kerja WFO, jadwal petugas adzan & kajian, serta alokasi ruangan terintegrasi PT Inovindo Digital Media.
                            </p>
                        @endif

                        <div class="flex items-center justify-between pt-4">
                            <div class="text-xs font-medium text-blue-200/70">
                                PT Inovindo Digital Media
                            </div>

                            <!-- Slide Dot Indicators -->
                            <div class="flex items-center gap-2">
                                <template x-for="(slide, index) in slides" :key="'dot-' + index">
                                    <button 
                                        type="button"
                                        @click="goToSlide(index)"
                                        class="h-2 rounded-full transition-all duration-300 cursor-pointer"
                                        :class="active === index ? 'w-6 bg-white' : 'w-2 bg-white/40 hover:bg-white/70'"
                                        :aria-label="'Slide ' + (index + 1)"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
