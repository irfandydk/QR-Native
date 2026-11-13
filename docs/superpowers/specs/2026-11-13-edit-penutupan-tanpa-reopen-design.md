# Desain: Izinkan Edit Laporan Penutupan Tanpa Membuka Kembali Operasi (CLOSED)

## Ringkasan Eksekutif
Pengguna membutuhkan kemampuan untuk mengedit data Laporan Penutupan Operasi SAR (SITREP Penutupan) yang telah diterbitkan pada saat operasi berstatus `CLOSED`, tanpa harus membuka kembali operasi (Rollback/Reopen). Jika operasi dibuka kembali, sistem menganggap ada perpanjangan hari pencarian dan mengubah hari operasi menjadi format `H + X` (misalnya `H7 + 1`), padahal aksi membuka kembali operasi secara operasional khusus diperuntukkan bagi **Perpanjangan Operasi SAR**.

## Tujuan (Goals)
1. Menampilkan tombol **Edit** pada kartu Laporan Penutupan di tab SITREP (`views/operasi/partials/tab_sitrep.php`) meskipun status operasi berstatus `CLOSED`.
2. Menjaga nilai `hari_ke` yang tersimpan pada laporan penutupan (misal `H.3` atau `H.7`) agar tidak bermutasi menjadi `H + X` ketika diedit dan disimpan kembali.
3. Memastikan status operasi tetap **CLOSED** dan tanggal penyelesaian tetap konsisten setelah Laporan Penutupan disimpan kembali.
4. Memperjelas banner dan label tombol pembukaan kembali operasi di tab SITREP dan header detail operasi agar eksplisit diperuntukkan bagi **"Perpanjangan Operasi SAR (Buka Hari Lanjutan)"**.
5. Menjamin seluruh 88+ unit & regression test suite PHP dan 6 test suite JS tetap 100% lulus (PASS).

## Non-Goals & Anti-Goals
- **Non-Goal:** Mengizinkan penambahan LAPBANG harian baru saat operasi berstatus CLOSED (penambahan LAPBANG baru tetap mensyaratkan perpanjangan operasi dibuka kembali).
- **Anti-Goal:** Mengubah status operasi menjadi `OPEN` atau mengisi `waktu_reopen` saat pengguna hanya sekadar mengoreksi/mengedit naskah Laporan Penutupan.

## Analisis & Pendekatan Teknis

### 1. `views/operasi/partials/tab_sitrep.php`
- Pada baris 107, tombol edit saat ini dibungkus `<?php if ($isOpen): ?>`.
- Diperbarui menjadi `<?php if ($isOpen || $isPenutupan): ?>` sehingga tombol Edit selalu aktif untuk kartu Laporan Penutupan.
- Pada banner operasi CLOSED (baris 28-40), perjelas pesan bahwa:
  - Penambahan LAPBANG baru dinonaktifkan demi integritas arsip, namun Laporan Penutupan tetap dapat diedit secara langsung jika ada koreksi data.
  - Tombol aksi ditegaskan menjadi *"Perpanjang Operasi SAR"* dengan tooltip/keterangan yang jelas.

### 2. `controllers/OperasiController.php`
- Guard otorisasi di baris 814 telah memiliki:
  `$isExistingPenutupan = ($editMode && $laporan && strtoupper((string)($laporan['jenis_laporan'] ?? '')) === 'PENUTUPAN');`
  `if ($operasi['status'] === 'CLOSED' && !$isExistingPenutupan) { ... }`
- Namun pada saat pengolahan POST (baris 842):
  Sebelumnya selalu menimpa `$_POST['hari_ke'] = TemplateHelper::hitungHariKe($operasi, $tglLap);`.
  Solusi: Jika `$editMode` dan laporan memiliki `hari_ke` yang valid, pertahankan `hari_ke` tersebut bila tanggal laporan tidak berubah:
  ```php
  if ($editMode && !empty($laporan['hari_ke'])) {
      $origTgl = !empty($laporan['waktu_laporan']) ? date('Y-m-d', strtotime($laporan['waktu_laporan'])) : null;
      if ($origTgl === $tglLap) {
          $_POST['hari_ke'] = $laporan['hari_ke'];
      } else {
          $_POST['hari_ke'] = !empty($_POST['hari_ke']) ? trim($_POST['hari_ke']) : TemplateHelper::hitungHariKe($operasi, $tglLap, $laporan['hari_ke']);
      }
  } else {
      $_POST['hari_ke'] = TemplateHelper::hitungHariKe($operasi, $tglLap);
  }
  ```
- Pada saat memuat form GET (baris 868):
  Gunakan `$hariKe = !empty($laporan['hari_ke']) ? $laporan['hari_ke'] : TemplateHelper::hitungHariKe($operasi, $tglLap);` alih-alih menimpa dengan kalkulasi ulang dinamis.

### 3. `views/operasi/sitrep_form.php`
- Pada mode edit laporan penutupan saat operasi CLOSED, pastikan radio pilihan jenis laporan tetap terkunci atau terfokus pada `PENUTUPAN` agar tidak menimbulkan anomali operasi CLOSED tanpa penutupan.

### 4. `views/operasi/detail.php`
- Perjelas tombol aksi pembukaan kembali operasi di header halaman detail dari *"Buka Kembali"* menjadi *"Perpanjang Operasi"*.

### 5. Automated Regression Test
- Buat file test `tests/test_penutupan_edit_without_reopen.php` yang menguji siklus lengkap:
  1. Buat operasi SAR baru.
  2. Terbitkan laporan perkembangan H.1.
  3. Terbitkan laporan penutupan H.2 (operasi otomatis berubah menjadi CLOSED).
  4. Edit laporan penutupan saat operasi masih CLOSED (ubah naskah/debrief).
  5. Verifikasi operasi tetap CLOSED, `waktu_reopen` tetap null, `hari_ke` tetap H.2 (tidak berubah menjadi H + X), dan data hasil edit tersimpan dengan benar.

