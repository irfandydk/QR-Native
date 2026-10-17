<?php
/**
 * QR-SAR - Master Data & App Settings Hub
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$activeTab = $_GET['tab'] ?? 'alut';

$masterAlut = MasterModel::getAlut();
$masterTim = MasterModel::getTim();
$sarUnits = MasterModel::getSarUnits();
$settings = SettingsModel::get();
$pejabatList = $settings['pejabat_list'] ?? [];
$adminProfile = $settings['admin_profile'] ?? [];
?>

<div class="space-y-6">
  <!-- Header Banner -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border border-slate-700/60 shadow-lg text-white">
    <div class="flex items-center gap-3.5">
      <div class="w-12 h-12 rounded-xl bg-orange-500/20 border border-orange-500/40 flex items-center justify-center text-orange-400 shrink-0">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      </div>
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white flex items-center gap-2">
          Master Data & Pengaturan Sistem
          <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-orange-500 text-white font-bold tracking-wider">SAR-304</span>
        </h1>
        <p class="text-xs text-slate-300 mt-0.5">
          Kelola armada & palsar, unsur potensi, data pejabat penandatangan SMC, profil satker, dan cadangan basis data.
        </p>
      </div>
    </div>

    <!-- Quick SQL Export Button -->
    <a href="<?= BASE_URL ?>/api/export/sql" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 active:scale-95 rounded-xl shadow-md transition shrink-0">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
      <span>Ekspor SQL Database</span>
    </a>
  </div>

  <!-- Navigation Tabs -->
  <div class="card p-1.5 flex flex-wrap gap-1.5 bg-slate-100/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
    <a href="<?= BASE_URL ?>/settings.php?tab=alut" class="px-4 py-2 text-xs font-bold rounded-xl transition flex items-center gap-1.5 <?= $activeTab === 'alut' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>">
      <span>🚢 Armada & Alut SAR</span>
      <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $activeTab === 'alut' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300' ?>"><?= count($masterAlut) ?></span>
    </a>
    <a href="<?= BASE_URL ?>/settings.php?tab=tim" class="px-4 py-2 text-xs font-bold rounded-xl transition flex items-center gap-1.5 <?= $activeTab === 'tim' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>">
      <span>👥 Unsur Potensi SAR</span>
      <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $activeTab === 'tim' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300' ?>"><?= count($masterTim) ?></span>
    </a>
    <a href="<?= BASE_URL ?>/settings.php?tab=pejabat" class="px-4 py-2 text-xs font-bold rounded-xl transition flex items-center gap-1.5 <?= $activeTab === 'pejabat' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>">
      <span>🎖️ Pejabat & SMC</span>
      <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $activeTab === 'pejabat' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300' ?>"><?= count($pejabatList) ?></span>
    </a>
    <a href="<?= BASE_URL ?>/settings.php?tab=satker" class="px-4 py-2 text-xs font-bold rounded-xl transition flex items-center gap-1.5 <?= $activeTab === 'satker' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>">
      <span>🏢 Profil Satker & Kop</span>
    </a>
    <a href="<?= BASE_URL ?>/settings.php?tab=keamanan" class="px-4 py-2 text-xs font-bold rounded-xl transition flex items-center gap-1.5 <?= $activeTab === 'keamanan' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>">
      <span>🔒 Akun & Keamanan</span>
    </a>
    <a href="<?= BASE_URL ?>/settings.php?tab=backup" class="px-4 py-2 text-xs font-bold rounded-xl transition flex items-center gap-1.5 <?= $activeTab === 'backup' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>">
      <span>💾 Backup & Restore DB</span>
    </a>
  </div>

  <!-- TAB CONTENT -->

  <!-- TAB 1: ALUT & PALSAR -->
  <?php if ($activeTab === 'alut'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Add Alut Form -->
      <div class="card p-6 h-fit space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-slate-800">
          <div class="w-8 h-8 rounded-lg bg-orange-500/15 text-orange-500 flex items-center justify-center font-bold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">Tambah Alut / Palsar</h3>
            <p class="text-[11px] text-slate-500">Daftarkan alut baru untuk operasi pencarian</p>
          </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/settings.php?action=add_alut" class="space-y-4">
          <?= csrfInput() ?>
          
          <div class="form-group">
            <label class="form-label">Nama Alat Utama / Palsar <span class="text-red-500">*</span></label>
            <input type="text" name="nama_alut" class="input" placeholder="Contoh: KN SAR Pacitan 102 / Rescue Boat 210" required>
            <span class="text-[11px] text-slate-500">Nama lengkap kapal, kendaraan darat, atau peralatan khusus.</span>
          </div>

          <div class="form-group">
            <label class="form-label">Satuan Ukuran / Kuantitas <span class="text-red-500">*</span></label>
            <input type="text" name="satuan" class="input" placeholder="Contoh: Unit / Set / Buah / Pcs" required>
          </div>

          <div class="form-group">
            <label class="form-label">Kategori Matra <span class="text-red-500">*</span></label>
            <select name="kategori" class="input" required>
              <option value="" disabled selected>-- Pilih Kategori Matra --</option>
              <option value="Laut">🌊 Matra Laut (Kapal, RIB, Rubber Boat, Jet Ski)</option>
              <option value="Darat">🚙 Matra Darat (Rescue Car, Truck Personel, Motor Trail)</option>
              <option value="Palsar">🤿 Palsar Khusus (Aquaeye, Selam, Drone UAV, CSAR)</option>
              <option value="Komunikasi">📻 Alkom (HF Radio, VHF Marine, Direction Finder)</option>
            </select>
          </div>

          <button type="submit" class="w-full py-2.5 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 active:scale-98 rounded-xl transition shadow flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>Simpan Alut Baru</span>
          </button>
        </form>
      </div>

      <!-- Alut Table -->
      <div class="lg:col-span-2 card p-6 space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">
              Daftar Armada & Peralatan SAR
            </h3>
            <p class="text-[11px] text-slate-500">Total terdata: <?= count($masterAlut) ?> unit/set operasional</p>
          </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
          <table class="table-sar">
            <thead>
              <tr>
                <th style="width: 50px;">No</th>
                <th>Nama Alut / Palsar</th>
                <th>Satuan</th>
                <th>Kategori</th>
                <th class="text-right" style="width: 80px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($masterAlut)): ?>
                <tr>
                  <td colspan="5" class="text-center py-8 text-slate-500 text-xs">
                    Belum ada armada atau palsar terdaftar. Silakan tambahkan melalui form di samping.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($masterAlut as $i => $alut): 
                  $kat = $alut['kategori'] ?? 'Alut';
                  $badgeColor = match($kat) {
                    'Laut' => 'bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 border-sky-300 dark:border-sky-800',
                    'Darat' => 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
                    'Palsar' => 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-800',
                    'Komunikasi' => 'bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-800',
                    default => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700'
                  };
                ?>
                  <tr>
                    <td class="font-mono text-xs text-slate-400"><?= $i + 1 ?></td>
                    <td class="font-bold text-slate-800 dark:text-slate-200">
                      <?= htmlspecialchars($alut['nama_alut']) ?>
                    </td>
                    <td class="text-xs font-mono text-slate-600 dark:text-slate-400">
                      <?= htmlspecialchars($alut['satuan'] ?? 'Unit') ?>
                    </td>
                    <td>
                      <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-md border <?= $badgeColor ?>">
                        <?= htmlspecialchars($kat) ?>
                      </span>
                    </td>
                    <td class="text-right">
                      <a href="<?= BASE_URL ?>/settings.php?action=delete_alut&id=<?= $alut['id_alut'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus alut ini?');" class="inline-flex items-center gap-1 text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1 rounded-md hover:bg-red-50 dark:hover:bg-red-950/30 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>Hapus</span>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- TAB 2: TIM & POTENSI SAR -->
  <?php if ($activeTab === 'tim'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="card p-6 h-fit space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-slate-800">
          <div class="w-8 h-8 rounded-lg bg-orange-500/15 text-orange-500 flex items-center justify-center font-bold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">Tambah Unsur Potensi SAR</h3>
            <p class="text-[11px] text-slate-500">Daftarkan unsur / instansi potensi gabungan</p>
          </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/settings.php?action=add_tim" class="space-y-4">
          <?= csrfInput() ?>
          
          <div class="form-group">
            <label class="form-label">Nama Instansi / Tim Potensi <span class="text-red-500">*</span></label>
            <input type="text" name="nama_tim" class="input" placeholder="Contoh: Polairud Polda Sultra / Koramil / BPBD" required>
            <span class="text-[11px] text-slate-500">Nama resmi instansi atau kelompok relawan masyarakat.</span>
          </div>

          <div class="form-group">
            <label class="form-label">Kategori Unsur <span class="text-red-500">*</span></label>
            <select name="kategori" class="input" required>
              <option value="" disabled selected>-- Pilih Kategori Unsur --</option>
              <option value="Internal">🏢 Internal Basarnas (Rescuer Kansar / Pos SAR)</option>
              <option value="TNI">⚔️ TNI (TNI AL / TNI AD / TNI AU)</option>
              <option value="Polri">👮 POLRI (Polairud / Brimob / Samapta / Polsek)</option>
              <option value="Pemerintah">🏛️ Pemerintah Daerah (BPBD, Dinas Perhubungan, Damkar)</option>
              <option value="Masyarakat">🤝 Masyarakat & Relawan (Keluarga, Nelayan, Pramuka)</option>
            </select>
          </div>

          <button type="submit" class="w-full py-2.5 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 active:scale-98 rounded-xl transition shadow flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>Simpan Unsur Baru</span>
          </button>
        </form>
      </div>

      <div class="lg:col-span-2 card p-6 space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">
              Daftar Unsur Potensi SAR Terdaftar
            </h3>
            <p class="text-[11px] text-slate-500">Total terdata: <?= count($masterTim) ?> instansi / unsur potensi</p>
          </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
          <table class="table-sar">
            <thead>
              <tr>
                <th style="width: 50px;">No</th>
                <th>Nama Instansi / Organisasi Potensi</th>
                <th>Kategori</th>
                <th class="text-right" style="width: 80px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($masterTim)): ?>
                <tr>
                  <td colspan="4" class="text-center py-8 text-slate-500 text-xs">
                    Belum ada unsur potensi terdaftar. Silakan tambahkan melalui form di samping.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($masterTim as $i => $tim): 
                  $kat = $tim['kategori'] ?? 'Potensi';
                  $badgeColor = match($kat) {
                    'Internal' => 'bg-orange-100 dark:bg-orange-950 text-orange-700 dark:text-orange-300 border-orange-300 dark:border-orange-800',
                    'TNI' => 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
                    'Polri' => 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-800',
                    'Pemerintah' => 'bg-cyan-100 dark:bg-cyan-950 text-cyan-700 dark:text-cyan-300 border-cyan-300 dark:border-cyan-800',
                    'Masyarakat' => 'bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-800',
                    default => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700'
                  };
                ?>
                  <tr>
                    <td class="font-mono text-xs text-slate-400"><?= $i + 1 ?></td>
                    <td class="font-bold text-slate-800 dark:text-slate-200">
                      <?= htmlspecialchars($tim['nama_tim']) ?>
                    </td>
                    <td>
                      <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-md border <?= $badgeColor ?>">
                        <?= htmlspecialchars($kat) ?>
                      </span>
                    </td>
                    <td class="text-right">
                      <a href="<?= BASE_URL ?>/settings.php?action=delete_tim&id=<?= $tim['id_tim'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus unsur potensi ini?');" class="inline-flex items-center gap-1 text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1 rounded-md hover:bg-red-50 dark:hover:bg-red-950/30 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>Hapus</span>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- TAB 3: PEJABAT & SMC -->
  <?php if ($activeTab === 'pejabat'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="card p-6 h-fit space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-slate-800">
          <div class="w-8 h-8 rounded-lg bg-orange-500/15 text-orange-500 flex items-center justify-center font-bold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">Tambah Pejabat / SMC</h3>
            <p class="text-[11px] text-slate-500">Penandatangan naskah dinas & dokumen BRA</p>
          </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/settings.php?action=add_pejabat" class="space-y-4">
          <?= csrfInput() ?>
          
          <div class="form-group">
            <label class="form-label">Nama Lengkap & Gelar <span class="text-red-500">*</span></label>
            <input type="text" name="nama" class="input font-bold" placeholder="Contoh: AMIRUDDIN AS, S.Sos." required>
          </div>

          <div class="form-group">
            <label class="form-label">Pangkat / Golongan <span class="text-red-500">*</span></label>
            <input type="text" name="pangkat" class="input" placeholder="Contoh: PEMBINA (IV/a) / PENATA TK.I (III/d)" required>
          </div>

          <div class="form-group">
            <label class="form-label">Jabatan Kedinasan <span class="text-red-500">*</span></label>
            <input type="text" name="jabatan" class="input" placeholder="Contoh: Kepala Kantor / Kasi Ops & Siaga / SMC" required>
          </div>

          <div class="form-group">
            <label class="form-label">Nomor Induk Pegawai (NIP)</label>
            <input type="text" name="nip" class="input font-mono" placeholder="Contoh: 197405101998031002">
          </div>

          <div class="p-3 rounded-xl bg-orange-50 dark:bg-orange-950/20 border border-orange-200 dark:border-orange-500/20 flex items-center gap-2.5">
            <input type="checkbox" name="is_default_smc" id="defaultSmcCheck" value="1" class="w-4 h-4 rounded text-orange-500 focus:ring-orange-500">
            <label for="defaultSmcCheck" class="text-xs text-slate-700 dark:text-slate-300 font-semibold cursor-pointer">
              Tetapkan sebagai SMC Default Operasi
            </label>
          </div>

          <button type="submit" class="w-full py-2.5 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 active:scale-98 rounded-xl transition shadow flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>Simpan Pejabat Baru</span>
          </button>
        </form>
      </div>

      <div class="lg:col-span-2 card p-6 space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">
              Daftar Pejabat Penandatangan & SMC
            </h3>
            <p class="text-[11px] text-slate-500">Total terdata: <?= count($pejabatList) ?> pejabat resmi</p>
          </div>
        </div>

        <div class="space-y-3">
          <?php if (empty($pejabatList)): ?>
            <div class="text-center py-8 text-slate-500 text-xs">
              Belum ada pejabat yang didaftarkan. Silakan tambahkan melalui form di samping.
            </div>
          <?php else: ?>
            <?php foreach ($pejabatList as $p): ?>
              <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/40 hover:border-orange-500/40 transition flex items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                  <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-sm shrink-0 border border-slate-200 dark:border-slate-700">
                    🎖️
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="font-bold text-sm text-slate-900 dark:text-slate-100"><?= htmlspecialchars($p['nama']) ?></span>
                      <?php if (!empty($p['is_default_smc'])): ?>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
                          DEFAULT SMC
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                      <?= htmlspecialchars($p['pangkat'] ?? '-') ?> &bull; <?= htmlspecialchars($p['jabatan'] ?? '-') ?>
                    </div>
                    <?php if (!empty($p['nip'])): ?>
                      <div class="text-[11px] font-mono text-slate-400 mt-0.5">NIP. <?= htmlspecialchars($p['nip']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
                <a href="<?= BASE_URL ?>/settings.php?action=delete_pejabat&id=<?= urlencode($p['id'] ?? '') ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus pejabat ini?');" class="inline-flex items-center gap-1 text-red-500 hover:text-red-700 text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/30 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  <span>Hapus</span>
                </a>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- TAB 4: PROFIL SATKER & KOP -->
  <?php if ($activeTab === 'satker'): ?>
    <div class="card p-6 max-w-3xl space-y-6 border border-slate-200/80 dark:border-slate-800 shadow-sm">
      <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">
          Informasi Satuan Kerja & Kop Naskah Dinas
        </h3>
        <p class="text-xs text-slate-500">Konfigurasi naskah dinas telegram radio, kop surat dinas, dan identitas kantor</p>
      </div>

      <form method="POST" action="<?= BASE_URL ?>/settings.php?action=save_satker" class="space-y-4">
        <?= csrfInput() ?>

        <div class="form-group">
          <label class="form-label">Nama Satuan Kerja Basarnas <span class="text-red-500">*</span></label>
          <input type="text" name="satker_nama" value="<?= htmlspecialchars($settings['satker_nama'] ?? '') ?>" placeholder="Contoh: Kantor Pencarian dan Pertolongan Kendari" class="input font-bold" required>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="form-group">
            <label class="form-label">Kode Satker Senkom <span class="text-red-500">*</span></label>
            <input type="text" name="satker_kode" value="<?= htmlspecialchars($settings['satker_kode'] ?? '') ?>" placeholder="Contoh: SAR-304" class="input font-mono font-bold uppercase" required>
          </div>
          <div class="form-group">
            <label class="form-label">Call Sign Komunikasi <span class="text-red-500">*</span></label>
            <input type="text" name="call_sign" value="<?= htmlspecialchars($settings['call_sign'] ?? '') ?>" placeholder="Contoh: KANSAR KENDARI" class="input font-bold uppercase" required>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="form-group">
            <label class="form-label">No. Telepon / Emergency</label>
            <input type="text" name="no_telp" value="<?= htmlspecialchars($settings['no_telp'] ?? '') ?>" placeholder="Contoh: (0401) 3122115 / Emergency 115" class="input">
          </div>
          <div class="form-group">
            <label class="form-label">Email Resmi</label>
            <input type="email" name="email" value="<?= htmlspecialchars($settings['email'] ?? '') ?>" placeholder="Contoh: sar_kendari@basarnas.go.id" class="input">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Alamat Lengkap Kantor</label>
          <textarea name="alamat" rows="2" placeholder="Contoh: Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sulawesi Tenggara 93116" class="input"><?= htmlspecialchars($settings['alamat'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">URL Logo Resmi Basarnas</label>
          <input type="text" name="logo_url" value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>" placeholder="Contoh: https://upload.wikimedia.org/.../Logo_Basarnas.png" class="input font-mono text-xs">
          <span class="text-[11px] text-slate-500">Opsional: Biarkan kosong untuk menggunakan logo bawaan sistem.</span>
        </div>

        <div class="form-group p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40">
          <label class="form-label mb-1">Tema Tampilan Global Sistem</label>
          <div class="grid grid-cols-2 gap-3 mt-2">
            <label class="flex items-center gap-2 text-xs font-bold cursor-pointer">
              <input type="radio" name="default_theme" value="dark" <?= ($settings['default_theme'] ?? 'dark') === 'dark' ? 'checked' : '' ?> onchange="if(window.setAppTheme) window.setAppTheme('dark', true)">
              <span>🌙 Mode Gelap (Dark Mode)</span>
            </label>
            <label class="flex items-center gap-2 text-xs font-bold cursor-pointer">
              <input type="radio" name="default_theme" value="light" <?= ($settings['default_theme'] ?? 'dark') === 'light' ? 'checked' : '' ?> onchange="if(window.setAppTheme) window.setAppTheme('light', true)">
              <span>☀️ Mode Terang (Light Mode)</span>
            </label>
          </div>
        </div>

        <div class="pt-2">
          <button type="submit" class="px-6 py-2.5 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 active:scale-98 rounded-xl transition shadow flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>Simpan Profil Satker</span>
          </button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <!-- TAB 5: AKUN & KEAMANAN -->
  <?php if ($activeTab === 'keamanan'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Admin Profile -->
      <div class="card p-6 space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">
            Profil Administrator Utama
          </h3>
          <p class="text-xs text-slate-500">Identitas akun pimpinan operasional yang memegang kendali sistem</p>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/settings.php?action=save_profile" class="space-y-4">
          <?= csrfInput() ?>

          <div class="form-group">
            <label class="form-label">Nama Lengkap & Gelar <span class="text-red-500">*</span></label>
            <input type="text" name="admin_nama" value="<?= htmlspecialchars($adminProfile['nama'] ?? '') ?>" placeholder="Contoh: AMIRUDDIN AS, S.Sos." class="input font-bold" required>
          </div>

          <div class="form-group">
            <label class="form-label">Nomor Induk Pegawai (NIP)</label>
            <input type="text" name="admin_nip" value="<?= htmlspecialchars($adminProfile['nip'] ?? '') ?>" placeholder="Contoh: 197405101998031002" class="input font-mono">
          </div>

          <div class="form-group">
            <label class="form-label">Pangkat / Golongan</label>
            <input type="text" name="admin_pangkat" value="<?= htmlspecialchars($adminProfile['pangkat'] ?? '') ?>" placeholder="Contoh: PEMBINA (IV/a)" class="input">
          </div>

          <div class="form-group">
            <label class="form-label">Jabatan Kedinasan</label>
            <input type="text" name="admin_jabatan" value="<?= htmlspecialchars($adminProfile['jabatan'] ?? '') ?>" placeholder="Contoh: Kepala Kantor KPP Kendari" class="input">
          </div>

          <div class="form-group">
            <label class="form-label">Alamat Email</label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($adminProfile['email'] ?? '') ?>" placeholder="Contoh: admin.kendari@basarnas.go.id" class="input">
          </div>

          <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 active:scale-98 rounded-xl transition shadow flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>Simpan Perubahan Profil</span>
          </button>
        </form>
      </div>

      <!-- Change Password -->
      <div class="card p-6 space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">
            Ganti Kata Sandi Administrator
          </h3>
          <p class="text-xs text-slate-500">Perbarui kata sandi akun untuk menjaga keamanan akses sistem</p>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/settings.php?action=change_password" class="space-y-4">
          <?= csrfInput() ?>

          <div class="form-group">
            <label class="form-label">Kata Sandi Lama <span class="text-red-500">*</span></label>
            <input type="password" name="old_password" placeholder="Masukkan kata sandi lama Anda" class="input" required>
          </div>

          <div class="form-group">
            <label class="form-label">Kata Sandi Baru <span class="text-red-500">*</span></label>
            <input type="password" name="new_password" placeholder="Minimal 6 karakter" class="input" required>
          </div>

          <div class="form-group">
            <label class="form-label">Konfirmasi Kata Sandi Baru <span class="text-red-500">*</span></label>
            <input type="password" name="confirm_password" placeholder="Ulangi kata sandi baru" class="input" required>
          </div>

          <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 active:scale-98 rounded-xl transition shadow flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <span>Perbarui Kata Sandi</span>
          </button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- TAB 6: BACKUP & RESTORE DATABASE -->
  <?php if ($activeTab === 'backup'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Export Box -->
      <div class="card p-6 space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-slate-800">
          <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-500 flex items-center justify-center font-bold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">Ekspor & Cadangkan Data</h3>
            <p class="text-[11px] text-slate-500">Unduh cadangan data sistem secara berkala</p>
          </div>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">
          Gunakan fitur ini untuk membuat berkas arsip lengkap sistem QR-SAR untuk audit berkala, backup bulanan, atau migrasi ke server hosting/VPS lain.
        </p>

        <div class="space-y-3 pt-2">
          <a href="<?= BASE_URL ?>/api/export/sql" class="w-full flex items-center justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500 bg-slate-50 dark:bg-slate-800/40 transition group">
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-orange-500 flex items-center gap-2">
                <span>Ekspor Standalone SQL Dump (.sql)</span>
                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-600">Direkomendasikan</span>
              </div>
              <div class="text-[11px] text-slate-500 mt-0.5">Struktur DDL tabel + seluruh data operasional siap diimport ke MySQL/phpMyAdmin</div>
            </div>
            <span class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-orange-500 text-white shrink-0 shadow-sm group-hover:bg-orange-600 transition">Unduh SQL</span>
          </a>

          <a href="<?= BASE_URL ?>/api/backup/export" class="w-full flex items-center justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500 bg-slate-50 dark:bg-slate-800/40 transition group">
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-orange-500">
                Ekspor JSON Backup Kompatibel (.json)
              </div>
              <div class="text-[11px] text-slate-500 mt-0.5">Format JSON identik dengan arsitektur data repo asli QR-SAR Basarnas</div>
            </div>
            <span class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-slate-800 dark:bg-slate-700 text-white shrink-0 group-hover:bg-slate-900 transition">Unduh JSON</span>
          </a>
        </div>
      </div>

      <!-- Restore Box -->
      <div class="card p-6 space-y-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-slate-800">
          <div class="w-8 h-8 rounded-lg bg-amber-500/15 text-amber-500 flex items-center justify-center font-bold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider">Pulihkan Basis Data (Restore JSON)</h3>
            <p class="text-[11px] text-slate-500">Restorasi data operasional dari file backup</p>
          </div>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">
          Unggah berkas JSON hasil ekspor sistem untuk memulihkan seluruh data Operasi SAR, Renops, Lapbang, Dokumen BRA, dan Alut yang pernah dicadangkan.
        </p>

        <form method="POST" action="<?= BASE_URL ?>/settings.php?action=restore_json" enctype="multipart/form-data" class="space-y-4 pt-1">
          <?= csrfInput() ?>

          <div class="form-group">
            <label class="form-label">Pilih Berkas Backup JSON <span class="text-red-500">*</span></label>
            <input type="file" name="backup_file" accept=".json" class="input text-xs" required>
          </div>

          <div class="p-3 bg-amber-50 dark:bg-amber-950/20 rounded-xl border border-amber-200 dark:border-amber-500/20 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
            <span class="text-base shrink-0">⚠️</span>
            <div><strong>Perhatian:</strong> Pemulihan data akan memperbarui dan menggabungkan data yang cocok di dalam database MySQL.</div>
          </div>

          <button type="submit" onclick="return confirm('Apakah Anda yakin ingin memulihkan basis data dari file ini?');" class="w-full py-2.5 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 active:scale-98 rounded-xl transition shadow flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <span>Proses Pemulihan Data</span>
          </button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
