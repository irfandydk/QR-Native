/**
 * QR-SAR - BRA SAR Telegram Templates
 * Format sandi telegram (KMA/TTK/TTK DUA, TW, daftar) dan pembuat draf naskah BRA per jenis.
 * Murni tanpa DOM: dipakai bra_form.js (window.BraTemplates) dan diuji via node (module.exports).
 */
(function (global) {
  'use strict';

  const TRANSPORT_RE = /\b(RESCUE CAR|RIB|RUBBER BOAT|LCR|KN|RB|MOBIL|TRUK|LONGBOAT|KAPAL|HELI(?:KOPTER)?)\b/;
  const LIST_LINE_RE = /^\s*(\d+|[A-Z])\.\s/;
  const BULAN = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
  const HARI = '(?:SENIN|SELASA|RABU|KAMIS|JUMAT|JUM\'AT|SABTU|MINGGU)';
  const LONG_DATE_TIME_RE = new RegExp(`(?:${HARI},?\\s+)?(\\d{1,2})\\s+(${BULAN.join('|')})\\s+\\d{4},?\\s+(?:PUKUL|PKL|JAM)\\s+(\\d{1,2})[.:](\\d{2})(?:\\s*(WITA|WIB|WIT))?`, 'gi');
  const TIME_RE = /\b(PUKUL|PKL|JAM)\s+(\d{1,2})[.:](\d{2})(?:\s*(WITA|WIB|WIT)\b)?/gi;
  // Sandi zona waktu militer: WIB = G, WITA = H (bawaan), WIT = I
  const zone = (z) => ({ WIB: 'G', WIT: 'I' })[String(z || '').toUpperCase()] || 'H';

  function upper(v) {
    return String(v === null || v === undefined ? '' : v).trim().toUpperCase();
  }

  // CIRI-CIRI -> CIRIS (sandi reduplikasi telegram)
  function reduplication(text) {
    let res = text.replace(/\b([a-zA-Z]+)-\1\b/gi, (m, w) => w.toUpperCase() + 'S');
    const special = {
      'TERAPUNG-APUNG': 'TERAPUNG', 'BERPINTAL-PINTAL': 'BERPINTAL', 'TEROMBANG-AMBING': 'TEROMBANG AMBING',
      'SAMBUNG-MENYAMBUNG': 'SAMBUNG MENYAMBUNG', 'BOLAK-BALIK': 'BOLAK BALIK', 'HILANG-KONTAK': 'HILANG KONTAK'
    };
    for (const [k, v] of Object.entries(special)) res = res.replace(new RegExp('\\b' + k + '\\b', 'gi'), v);
    return res;
  }

  /** Kalimat naratif: kapital, tanda baca tengah kalimat jadi sandi; isi ( ), desimal dan slot {key} utuh. */
  function toSentence(text, verbatim) {
    let str = String(text === null || text === undefined ? '' : text).trim();
    if (!str) return '';
    str = str.replace(/(?:TW\s+)?(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::\d{2})?/gi, (m, y, mo, d, h, mi) => `TW ${mo}${d} ${h}${mi} H`);
    const pad = (n) => String(n).padStart(2, '0');
    // '23 OKTOBER 2026 PUKUL 06.30 WITA' -> 'TW 1023 0630 H'; 'PUKUL 09.00 WITA' -> 'PUKUL 0900 H'
    str = str.replace(LONG_DATE_TIME_RE, (m, d, bln, h, mi, z) => `TW ${pad(BULAN.indexOf(bln.toUpperCase()) + 1)}${pad(d)} ${pad(h)}${mi} ${zone(z)}`);
    str = str.replace(TIME_RE, (m, kata, h, mi, z) => `${kata} ${pad(h)}${mi} ${zone(z)}`);
    str = str.replace(/\b(\d{1,2}):(\d{2})\b/g, (m, h, mi) => `${pad(h)}${mi}`);
    str = str.replace(/\bH[.\-+](\d+)\b/gi, 'HARI KE $1');
    str = str.replace(/[*_~]/g, '');
    str = reduplication(str);

    const kept = [];
    const keep = (val) => { kept.push(val); return `\u0001${kept.length - 1}\u0001`; };
    str = str.replace(/\{(\w+)\}/g, (m, key) => keep(upper(verbatim && verbatim[key])));
    str = str.replace(/\([^)]*\)/g, (m) => keep(m.toUpperCase()));
    str = str.replace(/(\d+)[.,](\d+)/g, (m, a, b) => keep(`${a}.${b}`));

    str = str.replace(/:\s*/g, ' TTK DUA ').replace(/,\s*/g, ' KMA ').replace(/\.\s*/g, ' TTK ').replace(/\//g, ' ');
    str = str.replace(/\u0001(\d+)\u0001/g, (m, i) => kept[Number(i)]);
    return str.replace(/\bTW\s+TW\b/gi, 'TW').replace(/\s+/g, ' ').trim().toUpperCase();
  }

  /** Teks bagian: baris daftar/judul daftar cukup kapital, baris lain diformat sebagai kalimat. */
  function formatSectionText(text) {
    return String(text || '').split(/\r?\n/).map((line) => {
      if (!line.trim()) return '';
      if (LIST_LINE_RE.test(line) || /TTK DUA\s*$/i.test(line)) return upper(line);
      return toSentence(line);
    }).join('\n');
  }

  /** 'MMDD HHMM H' dari datetime DB (sudah WITA), Date (dikonversi ke WITA) atau TW jadi; '' bila kosong. */
  function militaryTime(value) {
    if (value instanceof Date) {
      if (isNaN(value.getTime())) return '';
      const wita = new Date(value.getTime() + value.getTimezoneOffset() * 60000 + 8 * 3600000);
      const p = (n) => String(n).padStart(2, '0');
      return `${p(wita.getMonth() + 1)}${p(wita.getDate())} ${p(wita.getHours())}${p(wita.getMinutes())} H`;
    }
    const str = String(value || '').trim();
    if (/^\d{4}\s+\d{4}\s+H$/i.test(str)) return str.toUpperCase();
    const m = str.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{2}):(\d{2}))?/);
    return m ? `${m[2]}${m[3]} ${m[4] || '00'}${m[5] || '00'} H` : '';
  }

  function hariNumber(hariKe) {
    const m = String(hariKe === null || hariKe === undefined ? '' : hariKe).match(/(\d+)/);
    return m ? parseInt(m[1], 10) : 1;
  }

  function list(title, items, marker) {
    const rows = (items || []).map(upper).filter(Boolean);
    if (!rows.length) return '';
    return `${upper(title)} TTK DUA\n` + rows.map((r, i) => `${marker(i)}. ${r}`).join('\n');
  }
  const numberedList = (title, items) => list(title, items, (i) => i + 1);
  const letteredList = (title, items) => list(title, items, (i) => String.fromCharCode(65 + i));

  function unsurItems(rows) {
    return (rows || []).filter((r) => r && upper(r.nama_unsur)).map((r) =>
      upper(r.nama_unsur) + (r.jumlah_personil ? ` : ${r.jumlah_personil} ORANG` : ''));
  }

  // Urutan kategori alut (sama dengan AlutKategori::URUTAN di PHP); tanpa kategori di akhir.
  const KATEGORI_URUT = ['ALUT_DARAT', 'ALUT_LAUT', 'ALUT_UDARA', 'PALSAR'];
  const urutKategori = (r) => {
    const i = KATEGORI_URUT.indexOf(upper(r.kategori));
    return i === -1 ? KATEGORI_URUT.length : i;
  };

  function alutItems(rows) {
    return (rows || []).filter((r) => r && upper(r.nama_alut))
      .map((r, i) => [r, i]).sort((a, b) => urutKategori(a[0]) - urutKategori(b[0]) || a[1] - b[1]).map(([r]) => r).map((r) =>
      upper(r.nama_alut) + (r.jumlah_alut ? ` : ${r.jumlah_alut} ${upper(r.satuan)}`.trimEnd() : ''));
  }

  function cuacaItems(w) {
    w = w || {};
    return [['CUACA', w.cuaca], ['ANGIN', w.angin], ['GELOMBANG', w.gelombang]]
      .filter(([, v]) => upper(v)).map(([k, v]) => `${k} : ${upper(v)}`);
  }

  function isTransportAlut(nama) {
    return TRANSPORT_RE.test(upper(nama));
  }

  /** Kendaraan untuk kalimat "MENGGUNAKAN": kategori ALUT_* kecuali drone; data lama tanpa kategori pakai tebakan nama. */
  function isKendaraanAlut(row) {
    const kat = upper(row && row.kategori);
    if (!kat) return isTransportAlut(row && row.nama_alut);
    return kat.startsWith('ALUT_') && !/\bDRONE\b/.test(upper(row.nama_alut));
  }

  /** Urai teks koordinat_area SRU (hasil Impor GIS form RENOPS) menjadi koordinat batas + entry/exit. */
  function parseSruArea(text) {
    const lines = String(text || '').split(/\r?\n/);
    const coords = [];
    let start = lines.findIndex((l) => /^\s*Batas Koordinat\s*:?\s*$/i.test(l));
    const fromSektor = start === -1;
    if (fromSektor) start = lines.findIndex((l) => /^\s*Sektor\s*:/i.test(l));
    if (start !== -1) {
      for (let i = start + 1; i < lines.length; i++) {
        const l = lines[i].trim();
        if (!l || l.startsWith('[')) break;
        // Label titik bawaan ('A: ', 'P1: ') dibuang karena daftar BRA memberi huruf sendiri
        if (!fromSektor || l.includes('°') || /^Radius\b/i.test(l)) coords.push(l.replace(/^[A-Z0-9]{1,3}(?:\s*\([A-Z]{1,3}\))?\s*:\s*/i, ''));
      }
    }
    const pointCoord = (kind) => {
      const i = lines.findIndex((l) => new RegExp(`^\\s*\\[Titik ${kind} Poin\\s*:`, 'i').test(l));
      if (i === -1) return '';
      const m = (lines[i + 1] || '').match(/^\s*Koordinat\s*:\s*(.+)$/i);
      return m ? m[1].trim() : '';
    };
    return { coords, entry: pointCoord('Entry'), exit: pointCoord('Exit') };
  }

  // --- Draf naskah per jenis BRA (Draft = {perihal, aksi, info, kind, sections:[{kode,isi}]}) ---
  const KABASARNAS = 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN';
  const CODES = ['AAA', 'BBB', 'CCC', 'DDD', 'EEE', 'FFF'];

  function draft(perihal, aksi, info, kind, isiList) {
    return { perihal: toSentence(perihal), aksi, info, kind, sections: isiList.map((isi, i) => ({ kode: CODES[i], isi })) };
  }

  // Isi laporan bebas jadi satu klausa: tanpa baris baru dan tanpa titik penutup (titik ditambah template)
  function clause(text) {
    return String(text || '').replace(/\s*\n+\s*/g, ' ').replace(/[\s.]+$/, '');
  }

  // Nama kejadian dari nama_operasi tanpa awalan 'OPERASI SAR (TERHADAP)' agar tidak dobel di kalimat/perihal
  const kejadianOf = (op) => upper(op.nama_operasi).replace(/^OPERASI SAR\s+(?:TERHADAP\s+)?/, '').replace(/[\s.]+$/, '');
  const rowsOr = (docRows, opRows) => (Array.isArray(docRows) && docRows.length ? docRows : (opRows || []));
  const timOf = (op) => (op.detail_tim_berangkat || []).map((t) => upper(t.nama_tim)).filter(Boolean).join(' DAN ') || upper(op.tim_berangkat) || 'TIM RESCUE';

  // Teks bebas operasi lama ("• RESCUER : 6 ORG") jadi butir daftar tanpa simbol/nomor bawaan
  const textItems = (text) => String(text || '').split(/\r?\n/).map((l) => upper(l.replace(/^[\s•*\-–]+|^\s*\d+[.)]\s*/g, ''))).filter(Boolean);
  const orText = (items, text) => (items.length ? items : textItems(text));
  const unsurOf = (doc, op) => orText(unsurItems(rowsOr(doc.detail_unsur, op.detail_unsur)), doc.unsur_text || op.unsur_terlibat);
  const alutOf = (doc, op) => orText(alutItems(rowsOr(doc.detail_alut, op.detail_alut)), doc.alut_text || op.alut_palsar);

  // Unsur SAR, alut SAR dan cuaca selalu satu bagian dengan judul yang sama dari Laporan Awal sampai Penutupan
  function listsCcc(doc, op, cuaca) {
    return [numberedList('UNSUR SAR TERLIBAT', unsurOf(doc, op)), numberedList('ALUT SAR', alutOf(doc, op)), numberedList('KONDISI CUACA', cuacaItems(cuaca))]
      .filter(Boolean).join('\n\n');
  }

  // Kalimat terima info Laporan Awal (dipakai juga AAA Pemapelan); slot {k} = koordinat verbatim
  function terimaInfoText(op) {
    const tw = militaryTime(op.waktu_lapor);
    const hub = upper(op.hubungan_pelapor);
    const hp = upper(op.no_telp_pelapor);
    const ket = hub && hp ? `(${hub}/ HP ${hp})` : hub ? `(${hub})` : hp ? `(HP ${hp})` : '';
    return (tw ? `PD TW ${tw} ` : '') + `TERIMA INFO DARI ${upper(op.nama_pelapor) || 'MASYARAKAT'} ${ket} BAHWA TELAH TERJADI ${kejadianOf(op)}`
      + (upper(op.lokasi_kejadian) ? ` DI ${upper(op.lokasi_kejadian)}` : '')
      + (upper(op.koordinat) ? ' PADA PERKIRAAN KOORDINAT {k}' : '') + '.';
  }

  function buildLaporanAwal(op) {
    const angkut = (op.detail_alut || []).filter(isKendaraanAlut)
      .map((a) => `${a.jumlah_alut || 1} ${upper(a.satuan)} ${upper(a.nama_alut)}`.replace(/\s+/g, ' ')).join(' DAN ');
    const jarak = upper(op.jarak), radial = upper(op.radial), eta = upper(op.eta_lokasi);
    const bbb = `TERSEBUT AAA DIATAS, ${timOf(op)} DIBERANGKATKAN MENUJU LKP`
      + (angkut ? ` MENGGUNAKAN ${angkut}` : '')
      + (jarak ? ` DENGAN JARAK ${jarak}` : '')
      + (radial ? (jarak ? ` RADIAL ${radial}` : ` DENGAN RADIAL ${radial}`) : '')
      + (eta ? (jarak || radial ? ` DAN ETA ${eta}` : ` DENGAN ETA ${eta}`) : '') + '.';
    return draft(`LAPORAN AWAL OPERASI SAR TERHADAP ${kejadianOf(op)}.`, KABASARNAS, '', 'LAPORAN_AWAL', [
      toSentence(terimaInfoText(op), { k: op.koordinat }),
      toSentence(bbb),
      listsCcc(op, op, { cuaca: op.cuaca, angin: op.angin, gelombang: op.gelombang }),
      'LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI KRM TTK HBS'
    ]);
  }

  function sruBlock(sru, n) {
    const area = parseSruArea(sru.koordinat_area);
    const lines = [`${n}. SEARCH AREA SRU ${n} TTK DUA`].concat(area.coords.map((c, j) => `${String.fromCharCode(65 + j)}. ${upper(c)}`));
    lines.push(toSentence(`POLA ${upper(sru.pola_pencarian).replace(/^POLA\s+(?:PENCARIAN\s+)?/, '')}` + (upper(sru.track_spacing) ? ` TS ${upper(sru.track_spacing)}` : '') + (upper(sru.luas_area) ? ` LUAS ${upper(sru.luas_area)}` : '')));
    if (area.entry || area.exit) lines.push(`ENTRY DAN EXIT POINT ${upper(area.entry)} ${upper(area.exit)}`.trim());
    return lines.join('\n');
  }

  function buildRenops(op, renops) {
    const x = hariNumber(renops.hari_ke), nama = kejadianOf(op);
    const srus = renops.srus || [];
    return draft(`RENCANA OPERASI SAR HARI KE ${x} TERHADAP ${nama}.`, KABASARNAS, '', 'LANJUTAN', [
      toSentence(`PD TW ${militaryTime(renops.waktu_briefing)} TIM SAR GABUNGAN MELAKSANAKAN BRIEFING RENCANA OPERASI SAR HARI KE ${x} TERHADAP ${nama} DENGAN WAKTU MULAI PERGERAKAN TW ${militaryTime(renops.waktu_mulai_ops)}.`),
      srus.length ? 'PEMBAGIAN SRU TTK DUA\n' + srus.map((s, i) => sruBlock(s, i + 1)).join('\n') : '',
      listsCcc(renops, op, { cuaca: renops.cuaca }),
      'LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS'
    ]);
  }

  function buildLapbang(op, lap) {
    const x = hariNumber(lap.hari_ke), nama = kejadianOf(op), tw = militaryTime(lap.waktu_laporan);
    const henti = toSentence(`PADA TW ${tw} PENCARIAN HARI KE ${x} DIHENTIKAN SEMENTARA DAN AKAN DILANJUTKAN KEMBALI PADA HARI KE ${x + 1}.`);
    return draft(`LAPORAN PERKEMBANGAN HARI KE ${x} OPERASI SAR TERHADAP ${nama}.`, KABASARNAS, '', 'LANJUTAN', [
      toSentence(`PD TW ${tw} TERIMA INFO DARI OSC TIM SAR GABUNGAN BAHWA ${clause(lap.isi_laporan)}.`),
      listsCcc(lap, op, { cuaca: lap.cuaca }),
      henti,
      `DMKN SBG LAPORAN PERKEMBANGAN HARI KE ${x} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`
    ]);
  }

  // Statistik korban: pakai stats_korban dari API (SarCalculationService::hitungStatistikKorban), bila tidak ada hitung dengan aturan yang sama
  function victimStats(op) {
    const st = op.stats_korban;
    if (st && typeof st.s === 'number') return { s: st.s, md: st.md || 0, dp: st.dp || 0, h: st.h || 0, total: st.total || 0 };
    const korban = op.korban || [];
    const pob = Number(op.jumlah_pob) > 0 ? Math.max(Number(op.jumlah_pob), korban.length) : korban.length;
    const k = { s: 0, md: 0, dp: pob - korban.length, h: 0, total: pob };
    korban.forEach((row) => {
      const v = upper(row.status_korban) || 'DP';
      if (v === 'S' || v === 'SELAMAT') k.s++;
      else if (v === 'MD' || v === 'MENINGGAL DUNIA') k.md++;
      else if (v === 'H' || v === 'HILANG / NIHIL') k.h++;
      else k.dp++;
    });
    return k;
  }

  function buildPenutupan(op, lap) {
    const nama = kejadianOf(op);
    const k = victimStats(op);
    return draft(`LAPORAN PERKEMBANGAN DAN PENUTUPAN OPERASI SAR TERHADAP ${nama}.`, KABASARNAS, '', 'LANJUTAN', [
      toSentence(`PD TW ${militaryTime(lap.waktu_debrief || lap.waktu_laporan)} TIM SAR GABUNGAN MELAKSANAKAN DEBRIEFING PENUTUPAN OPERASI SAR TERHADAP ${nama} DENGAN HASIL ${clause(lap.isi_laporan)}.`),
      listsCcc(lap, op, { cuaca: lap.cuaca }),
      numberedList('DATA KORBAN', [`SELAMAT : ${k.s} ORANG`, `MENINGGAL DUNIA : ${k.md} ORANG`, `DALAM PENCARIAN : ${k.dp} ORANG`]
        .concat(k.h ? [`HILANG : ${k.h} ORANG`] : [], [`TOTAL POB : ${k.total} ORANG`])),
      toSentence(`DENGAN DEMIKIAN OPERASI SAR TERHADAP ${nama} DINYATAKAN DITUTUP. ATAS KERJASAMA SELURUH UNSUR SAR TERLIBAT DIUCAPKAN TERIMA KASIH.`),
      'DMKN SBG LAPORAN PERKEMBANGAN DAN PENUTUPAN OPERASI SAR KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS'
    ]);
  }

  function buildMapel(op) {
    return draft(`PERMINTAAN PEMAPELAN DAN PEMANTAUAN TERHADAP ${kejadianOf(op)}.`, 'KA KSOP KENDARI KMA KA SROP KENDARI', KABASARNAS, 'LANJUTAN', [
      toSentence(`${terimaInfoText(op)} TERHADAP KEJADIAN TERSEBUT ${timOf(op)} TELAH DIBERANGKATKAN MENUJU LKP UNTUK MELAKSANAKAN PENCARIAN.`, { k: op.koordinat }),
      'TERKAIT INFO MUSIBAH TSB MHN BANTUAN PEMANTAUAN DAN PEMAPELAN KEPADA KAPALS YANG MELINTASI DI AREA TERSEBUT KMA APABILA MENEMUKAN TANDAS KEBERADAAN KAPAL TERSEBUT AGAR DAPAT MEMBERIKAN PERTOLONGAN PERTAMA SERTA MELAPORKAN KPD BASARNAS KENDARI TTK',
      // Tanpa operasi tertaut (preset) pengirim memakai jabatan asli, bukan SMC
      `ATAS PERHATIAN DAN KERJASAMANYA DIUCAPKAN TERIMA KASIH KMA KA KPP KENDARI${op.id_operasi ? ' SELAKU SMC' : ''} KRM TTK HBS`
    ]);
  }

  function referenceOptions(op) {
    const lap = (op && op.laporan_harian) || [];
    // Urut per hari lalu id; label hari yang sama diberi nomor urut agar bisa dibedakan
    const options = (rows, idKey) => {
      const seen = {};
      return rows.map((r) => ({ id: r[idKey], hari: hariNumber(r.hari_ke) }))
        .sort((a, b) => a.hari - b.hari || Number(a.id) - Number(b.id))
        .map((o) => {
          seen[o.hari] = (seen[o.hari] || 0) + 1;
          return { id: o.id, label: `HARI KE ${o.hari}` + (seen[o.hari] > 1 ? ` (${seen[o.hari]})` : '') };
        });
    };
    const tutup = lap.filter((l) => upper(l.jenis_laporan) === 'PENUTUPAN');
    return {
      renops: options((op && op.renops) || [], 'id_renops'),
      lapbang: options(lap.filter((l) => upper(l.jenis_laporan) !== 'PENUTUPAN'), 'id_laporan'),
      penutupan: tutup.length ? tutup[tutup.length - 1] : null
    };
  }

  // Kepala naskah diwarisi dari BRA terakhir operasi ini dengan kategori sama (bra_sar terurut id_bra DESC).
  // MENBRA Pemapelan merujuk BRA Operasi SAR terakhir. null bila tidak ada pendahulu.
  const normCat = (k) => { const c = upper(k); return c === 'SAR_UMUM' || c === 'OPERASI_SAR' ? 'OPERASI_SAR' : c === 'KAPAL' || c === 'PERGERAKAN_KAPAL' ? 'PERGERAKAN_KAPAL' : 'MAPEL'; };
  function inheritHeader(op, kategori, excludeId) {
    const list = ((op && op.bra_sar) || []).filter((b) => String(b.id_bra) !== String(excludeId || 0));
    const latest = (cat) => list.find((b) => normCat(b.kategori) === cat) || null;
    const prev = latest(normCat(kategori));
    const ref = normCat(kategori) === 'MAPEL' ? latest('OPERASI_SAR') : prev;
    if (!prev && !ref) return null;
    const out = {};
    if (ref && ref.no_bra) out.menbra = upper(ref.no_bra);
    if (prev) {
      out.aksi = upper(prev.aksi);
      out.info = upper(prev.info);
      out.derajat = upper(prev.derajat);
    }
    return out;
  }

  function senderTitle(kind, linked, realTitle) {
    return kind === 'LAPORAN_AWAL' || !linked ? realTitle : 'SMC';
  }

  const BraTemplates = {
    toSentence, formatSectionText, militaryTime, hariNumber, numberedList, letteredList,
    unsurItems, alutItems, cuacaItems, isTransportAlut, isKendaraanAlut, parseSruArea,
    buildLaporanAwal, buildRenops, buildLapbang, buildPenutupan, buildMapel, referenceOptions, senderTitle, inheritHeader,
    KABASARNAS
  };

  global.BraTemplates = BraTemplates;
  if (typeof module !== 'undefined' && module.exports) module.exports = BraTemplates;
})(typeof window !== 'undefined' ? window : globalThis);
