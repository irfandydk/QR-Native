# Desain Penyelarasan Format WhatsApp LAPBANG & Penutupan Operasi SAR + Pemisahan Input Cuaca (Opsi A)

**Tanggal:** 2026-11-14  
**Konteks:** [services/WhatsAppTemplateService.php](file:///Users/irfandydk/antigravity/QR-SAR/services/WhatsAppTemplateService.php), [views/operasi/sitrep_form.php](file:///Users/irfandydk/antigravity/QR-SAR/views/operasi/sitrep_form.php), [models/LaporanModel.php](file:///Users/irfandydk/antigravity/QR-SAR/models/LaporanModel.php), [controllers/OperasiController.php](file:///Users/irfandydk/antigravity/QR-SAR/controllers/OperasiController.php), [helpers/HierarchyHelper.php](file:///Users/irfandydk/antigravity/QR-SAR/helpers/HierarchyHelper.php), [api/controllers/OperasiApiController.php](file:///Users/irfandydk/antigravity/QR-SAR/api/controllers/OperasiApiController.php), [assets/js/pages/sitrep_form.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/pages/sitrep_form.js)

## Tujuan & Masalah
Menyelaraskan teks WhatsApp keluaran **Laporan Perkembangan (LAPBANG)** dan **Laporan Perkembangan dan Penutupan Operasi SAR** agar persis mengikuti format resmi terbaru Basarnas KPP Kendari, serta memisahkan input kondisi cuaca (`cuaca`, `gelombang`, `angin`) pada form dan tabel `LaporanHarian` agar pratinjau maupun hasil simpan langsung siap kirim tanpa sunting manual.

## Pendekatan yang Dipilih (Opsi A — Disetujui Pengguna)
1. **Opsi A (Dipilih):** Menambah kolom `gelombang` dan `angin` ke tabel `LaporanHarian` serta menyediakan 3 input terpisah (`Kondisi Cuaca`, `Tinggi Gelombang`, `Arah & Kecepatan Angin`) pada form SITREP (`sitrep_form.php`), otomatis mewarisi data Laporan Awal / LAPBANG sebelumnya, disertai fallback aman untuk data lama.
2. *Alternatif yang tidak dipilih (Opsi B):* Tetap 1 kolom teks `cuaca` bebas — ditolak karena rawan inkonsistensi format baris `Gelombang` dan `Angin`.
3. **Header & Footer WA Bersama:** Menyeragamkan `getHeaderWA()` dan `getFooterWA()` pada `WhatsAppTemplateService.php` sehingga perbaikan spasi, ejaan `izin`, pemisah `=`, titik penutup, dan URL/email huruf kecil berlaku konsisten di semua pesan WA, sementara isi tubuh Laporan Awal & RENOPS yang sudah mapan tetap dipertahankan.

## Spesifikasi Format Naskah WhatsApp

### 1. Header (`WhatsAppTemplateService::getHeaderWA`)
```text
————————-
FILE AKTIF
————————-

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI

{JUDUL_UPPER}[NO: {nomor}]
KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
{Hari, dd Bulan yyyy}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon izin melaporkan {subject}.
====================================
```
- Pemisah atas & bawah `FILE AKTIF`: `————————-` (8 em-dash + 1 hyphen).
- Spasi kosong berada setelah `PROFESIONAL MODERN TERUJI` (bukan setelah judul laporan).
- Pemisah penutup header: 36 karakter `=`.
- Pada `generateLapbangWA`:
  - Normalisasi judul operasi lewat `formatOperasiSubject($ops)`: menghilangkan awalan duplikat `Operasi SAR` / `Ops SAR` jika sudah ada, lalu memastikan format `Operasi SAR <Judul>`.
  - Jenis `PENUTUPAN`: `$subject = "Perkembangan dan Penutupan " . $opsSubject`.
  - Jenis `PERKEMBANGAN`: `$subject = "Perkembangan " . $opsSubject`.

### 2. Hasil Ops SAR & Debriefing (`generateLapbangWA`)
```text
Hasil Ops SAR {H.x}:

- Pukul 08.10 WITA, ...
- Pada tanggal 12 November 2026 pukul 12.10 WITA, Tim SAR Gabungan tiba di posko dan melaksanakan debriefing penutupan terhadap {opsSubject}. ...
```
- Baris isi laporan dinormalisasi menjadi bullet `- ` (mengonversi `• ` / `* ` / `-` tanpa spasi menjadi `- `, membiarkan penomoran `1. ` bila sengaja bernomor, dan menambahkan `- ` pada baris narasi polos; bila kosong diisi `-`).
- Bila `$isPenutupan === true` dan isi laporan belum mengandung kata `debriefing`:
  - Jam debriefing memakai titik: `str_replace(':', '.', SarCalculationService::formatJamWITA($waktuDebrief))`.
  - Jika korban ditemukan seluruhnya (`$stats['dp'] === 0 && ($stats['s'] + $stats['md']) > 0`):
    `"- Pada tanggal {$tglDebrief} pukul {$jamDebrief} WITA, Tim SAR Gabungan tiba di posko dan melaksanakan debriefing penutupan terhadap {$opsSubject}. Dengan ditemukannya korban serta selesainya proses evakuasi dan penyerahan, Operasi SAR dinyatakan ditutup dan unsur SAR yang terlibat kembali ke kesatuannya masing-masing dengan ucapan terima kasih."`
  - Jika masih terdapat DP/H atau nihil:
    `"- Pada tanggal {$tglDebrief} pukul {$jamDebrief} WITA, Tim SAR Gabungan tiba di posko dan melaksanakan debriefing penutupan terhadap {$opsSubject}. Dengan selesainya seluruh rangkaian pencarian, Operasi SAR dinyatakan ditutup dan unsur SAR yang terlibat kembali ke kesatuannya masing-masing dengan ucapan terima kasih."`
  - Digabung dengan `\n` tunggal setelah daftar poin hasil (`Hasil Ops SAR {hariKe}:`).

### 3. Seksi Data Korban, Identitas Korban, Unsur, Alut, & Cuaca
```text
Data Korban: {totalOrang}
- S : {s}
- MD : {md}
- DP : {dp}
- H : {h}

Identitas Korban:
{identitasText}

Unsur SAR Terlibat:
- {nama_unsur} : {jumlah_personil} Orang

Alut dan Palsar:
- {jumlah_alut} {satuan} {nama_alut}

Kondisi Cuaca:
• cuaca : {cuaca}
• Gelombang : {gelombang}
• Angin: {angin}[ (Sumber BMKG)]
```
- Judul seksi tidak lagi memakai angka `1.`..`4.`, seluruhnya diakhiri `:`.
- Rincian korban memakai `Orang` (Title Case, bukan `ORANG`) dan nilai `0` ditulis `-`.
- Unsur & Alut pada `generateLapbangWA` memakai bullet `- ` (menggunakan parameter opsional `$bullet` pada `formatAlutLines()` agar tidak menduplikasi logika pengurutan kategori).
- Kondisi Cuaca fallback ke nilai `Operasi` bila pada `LaporanHarian` kosong, dan menampilkan `-` jika keduanya kosong; ` (Sumber BMKG)` ditambahkan otomatis pada baris `Angin:` ketika data angin terisi dan kata `BMKG` belum tercantum di data cuaca.

### 4. Footer (`WhatsAppTemplateService::getFooterWA`)
```text
Demikian kami laporkan, mohon petunjuk dan arahan.

Ttd

KKS Kendari
==================================================
🏢 Kantor Pencarian dan Pertolongan Kendari
📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116
☎️ Telp. : (0401) 3196557
📠 Fax. : (0401) 3196558
📱 WhatsApp : +62811-4034-115
🚨 Emergency Call : 115
🌐 Website : www.kendari.basarnas.go.id
📧 E-Mail : sar304kendari@gmail.com / pusdatinsarkendari@yahoo.co.id
📲 Medsos : @Basarnaskendari
```

## Non-Goals
- Tidak mengubah format dokumen BRA SAR, PDF SMEAC, maupun seksi tubuh 1–9 pada `generateLaporanAwalWA` / `generateRenopsWA`.
- Tidak menulis ulang otomatis arsip historis `full_text_wa` di DB tanpa aksi edit/simpan.
