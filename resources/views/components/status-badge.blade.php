@props(['status'])

@php
$classes = match($status) {
    'siap', 'disetujui', 'aktif', 'tersedia'   => 'bg-emerald-100 text-emerald-700',
    'berhalangan', 'ditolak', 'nonaktif',
    'tidak_tersedia'                             => 'bg-red-100 text-red-700',
    'dibatalkan'                                 => 'bg-zinc-100 text-zinc-400 line-through',
    default                                      => 'bg-zinc-100 text-zinc-700', // menunggu
};

$label = match($status) {
    'aktif'           => 'Aktif',
    'nonaktif'        => 'Nonaktif',
    'tersedia'        => 'Tersedia',
    'tidak_tersedia'  => 'Tidak Tersedia',
    'siap'            => 'Siap',
    'berhalangan'     => 'Berhalangan',
    'menunggu'        => 'Menunggu',
    'disetujui'       => 'Disetujui',
    'ditolak'         => 'Ditolak',
    'dibatalkan'      => 'Dibatalkan',
    default           => ucfirst($status),
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium $classes"]) }}>
    {{ $label }}
</span>
