# Desain Optimasi Navigasi (RENOPS Capture & BRA) dan UI Halaman Ploting Peta GIS

**Tanggal:** 2026-11-20  
**Status:** Menunggu Persetujuan  
**Lingkup:** QR-SAR (KPP Kelas A Kendari — Basarnas)

---

## 1. Tujuan & Ringkasan Masalah

Optimasi ini menyelesaikan **5 peningkatan UX/UI** yang diminta pengguna secara bersih, aman, dan 100% kompatibel dengan seluruh test suite QR-SAR yang ada (92 PHP test + 7 JS unit test):

1. **Navigasi Capture Peta LKP/Rencana dari Draf RENOPS:** Ketika klik tombol **Lihat Cetakan** setelah berhasil capture peta dari editor draf RENOPS, halaman langsung kembali ke draf RENOPS asal di **tab yang sama** (tidak membuka tab baru) dan tepat menuju `id_renops` asal (termasuk saat capture Peta LKP dari RENOPS Hari ke-2/3).
2. **Navigasi Kembali BRA SAR dari Detail Operasi:** Ketika membuka dokumen BRA SAR dari sub-tab BRA di halaman Detail Operasi SAR (`/operasi_detail.php?id=<id>&tab=bra`), tombol **Kembali** harus pulang langsung ke sub-tab BRA pada Detail Operasi SAR tersebut, bukan ke halaman daftar arsip `/bra.php`.
3. **Header Tunggal Clean & Modern pada Halaman Ploting Peta:** Menggabungkan logo Basarnas + judul **GIS RADAR OPERASI SAR KENDARI** ke satu header atas ramping (`~52px`, Opsi A), menghapus duplikasi header ganda (`navbar.php` + sub-header `views/gis.php`).
4. **3 Tombol Bulat Melayang (*Floating Circular Controls*) di Kanvas Peta:** Memindahkan kontrol **Pencarian Lokasi**, **Jenis Peta (Basemap)**, dan **Unit (`NM`/`KM`/`M`)** dari header ke 3 tombol bulat melayang yang tersusun vertikal di pojok kanan atas kanvas peta (`top-3 right-3`), masing-masing membuka popover/form saat diklik.
5. **Sidebar Tools Lebih Lega ke Atas & Lebih Simpel:** Merampingkan lebar (`360px`), padding, tab bar, dan mengganti tombol-tombol lebar (`w-full`) di tiap tab menjadi bar aksi 1 baris yang ringkas sehingga ruang vertikal daftar komponen meningkat sekitar **+130px hingga +170px**.

---

## 2. Arsitektur Solusi Per Modul

### 2.1 Navigasi #1 — Capture Peta LKP / Rencana Kembali ke Draf RENOPS (Same-Tab)

**Akar masalah saat ini:**
- `assets/js/gis_master.js:8398` memberi `target="_blank"` pada tombol `🖨️ Lihat Cetakan`.
- `views/operasi/partials/renops_smeac_doc.php:102` tidak membawa ID draf RENOPS pada tautan Capture LKP (`capture_doc=RENOPS`), karena `capture_ref` sengaja dikosongkan agar snapshot LKP disimpan 1x per operasi (`id_referensi = NULL`). Akibatnya `getPrintUrlForSelection()` meleset ke RENOPS hari pertama atau ke `tab=cetak`.

**Desain perubahan:**
1. **`views/operasi/partials/renops_smeac_doc.php`:**
   - Tambahkan parameter konteks `&from_renops=<?= (int)($meta['id_renops'] ?? 0) ?>` pada `$capturePetaUrl` (LKP) dan `$capturePetaRencanaUrl` (Peta Rencana).
   - `capture_ref` tetap **tidak** dikirim untuk LKP agar semantik penyimpanan database (`id_referensi = NULL`) tidak berubah sama sekali.
2. **`views/gis.php`:**
   - Parse `$fromRenopsParam = isset($_GET['from_renops']) && (int)$_GET['from_renops'] > 0 ? (int)$_GET['from_renops'] : null;`.
   - Ekspos `fromRenopsParam` di objek `window.SAR_INITIAL_GIS_DATA`.
3. **`assets/js/gis_master.js`:**
   - Pada `getPrintUrlForSelection(idOp, jenis, idRef)`:
     - Prioritas resolusi `ref`:
       1. `idRef` (bila capture per-hari seperti `RENOPS_RENCANA`)
       2. `initialData.fromRenopsParam` **hanya jika** `parseInt(idOp, 10) === parseInt(initialData.currentOperasiId || 0, 10)` (mencegah kebocoran `id_renops` jika operator mengganti dropdown operasi lain di panel Capture!)
       3. `cachedOperasiRenopsList[0]?.id_renops`
       4. Fallback: `${BASE_URL}/operasi_detail.php?id=${idOp}&tab=cetak`
   - Pada `updateSavedStatusBanner()`:
     - **Hapus `target="_blank"`** pada tautan aksi sehingga selalu berpindah di tab yang sama.
     - Tampilkan label yang kontekstual & jelas: jika `fromRenopsParam` cocok dengan operasi aktif dan URL menuju `renops_dokumen`, tampilkan `← Kembali ke Draf RENOPS` (atau tetap `🖨️ Lihat Cetakan` di tab yang sama bila dibuka dari halaman lain).

---

### 2.2 Navigasi #2 — Context-Aware Back Button pada BRA SAR

**Akar masalah saat ini:**
- `views/operasi/partials/tab_bra.php` menautkan Lihat/Edit/Buat BRA tanpa konteks asal.
- `views/bra/view.php:56` dan `views/bra/form.php:1275` men-hardcode tombol kembali/batal ke `/bra.php`.
- Catatan penting: `views/bra/form.php:27` sudah memakai parameter `$_GET['from']` untuk preset template auto-draft (`laporan_awal`, `renops`, `mapel`). Oleh karena itu **dilarang** memakai ulang nama parameter `from` agar tidak bentrok dengan generator draf BRA.

**Desain perubahan:**
1. **Parameter konteks terdedikasi & aman (Anti Open-Redirect):**
   - Gunakan pasangan query parameter `return_to=operasi_bra&return_op=<id_operasi_positif>`.
   - Buat helper terpusat di `controllers/BraController.php` (`resolveReturnContext()` & `buildReturnQuery()`): hanya menerima `return_to === 'operasi_bra'` dan `return_op` integer positif (`preg_match('/^[1-9][0-9]{0,9}$/')`). Selain itu fallback aman ke `/bra.php`.
2. **`views/operasi/partials/tab_bra.php`:**
   - Tambahkan `&return_to=operasi_bra&return_op=<?= (int)$id ?>` pada tautan:
     - **Lihat Dokumen** (`bra.php?view=...`)
     - **Edit** (`bra.php?action=edit&id=...`)
     - **Buat BRA Operasi / Buat BRA Pertama** (`bra.php?action=create&kategori=OPERASI_SAR&id_operasi=...`)
3. **`controllers/BraController.php`:**
   - `showDocument()` meneruskan `$backUrl`, `$backLabel`, dan `$returnQuery` ke `views/bra/view.php`.
   - `showForm()` meneruskan `$backUrl`, `$returnTo`, `$returnOp`, dan `$returnQuery` ke `views/bra/form.php`.
   - `save()` membaca `return_to` & `return_op` dari `$_POST` (hidden input) dan meneruskannya pada redirect sukses (`/bra.php?view={$id}{$returnQuery}`) maupun redirect gagal (`/bra.php?action=...{$returnQuery}`).
   - Portal verifikasi publik (`$isGuest === true`) dan ekspor PDF (`?format=pdf`) tidak terpengaruh sama sekali.
4. **`views/bra/view.php` & `views/bra/form.php`:**
   - Tombol kembali di `views/bra/view.php` memakai `$backUrl` (default `/bra.php` jika dibuka langsung dari arsip, atau `/operasi_detail.php?id=<return_op>&tab=bra` jika dibuka dari Detail Operasi) dengan `title` adaptif (`Kembali ke BRA Operasi SAR` vs `Kembali ke Daftar`).
   - Tombol **Edit Form** di `views/bra/view.php` menyertakan `$returnQuery` agar alur `Detail Ops → Lihat BRA → Edit → Simpan → Lihat BRA → Kembali` tetap pulang ke sub-tab BRA Detail Operasi.
   - Tombol **Batal** di `views/bra/form.php` memakai `$backUrl`, dan `<form>` menyimpan `<input type="hidden" name="return_to">` + `<input type="hidden" name="return_op">`.

---

### 2.3 UI Ploting Peta #1 — Unified Single Slim Header (Opsi A)

**Desain perubahan:**
1. **`views/layout/navbar.php`:**
   - Jika `($currentRoute ?? '') === 'gis'`, jangan render elemen `<header class="... top-navbar ...">` generik — langsung buka `<main class="page-body">`.
   - Seluruh halaman lain selain `gis` tetap tidak berubah 100% (termasuk atribut `print:hidden no-print` yang diverifikasi oleh `tests/test_bra_perfection.php`).
2. **`assets/css/style.css`:**
   - Ubah `body.route-gis .page-body` menjadi `height: 100vh !important;` (sebelumnya `calc(100vh - 56px)`) sehingga `#gisRootContainer` langsung menempel di `top: 0`.
3. **`views/gis.php` — Header Tunggal (`#gisUnifiedHeader`):**
   - Kelas `print:hidden no-print top-navbar cockpit-glass h-13/min-h-[52px] px-3 py-1.5 flex items-center justify-between gap-2 shrink-0 z-30 border-b`.
   - **Sisi Kiri (Navigasi, Brand & Konteks Misi):**
     - `#mobile-menu-btn` (`lg:hidden`) — ID & ikon identik dengan `navbar.php` agar drawer sidebar utama tetap berfungsi di mobile.
     - `#btnToggleGisSidebar` — tombol ikon ringkas (`w-8 h-8` atau pill kecil) dengan `#iconSidebarClose`, `#iconSidebarOpen`, dan `<span id="labelSidebarToggle" class="sr-only">` agar rapi tanpa memakan lebar horizontal.
     - **Brand Terpadu:** Logo Basarnas (`w-7 h-7`) + judul **GIS RADAR OPERASI SAR KENDARI** + badge tipis `SULTRA OPS` (`hidden xl:inline-flex`).
     - **Selector Misi Ops:** `#selectGisOperasi` (ringkas `max-w-[195px]`), `#wrapperIncludeBase` (`+ Base`), `#btnResetGisOperasi`, dan shortcut `Ke Operasi` (`operasi_detail.php?id=...`) saat mode operasi aktif.
   - **Sisi Kanan (Aksi Spasial & Utilitas Global):**
     - `#btnToggleCaptureMode` (`Capture`, emerald pill)
     - `#btnToggleExportMenu` (`Ekspor`, orange pill)
     - `#btnOpenImportKmlModal` (`Impor`, sky pill)
     - Pemisah vertikal tipis
     - `#btnToggleFullscreen` (ikon layar penuh `w-8 h-8`)
     - `#theme-toggle-btn` dengan `#theme-icon` (ID identik dengan `navbar.php` agar `app.js` global theme switcher bekerja otomatis).

---

### 2.4 UI Ploting Peta #2 — 3 Tombol Bulat Melayang di Pojok Kanan Atas Peta

**Penempatan & Struktur:**
- Ditempatkan di dalam `#gisMapWrapper`: `id="gisFloatingMapDock"`, posisi `absolute top-3 right-3 z-[405] flex flex-col items-end gap-2`.
- Mengikuti standar aksesibilitas `modern-web-guidance` (`toolbar` + `icons`):
  - Atribut `role="toolbar" aria-orientation="vertical" aria-label="Kontrol Peta Taktis" aria-controls="gisLeafletMap" focusgroup="toolbar wrap block"`.
  - Fallback JS roving tabindex (`ArrowDown` / `ArrowUp` / `Home` / `End`) dipasang **hanya** jika `!('focusGroup' in HTMLElement.prototype)`.
  - Setiap tombol bulat memiliki ukuran minimal `40×40px` (`min-inline-size: 2.5rem; min-block-size: 2.5rem`), membesar ke `2.75rem` pada `@media (pointer: coarse)`, outline `:focus-visible` yang jelas dengan offset, `<svg aria-hidden="true">`, `aria-label`, `title`, `aria-expanded="false"`, dan `aria-controls`.
  - Efek hover dibungkus `@media (hover: hover)` dan transisi dibungkus `@media (prefers-reduced-motion: no-preference)`.

**3 Tombol Bulat & Popover-nya (semua membuka ke kiri `right-12 top-0`):**
1. **🔍 Pencarian Lokasi (`#btnFloatingSearchToggle` → `#floatingSearchPopover`):**
   - Popover kapsul kaca horizontal (`width: min(340px, calc(100vw - 5rem))`) yang membungkus elemen `#locationSearchBarWrapper`, `#locationSearchInput`, `#btnClearLocationSearch`, `#locationSearchResultsPopover`, dan `#locationSearchResultsList` yang sudah ada.
   - Membuka popover otomatis memfokuskan `#locationSearchInput`.
2. **🗺️ Jenis Peta / Basemap (`#btnFloatingBasemapToggle` → `#floatingBasemapPopover`):**
   - Tombol bulat ikon layer peta + indikator dot/tooltip jenis peta aktif.
   - Popover berisi 4 tombol `.basemap-pill-btn` (`data-layer="hybrid|satellite|roadmap|terrain"`) dalam grid 2×2 yang bersih dan mudah dipilih. Memilih layer langsung menerapkan basemap dan menutup popover.
3. **📏 Satuan Unit (`#btnFloatingUnitToggle` → `#floatingUnitPopover`):**
   - Tombol bulat menampilkan kode satuan aktif secara langsung di tengah lingkaran (`<span id="floatingUnitBadge">NM</span>`, font mono tebal) sehingga operator selalu tahu unit aktif tanpa perlu klik dulu.
   - Popover membungkus `#gisGlobalUnitSwitcher` dengan 3 tombol `.global-unit-pill-btn` (`data-global-unit="NM|KM|M"`), lengkap dengan keterangan singkat (`Nautical Mile`, `Kilometer`, `Meter`). Memilih satuan memperbarui `#floatingUnitBadge` dan menutup popover.

**Aturan Interaksi & Anti-Bentrok:**
- **Single-open:** Membuka satu popover otomatis menutup popover lain.
- **Light-dismiss & Keyboard:** Klik di luar dock (termasuk klik peta) atau menekan tombol `Escape` menutup popover aktif; saat ditutup dengan `Escape`, fokus dikembalikan ke tombol bulat pemicunya.
- **Mode Capture 16:9:** Saat `openCaptureMode()` aktif (karena `#captureControlPanel` juga berada di `top-3 right-3`), `#gisFloatingMapDock` disembunyikan otomatis (`hidden`) dan dimunculkan kembali saat `closeCaptureMode()` selesai.
- **Mode Fullscreen:** Karena berada di dalam `#gisMapWrapper` (di dalam `#gisRootContainer`), seluruh tombol bulat dan popover tetap tampil sempurna saat mode layar penuh.

---

### 2.5 UI Ploting Peta #3 — Sidebar Tools Lebih Lega & Simpel

**Penyederhanaan Struktur (`#gisSidebarPanel`):**
- **Lebar & Collapse:** Rampingkan lebar dari `390px` → `360px` menggunakan kelas CSS terpadu `.gis-sidebar-panel` (`width: var(--gis-sidebar-w, 360px)`) dan `.gis-sidebar-panel.is-collapsed` (`margin-left: calc(var(--gis-sidebar-w, 360px) * -1)`), menggantikan kelas duplikat `-ml-[390px]` di 4 lokasi pada `assets/js/gis_master.js` dengan 1 helper terpusat `setSidebarVisibility(visible)`.
- **Mode Banner (`#gisSidebarOperasiBanner`):** Ringkas 1 baris tipis (`px-3 py-1.5 text-[11px]`).
- **4 Tabs Header:** Padding diperkecil (`py-2 px-1 gap-0.5 text-[10.5px]`) dengan ikon `w-3.5 h-3.5`. **Wajib mempertahankan string label & span badge yang dikunci oleh test suite:**
  - `Titik (<span id="pointsBadgeCount"><?= $initialPointsCount ?></span>)`
  - `Search Area (<span id="polygonsBadgeCount"><?= $initialPolygonsCount ?></span>)`
  - `Pola SAR (<span id="patternsBadgeCount"><?= $initialPatternsCount ?></span>)`
  - `Jarak & ETA (<span id="routesBadgeCount"><?= $initialRoutesCount ?></span>)`
- **Bar Aksi Ringkas per Tab (menghapus tombol lebar `w-full` oranye):**
  - **Tab Titik (`#tab-points`):** 1 baris aksi (`flex items-center gap-1.5`):
    - `#btnShowPointCreator` — pill primer ringkas (`+ Titik`)
    - `#btnToggleDragLock` — pill toggle sekunder (`flex-1`, `#iconLockClosed`, `#iconLockOpen`, `#labelLockStatus`)
    - `#btnFocusKansarQuick` — tombol ikon ringkas (`w-8 h-8`) fokus Kansar Kendari
  - **Tab Search Area (`#tab-polygon`):** 1 baris header daftar (`flex items-center justify-between gap-2`) — judul `Sektor Area Tersimpan` di kiri + `#btnStartNewPolygon` pill ringkas (`+ Area`) di kanan.
  - **Tab Pola SAR (`#tab-pattern`):** 1 baris header daftar — judul `Pola Lintasan Tersimpan` di kiri + `#btnStartNewPattern` pill ringkas (`+ Pola SAR`) di kanan.
  - **Tab Jarak & ETA (`#tab-measure`):** 1 baris header daftar — judul `Rute Terukur Tersimpan` di kiri + `#btnStartNewRoute` pill ringkas (`+ Rute`) di kanan.
- **Padding Tab Pane:** Dari `p-3.5 space-y-3.5` → `p-2.5 space-y-2.5`.

---

## 3. Kompatibilitas & Rencana Pengujian

1. **Kompilasi CSS:** Jalankan `npm run build:css` agar seluruh utility Tailwind baru masuk ke `assets/css/tailwind.css` secara offline.
2. **Regresi Penuh:**
   - PHP lint (`php -l`) pada seluruh berkas PHP yang diubah.
   - JS syntax check (`node --check assets/js/gis_master.js`).
   - Seluruh 7 unit test JS (`node tests/js/*.test.js`).
   - Seluruh 92+ test suite PHP (`DB_DATABASE=qrsar_test php tests/run_all.php`).
3. **Tes Otomatis Baru:** Tambahkan berkas tes khusus (`tests/test_navigasi_renops_bra_dan_gis_ui.php`) yang memverifikasi:
   - Tautan Capture LKP & Rencana membawa `from_renops`, `gis_master.js` tidak lagi memakai `target="_blank"`, dan resolusi URL `getPrintUrlForSelection` memprioritaskan `fromRenopsParam` hanya untuk operasi yang sama.
   - Tautan BRA di `tab_bra.php` membawa `return_to=operasi_bra&return_op=...`, `BraController` memvalidasi ketat parameter (menolak URL eksternal/non-integer), dan `views/bra/view.php` + `views/bra/form.php` mengarahkan tombol kembali ke `/operasi_detail.php?id=...&tab=bra`.
   - `views/layout/navbar.php` menyembunyikan header duplikat pada `route === 'gis'`, `views/gis.php` memiliki `#gisUnifiedHeader`, `#gisFloatingMapDock` (3 tombol bulat + ARIA toolbar), semua ID GIS lama tetap ada, dan `sidebar` memakai bar aksi ringkas.
4. **Verifikasi Visual Browser:** Buka halaman lokal `http://127.0.0.1:8000` (GIS Plotting, Draf RENOPS → Capture → Kembali, Detail Operasi → Tab BRA → View BRA → Kembali) untuk memverifikasi visual, mode gelap/terang, responsivitas, dan 0 error konsol.
5. **Pemeliharaan Graphify:** Jalankan `graphify update .` setelah seluruh perubahan selesai.
