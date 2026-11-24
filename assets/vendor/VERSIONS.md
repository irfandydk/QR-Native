# Offline Vendor Libraries Inventory (`assets/vendor/`)

Daftar pustaka pihak ketiga yang dibundel secara lokal (offline-first) pada aplikasi QR-SAR.
Karena pustaka di bawah tidak dikelola via `package.json`, lakukan peninjauan rilis keamanan secara berkala setiap 3 bulan.

| Pustaka | Versi Terpasang | Berkas Utama | Ukuran | Lisensi | Catatan |
|---|---|---|---|---|---|
| **Leaflet** | `1.9.4` | `leaflet/leaflet.min.js`, `leaflet/leaflet.min.css` | 144 KB / 14 KB | BSD-2-Clause | Mesin peta interaktif GIS |
| **SweetAlert2** | `11.26.25` | `sweetalert2/sweetalert2.all.min.js`, `sweetalert2/sweetalert2.min.css` | 77 KB / 29 KB | MIT | Dialog konfirmasi & notifikasi toast |
| **JSZip** | `3.10.1` | `jszip/jszip.min.js` | 95 KB | MIT / GPLv3 | Ekspor & impor arsip KMZ (GIS) |
| **SortableJS** | `1.15.6` | `sortablejs/Sortable.min.js` | 44 KB | MIT | Drag-and-drop urutan item |
| **qrcode (node-qrcode)** | `1.5.1` | `qrcode/qrcode.min.js` | 23 KB | MIT | Generator canvas QR Code dokumen resmi |
| **html2pdf.js (bundle)** | Tidak tercantum di header (`bundle.min.js`) | `html2pdf/html2pdf.bundle.min.js` | 885 KB | MIT | Bundel `html2canvas` + `jsPDF`; dimuat lazy di form BRA & `defer` di lembar cetak |

## Prosedur Pembaruan

1. Unduh rilis resmi terbaru dari repositori / CDN terpercaya.
2. Ganti berkas di folder `assets/vendor/<nama>/`.
3. Perbarui tabel versi dan tanggal audit di berkas ini.
4. Jalankan `DB_DATABASE=qrsar_test php tests/run_all.php` untuk memastikan seluruh suite PHP & JS lulus.

**Terakhir diverifikasi:** 25 November 2026
