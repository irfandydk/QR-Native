<?php
/**
 * QR-SAR - Form Inisiasi Laporan Awal Operasi SAR
 * KPP Kendari - Basarnas
 * 100% Faithful to Reference FormInisiasi.tsx
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$masterAlut = MasterModel::getAlut();
$masterTim = MasterModel::getTim();
$sarUnits = class_exists('GisModel') ? GisModel::getMasterBases() : [];
if (empty($sarUnits)) {
    $sarUnits = MasterModel::getSarUnits();
}
$defaultUnit = array_values(array_filter($sarUnits, fn($u) => !empty($u['is_default'])))[0] ?? ($sarUnits[0] ?? null);
$settings = class_exists('SettingsModel') ? SettingsModel::get() : [];
$satkerKode = $settings['satker_kode'] ?? 'SAR-304';
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
        ['nama_alut' => 'Palsar Medis', 'jumlah_alut' => 1, 'satuan' => 'Set'],
        ['nama_alut' => 'Palsar Komunikasi', 'jumlah_alut' => 1, 'satuan' => 'Set'],
        ['nama_alut' => 'Palsar Evakuasi', 'jumlah_alut' => 1, 'satuan' => 'Set']
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
        ['nama_unsur' => 'SMC', 'jumlah_personil' => 1],
        ['nama_unsur' => 'Staf', 'jumlah_personil' => 1]
    ];
}

// Parse DMS Coordinate Components (Degrees, Minutes, Seconds, Direction)
$rawCoord = $operasi['koordinat'] ?? '';
$latDeg = '04'; $latMin = '02'; $latSec = '16'; $latDir = 'S';
$lngDeg = '122'; $lngMin = '29'; $lngSec = '12'; $lngDir = 'E';

if (!empty($rawCoord)) {
    $parsed = GeoHelper::parseCoordinates($rawCoord);
    if ($parsed && isset($parsed['lat'], $parsed['lng'])) {
        $cLat = (float)$parsed['lat'];
        $cLng = (float)$parsed['lng'];

        $latAbs = abs($cLat);
        $dLat = (int)floor($latAbs);
        $mLatFloat = ($latAbs - $dLat) * 60;
        $mLat = (int)floor($mLatFloat);
        $sLat = round(($mLatFloat - $mLat) * 60, 1);
        if ($sLat >= 60.0) { $sLat = 0.0; $mLat++; }
        if ($mLat >= 60) { $mLat = 0; $dLat++; }

        $lngAbs = abs($cLng);
        $dLng = (int)floor($lngAbs);
        $mLngFloat = ($lngAbs - $dLng) * 60;
        $mLng = (int)floor($mLngFloat);
        $sLng = round(($mLngFloat - $mLng) * 60, 1);
        if ($sLng >= 60.0) { $sLng = 0.0; $mLng++; }
        if ($mLng >= 60) { $mLng = 0; $dLng++; }

        $latDeg = sprintf('%02d', $dLat);
        $latMin = sprintf('%02d', $mLat);
        $latSec = ($sLat == floor($sLat)) ? sprintf('%02d', (int)$sLat) : (string)$sLat;
        $latDir = $cLat < 0 ? 'S' : 'N';

        $lngDeg = sprintf('%03d', $dLng);
        $lngMin = sprintf('%02d', $mLng);
        $lngSec = ($sLng == floor($sLng)) ? sprintf('%02d', (int)$sLng) : (string)$sLng;
        $lngDir = $cLng < 0 ? 'W' : 'E';
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

require_once dirname(__DIR__, 2) . '/models/GisModel.php';
$existingGisPoints = $editMode ? GisModel::getPoints((int)($operasi['id_operasi'] ?? 0), false) : [];
$existingGisRoutes = $editMode ? GisModel::getRoutes((int)($operasi['id_operasi'] ?? 0), false) : [];
$existingLkpPoint = null;
foreach ($existingGisPoints as $pt) {
    if (($pt['category'] ?? '') === 'LKP' || ($pt['pointType'] ?? '') === 'lkp') {
        $existingLkpPoint = $pt;
        break;
    }
}
$existingRoute = !empty($existingGisRoutes) ? $existingGisRoutes[0] : null;
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

  <!-- LocalStorage Draft Restore Banner (Only in Create Mode) -->
  <div id="draft-restore-alert" class="hidden p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-xs">
    <div class="flex items-center gap-2.5 text-amber-900 dark:text-amber-200">
      <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div>
        <span class="font-bold">Draf Laporan Otomatis Ditemukan!</span>
        <p class="text-[11px] text-amber-700 dark:text-amber-300/80">Sistem mendeteksi draf pengisian laporan awal yang tersimpan dari sesi sebelumnya.</p>
      </div>
    </div>
    <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
      <button type="button" onclick="restoreLocalStorageDraft()" class="px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold cursor-pointer transition shadow-xs">
        Pulihkan Draf
      </button>
      <button type="button" onclick="discardLocalStorageDraft()" class="px-3 py-1.5 rounded-xl border border-amber-500/30 hover:bg-amber-500/20 text-amber-800 dark:text-amber-300 font-semibold cursor-pointer transition">
        Abaikan / Hapus
      </button>
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
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
              Tim yang Diberangkatkan <span class="text-red-500">*</span>
            </label>
            <a href="<?= BASE_URL ?>/master.php?tab=tim" target="_blank" class="text-[11px] text-orange-600 dark:text-orange-400 hover:underline flex items-center gap-1 font-medium" title="Buka Manajemen Master Tim di Tab Baru">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
              <span>Kelola Master Tim</span>
            </a>
          </div>

          <?php 
            $currentTimVal = trim($operasi['tim_berangkat'] ?? 'Tim Rescue Kansar Kendari');
            $matchedTim = false;
            $selectedIdTim = 0;
            foreach ($masterTim as $mt) {
                if (strcasecmp(trim($mt['nama_tim']), $currentTimVal) === 0) {
                    $matchedTim = true;
                    $selectedIdTim = (int)$mt['id_tim'];
                    break;
                }
            }
          ?>
          <div class="space-y-2">
            <select id="select_tim_berangkat" onchange="handleSelectTimBerangkat(this)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 font-medium">
              <option value="">-- Pilih Tim Penyelamat dari Master Data --</option>
              <?php foreach ($masterTim as $mt): 
                $isSel = (strcasecmp(trim($mt['nama_tim']), $currentTimVal) === 0);
              ?>
                <option value="<?= e($mt['nama_tim']) ?>" data-id="<?= (int)$mt['id_tim'] ?>" <?= $isSel ? 'selected' : '' ?>>
                  🏢 <?= e($mt['nama_tim']) ?>
                </option>
              <?php endforeach; ?>
              <option value="__CUSTOM__" <?= (!$matchedTim && !empty($currentTimVal)) ? 'selected' : '' ?>>
                ✏️ + Ketik Nama Tim Kustom / Lainnya...
              </option>
            </select>

            <div id="wrapper_custom_tim" class="<?= $matchedTim ? 'hidden' : '' ?>">
              <input type="text" name="tim_berangkat" id="tim_berangkat"
                placeholder="Ketik nama tim yang diberangkatkan..."
                value="<?= e($currentTimVal) ?>"
                oninput="updateWaPreview()"
                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 font-medium" />
              <p class="text-[10px] text-slate-400 mt-1 italic">* Masukkan nama tim atau unsur khusus jika tidak terdaftar di Master Tim.</p>
            </div>
            <input type="hidden" name="id_tim_berangkat" id="id_tim_berangkat" value="<?= $selectedIdTim ?>" />
          </div>
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

        <!-- Pangkalan / Pos Asal Pemberangkatan (Origin Base) Dinamis -->
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <label class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                <span>📍 Pangkalan / Pos Awal Pemberangkatan (Origin):</span>
              </label>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Titik tolak tim penyelamat (Pos SAR / Kansar / USS / Dermaga) untuk perhitungan jarak & heading radial ke LKP</p>
            </div>
            <button type="button" onclick="calculateDistanceAndRadial(true)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-orange-500/10 hover:bg-orange-600 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/20 hover:border-orange-600 transition cursor-pointer shadow-xs shrink-0" title="Hitung ulang jarak nautical miles dan bearing derajat kompas dari pangkalan ini ke LKP">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              <span>⚡ Hitung Jarak & Radial Otomatis</span>
            </button>
          </div>
          
          <select id="origin_base_select" onchange="handleOriginBaseChange()" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500">
            <?php foreach ($sarUnits as $unit): 
                $cat = $unit['category'] ?? $unit['jenis_unit'] ?? 'POS SAR';
                $name = $unit['nama_unit'] ?? $unit['label'] ?? '';
                $iconEmoji = (stripos($name, 'dermaga') !== false) ? '⚓' : ((stripos($cat, 'kantor') !== false) ? '🏢' : '🚨');
            ?>
              <option value="<?= e($unit['id'] ?? $unit['id_unit']) ?>" 
                      data-lat="<?= $unit['latitude'] ?? $unit['lat'] ?>" 
                      data-lng="<?= $unit['longitude'] ?? $unit['lng'] ?>" 
                      data-dms="<?= e($unit['koordinat_dms'] ?? '') ?>"
                      data-icon="<?= e($unit['customIconUrl'] ?? '') ?>"
                      data-point-type="<?= e($unit['pointType'] ?? 'custom_upload') ?>"
                      data-category="<?= e($cat) ?>"
                      <?= (!empty($unit['is_default']) ? 'selected' : '') ?>>
                <?= $iconEmoji ?> [<?= e($cat) ?>] <?= e($name) ?> — [<?= e($unit['koordinat_dms'] ?? '') ?>]
              </option>
            <?php endforeach; ?>
          </select>
          <div id="origin_calc_feedback" class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold min-h-[16px]"></div>
        </div>

        <!-- Hidden Inputs for GIS Synchronization & Waypoint Persistence -->
        <input type="hidden" name="lkp_lat" id="lkp_lat" value="<?= e((string)($existingLkpPoint['lat'] ?? '')) ?>" />
        <input type="hidden" name="lkp_lng" id="lkp_lng" value="<?= e((string)($existingLkpPoint['lng'] ?? '')) ?>" />
        <input type="hidden" name="manual_route_json" id="manual_route_json" value="<?= e(!empty($existingRoute['points']) ? json_encode($existingRoute['points']) : '') ?>" />
        <input type="hidden" name="vessel_speed_knots" id="vessel_speed_knots" value="<?= e((string)($existingRoute['vesselSpeedKnots'] ?? '25')) ?>" />
        <input type="hidden" name="origin_lat" id="origin_lat" value="" />
        <input type="hidden" name="origin_lng" id="origin_lng" value="" />
        <input type="hidden" name="origin_name" id="origin_name" value="" />
        <input type="hidden" name="origin_icon" id="origin_icon" value="" />
        <input type="hidden" name="origin_point_type" id="origin_point_type" value="" />

        <!-- PETA INTERAKTIF PENENTUAN LKP & PLOTTING JALUR MANUAL (ROUTING) -->
        <div id="tactical-map-card" class="p-4 sm:p-5 rounded-2xl border border-orange-500/30 bg-gradient-to-b from-orange-500/5 to-slate-50 dark:to-slate-900/60 space-y-4 shadow-xs transition-all duration-300">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-3 border-b border-orange-500/10">
            <div class="space-y-0.5">
              <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center text-xs font-black">
                  🗺️
                </span>
                <label class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                  <span>Peta Taktis LKP & Plotting Jalur Tempuh</span>
                  <span id="route-mode-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                    GARIS LURUS
                  </span>
                </label>
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Klik atau geser pin 🚨 <strong>LKP</strong> pada peta untuk menentukan lokasi presisi, atau aktifkan <strong>Jalur Manual</strong> untuk merencanakan alur berlayar laut melewati selat/pulau.
              </p>
            </div>

            <!-- Toolbar Buttons -->
            <div class="flex items-center gap-1.5 flex-wrap sm:flex-nowrap shrink-0">
              <button type="button" onclick="centerMapOnLkp()" class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs flex items-center gap-1" title="Fokuskan peta ke titik koordinat LKP">
                <span>🎯 Ke LKP</span>
              </button>
              <button type="button" onclick="centerMapOnOrigin()" class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs flex items-center gap-1" title="Fokuskan peta ke pangkalan asal">
                <span>🏢 Ke Pangkalan</span>
              </button>
              <button type="button" onclick="openFullscreenMapModal()" class="px-3 py-1.5 rounded-xl text-[11px] font-bold bg-orange-600 hover:bg-orange-500 text-white transition cursor-pointer shadow-xs flex items-center gap-1" title="Buka peta ukuran penuh untuk plotting waypoint lebih leluasa">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                <span>Perbesar Peta</span>
              </button>
              <button type="button" id="btn-toggle-map-minimize" onclick="toggleTacticalMapMinimize()" class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs flex items-center gap-1" title="Minimize / sembunyikan tampilan peta untuk menghemat ruang">
                <span id="btn-minimize-icon">🔽</span>
                <span id="btn-minimize-text">Minimize</span>
              </button>
            </div>
          </div>

          <!-- Minimized Quick Status Strip (Tampil saat peta di-minimize) -->
          <div id="tactical-map-minimized-strip" class="hidden flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-orange-500/10 dark:bg-slate-800/80 border border-orange-500/20 text-xs">
            <div class="flex items-center gap-3 sm:gap-4 flex-wrap text-xs">
              <div class="flex items-center gap-1.5">
                <span class="text-base">🚨</span>
                <div>
                  <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-semibold">Koordinat LKP:</span>
                  <strong id="strip-summary-lkp" class="font-mono text-orange-600 dark:text-orange-400 font-bold">-</strong>
                </div>
              </div>

              <div class="border-l border-orange-500/20 pl-3 sm:pl-4">
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-semibold">Jarak Tempuh:</span>
                <strong id="strip-summary-dist" class="font-mono text-slate-800 dark:text-slate-200 font-bold">0.0 NM</strong>
              </div>

              <div class="border-l border-orange-500/20 pl-3 sm:pl-4">
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-semibold">Estimasi Waktu (ETA):</span>
                <strong id="strip-summary-eta" class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">-</strong>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <button type="button" onclick="applyTacticalRouteToForm()" class="px-2.5 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white font-bold text-[11px] shadow-xs transition cursor-pointer flex items-center gap-1" title="Terapkan jarak & estimasi waktu ke isian form">
                <span>⚡ Terapkan</span>
              </button>
              <button type="button" onclick="toggleTacticalMapMinimize(false)" class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold text-[11px] border border-slate-200 dark:border-slate-600 shadow-xs transition cursor-pointer flex items-center gap-1">
                <span>🔼 Tampilkan Peta</span>
              </button>
            </div>
          </div>

          <!-- Collapsible Map Body (Map Canvas + Routing & Speed Controls) -->
          <div id="tactical-map-body" class="space-y-4 transition-all duration-300">
            <!-- Map Container -->
            <div class="relative rounded-2xl overflow-hidden border border-slate-300 dark:border-slate-700 bg-slate-900 shadow-inner">
              <div id="lkp_inline_map" style="height: 350px; width: 100%; z-index: 1;"></div>
              
              <!-- Floating Quick Instructions overlay -->
              <div id="map-routing-instruction" class="absolute top-2.5 left-2.5 z-[1000] px-3 py-1.5 rounded-xl bg-slate-900/85 backdrop-blur-md text-white text-[11px] font-semibold border border-white/20 shadow-lg pointer-events-none transition-all flex items-center gap-1.5">
                <span>💡</span>
                <span id="map-instruction-text">Geser pin merah 🚨 untuk mengatur LKP, atau klik peta untuk pindah posisi.</span>
              </div>
            </div>

            <!-- Route Mode & Waypoint Controls -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 pt-1">
              <!-- Mode Switcher -->
              <div class="lg:col-span-5 flex flex-col gap-1.5">
                <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                  Metode Perhitungan Rute:
                </label>
                <div class="inline-flex p-1 rounded-xl bg-slate-200/70 dark:bg-slate-800/80 border border-slate-300/70 dark:border-slate-700">
                  <button type="button" id="btn-mode-direct" onclick="setRoutingMode('direct')" class="flex-1 px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-white dark:bg-slate-700 text-orange-600 dark:text-orange-400 shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                    <span>📐 Garis Lurus (Direct)</span>
                  </button>
                  <button type="button" id="btn-mode-manual" onclick="setRoutingMode('manual')" class="flex-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                    <span>🧭 Jalur Manual (Waypoint)</span>
                  </button>
                </div>

                <!-- Waypoint Actions (shown when manual mode active) -->
                <div id="manual-route-actions" class="hidden flex items-center gap-1.5 mt-1">
                  <button type="button" onclick="undoLastWaypoint()" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white border border-amber-500/20 transition cursor-pointer flex items-center gap-1">
                    <span>↩️ Undo Titik</span>
                  </button>
                  <button type="button" onclick="clearManualRouteWaypoints()" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-red-500/10 hover:bg-red-500 text-red-600 dark:text-red-400 hover:text-white border border-red-500/20 transition cursor-pointer flex items-center gap-1">
                    <span>🗑️ Reset Jalur</span>
                  </button>
                  <span id="waypoint-count-pill" class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                    0 Waypoint
                  </span>
                </div>
              </div>

              <!-- Speed & Terrain Selection (User emphasized: Presets + Manual Input) -->
              <div class="lg:col-span-7 flex flex-col gap-1.5">
                <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center justify-between">
                  <span>Kecepatan Alut & Kondisi Medan:</span>
                  <span class="text-[10px] text-slate-400 font-normal italic">Preset atau input manual</span>
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                  <!-- Preset Dropdown -->
                  <div class="sm:col-span-7">
                    <select id="select_speed_preset" onchange="handleSpeedPresetChange(this)" class="w-full px-2.5 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-orange-500">
                      <option value="25" data-unit="knots" selected>🚤 25 Knots — RIB Basarnas (Normal)</option>
                      <option value="18" data-unit="knots">🚢 18 Knots — KN SAR Pacitan / KRI</option>
                      <option value="15" data-unit="knots">🌊 15 Knots — RIB (Ombak / Cuaca Buruk)</option>
                      <option value="20" data-unit="knots">🛥️ 20 Knots — Speedboat Polairud</option>
                      <option value="10" data-unit="knots">🛶 10 Knots — Rubber Boat / Mopel 40 PK</option>
                      <option value="7" data-unit="knots">⛵ 7 Knots — Longboat Nelayan</option>
                      <option value="60" data-unit="kmh">🚙 60 Km/j — Rescue Car (Jalan Aspal)</option>
                      <option value="40" data-unit="kmh">🚛 40 Km/j — Truk SAR (Medan Sedang)</option>
                      <option value="4" data-unit="kmh">🚶 4 Km/j — Rescuer Jalan Kaki (Hutan)</option>
                      <option value="__CUSTOM__">✏️ Custom / Input Manual...</option>
                    </select>
                  </div>

                  <!-- Manual Speed Input with Unit Toggle -->
                  <div class="sm:col-span-5 flex items-center gap-1">
                    <input type="number" step="any" min="0.1" id="input_vessel_speed_num" value="<?= e((string)($existingRoute['vesselSpeedKnots'] ?? '25')) ?>" oninput="handleManualSpeedInput(this.value)" class="w-full px-2.5 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold text-center focus:outline-none focus:ring-1 focus:ring-orange-500" placeholder="25" title="Ketik kecepatan wahana secara manual" />
                    <select id="select_speed_unit" onchange="handleSpeedUnitChange(this.value)" class="px-2 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs font-bold focus:outline-none shrink-0">
                      <option value="knots">Kts</option>
                      <option value="kmh">Km/j</option>
                    </select>
                  </div>
                </div>

                <!-- Tactical Summary Bar & Apply to Form Button -->
                <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-xl bg-orange-500/10 border border-orange-500/20 mt-0.5">
                  <div class="flex items-center gap-3 text-xs flex-wrap">
                    <div>
                      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Jarak Tempuh:</span>
                      <strong id="tactical-summary-dist" class="font-mono text-orange-600 dark:text-orange-400 font-bold">0.0 NM</strong>
                      <span id="tactical-summary-km" class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">(0.0 KM)</span>
                    </div>
                    <div class="border-l border-orange-500/20 pl-3">
                      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Radial / Heading:</span>
                      <strong id="tactical-summary-radial" class="font-mono text-slate-800 dark:text-slate-200 font-bold">000°</strong>
                    </div>
                    <div class="border-l border-orange-500/20 pl-3">
                      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Estimasi Waktu (ETA):</span>
                      <strong id="tactical-summary-eta" class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">-</strong>
                    </div>
                  </div>

                  <button type="button" onclick="applyTacticalRouteToForm()" class="px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition cursor-pointer flex items-center gap-1.5 ml-auto" title="Terapkan jarak, radial, dan estimasi waktu tempuh dari kalkulasi peta ini ke isian form laporan awal">
                    <span>⚡ Terapkan ke Form</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
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
              <select id="jarak_unit" onchange="handleJarakUnitChange(this.value)" class="px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-orange-500">
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
            <span class="text-[10px] text-slate-400 block mt-1">Sudut bearing kompas dari pangkalan asal (0° - 360°)</span>
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
                <input type="hidden" name="korban[<?= $idx ?>][id_korban]" value="<?= (int)($k['id_korban'] ?? 0) ?>" />
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
          <input type="hidden" id="unsur-input-id" value="">
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
          <input type="hidden" id="alut-input-id" value="">
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
      <a href="<?= $editMode ? (BASE_URL . '/operasi_detail.php?id=' . (int)($operasi['id_operasi'] ?? 0)) : (BASE_URL . '/index.php') ?>" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold text-xs transition cursor-pointer">
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

<!-- Modal 4: Fullscreen Tactical Map Routing Modal -->
<div id="modal-fullscreen-map" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/75 backdrop-blur-md hidden" style="display: none;">
  <div class="w-full max-w-7xl h-[92vh] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col">
    <!-- Modal Header -->
    <div class="px-6 py-3.5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/80 dark:bg-slate-950/80 shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center text-base font-black">
          🗺️
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
            <span>Peta Taktis LKP & Plotting Jalur Tempuh SAR (Fullscreen)</span>
            <span id="map-modal-route-mode-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
              GARIS LURUS
            </span>
          </h3>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">Tentukan titik LKP dengan presisi dan buat rute navigasi laut melewati selat/pulau</p>
        </div>
      </div>

      <!-- Quick Actions & Close -->
      <div class="flex items-center gap-2 shrink-0">
        <button type="button" onclick="centerMapOnLkp(true)" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
          🎯 Ke LKP
        </button>
        <button type="button" onclick="centerMapOnOrigin(true)" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
          🏢 Ke Pangkalan
        </button>
        <button type="button" onclick="closeFullscreenMapModal()" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
          ✕
        </button>
      </div>
    </div>

    <!-- Modal Map Container -->
    <div class="relative flex-1 w-full bg-slate-950 overflow-hidden">
      <div id="lkp_modal_map" style="width: 100%; height: 100%; z-index: 1;"></div>

      <!-- Floating Toolbar inside Modal -->
      <div class="absolute top-4 left-4 z-[1000] flex flex-col gap-2 max-w-md pointer-events-auto">
        <div class="p-3 rounded-2xl bg-slate-900/90 backdrop-blur-md border border-white/20 shadow-xl space-y-2 text-white">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-orange-400">Mode Rute:</span>
            <div class="inline-flex p-0.5 rounded-lg bg-slate-800 border border-slate-700">
              <button type="button" id="map-modal-btn-mode-direct" onclick="setRoutingMode('direct')" class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-orange-600 text-white cursor-pointer">
                Direct
              </button>
              <button type="button" id="map-modal-btn-mode-manual" onclick="setRoutingMode('manual')" class="px-2.5 py-1 rounded-md text-[11px] font-medium text-slate-300 hover:text-white cursor-pointer">
                Manual Waypoints
              </button>
            </div>
          </div>
          <div id="map-modal-manual-actions" class="hidden flex items-center gap-1.5 pt-1 border-t border-white/10">
            <button type="button" onclick="undoLastWaypoint()" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-500/20 hover:bg-amber-500 text-amber-300 hover:text-white border border-amber-500/30 transition cursor-pointer">
              ↩️ Undo Titik
            </button>
            <button type="button" onclick="clearManualRouteWaypoints()" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-red-500/20 hover:bg-red-500 text-red-300 hover:text-white border border-red-500/30 transition cursor-pointer">
              🗑️ Reset Jalur
            </button>
            <span id="map-modal-waypoint-count-pill" class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-slate-800 text-slate-400">
              0 WP
            </span>
          </div>
          <p class="text-[10px] text-slate-300 leading-tight">
            💡 Pada mode manual, klik di atas peta laut untuk menambah waypoint belokan. Titik LKP dapat digeser kapan saja.
          </p>
        </div>
      </div>

      <!-- Floating Live Metrics Pill -->
      <div class="absolute bottom-4 left-4 z-[1000] p-3 rounded-2xl bg-slate-900/90 backdrop-blur-md border border-white/20 shadow-xl text-white flex items-center gap-4 pointer-events-auto">
        <div>
          <span class="text-[10px] text-slate-400 block">Jarak Total:</span>
          <strong id="map-modal-tactical-dist" class="font-mono text-sm text-orange-400 font-bold">0.0 NM</strong>
          <span id="map-modal-tactical-km" class="text-[11px] text-slate-400 font-mono">(0.0 KM)</span>
        </div>
        <div class="border-l border-white/15 pl-3">
          <span class="text-[10px] text-slate-400 block">Radial / Heading:</span>
          <strong id="map-modal-tactical-radial" class="font-mono text-sm text-slate-200 font-bold">000°</strong>
        </div>
        <div class="border-l border-white/15 pl-3">
          <span class="text-[10px] text-slate-400 block">Estimasi ETA:</span>
          <strong id="map-modal-tactical-eta" class="font-mono text-sm text-emerald-400 font-bold">-</strong>
        </div>
      </div>
    </div>

    <!-- Modal Footer -->
    <div class="px-6 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/80 dark:bg-slate-950/80 shrink-0">
      <button type="button" onclick="closeFullscreenMapModal()" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
        Tutup
      </button>
      <button type="button" onclick="applyAndCloseFullscreenMapModal()" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-md transition cursor-pointer">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
        <span>Terapkan Hasil Plotting & Tutup</span>
      </button>
    </div>
  </div>
</div>

<script>
window.__OPS_FORM_CONFIG__ = <?= jsonSafe([
    'initialUnsurData' => $initialUnsurData,
    'initialAlutData' => $initialAlutData,
    'sarUnits' => $sarUnits,
    'satkerKode' => $satkerKode,
    'isEditMode' => $editMode,
    'existingLkpPoint' => $existingLkpPoint,
    'existingRoute' => $existingRoute,
    'initialCoord' => !empty($operasi['koordinat']) ? $operasi['koordinat'] : '04° 02\' 16" S - 122° 29\' 12" E'
]) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/pages/operasi_form_map.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/pages/operasi_form.js?v=<?= APP_VERSION ?>"></script>
