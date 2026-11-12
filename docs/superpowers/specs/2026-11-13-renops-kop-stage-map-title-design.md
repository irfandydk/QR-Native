# Design Document: Optimasi KOP Dokumen, Operation Stage Kumulatif (H1-H7), & Judul Peta Taktis Lengkap

**Tanggal:** 2026-11-13  
**Status:** Menunggu Persetujuan Pengguna (Awaiting Approval)  
**Tujuan:** Menyempurnakan dokumen Rencana Operasi SAR (SMEAC) dan Studio Capture Peta GIS agar KOP tidak menduplikasi jenis kejadian, Bagian D (Operation Stage) menampilkan kronologi bertahap H1 s/d H7 secara kumulatif, serta judul operasi pada peta LKP dan rencana operasi tampil lengkap tanpa ID operasi.

---

## 1. Intent & Why
- **KOP Dokumen RENOPS**: Baris ke-3 saat ini menggabungkan jenis kejadian dan nama operasi (`Operasi SAR [jenis_kejadian]: [nama_operasi]`). Jika jenis kejadian sudah terkandung di dalam judul operasi, terjadi duplikasi kata yang tidak sedap dipandang pada dokumen resmi dinas.
- **D. Operation Stage Kumulatif (H1 s/d H7)**: Dokumen Rencana Operasi SAR hari ke-$N$ (misal H2, H3 s/d H7) memerlukan rekapitulasi riwayat tahapan operasi dari Hari ke-1 beserta hasil/perkembangannya, bukan hanya mengambil 1 lapbang hari kemarin secara sepihak dan menghilangkan pengerahan awal Hari ke-1.
- **Judul Peta Taktis 16:9 (Peta LKP & Rencana Operasi)**: Pada HUD overlay capture peta, judul operasi diawali dengan `OPS #[ID] - ` dan dipotong paksa pada 62 karakter (`.substring(0, 62)`), sehingga judul panjang terpotong di tengah jalan dan memuat ID internal yang tidak diinginkan dalam dokumen resmi.

---

## 2. Goals, Non-Goals, Anti-Goals

### Goals:
1. **KOP RENOPS**: Menampilkan judul operasi SAR murni di baris ke-3 KOP tanpa menyisipkan jenis kejadian dan tanpa menambahkan prefix "OPERASI SAR" (langsung nama operasi murni), serta terintegrasi sebagai seksi yang dapat disunting langsung (*editable text section*) pada draf dokumen.
2. **D. Operation Stage (Pilihan A: Struktur Berlabel Hari)**:
   - Jika H1: Menampilkan waktu pengerahan awal, jumlah personel & tim pemberangkatan, serta hasil/perkembangan H1 bila sudah tersedia.
   - Jika H2: Menampilkan riwayat tahap Hari ke-1 (pemberangkatan & perkembangan H1), lalu dilanjutkan rencana/pelaksanaan Hari ke-2.
   - Jika H3: Menampilkan riwayat tahap H1, riwayat tahap H2, lalu dilanjutkan rencana/pelaksanaan Hari ke-3.
   - Jika H4 s/d H7: Menyajikan akumulasi kronologis seluruh hari sebelumnya (H1 s/d HN-1) beserta perkembangan masing-masing, ditutup dengan pelaksanaan hari berjalan.
3. **Judul Peta Taktis Lengkap (LKP & Rencana Operasi)**:
   - Menghapus prefix ID operasi `OPS #[ID] - `.
   - Menghapus batasan kaku `.substring(0, 62)`.
   - Mengimplementasikan penyesuaian teks (auto-wrapping / auto-scaling font) dan box HUD yang proporsional sehingga judul tampil utuh 100%.

### Non-Goals:
- Mengubah struktur seksi SMEAC lain (A. Situation, B. Mission, C. Execution, E. Admin & Logistik, F. Struktur).
- Mengubah format sandi tanggal militer (DTG) atau format koordinat DMS.

### Anti-Goals:
- Jangan menghilangkan data `isi_laporan` lapbang yang sudah ada di masa lampau.
- Jangan sampai teks judul peta bertabrakan dengan kompas mata angin di pojok kanan atas.

---

## 3. Approaches Considered & Trade-offs

### Poin 2: D. Operation Stage
- **Pilihan A (Dipilih Pengguna)**: Struktur berlabel hari per paragraf:
  - `Hari Ke-1:` [Pemberangkatan & Perkembangan H1]
  - `Hari Ke-2:` [Pelaksanaan & Perkembangan H2]
  - `Hari Ke-N (Rencana Pelaksanaan):` [Waktu mulai ops, personel, SRU]
- **Pilihan B (Dieliminasi)**: Paragraf narasi mengalir tanpa label hari.
- *Alasan*: Pilihan A memberikan kejelasan struktural bagi pimpinan/SMC dalam meninjau progres pencarian hari per hari secara cepat.

---

## 4. Detailed Specification

### A. KOP Dokumen RENOPS (`views/operasi/partials/renops_smeac_doc.php` & `services/RenopsDokumenService.php`)
1. Di `RenopsDokumenService::defaultSections()`:
   Menambahkan seksi `'judul_operasi' => $nama` ke dalam array seksi dokumen.
2. Di `views/operasi/partials/renops_smeac_doc.php`:
   - `$kopBaris3 = trim((string)($S['judul_operasi'] ?? $namaOps));` (langsung judul operasi murni tanpa awalan "OPERASI SAR" dan tanpa duplikasi `jenis_kejadian`).
   - Baris ke-3 KOP dibungkus dengan atribut editable draft:
     ```html
     <p class="k3 sec"<?= $sec('judul_operasi', 'text') ?>><span class="sec-body"><?= e($kopBaris3) ?></span></p>
     ```
   - Dengan ini, pengguna dapat menyunting judul di KOP secara langsung pada lembar draf dokumen (`renops_dokumen.php`) dan perubahan tersimpan persisten ke dalam draf.

### B. Cumulative Operation Stage (`services/RenopsDokumenService.php`)
Di dalam `defaultSections()`, alih-alih hanya mengecek 1 `$isiLapbang` terakhir, kita buat fungsi kronologis:
1. Ambil pengerahan awal H1 dari `$ops['waktu_berangkat']`, `$ops['detail_tim_berangkat']`, dan personel awal.
2. Petakan seluruh laporan harian (`$ops['laporan_harian']`) berdasarkan nomor hari absolut (H1, H2, dst).
3. Petakan seluruh renops (`$ops['renops']`) berdasarkan nomor hari absolut.
4. Untuk setiap hari $i$ dari 1 sampai $n-1$:
   - Hari ke-1: Gabungkan pengerahan awal dan perkembangan pencarian H1 (dari Lapbang H1 bila ada).
   - Hari ke-$i$ ($i \ge 2$): Cantumkan waktu mulai operasi H-$i$ dan perkembangan pencarian H-$i$ (dari Lapbang H-$i$ bila ada).
5. Hari ke-$n$ (Hari berjalan):
   - Cantumkan pelaksanaan hari berjalan: `"Hari ke-{$label} (Rencana Pelaksanaan):\nPada tanggal {$wMulai}, Operasi SAR Hari ke-{$label} dilanjutkan dengan {$personelStr} personel."`
6. Gabungkan seluruh blok hari dengan pemisah baris ganda `\n\n`.

### C. Judul Peta Taktis (`assets/js/gis_master.js`)
Di dalam `drawCaptureHudOverlay(ctx, w, h)`:
1. `opTitle = opObj ? (opObj.nama_operasi || 'OPERASI SAR') : 'PETA TAKTIS OPERASI SAR';`
2. Ukur panjang teks:
   - Jika teks cukup panjang, buat auto-wrapping 2 baris atau sesuaikan font (11px - 13px) dan perlebar box HUD secara dinamis (maksimal `w - 100` agar tidak mengenai kompas di `w - 42`).
   - Hapus pemotongan `.substring(0, 62)`.

---

## 5. Verification Plan
1. **Uji Dokumen RENOPS (H1, H2, H3)**:
   - Tes H1 menghasilkan Operation Stage pengerahan H1.
   - Tes H2 menghasilkan Operation Stage berisi rekapitulasi H1 + pelaksanaan H2.
   - Tes H3 menghasilkan Operation Stage berisi rekapitulasi H1 + rekapitulasi H2 + pelaksanaan H3.
2. **Uji KOP Dokumen**:
   - Memastikan baris ke-3 KOP bersih tanpa jenis kejadian ganda.
3. **Uji Capture Peta Taktis**:
   - Memverifikasi `assets/js/gis_master.js` tidak lagi menyertakan `OPS #` dan tidak memotong judul pada 62 karakter.
4. **Regresi Penuh**:
   - Menjalankan `DB_DATABASE=qrsar_test php tests/run_all.php` (84/84 lolos).

