<?php
/**
 * QR-SAR - User Profile & Password Management View
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dedicated self-service profile and password update page for operational users (Operators).
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$user = $currentUser ?? AuthHelper::getCurrentUser();
$roleUpper = strtoupper((string)($user['role'] ?? 'USER'));
$roleTitle = $user['roleTitle'] ?? ($roleUpper === 'ADMIN' ? 'Administrator Utama SAR' : 'Operator Siaga SAR & Senkom');
?>

<div class="space-y-6 max-w-6xl mx-auto">
  <!-- Top Header Banner -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-orange-600 shadow-lg text-white" style="background: linear-gradient(135deg, var(--primary) 0%, #ea580c 50%, #f59e0b 100%);">
    <div class="flex items-center gap-3.5">
      <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm border border-white/30 flex items-center justify-center text-white shrink-0 shadow-inner">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white">
            Profil Pengguna & Keamanan Akun
          </h1>
          <span class="text-[11px] font-mono uppercase px-2 py-0.5 rounded-full bg-white/25 text-white font-bold tracking-wider">
            <?= e($roleUpper) ?>
          </span>
        </div>
        <p class="text-xs text-orange-100 mt-0.5">
          Informasi identitas kedinasan petugas dan pengelolaan kata sandi akun mandiri.
        </p>
      </div>
    </div>

    <div class="flex items-center gap-2 self-end sm:self-auto">
      <a href="<?= BASE_URL ?>/index.php" class="btn-tactile inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-white/20 hover:bg-white/30 backdrop-blur-sm border border-white/30 text-white transition shadow-sm" title="Kembali ke Dashboard">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Dashboard</span>
      </a>
    </div>
  </div>

  <!-- Content Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Column: User Identity Card -->
    <div class="lg:col-span-5 space-y-6">
      <div class="bento-card-v1 cockpit-glass p-6 rounded-2xl border border-slate-200/80 dark:border-white/10 shadow-sm space-y-5">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="w-14 h-14 rounded-2xl bg-orange-600 text-white flex items-center justify-center font-bold text-xl shadow-md shrink-0">
            <?= strtoupper(substr((string)($user['nama'] ?: $user['username'] ?: 'U'), 0, 1)) ?>
          </div>
          <div class="min-w-0 flex-1">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white truncate">
              <?= e($user['nama'] ?: 'Petugas Siaga SAR') ?>
            </h2>
            <div class="flex items-center gap-1.5 mt-0.5">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold <?= $roleUpper === 'ADMIN' ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30' : 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30' ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $roleUpper === 'ADMIN' ? 'bg-orange-500' : 'bg-blue-500' ?>"></span>
                <?= e($roleTitle) ?>
              </span>
            </div>
          </div>
        </div>

        <div class="space-y-3.5 text-xs">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Nama Pengguna (Username)</span>
            <div class="font-mono font-bold text-slate-800 dark:text-slate-100 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800">
              <?= e($user['username'] ?? 'user') ?>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">NIP</span>
              <div class="font-mono font-medium text-slate-700 dark:text-slate-200 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800 truncate">
                <?= e($user['nip'] ?: '-') ?>
              </div>
            </div>
            <div>
              <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Pangkat / Golongan</span>
              <div class="font-medium text-slate-700 dark:text-slate-200 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800 truncate">
                <?= e($user['pangkat'] ?: '-') ?>
              </div>
            </div>
          </div>

          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Jabatan Kedinasan</span>
            <div class="font-medium text-slate-700 dark:text-slate-200 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800">
              <?= e($user['jabatan'] ?: 'Operator Komunikasi & Petugas Siaga SAR') ?>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Alamat Email</span>
              <div class="font-medium text-slate-700 dark:text-slate-200 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800 truncate">
                <?= e($user['email'] ?: '-') ?>
              </div>
            </div>
            <div>
              <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">No. Handphone / WA</span>
              <div class="font-medium text-slate-700 dark:text-slate-200 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800 truncate">
                <?= e($user['no_hp'] ?: '-') ?>
              </div>
            </div>
          </div>
        </div>

        <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/60 text-[11px] text-amber-800 dark:text-amber-300 space-y-1">
          <div class="flex items-center gap-1.5 font-bold">
            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span>Sinkronisasi Data Personel</span>
          </div>
          <p class="leading-relaxed">
            Perubahan identitas kedinasan (Nama Lengkap, NIP, Pangkat, Jabatan) dipusatkan melalui Administrator Utama SAR pada panel Pengaturan Admin.
          </p>
        </div>
      </div>
    </div>

    <!-- Right Column: Self-Service Password Change Card -->
    <div class="lg:col-span-7">
      <div class="bento-card-v1 cockpit-glass p-6 rounded-2xl border border-slate-200/80 dark:border-white/10 shadow-sm space-y-5">
        <div class="flex items-center gap-2 pb-4 border-b border-slate-100 dark:border-slate-800">
          <span class="p-2 rounded-xl text-orange-600 dark:text-orange-400" style="background-color: var(--primary-light); color: var(--primary);">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
          </span>
          <div>
            <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
              Ubah Kata Sandi Mandiri
            </h2>
            <p class="text-xs text-slate-400">
              Perbarui kata sandi akun siaga Anda secara berkala demi menjaga keamanan data operasional.
            </p>
          </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/profile.php" class="space-y-4">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="change_password">

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Kata Sandi Saat Ini (Sandi Lama) <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input type="password" id="oldPasswordInput" name="old_password" required placeholder="Masukkan kata sandi lama Anda..." class="control control-sm w-full font-medium">
              <button type="button" onclick="togglePasswordVisibility('oldPasswordInput')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition" title="Lihat/Sembunyikan Sandi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Kata Sandi Baru <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <input type="password" id="newPasswordInput" name="new_password" required minlength="8" placeholder="Minimal 8 karakter..." class="control control-sm w-full font-medium">
                <button type="button" onclick="togglePasswordVisibility('newPasswordInput')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition" title="Lihat/Sembunyikan Sandi">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                Konfirmasi Kata Sandi Baru <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <input type="password" id="confirmPasswordInput" name="confirm_password" required minlength="8" placeholder="Ulangi kata sandi baru..." class="control control-sm w-full font-medium">
                <button type="button" onclick="togglePasswordVisibility('confirmPasswordInput')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition" title="Lihat/Sembunyikan Sandi">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
          </div>

          <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 space-y-1">
            <span class="font-bold text-slate-700 dark:text-slate-300 block">Kriteria Keamanan Kata Sandi:</span>
            <p>• Panjang minimal <strong>8 karakter</strong>.</p>
            <p>• Disarankan memadukan kombinasi huruf besar, huruf kecil, angka, serta tanda baca.</p>
            <p>• Hindari menggunakan tanggal lahir atau kata yang mudah ditebak.</p>
          </div>

          <div class="pt-2 flex justify-end">
            <button type="submit" class="btn-tactile px-5 py-2.5 text-xs font-bold text-white rounded-xl transition shadow-md flex items-center justify-center gap-2 cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              <span>Perbarui Kata Sandi Saya</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function togglePasswordVisibility(inputId) {
  const input = document.getElementById(inputId);
  if (input) {
    input.type = (input.type === 'password') ? 'text' : 'password';
  }
}
</script>

