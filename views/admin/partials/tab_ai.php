<?php
/**
 * QR-SAR - Admin Tab Partial: Pengaturan Multi-API Key & Model Asisten AI (Gemini & DeepSeek)
 * Berlaku untuk penyusunan & perapian teks AI pada Laporan Awal dan LAPBANG.
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$aiKeys = is_array($aiConfig['keys'] ?? null) ? $aiConfig['keys'] : [];
$aiProviders = is_array($aiConfig['providers'] ?? null) ? $aiConfig['providers'] : AiConfigService::PROVIDERS;
$defaultKeyId = (string)($aiConfig['default_key_id'] ?? '');
$autoFallback = !array_key_exists('auto_fallback', $aiConfig) || (bool)$aiConfig['auto_fallback'];
$activeDefault = $aiConfig['active_default'] ?? null;
$envFallbacks = is_array($aiConfig['env_fallbacks'] ?? null) ? $aiConfig['env_fallbacks'] : [];
?>

<div class="space-y-5">
  <!-- Section 1: Ringkasan Default Aktif & Auto-Fallback -->
  <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3.5 border-b border-slate-100 dark:border-slate-800">
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Pengaturan Default Asisten AI</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Menentukan API Key dan model utama untuk proses teks AI pada Laporan Awal dan LAPBANG.</p>
      </div>
      <?php if (is_array($activeDefault)): ?>
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-xs">
          <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
          <span class="font-bold text-emerald-700 dark:text-emerald-300"><?= e((string)$activeDefault['provider_label']) ?></span>
          <span class="font-mono text-[11px] text-slate-600 dark:text-slate-300"><?= e((string)$activeDefault['model']) ?></span>
        </div>
      <?php else: ?>
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/25 text-xs text-amber-700 dark:text-amber-300 font-semibold">
          <span><?= !empty($envFallbacks) ? 'Memakai Cadangan ' . e(implode(', ', $envFallbacks)) : 'Belum Ada API Key Aktif' ?></span>
        </div>
      <?php endif; ?>
    </div>

    <form action="<?= BASE_URL ?>/admin.php?tab=ai&action=save_ai_preferences" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-end">
      <?= csrfInput() ?>
      <div class="lg:col-span-6 space-y-1.5">
        <label for="aiDefaultKeySelect" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">API Key &amp; Model Default</label>
        <select id="aiDefaultKeySelect" name="default_key_id" class="control control-sm w-full font-semibold cursor-pointer" <?= empty($aiKeys) ? 'disabled' : '' ?>>
          <?php if (empty($aiKeys)): ?>
            <option value="">Tambahkan minimal 1 API Key di bawah</option>
          <?php else: ?>
            <?php foreach ($aiKeys as $k): ?>
              <option value="<?= e((string)$k['id']) ?>" <?= $k['id'] === $defaultKeyId ? 'selected' : '' ?>>
                [<?= e((string)$k['provider_badge']) ?>] <?= e((string)$k['label']) ?> &mdash; <?= e((string)$k['model']) ?><?= empty($k['enabled']) ? ' (Nonaktif)' : '' ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>

      <div class="lg:col-span-4 flex items-center h-9">
        <label class="inline-flex items-center gap-2.5 text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer select-none">
          <input type="checkbox" id="chkAiAutoFallback" name="auto_fallback" value="1" <?= $autoFallback ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-orange-600 cursor-pointer" />
          <span>Auto-Fallback ke API Key cadangan jika gagal</span>
        </label>
      </div>

      <div class="lg:col-span-2 flex justify-end">
        <button type="submit" id="btnSaveAiPrefs" class="adm-accent-btn w-full sm:w-auto inline-flex items-center justify-center h-9 px-4 rounded-xl font-bold text-xs transition cursor-pointer">
          Simpan Default
        </button>
      </div>
    </form>
  </section>

  <!-- Section 2: Daftar API Key Tersimpan -->
  <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2 pb-3.5 border-b border-slate-100 dark:border-slate-800">
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Daftar API Key Tersimpan</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Simpan lebih dari satu kunci (Google Gemini dan DeepSeek). Kunci dienkripsi aman di basis data.</p>
      </div>
      <button type="button" id="btnResetAiKeyForm" class="adm-ghost-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold transition cursor-pointer">
        <span>+ Tambah API Key Baru</span>
      </button>
    </div>

    <?php if (empty($aiKeys)): ?>
      <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-6 text-center space-y-1">
        <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Belum ada profil API Key tersimpan</p>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Gunakan formulir di bawah untuk menambahkan API Key Google Gemini atau DeepSeek Anda.</p>
      </div>
    <?php else: ?>
      <div id="aiKeyListContainer" class="space-y-2.5">
        <?php foreach ($aiKeys as $k):
          $isDef = !empty($k['is_default']);
          $isOn = !empty($k['enabled']);
          $provBadgeCls = $k['provider'] === 'deepseek'
            ? 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-500/30'
            : 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30';
        ?>
          <div class="rounded-xl border <?= $isDef ? 'border-orange-500/60 bg-orange-500/[0.04]' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40' ?> p-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3" data-ai-key-card="<?= e((string)$k['id']) ?>">
            <div class="min-w-0 space-y-1">
              <div class="flex flex-wrap items-center gap-1.5">
                <span class="px-2 py-0.5 rounded-md border text-[10px] font-mono font-bold <?= $provBadgeCls ?>"><?= e((string)$k['provider_badge']) ?></span>
                <span class="text-xs font-bold text-slate-900 dark:text-white"><?= e((string)$k['label']) ?></span>
                <?php if ($isDef): ?>
                  <span class="px-2 py-0.5 rounded-md bg-orange-600 text-white text-[10px] font-bold tracking-wide">DEFAULT</span>
                <?php endif; ?>
                <?php if (!$isOn): ?>
                  <span class="px-2 py-0.5 rounded-md bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-bold">NONAKTIF</span>
                <?php endif; ?>
              </div>
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-mono text-slate-500 dark:text-slate-400">
                <span>Model: <strong class="text-slate-700 dark:text-slate-200"><?= e((string)$k['model']) ?></strong></span>
                <span>Kunci: <strong class="text-slate-700 dark:text-slate-200"><?= e((string)$k['masked_key']) ?></strong></span>
                <span class="ai-test-status text-[11px] font-sans" data-test-status-for="<?= e((string)$k['id']) ?>"></span>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 shrink-0">
              <?php if (!$isDef): ?>
                <form action="<?= BASE_URL ?>/admin.php?tab=ai&action=set_default_ai_key" method="POST" class="inline">
                  <?= csrfInput() ?>
                  <input type="hidden" name="ai_key_id" value="<?= e((string)$k['id']) ?>" />
                  <button type="submit" class="h-8 px-2.5 rounded-lg border border-orange-500/40 bg-orange-500/10 hover:bg-orange-500/20 text-orange-700 dark:text-orange-300 text-[11px] font-bold transition cursor-pointer">
                    Jadikan Default
                  </button>
                </form>
              <?php endif; ?>

              <button type="button"
                class="adm-ghost-btn h-8 px-2.5 rounded-lg text-[11px] font-semibold transition cursor-pointer btn-test-saved-ai-key"
                data-key-id="<?= e((string)$k['id']) ?>">
                Uji Koneksi
              </button>

              <button type="button"
                class="adm-ghost-btn h-8 px-2.5 rounded-lg text-[11px] font-semibold transition cursor-pointer btn-edit-ai-key"
                data-key-json="<?= e(json_encode([
                  'id' => $k['id'],
                  'label' => $k['label'],
                  'provider' => $k['provider'],
                  'model' => $k['model'],
                  'enabled' => $k['enabled'],
                  'is_default' => $k['is_default'],
                  'masked_key' => $k['masked_key'],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">
                Edit
              </button>

              <form action="<?= BASE_URL ?>/admin.php?tab=ai&action=delete_ai_key" method="POST" class="inline form-delete-ai-key" data-delete-label="<?= e((string)$k['label']) ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="ai_key_id" value="<?= e((string)$k['id']) ?>" />
                <button type="submit" class="h-8 px-2.5 rounded-lg border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-300 text-[11px] font-semibold transition cursor-pointer">
                  Hapus
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Section 3: Formulir Tambah / Edit API Key -->
  <section id="aiKeyFormSection" class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
    <div class="flex items-center justify-between gap-2 pb-3.5 border-b border-slate-100 dark:border-slate-800">
      <h2 id="aiFormHeading" class="text-sm font-bold text-slate-900 dark:text-white">Tambah API Key AI</h2>
      <button type="button" id="btnCancelEditAiKey" class="hidden h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
        Batal Edit
      </button>
    </div>

    <form id="formSaveAiKey" action="<?= BASE_URL ?>/admin.php?tab=ai&action=save_ai_key" method="POST" class="space-y-4">
      <?= csrfInput() ?>
      <input type="hidden" id="inputAiKeyId" name="ai_key_id" value="" />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <label for="inputAiLabel" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Nama / Label Profil <span class="text-rose-500">*</span></label>
          <input id="inputAiLabel" type="text" name="ai_label" required maxlength="80" placeholder="Contoh: DeepSeek Utama atau Gemini KPP" class="control control-sm w-full font-semibold" />
        </div>

        <div class="space-y-1.5">
          <label for="selectAiProvider" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Provider AI <span class="text-rose-500">*</span></label>
          <select id="selectAiProvider" name="ai_provider" class="control control-sm w-full font-semibold cursor-pointer">
            <?php foreach ($aiProviders as $pKey => $pMeta): ?>
              <option value="<?= e($pKey) ?>"><?= e((string)$pMeta['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="space-y-1.5">
          <div class="flex items-center justify-between gap-2">
            <label for="selectAiModelPreset" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Model AI <span class="text-rose-500">*</span></label>
            <span id="aiModelsLiveBadge" class="hidden text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"></span>
          </div>
          <div class="flex items-center gap-2">
            <select id="selectAiModelPreset" name="ai_model_preset" class="control control-sm flex-1 min-w-0 font-mono text-xs font-semibold cursor-pointer"></select>
            <button type="button" id="btnFetchAiModels" class="adm-ghost-btn h-9 px-2.5 rounded-lg text-[11px] font-semibold shrink-0 cursor-pointer" title="Ambil seluruh daftar model langsung dari API Key Anda">
              Muat Model API
            </button>
          </div>
        </div>

        <div id="wrapAiModelCustom" class="space-y-1.5 hidden">
          <label for="inputAiModelCustom" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">ID Model Kustom <span class="text-rose-500">*</span></label>
          <input id="inputAiModelCustom" type="text" name="ai_model_custom" maxlength="80" placeholder="Contoh: gemini-3.8-flash / deepseek-chat" class="control control-sm w-full font-mono text-xs" />
        </div>

        <div class="sm:col-span-2 space-y-1.5">
          <label for="inputAiApiKey" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">API Key <span id="aiKeyRequiredMark" class="text-rose-500">*</span></label>
          <div class="flex items-center gap-2">
            <input id="inputAiApiKey" type="password" name="ai_api_key" autocomplete="new-password" placeholder="AIzaSy..." class="control control-sm flex-1 font-mono text-xs" />
            <button type="button" id="btnToggleAiKeyVis" class="adm-ghost-btn h-9 px-3 rounded-lg text-xs font-semibold shrink-0 cursor-pointer">Lihat</button>
          </div>
          <p id="aiKeyEditHint" class="hidden text-[11px] text-slate-500 dark:text-slate-400 mt-1">Kosongkan bila tidak ingin mengganti API Key yang tersimpan.</p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-5 pt-1">
        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer">
          <input type="checkbox" id="chkAiSetDefault" name="ai_set_default" value="1" <?= empty($aiKeys) ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-orange-600 cursor-pointer" />
          <span>Jadikan API Key Default</span>
        </label>

        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer">
          <input type="checkbox" id="chkAiEnabled" name="ai_enabled" value="1" checked class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-orange-600 cursor-pointer" />
          <span>Aktif (tersedia untuk default / cadangan)</span>
        </label>
      </div>

      <div id="aiFormTestResult" class="hidden rounded-xl px-3.5 py-2.5 text-xs font-semibold"></div>

      <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-end gap-2">
        <button type="button" id="btnTestFormAiKey" class="adm-ghost-btn inline-flex items-center justify-center h-9 px-4 rounded-xl text-xs font-semibold transition cursor-pointer">
          Uji Koneksi
        </button>
        <button type="submit" id="btnSubmitAiKey" class="adm-accent-btn inline-flex items-center justify-center h-9 px-5 rounded-xl font-bold text-xs transition cursor-pointer">
          Simpan API Key
        </button>
      </div>
    </form>
  </section>
</div>

<script>
(function() {
  const PROVIDERS = <?= jsonSafe($aiProviders) ?>;
  const TEST_ENDPOINT = <?= jsonSafe(BASE_URL . '/api/index.php?path=/ai/test-key') ?>;
  const LIST_MODELS_ENDPOINT = <?= jsonSafe(BASE_URL . '/api/index.php?path=/ai/list-models') ?>;

  const formHeading = document.getElementById('aiFormHeading');
  const btnCancelEdit = document.getElementById('btnCancelEditAiKey');
  const btnResetNew = document.getElementById('btnResetAiKeyForm');
  const inputId = document.getElementById('inputAiKeyId');
  const inputLabel = document.getElementById('inputAiLabel');
  const selectProvider = document.getElementById('selectAiProvider');
  const selectModelPreset = document.getElementById('selectAiModelPreset');
  const btnFetchModels = document.getElementById('btnFetchAiModels');
  const liveModelsBadge = document.getElementById('aiModelsLiveBadge');
  const wrapModelCustom = document.getElementById('wrapAiModelCustom');
  const inputModelCustom = document.getElementById('inputAiModelCustom');
  const inputApiKey = document.getElementById('inputAiApiKey');
  const btnToggleVis = document.getElementById('btnToggleAiKeyVis');
  const keyReqMark = document.getElementById('aiKeyRequiredMark');
  const keyEditHint = document.getElementById('aiKeyEditHint');
  const chkDefault = document.getElementById('chkAiSetDefault');
  const chkEnabled = document.getElementById('chkAiEnabled');
  const btnSubmit = document.getElementById('btnSubmitAiKey');
  const btnTestForm = document.getElementById('btnTestFormAiKey');
  const formTestResult = document.getElementById('aiFormTestResult');

  function getCsrfToken() {
    const inp = document.querySelector('#formSaveAiKey input[name="csrf_token"]');
    return (inp && inp.value) || window.CSRF_TOKEN || '';
  }

  function renderModelOptions(listItems, selectedModel, isLive) {
    if (!selectModelPreset) return;
    selectModelPreset.innerHTML = '';
    let matched = false;

    listItems.forEach(function(item) {
      const opt = document.createElement('option');
      opt.value = item.id;
      opt.textContent = item.label && item.label !== item.id ? (item.id + ' - ' + item.label) : item.id;
      if (selectedModel && selectedModel === item.id) {
        opt.selected = true;
        matched = true;
      }
      selectModelPreset.appendChild(opt);
    });

    const customOpt = document.createElement('option');
    customOpt.value = '__custom__';
    customOpt.textContent = 'Model Kustom (ketik manual)...';
    if (selectedModel && !matched) {
      customOpt.selected = true;
    }
    selectModelPreset.appendChild(customOpt);

    if (selectedModel && !matched) {
      wrapModelCustom.classList.remove('hidden');
      inputModelCustom.value = selectedModel;
    } else {
      wrapModelCustom.classList.add('hidden');
      inputModelCustom.value = '';
    }

    if (liveModelsBadge) {
      if (isLive && listItems.length > 0) {
        liveModelsBadge.textContent = listItems.length + ' model live API';
        liveModelsBadge.classList.remove('hidden');
      } else {
        liveModelsBadge.classList.add('hidden');
      }
    }
  }

  function populateModels(provider, selectedModel) {
    const p = PROVIDERS[provider] || PROVIDERS.gemini;
    if (!p) return;
    const models = p.models || {};
    const items = Object.keys(models).map(function(mId) {
      return { id: mId, label: models[mId] };
    });
    renderModelOptions(items, selectedModel, false);
    if (inputApiKey && !inputId.value) {
      inputApiKey.placeholder = p.placeholder || 'Tempel API Key...';
    }
  }

  async function fetchLiveModelsForForm(silent) {
    const provider = selectProvider.value;
    const apiKey = inputApiKey.value.trim();
    const keyId = inputId.value.trim();
    const currentSelected = selectModelPreset.value === '__custom__'
      ? inputModelCustom.value.trim()
      : selectModelPreset.value;

    if (!apiKey && !keyId) {
      if (!silent) {
        formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300';
        formTestResult.textContent = 'Masukkan API Key atau klik Edit pada kunci tersimpan terlebih dahulu untuk memuat semua model dari akun Anda.';
        formTestResult.classList.remove('hidden');
      }
      return;
    }

    const origText = btnFetchModels ? btnFetchModels.textContent : 'Muat Model API';
    if (btnFetchModels) {
      btnFetchModels.disabled = true;
      btnFetchModels.textContent = 'Memuat...';
    }
    try {
      const resp = await fetch(LIST_MODELS_ENDPOINT, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          csrf_token: getCsrfToken(),
          key_id: keyId,
          provider: provider,
          api_key: apiKey,
        }),
      });
      const res = await resp.json().catch(function() { return null; });
      if (res && res.success && Array.isArray(res.models) && res.models.length > 0) {
        renderModelOptions(res.models, currentSelected, true);
        if (!silent) {
          formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300';
          formTestResult.textContent = 'Berhasil memuat ' + res.models.length + ' model teks langsung dari akun ' + (PROVIDERS[provider] ? PROVIDERS[provider].label : provider) + ' Anda.';
          formTestResult.classList.remove('hidden');
        }
      } else if (!silent) {
        const msg = (res && (res.error || res.message)) || 'Gagal mengambil daftar model dari server provider.';
        formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300';
        formTestResult.textContent = msg;
        formTestResult.classList.remove('hidden');
      }
    } catch (e) {
      if (!silent) {
        formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300';
        formTestResult.textContent = 'Gagal terhubung saat mengambil daftar model.';
        formTestResult.classList.remove('hidden');
      }
    } finally {
      if (btnFetchModels) {
        btnFetchModels.disabled = false;
        btnFetchModels.textContent = origText;
      }
    }
  }

  function resetFormToAdd() {
    inputId.value = '';
    inputLabel.value = '';
    selectProvider.value = 'gemini';
    populateModels('gemini', '');
    inputApiKey.value = '';
    inputApiKey.type = 'password';
    if (btnToggleVis) btnToggleVis.textContent = 'Lihat';
    keyReqMark.classList.remove('hidden');
    keyEditHint.classList.add('hidden');
    chkEnabled.checked = true;
    chkDefault.checked = <?= empty($aiKeys) ? 'true' : 'false' ?>;
    formHeading.textContent = 'Tambah API Key AI';
    btnSubmit.textContent = 'Simpan API Key';
    btnCancelEdit.classList.add('hidden');
    formTestResult.classList.add('hidden');
  }

  function loadIntoEdit(item) {
    inputId.value = item.id || '';
    inputLabel.value = item.label || '';
    selectProvider.value = item.provider || 'gemini';
    populateModels(selectProvider.value, item.model || '');
    inputApiKey.value = '';
    inputApiKey.placeholder = 'Tersimpan: ' + (item.masked_key || '******') + ' (kosongkan jika tetap)';
    keyReqMark.classList.add('hidden');
    keyEditHint.classList.remove('hidden');
    chkDefault.checked = !!item.is_default;
    chkEnabled.checked = !!item.enabled;
    formHeading.textContent = 'Edit API Key: ' + (item.label || '');
    btnSubmit.textContent = 'Simpan Perubahan';
    btnCancelEdit.classList.remove('hidden');
    formTestResult.classList.add('hidden');
    document.getElementById('aiKeyFormSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    inputLabel.focus();
    // Muat seluruh daftar model live dari akun API Key tersimpan secara otomatis
    fetchLiveModelsForForm(true);
  }

  selectProvider.addEventListener('change', function() {
    populateModels(selectProvider.value, '');
    if (inputApiKey.value.trim().length >= 16) {
      fetchLiveModelsForForm(true);
    }
  });

  if (btnFetchModels) {
    btnFetchModels.addEventListener('click', function() {
      fetchLiveModelsForForm(false);
    });
  }

  if (inputApiKey) {
    inputApiKey.addEventListener('change', function() {
      if (inputApiKey.value.trim().length >= 16) {
        fetchLiveModelsForForm(true);
      }
    });
  }

  selectModelPreset.addEventListener('change', function() {
    const isCustom = selectModelPreset.value === '__custom__';
    wrapModelCustom.classList.toggle('hidden', !isCustom);
    if (isCustom) inputModelCustom.focus();
  });

  if (btnToggleVis) {
    btnToggleVis.addEventListener('click', function() {
      const hidden = inputApiKey.type === 'password';
      inputApiKey.type = hidden ? 'text' : 'password';
      btnToggleVis.textContent = hidden ? 'Tutup' : 'Lihat';
    });
  }

  if (btnCancelEdit) btnCancelEdit.addEventListener('click', resetFormToAdd);
  if (btnResetNew) btnResetNew.addEventListener('click', function() {
    resetFormToAdd();
    document.getElementById('aiKeyFormSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    inputLabel.focus();
  });

  document.querySelectorAll('.btn-edit-ai-key').forEach(function(btn) {
    btn.addEventListener('click', function() {
      try {
        const item = JSON.parse(btn.getAttribute('data-key-json') || '{}');
        loadIntoEdit(item);
      } catch (e) {}
    });
  });

  document.querySelectorAll('.form-delete-ai-key').forEach(function(frm) {
    frm.addEventListener('submit', function(ev) {
      const lbl = frm.getAttribute('data-delete-label') || 'ini';
      if (!window.confirm('Hapus profil API Key "' + lbl + '"?')) {
        ev.preventDefault();
      }
    });
  });

  async function runConnectionTest(payload) {
    const resp = await fetch(TEST_ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(Object.assign({ csrf_token: getCsrfToken() }, payload)),
    });
    return resp.json().catch(function() { return { success: false, error: 'Respons server tidak valid.' }; });
  }

  document.querySelectorAll('.btn-test-saved-ai-key').forEach(function(btn) {
    btn.addEventListener('click', async function() {
      const keyId = btn.getAttribute('data-key-id') || '';
      const statusEl = document.querySelector('[data-test-status-for="' + keyId + '"]');
      const origText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Menguji...';
      if (statusEl) {
        statusEl.textContent = 'Menghubungi server AI...';
        statusEl.className = 'ai-test-status text-[11px] font-sans text-slate-500';
      }
      try {
        const res = await runConnectionTest({ key_id: keyId });
        if (res && res.success) {
          if (statusEl) {
            statusEl.textContent = 'OK (' + (res.latency_ms || 0) + ' ms)';
            statusEl.className = 'ai-test-status text-[11px] font-sans font-bold text-emerald-600 dark:text-emerald-400';
          }
          if (typeof showToast === 'function') showToast(res.message || 'Koneksi AI berhasil.', 'success');
        } else {
          const msg = (res && (res.error || res.message)) || 'Uji koneksi gagal.';
          if (statusEl) {
            statusEl.textContent = 'Gagal: ' + msg;
            statusEl.className = 'ai-test-status text-[11px] font-sans font-bold text-rose-600 dark:text-rose-400';
          }
          if (typeof showToast === 'function') showToast(msg, 'error');
        }
      } catch (err) {
        if (statusEl) {
          statusEl.textContent = 'Gagal terhubung ke server.';
          statusEl.className = 'ai-test-status text-[11px] font-sans font-bold text-rose-600 dark:text-rose-400';
        }
      } finally {
        btn.disabled = false;
        btn.textContent = origText;
      }
    });
  });

  if (btnTestForm) {
    btnTestForm.addEventListener('click', async function() {
      const provider = selectProvider.value;
      const model = selectModelPreset.value === '__custom__' ? inputModelCustom.value.trim() : selectModelPreset.value;
      const apiKey = inputApiKey.value.trim();
      const keyId = inputId.value.trim();
      if (!apiKey && !keyId) {
        formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300';
        formTestResult.textContent = 'Masukkan API Key terlebih dahulu sebelum menguji koneksi.';
        formTestResult.classList.remove('hidden');
        return;
      }
      const origText = btnTestForm.textContent;
      btnTestForm.disabled = true;
      btnTestForm.textContent = 'Menguji...';
      formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300';
      formTestResult.textContent = 'Menguji koneksi ke ' + (PROVIDERS[provider] ? PROVIDERS[provider].label : provider) + ' (' + model + ')...';
      formTestResult.classList.remove('hidden');
      try {
        const res = await runConnectionTest({ key_id: keyId, provider: provider, api_key: apiKey, model: model });
        if (res && res.success) {
          formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300';
          formTestResult.textContent = res.message || 'Koneksi berhasil.';
        } else {
          formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300';
          formTestResult.textContent = (res && (res.error || res.message)) || 'Uji koneksi gagal.';
        }
      } catch (e) {
        formTestResult.className = 'rounded-xl px-3.5 py-2.5 text-xs font-semibold bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300';
        formTestResult.textContent = 'Terjadi kesalahan jaringan saat menguji koneksi AI.';
      } finally {
        btnTestForm.disabled = false;
        btnTestForm.textContent = origText;
      }
    });
  }

  populateModels(selectProvider.value, '');
})();
</script>
