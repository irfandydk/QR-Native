<?php
/**
 * QR-SAR - User Profile & Avatar View
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Akun USER dapat melihat identitas dan mengubah foto profilnya sendiri,
 * namun tidak diperkenankan mengubah kata sandi sendiri (terpusat di Administrator).
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$user = $currentUser ?? AuthHelper::getCurrentUser();
$roleUpper = strtoupper((string)($user['role'] ?? 'USER'));
$roleTitle = $user['roleTitle'] ?? ($roleUpper === 'ADMIN' ? 'Administrator Utama SAR' : 'Operator Siaga SAR & Senkom');
$avatarUrl = UserModel::resolveAvatarDisplayUrl((string)($user['avatar_url'] ?? ''));
$initial = strtoupper(mb_substr((string)($user['nama'] ?: $user['username'] ?: 'U'), 0, 1, 'UTF-8'));
?>

<div class="space-y-6 max-w-6xl mx-auto">
  <!-- Top Header Banner -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-orange-600 shadow-lg text-white" style="background: linear-gradient(135deg, var(--primary) 0%, #ea580c 50%, #f59e0b 100%);">
    <div class="flex items-center gap-3.5">
      <?php if ($avatarUrl !== ''): ?>
        <img src="<?= e($avatarUrl) ?>" alt="Foto Profil" class="w-12 h-12 rounded-xl object-cover border-2 border-white/40 shrink-0 shadow-sm" />
      <?php else: ?>
        <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm border border-white/30 flex items-center justify-center text-white font-bold text-lg shrink-0 shadow-inner">
          <?= e($initial) ?>
        </div>
      <?php endif; ?>
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white">
            Profil Pengguna
          </h1>
          <span class="text-[11px] font-mono uppercase px-2 py-0.5 rounded-full bg-white/25 text-white font-bold tracking-wider">
            <?= e($roleUpper) ?>
          </span>
        </div>
        <p class="text-xs text-orange-100 mt-0.5">
          Informasi identitas kedinasan dan pengaturan foto profil akun petugas siaga.
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
    <div class="lg:col-span-7 space-y-6">
      <div class="bento-card-v1 cockpit-glass p-6 rounded-2xl border border-slate-200/80 dark:border-white/10 shadow-sm space-y-5">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
          <?php if ($avatarUrl !== ''): ?>
            <img src="<?= e($avatarUrl) ?>" alt="Foto <?= e((string)($user['nama'] ?? '')) ?>" class="w-14 h-14 rounded-2xl object-cover border border-slate-200 dark:border-slate-700 shadow-md shrink-0" />
          <?php else: ?>
            <div class="w-14 h-14 rounded-2xl bg-orange-600 text-white flex items-center justify-center font-bold text-xl shadow-md shrink-0">
              <?= e($initial) ?>
            </div>
          <?php endif; ?>
          <div class="min-w-0 flex-1">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white truncate">
              <?= e($user['nama'] ?: 'Petugas Siaga SAR') ?>
            </h2>
            <div class="flex items-center gap-1.5 mt-0.5">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
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
      </div>
    </div>

    <!-- Right Column: Profile Photo Management + Password Policy Notice (NO Self-Service Password Change for USER) -->
    <div class="lg:col-span-5 space-y-5">
      <div class="bento-card-v1 cockpit-glass p-6 rounded-2xl border border-slate-200/80 dark:border-white/10 shadow-sm space-y-5">
        <div class="pb-3.5 border-b border-slate-100 dark:border-slate-800">
          <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Foto Profil Akun</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Format JPG, PNG, atau WebP (maks. 3 MB).</p>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/profile.php" enctype="multipart/form-data" class="space-y-4">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="update_avatar" />

          <div class="flex items-center gap-4">
            <div class="w-20 h-20 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0">
              <img id="userProfileAvatarPreview" src="<?= e($avatarUrl) ?>" alt="Preview Foto Profil" class="<?= $avatarUrl !== '' ? '' : 'hidden' ?> w-full h-full object-cover" />
              <span id="userProfileAvatarInitial" class="<?= $avatarUrl !== '' ? 'hidden' : '' ?> text-2xl font-bold text-slate-500 dark:text-slate-300"><?= e($initial) ?></span>
            </div>

            <div class="flex-1 space-y-2">
              <label class="inline-flex items-center justify-center h-9 px-3.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer transition">
                <span>Pilih Foto Baru</span>
                <input type="file" id="inputUserProfileAvatar" name="avatar_file" accept="image/jpeg,image/png,image/webp" class="hidden" />
              </label>
              <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn-tactile h-9 px-4 text-xs font-bold text-white rounded-xl transition cursor-pointer" style="background-color: var(--primary);">
                  Simpan Foto
                </button>
                <?php if ($avatarUrl !== ''): ?>
                  <button type="submit" name="clear_avatar" value="1" class="h-9 px-3 rounded-xl border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-700 dark:text-rose-300 text-xs font-semibold cursor-pointer transition">
                    Hapus Foto
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </form>
      </div>

      <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 space-y-1.5">
        <div class="font-bold">Kebijakan Pengelolaan Akun &amp; Kata Sandi</div>
        <p class="leading-relaxed text-[11.5px]">
          Perubahan kata sandi dan data kedinasan (Nama, NIP, Pangkat, Jabatan) untuk akun User hanya dapat dilakukan oleh Administrator melalui menu Pengaturan Admin.
        </p>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const inp = document.getElementById('inputUserProfileAvatar');
  const img = document.getElementById('userProfileAvatarPreview');
  const ini = document.getElementById('userProfileAvatarInitial');
  if (!inp || !img) return;
  inp.addEventListener('change', function() {
    const f = inp.files && inp.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = function(ev) {
      img.src = ev.target.result;
      img.classList.remove('hidden');
      if (ini) ini.classList.add('hidden');
    };
    r.readAsDataURL(f);
  });
})();
</script>
