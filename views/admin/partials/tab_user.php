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
<div class="space-y-5">

  <!-- Form Akun Operator -->
  <section class="bento-card-v1 rounded-2xl p-5 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3.5 border-b border-slate-100 dark:border-slate-800">
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Akun Pengguna Operasional (Operator)</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kredensial petugas siaga SAR dan Senkom.</p>
      </div>

      <form action="<?= BASE_URL ?>/admin.php?action=reset_user" method="POST" data-confirm="Apakah Anda yakin ingin mereset akun pengguna operasional ke username 'user' dengan sandi sementara acak baru? Sandi baru akan ditampilkan sekali setelah reset." data-confirm-title="Konfirmasi Reset Akun Pengguna" data-confirm-btn="Ya, Reset Akun">
        <?= csrfInput() ?>
        <button type="submit" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-rose-500/25 bg-rose-500/10 hover:bg-rose-600 text-rose-600 dark:text-rose-400 hover:text-white text-xs font-semibold transition cursor-pointer">
          <span>Reset ke Standar</span>
        </button>
      </form>
    </div>

    <form action="<?= BASE_URL ?>/admin.php?action=save_user" method="POST" class="mt-5 space-y-4">
      <?= csrfInput() ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2 field">
          <label class="field-label">Nama Petugas Operasional <span class="text-rose-500">*</span></label>
          <input class="field-input w-full font-semibold" type="text" name="user_nama" value="<?= e($settings['user_nama'] ?? 'Petugas Siaga SAR') ?>" required placeholder="Petugas Siaga SAR" />
        </div>

        <div class="field">
          <label class="field-label">NIP Petugas</label>
          <input class="field-input w-full font-mono" type="text" name="user_nip" value="<?= e($settings['user_nip'] ?? '199501012020121001') ?>" placeholder="199501012020121001" />
        </div>

        <div class="field">
          <label class="field-label">Pangkat / Golongan</label>
          <input class="field-input w-full font-semibold" type="text" name="user_pangkat" value="<?= e($settings['user_pangkat'] ?? 'PENGATUR (II/c)') ?>" placeholder="PENGATUR (II/c)" />
        </div>

        <div class="sm:col-span-2 field">
          <label class="field-label">Jabatan Petugas Siaga</label>
          <input class="field-input w-full font-semibold" type="text" name="user_jabatan" value="<?= e($settings['user_jabatan'] ?? 'Operator Komunikasi & Petugas Siaga SAR') ?>" placeholder="Operator Komunikasi & Petugas Siaga SAR" />
        </div>

        <div class="field">
          <label class="field-label">Username Akun User <span class="text-rose-500">*</span></label>
          <input class="field-input w-full font-mono font-bold" type="text" name="user_username" value="<?= e($settings['user_username'] ?? 'user') ?>" required placeholder="user" />
        </div>

        <div class="field">
          <label class="field-label">Set Kata Sandi Baru <span class="text-slate-400 font-normal">(Opsional)</span></label>
          <input type="password" name="user_password" placeholder="Kosongkan jika tidak diubah" class="field-input w-full" />
        </div>

        <div class="field">
          <label class="field-label">Email Petugas</label>
          <input class="field-input w-full" type="email" name="user_email" value="<?= e($settings['user_email'] ?? 'operator.kendari@basarnas.go.id') ?>" placeholder="operator.kendari@basarnas.go.id" />
        </div>

        <div class="field">
          <label class="field-label">Nomor HP / WA Petugas</label>
          <input class="field-input w-full font-mono" type="text" name="user_no_hp" value="<?= e($settings['user_no_hp'] ?? '0811-4091-115') ?>" placeholder="0811-4091-115" />
        </div>
      </div>

      <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
        <button type="submit" class="adm-accent-btn inline-flex items-center gap-2 h-9 px-4 rounded-xl font-bold text-xs transition cursor-pointer">
          <span>Simpan Akun Pengguna</span>
        </button>
      </div>
    </form>
  </section>

  <!-- Tabel Ringkasan Akun Pengguna Sistem -->
  <section class="bento-card-v1 rounded-2xl overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
      <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Akun Pengguna Sistem</h3>
      <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
        <?= count($usersList ?? []) ?> Akun
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-slate-500 dark:text-slate-400 font-semibold text-[11px]">
            <th class="py-2.5 px-4">Identitas Pengguna</th>
            <th class="py-2.5 px-4">Role Akses</th>
            <th class="py-2.5 px-4">Pangkat / NIP</th>
            <th class="py-2.5 px-4">Status</th>
            <th class="py-2.5 px-4">Login Terakhir</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
          <?php if (empty($usersList)): ?>
            <tr>
              <td colspan="5" class="py-8 text-center text-slate-400">Tidak ada data pengguna ditemukan.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($usersList as $u): ?>
              <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors">
                <td class="py-3 px-4">
                  <div class="font-semibold text-slate-900 dark:text-white"><?= e($u['nama'] ?? '') ?></div>
                  <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400">@<?= e($u['username'] ?? '') ?></div>
                </td>
                <td class="py-3 px-4 whitespace-nowrap">
                  <?php if (($u['role'] ?? '') === 'ADMIN'): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-bold adm-accent-text bg-slate-100 dark:bg-slate-800">
                      ADMIN
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                      PETUGAS SIAGA
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4 whitespace-nowrap">
                  <div class="text-slate-700 dark:text-slate-300"><?= e($u['pangkat'] ?: '-') ?></div>
                  <div class="text-[11px] font-mono text-slate-400"><?= e($u['nip'] ?: '-') ?></div>
                </td>
                <td class="py-3 px-4 whitespace-nowrap">
                  <?php if (!empty($u['is_active'])): ?>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                      <span>Aktif</span>
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                      <span class="w-1.5 h-1.5 rounded-full bg-slate-400" aria-hidden="true"></span>
                      <span>Nonaktif</span>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-slate-600 dark:text-slate-400 font-mono text-[11px] whitespace-nowrap">
                  <?= !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : 'Belum pernah' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

</div>
