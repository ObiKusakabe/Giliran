# 📋 IMPLEMENTATION PLAN - UI/UX Comprehensive Overhaul

**Project**: Sistem Manajemen Jadwal Internal - PT Inovindo Digital Media  
**Version**: 0.10.0 (Post UI/UX Fixes)  
**Created**: 27 Agustus 2026  
**Planning Agent**: Claude (Kiro)  
**Execution**: Phase 1 (Batch 8,7,2) - Claude | Phase 2 (Batch 1,3,4,5,6) - Gemini  

---

## 🎯 PROBLEM STATEMENT

Berdasarkan feedback user dan UI/UX testing, ditemukan **12 major issues** + **1 new feature** yang perlu diperbaiki:

### Issues Fixed (Phase 1 - Kiro) ✅
1. **Button hover states** primary/danger berubah jadi putih (text unreadable) 
2. **Sidebar collapsed state** rusak (horizontal scroll, square buttons tidak konsisten, animasi instant)
3. **Date range picker** z-index issue di modal (calendar tidak muncul di atas modal backdrop)

### Issues Remaining (Phase 2 - Gemini) 🔵
4. **Authentication pages** belum mengikuti Flux demo split-screen pattern
5. **Settings page** masih pakai tabs horizontal, tidak sesuai Flux demo (1-page multi-section)
6. **Notifikasi** sebagai page terpisah, seharusnya modal dropdown top-right
7. **Konfirmasi password page** belum ada tema Inovindo
8. **Export** ada di menu terpisah, seharusnya button di dashboard table
9. **Generate Jadwal** terlalu kompleks (date input redundant)
10. **Dashboard** tidak menampilkan kalender view (terpisah di `/kalender`)
11. **Master Data pages** tidak ada quick info cards (analytics)
12. **[NEW FEATURE]** Auto-generate akun tim untuk instansi magang

---

## 📊 REQUIREMENTS SUMMARY

### User Preferences
- **UI Framework**: Flux UI Pro (90% coverage, avoid custom components)
- **Theme**: Inovindo color palette (Navy #12314F, Blue #3B71CA, Green #2FA84F, Orange #F2A340)
- **Animation**: **Ease-out gentle** (cepat di awal, lambat di akhir, **no bounce**)
  - Timing: 300ms
  - Easing: `cubic-bezier(0.4, 0.0, 0.2, 1)`
  - Behavior: Fast start, soft landing (seperti "mendaratnya melambat")
- **Language**: Bahasa Indonesia untuk UI text
- **Mobile**: Responsive + touch support

### Technical Constraints
- Laravel 13 + Livewire 4 + Flux UI Pro
- Tailwind v4 (alpha.25)
- Alpine.js 3.14
- PHP 8.3
- No breaking changes to existing features

---

## 🏗️ BATCH STRUCTURE

### Phase 1: Kiro Execution ✅ (COMPLETE)
- ✅ **Batch 8**: Button Hover Audit & Fix (~1.5 credits)
- ✅ **Batch 7**: Range Picker Z-Index Fix (~0.5 credits)
- ✅ **Batch 2**: Sidebar Improvements (~2 credits)

**Total Credits Used**: ~4 credits  
**Status**: Complete — awaiting user testing

### Phase 2: Gemini Execution 🔵 (PENDING)
- 🔵 **Batch 1**: Auth Pages Redesign (~4-5 credits)
- 🔵 **Batch 3**: Dashboard Restructure (~5-7 credits)
- 🔵 **Batch 4**: Master Data Cards + Auto-Generate Akun (~5-6 credits)
- 🔵 **Batch 5**: Settings Page Restructure (~3-4 credits)
- 🔵 **Batch 6**: Notifikasi Modal Dropdown (~4-5 credits)

**Estimated Credits**: ~22-27 credits

---

## ✅ PHASE 1 EXECUTION SUMMARY (COMPLETE)

### Batch 8: Button Hover Fix
**Problem**: Primary/danger buttons jadi putih saat hover, text unreadable.

**Solution Applied**:
```css
/* Strengthened selectors - catch all Flux variants */
button[data-flux-button][variant="primary"],
button[data-flux-button][class*="primary"] {
    background-color: rgb(37 99 235) !important;
    border-color: rgb(37 99 235) !important;
}

button[data-flux-button][variant="primary"]:hover {
    background-color: rgb(29 78 216) !important; /* blue-700 */
}

/* Same for danger variant (red-600 → red-700) */
```

**Files Changed**:
- `resources/views/partials/head.blade.php`

**Testing**: User needs to hover all buttons (login, tambah, hapus, etc.) and confirm colors stay blue/red.

---

### Batch 7: Range Picker Z-Index
**Problem**: Date range picker tidak muncul di atas modal backdrop.

**Solution Applied**:
```javascript
onOpen: function(selectedDates, dateStr, instance) {
    const fpContainer = instance.calendarContainer;
    if (fpContainer) {
        fpContainer.style.zIndex = '10000'; // Above modal (9999)
    }
}
```

**Files Changed**:
- `resources/views/components/date-range-picker.blade.php`

**Testing**: User needs to open Periode WFO modal, click date picker, verify calendar appears on top.

---

### Batch 2: Sidebar Improvements
**Problem**: Collapsed state rusak (horizontal scroll, buttons tidak square, animasi instant).

**Solution Applied**:

**Animation** (300ms ease-out, no bounce):
```css
[data-flux-sidebar] {
    transition: width 300ms cubic-bezier(0.4, 0.0, 0.2, 1) !important;
}
```

**Square buttons** (40x40px, rounded-lg):
```css
[data-flux-sidebar][data-flux-collapsed="true"] button[data-flux-sidebar-item] {
    width: 2.5rem !important;
    height: 2.5rem !important;
    border-radius: 0.5rem !important;
    justify-content: center !important;
}
```

**Files Changed**:
- `resources/views/partials/head.blade.php`

**Testing**: User needs to toggle sidebar, verify:
- Smooth animation (no instant snap, no bounce)
- Collapsed buttons are perfect squares
- Active state maintains square shape
- No horizontal scroll

---

## 🔵 PHASE 2 REMAINING BATCHES (FOR GEMINI)

---

## **BATCH 1: AUTH PAGES REDESIGN**

### Problem
Login, signup, dan confirm password pages belum mengikuti Flux demo split-screen pattern.

### Requirements
1. **Login page**: Split-screen (form left, decorative pattern right), passkey support
2. **Signup page**: Split-screen matching login style
3. **Confirm password**: Inovindo color theme, simple centered layout
4. **Forgot password**: SKIP (tidak perlu redesign)
5. **Background**: Abstract pattern SVG placeholder

### Tasks

**Task 1.1: Login Page Split-Screen**
- **File**: `resources/views/pages/auth/⚡login.blade.php`
- **Changes**:
  ```blade
  <div class="min-h-screen grid lg:grid-cols-2">
      <!-- Left: Form -->
      <div class="flex items-center justify-center p-8">
          <div class="w-full max-w-md">
              <!-- Form content (existing) -->
          </div>
      </div>
      
      <!-- Right: Decorative -->
      <div class="hidden lg:flex items-center justify-center bg-gradient-to-br from-blue-600 to-blue-800">
          <img src="/images/auth-pattern.svg" alt="" class="w-3/4 opacity-20" />
      </div>
  </div>
  ```
- **Mobile**: Stacked layout (< lg breakpoint)
- **Passkey**: Reuse `<x-passkey-verify>` component (conditional render)

**Task 1.2: Signup Page Split-Screen**
- **File**: `resources/views/pages/auth/⚡register.blade.php`
- **Changes**: Same split-screen structure as login

**Task 1.3: Confirm Password Theme**
- **File**: `resources/views/pages/auth/⚡confirm-password.blade.php`
- **Changes**: Update colors (Navy heading, Blue button), keep centered layout

**Task 1.4: Create Abstract Pattern**
- **File**: `public/images/auth-pattern.svg` (create new)
- **Content**: Geometric pattern (circles/lines/grid), monochrome, < 50KB

---

## **BATCH 3: DASHBOARD RESTRUCTURE**

### Problem
1. Dashboard tidak menampilkan kalender (terpisah di `/kalender`)
2. Export di menu terpisah, seharusnya button di dashboard
3. Generate Jadwal terlalu kompleks (date input redundant)

### Requirements
1. **Move kalender** dari `/kalender` ke dashboard
2. **Export button** di dashboard (no date input, auto periode aktif, 1 PDF semua jadwal)
3. **Generate button** simplified (modal konfirmasi: "Generate jadwal untuk periode X?")

### Tasks

**Task 3.1: Move Kalender to Dashboard**
- **Files**: 
  - `resources/views/pages/admin/⚡dashboard.blade.php` (update)
  - `app/Livewire/Admin/Dashboard.php` (add calendar logic)
- **Changes**: Copy FullCalendar integration dari `/kalender` page

**Task 3.2: Simplify Generate Jadwal**
- **Changes**:
  - Remove date range picker input
  - Button click: Open modal
  - Modal content:
    ```
    Generate jadwal untuk periode aktif?
    Periode: [Nama Periode]
    Tanggal: Senin–Sabtu, 3 Feb–8 Feb 2026
    
    [Batal] [Generate]
    ```
  - On confirm: `generateJadwal()` auto-uses `PeriodeWfo::where('status', 'aktif')->first()`

**Task 3.3: Add Export Button**
- **Changes**:
  - Button: `<flux:button variant="primary" icon="arrow-down-tray" wire:click="exportPDF">`
  - No dropdown, no date input
  - Backend: Update `ExportController@exportPdf()`
  - Remove `$jenis` parameter (always "semua")
  - PDF structure: 1 file dengan 4 sections (Adzan, Kitab, Ruangan, Briefing)

**Task 3.4: Handle Route `/kalender`**
- **File**: `routes/web.php`
- **Changes**: `Route::redirect('/admin/kalender', '/admin/dashboard')->name('admin.kalender');`

---

## **BATCH 4: MASTER DATA QUICK INFO CARDS + AUTO-GENERATE AKUN**

### Problem
1. Master Data pages tidak ada analytics/quick info
2. Belum ada fitur auto-generate akun untuk tim magang

### Requirements
1. **Quick info cards**: 4 cards horizontal di atas tabel (Flux soft variant, icons)
2. **Auto-generate akun**:
   - Format: `tahun_bulan_nourut` (contoh: `2026_08_001`)
   - Reset per tahun (bukan per bulan)
   - Password: `inovindojaya`
   - UI: Button "Generate Akun" di Manajemen Tim
   - Ganti password: Opsional (tidak dipaksa)

### Tasks

**Task 4.1: Add Quick Info Cards - Tim**
- **File**: `resources/views/pages/admin/⚡manajemen-tim.blade.php`
- **Changes**:
  ```blade
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <flux:card variant="soft" class="p-4">
          <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100">
                  <flux:icon icon="user-group" class="h-6 w-6 text-blue-600" />
              </div>
              <div>
                  <flux:text class="text-sm text-zinc-500">Total Tim</flux:text>
                  <flux:heading size="lg">{{ $totalTim }}</flux:heading>
              </div>
          </div>
      </flux:card>
      <!-- 3 cards lainnya: Tim Aktif, Tim Inactive, Periode Aktif -->
  </div>
  ```

**Task 4.2: Repeat for Personil & Ruangan**
- Same card structure, different metrics

**Task 4.3: Create TimAccountGenerator Service**
- **File**: `app/Services/TimAccountGenerator.php` (create new)
- **Methods**:
  ```php
  public function generateUsername(): string {
      $year = now()->year;
      $month = now()->format('m');
      
      // Find highest no_urut untuk tahun ini
      $lastUser = User::where('email', 'LIKE', "{$year}_%")
          ->orderBy('email', 'desc')
          ->first();
      
      $noUrut = 1;
      if ($lastUser) {
          preg_match('/_(\d+)$/', $lastUser->email, $matches);
          $noUrut = isset($matches[1]) ? (int)$matches[1] + 1 : 1;
      }
      
      return sprintf('%s_%s_%03d', $year, $month, $noUrut);
  }
  
  public function createAccount(Tim $tim): User {
      $username = $this->generateUsername();
      
      return User::create([
          'name' => $tim->nama_tim,
          'email' => $username . '@inovindo.local',
          'password' => Hash::make('inovindojaya'),
          'role' => 'tim',
          'tim_id' => $tim->id,
      ]);
  }
  ```

**Task 4.4: Add "Generate Akun" Button**
- **File**: `resources/views/pages/admin/⚡manajemen-tim.blade.php`
- **Changes**: 
  - Button di tabel row actions (conditional: only if `$tim->user_id === null`)
  - Modal konfirmasi: Preview username + password
  - Wire: `generateAkun($timId)`

**Task 4.5: Wire Logic**
- **File**: `app/Livewire/Admin/ManajemenTim.php`
- **Method**:
  ```php
  public function generateAkun($timId) {
      $tim = Tim::findOrFail($timId);
      
      if ($tim->user_id) {
          $this->dispatch('notify', type: 'error', message: 'Tim ini sudah memiliki akun.');
          return;
      }
      
      $generator = app(TimAccountGenerator::class);
      $user = $generator->createAccount($tim);
      
      $tim->update(['user_id' => $user->id]);
      
      $this->dispatch('notify', type: 'success', message: "Akun berhasil dibuat! Username: {$user->email}");
  }
  ```

**Task 4.6: Migration (If Needed)**
- Check if `tim` table has `user_id` column, add if missing

---

## **BATCH 5: SETTINGS PAGE RESTRUCTURE**

### Problem
Settings masih pakai tabs horizontal. User request: 1-page multi-section.

### Requirements
1. Delete tabs navigation
2. 1-page layout: Profile + Security + Appearance sections (all in one scroll)
3. Section separators via card boundaries

### Tasks

**Task 5.1: Remove Tabs**
- **File**: `resources/views/pages/settings/⚡profile.blade.php` (or main settings layout)
- **Changes**: Delete `<flux:tabs>` component, remove tab switching logic

**Task 5.2: Restructure as 3 Cards**
- **Layout**:
  ```blade
  <div class="max-w-4xl mx-auto space-y-8">
      <!-- Card 1: Profile -->
      <flux:card>
          <flux:heading size="lg">Profil</flux:heading>
          <!-- Profile form -->
      </flux:card>
      
      <!-- Card 2: Security -->
      <flux:card>
          <flux:heading size="lg">Keamanan</flux:heading>
          <!-- Security forms -->
      </flux:card>
      
      <!-- Card 3: Appearance -->
      <flux:card>
          <flux:heading size="lg">Tampilan</flux:heading>
          <!-- Theme toggles -->
      </flux:card>
  </div>
  ```

**Task 5.3: Update Breadcrumb**
- **File**: `resources/views/layouts/admin.blade.php`
- **Changes**: Settings breadcrumb: "Pengaturan" only (no sub-sections)

**Task 5.4: Consolidate Routes**
- **File**: `routes/web.php`
- **Changes**: Single route `/profile` untuk all settings (delete separate routes jika ada)

---

## **BATCH 6: NOTIFIKASI MODAL DROPDOWN**

### Problem
Notifikasi sekarang page terpisah. User request: Modal dropdown di navbar top-right.

### Requirements
1. Bell icon di navbar (before profile)
2. Badge count (unread notifications)
3. Dropdown: Max 5 items, scroll jika lebih
4. "Mark all read" button
5. Notification types: Personil berhalangan only (Adzan, Kitab, Briefing) — skip Ruangan

### Tasks

**Task 6.1: Add Bell Icon to Navbar**
- **File**: `resources/views/layouts/admin.blade.php`
- **Changes**:
  ```blade
  <nav class="flex items-center gap-4">
      <!-- Bell icon dengan badge -->
      <flux:dropdown>
          <flux:dropdown.trigger>
              <button class="relative">
                  <flux:icon icon="bell" class="h-5 w-5" />
                  @if($unreadCount > 0)
                      <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] text-white">
                          {{ $unreadCount }}
                      </span>
                  @endif
              </button>
          </flux:dropdown.trigger>
          
          <flux:dropdown.content class="w-80">
              <livewire:notifikasi-dropdown />
          </flux:dropdown.content>
      </flux:dropdown>
      
      <!-- Profile dropdown (existing) -->
  </nav>
  ```

**Task 6.2: Create NotifikasiDropdown Component**
- **File**: `app/Livewire/NotifikasiDropdown.php` (create new)
- **Logic**:
  ```php
  public function render() {
      $notifikasi = Notifikasi::where('user_id', auth()->id())
          ->latest()
          ->take(5)
          ->get();
      
      return view('livewire.notifikasi-dropdown', [
          'notifikasi' => $notifikasi,
          'unreadCount' => Notifikasi::where('user_id', auth()->id())
              ->whereNull('dibaca_pada')
              ->count(),
      ]);
  }
  
  public function markAllRead() {
      Notifikasi::where('user_id', auth()->id())
          ->whereNull('dibaca_pada')
          ->update(['dibaca_pada' => now()]);
  }
  ```

**Task 6.3: Create Dropdown View**
- **File**: `resources/views/livewire/notifikasi-dropdown.blade.php` (create new)
- **Layout**: Header + scroll area (max-h-80) + optional "Lihat Semua" link

**Task 6.4: Update Notification Logic**
- **File**: `app/Console/Commands/KirimNotifikasiH1.php`
- **Changes**: Ensure only personil berhalangan notifications created (skip Ruangan)

---

## 📝 TESTING CHECKLIST (GEMINI)

### Per-Batch Testing
- [ ] **Batch 1**: Auth pages responsive, passkey works, mobile stacks properly
- [ ] **Batch 3**: Kalender displays, export generates PDF, generate works without date input
- [ ] **Batch 4**: Cards show correct counts, auto-generate creates user with correct format
- [ ] **Batch 5**: Settings 1-page scrollable, all features work
- [ ] **Batch 6**: Dropdown shows latest 5, bell badge updates, mark all read works

### Integration Testing
- [ ] End-to-end: Login → Dashboard → Generate → Export → Settings
- [ ] Mobile responsive: All new features work on tablet/mobile
- [ ] Livewire reactivity: No race conditions
- [ ] Database queries: No N+1 issues

### Regression Testing
- [ ] Existing features work: CRUD master data, scheduler algorithms
- [ ] Multi-gelombang naming works
- [ ] Status active/inactive filter works
- [ ] Auth block tim inactive works

---

## 📚 DOCUMENTATION UPDATES (GEMINI)

After each batch completion, update:

1. **BATCH_LOG.md**: Mark tasks complete, add files changed, notes
2. **CHANGELOG.md**: Add entries per batch (✨ Added, 🔧 Changed, 🐛 Fixed)
3. **DEVELOPMENT_LOG.md**: Document new patterns, workarounds discovered
4. **TIMELINE_REVISI.md**: Update progress percentage (79% → 85%)

---

## 🚀 EXECUTION CHECKLIST (GEMINI)

Before starting each batch:
- [ ] Read BATCH_LOG.md untuk understand Phase 1 context
- [ ] Read this IMPLEMENTATION_PLAN.md section for current batch
- [ ] Check existing files mentioned in tasks
- [ ] Run `php artisan route:list` untuk understand current routes

After completing each task:
- [ ] Run `vendor/bin/pint --dirty --format agent` (PHP files)
- [ ] Run `npm run build` (Blade/CSS/JS changes)
- [ ] Test feature manually (if possible)
- [ ] Update BATCH_LOG.md dengan task completion

After completing each batch:
- [ ] Update CHANGELOG.md with batch changes
- [ ] Commit dengan format: `feat(batch-N): Summary of batch changes`
- [ ] Tag: `git tag v0.10.0-batch-N`

---

## 📞 HANDOFF NOTES FOR GEMINI

### Important Context
1. **Phase 1 complete** (Batch 8, 7, 2) — button hover, sidebar, range picker fixed
2. **User testing pending** — waiting user confirmation before Phase 2
3. **Credit budget**: ~10 credits remaining in Kiro (Phase 2 must use Gemini)
4. **Animation spec**: 300ms ease-out (`cubic-bezier(0.4, 0.0, 0.2, 1)`), no bounce
5. **Flux compliance**: 90% using Flux UI, avoid custom components
6. **Theme**: Inovindo colors (Navy, Blue, Green, Orange)
7. **Language**: Bahasa Indonesia for UI text

### Files to Read First
- `BATCH_LOG.md` — Phase 1 execution summary
- `CHANGELOG.md` — Version history (v0.9.0 → v0.10.0-alpha)
- `DEVELOPMENT_LOG.md` — Technical patterns, workarounds
- `TIMELINE_REVISI.md` — Project progress (79% complete)

### Common Patterns
- Livewire component: `⚡nama-component.blade.php` (anonymous class)
- Service class: `app/Services/NamaService.php`
- Route name: `role.resource.action`
- Breadcrumb: Auto-detect by route prefix

### Known Issues to Avoid
- Alpine `:class` object syntax conflict dengan Blade `{{ }}` (use ternary)
- Flux icon "ban" tidak exist (use "x-circle" instead)
- View cache timeout (run `php artisan view:clear` if needed)

---

**Ready for Phase 2 Execution! 🚀**

---

**End of Implementation Plan**

