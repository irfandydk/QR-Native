<?php
/**
 * QR-SAR - User Profile & Avatar View (Clean Tactical Minimalist)
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

<div class="max-w-7xl mx-auto space-y-5">
  <h1 class="sr-only">Profil Pengguna</h1>

  <!-- Clean Page Context Strip (Selaras dengan Detail Operasi & Admin, bebas banner gradien lama) -->
  <div class="bento-card-v1 rounded-2xl px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center gap-3.5 min-w-0">
      <?php if ($avatarUrl !== ''): ?>
        <img src="<?= e($avatarUrl) ?>" alt="Foto profil <?= e((string)($user['nama'] ?? '')) ?>" class="w-12 h-12 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shrink-0" />
      <?php else: ?>
        <div class="w-12 h-12 rounded-xl text-white flex items-center justify-center font-bold text-lg shrink-0" style="background-color: var(--primary);">
          <?= e($initial) ?>
        </div>
      <?php endif; ?>
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 dark:text-white truncate">
            <?= e($user['nama'] ?: 'Petugas Siaga SAR') ?>
          </h2>
          <span class="text-[10.5px] font-mono font-bold uppercase px-2 py-0.5 rounded-md bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30">
            <?= e($roleUpper) ?>
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
          <?= e($roleTitle) ?> &bull; <span class="font-mono">@<?= e($user['username'] ?? 'user') ?></span>
        </p>
      </div>
    </div>

    <div class="flex items-center gap-2 shrink-0">
      <a href="<?= BASE_URL ?>/index.php" class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800/80 hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 transition">
        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Kembali ke Dashboard</span>
      </a>
    </div>
  </div>

  <!-- Content Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
    <!-- Left Column: User Identity Card -->
    <section class="lg:col-span-7 bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-5">
      <div class="pb-3.5 border-b border-slate-100 dark:border-slate-800">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Identitas Kedinasan</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Data kepegawaian yang terdaftar pada sistem QR-SAR KPP Kendari.</p>
      </div>

      <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
        <div class="sm:col-span-2 space-y-1">
          <dt class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Nama Pengguna (Username)</dt>
          <dd class="font-mono font-bold text-slate-900 dark:text-slate-100 px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200/80 dark:border-slate-800">
            @<?= e($user['username'] ?? 'user') ?>
          </dd>
        </div>

        <div class="space-y-1">
          <dt class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">NIP</dt>
          <dd class="font-mono font-medium text-slate-800 dark:text-slate-200 px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200/80 dark:border-slate-800 truncate">
            <?= e($user['nip'] ?: '-') ?>
          </dd>
        </div>

        <div class="space-y-1">
          <dt class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Pangkat / Golongan</dt>
          <dd class="font-medium text-slate-800 dark:text-slate-200 px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200/80 dark:border-slate-800 truncate">
            <?= e($user['pangkat'] ?: '-') ?>
          </dd>
        </div>

        <div class="sm:col-span-2 space-y-1">
          <dt class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Jabatan Kedinasan</dt>
          <dd class="font-medium text-slate-800 dark:text-slate-200 px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200/80 dark:border-slate-800">
            <?= e($user['jabatan'] ?: 'Operator Komunikasi & Petugas Siaga SAR') ?>
          </dd>
        </div>

        <div class="space-y-1">
          <dt class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Alamat Email</dt>
          <dd class="font-medium text-slate-800 dark:text-slate-200 px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200/80 dark:border-slate-800 truncate">
            <?= e($user['email'] ?: '-') ?>
          </dd>
        </div>

        <div class="space-y-1">
          <dt class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">No. Handphone / WA</dt>
          <dd class="font-mono font-medium text-slate-800 dark:text-slate-200 px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200/80 dark:border-slate-800 truncate">
            <?= e($user['no_hp'] ?: '-') ?>
          </dd>
        </div>
      </dl>
    </section>

    <!-- Right Column: Profile Photo Management + Password Policy Notice (NO Self-Service Password Change for USER) -->
    <div class="lg:col-span-5 space-y-5">
      <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-5">
        <div class="pb-3.5 border-b border-slate-100 dark:border-slate-800">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">Foto Profil Akun</h2>
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

            <div class="flex-1 space-y-2.5 min-w-0">
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
      </section>

      <div class="rounded-2xl border border-amber-300/60 dark:border-amber-700/50 bg-amber-50/70 dark:bg-amber-950/25 p-4 space-y-1">
        <div class="text-xs font-bold text-amber-900 dark:text-amber-200">Kebijakan Pengelolaan Akun &amp; Kata Sandi</div>
        <p class="text-xs text-amber-800/90 dark:text-amber-300/90 leading-relaxed">
          Perubahan kata sandi dan data kedinasan (Nama, NIP, Pangkat, Jabatan) untuk akun User hanya dapat dilakukan oleh Administrator melalui menu Pengaturan Admin.
        </p>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var inp = document.getElementById('inputUserProfileAvatar');
  var img = document.getElementById('userProfileAvatarPreview');
  var initEl = document.getElementById('userProfileAvatarInitial');
  if (!inp || !img) return;
  inp.addEventListener('change', function() {
    var f = inp.files && inp.files[0];
    if (!f) return;
    var reader = new FileReader();
    reader.onload = function(ev) {
      img.src = String(ev.target && ev.target.result ? ev.target.result : '');
      img.classList.remove('hidden');
      if (initEl) initEl.classList.add('hidden');
    };
    reader.readAsDataURL(f);
  });
})();
</script>
