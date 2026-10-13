<?php
/**
 * QR-SAR - Admin Settings View
 * KPP Kendari - Basarnas
 * 100% Faithful to Reference AdminSettingsPage.tsx
 */

declare(strict_types=1);

$tab = $tab ?? 'admin';
$settings = $settings ?? [];
$dbStats = $dbStats ?? [];
$logoUrl = getAppLogoUrl();
?>

<div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors pb-16">

  <!-- Header Bar -->
  <div class="border-b sticky top-0 z-20 backdrop-blur-md bg-white/95 dark:bg-slate-900/95 border-slate-200 dark:border-slate-800 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        
        <div class="flex items-center gap-3">
          <a href="<?= BASE_URL ?>/index.php" class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer shrink-0" title="Kembali ke Dashboard">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          </a>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="font-black text-base sm:text-lg tracking-wide uppercase text-slate-900 dark:text-white">
                Pengaturan Satker & Keamanan Admin
              </h1>
              <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                HALAMAN KHUSUS
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">
              Konfigurasi identitas kantor SAR, profil admin, sandi akun, serta cadangan basis data terpadu
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto">
          <a href="<?= BASE_URL ?>/admin.php?tab=<?= e($tab) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-750 text-xs font-semibold shadow-xs transition-colors cursor-pointer" title="Muat ulang pengaturan">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
            <span>Segarkan</span>
          </a>
        </div>
      </div>

      <!-- Tab Navigation matching AdminSettingsPage.tsx -->
      <div class="flex items-center gap-2 mt-4 overflow-x-auto no-scrollbar border-t border-slate-100 dark:border-slate-800/80 pt-3">
        
        <!-- Tab 1: admin -->
        <a href="<?= BASE_URL ?>/admin.php?tab=admin" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'admin' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
          <span>Profil & Sandi Admin</span>
        </a>

        <!-- Tab 2: user -->
        <a href="<?= BASE_URL ?>/admin.php?tab=user" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'user' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span>Akun Pengguna (User)</span>
        </a>

        <!-- Tab 3: satker -->
        <a href="<?= BASE_URL ?>/admin.php?tab=satker" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'satker' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M8 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M16 14h.01"/></svg>
          <span>Pengaturan Satker & Aplikasi</span>
        </a>

        <!-- Tab 4: backup -->
        <a href="<?= BASE_URL ?>/admin.php?tab=backup" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'backup' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
          <span>Backup & Restore Database</span>
          <span class="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-extrabold border border-emerald-500/30">
            100% COVER
          </span>
        </a>

      </div>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    <!-- ==================== TAB 1: PROFIL & SANDI ADMIN ==================== -->
    <?php if ($tab === 'admin'): ?>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Kolom Kiri: Form Identitas Administrator (2 Kolom) -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                  <h2 class="text-sm font-bold text-slate-900 dark:text-white">Identitas Pejabat Administrator</h2>
                  <p class="text-xs text-slate-500 dark:text-slate-400">Profil dinas Kepala Kantor / Admin Utama KPP Kendari</p>
                </div>
              </div>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                ROLE: ADMIN
              </span>
            </div>

            <form action="<?= BASE_URL ?>/admin.php?action=save_admin" method="POST" class="mt-6 space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <!-- Nama Lengkap -->
                <div class="sm:col-span-2">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Nama Lengkap & Gelar Resmi <span class="text-red-500">*</span>
                  </label>
                  <input type="text" name="admin_nama" value="<?= e($settings['admin_nama'] ?? 'AMIRUDDIN AS, S.Sos.') ?>" required placeholder="Contoh: AMIRUDDIN AS, S.Sos." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

                <!-- NIP -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    NIP Pegawai
                  </label>
                  <input type="text" name="admin_nip" value="<?= e($settings['admin_nip'] ?? '197405101998031002') ?>" placeholder="197405101998031002" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

                <!-- Pangkat / Golongan -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Pangkat / Golongan
                  </label>
                  <input type="text" name="admin_pangkat" value="<?= e($settings['admin_pangkat'] ?? 'PEMBINA (IV/a)') ?>" placeholder="PEMBINA (IV/a)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

                <!-- Jabatan Resmi -->
                <div class="sm:col-span-2">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Jabatan Struktural Dinas
                  </label>
                  <input type="text" name="admin_jabatan" value="<?= e($settings['admin_jabatan'] ?? 'Kepala Kantor / Administrator Utama KPP Kendari') ?>" placeholder="Kepala Kantor / Administrator Utama KPP Kendari" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

                <!-- Username Login -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Username Login Admin <span class="text-red-500">*</span>
                  </label>
                  <input type="text" name="admin_username" value="<?= e($settings['admin_username'] ?? 'admin') ?>" required placeholder="admin" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

                <!-- Email Dinas -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Email Administrator
                  </label>
                  <input type="email" name="admin_email" value="<?= e($settings['admin_email'] ?? 'irfandydk@gmail.com') ?>" placeholder="irfandydk@gmail.com" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

                <!-- No Handphone / WA -->
                <div class="sm:col-span-2">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Nomor Telepon / WhatsApp Dinas
                  </label>
                  <input type="text" name="admin_no_hp" value="<?= e($settings['admin_no_hp'] ?? '0811-4091-115') ?>" placeholder="0811-4091-115" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

              </div>

              <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-md shadow-orange-600/30 transition-all cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                  <span>Simpan Profil Administrator</span>
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Kolom Kanan: Form Ubah Sandi Admin -->
        <div class="space-y-6">
          <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
              <div class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </div>
              <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Ubah Kata Sandi Admin</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Proteksi akun utama administrator</p>
              </div>
            </div>

            <div class="p-3 my-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-xs flex items-start gap-2.5">
              <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
              <div>
                <p class="font-bold">Keamanan Akun</p>
                <p class="text-[11px] opacity-90 mt-0.5">Gunakan kombinasi minimal 4 karakter. Standar awal: <strong>Kendari304</strong>.</p>
              </div>
            </div>

            <form action="<?= BASE_URL ?>/admin.php?action=change_admin_pass" method="POST" class="space-y-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Kata Sandi Saat Ini <span class="text-red-500">*</span>
                </label>
                <input type="password" name="old_password" required placeholder="Masukkan kata sandi lama" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Kata Sandi Baru <span class="text-red-500">*</span>
                </label>
                <input type="password" name="new_password" required minlength="4" placeholder="Minimal 4 karakter" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Konfirmasi Sandi Baru <span class="text-red-500">*</span>
                </label>
                <input type="password" name="confirm_password" required minlength="4" placeholder="Ulangi kata sandi baru" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <div class="pt-3">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-black dark:bg-slate-800 dark:hover:bg-slate-700 text-white font-bold text-xs shadow-md transition-all cursor-pointer">
                  <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 2l-2 2m-2-2l2 2"/><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  <span>Perbarui Kata Sandi</span>
                </button>
              </div>
            </form>
          </div>
        </div>

      </div>
    <?php endif; ?>

    <!-- ==================== TAB 2: AKUN PENGGUNA (USER) ==================== -->
    <?php if ($tab === 'user'): ?>
      <div class="max-w-4xl mx-auto space-y-6">
        
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
          
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Akun Pengguna Operasional (Operator)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Dikelola oleh Administrator untuk petugas siaga SAR & Senkom</p>
              </div>
            </div>

            <!-- Tombol Reset ke Standar with SweetAlert2 data-confirm -->
            <form action="<?= BASE_URL ?>/admin.php?action=reset_user" method="POST" data-confirm="Apakah Anda yakin ingin mereset akun pengguna operasional ke setelan standar (Username: user, Kata Sandi: Kendari304)?" data-confirm-title="Konfirmasi Reset Akun Pengguna" data-confirm-btn="Ya, Reset ke Standar">
              <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-red-200 dark:border-red-900/40 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 text-xs font-bold transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
                <span>Reset ke Standar</span>
              </button>
            </form>
          </div>

          <form action="<?= BASE_URL ?>/admin.php?action=save_user" method="POST" class="mt-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              
              <!-- Nama Petugas -->
              <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Nama Petugas Operasional <span class="text-red-500">*</span>
                </label>
                <input type="text" name="user_nama" value="<?= e($settings['user_nama'] ?? 'Petugas Siaga SAR') ?>" required placeholder="Petugas Siaga SAR" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <!-- NIP Petugas -->
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  NIP Petugas
                </label>
                <input type="text" name="user_nip" value="<?= e($settings['user_nip'] ?? '199501012020121001') ?>" placeholder="199501012020121001" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <!-- Pangkat Petugas -->
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Pangkat / Golongan
                </label>
                <input type="text" name="user_pangkat" value="<?= e($settings['user_pangkat'] ?? 'PENGATUR (II/c)') ?>" placeholder="PENGATUR (II/c)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <!-- Jabatan Petugas -->
              <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Jabatan Petugas Siaga
                </label>
                <input type="text" name="user_jabatan" value="<?= e($settings['user_jabatan'] ?? 'Operator Komunikasi & Petugas Siaga SAR') ?>" placeholder="Operator Komunikasi & Petugas Siaga SAR" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <!-- Username Login User -->
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Username Akun User <span class="text-red-500">*</span>
                </label>
                <input type="text" name="user_username" value="<?= e($settings['user_username'] ?? 'user') ?>" required placeholder="user" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <!-- Kata Sandi Baru (Opsional) -->
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Set Kata Sandi Baru <span class="text-slate-400 font-normal">(Kosongkan jika tetap)</span>
                </label>
                <input type="password" name="user_password" placeholder="Biarkan kosong jika tidak diubah" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <!-- Email Dinas User -->
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Email Petugas
                </label>
                <input type="email" name="user_email" value="<?= e($settings['user_email'] ?? 'operator.kendari@basarnas.go.id') ?>" placeholder="operator.kendari@basarnas.go.id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

              <!-- No HP User -->
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Nomor HP / WA Petugas
                </label>
                <input type="text" name="user_no_hp" value="<?= e($settings['user_no_hp'] ?? '0811-4091-115') ?>" placeholder="0811-4091-115" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-500" />
              </div>

            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
              <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-md shadow-orange-600/30 transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <span>Simpan Akun Pengguna</span>
              </button>
            </div>
          </form>

        </div>
      </div>
    <?php endif; ?>

    <!-- ==================== TAB 3: PENGATURAN SATKER & APLIKASI ==================== -->
    <?php if ($tab === 'satker'): ?>
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
    <?php endif; ?>

    <!-- ==================== TAB 4: BACKUP & RESTORE DATABASE ==================== -->
    <?php if ($tab === 'backup'): ?>
      <div class="space-y-6">
        
        <!-- Live Statistics Tiles (100% Coverage) -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
          <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
              </div>
              <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Statistik Basis Data Aktif</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Ringkasan entitas data operasional yang tersimpan di server MySQL</p>
              </div>
            </div>
            <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
              STATUS: AKTIF & TERSINKRON
            </span>
          </div>

          <!-- Counter Cards Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mt-5">
            
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Operasi SAR</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_operasi'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Data Korban</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_korban'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Dokumen BRA</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_bra_sar'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Renops SAR</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_renops'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Laporan Harian</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_laporan_harian'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Master Alut</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_master_alut'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Master Potensi</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_master_tim'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Pangkalan SAR</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_sar_units'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Pejabat & SMC</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_pejabat'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Titik GIS Map</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_gis_points'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Polygon Area</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_gis_polygons'] ?? 0) ?></p>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Rute Jarak GIS</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_gis_routes'] ?? 0) ?></p>
            </div>

          </div>
        </div>

        <!-- Export & Restore Actions Row (Strictly SQL Format Only) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          
          <!-- Card Ekspor Cadangan SQL -->
          <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs flex flex-col justify-between">
            <div>
              <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                </div>
                <div>
                  <h3 class="text-sm font-bold text-slate-900 dark:text-white">Ekspor Dump Basis Data SQL (.sql)</h3>
                  <p class="text-xs text-slate-500 dark:text-slate-400">Pencadangan resmi basis data MySQL server dalam format skrip SQL murni</p>
                </div>
              </div>

              <div class="space-y-4 my-5">
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">FORMAT RESMI .SQL</span>
                      <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">MySQL Database Dump File</h4>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                      Mencakup seluruh skrip DDL (struktur tabel) dan DML (seluruh record data) dari 23 tabel entitas operasional SAR, BRA, Renops, Korban, hingga konfigurasi sistem.
                    </p>
                  </div>
                  <a href="<?= BASE_URL ?>/admin.php?action=export_sql" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-md shadow-orange-600/30 transition-all shrink-0 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    <span>Unduh Cadangan SQL</span>
                  </a>
                </div>

                <div class="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-800 dark:text-blue-300 text-xs flex items-start gap-2.5">
                  <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg>
                  <p class="text-[11px] leading-relaxed">
                    Berkas dump SQL ini kompatibel 100% untuk migrasi langsung ke server MySQL / MariaDB via phpMyAdmin, CLI mysql, maupun fitur pemulihan sistem ini.
                  </p>
                </div>
              </div>
            </div>

            <p class="text-[11px] text-slate-400 text-center pt-2">
              Lakukan pencadangan berkala sebelum pembaruan sistem atau penutupan operasi bulanan.
            </p>
          </div>

          <!-- Card Impor / Pemulihan Basis Data SQL -->
          <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
              <div class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pemulihan Basis Data (Restore SQL)</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Pulihkan seluruh data sistem dari berkas cadangan SQL (.sql)</p>
              </div>
            </div>

            <div class="p-3 my-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-xs flex items-start gap-2.5">
              <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>
              <div>
                <p class="font-bold">Peringatan Pemulihan Data</p>
                <p class="text-[11px] opacity-90 mt-0.5">Memulihkan basis data dari berkas SQL akan menimpa tabel yang ada saat ini. Pastikan Anda telah mengunduh cadangan terbaru sebelum melanjutkan.</p>
              </div>
            </div>

            <form action="<?= BASE_URL ?>/admin.php?action=restore_sql" method="POST" enctype="multipart/form-data" class="space-y-4" data-confirm="Apakah Anda yakin ingin memulihkan seluruh basis data sistem dari berkas SQL yang dipilih? Tindakan ini akan menimpa data yang ada saat ini." data-confirm-title="Konfirmasi Pemulihan SQL" data-confirm-btn="Ya, Pulihkan Sekarang">
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                  Pilih Berkas Cadangan Database (.sql) <span class="text-red-500">*</span>
                </label>
                <input type="file" name="backup_file" accept=".sql" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 dark:file:bg-orange-500/20 dark:file:text-orange-400 cursor-pointer" />
              </div>

              <div class="pt-2">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-md shadow-orange-600/30 transition-all cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                  <span>Pulihkan Database dari SQL Sekarang</span>
                </button>
              </div>
            </form>
          </div>

        </div>

      </div>
    <?php endif; ?>

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
