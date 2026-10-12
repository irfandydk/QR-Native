<?php
/**
 * QR-SAR - Dedicated View Form Laporan Perkembangan & Penutupan (SITREP / LAPBANG)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);
?>

<div class="max-w-7xl mx-auto space-y-6 pb-20">

  <!-- Breadcrumb & Top Bar -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
    <div class="space-y-1.5 flex-1 min-w-0">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>" class="hover:text-blue-500 transition-colors flex items-center gap-1 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          Detail Operasi
        </a>
        <span>/</span>
        <span class="text-blue-600 dark:text-blue-400 font-bold"><?= $editMode ? 'Edit SITREP' : 'Penerbitan SITREP' ?></span>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
          <?= $editMode ? "Edit Laporan Perkembangan ({$hariKe})" : "Penerbitan Laporan Perkembangan ({$hariKe})" ?>
        </h1>
        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full <?= $editMode ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30' ?> uppercase tracking-wider shrink-0">
          <?= $editMode ? 'MODE PERBAIKAN' : 'DOKUMEN SITREP' ?>
        </span>
      </div>
      <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 line-clamp-1 max-w-2xl">
        Operasi: <?= e($operasi['nama_operasi']) ?>
      </p>
    </div>

    <!-- Quick Action Buttons -->
    <div class="flex items-center gap-2.5 shrink-0 self-end sm:self-auto">
      <button type="button" onclick="previewSitrepWa()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <span>Pratinjau Format WA</span>
      </button>
      <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>&tab=sitrep" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
        Batal
      </a>
    </div>
  </div>

  <form id="form-sitrep" method="POST" action="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $idOperasi ?><?= $editMode ? "&id_laporan={$idLaporan}" : "" ?>" class="space-y-6">
    <?= csrfInput() ?>
    <input type="hidden" name="id_operasi" value="<?= $idOperasi ?>">
    <?php if ($editMode): ?>
      <input type="hidden" name="id_laporan" value="<?= $idLaporan ?>">
    <?php endif; ?>

    <!-- POIN 1: Jenis Laporan & Parameter Waktu -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black text-xs">
            01
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              1. Klasifikasi Laporan & Parameter Waktu
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pilih jenis laporan perkembangan harian atau laporan resmi penutupan operasi SAR</p>
          </div>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
          SITREP / LAPBANG
        </span>
      </div>

      <!-- Toggle Jenis Laporan (Perkembangan vs Penutupan) -->
      <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <label class="text-xs font-bold text-slate-800 dark:text-slate-200 block">
            Jenis Laporan Operasional:
          </label>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">
            Pilih "Penutupan Operasi" jika seluruh korban telah ditemukan atau operasi resmi dihentikan
          </p>
        </div>
        <div class="flex items-center gap-2">
          <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold <?= $jenisLaporan === 'PERKEMBANGAN' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700' ?>" id="label-jenis-perkembangan">
            <input type="radio" name="jenis_laporan" value="PERKEMBANGAN" <?= $jenisLaporan === 'PERKEMBANGAN' ? 'checked' : '' ?> onchange="handleJenisLaporanChange(this.value)" class="hidden">
            <span>📊 Laporan Harian (SITREP)</span>
          </label>
          <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold <?= $jenisLaporan === 'PENUTUPAN' ? 'bg-red-600 text-white border-red-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700' ?>" id="label-jenis-penutupan">
            <input type="radio" name="jenis_laporan" value="PENUTUPAN" <?= $jenisLaporan === 'PENUTUPAN' ? 'checked' : '' ?> onchange="handleJenisLaporanChange(this.value)" class="hidden">
            <span>🔒 Penutupan Operasi</span>
          </label>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Hari Operasi Ke <span class="text-red-500">*</span>
          </label>
          <input type="text" name="hari_ke" id="sitrep_hari_ke" required
            placeholder="Contoh: H.1, H.2"
            value="<?= e($hariKe) ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Waktu Laporan (WITA) <span class="text-red-500">*</span>
          </label>
          <input type="datetime-local" name="waktu_laporan" id="sitrep_waktu_laporan" required
            value="<?= e($waktuLaporan) ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-medium focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div id="wrapper-debrief" class="<?= $jenisLaporan === 'PENUTUPAN' ? '' : 'hidden' ?>">
          <label class="block text-xs font-bold text-red-600 dark:text-red-400 mb-1.5">
            Waktu Debriefing Penutupan (WITA) <span class="text-red-500">*</span>
          </label>
          <input type="datetime-local" name="waktu_debrief" id="sitrep_waktu_debrief"
            value="<?= e($waktuDebrief) ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-red-300 dark:border-red-700 bg-red-50/50 dark:bg-red-950/40 text-red-900 dark:text-red-200 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>
      </div>

      <!-- Cuaca BMKG -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
          Kondisi Cuaca Terkini (BMKG)
        </label>
        <input type="text" name="cuaca" id="sitrep_cuaca"
          placeholder="Contoh: Cerah Berawan, Gelombang 0.5 - 1.25 M, Angin 5 - 15 Knots"
          value="<?= e($cuaca) ?>"
          class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </div>

    <!-- POIN 2: Hasil Pencarian & Tindakan Lapangan -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center font-black text-xs">
            02
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              2. Hasil Pencarian & Tindakan Lapangan
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Deskripsi kronologis hasil operasi hari ini, koordinat temuan, debriefing, dan rencana lanjutan</p>
          </div>
        </div>
        <button type="button" onclick="quickAiRefineSitrep()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-500/10 hover:bg-purple-600 text-purple-700 dark:text-purple-300 hover:text-white border border-purple-500/20 hover:border-purple-600 shadow-sm transition cursor-pointer" title="Perhalus bahasa naskah dengan AI Gemini">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
          <span>✨ Rapikan dengan AI</span>
        </button>
      </div>

      <div class="space-y-3">
        <textarea name="isi_laporan" id="sitrep_isi_laporan" rows="5" required
          placeholder="Tuliskan hasil operasi SAR, temuan lapangan, debriefing atau status operasi..."
          class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs leading-relaxed focus:outline-none focus:ring-2 focus:ring-blue-500"><?= e($isiLaporan) ?></textarea>
      </div>
    </div>

    <!-- POIN 3: Mutasi Status Korban (POB) -->
    <?php if (!empty($operasi['korban'])): ?>
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-xs">
            03
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              3. Pembaruan Status Korban (Mutasi POB)
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Ubah status kondisi korban terkini berdasarkan hasil evakuasi lapangan</p>
          </div>
        </div>
        <span class="text-xs font-mono font-bold text-slate-500">
          Total POB: <?= count($operasi['korban']) ?> Orang
        </span>
      </div>

      <div class="table-responsive rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <table class="table w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400">
            <tr>
              <th class="py-2.5 px-3">No</th>
              <th class="py-2.5 px-3">Nama Korban</th>
              <th class="py-2.5 px-3">Umur</th>
              <th class="py-2.5 px-3">L/P</th>
              <th class="py-2.5 px-3" style="width: 250px;">Status Terkini</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($operasi['korban'] as $idx => $k): 
              $st = strtoupper((string)($k['status_korban'] ?? 'DP'));
            ?>
            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
              <td class="py-2.5 px-3 font-mono text-slate-400"><?= $idx + 1 ?></td>
              <td class="py-2.5 px-3 font-bold text-slate-800 dark:text-slate-200"><?= e($k['nama_korban']) ?></td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-400"><?= e($k['umur'] ?: '-') ?></td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-400"><?= e($k['jk'] ?: '-') ?></td>
              <td class="py-2.5 px-3">
                <select name="update_korban[<?= (int)$k['id_korban'] ?>]" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="DP" <?= $st === 'DP' ? 'selected' : '' ?>>🟡 Dalam Pencarian (DP)</option>
                  <option value="S" <?= $st === 'S' ? 'selected' : '' ?>>🟢 Selamat (S)</option>
                  <option value="MD" <?= $st === 'MD' ? 'selected' : '' ?>>🔴 Meninggal Dunia (MD)</option>
                  <option value="H" <?= $st === 'H' ? 'selected' : '' ?>>⚪ Hilang / Nihil (H)</option>
                </select>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- POIN 4 & 5: Unsur SAR Terlibat & Alut/Palsar (Interactive Reorderable Editors) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Unsur SAR Terlibat -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4 transition-colors">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-xl bg-purple-500/15 text-purple-600 dark:text-purple-400 flex items-center justify-center font-black text-xs">
              04
            </div>
            <div>
              <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                4. Unsur SAR Terlibat
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih dari Master Data atau input manual, dan atur prioritas urutan</p>
            </div>
          </div>
          <span id="sitrep-total-personil" class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400">
            Total: 0 Org
          </span>
        </div>

        <!-- Master Data Selector -->
        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-2">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
              Pilih Master Tim:
            </label>
            <select id="select-master-tim" onchange="pickMasterTim(this.value)" class="flex-1 px-3 py-1.5 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">-- Cari / Pilih dari Master Data Tim Basarnas --</option>
              <?php foreach ($masterTim as $mt): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_tim' => $mt['id_tim'], 'nama_tim' => $mt['nama_tim']]), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($mt['nama_tim']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Input Bar (Manual Add) -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="sm:col-span-8">
            <input type="text" id="unsur-input-nama" placeholder="Ketik nama unsur (contoh: Tim Rescue Kansar, BPBD)..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div class="sm:col-span-3 flex items-center gap-1.5">
            <input type="number" id="unsur-input-jml" min="1" max="999" value="1" placeholder="Qty" class="w-full px-2 py-2 text-xs text-center font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 shrink-0">Org</span>
          </div>
          <div class="sm:col-span-1">
            <button type="button" onclick="handleAddUnsurClick()" class="w-full h-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold flex items-center justify-center shadow transition-all cursor-pointer" title="Tambah Unsur ke Daftar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
        </div>

        <!-- Reorderable List Container -->
        <div id="unsur-items-container" class="space-y-2 min-h-[60px]">
          <!-- Injected via JavaScript -->
        </div>

        <!-- Hidden inputs for backend synchronization -->
        <input type="hidden" name="detail_unsur_json" id="detail_unsur_json" value="">
        <textarea name="unsur_text" id="unsur_text" class="hidden"><?= e($laporan['unsur_text'] ?? '') ?></textarea>
      </div>

      <!-- Alut & Palsar Terlibat -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4 transition-colors">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-xs">
              05
            </div>
            <div>
              <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                5. Alut & Palsar Terlibat
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih dari Master Data atau input manual, dan atur prioritas urutan</p>
            </div>
          </div>
          <span id="sitrep-total-alut" class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            Total: 0 Alut
          </span>
        </div>

        <!-- Master Data Selector -->
        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-2">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
              Pilih Master Alut:
            </label>
            <select id="select-master-alut" onchange="pickMasterAlut(this.value)" class="flex-1 px-3 py-1.5 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">-- Cari / Pilih dari Master Data Alut Basarnas --</option>
              <?php foreach ($masterAlut as $ma): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_alut' => $ma['id_alut'], 'nama_alut' => $ma['nama_alut'], 'satuan' => $ma['satuan'] ?? 'Unit', 'kategori' => $ma['kategori'] ?? 'ALUT']), ENT_QUOTES, 'UTF-8') ?>">
                  [<?= htmlspecialchars($ma['kategori'] ?? 'ALUT') ?>] <?= htmlspecialchars($ma['nama_alut']) ?> (<?= htmlspecialchars($ma['satuan'] ?? 'Unit') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Input Bar (Manual Add) -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="sm:col-span-7">
            <input type="text" id="alut-input-nama" placeholder="Ketik nama alut / palsar (contoh: Rubber Boat, Aqua Eye)..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div class="sm:col-span-2">
            <input type="number" id="alut-input-jml" min="1" max="999" value="1" placeholder="Qty" class="w-full px-2 py-2 text-xs text-center font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div class="sm:col-span-2">
            <select id="alut-input-satuan" class="w-full px-2 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="Unit">Unit</option>
              <option value="Set">Set</option>
              <option value="Buah">Buah</option>
              <option value="Paket">Paket</option>
              <option value="Batang">Batang</option>
              <option value="Orang">Orang</option>
            </select>
          </div>
          <div class="sm:col-span-1">
            <button type="button" onclick="handleAddAlutClick()" class="w-full h-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold flex items-center justify-center shadow transition-all cursor-pointer" title="Tambah Alut ke Daftar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
        </div>

        <!-- Reorderable List Container -->
        <div id="alut-items-container" class="space-y-2 min-h-[60px]">
          <!-- Injected via JavaScript -->
        </div>

        <!-- Hidden inputs for backend synchronization -->
        <input type="hidden" name="detail_alut_json" id="detail_alut_json" value="">
        <textarea name="alut_text" id="alut_text" class="hidden"><?= e($laporan['alut_text'] ?? '') ?></textarea>
      </div>

    </div>

    <!-- Bottom Action Bar (Simpan / Batal) -->
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4 transition-colors">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <span>Status Siklus:</span>
        <span class="font-bold font-mono text-blue-600 dark:text-blue-400"><?= e($hariKe) ?> Terintegrasi</span>
      </div>

      <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>&tab=sitrep" class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 font-bold text-xs transition">
          Batal
        </a>
        <button type="submit" id="btn-submit-sitrep" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-lg shadow-blue-600/30 hover:scale-[1.02] active:scale-[0.98] transition cursor-pointer flex items-center gap-2">
          <span>💾</span>
          <span><?= $editMode ? 'Simpan Perubahan SITREP' : 'Terbitkan Laporan Perkembangan (SITREP)' ?></span>
        </button>
      </div>
    </div>

  </form>
</div>

<!-- Modal Pratinjau WhatsApp SITREP -->
<div id="modal-preview-sitrep-wa" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs hidden">
  <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
    <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pratinjau Format WhatsApp SITREP</h3>
      </div>
      <button type="button" onclick="closeModal('modal-preview-sitrep-wa')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="p-5 overflow-y-auto flex-1 font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap select-all leading-relaxed bg-slate-50 dark:bg-slate-950" id="sitrep-wa-preview-content"></div>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center justify-between">
      <span class="text-xs text-slate-400">Siap dikirim ke grup komando</span>
      <div class="flex items-center gap-2">
        <button type="button" onclick="copySitrepWa()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition cursor-pointer flex items-center gap-1.5">
          <span>📋</span>
          <span>Salin Teks WA</span>
        </button>
        <button type="button" onclick="closeModal('modal-preview-sitrep-wa')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Data state
let currentUnsurList = <?= json_encode($initialUnsurData) ?>;
let currentAlutList = <?= json_encode($initialAlutData) ?>;

// Escape HTML utility
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Toggle Perkembangan vs Penutupan ──
function handleJenisLaporanChange(val) {
  const isPenutupan = (val === 'PENUTUPAN');
  const debriefWrap = document.getElementById('wrapper-debrief');
  const lblPerkembangan = document.getElementById('label-jenis-perkembangan');
  const lblPenutupan = document.getElementById('label-jenis-penutupan');
  const btnSubmit = document.getElementById('btn-submit-sitrep');

  if (isPenutupan) {
    if (debriefWrap) debriefWrap.classList.remove('hidden');
    if (lblPenutupan) {
      lblPenutupan.className = 'cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold bg-red-600 text-white border-red-600';
    }
    if (lblPerkembangan) {
      lblPerkembangan.className = 'cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700';
    }
    if (btnSubmit) {
      btnSubmit.classList.remove('bg-blue-600', 'hover:bg-blue-500');
      btnSubmit.classList.add('bg-red-600', 'hover:bg-red-500');
      btnSubmit.querySelector('span:last-child').textContent = 'Terbitkan Laporan Penutupan Operasi';
    }
  } else {
    if (debriefWrap) debriefWrap.classList.add('hidden');
    if (lblPerkembangan) {
      lblPerkembangan.className = 'cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold bg-blue-600 text-white border-blue-600';
    }
    if (lblPenutupan) {
      lblPenutupan.className = 'cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700';
    }
    if (btnSubmit) {
      btnSubmit.classList.remove('bg-red-600', 'hover:bg-red-500');
      btnSubmit.classList.add('bg-blue-600', 'hover:bg-blue-500');
      btnSubmit.querySelector('span:last-child').textContent = 'Terbitkan Laporan Perkembangan (SITREP)';
    }
  }
}

// ── Quick AI Refinement for Sitrep ──
async function quickAiRefineSitrep() {
  const isiEl = document.getElementById('sitrep_isi_laporan');
  if (!isiEl) return;
  const rawText = isiEl.value.trim();
  if (!rawText) {
    if (typeof showToast === 'function') showToast('Ketikkan ringkasan hasil lapangan terlebih dahulu untuk dirapikan AI.', 'warning');
    return;
  }

  if (typeof showToast === 'function') showToast('Menyusun naskah laporan resmi dengan AI Gemini...', 'info');

  try {
    const res = await fetch('<?= BASE_URL ?>/api/index.php?path=/ai/refine-sitrep', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        catatan: rawText,
        hari_ke: document.getElementById('sitrep_hari_ke')?.value || 'H.1',
        nama_operasi: <?= json_encode($operasi['nama_operasi']) ?>
      })
    });
    const json = await res.json();
    if (json.success && json.text) {
      isiEl.value = json.text;
      if (typeof showToast === 'function') showToast('Laporan berhasil dirapikan sesuai format resmi Basarnas!', 'success');
    } else {
      if (typeof showToast === 'function') showToast(json.error || 'Gagal merapikan teks AI', 'error');
    }
  } catch (e) {
    console.error('AI Error:', e);
    if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan AI', 'error');
  }
}

// ── Unsur SAR Terlibat Editor ──
function renderUnsurList() {
  const container = document.getElementById('unsur-items-container');
  if (!container) return;
  container.innerHTML = '';

  let totalPersonil = 0;

  if (currentUnsurList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada unsur SAR yang ditambahkan. Gunakan dropdown Master Data atau input manual di atas.
      </div>
    `;
  } else {
    currentUnsurList.forEach((item, idx) => {
      const jml = parseInt(item.jumlah_personil, 10) || 1;
      totalPersonil += jml;

      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-blue-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-purple-500/15 text-purple-600 dark:text-purple-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_unsur || '')}" onchange="updateUnsurItem(${idx}, 'nama_unsur', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-blue-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="flex items-center gap-1">
            <input type="number" min="1" max="999" value="${jml}" onchange="updateUnsurItem(${idx}, 'jumlah_personil', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-purple-600 dark:text-purple-400 focus:outline-none focus:border-blue-500" />
            <span class="text-xs font-medium text-slate-400">Org</span>
          </div>
          <div class="flex items-center gap-0.5 border-l border-slate-200 dark:border-slate-800 pl-2">
            <button type="button" onclick="moveUnsurRow(${idx}, -1)" ${idx === 0 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Atas (Prioritas Naik)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            </button>
            <button type="button" onclick="moveUnsurRow(${idx}, 1)" ${idx === currentUnsurList.length - 1 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Bawah (Prioritas Turun)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <button type="button" onclick="removeUnsurRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus Unsur">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      `;
      container.appendChild(row);
    });
  }

  const badgeEl = document.getElementById('sitrep-total-personil');
  if (badgeEl) badgeEl.textContent = `Total: ${totalPersonil} Org`;

  syncUnsurFields();
}

function pickMasterTim(jsonStr) {
  if (!jsonStr) return;
  try {
    const item = JSON.parse(jsonStr);
    if (item && item.nama_tim) {
      document.getElementById('unsur-input-nama').value = item.nama_tim;
      document.getElementById('unsur-input-jml').value = '3';
      document.getElementById('unsur-input-nama').focus();
    }
  } catch (e) {}
}

function handleAddUnsurClick() {
  const namaInput = document.getElementById('unsur-input-nama');
  const jmlInput = document.getElementById('unsur-input-jml');
  const nama = namaInput.value.trim();
  const jml = parseInt(jmlInput.value, 10) || 1;

  if (!nama) {
    namaInput.focus();
    return;
  }

  currentUnsurList.push({
    nama_unsur: nama,
    jumlah_personil: jml
  });

  namaInput.value = '';
  jmlInput.value = '1';
  document.getElementById('select-master-tim').value = '';
  renderUnsurList();
}

function moveUnsurRow(idx, direction) {
  const target = idx + direction;
  if (target < 0 || target >= currentUnsurList.length) return;
  const temp = currentUnsurList[idx];
  currentUnsurList[idx] = currentUnsurList[target];
  currentUnsurList[target] = temp;
  renderUnsurList();
}

function removeUnsurRow(idx) {
  currentUnsurList.splice(idx, 1);
  renderUnsurList();
}

function updateUnsurItem(idx, key, val) {
  if (currentUnsurList[idx]) {
    currentUnsurList[idx][key] = val;
    syncUnsurFields();
  }
}

function syncUnsurFields() {
  const jsonEl = document.getElementById('detail_unsur_json');
  const textEl = document.getElementById('unsur_text');
  if (jsonEl) jsonEl.value = JSON.stringify(currentUnsurList);
  if (textEl) {
    textEl.value = currentUnsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil || 1} Org`).join('\n');
  }
}

// ── Alut & Palsar Terlibat Editor ──
function renderAlutList() {
  const container = document.getElementById('alut-items-container');
  if (!container) return;
  container.innerHTML = '';

  let totalAlut = 0;

  if (currentAlutList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada armada alut / palsar yang ditambahkan. Gunakan dropdown Master Data atau input manual di atas.
      </div>
    `;
  } else {
    currentAlutList.forEach((item, idx) => {
      const jml = parseInt(item.jumlah_alut, 10) || 1;
      totalAlut += jml;

      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-blue-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_alut || '')}" onchange="updateAlutItem(${idx}, 'nama_alut', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-blue-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${jml}" onchange="updateAlutItem(${idx}, 'jumlah_alut', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 focus:outline-none focus:border-blue-500" />
          <select onchange="updateAlutItem(${idx}, 'satuan', this.value)" class="px-1.5 py-1 text-xs rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 focus:outline-none">
            <option value="Unit" ${item.satuan === 'Unit' ? 'selected' : ''}>Unit</option>
            <option value="Set" ${item.satuan === 'Set' ? 'selected' : ''}>Set</option>
            <option value="Buah" ${item.satuan === 'Buah' ? 'selected' : ''}>Buah</option>
            <option value="Paket" ${item.satuan === 'Paket' ? 'selected' : ''}>Paket</option>
            <option value="Batang" ${item.satuan === 'Batang' ? 'selected' : ''}>Batang</option>
            <option value="Orang" ${item.satuan === 'Orang' ? 'selected' : ''}>Orang</option>
          </select>
          <div class="flex items-center gap-0.5 border-l border-slate-200 dark:border-slate-800 pl-2">
            <button type="button" onclick="moveAlutRow(${idx}, -1)" ${idx === 0 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Atas (Prioritas Naik)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            </button>
            <button type="button" onclick="moveAlutRow(${idx}, 1)" ${idx === currentAlutList.length - 1 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Bawah (Prioritas Turun)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <button type="button" onclick="removeAlutRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus Alut">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      `;
      container.appendChild(row);
    });
  }

  const badgeEl = document.getElementById('sitrep-total-alut');
  if (badgeEl) badgeEl.textContent = `Total: ${totalAlut} Alut`;

  syncAlutFields();
}

function pickMasterAlut(jsonStr) {
  if (!jsonStr) return;
  try {
    const item = JSON.parse(jsonStr);
    if (item && item.nama_alut) {
      document.getElementById('alut-input-nama').value = item.nama_alut;
      document.getElementById('alut-input-jml').value = '1';
      if (item.satuan) {
        document.getElementById('alut-input-satuan').value = item.satuan;
      }
      document.getElementById('alut-input-nama').focus();
    }
  } catch (e) {}
}

function handleAddAlutClick() {
  const namaInput = document.getElementById('alut-input-nama');
  const jmlInput = document.getElementById('alut-input-jml');
  const satInput = document.getElementById('alut-input-satuan');

  const nama = namaInput.value.trim();
  const jml = parseInt(jmlInput.value, 10) || 1;
  const sat = satInput.value.trim() || 'Unit';

  if (!nama) {
    namaInput.focus();
    return;
  }

  currentAlutList.push({
    nama_alut: nama,
    jumlah_alut: jml,
    satuan: sat
  });

  namaInput.value = '';
  jmlInput.value = '1';
  document.getElementById('select-master-alut').value = '';
  renderAlutList();
}

function moveAlutRow(idx, direction) {
  const target = idx + direction;
  if (target < 0 || target >= currentAlutList.length) return;
  const temp = currentAlutList[idx];
  currentAlutList[idx] = currentAlutList[target];
  currentAlutList[target] = temp;
  renderAlutList();
}

function removeAlutRow(idx) {
  currentAlutList.splice(idx, 1);
  renderAlutList();
}

function updateAlutItem(idx, key, val) {
  if (currentAlutList[idx]) {
    currentAlutList[idx][key] = val;
    syncAlutFields();
  }
}

function syncAlutFields() {
  const jsonEl = document.getElementById('detail_alut_json');
  const textEl = document.getElementById('alut_text');
  if (jsonEl) jsonEl.value = JSON.stringify(currentAlutList);
  if (textEl) {
    textEl.value = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
  }
}

// ── WhatsApp Preview Modal ──
function previewSitrepWa() {
  const hariKe = document.getElementById('sitrep_hari_ke')?.value || 'H.1';
  const waktuLap = document.getElementById('sitrep_waktu_laporan')?.value || '';
  const isPenutupan = document.querySelector('input[name="jenis_laporan"]:checked')?.value === 'PENUTUPAN';
  const waktuDebrief = document.getElementById('sitrep_waktu_debrief')?.value || '';
  const cuaca = document.getElementById('sitrep_cuaca')?.value || 'Cerah Berawan';
  const isi = document.getElementById('sitrep_isi_laporan')?.value || '-';

  const tglStr = window.formatTanggalIndo ? window.formatTanggalIndo(waktuLap) : waktuLap;
  const jamLap = window.formatJamWita ? window.formatJamWita(waktuLap) : waktuLap;
  const namaOps = <?= json_encode($operasi['nama_operasi']) ?>;

  const unsurText = currentUnsurList.map(u => `• ${u.nama_unsur} (${u.jumlah_personil || 1} Org)`).join('\n') || '-';
  const alutText = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n') || '-';

  let headerTitle = isPenutupan ? `LAPORAN PENUTUPAN OPERASI SAR (${hariKe})` : `LAPORAN PERKEMBANGAN OPERASI SAR (${hariKe})`;

  const preview = `${headerTitle}
KANTOR PENCARIAN DAN PERTOLONGAN KENDARI

Operasi SAR: ${namaOps}
Waktu Laporan: ${tglStr} Pukul ${jamLap} WITA

1. Hasil Pencarian & Tindakan:
${isi}
${isPenutupan ? `\nDebriefing Penutupan: ${window.formatTanggalIndo ? window.formatTanggalIndo(waktuDebrief) : waktuDebrief} Pukul ${window.formatJamWita ? window.formatJamWita(waktuDebrief) : waktuDebrief} WITA\nOperasi SAR resmi dinyatakan DITUTUP.\n` : ''}
2. Kondisi Cuaca BMKG:
${cuaca}

3. Potensi SAR Terlibat:
${unsurText}

4. Alut & Palsar Terlibat:
${alutText}

Demikian dilaporkan.
SAR KENDARI`;

  document.getElementById('sitrep-wa-preview-content').textContent = preview;
  openModal('modal-preview-sitrep-wa');
}

function copySitrepWa() {
  const content = document.getElementById('sitrep-wa-preview-content')?.textContent || '';
  if (navigator.clipboard) {
    navigator.clipboard.writeText(content).then(() => {
      if (typeof showToast === 'function') showToast('Format WA SITREP berhasil disalin!', 'success');
    });
  } else {
    const ta = document.createElement('textarea');
    ta.value = content;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    if (typeof showToast === 'function') showToast('Format WA SITREP berhasil disalin!', 'success');
  }
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('hidden');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('hidden');
}

// --- AUTO-SAVE DRAFT (LOCAL STORAGE RESILIENCE) ---
const SITREP_DRAFT_KEY = 'qr_sar_sitrep_draft_<?= $idOperasi ?>';
const isSitrepEditMode = <?= $editMode ? 'true' : 'false' ?>;

function saveSitrepDraft() {
  if (isSitrepEditMode) return;
  const draft = {
    waktu_laporan: document.getElementById('input-waktu-laporan')?.value,
    waktu_debriefing: document.getElementById('input-waktu-debriefing')?.value,
    isi_laporan: document.getElementById('input-isi-laporan')?.value,
    unsur: currentUnsurList,
    alut: currentAlutList,
    savedAt: new Date().toISOString()
  };
  localStorage.setItem(SITREP_DRAFT_KEY, JSON.stringify(draft));
}

function restoreSitrepDraft() {
  if (isSitrepEditMode) return;
  const raw = localStorage.getItem(SITREP_DRAFT_KEY);
  if (!raw) return;
  try {
    const d = JSON.parse(raw);
    if (!d) return;
    if (d.isi_laporan) document.getElementById('input-isi-laporan').value = d.isi_laporan;
    if (d.waktu_debriefing && document.getElementById('input-waktu-debriefing')) {
      document.getElementById('input-waktu-debriefing').value = d.waktu_debriefing;
    }
    if (Array.isArray(d.unsur) && d.unsur.length > 0) { currentUnsurList = d.unsur; renderUnsurList(); }
    if (Array.isArray(d.alut) && d.alut.length > 0) { currentAlutList = d.alut; renderAlutList(); }
    
    if (typeof showToast === 'function') {
      showToast('Draf SITREP tersimpan berhasil dipulihkan otomatis.', 'info');
    }
  } catch (e) {}
}

function clearSitrepDraft() {
  localStorage.removeItem(SITREP_DRAFT_KEY);
}

let sitrepDraftTimeout = null;
document.addEventListener('input', (e) => {
  if (isSitrepEditMode) return;
  clearTimeout(sitrepDraftTimeout);
  sitrepDraftTimeout = setTimeout(saveSitrepDraft, 1000);
});

document.getElementById('form-sitrep')?.addEventListener('submit', () => {
  clearSitrepDraft();
});

document.addEventListener('DOMContentLoaded', () => {
  renderUnsurList();
  renderAlutList();
  restoreSitrepDraft();
});
</script>
