<?php
/**
 * QR-SAR - Multi-Provider AI Configuration & API Key Vault Service
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Mengelola beberapa API Key AI (Google Gemini, DeepSeek), pemilihan kunci & model default,
 * enkripsi kunci di database (AES-256-GCM berbasis APP_KEY), masking untuk UI,
 * serta urutan eksekusi auto-fallback untuk Laporan Awal dan LAPBANG.
 */

declare(strict_types=1);

class AiConfigService {
    private const ENC_PREFIX = 'enc:v1:';
    private static bool $schemaChecked = false;

    /**
     * Katalog provider dan daftar model preset resmi yang didukung.
     */
    public const PROVIDERS = [
        'gemini' => [
            'label' => 'Google Gemini',
            'badge' => 'GEMINI',
            'placeholder' => 'AIzaSy...',
            'default_model' => 'gemini-3.8-flash',
            'models' => [
                'gemini-3.8-flash' => 'Gemini 3.8 Flash (Direkomendasikan)',
                'gemini-3.6-flash' => 'Gemini 3.6 Flash',
                'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite (Hemat & Cepat)',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash (Legacy)',
            ],
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'badge' => 'DEEPSEEK',
            'placeholder' => 'sk-...',
            'default_model' => 'deepseek-chat',
            'models' => [
                'deepseek-chat' => 'DeepSeek Chat / V3 (Direkomendasikan)',
                'deepseek-reasoner' => 'DeepSeek Reasoner / R1 (Penalaran)',
            ],
        ],
    ];

    /**
     * Pastikan kolom AppSettings.ai_config_json tersedia secara idempoten.
     */
    public static function ensureSchemaUpdated(bool $force = false): void {
        if (!$force && self::$schemaChecked) {
            return;
        }
        try {
            $db = getDB();
            $cols = $db->query("SHOW COLUMNS FROM AppSettings LIKE 'ai_config_json'")->fetchAll();
            if (empty($cols)) {
                if ($db->inTransaction()) {
                    return;
                }
                $db->exec("ALTER TABLE AppSettings ADD COLUMN `ai_config_json` LONGTEXT NULL DEFAULT NULL AFTER `gemini_api_key`");
            }
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            error_log('[AiConfigService ensureSchemaUpdated] ' . $e->getMessage());
        }
    }

    /**
     * Enkripsi API key memakai AES-256-GCM dengan kunci turunan APP_KEY.
     */
    public static function encryptSecret(string $plain): string {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }
        if (str_starts_with($plain, self::ENC_PREFIX)) {
            return $plain;
        }
        if (!function_exists('openssl_encrypt') || !in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            return $plain;
        }
        $key = self::deriveEncryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false || $tag === '') {
            return $plain;
        }
        return self::ENC_PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Dekripsi API key tersimpan. Mendukung format terenkripsi enc:v1: maupun plaintext legacy.
     */
    public static function decryptSecret(string $stored): string {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (!str_starts_with($stored, self::ENC_PREFIX)) {
            return $stored;
        }
        if (!function_exists('openssl_decrypt')) {
            return '';
        }
        $raw = base64_decode(substr($stored, strlen(self::ENC_PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::deriveEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plain) ? trim($plain) : '';
    }

    private static function deriveEncryptionKey(): string {
        $appKey = defined('APP_KEY') ? (string)APP_KEY : 'QR_SAR_DEFAULT_FALLBACK_KEY';
        return hash('sha256', 'QR_SAR_AI_VAULT_V1:' . $appKey, true);
    }

    /**
     * Samarkan API Key untuk ditampilkan aman di antarmuka admin.
     */
    public static function maskApiKey(string $plain): string {
        $plain = trim($plain);
        $len = strlen($plain);
        if ($len === 0) {
            return '(kosong)';
        }
        if ($len <= 8) {
            return str_repeat('*', max(4, $len));
        }
        return substr($plain, 0, 4) . str_repeat('*', 6) . substr($plain, -4);
    }

    public static function isValidProvider(string $provider): bool {
        return array_key_exists(strtolower(trim($provider)), self::PROVIDERS);
    }

    public static function sanitizeModel(string $provider, string $model): string {
        $provider = strtolower(trim($provider));
        $model = trim($model);
        if ($model !== '' && preg_match('/^[a-zA-Z0-9._:\/-]{2,80}$/', $model)) {
            return $model;
        }
        return self::PROVIDERS[$provider]['default_model'] ?? 'gemini-3.8-flash';
    }

    public static function getProviderLabel(string $provider): string {
        $p = strtolower(trim($provider));
        return self::PROVIDERS[$p]['label'] ?? strtoupper($p);
    }

    /**
     * Muat konfigurasi mentah dari AppSettings.ai_config_json (dengan auto-hidrasi legacy gemini_api_key bila belum ada).
     */
    public static function loadRawConfig(): array {
        $settings = [];
        try {
            $settings = SettingsModel::getSettings();
        } catch (Throwable) {
            $settings = [];
        }

        $rawJson = isset($settings['ai_config_json']) ? trim((string)$settings['ai_config_json']) : '';
        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded) && isset($decoded['keys']) && is_array($decoded['keys'])) {
                return self::normalizeConfigStructure($decoded);
            }
        }

        // Kompatibilitas mundur: bila ada gemini_api_key lama di AppSettings, tampilkan sebagai profil awal
        $legacyGemini = trim((string)($settings['gemini_api_key'] ?? ''));
        if ($legacyGemini !== '') {
            $legacyId = 'ai_legacy_gemini';
            return [
                'version' => 1,
                'default_key_id' => $legacyId,
                'auto_fallback' => true,
                'keys' => [[
                    'id' => $legacyId,
                    'label' => 'Google Gemini (Legacy)',
                    'provider' => 'gemini',
                    'api_key' => self::encryptSecret($legacyGemini),
                    'model' => 'gemini-2.5-flash',
                    'enabled' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]],
            ];
        }

        return [
            'version' => 1,
            'default_key_id' => '',
            'auto_fallback' => true,
            'keys' => [],
        ];
    }

    private static function normalizeConfigStructure(array $cfg): array {
        $cleanKeys = [];
        $seenIds = [];
        foreach (($cfg['keys'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string)($item['id'] ?? ''));
            if ($id === '' || isset($seenIds[$id])) {
                continue;
            }
            $provider = strtolower(trim((string)($item['provider'] ?? '')));
            if (!self::isValidProvider($provider)) {
                continue;
            }
            $storedKey = trim((string)($item['api_key'] ?? ''));
            if ($storedKey === '') {
                continue;
            }
            $seenIds[$id] = true;
            $cleanKeys[] = [
                'id' => $id,
                'label' => mb_substr(trim((string)($item['label'] ?? self::getProviderLabel($provider))), 0, 80, 'UTF-8') ?: self::getProviderLabel($provider),
                'provider' => $provider,
                'api_key' => $storedKey,
                'model' => self::sanitizeModel($provider, (string)($item['model'] ?? '')),
                'enabled' => !isset($item['enabled']) || (bool)$item['enabled'],
                'created_at' => (string)($item['created_at'] ?? date('Y-m-d H:i:s')),
                'updated_at' => (string)($item['updated_at'] ?? date('Y-m-d H:i:s')),
            ];
        }

        $defaultId = trim((string)($cfg['default_key_id'] ?? ''));
        $defaultExists = false;
        foreach ($cleanKeys as $k) {
            if ($k['id'] === $defaultId && $k['enabled']) {
                $defaultExists = true;
                break;
            }
        }
        if (!$defaultExists) {
            $defaultId = '';
            foreach ($cleanKeys as $k) {
                if ($k['enabled']) {
                    $defaultId = $k['id'];
                    break;
                }
            }
            if ($defaultId === '' && !empty($cleanKeys)) {
                $defaultId = $cleanKeys[0]['id'];
            }
        }

        return [
            'version' => 1,
            'default_key_id' => $defaultId,
            'auto_fallback' => !array_key_exists('auto_fallback', $cfg) || (bool)$cfg['auto_fallback'],
            'keys' => $cleanKeys,
        ];
    }

    private static function persistConfig(array $cfg, string $auditDesc): bool {
        self::ensureSchemaUpdated(false);
        $normalized = self::normalizeConfigStructure($cfg);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $payload = ['ai_config_json' => $json];

        // Sinkronkan juga kolom legacy gemini_api_key agar instalasi/skrip lama yang membacanya tetap selaras
        $firstGeminiPlain = '';
        foreach ($normalized['keys'] as $k) {
            if ($k['provider'] === 'gemini' && $k['enabled']) {
                $dec = self::decryptSecret($k['api_key']);
                if ($dec !== '') {
                    if ($k['id'] === $normalized['default_key_id']) {
                        $firstGeminiPlain = $dec;
                        break;
                    }
                    if ($firstGeminiPlain === '') {
                        $firstGeminiPlain = $dec;
                    }
                }
            }
        }
        $payload['gemini_api_key'] = $firstGeminiPlain;

        $ok = SettingsModel::updateSettings($payload);
        if ($ok && class_exists('AuditHelper')) {
            AuditHelper::log('AI_CONFIG_UPDATE', $auditDesc, 'AppSettings', '1');
        }
        return $ok;
    }

    /**
     * Kembalikan konfigurasi aman untuk antarmuka Admin (tanpa pernah mengirim plaintext API Key).
     */
    public static function getPublicConfig(): array {
        $cfg = self::loadRawConfig();
        $publicKeys = [];
        $activeDefault = null;

        foreach ($cfg['keys'] as $k) {
            $plain = self::decryptSecret($k['api_key']);
            $isDefault = ($k['id'] === $cfg['default_key_id']);
            $entry = [
                'id' => $k['id'],
                'label' => $k['label'],
                'provider' => $k['provider'],
                'provider_label' => self::getProviderLabel($k['provider']),
                'provider_badge' => self::PROVIDERS[$k['provider']]['badge'] ?? strtoupper($k['provider']),
                'model' => $k['model'],
                'enabled' => (bool)$k['enabled'],
                'is_default' => $isDefault,
                'masked_key' => self::maskApiKey($plain),
                'has_valid_key' => $plain !== '',
                'updated_at' => $k['updated_at'],
            ];
            $publicKeys[] = $entry;
            if ($isDefault) {
                $activeDefault = $entry;
            }
        }

        // Deteksi kunci environment cadangan (hanya ditampilkan sebagai info status jika DB kosong)
        $envFallback = [];
        if (trim((string)(getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ''))) !== '') {
            $envFallback[] = 'GEMINI_API_KEY (.env)';
        }
        if (trim((string)(getenv('DEEPSEEK_API_KEY') ?: ($_ENV['DEEPSEEK_API_KEY'] ?? ''))) !== '') {
            $envFallback[] = 'DEEPSEEK_API_KEY (.env)';
        }

        return [
            'default_key_id' => $cfg['default_key_id'],
            'auto_fallback' => (bool)$cfg['auto_fallback'],
            'active_default' => $activeDefault,
            'keys' => $publicKeys,
            'env_fallbacks' => $envFallback,
            'providers' => self::PROVIDERS,
        ];
    }

    /**
     * Susun rencana eksekusi AI terurut:
     * 1. Profil Default aktif dari Pengaturan Aplikasi terlebih dahulu.
     * 2. Jika auto_fallback aktif, profil aktif lain menyusul sesuai urutan.
     * 3. Jika belum ada profil aktif di DB, jatuh ke variabel environment (.env).
     *
     * @return array<int, array{id:string,label:string,provider:string,provider_label:string,api_key:string,model:string,is_default:bool}>
     */
    public static function getExecutionPlan(): array {
        $cfg = self::loadRawConfig();
        $defaultEntry = null;
        $fallbackEntries = [];

        foreach ($cfg['keys'] as $k) {
            if (empty($k['enabled'])) {
                continue;
            }
            $plain = self::decryptSecret($k['api_key']);
            if ($plain === '') {
                continue;
            }
            $item = [
                'id' => $k['id'],
                'label' => $k['label'],
                'provider' => $k['provider'],
                'provider_label' => self::getProviderLabel($k['provider']),
                'api_key' => $plain,
                'model' => $k['model'],
                'is_default' => ($k['id'] === $cfg['default_key_id']),
            ];
            if ($item['is_default'] && $defaultEntry === null) {
                $defaultEntry = $item;
            } else {
                $fallbackEntries[] = $item;
            }
        }

        $plan = [];
        if ($defaultEntry !== null) {
            $plan[] = $defaultEntry;
            if (!empty($cfg['auto_fallback'])) {
                foreach ($fallbackEntries as $fb) {
                    $plan[] = $fb;
                }
            }
        } elseif (!empty($fallbackEntries)) {
            $plan[] = $fallbackEntries[0];
            if (!empty($cfg['auto_fallback']) && count($fallbackEntries) > 1) {
                foreach (array_slice($fallbackEntries, 1) as $fb) {
                    $plan[] = $fb;
                }
            }
        }

        if (!empty($plan)) {
            return $plan;
        }

        // Fallback terakhir bila belum ada satupun kunci di DB: cek .env
        $envGemini = trim((string)(getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '')));
        if ($envGemini !== '') {
            $plan[] = [
                'id' => 'env_gemini',
                'label' => 'Google Gemini (.env)',
                'provider' => 'gemini',
                'provider_label' => self::getProviderLabel('gemini'),
                'api_key' => $envGemini,
                'model' => self::PROVIDERS['gemini']['default_model'],
                'is_default' => empty($plan),
            ];
        }
        $envDeepSeek = trim((string)(getenv('DEEPSEEK_API_KEY') ?: ($_ENV['DEEPSEEK_API_KEY'] ?? '')));
        if ($envDeepSeek !== '') {
            $plan[] = [
                'id' => 'env_deepseek',
                'label' => 'DeepSeek (.env)',
                'provider' => 'deepseek',
                'provider_label' => self::getProviderLabel('deepseek'),
                'api_key' => $envDeepSeek,
                'model' => self::PROVIDERS['deepseek']['default_model'],
                'is_default' => empty($plan),
            ];
        }

        return $plan;
    }

    /**
     * Ambil satu profil terdekripsi khusus untuk uji koneksi server-side oleh Admin.
     */
    public static function resolveKeyById(string $id): ?array {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        $cfg = self::loadRawConfig();
        foreach ($cfg['keys'] as $k) {
            if ($k['id'] === $id) {
                $plain = self::decryptSecret($k['api_key']);
                if ($plain === '') {
                    return null;
                }
                return [
                    'id' => $k['id'],
                    'label' => $k['label'],
                    'provider' => $k['provider'],
                    'provider_label' => self::getProviderLabel($k['provider']),
                    'api_key' => $plain,
                    'model' => $k['model'],
                    'enabled' => (bool)$k['enabled'],
                ];
            }
        }
        return null;
    }

    /**
     * Tambah atau perbarui profil API Key AI.
     *
     * @return array{success:bool, id?:string, message?:string, error?:string}
     */
    public static function saveKey(array $input): array {
        $cfg = self::loadRawConfig();
        $id = trim((string)($input['id'] ?? ''));
        $provider = strtolower(trim((string)($input['provider'] ?? '')));
        if (!self::isValidProvider($provider)) {
            return ['success' => false, 'error' => 'Provider AI tidak valid. Pilih Google Gemini atau DeepSeek.'];
        }

        $label = mb_substr(trim((string)($input['label'] ?? '')), 0, 80, 'UTF-8');
        if ($label === '') {
            $label = self::getProviderLabel($provider) . ' #' . (count($cfg['keys']) + 1);
        }

        $modelPreset = trim((string)($input['model_preset'] ?? ($input['model'] ?? '')));
        $modelCustom = trim((string)($input['model_custom'] ?? ''));
        $rawModel = ($modelPreset === '__custom__' && $modelCustom !== '') ? $modelCustom : ($modelCustom !== '' && $modelPreset === '' ? $modelCustom : $modelPreset);
        if ($rawModel === '__custom__' || $rawModel === '') {
            $rawModel = self::PROVIDERS[$provider]['default_model'];
        }
        if (!preg_match('/^[a-zA-Z0-9._:\/-]{2,80}$/', $rawModel)) {
            return ['success' => false, 'error' => 'Format nama model tidak valid. Gunakan huruf, angka, titik, garis bawah, atau tanda hubung.'];
        }

        $newPlainKey = trim((string)($input['api_key'] ?? ''));
        $enabled = !array_key_exists('enabled', $input) || (bool)$input['enabled'];
        $setAsDefault = !empty($input['set_as_default']);
        $now = date('Y-m-d H:i:s');

        $foundIdx = null;
        if ($id !== '') {
            foreach ($cfg['keys'] as $i => $k) {
                if ($k['id'] === $id) {
                    $foundIdx = $i;
                    break;
                }
            }
        }

        if ($foundIdx !== null) {
            $existing = $cfg['keys'][$foundIdx];
            $storedKey = $newPlainKey !== '' ? self::encryptSecret($newPlainKey) : $existing['api_key'];
            if ($storedKey === '') {
                return ['success' => false, 'error' => 'API Key wajib diisi.'];
            }
            $cfg['keys'][$foundIdx] = [
                'id' => $existing['id'],
                'label' => $label,
                'provider' => $provider,
                'api_key' => $storedKey,
                'model' => $rawModel,
                'enabled' => $setAsDefault ? true : $enabled,
                'created_at' => $existing['created_at'] ?? $now,
                'updated_at' => $now,
            ];
            $targetId = $existing['id'];
            $desc = "Memperbarui profil API Key AI '{$label}' ({$provider} / {$rawModel})";
        } else {
            if ($newPlainKey === '') {
                return ['success' => false, 'error' => 'API Key baru wajib diisi.'];
            }
            $targetId = 'ai_' . bin2hex(random_bytes(6));
            $cfg['keys'][] = [
                'id' => $targetId,
                'label' => $label,
                'provider' => $provider,
                'api_key' => self::encryptSecret($newPlainKey),
                'model' => $rawModel,
                'enabled' => $setAsDefault ? true : $enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($cfg['default_key_id'] === '' || count($cfg['keys']) === 1) {
                $setAsDefault = true;
            }
            $desc = "Menambahkan profil API Key AI '{$label}' ({$provider} / {$rawModel})";
        }

        if ($setAsDefault) {
            $cfg['default_key_id'] = $targetId;
        }

        if (!self::persistConfig($cfg, $desc)) {
            return ['success' => false, 'error' => 'Gagal menyimpan konfigurasi AI ke basis data.'];
        }

        return [
            'success' => true,
            'id' => $targetId,
            'message' => $foundIdx !== null
                ? "Profil API Key '{$label}' ({$rawModel}) berhasil diperbarui."
                : "Profil API Key '{$label}' ({$rawModel}) berhasil ditambahkan.",
        ];
    }

    /**
     * Hapus profil API Key berdasarkan ID.
     */
    public static function deleteKey(string $id): array {
        $id = trim($id);
        $cfg = self::loadRawConfig();
        $removedLabel = null;
        $remaining = [];
        foreach ($cfg['keys'] as $k) {
            if ($k['id'] === $id) {
                $removedLabel = $k['label'];
                continue;
            }
            $remaining[] = $k;
        }
        if ($removedLabel === null) {
            return ['success' => false, 'error' => 'Profil API Key tidak ditemukan.'];
        }
        $cfg['keys'] = $remaining;
        if ($cfg['default_key_id'] === $id) {
            $cfg['default_key_id'] = '';
        }

        if (!self::persistConfig($cfg, "Menghapus profil API Key AI '{$removedLabel}'")) {
            return ['success' => false, 'error' => 'Gagal menghapus profil API Key dari basis data.'];
        }
        return ['success' => true, 'message' => "Profil API Key '{$removedLabel}' berhasil dihapus."];
    }

    /**
     * Perbarui API Key default dan status auto-fallback sekaligus.
     */
    public static function savePreferences(string $defaultKeyId, bool $autoFallback): array {
        $cfg = self::loadRawConfig();
        $defaultKeyId = trim($defaultKeyId);
        $chosenLabel = '';

        if ($defaultKeyId !== '') {
            $found = false;
            foreach ($cfg['keys'] as $i => $k) {
                if ($k['id'] === $defaultKeyId) {
                    $cfg['keys'][$i]['enabled'] = true;
                    $cfg['default_key_id'] = $defaultKeyId;
                    $chosenLabel = $k['label'] . ' (' . $k['model'] . ')';
                    $found = true;
                    break;
                }
            }
            if (!$found && !empty($cfg['keys'])) {
                return ['success' => false, 'error' => 'Profil API Key default yang dipilih tidak ditemukan.'];
            }
        }

        $cfg['auto_fallback'] = $autoFallback;
        $fbStr = $autoFallback ? 'Aktif' : 'Nonaktif';
        $desc = "Memperbarui preferensi AI default" . ($chosenLabel !== '' ? " ke {$chosenLabel}" : '') . " (Auto-Fallback: {$fbStr})";

        if (!self::persistConfig($cfg, $desc)) {
            return ['success' => false, 'error' => 'Gagal menyimpan preferensi AI.'];
        }
        return ['success' => true, 'message' => 'API Key default dan pengaturan auto-fallback berhasil disimpan.'];
    }
}
