{{-- Dev Mode Floating Editor - Bottom Right --}}
<div 
    x-data="devModeFloating()"
    class="fixed bottom-4 right-4 z-50"
    x-cloak
>
    {{-- Floating Button --}}
    <button
        @click="isOpen = !isOpen"
        class="flex items-center gap-2 px-4 py-2.5 rounded-full shadow-lg transition-all"
        :class="devMode 
            ? 'bg-amber-500 hover:bg-amber-600 text-white' 
            : 'bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700'"
    >
        <flux:icon icon="beaker" class="size-5" />
        <div class="flex flex-col items-start text-left">
            <span class="text-xs font-semibold leading-tight" x-text="devMode ? 'Dev Mode ON' : 'Dev Mode OFF'"></span>
            <span class="text-[10px] opacity-80 leading-tight" x-text="formatDisplayTime()"></span>
        </div>
    </button>

    {{-- Floating Panel --}}
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="isOpen = false"
        class="absolute bottom-full right-0 mb-2 w-80 bg-white dark:bg-zinc-800 rounded-xl shadow-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden"
    >
        {{-- Header --}}
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:icon icon="beaker" class="size-5 text-amber-500" />
                    <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">Dev Mode Time Editor</span>
                </div>
                <button 
                    @click="isOpen = false"
                    class="p-1 rounded-md hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
                >
                    <flux:icon icon="x-mark" class="size-4 text-zinc-500" />
                </button>
            </div>
        </div>

        {{-- Content --}}
        <div class="p-4 space-y-4 max-h-[70vh] overflow-y-auto">
            {{-- Toggle Switch --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Enable Dev Mode</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input 
                        type="checkbox" 
                        x-model="devMode"
                        class="sr-only peer"
                    >
                    <div class="w-11 h-6 bg-zinc-200 dark:bg-zinc-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 dark:peer-focus:ring-amber-800 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-zinc-600 peer-checked:bg-amber-500"></div>
                </label>
            </div>

            {{-- Time Input --}}
            <div x-show="devMode" x-transition x-cloak>
                <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1.5">
                    Simulasi Waktu
                </label>
                <input
                    type="datetime-local"
                    x-model="devTime"
                    class="w-full px-3 py-2 text-sm bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-600 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent dark:text-zinc-100"
                    placeholder="Pilih tanggal dan waktu"
                >
                
                {{-- Quick Presets --}}
                <div class="mt-2 space-y-1.5">
                    <div class="text-[10px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Quick Presets</div>
                    
                    {{-- Notulen Pagi Presets --}}
                    <div class="text-[9px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mt-2 mb-1">Notulen Pagi (08:50-11:00)</div>
                    <button
                        @click="devTime = '2026-09-21T08:45'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-md transition-colors text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800"
                    >
                        ⏰ Sebelum Window Pagi (08:45)
                    </button>
                    <button
                        @click="devTime = '2026-09-21T09:30'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/30 rounded-md transition-colors text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800"
                    >
                        ✅ Dalam Window Pagi (09:30)
                    </button>
                    <button
                        @click="devTime = '2026-09-21T11:05'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-md transition-colors text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800"
                    >
                        ❌ Lewat Deadline Pagi (11:05)
                    </button>
                    
                    {{-- Notulen Sore Presets --}}
                    <div class="text-[9px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mt-2 mb-1">Notulen Sore (16:50-18:00)</div>
                    <button
                        @click="devTime = '2026-09-21T16:45'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-md transition-colors text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800"
                    >
                        ⏰ Sebelum Window Sore (16:45)
                    </button>
                    <button
                        @click="devTime = '2026-09-21T17:15'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/30 rounded-md transition-colors text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800"
                    >
                        ✅ Dalam Window Sore (17:15)
                    </button>
                    <button
                        @click="devTime = '2026-09-21T18:05'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-md transition-colors text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800"
                    >
                        ❌ Lewat Deadline Sore (18:05)
                    </button>
                    
                    {{-- General Presets --}}
                    <div class="text-[9px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mt-2 mb-1">Waktu Umum</div>
                    <button
                        @click="devTime = '2026-09-21T09:00'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-md transition-colors text-zinc-700 dark:text-zinc-300"
                    >
                        🌅 Pagi (09:00)
                    </button>
                    <button
                        @click="devTime = '2026-09-21T13:00'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-md transition-colors text-zinc-700 dark:text-zinc-300"
                    >
                        ☀️ Siang (13:00)
                    </button>
                    <button
                        @click="devTime = '2026-09-21T00:00'"
                        class="w-full text-left px-2.5 py-1.5 text-xs bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-md transition-colors text-zinc-700 dark:text-zinc-300"
                    >
                        🌙 Tengah Malam (00:00)
                    </button>
                </div>

                {{-- Current Simulated Time Display --}}
                <div class="mt-3 p-2.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <div class="text-[10px] font-semibold text-amber-700 dark:text-amber-400 mb-0.5">Current Simulated Time:</div>
                    <div class="text-xs font-mono text-amber-900 dark:text-amber-300" x-text="formatDisplayTime()"></div>
                </div>
            </div>

            {{-- Info --}}
            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 leading-relaxed">
                ⚠️ Dev Mode hanya untuk testing. Waktu akan tersimulasi di seluruh aplikasi (jadwal, deadline, notifikasi).
            </div>
            
            {{-- Action Buttons --}}
            <div class="flex gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-700"
                x-data="{ saving: false }"
            >
                <button
                    @click="
                        if (devMode && devTime && !saving) {
                            saving = true;
                            Promise.resolve(applyDevMode()).finally(() => {
                                setTimeout(() => {
                                    window.location.reload();
                                }, 300);
                            });
                        }
                    "
                    :disabled="!devMode || !devTime || saving"
                    class="flex-1 px-3 py-2 text-sm font-semibold rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="devMode && devTime 
                        ? 'bg-amber-500 hover:bg-amber-600 text-white' 
                        : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-400 dark:text-zinc-500'"
                >
                    <span class="flex items-center justify-center gap-1.5">
                        <template x-if="!saving">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </template>
                        <template x-if="saving">
                            <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                        </template>
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan & Refresh'"></span>
                    </span>
                </button>
                <button
                    @click="
                        if (!saving) {
                            saving = true;
                            devMode = false; 
                            devTime = ''; 
                            resetDevMode();
                            setTimeout(() => {
                                window.location.href = window.location.href;
                            }, 500);
                        }
                    "
                    :disabled="saving"
                    class="px-3 py-2 text-sm font-semibold rounded-lg bg-zinc-200 dark:bg-zinc-700 hover:bg-zinc-300 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-300 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Reset ke waktu real dan refresh"
                >
                    <template x-if="!saving">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </template>
                    <template x-if="saving">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                    </template>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function devModeFloating() {
    return {
        isOpen: false,
        devMode: localStorage.getItem('devMode') === 'true',
        devTime: localStorage.getItem('devTime') || '',
        saving: false,
        
        init() {
            // Apply dev mode on page load
            if (this.devMode && this.devTime) {
                this.applyDevMode();
            }
            
            // Watch for changes
            this.$watch('devMode', value => {
                localStorage.setItem('devMode', value);
                if (!value) {
                    // Reset to real time when disabled
                    localStorage.removeItem('devTime');
                    this.devTime = '';
                    this.resetDevMode();
                }
            });
            
            this.$watch('devTime', value => {
                if (this.devMode && value) {
                    localStorage.setItem('devTime', value);
                    this.applyDevMode();
                }
            });
        },
        
        applyDevMode() {
            const timeString = this.devTime.replace('T', ' ') + ':00';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                || document.querySelector('meta[name="csrf-token"]')?.content 
                || '';
            
            return fetch('/api/dev-mode/set-time', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ time: timeString })
            })
            .then(response => {
                if (response.ok) {
                    console.log('Dev mode time set:', timeString);
                }
            })
            .catch(error => console.error('Failed to set dev mode time:', error));
        },
        
        resetDevMode() {
            if (window.Livewire) {
                window.Livewire.dispatch('dev-mode-time-changed', { time: null });
            }
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                || document.querySelector('meta[name="csrf-token"]')?.content 
                || '';
            return fetch('/api/dev-mode/reset-time', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                }
            })
            .catch(error => console.error('Failed to reset dev mode time:', error));
        },
        
        formatDisplayTime() {
            if (!this.devTime) return 'Real Time';
            return new Date(this.devTime).toLocaleString('id-ID', { 
                dateStyle: 'short', 
                timeStyle: 'short' 
            });
        }
    };
}
</script>

