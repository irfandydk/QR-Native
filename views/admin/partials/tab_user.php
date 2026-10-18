<?php
/**
 * QR-SAR - Admin Tab Partial: Manajemen Akun Pengguna (User / Operator)
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}
?>
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
        <?= csrfInput() ?>
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-red-200 dark:border-red-900/40 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 text-xs font-bold transition-colors cursor-pointer">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
          <span>Reset ke Standar</span>
        </button>
      </form>
    </div>

    <form action="<?= BASE_URL ?>/admin.php?action=save_user" method="POST" class="mt-6 space-y-4">
      <?= csrfInput() ?>
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

  <!-- Tabel Ringkasan Akun Pengguna Sistem -->
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
          <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Akun Pengguna Sistem</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Status otoritas akun aktif yang terhubung pada sistem QR-SAR</p>
        </div>
      </div>
      <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
        <?= count($usersList ?? []) ?> Akun Terdaftar
      </span>
    </div>

    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-black tracking-wider text-[10px]">
          <tr>
            <th class="py-3 px-4 rounded-l-xl">Identitas Pengguna</th>
            <th class="py-3 px-4">Role Akses</th>
            <th class="py-3 px-4">Pangkat / NIP</th>
            <th class="py-3 px-4">Status</th>
            <th class="py-3 px-4 rounded-r-xl">Login Terakhir</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
          <?php if (empty($usersList)): ?>
            <tr>
              <td colspan="5" class="py-6 text-center text-slate-400">Tidak ada data pengguna yang ditemukan.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($usersList as $u): ?>
              <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900 dark:text-white"><?= e($u['nama'] ?? '') ?></div>
                  <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400">@<?= e($u['username'] ?? '') ?></div>
                </td>
                <td class="py-3.5 px-4">
                  <?php if (($u['role'] ?? '') === 'ADMIN'): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
                      ADMINISTRATOR
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
                      PETUGAS SIAGA
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-3.5 px-4">
                  <div class="text-slate-700 dark:text-slate-300"><?= e($u['pangkat'] ?: '-') ?></div>
                  <div class="text-[11px] font-mono text-slate-400"><?= e($u['nip'] ?: '-') ?></div>
                </td>
                <td class="py-3.5 px-4">
                  <?php if (!empty($u['is_active'])): ?>
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-400">
                      <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Nonaktif
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 font-mono text-[11px]">
                  <?= !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '<span class="text-slate-400 italic">Belum pernah</span>' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
