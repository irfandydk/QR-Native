<?php
/**
 * QR-SAR - Admin Tab Partial: Pengaturan Satker, Identitas & Tampilan Aplikasi
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}
$logoUrl = $logoUrl ?? getAppLogoUrl();
$uiThemeSel = (($settings['ui_theme'] ?? 'klasik') === 'minimal') ? 'minimal' : 'klasik';
$defThemeSel = ($settings['default_theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
$aiActiveDefault = $aiConfig['active_default'] ?? null;
$aiKeyCount = is_array($aiConfig['keys'] ?? null) ? count($aiConfig['keys']) : 0;
?>
<form action="<?= BASE_URL ?>/admin.php?action=save_satker" method="POST" enctype="multipart/form-data" class="space-y-5">
  <?= csrfInput() ?>

  <!-- Section 1: Identitas Satker & Logo Kop -->
  <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-5">
    <div class="pb-3.5 border-b border-slate-100 dark:border-slate-800">
      <h2 class="text-sm font-bold text-slate-900 dark:text-white">Identitas Satker &amp; Kop Dokumen</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Konfigurasi logo, kop surat resmi BRA, RENOPS, dan komunikasi radio.</p>
    </div>

    <!-- 3-Column Institutional Logo Upload (Utama, SAR Nasional, ANOA Rescue) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
      <!-- Logo 1: Logo Utama Satker -->
      <div class="flex items-start gap-3.5">
        <div class="w-16 h-16 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-center p-2 shrink-0">
          <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" class="max-w-full max-h-full object-contain" id="satkerLogoPreview" />
        </div>
        <div class="flex-1 min-w-0 space-y-2">
          <div>
            <p class="text-xs font-bold text-slate-900 dark:text-white">Logo Utama Kantor SAR</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">PNG, SVG, JPG, atau WebP transparan.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <label class="adm-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-xs font-semibold cursor-pointer transition shrink-0">
              <span>Pilih Berkas</span>
              <input type="file" name="logo_file" accept="image/*" class="hidden" onchange="previewLogoFile(this)" />
            </label>
            <input type="text" name="logo_url" value="<?= e($settings['logo_url'] ?? '') ?>" placeholder="Atau tempel URL logo..." aria-label="URL logo utama" class="control control-sm flex-1 min-w-[120px]" />
          </div>
        </div>
      </div>

      <!-- Logo 2: Logo Kedua Kop Dokumen (Kanan) -->
      <div class="flex items-start gap-3.5 lg:pl-4 lg:border-l lg:border-slate-100 dark:lg:border-slate-800">
        <div class="w-16 h-16 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-center p-2 shrink-0">
          <img src="<?= e(getAppLogo2DataUri()) ?>" alt="Logo kedua kop dokumen" class="max-w-full max-h-full object-contain" id="satkerLogo2Preview" />
        </div>
        <div class="flex-1 min-w-0 space-y-2">
          <div>
            <p class="text-xs font-bold text-slate-900 dark:text-white">Logo Kedua (SAR Nasional)</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Kosongkan untuk memakai logo bawaan.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <label class="adm-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-xs font-semibold cursor-pointer transition shrink-0">
              <span>Pilih Berkas</span>
              <input type="file" name="logo_file_2" accept="image/*" class="hidden" onchange="previewLogoFile(this, 'satkerLogo2Preview')" />
            </label>
            <input type="text" name="logo_url_2" value="<?= e($settings['logo_url_2'] ?? '') ?>" placeholder="Atau tempel URL logo kedua..." aria-label="URL logo kedua" class="control control-sm flex-1 min-w-[120px]" />
          </div>
        </div>
      </div>

      <!-- Logo 3: Logo Ketiga (ANOA Rescue / Dokumentasi Operasi SAR) -->
      <div class="flex items-start gap-3.5 lg:pl-4 lg:border-l lg:border-slate-100 dark:lg:border-slate-800">
        <div class="w-16 h-16 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-center p-2 shrink-0">
          <img src="<?= e(getAppLogo3DataUri()) ?>" alt="Logo ANOA Rescue" class="max-w-full max-h-full object-contain" id="satkerLogo3Preview" />
        </div>
        <div class="flex-1 min-w-0 space-y-2">
          <div>
            <p class="text-xs font-bold text-slate-900 dark:text-white">Logo Ketiga (ANOA Rescue)</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Referensi logo dokumentasi kolase SAR.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <label class="adm-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-xs font-semibold cursor-pointer transition shrink-0">
              <span>Pilih Berkas</span>
              <input type="file" name="logo_file_3" accept="image/*" class="hidden" onchange="previewLogoFile(this, 'satkerLogo3Preview')" />
            </label>
            <input type="text" name="logo_url_3" value="<?= e($settings['logo_url_3'] ?? '') ?>" placeholder="Atau tempel URL logo ketiga..." aria-label="URL logo ketiga" class="control control-sm flex-1 min-w-[120px]" />
          </div>
        </div>
      </div>
    </div>

    <!-- Satker Identity Fields -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="field">
        <label class="field-label">Kode Satuan Kerja (Satker) <span class="text-rose-500">*</span></label>
        <input class="field-input w-full font-mono font-bold" type="text" name="satker_kode" value="<?= e($settings['satker_kode'] ?? 'SAR-304') ?>" required placeholder="SAR-304" />
      </div>

      <div class="field">
        <label class="field-label">Call Sign Radio Kantor</label>
        <input class="field-input w-full font-mono font-bold" type="text" name="call_sign" value="<?= e($settings['call_sign'] ?? 'KANSAR KENDARI') ?>" placeholder="KANSAR KENDARI" />
      </div>

      <div class="sm:col-span-2 field">
        <label class="field-label">Nama Satuan Kerja Lengkap <span class="text-rose-500">*</span></label>
        <input class="field-input w-full font-bold" type="text" name="satker_nama" value="<?= e($settings['satker_nama'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI') ?>" required placeholder="KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI" />
      </div>

      <div class="sm:col-span-2 field">
        <label class="field-label">Alamat Kantor &amp; Wilayah Kerja</label>
        <textarea name="alamat" rows="2" placeholder="Alamat lengkap kantor..." class="field-input w-full"><?= e($settings['alamat'] ?? 'Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan, Sulawesi Tenggara') ?></textarea>
      </div>

      <div class="field">
        <label class="field-label" for="satkerNoTelp">Telepon Kantor / Emergency Call Center</label>
        <input id="satkerNoTelp" class="field-input w-full" type="text" name="no_telp" value="<?= e($settings['no_telp'] ?? '0401 3196 557') ?>" placeholder="0401 3196 557" />
      </div>

      <div class="field">
        <label class="field-label" for="satkerNoWa">Nomor WhatsApp Kantor / Siaga (WA)</label>
        <input id="satkerNoWa" class="field-input w-full font-mono" type="text" name="no_wa" value="<?= e($settings['no_wa'] ?? '') ?>" placeholder="08114034115" />
      </div>

      <div class="field">
        <label class="field-label">Email Resmi Kantor SAR</label>
        <input class="field-input w-full" type="email" name="email" value="<?= e($settings['email'] ?? 'sar_kendari@basarnas.go.id') ?>" placeholder="sar_kendari@basarnas.go.id" />
      </div>

      <!-- Ringkasan Konfigurasi Asisten AI (Multi-API Key di Tab Asisten AI) -->
      <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50 p-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
          <div class="text-xs font-bold text-slate-800 dark:text-slate-100">Asisten AI (Laporan Awal &amp; LAPBANG)</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
            <?php if (is_array($aiActiveDefault)): ?>
              Default: <span class="font-semibold text-slate-700 dark:text-slate-200"><?= e((string)$aiActiveDefault['provider_label']) ?></span>
              (<span class="font-mono"><?= e((string)$aiActiveDefault['model']) ?></span>) &bull; <?= $aiKeyCount ?> kunci
            <?php else: ?>
              Belum ada API Key diatur (Gemini / DeepSeek)
            <?php endif; ?>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/admin.php?tab=ai" class="adm-ghost-btn inline-flex items-center h-8 px-3 rounded-lg text-xs font-semibold transition shrink-0 no-underline">
          Kelola AI
        </a>
      </div>
    </div>
  </section>

  <!-- Section 2: Tampilan & Tema Sistem -->
  <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-5">
    <div class="pb-3.5 border-b border-slate-100 dark:border-slate-800">
      <h2 class="text-sm font-bold text-slate-900 dark:text-white">Tampilan &amp; Tema Sistem</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Berlaku global di seluruh halaman tanpa mengubah data maupun alur kerja.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Gaya Tampilan Aplikasi -->
      <div class="space-y-2.5">
        <div class="text-xs font-bold text-slate-900 dark:text-white">Gaya Antarmuka</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
          <?php foreach ([
            'klasik' => ['Klasik', 'Standar QR-SAR dengan 4 palet aksen.', 'linear-gradient(135deg,#f8fafc 0%,#f8fafc 60%,#ea580c 60%)'],
            'minimal' => ['Minimal Glass', 'Minimalis, font Inter, sidebar kaca.', 'linear-gradient(135deg,rgba(255,255,255,.9) 0%,#eef0f3 55%,#ea6a1f 55%)'],
          ] as $val => [$nama, $desc, $swatch]): ?>
          <label class="adm-radio-card flex items-center gap-3 p-3 rounded-xl cursor-pointer" data-selected="<?= $uiThemeSel === $val ? 'true' : 'false' ?>">
            <input type="radio" name="ui_theme" value="<?= $val ?>" <?= $uiThemeSel === $val ? 'checked' : '' ?> onchange="syncRadioCards(this)">
            <span class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 shrink-0" style="background: <?= $swatch ?>;" aria-hidden="true"></span>
            <span class="min-w-0">
              <span class="block text-xs font-bold text-slate-900 dark:text-white"><?= $nama ?></span>
              <span class="block text-[11px] text-slate-500 dark:text-slate-400 leading-snug mt-0.5"><?= $desc ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Mode Warna Global -->
      <div class="space-y-2.5">
        <div class="text-xs font-bold text-slate-900 dark:text-white">Mode Warna Standar</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
          <label class="adm-radio-card flex items-center gap-3 p-3 rounded-xl cursor-pointer" data-selected="<?= $defThemeSel === 'dark' ? 'true' : 'false' ?>">
            <input type="radio" name="default_theme" value="dark" <?= $defThemeSel === 'dark' ? 'checked' : '' ?> onchange="syncRadioCards(this); if(window.setAppTheme) window.setAppTheme('dark', true)" />
            <div class="min-w-0">
              <div class="text-xs font-bold text-slate-900 dark:text-white">Mode Gelap</div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug mt-0.5">Nyaman untuk ruang siaga 24 jam.</p>
            </div>
          </label>

          <label class="adm-radio-card flex items-center gap-3 p-3 rounded-xl cursor-pointer" data-selected="<?= $defThemeSel === 'light' ? 'true' : 'false' ?>">
            <input type="radio" name="default_theme" value="light" <?= $defThemeSel === 'light' ? 'checked' : '' ?> onchange="syncRadioCards(this); if(window.setAppTheme) window.setAppTheme('light', true)" />
            <div class="min-w-0">
              <div class="text-xs font-bold text-slate-900 dark:text-white">Mode Terang</div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug mt-0.5">Kontras tinggi untuk siang dan cetak.</p>
            </div>
          </label>
        </div>
      </div>
    </div>

    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
      <button type="submit" class="adm-accent-btn inline-flex items-center gap-2 h-9 px-5 rounded-xl font-bold text-xs transition cursor-pointer">
        <span>Simpan Pengaturan Satker</span>
      </button>
    </div>
  </section>
</form>

<script>
function previewLogoFile(input, previewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const preview = document.getElementById(previewId || 'satkerLogoPreview');
      if (preview) {
        preview.src = e.target.result;
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function syncRadioCards(radioEl) {
  document.querySelectorAll('input[name="' + radioEl.name + '"]').forEach(function(r) {
    const card = r.closest('.adm-radio-card');
    if (card) card.setAttribute('data-selected', r.checked ? 'true' : 'false');
  });
}
</script>
