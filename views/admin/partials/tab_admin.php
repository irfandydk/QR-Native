<?php
/**
 * QR-SAR - Admin Tab Partial: Manajemen Terpadu Akun Admin & Pengguna (CRUD + Foto Profil + Ubah Sandi Pribadi)
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$usersList = is_array($usersList ?? null) ? $usersList : [];
$currentUser = $currentUser ?? AuthHelper::getCurrentUser();
$currentUserId = (string)($currentUser['id'] ?? '');
$activeAdminCount = 0;
foreach ($usersList as $uRow) {
    if (strtoupper((string)($uRow['role'] ?? '')) === 'ADMIN' && !empty($uRow['is_active'])) {
        $activeAdminCount++;
    }
}
?>

<div class="space-y-5">

  <!-- Section 1: Daftar Terpadu Seluruh Akun Admin & User -->
  <section class="bento-card-v1 rounded-2xl overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2.5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Akun Admin &amp; User</h2>
        <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
          <?= count($usersList) ?> Akun
        </span>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-0.5 text-[11px]" role="group" aria-label="Filter peran akun">
          <button type="button" class="acc-filter-btn px-2.5 py-1 rounded-md font-bold bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs cursor-pointer" data-role-filter="ALL">Semua</button>
          <button type="button" class="acc-filter-btn px-2.5 py-1 rounded-md font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white cursor-pointer" data-role-filter="ADMIN">Admin</button>
          <button type="button" class="acc-filter-btn px-2.5 py-1 rounded-md font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white cursor-pointer" data-role-filter="USER">User</button>
        </div>

        <button type="button" id="btnOpenAddAccount" class="adm-accent-btn inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg text-xs font-bold transition cursor-pointer">
          <span>+ Tambah Akun</span>
        </button>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse" id="accountsTable">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-slate-500 dark:text-slate-400 font-semibold text-[11px]">
            <th class="py-2.5 px-4">Profil &amp; Identitas</th>
            <th class="py-2.5 px-4">Role</th>
            <th class="py-2.5 px-4">Jabatan / Pangkat</th>
            <th class="py-2.5 px-4">Status</th>
            <th class="py-2.5 px-4">Login Terakhir</th>
            <th class="py-2.5 px-4 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
          <?php if (empty($usersList)): ?>
            <tr>
              <td colspan="6" class="py-8 text-center text-slate-400">Belum ada akun pengguna terdaftar.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($usersList as $u):
              $uid = (string)($u['id'] ?? '');
              $uRole = strtoupper((string)($u['role'] ?? 'USER'));
              $uNama = (string)($u['nama'] ?? '');
              $uUsername = (string)($u['username'] ?? '');
              $uAvatarUrl = UserModel::resolveAvatarDisplayUrl((string)($u['avatar_url'] ?? ''));
              $uInitial = strtoupper(mb_substr($uNama !== '' ? $uNama : ($uUsername !== '' ? $uUsername : 'U'), 0, 1, 'UTF-8'));
              $isSelf = ($uid !== '' && $uid === $currentUserId);
              $isLastActiveAdmin = ($uRole === 'ADMIN' && !empty($u['is_active']) && $activeAdminCount <= 1);
              $canDelete = !$isSelf && !$isLastActiveAdmin;
              $rowJson = json_encode([
                'id' => $uid,
                'username' => $uUsername,
                'nama' => $uNama,
                'nip' => (string)($u['nip'] ?? ''),
                'pangkat' => (string)($u['pangkat'] ?? ''),
                'jabatan' => (string)($u['jabatan'] ?? ''),
                'email' => (string)($u['email'] ?? ''),
                'no_hp' => (string)($u['no_hp'] ?? ''),
                'role' => $uRole,
                'is_active' => !empty($u['is_active']),
                'avatar_url' => $uAvatarUrl,
                'is_self' => $isSelf,
              ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            ?>
              <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors" data-account-row="<?= e($uid) ?>" data-account-role="<?= e($uRole) ?>">
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3 min-w-0">
                    <?php if ($uAvatarUrl !== ''): ?>
                      <img src="<?= e($uAvatarUrl) ?>" alt="Foto <?= e($uNama) ?>" class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shrink-0" />
                    <?php else: ?>
                      <div class="w-9 h-9 rounded-xl <?= $uRole === 'ADMIN' ? 'bg-orange-600' : 'bg-sky-600' ?> text-white flex items-center justify-center font-bold text-xs shrink-0">
                        <?= e($uInitial) ?>
                      </div>
                    <?php endif; ?>
                    <div class="min-w-0">
                      <div class="flex items-center gap-1.5">
                        <span class="font-bold text-slate-900 dark:text-white truncate"><?= e($uNama) ?></span>
                        <?php if ($isSelf): ?>
                          <span class="px-1.5 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold shrink-0">SAYA</span>
                        <?php endif; ?>
                      </div>
                      <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400">@<?= e($uUsername) ?><?= !empty($u['nip']) ? ' &bull; ' . e((string)$u['nip']) : '' ?></div>
                    </div>
                  </div>
                </td>

                <td class="py-3 px-4 whitespace-nowrap">
                  <?php if ($uRole === 'ADMIN'): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10.5px] font-mono font-bold bg-orange-500/15 text-orange-700 dark:text-orange-300 border border-orange-500/30">
                      ADMIN
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10.5px] font-mono font-semibold bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30">
                      USER
                    </span>
                  <?php endif; ?>
                </td>

                <td class="py-3 px-4">
                  <div class="text-slate-800 dark:text-slate-200 font-medium truncate max-w-[220px]"><?= e($u['jabatan'] ?: '-') ?></div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[220px]"><?= e($u['pangkat'] ?: '-') ?></div>
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
                  <?= !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime((string)$u['last_login_at'])) : 'Belum pernah' ?>
                </td>

                <td class="py-3 px-4 whitespace-nowrap text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button type="button"
                      class="adm-ghost-btn h-7 px-2.5 rounded-lg text-[11px] font-semibold transition cursor-pointer btn-edit-account"
                      data-account-json="<?= e((string)$rowJson) ?>">
                      Edit
                    </button>

                    <?php if ($canDelete): ?>
                      <form action="<?= BASE_URL ?>/admin.php?tab=admin&action=delete_account" method="POST" class="inline form-delete-account" data-delete-name="<?= e($uNama . ' (@' . $uUsername . ')') ?>">
                        <?= csrfInput() ?>
                        <input type="hidden" name="account_id" value="<?= e($uid) ?>" />
                        <button type="submit" class="h-7 px-2.5 rounded-lg border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-300 text-[11px] font-semibold transition cursor-pointer">
                          Hapus
                        </button>
                      </form>
                    <?php else: ?>
                      <button type="button" disabled class="h-7 px-2.5 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 text-[11px] font-semibold opacity-50 cursor-not-allowed" title="<?= $isSelf ? 'Akun yang sedang login tidak dapat dihapus' : 'Administrator aktif terakhir tidak dapat dihapus' ?>">
                        Hapus
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Section 2: Form Tambah / Edit Akun + Kartu Ubah Sandi Admin Login -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

    <!-- Form Tambah / Edit Akun Admin & User (2 Kolom) -->
    <section id="accountFormSection" class="lg:col-span-2 bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
      <div class="flex items-center justify-between gap-2 pb-3.5 border-b border-slate-100 dark:border-slate-800">
        <h2 id="accountFormTitle" class="text-sm font-bold text-slate-900 dark:text-white">Tambah Akun Baru</h2>
        <button type="button" id="btnCancelEditAccount" class="hidden h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
          Batal Edit
        </button>
      </div>

      <form id="formManageAccount" action="<?= BASE_URL ?>/admin.php?tab=admin&action=create_account" method="POST" enctype="multipart/form-data" class="space-y-4">
        <?= csrfInput() ?>
        <input type="hidden" id="inputAccountId" name="account_id" value="" />

        <!-- Foto Profil Upload + Live Preview -->
        <div class="flex flex-wrap items-center gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="w-16 h-16 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0">
            <img id="accountAvatarPreviewImg" src="" alt="Preview Foto Profil" class="hidden w-full h-full object-cover" />
            <span id="accountAvatarPreviewInitial" class="text-lg font-bold text-slate-500 dark:text-slate-300">U</span>
          </div>
          <div class="flex-1 min-w-[200px] space-y-1.5">
            <div class="text-xs font-bold text-slate-900 dark:text-white">Foto Profil</div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="adm-ghost-btn inline-flex items-center h-8 px-3 rounded-lg text-xs font-semibold cursor-pointer transition">
                <span>Pilih Foto</span>
                <input type="file" id="inputAccountAvatar" name="account_avatar" accept="image/jpeg,image/png,image/webp" class="hidden" />
              </label>
              <label id="wrapClearAccountAvatar" class="hidden inline-flex items-center gap-1.5 text-[11px] font-semibold text-rose-600 dark:text-rose-400 cursor-pointer">
                <input type="checkbox" id="chkClearAccountAvatar" name="clear_account_avatar" value="1" class="rounded border-slate-300 dark:border-slate-700" />
                <span>Hapus foto</span>
              </label>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label for="inputAccountNama" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Nama Lengkap <span class="text-rose-500">*</span></label>
            <input id="inputAccountNama" name="account_nama" type="text" required maxlength="191" placeholder="Nama lengkap & gelar" class="control control-sm w-full font-semibold" />
          </div>

          <div class="space-y-1.5">
            <label for="selectAccountRole" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Role Akses <span class="text-rose-500">*</span></label>
            <select id="selectAccountRole" name="account_role" class="control control-sm w-full font-semibold cursor-pointer">
              <option value="USER">USER (Petugas Siaga / Operator)</option>
              <option value="ADMIN">ADMIN (Administrator)</option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label for="inputAccountUsername" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Username Login <span class="text-rose-500">*</span></label>
            <input id="inputAccountUsername" name="account_username" type="text" required maxlength="50" autocomplete="off" placeholder="Contoh: petugas1" class="control control-sm w-full font-mono font-bold" />
          </div>

          <div class="space-y-1.5">
            <label for="inputAccountPassword" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Kata Sandi <span id="accountPassReqMark" class="text-rose-500">*</span></label>
            <input id="inputAccountPassword" name="account_password" type="password" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter" class="control control-sm w-full" />
          </div>

          <div class="space-y-1.5">
            <label for="inputAccountNip" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">NIP</label>
            <input id="inputAccountNip" name="account_nip" type="text" maxlength="50" placeholder="199501012020121001" class="control control-sm w-full font-mono" />
          </div>

          <div class="space-y-1.5">
            <label for="inputAccountPangkat" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Pangkat / Golongan</label>
            <input id="inputAccountPangkat" name="account_pangkat" type="text" maxlength="100" placeholder="PENGATUR (II/c)" class="control control-sm w-full" />
          </div>

          <div class="sm:col-span-2 space-y-1.5">
            <label for="inputAccountJabatan" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Jabatan Kedinasan</label>
            <input id="inputAccountJabatan" name="account_jabatan" type="text" maxlength="191" placeholder="Operator Komunikasi & Petugas Siaga SAR" class="control control-sm w-full" />
          </div>

          <div class="space-y-1.5">
            <label for="inputAccountEmail" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Email</label>
            <input id="inputAccountEmail" name="account_email" type="email" maxlength="191" placeholder="nama@basarnas.go.id" class="control control-sm w-full" />
          </div>

          <div class="space-y-1.5">
            <label for="inputAccountNoHp" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Nomor HP / WA</label>
            <input id="inputAccountNoHp" name="account_no_hp" type="text" maxlength="50" placeholder="0811-4091-115" class="control control-sm w-full font-mono" />
          </div>
        </div>

        <div class="pt-1">
          <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer">
            <input type="checkbox" id="chkAccountActive" name="account_is_active" value="1" checked class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-orange-600 cursor-pointer" />
            <span>Akun Aktif (dapat login)</span>
          </label>
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
          <button type="submit" id="btnSubmitAccount" class="adm-accent-btn inline-flex items-center justify-center h-9 px-5 rounded-xl font-bold text-xs transition cursor-pointer">
            Tambah Akun
          </button>
        </div>
      </form>
    </section>

    <!-- Kolom Kanan: Ubah Kata Sandi Admin yang Sedang Login -->
    <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
      <div class="pb-3.5 border-b border-slate-100 dark:border-slate-800">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Ubah Kata Sandi Saya</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Khusus akun Admin yang sedang login (@<?= e((string)($currentUser['username'] ?? 'admin')) ?>).</p>
      </div>

      <form action="<?= BASE_URL ?>/admin.php?tab=admin&action=change_admin_pass" method="POST" class="space-y-3.5">
        <?= csrfInput() ?>
        <div class="space-y-1.5">
          <label for="adminOldPass" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Kata Sandi Saat Ini <span class="text-rose-500">*</span></label>
          <input id="adminOldPass" type="password" name="old_password" required placeholder="Masukkan kata sandi lama" class="control control-sm w-full" />
        </div>

        <div class="space-y-1.5">
          <label for="adminNewPass" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Kata Sandi Baru <span class="text-rose-500">*</span></label>
          <input id="adminNewPass" type="password" name="new_password" required minlength="8" placeholder="Minimal 8 karakter" class="control control-sm w-full" />
        </div>

        <div class="space-y-1.5">
          <label for="adminConfirmPass" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Konfirmasi Sandi Baru <span class="text-rose-500">*</span></label>
          <input id="adminConfirmPass" type="password" name="confirm_password" required minlength="8" placeholder="Ulangi kata sandi baru" class="control control-sm w-full" />
        </div>

        <div class="pt-1">
          <button type="submit" class="w-full h-9 inline-flex items-center justify-center px-4 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white text-white dark:text-slate-900 font-bold text-xs transition cursor-pointer">
            Perbarui Kata Sandi
          </button>
        </div>
      </form>
    </section>

  </div>
</div>

<script>
(function() {
  const CREATE_URL = <?= jsonSafe(BASE_URL . '/admin.php?tab=admin&action=create_account') ?>;
  const UPDATE_URL = <?= jsonSafe(BASE_URL . '/admin.php?tab=admin&action=update_account') ?>;

  const form = document.getElementById('formManageAccount');
  const formTitle = document.getElementById('accountFormTitle');
  const btnCancelEdit = document.getElementById('btnCancelEditAccount');
  const btnOpenAdd = document.getElementById('btnOpenAddAccount');
  const btnSubmit = document.getElementById('btnSubmitAccount');
  const inputId = document.getElementById('inputAccountId');
  const inputNama = document.getElementById('inputAccountNama');
  const selectRole = document.getElementById('selectAccountRole');
  const inputUsername = document.getElementById('inputAccountUsername');
  const inputPassword = document.getElementById('inputAccountPassword');
  const passReqMark = document.getElementById('accountPassReqMark');
  const inputNip = document.getElementById('inputAccountNip');
  const inputPangkat = document.getElementById('inputAccountPangkat');
  const inputJabatan = document.getElementById('inputAccountJabatan');
  const inputEmail = document.getElementById('inputAccountEmail');
  const inputNoHp = document.getElementById('inputAccountNoHp');
  const chkActive = document.getElementById('chkAccountActive');
  const inputAvatar = document.getElementById('inputAccountAvatar');
  const avatarImg = document.getElementById('accountAvatarPreviewImg');
  const avatarInitial = document.getElementById('accountAvatarPreviewInitial');
  const wrapClearAvatar = document.getElementById('wrapClearAccountAvatar');
  const chkClearAvatar = document.getElementById('chkClearAccountAvatar');

  function setAvatarPreview(url, nameStr) {
    const ch = ((nameStr || 'U').trim().charAt(0) || 'U').toUpperCase();
    if (avatarInitial) avatarInitial.textContent = ch;
    if (url) {
      avatarImg.src = url;
      avatarImg.classList.remove('hidden');
      avatarInitial.classList.add('hidden');
    } else {
      avatarImg.src = '';
      avatarImg.classList.add('hidden');
      avatarInitial.classList.remove('hidden');
    }
  }

  function resetToCreateMode() {
    form.action = CREATE_URL;
    inputId.value = '';
    inputNama.value = '';
    selectRole.value = 'USER';
    inputUsername.value = '';
    inputPassword.value = '';
    inputPassword.required = true;
    inputPassword.placeholder = 'Minimal 8 karakter';
    passReqMark.classList.remove('hidden');
    inputNip.value = '';
    inputPangkat.value = '';
    inputJabatan.value = '';
    inputEmail.value = '';
    inputNoHp.value = '';
    chkActive.checked = true;
    inputAvatar.value = '';
    chkClearAvatar.checked = false;
    wrapClearAvatar.classList.add('hidden');
    setAvatarPreview('', 'U');
    formTitle.textContent = 'Tambah Akun Baru';
    btnSubmit.textContent = 'Tambah Akun';
    btnCancelEdit.classList.add('hidden');
  }

  function loadAccountIntoEdit(acc) {
    form.action = UPDATE_URL;
    inputId.value = acc.id || '';
    inputNama.value = acc.nama || '';
    selectRole.value = acc.role === 'ADMIN' ? 'ADMIN' : 'USER';
    inputUsername.value = acc.username || '';
    inputPassword.value = '';
    inputPassword.required = false;
    inputPassword.placeholder = 'Kosongkan jika sandi tidak diubah';
    passReqMark.classList.add('hidden');
    inputNip.value = acc.nip || '';
    inputPangkat.value = acc.pangkat || '';
    inputJabatan.value = acc.jabatan || '';
    inputEmail.value = acc.email || '';
    inputNoHp.value = acc.no_hp || '';
    chkActive.checked = !!acc.is_active;
    inputAvatar.value = '';
    chkClearAvatar.checked = false;
    setAvatarPreview(acc.avatar_url || '', acc.nama || acc.username || 'U');
    wrapClearAvatar.classList.toggle('hidden', !acc.avatar_url);
    formTitle.textContent = 'Edit Akun: ' + (acc.nama || acc.username || '');
    btnSubmit.textContent = 'Simpan Perubahan';
    btnCancelEdit.classList.remove('hidden');
    document.getElementById('accountFormSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    inputNama.focus();
  }

  if (btnOpenAdd) {
    btnOpenAdd.addEventListener('click', function() {
      resetToCreateMode();
      document.getElementById('accountFormSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      inputNama.focus();
    });
  }
  if (btnCancelEdit) btnCancelEdit.addEventListener('click', resetToCreateMode);

  if (inputAvatar) {
    inputAvatar.addEventListener('change', function() {
      const f = inputAvatar.files && inputAvatar.files[0];
      if (!f) return;
      const reader = new FileReader();
      reader.onload = function(ev) {
        setAvatarPreview(ev.target.result, inputNama.value || 'U');
        chkClearAvatar.checked = false;
      };
      reader.readAsDataURL(f);
    });
  }

  document.querySelectorAll('.btn-edit-account').forEach(function(btn) {
    btn.addEventListener('click', function() {
      try {
        loadAccountIntoEdit(JSON.parse(btn.getAttribute('data-account-json') || '{}'));
      } catch (e) {}
    });
  });

  document.querySelectorAll('.form-delete-account').forEach(function(frm) {
    frm.addEventListener('submit', function(ev) {
      const nm = frm.getAttribute('data-delete-name') || 'akun ini';
      if (!window.confirm('Hapus ' + nm + ' secara permanen?')) {
        ev.preventDefault();
      }
    });
  });

  document.querySelectorAll('.acc-filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const target = btn.getAttribute('data-role-filter') || 'ALL';
      document.querySelectorAll('.acc-filter-btn').forEach(function(b) {
        const active = b === btn;
        b.className = active
          ? 'acc-filter-btn px-2.5 py-1 rounded-md font-bold bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs cursor-pointer'
          : 'acc-filter-btn px-2.5 py-1 rounded-md font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white cursor-pointer';
      });
      document.querySelectorAll('[data-account-row]').forEach(function(tr) {
        const r = tr.getAttribute('data-account-role') || '';
        tr.classList.toggle('hidden', target !== 'ALL' && r !== target);
      });
    });
  });

  resetToCreateMode();
})();
</script>
