# Desain Fitur Ekspor & Impor KML/KMZ Dua Arah (Google Earth ↔ QR-SAR)

**Tanggal:** 2026-11-14  
**Pendekatan Terpilih:** Pendekatan A — Modul Ekspor & Parser KML/KMZ di Klien (dengan Pratinjau Interaktif) + Endpoint Transaksi Atomik Batch di Server (`POST /api/gis/import`)  
**Konteks Kode:**
- Frontend GIS: [views/gis.php](file:///Users/irfandydk/antigravity/QR-SAR/views/gis.php), [assets/js/gis/export.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis/export.js), [assets/js/gis/kml_importer.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis/kml_importer.js) *(baru)*, [assets/js/gis_master.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis_master.js)
- Backend GIS: [api/controllers/GisApiController.php](file:///Users/irfandydk/antigravity/QR-SAR/api/controllers/GisApiController.php), [models/GisModel.php](file:///Users/irfandydk/antigravity/QR-SAR/models/GisModel.php)

---

## 1. Tujuan & Masalah Saat Ini

Pengguna membutuhkan pertukaran data spasial **dua arah yang andal, ringan, dan konsisten** antara **Google Earth Pro / Google Earth Web (`.kml` / `.kmz`)** dan **QR-SAR Tactical GIS** untuk empat entitas utama:
1. **Titik (`Point`)**
2. **Poligon / Search Area (`Polygon`, termasuk bentuk Lingkaran `circle` dan Kotak `rectangle`)**
3. **Pola Pencarian SAR (`SearchPattern`)**
4. **Jalur / Rute Navigasi (`Route`)**

Serta dukungan tambahan untuk elemen KML umum Google Earth (`<Folder>` bertingkat, `<Style>` / `<StyleMap>`, `<MultiGeometry>`, `<gx:Track>` / `<gx:MultiTrack>`, dan `<ExtendedData>`) dengan kebebasan memilih cakupan **Operasi SAR Aktif tertentu** ataupun **Base Map**.

### Kesenjangan pada Sistem Saat Ini
- [assets/js/gis/export.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis/export.js) & [assets/js/gis_master.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis_master.js):
  - KML belum menyertakan `<Style>` warna/tebal/opasitas KML (`aabbggrr`), `<tessellate>1</tessellate>`, maupun `<ExtendedData>` QR-SAR.
  - Search Area bertipe `circle` hilang saat `poly.points` kosong atau tidak terkonversi menjadi poligon cincin.
  - Pola E-SAR (`sector_search` / `e_sar`) berbentuk segmen terpisah disatukan menjadi satu `<LineString>`, sehingga Google Earth menggambar garis lompat palsu antar kaki lintasan.
  - Ekspor selalu memakai seluruh array `saved*` pada mode peta aktif tanpa pilihan cakupan eksplisit (**Operasi Aktif** vs **Base Map**) atau filter item tersembunyi (`hidden*Ids`).
- **Impor KML/KMZ:** Belum ada antarmuka, parser, pemetaan pratinjau, maupun endpoint batch impor.

### Tujuan & Batasan Non-Tujuan
- **Tujuan:**
  - *Lossless round-trip* QR-SAR → KML/KMZ → QR-SAR (warna, kategori, tipe ikon preset maupun aset ikon lokal terverifikasi `assets/img/*` & `uploads/icons/*`, bentuk lingkaran/kotak/poligon selama belum diubah bentuknya di Google Earth, tipe pola SAR, spacing, kecepatan kapal, dan keterkaitan `polygonId` tetap utuh).
  - Kompatibilitas kuat Google Earth → QR-SAR (membaca `<Point>`, `<Polygon>`, `<LineString>`, `<gx:Track>`, `<MultiGeometry>`, folder bertingkat, gaya warna `aabbggrr`, penghormatan terhadap suntingan koordinat Google Earth pada poligon, serta pembersihan deskripsi HTML/CDATA menjadi teks aman).
  - Pemilihan target eksplisit baik saat **Ekspor** maupun **Impor**: **Operasi SAR Aktif** (`OPEN`) atau **Base Map** (dengan penegakan otoritas Admin).
  - Operasi penyimpanan batch bersifat **atomik** (satu transaksi database: semua berhasil atau dibatalkan bersih).
- **Non-Tujuan & Batasan (Anti-Goals):**
  - Tidak mendukung *network fetch* eksternal dari `<NetworkLink>` (akan dilewati dengan pesan peringatan yang jelas demi keamanan SSRF dan keandalan offline).
  - Tidak mengimpor raster overlay (`<GroundOverlay>` / model 3D `<Model>`) maupun menyisipkan gambar PNG internal KMZ/URL eksternal sebagai DataURL `customIconUrl` ke DB; ikon eksternal KMZ dipetakan ke preset/kategori QR-SAR agar database tetap ringan dan bebas SSRF/XSS.
  - Tidak mengubah skema tabel database `GisPoint` untuk menambah kolom warna pin khusus (warna titik QR-SAR mengikuti `PRESET_ICONS`, sehingga `<Point>` eksternal tanpa `qrsar:pointType` dipetakan ke preset yang sesuai atau `pin_umum` dengan opsi ubah preset di Pratinjau) *(Keputusan Desain — menjaga kesederhanaan & konsistensi lintas halaman peta)*.
  - Tidak mengubah kontrak publik `QrSarGis.Export.generateKmlDocument` / `generateGeoJson` secara *breaking* (harus tetap lolos di Node.js oleh [test_phase5_gis_modular.php](file:///Users/irfandydk/antigravity/QR-SAR/tests/test_phase5_gis_modular.php)).

---

## 2. Pilihan Pendekatan & Alasan

| Aspek | Pendekatan A *(Dipilih)* | Pendekatan B *(Server-Side Parse/Export)* | Pendekatan C *(Loop Endpoint Satuan)* |
|---|---|---|---|
| **Ekstraksi KMZ / Parse XML** | Klien (`JSZip` + `DOMParser` / fallback XML pure-JS) | PHP (`ZipArchive` + `DOMDocument`) | Klien |
| **Pratinjau & Klasifikasi Garis** | Modal interaktif sebelum simpan (instan, zero round-trip) | Perlu 2x upload/response | Bisa di klien |
| **Simpan ke Database** | 1x `POST /api/gis/import` (1 transaksi atomik + relasi `polygonId`) | 1x transaksi setelah konfirmasi | N HTTP request (tidak atomik, rawan parsial) |
| **Beban Server & Keamanan** | Server hanya menerima JSON terstruktur tervalidasi | Server memproses file ZIP/XML biner (risiko XXE / I/O temp) | Banyak request & audit |

**Keputusan:** **Pendekatan A** dipilih karena ringan di server, memanfaatkan vendor `JSZip` yang sudah terpasang di [views/gis.php](file:///Users/irfandydk/antigravity/QR-SAR/views/gis.php), menjaga kelanjutan modul [export.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis/export.js), memberi kontrol pratinjau/klasifikasi sebelum data menyentuh database, dan memastikan integritas transaksi di backend.

---

## 3. Arsitektur & Spesifikasi Komponen

### A. Peningkatan Ekspor KML / KMZ / GeoJSON ([assets/js/gis/export.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis/export.js))

1. **Kompatibilitas Geometri Lengkap:**
   - **Titik (`points`):** Validasi koordinat numerik (`lat` ∈ [-90, 90], `lng` ∈ [-180, 180]); resolusi warna titik dari `PRESET_ICONS` ([markers.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis/markers.js)) atau parameter; menghasilkan `<Style>` dengan `<IconStyle>` (`<color>` ABGR, `<scale>1.1</scale>`, `<Icon><href>https://maps.google.com/mapfiles/kml/paddle/wht-blank.png</href></Icon>`). Catatan: karena ikon preset Google Earth yang paling konsisten terwarna adalah pin putih standar (`wht-blank.png`), tag `<color>` akan mewarnai pin sesuai kategori/ikon QR-SAR di Google Earth. Jika `pointType === 'custom_upload'` dan `customIconUrl` menunjuk ke aset lokal tepercaya (`assets/img/...` atau `uploads/icons/...` setelah menormalkan prefix origin/`BASE_URL`), simpan path relatif tersebut ke `qrsar:customIconPath` di `<ExtendedData>` *(Keputusan Desain)*.
   - **Poligon (`polygons`):**
     - Jika `shapeType === 'circle'` dan `poly.points` kosong atau < 3 titik: bangkitkan **64 titik cincin geodesik** dari `centerLat`, `centerLng`, dan `radiusNm` (memanfaatkan `QrSarGis.Geodesy` atau rumus proyeksi bulat standar yang sama) agar lingkaran selalu tampil di Google Earth (dan GeoJSON; terapkan pelengkap serupa di `GisModel::exportGeoJson`).
     - Pastikan cincin `<LinearRing>` tertutup (`first !== last` → tambahkan titik pertama di akhir; hindari duplikasi jika sudah tertutup), sertakan `<tessellate>1</tessellate>` dan `<altitudeMode>clampToGround</altitudeMode>`.
     - Terapkan `<Style>` dengan `<LineStyle>` (`strokeColor`, `strokeOpacity`, `strokeWeight`) dan `<PolyStyle>` (`fillColor`, `fillOpacity`, `<outline>1</outline>`, `<fill>1</fill>` jika `fillOpacity > 0`).
   - **Pola Pencarian SAR (`patterns`):**
     - Untuk `patternType === 'sector_search'` atau `'e_sar'`, bila tersedia fungsi pembentuk segmen `getPatternPolylineCoords` (atau dipasok sebagai segmen), ekspor sebagai `<MultiGeometry>` berisi masing-masing `<LineString>` segmen agar Google Earth tidak menarik garis lompat palsu antar kaki lintasan; sekaligus simpan array `waypoints` asli yang ringkas di `<ExtendedData>` (`qrsar:waypointsJson`) agar re-impor memulihkan struktur E-SAR QR-SAR secara presisi.
     - Pola lain (`parallel_track`, `expanding_square`, `creeping_line`, dll.) diekspor sebagai `<LineString>` dengan `<tessellate>1</tessellate>` dan `<LineStyle>` berwarna sesuai `pat.color`, `pat.opacity`, `pat.strokeWeight`.
   - **Jalur / Rute (`routes`):**
     - Diekspor sebagai `<LineString>` dengan `<tessellate>1</tessellate>`, `<LineStyle>` sesuai `strokeColor`, `strokeOpacity`, `strokeWeight`, serta `<description>` terformat berisi jarak total (NM/KM) dan estimasi waktu tempuh pada kecepatan `vesselSpeedKnots`.

2. **KML Color Helper & `<ExtendedData>`:**
   - Fungsi murni `cssColorToKmlAbgr(color, opacity)`: mengonversi hex `#rgb` / `#rrggbb` + `opacity` (0..1) menjadi string 8-digit hex KML **`aabbggrr`** (Alpha-Blue-Green-Red).
   - Folder KML rapi dalam `<Document>`:
     1. `Titik Operasi SAR`
     2. `Area Sektor Poligon`
     3. `Pola Pencarian SAR`
     4. `Rute dan Jalur Navigasi`
   - Deduplikasi `<Style id="...">` pada level `<Document>` agar ukuran file tetap ramping (`<styleUrl>#style-...</styleUrl>` dipakai ulang oleh fitur dengan gaya sama).
   - Tag `<ExtendedData><Data name="qrsar:..."><value>...</value></Data></ExtendedData>` menyimpan atribut inti QR-SAR (`sourceId`, `sarType`, `category`, `pointType`, `customIconPath` khusus aset lokal tepercaya, `alphabetChar`, `shapeType`, `centerLat`, `centerLng`, `radiusNm`, `boxWidthNm`, `boxHeightNm`, `areaNm2`, `patternType`, `trackSpacingNm`, `legsCount`, `totalDistanceNm`, `flipH`, `flipV`, `polygonRef`, `vesselSpeedKnots`, `waypointsJson` khusus E-SAR, dan atribut warna/opasitas).

3. **Pemilih Cakupan Ekspor (Operasi Aktif vs Base Map):**
   - Menu **Ekspor KMZ/KML** pada [views/gis.php](file:///Users/irfandydk/antigravity/QR-SAR/views/gis.php) membuka modal/panel ringkas **Ekspor Data Spasial**:
     - **Sumber Data:**
       1. `Tampilan Peta Saat Ini` (mengikuti filter aktif di layar saat ini)
       2. `Operasi SAR Aktif tertentu` (dropdown daftar `operasiList` berstatus `OPEN` + opsi centang `Sertakan Base Map`, mengambil seluruh data operasi tersebut dari `rawAll*`)
       3. `Base Map (Master Data Global)` (`!item.id_operasi`)
     - **Opsi Layer Tersembunyi:** Checkbox `Sertakan item yang disembunyikan (eye-off)` (aktif otomatis bila memilih sumber Operasi/Base Map eksplisit; nonaktif default bila memilih `Tampilan Peta Saat Ini`).
     - **Format Unduhan:** `.KMZ` (terkompresi `DEFLATE` via `JSZip` dengan entry `doc.kml` dan MIME `application/vnd.google-earth.kmz`), `.KML`, atau `.geojson`.
     - **Penamaan Berkas:** Mencerminkan cakupan, mis. `SAR_KENDARI_OPS_12_2026-11-14.kmz` atau `SAR_KENDARI_BASEMAP_2026-11-14.kmz` atau `SAR_KENDARI_PLOTTING_2026-11-14.kmz`.
   - Menghapus kode duplikat fallback KML/GeoJSON di [gis_master.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis_master.js) sehingga seluruh alur ekspor terpusat pada `window.QrSarGis.Export` (dengan `triggerDownload` memakai `setTimeout(..., 1500)` sebelum `URL.revokeObjectURL` agar aman di Safari/Chrome).

---

### B. Parser KML / KMZ Modular ([assets/js/gis/kml_importer.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis/kml_importer.js))

1. **Lingkungan Eksekusi Ganda (Browser & Node.js Test):**
   - Saat dijalankan di browser, menggunakan native `DOMParser` (`parseFromString(xml, 'text/xml')`, memeriksa `<parsererror>`).
   - Saat dijalankan di lingkungan tanpa DOM (mis. `node --test` CLI), menyediakan parser XML ringan pure-JS internal (atau opsi injeksi XML parser) yang membangun struktur tag/atribut/teks termasuk `<![CDATA[...]]>` sehingga seluruh logika ekstraksi gaya, `<ExtendedData>`, koordinat, dan klasifikasi dapat diuji penuh tanpa paket `npm` eksternal.
   - Mendukung input `.kml` (string/File) maupun `.kmz` (`ArrayBuffer`/File via `JSZip`: memprioritaskan `doc.kml`, fallback ke berkas `.kml` pertama di root/subdirektori arsip).

2. **Batas Keamanan & Kestabilan (Guardrail):**
   - Batas ukuran file unggahan disarankan max **10 MB** (atau hasil dekompresi `doc.kml` max **15 MB**) serta batas **500 fitur (`Placemark` hasil ekstrak)** per proses impor agar browser maupun transaksi PHP tidak kehabisan memori/timeout.
   - Bila file berisi `<NetworkLink>` eksternal tanpa geometri lokal, parser mencatat peringatan informatif di pratinjau bahwa tautan jaringan eksternal tidak diambil otomatis.
   - Nilai `<description>` yang memuat HTML/CDATA disanitasi menjadi teks polos yang aman (menghapus tag HTML dan mengurai entitas dasar) sebelum disimpan ke `GisPoint.description`.

3. **Resolusi Gaya & Ekstraksi Geometri Google Earth:**
   - Mengindeks seluruh `<Style id="...">` dan `<StyleMap id="...">` (`key = normal` → target `#styleUrl`) maupun `<Style>` inline pada `<Placemark>`.
   - Mengonversi KML `aabbggrr` → `#rrggbb` + `opacity` (0..1); jika `<PolyStyle><fill>0</fill>`, `fillOpacity` diset `0`.
   - Mengurai `<coordinates>` yang mendukung pemisah spasi, tab, baris baru, dan format `lng,lat` atau `lng,lat,alt`:
     - Memvalidasi angka finite, `lat ∈ [-90, 90]`, `lng ∈ [-180, 180]`. Titik invalid atau poligon < 3 titik / garis < 2 titik dilewati dan dicatat dalam daftar `warnings` pratinjau.
   - Menangani `<Polygon>` (mengambil `outerBoundaryIs/LinearRing/coordinates`, menghapus titik penutup duplikat bila identik dengan titik pertama, lalu menghitung `areaNm2`, `centerLat`, `centerLng` otomatis lewat `QrSarGis.Geodesy`).
   - **Resolusi `circle` / `rectangle` vs Suntingan Geometri Google Earth *(Keputusan Desain)*:** Jika poligon memuat `qrsar:shapeType` (`circle` atau `rectangle`), parser membandingkan geometri `<LinearRing>` hasil parse terhadap metadata `<ExtendedData>` (`centerLat`/`centerLng` selisih ≤ `0.0005°` ≈ 55 m dan luas/`bounding span` selisih ≤ `5%`). Bila konsisten, pertahankan penuh `shapeType` dan dimensi aslinya (serta tetap menyimpan titik cincin `points` — minimal titik hasil parse atau bangkitkan 36 titik lingkaran agar kompatibel dengan `focusPolygon`, `editPolygon`, dan `exportGeoJson`); namun **bila koordinat `<LinearRing>` telah digeser/diubah di Google Earth**, **geometri `<LinearRing>` yang menang** — gunakan titik hasil suntingan, hitung ulang `centerLat`/`centerLng`/`areaNm2`, dan turunkan `shapeType` aman menjadi `'polygon'` disertai peringatan informatif di pratinjau.
   - Menangani `<LineString>`, `<gx:Track>` / `<gx:MultiTrack>` (`<gx:coord>lng lat [alt]</gx:coord>` yang dipisahkan spasi).
   - Menangani `<MultiGeometry>`:
     - Jika merupakan ekspor `qrsar:sarType=pattern` dan membawa `qrsar:waypointsJson` pada `<ExtendedData>`, memulihkan array `waypoints` penuh (termasuk pasangan E-SAR).
     - Jika merupakan `MultiGeometry` umum Google Earth (mis. gabungan beberapa poligon/garis/titik dalam 1 Placemark), memecah tiap child geometri menjadi item individual yang diberi akhiran indeks bila > 1 (`Nama (1)`, `Nama (2)`) sehingga seluruh bagian terimpor lengkap tanpa ada yang hilang.

4. **Klasifikasi Otomatis & Re-klasifikasi Manual (`Titik`, `Poligon`, `Pola SAR`, `Jalur`):**
   - **Ada `<ExtendedData>` `qrsar:sarType`:** dipetakan langsung ke tipe dan atribut aslinya (`point`, `polygon`, `pattern`, `route`), termasuk membawa `qrsar:polygonRef` sementara dan `qrsar:customIconPath` (jika memenuhi pola path lokal aman `assets/img/...` atau `uploads/icons/...`; server akan memverifikasi keberadaan berkas di disk).
   - **File asli Google Earth (tanpa `qrsar:sarType`):**
     - Geometri `Point` → **`point`** (mengambil `category` dari nama `<Folder>` induk bila bermakna atau default `'Impor KML'`; menebak `pointType` aman ke preset bila kata kunci sangat jelas seperti `LKP`, `POS SAR`, `Kantor SAR`, atau fallback `'pin_umum'`; warna pin eksternal dapat disesuaikan oleh operator melalui pemilih preset ikon pada pratinjau tanpa mengubah skema `GisPoint`).
     - Geometri `Polygon` → **`polygon`** (`shapeType: 'polygon'`, mengambil warna garis/isi dari style Google Earth).
     - Geometri `LineString` / `gx:Track` → otomatis diklasifikasikan ke **`pattern`** jika nama/folder/deskripsi memuat kata kunci pola SAR (`pola`, `pattern`, `parallel`, `creeping`, `expanding`, `square`, `sector`, `e-sar`, `lintasan`), atau default ke **`route`** (`Jalur`) jika tidak. Pada UI Pratinjau, setiap item garis memiliki selector **`Jalur` / `Pola SAR`** (plus tombol ubah massal) sehingga operator bebas menentukan klasifikasi final sebelum simpan.

---

### C. UI Impor & Pratinjau Interaktif ([views/gis.php](file:///Users/irfandydk/antigravity/QR-SAR/views/gis.php) & [assets/js/gis_master.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis_master.js))

1. **Pemaparan Hak Admin ke Konfigurasi GIS:**
   - Menambahkan `isAdmin: <?= jsonSafe(AuthHelper::isAdmin()) ?>` ke `window.SAR_INITIAL_GIS_DATA` di [views/gis.php](file:///Users/irfandydk/antigravity/QR-SAR/views/gis.php).
2. **Tombol & Modal Impor KML/KMZ:**
   - Menambahkan tombol **`Impor KML/KMZ`** pada header bar GIS di sebelah tombol `Ekspor KMZ/KML`.
   - Isi Modal Impor:
     - **Langkah 1 — Pilih File:** Input/drag-drop `.kml` / `.kmz`.
     - **Langkah 2 — Target Tujuan Penyimpanan:**
       - Radio/Select: **Operasi SAR Aktif** (dropdown seluruh operasi `OPEN`; otomatis terpilih jika halaman sedang membuka salah satu Operasi Aktif) atau **Base Map (Master Global)**. Jika `!isAdmin`, opsi Base Map dinonaktifkan (`disabled`) disertai catatan *"Khusus Administrator"*.
       - Jika tidak ada Operasi Aktif dan pengguna bukan Admin, tombol Simpan dinonaktifkan dengan penjelasan jelas.
     - **Langkah 3 — Pratinjau & Kurasi Komponen:**
       - Kartu ringkasan jumlah komponen (`Titik`, `Poligon`, `Pola SAR`, `Jalur`, `Peringatan/Dilewati`).
       - Daftar item dengan checkbox aktif/nonaktif per item (plus *Pilih Semua / Kosongkan*), pratinjau warna swatch, nama yang bisa disunting cepat bila perlu, dropdown **`Jalur` / `Pola SAR`** untuk garis, serta dropdown **`Tipe Ikon Preset`** untuk titik.
       - Checkbox: `Zoom peta otomatis ke hasil impor setelah disimpan` (default aktif).
3. **Sinkronisasi State Setelah Simpan:**
   - Setelah `POST /api/gis/import` mengembalikan `imported` (`points`, `polygons`, `patterns`, `routes`), `gis_master.js` menambahkan record tersebut ke `rawAll*`, memanggil `applyActiveFilter()` (atau jika target operasi lain yang sedang tidak dibuka, menawarkan/mengalihkan atau memberi notifikasi berhasil + jumlah tersimpan), merender ulang seluruh layer/list/badge, dan melakukan `map.fitBounds(...)` bila item terlihat pada filter aktif.

---

### D. Backend Batch Endpoint & Model Transaksional ([api/controllers/GisApiController.php](file:///Users/irfandydk/antigravity/QR-SAR/api/controllers/GisApiController.php), [models/GisModel.php](file:///Users/irfandydk/antigravity/QR-SAR/models/GisModel.php))

1. **Endpoint `POST /api/gis/import`:**
   - Dilindungi middleware auth + CSRF yang sudah aktif di [api/index.php](file:///Users/irfandydk/antigravity/QR-SAR/api/index.php).
   - Validasi payload & RBAC target `id_operasi`:
     - `id_operasi === null` → wajib `AuthHelper::isAdmin()` (HTTP 403 bila bukan Admin).
     - `id_operasi > 0` → operasi harus ada di DB (HTTP 404 bila tidak ada) dan tidak berstatus `'CLOSED'` (HTTP 403 bila sudah ditutup).
   - Validasi batas aman per batch: total item (`points + polygons + patterns + routes`) maks **500 item** (HTTP 400 jika melebihi atau jika kosong seluruhnya), koordinat tervalidasi `is_finite` serta rentang `[-90, 90]` / `[-180, 180]`, poligon min 3 titik (termasuk poligon bertipe `circle` wajib memiliki titik `GisPolygonPoint`), jalur & pola min 2 titik.
2. **Transaksi Atomik Tunggal di `GisModel::importBatch(?int $idOperasi, array $payload): array`:**
   - **Refaktor transaksi di `GisModel`:** Ekstrak pola transaksi aman (`$ownTx = !$db->inTransaction();`) dengan menjaga `beginTransaction()`, `commit()`, dan `rollBack()` hanya dijalankan bila `$ownTx === true` pada `savePolygon`, `saveRoute`, dan `saveSearchPattern`, sehingga fungsi CRUD satuan tetap bekerja persis seperti sebelumnya sementara `importBatch()` membungkus seluruh batch dalam **1 transaksi database tunggal** (tidak pernah commit/rollback prematur di tengah batch).
   - **ID Baru Unik:** Seluruh entitas hasil impor dibuatkan ID server unik baru (`pt-imp-...`, `poly-imp-...`, `pattern-imp-...`, `route-imp-...`) agar impor ulang file Google Earth tidak menimpa/merusak item operasi lain atau Base Map yang sudah ada.
   - **Validasi Path Ikon Lokal `custom_upload` *(Keputusan Desain)*:** Bila titik menyertakan `pointType === 'custom_upload'` dan `customIconUrl`, server memverifikasi path memenuhi pola ketat `^(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+$` tanpa `..` serta `is_file(ROOT_DIR . '/' . $relPath)` benar-benar ada. Jika gagal/eksternal/berkas tidak ditemukan, server menurunkannya aman ke preset fallback (`kantor` / `pos_sar` / `pin_umum`) dan mengosongkan `customIconUrl = null`.
   - **Resolusi 3-Tahap Referensi Poligon `polygonRef` *(Keputusan Desain)*:** Saat menyimpan `pattern` atau `route` yang membawa `polygonRef`:
     1. Jika `polygonRef === 'datum_kansar'`, pertahankan `'datum_kansar'`.
     2. Jika `polygonRef` cocok dengan `clientRefId` poligon yang ikut disimpan pada batch yang sama, petakan `polygonId` & `polygonName` ke ID poligon baru (`poly-imp-...`) hasil transaksi tersebut.
     3. Jika tidak ada dalam batch (mis. poligon sudah ada di target dan tidak dicentang ulang saat pratinjau), periksa `GisPolygon` di DB dalam cakupan target yang sah (`id = :ref AND (id_operasi <=> :targetOpId OR (:targetOpId IS NOT NULL AND id_operasi IS NULL))`). Jika ditemukan, pertahankan tautan ID tersebut; jika tidak ditemukan (mis. impor lintas-operasi tanpa poligonnya), kosongkan `polygonId` (`''` pada `GisSearchPattern`, `null` pada `GisRoute`) dan `polygonName` agar bebas referensi yatim/lintas-operasi.
   - **Kembalian Data Lengkap:** Mengembalikan entitas yang sudah tersimpan beserta ID, `customIconUrl` tervalidasi, dan `areaNm2` hasil kalkulasi `GisModel` sehingga state JS klien 100% sinkron dengan database tanpa perlu reload halaman.

---

## 4. Rencana Verifikasi & Pengujian

1. **Unit Test JS (`tests/js/kml_roundtrip.test.js` via `node --test`):**
   - Ekspor KML dari `QrSarGis.Export.generateKmlDocument`: verifikasi `<Style>`, konversi warna KML `aabbggrr`, `<tessellate>1</tessellate>`, lingkaran `shapeType === 'circle'` menghasilkan poligon 64 titik, pola E-SAR menghasilkan `<MultiGeometry>`, `qrsar:customIconPath` untuk ikon lokal, `<ExtendedData>` lengkap, serta sanitasi XML khusus `& < > " '`.
   - Impor KML oleh `QrSarGis.KmlImporter.parseKmlString`: uji KML ekspor QR-SAR (*round-trip lossless* untuk Point preset & `custom_upload` lokal, Polygon biasa, Circle, E-SAR Pattern, Parallel Track Pattern, Route + `polygonRef`), suntingan geometri Google Earth pada exported Circle/Rectangle otomatis turun aman menjadi `polygon`, serta KML asli Google Earth (`StyleMap`, folder bertingkat, `gx:Track`, `MultiGeometry`, poligon dengan titik penutup berulang, deskripsi HTML, koordinat cacat yang ditolak dengan warning, batas maksimal item).
   - Validasi kompatibilitas mundur dengan [test_phase5_gis_modular.php](file:///Users/irfandydk/antigravity/QR-SAR/tests/test_phase5_gis_modular.php).
2. **Integration Test PHP (`tests/test_gis_kml_import_export.php`):**
   - Pengujian `GisModel::importBatch()` untuk target **Operasi Aktif** dan **Base Map (`null`)**, penyimpanan titik `GisPolygonPoint` untuk lingkaran impor, resolusi 3-tahap `polygonRef` (batch remap, `datum_kansar`, existing scoped match, dan pembersihan lintas-operasi), validasi allowlist `custom_upload` (lokal sah dipertahankan, path tidak sah/hilang diturunkan aman ke preset), dan **rollback atomik penuh** saat satu item gagal validasi di tengah batch.
   - Pengujian otorisasi API `GisApiController` (`POST /api/gis/import`):
     - Non-Admin ditolak (HTTP 403) saat target `id_operasi = null` (Base Map).
     - Admin diizinkan saat target Base Map.
     - Operasi `CLOSED` ditolak (HTTP 403), operasi tidak ditemukan ditolak (HTTP 404).
     - Operasi `OPEN` berhasil untuk user terautentikasi dan data bersih setelah cleanup `try/finally`.
   - Pemeriksaan UI [views/gis.php](file:///Users/irfandydk/antigravity/QR-SAR/views/gis.php) memuat `kml_importer.js`, `isAdmin`, serta modal Ekspor & Impor KML/KMZ, serta `node -c` seluruh file JS terkait dan `php tests/run_all.php`.

---

## 5. Yang Diserahkan ke Implementer / Builder
- Penataan visual tata letak modal Ekspor & Impor pada [views/gis.php](file:///Users/irfandydk/antigravity/QR-SAR/views/gis.php) mengikuti komponen UI/CSS cockpit-glass dan dark/light theme yang sudah ada pada modal Capture Peta / Import RENOPS.
- Detil implementasi parser XML pure-JS fallback untuk eksekusi Node.js pada `kml_importer.js` selama lolos seluruh skenario Google Earth & XML entity/CDATA.
