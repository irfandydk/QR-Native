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
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

  <!-- Kolom Kiri: Form Identitas Administrator (2 Kolom) -->
  <section class="lg:col-span-2 bento-card-v1 rounded-2xl p-5 sm:p-6">
    <div class="flex items-center justify-between gap-3 pb-3.5 border-b border-slate-100 dark:border-slate-800">
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Identitas Administrator</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Profil dinas Kepala Kantor dan akun administrator utama.</p>
      </div>
      <span class="px-2 py-0.5 rounded-md font-mono text-[11px] font-bold adm-accent-text bg-slate-100 dark:bg-slate-800 shrink-0">
        ADMIN
      </span>
    </div>

    <form action="<?= BASE_URL ?>/admin.php?action=save_admin" method="POST" class="mt-5 space-y-4">
      <?= csrfInput() ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2 field">
          <label class="field-label">Nama Lengkap &amp; Gelar Resmi <span class="text-rose-500">*</span></label>
          <input class="field-input w-full font-semibold" type="text" name="admin_nama" value="<?= e($settings['admin_nama'] ?? 'AMIRUDDIN AS, S.Sos.') ?>" required placeholder="Contoh: AMIRUDDIN AS, S.Sos." />
        </div>

        <div class="field">
          <label class="field-label">NIP Pegawai</label>
          <input class="field-input w-full font-mono" type="text" name="admin_nip" value="<?= e($settings['admin_nip'] ?? '197405101998031002') ?>" placeholder="197405101998031002" />
        </div>

        <div class="field">
          <label class="field-label">Pangkat / Golongan</label>
          <input class="field-input w-full font-semibold" type="text" name="admin_pangkat" value="<?= e($settings['admin_pangkat'] ?? 'PEMBINA (IV/a)') ?>" placeholder="PEMBINA (IV/a)" />
        </div>

        <div class="sm:col-span-2 field">
          <label class="field-label">Jabatan Struktural Dinas</label>
          <input class="field-input w-full font-semibold" type="text" name="admin_jabatan" value="<?= e($settings['admin_jabatan'] ?? 'Kepala Kantor / Administrator Utama KPP Kendari') ?>" placeholder="Kepala Kantor / Administrator Utama KPP Kendari" />
        </div>

        <div class="field">
          <label class="field-label">Username Login Admin <span class="text-rose-500">*</span></label>
          <input class="field-input w-full font-mono font-bold" type="text" name="admin_username" value="<?= e($settings['admin_username'] ?? 'admin') ?>" required placeholder="admin" />
        </div>

        <div class="field">
          <label class="field-label">Email Administrator</label>
          <input class="field-input w-full" type="email" name="admin_email" value="<?= e($settings['admin_email'] ?? 'irfandydk@gmail.com') ?>" placeholder="admin@basarnas.go.id" />
        </div>

        <div class="sm:col-span-2 field">
          <label class="field-label">Nomor Telepon / WhatsApp Dinas</label>
          <input class="field-input w-full font-mono" type="text" name="admin_no_hp" value="<?= e($settings['admin_no_hp'] ?? '0811-4091-115') ?>" placeholder="0811-4091-115" />
        </div>
      </div>

      <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
        <button type="submit" class="adm-accent-btn inline-flex items-center gap-2 h-9 px-4 rounded-xl font-bold text-xs transition cursor-pointer">
          <span>Simpan Profil Administrator</span>
        </button>
      </div>
    </form>
  </section>

  <!-- Kolom Kanan: Form Ubah Sandi Admin -->
  <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
    <div class="pb-3.5 border-b border-slate-100 dark:border-slate-800">
      <h2 class="text-sm font-bold text-slate-900 dark:text-white">Ubah Kata Sandi Admin</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Proteksi kredensial akun utama.</p>
    </div>

    <div class="pl-3 py-1 border-l-2 border-amber-500 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
      Gunakan kombinasi minimal 4 karakter. Standar awal: <strong class="font-mono text-slate-800 dark:text-slate-200">Kendari304</strong>.
    </div>

    <form action="<?= BASE_URL ?>/admin.php?action=change_admin_pass" method="POST" class="space-y-3.5">
      <?= csrfInput() ?>
      <div class="field">
        <label class="field-label">Kata Sandi Saat Ini <span class="text-rose-500">*</span></label>
        <input type="password" name="old_password" required placeholder="Masukkan kata sandi lama" class="field-input w-full" />
      </div>

      <div class="field">
        <label class="field-label">Kata Sandi Baru <span class="text-rose-500">*</span></label>
        <input type="password" name="new_password" required minlength="4" placeholder="Minimal 4 karakter" class="field-input w-full" />
      </div>

      <div class="field">
        <label class="field-label">Konfirmasi Sandi Baru <span class="text-rose-500">*</span></label>
        <input type="password" name="confirm_password" required minlength="4" placeholder="Ulangi kata sandi baru" class="field-input w-full" />
      </div>

      <div class="pt-1">
        <button type="submit" class="w-full h-9 inline-flex items-center justify-center gap-2 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white text-white dark:text-slate-900 font-bold text-xs transition cursor-pointer">
          <span>Perbarui Kata Sandi</span>
        </button>
      </div>
    </form>
  </section>

</div>
