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

  function swalError(title, text) {
    msg(text || title, true);
    if (typeof showErrorAlert === 'function') {
      showErrorAlert(title, text);
    } else if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({ icon: 'error', title: title, text: text, confirmButtonColor: '#ef4444' });
    } else if (typeof showToast === 'function') {
      showToast(text || title, 'error');
    }
  }

  async function swalConfirm(title, text, confirmText, isDanger) {
    if (typeof confirmAction === 'function') {
      return await confirmAction(title, text, confirmText, isDanger);
    }
    if (window.Swal && typeof window.Swal.fire === 'function') {
      const res = await window.Swal.fire({
        title: title,
        text: text,
        icon: isDanger ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: isDanger ? '#ef4444' : '#ea580c',
        confirmButtonText: confirmText || 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
      });
      return !!res.isConfirmed;
    }
    return true;
  }

  const fail = (e) => { swalError('Permintaan Gagal', e.message || 'Terjadi kesalahan pada sistem.'); };

  // ---- markup editable ----
  const supportsPlaintextOnly = (function () {
    try {
      const d = document.createElement('div');
      d.contentEditable = 'plaintext-only';
      return d.contentEditable === 'plaintext-only';
    } catch {
      return false;
    }
  })();

  function editables(sec) {
    const k = sec.dataset.kind;
    if (k === 'text') return $$('.sec-body', sec);
    if (k === 'list') return $$('li', sec);
    // nama kotak SRU di struktur ditimpa server dari tabel SRU (R9): hanya keterangan yang boleh disunting
    return $$('[data-col]:not([hidden])', sec).filter((el) => !(k === 'struktur' && el.dataset.col === 'nama' && el.closest('[data-row]')));
  }
  function arm(el) {
    if (supportsPlaintextOnly) {
      el.setAttribute('contenteditable', 'plaintext-only');
    } else {
      el.setAttribute('contenteditable', 'true');
      el.addEventListener('paste', function (e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
      });
    }
  }
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
    if (dirty.size && !(await swalConfirm('Reset Bagian Dokumen?', 'Ada suntingan yang belum disimpan dan akan hilang. Lanjutkan reset?', 'Ya, Reset', true))) return;
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
    if (!(await swalConfirm('Terbitkan Versi Baru?', "Terbitkan versi baru? QR versi sebelumnya akan berstatus 'telah diubah'.", 'Ya, Terbitkan QR', false))) return;
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

  function uploadFotoWithProgress(fd, onProgress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', `${API}/foto`);
      xhr.setRequestHeader('Accept', 'application/json');
      const token = window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');
      if (token) {
        xhr.setRequestHeader('X-CSRF-Token', token);
      }
      if (xhr.upload && typeof onProgress === 'function') {
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            onProgress(pct, e.loaded, e.total);
          }
        };
      }
      xhr.onload = () => {
        let data = {};
        try { data = JSON.parse(xhr.responseText); } catch (err) {}
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve(data);
        } else {
          const err = new Error(data.message || `Permintaan gagal (${xhr.status})`);
          err.status = xhr.status;
          err.data = data;
          reject(err);
        }
      };
      xhr.onerror = () => {
        const err = new Error('Terjadi kesalahan jaringan saat mengunggah foto.');
        err.status = 0;
        reject(err);
      };
      xhr.ontimeout = () => {
        const err = new Error('Unggahan foto melebihi batas waktu (timeout).');
        err.status = 408;
        reject(err);
      };
      xhr.send(fd);
    });
  }

  function compressFotoForUpload(file) {
    return new Promise((resolve) => {
      if (!file || !file.type || !/^image\/(jpeg|png|webp)$/i.test(file.type)) {
        resolve(file);
        return;
      }
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        URL.revokeObjectURL(url);
        try {
          const MAX_SIDE = 1600;
          let w = img.naturalWidth || img.width;
          let h = img.naturalHeight || img.height;
          if (!w || !h) { resolve(file); return; }
          if (Math.max(w, h) > MAX_SIDE) {
            if (w >= h) { h = Math.max(1, Math.round((h * MAX_SIDE) / w)); w = MAX_SIDE; }
            else { w = Math.max(1, Math.round((w * MAX_SIDE) / h)); h = MAX_SIDE; }
          }
          const canvas = document.createElement('canvas');
          canvas.width = w;
          canvas.height = h;
          const ctx = canvas.getContext('2d');
          if (!ctx) { resolve(file); return; }
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, w, h);
          ctx.drawImage(img, 0, 0, w, h);
          canvas.toBlob((blob) => {
            if (!blob || blob.size >= file.size) { resolve(file); return; }
            const baseName = String(file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
            resolve(new File([blob], baseName, { type: 'image/jpeg', lastModified: Date.now() }));
          }, 'image/jpeg', 0.85);
        } catch (err) {
          resolve(file);
        }
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        resolve(file);
      };
      img.src = url;
    });
  }

  // ---- foto ----
  const fileInput = $('#inputFoto');
  $('#btnUploadFoto').addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', async () => {
    const files = Array.from(fileInput.files || []);
    if (!files.length) return;

    const MAX_PER_RENOPS = 12;
    if (files.length > MAX_PER_RENOPS) {
      const pesan = 'Maksimal 12 foto dapat diunggah sekaligus.';
      swalError('Unggahan Ditolak', pesan);
      fileInput.value = '';
      return;
    }

    const existingCount = $$('figure.foto[data-id-foto]', app).length;
    const sisaSlot = Math.max(0, MAX_PER_RENOPS - existingCount);

    if (files.length > sisaSlot) {
      const pesan = existingCount > 0
        ? `Maksimal ${MAX_PER_RENOPS} foto per RENOPS. Dokumen ini sudah memiliki ${existingCount} foto, Anda hanya dapat menambahkan maksimal ${sisaSlot} foto lagi.`
        : 'Maksimal 12 foto dapat diunggah sekaligus.';
      swalError('Kuota Foto Penuh', pesan);
      fileInput.value = '';
      return;
    }

    // Batas 5.5 MB per foto (f.size > 5 * 1024 * 1024 nominal, Ukuran foto maksimal 5 MB dengan toleransi 5.5 MB)
    const MAX_FOTO_SIZE = 5.5 * 1024 * 1024; // 5,767,168 bytes
    const besar = files.filter((f) => f.size > MAX_FOTO_SIZE || (f.size > 5 * 1024 * 1024 && false));
    if (besar.length) {
      const pesan = besar.map((f) => `Berkas ${f.name} (${(f.size / (1024 * 1024)).toFixed(1)} MB) melebihi batas maksimal 5 MB.`).join('\n');
      swalError('Ukuran Foto Terlalu Besar', pesan);
      fileInput.value = '';
      return;
    }

    const b = $('#btnUploadFoto');
    const origText = b ? b.textContent : '+ Upload Foto Dokumentasi';
    if (b) b.disabled = true;
    fileInput.value = '';

    try {
      const prepText = `Menyiapkan ${files.length} foto...`;
      if (b) b.textContent = prepText;
      msg(prepText, false);

      const preparedFiles = [];
      for (const file of files) {
        preparedFiles.push(await compressFotoForUpload(file));
      }

      let totalBytes = 0;
      const fd = new FormData();
      const token = window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');
      if (token) {
        fd.append('csrf_token', token);
      }
      for (const file of preparedFiles) {
        fd.append('foto[]', file);
        fd.append('keterangan[]', '');
        totalBytes += file.size;
      }
      const totalMB = (totalBytes / (1024 * 1024)).toFixed(1);

      const initText = `Mengunggah ${files.length} foto (${totalMB} MB)...`;
      if (b) b.textContent = initText;
      msg(initText, false);

      await uploadFotoWithProgress(fd, (pct) => {
        const progressText = `Mengunggah ${files.length} foto (${totalMB} MB)... ${pct}%`;
        if (b) b.textContent = progressText;
        msg(progressText, false);
      });

      if (b) b.textContent = 'Memproses foto di server...';
      msg('Memproses foto di server...', false);

      msg(`Berhasil mengunggah ${files.length} foto dokumentasi!`, false);
      if (typeof showToast === 'function') showToast(`Berhasil mengunggah ${files.length} foto dokumentasi!`, 'success');
      window.location.reload();
    } catch (e) {
      fail(e);
      // 422 + daftar foto = sebagian berkas sudah tersimpan: muat ulang agar tampil
      if (e.data && e.data.foto) {
        window.location.reload();
      } else if (b) {
        b.disabled = false;
        b.textContent = origText;
      }
    }
  });
  // Rapikan distribusi maksimal 6 foto per .foto-grid dan perbarui nomor Gambar X. secara langsung di DOM.
  function rebalanceAndRenumberFotos() {
    const grids = $$('.foto-grid', app);
    const baseGambar = grids.length ? (parseInt(grids[0].dataset.baseGambar, 10) || 0) : 0;
    const allFigs = $$('figure.foto[data-id-foto]', app);
    grids.forEach((g, gi) => {
      allFigs.slice(gi * 6, (gi + 1) * 6).forEach((fig) => g.append(fig));
    });
    allFigs.forEach((fig, idx) => {
      const numEl = $('.foto-num', fig);
      if (numEl) numEl.textContent = `Gambar ${baseGambar + idx + 1}. `;
    });
    return allFigs.map((fig) => parseInt(fig.dataset.idFoto, 10)).filter((n) => n > 0);
  }

  // Drag-and-drop urutan foto lintas lembar lampiran via SortableJS (tanpa tombol reorder manual).
  const fotoGrids = $$('.foto-grid', app);
  if (fotoGrids.length && typeof Sortable !== 'undefined') {
    fotoGrids.forEach((grid) => {
      new Sortable(grid, {
        group: 'renops-foto',
        animation: 150,
        draggable: 'figure.foto',
        filter: '.foto-ket, input, button, label',
        preventOnFilter: false,
        ghostClass: 'foto-sortable-ghost',
        onEnd: async () => {
          const ids = rebalanceAndRenumberFotos();
          if (!ids.length) return;
          try {
            await api('PUT', '/foto/reorder', { ids });
            msg('Urutan foto dokumentasi disimpan.', false);
            if (typeof showToast === 'function') showToast('Urutan foto dokumentasi disimpan.', 'success');
          } catch (e) {
            fail(e);
          }
        },
      });
    });
  }

  // Seleksi & Bulk Delete foto dokumentasi
  const cbPilihSemuaFoto = $('#cbPilihSemuaFoto');
  const btnHapusFotoTerpilih = $('#btnHapusFotoTerpilih');
  const countFotoTerpilih = $('#countFotoTerpilih');

  function updateBulkFotoState() {
    const cbs = $$('input[data-foto-pilih]', app);
    const checked = cbs.filter((cb) => cb.checked);
    cbs.forEach((cb) => {
      const fig = cb.closest('figure.foto');
      if (fig) fig.classList.toggle('is-selected', cb.checked);
    });
    if (countFotoTerpilih) countFotoTerpilih.textContent = String(checked.length);
    if (btnHapusFotoTerpilih) {
      btnHapusFotoTerpilih.disabled = checked.length === 0;
      btnHapusFotoTerpilih.textContent = checked.length > 0 ? `Hapus Foto Terpilih (${checked.length})` : 'Hapus Foto Terpilih';
    }
    if (cbPilihSemuaFoto) {
      cbPilihSemuaFoto.checked = cbs.length > 0 && checked.length === cbs.length;
      cbPilihSemuaFoto.indeterminate = checked.length > 0 && checked.length < cbs.length;
    }
  }

  if (cbPilihSemuaFoto) {
    cbPilihSemuaFoto.addEventListener('change', () => {
      $$('input[data-foto-pilih]', app).forEach((cb) => { cb.checked = cbPilihSemuaFoto.checked; });
      updateBulkFotoState();
    });
  }

  if (btnHapusFotoTerpilih) {
    btnHapusFotoTerpilih.addEventListener('click', async () => {
      const ids = $$('input[data-foto-pilih]:checked', app)
        .map((cb) => parseInt(cb.value, 10))
        .filter((n) => n > 0);
      if (!ids.length) return;
      if (!(await swalConfirm('Hapus Foto Terpilih?', `Apakah Anda yakin ingin menghapus ${ids.length} foto dokumentasi yang dipilih? Tindakan ini tidak dapat dibatalkan.`, 'Ya, Hapus Foto', true))) return;
      btnHapusFotoTerpilih.disabled = true;
      try {
        await api('POST', '/foto/bulk-delete', { ids });
        if (typeof showToast === 'function') showToast(`${ids.length} foto dokumentasi berhasil dihapus.`, 'success');
        window.location.reload();
      } catch (e) {
        fail(e);
        updateBulkFotoState();
      }
    });
  }

  // Kontrol di foto (kertas): keterangan disunting langsung, checkbox pilih, ikon tempat sampah untuk hapus.
  $$('figure.foto[data-id-foto]', app).forEach((fig) => {
    const idFoto = fig.dataset.idFoto;
    const cb = $('input[data-foto-pilih]', fig);
    if (cb) {
      cb.addEventListener('change', updateBulkFotoState);
    }
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
    const hapus = $('[data-foto-hapus]', fig);
    if (hapus) hapus.addEventListener('click', async () => {
      if (!(await swalConfirm('Hapus Foto Dokumentasi?', 'Apakah Anda yakin ingin menghapus foto dokumentasi ini?', 'Ya, Hapus Foto', true))) return;
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
