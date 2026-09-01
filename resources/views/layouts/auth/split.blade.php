<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="flex min-h-screen">
            <!-- Left: Input / Form Section -->
            <div class="flex-1 flex justify-center items-center p-6 sm:p-8">
                <div class="w-80 max-w-80 space-y-6">
                    <!-- Brand Icon & Web Name (Transparent logo, theme-adaptive) -->
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
                            $hour = now()->setTimezone('Asia/Jakarta')->hour;
                            if ($hour >= 4 && $hour < 11) {
                                $salam = 'Selamat Pagi';
                            } elseif ($hour >= 11 && $hour < 15) {
                                $salam = 'Selamat Siang';
                            } elseif ($hour >= 15 && $hour < 18) {
                                $salam = 'Selamat Sore';
                            } else {
                                $salam = 'Selamat Malam';
                            }
                            $isRegister = request()->routeIs('register*');
                        @endphp

                        @if ($isRegister)
                            <!-- Sign Up / Register Heading & Description -->
                            <h1 class="text-4xl xl:text-5xl font-bold tracking-tight text-white leading-tight">
                                {{ $salam }},<br>join us!
                            </h1>
                            <p class="text-sm xl:text-base text-blue-100/85 leading-relaxed max-w-md">
                                Daftarkan akun baru Anda untuk mulai mengelola jadwal giliran kerja, briefing tim, dan fasilitas kantor bersama Giliran.
                            </p>
                        @else
                            <!-- Sign In / Login Heading & Description -->
                            <h1 class="text-4xl xl:text-5xl font-bold tracking-tight text-white leading-tight">
                                {{ $salam }},<br>welcome!
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
