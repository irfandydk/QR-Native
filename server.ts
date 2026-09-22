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

      const settings = db.getAppSettings();
      const user = {
        id: 'USR-ADMIN',
        nama: settings.admin_nama || 'AMIRUDDIN AS, S.Sos.',
        nip: settings.admin_nip || '197405101998031002',
        role: 'ADMIN',
        roleTitle: 'Administrator SAR',
        pangkat: settings.admin_pangkat || 'PEMBINA (IV/a)',
        jabatan: settings.admin_jabatan || 'Kepala Kantor / Administrator Utama KPP Kendari',
        email: settings.admin_email || 'irfandydk@gmail.com',
        username: settings.admin_username || 'admin',
        no_hp: settings.admin_no_hp || '0811-4091-115',
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

  app.get('/api/auth/profile', (req, res) => {
    try {
      const settings = db.getAppSettings();
      res.json({
        success: true,
        data: {
          id: 'USR-ADMIN',
          nama: settings.admin_nama || 'AMIRUDDIN AS, S.Sos.',
          nip: settings.admin_nip || '197405101998031002',
          role: 'ADMIN',
          roleTitle: 'Administrator SAR',
          pangkat: settings.admin_pangkat || 'PEMBINA (IV/a)',
          jabatan: settings.admin_jabatan || 'Kepala Kantor / Administrator Utama KPP Kendari',
          email: settings.admin_email || 'irfandydk@gmail.com',
          username: settings.admin_username || 'admin',
          no_hp: settings.admin_no_hp || '0811-4091-115',
          smtp_host: settings.smtp_host || '',
          smtp_port: settings.smtp_port || 587,
          smtp_user: settings.smtp_user || '',
          smtp_from: settings.smtp_from || '',
        },
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/auth/profile', (req, res) => {
    try {
      const { nama, nip, jabatan, pangkat, email, username, no_hp, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from } = req.body;
      const updateData: any = {};
      if (nama !== undefined) updateData.admin_nama = String(nama).trim();
      if (nip !== undefined) updateData.admin_nip = String(nip).trim();
      if (jabatan !== undefined) updateData.admin_jabatan = String(jabatan).trim();
      if (pangkat !== undefined) updateData.admin_pangkat = String(pangkat).trim();
      if (email !== undefined) updateData.admin_email = String(email).trim();
      if (username !== undefined) updateData.admin_username = String(username).trim();
      if (no_hp !== undefined) updateData.admin_no_hp = String(no_hp).trim();
      if (smtp_host !== undefined) updateData.smtp_host = String(smtp_host).trim();
      if (smtp_port !== undefined) updateData.smtp_port = Number(smtp_port) || 587;
      if (smtp_user !== undefined) updateData.smtp_user = String(smtp_user).trim();
      if (smtp_pass !== undefined && smtp_pass !== '••••••••') updateData.smtp_pass = String(smtp_pass).trim();
      if (smtp_from !== undefined) updateData.smtp_from = String(smtp_from).trim();

      const updated = db.updateAppSettings(updateData);

      res.json({
        success: true,
        message: 'Data profil administrator berhasil diperbarui.',
        data: {
          id: 'USR-ADMIN',
          nama: updated.admin_nama,
          nip: updated.admin_nip,
          jabatan: updated.admin_jabatan,
          pangkat: updated.admin_pangkat,
          email: updated.admin_email,
          username: updated.admin_username,
          no_hp: updated.admin_no_hp,
          role: 'ADMIN',
          roleTitle: 'Administrator SAR',
        },
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

  // Reset password to default 'Kendari2026'
  app.post('/api/auth/reset-default', (req, res) => {
    try {
      const result = db.resetToDefaultPassword();
      res.json({
        success: true,
        message: 'Kata sandi administrator berhasil dikembalikan ke standar sistem "Kendari2026".',
        password: result.password,
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Forgot password endpoint: directly resets password to default 'Kendari2026'
  app.post('/api/auth/forgot-password', (req, res) => {
    try {
      const result = db.resetToDefaultPassword();
      res.json({
        success: true,
        message: 'Kata sandi administrator telah direset ke kata sandi standar "Kendari2026".',
        password: result.password,
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
      const settings = db.getAppSettings();
      res.json({
        success: true,
        data: {
          username: settings.admin_username || 'admin',
          email: settings.admin_email || 'irfandydk@gmail.com',
          nama: settings.admin_nama || 'AMIRUDDIN AS, S.Sos.',
          nip: settings.admin_nip || '197405101998031002',
          jabatan: settings.admin_jabatan || 'Kepala Kantor / Administrator Utama KPP Kendari',
          pangkat: settings.admin_pangkat || 'PEMBINA (IV/a)',
          no_hp: settings.admin_no_hp || '0811-4091-115',
        },
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // --- Rekapitulasi Laporan & Monitoring Analytics Endpoint ---
  app.get(['/api/laporan/stats', '/api/monitoring/stats'], (req, res) => {
    try {
      const operasiList = db.getAllOperasi();

      let totalPob = 0;
      let totalSelamat = 0;
      let totalMeninggal = 0;
      let totalHilang = 0; // DP / H
      let totalPersonilPotensi = 0;
      let totalHariOperasi = 0;

      const potensiByUnsurMap: Record<string, { nama: string; totalPersonil: number; countOperasi: number }> = {};
      const jenisKejadianMap: Record<string, number> = {};

      const operasiDetails = operasiList.map(op => {
        // Korban per operasi
        const korbanList = op.korban || [];
        const s = korbanList.filter(k => k.status_korban === 'S').length;
        const md = korbanList.filter(k => k.status_korban === 'MD').length;
        const dp = korbanList.filter(k => k.status_korban === 'DP' || k.status_korban === 'H').length;
        const opPob = korbanList.length > 0 ? korbanList.length : (op.jumlah_pob || 0);

        totalPob += opPob;
        totalSelamat += s;
        totalMeninggal += md;
        totalHilang += dp;

        // Potensi per operasi
        const unsurList = op.detail_unsur || [];
        const personilOp = unsurList.reduce((sum, u) => sum + (Number(u.jumlah_personil) || 0), 0);
        totalPersonilPotensi += personilOp;

        unsurList.forEach(u => {
          const key = (u.nama_unsur || 'Unsur Gabungan').trim();
          if (!potensiByUnsurMap[key]) {
            potensiByUnsurMap[key] = { nama: key, totalPersonil: 0, countOperasi: 0 };
          }
          potensiByUnsurMap[key].totalPersonil += (Number(u.jumlah_personil) || 0);
          potensiByUnsurMap[key].countOperasi += 1;
        });

        // Durasi hari per operasi
        let hariKeCount = 1;
        if (op.renops && op.renops.length > 0) {
          op.renops.forEach(r => {
            const match = (r.hari_ke || '').match(/H\.?(\d+)/i);
            if (match) {
              hariKeCount = Math.max(hariKeCount, parseInt(match[1], 10));
            }
          });
        }
        if (op.laporan_harian && op.laporan_harian.length > 0) {
          op.laporan_harian.forEach(l => {
            const match = (l.hari_ke || '').match(/H\.?(\d+)/i);
            if (match) {
              hariKeCount = Math.max(hariKeCount, parseInt(match[1], 10));
            }
          });
        }
        if (op.waktu_lapor || op.waktu_berangkat) {
          const start = new Date(op.waktu_lapor || op.waktu_berangkat).getTime();
          const end = op.waktu_selesai ? new Date(op.waktu_selesai).getTime() : Date.now();
          if (!isNaN(start) && end > start) {
            const daysFromDates = Math.max(1, Math.ceil((end - start) / (1000 * 60 * 60 * 24)));
            hariKeCount = Math.max(hariKeCount, daysFromDates);
          }
        }
        totalHariOperasi += hariKeCount;

        // Categorize incident
        const namaLower = (op.nama_operasi || '').toLowerCase();
        let kategori = 'Lain-lain';
        if (namaLower.includes('kapal') || namaLower.includes('perahu') || namaLower.includes('nelayan') || namaLower.includes('longboat') || namaLower.includes('kmm')) {
          kategori = 'Kecelakaan Kapal (KMM)';
        } else if (namaLower.includes('tenggelam') || namaLower.includes('sungai') || namaLower.includes('pantai') || namaLower.includes('danau') || namaLower.includes('membahayakan')) {
          kategori = 'Kondisi Membahayakan Manusia (KMM)';
        } else if (namaLower.includes('hutan') || namaLower.includes('gunung') || namaLower.includes('hilang') || namaLower.includes('tersesat')) {
          kategori = 'Orang Hilang / Tersesat';
        } else if (namaLower.includes('banjir') || namaLower.includes('longsor') || namaLower.includes('gempa') || namaLower.includes('bencana')) {
          kategori = 'Bencana Alam (KND)';
        } else if (namaLower.includes('pesawat') || namaLower.includes('udara')) {
          kategori = 'Kecelakaan Pesawat Udara (KPU)';
        }
        jenisKejadianMap[kategori] = (jenisKejadianMap[kategori] || 0) + 1;

        return {
          id_operasi: op.id_operasi,
          nama_operasi: op.nama_operasi,
          status: op.status,
          lokasi_kejadian: op.lokasi_kejadian,
          koordinat: op.koordinat,
          waktu_lapor: op.waktu_lapor,
          waktu_berangkat: op.waktu_berangkat,
          waktu_selesai: op.waktu_selesai,
          durasi_hari: hariKeCount,
          kategori,
          korban_stats: {
            total: opPob,
            selamat: s,
            meninggal: md,
            hilang: dp,
          },
          potensi_stats: {
            total_personil: personilOp,
            jumlah_unsur: unsurList.length,
            unsur_list: unsurList.map(u => ({ nama: u.nama_unsur, personil: u.jumlah_personil })),
          },
          alut_count: (op.detail_alut || []).length,
          renops_count: (op.renops || []).length,
          laporan_count: (op.laporan_harian || []).length,
        };
      });

      const totalOperasi = operasiList.length;
      const operasiAktif = operasiList.filter(o => o.status === 'OPEN').length;
      const operasiSelesai = operasiList.filter(o => o.status === 'CLOSED').length;
      const rataRataHari = totalOperasi > 0 ? Number((totalHariOperasi / totalOperasi).toFixed(1)) : 0;
      const rateSelamat = totalPob > 0 ? Number(((totalSelamat / totalPob) * 100).toFixed(1)) : 0;

      // Top potensi sorted by total personil
      const potensiTopList = Object.values(potensiByUnsurMap)
        .sort((a, b) => b.totalPersonil - a.totalPersonil);

      res.json({
        success: true,
        data: {
          timestamp: new Date().toISOString(),
          operasi_summary: {
            total: totalOperasi,
            aktif: operasiAktif,
            selesai: operasiSelesai,
            jenis_kejadian: jenisKejadianMap,
          },
          korban_summary: {
            total_pob: totalPob,
            selamat: totalSelamat,
            meninggal: totalMeninggal,
            hilang: totalHilang,
            persentase_selamat: rateSelamat,
          },
          potensi_summary: {
            total_personil: totalPersonilPotensi,
            total_entitas_unsur: Object.keys(potensiByUnsurMap).length,
            top_unsur: potensiTopList,
          },
          durasi_summary: {
            total_hari: totalHariOperasi,
            rata_rata_hari: rataRataHari,
            max_hari: Math.max(0, ...operasiDetails.map(o => o.durasi_hari)),
          },
          operasi_details: operasiDetails,
        },
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Google Maps Configuration Endpoint
  app.get('/api/config/maps', (req, res) => {
    const apiKey = process.env.GOOGLE_MAPS_API_KEY || process.env.VITE_GOOGLE_MAPS_API_KEY || 'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc';
    res.json({
      success: true,
      apiKey,
    });
  });

  // --- SAR Units (Kantor SAR, POS SAR, Unit Siaga SAR) Endpoints ---
  app.get('/api/sar-units', (req, res) => {
    try {
      const units = db.getSarUnits();
      res.json({ success: true, data: units });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/sar-units', (req, res) => {
    try {
      const { nama_unit, jenis_unit, wilayah, latitude, longitude, koordinat_dms, alamat, no_telp, is_default } = req.body;
      if (!nama_unit || !jenis_unit || latitude === undefined || longitude === undefined) {
        return res.status(400).json({ success: false, error: 'Nama unit, jenis unit, latitude dan longitude wajib diisi' });
      }
      const unit = db.addSarUnit({
        nama_unit: nama_unit.trim(),
        jenis_unit,
        wilayah: (wilayah || '').trim(),
        latitude: parseFloat(latitude),
        longitude: parseFloat(longitude),
        koordinat_dms: koordinat_dms || '',
        alamat: alamat || '',
        no_telp: no_telp || '',
        is_default: !!is_default,
      });
      res.json({ success: true, data: unit });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.put('/api/sar-units/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const updated = db.updateSarUnit(id, req.body);
      if (!updated) {
        return res.status(404).json({ success: false, error: 'Unit SAR tidak ditemukan' });
      }
      res.json({ success: true, data: updated });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.delete('/api/sar-units/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const ok = db.deleteSarUnit(id);
      if (!ok) {
        return res.status(404).json({ success: false, error: 'Unit SAR tidak ditemukan' });
      }
      res.json({ success: true, message: 'Unit SAR berhasil dihapus' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.post('/api/sar-units/:id/set-default', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const ok = db.setDefaultSarUnit(id);
      if (!ok) {
        return res.status(404).json({ success: false, error: 'Unit SAR tidak ditemukan' });
      }
      res.json({ success: true, message: 'Pangkalan default berhasil diset' });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Calculate route distance via Google Directions / Distance Matrix proxy or fallback
  app.post('/api/maps/calculate-route', async (req, res) => {
    try {
      const { originLat, originLng, destLat, destLng, mode = 'driving' } = req.body;
      const apiKey = process.env.GOOGLE_MAPS_API_KEY || process.env.VITE_GOOGLE_MAPS_API_KEY || 'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc';

      if (originLat === undefined || originLng === undefined || destLat === undefined || destLng === undefined) {
        return res.status(400).json({ success: false, error: 'Koordinat asal dan tujuan wajib disertakan' });
      }

      // Try Google Maps Directions API
      try {
        const url = `https://maps.googleapis.com/maps/api/directions/json?origin=${originLat},${originLng}&destination=${destLat},${destLng}&mode=${mode}&key=${apiKey}`;
        const fetchRes = await fetch(url);
        const data = await fetchRes.json();

        if (data.status === 'OK' && data.routes && data.routes.length > 0) {
          const route = data.routes[0];
          const leg = route.legs[0];
          const distanceMeters = leg.distance.value;
          const distanceKm = Math.round((distanceMeters / 1000) * 10) / 10;
          const distanceNm = Math.round((distanceKm * 0.539957) * 10) / 10;
          const durationSeconds = leg.duration.value;
          const durationMinutes = Math.round(durationSeconds / 60);

          return res.json({
            success: true,
            source: 'google_directions',
            distanceKm,
            distanceNm,
            distanceText: leg.distance.text,
            durationMinutes,
            durationText: leg.duration.text,
            startAddress: leg.start_address,
            endAddress: leg.end_address,
            polyline: route.overview_polyline?.points,
          });
        }
      } catch (gErr) {
        console.warn('Google Directions API call failed, falling back to calculation:', gErr);
      }

      // Fallback: Haversine distance with road curvature factor (approx 1.35x for road network)
      const R = 6371;
      const dLat = ((destLat - originLat) * Math.PI) / 180;
      const dLon = ((destLng - originLng) * Math.PI) / 180;
      const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos((originLat * Math.PI) / 180) *
          Math.cos((destLat * Math.PI) / 180) *
          Math.sin(dLon / 2) *
          Math.sin(dLon / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      const straightKm = R * c;
      const roadFactor = 1.32; // Road network winding factor
      const roadKm = Math.round((straightKm * roadFactor) * 10) / 10;
      const roadNm = Math.round((roadKm * 0.539957) * 10) / 10;
      const avgSpeedKmh = 45; // Rescue vehicle average speed
      const durationMins = Math.round((roadKm / avgSpeedKmh) * 60);

      const hours = Math.floor(durationMins / 60);
      const mins = durationMins % 60;
      const durationText = hours > 0 ? `${hours} Jam ${mins} Menit` : `${mins} Menit`;

      res.json({
        success: true,
        source: 'estimated_road_network',
        distanceKm: roadKm,
        distanceNm: roadNm,
        distanceText: `${roadKm} km`,
        durationMinutes: durationMins,
        durationText,
        straightKm: Math.round(straightKm * 10) / 10,
      });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Reverse Geocode endpoint (Google Maps API with Nominatim / Local Gazetteer Fallback)
  app.post('/api/maps/reverse-geocode', async (req, res) => {
    try {
      const { lat, lng } = req.body;
      if (lat === undefined || lng === undefined) {
        return res.status(400).json({ success: false, error: 'Latitude dan longitude wajib diisi' });
      }

      const apiKey = process.env.GOOGLE_MAPS_API_KEY || process.env.VITE_GOOGLE_MAPS_API_KEY;

      // 1. Try Google Geocoding API if key is present
      if (apiKey) {
        try {
          const gUrl = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${apiKey}`;
          const gRes = await fetch(gUrl);
          const gData = await gRes.json();
          if (gData.status === 'OK' && gData.results && gData.results.length > 0) {
            return res.json({
              success: true,
              address: gData.results[0].formatted_address,
              source: 'google',
            });
          }
        } catch {
          // silently move to fallback
        }
      }

      // 2. Fallback to OpenStreetMap Nominatim
      try {
        const osmUrl = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&zoom=14&addressdetails=1`;
        const osmRes = await fetch(osmUrl, {
          headers: {
            'User-Agent': 'BasarnasSARApp/1.0 (rescuer-support@basarnas.go.id)',
          },
        });
        if (osmRes.ok) {
          const osmData = await osmRes.json();
          if (osmData && (osmData.display_name || osmData.name)) {
            const addr = osmData.display_name || osmData.name;
            return res.json({
              success: true,
              address: addr,
              source: 'osm',
            });
          }
        }
      } catch {
        // silently move to fallback
      }

      // 3. Fallback: Format coordinates descriptor
      const dms = `${Math.abs(lat).toFixed(4)}° ${lat >= 0 ? 'N' : 'S'}, ${Math.abs(lng).toFixed(4)}° ${lng >= 0 ? 'E' : 'W'}`;
      res.json({
        success: true,
        address: `Wilayah Koordinat (${dms})`,
        source: 'coordinates',
      });
    } catch (err: any) {
      res.json({ success: true, address: 'Lokasi Titik Koordinat LKP', source: 'fallback' });
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

  app.put('/api/renops/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const { renops, srus, detail_unsur, detail_alut } = req.body;

      if (!renops) {
        return res.status(400).json({ success: false, error: 'Data renops wajib diisi' });
      }

      const updated = db.updateRenops(
        id,
        renops,
        srus,
        detail_unsur,
        detail_alut
      );

      if (!updated) {
        return res.status(404).json({ success: false, error: 'Renops tidak ditemukan' });
      }

      res.json({ success: true, data: updated });
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

  app.put('/api/laporan/:id', (req, res) => {
    try {
      const id = parseInt(req.params.id, 10);
      const { laporan, updated_korban, detail_unsur, detail_alut } = req.body;

      if (!laporan) {
        return res.status(400).json({ success: false, error: 'Data laporan wajib diisi' });
      }

      const updated = db.updateLaporanHarian(
        id,
        laporan,
        updated_korban,
        detail_unsur,
        detail_alut
      );

      if (!updated) {
        return res.status(404).json({ success: false, error: 'Laporan tidak ditemukan' });
      }

      res.json({ success: true, data: updated });
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

  // Native JSON Database Export (Full App-Compatible Backup)
  app.get(['/api/database/export', '/api/backup/export'], (req, res) => {
    try {
      const fullBackup = db.getFullDatabase();
      const dateStr = new Date().toISOString().split('T')[0];
      res.setHeader('Content-Type', 'application/json');
      res.setHeader('Content-Disposition', `attachment; filename="BACKUP_DATABASE_SAR_KENDARI_${dateStr}.json"`);
      res.json(fullBackup);
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Native JSON Database Import & Restore
  app.post(['/api/database/import', '/api/backup/import'], (req, res) => {
    try {
      const payload = req.body;
      if (!payload) {
        return res.status(400).json({ success: false, error: 'Data cadangan tidak boleh kosong.' });
      }

      const result = db.restoreFullDatabase(payload);
      if (!result.success) {
        return res.status(400).json({ success: false, error: result.error });
      }

      res.json({
        success: true,
        message: 'Basis data berhasil dipulihkan secara penuh.',
        stats: result.stats,
        settings: result.settings,
      });
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
