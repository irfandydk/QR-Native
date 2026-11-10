<?php
/**
 * QR-SAR - AI Assistant Helper (Gemini 2.5 Flash with Humanizer & Basarnas Fallback Engine)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class AiHelper {
    /**
     * Retrieve Gemini API Key from multiple sources:
     * 1. Environment variable GEMINI_API_KEY
     * 2. $_ENV['GEMINI_API_KEY']
     * 3. Database AppSettings (column gemini_api_key)
     */
    public static function getApiKey(): string {
        $key = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
        if (!empty($key)) return trim((string)$key);

        try {
            $db = getDB();
            $stmt = $db->query("SELECT gemini_api_key FROM AppSettings WHERE id = 1 LIMIT 1");
            $row = $stmt->fetch();
            if ($row && !empty($row['gemini_api_key'])) {
                return trim((string)$row['gemini_api_key']);
            }
        } catch (Throwable) {}

        return '';
    }

    /**
     * Clean and humanize AI output to eliminate robotic AI cliches
     */
    public static function cleanHumanizer(string $text): string {
        $text = trim($text);

        // Remove code blocks if AI wrapped output in markdown blocks
        $text = preg_replace('/^```(?:json|markdown|text)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        // Remove robotic conversational AI preambles
        $preambles = [
            '/^(?:tentu|tentu saja|berikut|berikut ini|halo|baik|siap)[,\s]+(?:berikut(?:\s+adalah)?)?[^\n:]*[:\n]\s*/i',
            '/^(?:kronologi(?:nya)?(?:\s+adalah)?|narasi(?:\s+kejadian)?|draf(?:\s+sitrep)?)[^\n:]*[:\n]\s*/i',
            '/^sebagai\s+(?:petugas|perwira|anggota)\s+sar[^\n:]*[:\n]\s*/i',
        ];
        foreach ($preambles as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        // Remove conversational sign-offs (only if formatted as trailing footnote or disclaimer)
        $signOffs = [
            '/\s*(?:semoga\s+membantu|demikian\s+laporan\s+ini\s+dibuat\s+oleh\s+ai|ada\s+lagi\s+yang\s+bisa\s+saya\s+bantu\??)\s*$/i',
            '/\n+(?:\*\*|\*|#+)?\s*(?:catatan|note|disclaimer)\s*:\s*.*$/is'
        ];
        foreach ($signOffs as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        // Remove excessive markdown formatting asterisks to keep natural plain operational text
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text);
        $text = preg_replace('/^#+\s+/m', '', $text);

        // Safeguard: Automatically fix any ungrammatical 'Pada kemarin' phrase into formal Indonesian date
        $kemarinDate = TemplateHelper::formatTanggalIndo(date('Y-m-d', strtotime('-1 day')));
        $text = preg_replace('/\bPada\s+kemarin\b/i', "Pada {$kemarinDate}", $text);

        // Trim quotes and whitespace
        $text = trim($text, " \t\n\r\0\x0B\"'");

        return trim($text);
    }

    /**
     * Asisten AI Kronologi: Focus PURELY on raw report restructuring and formalizing.
     * Intelligently resolves relative time ("kemarin" -> "Minggu, 11 Oktober 2026", "tadi pagi", etc.) into formal dates.
     * Styles output into dignified, formal, clear, and non-wordy Basarnas operational narrative using Gemini 3.8 Flash.
     */
    public static function refineRawDraft(string $rawDraft): array {
        $rawDraft = trim($rawDraft);
        if (empty($rawDraft)) {
            return [
                'success' => false,
                'error' => 'Catatan / laporan mentah belum diisi.'
            ];
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return [
                'success' => true,
                'text' => $rawDraft,
                'model' => 'raw-fallback',
                'notice' => 'API Key tidak disetel, mengembalikan teks mentah.'
            ];
        }

        // Relative anchor dates in WITA (Asia/Makassar)
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

        $prompt = <<<PROMPT
{$systemInstruction}

LAPORAN MENTAH DARI PELAPOR:
"{$rawDraft}"

Susun ulang laporan mentah di atas menjadi narasi kronologi dinas resmi Basarnas yang formal, lugas, lengkap, dan tuntas sekarang:
PROMPT;

        $response = self::callGemini($apiKey, $prompt);
        if ($response !== null && !empty($response['text'])) {
            $cleaned = self::cleanHumanizer($response['text']);
            if (!empty($cleaned)) {
                return ['success' => true, 'text' => $cleaned, 'model' => $response['model'] ?? 'gemini-2.5-flash'];
            }
        }

        return [
            'success' => true,
            'text' => $rawDraft,
            'model' => 'raw-fallback'
        ];
    }

    /**
     * Draft Kronologi Kejadian with Basarnas Humanizer Style
     */
    public static function draftKronologi(string $draftInput, string $namaOps = '', string $lokasi = '', string $waktu = '', array $extra = []): array {
        $formattedDate = !empty($waktu) ? TemplateHelper::formatTanggalIndo($waktu) : 'hari kejadian';
        $jamWita = !empty($waktu) ? TemplateHelper::formatJamWita($waktu) : 'pagi hari';

        $pelapor = $extra['pelapor'] ?? '';
        $waktuLapor = $extra['waktuLapor'] ?? '';
        $waktuLaporStr = !empty($waktuLapor) ? (TemplateHelper::formatTanggalIndo($waktuLapor) . ' pkl ' . TemplateHelper::formatJamWita($waktuLapor) . ' WITA') : '';
        $korbanInfo = $extra['korbanInfo'] ?? '';

        $fallback = "Pada {$formattedDate} sekitar pukul {$jamWita} WITA, telah terjadi musibah di {$lokasi}. Berdasarkan informasi pelapor, {$draftInput}. Upaya pencarian awal sempat dilakukan oleh pihak keluarga dan masyarakat setempat namun hingga informasi ini diterima hasil masih nihil, selanjutnya dilaporkan ke Kantor Pencarian dan Pertolongan (KPP) Kendari untuk bantuan SAR.";

        $apiKey = self::getApiKey();

        if (empty($apiKey)) {
            return [
                'success' => true,
                'text' => $fallback,
                'model' => 'basarnas-official-template',
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
            $systemInstruction,
            "",
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
        $prompt = implode("\n", $promptParts);

        $response = self::callGemini($apiKey, $prompt);
        if ($response !== null && !empty($response['text'])) {
            $cleaned = self::cleanHumanizer($response['text']);
            if (!empty($cleaned)) {
                return ['success' => true, 'text' => $cleaned, 'model' => $response['model'] ?? 'gemini-2.5-flash'];
            }
        }

        return [
            'success' => true,
            'text' => $fallback,
            'model' => 'basarnas-fallback-template'
        ];
    }

    /**
     * Refine Raw Field Search Draft for Sitrep / Laporan Perkembangan
     */
    public static function refineSitrepRawDraft(string $rawDraft, string $hariKe = 'H.1', string $namaOps = '', array $extra = []): array {
        $rawDraft = trim($rawDraft);
        if (empty($rawDraft)) {
            return [
                'success' => false,
                'error' => 'Catatan / laporan mentah hasil pencarian belum diisi.'
            ];
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return [
                'success' => true,
                'text' => $rawDraft,
                'model' => 'raw-fallback',
                'notice' => 'API Key tidak disetel, mengembalikan teks mentah.'
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
            $systemInstruction,
            "",
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

        $prompt = implode("\n", $promptParts);

        $response = self::callGemini($apiKey, $prompt);
        if ($response !== null && !empty($response['text'])) {
            $cleaned = self::cleanHumanizer($response['text']);
            if (!empty($cleaned)) {
                return ['success' => true, 'text' => $cleaned, 'model' => $response['model'] ?? 'gemini-2.5-flash'];
            }
        }

        return [
            'success' => true,
            'text' => $rawDraft,
            'model' => 'raw-fallback'
        ];
    }

    /**
     * Call Gemini API with generation configuration (Prioritizing Gemini 3.8 Flash with thinkingBudget 0)
     */
    private static function callGemini(string $apiKey, string $prompt): ?array {
        $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];

        foreach ($models as $modelName) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . urlencode($apiKey);
            
            $generationConfig = [
                'temperature' => 0.25,
                'topP' => 0.85,
                'maxOutputTokens' => 2048,
            ];

            // thinkingConfig is only supported on Gemini 2.0/2.5 series
            if (str_starts_with($modelName, 'gemini-2.')) {
                $generationConfig['thinkingConfig'] = [
                    'thinkingBudget' => 0
                ];
            }

            $payload = [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => $generationConfig
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            unset($ch);

            if ($httpCode === 200 && $res) {
                $data = json_decode((string)$res, true);
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (!empty($text)) {
                    $usedModel = $data['modelVersion'] ?? $modelName;
                    return [
                        'text' => trim((string)$text),
                        'model' => $usedModel
                    ];
                }
            } else {
                error_log(sprintf("[QR-SAR AI] Model %s failed (HTTP %d): %s | %s", $modelName, $httpCode, $curlErr, is_string($res) ? substr($res, 0, 200) : ''));
            }
        }

        return null;
    }
}
