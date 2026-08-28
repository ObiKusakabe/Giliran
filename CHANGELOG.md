# 📝 CHANGELOG - Sistem Manajemen Jadwal Internal

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### 🔴 To Do (High Priority)
- UI/UX Overhaul - Batch 1, 3, 4, 5, 6 (login split-screen, dashboard restructure, master data cards, settings 1-page, notifikasi modal)
- Auto-Swap feature (request pengganti otomatis)
- Unit testing dengan Pest untuk algoritma kritis
- Integration testing (notifikasi, export, end-to-end)
- Production deployment & environment setup
- User manual & technical documentation

---

## [0.10.0-alpha] - 2026-08-28

### 🐛 Fixed
- **Button hover states**: Primary/danger buttons now retain correct colors on hover (blue-700/red-700, not white) — strengthened CSS selectors with class-based targeting
- **Sidebar animation**: Smooth collapse/expand transition (300ms ease-out cubic-bezier, no bounce)
- **Sidebar collapsed buttons**: Forced square dimensions (40x40px), rounded corners (rounded-lg), centered icons
- **Date range picker z-index**: Calendar now renders above modal backdrop in Periode WFO modal (z-index: 10000)

### 🔧 Changed
- **Sidebar animation timing**: Implemented gentle ease-out (cubic-bezier(0.4, 0.0, 0.2, 1)) — cepat di awal, lambat di akhir, no bounce
- **Button CSS selectors**: Strengthened to catch all Flux variants (attribute + class-based patterns)
- **Disabled button state**: Added pointer-events:none untuk prevent accidental clicks

### 📚 Documentation
- Created **BATCH_LOG.md** untuk track per-batch execution progress (Phase 1: Batch 8, 7, 2 complete)
- Created **IMPLEMENTATION_PLAN.md** untuk comprehensive UI/UX overhaul roadmap (8 batches, 45+ tasks)
- Updated **DEVELOPMENT_LOG.md** dengan animation specifications dan button hover patterns

---

## [0.9.0] - 2026-08-27

### ✨ Added
- **Multi-Gelombang Tim**: Auto-naming dengan suffix tahun + roman numeral (Tim LPKIA-2026-I, Tim LPKIA-2026-II)
  - Service: `App\Services\TimNamingService`
  - Methods: `generateNamaGelombang()`, `romanToInt()`, `intToRoman()`
- **Status Active/Inactive Tim**:
  - Migration: `add_status_to_tim_table` (kolom `status` ENUM: active, inactive)
  - Toggle status dengan konfirmasi modal
  - Badge status (green: Active, grey: Inactive)
  - Filter checkbox: "Tampilkan Tim Inactive"
- **Smart Breadcrumb System**:
  - Auto-detection berdasarkan route pattern
  - Format hierarki: `Master Data > Tim`, `Jadwal > Periode WFO`, etc.
  - PHP logic di layout untuk generate breadcrumb dinamis
- **Navbar Top Bar**:
  - Sticky navbar dengan breadcrumb di kiri
  - Notifikasi bell icon + profile dropdown di kanan
  - Mobile-friendly (hamburger + notif/profile tetap accessible)
- **Flux Demo Style Sidebar**:
  - Width: w-64 (256px) expanded → w-14 (56px) collapsed
  - Panel icon toggle button (bukan arrow)
  - Rounded-lg buttons di collapsed state
  - Sticky header + scrollable navigation

### 🔧 Changed
- **LraScheduler Filter**: Tim inactive otomatis excluded dari kandidat WFO, adzan, kitab, briefing
  - Updated: `ambilPersonilWfo()` dan `generateBriefing()`
- **FortifyServiceProvider**: Custom `authenticateUsing()` untuk block login tim inactive
- **Layout Admin**: Restructure dengan navbar atas (pindahkan notif + profile dari sidebar footer)
- **Sidebar Style**: Follow Flux demo convention (w-64/w-14, rounded buttons)

### 🐛 Fixed
- **Alpine.js Syntax Error**: Fixed `:class` binding conflict dengan Blade `{{ }}` di jadwal-wfo-grid
  - Changed object syntax ke ternary operator
- **Flux Icon Error**: `icon="ban"` tidak exist, diganti ke `icon="x-circle"`
- **Button Hover State**: Primary & danger buttons jadi putih saat hover
  - Added CSS override: `button[data-flux-button]:hover` dengan proper color states
- **Logo Text Selection**: Logo "Giliran" bisa di-select
  - Added `user-select: none` CSS rule
- **Double Breadcrumb**: Settings pages punya breadcrumb duplicate
  - Removed manual breadcrumb dari view, pakai layout logic

### 📚 Documentation
- Created `TIMELINE_REVISI.md`: Comprehensive checklist & progress tracking (79% complete)
- Created `CHANGELOG.md`: This file untuk track semua perubahan

---

## [0.8.0] - 2026-08-20

### ✨ Added
- **Sistem Notifikasi H-1**:
  - Command: `php artisan notifikasi:h1`
  - Scheduled daily di `app/Console/Kernel.php` (06:00 WIB)
  - Notifikasi untuk: WFO, Adzan, Kitab, Briefing
- **Export PDF**:
  - Controller: `Admin\ExportController`
  - Generate surat resmi dengan kop surat
  - Tabel jadwal terstruktur per personil
- **Kalender Controller**:
  - `Admin\KalenderController`
  - API endpoint untuk FullCalendar
  - Filter per personil & tim
- **Dashboard Real-Time**:
  - Statistics cards (periode aktif, total jadwal, dll)
  - Highlight H-1 untuk notifikasi
  - Visual calendar heatmap

### 🔧 Changed
- **LraScheduler**: Optimasi algoritma rotasi dengan in-memory counter
- **Database Seeder**: Enhanced dengan data dummy yang lebih realistic

---

## [0.7.0] - 2026-08-15

### ✨ Added
- **Drag-Drop Jadwal WFO**:
  - Grid interface Senin-Sabtu
  - Drag-drop tim ke hari tertentu
  - Touch support untuk mobile/tablet
  - Visual feedback: drag ghost, drop zones
  - Optimistic UI updates
- **Generate Jadwal Otomatis**:
  - Algoritma auto-assign ruangan 1:1 dengan tim WFO
  - Constraint validation (1 ruangan = 1 tim per hari)
  - Service: `LraScheduler::ambilPersonilWfo()`
- **Algoritma Rotasi Adzan & Kitab**:
  - Distribusi merata berbasis WFO
  - Rolling index untuk fairness
  - Service: `LraScheduler` methods
- **Algoritma Rotasi Briefing**:
  - Distribusi internal tim (pagi & sore)
  - Fairness check dengan LRA
  - Service: `LraScheduler::generateBriefing()`

### 🔧 Changed
- Livewire components updated ke Livewire 4 syntax
- Alpine.js integration dengan wire:navigate

---

## [0.6.0] - 2026-08-10

### ✨ Added
- **CRUD Master Data**:
  - Tim: `app/Livewire/Admin/ManajemenTim.php`
  - Personil: `app/Livewire/Admin/ManajemenPersonil.php`
  - Ruangan: `app/Livewire/Admin/ManajemenRuangan.php`
  - Semua dengan Flux UI components
- **CRUD Periode WFO**:
  - Manajemen periode aktif
  - Validasi tanggal overlap
  - Status aktif/nonaktif
- **Middleware CheckRole**:
  - Role-based access control
  - Routes protected per role (admin, personil, tim)

### 🔧 Changed
- Migration files untuk 10 tabel utama
- Model relationships (Tim hasMany Personil, dll)

---

## [0.5.0] - 2026-08-07

### ✨ Added
- **Flux UI Integration**:
  - Installed Flux UI Pro components
  - Configured theme dengan Tailwind v4
  - Custom components: sidebar, navbar, cards, forms
- **Tema Inovindo**:
  - Custom color palette: Navy (#12314F), Blue (#3B71CA), Green (#2FA84F), Orange (#F2A340)
  - Toggle tema Inovindo di appearance settings
  - Alpine.js logic untuk persist theme
- **Sidebar Collapsible**:
  - Desktop: Collapse to icon-only
  - Mobile: Hamburger menu
  - LocalStorage untuk remember state

### 🔧 Changed
- Tailwind v4 setup dengan `@import "tailwindcss"`
- Vite config untuk handle Flux components

---

## [0.4.0] - 2026-08-05

### ✨ Added
- **Laravel Fortify Authentication**:
  - Login, logout, password reset
  - Email verification (optional)
  - Two-factor authentication (optional)
  - Passkeys support
- **User Roles**:
  - Admin: Full access
  - Personil: Lihat jadwal pribadi
  - Tim: Lihat jadwal tim & ruangan
- **Settings Pages**:
  - Profile: Update nama & email
  - Security: Change password, 2FA, passkeys
  - Appearance: Light/dark mode, tema Inovindo

---

## [0.3.0] - 2026-08-03

### ✨ Added
- **Database Schema** (10 tabel utama):
  - users, tim, personil, ruangan
  - periode_wfo, jadwal_wfo, alokasi_ruangan
  - jadwal_adzan, jadwal_kitab, jadwal_briefing
  - notifikasi
- **Migrations** dengan foreign keys & soft deletes
- **Models** dengan relationships Eloquent
- **Seeders** untuk data dummy (TimSeeder, PersonilSeeder, dll)

---

## [0.2.0] - 2026-08-01

### ✨ Added
- **Laravel 13 Project Setup**:
  - PHP 8.3 requirement
  - Composer dependencies
  - Laravel Pint (code formatter)
  - Laravel Pest (testing framework)
- **Livewire 4 Setup**:
  - Installed via Composer
  - Configured blade namespace
  - Wire:navigate enabled
- **MySQL Database Connection**:
  - .env configuration
  - Database created: `giliran`

---

## [0.1.0] - 2026-07-28

### ✨ Added
- Initial project structure
- Requirements analysis document (SRS.md)
- Database design (ERD)
- Wireframes & mockups
- Timeline planning

---

## 📌 Legend

- ✨ **Added**: New features
- 🔧 **Changed**: Changes in existing functionality
- 🐛 **Fixed**: Bug fixes
- 🗑️ **Deprecated**: Soon-to-be removed features
- ❌ **Removed**: Removed features
- 🔒 **Security**: Security improvements
- 📚 **Documentation**: Documentation updates
- ⚡ **Performance**: Performance improvements

---

## 🔄 Version Naming Convention

- **Major** (1.0.0): Breaking changes, major milestones
- **Minor** (0.x.0): New features, backward-compatible
- **Patch** (0.0.x): Bug fixes, small improvements

Current version: **0.9.0** (near MVP ready, missing Auto-Swap)

---

**Note**: Untuk detail teknis lengkap, lihat:
- `TIMELINE_REVISI.md` - Progress tracking
- `.kiro/docs/SRS.md` - Software Requirements Specification
- `.kiro/docs/HANDOVER.md` - Handover documentation
- `README.md` - Installation & usage guide
