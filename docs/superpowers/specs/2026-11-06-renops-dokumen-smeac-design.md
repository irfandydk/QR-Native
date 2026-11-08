# Desain: Dokumen Rencana Operasi SAR (format SMEAC) dengan Draf, Versi Terbit & QR

Tanggal: 2026-11-06
Status: menunggu persetujuan

## 1. Maksud & alasan

Di tab **Cetak** pada detail operasi, tombol RENOPS harus menghasilkan dokumen **persis seperti lampiran**
`Rencana_Operasi_SAR_Hari_ke-1_KMM_Desa_Manuru.docx`. Formatnya SMEAC: Situation, Mission, Implementation,
Operation Stage, Administration & Logistics, serta Koordinasi, Pengendalian & Komunikasi. Dokumen berukuran
A4 landscape dan dibuat satu per hari RENOPS. Isinya otomatis diambil dari data operasi menurut hierarki
laporan.

Sebelum dicetak, dokumen dibuka sebagai **draf yang bisa disesuaikan**. Setelah final, dokumen
**diterbitkan** dengan QR sebagai pengganti tanda tangan. Isi versi terbit **dibekukan**, jadi cetak ulang
kapan pun hasilnya sama persis. Dokumen yang sudah terbit tetap boleh direvisi; revisi menerbitkan QR baru, dan
QR lama memberi tahu bahwa dokumen telah diubah.

## 2. Tujuan, non-tujuan, anti-tujuan

**Tujuan**
- Dokumen RENOPS per hari (H1–H7, termasuk H7+n setelah reopen) dalam format lampiran.
- Isi otomatis mengikuti hierarki laporan (bagian 4).
- Editor draf "edit langsung di kertas" (Opsi A), upload foto dokumentasi, dan isian struktur organisasi.
- Versi terbit beku, revisi menerbitkan versi dan QR baru, serta verifikasi QR publik dengan tiga status.

**Non-tujuan**
- Tidak mengubah Laporan Awal, LAPBANG, Berita Acara Penutupan, maupun BRA.
- Tidak membuat PDF di server. Cetak tetap lewat browser (Cetak / Simpan PDF), ditambah exporter `html2pdf` yang sudah ada.
- Tidak mengubah form RENOPS (`renops_form.php`) maupun plotting GIS.

**Anti-tujuan** (dianggap gagal walau secara teknis jalan)
- Cetak ulang versi terbit berbeda dari aslinya, misalnya karena status korban, peta, atau SRU berubah belakangan.
- QR dokumen lama yang sudah beredar tiba-tiba "tidak valid" padahal dokumennya tidak dihapus.
- Operator harus mengetik ulang data yang sudah ada di sistem.

## 3. Keputusan yang sudah diambil pengguna

| # | Keputusan |
|---|-----------|
| K1 | Hanya dokumen Rencana Operasi SAR. |
| K2 | Area & rencana pencarian diambil dari data plotting GIS + RENOPS (SRU yang diimpor dari sektor plotting). |
| K3 | Struktur organisasi: nama-nama bisa diinput. |
| K4 | Jaring komunikasi tetap seperti lampiran. |
| K5 | Kolom *Total Endurance* di tabel aset dihapus. |
| K6 | Foto dokumentasi: tombol upload, foto otomatis tersusun rapi di bagian dokumentasi. |
| K7 | Tanda tangan diganti QR yang bisa divalidasi. |
| K8 | Sebelum dicetak, dokumen bisa disesuaikan (draf). |
| K9 | Hierarki data per hari (bagian 4). Bila bentrok, RENOPS hari itu yang menang. |
| K10 | Versi terbit dibekukan. |
| K11 | Versi terbit bisa diedit lagi dan terbit QR baru. QR lama → "dokumen telah diubah" + tautan ke dokumen terbaru. RENOPS dihapus → "dokumen tidak valid". |
| K12 | Orang yang memindai QR bisa membuka **isi dokumen lengkap tanpa login**. |
| K13 | **Semua user yang login** (ADMIN & USER) boleh menerbitkan dan merevisi. |
| K14 | Tata letak editor: **Opsi A**, yaitu edit langsung di kertas dengan daftar isi di kiri. |
| K15 | Pendekatan penyimpanan: **A**, yaitu tabel versi dokumen (bagian 6). |
| K16 | Operasi DITUTUP: dokumen RENOPS **boleh** disunting dan diterbitkan. Data RENOPS-nya tetap terkunci. |

Usulan yang disetujui pengguna ("saranmu bagus"):
- Cetak RENOPS portrait lama diganti format baru ini.
- Bila LAPBANG H(N-1) tidak ada, sistem memakai laporan terdekat sebelumnya.
- Struktur organisasi hari N disalin dari hari sebelumnya.
- Draf: bagian yang belum disunting ikut data terbaru, bagian yang sudah disunting dipertahankan dan punya tombol *Reset*.
- Kotak "Kantor Kendari" di jaring komunikasi memakai telepon dan email dari Pengaturan.

## 4. Hierarki sumber data per hari

Urutan lapisan, dari prioritas rendah ke tinggi:

- **H1:** Laporan Awal (`Operasi`, `Korban`, `DetailUnsur`, `DetailAlut`) → RENOPS H1 → suntingan draf.
- **HN (N ≥ 2):** Laporan Awal → LAPBANG H(N-1) → RENOPS HN → suntingan draf.
  - Bila LAPBANG H(N-1) tidak ada, pakai laporan terdekat sebelumnya: LAPBANG terakhir sebelum hari N, lalu RENOPS H(N-1), lalu Laporan Awal.
- Penentuan "hari ke" memakai logika yang sudah ada: `TemplateHelper::hitungHariKe`, `HierarchyHelper::extractDayNumber`, dan pola pemilihan di `HierarchyHelper::getNextRenopsDefaults`. Logika tersebut dipakai ulang, tidak ditulis ulang.

Contoh dari pengguna: LAPBANG H1 mencatat 3 unsur/alut, RENOPS H2 mencatat 4. Dokumen H2 memakai 4.

## 5. Pemetaan isi dokumen

Kop dan tata letak mengikuti lampiran:
- A4 landscape. Margin di lampiran: atas, kiri, dan kanan 20 mm, bawah 18 mm.
- Kop berupa tabel 3 kolom: logo Basarnas (logo aplikasi) | judul | logo "SAR Nasional".
- Logo SAR Nasional diambil dari lampiran dan disimpan sebagai aset statis di `assets/images/`.
- Judul kop terdiri dari tiga baris:
  - "RENCANA OPERASI SAR HARI KE-N"
  - `satker_nama` dalam huruf besar
  - "Operasi SAR {jenis_kejadian}: {nama_operasi}"
- Header berjalan: "Rencana Operasi SAR Hari ke-N | {satker_nama}".
- Footer: "Halaman X dari Y".
- Penomoran judul bab bertingkat seperti lampiran: 1. / a. / 1).
- Penomoran tabel dan gambar ("Tabel n", "Gambar n") dibuat otomatis berurutan.

| Bagian lampiran | Sumber | Catatan |
|---|---|---|
| **1.a Awareness Stage**, paragraf pembuka | Laporan Awal: `waktu_lapor`, `nama_pelapor`, `hubungan_pelapor`, `no_telp_pelapor`, `waktu_kejadian`, `jenis_kejadian`, `lokasi_kejadian`, `koordinat`, `jarak`, unit SAR terdekat | Kalimat dirangkai dari template, bisa disunting. |
| Kronologi | `Operasi.kronologi` | Dipecah per paragraf. |
| Jenis Operasi | `jenis_kejadian` | |
| **Tabel 1 Data Kejadian** | jenis, koordinat, waktu kejadian, *Penyebab*, objek dicari (`jumlah_pob` / jumlah korban), *Upaya yang telah dilakukan*, jumlah korban dalam pencarian (status korban), catatan berisi identitas tiap korban (nama, JK, umur, alamat) | *Penyebab* tidak ada di data. Default diturunkan dari status korban (mis. "Hilang"), bisa disunting. *Upaya*: H1 dari teks default koordinasi; HN dari LAPBANG H(N-1). |
| **Gambar Peta Lokasi** | `DokumenPetaSnapshot` RENOPS hari N, dengan cadangan snapshot LAPORAN_AWAL | Bila tidak ada, tampil kotak kosong dengan tombol "Capture Peta 16:9" di editor. |
| **1.b Initial Action**: Upaya Sebelumnya | H1: teks default. HN: ringkasan `isi_laporan` LAPBANG H(N-1) | Bisa disunting. |
| Koordinasi | Daftar butir default: "Menyampaikan laporan musibah kepada BCC", "Berkoordinasi dengan:" + daftar instansi dari unsur terlibat | Daftar bisa disunting (tambah/hapus butir). |
| Persiapan: Personel / Sarana / Peralatan | Personel: teks baku. Sarana & Peralatan: `detail_alut` hasil hierarki | *Keputusan builder-check:* `MasterAlut`/`DetailAlut` tidak punya kategori. Default dibagi dengan kata kunci nama: kendaraan atau wahana (car, truck, mobil, motor, kapal, RIB, perahu, boat, heli, pesawat) masuk **Sarana**, sisanya **Peralatan**. Di draf, butir bisa dipindah antar daftar. Tidak ada perubahan master data. |
| **2. Mission** | Template "Melaksanakan Operasi SAR pada {jenis_kejadian}, yaitu {ringkasan musibah}" | Bisa disunting. |
| **3. Implementation**: Waktu | RENOPS: `hari_ke`, `waktu_mulai_ops` | "…dilaksanakan pada tanggal …, pukul … WITA sampai dengan selesai." |
| Area Pencarian | Dirangkai **hanya** dari teks SRU RENOPS hari N (`nama_sru`, `koordinat_area`, `luas_area`, `pola_pencarian`, `track_spacing`) | *Keputusan builder-check:* `RenopsSRU` tidak menyimpan id poligon, dan `GisPolygon` berlaku per operasi (bisa berubah belakangan). Karena itu tidak ada pencocokan ke poligon dan tidak ada perubahan skema atau form. Kalimat bisa disunting di draf, misalnya menjadi "radius ±500 m". |
| **Tabel Alokasi Aset** | Butir **Sarana** dari `detail_alut` hasil hierarki, dikelompokkan Darat/Laut/Udara dengan kata kunci nama, selain itu satu tabel | Kolom **Aset \| Jumlah** saja (K5). |
| **Tabel Penugasan SRU** | `RenopsSRU` hari N: No, Unsur (`nama_sru`), Tugas, Area, Pola Pencarian, Track Spacing | *Tugas* default "Pencarian, pertolongan, dan evakuasi". Baris bisa ditambah dan disunting. |
| Rencana Penyelamatan | Teks default lampiran | Bisa disunting. |
| Briefing & Debriefing | `waktu_briefing` + teks default | |
| **4. Operation Stage** | H1: `waktu_berangkat`, `tim_berangkat`, jumlah personel (total `jumlah_personil` unsur). HN: hasil hari sebelumnya (LAPBANG H(N-1)) + rencana hari N | Diikuti grid foto dokumentasi (bagian 8). |
| **5. Administrasi & Logistik** | Daftar butir baku seperti lampiran | Bisa disunting. |
| **6.a Jaring Komunikasi** | Diagram seperti lampiran (K4), dibuat ulang sebagai HTML/SVG agar tajam saat dicetak | Kotak Kantor Kendari: `AppSettings.no_telp`, `email`, `satker_nama`. Kotak lain statis seperti lampiran. |
| **6.b Struktur Organisasi** | Isian per hari (bagian 7) | Digambar sebagai bagan seperti lampiran. |
| **Blok pengesahan** | "{kota}, {tanggal terbit}", "Koordinator Misi Pencarian dan Pertolongan (SMC)", **QR**, nama SMC, pangkat | QR menggantikan tanda tangan dan stempel (K7). Draf menampilkan kotak "QR terbit setelah diterbitkan". |

## 6. Penyimpanan & versi (Pendekatan A)

### Pendekatan yang dipertimbangkan

| | Pendekatan | Trade-off | Hasil |
|---|---|---|---|
| A | Tabel versi dokumen tersendiri | Riwayat versi rapi, verifikasi cukup query biasa; perlu 1 migrasi (tabel baru) | **Dipilih** pengguna. Revisi + validasi QR adalah inti kebutuhan. |
| B | Semua di `Renops.doc_metadata` (JSON) + kolom versi di `DokumenToken` | Tanpa tabel dokumen baru, tetapi blob membesar per revisi, pencarian token harus membongkar JSON, dan PK `DokumenToken` tetap harus diubah | Ditolak. |
| C | Simpan hasil akhir sebagai file HTML/PDF | Beku "sempurna", tetapi revisi tetap butuh data terstruktur dan tidak ada pembuat PDF di server | Dibuang. |

### Skema (migrasi baru di `database/migrations/`)

**`RenopsDokumen`**: satu baris per versi.
- `id_dokumen` PK.
- `id_renops` FK → `Renops` `ON DELETE CASCADE`.
- `id_operasi` untuk query.
- `versi` int: 0 = draf, 1..n = versi terbit.
- `status`: `DRAF` | `TERBIT` | `DIGANTIKAN`.
- `konten_json` longtext:
  - Untuk DRAF: hanya suntingan, isian struktur organisasi, dan urutan/keterangan foto.
  - Untuk TERBIT: isi lengkap hasil resolusi yang beku (bagian 6.3).
- `content_hash` char(64): SHA-256 dari `konten_json` beku.
- `token` char(16): unik; null untuk draf.
- `issued_at`, `issued_by` (id user), `updated_at`.
- Indeks: `(id_renops, status)`, unik `(token)`.
- Hanya ada satu baris `DRAF` per `id_renops` dan satu baris `TERBIT` per `id_renops`.

**`RenopsFoto`**: foto dokumentasi per hari.
- `id_foto` PK.
- `id_renops` FK `ON DELETE CASCADE`.
- `path`, `keterangan`, `urutan`, `uploaded_by`, `created_at`.

### Resolusi, draf, dan pembekuan
- Satu fungsi resolver, misalnya `RenopsDokumenService::resolve(id_renops)`, menghasilkan satu struktur data dokumen lengkap. Urutannya: lapisan hierarki (bagian 4), lalu suntingan draf ditimpakan di atasnya.
- **Satu renderer** menggambar dokumen dari struktur data itu. Draf memakai hasil resolusi live; versi terbit memakai `konten_json` beku. Dengan begitu tampilan draf dan cetak tidak pernah berbeda.
- Struktur draf menyimpan suntingan **per bagian** (kunci per bagian). Bagian tanpa suntingan = "Otomatis". Bagian dengan suntingan = "Disunting", dengan tombol Reset untuk menghapus kunci itu.
- **Terbitkan:**
  1. Resolve penuh.
  2. Salin gambar peta dan semua foto yang dipakai ke folder milik versi itu, karena capture ulang peta menghapus file lama (`DokumenPetaSnapshotModel::saveSnapshot` → `removeImageFile`).
  3. Simpan `konten_json` beku beserta hash.
  4. Buat token baru.
  5. Ubah versi TERBIT sebelumnya menjadi DIGANTIKAN.

  Semua langkah di atas berjalan dalam satu transaksi.
- Draf tetap ada setelah terbit, sehingga revisi berikutnya berangkat dari suntingan terakhir.
- Hapus RENOPS: baris versi dan foto ikut terhapus (cascade), file fisik dibersihkan, dan token mati. QR mana pun → "tidak valid". Pembersihan ditambahkan ke `RenopsModel::delete` seperti `DokumenPetaSnapshotModel::deleteByReferensi`.

## 7. Struktur organisasi (K3)

- Jabatan yang diisi: SC, SMC, OSC, Staf Operasi, Staf Admin, Staf Logistik, Staf Intelijen, Staf Humas, Staf Komunikasi.
- SRU: diambil otomatis dari daftar SRU RENOPS hari N (nama + keterangan), bisa disunting.
- Default nilai:
  - SMC: pejabat `is_default_smc` (`PejabatModel::getDefaultSmc`).
  - Jabatan lain: disalin dari dokumen RENOPS hari sebelumnya dalam operasi yang sama.
  - Untuk H1: disalin dari dokumen RENOPS terakhir mana pun di sistem (misalnya nama SC Kepala Basarnas), selain itu kosong.
- Isian disimpan di draf (bagian 6) dan ikut dibekukan saat terbit.

## 8. Foto dokumentasi (K6)

- Tombol "Upload Foto" di editor, dengan pilih banyak berkas sekaligus.
- Foto muncul sebagai grid rapi di bagian Operation Stage, dengan keterangan "Gambar n. {keterangan}".
- Keterangan bisa disunting, urutan bisa diubah, dan foto bisa dikeluarkan dari draf.
- Validasi di server:
  - Hanya JPEG/PNG/WebP, diperiksa dengan `finfo` (bukan ekstensi).
  - Maksimal 5 MB per berkas (diubah pengguna 2026-11-07), maksimal 12 foto per hari.
  - Di-encode ulang dengan GD ke JPEG, sisi terpanjang maksimal 1600 px. Ini menghapus metadata EXIF/GPS dan payload tersembunyi.
  - Nama berkas acak (`bin2hex(random_bytes(16))`) di `uploads/renops_foto/`.
  - Butuh login + CSRF seperti API lain.
- Foto yang dipakai oleh versi terbit tidak boleh terhapus secara fisik (lihat penyalinan saat terbit).

## 9. Editor draf (Opsi A)

- Halaman baru khusus login, dibuka dari tab Cetak dan tab RENOPS.
- Toolbar atas berisi:
  - Kembali ke detail.
  - Label "RENOPS H.N • tanggal".
  - Lencana status: DRAF / TERBIT vN / "ada perubahan belum diterbitkan".
  - Tombol: Simpan Draf, Upload Foto, Capture Peta, Terbitkan QR, Cetak / PDF.
- Panel kiri berisi daftar isi bagian. Klik untuk lompat ke bagian itu.
- Kertas A4 landscape ditampilkan identik dengan hasil cetak.
  - Paragraf bisa disunting di tempat dengan teks polos (`contenteditable` plaintext; tidak menyimpan HTML).
  - Tabel bisa disunting per sel, termasuk tambah/hapus baris.
  - Daftar butir bisa ditambah/dihapus.
- Setiap bagian punya lencana *Otomatis* / *Disunting*, dan tombol *Reset* untuk bagian yang disunting.
- Mockup referensi: `.superpowers/brainstorm/30065-1793928409/content/editor-layout.html`, opsi A.
- Penyimpanan draf memakai optimistic lock (`updated_at`). Bila draf sudah diubah user lain, simpan ditolak dengan pesan "muat ulang".
- **Cetak / PDF** selalu mencetak **versi TERBIT terakhir**. Bila belum pernah terbit, tombol cetak nonaktif dengan petunjuk "Terbitkan dulu". Pratinjau draf diberi watermark "DRAF".
- Operasi berstatus CLOSED (K16): **data RENOPS tetap terkunci** (aturan yang sudah ada di `RenopsApiController` dan `renopsForm`). Namun **draf dokumen, upload foto, penerbitan, dan revisi tetap diizinkan**, karena ketiganya tidak mengubah data operasional. Dengan begitu RENOPS lama di operasi yang sudah ditutup tetap bisa diterbitkan dan dicetak dalam format baru.
  - Editor menampilkan peringatan untuk operasi CLOSED: Tabel 1 berisi status korban **terakhir**, sehingga perlu diperiksa dan dikoreksi sebelum terbit (lihat Kendala).
  - Tidak ada backfill versi TERBIT otomatis saat migrasi. Setiap versi terbit lahir dari tindakan user.

## 10. QR, verifikasi & akses publik (K7, K11, K12)

- QR berisi URL verifikasi bertoken versi itu, misalnya `operasi.php?action=verify&type=renops&id={ops}&ref={id_renops}&token={token}`.
- Token dibuat dengan HMAC seperti `DocVerifyHelper::generateToken`, ditambah `versi` dan `content_hash` di payload, lalu disimpan di `RenopsDokumen.token`.

Hasil pindai QR:

| Kondisi token | Tampilan |
|---|---|
| Token = versi berstatus TERBIT | **SAH**. Ringkasan (judul, nomor, hari ke-N, versi, tanggal terbit, SMC) + tombol **Buka dokumen**. |
| Token = versi berstatus DIGANTIKAN | **Dokumen telah diubah**. Info versi lama vs terbaru + tautan **"Klik di sini untuk membuka dokumen"** ke versi TERBIT terbaru. |
| Token tidak ditemukan (RENOPS dihapus / token palsu) | **Dokumen tidak valid** (view `bra/verify_invalid` yang sudah ada). |

- **Buka dokumen tanpa login (K12):** route publik read-only yang merender `konten_json` beku dari versi milik token itu, atau dari versi terbaru bila datang dari tautan "telah diubah".
  - Aksesnya hanya lewat token yang sah.
  - Diberi `noindex`.
  - Tidak bisa membuka draf.
  - Tidak bisa membuka jenis dokumen lain.
- **Peringatan yang sudah disampaikan ke pengguna:** ini membuka data pribadi korban dan pelapor (nama, alamat, telepon) kepada siapa pun yang memegang QR. Aturan "print wajib login" di `OperasiController` tetap berlaku untuk halaman editor dan cetak internal. Pengecualian publik hanya untuk route bertoken ini.
- **Kompatibilitas QR lama:** RENOPS portrait yang sudah tercetak memakai token di `DokumenToken`. Bila token tidak ditemukan di `RenopsDokumen`, jalur verifikasi lama (`DocVerifyHelper::verifyToken`) tetap dipakai sebagai cadangan, sehingga QR yang beredar tidak mendadak tidak valid. *Keputusan builder-check (turunan K11):*
  - Token lama sah **dan** RENOPS itu sudah punya versi TERBIT format baru: tampilkan **"Dokumen telah diubah"** dengan tautan ke versi TERBIT terbaru.
  - Token lama sah tanpa versi TERBIT: tampilkan SAH seperti sekarang.
  - Token lama tidak sah, atau RENOPS dihapus: tampilkan tidak valid.
  - `test_doc_verify_and_barcode.php` disesuaikan.

## 11. Integrasi dengan yang sudah ada

- **Tab Cetak, kartu RENOPS** (`views/operasi/partials/tab_cetak.php`): tombol per hari "Buka Draf / Edit" dan "Cetak" (versi terbit). Lencana status per hari.
- **`print_renops`**: diganti agar merender versi TERBIT terakhir dengan format baru. Bila belum ada versi terbit, diarahkan ke editor. File `views/operasi/renops_print.php` portrait lama digantikan.
- Test yang mengacu pada `renops_print.php` / `print_renops` perlu disesuaikan:
  - `test_audit_modul_5_verify_qr_pdf.php`
  - `test_audit_optimalisasi_menyeluruh.php`
  - `test_buttons_and_navigation_e2e.php`
  - `test_document_map_snapshot.php`
  - `test_doc_verify_and_barcode.php`
  - `test_renops_lapbang_phase2_3.php`
  - `test_renops_distance_unit_selection.php`
- Tombol "Salin WA" RENOPS tidak dipengaruhi (naskah WA tetap dari `Renops.full_text_wa`).
- Exporter `html2pdf` ("PDF Gambar") harus menghormati orientasi landscape.

- Logo "SAR Nasional" untuk kop kanan diambil dari lampiran (`/Users/irfandydk/Downloads/Rencana_Operasi_SAR_Hari_ke-1_KMM_Desa_Manuru.docx`, `word/media/bd5730a7…jpg`). File itu disalin ke `assets/images/logo_sar_nasional.jpg`. Lampiran tidak ada di repo.

## 12. Diserahkan ke builder

- Nama route/aksi, nama service/kelas, dan struktur kunci JSON per bagian.
- Teknik header berjalan dan "Halaman X dari Y". Usulan: `@page` margin boxes `counter(page)`/`counter(pages)` (didukung Chromium 131+). Bila exporter `html2pdf` tidak mendukung, nomor halaman dihitung di JS exporter.
- Teknik menggambar diagram jaring komunikasi dan bagan struktur organisasi (HTML/CSS grid atau SVG inline), asal tajam saat dicetak dan mirip lampiran.
- Aturan pengelompokan aset per jenis (Darat/Laut/Udara) dari `nama_alut`, atau satu tabel bila tidak dapat ditentukan.
- Kalimat template default untuk tiap paragraf (rujukan: teks lampiran).
- Cara menyusun rangkuman "Upaya sebelumnya" dari `isi_laporan` LAPBANG.
- Test: ikuti pola `tests/*.php` + `tests/run_all.php`. Minimal mencakup:
  - Resolusi hierarki H1/HN, termasuk contoh 3 vs 4 unsur.
  - Pembekuan (ubah data setelah terbit → cetak tetap sama).
  - Tiga status verifikasi.
  - Validasi upload foto.
  - Cascade hapus.

## 13. Kendala

- PHP + MySQL tanpa framework. Pola Model statis + `api/controllers/*ApiController` + view PHP + Tailwind yang sudah ada.
- Ekstensi GD tersedia. Tidak ada pembuat PDF di server.
- Status korban hanya tersimpan sebagai kondisi terakhir (`Korban.status_korban`, ditimpa oleh LAPBANG). Karena itu kebenaran historis hanya terjamin lewat pembekuan saat terbit.
- Folder `uploads/` dilayani statis lewat router (`Router::serveStaticFile`). Nama berkas acak wajib.
