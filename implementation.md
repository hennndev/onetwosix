# Implementation: Responsive Seluruh Halaman — 126club-v2

> File panduan eksekusi. Target: buat seluruh halaman admin (desktop) responsif di mobile/tablet.
> **Framework: Laravel 11 + Blade + Tailwind CSS (Utility-first) + Alpine.js.** Tidak ada CSS custom (hanya `@tailwindcss` di `resources/css/app.css`).
> **Stack CSS:** semua styling via class Tailwind inline di blade. Tidak ada framework UI (no Bootstrap).

---

## 1. Konteks Arsitektur

### Layout
- **`resources/views/layouts/app.blade.php`** — layout admin desktop. Struktur:
  ```
  <body class="font-sans antialiased bg-gray-50">
    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: ... }">
      @include('layouts.sidebar')          ← <aside class="w-64 / w-0">
      <div class="flex-1 flex flex-col overflow-hidden">
        @include('layouts.header')         ← top bar
        @include('layouts.top-spender-banner')
        <main class="flex-1 overflow-y-auto">{{ $slot }}</main>
      </div>
    </div>
  ```
- **`resources/views/layouts/sidebar.blade.php`** — `<aside>` `w-64` saat terbuka, `w-0` saat tutup. Toggle via tombol hamburger di header. **Tidak ada drawer overlay untuk mobile**.
- **`resources/views/layouts/header.blade.php`** — top bar: hamburger, judul, area switcher, user menu.
- **`resources/views/layouts/waiter-mobile.blade.php`** — layout POS waiter (sudah mobile-first, jangan diubah).
- **`resources/views/layouts/guest.blade.php`** — halaman login/register.

### 37 halaman pakai `x-app-layout` (admin desktop)
Dashboard, customers, settings/*, recap, tables, transaction-checker, bookings, areas, stock-opname/*, customer-keep, rewards, admin/auth-code-requests, display-messages, roles, waiter-performance, printers, **pos**, inventory, profile, users, **transaction-history**, song-requests, menus, table-scanner, **bar**, events, **kitchen**, **active-tables** (index + readonly).

### View terbesar (prioritas utama — paling padat, paling banyak yang harus disesuaikan)
| File | Baris | Catatan |
|---|---|---|
| `resources/views/pos/index.blade.php` | 1942 | Admin POS — heavy table + grid |
| `resources/views/recap/index.blade.php` | 1525 | Tabs + banyak grid kartu |
| `resources/views/bookings/_partials/tab-history.blade.php` | 1426 | Tabel history |
| `resources/views/menus/index.blade.php` | 1374 | |
| `resources/views/transaction-history/index.blade.php` | 1272 | Tabel + modal detail |
| `resources/views/transaction-history/walk-in/index.blade.php` | 1215 | |
| `resources/views/bookings/_components/close-billing-modal.blade.php` | 1117 | Modal |
| `resources/views/waiter-performance/index.blade.php` | 928 | |

### Pola anti-responsif yang TERKONFIRMASI ada di codebase
1. **Tabel lebar dalam `overflow-x-auto`** — 33 view. Ini OKE untuk mobile (horizontal scroll) tapi beberapa tabel tidak dibungkus `overflow-x-auto` → overflow halaman.
2. **Grid 4+ kolom tanpa breakpoint responsif** — 25 view. `grid-cols-4`, `grid-cols-5`, `xl:grid-cols-*` yang tidak punya fallback `grid-cols-1`/`sm:` → di mobile jadi sempit.
3. **View dengan 0 breakpoint `sm/md/lg`** (11 view) — kemungkinan besar berantakan di mobile:
   `settings/pos-categories`, `settings/daily-auth-code`, `settings/tier-settings`, `bookings/index`, `stock-opname/history`, `admin/auth-code-requests/show`, `pos/index`, `inventory/index`, `transaction-history/index`, `events/index`, `active-tables/readonly`.

### Realtime (jangan rusak!)
Beberapa halaman baru saja di-upgrade realtime via polling. **Jangan ubah struktur `id`/`x-data`/URL polling** — hanya ubah class layout (grid, spacing, width). File realtime:
- `active-tables/readonly.blade.php` — container `#realtimeStats`, `#realtimeTable` (`realtimePoll`).
- `dashboard.blade.php` — `#dashboardStats`.
- `waiter-performance/index.blade.php` — `#waiterStats`.
- `recap/index.blade.php` — `#recapSummary`.
- `transaction-history/index.blade.php` — `#txStats`, `#txList` (`txListPoll`), `data-tx-count`.
- `waiter/pos.blade.php` — `waiterPos()` Alpine, `pollLive()`.

---

## 2. Pendekatan

**Jangan buat mobile-first ulang. Jangan ganti layout halaman admin ke drawer. Minimal diff, maksimal konsistensi:**

1. **Layout global dulu** — buat sidebar jadi drawer overlay di mobile (hamburger menutup sidebar, konten full-width). Ini satu perubahan di `app.blade.php` + `sidebar.blade.php` + `header.blade.php` yang memperbaiki 37 halaman sekaligus.
2. **Fix per-halaman** — untuk setiap halaman, pastikan:
   - Grid multi-kolom punya fallback `grid-cols-1` lalu naik breakpoint (`sm:`/`md:`/`lg:`).
   - Tabel dibungkus `overflow-x-auto` (jika belum).
   - Spacing `p-6`/`px-6` di container jadi `p-4 sm:p-6`.
   - Form input tidak meluber (pakai `w-full`).
   - Modal (`fixed inset-0`) sudah responsif (pakai `max-w-*` + `w-full` + `mx-4`).
3. **Prioritas: halaman terbesar & paling dipakai dulu** (lihat tabel di atas).

---

## 3. Step-by-step

### Step 0 — Setup verifikasi
```bash
php artisan view:cache        # compile semua blade — deteksi error syntax
php artisan route:list        # pastikan route jalan
```
Jalankan `php artisan view:cache` setelah selesai tiap halaman.

### Step 1 — Layout global (sidebar drawer mobile)

**File: `resources/views/layouts/app.blade.php`**
Ubah `sidebarOpen` state + tambah overlay. Target:
- Di mobile (`< md`): sidebar jadi **off-canvas overlay** yang menutupi layar, konten full-width.
- Di desktop (`≥ md`): tetap inline seperti sekarang (`flex`).

Struktur target:
```blade
<div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">
  <!-- Sidebar: off-canvas di mobile, inline di desktop -->
  <aside :class="[
    'fixed z-40 h-screen transition-all duration-300 lg:relative lg:flex',
    sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
    sidebarOpen ? 'w-64' : 'w-0 lg:w-64'
  ]">
    @include('layouts.sidebar')
  </aside>

  <!-- Overlay (mobile only, saat sidebar terbuka) -->
  <div x-show="sidebarOpen"
       @click="sidebarOpen = false"
       class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

  <div class="flex-1 flex flex-col overflow-hidden">
    @include('layouts.header')
    @include('layouts.top-spender-banner')
    <main class="flex-1 overflow-y-auto">{{ $slot }}</main>
  </div>
</div>
```
> Catatan: `sidebarOpen` default jadikan `false` di mobile (agar tidak terbuka awal di HP). Gunakan `localStorage` atau `matchMedia` jika ingin mempertahankan preferensi desktop. Sederhana: `x-data="{ sidebarOpen: window.innerWidth >= 1024 }"`.

**File: `resources/views/layouts/sidebar.blade.php`**
Sesuaikan class `aside`: hilangkan `flex`/`shrink-0` dependency pada parent, pastikan `h-full` agar scroll internal `overflow-y-auto` jalan. Ganti `w-64`/`w-0` toggle lama dengan class mobile-first di atas.

**File: `resources/views/layouts/header.blade.php`**
Hamburger button (baris 5) tetap ada — pastikan tetap toggle `sidebarOpen`. Area switcher (baris 33-67) saat ini `flex items-center` — pastikan di mobile jadi `w-full`/wrap, tidak meluber. User menu & logout (baris 70-97) — pastikan di mobile bisa diakses (mungkin jadi dropdown atau tetap icon).

**Verifikasi Step 1:** `php artisan view:cache` + buka 2-3 halaman di viewport mobile (dashboard, bookings) — sidebar drawer muncul, konten full-width.

### Step 2 — Fix grid multi-kolom (25 view)
Pola: cari `grid grid-cols-N` (tanpa `grid-cols-1` fallback) → ubah jadi `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-N` atau sesuaikan.

Contoh di **dashboard.blade.php** (kartu stat, sudah benar `grid-cols-1 md:grid-cols-2 lg:grid-cols-4`) — **jangan diubah**, jadikan referensi.

Pola yang perlu di-fix (cari di tiap view):
- `grid grid-cols-4` tanpa `grid-cols-1` → `grid grid-cols-2 lg:grid-cols-4` (2 kolom di mobile cukup untuk kartu angka).
- `grid grid-cols-5` (recap) → `grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5`.
- `grid-cols-3` untuk kartu yang teks panjang → `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`.

**File yang pasti:**
- `recap/index.blade.php` — grid 5 kolom summary + grid 4 kolom preview dashboard.
- `pos/index.blade.php` — grid produk (kemungkinan `grid-cols-N` produk menu).
- `kitchen/index.blade.php`, `bar/index.blade.php` — grid order cards.
- `events/index.blade.php`, `customers/index.blade.php`, `rewards/index.blade.php`, `users/index.blade.php`, `inventory/index.blade.php` — grid kartu/stat.
- `waiter-performance/index.blade.php` — `grid-cols-4` stat (sudah `lg:`), `grid-cols-2 lg:grid-cols-3` stat cards.

### Step 3 — Fix tabel (33 view overflow-x-auto)
Pola: pastikan **setiap** `<table>` dibungkus `<div class="overflow-x-auto">`.
- Sudah ada 33 view yang benar. Cari yang BELUM: `grep -l "<table" resources/views --include="*.blade.php"` lalu cek yang tidak ada `overflow-x-auto` terdekat.
- Untuk tabel dengan banyak kolom (transaction-history, bookings tab-history, stock-opname): tambahkan `min-w-[800px]` pada `<table>` agar kolom tidak sempit saat scroll horizontal.

### Step 4 — Fix spacing & form
- Container utama `{{ $slot }}` di banyak view: `class="p-6"` → `class="p-4 sm:p-6"`.
- Form search/filter (`active-tables/readonly`, `transaction-history`, `bar`, `kitchen`): pastikan `flex flex-col sm:flex-row` agar input tidak berdempet di mobile.
- Input/select: pastikan `w-full sm:w-auto` atau `w-full`.

### Step 5 — Fix modal
Modal pakai `fixed inset-0 z-* flex items-center justify-center p-4` — pastikan panel dalam punya `w-full max-w-*`. Cek:
- `bookings/_components/close-billing-modal.blade.php` (1117 baris — besar).
- `transaction-history/index.blade.php` — modal detail, payment edit, error, debt.
- `pos/index.blade.php` — modal checkout, counter, history.
- `customer-keep`, `rewards`, `song-requests` — modal umum.

Pola: panel `class="w-full max-w-lg"` atau `max-w-md` — sudah benar sebagian besar. Fix yang pakai `w-[600px]`/`w-96` fixed → `w-full max-w-*`.

### Step 6 — Halaman spesifik (prioritas)

**`pos/index.blade.php` (1942 baris — PRIORITAS 1)**
- Grid produk menu → `grid-cols-2 md:grid-cols-3 lg:grid-cols-4` (atau sesuai existing).
- Layout 2 kolom (produk kiri, cart kanan) → jadi stack di mobile: `flex flex-col lg:flex-row`.
- Cart panel → `fixed bottom-0 inset-x-0` sheet di mobile ATAU `lg:static lg:w-96`. Pilih yang sudah ada di codebase — cek struktur kartu saat ini.
- Modal checkout → `w-full max-w-lg mx-4`.

**`recap/index.blade.php` (1525 baris — PRIORITAS 2)**
- Tabs (Recap/History/Transactions) → `overflow-x-auto` di mobile (sudah `flex` — pastikan scroll).
- Grid summary 5 kolom → `grid-cols-2 md:grid-cols-3 xl:grid-cols-5`.
- Preview dashboard grid 4 kolom → `grid-cols-2 lg:grid-cols-4`.

**`transaction-history/index.blade.php` + `walk-in/index.blade.php` (PRIORITAS 3)**
- Stat cards `grid-cols-4` → `grid-cols-2 lg:grid-cols-4`.
- Header area tabs → `overflow-x-auto`.
- Tabel → sudah `overflow-x-auto`, tambah `min-w-[800px]`.
- Toolbar (per-page, search) → `flex-col sm:flex-row`.
- **JANGAN ubah:** `id="txStats"`, `id="txList"`, `txListPoll`, `data-tx-count`, route `admin.transaction-history.refresh`.

**`waiter-performance/index.blade.php` (928)**
- Stat cards `grid-cols-1 lg:grid-cols-4` — sudah benar.
- Period buttons `flex gap-2` → `flex-wrap` di mobile.
- `grid-cols-2 lg:grid-cols-3` stat → sudah benar.
- Header + area tabs → `overflow-x-auto` untuk area.
- **JANGAN ubah:** `#waiterStats`, `summaryQuery`, `realtimePoll`.

**`kitchen/index.blade.php` + `bar/index.blade.php` (814)**
- Sudah punya `setInterval` polling — **jangan ubah logika JS/route**.
- Grid order cards → `grid-cols-1 md:grid-cols-2 xl:grid-cols-3`.
- Pastikan polling tetap jalan setelah class diubah (Alpine `init()`).

**`active-tables/readonly.blade.php`**
- Stat cards `grid-cols-1 md:grid-cols-2` — sudah benar.
- Tabel → `overflow-x-auto` + `min-w-[1000px]` (12 kolom).
- Filter form → `flex-col sm:flex-row`.
- **JANGAN ubah:** `#realtimeStats`, `#realtimeTable`, `realtimePoll`, `route('admin.active-tables.readonly')`.

**`dashboard.blade.php`**
- Sudah responsif sebagian besar. Cek hero section (`p-8` → `p-4 sm:p-8`), tombol sync.
- **JANGAN ubah:** `#dashboardStats`.

---

## 4. Checklist Verifikasi Akhir
```bash
php artisan view:cache                                    # wajib bersih
php artisan route:list | grep -c "GET"                    # route intact
grep -rn "id=\"realtimeStats\"\|id=\"txList\"\|txListPoll\|#waiterStats\|#recapSummary\|#dashboardStats" resources/views --include="*.blade.php"
```
- Semua `id`/`x-data` realtime masih ada persis seperti sebelum perubahan.
- Tidak ada `<table>` tanpa parent `overflow-x-auto`.
- Tidak ada `grid grid-cols-[3-9]` tanpa fallback `grid-cols-1`/`sm:`/`md:`/`lg:`.

## 5. Larangan
- **JANGAN** ubah `resources/views/layouts/waiter-mobile.blade.php` — sudah mobile-first.
- **JANGAN** tambah package/CSS baru — cukup class Tailwind.
- **JANGAN** ubah logika JS realtime (polling interval, URL, id container).
- **JANGAN** ganti `x-app-layout` → layout lain untuk halaman tertentu.
- **JANGAN** hapus `overflow-x-auto` yang sudah ada (33 view bergantung padanya).

## 6. Ringkas (untuk dipelajari model lain)
1. Global: sidebar jadi drawer mobile via `app.blade.php` + `sidebar.blade.php` + `header.blade.php`.
2. Per-halaman: fix grid fallback, tabel `overflow-x-auto`, spacing `p-4 sm:p-6`, form `w-full sm:flex-row`, modal `w-full max-w-*`.
3. Prioritas: pos → recap → transaction-history → waiter-performance → kitchen/bar → active-tables → dashboard → sisanya.
4. Verifikasi: `php artisan view:cache` + cek id realtime tidak berubah.
