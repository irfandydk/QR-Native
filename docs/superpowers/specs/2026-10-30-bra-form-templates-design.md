# Optimasi Isi Form BRA SAR — Desain Singkat

## Tujuan
Draf BRA terisi cepat dari data operasi yang dipilih, dan hasilnya selalu sesuai format telegram baku. Petugas cukup memeriksa dan mengirim, tanpa membetulkan tanda baca, format waktu, atau jabatan.

## Bukan tujuan
- Tidak ada perubahan database.
- Tidak menambah jenis alut di Master Alut. Ini cara B, bisa dikerjakan nanti bila kata kunci sering meleset.
- Tidak mengubah tampilan hasil cetak BRA (`views/bra/view.php`).

## Gagal bila
- Referensi diambil dari operasi lain, bukan dari `id_operasi` yang dipilih.
- Tombol referensi bisa diklik padahal datanya belum ada.
- Ada `/`, `:`, koma, atau titik di tengah kalimat naratif.

## Aturan format (berlaku untuk semua kategori)
- **Kalimat naratif:** huruf kapital, tanpa tanda baca di tengah kalimat. Koma menjadi `KMA`, titik menjadi `TTK`, titik dua menjadi `TTK DUA`, dan `/` dihilangkan.
- **Pengecualian:** keterangan pelapor di dalam kurung, misalnya `(KELUARGA KORBAN/ HP 0812...)`.
- **Waktu:** `TW MMDD HHMM H` (H = WITA), bukan tanggal panjang.
- **Koordinat:** tetap memakai format DMS aslinya.
- **H di tengah kalimat:** ditulis `HARI KE X`.
- **Daftar boleh memakai tanda baca.**
  - Unsur dan alut memakai daftar bernomor, contoh `1. RESCUER KENDARI : 1 ORANG`.
  - Koordinat area memakai daftar berhuruf `A.`, `B.`.
  - Cuaca memakai daftar bernomor `1. CUACA : ...`, `2. ANGIN : ...`, `3. GELOMBANG : ...`. Baris yang datanya kosong tidak ditampilkan.

## Jabatan pengirim
| BRA | Jabatan |
|---|---|
| Laporan Awal | jabatan asli pejabat yang dipilih |
| BRA lain yang terkait operasi (RENOPS, Lapbang, Penutupan, Pemapelan, Pergerakan Kapal) | `SMC` |
| BRA tanpa kaitan operasi | jabatan asli |

## Alamat
| Kategori | AKSI | INFO |
|---|---|---|
| Operasi SAR | KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN | kosong, placeholder `POLRES/POLSEK KMA KSOP KMA SROP SETEMPAT` |
| Pemapelan | KA KSOP KENDARI KMA KA SROP KENDARI | KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN |
| Pergerakan Kapal | KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN | DEPUTI OPS DAN KESIAPSIAGAAN KMA DEPUTI SARPRAS DAN SISTEM KOMUNIKASI KMA DIREKTUR SARPRAS DAN DIREKTUR KESIAPSIAGAAN |

## Tombol referensi (kategori Operasi SAR, berdasarkan id operasi yang dipilih)
Tombol yang datanya belum ada diberi atribut `disabled` dan keterangan alasannya. Tombol tidak hanya dipudarkan.

| Tombol | Aktif bila | Sumber |
|---|---|---|
| Laporan Awal | ada operasi dipilih | data Operasi |
| RENOPS | ada RENOPS | RENOPS H yang dipilih dari daftar |
| Lapbang | ada laporan non-PENUTUPAN | laporan H yang dipilih dari daftar |
| Lapbang dan Penutupan | ada laporan berjenis PENUTUPAN | laporan PENUTUPAN |
| Pemapelan | ada operasi dipilih | data Operasi |

## Isi per jenis

**Laporan Awal**
- **AAA:** `PD TW <waktu terima info> H TERIMA INFO DARI <pelapor> (<hubungan>/ HP <no>) BAHWA TELAH TERJADI <kejadian> DI <lokasi> PADA PERKIRAAN KOORDINAT <DMS> TTK`
- **BBB:** `TERSEBUT AAA DIATAS KMA <tim berangkat> DIBERANGKATKAN MENUJU LKP MENGGUNAKAN <alat angkut> DENGAN JARAK <radial> DAN ETA <eta> TTK`
- **CCC:** daftar `UNSUR SAR TERLIBAT TTK DUA`, lalu `ALUT SAR TTK DUA`, lalu `KONDISI CUACA TTK DUA`.
- **DDD:** `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI KRM TTK HBS`
- Alat angkut dipilih dari alut operasi lewat kata kunci nama: RESCUE CAR, RIB, RUBBER BOAT, LCR, KN, RB, MOBIL, TRUK, LONGBOAT, KAPAL, HELI. Palsar seperti medis dan komunikasi tidak disebut.

**RENOPS (H dipilih)**
- **AAA:** `PD TW <briefing> H TIM SAR GABUNGAN MELAKSANAKAN BRIEFING RENCANA OPERASI SAR HARI KE X TERHADAP <kejadian> DENGAN WAKTU MULAI PERGERAKAN TW <mulai> H TTK`
- **BBB:** `PEMBAGIAN SRU TTK DUA`, lalu untuk setiap SRU:
  ```
  1. SEARCH AREA SRU 1 TTK DUA
  A. <koordinat>
  B. <koordinat>
  POLA <pola> TS <track spacing> LUAS <luas>
  ENTRY DAN EXIT POINT <entry> <exit>
  ```
  Koordinat dan entry/exit diurai dari teks `koordinat_area` SRU: baris di bawah `Batas Koordinat`, serta `[Titik Entry Poin: ...]` dan `[Titik Exit Poin: ...]` beserta baris `Koordinat:` di bawahnya. Bila penanda tidak ditemukan, baris itu kosong dan diisi petugas.
- **CCC:** sama dengan Laporan Awal (unsur, alut, cuaca).
- **DDD:** `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`

**Lapbang (H dipilih, tanpa data korban)**
- **AAA:** hasil pencarian dari isi laporan.
- **BBB:** daftar alut.
- **CCC:** daftar unsur.
- **DDD:** daftar cuaca, lalu pernyataan pencarian dihentikan sementara.
- **EEE:** `DMKN SBG LAPORAN PERKEMBANGAN HARI KE X KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`

**Lapbang dan Penutupan**
- **AAA:** debriefing dan hasil akhir.
- **BBB:** alut dikembalikan.
- **CCC:** unsur dikembalikan.
- **DDD:** rekap korban: selamat, meninggal dunia, dalam pencarian, total POB.
- **EEE:** pernyataan operasi ditutup.
- **FFF:** penutup SMC.

**Pemapelan**
- **AAA:** dasar permohonan: terima info, kejadian, LKP, dan usaha yang telah dilakukan.
- **BBB:** permintaan pemapelan kepada kapal yang melintas.
- **CCC:** penutup, ucapan terima kasih atas kerja sama.

**Pergerakan Kapal**
- Bagian AAA, BBB, dan seterusnya kosong, hanya berisi placeholder.
- Preset kapal (KN SAR Pacitan 102, RB 210 Bau Bau, RB 307 Kolaka) hanya mengisi kepala naskah, tidak mengisi isi.

## Pendekatan
- **A (dipilih).** Rombak di tempat, di `assets/js/pages/bra_form.js` dan `views/bra/form.php`. Alat angkut dikenali lewat kata kunci, tanpa perubahan database. Dipilih karena paling kecil dan bisa naik ke B tanpa membuang pekerjaan.
- **B.** Seperti A, ditambah kolom jenis alut di Master Alut. Ditolak untuk sekarang karena menyentuh database dan halaman Master hanya demi satu kalimat.
- **C.** Pembuatan naskah dipindah ke server (PHP). Ditolak karena perombakan besar tanpa manfaat yang dibutuhkan sekarang.

## Juga diperbaiki
- Dropdown (Operasi SAR, Pejabat, Kapal) yang isi, label, dan placeholder-nya bertumpuk. Penyebabnya dicek di browser, lalu diperbaiki lewat CSS form.
- Isi bagian (AAA, BBB, dst.) dibuat dengan elemen DOM dan `.value`, tidak lagi lewat `innerHTML`. Ini menutup temuan audit 4.10.

## Diserahkan ke pelaksana
- Detail kalimat "usaha yang telah dilakukan" pada Pemapelan (keputusan saya): diambil dari tim yang diberangkatkan di Laporan Awal.
- Bentuk teks pemilih H, misalnya dropdown kecil di samping tombol.
- Daftar kata kunci alat angkut bisa ditambah bila ada nama alut lain.
