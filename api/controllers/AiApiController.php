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

        // 4. Test AI API Key Connection (Admin Only)
        if ($path === '/ai/test-key' && $method === 'POST') {
            self::requireAdmin();
            self::requireCsrf($input);
            self::checkAiRateLimit();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $keyId = trim((string)($input['key_id'] ?? ''));
            $provider = strtolower(trim((string)($input['provider'] ?? '')));
            $apiKey = trim((string)($input['api_key'] ?? ''));
            $model = trim((string)($input['model'] ?? ''));

            if ($keyId !== '') {
                $resolved = AiConfigService::resolveKeyById($keyId);
                if (!$resolved) {
                    self::error('Profil API Key tidak ditemukan atau kunci tidak dapat dibaca.', 404, 'AI_KEY_NOT_FOUND');
                }
                if ($provider === '') {
                    $provider = (string)$resolved['provider'];
                }
                if ($apiKey === '') {
                    $apiKey = (string)$resolved['api_key'];
                }
                if ($model === '') {
                    $model = (string)$resolved['model'];
                }
            }

            if ($provider === '' || $apiKey === '') {
                self::error('Provider dan API Key wajib diisi untuk pengujian koneksi.', 400, 'VALIDATION_ERROR');
            }

            self::json(AiHelper::testConnection($provider, $apiKey, $model));
            return true;
        }

        // 5. Fetch All Available Live Models for an API Key (Admin Only — supports Tier 1 / full catalog)
        if ($path === '/ai/list-models' && $method === 'POST') {
            self::requireAdmin();
            self::requireCsrf($input);
            self::checkAiRateLimit();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $keyId = trim((string)($input['key_id'] ?? ''));
            $provider = strtolower(trim((string)($input['provider'] ?? '')));
            $apiKey = trim((string)($input['api_key'] ?? ''));

            if ($keyId !== '') {
                $resolved = AiConfigService::resolveKeyById($keyId);
                if (!$resolved) {
                    self::error('Profil API Key tidak ditemukan.', 404, 'AI_KEY_NOT_FOUND');
                }
                if ($provider === '') {
                    $provider = (string)$resolved['provider'];
                }
                if ($apiKey === '') {
                    $apiKey = (string)$resolved['api_key'];
                }
            }

            if ($provider === '' || $apiKey === '') {
                self::error('Masukkan API Key atau pilih profil tersimpan terlebih dahulu untuk memuat daftar model.', 400, 'VALIDATION_ERROR');
            }

            self::json(AiHelper::listAvailableModels($provider, $apiKey));
            return true;
        }

        return false;
    }
}
