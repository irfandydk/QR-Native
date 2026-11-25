<?php
/**
 * QR-SAR - Automated Test: Multi-Provider AI API Keys & Model Selection (Gemini & DeepSeek)
 * Memverifikasi penyimpanan multi-API key terenkripsi, pemilihan default & model,
 * auto-fallback, pemanggilan Laporan Awal & LAPBANG, uji koneksi, serta integritas UI/API.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "=== TEST MULTI-PROVIDER AI (GEMINI & DEEPSEEK) ===\n\n";

$passed = 0;
$failed = 0;

function assertAi(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
    }
}

$db = getDB();
AiConfigService::ensureSchemaUpdated(true);

$origRow = $db->query("SELECT gemini_api_key, ai_config_json FROM AppSettings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

try {
    // 1. Verifikasi kolom ai_config_json tersedia
    $cols = $db->query("SHOW COLUMNS FROM AppSettings LIKE 'ai_config_json'")->fetchAll();
    assertAi(!empty($cols), 'Kolom AppSettings.ai_config_json tersedia di skema database');

    // 2. Verifikasi enkripsi AES-256-GCM & masking
    $sampleSecret = 'sk-deepseek-test-1234567890abcdef';
    $encrypted = AiConfigService::encryptSecret($sampleSecret);
    $decrypted = AiConfigService::decryptSecret($encrypted);
    $masked = AiConfigService::maskApiKey($sampleSecret);
    assertAi(
        str_starts_with($encrypted, 'enc:v1:') && $encrypted !== $sampleSecret && $decrypted === $sampleSecret,
        'Enkripsi AES-256-GCM menyimpan API key terenkripsi dan mendekripsi balik secara tepat'
    );
    assertAi(
        $masked === 'sk-d******cdef' && !str_contains($masked, '1234567890'),
        "maskApiKey() menyamarkan kunci dengan aman ({$masked})"
    );

    // 3. Reset konfigurasi bersih lalu simpan 3 kunci (Gemini, DeepSeek, dan Gemini kustom)
    SettingsModel::updateSettings(['ai_config_json' => null, 'gemini_api_key' => '']);

    $resGem = AiConfigService::saveKey([
        'label' => 'Gemini KPP Utama',
        'provider' => 'gemini',
        'api_key' => 'AIzaSyTestGeminiKey111122223333',
        'model_preset' => 'gemini-3.8-flash',
        'enabled' => true,
        'set_as_default' => true,
    ]);
    $resDs = AiConfigService::saveKey([
        'label' => 'DeepSeek Operasional',
        'provider' => 'deepseek',
        'api_key' => 'sk-test-deepseek-key-999988887777',
        'model_preset' => 'deepseek-chat',
        'enabled' => true,
        'set_as_default' => false,
    ]);
    $resCustom = AiConfigService::saveKey([
        'label' => 'DeepSeek Reasoner Cadangan',
        'provider' => 'deepseek',
        'api_key' => 'sk-test-deepseek-r1-555566664444',
        'model_preset' => '__custom__',
        'model_custom' => 'deepseek-reasoner',
        'enabled' => true,
        'set_as_default' => false,
    ]);

    assertAi(
        !empty($resGem['success']) && !empty($resDs['success']) && !empty($resCustom['success']),
        'Berhasil menyimpan lebih dari 1 API Key (Gemini + DeepSeek + Model Kustom)'
    );

    $pub = AiConfigService::getPublicConfig();
    $pubJson = json_encode($pub) ?: '';
    assertAi(
        count($pub['keys']) === 3
            && !str_contains($pubJson, 'AIzaSyTestGeminiKey111122223333')
            && !str_contains($pubJson, 'sk-test-deepseek-key-999988887777')
            && !str_contains($pubJson, 'enc:v1:'),
        'getPublicConfig() mengembalikan 3 profil tanpa pernah membocorkan kunci plaintext atau ciphertext'
    );

    // 4. Ubah Default ke DeepSeek + aktifkan Auto-Fallback
    $prefRes = AiConfigService::savePreferences((string)$resDs['id'], true);
    $plan = AiConfigService::getExecutionPlan();
    assertAi(
        !empty($prefRes['success'])
            && count($plan) === 3
            && $plan[0]['provider'] === 'deepseek'
            && $plan[0]['model'] === 'deepseek-chat'
            && $plan[1]['provider'] === 'gemini'
            && $plan[1]['model'] === 'gemini-3.8-flash',
        'Mengubah default ke DeepSeek menempatkan deepseek-chat di urutan pertama & Gemini sebagai fallback'
    );

    // 5. Simulasi Laporan Awal (refineRawDraft & draftKronologi) dan LAPBANG (refineSitrepRawDraft) memakai DeepSeek default
    $capturedCalls = [];
    AiHelper::setHttpClient(function (string $url, array $headers, array $payload) use (&$capturedCalls): array {
        $capturedCalls[] = ['url' => $url, 'headers' => $headers, 'payload' => $payload];
        if (str_contains($url, 'api.deepseek.com')) {
            return [
                'http_code' => 200,
                'body' => json_encode([
                    'model' => 'deepseek-chat',
                    'choices' => [[
                        'message' => [
                            'content' => '<think>Analisis internal</think>Pada Rabu, 25 November 2026 sekitar pukul 08.00 WITA, Tim Rescue KPP Kendari melaksanakan penyisiran di sektor laut Teluk Kendari.'
                        ]
                    ]]
                ]),
                'error' => '',
            ];
        }
        return ['http_code' => 500, 'body' => '', 'error' => 'unexpected call'];
    });

    $rLapAwal = AiHelper::refineRawDraft('tadi pagi nelayan hilang di teluk kendari');
    $rDraftKron = AiHelper::draftKronologi('perahu mati mesin di perairan wowoni', 'Kecelakaan Kapal', 'Perairan Wawonii', '2026-11-25 08:00:00');
    $rLapbang = AiHelper::refineSitrepRawDraft('tim sar menyisir 10 nm hasil nihil', 'H.2', 'Kecelakaan Kapal');

    assertAi(
        !empty($rLapAwal['success'])
            && ($rLapAwal['provider'] ?? '') === 'deepseek'
            && ($rLapAwal['model'] ?? '') === 'deepseek-chat'
            && ($rLapAwal['used_fallback'] ?? true) === false
            && !str_contains((string)$rLapAwal['text'], '<think>')
            && ($rDraftKron['provider'] ?? '') === 'deepseek'
            && ($rLapbang['provider'] ?? '') === 'deepseek'
            && count($capturedCalls) === 3
            && in_array('Authorization: Bearer sk-test-deepseek-key-999988887777', $capturedCalls[0]['headers'], true),
        'Laporan Awal & LAPBANG menggunakan DeepSeek default via Authorization Bearer dan membersihkan blok <think>'
    );

    // 6. Simulasi Auto-Fallback: DeepSeek HTTP 429 -> otomatis jatuh ke Gemini (x-goog-api-key + thinkingLevel low)
    $fallbackCalls = [];
    AiHelper::setHttpClient(function (string $url, array $headers, array $payload) use (&$fallbackCalls): array {
        $fallbackCalls[] = ['url' => $url, 'headers' => $headers, 'payload' => $payload];
        if (str_contains($url, 'api.deepseek.com')) {
            return ['http_code' => 429, 'body' => json_encode(['error' => ['message' => 'Rate limit exceeded']]), 'error' => ''];
        }
        if (str_contains($url, 'generativelanguage.googleapis.com')) {
            return [
                'http_code' => 200,
                'body' => json_encode([
                    'modelVersion' => 'gemini-3.8-flash',
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'Pada Rabu, 25 November 2026 sekitar pukul 09.00 WITA, Tim SAR Gabungan melaksanakan pencarian korban di perairan Kendari dengan hasil sementara nihil.']]]
                    ]]
                ]),
                'error' => '',
            ];
        }
        return ['http_code' => 500, 'body' => '', 'error' => 'unknown'];
    });

    $rFallback = AiHelper::refineSitrepRawDraft('penyisiran pagi hasil nihil', 'H.1', 'KMM Tenggelam');
    $gemCall = $fallbackCalls[1] ?? [];
    assertAi(
        !empty($rFallback['success'])
            && ($rFallback['provider'] ?? '') === 'gemini'
            && ($rFallback['model'] ?? '') === 'gemini-3.8-flash'
            && ($rFallback['used_fallback'] ?? false) === true
            && !str_contains((string)($gemCall['url'] ?? ''), 'AIzaSyTestGeminiKey111122223333')
            && in_array('x-goog-api-key: AIzaSyTestGeminiKey111122223333', $gemCall['headers'] ?? [], true)
            && (($gemCall['payload']['generationConfig']['thinkingConfig']['thinkingLevel'] ?? '') === 'low'),
        'Auto-Fallback otomatis berpindah ke Google Gemini saat DeepSeek 429 (header x-goog-api-key aman & thinkingLevel low)'
    );

    // 7. Nonaktifkan Auto-Fallback -> hanya 1 profil di execution plan & tidak memanggil cadangan saat gagal
    AiConfigService::savePreferences((string)$resDs['id'], false);
    $planNoFb = AiConfigService::getExecutionPlan();
    $noFbCalls = 0;
    AiHelper::setHttpClient(function () use (&$noFbCalls): array {
        $noFbCalls++;
        return ['http_code' => 429, 'body' => '', 'error' => ''];
    });
    $rNoFb = AiHelper::refineRawDraft('laporan mentah uji tanpa fallback');
    assertAi(
        count($planNoFb) === 1 && $noFbCalls === 1 && ($rNoFb['provider'] ?? '') === 'fallback',
        'Saat Auto-Fallback dimatikan, hanya API Key default yang dipanggil sebelum jatuh ke teks baku'
    );

    // 8. Edit profil tanpa mengisi API key baru -> kunci lama tetap terjaga
    $editRes = AiConfigService::saveKey([
        'id' => (string)$resDs['id'],
        'label' => 'DeepSeek V3 Produksi',
        'provider' => 'deepseek',
        'api_key' => '',
        'model_preset' => 'deepseek-reasoner',
        'enabled' => true,
        'set_as_default' => true,
    ]);
    $resolvedEdited = AiConfigService::resolveKeyById((string)$resDs['id']);
    assertAi(
        !empty($editRes['success'])
            && $resolvedEdited !== null
            && $resolvedEdited['label'] === 'DeepSeek V3 Produksi'
            && $resolvedEdited['model'] === 'deepseek-reasoner'
            && $resolvedEdited['api_key'] === 'sk-test-deepseek-key-999988887777',
        'Edit profil dengan input API Key kosong mempertahankan kunci lama dan memperbarui label serta model'
    );

    // 9. Uji koneksi langsung (AiHelper::testConnection)
    AiHelper::setHttpClient(function (string $url, array $headers, array $payload): array {
        // Pastikan model reasoner tidak mengirim temperature
        if (str_contains($url, 'api.deepseek.com') && ($payload['model'] ?? '') === 'deepseek-reasoner') {
            if (array_key_exists('temperature', $payload)) {
                return ['http_code' => 400, 'body' => '{"error":{"message":"temperature not supported"}}', 'error' => ''];
            }
            return ['http_code' => 200, 'body' => json_encode(['model' => 'deepseek-reasoner', 'choices' => [['message' => ['content' => 'OK']]]]), 'error' => ''];
        }
        return ['http_code' => 401, 'body' => '{"error":{"message":"Invalid key"}}', 'error' => ''];
    });
    $tOk = AiHelper::testConnection('deepseek', 'sk-test-deepseek-key-999988887777', 'deepseek-reasoner');
    $tFail = AiHelper::testConnection('gemini', 'bad-key', 'gemini-3.8-flash');
    assertAi(
        !empty($tOk['success']) && empty($tFail['success']) && str_contains((string)($tFail['error'] ?? ''), 'HTTP 401'),
        'AiHelper::testConnection() memverifikasi koneksi sukses (termasuk deepseek-reasoner tanpa temperature) dan melaporkan error HTTP 401'
    );

    // 10. Hapus kunci default -> profil aktif tersisa otomatis naik jadi default
    $delRes = AiConfigService::deleteKey((string)$resDs['id']);
    $afterDel = AiConfigService::getPublicConfig();
    assertAi(
        !empty($delRes['success'])
            && count($afterDel['keys']) === 2
            && $afterDel['default_key_id'] === (string)$resGem['id'],
        'Menghapus API Key default otomatis mempromosikan API Key aktif berikutnya sebagai default'
    );

    // 11. Kontrak UI Admin & Endpoint API
    $adminIdx = (string)file_get_contents(ROOT_DIR . '/views/admin/index.php');
    $tabAi = (string)file_get_contents(ROOT_DIR . '/views/admin/partials/tab_ai.php');
    $tabSatker = (string)file_get_contents(ROOT_DIR . '/views/admin/partials/tab_satker.php');
    $aiApi = (string)file_get_contents(ROOT_DIR . '/api/controllers/AiApiController.php');
    assertAi(
        str_contains($adminIdx, 'tab=ai')
            && str_contains($tabAi, 'id="aiDefaultKeySelect"')
            && str_contains($tabAi, 'id="chkAiAutoFallback"')
            && str_contains($tabAi, 'id="selectAiProvider"')
            && str_contains($tabAi, 'id="selectAiModelPreset"')
            && str_contains($tabSatker, 'admin.php?tab=ai')
            && str_contains($aiApi, "'/ai/test-key'"),
        'Kontrak UI tab Asisten AI, tautan dari tab Satker, dan endpoint /ai/test-key lengkap'
    );
} finally {
    AiHelper::setHttpClient(null);
    SettingsModel::updateSettings([
        'gemini_api_key' => $origRow['gemini_api_key'] ?? '',
        'ai_config_json' => $origRow['ai_config_json'] ?? null,
    ]);
}

echo "\n--- HASIL TEST MULTI-PROVIDER AI ---\n";
echo "Lulus (PASS): {$passed}\n";
echo "Gagal (FAIL): {$failed}\n";
exit($failed === 0 ? 0 : 1);
