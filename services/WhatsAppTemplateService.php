<?php
/**
 * QR-SAR - WhatsApp & Messaging Template Service (Format Resmi Telegram Siaga Basarnas)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dedicated domain service for formatting operational dispatch messages, situational reports,
 * and mission debriefings intended for Kabasarnas, Pimti, and operational WhatsApp groups.
 */

declare(strict_types=1);

require_once __DIR__ . '/SarCalculationService.php';
require_once __DIR__ . '/DocumentNumberService.php';
require_once __DIR__ . '/../helpers/GeoHelper.php';

class WhatsAppTemplateService {
    /**
     * Generate standard Basarnas official message header (FILE AKTIF).
     */
    public static function getHeaderWA(string $judulLaporan, string $namaOperasi, ?string $waktu = null, ?string $nomor = null): string {
        $tglFormatted = SarCalculationService::formatTanggalIndo($waktu ?: date('Y-m-d H:i:s'));
        $judulUpper = strtoupper($judulLaporan);
        $nomorLine = !empty($nomor) ? "\nNO: {$nomor}" : "";
        return <<<HEADER
FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
{$judulUpper}{$nomorLine}

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
{$tglFormatted}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan {$namaOperasi}.
__________________________________________________
HEADER;
    }

    /**
     * Generate standard Basarnas official message footer and emergency contact info.
     */
    public static function getFooterWA(): string {
        return <<<FOOTER
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
📲 Medsos : @Basarnaskendari
FOOTER;
    }

    /**
     * Clean SRU area coordinates from unwanted prefixes and GIS pattern metadata.
     */
    public static function cleanSruCoordinates(?string $koor): string {
        if (!$koor) return '';
        $lines = explode("\n", str_replace("\r", "", $koor));
        $cleanLines = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^(pola\s*pencarian\s*:|total\s*sapuan\s*:|\[pola\s*sar|tipe\s*:)/i', $trim)) {
                continue;
            }
            $cleanLines[] = $line;
        }
        return trim(implode("\n", $cleanLines));
    }

    /**
     * Generate WhatsApp message for Laporan Awal Operasi SAR.
     */
    /** Daftar "ALUT DAN PALSAR" naskah WA: satu judul gabungan, isi diurutkan Darat → Laut → Udara → PALSAR. */
    private static function formatAlutLines(array $alut): string {
        if (empty($alut)) {
            return "• Belum ada data alut";
        }
        $aList = [];
        foreach (AlutKategori::sort($alut) as $a) {
            $sat = ($a['satuan'] ?? '') ?: 'Unit';
            $aList[] = "• {$a['jumlah_alut']} {$sat} {$a['nama_alut']}";
        }
        return implode("\n", $aList);
    }

    public static function generateLaporanAwalWA(array $ops, array $korban = [], array $unsur = [], array $alut = []): string {
        $header = self::getHeaderWA(
            'LAPORAN AWAL OPS SAR',
            $ops['nama_operasi'] ?? 'Operasi SAR',
            $ops['waktu_lapor'] ?? date('Y-m-d H:i:s')
        );

        $tglLapor = SarCalculationService::formatTanggalSimple($ops['waktu_lapor'] ?? null);
        $jamLapor = SarCalculationService::formatJamWITA($ops['waktu_lapor'] ?? null);

        $tglBerangkat = SarCalculationService::formatTanggalSimple($ops['waktu_berangkat'] ?? null);
        $jamBerangkat = SarCalculationService::formatJamWITA($ops['waktu_berangkat'] ?? null);

        $waktuEta = $ops['eta_tiba'] ?? null;
        $tglEta = SarCalculationService::formatTanggalSimple($waktuEta);
        $jamEta = SarCalculationService::formatJamWITA($waktuEta);

        $pobTotal = max((int)($ops['jumlah_pob'] ?? 0), count($korban));
        $stats = SarCalculationService::hitungStatistikKorban($korban, $pobTotal);

        // Identitas korban text
        $kList = [];
        if (!empty($korban)) {
            foreach ($korban as $idx => $k) {
                $num = $idx + 1;
                $statusK = $k['status_korban'] ?? $k['status'] ?? 'DP';
                $label = $statusK === 'DP' ? '' : ' (' . SarCalculationService::getLabelStatusKorban($statusK) . ')';
                $namaKorban = $k['nama_korban'] ?? $k['nama'] ?? '-';
                $jkKorban = $k['jk'] ?? $k['jenis_kelamin'] ?? '-';
                $umurKorban = !empty($k['umur']) ? $k['umur'] : '-';
                $alamatKorban = !empty($k['alamat']) ? $k['alamat'] : '-';
                $kList[] = "{$num}. Nama : {$namaKorban}{$label}\n   Umur : {$umurKorban}\n   Jenis Kelamin : {$jkKorban}\n   Alamat : {$alamatKorban}";
            }
        }

        if ($stats['unrecorded_dp'] > 0) {
            $nextNum = count($kList) + 1;
            $kList[] = "{$nextNum}. {$stats['unrecorded_dp']} Orang Dalam Pencarian (Identitas Belum Terdata / Belum Diketahui)";
        }

        if (empty($kList)) {
            $identitasText = "1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -";
        } else {
            $identitasText = implode("\n\n", $kList);
        }

        // Unsur SAR
        if (empty($unsur)) {
            $unsurText = "• Belum ada data unsur SAR";
        } else {
            $uList = [];
            foreach ($unsur as $u) {
                $uList[] = "• {$u['nama_unsur']} ({$u['jumlah_personil']} Org)";
            }
            $unsurText = implode("\n", $uList);
        }

        // Alut & Palsar
        $alutText = self::formatAlutLines($alut);

        $pelapor = ($ops['nama_pelapor'] ?? '') ?: '-';
        $hub = ($ops['hubungan_pelapor'] ?? '') ?: '-';
        $hp = ($ops['no_telp_pelapor'] ?? '') ?: '-';
        $tim = ($ops['tim_berangkat'] ?? '') ?: 'Tim SAR';

        $jarakStr = ($ops['jarak'] ?? '') ?: '-';
        if (!empty($ops['radial'])) {
            $jarakStr .= " (Radial {$ops['radial']})";
        }

        $lokasiKejadian = $ops['lokasi_kejadian'] ?? '-';
        $koordinatOps = $ops['koordinat'] ?? '-';
        $etaLokasi = ($ops['eta_lokasi'] ?? '') ?: '-';
        $kronologi = ($ops['kronologi'] ?? '') ?: '-';
        $faktorPenghambat = ($ops['faktor_penghambat'] ?? '') ?: 'Nihil';
        $cuacaOps = ($ops['cuaca'] ?? '') ?: '-';
        $gelombangOps = ($ops['gelombang'] ?? '') ?: '-';
        $anginOps = ($ops['angin'] ?? '') ?: '-';

        $dataKorbanHeader = "5. Data Korban: {$stats['total']} orang";
        if ($stats['unrecorded_dp'] > 0) {
            $dataKorbanHeader .= " (POB: {$stats['total']} Org, Teridentifikasi: {$stats['identified']} Org, Belum Terdata: {$stats['unrecorded_dp']} Org)";
        }

        $body = <<<BODY
1. Sumber Info kejadian :
{$pelapor} ({$hub} / No. HP {$hp})
Pada Tanggal {$tglLapor} pukul {$jamLapor} Wita

2. Lokasi Kejadian :
Lokasi : {$lokasiKejadian}
Koordinat : {$koordinatOps}
jarak tempuh : {$jarakStr}
Estimasi waktu tempuh : {$etaLokasi}

3. Pelaksanaan SAR
Pada Tanggal {$tglBerangkat} pukul {$jamBerangkat} Wita, {$tim} berangkat ke LKP untuk melaksanakan Operasi SAR, dengan estimasi waktu tempuh ETA {$tglEta} pukul {$jamEta} WITA.

4. Kronologi Kejadian
{$kronologi}

{$dataKorbanHeader}
• S : {$stats['s']} orang
• MD : {$stats['md']} orang
• DP : {$stats['dp']} orang
• H : {$stats['h']} orang

Identitas Korban :
{$identitasText}

6. Unsur SAR Terlibat
{$unsurText}

7. Alut dan Palsar
{$alutText}

8. Faktor Penghambat
{$faktorPenghambat}

9. Kondisi Cuaca :
• cuaca : {$cuacaOps}
• Gelombang : {$gelombangOps}
• Angin: {$anginOps}
BODY;

        return $header . "\n\n" . $body . "\n\n" . self::getFooterWA();
    }

    /**
     * Generate WhatsApp message for Rencana Operasi SAR (RENOPS).
     */
    public static function generateRenopsWA(array $ops, array $renops, array $srus = [], array $korban = [], array $unsur = [], array $alut = []): string {
        $rawHari = trim((string)($renops['hari_ke'] ?? 'H.1'));
        $hariKe = is_numeric($rawHari) ? "H.{$rawHari}" : strtoupper($rawHari);
        $namaOps = $ops['nama_operasi'] ?? 'Operasi SAR';
        $waktuLap = $renops['tanggal_laporan'] ?? date('Y-m-d');

        $header = self::getHeaderWA("RENCANA OPS SAR {$hariKe}", "Rencana Ops {$namaOps}", $waktuLap);

        $twBriefing = SarCalculationService::formatTW($renops['waktu_briefing'] ?? null);
        $twMulai = SarCalculationService::formatTW($renops['waktu_mulai_ops'] ?? null);

        // SRUs
        if (empty($srus)) {
            $sruText = "Pembagian SRU dan area pencarian belum ditetapkan.";
        } else {
            $sList = [];
            foreach ($srus as $s) {
                $rawName = trim($s['nama_sru'] ?? 'SRU');
                $cleanName = trim($rawName, "* \t\n\r\0\x0B") ?: 'SRU';
                $nameFormatted = "*{$cleanName}* melakukan penyisiran dgn koordinat :";

                $koor = self::cleanSruCoordinates($s['koordinat_area'] ?? '') ?: (($ops['koordinat'] ?? '') ?: '-');
                $pola = !empty($s['pola_pencarian']) ? $s['pola_pencarian'] : '-';
                
                $lines = [
                    $nameFormatted,
                    $koor
                ];

                if (!empty($s['luas_area'])) {
                    $luasVal = trim($s['luas_area']);
                    if (!preg_match('/^luas\s*area/i', $luasVal)) {
                        $lines[] = "Luas area pencarian : {$luasVal}";
                    } else {
                        $lines[] = $luasVal;
                    }
                }

                $lines[] = "Pola pencarian : {$pola}";

                if (!empty($s['total_sapuan'])) {
                    $sapuanVal = trim($s['total_sapuan']);
                    if (!preg_match('/^total\s*sapuan\s*:/i', $sapuanVal)) {
                        $lines[] = "Total sapuan : {$sapuanVal}";
                    } else {
                        $lines[] = $sapuanVal;
                    }
                }

                $sList[] = implode("\n", $lines);
            }
            $sruText = implode("\n\n", $sList);
        }

        if (empty($unsur)) {
            $unsurText = "• Belum ada data unsur SAR";
        } else {
            $uList = [];
            foreach ($unsur as $u) {
                $uList[] = "• {$u['nama_unsur']} : {$u['jumlah_personil']} Orang";
            }
            $unsurText = implode("\n", $uList);
        }

        $alutText = self::formatAlutLines($alut);

        $cuaca = !empty($renops['cuaca']) ? $renops['cuaca'] : (!empty($ops['cuaca']) ? $ops['cuaca'] : '-');
        $sruCount = count($srus);

        $body = <<<BODY
Rencana Ops {$hariKe} :

• PD {$twBriefing} Tim SAR Gabungan Melaksanakan Brifing Operasi.
• Pada {$twMulai} Tim SAR Gabungan Melanjutkan Operasi Pencarian {$hariKe} Sesuai Rencana Operasi {$hariKe} dengan Tim SAR gabungan menuju ke entri point masing-masing.
Dengan Tim dibagi {$sruCount} SRU dengan area pencarian sbb :

{$sruText}

1. Unsur SAR Terlibat :
{$unsurText}

2. Alut dan Palsar :
{$alutText}

3. Kondisi Cuaca :
• Cuaca: {$cuaca}
(Sumber BMKG)
BODY;

        return $header . "\n\n" . $body . "\n\n" . self::getFooterWA();
    }

    /**
     * Generate WhatsApp message for Laporan Perkembangan (LAPBANG / SITREP) & Penutupan.
     */
    public static function generateLapbangWA(array $ops, array $laporan, array $korban = [], array $unsur = [], array $alut = [], bool $isPenutupan = false): string {
        $rawHari = trim((string)($laporan['hari_ke'] ?? 'H.1'));
        $hariKe = is_numeric($rawHari) ? "H.{$rawHari}" : strtoupper($rawHari);
        $judul = $isPenutupan ? "LAPBANG OPS SAR {$hariKe} DAN PENUTUPAN" : "LAPBANG OPS SAR {$hariKe}";
        $namaOps = $ops['nama_operasi'] ?? 'Operasi SAR';
        $waktuLap = $laporan['waktu_laporan'] ?? date('Y-m-d H:i:s');

        $header = self::getHeaderWA($judul, "Perkembangan Ops {$namaOps}", $waktuLap);

        $klausulPenutupan = '';
        if ($isPenutupan) {
            $waktuDebrief = ($laporan['waktu_debrief'] ?? '') ?: (($laporan['waktu_laporan'] ?? '') ?: date('Y-m-d H:i:s'));
            $tglDebrief = SarCalculationService::formatTanggalSimple($waktuDebrief);
            $jamDebrief = SarCalculationService::formatJamWITA($waktuDebrief);
            $klausulPenutupan = "\n\n• Pada tanggal {$tglDebrief} Pukul {$jamDebrief} WITA, Tim SAR Gabungan melakukan debriefing penutupan Ops SAR terhadap {$namaOps}, selanjutnya operasi SAR diusulkan ditutup dan unsur SAR yang terlibat kembali ke kesatuannya masing-masing dengan ucapan terima kasih.";
        }

        $pobTotal = max((int)($ops['jumlah_pob'] ?? 0), count($korban));
        $stats = SarCalculationService::hitungStatistikKorban($korban, $pobTotal);

        $kList = [];
        if (!empty($korban)) {
            foreach ($korban as $idx => $k) {
                $num = $idx + 1;
                $label = SarCalculationService::getLabelStatusKorban($k['status_korban'] ?? '');
                $namaK = !empty($k['nama_korban']) ? (string)$k['nama_korban'] : '-';
                $umurK = !empty($k['umur']) ? (string)$k['umur'] : '-';
                $jkK = !empty($k['jk']) ? (string)$k['jk'] : (!empty($k['jenis_kelamin']) ? (string)$k['jenis_kelamin'] : '-');
                $alamatK = !empty($k['alamat']) ? (string)$k['alamat'] : '-';
                $kList[] = "{$num}. Nama : {$namaK} [Status: {$label}]\n   Umur : {$umurK}\n   Jenis Kelamin : {$jkK}\n   Alamat : {$alamatK}";
            }
        }

        if ($stats['unrecorded_dp'] > 0) {
            $nextNum = count($kList) + 1;
            $kList[] = "{$nextNum}. {$stats['unrecorded_dp']} Orang Dalam Pencarian (Identitas Belum Terdata / Belum Diketahui)";
        }

        if (empty($kList)) {
            $identitasText = "1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -";
        } else {
            $identitasText = implode("\n\n", $kList);
        }

        if (empty($unsur)) {
            $unsurText = "• Belum ada data unsur SAR";
        } else {
            $uList = [];
            foreach ($unsur as $u) {
                $uList[] = "• {$u['nama_unsur']} : {$u['jumlah_personil']} Orang";
            }
            $unsurText = implode("\n", $uList);
        }

        $alutText = self::formatAlutLines($alut);

        $isi = !empty($laporan['isi_laporan']) ? $laporan['isi_laporan'] : '-';

        $dataKorbanHeader = "1. Data Korban: {$stats['total']} Orang";
        if ($stats['unrecorded_dp'] > 0) {
            $dataKorbanHeader .= " (POB: {$stats['total']} Org, Teridentifikasi: {$stats['identified']} Org, Belum Terdata: {$stats['unrecorded_dp']} Org)";
        }

        $body = <<<BODY
Hasil Ops SAR {$hariKe}:

{$isi}{$klausulPenutupan}

{$dataKorbanHeader}
• S : {$stats['s']} ORANG
• MD : {$stats['md']} ORANG
• DP : {$stats['dp']} ORANG
• H : {$stats['h']} ORANG

2. Identitas Korban
{$identitasText}

3. Unsur SAR Terlibat
{$unsurText}

4. Alut dan Palsar
{$alutText}
BODY;

        return $header . "\n\n" . $body . "\n\n" . self::getFooterWA();
    }
}
