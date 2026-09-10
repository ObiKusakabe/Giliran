<div>
    @if ($showModal && $briefingData)
        <flux:modal wire:model="showModal" class="max-w-md" closeable>
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <flux:icon.clipboard-document-list class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg" class="text-blue-900 dark:text-blue-100">
                            Reminder: Isi Notulensi Briefing
                        </flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Jangan lupa untuk mengisi notulensi briefing hari ini
                        </flux:text>
                    </div>
                </div>

                <div class="bg-blue-50 dark:bg-blue-950/30 rounded-lg p-4 space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                        <flux:icon.calendar class="w-4 h-4 text-blue-600" />
                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ $briefingData['tanggal'] }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <flux:icon.clock class="w-4 h-4 text-blue-600" />
                        <span class="font-medium text-blue-900 dark:text-blue-100">Sesi {{ $briefingData['sesi'] }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <flux:icon.user-group class="w-4 h-4 text-blue-600" />
                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ $briefingData['tim'] }}</span>
                    </div>
                </div>

                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 flex items-start gap-1.5">
                    <flux:icon.light-bulb class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                    <span>
                        Popup ini hanya muncul sekali per sesi. 
                        @if ($briefingData['sesi'] === 'Pagi')
                            <strong>Sesi Pagi:</strong> Pengisian dibuka jam 08:50 - 11:00 WIB.
                        @else
                            <strong>Sesi Sore:</strong> Pengisian dibuka jam 16:50 - 18:00 WIB.
                        @endif
                        Silakan isi notulensi sebelum waktu habis.
                    </span>
                </flux:text>

                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button variant="ghost" wire:click="dismiss">
                        Nanti Saja
                    </flux:button>
                    <flux:button variant="primary" wire:click="goToNotulen" icon="arrow-right">
                        Isi Sekarang
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
