# Edit Laporan Penutupan Tanpa Reopen Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengizinkan pengeditan Laporan Penutupan Operasi SAR (SITREP Penutupan) saat operasi berstatus CLOSED tanpa perlu membuka kembali operasi, mempertahankan `hari_ke` asli dokumen agar tidak bermutasi menjadi `H + X`, dan menegaskan aksi pembukaan kembali operasi khusus untuk perpanjangan operasi SAR.

**Architecture:** Modifikasi guard otorisasi tombol Edit di partial view `tab_sitrep.php`, perbaiki preservasi `hari_ke` pada `OperasiController.php` (baik pada pembacaan GET maupun penyimpanan POST), perjelas copy visual pembukaan kembali operasi untuk perpanjangan SAR, dan validasi dengan automated regression test.

**Tech Stack:** PHP 8+, MySQL/MariaDB, Tailwind CSS, Vanilla JS, PHP Test Runner (`tests/run_all.php`).

**Spec:** `docs/superpowers/specs/2026-11-13-edit-penutupan-tanpa-reopen-design.md`

## Global Constraints
- Operasi harus tetap berstatus `CLOSED` setelah Laporan Penutupan diedit dan disimpan.
- Nilai `hari_ke` pada Laporan Penutupan tidak boleh berubah menjadi `H + X` jika tanggal laporan tidak diubah.
- Seluruh 88+ test suite PHP (`DB_DATABASE=qrsar_test php tests/run_all.php`) dan 6 test suite JS (`node --test tests/js/*.test.js`) harus 100% PASS.

## Review Focus
1. Upaya edit laporan biasa (jenis 'PERKEMBANGAN') saat operasi CLOSED harus tetap ditolak (`403` / Flash error redirect).
2. Perubahan pada naskah atau detail Laporan Penutupan saat CLOSED harus tersimpan tanpa menghapus `waktu_selesai`.
3. Kolom `waktu_reopen` pada tabel `Operasi` harus tetap `NULL` saat mengedit Laporan Penutupan.
4. Nilai `hari_ke` yang tersimpan (misal `H.3` atau `H.7`) tidak boleh bergeser atau berubah format.
5. Tombol "Buka Kembali" / "Perpanjang Operasi" tetap berfungsi normal saat komandan operasi memutuskan memperpanjang operasi SAR.

---

### Task 1: Automated Regression Test Suite (TDD RED)

**Files:**
- Create: `tests/test_penutupan_edit_without_reopen.php`
- Modify: `tests/run_all.php`

**Interfaces:**
- Consumes: `OperasiModel`, `LaporanModel`, `OperasiController`
- Produces: Test suite yang memverifikasi bahwa Laporan Penutupan dapat diedit langsung saat `CLOSED`, status operasi tetap `CLOSED`, dan `hari_ke` tidak berubah menjadi `H + X`.

- [ ] **Step 1: Write the failing test in `tests/test_penutupan_edit_without_reopen.php`**
  Buat test suite yang:
  1. Menyiapkan data operasi dummy.
  2. Menerbitkan laporan perkembangan H.1.
  3. Menerbitkan laporan penutupan (jenis `PENUTUPAN`, hari `H.2`). Verifikasi status operasi menjadi `CLOSED`.
  4. Mensimulasikan pemanggilan update laporan penutupan via `LaporanModel::update` atau alur controller dengan teks baru tanpa membuka operasi.
  5. Memverifikasi bahwa data laporan terbarui, `hari_ke` tetap `H.2`, dan status operasi tetap `CLOSED` dengan `waktu_reopen` tetap `NULL`.
  6. Menguji bahwa view `tab_sitrep.php` merender tombol Edit untuk laporan penutupan meskipun `$isOpen` bernilai `false`.

- [ ] **Step 2: Daftarkan test di `tests/run_all.php` dan jalankan untuk memverifikasi kegagalan atau status awal**
  Jalankan: `DB_DATABASE=qrsar_test php tests/test_penutupan_edit_without_reopen.php`

---

### Task 2: Controller & View Implementation (TDD GREEN)

**Files:**
- Modify: `views/operasi/partials/tab_sitrep.php`
- Modify: `controllers/OperasiController.php`
- Modify: `views/operasi/sitrep_form.php`
- Modify: `views/operasi/detail.php`

**Interfaces:**
- Consumes: `$isOpen`, `$isPenutupan`, `$laporan`, `$operasi`
- Produces: UI yang menampilkan tombol Edit untuk Laporan Penutupan saat `CLOSED`, controller yang mempertahankan `$laporan['hari_ke']`, dan label perpanjangan operasi yang jelas.

- [ ] **Step 1: Update `views/operasi/partials/tab_sitrep.php`**
  - Ubah kondisi tombol Edit di baris 107 menjadi `<?php if ($isOpen || $isPenutupan): ?>`.
  - Pada banner operasi CLOSED (baris 28-40), tambahkan keterangan bahwa Laporan Penutupan tetap dapat diedit secara langsung, dan ubah teks tombol menjadi *"Perpanjang Operasi SAR (Buka Hari Lanjutan)"*.

- [ ] **Step 2: Update `controllers/OperasiController.php`**
  - Pada method `sitrepForm()` penanganan POST:
    Pertahankan `$laporan['hari_ke']` bila mode edit aktif dan tanggal laporan tidak berubah.
  - Pada method `sitrepForm()` penanganan GET:
    Gunakan `$hariKe = !empty($laporan['hari_ke']) ? $laporan['hari_ke'] : TemplateHelper::hitungHariKe($operasi, $tglLap);`.

- [ ] **Step 3: Update `views/operasi/sitrep_form.php` & `views/operasi/detail.php`**
  - Di `sitrep_form.php`: Jika operasi berstatus CLOSED dan sedang dalam mode edit laporan penutupan, pastikan opsi jenis laporan tetap terkunci pada Penutupan agar integritas status CLOSED terjaga.
  - Di `detail.php`: Tegaskan tombol di header saat closed menjadi *"Perpanjang Operasi"*.

- [ ] **Step 4: Run test to verify it passes**
  Jalankan: `DB_DATABASE=qrsar_test php tests/test_penutupan_edit_without_reopen.php`
  Expected: All assertions PASS.

---

### Task 3: Full Verification & Sanity Checks

**Files:**
- Test: All test suites in `tests/` and `tests/js/`

- [ ] **Step 1: Run all PHP test suites**
  Run: `DB_DATABASE=qrsar_test php tests/run_all.php`
  Expected: 100% test suites PASS.

- [ ] **Step 2: Run all JavaScript test suites**
  Run: `node --test tests/js/*.test.js`
  Expected: 100% PASS.

- [ ] **Step 3: Commit all changes cleanly**
  Git commit dengan pesan deskriptif.

