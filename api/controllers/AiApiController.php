<?php
/**
 * QR-SAR - Artificial Intelligence (Gemini AI) API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class AiApiController extends BaseApiController {
    private static function prepareAiRequest(): void {
        self::requireAuth();
        self::checkAiRateLimit();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function handle(string $path, string $method, array $input): bool {
        // 1. Refine Kronologi
        if ($path === '/ai/refine-kronologi' && $method === 'POST') {
            self::prepareAiRequest();
            $raw = $input['rawDraft'] ?? ($input['draftInput'] ?? '');
            self::json(AiHelper::refineRawDraft($raw));
            return true;
        }

        // 2. Draft Kronologi
        if ($path === '/ai/draft-kronologi' && $method === 'POST') {
            self::prepareAiRequest();
            $mode = $input['mode'] ?? 'quick_draft';
            if ($mode === 'refine_raw') {
                $raw = $input['rawDraft'] ?? ($input['draftInput'] ?? '');
                self::json(AiHelper::refineRawDraft($raw));
                return true;
            }

            $draft = $input['draftInput'] ?? '';
            $nama = $input['namaOperasi'] ?? '';
            $lokasi = $input['lokasi'] ?? '';
            $waktu = $input['waktuKejadian'] ?? '';
            $pelapor = $input['pelapor'] ?? '';
            $waktuLapor = $input['waktuLapor'] ?? '';
            $korbanInfo = $input['korbanInfo'] ?? '';
            self::json(AiHelper::draftKronologi($draft, $nama, $lokasi, $waktu, [
                'pelapor' => $pelapor,
                'waktuLapor' => $waktuLapor,
                'korbanInfo' => $korbanInfo,
            ]));
            return true;
        }

        // 3. Refine SITREP
        if ($path === '/ai/refine-sitrep' && $method === 'POST') {
            self::prepareAiRequest();
            $raw = $input['rawDraft'] ?? ($input['catatan'] ?? ($input['rawText'] ?? ($input['draftInput'] ?? ($input['isi_laporan'] ?? ''))));
            $hariKe = $input['hariKe'] ?? ($input['hari_ke'] ?? 'H.1');
            $nama = $input['namaOperasi'] ?? ($input['nama_operasi'] ?? '');
            self::json(AiHelper::refineSitrepRawDraft($raw, $hariKe, $nama, [
                'unsur' => $input['unsur'] ?? '',
                'alut' => $input['alut'] ?? '',
                'hasil' => $input['hasil'] ?? '',
                'kendala' => $input['kendala'] ?? ''
            ]));
            return true;
        }

        // 4. Draft SITREP
        if ($path === '/ai/draft-sitrep' && $method === 'POST') {
            self::prepareAiRequest();
            $mode = $input['mode'] ?? 'quick_draft';
            $hariKe = $input['hariKe'] ?? ($input['hari_ke'] ?? 'H.1');
            $nama = $input['namaOperasi'] ?? ($input['nama_operasi'] ?? '');
            $unsur = $input['unsur'] ?? '';
            $alut = $input['alut'] ?? '';
            $hasil = $input['hasil'] ?? 'NIHIL';
            $kendala = $input['kendala'] ?? 'Nihil';

            if ($mode === 'refine_raw') {
                $raw = $input['rawDraft'] ?? ($input['catatan'] ?? ($input['rawText'] ?? ($input['draftInput'] ?? ($input['tindakanHariIni'] ?? ''))));
                self::json(AiHelper::refineSitrepRawDraft($raw, $hariKe, $nama, [
                    'unsur' => $unsur,
                    'alut' => $alut,
                    'hasil' => $hasil,
                    'kendala' => $kendala
                ]));
                return true;
            }

            $tindakan = $input['tindakanHariIni'] ?? ($input['draftInput'] ?? ($input['catatan'] ?? ''));
            self::json(AiHelper::draftSitrep($hariKe, $tindakan, $hasil, $kendala, $nama, [
                'unsur' => $unsur,
                'alut' => $alut
            ]));
            return true;
        }

        return false;
    }
}
