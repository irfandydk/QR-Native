import { AppSettings } from '../src/types.js';

export interface SendResetEmailParams {
  toEmail: string;
  adminName: string;
  resetToken: string;
  resetLink: string;
  expiresAt: string;
  settings?: AppSettings;
}

export interface EmailSendResult {
  success: boolean;
  sent: boolean;
  message: string;
  previewUrl?: string;
  error?: string;
}

/**
 * Creates a transporter dynamically if nodemailer is present in runtime
 */
async function createTransporter(settings?: AppSettings): Promise<any> {
  const host = process.env.SMTP_HOST || settings?.smtp_host;
  const port = Number(process.env.SMTP_PORT || settings?.smtp_port || 587);
  const user = process.env.SMTP_USER || settings?.smtp_user;
  const pass = process.env.SMTP_PASS || settings?.smtp_pass;
  const isSecure = port === 465;

  if (host && user && pass) {
    try {
      // Dynamic import to prevent build failure if nodemailer isn't installed
      const nodemailer = await (Function('return import("nodemailer")')().catch(() => null));
      if (!nodemailer || !nodemailer.createTransport) return null;
      return nodemailer.createTransport({
        host,
        port,
        secure: isSecure,
        auth: {
          user,
          pass,
        },
        tls: {
          rejectUnauthorized: false,
        },
      });
    } catch {
      return null;
    }
  }

  return null;
}

/**
 * Generates an official HTML email template for password reset.
 */
function buildResetEmailHtml(params: SendResetEmailParams, satkerName: string): string {
  const { adminName, resetToken, resetLink, expiresAt } = params;
  const expiryFormatted = new Date(expiresAt).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    timeZoneName: 'short',
  });

  return `
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="utf-8">
    <title>Reset Kata Sandi Akun Administrator</title>
  </head>
  <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
      <!-- Header Banner -->
      <div style="background: linear-gradient(135deg, #ea580c, #c2410c); padding: 28px 24px; text-align: center;">
        <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">
          BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
        </h1>
        <p style="color: #fed7aa; margin: 6px 0 0 0; font-size: 13px; font-weight: 600;">
          ${satkerName} • SISTEM QUICK REPORT SAR (QR SAR)
        </p>
      </div>

      <!-- Main Body -->
      <div style="padding: 28px 24px; color: #e2e8f0; line-height: 1.6;">
        <p style="font-size: 15px; margin-top: 0;">Yth. <strong>${adminName}</strong>,</p>
        <p style="font-size: 14px; color: #cbd5e1;">
          Kami menerima permohonan pemulihan kata sandi untuk akun Administrator Utama Sistem Komando QR SAR KPP Kendari.
        </p>

        <!-- Security Token Box -->
        <div style="background-color: #090d16; border: 1px solid #ea580c; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0;">
          <div style="font-size: 11px; font-weight: bold; color: #ea580c; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">
            TOKEN PEMULIHAN RESMI (BERLAKU 1 JAM)
          </div>
          <div style="font-family: monospace; font-size: 24px; font-weight: bold; color: #ffffff; letter-spacing: 3px; background: #1e293b; padding: 10px 16px; border-radius: 8px; display: inline-block;">
            ${resetToken}
          </div>
          <div style="font-size: 11px; color: #94a3b8; margin-top: 10px;">
            Berlaku hingga pukul ${expiryFormatted} WITA
          </div>
        </div>

        <!-- Direct Reset Button -->
        <div style="text-align: center; margin: 28px 0;">
          <a href="${resetLink}" style="background-color: #ea580c; color: #ffffff; text-decoration: none; padding: 14px 28px; font-size: 14px; font-weight: bold; border-radius: 10px; display: inline-block; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.4);">
            Atur Ulang Kata Sandi Sekarang &rarr;
          </a>
        </div>

        <p style="font-size: 12px; color: #94a3b8; margin-bottom: 4px;">
          Atau salin tautan berikut ke peramban (browser) Anda:
        </p>
        <div style="background-color: #090d16; padding: 10px; border-radius: 8px; font-family: monospace; font-size: 11px; word-break: break-all; color: #38bdf8;">
          ${resetLink}
        </div>

        <hr style="border: 0; border-top: 1px solid #334155; margin: 24px 0;">

        <div style="font-size: 11px; color: #94a3b8;">
          <strong style="color: #fca5a5;">Penting:</strong> Jika Anda tidak mengajukan permohonan pemulihan ini, abaikan email ini atau segera hubungi Administrator Jaringan KPP Kendari. Jangan pernah membagikan token ini kepada siapapun.
        </div>
      </div>

      <!-- Footer -->
      <div style="background-color: #090d16; padding: 16px 24px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #1e293b;">
        KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI<br>
        Emergency Call: 115 • Siaga 24 Jam
      </div>
    </div>
  </body>
  </html>
  `;
}

/**
 * Sends a password reset email using nodemailer (SMTP) or generates local verification link.
 */
export async function sendPasswordResetEmail(params: SendResetEmailParams): Promise<EmailSendResult> {
  const { toEmail, resetToken, resetLink, settings } = params;
  const satkerName = settings?.satker_nama || 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI';
  const fromAddress = process.env.SMTP_FROM || settings?.smtp_from || `"Pusdatin SAR Kendari" <no-reply@basarnas.go.id>`;

  const transporter = await createTransporter(settings);

  if (transporter) {
    try {
      const info = await transporter.sendMail({
        from: fromAddress,
        to: toEmail,
        subject: `[BASARNAS KENDARI] Permohonan Reset Kata Sandi Administrator SAR (${resetToken})`,
        html: buildResetEmailHtml(params, satkerName),
        text: `Yth. ${params.adminName},\n\nToken reset kata sandi Anda adalah: ${resetToken}\n\nTautan pemulihan: ${resetLink}\n\nBerlaku selama 1 jam.\n\nKPP KENDARI - Quick Report SAR`,
      });

      console.log(`[EMAIL] Reset email successfully sent via SMTP to ${toEmail}: ${info.messageId}`);
      return {
        success: true,
        sent: true,
        message: `Email petunjuk reset kata sandi telah berhasil dikirim ke server email ${toEmail}.`,
      };
    } catch (err: any) {
      console.error(`[EMAIL] Failed to send via SMTP:`, err.message);
      // Fallback: still provide token & link so user is never locked out
      return {
        success: true,
        sent: false,
        message: `Koneksi SMTP belum berhasil (${err.message}). Namun token keamanan (${resetToken}) dan tautan reset telah dibuat secara resmi.`,
        error: err.message,
      };
    }
  }

  // If no SMTP configured, log cleanly and return valid token & instructions
  console.log(`[EMAIL-NOTICE] No SMTP server configured. Direct reset token generated for ${toEmail}: ${resetToken}`);
  return {
    success: true,
    sent: false,
    message: `Token reset kata sandi (${resetToken}) telah siap. Anda dapat mengatur ulang kata sandi langsung dengan token ini atau tautan reset yang disediakan.`,
  };
}
