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
  const vesselPresets = document.getElementById('vesselPresetsContainer');
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
  function getMilitaryTW(dateObj) {
    const d = dateObj || new Date();
    // Compute in WITA (UTC+8)
    const utc = d.getTime() + d.getTimezoneOffset() * 60000;
    const wita = new Date(utc + 8 * 3600000);
    const mm = String(wita.getMonth() + 1).padStart(2, '0');
    const dd = String(wita.getDate()).padStart(2, '0');
    const hh = String(wita.getHours()).padStart(2, '0');
    const min = String(wita.getMinutes()).padStart(2, '0');
    return `${mm}${dd} ${hh}${min} H`;
  }

  // Section letter helper (0 -> AAA, 1 -> BBB, ...)
  function getSectionLetter(idx) {
    const char = String.fromCharCode(65 + (idx % 26));
    return char.repeat(3);
  }

  function getSectionHint(idx, kat) {
    if (idx === 0) return kat === 'PERGERAKAN_KAPAL' ? '(Dasar & Rencana Pelayaran / BKO)' : '(Kronologi / Informasi Awal)';
    if (idx === 1) return kat === 'MAPEL' ? '(Permintaan Mapel / Pemantauan Kapal)' : (kat === 'PERGERAKAN_KAPAL' ? '(Data Kapal, Ren Layar, POB & Palsar)' : '(Unsur SAR Terlibat, Alut, Palsar & Cuaca)');
    if (idx === 2) return kat === 'PERGERAKAN_KAPAL' ? '(Laporan Perkembangan Menyusul / Penutup)' : '(Penutup / Rencana Tindak Lanjut)';
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
      if (ta) { ta.name = `sections[${idx}][isi]`; ta.placeholder = `Isi naskah telegram untuk bagian ${code}...`; }
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
            if (l.length > 140 && /\bTTK\b/i.test(l)) {
              const parts = l.split(/(\bTTK\b\s*)/i);
              let chunk = '';
              for (let i = 0; i < parts.length; i++) {
                chunk += parts[i];
                if (parts[i].trim().toUpperCase() === 'TTK' || i === parts.length - 1) {
                  const t = chunk.trim();
                  if (t) textLines.push(t);
                  chunk = '';
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

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Bind live sync to all form inputs
  const liveSyncInputs = form.querySelectorAll('input, textarea, select');
  liveSyncInputs.forEach(el => {
    el.addEventListener('input', syncLivePreview);
    el.addEventListener('change', syncLivePreview);
  });

  // Buttons for Waktu Sekarang
  document.getElementById('btnSetTwbNow').addEventListener('click', function() {
    document.getElementById('twbInput').value = getMilitaryTW(new Date(Date.now() - 15 * 60000));
    syncLivePreview();
  });
  document.getElementById('btnSetTwuNow').addEventListener('click', function() {
    document.getElementById('twuInput').value = getMilitaryTW(new Date());
    syncLivePreview();
  });

  // Track Auto vs Custom Nomor Naskah
  const noBraInput = document.getElementById('noBraInput');
  const isAutoNoBra = document.getElementById('isAutoNoBra');
  const initialNoBraPlaceholder = braFormCfg.noBraPlaceholder || '';
  if (noBraInput && isAutoNoBra) {
    noBraInput.addEventListener('input', function() {
      const val = this.value.trim().toUpperCase();
      if (!val || val === initialNoBraPlaceholder) {
        isAutoNoBra.value = '1';
      } else {
        isAutoNoBra.value = '0';
      }
    });
  }

  // Format Sandi Telegram (Kapital & TTK/KMA/TTK DUA)
  document.getElementById('btnFormatTelegramPunctuation').addEventListener('click', function() {
    form.querySelectorAll('textarea, input[type="text"]').forEach(el => {
      if (['noBraInput','menbraInput','twuInput','twbInput','twtInput','twkInput','sistemInput','parafInput','panggilanInput','nrStnInput'].includes(el.id)) {
        el.value = el.value.toUpperCase();
        return;
      }
      let val = el.value.toUpperCase();
      val = val.replace(/\.\s+/g, ' TTK ').replace(/\,\s+/g, ' KMA ').replace(/\:\s+/g, ' TTK DUA ');
      el.value = val;
    });
    syncLivePreview();
    if (typeof showToast === 'function') showToast('Naskah berhasil diformat ke Huruf Kapital Baku Telegram!', 'success');
  });

  // Category switch handler
  kategoriRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const kat = this.value;
      const menbraTitle = document.getElementById('labelMenbraTitle');
      const menbraInput = document.getElementById('menbraInput');
      const hintMenbra = document.getElementById('hintMenbraText');

      if (kat === 'PERGERAKAN_KAPAL') {
        vesselPresets.classList.remove('hidden');
        if (menbraTitle) menbraTitle.textContent = 'MENBRA (Rujukan Naskah Lalu)';
        if (menbraInput) {
          menbraInput.placeholder = 'Nomor naskah pergerakan kapal sebelumnya (opsional)';
          if (menbraInput.value.trim().toUpperCase() === 'SEGERA' || menbraInput.value.trim() === '-') menbraInput.value = '';
        }
        if (hintMenbra) hintMenbra.textContent = 'Nomor naskah telegram pergerakan kapal sebelumnya bila merupakan pergerakan lanjutan.';
        const vesselSel = document.getElementById('selectVesselChoice');
        const defaultCallSign = vesselSel ? (VESSEL_SPECS[vesselSel.value]?.callSign || 'YCSB') : 'YCSB';
        const panggilEl = document.getElementById('panggilanInput');
        if (panggilEl && (!panggilEl.value || panggilEl.value === '—' || panggilEl.value === '-')) {
          panggilEl.value = defaultCallSign;
        }
      } else if (kat === 'MAPEL') {
        vesselPresets.classList.add('hidden');
        if (menbraTitle) menbraTitle.textContent = 'MENBRA (Rujukan BRA/MAPEL Lalu)';
        if (menbraInput) {
          menbraInput.placeholder = 'Nomor MAPEL/BRA rujukan sebelumnya (opsional)';
          if (menbraInput.value.trim().toUpperCase() === 'SEGERA' || menbraInput.value.trim() === '-') menbraInput.value = '';
        }
        if (hintMenbra) hintMenbra.textContent = 'Nomor naskah telegram/MAPEL sebelumnya yang menjadi dasar rujukan bila ada lanjutan.';
      } else {
        vesselPresets.classList.add('hidden');
        if (menbraTitle) menbraTitle.textContent = 'MENBRA (Rujukan BRA Lalu)';
        if (menbraInput) {
          menbraInput.placeholder = (Math.max(1, (braFormCfg.nomorUrutPlaceholder || 1) - 1)) + '/' + (braFormCfg.satkerKode || 'SAR-304') + '/' + (braFormCfg.bulanTahunPlaceholder || '');
          if (menbraInput.value.trim().toUpperCase() === 'SEGERA' || menbraInput.value.trim() === '-') menbraInput.value = '';
        }
        if (hintMenbra) hintMenbra.textContent = 'Nomor naskah BRA sebelumnya yang menjadi dasar rujukan (bila ada).';
      }

      reindexSections();
    });
  });

  // Operasi Select handler
  opSelect.addEventListener('change', function() {
    const opId = this.value;
    const opt = this.options[this.selectedIndex];
    const hiddenNamaOperasi = document.getElementById('hiddenNamaOperasi');

    if (opId) {
      if (hiddenNamaOperasi) {
        hiddenNamaOperasi.value = opt.getAttribute('data-nama') || opt.text.split(' • ')[1]?.split(' (Status')[0]?.trim() || '';
      }
      opRecBar.classList.remove('hidden');
      document.getElementById('jabatanSmcInput').value = 'SMC';

      // Otomatis aktifkan radio SAR_UMUM jika sebelumnya bukan kapal
      const currentKat = getSelectedKategori();
      if (currentKat !== 'PERGERAKAN_KAPAL') {
        const radSar = document.querySelector('input[name="kategori"][value="SAR_UMUM"]');
        if (radSar && !radSar.checked) {
          radSar.checked = true;
          radSar.dispatchEvent(new Event('change'));
        }
      }
    } else {
      if (hiddenNamaOperasi) hiddenNamaOperasi.value = '';
      opRecBar.classList.add('hidden');
      document.getElementById('jabatanSmcInput').value = 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';
    }
    syncLivePreview();
  });

  // Pejabat Select Auto-fill
  if (pejabatSelect) {
    pejabatSelect.addEventListener('change', function() {
      const opt = this.options[this.selectedIndex];
      if (opt && opt.value) {
        document.getElementById('namaSmcInput').value = opt.getAttribute('data-nama') || '';
        document.getElementById('pangkatSmcInput').value = opt.getAttribute('data-pangkat') || '';
        const isOps = opSelect && opSelect.value;
        document.getElementById('jabatanSmcInput').value = isOps ? 'SMC' : (opt.getAttribute('data-jabatan') || 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI');
        syncLivePreview();
      }
    });
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

  function setSectionsContent(sectionsArr) {
    sectionsContainer.innerHTML = '';
    sectionsArr.forEach((s, idx) => {
      const div = document.createElement('div');
      div.className = 'sec-item section-block';
      div.setAttribute('data-index', idx);
      div.innerHTML = `
        <div class="sec-item-header">
          <div class="flex items-center gap-2">
            <span class="sec-code-badge">${s.kode}</span>
            <input type="hidden" name="sections[${idx}][kode]" value="${s.kode}" class="sec-code-input">
            <span class="sec-hint-title sec-hint-label">${getSectionHint(idx, getSelectedKategori())}</span>
          </div>
          ${idx >= 3 ? `<button type="button" class="sec-del-btn btn-delete-section" title="Hapus bagian ini"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg><span>Hapus</span></button>` : ''}
        </div>
        <textarea name="sections[${idx}][isi]" rows="4" class="bra-control sec-content-textarea upper" placeholder="Isi naskah telegram untuk bagian ${s.kode}..." required>${s.isi}</textarea>
      `;
      sectionsContainer.appendChild(div);
    });
    reindexSections();
  }

  // 1. Rekomendasi: Laporan Awal (Inisiasi)
  document.getElementById('btnAutoInisiasi').addEventListener('click', async function() {
    const opId = opSelect.value;
    if (!opId) {
      if (typeof showToast === 'function') showToast('Pilih Operasi SAR pada dropdown terlebih dahulu.', 'warning');
      return;
    }
    const op = await loadOperasiData(opId);
    if (!op) return;

    const twb = document.getElementById('twbInput').value || getMilitaryTW(new Date(Date.now() - 15 * 60000));
    const twu = document.getElementById('twuInput').value || getMilitaryTW(new Date());

    const pelapor = (op.nama_pelapor || 'BAPAK POLSEK').toUpperCase();
    const hubungan = (op.hubungan_pelapor || 'MASYARAKAT').toUpperCase();
    const noHp = op.no_telp_pelapor ? `HP ${op.no_telp_pelapor}` : 'HP. -';
    const infoPelapor = `${pelapor} (${hubungan} / ${noHp})`;
    const namaOps = (op.nama_operasi || 'OPERASI SAR').toUpperCase();
    const lokasi = (op.lokasi_kejadian || 'PERAIRAN SULTRA').toUpperCase();
    const koordinat = (op.koordinat || '03° 58\' 30" S - 122° 35\' 12" E').toUpperCase();
    const timBerangkat = (op.tim_berangkat || 'TIM RESCUE KANSAR KENDARI').toUpperCase();

    document.getElementById('perihalInput').value = `LAPORAN AWAL OPERASI SAR TERHADAP ${namaOps} TTK`;
    document.getElementById('aksiInput').value = `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`;
    document.getElementById('infoInput').value = `DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN`;
    document.getElementById('derajatInput').value = 'SGR';

    setSectionsContent([
      { kode: 'AAA', isi: `PADA TW ${twb} TERIMA INFO DARI ${infoPelapor} BAHWA TELAH TERJADI ${namaOps} DI ${lokasi} PADA KOORDINAT ${koordinat} TTK` },
      { kode: 'BBB', isi: `TERSEBUT AAA DIATAS MAKA PADA TW ${twu} ${timBerangkat} DIBERANGKATKAN MENUJU LKP DENGAN MENGGUNAKAN ${(op.alut_palsar || '1 UNIT RESCUE CAR DAN 1 UNIT RUBBER BOAT').toUpperCase().replace(/\n/g, ' KMA ')} DENGAN ESTIMASI WAKTU TEMPUH ${op.eta_lokasi || 'SEGERA'} TTK` },
      { kode: 'CCC', isi: `UNSUR SAR TERLIBAT TTK DUA\n${(op.unsur_terlibat || '* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG').toUpperCase()}\n\nALUT DAN PALSAR TTK DUA\n${(op.alut_palsar || '• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT').toUpperCase()}\n\nKONDISI CUACA TTK DUA\n• CUACA : ${(op.cuaca || 'CERAH BERAWAN').toUpperCase()}\n• ANGIN : ${(op.angin || 'TIMUR LAUT - TENGGARA, 5-15 KNOTS').toUpperCase()}\n• GELOMBANG : ${(op.gelombang || '0.5 - 1.25 METER').toUpperCase()}` },
      { kode: 'DDD', isi: `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast('Rekomendasi Naskah Laporan Awal berhasil dimuat!', 'success');
  });

  // 2. Rekomendasi: Renops Terakhir
  document.getElementById('btnAutoRenops').addEventListener('click', async function() {
    const opId = opSelect.value;
    if (!opId) return;
    const op = await loadOperasiData(opId);
    if (!op || !op.renops || op.renops.length === 0) {
      if (typeof showToast === 'function') showToast('Belum ada data Rencana Operasi (Renops) pada operasi ini.', 'warning');
      return;
    }
    const r = op.renops[op.renops.length - 1];
    const hariKe = (r.hari_ke || 'H.1').toUpperCase();
    const namaOps = (op.nama_operasi || 'OPERASI SAR').toUpperCase();

    document.getElementById('perihalInput').value = `RENCANA OPERASI SAR ${hariKe} ${namaOps} TTK`;
    document.getElementById('aksiInput').value = `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`;
    document.getElementById('infoInput').value = `DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN`;

    let sruText = '';
    if (r.srus && Array.isArray(r.srus) && r.srus.length > 0) {
      sruText = r.srus.map(s => `* ${s.nama_sru.toUpperCase()} DENGAN POLA ${s.pola_pencarian.toUpperCase()} LUAS ${s.luas_area.toUpperCase()} AREA: ${s.koordinat_area.replace(/\n/g, ' / ')}`).join('\n');
    } else {
      sruText = `* SRU 1: PENYISIRAN PARALLEL TRACK SEARCH DISEPUTARAN LKP\n* SRU 2: PENYISIRAN PESISIR PANTAI`;
    }

    setSectionsContent([
      { kode: 'AAA', isi: `PADA TW ${r.waktu_briefing || document.getElementById('twbInput').value} TIM SAR GABUNGAN MELAKSANAKAN BRIEFING PEMBAGIAN SEKTOR PENCARIAN RENCANA OPERASI SAR ${hariKe} TERHADAP ${namaOps} DENGAN WAKTU MULAI PERGERAKAN TW ${r.waktu_mulai_ops || document.getElementById('twuInput').value} TTK` },
      { kode: 'BBB', isi: `RENCANA PEMBAGIAN SRU TTK DUA\n${sruText}\n\nKONDISI METEOROLOGI / CUACA TTK DUA\n• CUACA : ${(r.cuaca || op.cuaca || 'CERAH BERAWAN').toUpperCase()}` },
      { kode: 'CCC', isi: `UNSUR SAR TERLIBAT TTK DUA\n${(r.unsur_text || op.unsur_terlibat || '* RESCUER KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG').toUpperCase()}\n\nALUT DAN PALSAR TTK DUA\n${(r.alut_text || op.alut_palsar || '• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT').toUpperCase()}` },
      { kode: 'DDD', isi: `DMKN SBG RENCANA OPERASI ${hariKe} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast(`Rekomendasi Rencana Operasi ${hariKe} berhasil dimuat!`, 'success');
  });

  // 3. Rekomendasi: SITREP (Lapbang)
  document.getElementById('btnAutoSitrep').addEventListener('click', async function() {
    const opId = opSelect.value;
    if (!opId) return;
    const op = await loadOperasiData(opId);
    if (!op || !op.laporan_harian || op.laporan_harian.length === 0) {
      if (typeof showToast === 'function') showToast('Belum ada data Laporan Perkembangan (SITREP) pada operasi ini.', 'warning');
      return;
    }
    const sit = op.laporan_harian[op.laporan_harian.length - 1];
    const hariKe = (sit.hari_ke || 'H.1').toUpperCase();
    const namaOps = (op.nama_operasi || 'OPERASI SAR').toUpperCase();

    document.getElementById('perihalInput').value = `LAPORAN PERKEMBANGAN ${hariKe} OPERASI SAR ${namaOps} TTK`;
    document.getElementById('aksiInput').value = `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`;
    document.getElementById('infoInput').value = `DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN`;

    // Compute victim stats
    const victims = op.korban || [];
    let sCount = victims.filter(k => (k.status_korban || '').toUpperCase() === 'S').length;
    let mdCount = victims.filter(k => (k.status_korban || '').toUpperCase() === 'MD').length;
    let dpCount = victims.filter(k => !['S', 'MD'].includes((k.status_korban || '').toUpperCase())).length;

    setSectionsContent([
      { kode: 'AAA', isi: `PADA TW ${sit.waktu_laporan || document.getElementById('twuInput').value} TERIMA INFO DARI OSC TIM SAR GABUNGAN BAHWA ${(sit.isi_laporan || 'PENCARIAN TELAH DILAKSANAKAN SESUAI RENOPS DENGAN HASIL KORBAN BELUM DITEMUKAN (NIHIL)').toUpperCase()} TTK` },
      { kode: 'BBB', isi: `ALUT DAN PALSAR DIKERAHKAN TTK DUA\n${(sit.alut_text || op.alut_palsar || '• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT').toUpperCase()}` },
      { kode: 'CCC', isi: `UNSUR SAR TERLIBAT TTK DUA\n${(sit.unsur_text || op.unsur_terlibat || '* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG').toUpperCase()}` },
      { kode: 'DDD', isi: `DATA REKAPITULASI KORBAN TTK DUA\n* KORBAN SELAMAT : ${sCount} ORANG\n* KORBAN MENINGGAL DUNIA : ${mdCount} ORANG\n* KORBAN DALAM PENCARIAN (DP) : ${dpCount} ORANG\n* TOTAL POB : ${op.jumlah_pob || (sCount + mdCount + dpCount)} ORANG` },
      { kode: 'EEE', isi: `KONDISI CUACA TTK DUA\n• CUACA : ${(sit.cuaca || op.cuaca || 'CERAH BERAWAN').toUpperCase()}\n• GELOMBANG : ${(op.gelombang || '0.5 - 1.25 METER').toUpperCase()}\n\nPADA TW ${document.getElementById('twuInput').value} PENCARIAN DIHENTIKAN SEMENTARA DENGAN HASIL NIHIL KMA OPERASI AKAN DILANJUTKAN KEMBALI BESOK PAGI PADA TW 0700 H TTK` },
      { kode: 'FFF', isi: `DMKN SBG LAPORAN PERKEMBANGAN ${hariKe} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast(`Rekomendasi Lapbang ${hariKe} berhasil dimuat!`, 'success');
  });

  // 4. Rekomendasi: Penutupan Operasi
  document.getElementById('btnAutoPenutupan').addEventListener('click', async function() {
    const opId = opSelect.value;
    if (!opId) return;
    const op = await loadOperasiData(opId);
    if (!op) return;

    const namaOps = (op.nama_operasi || 'OPERASI SAR').toUpperCase();
    document.getElementById('perihalInput').value = `LAPORAN PERKEMBANGAN DAN PENUTUPAN OPERASI SAR TERHADAP ${namaOps} TTK`;
    document.getElementById('aksiInput').value = `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`;
    document.getElementById('infoInput').value = `DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN KMA GUBERNUR SULTRA`;

    const victims = op.korban || [];
    let sCount = victims.filter(k => (k.status_korban || '').toUpperCase() === 'S').length;
    let mdCount = victims.filter(k => (k.status_korban || '').toUpperCase() === 'MD').length;
    let dpCount = victims.filter(k => !['S', 'MD'].includes((k.status_korban || '').toUpperCase())).length;

    const latestSit = (op.laporan_harian && op.laporan_harian.length > 0) ? op.laporan_harian[op.laporan_harian.length - 1] : null;

    setSectionsContent([
      { kode: 'AAA', isi: `PADA TW ${latestSit?.waktu_debrief || document.getElementById('twuInput').value} TIM SAR GABUNGAN MELAKSANAKAN DEBRIEFING DAN EVALUASI PENUTUPAN OPERASI SAR TERHADAP ${namaOps} KMA HASIL AKHIR ${(latestSit?.isi_laporan || 'SELURUH KORBAN TELAH BERHASIL DIEVAKUASI DAN OPERASI SAR DINYATAKAN SELESAI').toUpperCase()} TTK` },
      { kode: 'BBB', isi: `ALUT DAN PALSAR DIKEMBALIKAN KE PANGKALAN TTK DUA\n${(latestSit?.alut_text || op.alut_palsar || '• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT').toUpperCase()}` },
      { kode: 'CCC', isi: `UNSUR SAR TERLIBAT DIKEMBALIKAN KE KESATUAN MASING-MASING TTK DUA\n${(latestSit?.unsur_text || op.unsur_terlibat || '* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG').toUpperCase()}` },
      { kode: 'DDD', isi: `DATA REKAPITULASI KORBAN AKHIR TTK DUA\n* KORBAN SELAMAT : ${sCount} ORANG\n* KORBAN MENINGGAL DUNIA : ${mdCount} ORANG\n* KORBAN DALAM PENCARIAN (DP) : ${dpCount} ORANG\n* TOTAL POB : ${op.jumlah_pob || (sCount + mdCount + dpCount)} ORANG` },
      { kode: 'EEE', isi: `DENGAN DITEMUKANNYA SELURUH KORBAN MAKA OPERASI SAR DINYATAKAN DITUTUP KMA UCAPAN TERIMA KASIH ATAS KERJASAMANYA KEPADA SELURUH UNSUR SAR TERLIBAT TTK` },
      { kode: 'FFF', isi: `DMKN SBG LAPORAN PENUTUPAN OPERASI SAR KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast('Rekomendasi Naskah Penutupan Operasi SAR berhasil dimuat!', 'success');
  });

  // 5. Preset Alut Laut Resmi KPP Kendari (ISSUE-10): KN SAR Pacitan, Rescue Boat 210, Rescue Boat 307
  const VESSEL_SPECS = {
    'KN_SAR_PACITAN': {
      name: 'KN SAR PACITAN 102',
      shortName: 'KN SAR PACITAN',
      callSign: 'YCSB',
      loa: '59,80 METER',
      type: 'ALUMINIUM ALLOY',
      speedMax: '20 KNOT',
      speedEco: '15 KNOT',
      endurance: '48 JAM / 70%',
      basePort: 'DERMAGA UTAMA BASARNAS BUNGKUTOKO KENDARI',
      pobTotal: 40,
      abkCount: 20,
      rescuerCount: 20,
      nakhoda: 'RUSLAN, S.T.',
      palsarAlkom: 'SEA RIDER 1 UNIT (40 PK) READY KMA SEKOCI 2 UNIT READY KMA ALAT SELAM 4 SET READY KMA RADAR AIS ECDIS READY TTK'
    },
    'RB_210': {
      name: 'RESCUE BOAT 210 KENDARI',
      shortName: 'RB 210',
      callSign: 'YCSB-210',
      loa: '36 METER',
      type: 'MONOHULL FIBERGLASS',
      speedMax: '26 KNOT',
      speedEco: '18 KNOT',
      endurance: '14.500 LITER BBM / 6.000 LITER AIR TAWAR',
      basePort: 'DERMAGA UTAMA BASARNAS KENDARI',
      pobTotal: 16,
      abkCount: 12,
      rescuerCount: 4,
      nakhoda: 'JUMARDIN, S.E.',
      palsarAlkom: 'RUBBER BOAT 1 UNIT (25 PK) KMA LIFE JACKET 20 BUAH KMA ALAT SELAM 2 SET KMA VHF MARINE RADAR GPS AIS READY TTK'
    },
    'RB_307': {
      name: 'RESCUE BOAT 307 KENDARI',
      shortName: 'RB 307',
      callSign: 'YCSB-307',
      loa: '28 METER',
      type: 'MONOHULL FIBERGLASS',
      speedMax: '25 KNOT',
      speedEco: '16 KNOT',
      endurance: '12.000 LITER BBM / 4.000 LITER AIR TAWAR',
      basePort: 'DERMAGA BASARNAS KENDARI / POS SAR BAUBAU',
      pobTotal: 14,
      abkCount: 10,
      rescuerCount: 4,
      nakhoda: 'ERIK PRATAMA, S.Sos.',
      palsarAlkom: 'RUBBER BOAT 1 UNIT (25 PK) KMA LIFE JACKET 15 BUAH KMA ALAT SELAM 2 SET KMA VHF MARINE RADAR GPS AIS READY TTK'
    }
  };

  function applyVesselPreset(vesselKey, movementType = 'TOLAK') {
    const radioKapal = document.querySelector('input[name="kategori"][value="PERGERAKAN_KAPAL"]');
    if (radioKapal) {
      radioKapal.checked = true;
      radioKapal.dispatchEvent(new Event('change'));
    }

    const vKey = VESSEL_SPECS[vesselKey] ? vesselKey : 'KN_SAR_PACITAN';
    const v = VESSEL_SPECS[vKey];
    const vesselSel = document.getElementById('selectVesselChoice');
    if (vesselSel) vesselSel.value = vKey;

    const twbVal = document.getElementById('twbInput').value || getMilitaryTW(new Date());
    const panggilanInput = document.getElementById('panggilanInput');
    if (panggilanInput) panggilanInput.value = v.callSign;

    document.getElementById('aksiInput').value = 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN';
    document.getElementById('infoInput').value = 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN KMA DIREKTUR SARPRAS KMA DIREKTUR KESIAPSIAGAAN';

    let perihal = '';
    let derajat = 'SGR';
    let sections = [];

    if (movementType === 'SANDAR') {
      derajat = 'RUTIN';
      perihal = `PERGERAKAN ${v.name} TIBA DAN SANDAR DI ${v.basePort} TTK`;
      sections = [
        { kode: 'AAA', isi: `PD TW ${twbVal} KMA ${v.name} TELAH TIBA DAN SANDAR AMAN DI ${v.basePort} SETELAH MELAKSANAKAN KEGIATAN OPERASI SAR / PATROLI DI PERAIRAN SULAWESI TENGGARA TTK` },
        { kode: 'BBB', isi: `STATUS ALUT PASCA PELAYARAN TTK DUA\n• KONDISI MESIN / LAMBUNG : BAIK DAN NORMAL\n• KESIAPSIAGAAN : SIAP DITUGASKAN KEMBALI SEWAKTU-WAKTU (STANDBY PANGKALAN)\n• SISA BBM & AIR TAWAR : AMAN / CUKUP UNTUK SIAGA KEDARURATAN TTK` },
        { kode: 'CCC', isi: `SELURUH CREW DAN RESCUER LENGKAP DALAM KEADAAN SEHAT KMA DMKN SBG LAPORAN KMA NAKHODA ${v.name} KRM TTK HBS` }
      ];
    } else if (movementType === 'PANTAUAN') {
      derajat = 'SGR';
      perihal = `LAPORAN PERKEMBANGAN POSISI ${v.name} DI PERAIRAN SULAWESI TENGGARA TTK`;
      sections = [
        { kode: 'AAA', isi: `PD TW ${twbVal} KMA POSISI ${v.name} BERADA PADA KOORDINAT 03° 58' 30" S - 122° 38' 15" E KMA HALUAN 135° KMA KECEPATAN ${v.speedEco} MENUJU SEKTOR PENCARIAN / PATROLI TTK` },
        { kode: 'BBB', isi: `KONDISI METEOROLOGI DAN LAUT TTK DUA\n• CUACA : CERAH BERAWAN\n• ANGIN : TIMUR LAUT - TENGGARA, KECEPATAN 8 - 15 KNOTS\n• TINGGI GELOMBANG : 0.5 - 1.25 METER\n• JARAK PANDANG : BAIK (VISIBILITY 8 - 10 NM)\n\nKONDISI MATERIAL DAN PERSONIL TTK DUA\nSELURUH CREW DAN RESCUER LENGKAP ${v.pobTotal} ORANG DALAM KEADAAN SEHAT KMA ALUT DAN PERALATAN KAPAL BERFUNGSI NORMAL TTK` },
        { kode: 'CCC', isi: `PELAYARAN DILANJUTKAN SESUAI RENCANA OPERASI KMA NAKHODA ${v.name} KRM TTK HBS` }
      ];
    } else {
      // Default: TOLAK (Keberangkatan / Berlayar)
      derajat = 'SGR';
      perihal = `PELAYARAN ${v.name} DALAM RANGKA BKO / OPERASI SAR DI SEKITAR PERAIRAN SULAWESI TENGGARA TTK`;
      sections = [
        { kode: 'AAA', isi: `PD TW ${twbVal} KMA PERINTAH/RENCANA PELAYARAN ${v.name} DARI ${v.basePort} MENUJU LKP/AREA OPERASI DI SEKITAR PERAIRAN SULTRA DALAM RANGKA PELAKSANAAN OPERASI SAR TTK` },
        { kode: 'BBB', isi: `DATA KAPAL TTK DUA\nNAMA : ${v.name}\nCALL SIGN : ${v.callSign}\nNAKHODA : ${v.nakhoda}\nLOA : ${v.loa}\nTYPE : ${v.type}\nSPEED MAX : ${v.speedMax}\nSPEED ECO : ${v.speedEco}\nENDURANCE : ${v.endurance}\nKONDISI MESIN : BAIK / SIAP OPERASI\n\nREN LAYAR TTK DUA\nRUTE : ${v.basePort} MENUJU PERAIRAN SULAWESI TENGGARA\nESTIMASI : 4 JAM PERJALANAN\n\nMUATAN POB TTK DUA\nTOTAL POB : ${v.pobTotal} ORANG (CREW ${v.abkCount} ORANG + RESCUER ${v.rescuerCount} ORANG)\n\nDATA PALSAR ALKOM ALNAV TTK DUA\n${v.palsarAlkom}` },
        { kode: 'CCC', isi: `LAPORAN PERKEMBANGAN MENYUSUL KMA NAKHODA ${v.name} KRM TTK HBS` }
      ];
    }

    document.getElementById('derajatInput').value = derajat;
    document.getElementById('perihalInput').value = perihal;
    setSectionsContent(sections);
    syncLivePreview();
    if (typeof showToast === 'function') {
      const typeLabel = movementType === 'SANDAR' ? 'Sandar / Tiba' : (movementType === 'PANTAUAN' ? 'Pantauan Posisi' : 'Tolak / Berlayar');
      showToast(`Format ${v.shortName} (${typeLabel}) berhasil dimuat!`, 'success');
    }
  }

  // Event Listeners for Vessel Controls
  const selectVessel = document.getElementById('selectVesselChoice');
  if (selectVessel) {
    selectVessel.addEventListener('change', function() {
      const v = VESSEL_SPECS[this.value];
      const panggilEl = document.getElementById('panggilanInput');
      if (panggilEl && v) panggilEl.value = v.callSign;
    });
  }

  const btnVesselTolak = document.getElementById('btnVesselTolak');
  if (btnVesselTolak) {
    btnVesselTolak.addEventListener('click', function() {
      const vKey = selectVessel ? selectVessel.value : 'KN_SAR_PACITAN';
      applyVesselPreset(vKey, 'TOLAK');
    });
  }

  const btnVesselPantauan = document.getElementById('btnVesselPantauan');
  if (btnVesselPantauan) {
    btnVesselPantauan.addEventListener('click', function() {
      const vKey = selectVessel ? selectVessel.value : 'KN_SAR_PACITAN';
      applyVesselPreset(vKey, 'PANTAUAN');
    });
  }

  const btnVesselSandar = document.getElementById('btnVesselSandar');
  if (btnVesselSandar) {
    btnVesselSandar.addEventListener('click', function() {
      const vKey = selectVessel ? selectVessel.value : 'KN_SAR_PACITAN';
      applyVesselPreset(vKey, 'SANDAR');
    });
  }

  // Shortcuts
  const btnPacitan = document.getElementById('btnPresetPacitan');
  if (btnPacitan) {
    btnPacitan.addEventListener('click', function() {
      applyVesselPreset('KN_SAR_PACITAN', 'TOLAK');
    });
  }

  const btnRB210 = document.getElementById('btnPresetRB210');
  if (btnRB210) {
    btnRB210.addEventListener('click', function() {
      applyVesselPreset('RB_210', 'TOLAK');
    });
  }

  const btnRB307 = document.getElementById('btnPresetRB307');
  if (btnRB307) {
    btnRB307.addEventListener('click', function() {
      applyVesselPreset('RB_307', 'TOLAK');
    });
  }

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
  const splitLabel = document.getElementById('splitLabel');
  let isSplitActive = true;

  if (btnToggleSplit && appWrap) {
    appWrap.classList.add('bra-split-active');
    btnToggleSplit.addEventListener('click', function() {
      isSplitActive = !isSplitActive;
      if (isSplitActive) {
        appWrap.classList.add('bra-split-active');
        splitLabel.textContent = 'Split Pratinjau';
      } else {
        appWrap.classList.remove('bra-split-active');
        splitLabel.textContent = 'Buka Pratinjau';
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
      margin: [15, 15, 15, 15],
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

  // Form submission validation
  form.addEventListener('submit', function(e) {
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
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initBraForm);
} else {
  initBraForm();
}
window.addEventListener('load', function() {
  updateLiveQrCode();
});
