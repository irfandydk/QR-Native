<?php
/**
 * QR-SAR - Form Inisiasi Laporan Awal Operasi SAR
 * KPP Kendari - Basarnas
 * 100% Faithful to Reference FormInisiasi.tsx
 */

declare(strict_types=1);

$masterAlut = MasterModel::getAlut();
$masterTim = MasterModel::getTim();
$sarUnits = MasterModel::getSarUnits();
$defaultUnit = MasterModel::getSarUnits()[0] ?? null;
$operasi = (isset($operasi) && is_array($operasi)) ? $operasi : [];
$editMode = !empty($operasi['id_operasi']);
$formTitle = $editMode ? ("Edit Laporan Awal: " . ($operasi['nama_operasi'] ?? '')) : "Inisiasi Laporan Awal Operasi SAR";

$initialAlutData = [];
if (!empty($operasi['detail_alut']) && is_array($operasi['detail_alut'])) {
    $initialAlutData = $operasi['detail_alut'];
} elseif (!empty($operasi['alut_palsar'])) {
    $lines = explode("\n", $operasi['alut_palsar']);
    foreach ($lines as $line) {
        $cleanLine = trim($line, " \t\n\r•*-");
        if (empty($cleanLine)) continue;
        if (preg_match('/^(\d+)\s+([A-Za-z]+)\s+(.+)$/i', $cleanLine, $m)) {
            $initialAlutData[] = [
                'nama_alut' => trim($m[3]),
                'jumlah_alut' => (int)$m[1],
                'satuan' => trim($m[2])
            ];
        } else {
            $initialAlutData[] = [
                'nama_alut' => $cleanLine,
                'jumlah_alut' => 1,
                'satuan' => 'Unit'
            ];
        }
    }
} else {
    $initialAlutData = [
        ['nama_alut' => 'Rescue Car', 'jumlah_alut' => 1, 'satuan' => 'Unit'],
        ['nama_alut' => 'Rubber Boat + Mopel', 'jumlah_alut' => 1, 'satuan' => 'Unit'],
        ['nama_alut' => 'Palsar Evakuasi', 'jumlah_alut' => 1, 'satuan' => 'Set'],
        ['nama_alut' => 'Palsar Komunikasi', 'jumlah_alut' => 1, 'satuan' => 'Set']
    ];
}

$initialUnsurData = [];
if (!empty($operasi['detail_unsur']) && is_array($operasi['detail_unsur'])) {
    $initialUnsurData = $operasi['detail_unsur'];
} elseif (!empty($operasi['unsur_terlibat'])) {
    $lines = explode("\n", $operasi['unsur_terlibat']);
    foreach ($lines as $line) {
        $cleanLine = trim($line, " \t\n\r•*-");
        if (empty($cleanLine)) continue;
        if (preg_match('/^(.+?)\s*:\s*(\d+)\s*(?:Org|Orang)?$/i', $cleanLine, $m)) {
            $initialUnsurData[] = [
                'nama_unsur' => trim($m[1]),
                'jumlah_personil' => (int)$m[2]
            ];
        } elseif (preg_match('/^(\d+)\s*(?:Org|Orang)?\s+(.+)$/i', $cleanLine, $m)) {
            $initialUnsurData[] = [
                'nama_unsur' => trim($m[2]),
                'jumlah_personil' => (int)$m[1]
            ];
        } else {
            $initialUnsurData[] = [
                'nama_unsur' => $cleanLine,
                'jumlah_personil' => 1
            ];
        }
    }
} else {
    $initialUnsurData = [
        ['nama_unsur' => 'Tim Rescue KPP Kendari', 'jumlah_personil' => 7],
        ['nama_unsur' => 'Polairud Polda Sultra', 'jumlah_personil' => 4],
        ['nama_unsur' => 'Masyarakat & Keluarga Korban', 'jumlah_personil' => 10]
    ];
}

// Parse DMS Coordinate Components (Degrees, Minutes, Seconds, Direction)
$rawCoord = $operasi['koordinat'] ?? '';
$latDeg = '04'; $latMin = '02'; $latSec = '16'; $latDir = 'S';
$lngDeg = '122'; $lngMin = '29'; $lngSec = '12'; $lngDir = 'E';

if (!empty($rawCoord)) {
    $parts = preg_split('/[-–—]/', $rawCoord);
    if (count($parts) >= 2) {
        if (preg_match('/(\d{1,3})[°\s]+(\d{1,2})[\'\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([NSEWnsewLSluBTbt]{1,2})?/iu', trim($parts[0]), $m)) {
            $latDeg = sprintf('%02d', (int)$m[1]);
            $latMin = sprintf('%02d', (int)$m[2]);
            $latSec = $m[3];
            $d = strtoupper($m[4] ?? 'S');
            $latDir = (str_contains($d, 'U') || str_contains($d, 'N')) ? 'N' : 'S';
        }
        if (preg_match('/(\d{1,3})[°\s]+(\d{1,2})[\'\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([NSEWnsewLSluBTbt]{1,2})?/iu', trim($parts[1]), $m)) {
            $lngDeg = sprintf('%03d', (int)$m[1]);
            $lngMin = sprintf('%02d', (int)$m[2]);
            $lngSec = $m[3];
            $d = strtoupper($m[4] ?? 'E');
            $lngDir = (str_contains($d, 'B') || str_contains($d, 'W')) && !str_contains($d, 'BT') ? 'W' : 'E';
        }
    }
}

// Parse initial jarak & radial for numeric input handling
$jarakVal = '';
$jarakUnit = 'NM';
if (!empty($operasi['jarak'])) {
    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(NM|KM)?/i', trim($operasi['jarak']), $jm)) {
        $jarakVal = $jm[1];
        if (!empty($jm[2])) {
            $jarakUnit = strtoupper($jm[2]);
        }
    } else {
        $jarakVal = preg_replace('/[^0-9.]/', '', $operasi['jarak']);
        if (stripos($operasi['jarak'], 'KM') !== false) {
            $jarakUnit = 'KM';
        }
    }
}

$radialVal = '';
if (!empty($operasi['radial'])) {
    $radialVal = preg_replace('/[^0-9.]/', '', $operasi['radial']);
}
?>

<div class="max-w-7xl mx-auto space-y-6 pb-20">

  <!-- Breadcrumb & Top Bar -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
    <div class="space-y-1.5 flex-1 min-w-0">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <a href="<?= BASE_URL ?>/index.php" class="hover:text-orange-500 transition-colors flex items-center gap-1 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          Dashboard
        </a>
        <span>/</span>
        <span class="text-orange-600 dark:text-orange-400 font-bold"><?= $editMode ? 'Edit Operasi' : 'Inisiasi Laporan Awal' ?></span>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
          <?= $editMode ? 'Edit Laporan Awal Operasi SAR' : 'Form Inisiasi Laporan Awal Operasi SAR' ?>
        </h1>
        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full <?= $editMode ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30' ?> uppercase tracking-wider shrink-0">
          <?= $editMode ? 'MODE PERBAIKAN' : 'FILE AKTIF' ?>
        </span>
      </div>
      <?php if ($editMode && !empty($operasi['nama_operasi'])): ?>
        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 line-clamp-1 max-w-2xl">
          <?= e($operasi['nama_operasi']) ?>
        </p>
      <?php endif; ?>
      <p class="text-xs text-slate-500 dark:text-slate-400">
        Standar Pelaporan Resmi Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari • Basarnas
      </p>
    </div>

    <!-- Quick Action Buttons -->
    <div class="flex items-center gap-2.5 shrink-0 self-end sm:self-auto">
      <button type="button" onclick="previewWhatsApp()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <span>Pratinjau Format WA</span>
      </button>
      <a href="<?= $editMode ? (BASE_URL . '/operasi_detail.php?id=' . (int)($operasi['id_operasi'] ?? 0)) : (BASE_URL . '/index.php') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
        Batal
      </a>
    </div>
  </div>

  <form id="form-inisiasi-ops" method="POST" action="<?= BASE_URL ?>/<?= $editMode ? ("operasi.php?action=update&id=" . (int)($operasi['id_operasi'] ?? 0)) : "operasi.php?action=create" ?>" class="space-y-6">
    <?= csrfInput() ?>
    <?php if ($editMode): ?>
      <input type="hidden" name="id_operasi" value="<?= e((string)($operasi['id_operasi'] ?? '')) ?>">
    <?php endif; ?>

    <!-- POIN 1: Identitas Operasi & Sumber Informasi -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center font-black text-xs">
            01
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              1. Identitas Operasi & Sumber Informasi
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Nama operasi SAR dan data lengkap pelapor pertama</p>
          </div>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
          SUMBER INFO
        </span>
      </div>

      <div class="space-y-4">
        <!-- Nama Operasi SAR -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Nama Operasi SAR <span class="text-red-500">*</span>
          </label>
          <input type="text" name="nama_operasi" id="nama_operasi" required
            placeholder="Contoh: Operasi SAR Kecelakaan Kapal KM Sumber Bahagia di Perairan Saponda Kab. Konawe"
            value="<?= e($operasi['nama_operasi'] ?? '') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500 transition-colors" />
          
          <!-- Preset Nama Operasi Pills -->
          <div class="flex flex-wrap items-center gap-1.5 mt-2">
            <span class="text-[11px] font-semibold text-slate-400 mr-1">Preset Cepat:</span>
            <button type="button" onclick="setPresetOps('Operasi SAR Kecelakaan Kapal Longboat Mati Mesin di Perairan ')" class="px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
              🚢 Kapal Mati Mesin
            </button>
            <button type="button" onclick="setPresetOps('Operasi SAR Kecelakaan Kapal Terbalik di Perairan ')" class="px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
              🌊 Kapal Terbalik
            </button>
            <button type="button" onclick="setPresetOps('Operasi SAR Kondisi Membahayakan Manusia (KMM) 1 Orang Hilang di Hutan ')" class="px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
              🌲 KMM Hutan
            </button>
            <button type="button" onclick="setPresetOps('Operasi SAR Kondisi Membahayakan Manusia (KMM) 1 Orang Tenggelam di Sungai ')" class="px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
              🏊 KMM Sungai
            </button>
          </div>
        </div>

        <!-- Data Pelapor Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Nama Pelapor <span class="text-red-500">*</span>
            </label>
            <input type="text" name="nama_pelapor" id="nama_pelapor" required
              placeholder="Contoh: Bpk. La Ode Ridwan"
              value="<?= e($operasi['nama_pelapor'] ?? '') ?>"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Hubungan Pelapor / Instansi
            </label>
            <input type="text" name="hubungan_pelapor" id="hubungan_pelapor"
              placeholder="Contoh: Keluarga Korban / Polsek / Polairud"
              value="<?= e($operasi['hubungan_pelapor'] ?? '') ?>"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              No. Telepon / WhatsApp Pelapor
            </label>
            <input type="text" name="no_telp_pelapor" id="no_telp_pelapor"
              placeholder="Contoh: 0812-3456-7890"
              value="<?= e($operasi['no_telp_pelapor'] ?? '') ?>"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-500" />
          </div>
        </div>
      </div>
    </div>

    <!-- POIN 2: Kronologi Waktu & Perjalanan Tim -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black text-xs">
            02
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              2. Kronologi Waktu & Perjalanan Tim (WITA / UTC+8)
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Waktu kejadian, waktu terima laporan, keberangkatan tim, dan estimasi tiba di LKP</p>
          </div>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
          WAKTU & PERGERAKAN
        </span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Waktu Kejadian (WITA) <span class="text-red-500">*</span>
          </label>
          <input type="datetime-local" name="waktu_kejadian" id="waktu_kejadian" required
            value="<?= !empty($operasi['waktu_kejadian']) ? date('Y-m-d\TH:i', strtotime($operasi['waktu_kejadian'])) : '' ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Waktu Lapor Diterima (WITA) <span class="text-red-500">*</span>
          </label>
          <input type="datetime-local" name="waktu_lapor" id="waktu_lapor" required
            value="<?= !empty($operasi['waktu_lapor']) ? date('Y-m-d\TH:i', strtotime($operasi['waktu_lapor'])) : date('Y-m-d\TH:i') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Waktu Berangkat Tim (WITA) <span class="text-red-500">*</span>
          </label>
          <input type="datetime-local" name="waktu_berangkat" id="waktu_berangkat" required
            value="<?= !empty($operasi['waktu_berangkat']) ? date('Y-m-d\TH:i', strtotime($operasi['waktu_berangkat'])) : date('Y-m-d\TH:i') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Estimasi Tiba (ETA LKP WITA) <span class="text-red-500">*</span>
          </label>
          <input type="datetime-local" name="eta_tiba" id="eta_tiba" required
            value="<?= !empty($operasi['eta_tiba']) ? date('Y-m-d\TH:i', strtotime($operasi['eta_tiba'])) : '' ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono" />
        </div>

        <div class="sm:col-span-2">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Tim yang Diberangkatkan
          </label>
          <input type="text" name="tim_berangkat" id="tim_berangkat"
            placeholder="Contoh: Tim Rescue Kansar Kendari / Pos SAR Kolaka"
            value="<?= e($operasi['tim_berangkat'] ?? 'Tim Rescue Kansar Kendari') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <div class="sm:col-span-2">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Estimasi Waktu Tempuh (ETA Lokasi Teks)
          </label>
          <input type="text" name="eta_lokasi" id="eta_lokasi"
            placeholder="Contoh: 1 Jam 30 Menit"
            value="<?= e($operasi['eta_lokasi'] ?? '') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
          
          <!-- ETA Quick Pills matching reference -->
          <div class="flex flex-wrap items-center gap-1.5 mt-2">
            <span class="text-[11px] font-semibold text-slate-400 mr-1">Preset Cepat:</span>
            <?php foreach (['15 Menit', '30 Menit', '45 Menit', '1 Jam', '1 Jam 30 Menit', '2 Jam', '3 Jam'] as $et): ?>
              <button type="button" onclick="setEtaPreset('<?= $et ?>')" class="px-2 py-0.5 rounded-lg text-[10px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
                <?= $et ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- POIN 3: Lokasi Kejadian & Titik Koordinat (LKP) -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-xs">
            03
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              3. Lokasi Kejadian & Titik Koordinat (LKP)
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Deskripsi wilayah, koordinat format baku Basarnas (DMS), dan kalkulasi jarak radial</p>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Deskripsi Lokasi / Wilayah Kejadian <span class="text-red-500">*</span>
          </label>
          <input type="text" name="lokasi_kejadian" id="lokasi_kejadian" required
            placeholder="Contoh: Perairan Saponda, Kec. Soropia, Kab. Konawe, Sulawesi Tenggara"
            value="<?= e($operasi['lokasi_kejadian'] ?? '') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <!-- Dedicated DMS Coordinate Inputs (No manual degree/minute symbols required) -->
        <div class="p-4 rounded-xl border border-emerald-200/70 dark:border-emerald-800/60 bg-emerald-50/40 dark:bg-emerald-950/20 space-y-3">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-800 dark:text-slate-200">
              Titik Koordinat LKP (Format DMS Derajat-Menit-Detik) <span class="text-red-500">*</span>
            </label>
            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">Cukup masukkan angka saja & arah</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Latitude (Lintang) -->
            <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
              <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block uppercase tracking-wider">Lintang (Latitude):</span>
              <div class="flex items-center gap-1.5 flex-wrap sm:flex-nowrap">
                <div class="flex items-center gap-1">
                  <input type="number" id="dms_lat_deg" min="0" max="90" value="<?= e($latDeg) ?>" placeholder="04" class="w-14 px-2 py-1.5 text-xs text-center font-bold font-mono rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500" title="Derajat Lintang (0-90)">
                  <span class="font-bold text-xs text-slate-400">°</span>
                </div>
                <div class="flex items-center gap-1">
                  <input type="number" id="dms_lat_min" min="0" max="59" value="<?= e($latMin) ?>" placeholder="02" class="w-14 px-2 py-1.5 text-xs text-center font-bold font-mono rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500" title="Menit Lintang (0-59)">
                  <span class="font-bold text-xs text-slate-400">'</span>
                </div>
                <div class="flex items-center gap-1">
                  <input type="number" id="dms_lat_sec" min="0" max="59.99" step="any" value="<?= e($latSec) ?>" placeholder="16" class="w-16 px-2 py-1.5 text-xs text-center font-bold font-mono rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500" title="Detik Lintang (0-59)">
                  <span class="font-bold text-xs text-slate-400">"</span>
                </div>
                <select id="dms_lat_dir" class="px-2 py-1.5 text-xs font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                  <option value="S" <?= $latDir === 'S' ? 'selected' : '' ?>>S (LS - Selatan)</option>
                  <option value="N" <?= $latDir === 'N' ? 'selected' : '' ?>>N (LU - Utara)</option>
                </select>
              </div>
            </div>

            <!-- Longitude (Bujur) -->
            <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
              <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block uppercase tracking-wider">Bujur (Longitude):</span>
              <div class="flex items-center gap-1.5 flex-wrap sm:flex-nowrap">
                <div class="flex items-center gap-1">
                  <input type="number" id="dms_lng_deg" min="0" max="180" value="<?= e($lngDeg) ?>" placeholder="122" class="w-16 px-2 py-1.5 text-xs text-center font-bold font-mono rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500" title="Derajat Bujur (0-180)">
                  <span class="font-bold text-xs text-slate-400">°</span>
                </div>
                <div class="flex items-center gap-1">
                  <input type="number" id="dms_lng_min" min="0" max="59" value="<?= e($lngMin) ?>" placeholder="29" class="w-14 px-2 py-1.5 text-xs text-center font-bold font-mono rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500" title="Menit Bujur (0-59)">
                  <span class="font-bold text-xs text-slate-400">'</span>
                </div>
                <div class="flex items-center gap-1">
                  <input type="number" id="dms_lng_sec" min="0" max="59.99" step="any" value="<?= e($lngSec) ?>" placeholder="12" class="w-16 px-2 py-1.5 text-xs text-center font-bold font-mono rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500" title="Detik Bujur (0-59)">
                  <span class="font-bold text-xs text-slate-400">"</span>
                </div>
                <select id="dms_lng_dir" class="px-2 py-1.5 text-xs font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                  <option value="E" <?= $lngDir === 'E' ? 'selected' : '' ?>>E (BT - Timur)</option>
                  <option value="W" <?= $lngDir === 'W' ? 'selected' : '' ?>>W (BB - Barat)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Preview & Hidden Form Input -->
          <div class="flex items-center justify-between pt-1 text-xs">
            <div class="flex items-center gap-2">
              <span class="text-slate-500 dark:text-slate-400">Hasil Format Baku:</span>
              <span id="dms_preview_text" class="font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 rounded-lg border border-emerald-200 dark:border-emerald-800"><?= e(!empty($operasi['koordinat']) ? $operasi['koordinat'] : '04° 02\' 16" S - 122° 29\' 12" E') ?></span>
            </div>
          </div>
          <input type="hidden" name="koordinat" id="koordinat" value="<?= e(!empty($operasi['koordinat']) ? $operasi['koordinat'] : '04° 02\' 16" S - 122° 29\' 12" E') ?>">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Jarak Tempuh (Hanya Angka, Satuan Otomatis) -->
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Jarak Tempuh ke LKP
            </label>
            <div class="flex items-center gap-2">
              <input type="number" step="any" min="0" id="jarak_angka"
                placeholder="Contoh: 15.5"
                value="<?= e($jarakVal) ?>"
                oninput="syncJarakDanRadial()"
                class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
              <select id="jarak_unit" onchange="syncJarakDanRadial()" class="px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="NM" <?= $jarakUnit === 'NM' ? 'selected' : '' ?>>NM</option>
                <option value="KM" <?= $jarakUnit === 'KM' ? 'selected' : '' ?>>KM</option>
              </select>
            </div>
            <input type="hidden" name="jarak" id="jarak" value="<?= e($operasi['jarak'] ?? '') ?>">
          </div>

          <!-- Arah / Radial (Hanya Angka, Derajat Otomatis) -->
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Arah / Radial / Heading LKP
            </label>
            <div class="relative flex items-center">
              <input type="number" step="any" min="0" max="360" id="radial_angka"
                placeholder="Contoh: 045"
                value="<?= e($radialVal) ?>"
                oninput="syncJarakDanRadial()"
                class="w-full pl-3.5 pr-10 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500" />
              <span class="absolute right-3.5 text-xs font-bold text-slate-400 select-none">°</span>
            </div>
            <input type="hidden" name="radial" id="radial" value="<?= e($operasi['radial'] ?? '') ?>">
            <span class="text-[10px] text-slate-400 block mt-1">Sudut bearing kompas (0° - 360°)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- POIN 4: Kronologi Kejadian & Asisten AI -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-purple-500/15 text-purple-600 dark:text-purple-400 flex items-center justify-center font-black text-xs">
            04
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              4. Kronologi Kejadian
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Deskripsi urutan kronologis kejadian musibah / kecelakaan</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" id="btn-quick-draft-ai" onclick="quickDraftAiKronologi(this)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-500/10 hover:bg-purple-600 text-purple-700 dark:text-purple-300 hover:text-white border border-purple-500/20 hover:border-purple-600 shadow-sm transition cursor-pointer" title="Susun narasi kronologi resmi secara instan dengan AI Gemini">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span class="btn-text">✨ Draf Cepat AI</span>
          </button>
          <button type="button" id="btn-open-ai-modal" onclick="openAiKronologiModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-600 hover:bg-purple-700 text-white shadow-sm transition cursor-pointer" title="Buka dialog interaktif Asisten AI untuk menyusun narasi kronologi">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span>Asisten AI Kronologi</span>
          </button>
        </div>
      </div>

      <div class="space-y-3">
        <textarea name="kronologi" id="kronologi" rows="4" required
          placeholder="Tuliskan kronologi kejadian secara runtut, lugas, dan baku sesuai format Basarnas..."
          class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs leading-relaxed focus:outline-none focus:ring-2 focus:ring-orange-500"><?= e($operasi['kronologi'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- POIN 5: Data Korban Dinamis (POB) -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center font-black text-xs">
            05
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              5. Data Korban Dinamis (Person On Board / POB)
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Daftar identitas korban, usia, jenis kelamin, serta status pencarian</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
            <span>Total POB:</span>
            <input type="number" name="jumlah_pob" id="jumlah_pob" min="1" value="<?= e($operasi['jumlah_pob'] ?? '1') ?>" class="w-16 px-2 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-center font-mono font-bold text-xs" />
          </div>
          <button type="button" onclick="addKorbanRow()" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>Tambah Korban</span>
          </button>
        </div>
      </div>

      <!-- Table / Cards List of Victims -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs" id="table-korban">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase tracking-wider text-[11px] font-bold">
              <th class="py-2 px-2 w-10 text-center">No</th>
              <th class="py-2 px-3">Nama Lengkap Korban <span class="text-red-500">*</span></th>
              <th class="py-2 px-3 w-28">Umur</th>
              <th class="py-2 px-3 w-32">Jenis Kelamin</th>
              <th class="py-2 px-3 w-48">Status Korban</th>
              <th class="py-2 px-3">Alamat / Domisili</th>
              <th class="py-2 px-2 w-12 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody id="korban-body" class="divide-y divide-slate-100 dark:divide-slate-800/80">
            <?php 
            $korbanList = $operasi['korban'] ?? [
                ['nama_korban' => '', 'umur' => '', 'jk' => 'Laki-laki', 'alamat' => '', 'status_korban' => 'DP']
            ];
            foreach ($korbanList as $idx => $k): 
            ?>
            <tr class="korban-row">
              <td class="row-num py-3 px-2 text-center font-mono font-bold text-slate-400"><?= $idx + 1 ?></td>
              <td class="py-3 px-3">
                <input type="text" name="korban[<?= $idx ?>][nama_korban]" required placeholder="Nama korban..." value="<?= e($k['nama_korban']) ?>" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-orange-500" />
              </td>
              <td class="py-3 px-3">
                <input type="text" name="korban[<?= $idx ?>][umur]" placeholder="Contoh: 35 Thn" value="<?= e($k['umur']) ?>" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-orange-500" />
              </td>
              <td class="py-3 px-3">
                <select name="korban[<?= $idx ?>][jk]" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-orange-500">
                  <option value="Laki-laki" <?= ($k['jk'] ?? '') === 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                  <option value="Perempuan" <?= ($k['jk'] ?? '') === 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                </select>
              </td>
              <td class="py-3 px-3">
                <select name="korban[<?= $idx ?>][status_korban]" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-1 focus:ring-orange-500">
                  <option value="DP" <?= ($k['status_korban'] ?? 'DP') === 'DP' ? 'selected' : '' ?>>🟡 Dalam Pencarian (DP)</option>
                  <option value="S" <?= ($k['status_korban'] ?? '') === 'S' ? 'selected' : '' ?>>🟢 Selamat (S)</option>
                  <option value="MD" <?= ($k['status_korban'] ?? '') === 'MD' ? 'selected' : '' ?>>🔴 Meninggal Dunia (MD)</option>
                  <option value="H" <?= ($k['status_korban'] ?? '') === 'H' ? 'selected' : '' ?>>⚪ Hilang / Nihil (H)</option>
                </select>
              </td>
              <td class="py-3 px-3">
                <input type="text" name="korban[<?= $idx ?>][alamat]" placeholder="Alamat domisili..." value="<?= e($k['alamat']) ?>" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-orange-500" />
              </td>
              <td class="py-3 px-2 text-center">
                <button type="button" onclick="removeKorbanRow(this)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-500/10 transition cursor-pointer" title="Hapus Korban">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- POIN 6 & 7: Unsur SAR Terlibat & Alut/Palsar -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- POIN 6: Unsur SAR Terlibat (Interactive Reorderable Editor with Master Data) -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4 transition-colors">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center font-black text-xs">
              06
            </div>
            <div>
              <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                6. Unsur SAR Terlibat
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih dari Master Data Tim Basarnas, atur jumlah personil, dan susun urutan prioritas</p>
            </div>
          </div>
          <span class="text-[10px] font-mono text-slate-400">Personil & Satuan</span>
        </div>

        <!-- Master Data Selector (Tanpa Rekomendasi Cepat Sesuai Permintaan) -->
        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-3">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
              Pilih Master Tim / Unsur:
            </label>
            <select id="select-master-tim" onchange="pickMasterTim(this.value)" class="flex-1 px-3 py-1.5 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">-- Cari / Pilih dari Master Data Tim Basarnas --</option>
              <?php foreach ($masterTim as $mt): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_tim' => $mt['id_tim'], 'nama_tim' => $mt['nama_tim']]), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($mt['nama_tim']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Input Bar (Nama Unsur, Jumlah Personil, Tambah) -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="sm:col-span-8">
            <input type="text" id="unsur-input-nama" placeholder="Ketik nama unsur (contoh: Tim Rescue KPP Kendari, Polairud)..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
          <div class="sm:col-span-3 flex items-center gap-1.5">
            <input type="number" id="unsur-input-jml" min="1" max="999" value="1" placeholder="Qty" class="w-full px-2 py-2 text-xs text-center font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 shrink-0">Org</span>
          </div>
          <div class="sm:col-span-1">
            <button type="button" onclick="handleAddUnsurClick()" class="w-full h-full py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold flex items-center justify-center shadow transition-all cursor-pointer" title="Tambah Unsur ke Daftar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
        </div>

        <!-- Reorderable List Container -->
        <div id="unsur-items-container" class="space-y-2 min-h-[60px]">
          <!-- Injected via JavaScript -->
        </div>

        <!-- Hidden inputs for backend & WA generator synchronization -->
        <input type="hidden" name="detail_unsur_json" id="detail_unsur_json" value="">
        <textarea name="unsur_terlibat" id="unsur_terlibat" class="hidden"><?= e($operasi['unsur_terlibat'] ?? '') ?></textarea>
      </div>

      <!-- POIN 7: Alut & Palsar Terlibat (Interactive Reorderable Editor with Master Data) -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4 transition-colors">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center font-black text-xs">
              07
            </div>
            <div>
              <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                7. Alut & Palsar Terlibat
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih dari Master Data Alut Basarnas, atur jumlah, dan susun urutan prioritas</p>
            </div>
          </div>
          <span class="text-[10px] font-mono text-slate-400">Armada & Alat</span>
        </div>

        <!-- Master Data Selector & Preset Chips -->
        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-3">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
              Pilih Master Alut:
            </label>
            <select id="select-master-alut" onchange="pickMasterAlut(this.value)" class="flex-1 px-3 py-1.5 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">-- Cari / Pilih dari Master Data Alut Basarnas --</option>
              <?php foreach ($masterAlut as $ma): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_alut' => $ma['id_alut'], 'nama_alut' => $ma['nama_alut'], 'satuan' => $ma['satuan'] ?? 'Unit', 'kategori' => $ma['kategori'] ?? 'ALUT']), ENT_QUOTES, 'UTF-8') ?>">
                  [<?= htmlspecialchars($ma['kategori'] ?? 'ALUT') ?>] <?= htmlspecialchars($ma['nama_alut']) ?> (<?= htmlspecialchars($ma['satuan'] ?? 'Unit') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Input Bar (Nama, Jumlah, Satuan, Tambah) -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="sm:col-span-7">
            <input type="text" id="alut-input-nama" placeholder="Ketik nama alut / palsar (contoh: Mobil Rescue, RIB 01)..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
          <div class="sm:col-span-2">
            <input type="number" id="alut-input-jml" min="1" max="999" value="1" placeholder="Qty" class="w-full px-2 py-2 text-xs text-center font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
          <div class="sm:col-span-2">
            <select id="alut-input-satuan" class="w-full px-2 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="Unit">Unit</option>
              <option value="Set">Set</option>
              <option value="Buah">Buah</option>
              <option value="Paket">Paket</option>
              <option value="Batang">Batang</option>
              <option value="Orang">Orang</option>
            </select>
          </div>
          <div class="sm:col-span-1">
            <button type="button" onclick="handleAddAlutClick()" class="w-full h-full py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold flex items-center justify-center shadow transition-all cursor-pointer" title="Tambah Alut ke Daftar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
        </div>

        <!-- Reorderable List Container -->
        <div id="alut-items-container" class="space-y-2 min-h-[60px]">
          <!-- Injected via JavaScript -->
        </div>

        <!-- Hidden inputs for backend & WA generator synchronization -->
        <input type="hidden" name="detail_alut_json" id="detail_alut_json" value="">
        <textarea name="alut_palsar" id="alut_palsar" class="hidden"><?= e($operasi['alut_palsar'] ?? '') ?></textarea>
      </div>

    </div>

    <!-- POIN 8: Kondisi Cuaca & Faktor Penghambat -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 flex items-center justify-center font-black text-xs">
            08
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              8. Kondisi Cuaca & Faktor Penghambat
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Data meteorologi BMKG dan kendala operasional di lapangan</p>
          </div>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30">
          BMKG & KENDALA
        </span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Kondisi Cuaca (BMKG)
          </label>
          <input type="text" name="cuaca" id="cuaca"
            placeholder="Contoh: Cerah Berawan"
            value="<?= e($operasi['cuaca'] ?? 'Cerah Berawan') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
          
          <div class="flex flex-wrap items-center gap-1 mt-1.5">
            <?php foreach (['Cerah Berawan', 'Berawan', 'Hujan Ringan', 'Hujan Sedang'] as $c): ?>
              <button type="button" onclick="document.getElementById('cuaca').value='<?= $c ?>'" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-orange-500">
                <?= $c ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Tinggi Gelombang
          </label>
          <input type="text" name="gelombang" id="gelombang"
            placeholder="Contoh: 0.5 - 1.25 M"
            value="<?= e($operasi['gelombang'] ?? '0.5 - 1.25 M') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
          
          <div class="flex flex-wrap items-center gap-1 mt-1.5">
            <?php foreach (['0.5 - 1.25 M', '1.25 - 2.5 M', '2.5 - 4.0 M'] as $g): ?>
              <button type="button" onclick="document.getElementById('gelombang').value='<?= $g ?>'" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-orange-500">
                <?= $g ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Arah & Kecepatan Angin
          </label>
          <input type="text" name="angin" id="angin"
            placeholder="Contoh: Barat Daya, 5 - 15 Knots"
            value="<?= e($operasi['angin'] ?? 'Barat Daya, 5 - 15 Knots') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
          
          <div class="flex flex-wrap items-center gap-1 mt-1.5">
            <?php foreach (['Barat Daya, 5-15 Knots', 'Timur Laut, 10-20 Knots', 'Selatan, 5-10 Knots'] as $a): ?>
              <button type="button" onclick="document.getElementById('angin').value='<?= $a ?>'" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-orange-500">
                <?= $a ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Faktor Penghambat
          </label>
          <input type="text" name="faktor_penghambat" id="faktor_penghambat"
            placeholder="Contoh: Nihil / Arus Deras"
            value="<?= e($operasi['faktor_penghambat'] ?? 'Nihil') ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
          
          <div class="flex flex-wrap items-center gap-1 mt-1.5">
            <?php foreach (['Nihil', 'Arus Deras', 'Jarak Pandang Terbatas', 'Blank Spot'] as $fp): ?>
              <button type="button" onclick="document.getElementById('faktor_penghambat').value='<?= $fp ?>'" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-orange-500">
                <?= $fp ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Sticky Bottom Actions Bar -->
    <div class="sticky bottom-4 z-20 p-4 rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-slate-200 dark:border-slate-800 shadow-xl flex items-center justify-between gap-4 transition-colors">
      <a href="<?= BASE_URL ?>/index.php" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold text-xs transition cursor-pointer">
        Batal
      </a>

      <div class="flex items-center gap-3">
        <button type="button" onclick="previewWhatsApp()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 font-bold text-xs border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
          <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
          <span>Pratinjau Format WA</span>
        </button>

        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 active:scale-95 text-white font-black text-xs shadow-lg shadow-orange-600/30 transition cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span><?= $editMode ? 'Simpan Perubahan' : 'Inisiasi & Terbitkan Laporan Awal' ?></span>
        </button>
      </div>
    </div>
  </form>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Modal 1: WhatsApp Preview Modal with Official "12 Oktober 2026" Date Format -->
<div id="modal-wa-preview" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-emerald-500/15 text-emerald-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Pratinjau Format WhatsApp Resmi</h3>
          <p class="text-[11px] text-slate-400">Format pesan baku FILE AKTIF Basarnas Kendari</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-wa-preview')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
        ✕
      </button>
    </div>

    <div class="p-6 overflow-y-auto flex-1">
      <pre id="wa-preview-text" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed select-all"></pre>
    </div>

    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
      <button type="button" onclick="closeModal('modal-wa-preview')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
        Tutup
      </button>
      <button type="button" onclick="copyWaText()" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition cursor-pointer">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
        <span>Salin Teks WhatsApp</span>
      </button>
    </div>
  </div>
</div>



<!-- Modal 3: AI Kronologi Assistant Modal -->
<div id="modal-ai-kronologi" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-purple-500/15 text-purple-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Asisten AI Kronologi Basarnas</h3>
          <p class="text-[11px] text-slate-400">Menyusun ulang dan merapikan kalimat laporan mentah menjadi format narasi resmi</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-ai-kronologi')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
        ✕
      </button>
    </div>

    <div class="p-6 space-y-4 overflow-y-auto">
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          Laporan / Catatan Mentah dari Pelapor:
        </label>
        <textarea id="ai-draft-input" rows="4" placeholder="Tuliskan cerita/laporan mentah kejadian di sini (contoh: kemarin sore sekitar jam 5 terjadi kapal mati mesin di selat buton, korban 2 orang...)" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
        <p class="text-[10px] text-purple-600 dark:text-purple-400 mt-1">💡 Asisten AI berfokus penuh menyusun ulang kalimat laporan mentah di atas tanpa mengubah waktu atau alur kejadian yang Anda tulis.</p>

        <!-- Presets inside modal -->
        <div class="flex flex-wrap items-center gap-1.5 mt-2">
          <span class="text-[10px] text-slate-400 font-semibold mr-1">Preset Cepat:</span>
          <button type="button" onclick="setAiDraftInput('Perahu nelayan/longboat dengan mesin mati dilaporkan terombang-ambing di perairan akibat cuaca buruk dan kehabisan bahan bakar. Korban meminta pertolongan SAR.')" class="px-2 py-0.5 rounded-lg text-[10px] font-medium bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/40 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 transition cursor-pointer">
            🚢 Mesin Mati di Laut
          </button>
          <button type="button" onclick="setAiDraftInput('Korban pergi melaut seorang diri menggunakan perahu nelayan, namun hingga waktu yang ditentukan korban belum kembali ke dermaga. Perahu ditemukan dalam keadaan kosong.')" class="px-2 py-0.5 rounded-lg text-[10px] font-medium bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/40 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 transition cursor-pointer">
            🎣 Nelayan Belum Kembali
          </button>
          <button type="button" onclick="setAiDraftInput('Korban berenang/mandi di pantai/sungai kemudian terseret ombak dan arus deras lalu tenggelam. Warga sempat mencari namun hasil masih nihil.')" class="px-2 py-0.5 rounded-lg text-[10px] font-medium bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/40 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 transition cursor-pointer">
            🌊 Korban Tenggelam
          </button>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="button" id="btn-generate-ai" onclick="generateAiKronologi()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition cursor-pointer">
          ⚡ Susun Narasi Resmi
        </button>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          Hasil Narasi Kronologi Resmi Basarnas:
        </label>
        <textarea id="ai-result-output" rows="4" placeholder="Hasil olahan narasi AI akan muncul di sini..." class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs leading-relaxed font-mono focus:outline-none"></textarea>
      </div>
    </div>

    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
      <button type="button" onclick="closeModal('modal-ai-kronologi')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
        Batal
      </button>
      <button type="button" onclick="applyAiKronologi()" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md transition cursor-pointer">
        Terapkan ke Form
      </button>
    </div>
  </div>
</div>

<script>
// Helper Presets
function setPresetOps(prefix) {
  const el = document.getElementById('nama_operasi');
  el.value = prefix;
  el.focus();
}

function setEtaPreset(text) {
  document.getElementById('eta_lokasi').value = text;
}

function insertKronologiPreset(text) {
  document.getElementById('kronologi').value = text;
}

// ── Interactive Unsur SAR Terlibat Editor (Reorderable + Master Data Reference) ──
let currentUnsurList = <?= json_encode($initialUnsurData) ?>;

function renderUnsurList() {
  const container = document.getElementById('unsur-items-container');
  if (!container) return;
  container.innerHTML = '';

  if (currentUnsurList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada unsur SAR yang ditambahkan. Gunakan dropdown Master Data Tim atau form di atas.
      </div>
    `;
  } else {
    currentUnsurList.forEach((item, idx) => {
      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_unsur || '')}" onchange="updateUnsurItem(${idx}, 'nama_unsur', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${item.jumlah_personil || 1}" onchange="updateUnsurItem(${idx}, 'jumlah_personil', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-orange-600 dark:text-orange-400 focus:outline-none focus:border-orange-500" />
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Org</span>
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

  syncUnsurFields();
}

function pickMasterTim(jsonStr) {
  if (!jsonStr) return;
  try {
    const item = JSON.parse(jsonStr);
    if (item && item.nama_tim) {
      document.getElementById('unsur-input-nama').value = item.nama_tim;
      document.getElementById('unsur-input-jml').value = '1';
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
  const sel = document.getElementById('select-master-tim');
  if (sel) sel.value = '';

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
  const textEl = document.getElementById('unsur_terlibat');
  if (jsonEl) jsonEl.value = JSON.stringify(currentUnsurList);

  if (textEl) {
    if (currentUnsurList.length === 0) {
      textEl.value = '';
    } else {
      textEl.value = currentUnsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil || 1} Org`).join('\n');
    }
  }

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }
}

// ── DMS Coordinates Auto-Formatter (No special symbol input required) ──
function padZero(num, size = 2) {
  let s = String(num === null || num === undefined ? 0 : num);
  while (s.length < size) s = "0" + s;
  return s;
}

function updateDmsCoordinate() {
  const latDeg = parseInt(document.getElementById('dms_lat_deg')?.value || '0', 10);
  const latMin = parseInt(document.getElementById('dms_lat_min')?.value || '0', 10);
  const latSecVal = parseFloat(document.getElementById('dms_lat_sec')?.value || '0');
  const latDir = document.getElementById('dms_lat_dir')?.value || 'S';

  const lngDeg = parseInt(document.getElementById('dms_lng_deg')?.value || '0', 10);
  const lngMin = parseInt(document.getElementById('dms_lng_min')?.value || '0', 10);
  const lngSecVal = parseFloat(document.getElementById('dms_lng_sec')?.value || '0');
  const lngDir = document.getElementById('dms_lng_dir')?.value || 'E';

  const latSecStr = isNaN(latSecVal) ? '00' : (latSecVal % 1 === 0 ? padZero(Math.floor(latSecVal), 2) : latSecVal.toFixed(1));
  const lngSecStr = isNaN(lngSecVal) ? '00' : (lngSecVal % 1 === 0 ? padZero(Math.floor(lngSecVal), 2) : lngSecVal.toFixed(1));

  const latStr = `${padZero(latDeg, 2)}° ${padZero(latMin, 2)}' ${latSecStr}" ${latDir}`;
  const lngStr = `${padZero(lngDeg, 3)}° ${padZero(lngMin, 2)}' ${lngSecStr}" ${lngDir}`;
  const formatted = `${latStr} - ${lngStr}`;

  const coordInput = document.getElementById('koordinat');
  if (coordInput) coordInput.value = formatted;

  const previewEl = document.getElementById('dms_preview_text');
  if (previewEl) previewEl.textContent = formatted;

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }
}

function bindDmsInputEvents() {
  ['dms_lat_deg', 'dms_lat_min', 'dms_lat_sec', 'dms_lat_dir', 'dms_lng_deg', 'dms_lng_min', 'dms_lng_sec', 'dms_lng_dir'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', updateDmsCoordinate);
      el.addEventListener('change', updateDmsCoordinate);
    }
  });
}

// ── Interactive Alut & Palsar Editor (Reorderable + Master Data Reference) ──
let currentAlutList = <?= json_encode($initialAlutData) ?>;

function renderAlutList() {
  const container = document.getElementById('alut-items-container');
  if (!container) return;
  container.innerHTML = '';

  if (currentAlutList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada armada alut / palsar yang ditambahkan. Gunakan dropdown Master Data atau rekomendasi cepat di atas.
      </div>
    `;
  } else {
    currentAlutList.forEach((item, idx) => {
      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_alut || '')}" onchange="updateAlutItem(${idx}, 'nama_alut', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${item.jumlah_alut || 1}" onchange="updateAlutItem(${idx}, 'jumlah_alut', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-orange-600 dark:text-orange-400 focus:outline-none focus:border-orange-500" />
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

function quickAddAlutPreset(nama, jml, sat) {
  currentAlutList.push({
    nama_alut: nama,
    jumlah_alut: jml || 1,
    satuan: sat || 'Unit'
  });
  renderAlutList();
  if (typeof showToast === 'function') {
    showToast(`Alut "${nama}" ditambahkan ke daftar!`, 'success');
  }
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
  const textEl = document.getElementById('alut_palsar');
  if (jsonEl) jsonEl.value = JSON.stringify(currentAlutList);

  if (textEl) {
    if (currentAlutList.length === 0) {
      textEl.value = '';
    } else {
      textEl.value = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
    }
  }

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }
}

// Helper escapeHtml
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function syncJarakDanRadial() {
  const numJarak = document.getElementById('jarak_angka')?.value?.trim();
  const unitJarak = document.getElementById('jarak_unit')?.value || 'NM';
  const hiddenJarak = document.getElementById('jarak');
  if (hiddenJarak) {
    hiddenJarak.value = numJarak ? `${numJarak} ${unitJarak}` : '';
  }

  const numRadial = document.getElementById('radial_angka')?.value?.trim();
  const hiddenRadial = document.getElementById('radial');
  if (hiddenRadial) {
    hiddenRadial.value = numRadial ? `${numRadial}°` : '';
  }

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }
}

document.addEventListener('DOMContentLoaded', function() {
  renderAlutList();
  renderUnsurList();
  bindDmsInputEvents();
  updateDmsCoordinate();
  syncJarakDanRadial();
});

// Dynamic Victims
function addKorbanRow() {
  const tbody = document.getElementById('korban-body');
  const count = tbody.querySelectorAll('.korban-row').length;
  const num = count + 1;

  const tr = document.createElement('tr');
  tr.className = 'korban-row';
  tr.innerHTML = `
    <td class="row-num py-3 px-2 text-center font-mono font-bold text-slate-400">${num}</td>
    <td class="py-3 px-3">
      <input type="text" name="korban[${count}][nama_korban]" required placeholder="Nama korban..." class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-orange-500" />
    </td>
    <td class="py-3 px-3">
      <input type="text" name="korban[${count}][umur]" placeholder="Contoh: 28 Thn" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-orange-500" />
    </td>
    <td class="py-3 px-3">
      <select name="korban[${count}][jk]" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-orange-500">
        <option value="Laki-laki">Laki-laki</option>
        <option value="Perempuan">Perempuan</option>
      </select>
    </td>
    <td class="py-3 px-3">
      <select name="korban[${count}][status_korban]" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-1 focus:ring-orange-500">
        <option value="DP" selected>🟡 Dalam Pencarian (DP)</option>
        <option value="S">🟢 Selamat (S)</option>
        <option value="MD">🔴 Meninggal Dunia (MD)</option>
        <option value="H">⚪ Hilang / Nihil (H)</option>
      </select>
    </td>
    <td class="py-3 px-3">
      <input type="text" name="korban[${count}][alamat]" placeholder="Alamat domisili..." class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-orange-500" />
    </td>
    <td class="py-3 px-2 text-center">
      <button type="button" onclick="removeKorbanRow(this)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-500/10 transition cursor-pointer" title="Hapus Korban">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </td>
  `;
  tbody.appendChild(tr);

  // Update total POB input
  const pobInput = document.getElementById('jumlah_pob');
  if (pobInput) pobInput.value = num;
}

function removeKorbanRow(btn) {
  const tbody = document.getElementById('korban-body');
  const rows = tbody.querySelectorAll('.korban-row');
  if (rows.length <= 1) {
    if (typeof showToast === 'function') showToast('Minimal harus ada 1 data korban atau POB', 'warning');
    return;
  }
  btn.closest('.korban-row').remove();
  
  // Re-number
  tbody.querySelectorAll('.korban-row').forEach((r, idx) => {
    r.querySelector('.row-num').textContent = idx + 1;
    r.querySelectorAll('input, select').forEach(el => {
      if (el.name) el.name = el.name.replace(/\[\d+\]/, `[${idx}]`);
    });
  });

  const pobInput = document.getElementById('jumlah_pob');
  if (pobInput) pobInput.value = tbody.querySelectorAll('.korban-row').length;
}

// Modal Helper Delegation
function openModal(id) {
  const m = typeof id === 'string' ? document.getElementById(id) : id;
  if (!m) return;
  m.classList.remove('hidden');
  m.classList.add('open');
  m.style.display = 'flex';
}

function closeModal(id) {
  const m = typeof id === 'string' ? document.getElementById(id) : id;
  if (!m) return;
  m.classList.remove('open');
  m.classList.add('hidden');
  m.style.display = 'none';
}
window.openModal = openModal;
window.closeModal = closeModal;

// WhatsApp Preview Generator with Strict "12 Oktober 2026" Date Format
function previewWhatsApp() {
  const namaOps = document.getElementById('nama_operasi').value || 'Operasi SAR';
  const pelapor = document.getElementById('nama_pelapor').value || 'Bpk. Pelapor';
  const hub = document.getElementById('hubungan_pelapor').value || 'Masyarakat';
  const hp = document.getElementById('no_telp_pelapor').value || '-';

  const waktuLapor = document.getElementById('waktu_lapor').value;
  const waktuBerangkat = document.getElementById('waktu_berangkat').value;
  const etaTiba = document.getElementById('eta_tiba').value;
  const etaLokasi = document.getElementById('eta_lokasi').value || '-';
  const tim = document.getElementById('tim_berangkat').value || 'Tim Rescue Kansar Kendari';

  const lokasi = document.getElementById('lokasi_kejadian').value || '-';
  const koordinat = document.getElementById('koordinat').value || '-';
  const jarak = document.getElementById('jarak').value || '-';
  const radial = document.getElementById('radial').value || '';
  const jarakDanRadial = (jarak && jarak !== '-') ? (jarak + (radial && radial !== '-' ? ` (Radial ${radial})` : '')) : '-';

  const kronologi = document.getElementById('kronologi').value || '-';
  const unsur = document.getElementById('unsur_terlibat').value || '-';
  const alut = document.getElementById('alut_palsar').value || '-';

  const cuaca = document.getElementById('cuaca').value || 'Cerah Berawan';
  const gelombang = document.getElementById('gelombang').value || '0.5 - 1.25 M';
  const angin = document.getElementById('angin').value || 'Barat Daya, 5 - 15 Knots';
  const penghambat = document.getElementById('faktor_penghambat').value || 'Nihil';

  // Format Dates with Indonesian format: "12 Oktober 2026"
  const tglLaporStr = window.formatTanggalIndo(waktuLapor);
  const jamLaporStr = window.formatJamWita(waktuLapor);

  const tglBerangkatStr = window.formatTanggalIndo(waktuBerangkat);
  const jamBerangkatStr = window.formatJamWita(waktuBerangkat);

  const tglEtaStr = window.formatTanggalIndo(etaTiba);
  const jamEtaStr = window.formatJamWita(etaTiba);

  const todayStr = window.formatTanggalIndo(new Date());

  // Victim Statistics & Text
  const korbanRows = document.querySelectorAll('.korban-row');
  let kList = [];
  let sCount = 0, mdCount = 0, dpCount = 0, hCount = 0;

  korbanRows.forEach((r, idx) => {
    const kName = r.querySelector('input[name*="[nama_korban]"]')?.value || '-';
    const kUmur = r.querySelector('input[name*="[umur]"]')?.value || '-';
    const kJk = r.querySelector('select[name*="[jk]"]')?.value || '-';
    const kAlamat = r.querySelector('input[name*="[alamat]"]')?.value || '-';
    const kStatus = r.querySelector('select[name*="[status_korban]"]')?.value || 'DP';

    if (kStatus === 'S') sCount++;
    else if (kStatus === 'MD') mdCount++;
    else if (kStatus === 'DP') dpCount++;
    else if (kStatus === 'H') hCount++;

    const statusLabel = kStatus === 'DP' ? '' : ` (${kStatus === 'S' ? 'Selamat' : (kStatus === 'MD' ? 'Meninggal Dunia' : 'Hilang')})`;
    kList.push(`${idx + 1}. Nama : ${kName}${statusLabel}\n   Umur : ${kUmur}\n   Jenis Kelamin : ${kJk}\n   Alamat : ${kAlamat}`);
  });

  const totalKorban = korbanRows.length;
  const identitasKorbanText = kList.length > 0 ? kList.join('\n\n') : '1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -';

  const preview = `FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
LAPORAN AWAL OPS SAR

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
${todayStr}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan ${namaOps}.
__________________________________________________

1. Sumber Info kejadian :
${pelapor} (${hub} / No. HP ${hp})
Pada Tanggal ${tglLaporStr} pukul ${jamLaporStr} Wita

2. Lokasi Kejadian :
Lokasi : ${lokasi}
Koordinat : ${koordinat}
jarak tempuh : ${jarakDanRadial}
Estimasi waktu tempuh : ${etaLokasi}

3. Pelaksanaan SAR
Pada Tanggal ${tglBerangkatStr} pukul ${jamBerangkatStr} Wita, ${tim} berangkat ke LKP untuk melaksanakan Operasi SAR, dengan estimasi waktu tempuh ETA ${tglEtaStr} pukul ${jamEtaStr} WITA.

4. Kronologi Kejadian
${kronologi}

5. Data Korban: ${totalKorban} orang
• S : ${sCount} orang
• MD : ${mdCount} orang
• DP : ${dpCount} orang
• H : ${hCount} orang

Identitas Korban :
${identitasKorbanText}

6. Unsur SAR Terlibat
${unsur}

7. Alut dan Palsar
${alut}

8. Faktor Penghambat
${penghambat}

9. Kondisi Cuaca :
• cuaca : ${cuaca}
• Gelombang : ${gelombang}
• Angin: ${angin}

Demikian kami laporkan, mohon petunjuk dan arahan

Ttd

KKS Kendari
===========================
🏢 Kantor Pencarian Dan Pertolongan Kendari
📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116
☎️ Telp. : (0401) 3196557
📠 Fax. : (0401) 3196558
📱 WhatsApp : +62811-4034-115
🚨 Emergency Call : 115
🌐 Website : Www.Kendari.Basarnas.go.id
📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id
📲 Medsos : @Basarnaskendari`;

  document.getElementById('wa-preview-text').textContent = preview;
  openModal('modal-wa-preview');
}

function copyWaText() {
  const txt = document.getElementById('wa-preview-text').textContent;
  if (typeof copyToClipboard === 'function') {
    copyToClipboard(txt, 'Naskah Laporan Awal WhatsApp Berhasil Disalin!');
  } else {
    navigator.clipboard.writeText(txt).then(() => {
      if (typeof showToast === 'function') showToast('Naskah WhatsApp Berhasil Disalin!', 'success');
    });
  }
}

// Preset input helper for modal
function setAiDraftInput(text) {
  const el = document.getElementById('ai-draft-input');
  if (el) {
    el.value = text;
    el.focus();
  }
}

// AI Kronologi Modal Handler
function openAiKronologiModal() {
  const current = document.getElementById('kronologi')?.value || '';
  const draftInputEl = document.getElementById('ai-draft-input');
  if (draftInputEl && !draftInputEl.value.trim() && current.trim()) {
    draftInputEl.value = current;
  }
  openModal('modal-ai-kronologi');
}

// Helper to gather rich form context for AI
function getAiKronologiFormContext() {
  const namaOps = document.getElementById('nama_operasi')?.value.trim() || '';
  const lokasi = document.getElementById('lokasi_kejadian')?.value.trim() || '';
  const waktu = document.getElementById('waktu_kejadian')?.value.trim() || '';
  const namaPelapor = document.getElementById('nama_pelapor')?.value.trim() || '';
  const hubPelapor = document.getElementById('hubungan_pelapor')?.value.trim() || '';
  const pelapor = namaPelapor ? (namaPelapor + (hubPelapor ? ' (' + hubPelapor + ')' : '')) : '';
  const waktuLapor = document.getElementById('waktu_lapor')?.value || '';

  // Extract korban list from table if any
  const korbanRows = document.querySelectorAll('.korban-row');
  let korbanArr = [];
  korbanRows.forEach(r => {
    const kName = r.querySelector('input[name*="[nama_korban]"]')?.value.trim();
    const kUmur = r.querySelector('input[name*="[umur]"]')?.value.trim();
    if (kName && kName !== '-') {
      korbanArr.push(`${kName}${kUmur && kUmur !== '-' ? ' (' + kUmur + ' thn)' : ''}`);
    }
  });

  return {
    namaOperasi: namaOps,
    lokasi,
    waktuKejadian: waktu,
    pelapor,
    waktuLapor,
    korbanInfo: korbanArr.length > 0 ? korbanArr.join(', ') : ''
  };
}

// 1-Click Quick AI Draft directly into form #kronologi
async function quickDraftAiKronologi(btn) {
  const kronologiEl = document.getElementById('kronologi');
  const existingKronologi = kronologiEl ? kronologiEl.value.trim() : '';
  const ctx = getAiKronologiFormContext();

  let draftInput = existingKronologi;
  if (!draftInput) {
    if (ctx.namaOperasi || ctx.lokasi) {
      draftInput = `Terjadi musibah sesuai data operasi ${ctx.namaOperasi} di lokasi ${ctx.lokasi}. Korban membutuhkan pertolongan SAR segera.`;
    } else {
      draftInput = 'Korban dilaporkan mengalami musibah saat beraktivitas dan memerlukan pertolongan SAR segera.';
    }
  }

  const originalContent = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin w-3.5 h-3.5 inline mr-1 text-purple-600 dark:text-purple-300" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Menyusun AI...</span>`;
  }

  if (typeof showToast === 'function') showToast('Menyusun draf kronologi resmi dengan AI Gemini...', 'info');

  try {
    const res = await fetch('<?= BASE_URL ?>/api/ai/draft-kronologi', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        draftInput,
        ...ctx
      })
    });
    const json = await res.json();
    if (json.success && json.text) {
      if (kronologiEl) {
        kronologiEl.value = json.text;
        kronologiEl.classList.add('ring-4', 'ring-purple-500/50', 'transition-all');
        setTimeout(() => {
          kronologiEl.classList.remove('ring-4', 'ring-purple-500/50');
        }, 2000);
      }
      if (typeof showToast === 'function') showToast('Draf kronologi resmi berhasil disusun oleh AI!', 'success');
    } else {
      if (typeof showToast === 'function') showToast(json.error || 'Gagal memproses draf AI', 'error');
    }
  } catch (err) {
    console.error('quickDraftAiKronologi error:', err);
    if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan AI', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalContent;
    }
  }
}

// Generate narrative inside Modal (Asisten AI Kronologi)
// Berfokus murni menyusun ulang dan merapikan kalimat laporan mentah tanpa menimpa waktu/fakta dengan data form!
async function generateAiKronologi() {
  const draftInput = document.getElementById('ai-draft-input')?.value.trim() || '';

  if (!draftInput) {
    if (typeof showToast === 'function') showToast('Silakan tuliskan cerita atau laporan mentah terlebih dahulu untuk disusun ulang oleh Asisten AI', 'warning');
    document.getElementById('ai-draft-input')?.focus();
    return;
  }

  const btn = document.getElementById('btn-generate-ai');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin w-3.5 h-3.5 inline mr-1 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Menyusun Ulang Narasi...</span>`;
  }

  if (typeof showToast === 'function') showToast('Asisten AI sedang menyusun ulang laporan mentah...', 'info');

  try {
    const res = await fetch('<?= BASE_URL ?>/api/ai/refine-kronologi', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        rawDraft: draftInput,
        mode: 'refine_raw'
      })
    });
    const json = await res.json();
    if (json.success && json.text) {
      document.getElementById('ai-result-output').value = json.text;
      if (typeof showToast === 'function') showToast('Laporan mentah berhasil disusun ulang menjadi narasi resmi!', 'success');
    } else {
      if (typeof showToast === 'function') showToast(json.error || 'Gagal menyusun narasi AI', 'error');
    }
  } catch (err) {
    console.error('generateAiKronologi error:', err);
    if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan AI', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

function applyAiKronologi() {
  const result = document.getElementById('ai-result-output')?.value.trim() || '';
  if (!result) {
    if (typeof showToast === 'function') showToast('Belum ada narasi yang dihasilkan', 'warning');
    return;
  }
  const kronologiEl = document.getElementById('kronologi');
  if (kronologiEl) {
    kronologiEl.value = result;
    kronologiEl.classList.add('ring-4', 'ring-purple-500/50', 'transition-all');
    setTimeout(() => {
      kronologiEl.classList.remove('ring-4', 'ring-purple-500/50');
    }, 2000);
  }
  closeModal('modal-ai-kronologi');
  if (typeof showToast === 'function') showToast('Narasi resmi diterapkan ke kronologi form!', 'success');
}


</script>
