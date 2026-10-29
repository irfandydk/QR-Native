<?php
/**
 * QR-SAR - Admin Tab Partial: Profil & Kata Sandi Administrator
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}
?>
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
        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
          ROLE: ADMIN
        </span>
      </div>

      <form action="<?= BASE_URL ?>/admin.php?action=save_admin" method="POST" class="mt-6 space-y-4">
        <?= csrfInput() ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          
          <!-- Nama Lengkap -->
          <div class="sm:col-span-2">
            <div class="field">
              <label class="field-label">Nama Lengkap & Gelar Resmi <span class="text-red-500">*</span></label>
              <input class="field-input w-full font-semibold" type="text" name="admin_nama" value="<?= e($settings['admin_nama'] ?? 'AMIRUDDIN AS, S.Sos.') ?>" required placeholder="Contoh: AMIRUDDIN AS, S.Sos."  />
            </div>
          </div>

          <!-- NIP -->
          <div>
            <div class="field">
              <label class="field-label">NIP Pegawai</label>
              <input class="field-input w-full font-mono" type="text" name="admin_nip" value="<?= e($settings['admin_nip'] ?? '197405101998031002') ?>" placeholder="197405101998031002"  />
            </div>
          </div>

          <!-- Pangkat / Golongan -->
          <div>
            <div class="field">
              <label class="field-label">Pangkat / Golongan</label>
              <input class="field-input w-full font-semibold" type="text" name="admin_pangkat" value="<?= e($settings['admin_pangkat'] ?? 'PEMBINA (IV/a)') ?>" placeholder="PEMBINA (IV/a)"  />
            </div>
          </div>

          <!-- Jabatan Resmi -->
          <div class="sm:col-span-2">
            <div class="field">
              <label class="field-label">Jabatan Struktural Dinas</label>
              <input class="field-input w-full font-semibold" type="text" name="admin_jabatan" value="<?= e($settings['admin_jabatan'] ?? 'Kepala Kantor / Administrator Utama KPP Kendari') ?>" placeholder="Kepala Kantor / Administrator Utama KPP Kendari"  />
            </div>
          </div>

          <!-- Username Login -->
          <div>
            <div class="field">
              <label class="field-label">Username Login Admin <span class="text-red-500">*</span></label>
              <input class="field-input w-full font-mono font-bold" type="text" name="admin_username" value="<?= e($settings['admin_username'] ?? 'admin') ?>" required placeholder="admin"  />
            </div>
          </div>

          <!-- Email Dinas -->
          <div>
            <div class="field">
              <label class="field-label">Email Administrator</label>
              <input class="field-input w-full" type="email" name="admin_email" value="<?= e($settings['admin_email'] ?? 'irfandydk@gmail.com') ?>" placeholder="irfandydk@gmail.com"  />
            </div>
          </div>

          <!-- No Handphone / WA -->
          <div class="sm:col-span-2">
            <div class="field">
              <label class="field-label">Nomor Telepon / WhatsApp Dinas</label>
              <input class="field-input w-full font-mono" type="text" name="admin_no_hp" value="<?= e($settings['admin_no_hp'] ?? '0811-4091-115') ?>" placeholder="0811-4091-115"  />
            </div>
          </div>

        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
          <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs transition-all cursor-pointer">
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
        <?= csrfInput() ?>
        <div>
          <div class="field">
            <label class="field-label">Kata Sandi Saat Ini <span class="text-red-500">*</span></label>
            <input type="password" name="old_password" required placeholder="Masukkan kata sandi lama" class="field-input w-full" />
          </div>
        </div>

        <div>
          <div class="field">
            <label class="field-label">Kata Sandi Baru <span class="text-red-500">*</span></label>
            <input type="password" name="new_password" required minlength="4" placeholder="Minimal 4 karakter" class="field-input w-full" />
          </div>
        </div>

        <div>
          <div class="field">
            <label class="field-label">Konfirmasi Sandi Baru <span class="text-red-500">*</span></label>
            <input type="password" name="confirm_password" required minlength="4" placeholder="Ulangi kata sandi baru" class="field-input w-full" />
          </div>
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
