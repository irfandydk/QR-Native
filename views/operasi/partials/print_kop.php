<?php
/**
 * QR-SAR - Kop Surat Resmi Basarnas (dipakai bersama seluruh lembar cetak operasi)
 * Membutuhkan: $customLogo
 */
?>
<div class="kop-surat-container w-full mb-3 text-black" style="font-family: Arial, Helvetica, sans-serif !important;">
    <table class="kop-table" style="width: 100% !important; border: none !important; border-collapse: collapse !important; table-layout: auto !important; margin: 0 !important; padding: 0 !important;">
        <tr>
            <!-- Kolom Kiri: Logo Basarnas Sirkular (Tanpa Teks BASARNAS di Bawahnya) -->
            <td style="width: 80px; vertical-align: middle; text-align: center; border: none !important; padding: 0 !important;">
                <div style="width: 75px; height: 75px; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                    <?php if (!empty($customLogo)): ?>
                        <img src="<?= htmlspecialchars($customLogo) ?>" alt="Logo Basarnas" style="width: 100%; height: 100%; object-fit: contain;">
                    <?php else: ?>
                        <img src="<?= BASE_URL ?>/assets/images/logo_basarnas.svg" alt="Logo Basarnas" style="width: 100%; height: 100%; object-fit: contain;">
                    <?php endif; ?>
                </div>
            </td>

            <!-- Kolom Tengah: Teks Kop Surat Lengkap Rata Tengah -->
            <td style="vertical-align: middle; text-align: center; border: none !important; padding: 0 4px !important;">
                <div style="font-family: Arial, Helvetica, sans-serif !important; font-size: 13pt !important; font-weight: bold !important; text-transform: uppercase !important; line-height: 1.25; color: #000000; letter-spacing: 0.02em;">
                    BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
                </div>
                <div style="font-family: Arial, Helvetica, sans-serif !important; font-size: 14pt !important; font-weight: bold !important; text-transform: uppercase !important; line-height: 1.25; color: #000000; margin-top: 1px; letter-spacing: 0.02em;">
                    KANTOR PENCARIAN DAN PERTOLONGAN
                </div>
                <div style="font-family: Arial, Helvetica, sans-serif !important; font-size: 16pt !important; font-weight: 900 !important; text-transform: uppercase !important; line-height: 1.2; color: #000000; margin-top: 1px; letter-spacing: 0.04em;">
                    KENDARI
                </div>
                <div style="font-family: Arial, Helvetica, sans-serif !important; font-size: 8.5pt !important; font-weight: normal !important; line-height: 1.35; color: #000000; margin-top: 4px; white-space: nowrap !important;">
                    Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sulawesi Tenggara 93116
                </div>
                <div style="font-family: Arial, Helvetica, sans-serif !important; font-size: 8.5pt !important; font-weight: normal !important; line-height: 1.35; color: #000000; white-space: nowrap !important;">
                    Telepon: (0401) 3196557 &bull; Faksimile: (0401) 3196558 &bull; Emergency Call: 115
                </div>
                <div style="font-family: Arial, Helvetica, sans-serif !important; font-size: 8.5pt !important; font-style: italic !important; line-height: 1.35; color: #000000; white-space: nowrap !important;">
                    Website: kendari.basarnas.go.id &bull; Email: sar304kendari@gmail.com
                </div>
            </td>

            <!-- Kolom Kanan: Spacer Penyeimbang (Sama Lebar dengan Kolom Logo agar Teks Tepat di Tengah Halaman) -->
            <td style="width: 80px; vertical-align: middle; border: none !important; padding: 0 !important;"></td>
        </tr>
    </table>

    <!-- GARIS GANDA KOP DINAS (Double Horizontal Line) -->
    <div style="width: 100% !important; margin-top: 6px; margin-bottom: 12px; border-bottom: 2.5px solid #000000; padding-bottom: 1.5px;">
        <div style="border-bottom: 1px solid #000000;"></div>
    </div>
</div>
