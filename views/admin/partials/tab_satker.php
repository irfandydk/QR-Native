<?php
/**
 * QR-SAR - Admin Tab Partial: Pengaturan Satker, Identitas & Aplikasi
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}
$logoUrl = $logoUrl ?? getAppLogoUrl();
?>
<div class="max-w-4xl mx-auto space-y-6">
  
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
    
    <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
      <div class="w-10 h-10 rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M8 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M16 14h.01"/></svg>
      </div>
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Identitas Satker & Konfigurasi Kop Surat</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Identitas kantor SAR untuk kop surat resmi BRA, Renops, serta komunikasi radio</p>
      </div>
    </div>

    <form action="<?= BASE_URL ?>/admin.php?action=save_satker" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
      <?= csrfInput() ?>
      
      <!-- Logo Satker Section -->
      <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 flex flex-col sm:flex-row items-center gap-5">
        <div class="w-20 h-20 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center p-2 shrink-0 shadow-xs">
          <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" class="max-w-full max-h-full object-contain" id="satkerLogoPreview" />
        </div>
        <div class="flex-1 min-w-0 space-y-2 text-center sm:text-left">
          <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Logo Resmi Kantor SAR / Basarnas</p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">Gunakan format PNG, SVG, JPG, atau WebP dengan latar belakang transparan.</p>
          
          <div class="flex flex-wrap items-center gap-2 justify-center sm:justify-start pt-1">
            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-xs font-semibold cursor-pointer shadow-xs transition-colors">
              <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              <span>Pilih Berkas Logo</span>
              <input type="file" name="logo_file" accept="image/*" class="hidden" onchange="previewLogoFile(this)" />
            </label>
            <input type="text" name="logo_url" value="<?= e($settings['logo_url'] ?? '') ?>" placeholder="Atau tempel URL gambar logo..." class="flex-1 min-w-[200px] px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        
        <!-- Kode Satker -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Kode Satuan Kerja (Satker) <span class="text-red-500">*</span>
          </label>
          <input type="text" name="satker_kode" value="<?= e($settings['satker_kode'] ?? 'SAR-304') ?>" required placeholder="SAR-304" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <!-- Call Sign Radio -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Call Sign Radio Kantor
          </label>
          <input type="text" name="call_sign" value="<?= e($settings['call_sign'] ?? 'KANSAR KENDARI') ?>" placeholder="KANSAR KENDARI" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <!-- Nama Satker Lengkap -->
        <div class="sm:col-span-2">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Nama Satuan Kerja Lengkap <span class="text-red-500">*</span>
          </label>
          <input type="text" name="satker_nama" value="<?= e($settings['satker_nama'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI') ?>" required placeholder="KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <!-- Alamat Lengkap -->
        <div class="sm:col-span-2">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Alamat Kantor & Wilayah Kerja
          </label>
          <textarea name="alamat" rows="2" placeholder="Alamat lengkap kantor..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500"><?= e($settings['alamat'] ?? 'Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan, Sulawesi Tenggara') ?></textarea>
        </div>

        <!-- No Telepon / Emergency -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Telepon Kantor / Emergency Call Center
          </label>
          <input type="text" name="no_telp" value="<?= e($settings['no_telp'] ?? '(0401) 3122115 / Emergency 115') ?>" placeholder="(0401) 3122115 / Emergency 115" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <!-- Frekuensi Default -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Frekuensi Komunikasi SAR Default
          </label>
          <input type="text" name="frekuensi_default" value="<?= e($settings['frekuensi_default'] ?? '115.000 MHz / 5827 kHz') ?>" placeholder="115.000 MHz / 5827 kHz" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <!-- Email Kantor -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Email Resmi Kantor SAR
          </label>
          <input type="email" name="email" value="<?= e($settings['email'] ?? 'sar_kendari@basarnas.go.id') ?>" placeholder="sar_kendari@basarnas.go.id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <!-- Gemini AI API Key (Versi Terbaru Gemini 2.5 Flash) -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
              Google Gemini API Key (AI Assistant)
            </label>
            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-purple-500/15 text-purple-600 dark:text-purple-300 border border-purple-500/30">
              GEMINI 2.5 FLASH
            </span>
          </div>
          <input type="password" name="gemini_api_key" value="<?= e($settings['gemini_api_key'] ?? '') ?>" placeholder="AIzaSy... (Opsional, untuk asisten narasi pintar)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <!-- Pengaturan Tema Global Sistem -->
        <div class="sm:col-span-2 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <label class="block text-xs font-bold text-slate-900 dark:text-white">
                Tema Tampilan Standar Sistem (Global)
              </label>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Pilihan tema yang berlaku secara global di seluruh modul (Dashboard, Form SAR, Ploting Peta, BRA SAR, hingga Pengaturan Admin).
              </p>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
              SINKRON OTOMATIS
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <!-- Dark Mode Option -->
            <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all <?= ($settings['default_theme'] ?? 'dark') === 'dark' ? 'border-orange-500 bg-orange-500/10' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-slate-400' ?>">
              <input type="radio" name="default_theme" value="dark" <?= ($settings['default_theme'] ?? 'dark') === 'dark' ? 'checked' : '' ?> class="text-orange-500 focus:ring-orange-500" onchange="if(window.setAppTheme) window.setAppTheme('dark', true)" />
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-900 dark:text-white">🌙 Mode Gelap (Dark Mode)</span>
                  <span class="text-[9px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 font-bold">Default</span>
                </div>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Standar taktis Basarnas untuk siaga operasional 24 jam</p>
              </div>
            </label>

            <!-- Light Mode Option -->
            <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all <?= ($settings['default_theme'] ?? 'dark') === 'light' ? 'border-orange-500 bg-orange-500/10' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-slate-400' ?>">
              <input type="radio" name="default_theme" value="light" <?= ($settings['default_theme'] ?? 'dark') === 'light' ? 'checked' : '' ?> class="text-orange-500 focus:ring-orange-500" onchange="if(window.setAppTheme) window.setAppTheme('light', true)" />
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-900 dark:text-white">☀️ Mode Terang (Light Mode)</span>
                </div>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Latar bersih kontras tinggi cocok untuk cetak dan luar ruangan</p>
              </div>
            </label>
          </div>
        </div>

      </div>

      <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-md shadow-orange-600/30 transition-all cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span>Simpan Pengaturan Satker</span>
        </button>
      </div>
    </form>

  </div>
</div>

<script>
function previewLogoFile(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const preview = document.getElementById('satkerLogoPreview');
      if (preview) {
        preview.src = e.target.result;
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
