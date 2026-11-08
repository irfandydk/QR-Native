/* QR-SAR - Kategori Alut & Palsar di form (Laporan Awal, RENOPS, LAPBANG).
 * Kode, label, urutan & kata kunci disuntik server (AlutKategori::configJs) sebagai window.ALUT_KATEGORI. */
(function () {
  'use strict';
  const cfg = window.ALUT_KATEGORI || { urutan: [], kata: {} };
  const kodes = cfg.urutan.map((k) => k.kode);
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const PALSAR = 'PALSAR';
  const master = cfg.master || { id: {}, nama: {} };
  // Kunci nama sama dengan AlutKategori::kunciNama (huruf kecil, spasi dirapikan).
  const kunci = (n) => String(n || '').replace(/\s+/g, ' ').trim().toLowerCase();

  function normalize(v) {
    const k = String(v || '').trim().toUpperCase();
    return kodes.includes(k) ? k : '';
  }
  // Nama: master (nama ternormalisasi) dulu, lalu kata utuh (urutan sama dengan server: Laut → Udara → Darat), selain itu PALSAR.
  function tebak(nama) {
    const dariMaster = normalize((master.nama || {})[kunci(nama)]);
    if (dariMaster) return dariMaster;
    const n = String(nama || '');
    for (const [kode, kata] of Object.entries(cfg.kata)) {
      if (new RegExp('(^|[^\\p{L}\\p{N}_])(' + kata.join('|') + ')(?=$|[^\\p{L}\\p{N}_])', 'iu').test(n)) return kode;
    }
    return PALSAR;
  }
  // Resolver: kategori item → master via id_alut → master via nama → kata kunci.
  const kategoriOf = (item) => normalize(item && item.kategori)
    || normalize(item && item.id_alut ? (master.id || {})[item.id_alut] : '') || tebak(item && item.nama_alut);
  function sort(list) {
    return (list || []).map((it, i) => [kodes.indexOf(kategoriOf(it)), i, it])
      .sort((a, b) => a[0] - b[0] || a[1] - b[1]).map((x) => x[2]);
  }
  function options(selected) {
    const sel = normalize(selected) || PALSAR;
    return cfg.urutan.map((k) => `<option value="${esc(k.kode)}"${k.kode === sel ? ' selected' : ''}>${esc(k.label)}</option>`).join('');
  }
  // Nama ketik-bebas: isi tebakan selama pengguna belum memilih kategori sendiri.
  function bindInput(namaId, kategoriId) {
    const nama = document.getElementById(namaId);
    const kat = document.getElementById(kategoriId);
    if (!nama || !kat) return;
    kat.addEventListener('change', () => { kat.dataset.manual = '1'; });
    nama.addEventListener('input', () => { if (kat.dataset.manual !== '1') kat.value = tebak(nama.value); });
  }
  function resetInput(kategoriId) {
    const kat = document.getElementById(kategoriId);
    if (kat) { kat.value = PALSAR; delete kat.dataset.manual; }
  }

  window.AlutKategoriUI = { normalize, tebak, kategoriOf, sort, options, bindInput, resetInput };
})();
