# 🔧 DEVELOPMENT LOG - Context untuk AI Agent

> **Tujuan**: Dokumentasi keputusan teknis, pattern yang dipakai, dan context penting untuk AI agent selanjutnya (Gemini/Claude/dll)

**Last Updated**: 27 Agustus 2026 by Claude (Kiro IDE)  
**Project**: Sistem Manajemen Jadwal Internal - PT Inovindo Digital Media

---

## 📁 PROJECT STRUCTURE

```
giliran/
├── app/
│   ├── Actions/Fortify/          # Fortify custom actions
│   ├── Concerns/                 # Reusable traits
│   ├── Console/Commands/         # Artisan commands
│   ├── Http/
│   │   ├── Controllers/Admin/    # Admin controllers (Export, Kalender)
│   │   └── Middleware/           # CheckRole middleware
│   ├── Livewire/                 # Semua Livewire components (⚡prefix)
│   ├── Models/                   # Eloquent models
│   ├── Providers/                # Service providers
│   └── Services/                 # Business logic services
├── database/
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Data seeders
├── resources/
│   ├── css/app.css              # Tailwind v4 entry
│   ├── js/app.js                # Alpine.js + Livewire
│   └── views/
│       ├── components/          # Blade components
│       ├── layouts/             # Layout templates
│       ├── pages/               # Livewire views (⚡prefix)
│       └── partials/            # Reusable partials
├── routes/
│   └── web.php                  # Route definitions
├── .kiro/docs/                  # Project documentation
├── CHANGELOG.md                 # Version history
├── TIMELINE_REVISI.md          # Progress tracking
└── README.md                    # Installation guide
```

---

## 🎯 ARCHITECTURAL DECISIONS

### 1. **Livewire Single-File Components (⚡ Prefix)**
Semua Livewire components pakai **anonymous class** dengan inline PHP di blade file:

```php
<?php
use Livewire\Component;
new class extends Component { 
    // Component logic 
}; 
?>
<div>
    <!-- View markup -->
</div>
```

**File naming**: `⚡component-name.blade.php` (⚡ = lightning bolt = Livewire)

**Kenapa?**
- Faster development (1 file = logic + view)
- Sesuai Laravel Livewire 4 best practice
- Blade hot reload tanpa restart server

### 2. **Service Layer Pattern**
Business logic ada di `app/Services/`:
- `LraScheduler.php` - Algoritma rotasi LRA
- `TimNamingService.php` - Auto-naming multi-gelombang

**Kenapa?**
- Separation of concerns (Livewire = UI, Service = Logic)
- Reusable & testable
- Bisa dipanggil dari multiple entry points (Livewire, Command, API)

### 3. **Flux UI Components**
UI pakai **Flux UI Pro** (Laravel official UI kit):
- `<flux:button>`, `<flux:card>`, `<flux:modal>`, dll
- Variant: `primary`, `danger`, `ghost`, `outline`
- Custom CSS override di `resources/views/partials/head.blade.php`

**Penting**: Flux components ada di vendor, JANGAN edit langsung. Pakai CSS override atau wrapper component.

### 4. **Database Naming Convention**
- Table: **snake_case plural** (`jadwal_wfo`, `periode_wfo`)
- Column: **snake_case** (`nama_tim`, `tanggal_mulai`)
- Foreign key: **singular_id** (`tim_id`, `personil_id`)
- Timestamps: Laravel default (`created_at`, `updated_at`, `deleted_at`)

### 5. **Route Naming Convention**
Pattern: `{role}.{resource}.{action}`

```php
Route::get('/admin/tim', ...)->name('admin.tim');
Route::get('/admin/jadwal-wfo', ...)->name('admin.jadwal-wfo');
Route::get('/profile/edit', ...)->name('profile.edit');
```

Ini penting untuk **smart breadcrumb detection** di layout.

---

## 🔑 KEY FEATURES & IMPLEMENTATION

### **Multi-Gelombang Auto-Naming**
**File**: `app/Services/TimNamingService.php`

**Logic**:
1. Cek apakah nama tim sudah ada suffix `-YYYY-I`
2. Jika belum, ambil tahun sekarang
3. Query DB untuk cari tim dengan nama sama di tahun yang sama
4. Increment roman numeral (I → II → III)
5. Return: `Tim LPKIA-2026-II`

**Integration**:
- Called from `ManajemenTim` Livewire component saat create
- Hanya jalan kalau mode CREATE (bukan EDIT)

### **Status Active/Inactive Tim**
**Migration**: `2026_08_26_015157_add_status_to_tim_table.php`

**Behavior**:
- Default: `active`
- Toggle via UI dengan confirmation modal
- **Filtered di scheduler**: `LraScheduler` methods exclude tim inactive
- **Auth block**: `FortifyServiceProvider` custom `authenticateUsing()`
- **UI indicator**: Badge (green = Active, grey = Inactive)

**Edge Cases Handled**:
1. Jadwal lama tetap valid (tidak dihapus)
2. Jadwal baru tidak include tim inactive
3. Tim inactive tidak bisa login
4. Admin tetap bisa view tim inactive

### **Smart Breadcrumb System**
**File**: `resources/views/layouts/admin.blade.php`

**Logic**:
```php
$routeName = request()->route()->getName();
if (str_starts_with($routeName, 'admin.tim')) {
    $breadcrumbParts = [['label' => 'Master Data'], ['label' => 'Tim']];
}
// dst...
```

**Pattern**:
- Dashboard only: `Dashboard`
- Master Data: `Master Data > Tim/Personil/Ruangan`
- Jadwal: `Jadwal > Periode WFO/Jadwal WFO/Generate/Kalender`
- Tools: `Tools > Export`
- Settings: `Pengaturan > Profil/Keamanan/Tampilan` (manual via prop)

### **LRA Algorithm (Least Recently Assigned)**
**File**: `app/Services/LraScheduler.php`

**Core Methods**:
- `ambilPersonilWfo()` - Pilih personil untuk WFO (adzan, kitab, ruangan)
- `generateBriefing()` - Generate briefing pagi/sore per tim

**How It Works**:
1. Query semua kandidat yang eligible (tim active, available)
2. Group by frequency count (historis dari DB)
3. Pilih yang count-nya paling kecil
4. Shuffle jika ada tie
5. Update counter in-memory (tidak query DB tiap loop)
6. Save batch ke DB setelah semua selesai

**Important**: Counter di-reset per batch generate, bukan global persistent.

### **Drag-Drop Jadwal WFO**
**File**: `resources/views/pages/admin/⚡jadwal-wfo-grid.blade.php`

**Tech Stack**:
- Alpine.js untuk state management
- Native HTML5 Drag API
- Touch polyfill untuk mobile/tablet

**Features**:
- Drag chip tim ke kolom hari
- Visual feedback (ghost element, drop zones)
- Optimistic UI (update langsung tanpa tunggu server)
- Rollback jika server error
- Loading state per chip

**Important**: Pakai `@dragstart`, `@dragover`, `@drop` events. Touch pakai custom polyfill.

---

## 🎨 UI/UX PATTERNS

### **Flux Demo Style Sidebar**
**Width**:
- Expanded: `w-64` (256px)
- Collapsed: `w-14` (56px)

**Toggle Button**:
- Icon: Panel icon (bukan arrow)
- SVG inline di button
- Position: Header kanan

**Collapsed State**:
- Show: Icon only (h-10 w-10 rounded-lg)
- Hide: Text labels, group headings
- Tooltip: Show on hover (Flux tooltip component)

### **Navbar Top Bar (Flux Convention)**
**Layout**:
```
[Hamburger (mobile)] [Breadcrumb (desktop)]      [🔔] [👤 Profile]
```

**Behavior**:
- Sticky: Always visible saat scroll
- Mobile: Hamburger + notif/profile tetap accessible
- Desktop: Breadcrumb full, hamburger hidden

**Kenapa pindah dari sidebar?**
- Convention: Hampir semua admin template (DreamsPOS, AdminLTE, Metronic) pakai navbar atas
- Mobile UX: Notif & profile harus accessible tanpa buka sidebar
- Claude recommendation: Sesuai user expectation

### **Button Hover States (Fixed)**
**Issue**: Tombol primary/danger jadi putih saat hover.

**Fix**: CSS override di `partials/head.blade.php`:
```css
button[data-flux-button][variant="primary"]:hover {
    background-color: rgb(29 78 216) !important; /* blue-700 */
}
```

**Reason**: Flux default hover behavior conflict dengan Tailwind custom colors.

---

## 🚨 KNOWN ISSUES & WORKAROUNDS

### 1. **Alpine `:class` Object Syntax dengan Blade**
**Problem**: 
```blade
:class="{ 'active': someVar === '{{ $bladeVar }}' }"
```
Ini syntax error karena Blade `{{ }}` conflict dengan Alpine object.

**Solution**: Pakai ternary operator:
```blade
x-bind:class="someVar === '{{ $bladeVar }}' ? 'active' : ''"
```

**Affected Files**: `⚡jadwal-wfo-grid.blade.php`

### 2. **View Cache Timeout**
**Problem**: `php artisan view:clear` kadang timeout (120s+).

**Workaround**: Run 2x atau langsung delete `storage/framework/views/*`

**Why**: Large compiled views + slow disk I/O.

### 3. **Flux Icon "ban" Tidak Exist**
**Problem**: `<flux:icon icon="ban" />` error.

**Solution**: Pakai `x-circle` atau `x-mark` sebagai alternatif.

**Reason**: Flux pakai Heroicons, tidak semua icon tersedia.

---

## 📦 DEPENDENCIES & VERSIONS

### **PHP Packages**
```json
{
  "php": "^8.3",
  "laravel/framework": "^13.0",
  "livewire/livewire": "^4.0",
  "livewire/flux": "^2.0",
  "laravel/fortify": "^2.0"
}
```

### **NPM Packages**
```json
{
  "tailwindcss": "^4.0.0-alpha.25",
  "alpinejs": "^3.14",
  "@livewire/navigate": "^4.0"
}
```

### **Important**:
- **Tailwind v4** (masih alpha) - Import CSS berbeda dari v3
- **Flux UI** - Requires license key di `.env` (`FLUX_LICENSE_KEY`)
- **Livewire 4** - Breaking changes dari v3 (syntax, lifecycle)

---

## 🧪 TESTING NOTES

### **Test Database**
**Name**: `giliran_test` (MySQL)

**Setup**:
```sql
CREATE DATABASE giliran_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Config di `phpunit.xml`:
```xml
<env name="DB_DATABASE" value="giliran_test"/>
```

### **Test Commands**
```bash
# All tests
php artisan test --compact

# Filter by class
php artisan test --filter=LraSchedulerTest

# Filter by method
php artisan test --filter=test_generate_jadwal
```

### **Test Coverage (Current)**
- ❌ **0%** - Belum ada test suite yang comprehensive
- 📝 **To Do**: Write tests untuk LRA algorithms, Tim naming service, Validation rules

---

## 🔐 SECURITY NOTES

### **Role-Based Access Control**
**Middleware**: `app/Http/Middleware/CheckRole.php`

**Usage**:
```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Admin only routes
});
```

**Roles**:
- `admin` - Full access
- `personil` - View own schedule
- `tim` - View tim schedule & room allocation

### **Auth Block untuk Tim Inactive**
**File**: `app/Providers/FortifyServiceProvider.php`

**Logic**:
```php
Fortify::authenticateUsing(function (Request $request) {
    $user = User::where('email', $request->email)->first();
    if ($user && Hash::check($request->password, $user->password)) {
        if ($user->role === 'tim' && $user->tim?->status === 'inactive') {
            throw ValidationException::withMessages([
                'email' => 'Tim Anda sedang tidak aktif. Silakan hubungi admin.'
            ]);
        }
        return $user;
    }
});
```

---

## 🛠️ DEVELOPMENT WORKFLOW

### **Code Style**
**Formatter**: Laravel Pint

**Run**:
```bash
vendor/bin/pint --dirty --format agent
```

**Config**: `pint.json` (Laravel preset)

### **Hot Reload**
**Development Server**:
```bash
npm run dev      # Vite dev server (hot reload)
php artisan serve # Laravel server
```

**Important**: Jangan run `npm run build` saat development (slow rebuild).

### **Git Workflow**
**Branches**:
- `main` - Production ready
- `develop` - Development branch
- `feature/*` - Feature branches

**Commit Convention**:
```
feat: add auto-swap feature
fix: button hover state issue
docs: update changelog
refactor: extract service layer
```

---

## 📞 IMPORTANT CONTACTS

### **User (Product Owner)**
- **Name**: Roby Rachmat F
- **Role**: Developer & PM
- **Preferences**:
  - Bahasa Indonesia untuk user-facing text
  - Flux UI convention (jangan custom component kalau bisa pakai Flux)
  - Smart defaults (auto-detect, auto-naming, auto-filter)
  - Mobile-friendly (responsive + touch support)

### **AI Handover Notes**
**From Claude (Kiro) → Next AI (Gemini/etc.)**:

1. **Project sudah 79% complete** - Core features jalan, tinggal Auto-Swap, Testing, Deployment
2. **Code convention strict** - Pakai Laravel Pint, Livewire 4 syntax, Flux UI components
3. **Documentation lengkap** - CHANGELOG, TIMELINE_REVISI, SRS, README semua ada
4. **User prefer Indonesian** - UI text, error messages, notifications
5. **Smart features prioritized** - Auto-naming, auto-filter, breadcrumb detection
6. **Testing belum maksimal** - Perlu unit tests untuk algorithms
7. **Production belum ready** - Masih localhost, perlu deployment planning

**Key Files to Read First**:
- `CHANGELOG.md` - Apa yang udah dikerjain
- `TIMELINE_REVISI.md` - Apa yang belum
- `.kiro/docs/SRS.md` - Requirements lengkap
- `README.md` - How to run

**Common Patterns**:
- Livewire component = `⚡nama-component.blade.php`
- Service class = `app/Services/NamaService.php`
- Route name = `role.resource.action`
- Breadcrumb auto-detect by route prefix

Good luck! 🚀

---

**End of Development Log**
