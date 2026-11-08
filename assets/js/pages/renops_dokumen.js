/* Editor draf Dokumen RENOPS: edit langsung di kertas. Teks polos saja (plaintext-only, innerText); CSRF lewat wrapper fetch di app.js. */
(function () {
  'use strict';
  const app = document.getElementById('renopsDokumenApp');
  if (!app) return;
  const ID = parseInt(app.dataset.idRenops, 10);
  let rev = parseInt(app.dataset.rev, 10) || 0;
  const API = `${window.BASE_URL || ''}/api/renops/${ID}`;
  const dirty = new Set();
  const COLS = {
    data_kejadian: ['label', 'value'],
    aset: ['grup', 'aset', 'jumlah'],
    sru: ['unsur', 'tugas', 'area', 'pola', 'track_spacing'],
  };
  const lastFocus = {};
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const secs = () => $$('[data-sec]', app);
  const val = (el) => (el.innerText || '').replace(/\r\n?/g, '\n').trim();

  function msg(text, bad) {
    const m = $('#renopsMsg');
    m.textContent = text;
    m.className = 'no-print rounded-xl border px-4 py-2 text-xs font-semibold ' +
      (bad ? 'border-rose-300 bg-rose-50 text-rose-800' : 'border-emerald-300 bg-emerald-50 text-emerald-800');
  }

  async function api(method, path, body) {
    const opt = { method, headers: { Accept: 'application/json' } };
    if (body instanceof FormData) opt.body = body;
    else if (body !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
    const res = await fetch(API + path, opt);
    let data = {};
    try { data = await res.json(); } catch (e) { /* tubuh bukan JSON */ }
    if (!res.ok) { const err = new Error(data.message || `Permintaan gagal (${res.status})`); err.status = res.status; err.data = data; throw err; }
    return data;
  }
  const fail = (e) => { window.alert(e.message); };

  // ---- markup editable ----
  function editables(sec) {
    const k = sec.dataset.kind;
    if (k === 'text') return $$('.sec-body', sec);
    if (k === 'list') return $$('li', sec);
    // nama kotak SRU di struktur ditimpa server dari tabel SRU (R9): hanya keterangan yang boleh disunting
    return $$('[data-col]:not([hidden])', sec).filter((el) => !(k === 'struktur' && el.dataset.col === 'nama' && el.closest('[data-row]')));
  }
  function arm(el) { el.setAttribute('contenteditable', 'plaintext-only'); }
  function ctlButton(text, color, fn) {
    const b = document.createElement('button');
    b.type = 'button'; b.textContent = text;
    b.style.cssText = `color:${color};margin-right:12px`;
    b.addEventListener('click', fn);
    return b;
  }
  function setup(sec) {
    editables(sec).forEach(arm);
    const k = sec.dataset.kind;
    if (k === 'list' || k === 'table') {
      const bar = document.createElement('div');
      bar.className = 'no-print sec-ctl';
      bar.style.cssText = 'font-size:11px;font-weight:700;margin:2px 0';
      const unit = k === 'list' ? 'butir' : 'baris';
      bar.append(
        ctlButton(`+ tambah ${unit}`, '#2563eb', () => (k === 'list' ? addItem(sec) : addRow(sec))),
        ctlButton(`hapus ${unit} terpilih`, '#dc2626', () => (k === 'list' ? delItem(sec) : delRow(sec)))
      );
      sec.append(bar);
    }
    if ($('.sec-badge.is-edited', sec)) {
      const r = document.createElement('button');
      r.type = 'button'; r.textContent = 'Reset';
      r.className = 'no-print sec-reset';
      r.style.cssText = 'position:absolute;top:14px;right:-3mm;transform:translateX(100%);font-size:7pt;font-weight:bold;color:#dc2626';
      r.addEventListener('click', () => reset(sec.dataset.sec));
      sec.append(r);
    }
  }

  const mark = (sec) => { dirty.add(sec.dataset.sec); };
  app.addEventListener('input', (ev) => { const s = ev.target.closest && ev.target.closest('[data-sec]'); if (s) mark(s); });
  // Hanya sel/butir yang bisa disunting: tombol "hapus … terpilih" berada di dalam bagian yang sama, dan fokus ke
  // tombol itu tidak boleh menimpa baris/butir terpilih (dulu membuat hapus tidak berfungsi).
  app.addEventListener('focusin', (ev) => {
    const s = ev.target.closest && ev.target.closest('[data-sec]');
    if (s && ev.target.isContentEditable) lastFocus[s.dataset.sec] = ev.target;
  });

  // ---- daftar ----
  function addItem(sec) {
    const li = document.createElement('li');
    arm(li);
    $('ol', sec).append(li);
    const empty = $('.sec-empty', sec);
    if (empty) empty.remove();
    li.focus();
    mark(sec);
  }
  function delItem(sec) {
    const f = lastFocus[sec.dataset.sec];
    const li = f && f.closest('li');
    if (!li || !sec.contains(li)) return;
    li.remove();
    mark(sec);
  }

  // ---- tabel ----
  function renumber(sec) {
    if (sec.dataset.sec !== 'sru') return;
    $$('tr[data-row]', sec).forEach((tr, i) => { const c = tr.firstElementChild; if (c && !c.dataset.col) c.textContent = String(i + 1); });
  }
  function addRow(sec) {
    const key = sec.dataset.sec;
    const f = lastFocus[key];
    const bodies = $$('tbody', sec);
    const tb = (f && f.closest('tbody')) || bodies[bodies.length - 1];
    if (!tb) return;
    const src = f && f.closest('tr[data-row]');
    const baris = $$('tr[data-row]', tb);
    const ref = src || baris[baris.length - 1] || $('tr[data-row]', sec);
    const grup = ref ? ((ref.querySelector('[data-col=grup]') || {}).textContent || '') : '';
    const tr = document.createElement('tr');
    tr.setAttribute('data-row', '');
    if (key === 'sru') { const no = document.createElement('td'); no.className = 'c'; tr.append(no); }
    COLS[key].forEach((c) => {
      const td = document.createElement('td');
      td.dataset.col = c;
      if (c === 'grup') { td.hidden = true; td.textContent = grup; } else arm(td);
      tr.append(td);
    });
    const empty = $('.row-empty', tb);
    if (empty) empty.remove();
    tb.append(tr);
    renumber(sec);
    const first = $('[contenteditable]', tr);
    if (first) first.focus();
    mark(sec);
  }
  function delRow(sec) {
    const f = lastFocus[sec.dataset.sec];
    const tr = f && f.closest('tr[data-row]');
    if (!tr || !sec.contains(tr)) return;
    tr.remove();
    renumber(sec);
    mark(sec);
  }

  // ---- kumpul nilai ----
  function collect(sec) {
    const k = sec.dataset.kind, key = sec.dataset.sec;
    if (k === 'text') return val($('.sec-body', sec));
    if (k === 'list') return $$('li', sec).map(val).filter((x) => x !== '');
    if (k === 'table') {
      return $$('tr[data-row]', sec).map((tr) => {
        const o = {};
        COLS[key].forEach((c) => { const td = $(`[data-col="${c}"]`, tr); o[c] = td ? val(td) : ''; });
        return o;
      }).filter((o) => Object.entries(o).some(([k, v]) => k !== 'grup' && v !== ''));
    }
    const o = {};
    $$('[data-col]', sec).forEach((el) => { if (!el.closest('[data-row]')) o[el.dataset.col] = val(el); });
    o.sru = $$('[data-row]', sec).map((b) => ({
      nama: val($('[data-col=nama]', b) || b), keterangan: val($('[data-col=keterangan]', b) || b),
    }));
    return o;
  }

  async function save() {
    if (!dirty.size) return;
    const sections = {};
    secs().forEach((s) => { if (dirty.has(s.dataset.sec)) sections[s.dataset.sec] = collect(s); });
    const res = await api('PUT', '/dokumen', { sections, rev });
    if (!Number.isInteger(res.rev)) throw new Error('Respons server tidak valid; suntingan belum tentu tersimpan. Muat ulang halaman.');
    rev = res.rev;
    dirty.clear();
  }

  async function reset(key) {
    if (dirty.size && !window.confirm('Ada suntingan yang belum disimpan dan akan hilang. Lanjutkan reset?')) return;
    try {
      const res = await api('PUT', '/dokumen', { sections: { [key]: null }, rev });
      if (!Number.isInteger(res.rev)) throw new Error('Respons server tidak valid. Muat ulang halaman.');
      rev = res.rev; dirty.clear();
      window.location.reload();
    } catch (e) { fail(e); }
  }

  $('#btnSimpanDraf').addEventListener('click', async (ev) => {
    const b = ev.currentTarget; b.disabled = true;
    try { await save(); window.location.reload(); } catch (e) { fail(e); b.disabled = false; }
  });

  // Cetak draf: suntingan yang belum disimpan disimpan dulu agar ikut tercetak.
  const btnCetakDraf = $('#btnCetakDraf');
  if (btnCetakDraf) {
    btnCetakDraf.addEventListener('click', async (ev) => {
      if (!dirty.size) return;
      ev.preventDefault();
      const w = window.open('', '_blank'); // dibuka saat klik agar tidak diblokir popup
      try {
        await save();
        if (w) w.location.href = btnCetakDraf.href; else window.location.href = btnCetakDraf.href;
      } catch (e) { if (w) w.close(); fail(e); }
    });
  }

  $('#btnTerbitkan').addEventListener('click', async (ev) => {
    const b = ev.currentTarget;
    if (!window.confirm("Terbitkan versi baru? QR versi sebelumnya akan berstatus 'telah diubah'.")) return;
    b.disabled = true;
    try {
      await save();
      const res = await api('POST', '/dokumen/terbit', { rev });
      if (res.baru === false) {
        msg(`Tidak ada perubahan sejak versi terakhir — QR tetap v${res.versi}.`);
        b.disabled = false;
        return;
      }
      window.location.reload();
    } catch (e) { fail(e); b.disabled = false; }
  });

  // ---- foto ----
  const fileInput = $('#inputFoto');
  $('#btnUploadFoto').addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', async () => {
    if (!fileInput.files.length) return;
    // Batas 5 MB per foto (RenopsFotoModel::MAX_BYTES): tolak di peramban agar tidak terkirim sia-sia.
    const besar = Array.from(fileInput.files).filter((f) => f.size > 5 * 1024 * 1024);
    if (besar.length) {
      window.alert('Ukuran foto maksimal 5 MB. Terlalu besar: ' + besar.map((f) => f.name).join(', '));
      fileInput.value = '';
      return;
    }
    const fd = new FormData();
    Array.from(fileInput.files).forEach((f) => { fd.append('foto[]', f); fd.append('keterangan[]', ''); });
    const b = $('#btnUploadFoto'); b.disabled = true;
    fileInput.value = '';
    try { await api('POST', '/foto', fd); window.location.reload(); } catch (e) {
      fail(e);
      // 422 + daftar foto = sebagian berkas sudah tersimpan: muat ulang agar tampil
      if (e.status === 422 && e.data && e.data.foto) window.location.reload();
      else b.disabled = false;
    }
  });
  // urutan foto: tukar dengan tetangga, lalu nomori ulang 1..n (hanya foto yang berubah dikirim; tahan celah/duplikat)
  async function geser(idFoto, arah) {
    const { foto } = await api('GET', '/dokumen');
    const i = foto.findIndex((f) => String(f.id_foto) === idFoto);
    const j = i + arah;
    if (i < 0 || j < 0 || j >= foto.length) return;
    [foto[i], foto[j]] = [foto[j], foto[i]];
    for (const [n, f] of foto.entries()) {
      if (Number(f.urutan) !== n + 1) await api('PUT', `/foto/${f.id_foto}`, { urutan: n + 1 });
    }
    window.location.reload();
  }
  // Kontrol di foto (kertas): keterangan disunting langsung, tombol urutan, ikon tempat sampah untuk hapus.
  $$('figure.foto[data-id-foto]', app).forEach((fig) => {
    const idFoto = fig.dataset.idFoto;
    const ket = $('.foto-ket', fig);
    if (ket) {
      arm(ket);
      let saved = ket.dataset.fotoKet || '';
      const tampil = val(ket);
      ket.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); ket.blur(); } });
      ket.addEventListener('blur', async () => {
        const v = val(ket);
        if (v === saved || (saved === '' && v === tampil)) return;
        try {
          await api('PUT', `/foto/${idFoto}`, { keterangan: v });
          saved = v;
          msg('Keterangan foto disimpan.');
        } catch (e) { fail(e); }
      });
    }
    $$('[data-foto-geser]', fig).forEach((btn) => btn.addEventListener('click', async () => {
      btn.disabled = true;
      try { await geser(idFoto, parseInt(btn.dataset.fotoGeser, 10)); } catch (e) { fail(e); btn.disabled = false; }
    }));
    const hapus = $('[data-foto-hapus]', fig);
    if (hapus) hapus.addEventListener('click', async () => {
      if (!window.confirm('Hapus foto ini?')) return;
      try { await api('DELETE', `/foto/${idFoto}`); window.location.reload(); } catch (e) { fail(e); }
    });
  });

  // ---- daftar isi ----
  $$('#renopsToc [data-goto]').forEach((a) => a.addEventListener('click', (ev) => {
    ev.preventDefault();
    const t = $(`[data-sec="${a.dataset.goto}"]`, app);
    if (t) t.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }));

  secs().forEach(setup);

  // ---- muat pas lebar: layar saja (@media print & exporter PDF memakai zoom 1); 72px kanan untuk lencana/Reset ----
  const paper = $('#renopsPaper');
  const sheets = paper ? $$('.page-sheet.renops-smeac', paper) : [];
  if (sheets.length) {
    // Lebar asli tiap lembar (portrait 210mm, landscape 297mm), diukur sebelum zoom.
    const widths = sheets.map((sh) => sh.offsetWidth);
    const fit = () => sheets.forEach((sh, i) => { sh.style.zoom = String(Math.min(1, (paper.clientWidth - 72) / widths[i])); });
    fit();
    window.addEventListener('resize', fit);
  }
  window.addEventListener('beforeunload', (ev) => { if (dirty.size) { ev.preventDefault(); ev.returnValue = ''; } });
})();
