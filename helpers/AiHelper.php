<?php
/**
 * QR-SAR - Multi-Provider AI Assistant Helper (Google Gemini & DeepSeek with Humanizer & Auto-Fallback)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class AiHelper {
    /**
     * Optional injectable HTTP client for deterministic CLI/unit tests.
     * Signature: function(string $url, array $headers, array $payload, int $timeout): array{http_code:int, body:string, error:string}
     *
     * @var callable|null
     */
    private static $httpClient = null;

    public static function setHttpClient(?callable $client): void {
        self::$httpClient = $client;
    }

    /**
     * Backward-compatible helper returning the active default API key (or legacy/env fallback).
     */
    public static function getApiKey(): string {
        if (class_exists('AiConfigService')) {
            $plan = AiConfigService::getExecutionPlan();
            if (!empty($plan[0]['api_key'])) {
                return (string)$plan[0]['api_key'];
            }
        }

        try {
            $db = getDB();
            $stmt = $db->query("SELECT gemini_api_key FROM AppSettings WHERE id = 1 LIMIT 1");
            $row = $stmt->fetch();
            if ($row && !empty($row['gemini_api_key'])) {
                return trim((string)$row['gemini_api_key']);
            }
        } catch (Throwable) {}

        $key = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
        return trim((string)$key);
    }

    /**
     * Clean and humanize AI output to eliminate robotic AI cliches & stray reasoning tags.
     */
    public static function cleanHumanizer(string $text): string {
        $text = trim($text);

        // Strip <think>...</think> blocks if emitted by reasoning models (e.g. deepseek-reasoner)
        $text = (string)preg_replace('/<think>.*?<\/think>\s*/is', '', $text);

        // Remove code blocks if AI wrapped output in markdown blocks
        $text = (string)preg_replace('/^```(?:json|markdown|text)?\s*/i', '', $text);
        $text = (string)preg_replace('/\s*```$/', '', $text);

        // Remove robotic conversational AI preambles
        $preambles = [
            '/^(?:tentu|tentu saja|berikut|berikut ini|halo|baik|siap)[,\s]+(?:berikut(?:\s+adalah)?)?[^\n:]*[:\n]\s*/i',
            '/^(?:kronologi(?:nya)?(?:\s+adalah)?|narasi(?:\s+kejadian)?|draf(?:\s+sitrep)?)[^\n:]*[:\n]\s*/i',
            '/^sebagai\s+(?:petugas|perwira|anggota)\s+sar[^\n:]*[:\n]\s*/i',
        ];
        foreach ($preambles as $pattern) {
            $text = (string)preg_replace($pattern, '', $text);
        }

        // Remove conversational sign-offs (only if formatted as trailing footnote or disclaimer)
        $signOffs = [
            '/\s*(?:semoga\s+membantu|demikian\s+laporan\s+ini\s+dibuat\s+oleh\s+ai|ada\s+lagi\s+yang\s+bisa\s+saya\s+bantu\??)\s*$/i',
            '/\n+(?:\*\*|\*|#+)?\s*(?:catatan|note|disclaimer)\s*:\s*.*$/is'
        ];
        foreach ($signOffs as $pattern) {
            $text = (string)preg_replace($pattern, '', $text);
        }

        // Remove excessive markdown formatting asterisks to keep natural plain operational text
        $text = (string)preg_replace('/\*\*([^*]+)\*\*/', '$1', $text);
        $text = (string)preg_replace('/^#+\s+/m', '', $text);

        // Safeguard: Automatically fix any ungrammatical 'Pada kemarin' phrase into formal Indonesian date
        $kemarinDate = TemplateHelper::formatTanggalIndo(date('Y-m-d', strtotime('-1 day')));
        $text = (string)preg_replace('/\bPada\s+kemarin\b/i', "Pada {$kemarinDate}", $text);

        // Trim quotes and whitespace
        $text = trim($text, " \t\n\r\0\x0B\"'");

        return trim($text);
    }

    /**
     * Asisten AI Kronologi: Focus PURELY on raw report restructuring and formalizing.
     */
    public static function refineRawDraft(string $rawDraft): array {
        $rawDraft = trim($rawDraft);
        if (empty($rawDraft)) {
            return [
                'success' => false,
                'error' => 'Catatan / laporan mentah belum diisi.'
            ];
        }

        $plan = self::resolvePlan();
        if (empty($plan)) {
            return [
                'success' => true,
                'text' => $rawDraft,
                'model' => 'raw-fallback',
                'provider' => 'fallback',
                'provider_label' => 'Template Baku',
                'notice' => 'API Key AI belum disetel di Pengaturan, mengembalikan teks mentah.'
            ];
        }

        $today = date('Y-m-d');
        $hariIni = TemplateHelper::formatTanggalIndo($today);
        $kemarin = date('Y-m-d', strtotime('-1 day'));
        $hariKemarin = TemplateHelper::formatTanggalIndo($kemarin);
        $duaHariLalu = date('Y-m-d', strtotime('-2 days'));
        $hariDuaLalu = TemplateHelper::formatTanggalIndo($duaHariLalu);

        $systemInstruction = <<<INSTRUCTION
Anda adalah Perwira Komunikasi SAR di Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas.
Tugas Anda: Menyusun ulang dan memformalkan LAPORAN MENTAH / CATATAN KASAR kejadian musibah menjadi narasi KRONOLOGI KEJADIAN dinas resmi Basarnas yang formal, lugas, mudah dipahami, dan tidak bertele-tele.

KONTEKS WAKTU SAAT INI (Gunakan sebagai patokan konversi waktu relatif yang akurat):
- Hari ini / waktu saat ini: {$hariIni}
- Kemarin: {$hariKemarin}
- Dua hari lalu: {$hariDuaLalu}

PEDOMAN UTAMA (WAJIB DIPATUHI SECARA KETAT):
1. KONVERSI WAKTU RELATIF MENJADI TANGGAL RESMI:
   - DILARANG KERAS menggunakan frasa janggal/tidak baku seperti "pada kemarin...", "pada tadi pagi...", "hari kemarin", atau semacamnya.
   - Jika laporan mentah menyebut "kemarin", ubah menjadi tanggal resmi yang tepat, contoh: "Pada {$hariKemarin} sekitar...".
   - Jika laporan mentah menyebut jam/waktu kejadian (misal "kemarin jam 3 sore" atau "kemarin sore"), ubah menjadi waktu resmi: "Pada {$hariKemarin} sekitar pukul 15.00 WITA..." atau "Pada {$hariKemarin} sekitar sore hari...".
   - Jika laporan mentah menyebut "tadi pagi" atau "hari ini", ubah menjadi: "Pada {$hariIni} sekitar pagi hari...".
   - Jika laporan mentah menyebut "dua hari yang lalu", ubah menjadi: "Pada {$hariDuaLalu} sekitar...".
   - Jika laporan mentah sudah mencantumkan tanggal kalender pasti, gunakan tanggal kalender tersebut.

2. GAYA BAHASA FORMAL, LUGAS, DAN TIDAK BERTELE-TELE:
   - Gunakan tata bahasa Indonesia baku naskah dinas Basarnas. Kalimat harus tegas, mengalir alami, dan langsung ke substansi peristiwa tanpa kata-kata hiasan/puitis.
   - Rangkai alur peristiwa secara runtut:
     a. Keterangan waktu kejadian resmi (Hari, Tanggal Bulan Tahun jam/WITA).
     b. Identitas subjek/korban & jenis musibah (contoh: Kecelakaan Kapal, Kondisi Membahayakan Manusia/KMM seperti diterkam buaya, tenggelam, terseret arus).
     c. Lokasi kejadian musibah yang jelas.
     d. Tindakan awal yang sempat dilakukan keluarga/masyarakat setempat beserta hasil awal (nihil).
     e. Pelaporan kejadian ke KPP Kendari untuk permohonan bantuan operasi SAR.
   - Kemas dalam 1 (satu) paragraf yang utuh, padat, dan selesai tuntas.

3. KETUNTASAN PENUH:
   - Tulis secara LENGKAP sampai tuntas dari awal hingga akhir. Dilarang terpotong di tengah jalan. Akhiri dengan tanda titik penutup (.).

4. GAYA FORMAT:
   - DILARANG menggunakan kata pembuka AI (seperti "Tentu,", "Berikut narasi resminya:", dll.).
   - DILARANG menggunakan tanda bintang tebal (**), bullet points, atau tanda kutip pembuka-penutup.
   - Langsung mulai dari kata pertama narasi.
INSTRUCTION;

        $userPrompt = <<<PROMPT
LAPORAN MENTAH DARI PELAPOR:
"{$rawDraft}"

Susun ulang laporan mentah di atas menjadi narasi kronologi dinas resmi Basarnas yang formal, lugas, lengkap, dan tuntas sekarang:
PROMPT;

        $response = self::callConfiguredAi($systemInstruction, $userPrompt, $plan);
        if ($response !== null && !empty($response['text'])) {
            $cleaned = self::cleanHumanizer($response['text']);
            if (!empty($cleaned)) {
                return [
                    'success' => true,
                    'text' => $cleaned,
                    'model' => $response['model'],
                    'provider' => $response['provider'],
                    'provider_label' => $response['provider_label'],
                    'key_label' => $response['key_label'],
                    'used_fallback' => $response['used_fallback'],
                ];
            }
        }

        return [
            'success' => true,
            'text' => $rawDraft,
            'model' => 'raw-fallback',
            'provider' => 'fallback',
            'provider_label' => 'Template Baku',
            'used_fallback' => true,
        ];
    }

    /**
     * Draft Kronologi Kejadian with Basarnas Humanizer Style (Laporan Awal)
     */
    public static function draftKronologi(string $draftInput, string $namaOps = '', string $lokasi = '', string $waktu = '', array $extra = []): array {
        $formattedDate = !empty($waktu) ? TemplateHelper::formatTanggalIndo($waktu) : 'hari kejadian';
        $jamWita = !empty($waktu) ? TemplateHelper::formatJamWita($waktu) : 'pagi hari';

        $pelapor = $extra['pelapor'] ?? '';
        $waktuLapor = $extra['waktuLapor'] ?? '';
        $waktuLaporStr = !empty($waktuLapor) ? (TemplateHelper::formatTanggalIndo($waktuLapor) . ' pkl ' . TemplateHelper::formatJamWita($waktuLapor) . ' WITA') : '';
        $korbanInfo = $extra['korbanInfo'] ?? '';

        $fallback = "Pada {$formattedDate} sekitar pukul {$jamWita} WITA, telah terjadi musibah di {$lokasi}. Berdasarkan informasi pelapor, {$draftInput}. Upaya pencarian awal sempat dilakukan oleh pihak keluarga dan masyarakat setempat namun hingga informasi ini diterima hasil masih nihil, selanjutnya dilaporkan ke Kantor Pencarian dan Pertolongan (KPP) Kendari untuk bantuan SAR.";

        $plan = self::resolvePlan();
        if (empty($plan)) {
            return [
                'success' => true,
                'text' => $fallback,
                'model' => 'basarnas-official-template',
                'provider' => 'fallback',
                'provider_label' => 'Template Baku',
                'notice' => 'Disusun otomatis berdasarkan format baku operasional KPP Kendari.'
            ];
        }

        $systemInstruction = <<<INSTRUCTION
Anda adalah Perwira Komunikasi SAR senior di Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas.
Tugas Anda: Menyusun naskah KRONOLOGI KEJADIAN resmi untuk Laporan Awal Operasi SAR berdasarkan data dan deskripsi lapangan yang diberikan.

PANDUAN UTAMA KETUNTASAN & RELEVANSI (SANGAT PENTING):
1. RELEVANSI PENUH DENGAN DESKRIPSI (FAKTA UTAMA):
   - Jadikan 'Informasi Awal / Catatan Kasar' yang dituliskan pengguna sebagai FAKTA UTAMA kejadian.
   - Narasi HARUS MEREFLEKSIKAN persis musibah yang dideskripsikan (misal: jika nelayan belum kembali, ceritakan nelayan belum kembali; jika perahu mati mesin/patah as/bocor, ceritakan kerusakan kapal tersebut; jika korban tenggelam di sungai/pantai, ceritakan tenggelam di perairan tersebut; jika kecelakaan pendakian/hutan/darurat medis, sesuaikan secara akurat).
   - JANGAN pernah mengubah jenis musibah atau menambahkan cerita fiktif yang bertentangan dengan deskripsi yang diberikan.
   - Jangan memaksakan istilah kapal/laut jika kejadiannya adalah musibah di darat atau sungai.

2. KETUNTASAN NARASI (HARUS LENGKAP & SELESAI TUNTAS):
   - Narasi HARUS DITULIS LENGKAP SAMPAI SELESAI TUNTAS (1 hingga 2 paragraf padat).
   - DILARANG KERAS berhenti atau terpotong di tengah kalimat. Kalimat penutup harus selesai tuntas dan diakhiri dengan tanda titik.
   - Alur kronologi harus mengalir runtut:
     a) Awal kejadian (kapan, di mana, identitas korban/sarana yang digunakan).
     b) Situasi musibah atau kondisi darurat yang dialami.
     c) Upaya pencarian awal oleh keluarga/masyarakat setempat (jika ada).
     d) Pelaporan musibah dan permintaan bantuan ke KPP Kendari serta kesiapsiagaan Tim Rescue.

3. GAYA BAHASA RESMI BASARNAS:
   - Gunakan gaya bahasa dinas operasional Basarnas yang lugas, tegas, objektif, dan baku.
   - Cantumkan waktu kejadian dengan format WITA.
   - DILARANG menggunakan kata pembuka klise AI (seperti "Tentu saja", "Berikut adalah", "Sebagai perwira SAR").
   - DILARANG menggunakan tanda tebal (**), judul (#), atau tanda bintang daftar.
   - Langsung mulai dari kata pertama narasi.
INSTRUCTION;

        $promptParts = [
            "DATA LAPANGAN:",
            "- Informasi Awal / Catatan Kasar: " . ($draftInput ?: 'Terjadi musibah dan membutuhkan bantuan SAR.')
        ];

        if (!empty($namaOps)) $promptParts[] = "- Nama Operasi: " . $namaOps;
        if (!empty($lokasi)) $promptParts[] = "- Lokasi Kejadian: " . $lokasi;
        if (!empty($waktu)) $promptParts[] = "- Waktu Kejadian: " . $waktu . " ({$formattedDate} {$jamWita} WITA)";
        if (!empty($pelapor)) $promptParts[] = "- Pelapor: " . $pelapor;
        if (!empty($waktuLaporStr)) $promptParts[] = "- Waktu Laporan Diterima: " . $waktuLaporStr;
        if (!empty($korbanInfo)) $promptParts[] = "- Korban / POB: " . $korbanInfo;

        $promptParts[] = "";
        $promptParts[] = "Tulis narasi kronologi kejadian secara lengkap dan selesai tuntas sekarang:";
        $userPrompt = implode("\n", $promptParts);

        $response = self::callConfiguredAi($systemInstruction, $userPrompt, $plan);
        if ($response !== null && !empty($response['text'])) {
            $cleaned = self::cleanHumanizer($response['text']);
            if (!empty($cleaned)) {
                return [
                    'success' => true,
                    'text' => $cleaned,
                    'model' => $response['model'],
                    'provider' => $response['provider'],
                    'provider_label' => $response['provider_label'],
                    'key_label' => $response['key_label'],
                    'used_fallback' => $response['used_fallback'],
                ];
            }
        }

        return [
            'success' => true,
            'text' => $fallback,
            'model' => 'basarnas-fallback-template',
            'provider' => 'fallback',
            'provider_label' => 'Template Baku',
            'used_fallback' => true,
        ];
    }

    /**
     * Refine Raw Field Search Draft for Sitrep / Laporan Perkembangan (LAPBANG)
     */
    public static function refineSitrepRawDraft(string $rawDraft, string $hariKe = 'H.1', string $namaOps = '', array $extra = []): array {
        $rawDraft = trim($rawDraft);
        if (empty($rawDraft)) {
            return [
                'success' => false,
                'error' => 'Catatan / laporan mentah hasil pencarian belum diisi.'
            ];
        }

        $plan = self::resolvePlan();
        if (empty($plan)) {
            return [
                'success' => true,
                'text' => $rawDraft,
                'model' => 'raw-fallback',
                'provider' => 'fallback',
                'provider_label' => 'Template Baku',
                'notice' => 'API Key AI belum disetel di Pengaturan, mengembalikan teks mentah.'
            ];
        }

        $today = date('Y-m-d');
        $hariIni = TemplateHelper::formatTanggalIndo($today);
        $kemarin = date('Y-m-d', strtotime('-1 day'));
        $hariKemarin = TemplateHelper::formatTanggalIndo($kemarin);

        $systemInstruction = <<<INSTRUCTION
Anda adalah On-Scene Commander (OSC) / Perwira Komunikasi Resmi Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas.
Tugas Anda: Menyusun ulang dan memformalkan CATATAN MENTAH / LAPORAN LAPANGAN menjadi narasi resmi HASIL PENCARIAN & TINDAKAN LAPANGAN (SITREP / LAPBANG) yang formal, lugas, baku, dan tuntas.

KONTEKS WAKTU SAAT INI (Gunakan sebagai patokan konversi waktu relatif yang akurat):
- Hari ini / waktu saat ini: {$hariIni}
- Kemarin: {$hariKemarin}

PANDUAN UTAMA (WAJIB DIPATUHI SECARA KETAT):
1. RELEVANSI & FOKUS PADA LAPORAN MENTAH:
   - Berfokus penuh pada fakta tindakan lapangan dan hasil yang ditulis pada LAPORAN MENTAH pengguna.
   - Jangan menambahkan spekulasi baru yang bertentangan dengan isi laporan mentah.
   - Konversi penyebutan waktu informal (seperti "tadi pagi jam 7", "kemarin sore", "jam 5 sore") menjadi format resmi: "pukul 07.00 WITA", "pukul 17.00 WITA".
   - Dilarang menggunakan frasa non-baku seperti "pada kemarin", "pada tadi pagi".

2. STRUKTUR NARASI RESMI BASARNAS:
   - Mulai dengan pergerakan SRU gabungan menyisir search area / LKP sesuai rencana operasi {$hariKe}.
   - Uraikan tindakan pencarian, alutsista yang digunakan, serta kendala cuaca/medan di lapangan jika disebutkan.
   - Nyatakan hasil pencarian secara tegas (apakah hasil masih NIHIL atau korban ditemukan/dievakuasi dalam keadaan Selamat/Meninggal Dunia).
   - Nyatakan status operasi pada sore hari (contoh: pencarian sementara dihentikan dan dilanjutkan esok hari pukul 07.00 WITA, atau operasi diusulkan ditutup jika korban telah dievakuasi seluruhnya).

3. KETUNTASAN & FORMAT DINAS:
   - Tulis 1-2 paragraf yang utuh, padat, dan SELESAI TUNTAS. Dilarang terpotong di tengah kalimat.
   - Tanpa kata pembuka percakapan AI, tanpa tanda bintang tebal (**), tanpa bullet points (*).
   - Langsung mulai dari kata pertama narasi dinas.
INSTRUCTION;

        $promptParts = [
            "DATA OPERASI:",
            "- Nama Operasi: " . ($namaOps ?: 'Operasi SAR'),
            "- Hari Operasi: " . ($hariKe ?: 'H.1')
        ];

        if (!empty($extra['hasil'])) $promptParts[] = "- Status Hasil Korban: " . $extra['hasil'];
        if (!empty($extra['unsur'])) $promptParts[] = "- Unsur SAR Terlibat: " . $extra['unsur'];
        if (!empty($extra['alut'])) $promptParts[] = "- Alutsista Digunakan: " . $extra['alut'];
        if (!empty($extra['kendala'])) $promptParts[] = "- Kendala Lapangan: " . $extra['kendala'];

        $promptParts[] = "";
        $promptParts[] = "CATATAN / LAPORAN MENTAH DARI LAPANGAN:";
        $promptParts[] = "\"{$rawDraft}\"";
        $promptParts[] = "";
        $promptParts[] = "Susun ulang catatan mentah di atas menjadi narasi hasil pencarian SITREP resmi Basarnas yang formal, lugas, lengkap, dan tuntas sekarang:";

        $userPrompt = implode("\n", $promptParts);

        $response = self::callConfiguredAi($systemInstruction, $userPrompt, $plan);
        if ($response !== null && !empty($response['text'])) {
            $cleaned = self::cleanHumanizer($response['text']);
            if (!empty($cleaned)) {
                return [
                    'success' => true,
                    'text' => $cleaned,
                    'model' => $response['model'],
                    'provider' => $response['provider'],
                    'provider_label' => $response['provider_label'],
                    'key_label' => $response['key_label'],
                    'used_fallback' => $response['used_fallback'],
                ];
            }
        }

        return [
            'success' => true,
            'text' => $rawDraft,
            'model' => 'raw-fallback',
            'provider' => 'fallback',
            'provider_label' => 'Template Baku',
            'used_fallback' => true,
        ];
    }

    /**
     * Uji koneksi API Key + Model tertentu secara langsung (untuk halaman Pengaturan Admin).
     */
    public static function testConnection(string $provider, string $apiKey, string $model): array {
        $provider = strtolower(trim($provider));
        $apiKey = trim($apiKey);
        $model = AiConfigService::sanitizeModel($provider, $model);

        if (!AiConfigService::isValidProvider($provider)) {
            return ['success' => false, 'error' => 'Provider AI tidak didukung.'];
        }
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'API Key kosong atau belum disimpan.'];
        }

        $t0 = microtime(true);
        $sys = 'Anda adalah asisten uji koneksi sistem QR-SAR. Balas sangat singkat.';
        $usr = 'Balas hanya dengan kata: OK';

        $res = self::dispatchSingleProvider($provider, $apiKey, $model, $sys, $usr, 12);
        $latencyMs = (int)round((microtime(true) - $t0) * 1000);

        if (!empty($res['ok']) && !empty($res['text'])) {
            return [
                'success' => true,
                'provider' => $provider,
                'provider_label' => AiConfigService::getProviderLabel($provider),
                'model' => $res['model'] ?? $model,
                'latency_ms' => $latencyMs,
                'message' => 'Koneksi berhasil (' . AiConfigService::getProviderLabel($provider) . ' / ' . ($res['model'] ?? $model) . ', ' . $latencyMs . ' ms).',
            ];
        }

        $errDetail = (string)($res['error'] ?? 'Tidak ada respons dari server AI.');
        return [
            'success' => false,
            'provider' => $provider,
            'provider_label' => AiConfigService::getProviderLabel($provider),
            'model' => $model,
            'latency_ms' => $latencyMs,
            'error' => $errDetail,
        ];
    }

    private static function resolvePlan(): array {
        if (class_exists('AiConfigService')) {
            return AiConfigService::getExecutionPlan();
        }
        $key = self::getApiKey();
        if ($key === '') {
            return [];
        }
        return [[
            'id' => 'legacy_gemini',
            'label' => 'Google Gemini',
            'provider' => 'gemini',
            'provider_label' => 'Google Gemini',
            'api_key' => $key,
            'model' => 'gemini-2.5-flash',
            'is_default' => true,
        ]];
    }

    /**
     * Eksekusi urutan rencana provider (Default terlebih dahulu, lalu fallback otomatis bila diaktifkan).
     */
    private static function callConfiguredAi(string $systemInstruction, string $userPrompt, array $plan): ?array {
        foreach ($plan as $idx => $entry) {
            $provider = strtolower((string)($entry['provider'] ?? ''));
            $apiKey = (string)($entry['api_key'] ?? '');
            $model = (string)($entry['model'] ?? '');
            if ($apiKey === '' || $provider === '') {
                continue;
            }

            $res = self::dispatchSingleProvider($provider, $apiKey, $model, $systemInstruction, $userPrompt, 20);
            if (!empty($res['ok']) && !empty($res['text'])) {
                return [
                    'text' => (string)$res['text'],
                    'model' => (string)($res['model'] ?? $model),
                    'provider' => $provider,
                    'provider_label' => (string)($entry['provider_label'] ?? AiConfigService::getProviderLabel($provider)),
                    'key_label' => (string)($entry['label'] ?? $provider),
                    'used_fallback' => ($idx > 0),
                ];
            }

            error_log(sprintf(
                '[QR-SAR AI] Provider %s (%s / %s) failed: %s',
                $provider,
                (string)($entry['label'] ?? '-'),
                $model,
                (string)($res['error'] ?? 'unknown')
            ));
        }

        return null;
    }

    private static function dispatchSingleProvider(
        string $provider,
        string $apiKey,
        string $model,
        string $systemInstruction,
        string $userPrompt,
        int $timeout
    ): array {
        if ($provider === 'deepseek') {
            return self::callDeepSeekApi($apiKey, $model, $systemInstruction, $userPrompt, $timeout);
        }
        if ($provider === 'gemini') {
            return self::callGeminiApi($apiKey, $model, $systemInstruction, $userPrompt, $timeout);
        }
        return ['ok' => false, 'error' => "Provider '{$provider}' tidak dikenali."];
    }

    /**
     * Panggil Google Gemini REST API (x-goog-api-key header agar kunci tidak terpapar di URL).
     */
    private static function callGeminiApi(
        string $apiKey,
        string $modelName,
        string $systemInstruction,
        string $userPrompt,
        int $timeout = 20
    ): array {
        $modelName = trim($modelName) !== '' ? trim($modelName) : 'gemini-3.8-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($modelName) . ':generateContent';

        $generationConfig = [
            'temperature' => 0.25,
            'topP' => 0.85,
            'maxOutputTokens' => 2048,
        ];

        // Konfigurasi thinking sesuai generasi seri Gemini
        if (str_starts_with($modelName, 'gemini-2.5') || str_starts_with($modelName, 'gemini-2.0')) {
            $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
        } elseif (str_starts_with($modelName, 'gemini-3.')) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => 'low'];
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => $generationConfig,
        ];

        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ];

        $resp = self::postJson($url, $headers, $payload, $timeout);
        if ($resp['http_code'] === 200 && $resp['body'] !== '') {
            $data = json_decode($resp['body'], true);
            $parts = $data['candidates'][0]['content']['parts'] ?? [];
            $chunks = [];
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    if (is_array($part) && !empty($part['text']) && empty($part['thought'])) {
                        $chunks[] = (string)$part['text'];
                    }
                }
            }
            $text = trim(implode("\n", $chunks));
            if ($text !== '') {
                return [
                    'ok' => true,
                    'text' => $text,
                    'model' => (string)($data['modelVersion'] ?? $modelName),
                ];
            }
            return ['ok' => false, 'error' => 'Respons Gemini kosong (tanpa kandidat teks).'];
        }

        return [
            'ok' => false,
            'error' => self::extractApiError('Gemini', $resp),
        ];
    }

    /**
     * Panggil DeepSeek Chat Completions API (kompatibel OpenAI).
     */
    private static function callDeepSeekApi(
        string $apiKey,
        string $modelName,
        string $systemInstruction,
        string $userPrompt,
        int $timeout = 20
    ): array {
        $modelName = trim($modelName) !== '' ? trim($modelName) : 'deepseek-chat';
        $url = 'https://api.deepseek.com/chat/completions';

        $payload = [
            'model' => $modelName,
            'messages' => [
                ['role' => 'system', 'content' => $systemInstruction],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => 2048,
            'stream' => false,
        ];

        // Model reasoner (R1) dapat menolak/mengabaikan temperature & top_p
        if (!str_contains(strtolower($modelName), 'reasoner')) {
            $payload['temperature'] = 0.25;
            $payload['top_p'] = 0.85;
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $resp = self::postJson($url, $headers, $payload, $timeout);
        if ($resp['http_code'] === 200 && $resp['body'] !== '') {
            $data = json_decode($resp['body'], true);
            $text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
            if ($text !== '') {
                return [
                    'ok' => true,
                    'text' => $text,
                    'model' => (string)($data['model'] ?? $modelName),
                ];
            }
            return ['ok' => false, 'error' => 'Respons DeepSeek kosong.'];
        }

        return [
            'ok' => false,
            'error' => self::extractApiError('DeepSeek', $resp),
        ];
    }

    /**
     * HTTP POST JSON transport via cURL (atau mock client saat pengujian otomatis).
     *
     * @return array{http_code:int, body:string, error:string}
     */
    private static function postJson(string $url, array $headers, array $payload, int $timeout): array {
        if (self::$httpClient !== null) {
            $res = (self::$httpClient)($url, $headers, $payload, $timeout);
            return [
                'http_code' => (int)($res['http_code'] ?? 0),
                'body' => (string)($res['body'] ?? ''),
                'error' => (string)($res['error'] ?? ''),
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $res = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        unset($ch);

        return [
            'http_code' => $httpCode,
            'body' => is_string($res) ? $res : '',
            'error' => $curlErr,
        ];
    }

    private static function extractApiError(string $providerName, array $resp): string {
        $httpCode = (int)($resp['http_code'] ?? 0);
        $curlErr = trim((string)($resp['error'] ?? ''));
        $body = trim((string)($resp['body'] ?? ''));

        if ($httpCode === 0 && $curlErr !== '') {
            return "Gagal terhubung ke {$providerName}: {$curlErr}";
        }

        $parsed = $body !== '' ? json_decode($body, true) : null;
        $apiMsg = '';
        if (is_array($parsed)) {
            if (isset($parsed['error']['message']) && is_string($parsed['error']['message'])) {
                $apiMsg = trim($parsed['error']['message']);
            } elseif (isset($parsed['message']) && is_string($parsed['message'])) {
                $apiMsg = trim($parsed['message']);
            }
        }

        if ($httpCode === 401 || $httpCode === 403) {
            return "{$providerName} menolak API Key (HTTP {$httpCode})" . ($apiMsg !== '' ? ': ' . mb_substr($apiMsg, 0, 140, 'UTF-8') : '.');
        }
        if ($httpCode === 429) {
            return "Kuota {$providerName} terlampaui / Rate Limit (HTTP 429).";
        }
        if ($httpCode === 404) {
            return "Model {$providerName} tidak ditemukan (HTTP 404)" . ($apiMsg !== '' ? ': ' . mb_substr($apiMsg, 0, 140, 'UTF-8') : '.');
        }
        return "{$providerName} mengembalikan HTTP {$httpCode}" . ($apiMsg !== '' ? ': ' . mb_substr($apiMsg, 0, 160, 'UTF-8') : '.');
    }
}
