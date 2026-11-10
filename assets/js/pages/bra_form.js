// Page Configuration from window.__BRA_FORM_CONFIG__
const braFormCfg = window.__BRA_FORM_CONFIG__ || {};
const verifyUrl = braFormCfg.verifyUrl || '';
const logoDataUri = braFormCfg.logoDataUri || '';

function initBraForm() {
  const form = document.getElementById('braMasterForm');
  const sectionsContainer = document.getElementById('sectionsListContainer');
  const btnAddSection = document.getElementById('btnAddSectionBtn');
  const liveWordCountBadge = document.getElementById('liveWordCountBadge');
  const hiddenJumlahKata = document.getElementById('hiddenJumlahKata');
  const opSelect = document.getElementById('operasiSelect');
  const pejabatSelect = document.getElementById('pejabatSelectControl');
  const kategoriRadios = document.querySelectorAll('.kategori-radio');
  const mapelPresets = document.getElementById('mapelPresetsContainer');
  const opRecBar = document.getElementById('operasiRecommendationBar');

  // Preview elements
  const pvPanggilan = document.getElementById('pvPanggilan');
  const pvJenis = document.getElementById('pvJenis');
  const pvNrStn = document.getElementById('pvNrStn');
  const pvInstruksiPenerusan = document.getElementById('pvInstruksiPenerusan');
  const pvDerajat = document.getElementById('pvDerajat');
  const pvDari = document.getElementById('pvDari');
  const pvAksi = document.getElementById('pvAksi');
  const pvInfo = document.getElementById('pvInfo');
  const pvTwu = document.getElementById('pvTwu');
  const pvTwb = document.getElementById('pvTwb');
  const pvJumlahKata = document.getElementById('pvJumlahKata');
  const pvNoBra = document.getElementById('pvNoBra');
  const pvMenbra = document.getElementById('pvMenbra');
  const pvMenbraRow = document.getElementById('pvMenbraRow');
  const pvPerihal = document.getElementById('pvPerihal');
  const pvSections = document.getElementById('pvSectionsContainer');
  const pvPengirim = document.getElementById('pvPengirim');
  const pvNamaSmc = document.getElementById('pvNamaSmc');
  const pvPangkatSmc = document.getElementById('pvPangkatSmc');
  const pvJabatanSmc = document.getElementById('pvJabatanSmc');
  const pvTwt = document.getElementById('pvTwt');
  const pvTwk = document.getElementById('pvTwk');
  const pvSistem = document.getElementById('pvSistem');
  const pvParaf = document.getElementById('pvParaf');

  // Military TW Format Helper
  const getMilitaryTW = (typeof window !== 'undefined' && window.QrSarUtils && window.QrSarUtils.Bra && typeof window.QrSarUtils.Bra.getMilitaryTW === 'function')
    ? window.QrSarUtils.Bra.getMilitaryTW
    : function (dateObj) {
        const d = dateObj || new Date();
        const utc = d.getTime() + d.getTimezoneOffset() * 60000;
        const wita = new Date(utc + 8 * 3600000);
        const mm = String(wita.getMonth() + 1).padStart(2, '0');
        const dd = String(wita.getDate()).padStart(2, '0');
        const hh = String(wita.getHours()).padStart(2, '0');
        const min = String(wita.getMinutes()).padStart(2, '0');
        return `${mm}${dd} ${hh}${min} H`;
      };

  // Section letter helper (0 -> AAA, 1 -> BBB, ...)
  const getSectionLetter = (typeof window !== 'undefined' && window.QrSarUtils && window.QrSarUtils.Bra && typeof window.QrSarUtils.Bra.getSectionLetter === 'function')
    ? window.QrSarUtils.Bra.getSectionLetter
    : function (idx) {
        const char = String.fromCharCode(65 + (idx % 26));
        return char.repeat(3);
      };

  function getSectionHint(idx, kat) {
    if (idx === 0) return kat === 'MAPEL' ? '(Dasar Info, TW Terima, Kejadian/POB, Ciri Kapal, LKP & Kondisi)' : (kat === 'PERGERAKAN_KAPAL' ? '(Dasar & Rencana Pelayaran / BKO)' : '(Kronologi / Informasi Awal)');
    if (idx === 1) return kat === 'MAPEL' ? '(Permintaan Bantuan Pemantauan & Pemapelan Kapal Melintas)' : (kat === 'PERGERAKAN_KAPAL' ? '(Data Kapal, Ren Layar, POB & Palsar)' : '(Unsur SAR Terlibat, Alut, Palsar & Cuaca)');
    if (idx === 2) return kat === 'MAPEL' ? '(Penutup & Ucapan Terima Kasih Kakansar)' : (kat === 'PERGERAKAN_KAPAL' ? '(Laporan Perkembangan Menyusul / Penutup)' : '(Penutup / Rencana Tindak Lanjut)');
    return '(Bagian Tambahan)';
  }

  // Quick Derajat setter
  window.setDerajat = function(val) {
    document.getElementById('derajatInput').value = val;
    syncLivePreview();
  };

  // Stepper Smooth Scroll & Active Indicator
  const stepperBar = document.getElementById('braStepperBar');
  if (stepperBar) {
    stepperBar.querySelectorAll('.bra-step-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('data-target');
        const targetEl = document.getElementById(targetId);
        if (targetEl) {
          targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
          stepperBar.querySelectorAll('.bra-step-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
        }
      });
    });

    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const id = entry.target.id;
            stepperBar.querySelectorAll('.bra-step-btn').forEach(b => {
              if (b.getAttribute('data-target') === id) b.classList.add('active');
              else b.classList.remove('active');
            });
          }
        });
      }, { threshold: 0.35 });
      document.querySelectorAll('.bra-card[id]').forEach(c => observer.observe(c));
    }
  }

  // Re-index all sections
  function reindexSections() {
    const currentKat = getSelectedKategori();
    const rows = sectionsContainer.querySelectorAll('.section-block');
    rows.forEach((row, idx) => {
      const code = getSectionLetter(idx);
      const badge = row.querySelector('.sec-code-badge');
      const hidden = row.querySelector('.sec-code-input');
      const ta = row.querySelector('.sec-content-textarea');
      const hint = row.querySelector('.sec-hint-label');

      if (badge) badge.textContent = code;
      if (hidden) { hidden.value = code; hidden.name = `sections[${idx}][kode]`; }
      if (ta) { ta.name = `sections[${idx}][isi]`; ta.placeholder = `Isi bagian ${code} ${getSectionHint(idx, currentKat)}`; }
      if (hint) hint.textContent = getSectionHint(idx, currentKat);

      // Handle remove button (only >= 3)
      let delBtn = row.querySelector('.btn-delete-section');
      if (idx >= 3) {
        if (!delBtn) {
          const header = row.querySelector('.sec-item-header');
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'sec-del-btn btn-delete-section';
          btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg><span>Hapus</span>`;
          header.appendChild(btn);
        }
      } else {
        if (delBtn) delBtn.remove();
      }
    });
    syncLivePreview();
  }

  // Add section
  if (btnAddSection) {
    btnAddSection.addEventListener('click', function() {
      const rows = sectionsContainer.querySelectorAll('.section-block');
      const nextIdx = rows.length;
      const nextCode = getSectionLetter(nextIdx);

      const div = document.createElement('div');
      div.className = 'sec-item section-block';
      div.setAttribute('data-index', nextIdx);
      div.innerHTML = `
        <div class="sec-item-header">
          <div class="flex items-center gap-2">
            <span class="sec-code-badge">${nextCode}</span>
            <input type="hidden" name="sections[${nextIdx}][kode]" value="${nextCode}" class="sec-code-input">
            <span class="sec-hint-title sec-hint-label">(Bagian Tambahan)</span>
          </div>
          <button type="button" class="sec-del-btn btn-delete-section" title="Hapus bagian ini">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            <span>Hapus</span>
          </button>
        </div>
        <textarea name="sections[${nextIdx}][isi]" rows="4" class="bra-control sec-content-textarea upper" placeholder="Isi naskah telegram untuk bagian ${nextCode}..." required></textarea>
      `;
      sectionsContainer.appendChild(div);
      div.querySelector('textarea').focus();
      reindexSections();
    });
  }

  // Remove section
  sectionsContainer.addEventListener('click', function(e) {
    const delBtn = e.target.closest('.btn-delete-section');
    if (delBtn) {
      const rows = sectionsContainer.querySelectorAll('.section-block');
      if (rows.length <= 3) {
        if (typeof showToast === 'function') showToast('Dokumen BRA SAR minimal memerlukan bagian AAA, BBB, dan CCC.', 'warning');
        return;
      }
      delBtn.closest('.section-block').remove();
      reindexSections();
    }
  });

  // Section textarea input -> Live sync & uppercase
  sectionsContainer.addEventListener('input', function(e) {
    if (e.target.classList.contains('sec-content-textarea')) {
      syncLivePreview();
    }
  });

  // Calculate words (Standardized Senkom Formula: Perihal + Kode Seksi + Isi Seksi)
  function calculateWordCount() {
    let combined = '';
    const perihalEl = document.getElementById('perihalInput');
    const perihalVal = perihalEl ? perihalEl.value.trim() : '';
    if (perihalVal) {
      combined += perihalVal + ' ';
    }

    sectionsContainer.querySelectorAll('.section-block').forEach(row => {
      const badge = row.querySelector('.sec-code-badge');
      const ta = row.querySelector('.sec-content-textarea');
      const code = badge ? badge.textContent.trim() : '';
      const isi = ta ? ta.value.trim() : '';
      if (isi) {
        if (code) combined += code + ' ';
        combined += isi + ' ';
      }
    });

    const words = combined.trim().split(/\s+/).filter(Boolean);
    const count = words.length;
    const str = `${count} KATA`;

    if (liveWordCountBadge) liveWordCountBadge.textContent = str;
    if (hiddenJumlahKata) hiddenJumlahKata.value = str;
    if (pvJumlahKata) pvJumlahKata.textContent = str;
    return count;
  }

  function getSelectedKategori() {
    const checked = document.querySelector('input[name="kategori"]:checked');
    return checked ? checked.value : 'MAPEL';
  }

  // Live Sync Engine between form and official paper preview
  function syncLivePreview() {
    calculateWordCount();

    if (pvPanggilan) pvPanggilan.textContent = document.getElementById('panggilanInput').value.trim() || '—';
    if (pvJenis) pvJenis.textContent = document.getElementById('jenisInput').value.trim() || 'SAR';
    if (pvNrStn) pvNrStn.textContent = document.getElementById('nrStnInput').value.trim() || '—';
    if (pvInstruksiPenerusan) pvInstruksiPenerusan.textContent = document.getElementById('instruksiPenerusanInput').value.trim() || '—';
    if (pvDerajat) pvDerajat.textContent = document.getElementById('derajatInput').value.trim() || 'SGR';

    if (pvDari) pvDari.textContent = document.getElementById('dariInput').value.trim() || 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';
    if (pvAksi) pvAksi.textContent = document.getElementById('aksiInput').value.trim() || '—';
    if (pvInfo) pvInfo.textContent = document.getElementById('infoInput').value.trim() || '—';

    const twuVal = document.getElementById('twuInput').value.trim();
    if (pvTwu) pvTwu.textContent = twuVal.startsWith('TWU') ? twuVal : `TWU ${twuVal || (braFormCfg.twuPlaceholder || '')}`;

    const twbVal = document.getElementById('twbInput').value.trim();
    if (pvTwb) pvTwb.textContent = twbVal.startsWith('TWB') ? twbVal : `TWB ${twbVal || (braFormCfg.twbPlaceholder || '')}`;

    const noBraVal = document.getElementById('noBraInput').value.trim();
    if (pvNoBra) pvNoBra.textContent = noBraVal || (braFormCfg.noBraPlaceholder || '');
    const matchUrut = noBraVal.match(/^(\d+)/);
    const hiddenUrutEl = document.getElementById('hiddenNomorUrut');
    if (matchUrut && hiddenUrutEl) {
      hiddenUrutEl.value = matchUrut[1];
    }

    const menbraVal = document.getElementById('menbraInput').value.trim();
    if (pvMenbra) pvMenbra.textContent = menbraVal;
    if (pvMenbraRow) {
      if (menbraVal && menbraVal.toUpperCase() !== 'SEGERA' && menbraVal !== '-') {
        pvMenbraRow.classList.remove('hidden');
      } else {
        pvMenbraRow.classList.add('hidden');
      }
    }

    if (pvPerihal) pvPerihal.textContent = document.getElementById('perihalInput').value.trim() || 'BERITA RADIO SAR TTK';

    // Sections
    if (pvSections) {
      pvSections.innerHTML = '';
      sectionsContainer.querySelectorAll('.section-block').forEach(row => {
        const code = row.querySelector('.sec-code-badge').textContent.trim();
        const text = row.querySelector('.sec-content-textarea').value.trim();
        if (text) {
          const div = document.createElement('div');
          div.className = 'bra-section-row flex items-start text-xs sm:text-[13px] leading-relaxed';

          const rawLines = text.split(/\r?\n/);
          const textLines = [];
          rawLines.forEach(rLine => {
            const l = rLine.trim();
            if (!l) {
              textLines.push('');
              return;
            }
            if (l.length > 500 && /\bTTK(?!\s+DUA)\b/i.test(l)) {
              const parts = l.split(/(\bTTK(?!\s+DUA)\b\s*)/i);
              let chunk = '';
              for (let i = 0; i < parts.length; i++) {
                chunk += parts[i];
                if (/^TTK$/i.test(parts[i].trim()) || i === parts.length - 1) {
                  if (chunk.length >= 250 || i === parts.length - 1) {
                    const t = chunk.trim();
                    if (t) textLines.push(t);
                    chunk = '';
                  }
                }
              }
              if (chunk.trim()) textLines.push(chunk.trim());
            } else {
              textLines.push(l);
            }
          });

          let linesHtml = '';
          textLines.forEach(tLine => {
            const tTrim = tLine.trim();
            if (tTrim === '') {
              linesHtml += '<div class="bra-text-line h-2.5"></div>';
            } else {
              linesHtml += `<div class="bra-text-line text-justify">${escapeHtml(tTrim)}</div>`;
            }
          });

          div.innerHTML = `<span class="w-14 sm:w-16 font-black tracking-wider uppercase shrink-0 text-black leading-relaxed">${code}</span><span class="w-4 text-center shrink-0 text-black font-black leading-relaxed">:</span><div class="flex-1 uppercase font-normal leading-relaxed ml-1 text-black bra-section-content text-justify">${linesHtml}</div>`;
          pvSections.appendChild(div);
        }
      });
    }

    if (pvPengirim) pvPengirim.textContent = document.getElementById('pengirimInput').value.trim() || 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';
    if (pvNamaSmc) pvNamaSmc.textContent = document.getElementById('namaSmcInput').value.trim() || 'AMIRUDDIN AS, S.Sos.';
    if (pvPangkatSmc) pvPangkatSmc.textContent = document.getElementById('pangkatSmcInput').value.trim() || 'PEMBINA (IV/a)';
    if (pvJabatanSmc) pvJabatanSmc.textContent = document.getElementById('jabatanSmcInput').value.trim() || 'SMC';

    if (pvTwt) pvTwt.textContent = document.getElementById('twtInput').value.trim() || '—';
    if (pvTwk) pvTwk.textContent = document.getElementById('twkInput').value.trim() || '—';
    if (pvSistem) pvSistem.textContent = document.getElementById('sistemInput').value.trim() || '—';
    if (pvParaf) pvParaf.textContent = document.getElementById('parafInput').value.trim() || '—';
  }

  const escapeHtml = (window.QrSarUtils && window.QrSarUtils.escapeHtml) || function(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  };

  // Bind live sync to all form inputs
  const liveSyncInputs = form.querySelectorAll('input, textarea, select');
  liveSyncInputs.forEach(el => {
    el.addEventListener('input', syncLivePreview);
    el.addEventListener('change', syncLivePreview);
  });

  // Buttons for Waktu Sekarang
  document.getElementById('btnSetTwbNow')?.addEventListener('click', function() {
    document.getElementById('twbInput').value = getMilitaryTW(new Date(Date.now() - 15 * 60000));
    syncLivePreview();
  });
  document.getElementById('btnSetTwuNow')?.addEventListener('click', function() {
    document.getElementById('twuInput').value = getMilitaryTW(new Date());
    syncLivePreview();
  });

  // Track Auto vs Custom Nomor Naskah & Deteksi Tabrakan Nomor Unik
  const noBraInput = document.getElementById('noBraInput');
  const isAutoNoBra = document.getElementById('isAutoNoBra');
  const noBraWarning = document.getElementById('noBraWarning');
  const currentBraId = typeof braFormCfg !== 'undefined' && braFormCfg.idBra ? braFormCfg.idBra : 0;
  const initialNoBraPlaceholder = braFormCfg.noBraPlaceholder || '';
  let checkNoBraTimeout = null;

  function verifyNoBraAvailability(val) {
    if (!noBraWarning) return;
    if (!val || val === initialNoBraPlaceholder) {
      noBraWarning.classList.add('hidden');
      noBraWarning.textContent = '';
      return;
    }
    const cleanVal = val.trim();
    fetch(`${BASE_URL}/api/bra/check-number?no_bra=${encodeURIComponent(cleanVal)}&exclude_id=${currentBraId}`)
      .then(r => r.json())
      .then(res => {
        if (res.success && res.exists && res.data) {
          noBraWarning.textContent = `⚠️ PERINGATAN: Nomor "${res.data.no_bra}" sudah digunakan pada dokumen #${res.data.id_bra} (${res.data.perihal}). Harap pastikan nomor urut tidak bertabrakan!`;
          noBraWarning.classList.remove('hidden');
        } else {
          noBraWarning.classList.add('hidden');
          noBraWarning.textContent = '';
        }
      })
      .catch(() => {
        noBraWarning.classList.add('hidden');
      });
  }

  if (noBraInput && isAutoNoBra) {
    noBraInput.addEventListener('input', function() {
      const val = this.value.trim().toUpperCase();
      if (!val || val === initialNoBraPlaceholder) {
        isAutoNoBra.value = '1';
      } else {
        isAutoNoBra.value = '0';
      }
      clearTimeout(checkNoBraTimeout);
      checkNoBraTimeout = setTimeout(() => {
        verifyNoBraAvailability(val);
      }, 500);
    });
  }

  // Sandi telegram & draf naskah: logika murni ada di assets/js/bra/bra_templates.js (BraTemplates)
  // Bila bra_templates.js gagal dimuat, form tetap jalan: tombol draf nonaktif dan teks hanya dikapitalkan
  const BT_READY = !!window.BraTemplates;
  if (!BT_READY) console.error('assets/js/bra/bra_templates.js tidak termuat: draf otomatis BRA dinonaktifkan.');
  const BT = window.BraTemplates || {
    KABASARNAS: 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
    toSentence: (v) => String(v || '').trim().toUpperCase(),
    formatSectionText: (v) => String(v || '').toUpperCase(),
    senderTitle: (kind, linked, real) => (kind === 'LAPORAN_AWAL' || !linked ? real : 'SMC'),
    referenceOptions: () => ({ renops: [], lapbang: [], penutupan: null }),
    hariNumber: () => 1
  };
  const KABASARNAS = BT.KABASARNAS;
  const INFO_KAPAL = 'DEPUTI OPS DAN KESIAPSIAGAAN KMA DEPUTI SARPRAS DAN SISTEM KOMUNIKASI KMA DIREKTUR SARPRAS DAN DIREKTUR KESIAPSIAGAAN';
  const JABATAN_DEFAULT = 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';

  // Perihal: kalimat sandi yang selalu berakhiran TTK
  function perihalText(val) {
    const p = BT.toSentence(val);
    return p && !/\bTTK$/.test(p) ? p + ' TTK' : p;
  }

  // Pastikan judul perihal selalu berakhiran TTK saat blur
  const perihalInput = document.getElementById('perihalInput');
  if (perihalInput) {
    perihalInput.addEventListener('blur', function() {
      if (this.value.trim()) {
        this.value = perihalText(this.value);
        syncLivePreview();
      }
    });
  }

  // Format Sandi Telegram (Kapital & TTK/KMA/TTK DUA & Reduplikasi S) Manual via Button
  document.getElementById('btnFormatTelegramPunctuation')?.addEventListener('click', function() {
    form.querySelectorAll('textarea, input[type="text"]').forEach(el => {
      if (['noBraInput','menbraInput','twuInput','twbInput','twtInput','twkInput','sistemInput','parafInput','panggilanInput','nrStnInput','namaSmcInput','pangkatSmcInput','jabatanSmcInput','dariInput','pengirimInput','aksiInput','infoInput'].includes(el.id)) {
        el.value = el.value.toUpperCase();
        return;
      }
      el.value = el.id === 'perihalInput' ? perihalText(el.value) : BT.formatSectionText(el.value);
    });
    syncLivePreview();
    if (typeof showToast === 'function') showToast('Naskah berhasil diformat ke Sandi Baku Telegram!', 'success');
  });

  // Alamat AKSI/INFO baku per kategori; nilai yang sudah diubah petugas tidak ditimpa
  const RECIPIENTS = {
    OPERASI_SAR: { aksi: KABASARNAS, info: '', aksiPh: KABASARNAS, infoPh: 'POLRES/POLSEK KMA KSOP KMA SROP SETEMPAT' },
    MAPEL: { aksi: 'KA KSOP KENDARI KMA KA SROP KENDARI', info: KABASARNAS, aksiPh: 'KA KSOP KENDARI KMA KA SROP KENDARI', infoPh: KABASARNAS },
    PERGERAKAN_KAPAL: { aksi: KABASARNAS, info: INFO_KAPAL, aksiPh: KABASARNAS, infoPh: INFO_KAPAL }
  };
  const KNOWN_RECIPIENTS = new Set([
    KABASARNAS, INFO_KAPAL, 'KA KSOP KENDARI KMA KA SROP KENDARI',
    'POLRES/POLSEK KMA KSOP/SROP SETEMPAT', 'POLRES/POLSEK KMA KSOP KMA SROP SETEMPAT',
    'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
    'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN KMA GUBERNUR SULTRA',
    'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN KMA DIREKTUR SARPRAS KMA DIREKTUR KESIAPSIAGAAN'
  ]);

  function setRecipient(el, value, placeholder, keepValue) {
    if (!el) return;
    el.placeholder = placeholder;
    if (keepValue) return;
    const cur = el.value.trim().toUpperCase();
    if (!cur || KNOWN_RECIPIENTS.has(cur)) el.value = value;
  }

  // Jabatan pengirim: Laporan Awal & BRA tanpa operasi = jabatan asli, BRA lanjutan terkait operasi = SMC
  let lastDraftKind = /^LAPORAN AWAL\b/.test((document.getElementById('perihalInput')?.value || '').trim().toUpperCase()) ? 'LAPORAN_AWAL' : 'LANJUTAN';
  function realTitle() {
    const opt = pejabatSelect ? pejabatSelect.options[pejabatSelect.selectedIndex] : null;
    return ((opt && opt.value && opt.getAttribute('data-jabatan')) || JABATAN_DEFAULT).toUpperCase();
  }
  function applySenderTitle(kind) {
    lastDraftKind = kind;
    document.getElementById('jabatanSmcInput').value = BT.senderTitle(kind, !!(opSelect && opSelect.value), realTitle());
  }

  // Centralized Category UI switch handler
  // initial=true saat halaman dimuat: nilai AKSI/INFO tersimpan (mode edit) tidak disentuh
  function updateCategoryUI(kat, initial) {
    const menbraTitle = document.getElementById('labelMenbraTitle');
    const menbraInput = document.getElementById('menbraInput');
    const hintMenbra = document.getElementById('hintMenbraText');
    const show = (el, on) => { if (el) { el.classList.toggle('hidden', !on); el.style.display = on ? 'block' : 'none'; } };
    show(mapelPresets, kat === 'MAPEL');

    if (menbraInput && (menbraInput.value.trim().toUpperCase() === 'SEGERA' || menbraInput.value.trim() === '-')) menbraInput.value = '';
    if (kat === 'PERGERAKAN_KAPAL') {
      if (menbraTitle) menbraTitle.textContent = 'MENBRA (Rujukan Naskah Lalu)';
      if (menbraInput) menbraInput.placeholder = 'Nomor naskah pergerakan kapal sebelumnya (opsional)';
      if (hintMenbra) hintMenbra.textContent = 'Nomor naskah telegram pergerakan kapal sebelumnya bila merupakan pergerakan lanjutan.';
    } else if (kat === 'MAPEL') {
      if (menbraTitle) menbraTitle.textContent = 'MENBRA (Rujukan BRA/MAPEL Lalu)';
      if (menbraInput) menbraInput.placeholder = 'Nomor MAPEL/BRA rujukan sebelumnya (opsional)';
      if (hintMenbra) hintMenbra.textContent = 'Nomor naskah telegram/MAPEL sebelumnya yang menjadi dasar rujukan bila ada lanjutan.';
    } else {
      if (menbraTitle) menbraTitle.textContent = 'MENBRA (Rujukan BRA Lalu)';
      if (menbraInput) menbraInput.placeholder = (Math.max(1, (braFormCfg.nomorUrutPlaceholder || 1) - 1)) + '/' + (braFormCfg.satkerKode || 'SAR-304') + '/' + (braFormCfg.bulanTahunPlaceholder || '');
      if (hintMenbra) hintMenbra.textContent = 'Nomor naskah BRA sebelumnya yang menjadi dasar rujukan (bila ada).';
    }

    const r = RECIPIENTS[kat] || RECIPIENTS.OPERASI_SAR;
    setRecipient(document.getElementById('aksiInput'), r.aksi, r.aksiPh, initial);
    setRecipient(document.getElementById('infoInput'), r.info, r.infoPh, initial);

    reindexSections();
    syncLivePreview();
  }

  // Bind radio events (inisialisasi kategori dijalankan setelah semua handler terdefinisi)
  kategoriRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      updateCategoryUI(this.value);
      applyInheritance();
    });
  });

  function ensureKategori(kat) {
    const rad = document.querySelector(`input[name="kategori"][value="${kat}"]`);
    if (rad && !rad.checked) {
      rad.checked = true;
      rad.dispatchEvent(new Event('change'));
    }
  }

  // Tombol referensi & pemilih H berdasarkan data operasi yang sedang dipilih
  let currentOp = null;
  const btnAutoInisiasi = document.getElementById('btnAutoInisiasi');
  const btnAutoRenops = document.getElementById('btnAutoRenops');
  const btnAutoSitrep = document.getElementById('btnAutoSitrep');
  const btnAutoPenutupan = document.getElementById('btnAutoPenutupan');
  const btnAutoMapel = document.getElementById('btnAutoMapel');
  const selRenopsHari = document.getElementById('selectRenopsHari');
  const selLapbangHari = document.getElementById('selectLapbangHari');

  function setRefButton(btn, enabled, offTitle, onTitle) {
    if (!btn) return;
    btn.disabled = !enabled;
    btn.classList.toggle('opacity-40', !enabled);
    btn.classList.toggle('cursor-not-allowed', !enabled);
    btn.title = enabled ? onTitle : offTitle;
  }

  function fillHariSelect(sel, items) {
    if (!sel) return;
    sel.replaceChildren(...items.map(o => new Option(o.label, String(o.id))));
    if (items.length) sel.selectedIndex = items.length - 1;
    sel.disabled = !items.length;
    sel.classList.toggle('hidden', !items.length);
  }

  function renderReferenceControls(op) {
    if (!BT_READY) op = null;
    const ref = op ? BT.referenceOptions(op) : { renops: [], lapbang: [], penutupan: null };
    const noOp = 'Pilih Operasi SAR terlebih dahulu';
    fillHariSelect(selRenopsHari, ref.renops);
    fillHariSelect(selLapbangHari, ref.lapbang);
    setRefButton(btnAutoInisiasi, !!op, noOp, 'Muat naskah Laporan Awal');
    setRefButton(btnAutoRenops, ref.renops.length > 0, op ? 'Belum ada RENOPS pada operasi ini' : noOp, 'Muat RENOPS pada hari terpilih');
    setRefButton(btnAutoSitrep, ref.lapbang.length > 0, op ? 'Belum ada Laporan Perkembangan pada operasi ini' : noOp, 'Muat Lapbang pada hari terpilih');
    setRefButton(btnAutoPenutupan, !!ref.penutupan, op ? 'Belum ada Laporan Penutupan pada operasi ini' : noOp, 'Muat Lapbang dan Penutupan Operasi SAR');
    setRefButton(btnAutoMapel, !!op, noOp, 'Muat naskah Pemapelan dari data Operasi SAR');
  }

  // API Load Operasi SAR data
  async function loadOperasiData(opId) {
    if (!opId) return null;
    try {
      const res = await fetch((window.BASE_URL || '') + '/api/operasi/' + encodeURIComponent(opId));
      const json = await res.json();
      return json.success ? json.data : null;
    } catch (e) {
      console.warn('Gagal memuat detail operasi:', e);
      return null;
    }
  }

  // Muat operasi terpilih; respons yang datang setelah operasi diganti diabaikan
  async function selectOperasi(opId) {
    currentOp = null;
    renderReferenceControls(null);
    fillMenbraOptions(null);
    if (!opId) return null;
    const op = await loadOperasiData(opId);
    if (!opSelect || opSelect.value !== opId) return null;
    if (!op && typeof showToast === 'function') showToast('Gagal memuat data Operasi SAR. Periksa koneksi lalu pilih ulang operasi.', 'error');
    currentOp = op;
    renderReferenceControls(op);
    fillMenbraOptions(op);
    return op;
  }

  // MENBRA: tawarkan nomor BRA yang sudah terbit untuk operasi ini (terbaru di atas), selain naskah yang sedang diedit
  function fillMenbraOptions(op) {
    const dl = document.getElementById('menbraOptions');
    const input = document.getElementById('menbraInput');
    if (!dl) return;
    const list = ((op && op.bra_sar) || []).filter(b => b.no_bra && String(b.id_bra) !== String(currentBraId));
    dl.replaceChildren(...list.map(b => new Option(String(b.perihal || '').slice(0, 90), b.no_bra)));
    if (input && list.length) input.placeholder = `Terakhir: ${list[0].no_bra} (klik untuk memilih)`;
  }

  // Operasi Select handler
  opSelect.addEventListener('change', async function() {
    const opId = this.value;
    const opt = this.options[this.selectedIndex];
    const hiddenNamaOperasi = document.getElementById('hiddenNamaOperasi');
    if (hiddenNamaOperasi) {
      hiddenNamaOperasi.value = opId ? (opt.getAttribute('data-nama') || '') : '';
    }
    opRecBar.classList.toggle('hidden', !opId);
    applySenderTitle('LANJUTAN');
    const hasDraft = [...sectionsContainer.querySelectorAll('.sec-content-textarea')].some(t => t.value.trim());
    if (hasDraft && getSelectedKategori() === 'OPERASI_SAR' && typeof showToast === 'function') {
      showToast('Operasi diganti: isi naskah masih dari operasi sebelumnya. Muat ulang lewat tombol draf.', 'warning');
    }

    const op = await selectOperasi(opId);
    if (op) {
      if (getSelectedKategori() === 'MAPEL') {
        applyDraft(BT.buildMapel(op));
      } else if (getSelectedKategori() !== 'PERGERAKAN_KAPAL') {
        ensureKategori('OPERASI_SAR');
      }
      applyInheritance();
    }
    syncLivePreview();
  });

  // Tautan dari halaman operasi (?id_operasi=&from=renops|sitrep|penutupan|laporan_awal|mapel): draf dibuat lewat tombol yang sama
  selectOperasi(opSelect ? opSelect.value : '').then(op => {
    const a = braFormCfg.autoDraft;
    if (!op || !a) return;
    const pick = (sel, id) => { if (sel && id) sel.value = String(id); };
    if (a.from === 'renops') {
      pick(selRenopsHari, a.id_renops);
      btnAutoRenops.click();
    } else if (a.from === 'sitrep') {
      const list = op.laporan_harian || [];
      const l = findById(list, 'id_laporan', a.id_laporan) || list[list.length - 1];
      if (l && String(l.jenis_laporan || '').toUpperCase() === 'PENUTUPAN') return btnAutoPenutupan.click();
      pick(selLapbangHari, l && l.id_laporan);
      btnAutoSitrep.click();
    } else if (a.from === 'penutupan') {
      btnAutoPenutupan.click();
    } else if (a.from === 'mapel') {
      btnAutoMapel.click();
    } else {
      btnAutoInisiasi.click();
    }
  });

  // Pejabat Select Auto-fill
  if (pejabatSelect) {
    pejabatSelect.addEventListener('change', function() {
      const opt = this.options[this.selectedIndex];
      if (opt && opt.value) {
        document.getElementById('namaSmcInput').value = opt.getAttribute('data-nama') || '';
        document.getElementById('pangkatSmcInput').value = opt.getAttribute('data-pangkat') || '';
        applySenderTitle(lastDraftKind);
        syncLivePreview();
      }
    });
  }

  // Isi bagian naskah lewat DOM (.value), tanpa innerHTML untuk data operasi
  function setSectionsContent(sectionsArr) {
    sectionsContainer.replaceChildren();
    sectionsArr.forEach((s, idx) => {
      const div = document.createElement('div');
      div.className = 'sec-item section-block';
      div.setAttribute('data-index', idx);

      const header = document.createElement('div');
      header.className = 'sec-item-header';
      const left = document.createElement('div');
      left.className = 'flex items-center gap-2';
      const badge = document.createElement('span');
      badge.className = 'sec-code-badge';
      const kode = document.createElement('input');
      kode.type = 'hidden';
      kode.className = 'sec-code-input';
      const hint = document.createElement('span');
      hint.className = 'sec-hint-title sec-hint-label';
      left.append(badge, kode, hint);
      header.append(left);

      const ta = document.createElement('textarea');
      ta.rows = 4;
      ta.className = 'bra-control sec-content-textarea upper';
      ta.required = true;
      ta.value = s.isi || '';

      div.append(header, ta);
      sectionsContainer.appendChild(div);
    });
    reindexSections();
  }

  // Terapkan draf naskah dari BraTemplates ke form
  function applyDraft(d) {
    document.getElementById('perihalInput').value = d.perihal;
    document.getElementById('aksiInput').value = d.aksi;
    document.getElementById('infoInput').value = d.info;
    setSectionsContent(d.sections);
    applySenderTitle(d.kind);
    applyInheritance();
    syncLivePreview();
  }

  // BRA baru mewarisi MENBRA, AKSI, INFO dan derajat dari BRA sebelumnya pada operasi & kategori yang sama
  // (MENBRA Pemapelan merujuk BRA Operasi SAR terakhir). Mode edit tidak diubah.
  function applyInheritance() {
    if (currentBraId || !currentOp || !BT.inheritHeader) return;
    const h = BT.inheritHeader(currentOp, getSelectedKategori(), currentBraId);
    if (!h) return;
    const set = (id, v) => { const el = document.getElementById(id); if (el && v !== undefined) el.value = v; };
    set('menbraInput', h.menbra);
    // Belum ada BRA sebelumnya di kategori ini: pakai alamat baku kategori, bukan sisa warisan kategori lain
    const r = RECIPIENTS[getSelectedKategori()] || RECIPIENTS.OPERASI_SAR;
    set('aksiInput', h.aksi !== undefined ? h.aksi : r.aksi);
    set('infoInput', h.info !== undefined ? h.info : r.info);
    if (h.derajat) set('derajatInput', h.derajat);
    syncLivePreview();
  }

  function withCurrentOp(fn) {
    return function() {
      if (!currentOp) {
        if (typeof showToast === 'function') showToast('Pilih Operasi SAR pada dropdown terlebih dahulu.', 'warning');
        return;
      }
      fn(currentOp);
    };
  }

  const findById = (list, key, id) => (list || []).find(item => String(item[key]) === String(id));

  // 1. Laporan Awal
  btnAutoInisiasi.addEventListener('click', withCurrentOp(op => {
    ensureKategori('OPERASI_SAR');
    document.getElementById('derajatInput').value = 'SGR';
    applyDraft(BT.buildLaporanAwal(op));
    if (typeof showToast === 'function') showToast('Draf Laporan Awal berhasil dimuat!', 'success');
  }));

  // 2. RENOPS pada H terpilih
  btnAutoRenops.addEventListener('click', withCurrentOp(op => {
    const r = findById(op.renops, 'id_renops', selRenopsHari && selRenopsHari.value);
    if (!r) return;
    ensureKategori('OPERASI_SAR');
    applyDraft(BT.buildRenops(op, r));
    if (typeof showToast === 'function') showToast(`Draf RENOPS HARI KE ${BT.hariNumber(r.hari_ke)} berhasil dimuat!`, 'success');
  }));

  // 3. Lapbang pada H terpilih
  btnAutoSitrep.addEventListener('click', withCurrentOp(op => {
    const l = findById(op.laporan_harian, 'id_laporan', selLapbangHari && selLapbangHari.value);
    if (!l) return;
    ensureKategori('OPERASI_SAR');
    applyDraft(BT.buildLapbang(op, l));
    if (typeof showToast === 'function') showToast(`Draf Lapbang HARI KE ${BT.hariNumber(l.hari_ke)} berhasil dimuat!`, 'success');
  }));

  // 4. Lapbang dan Penutupan
  btnAutoPenutupan.addEventListener('click', withCurrentOp(op => {
    const l = BT.referenceOptions(op).penutupan;
    if (!l) return;
    ensureKategori('OPERASI_SAR');
    applyDraft(BT.buildPenutupan(op, l));
    if (typeof showToast === 'function') showToast('Draf Lapbang dan Penutupan berhasil dimuat!', 'success');
  }));

  // 5. Pemapelan dari Operasi SAR
  if (btnAutoMapel) {
    btnAutoMapel.addEventListener('click', withCurrentOp(op => {
      ensureKategori('MAPEL');
      document.getElementById('derajatInput').value = 'SGR';
      applyDraft(BT.buildMapel(op));
      if (typeof showToast === 'function') showToast('Draf Pemapelan dari Operasi berhasil dimuat!', 'success');
    }));
  }

  // 6. Aksi Cepat Mapel: Longboat / Kapal Hilang Kontak (tanpa operasi tetap bisa dipakai)
  const btnPresetLongboat = document.getElementById('btnPresetLongboatHilangKontak');
  if (btnPresetLongboat) {
    btnPresetLongboat.addEventListener('click', function() {
      ensureKategori('MAPEL');
      document.getElementById('derajatInput').value = 'SGR';
      applyDraft(BT.buildMapel({ ...(currentOp || {}), nama_operasi: 'LONGBOAT MATI MESIN DAN HILANG KONTAK' }));
      if (typeof showToast === 'function') showToast('Preset Pemapelan Longboat / Kapal Hilang Kontak berhasil dimuat!', 'success');
    });
  }

  updateCategoryUI(getSelectedKategori(), true);

  // QR Code Rendering for Live Preview & Modal Preview (verifyUrl and logoDataUri initialized from braFormCfg)

  function renderBraQrCode(targetCanvas, retries = 0) {
    if (!targetCanvas) return;
    if (typeof QRCode === 'undefined') {
      if (retries < 25) {
        setTimeout(function() { renderBraQrCode(targetCanvas, retries + 1); }, 80);
      }
      return;
    }
    QRCode.toCanvas(targetCanvas, verifyUrl, {
      width: 160,
      margin: 1,
      color: { dark: '#0a0f1d', light: '#ffffff' },
      errorCorrectionLevel: 'H'
    }, function(err) {
      if (err) {
        console.error('QR code error:', err);
        return;
      }
      targetCanvas.style.width = '66px';
      targetCanvas.style.height = '66px';
      targetCanvas.style.maxWidth = '66px';
      targetCanvas.style.maxHeight = '66px';
      const ctx = targetCanvas.getContext('2d');
      const size = targetCanvas.width;
      const logoImg = new Image();

      function drawBadgeAndLogo() {
        const logoSize = Math.floor(size * 0.22);
        const center = (size - logoSize) / 2;
        // White circular background badge
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2) + 4, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#cbd5e1';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        if (logoImg.naturalWidth > 0) {
          ctx.save();
          ctx.beginPath();
          ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2), 0, Math.PI * 2);
          ctx.clip();
          ctx.drawImage(logoImg, center, center, logoSize, logoSize);
          ctx.restore();
        } else {
          // Emergency fallback vector badge with 'SAR' text
          ctx.fillStyle = '#ea580c';
          ctx.beginPath();
          ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2), 0, Math.PI * 2);
          ctx.fill();
          ctx.fillStyle = '#ffffff';
          ctx.font = 'bold ' + Math.floor(logoSize * 0.44) + 'px sans-serif';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText('SAR', size / 2, size / 2 + 1);
        }
      }

      logoImg.onload = drawBadgeAndLogo;
      logoImg.onerror = function() {
        drawBadgeAndLogo();
      };
      logoImg.src = logoDataUri;
      if (logoImg.complete && logoImg.naturalWidth > 0) {
        drawBadgeAndLogo();
      }
    });
  }

  function updateLiveQrCode() {
    const liveCanvas = document.getElementById('liveBraQrCanvas');
    if (liveCanvas) {
      renderBraQrCode(liveCanvas);
    }
  }

  // Toggle Split View Layout (Desktop)
  const appWrap = document.getElementById('braMainAppWrap');
  const btnToggleSplit = document.getElementById('btnToggleSplitView');
  const splitLabel = document.getElementById('splitViewLabel') || document.getElementById('splitLabel');
  let isSplitActive = true;

  if (btnToggleSplit && appWrap) {
    appWrap.classList.add('bra-split-active');
    btnToggleSplit.addEventListener('click', function() {
      isSplitActive = !isSplitActive;
      if (isSplitActive) {
        appWrap.classList.add('bra-split-active');
        if (splitLabel) splitLabel.textContent = 'Sembunyikan Lembar';
      } else {
        appWrap.classList.remove('bra-split-active');
        if (splitLabel) splitLabel.textContent = 'Tampilkan Lembar';
      }
    });
  }

  // Modal Full Preview Engine (Robust Lifecycle & No Duplicate IDs)
  const modalPreview = document.getElementById('modalFullBraPreview');
  const btnOpenModal = document.getElementById('btnOpenModalPreview');
  const btnOpenModalBottom = document.getElementById('btnOpenModalPreviewBottom');
  const btnCloseModal = document.getElementById('btnCloseModalPreview');
  const modalTarget = document.getElementById('modalPaperTarget');

  function openModalPaperPreview() {
    syncLivePreview();
    updateLiveQrCode();
    const livePaper = document.getElementById('braLivePaperPreview');
    if (livePaper && modalTarget) {
      // Clone DOM node cleanly and strip unique IDs from children to prevent duplicate ID trapping
      const clonedPaper = livePaper.cloneNode(true);
      clonedPaper.id = 'modalClonedPaperPreview';
      clonedPaper.querySelectorAll('[id]').forEach(function(el) {
        if (el.tagName.toLowerCase() !== 'canvas') {
          el.removeAttribute('id');
        }
      });
      modalTarget.innerHTML = '';
      modalTarget.appendChild(clonedPaper);

      // Re-render QR code onto the cloned canvas in modal
      const modalCanvas = modalTarget.querySelector('canvas');
      if (modalCanvas) {
        modalCanvas.id = 'modalBraQrCanvas';
        modalCanvas.style.width = '72px';
        modalCanvas.style.height = '72px';
        modalCanvas.style.maxWidth = '72px';
        modalCanvas.style.maxHeight = '72px';
        renderBraQrCode(modalCanvas);
      }
    }
    if (modalPreview) {
      modalPreview.classList.remove('hidden');
      modalPreview.classList.add('open');
      modalPreview.style.display = '';
    }
    document.body.style.overflow = 'hidden';
  }

  function closeModalPaperPreview() {
    if (modalPreview) {
      modalPreview.classList.remove('open');
      modalPreview.classList.add('hidden');
      modalPreview.style.display = '';
    }
    if (modalTarget) {
      modalTarget.innerHTML = ''; // Safely release cloned elements from DOM memory
    }
    document.body.style.overflow = '';
  }

  window.closeModalPaperPreview = closeModalPaperPreview;

  if (btnOpenModal) btnOpenModal.addEventListener('click', openModalPaperPreview);
  if (btnOpenModalBottom) btnOpenModalBottom.addEventListener('click', openModalPaperPreview);
  if (btnCloseModal) btnCloseModal.addEventListener('click', closeModalPaperPreview);
  if (modalPreview) {
    modalPreview.addEventListener('click', function(e) {
      if (e.target === modalPreview) {
        closeModalPaperPreview();
      }
    });
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modalPreview && modalPreview.classList.contains('open')) {
      closeModalPaperPreview();
    }
  });

  // Print helper (Synchronizes live preview and triggers clean print)
  window.printLiveDocument = function() {
    syncLivePreview();
    updateLiveQrCode();
    window.print();
  };

  // High-fidelity Client-Side PDF Downloader (html2pdf A4 Retina 300 DPI)
  window.downloadBraPdf = function(targetElementId = 'braLivePaperPreview') {
    syncLivePreview();
    updateLiveQrCode();

    let el = document.getElementById(targetElementId);
    const modalCloned = document.getElementById('modalClonedPaperPreview');
    if (modalPreview && modalPreview.classList.contains('open') && modalCloned) {
      el = modalCloned;
    }
    if (!el) el = document.getElementById('braLivePaperPreview');
    if (!el) return;

    const noBraInput = document.getElementById('noBraInput');
    let noBra = (noBraInput ? noBraInput.value.trim() : '') || 'DRAF';
    noBra = noBra.replace(/[^a-zA-Z0-9_\-]/g, '_');
    const filename = `BRA-SAR-${noBra}.pdf`;

    if (typeof html2pdf === 'undefined') {
      if (typeof showToast === 'function') {
        showToast('Modul generator PDF sedang dimuat...', 'info');
      }
      return;
    }

    const prevTransform = el.style.transform;
    const prevBorder = el.style.border;
    const prevBoxShadow = el.style.boxShadow;
    const prevPadding = el.style.padding;
    const prevWidth = el.style.width;
    const prevMaxWidth = el.style.maxWidth;
    const prevBoxSizing = el.style.boxSizing;

    // Standardize to exact printable A4 width (180mm = 680px at 96 DPI)
    // with 15mm (1.5 cm) margins on all 4 sides.
    // 680px * (267 / 180) = 1008.66px, exactly matching html2pdf inner.px.height (1009px).
    // Eliminates pagebreak drift to 0px across all pages and prevents text cuts in CCC and DDD.
    el.style.width = '680px';
    el.style.maxWidth = '680px';
    el.style.boxSizing = 'border-box';
    el.style.transform = 'none';
    el.style.border = 'none';
    el.style.boxShadow = 'none';
    // 4px right safety padding ensures the 2px right border is 100% inside canvas
    el.style.padding = '0 4px 0 0';

    const opt = {
      margin: [5, 15, 15, 15],
      filename: filename,
      image: { type: 'jpeg', quality: 0.85 },
      html2canvas: {
        scale: 2,
        useCORS: true,
        letterRendering: true,
        scrollX: 0,
        scrollY: 0,
        backgroundColor: '#ffffff'
      },
      jsPDF: {
        unit: 'mm',
        format: 'a4',
        orientation: 'portrait',
        compress: true
      },
      pagebreak: {
        mode: ['css', 'legacy'],
        avoid: ['.bra-text-line', '.bra-kop', '.bra-top-header', '.bra-footer-section']
      }
    };

    if (typeof showToast === 'function') {
      showToast('Memproses berkas PDF resmi Basarnas...', 'info');
    }

    const restoreStyles = function() {
      el.style.transform = prevTransform;
      el.style.border = prevBorder;
      el.style.boxShadow = prevBoxShadow;
      el.style.padding = prevPadding;
      el.style.width = prevWidth;
      el.style.maxWidth = prevMaxWidth;
      el.style.boxSizing = prevBoxSizing;
    };

    html2pdf().set(opt).from(el).save().then(function() {
      restoreStyles();
      if (typeof showToast === 'function') {
        showToast('Dokumen PDF resmi berhasil diunduh!', 'success');
      }
    }).catch(function(err) {
      restoreStyles();
      console.error('PDF generation error:', err);
      if (typeof showToast === 'function') {
        showToast('Gagal membuat PDF: ' + err.message, 'error');
      }
    });
  };

  // Form submission validation & auto-formatting
  form.addEventListener('submit', function(e) {
    // 1. Otomatis jadikan uppercase semua input dan textarea
    form.querySelectorAll('input.upper, input.bra-control, textarea.bra-control').forEach(el => {
      if (el.value) {
        el.value = el.value.toUpperCase();
      }
    });

    // 2. Otomatis sanitasi perihal dengan akhiran TTK
    const perihalEl = document.getElementById('perihalInput');
    if (perihalEl && perihalEl.value.trim()) {
      perihalEl.value = perihalText(perihalEl.value);
    }

    // 3. Otomatis sanitasi seksi berita
    sectionsContainer.querySelectorAll('.sec-content-textarea').forEach(ta => {
      if (ta.value.trim()) {
        ta.value = BT.formatSectionText(ta.value);
      }
    });

    calculateWordCount();
    const noBra = document.getElementById('noBraInput').value.trim();
    if (!noBra) {
      e.preventDefault();
      if (typeof showToast === 'function') showToast('Nomor Dokumen BRA wajib diisi.', 'error');
      document.getElementById('noBraInput').focus();
      return;
    }
    const emptySec = Array.from(sectionsContainer.querySelectorAll('.sec-content-textarea')).some(ta => !ta.value.trim());
    if (emptySec) {
      e.preventDefault();
      if (typeof showToast === 'function') showToast('Semua bagian telegram wajib diisi teksnya.', 'warning');
      return;
    }
  });

  // Initial run
  reindexSections();
  syncLivePreview();
  updateLiveQrCode();
  // QR digambar ulang setelah semua aset (pustaka QRCode) termuat
  window.addEventListener('load', updateLiveQrCode);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initBraForm);
} else {
  initBraForm();
}
