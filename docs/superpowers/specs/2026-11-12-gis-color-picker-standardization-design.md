# Design Document: Standarisasi Color Picker & Visual Styling GIS (Search Area & Pola SAR)

**Tanggal:** 2026-11-12  
**Status:** Menunggu Persetujuan Pengguna (Awaiting Approval)  
**Tujuan:** Menyamakan antarmuka pemilihan warna (*color styling*) pada Tab Search Area dan Tab Pola SAR agar seragam dengan standar baru pada Tab Jarak & ETA (Rute Navigasi).

---

## 1. Intent & Why
Pada implementasi sebelumnya di Tab Jarak & ETA (Rute Navigasi), pengguna telah diberikan kontrol penataan gaya rute yang sangat rapi dan intuitif:
- 8 titik palet cepat standar operasi SAR (*preset swatch dots* dengan status cincin aktif).
- Baris khusus warna kustom (`<input type="color">`) lengkap dengan label teks HEX realtime.
- Slider ketebalan dan transparansi yang responsif langsung ke Leaflet map overlay.

Namun, pada **Tab Search Area** (Sektor Area Pencarian) dan **Tab Pola SAR** (Pola Lintasan IAMSAR), antarmuka pemilihan warna belum seragam:
- Search Area baru memiliki titik palet statis tanpa kemampuan memilih warna kustom bebas maupun pembacaan kode HEX.
- Pola SAR memiliki pemilih warna dasar yang terpasang secara inline bersama tombol kotak kaku tanpa label HEX dan tanpa standarisasi palet 8 warna.

Penyamaan ini bertujuan agar operator SAR di Kansar/Pos SAR memiliki kendali visual penuh yang konsisten, cepat, dan profesional saat merencanakan sektor area maupun lintasan pencarian.

---

## 2. Goals, Non-Goals, Anti-Goals

### Goals:
1. **Search Area (Garis & Arsiran)**:
   - Menyediakan pemilih warna kustom (`<input type="color">`) dan label HEX untuk **Warna Garis Border** (`polyStrokeColor`).
   - Menyediakan pemilih warna kustom (`<input type="color">`) dan label HEX untuk **Warna Isi Arsiran** (`polyFillColor`).
   - Mempertahankan pemisahan independen antara warna garis dan warna isi (peraturan *non-overwriting*).
   - Menyelaraskan 8 preset swatch dots dengan styling Jarak & ETA.
2. **Pola SAR (Garis Lintasan)**:
   - Merapikan blok warna garis lintasan pola pencarian menjadi format seragam:
     - Header dengan label HEX aktif berwarna.
     - 8 titik palet preset standar operasi SAR dengan indikator seleksi aktif (*ring border*).
     - Baris khusus "Warna Kustom:" dengan `<input type="color">` dan label HEX realtime.
3. **Reaktivitas Peta Dua Arah (*Two-Way Reactive Binding*)**:
   - Perubahan warna langsung terefleksikan pada kanvas peta Leaflet secara *real-time*.
   - Saat mengedit area atau pola tersimpan, warna kustom dan status titik palet dipulihkan secara presisi.

### Non-Goals:
- Mengubah skema database backend (`strokeColor`, `fillColor`, `strokeOpacity`, `fillOpacity` sudah tersedia dan didukung oleh `GisModel`).
- Mengubah algoritma pembentukan koordinat geometri (poligon, lingkaran, persegi, maupun lintasan sapuan IAMSAR).

### Anti-Goals:
- Jangan menggabungkan kembali warna garis dan warna isi di Search Area (harus tetap terpisah).
- Jangan merusak kompatibilitas pengujian regresi yang menguji keberadaan ID elemen seperti `#polyFillColorSwatches` dan `#polyStrokeOpacitySlider`.

---

## 3. Approaches Considered & Trade-offs

### Pendekatan A: Standarisasi Sistem Palet Seragam (Dipilih oleh Pengguna)
- Menyamakan komponen kontrol warna di seluruh tab GIS:
  - Palet 8 warna taktis maritim: Hijau Emerald (`#10b981`), Oranye Basarnas (`#ea580c`), Biru Laut (`#0284c7`), Merah Bahaya (`#ef4444`), Ungu Taktis (`#8b5cf6`), Kuning Cerah (`#f59e0b`), Cyan (`#06b6d4`), Putih Kontras (`#ffffff`).
  - Baris kustom `<input type="color">` + label teks HEX monospace uppercase.
  - Two-way binding antara swatch, input color, label HEX, dan visualisasi Leaflet.
- *Trade-off*: UX konsisten, intuitif, mendukung kecepatan operasional 1-klik sekaligus fleksibilitas warna tak terbatas.

### Pendekatan B: Color Input Native Saja (Dieliminasi)
- Menghapus swatch dan hanya menyisakan `<input type="color">`.
- *Alasan Ditolak*: Memperlambat kerja operator lapangan karena harus selalu mencari warna secara manual di spektrum.

---

## 4. Detailed Specification & UI Structure

### A. Tab Search Area (`views/gis.php` & `assets/js/gis_master.js`)
1. **Warna Garis (Stroke)**:
   - Header: `1. Warna Garis:` + `<span id="labelStrokeColor">#EA580C</span>`
   - Swatches: `<div id="polyStrokeColorSwatches">` (8 bulatan warna)
   - Baris Kustom:
     ```html
     <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-200/60 dark:border-slate-800">
       <span class="text-slate-600 dark:text-slate-400 font-medium">Warna Garis Kustom:</span>
       <div class="flex items-center gap-2">
         <input type="color" id="polyCustomStrokeColorInput" value="#ea580c" class="...">
         <span id="polyCustomStrokeColorHex" class="font-mono text-xs font-bold ...">#EA580C</span>
       </div>
     </div>
     ```
2. **Warna Isi (Fill/Arsiran)**:
   - Header: `4. Warna Isi (Arsiran):` + `<span id="labelFillColor">#EA580C</span>`
   - Swatches: `<div id="polyFillColorSwatches">` (8 bulatan warna)
   - Baris Kustom:
     ```html
     <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-200/60 dark:border-slate-800">
       <span class="text-slate-600 dark:text-slate-400 font-medium">Warna Isi Kustom:</span>
       <div class="flex items-center gap-2">
         <input type="color" id="polyCustomFillColorInput" value="#ea580c" class="...">
         <span id="polyCustomFillColorHex" class="font-mono text-xs font-bold ...">#EA580C</span>
       </div>
     </div>
     ```
3. **JS Controller (`syncPolyStyleControls`)**:
   - Memperbarui `polyCustomStrokeColorInput.value`, `polyCustomStrokeColorHex.textContent`, `polyCustomFillColorInput.value`, dan `polyCustomFillColorHex.textContent`.
   - Mengikat event listener `input` pada kedua color picker kustom untuk langsung memanggil `updateLivePolygonVisual()`.

### B. Tab Pola SAR (`views/gis.php` & `assets/js/gis_master.js`)
1. **Warna Garis Lintasan (Pattern Line)**:
   - Header: `Warna Garis Lintasan:` + `<span id="labelPatternLineColor">#0284C7</span>`
   - Swatches: `<div id="patternColorSwatches">` (8 bulatan warna)
   - Baris Kustom:
     ```html
     <div class="flex items-center justify-between text-[11px] pt-1.5 border-t border-slate-200/60 dark:border-slate-800">
       <span class="text-slate-600 dark:text-slate-400 font-medium">Warna Kustom:</span>
       <div class="flex items-center gap-2">
         <input type="color" id="patternLineColor" value="#0284c7" class="...">
         <span id="patternCustomColorHex" class="font-mono text-xs font-bold ...">#0284C7</span>
       </div>
     </div>
     ```
2. **JS Controller (`initSearchPatternUI` & `syncPatternStyleControls`)**:
   - Menghubungkan klik swatch dan input color ke pembaruan `activePatternPolyline.setStyle({ color: ... })` secara instan.
   - Sinkronisasi nilai warna saat edit pola tersimpan (`editPattern`).

---

## 5. Verification Plan
1. **Pemeriksaan Visual**:
   - Buka Tab Search Area $\rightarrow$ buat poligon/rectangle/circle $\rightarrow$ pilih swatch dan ubah via custom color picker $\rightarrow$ pastikan garis dan isi berubah sesuai pilihan secara independen.
   - Buka Tab Pola SAR $\rightarrow$ pilih swatch atau pilih custom color $\rightarrow$ pastikan garis lintasan dan panah arah berubah warna seketika.
2. **Pemeriksaan Regresi**:
   - Jalankan `DB_DATABASE=qrsar_test php tests/test_gis_area_style.php` (memastikan aturan swatch isi dan transparansi tetap lolos).
   - Jalankan `DB_DATABASE=qrsar_test php tests/test_audit_modul_6_gis_plotting.php` (memastikan persistensi spasial tetap 100% lolos).
   - Jalankan `node --test tests/js/*.test.js` (memastikan tes unit JS tetap lolos).
   - Jalankan `DB_DATABASE=qrsar_test php tests/run_all.php` (memastikan 82/82 suite pengujian tetap lulus).

