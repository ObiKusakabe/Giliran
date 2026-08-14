{{--
  Settings pages pakai layout admin kita (sidebar sama).
  Breadcrumb otomatis: Dashboard > Pengaturan > [sub-halaman]
--}}
<x-layouts::admin :title="$title ?? 'Pengaturan'">
    {{ $slot }}
</x-layouts::admin>
