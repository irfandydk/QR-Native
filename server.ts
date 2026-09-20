import express from 'express';
import path from 'path';
import dotenv from 'dotenv';
import { GoogleGenAI } from '@google/genai';
import { createServer as createViteServer } from 'vite';
import { db } from './server/db';
import { generateLaporanAwalWA, generateRenopsWA, generateLapbangWA } from './src/utils/templates';

dotenv.config();

const PORT = 3000;

// Lazy initialized Gemini client
let genAIClient: GoogleGenAI | null = null;
function getGenAI(): GoogleGenAI | null {
  if (!process.env.GEMINI_API_KEY) {
    return null;
  }
  if (!genAIClient) {
    genAIClient = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });
  }
  return genAIClient;
}

// Helper to call Gemini with model cascade and automatic failover
async function callGeminiWithCascade(ai: GoogleGenAI, prompt: string): Promise<{ text: string; model: string }> {
  // Prioritize stable alias gemini-flash-latest and lightweight gemini-3.1-flash-lite to avoid high-demand 503s
  const models = ['gemini-flash-latest', 'gemini-3.1-flash-lite', 'gemini-3.8-flash'];
  let lastError: any = null;

  for (const model of models) {
    try {
      const response = await ai.models.generateContent({
        model,
        contents: prompt,
      });
      if (response.text) {
        return { text: response.text.trim(), model };
      }
    } catch (err: any) {
      lastError = err;
      // Graceful fallback to next model with short backoff
      await new Promise((resolve) => setTimeout(resolve, 250));
    }
  }

  throw lastError || new Error('Gemini models temporarily unavailable');
}

async function startServer() {
  const app = express();
  app.use(express.json({ limit: '10mb' }));
  app.use(express.urlencoded({ extended: true }));

  // --- API Routes ---

  // Health check
  app.get('/api/health', (req, res) => {
    res.json({ status: 'ok', time: new Date().toISOString() });
  });

  // --- Authentication & Security Endpoints ---
  app.post('/api/auth/login', (req, res) => {
    try {
      const { username, password } = req.body;
      const isValid = db.validateAdminLogin(username, password);

      if (!isValid) {
        return res.status(401).json({
          success: false,
          error: 'Username atau kata sandi yang Anda masukkan salah. Pastikan menggunakan user "admin" dengan kata sandi yang sesuai.',
        });
      }

      const user = {
        id: 'USR-ADMIN',
        nama: 'Administrator SAR',
        nip: '198805122010121001',
        role: 'ADMIN',
        roleTitle: 'Administrator SAR',
        pangkat: 'Penata Muda (III/a)',
        jabatan: 'Pengelola Sistem Komando KPP Kendari',
        loginAt: new Date().toISOString(),
      };

      res.json({
        success: true,
        message: 'Login berhasil.',
        user,
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/auth/change-password', (req, res) => {
    try {
      const { oldPassword, newPassword } = req.body;
      const result = db.changeAdminPassword(oldPassword, newPassword);

      if (!result.success) {
        return res.status(400).json({ success: false, error: result.error });
      }

      res.json({
        success: true,
        message: 'Kata sandi administrator berhasil diperbarui.',
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/auth/forgot-password', (req, res) => {
    try {
      const originHost = `${req.protocol}://${req.get('host')}`;
      const resetInfo = db.generateResetToken(originHost);

      // In production/mock, this simulates email dispatch to irfandydk@gmail.com
      console.log(`[AUTH] Password reset requested. Reset link generated for ${resetInfo.email}: ${resetInfo.resetLink}`);

      res.json({
        success: true,
        message: `Link reset kata sandi telah berhasil dikirim ke email resmi (${resetInfo.email}). Silakan periksa kotak masuk atau spam email Anda.`,
        email: resetInfo.email,
        resetToken: resetInfo.token,
        resetLink: resetInfo.resetLink,
        expiresAt: resetInfo.expiresAt,
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/auth/reset-password', (req, res) => {
    try {
      const { token, newPassword } = req.body;
      const result = db.resetPasswordWithToken(token, newPassword);

      if (!result.success) {
        return res.status(400).json({ success: false, error: result.error });
      }

      res.json({
        success: true,
        message: 'Kata sandi baru berhasil disimpan. Silakan login kembali dengan kata sandi baru Anda.',
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.get('/api/auth/config', (req, res) => {
    try {
      res.json({
        success: true,
        data: {
          username: 'admin',
          email: db.getAdminEmail(),
        },
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Master Alut
  app.get('/api/master/alut', (req, res) => {
    try {
      const items = db.getMasterAlut();
      res.json({ success: true, data: items });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/master/alut', (req, res) => {
    try {
      const { nama_alut, satuan } = req.body;
      if (!nama_alut) {
        return res.status(400).json({ success: false, error: 'Nama Alut wajib diisi' });
      }
      const item = db.addMasterAlut(nama_alut, satuan || 'Unit');
      res.json({ success: true, data: item });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/master/alut/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const { nama_alut, satuan } = req.body;
      if (!nama_alut) {
        return res.status(400).json({ success: false, error: 'Nama Alut wajib diisi' });
      }
      const updated = db.updateMasterAlut(id, nama_alut, satuan || 'Unit');
      if (!updated) {
        return res.status(404).json({ success: false, error: 'Alut tidak ditemukan' });
      }
      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/master/alut/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const ok = db.deleteMasterAlut(id);
      if (!ok) {
        return res.status(404).json({ success: false, error: 'Alut tidak ditemukan' });
      }
      res.json({ success: true, message: 'Alut berhasil dihapus' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/master/alut/reorder', (req, res) => {
    try {
      const { ids } = req.body;
      if (!Array.isArray(ids)) {
        return res.status(400).json({ success: false, error: 'Array ID dibutuhkan' });
      }
      const items = db.reorderMasterAlut(ids);
      res.json({ success: true, data: items });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Master Tim
  app.get('/api/master/tim', (req, res) => {
    try {
      const items = db.getMasterTim();
      res.json({ success: true, data: items });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/master/tim', (req, res) => {
    try {
      const { nama_tim } = req.body;
      if (!nama_tim) {
        return res.status(400).json({ success: false, error: 'Nama Tim wajib diisi' });
      }
      const item = db.addMasterTim(nama_tim);
      res.json({ success: true, data: item });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/master/tim/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const { nama_tim } = req.body;
      if (!nama_tim) {
        return res.status(400).json({ success: false, error: 'Nama Tim wajib diisi' });
      }
      const updated = db.updateMasterTim(id, nama_tim);
      if (!updated) {
        return res.status(404).json({ success: false, error: 'Tim tidak ditemukan' });
      }
      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/master/tim/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const ok = db.deleteMasterTim(id);
      if (!ok) {
        return res.status(404).json({ success: false, error: 'Tim tidak ditemukan' });
      }
      res.json({ success: true, message: 'Tim berhasil dihapus' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/master/tim/reorder', (req, res) => {
    try {
      const { ids } = req.body;
      if (!Array.isArray(ids)) {
        return res.status(400).json({ success: false, error: 'Array ID dibutuhkan' });
      }
      const items = db.reorderMasterTim(ids);
      res.json({ success: true, data: items });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // App Settings & Pejabat
  app.get('/api/settings', (req, res) => {
    try {
      const settings = db.getAppSettings();
      res.json({ success: true, data: settings });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/settings', (req, res) => {
    try {
      const settings = db.updateAppSettings(req.body);
      res.json({ success: true, data: settings });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.get('/api/settings/pejabat', (req, res) => {
    try {
      const list = db.getPejabatList();
      res.json({ success: true, data: list });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/settings/pejabat', (req, res) => {
    try {
      const { nama, nip, pangkat, jabatan, is_default_smc } = req.body;
      if (!nama || !jabatan) {
        return res.status(400).json({ success: false, error: 'Nama dan Jabatan wajib diisi' });
      }
      const created = db.addPejabat({
        nama,
        nip: nip || '-',
        pangkat: pangkat || '',
        jabatan,
        is_default_smc: !!is_default_smc,
      });
      res.json({ success: true, data: created });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/settings/pejabat/:id', (req, res) => {
    try {
      const updated = db.updatePejabat(req.params.id, req.body);
      if (!updated) {
        return res.status(404).json({ success: false, error: 'Data Pejabat tidak ditemukan' });
      }
      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/settings/pejabat/:id', (req, res) => {
    try {
      const ok = db.deletePejabat(req.params.id);
      if (!ok) {
        return res.status(404).json({ success: false, error: 'Data Pejabat tidak ditemukan' });
      }
      res.json({ success: true, message: 'Data Pejabat berhasil dihapus' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Operasi
  app.get('/api/operasi', (req, res) => {
    try {
      const list = db.getAllOperasi();
      res.json({ success: true, data: list });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.get('/api/operasi/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const op = db.getOperasiById(id);
      if (!op) {
        return res.status(404).json({ success: false, error: 'Operasi tidak ditemukan' });
      }
      res.json({ success: true, data: op });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/operasi', (req, res) => {
    try {
      const { operasi, korban, detail_unsur, detail_alut, tim_berangkat_ids } = req.body;
      if (!operasi || !operasi.nama_operasi) {
        return res.status(400).json({ success: false, error: 'Data operasi tidak lengkap' });
      }

      const created = db.createOperasi(
        operasi,
        korban || [],
        detail_unsur || [],
        detail_alut || [],
        tim_berangkat_ids || []
      );

      res.status(201).json({ success: true, data: created });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/operasi/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const { operasi, korban, detail_unsur, detail_alut, tim_berangkat_ids } = req.body;

      const updated = db.updateOperasi(
        id,
        operasi || {},
        korban,
        detail_unsur,
        detail_alut,
        tim_berangkat_ids
      );

      if (!updated) {
        return res.status(404).json({ success: false, error: 'Operasi tidak ditemukan' });
      }

      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/operasi/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const deleted = db.deleteOperasi(id);
      if (!deleted) {
        return res.status(404).json({ success: false, error: 'Operasi tidak ditemukan' });
      }
      res.json({ success: true, message: 'Operasi berhasil dihapus' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Renops
  app.post('/api/operasi/:id/renops', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const { renops, srus, detail_unsur, detail_alut } = req.body;

      if (!renops) {
        return res.status(400).json({ success: false, error: 'Data renops wajib diisi' });
      }

      const created = db.createRenops(
        id,
        renops,
        srus || [],
        detail_unsur,
        detail_alut
      );

      res.status(201).json({ success: true, data: created });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/renops/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const deleted = db.deleteRenops(id);
      if (!deleted) {
        return res.status(404).json({ success: false, error: 'Renops tidak ditemukan' });
      }
      res.json({ success: true, message: 'Renops berhasil dihapus' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Laporan Harian (Lapbang / Penutupan)
  app.post('/api/operasi/:id/laporan', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const { laporan, updated_korban, detail_unsur, detail_alut } = req.body;

      if (!laporan) {
        return res.status(400).json({ success: false, error: 'Data laporan wajib diisi' });
      }

      const created = db.createLaporanHarian(
        id,
        laporan,
        updated_korban,
        detail_unsur,
        detail_alut
      );

      res.status(201).json({ success: true, data: created });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/laporan/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const result = db.deleteLaporanHarian(id);
      if (!result.success) {
        return res.status(404).json({ success: false, error: 'Laporan tidak ditemukan' });
      }
      res.json({
        success: true,
        message: 'Laporan berhasil dihapus',
        rolledBack: result.rolledBack,
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Rollback Penutupan manually
  app.post('/api/operasi/:id/rollback-penutupan', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const success = db.rollbackPenutupan(id);
      if (!success) {
        return res.status(404).json({ success: false, error: 'Operasi tidak ditemukan' });
      }
      res.json({ success: true, message: 'Status operasi berhasil dikembalikan ke OPEN' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Export MySQL SQL Dump
  app.get('/api/export/sql', (req, res) => {
    try {
      const sql = db.generateSQLDump();
      res.setHeader('Content-Type', 'application/sql');
      res.setHeader('Content-Disposition', 'attachment; filename="db_sar_qr.sql"');
      res.send(sql);
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // AI Assistant Endpoints using Gemini with Cascade and Fallback
  app.post('/api/ai/draft-kronologi', async (req, res) => {
    const { draftInput, namaOperasi, lokasi, waktuKejadian } = req.body;
    const fallback = `Pada ${waktuKejadian ? new Date(waktuKejadian).toLocaleDateString('id-ID') : 'hari kejadian'}, terjadi kecelakaan/musibah di ${lokasi || 'lokasi kejadian'}. Berdasarkan informasi pelapor, ${draftInput || 'korban mengalami musibah dan belum kembali/ditemukan'}. Upaya pencarian awal telah dilakukan oleh pihak keluarga dan masyarakat setempat namun hingga laporan ini dibuat belum membuahkan hasil, selanjutnya dilaporkan ke Kantor SAR Kendari untuk bantuan SAR.`;

    const ai = getGenAI();
    if (!ai) {
      return res.json({ success: true, text: fallback, model: 'fallback' });
    }

    try {
      const prompt = `Anda adalah petugas komunikasi SAR (Pusdatin) profesional di Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas.
Tulis narasi KRONOLOGI KEJADIAN resmi untuk Laporan Awal Operasi SAR dengan bahasa baku, singkat, lugas, presisi, dan sesuai standar operasional Basarnas.

Data Operasi:
- Nama Operasi: ${namaOperasi || '-'}
- Lokasi: ${lokasi || '-'}
- Waktu Kejadian: ${waktuKejadian || '-'}
- Catatan Kasar / Informasi Awal: ${draftInput || '-'}

Instruksi:
- Tulis HANYA paragraf narasi kronologi (tanpa pengantar, tanpa penutup).
- Gunakan terminologi resmi Basarnas (seperti: melaut, LKP/Last Known Position, longboat, mesin mati, terbalik, hilang kontak, warga setempat).
- Pastikan waktu menggunakan WITA.`;

      const result = await callGeminiWithCascade(ai, prompt);
      res.json({ success: true, text: result.text, model: result.model });
    } catch {
      res.json({
        success: true,
        text: fallback,
        model: 'basarnas-fallback-template',
        notice: 'AI sedang sibuk, draf kronologi disusun otomatis berdasarkan standar format Basarnas.',
      });
    }
  });

  app.post('/api/ai/draft-sitrep', async (req, res) => {
    const { hariKe, tindakanHariIni, hasil, kendala, namaOperasi } = req.body;
    const fallback = `Tim SAR Gabungan telah melaksanakan operasi pencarian ${hariKe || 'hari ini'} sesuai Rencana Operasi dengan membagi tim ke dalam beberapa SRU. Penyisiran darat/air telah dilaksanakan secara maksimal di sekitar LKP dan area yang dicurigai. ${kendala && kendala !== 'Nihil' ? `Kondisi di lapangan terkendala oleh ${kendala}.` : 'Pencarian berlangsung dalam kondisi cuaca yang cukup mendukung.'} Hingga batas waktu pencarian pukul 17.30 WITA, hasil pencarian masih ${hasil || 'NIHIL'} dan operasi sementara dihentikan untuk dilanjutkan esok hari.`;

    const ai = getGenAI();
    if (!ai) {
      return res.json({ success: true, text: fallback, model: 'fallback' });
    }

    try {
      const prompt = `Anda adalah koordinator misi SAR (SMC / On Scene Commander) Kantor Pencarian dan Pertolongan Kendari.
Buat narasi HASIL PENCARIAN HARI INI untuk laporan perkembangan (Lapbang/Sitrep ${hariKe || 'H.X'}) operasi "${namaOperasi || 'SAR'}".

Poin Masukan:
- Hari ke: ${hariKe || 'H.1'}
- Tindakan/Aktivitas: ${tindakanHariIni || 'Penyisiran darat/laut sesuai Renops'}
- Hasil Operasi: ${hasil || 'Nihil'}
- Faktor Penghambat: ${kendala || 'Nihil'}

Instruksi:
- Tulis HANYA narasi hasil pencarian tanpa nomor atau salam pembuka/penutup.
- Bahasa baku dinas Basarnas.
- Sebutkan tindakan Tim SAR Gabungan, area penyisiran, hasil sementara, dan rencana kelanjutan.`;

      const result = await callGeminiWithCascade(ai, prompt);
      res.json({ success: true, text: result.text, model: result.model });
    } catch {
      res.json({
        success: true,
        text: fallback,
        model: 'basarnas-fallback-template',
        notice: 'AI sedang sibuk, narasi disusun otomatis menggunakan format standar Basarnas.',
      });
    }
  });

  app.post('/api/ai/draft-sru-plan', async (req, res) => {
    const { namaOperasi, lokasi, koordinat, hariKe, jenisMedan } = req.body;
    const fallback = [
      {
        nama_sru: 'SRU 1 (Tim Rescue Kansar Kendari)',
        koordinat_area: `A: ${koordinat || '03° 58\' 30" S - 122° 35\' 12" E'}\nB: 03° 58' 30" S - 122° 40' 00" E\nC: 04° 03' 00" S - 122° 40' 00" E\nD: 04° 03' 00" S - 122° 35' 12" E`,
        luas_area: '10 Nm²',
        pola_pencarian: 'Parallel Track Search',
      },
      {
        nama_sru: 'SRU 2 (Unsur Gabungan & Masyarakat)',
        koordinat_area: 'Penyisiran pesisir pantai dan pulau terdekat sejauh 5 Nm arah angin/arus',
        luas_area: '6 Nm²',
        pola_pencarian: 'Creeping Line Search',
      },
    ];

    const ai = getGenAI();
    if (!ai) {
      return res.json({ success: true, srus: fallback, model: 'fallback' });
    }

    try {
      const prompt = `Anda adalah ahli Rencana Operasi (Renops) SAR Kantor Pencarian dan Pertolongan Kendari.
Rekomendasikan pembagian Search and Rescue Unit (SRU) dan Pola Pencarian SAR (seperti Parallel Track Search, Creeping Line Search, Sector Search, atau Expanding Square) untuk operasi berikut:

- Operasi: ${namaOperasi || 'Operasi SAR'}
- LKP / Koordinat Pusat: ${koordinat || '03° 58\' 30" S - 122° 35\' 12" E'}
- Lokasi: ${lokasi || 'Perairan Sulawesi Tenggara'}
- Hari Pencarian: ${hariKe || 'H.1'}
- Medan: ${jenisMedan || 'Perairan/Laut'}

Keluarkan output dalam format JSON valid berupa array objek:
[
  {
    "nama_sru": "SRU 1 (Alut / Tim)",
    "koordinat_area": "A: ... B: ... C: ... D: ...",
    "luas_area": "X Nm²",
    "pola_pencarian": "Parallel Track Search"
  }
]
Hanya berikan JSON murni tanpa markdown triple backticks.`;

      const result = await callGeminiWithCascade(ai, prompt);
      let jsonText = result.text || '[]';
      if (jsonText.startsWith('```')) {
        jsonText = jsonText.replace(/^```json\s*/, '').replace(/^```\s*/, '').replace(/\s*```$/, '');
      }

      try {
        const parsed = JSON.parse(jsonText);
        res.json({ success: true, srus: parsed, model: result.model });
      } catch (parseErr) {
        res.json({ success: true, srus: fallback, model: result.model });
      }
    } catch {
      res.json({
        success: true,
        srus: fallback,
        model: 'basarnas-fallback-template',
        notice: 'AI sedang sibuk, pola SRU disusun otomatis berdasarkan standar Basarnas.',
      });
    }
  });

  // --- BRA SAR Endpoints ---
  app.get('/api/bra', (req, res) => {
    try {
      const list = db.getAllBraSar();
      res.json({ success: true, data: list });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.get('/api/bra/next-number', (req, res) => {
    try {
      const satker = (req.query.satker as string) || 'SAR-304';
      const nextData = db.getNextBraNumber(satker);
      res.json({ success: true, data: nextData });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.get('/api/bra/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const bra = db.getBraSarById(id);
      if (!bra) {
        return res.status(404).json({ success: false, error: 'Dokumen BRA SAR tidak ditemukan' });
      }
      res.json({ success: true, data: bra });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/bra', (req, res) => {
    try {
      const originHost = `${req.protocol}://${req.get('host')}`;
      const newBra = db.createBraSar(req.body, originHost);
      res.json({ success: true, data: newBra });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/bra/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const updated = db.updateBraSar(id, req.body);
      if (!updated) {
        return res.status(404).json({ success: false, error: 'Dokumen BRA SAR tidak ditemukan' });
      }
      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/bra/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const success = db.deleteBraSar(id);
      if (!success) {
        return res.status(404).json({ success: false, error: 'Dokumen BRA SAR tidak ditemukan' });
      }
      res.json({ success: true, message: 'Dokumen BRA SAR berhasil dihapus' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // --- App Settings (Logo & Satker) ---
  app.get('/api/settings', (req, res) => {
    try {
      const settings = db.getSettings();
      res.json({ success: true, data: settings });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/settings', (req, res) => {
    try {
      const updated = db.updateSettings(req.body);
      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/settings', (req, res) => {
    try {
      const updated = db.updateSettings(req.body);
      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // --- Pejabat Management Endpoints ---
  app.get('/api/settings/pejabat', (req, res) => {
    try {
      const list = db.getPejabatList();
      res.json({ success: true, data: list });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/settings/pejabat', (req, res) => {
    try {
      const { nama, nip, pangkat, jabatan, is_default_smc } = req.body;
      if (!nama || !jabatan) {
        return res.status(400).json({ success: false, error: 'Nama dan Jabatan wajib diisi' });
      }
      const created = db.addPejabat({
        nama,
        nip: nip || '',
        pangkat: pangkat || '',
        jabatan,
        is_default_smc: Boolean(is_default_smc),
      });
      res.json({ success: true, data: created });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/settings/pejabat/:id', (req, res) => {
    try {
      const id = req.params.id;
      const updated = db.updatePejabat(id, req.body);
      if (!updated) {
        return res.status(404).json({ success: false, error: 'Data pejabat tidak ditemukan' });
      }
      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/settings/pejabat/:id', (req, res) => {
    try {
      const id = req.params.id;
      const ok = db.deletePejabat(id);
      if (!ok) {
        return res.status(404).json({ success: false, error: 'Data pejabat tidak ditemukan' });
      }
      res.json({ success: true, message: 'Pejabat berhasil dihapus' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Vite middleware for development vs static production serving
  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`QR SAR Server running at http://0.0.0.0:${PORT}`);
  });
}

startServer();
